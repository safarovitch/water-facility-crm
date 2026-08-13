<?php

use App\Support\XlsxWriter;
use Illuminate\Support\Carbon;

/**
 * Read a single part out of a written workbook.
 */
function xlsxPart(XlsxWriter $xlsx, string $part): string
{
    $path = $xlsx->writeToTempFile();

    $zip = new ZipArchive();
    expect($zip->open($path))->toBeTrue();

    $contents = $zip->getFromName($part);
    $zip->close();
    unlink($path);

    expect($contents)->toBeString();

    return $contents;
}

it('writes a workbook with every part Excel requires', function () {
    $xlsx = new XlsxWriter('Report', 'TJS');
    $xlsx->setColumns([['label' => 'Date', 'type' => XlsxWriter::DATE]]);
    $xlsx->addRow([Carbon::parse('2026-01-15 10:00:00')]);

    $path = $xlsx->writeToTempFile();

    $zip = new ZipArchive();
    $zip->open($path);

    $parts = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $parts[] = $zip->getNameIndex($i);
    }
    $zip->close();
    unlink($path);

    expect($parts)->toContain(
        '[Content_Types].xml',
        '_rels/.rels',
        'xl/workbook.xml',
        'xl/_rels/workbook.xml.rels',
        'xl/styles.xml',
        'xl/worksheets/sheet1.xml',
    );
});

it('writes numbers, dates and text as their own cell types', function () {
    $xlsx = new XlsxWriter('Report', 'TJS');
    $xlsx->setColumns([
        ['label' => 'Date', 'type' => XlsxWriter::DATE],
        ['label' => 'Note', 'type' => XlsxWriter::TEXT],
        ['label' => 'Amount', 'type' => XlsxWriter::MONEY],
    ]);
    $xlsx->addRow([Carbon::parse('2026-01-15 00:00:00', 'UTC'), 'Rent & water', 1234.5]);

    $sheet = xlsxPart($xlsx, 'xl/worksheets/sheet1.xml');

    // 2026-01-15 is day 46037 of the Excel epoch (1899-12-30).
    expect($sheet)->toContain('<c r="A2" s="2"><v>46037</v></c>')
        ->toContain('<t xml:space="preserve">Rent &amp; water</t>')
        ->toContain('<c r="C2" s="3"><v>1234.5</v></c>');
});

it('keeps summary rows out of the autofilter range', function () {
    $xlsx = new XlsxWriter('Report');
    $xlsx->addTitle('Report');
    $xlsx->setColumns([['label' => 'Note'], ['label' => 'Amount', 'type' => XlsxWriter::MONEY]]);
    $xlsx->addRow(['First', 1]);
    $xlsx->addRow(['Second', 2]);
    $xlsx->addSummaryRow(['Total', 3]);

    $sheet = xlsxPart($xlsx, 'xl/worksheets/sheet1.xml');

    // Header on row 2, data on rows 3-4, the bold total on row 5 excluded.
    expect($sheet)->toContain('<autoFilter ref="A2:B4"/>')
        ->toContain('<pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/>');
});

it('strips characters that would make Excel reject the file', function () {
    $xlsx = new XlsxWriter('A/very:long[sheet]name that runs past the limit');
    $xlsx->setColumns([['label' => 'Note']]);
    $xlsx->addRow(["bad\x07control"]);

    expect(xlsxPart($xlsx, 'xl/worksheets/sheet1.xml'))->toContain('badcontrol');

    // Sheet names are capped at 31 characters and may not contain / : [ ].
    expect(xlsxPart($xlsx, 'xl/workbook.xml'))->toContain('name="A very long sheet name that run"');
});
