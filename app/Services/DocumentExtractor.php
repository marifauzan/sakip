<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

/**
 * Ekstraksi teks dari PDF & DOCX, lalu chunking menjadi potongan
 * dengan metadata halaman/bagian. Tanpa dependensi berisiko
 * (DOCX diekstrak langsung dari ZIP+XML, bukan PHPWord yang
 * membawa phpoffice/math dengan kerentanan XXE).
 */
class DocumentExtractor
{
    /** Ukuran target per chunk (dalam kata). */
    private const CHUNK_WORDS = 700;

    public function extract(Document $document): void
    {
        $document->update(['status' => 'extracting']);

        try {
            $path = storage_path('app/private/'.$document->file_path);

            $pages = match ($document->file_mime) {
                'application/pdf' => $this->extractPdf($path),
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword' => $this->extractDocx($path),
                'text/plain' => $this->extractText($path),
                default => throw new \RuntimeException('Tipe file tidak didukung: '.$document->file_mime),
            };

            $this->storeChunks($document, $pages);

            $document->update([
                'status' => 'extracted',
                'extract_error' => null,
            ]);
        } catch (\Throwable $e) {
            $document->update([
                'status' => 'failed',
                'extract_error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }
    }

    /**
     * @return array<int, string>  [pageNumber => text]
     */
    private function extractPdf(string $path): array
    {
        $parser = new PdfParser;
        $pdf = $parser->parseFile($path);

        $pages = [];
        foreach ($pdf->getPages() as $i => $page) {
            $pages[$i + 1] = trim((string) $page->getText());
        }

        return $pages;
    }

    /**
     * DOCX adalah ZIP berisi XML. Ekstrak teks dari word/document.xml.
     *
     * @return array<int, string>
     */
    private function extractDocx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Gagal membuka DOCX.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('Struktur DOCX tidak valid (word/document.xml tidak ditemukan).');
        }

        // XXE aman secara default sejak libxml 2.9 (PHP 8): entity eksternal
        // tidak dimuat. Gunakan LIBXML_NONET untuk menonaktifkan akses jaringan.
        $dom = new \DOMDocument;
        $dom->loadXML($xml, LIBXML_NONET);

        $parts = [];
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Ambil paragraf BUKAN di dalam tabel (//w:p yang bukan anak //w:tc).
        $paragraphs = $xpath->query('//w:p[not(ancestor::w:tc)]');
        foreach ($paragraphs as $p) {
            $texts = $xpath->query('.//w:t', $p);
            $line = '';
            foreach ($texts as $t) {
                $line .= $t->textContent;
            }
            $line = trim($line);
            if ($line !== '') {
                $parts[] = $line;
            }
        }

        // Tabel dibaca sebagai baris terpisah (diberi prefix "TABEL:").
        $rows = $xpath->query('//w:tbl/w:tr');
        foreach ($rows as $row) {
            $cells = $xpath->query('./w:tc', $row);
            $cellsText = [];
            foreach ($cells as $cell) {
                $cellParts = $xpath->query('.//w:t', $cell);
                $cellText = '';
                foreach ($cellParts as $t) {
                    $cellText .= $t->textContent;
                }
                $cellsText[] = trim($cellText);
            }
            $parts[] = 'TABEL: '.implode(' | ', $cellsText);
        }

        $full = implode("\n", $parts);
        // DOCX tidak punya halaman tegas; satu "halaman" = seluruh teks.
        return [1 => $full];
    }

    /**
     * @return array<int, string>
     */
    private function extractText(string $path): array
    {
        return [1 => (string) file_get_contents($path)];
    }

    /**
     * @param array<int, string> $pages
     */
    private function storeChunks(Document $document, array $pages): void
    {
        $document->chunks()->delete();

        $chunkIndex = 0;
        foreach ($pages as $page => $text) {
            // Chunk per paragraf/baris agar struktur & judul bab terjaga.
            $lines = preg_split('/\r?\n/', trim($text)) ?: [];
            $buffer = [];
            $bufferWords = 0;

            $flush = function () use ($document, $page, &$chunkIndex, &$buffer, &$bufferWords) {
                if ($buffer === []) {
                    return;
                }
                $content = implode("\n", $buffer);
                DocumentChunk::create([
                    'document_id' => $document->id,
                    'page' => $page,
                    'section' => $this->detectSection($content),
                    'content' => $content,
                    'chunk_index' => $chunkIndex++,
                ]);
                $buffer = [];
                $bufferWords = 0;
            };

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $lineWords = count(preg_split('/\s+/u', $line) ?: []);

                if ($bufferWords > 0 && ($bufferWords + $lineWords) > self::CHUNK_WORDS) {
                    $flush();
                }

                $buffer[] = $line;
                $bufferWords += $lineWords;
            }

            $flush();
        }
    }

    /**
     * Deteksi judul bab secara heuristik (baris pendek + huruf kapital).
     */
    private function detectSection(string $content): ?string
    {
        $lines = preg_split('/\r?\n/', $content) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (mb_strlen($line) >= 3 && mb_strlen($line) <= 80
                && preg_match('/^(BAB|I{1,3}\.|[A-Z][A-Z ]{2,})/u', $line)) {
                return mb_substr($line, 0, 120);
            }
        }

        return null;
    }
}
