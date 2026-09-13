<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Storage;
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
            $path = Storage::disk('private')->path($document->file_path);

            if (! file_exists($path)) {
                throw new \RuntimeException('File dokumen fisik tidak ditemukan di penyimpanan server.');
            }

            $ext = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
            $mime = strtolower((string) $document->file_mime);

            $pages = match (true) {
                $mime === 'application/pdf'
                    || in_array($mime, ['application/x-pdf', 'application/acrobat', 'applications/vnd.pdf', 'text/pdf'], true)
                    || $ext === 'pdf' => $this->extractPdf($path),

                $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    || $ext === 'docx'
                    || ($ext === 'doc' && in_array($mime, ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) => $this->extractDocx($path),

                $ext === 'doc' || $mime === 'application/msword' => $this->extractLegacyDocOrDocx($path),

                $mime === 'text/plain' || $ext === 'txt' => $this->extractText($path),

                default => throw new \RuntimeException('Tipe file tidak didukung: '.($document->file_mime ?: $ext)),
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
     * @return array<int, string> [pageNumber => text]
     */
    private function extractPdf(string $path): array
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($path);

            $pages = [];
            foreach ($pdf->getPages() as $i => $page) {
                $pages[$i + 1] = trim((string) $page->getText());
            }

            $allText = implode('', $pages);
            if (trim($allText) === '') {
                throw new \RuntimeException('PDF tidak memuat teks digital (kemungkinan berupa pindaian gambar tanpa OCR). Harap gunakan dokumen dengan teks digital atau format DOCX.');
            }

            return $pages;
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException) {
                throw $e;
            }

            throw new \RuntimeException('Gagal membaca dokumen PDF: '.$e->getMessage(), 0, $e);
        }
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
            throw new \RuntimeException('Gagal membuka file DOCX. Pastikan file tidak rusak atau terenkripsi password.');
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

        if (trim($full) === '') {
            throw new \RuntimeException('Dokumen DOCX kosong atau tidak memiliki konten teks.');
        }

        // DOCX tidak punya halaman tegas; satu "halaman" = seluruh teks.
        return [1 => $full];
    }

    /**
     * Cek apakah .doc sebenarnya adalah DOCX yang diberi ekstensi .doc, atau file biner OLE .doc lama.
     *
     * @return array<int, string>
     */
    private function extractLegacyDocOrDocx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) === true) {
            $hasXml = $zip->locateName('word/document.xml') !== false;
            $zip->close();
            if ($hasXml) {
                return $this->extractDocx($path);
            }
        }

        throw new \RuntimeException('Format .doc biner lama (Word 97-2003) tidak didukung untuk ekstraksi otomatis. Harap simpan ulang dokumen Anda ke format .docx atau .pdf, lalu unggah kembali.');
    }

    /**
     * @return array<int, string>
     */
    private function extractText(string $path): array
    {
        return [1 => (string) file_get_contents($path)];
    }

    /**
     * @param  array<int, string>  $pages
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
