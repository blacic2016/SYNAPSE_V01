<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$file = isset($argv[1]) ? $argv[1] : (__DIR__ . '/../portmapping/Plantilla port - Bodega JUTECERO 1.xlsx');
if (!file_exists($file)) {
    die("File does not exist: $file\n");
}
$reader = IOFactory::createReaderForFile($file);
$reader->setReadDataOnly(true);
$ss = $reader->load($file);

echo "Sheets: " . implode(', ', $ss->getSheetNames()) . PHP_EOL;
foreach ($ss->getAllSheets() as $s) {
    echo "========================================================\n";
    echo "SHEET: " . $s->getTitle() . " (Rows: " . $s->getHighestRow() . ", Cols: " . $s->getHighestColumn() . ")\n";
    echo "========================================================\n";
    for ($r = 1; $r <= min(6, $s->getHighestRow()); $r++) {
        $row = [];
        $maxC = min(30, Coordinate::columnIndexFromString($s->getHighestColumn()));
        for ($c = 1; $c <= $maxC; $c++) {
            $val = trim((string)$s->getCellByColumnAndRow($c, $r)->getValue());
            if ($val !== '') {
                $colLetter = Coordinate::stringFromColumnIndex($c);
                $row[] = "{$colLetter}[$c]: $val";
            }
        }
        if (!empty($row)) {
            echo "Row $r: " . implode(' | ', $row) . PHP_EOL;
        }
    }
}
