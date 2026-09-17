<?php

namespace App\Services;

use App\Models\KinerjaTree;
use App\Models\Node;
use App\Models\NodeLink;
use Illuminate\Support\Collection;

/**
 * Ekspor rancangan perjenjangan kinerja ke format Markdown / JSON / CSV.
 */
class KinerjaExporter
{
    /** Ekspor ke Markdown (hierarki + tabel indikator). */
    public function toMarkdown(KinerjaTree $tree): string
    {
        $tree->load(['nodes.indicators', 'links']);

        $lines = [];
        $lines[] = '# '.$tree->name;
        $lines[] = '';
        $lines[] = 'Periode: '.(($tree->period_start && $tree->period_end)
            ? $tree->period_start.'–'.$tree->period_end
            : '—');
        $lines[] = 'Status: '.$tree->status;
        $lines[] = '';

        // Root nodes = node yang tidak punya parent link.
        $roots = $this->roots($tree);
        foreach ($roots as $root) {
            $this->renderNode($tree, $root, 0, $lines);
        }

        // Tabel indikator
        $lines[] = '';
        $lines[] = '## Indikator';
        $lines[] = '';
        $lines[] = '| Sasaran | Indikator | Satuan | Arah | Baseline | Target |';
        $lines[] = '|---|---|---|---|---|---|';
        foreach ($tree->nodes as $node) {
            foreach ($node->indicators as $ind) {
                $lines[] = implode(' | ', [
                    $this->escape($node->statement),
                    $this->escape($ind->name),
                    $this->escape($ind->unit ?? ''),
                    $ind->direction ?? '',
                    $this->escape($ind->baseline ?? ''),
                    $this->escape($ind->target ?? ''),
                ]);
            }
        }

        return implode("\n", $lines)."\n";
    }

    /** Ekspor ke JSON (struktur lengkap). */
    public function toJson(KinerjaTree $tree): string
    {
        $tree->load(['nodes.indicators', 'links']);

        return json_encode([
            'name' => $tree->name,
            'period_start' => $tree->period_start,
            'period_end' => $tree->period_end,
            'status' => $tree->status,
            'nodes' => $tree->nodes->map(fn (Node $n) => [
                'id' => $n->id,
                'code' => $n->code,
                'statement' => $n->statement,
                'type' => $n->type,
                'source_type' => $n->source_type,
                'indicators' => $n->indicators->map(fn ($i) => [
                    'name' => $i->name,
                    'definition' => $i->definition,
                    'unit' => $i->unit,
                    'direction' => $i->direction,
                    'data_source' => $i->data_source,
                    'baseline' => $i->baseline,
                    'target' => $i->target,
                ]),
            ]),
            'links' => $tree->links->map(fn (NodeLink $l) => [
                'parent' => $l->parent_node_id,
                'child' => $l->child_node_id,
                'reason' => $l->reason,
            ]),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** @return Collection<int, Node> */
    private function roots(KinerjaTree $tree): Collection
    {
        $childIds = $tree->links->pluck('child_node_id')->unique();

        return $tree->nodes->whereNotIn('id', $childIds);
    }

    private function renderNode(KinerjaTree $tree, Node $node, int $depth, array &$lines): void
    {
        $indent = str_repeat('  ', $depth);
        $code = $node->code ? "[{$node->code}] " : '';
        $lines[] = $indent.'- '.$code.$node->statement;

        // Anak-anak
        $children = $tree->links
            ->where('parent_node_id', $node->id)
            ->map(fn ($l) => $tree->nodes->firstWhere('id', $l->child_node_id))
            ->filter();
        foreach ($children as $child) {
            $this->renderNode($tree, $child, $depth + 1, $lines);
        }
    }

    private function escape(string $text): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $text);
    }

    /**
     * Ekspor ke DOCX.
     *
     * DOCX dibuat native dari ZIP + XML (tanpa pustaka pihak ketiga),
     * mengikuti keputusan yang sama dengan DocumentExtractor: hindari
     * dependensi yang membawa risiko keamanan.
     */
    public function toDocx(KinerjaTree $tree): string
    {
        $tree->load(['nodes.indicators', 'links']);

        $body = [];
        $body[] = $this->docxParagraph($tree->name, bold: true, size: 32);
        $body[] = $this->docxParagraph(
            'Periode: '.(($tree->period_start && $tree->period_end)
                ? $tree->period_start.'–'.$tree->period_end
                : '—')
            .'  |  Status: '.$tree->status,
            size: 20
        );
        $body[] = $this->docxParagraph('', size: 20);

        // Hierarki sasaran
        foreach ($this->roots($tree) as $root) {
            $this->renderNodeDocx($tree, $root, 0, $body);
        }

        // Tabel indikator
        $body[] = $this->docxParagraph('', size: 20);
        $body[] = $this->docxParagraph('Indikator', bold: true, size: 26);

        $rows = [];
        foreach ($tree->nodes as $node) {
            foreach ($node->indicators as $ind) {
                $rows[] = [
                    $node->statement,
                    $ind->name,
                    $ind->unit ?? '',
                    $ind->direction ?? '',
                    $ind->baseline ?? '',
                    $ind->target ?? '',
                ];
            }
        }
        $body[] = $this->docxTable(
            ['Sasaran', 'Indikator', 'Satuan', 'Arah', 'Baseline', 'Target'],
            $rows
        );

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.implode('', $body)
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'
            .'</w:body></w:document>';

        return $this->buildDocxZip($documentXml);
    }

    private function docxParagraph(string $text, bool $bold = false, int $size = 22, int $indent = 0): string
    {
        $rPr = '<w:rPr>'.($bold ? '<w:b/>' : '').'<w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/></w:rPr>';
        $pPr = '<w:pPr>'
            .($indent > 0 ? '<w:ind w:left="'.($indent * 360).'"/>' : '')
            .$rPr
            .'</w:pPr>';

        return '<w:p>'.$pPr.'<w:r>'.$rPr.'<w:t xml:space="preserve">'
            .$this->xmlEscape($text).'</w:t></w:r></w:p>';
    }

    /** @param array<int, array<int, string>> $rows */
    private function docxTable(array $headers, array $rows): string
    {
        $buildRow = function (array $cells, bool $bold = false) {
            $xml = '<w:tr>';
            foreach ($cells as $cell) {
                $rPr = '<w:rPr>'.($bold ? '<w:b/>' : '').'<w:sz w:val="20"/></w:rPr>';
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/></w:tcPr>'
                    .'<w:p><w:pPr>'.$rPr.'</w:pPr><w:r>'.$rPr
                    .'<w:t xml:space="preserve">'.$this->xmlEscape($cell).'</w:t></w:r></w:p></w:tc>';
            }

            return $xml.'</w:tr>';
        };

        $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/>'
            .'<w:tblBorders>'
            .'<w:top w:val="single" w:sz="4" w:color="999999"/>'
            .'<w:left w:val="single" w:sz="4" w:color="999999"/>'
            .'<w:bottom w:val="single" w:sz="4" w:color="999999"/>'
            .'<w:right w:val="single" w:sz="4" w:color="999999"/>'
            .'<w:insideH w:val="single" w:sz="4" w:color="999999"/>'
            .'<w:insideV w:val="single" w:sz="4" w:color="999999"/>'
            .'</w:tblBorders></w:tblPr>';

        $xml .= $buildRow($headers, bold: true);
        foreach ($rows as $row) {
            $xml .= $buildRow($row);
        }

        return $xml.'</w:tbl>';
    }

    private function renderNodeDocx(KinerjaTree $tree, Node $node, int $depth, array &$body): void
    {
        $code = $node->code ? "[{$node->code}] " : '';
        $body[] = $this->docxParagraph('• '.$code.$node->statement, size: 22, indent: $depth);

        $children = $tree->links
            ->where('parent_node_id', $node->id)
            ->map(fn ($l) => $tree->nodes->firstWhere('id', $l->child_node_id))
            ->filter();
        foreach ($children as $child) {
            $this->renderNodeDocx($tree, $child, $depth + 1, $body);
        }
    }

    /** Rakit struktur ZIP minimal yang valid sebagai DOCX. */
    private function buildDocxZip(string $documentXml): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sakip_docx_');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>');

        $zip->addFromString('word/document.xml', $documentXml);

        $zip->close();

        $content = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $content;
    }

    /** Ekspor ke PDF via dompdf (menggunakan HTML sebagai sumber). */
    public function toPdf(KinerjaTree $tree): string
    {
        $html = $this->toHtml($tree);

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => false, // cegah pemuatan resource eksternal
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /** Bangun HTML (dipakai untuk PDF dan bisa juga di-print browser). */
    public function toHtml(KinerjaTree $tree): string
    {
        $tree->load(['nodes.indicators', 'links']);

        $h = [];
        $h[] = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
            .'<style>'
            .'body{font-family:"DejaVu Sans",sans-serif;font-size:11px;color:#111}'
            .'h1{font-size:16px;margin:0 0 4px}'
            .'h2{font-size:13px;margin:16px 0 6px;border-bottom:1px solid #ccc;padding-bottom:3px}'
            .'.meta{color:#555;font-size:10px;margin-bottom:12px}'
            .'ul{list-style:none;padding-left:0;margin:0}'
            .'li{margin:2px 0}'
            .'.d0{padding-left:0}.d1{padding-left:18px}.d2{padding-left:36px}.d3{padding-left:54px}'
            .'table{border-collapse:collapse;width:100%;margin-top:6px}'
            .'th,td{border:1px solid #999;padding:4px 6px;text-align:left;vertical-align:top;font-size:10px}'
            .'th{background:#f0f0f0}'
            .'</style></head><body>';

        $h[] = '<h1>'.htmlspecialchars($tree->name, ENT_QUOTES, 'UTF-8').'</h1>';
        $h[] = '<div class="meta">Periode: '
            .(($tree->period_start && $tree->period_end)
                ? $tree->period_start.'–'.$tree->period_end
                : '—')
            .' | Status: '.htmlspecialchars($tree->status, ENT_QUOTES, 'UTF-8').'</div>';

        $h[] = '<h2>Perjenjangan Kinerja</h2><ul>';
        foreach ($this->roots($tree) as $root) {
            $this->renderNodeHtml($tree, $root, 0, $h);
        }
        $h[] = '</ul>';

        $h[] = '<h2>Indikator</h2>';
        $h[] = '<table><thead><tr>'
            .'<th>Sasaran</th><th>Indikator</th><th>Satuan</th><th>Arah</th><th>Baseline</th><th>Target</th>'
            .'</tr></thead><tbody>';
        foreach ($tree->nodes as $node) {
            foreach ($node->indicators as $ind) {
                $h[] = '<tr>'
                    .'<td>'.htmlspecialchars($node->statement, ENT_QUOTES, 'UTF-8').'</td>'
                    .'<td>'.htmlspecialchars($ind->name, ENT_QUOTES, 'UTF-8').'</td>'
                    .'<td>'.htmlspecialchars((string) $ind->unit, ENT_QUOTES, 'UTF-8').'</td>'
                    .'<td>'.htmlspecialchars((string) $ind->direction, ENT_QUOTES, 'UTF-8').'</td>'
                    .'<td>'.htmlspecialchars((string) $ind->baseline, ENT_QUOTES, 'UTF-8').'</td>'
                    .'<td>'.htmlspecialchars((string) $ind->target, ENT_QUOTES, 'UTF-8').'</td>'
                    .'</tr>';
            }
        }
        $h[] = '</tbody></table></body></html>';

        return implode('', $h);
    }

    private function renderNodeHtml(KinerjaTree $tree, Node $node, int $depth, array &$h): void
    {
        $code = $node->code ? '<strong>['.htmlspecialchars($node->code, ENT_QUOTES, 'UTF-8').']</strong> ' : '';
        $depth = min($depth, 3);
        $h[] = '<li class="d'.$depth.'">'.$code.htmlspecialchars($node->statement, ENT_QUOTES, 'UTF-8').'</li>';

        $children = $tree->links
            ->where('parent_node_id', $node->id)
            ->map(fn ($l) => $tree->nodes->firstWhere('id', $l->child_node_id))
            ->filter();
        foreach ($children as $child) {
            $this->renderNodeHtml($tree, $child, $depth + 1, $h);
        }
    }

    private function xmlEscape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
