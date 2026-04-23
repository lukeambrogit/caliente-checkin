<?php
/**
 * XLSXWriter — Minimal single-file XLSX generator
 * No external dependencies. Uses only PHP core functions.
 * Does NOT require ZipArchive — ZIP is built manually using gzdeflate().
 *
 * Usage:
 *   $writer = new OC_XLSXWriter();
 *   $writer->writeSheetHeader('Sheet1', ['Col A' => 'string', 'Col B' => 'integer']);
 *   $writer->writeSheetRow('Sheet1', ['value1', 42]);
 *   $writer->writeToFile('/path/to/file.xlsx');
 *   // OR
 *   $writer->writeToStdOut(); // streams to browser (set headers first)
 */

if (!defined('ABSPATH')) { exit; }

class OC_XLSXWriter {

    /** @var array Sheet data keyed by sheet name */
    private array $sheets = [];

    /** @var float Workbook-level font scale multiplier (1.0 = default) */
    private float $fontScale = 1.0;

    // -------------------------------------------------- public API --

    /**
     * @param string $sheetName
     * @param array  $header   ['Column Label' => 'type']  type: string|integer|float|date|price
     * @param array  $options  ['widths' => [20, 15, ...]]
     */
    public function writeSheetHeader(string $sheetName, array $header, array $options = []): void {
        if (isset($options['font_scale'])) {
            $scale = (float) $options['font_scale'];
            if ($scale > 0) {
                $this->fontScale = $scale;
            }
        }

        $this->sheets[$sheetName] = [
            'columns' => $header,
            'widths'  => $options['widths'] ?? [],
            'row_height_multiplier' => isset($options['row_height_multiplier'])
                ? max(0.5, (float) $options['row_height_multiplier'])
                : 1.0,
            'rows'    => [['data' => array_keys($header), 'style' => 'header']],
        ];
    }

    public function writeSheetRow(string $sheetName, array $row): void {
        if (!isset($this->sheets[$sheetName])) {
            $this->sheets[$sheetName] = ['columns' => [], 'widths' => [], 'rows' => []];
        }
        $this->sheets[$sheetName]['rows'][] = ['data' => $row, 'style' => 'data'];
    }

    /** Write XLSX bytes directly to stdout. Caller must set headers and clear output buffers first. */
    public function writeToStdOut(): void {
        echo $this->buildXlsx();
    }

    /** Write XLSX bytes to a file on disk. */
    public function writeToFile(string $filePath): void {
        file_put_contents($filePath, $this->buildXlsx());
    }

    // -------------------------------------------- XLSX assembler --

    private function buildXlsx(): string {
        $sheetNames    = array_keys($this->sheets);
        $sharedStrings = [];
        $ssIndex       = 0;

        // Pre-build shared strings index
        foreach ($this->sheets as $sheet) {
            $cols = array_values($sheet['columns']);
            foreach ($sheet['rows'] as $rowMeta) {
                $isHeader = ($rowMeta['style'] === 'header');
                foreach ($rowMeta['data'] as $colIdx => $value) {
                    $type = $isHeader ? 'string' : ($cols[$colIdx] ?? 'string');
                    if ($isHeader || $this->isStringType($type)) {
                        $str = (string) $value;
                        if (!isset($sharedStrings[$str])) {
                            $sharedStrings[$str] = $ssIndex++;
                        }
                    }
                }
            }
        }

        $files = [];
        $files['[Content_Types].xml']          = $this->buildContentTypes($sheetNames);
        $files['_rels/.rels']                  = $this->buildRels();
        $files['xl/workbook.xml']              = $this->buildWorkbook($sheetNames);
        $files['xl/_rels/workbook.xml.rels']   = $this->buildWorkbookRels($sheetNames);
        $files['xl/styles.xml']                = $this->buildStyles();
        $files['xl/sharedStrings.xml']         = $this->buildSharedStrings($sharedStrings);

        foreach ($sheetNames as $i => $sheetName) {
            $idx = $i + 1;
            $files["xl/worksheets/sheet{$idx}.xml"] = $this->buildSheet($this->sheets[$sheetName], $sharedStrings);
        }

        return $this->buildZip($files);
    }

    // -------------------------------------------- Pure-PHP ZIP builder --
    // Builds a valid ZIP archive in memory using gzdeflate() for compression.
    // No ZipArchive extension required.

    private function buildZip(array $files): string {
        [$dosTime, $dosDate] = $this->dosDateTime();

        $localEntries = '';
        $centralDir   = '';
        $offset       = 0;
        $entryCount   = 0;

        foreach ($files as $name => $content) {
            $name     = str_replace('\\', '/', $name);
            $nameLen  = strlen($name);
            $uncSize  = strlen($content);
            $crc      = $this->crc32uint($content);

            // Try deflate; fall back to stored if not smaller
            $deflated = gzdeflate($content, 6);
            if ($deflated !== false && strlen($deflated) < $uncSize) {
                $method   = 8; // DEFLATE
                $compData = $deflated;
            } else {
                $method   = 0; // STORED
                $compData = $content;
            }
            $compSize = strlen($compData);

            // Local file header (30 bytes) — signature PK\x03\x04
            $localHeader = pack('VvvvvvVVVvv',
                0x04034b50, // signature
                20,         // version needed to extract (2.0)
                0,          // general purpose bit flag
                $method,    // compression method
                $dosTime,   // last mod time
                $dosDate,   // last mod date
                $crc,       // crc-32
                $compSize,  // compressed size
                $uncSize,   // uncompressed size
                $nameLen,   // file name length
                0           // extra field length
            );

            $localEntries .= $localHeader . $name . $compData;

            // Central directory entry (46 bytes) — signature PK\x01\x02
            $centralDir .= pack('VvvvvvvVVVvvvvvVV',
                0x02014b50, // signature
                20,         // version made by
                20,         // version needed
                0,          // general purpose bit flag
                $method,    // compression method
                $dosTime,   // last mod time
                $dosDate,   // last mod date
                $crc,       // crc-32
                $compSize,  // compressed size
                $uncSize,   // uncompressed size
                $nameLen,   // file name length
                0,          // extra field length
                0,          // file comment length
                0,          // disk number start
                0,          // internal file attributes
                0,          // external file attributes
                $offset     // relative offset of local header
            );
            $centralDir .= $name;

            $offset += strlen($localHeader) + $nameLen + $compSize;
            $entryCount++;
        }

        $cdOffset = $offset;
        $cdSize   = strlen($centralDir);

        // End of central directory record (22 bytes) — signature PK\x05\x06
        $eocd = pack('VvvvvVVv',
            0x06054b50, // signature
            0,          // disk number
            0,          // start disk
            $entryCount,
            $entryCount,
            $cdSize,    // central directory size
            $cdOffset,  // central directory offset
            0           // comment length
        );

        return $localEntries . $centralDir . $eocd;
    }

    /** Returns CRC-32 as unsigned 32-bit integer (safe on both 32/64-bit PHP). */
    private function crc32uint(string $data): int {
        $crc = crc32($data);
        return $crc < 0 ? $crc + 0x100000000 : $crc;
    }

    /** Returns [DOS time, DOS date] for the current local time. */
    private function dosDateTime(): array {
        $t    = localtime(time(), true);
        $time = ($t['tm_hour'] << 11) | ($t['tm_min'] << 5) | (int)($t['tm_sec'] / 2);
        $date = (($t['tm_year'] - 80) << 9) | (($t['tm_mon'] + 1) << 5) | $t['tm_mday'];
        return [$time, $date];
    }

    // -------------------------------------------- XML builders --

    private function buildContentTypes(array $sheetNames): string {
        $sheets = '';
        foreach ($sheetNames as $i => $name) {
            $idx = $i + 1;
            $sheets .= '<Override PartName="/xl/worksheets/sheet' . $idx . '.xml" '
                . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . $sheets
            . '</Types>';
    }

    private function buildRels(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function buildWorkbook(array $sheetNames): string {
        $sheets = '';
        foreach ($sheetNames as $i => $name) {
            $idx = $i + 1;
            $sheets .= '<sheet name="' . $this->xmlAttr($name) . '" sheetId="' . $idx . '" r:id="rId' . $idx . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets>'
            . '</workbook>';
    }

    private function buildWorkbookRels(array $sheetNames): string {
        $rels = '';
        foreach ($sheetNames as $i => $name) {
            $idx = $i + 1;
            $rels .= '<Relationship Id="rId' . $idx . '" '
                . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                . 'Target="worksheets/sheet' . $idx . '.xml"/>';
        }
        $ssId = count($sheetNames) + 1;
        $stId = count($sheetNames) + 2;
        $rels .= '<Relationship Id="rId' . $ssId . '" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" '
            . 'Target="sharedStrings.xml"/>';
        $rels .= '<Relationship Id="rId' . $stId . '" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" '
            . 'Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    private function buildStyles(): string {
        $baseFontSize = max(1, round(10 * $this->fontScale, 2));
        $headerFontSize = $baseFontSize;

        // Column palette based on the reference report:
        //   Header: #356854 with white bold text
        //   Data columns (1..10): #F6B26B, #FCE5CD, #FFF2CC, #C9DAF8, #D9EAD3,
        //                          #F4CCCC, #D9D2E9, #D9D2E9, #EAD1DC, #EAD1DC
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="' . $baseFontSize . '"/><name val="Arial"/></font>'
            . '<font><b/><sz val="' . $headerFontSize . '"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            . '</fonts>'
            . '<fills count="13">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF356854"/><bgColor rgb="FF356854"/></patternFill></fill>' // 2 header
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF6B26B"/><bgColor rgb="FFF6B26B"/></patternFill></fill>' // 3 col1
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFCE5CD"/><bgColor rgb="FFFCE5CD"/></patternFill></fill>' // 4 col2
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor rgb="FFFFF2CC"/></patternFill></fill>' // 5 col3
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFC9DAF8"/><bgColor rgb="FFC9DAF8"/></patternFill></fill>' // 6 col4
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9EAD3"/><bgColor rgb="FFD9EAD3"/></patternFill></fill>' // 7 col5
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF4CCCC"/><bgColor rgb="FFF4CCCC"/></patternFill></fill>' // 8 col6
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D2E9"/><bgColor rgb="FFD9D2E9"/></patternFill></fill>' // 9 col7
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D2E9"/><bgColor rgb="FFD9D2E9"/></patternFill></fill>' // 10 col8
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEAD1DC"/><bgColor rgb="FFEAD1DC"/></patternFill></fill>' // 11 col9
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEAD1DC"/><bgColor rgb="FFEAD1DC"/></patternFill></fill>' // 12 col10
            . '</fills>'
            . '<borders count="3">'
            . '<border/>'
            . '<border><left style="thin"><color rgb="FF000000"/></left><right style="thin"><color rgb="FF000000"/></right><top style="thin"><color rgb="FFB7B7B7"/></top><bottom style="thin"><color rgb="FFB7B7B7"/></bottom></border>'
            . '<border><left style="thin"><color rgb="FF356854"/></left><right style="thin"><color rgb="FF356854"/></right><top style="thin"><color rgb="FF356854"/></top><bottom style="thin"><color rgb="FF356854"/></bottom></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="13">'
                . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>' // 0 default
                . '<xf numFmtId="0" fontId="1" fillId="2" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>' // 1 header
                . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 2 col1
                . '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 3 col2
                . '<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 4 col3
                . '<xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 5 col4
                . '<xf numFmtId="0" fontId="0" fillId="7" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 6 col5
                . '<xf numFmtId="0" fontId="0" fillId="8" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 7 col6
                . '<xf numFmtId="0" fontId="0" fillId="9" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 8 col7
                . '<xf numFmtId="0" fontId="0" fillId="10" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 9 col8
                . '<xf numFmtId="0" fontId="0" fillId="11" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 10 col9
                . '<xf numFmtId="0" fontId="0" fillId="12" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 11 col10
                . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>' // 12 fallback
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private function buildSharedStrings(array $sharedStrings): string {
        $count   = count($sharedStrings);
        $ordered = array_flip($sharedStrings);
        ksort($ordered);
        $items = '';
        foreach ($ordered as $str) {
            $items .= '<si><t xml:space="preserve">' . $this->xmlEsc($str) . '</t></si>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">'
            . $items
            . '</sst>';
    }

    private function buildSheet(array $sheet, array $sharedStrings): string {
        $cols   = array_values($sheet['columns']);
        $widths = $sheet['widths'];
        $rowHeightMultiplier = (float) ($sheet['row_height_multiplier'] ?? 1.0);
        $baseRowHeight = 15.0;
        $rowHeight = round($baseRowHeight * $rowHeightMultiplier, 2);

        $colsXml = '';
        if (!empty($widths)) {
            $colsXml = '<cols>';
            foreach ($widths as $i => $w) {
                $idx = $i + 1;
                $colsXml .= '<col min="' . $idx . '" max="' . $idx . '" width="' . (float)$w . '" customWidth="1"/>';
            }
            $colsXml .= '</cols>';
        }

        $sheetData = '';
        foreach ($sheet['rows'] as $rowIdx => $rowMeta) {
            $r        = $rowIdx + 1;
            $isHeader = ($rowMeta['style'] === 'header');
            $cells    = '';
            foreach ($rowMeta['data'] as $colIdx => $value) {
                $colLetter = $this->colLetter($colIdx);
                $cellRef   = $colLetter . $r;
                $type      = $isHeader ? 'string' : ($cols[$colIdx] ?? 'string');
                $styleIdx  = $isHeader ? '1' : (string) $this->dataStyleIndexForColumn($colIdx);

                if ($isHeader || $this->isStringType($type)) {
                    $ssIdx = $sharedStrings[(string)$value] ?? 0;
                    $cells .= '<c r="' . $cellRef . '" t="s" s="' . $styleIdx . '"><v>' . $ssIdx . '</v></c>';
                } elseif ($type === 'integer') {
                    $cells .= '<c r="' . $cellRef . '" s="' . $styleIdx . '"><v>' . (int)$value . '</v></c>';
                } elseif ($type === 'float' || $type === 'price') {
                    $cells .= '<c r="' . $cellRef . '" s="' . $styleIdx . '"><v>' . (float)str_replace(',', '.', (string)$value) . '</v></c>';
                } else {
                    $ssIdx = $sharedStrings[(string)$value] ?? 0;
                    $cells .= '<c r="' . $cellRef . '" t="s" s="' . $styleIdx . '"><v>' . $ssIdx . '</v></c>';
                }
            }
            if ($rowHeightMultiplier !== 1.0) {
                $sheetData .= '<row r="' . $r . '" ht="' . $rowHeight . '" customHeight="1">' . $cells . '</row>';
            } else {
                $sheetData .= '<row r="' . $r . '">' . $cells . '</row>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $colsXml
            . '<sheetData>' . $sheetData . '</sheetData>'
            . '</worksheet>';
    }

    // -------------------------------------------- helpers --

    private function isStringType(string $type): bool {
        return in_array($type, ['string', 'date', 'text'], true);
    }

    private function colLetter(int $index): string {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index  = intdiv($index, 26);
        }
        return $letter;
    }

    /**
     * Returns style index for data cells based on report column position.
     * Expected order: Nr, Nume, Data Plata, Metoda, Suma, Coplata,
     *                 Tip Abonament, Cursuri, Nr Scanari, Data Ultima Scanare
     */
    private function dataStyleIndexForColumn(int $colIdx): int {
        // cellXfs index map for data columns (0-based column index)
        $styleByColumn = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
        return $styleByColumn[$colIdx] ?? 12;
    }

    private function xmlEsc(string $s): string {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function xmlAttr(string $s): string {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
