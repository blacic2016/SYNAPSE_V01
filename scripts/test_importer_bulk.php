<?php
require_once __DIR__ . '/../src/PortmappingExcelImporter.php';

$file = isset($argv[1]) ? $argv[1] : (__DIR__ . '/../portmapping/Plantilla_Oficial_Portmapping_Bulk.xlsx');
$res = PortmappingExcelImporter::analyzeExcel($file);

echo "Analyze result success: " . ($res['success'] ? 'YES' : 'NO') . PHP_EOL;
if (!$res['success']) {
    echo "Errors: " . implode(', ', $res['errors']) . PHP_EOL;
} else {
    $sum = $res['summary'];
    echo "Is Bulk: " . (!empty($sum['is_bulk']) ? 'YES' : 'NO') . PHP_EOL;
    echo "Devices Count: " . ($sum['devices_count'] ?? 1) . PHP_EOL;
    echo "Has Duplicates: " . (!empty($sum['has_duplicates']) ? 'YES' : 'NO') . PHP_EOL;
    if (!empty($sum['devices'])) {
        foreach ($sum['devices'] as $idx => $d) {
            echo "--- Device " . ($idx + 1) . ": " . $d['device_name'] . " (Serial: " . $d['serial'] . ", Ports: " . count($d['ports']) . ", Connected: " . $d['connected_count'] . ", Vacant: " . $d['vacant_count'] . ", Duplicate: " . ($d['is_duplicate'] ? 'YES' : 'NO') . ")" . PHP_EOL;
        }
    }
}
