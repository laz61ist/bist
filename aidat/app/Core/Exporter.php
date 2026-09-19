<?php

declare(strict_types=1);

namespace Aidat\Core;

use ZipArchive;

/** CSV (UTF-8 BOM, noktalı virgül; Türkçe Excel uyumlu) ve minimal XLSX (ZipArchive varsa). */
final class Exporter
{
    /** @param list<string> $headers @param list<list<mixed>> $rows */
    public static function csv(array $headers, array $rows): string
    {
        $out = "\xEF\xBB\xBF";
        $line = static function (array $cells): string {
            return implode(';', array_map(static function ($c): string {
                $s = (string) ($c ?? '');
                if (preg_match('/[;"\r\n]/', $s)) {
                    $s = '"' . str_replace('"', '""', $s) . '"';
                }
                return $s;
            }, $cells)) . "\r\n";
        };
        $out .= $line($headers);
        foreach ($rows as $r) {
            $out .= $line($r);
        }
        return $out;
    }

    public static function xlsxAvailable(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * Basit XLSX: tek sayfa, inline string; sayısal hücreler (int/float) sayı olarak yazılır.
     * @param list<string> $headers @param list<list<mixed>> $rows
     */
    public static function xlsx(array $headers, array $rows, string $sheet = 'Rapor'): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . htmlspecialchars(mb_substr($sheet, 0, 30), ENT_XML1) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellXfs count="3"><xf numFmtId="0" fontId="0"/><xf numFmtId="0" fontId="1" applyFont="1"/><xf numFmtId="164" fontId="0" applyNumberFormat="1"/></cellXfs></styleSheet>');
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $rowXml = static function (array $cells, int $r, bool $head): string {
            $s = '<row r="' . $r . '">';
            foreach (array_values($cells) as $i => $c) {
                $col = self::colName($i) . $r;
                if (!$head && (is_int($c) || is_float($c))) {
                    $s .= '<c r="' . $col . '" s="2"><v>' . $c . '</v></c>';
                } else {
                    $s .= '<c r="' . $col . '" t="inlineStr"' . ($head ? ' s="1"' : '') . '><is><t xml:space="preserve">' . htmlspecialchars((string) ($c ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
                }
            }
            return $s . '</row>';
        };
        $xml .= $rowXml($headers, 1, true);
        $r = 2;
        foreach ($rows as $row) {
            $xml .= $rowXml($row, $r++, false);
        }
        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $data;
    }

    private static function colName(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - 1, 26);
        }
        return $s;
    }
}
