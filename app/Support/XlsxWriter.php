<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use RuntimeException;
use ZipArchive;

/**
 * Minimal, dependency-free .xlsx writer (SpreadsheetML over ext-zip).
 *
 * It covers what the app's reports need — a single sheet with a title block,
 * a bold + filterable header row, typed columns (text / number / money / date)
 * and bold summary rows — without pulling PhpSpreadsheet into the project.
 *
 * Usage:
 *   $xlsx = new XlsxWriter('Accounting', 'TJS');
 *   $xlsx->addTitle('Accounting report');
 *   $xlsx->setColumns([['label' => 'Date', 'type' => XlsxWriter::DATE, 'width' => 18], ...]);
 *   $xlsx->addRow([$record->transaction_date, ...]);
 *   $xlsx->addSummaryRow([null, 'Total', 1234.5]);
 *   $path = $xlsx->writeToTempFile();
 */
class XlsxWriter
{
    public const TEXT = 'text';
    public const NUMBER = 'number';
    public const MONEY = 'money';
    public const DATE = 'date';

    /** Style ids — these must match the order of the <cellXfs> entries below. */
    private const STYLE_DEFAULT = 0;
    private const STYLE_HEADER = 1;
    private const STYLE_DATE = 2;
    private const STYLE_MONEY = 3;
    private const STYLE_BOLD = 4;
    private const STYLE_BOLD_MONEY = 5;
    private const STYLE_TITLE = 6;

    /** Days between the Excel epoch (1899-12-30) and the Unix epoch. */
    private const EXCEL_EPOCH_OFFSET = 25569;

    /** @var list<array{label: string, type: string, width: float}> */
    private array $columns = [];

    /** @var list<string> Rendered <row> elements, in sheet order. */
    private array $rows = [];

    private int $rowIndex = 0;

    /** Row holding the column labels — used for the frozen pane and autofilter. */
    private ?int $headerRow = null;

    /** Last data row, so the autofilter stops short of the totals below it. */
    private ?int $tableEndRow = null;

    private int $maxColumn = 0;

    public function __construct(
        private readonly string $sheetName = 'Sheet1',
        private readonly string $currency = '',
    ) {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The zip PHP extension is required to write .xlsx files.');
        }
    }

    /**
     * Add a large bold line — a report title or a section heading.
     */
    public function addTitle(string $text): static
    {
        $this->rows[] = $this->renderRow([
            $this->renderCell(1, $this->rowIndex + 1, $text, self::TEXT, self::STYLE_TITLE),
        ]);
        $this->rowIndex++;

        return $this;
    }

    /**
     * Add a plain text line, e.g. "Period: 01.01.2026 — 31.01.2026".
     *
     * @param  list<string|null>  $values
     */
    public function addTextRow(array $values): static
    {
        $cells = [];
        foreach (array_values($values) as $i => $value) {
            $cells[] = $this->renderCell($i + 1, $this->rowIndex + 1, $value, self::TEXT, self::STYLE_DEFAULT);
        }

        $this->rows[] = $this->renderRow($cells);
        $this->rowIndex++;

        return $this;
    }

    public function addBlankRow(): static
    {
        $this->rows[] = $this->renderRow([]);
        $this->rowIndex++;

        return $this;
    }

    /**
     * Define the table columns and write their labels as the header row.
     *
     * @param  list<array{label: string, type?: string, width?: float}>  $columns
     */
    public function setColumns(array $columns): static
    {
        $this->columns = array_map(fn (array $column): array => [
            'label' => $column['label'],
            'type' => $column['type'] ?? self::TEXT,
            'width' => (float) ($column['width'] ?? 16),
        ], array_values($columns));

        $this->maxColumn = max($this->maxColumn, count($this->columns));
        $this->headerRow = $this->rowIndex + 1;

        $cells = [];
        foreach ($this->columns as $i => $column) {
            $cells[] = $this->renderCell($i + 1, $this->headerRow, $column['label'], self::TEXT, self::STYLE_HEADER);
        }

        $this->rows[] = $this->renderRow($cells, 22.0);
        $this->rowIndex++;

        return $this;
    }

    /**
     * Add a data row, typed according to the column definitions.
     *
     * @param  list<mixed>  $values
     */
    public function addRow(array $values): static
    {
        $this->writeTypedRow($values, bold: false);
        $this->tableEndRow = $this->rowIndex;

        return $this;
    }

    /**
     * Add a bold row — totals and other summary lines.
     *
     * @param  list<mixed>  $values
     */
    public function addSummaryRow(array $values): static
    {
        return $this->writeTypedRow($values, bold: true);
    }

    /**
     * Write the workbook to a temporary file and return its path. The caller
     * owns the file — stream it with deleteFileAfterSend() or unlink it.
     */
    public function writeToTempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary file for the spreadsheet.');
        }

        $this->writeTo($path);

        return $path;
    }

    public function writeTo(string $path): void
    {
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to open [{$path}] for writing.");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());

        $zip->close();
    }

    /**
     * @param  list<mixed>  $values
     */
    private function writeTypedRow(array $values, bool $bold): static
    {
        $values = array_values($values);
        $this->maxColumn = max($this->maxColumn, count($values));

        $cells = [];
        foreach ($values as $i => $value) {
            $type = $this->columns[$i]['type'] ?? self::TEXT;
            $cells[] = $this->renderCell($i + 1, $this->rowIndex + 1, $value, $type, null, $bold);
        }

        $this->rows[] = $this->renderRow($cells);
        $this->rowIndex++;

        return $this;
    }

    /**
     * @param  list<string>  $cells
     */
    private function renderRow(array $cells, ?float $height = null): string
    {
        $attributes = 'r="'.($this->rowIndex + 1).'"';

        if ($height !== null) {
            $attributes .= ' ht="'.$height.'" customHeight="1"';
        }

        return '<row '.$attributes.'>'.implode('', $cells).'</row>';
    }

    /**
     * Render a single cell. A value that does not fit its column type (a label
     * sitting in a money column, say) falls back to a text cell so a summary
     * row can reuse the table's column layout.
     */
    private function renderCell(int $column, int $row, mixed $value, string $type, ?int $style = null, bool $bold = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $reference = self::columnLetter($column).$row;

        if ($style === null) {
            if ($type === self::DATE && $value instanceof DateTimeInterface) {
                return '<c r="'.$reference.'" s="'.self::STYLE_DATE.'"><v>'.self::excelDate($value).'</v></c>';
            }

            if (($type === self::MONEY || $type === self::NUMBER) && is_numeric($value)) {
                $style = match (true) {
                    $type === self::MONEY && $bold => self::STYLE_BOLD_MONEY,
                    $type === self::MONEY => self::STYLE_MONEY,
                    $bold => self::STYLE_BOLD,
                    default => self::STYLE_DEFAULT,
                };

                return '<c r="'.$reference.'" s="'.$style.'"><v>'.self::number($value).'</v></c>';
            }

            $style = $bold ? self::STYLE_BOLD : self::STYLE_DEFAULT;
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('d.m.Y H:i');
        }

        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .self::escape((string) $value).'</t></is></c>';
    }

    private function sheetXml(): string
    {
        $cols = '';
        foreach ($this->columns as $i => $column) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$column['width'].'" customWidth="1"/>';
        }

        $pane = '';
        $autoFilter = '';

        if ($this->headerRow !== null && $this->columns !== []) {
            $pane = '<pane ySplit="'.$this->headerRow.'" topLeftCell="A'.($this->headerRow + 1)
                .'" activePane="bottomLeft" state="frozen"/>';
            $autoFilter = '<autoFilter ref="A'.$this->headerRow.':'
                .self::columnLetter(count($this->columns)).max($this->tableEndRow ?? 0, $this->headerRow).'"/>';
        }

        $dimension = 'A1:'.self::columnLetter(max($this->maxColumn, 1)).max($this->rowIndex, 1);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="'.$dimension.'"/>'
            .'<sheetViews><sheetView workbookViewId="0">'.$pane.'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.implode('', $this->rows).'</sheetData>'
            .$autoFilter
            .'</worksheet>';
    }

    private function stylesXml(): string
    {
        // Excel rejects a literal currency code in a format code unless it is
        // quoted; an empty currency leaves a plain two-decimal number.
        $moneyFormat = $this->currency === ''
            ? '#,##0.00'
            : '#,##0.00\ &quot;'.self::escape($this->currency).'&quot;';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2">'
            .'<numFmt numFmtId="164" formatCode="dd.mm.yyyy\ hh:mm"/>'
            .'<numFmt numFmtId="165" formatCode="'.$moneyFormat.'"/>'
            .'</numFmts>'
            .'<fonts count="3">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEFEFEF"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left/><right/><top/><bottom style="thin"><color rgb="FFBFBFBF"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="165" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::escape(self::sanitizeSheetName($this->sheetName)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    /**
     * Excel stores a date as the number of days since 1899-12-30, in the
     * sheet's own (i.e. the app's) timezone — hence the offset correction.
     */
    private static function excelDate(DateTimeInterface $date): string
    {
        return self::number(self::EXCEL_EPOCH_OFFSET + ($date->getTimestamp() + $date->getOffset()) / 86400);
    }

    /**
     * Render a number the way the file format expects it: a plain decimal,
     * never scientific notation and never a locale-specific separator.
     */
    private static function number(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(sprintf('%.10F', (float) $value), '0'), '.') ?: '0';
    }

    private static function escape(string $value): string
    {
        // Control characters other than tab/newline/carriage return are not
        // representable in XML 1.0 and make Excel refuse the whole file.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Sheet names are capped at 31 characters and may not contain : \ / ? * [ ].
     */
    private static function sanitizeSheetName(string $name): string
    {
        $name = str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $name);

        return mb_substr(trim($name), 0, 31) ?: 'Sheet1';
    }

    /**
     * 1 => A, 26 => Z, 27 => AA …
     */
    private static function columnLetter(int $index): string
    {
        $letters = '';

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }
}
