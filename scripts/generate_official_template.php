<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

$spreadsheet = new Spreadsheet();

// -------------------------------------------------------------
// HOJA 1: PORTMAPPING_EQUIPOS (Datos en Bulk de Múltiples Equipos)
// -------------------------------------------------------------
$wsPorts = $spreadsheet->getActiveSheet();
$wsPorts->setTitle('PORTMAPPING_EQUIPOS');
$wsPorts->setShowGridLines(true);

// Fila 1: Grupos Superiores de Encabezado
$wsPorts->mergeCells('A1:D1');
$wsPorts->setCellValue('A1', '1. JERARQUÍA Y UBICACIÓN FÍSICA');

$wsPorts->mergeCells('E1:L1');
$wsPorts->setCellValue('E1', '2. DATOS DEL EQUIPO (CMDB CI)');

$wsPorts->mergeCells('M1:S1');
$wsPorts->setCellValue('M1', '3. PUERTOS FÍSICOS Y ESTADO DE ENLACE (LINK)');

$wsPorts->mergeCells('T1:W1');
$wsPorts->setCellValue('T1', '4. PATCH PANEL ORIGEN (CABLEADO)');

$wsPorts->mergeCells('X1:AA1');
$wsPorts->setCellValue('X1', '5. PATCH PANEL DESTINO');

$wsPorts->mergeCells('AB1:AF1');
$wsPorts->setCellValue('AB1', '6. DESTINO FINAL Y OBSERVACIONES');

// Estilos de los grupos superiores
$groupStyles = [
    'A1:D1' => ['fill' => '101B31', 'font' => 'FFFFFF'],
    'E1:L1' => ['fill' => '1E3A8A', 'font' => 'FFFFFF'],
    'M1:S1' => ['fill' => '0284C7', 'font' => 'FFFFFF'],
    'T1:W1' => ['fill' => 'D97706', 'font' => 'FFFFFF'],
    'X1:AA1' => ['fill' => '7C3AED', 'font' => 'FFFFFF'],
    'AB1:AF1' => ['fill' => '334155', 'font' => 'FFFFFF'],
];

foreach ($groupStyles as $range => $style) {
    $wsPorts->getStyle($range)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => $style['font']], 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $style['fill']]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]]
    ]);
}
$wsPorts->getRowDimension(1)->setRowHeight(24);

// Fila 2: Columnas Específicas Requeridas para Ingesta y Bulk
$headers = [
    1 => 'EMPRESA *',
    2 => 'Nombre Rack Origen *',
    3 => 'Ubicación *',
    4 => 'AREA *',
    5 => 'Equipo Origen *',
    6 => 'Nombre de equipo *',
    7 => 'Fabricante',
    8 => 'Modelo',
    9 => 'Numero de puertos *',
    10 => 'Serie *',
    11 => 'Unidad de rack *',
    12 => 'Hostname / IP',
    13 => 'Puerto *',
    14 => 'Tipo de Cable',
    15 => 'Tipo Conector',
    16 => 'Velocidad',
    17 => 'Estado Led *',
    18 => 'SFP Instalado',
    19 => 'Tipo Transceiver',
    20 => 'Patch Panel / ODF',
    21 => 'Unidad de rack',
    22 => 'Modulo',
    23 => 'Puerto / PAR',
    24 => 'Patch Panel Destino',
    25 => 'UR Destino',
    26 => 'Modulo Destino',
    27 => 'Puerto / PAR Destino',
    28 => 'Nombre Rack Destino',
    29 => 'Equipo Destino',
    30 => 'Fabricante Destino',
    31 => 'Puerto Destino',
    32 => 'Observaciones'
];

foreach ($headers as $colIdx => $text) {
    $cell = $wsPorts->getCellByColumnAndRow($colIdx, 2);
    $cell->setValue($text);
}

$wsPorts->getStyle('A2:AF2')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => '101B31'], 'size' => 9],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]]
]);
$wsPorts->getRowDimension(2)->setRowHeight(28);

// Datos de Muestra: 2 Equipos en Bulk para Demostración Clara
// EQUIPO 1: SW-ACCESO-01 (24 Puertos)
// EQUIPO 2: SW-ACCESO-02 (24 Puertos)
$sampleDevices = [
    [
        'client' => 'VILASECA',
        'rack' => 'RACK 01',
        'location' => 'BODEGA JUTECERO',
        'area' => 'OFICINA TECNICA',
        'eq_type' => 'SWITCH',
        'name' => 'SW-ACCESO-01',
        'mfg' => 'HP ARUBA',
        'model' => '1920S-24G',
        'ports' => 24,
        'serial' => 'CN89K3L4KX',
        'ur' => 7,
        'ip' => '172.16.10.11',
        'pp_name' => 'Patchpanel A',
        'pp_ur' => 11,
        'pp_mod' => 'A',
        'active_ports' => [1, 2, 4, 5, 7, 8, 10, 12, 15, 23, 24]
    ],
    [
        'client' => 'VILASECA',
        'rack' => 'RACK 01',
        'location' => 'BODEGA JUTECERO',
        'area' => 'OFICINA TECNICA',
        'eq_type' => 'SWITCH',
        'name' => 'SW-ACCESO-02',
        'mfg' => 'HP ARUBA',
        'model' => '1920S-24G',
        'ports' => 24,
        'serial' => 'CN90K5M8PZ',
        'ur' => 5,
        'ip' => '172.16.10.12',
        'pp_name' => 'Patchpanel B',
        'pp_ur' => 10,
        'pp_mod' => 'B',
        'active_ports' => [1, 3, 5, 9, 11, 13, 21, 22]
    ]
];

$currRow = 3;
foreach ($sampleDevices as $devIdx => $d) {
    for ($p = 1; $p <= $d['ports']; $p++) {
        $isActive = in_array($p, $d['active_ports']);
        $ledVal = $isActive ? ($p % 2 === 0 ? 'UP' : 'ON') : ($p % 2 === 0 ? 'DOWN' : 'OFF');
        $speed = ($p >= 23) ? '10GB' : '1GB';
        $cable = ($p >= 23) ? 'FIBRA' : 'UTP';
        $conn = ($p >= 23) ? 'LC' : 'RJ45';
        $sfp = ($p >= 23) ? 'SI' : 'NO';
        $trans = ($p >= 23) ? '10G SR' : 'NO';
        
        $destEq = $isActive ? ($p === 24 ? 'FIREWALL-CORE' : ($p === 23 ? 'SW-DISTRIB-01' : "PC-USUARIO-{$p}")) : '';
        $destPort = $isActive ? ($p >= 23 ? 'ETH 1/1' : 'ETH0') : '';
        $obs = $isActive ? 'Puerto activo en producción' : 'Disponible / Sin parchear';

        $wsPorts->setCellValueByColumnAndRow(1, $currRow, $d['client']);
        $wsPorts->setCellValueByColumnAndRow(2, $currRow, $d['rack']);
        $wsPorts->setCellValueByColumnAndRow(3, $currRow, $d['location']);
        $wsPorts->setCellValueByColumnAndRow(4, $currRow, $d['area']);
        $wsPorts->setCellValueByColumnAndRow(5, $currRow, $d['eq_type']);
        $wsPorts->setCellValueByColumnAndRow(6, $currRow, $d['name']);
        $wsPorts->setCellValueByColumnAndRow(7, $currRow, $d['mfg']);
        $wsPorts->setCellValueByColumnAndRow(8, $currRow, $d['model']);
        $wsPorts->setCellValueByColumnAndRow(9, $currRow, $d['ports']);
        $wsPorts->setCellValueByColumnAndRow(10, $currRow, $d['serial']);
        $wsPorts->setCellValueByColumnAndRow(11, $currRow, $d['ur']);
        $wsPorts->setCellValueByColumnAndRow(12, $currRow, $d['ip']);
        $wsPorts->setCellValueByColumnAndRow(13, $currRow, $p);
        $wsPorts->setCellValueByColumnAndRow(14, $currRow, $cable);
        $wsPorts->setCellValueByColumnAndRow(15, $currRow, $conn);
        $wsPorts->setCellValueByColumnAndRow(16, $currRow, $speed);
        $wsPorts->setCellValueByColumnAndRow(17, $currRow, $ledVal);
        $wsPorts->setCellValueByColumnAndRow(18, $currRow, $sfp);
        $wsPorts->setCellValueByColumnAndRow(19, $currRow, $trans);
        $wsPorts->setCellValueByColumnAndRow(20, $currRow, $d['pp_name']);
        $wsPorts->setCellValueByColumnAndRow(21, $currRow, $d['pp_ur']);
        $wsPorts->setCellValueByColumnAndRow(22, $currRow, $d['pp_mod']);
        $wsPorts->setCellValueByColumnAndRow(23, $currRow, $p);
        $wsPorts->setCellValueByColumnAndRow(24, $currRow, '');
        $wsPorts->setCellValueByColumnAndRow(25, $currRow, '');
        $wsPorts->setCellValueByColumnAndRow(26, $currRow, '');
        $wsPorts->setCellValueByColumnAndRow(27, $currRow, '');
        $wsPorts->setCellValueByColumnAndRow(28, $currRow, $d['rack']);
        $wsPorts->setCellValueByColumnAndRow(29, $currRow, $destEq);
        $wsPorts->setCellValueByColumnAndRow(30, $currRow, '');
        $wsPorts->setCellValueByColumnAndRow(31, $currRow, $destPort);
        $wsPorts->setCellValueByColumnAndRow(32, $currRow, $obs);

        // Formato condicional de fila
        $rowBg = ($devIdx % 2 === 0) ? ($p % 2 === 0 ? 'FFFFFF' : 'F8FAFC') : ($p % 2 === 0 ? 'F0FDF4' : 'E6F4EA');
        $wsPorts->getStyle("A{$currRow}:AF{$currRow}")->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']]]
        ]);
        
        // Centrar columnas clave
        $wsPorts->getStyle("A{$currRow}:E{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $wsPorts->getStyle("I{$currRow}:K{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $wsPorts->getStyle("M{$currRow}:S{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $wsPorts->getStyle("U{$currRow}:W{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Resaltar estado Led
        $ledColor = in_array(strtoupper($ledVal), ['UP', 'ON']) ? '15803D' : 'DC2626';
        $wsPorts->getStyle("Q{$currRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $ledColor]]
        ]);

        $currRow++;
    }
}

// Auto-ajustar ancho de columnas
for ($c = 1; $c <= 32; $c++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    $wsPorts->getColumnDimension($colLetter)->setAutoSize(true);
}


// -------------------------------------------------------------
// HOJA 2: RACKS_DATACENTER (Inventario de Equipos en el Rack)
// -------------------------------------------------------------
$wsRacks = $spreadsheet->createSheet();
$wsRacks->setTitle('RACKS_DATACENTER');
$wsRacks->setShowGridLines(true);

$wsRacks->mergeCells('A1:I1');
$wsRacks->setCellValue('A1', 'ELEVACIÓN FÍSICA DEL RACK (INVENTARIO EN U PARA DATACENTER)');
$wsRacks->getStyle('A1:I1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '101B31']]
]);
$wsRacks->getRowDimension(1)->setRowHeight(26);

$rackHeaders = [
    1 => 'EMPRESA *',
    2 => 'Nombre Rack Origen *',
    3 => 'Ubicación *',
    4 => 'AREA *',
    5 => 'ur totales *',
    6 => 'equipo *',
    7 => 'ur *',
    8 => 'urtequipo *',
    9 => 'Observaciones'
];

foreach ($rackHeaders as $c => $h) {
    $wsRacks->setCellValueByColumnAndRow($c, 3, $h);
}

$wsRacks->getStyle('A3:I3')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => '101B31'], 'size' => 9],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]]
]);
$wsRacks->getRowDimension(3)->setRowHeight(24);

$rackElevations = [
    ['client' => 'VILASECA', 'rack' => 'RACK 01', 'loc' => 'BODEGA JUTECERO', 'area' => 'OFICINA TECNICA', 'tot_u' => 12, 'name' => 'PATCH PANEL DE 24 - A', 'ur' => 11, 'h' => 1, 'obs' => 'Parcheo principal red datos'],
    ['client' => 'VILASECA', 'rack' => 'RACK 01', 'loc' => 'BODEGA JUTECERO', 'area' => 'OFICINA TECNICA', 'tot_u' => 12, 'name' => 'PATCH PANEL DE 24 - B', 'ur' => 10, 'h' => 1, 'obs' => 'Parcheo secundario telefonía/cámaras'],
    ['client' => 'VILASECA', 'rack' => 'RACK 01', 'loc' => 'BODEGA JUTECERO', 'area' => 'OFICINA TECNICA', 'tot_u' => 12, 'name' => 'ORGANIZADOR HORIZONTAL 1U', 'ur' => 8, 'h' => 1, 'obs' => 'Gestor de cables patch cords'],
    ['client' => 'VILASECA', 'rack' => 'RACK 01', 'loc' => 'BODEGA JUTECERO', 'area' => 'OFICINA TECNICA', 'tot_u' => 12, 'name' => 'SW-ACCESO-01 (HP 1920S)', 'ur' => 7, 'h' => 1, 'obs' => 'Switch datos piso 1'],
    ['client' => 'VILASECA', 'rack' => 'RACK 01', 'loc' => 'BODEGA JUTECERO', 'area' => 'OFICINA TECNICA', 'tot_u' => 12, 'name' => 'ORGANIZADOR HORIZONTAL 1U', 'ur' => 6, 'h' => 1, 'obs' => 'Gestor de cables'],
    ['client' => 'VILASECA', 'rack' => 'RACK 01', 'loc' => 'BODEGA JUTECERO', 'area' => 'OFICINA TECNICA', 'tot_u' => 12, 'name' => 'SW-ACCESO-02 (HP 1920S)', 'ur' => 5, 'h' => 1, 'obs' => 'Switch datos piso 2'],
    ['client' => 'VILASECA', 'rack' => 'RACK 01', 'loc' => 'BODEGA JUTECERO', 'area' => 'OFICINA TECNICA', 'tot_u' => 12, 'name' => 'PDU MURAL 8 TOMAS', 'ur' => 1, 'h' => 1, 'obs' => 'Alimentación eléctrica regulada UPS']
];

$rRow = 4;
foreach ($rackElevations as $re) {
    $wsRacks->setCellValueByColumnAndRow(1, $rRow, $re['client']);
    $wsRacks->setCellValueByColumnAndRow(2, $rRow, $re['rack']);
    $wsRacks->setCellValueByColumnAndRow(3, $rRow, $re['loc']);
    $wsRacks->setCellValueByColumnAndRow(4, $rRow, $re['area']);
    $wsRacks->setCellValueByColumnAndRow(5, $rRow, $re['tot_u']);
    $wsRacks->setCellValueByColumnAndRow(6, $rRow, $re['name']);
    $wsRacks->setCellValueByColumnAndRow(7, $rRow, $re['ur']);
    $wsRacks->setCellValueByColumnAndRow(8, $rRow, $re['h']);
    $wsRacks->setCellValueByColumnAndRow(9, $rRow, $re['obs']);

    $wsRacks->getStyle("A{$rRow}:I{$rRow}")->applyFromArray([
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CBD5E1']]]
    ]);
    $wsRacks->getStyle("A{$rRow}:E{$rRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $wsRacks->getStyle("G{$rRow}:H{$rRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $rRow++;
}

for ($c = 1; $c <= 9; $c++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    $wsRacks->getColumnDimension($colLetter)->setAutoSize(true);
}


// -------------------------------------------------------------
// HOJA 3: GUIA_Y_CATALOGO (Instrucciones, Campos Requeridos y Reglas Bulk)
// -------------------------------------------------------------
$wsGuia = $spreadsheet->createSheet();
$wsGuia->setTitle('GUIA_Y_CATALOGO');
$wsGuia->setShowGridLines(true);

$wsGuia->mergeCells('A1:F1');
$wsGuia->setCellValue('A1', 'GUÍA DE LLENADO Y ESPECIFICACIONES PARA CARGA MASIVA (BULK IMPORT)');
$wsGuia->getStyle('A1:F1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF5C05']] // Sonda Orange
]);
$wsGuia->getRowDimension(1)->setRowHeight(30);

$guiaContents = [
    ['CAMPO / COLUMNA', 'OBLIGATORIO', 'VALORES PERMITIDOS / EJEMPLO', 'EXPLICACIÓN Y REGLA DE NEGOCIO'],
    ['EMPRESA', 'SÍ (*)', 'VILASECA, GRUPO VILASECA, SONDA', 'Identificador del cliente multitenant. Si se coloca "GRUPO VILASECA", el sistema lo normaliza a "VILASECA".'],
    ['Nombre Rack Origen', 'SÍ (*)', 'RACK 01, RACK BODEGA, GABINETE 02', 'Nombre único del rack en la sede. Crea automáticamente el rack en el módulo Datacenter si no existe.'],
    ['Ubicación', 'SÍ (*)', 'BODEGA JUTECERO, PLANTA PRINCIPAL, SITE GUAYAQUIL', 'Sede o localidad física del equipo y del rack.'],
    ['AREA', 'SÍ (*)', 'OFICINA TECNICA, BODEGA, CUARTO TI, DATA CENTER', 'Área funcional dentro de la ubicación.'],
    ['Equipo Origen', 'SÍ (*)', 'SWITCH, ROUTER, FIREWALL, SERVIDOR', 'Tipo de dispositivo para catalogación en CMDB.'],
    ['Nombre de equipo', 'SÍ (*)', 'SW-ACCESO-01, SWITCH-CORE-01, RT-BODEGA', 'Nombre identificador del equipo. Si se deja vacío, se genera como Fabricante + Modelo.'],
    ['Serie', 'SÍ (*)', 'CN89K3L4KX, FCZ1234567, SIN SERIE', 'Número de serie físico. Es la clave primaria para validación de cero duplicados.'],
    ['Unidad de rack', 'SÍ (*)', '1, 2, 7, 12, 24, 42', 'Posición en Unidad de Rack (UR) donde está montado el dispositivo.'],
    ['Puerto', 'SÍ (*)', '1, 2, 3 ... 24, 48', 'Número correlativo del puerto físico.'],
    ['Estado Led / Link', 'SÍ (*)', 'UP, DOWN, ON, OFF, ACTIVO, INACTIVO', 'Estado del enlace. Soporta bivalencia: UP/ON = Conectado (verde), DOWN/OFF = Libre (rojo).'],
    ['Tipo de Cable', 'RECOMENDADO', 'UTP, FIBRA MONOMODO, FIBRA MULTIMODO, DAC', 'Medio físico del puerto.'],
    ['Tipo Conector', 'RECOMENDADO', 'RJ45, LC, SC, SFP+, FC', 'Tipo de interfaz mecánica.'],
    ['Velocidad', 'RECOMENDADO', '100MB, 1GB, 10GB, 25GB, 40GB, 100GB', 'Velocidad de sincronización del puerto.'],
    ['Patch Panel / ODF', 'OPCIONAL', 'Patchpanel A, ODF-01, FIBRA-ODF-PISO1', 'Nombre del patch panel de origen donde se conecta el patch cord.'],
    ['Puerto / PAR', 'OPCIONAL', '1, 2, 3 ... 24', 'Número de puerto en el Patch Panel.'],
    ['Observaciones', 'OPCIONAL', 'Texto descriptivo libre', 'Cualquier detalle relevante del puerto o conexión.'],
];

$gRow = 3;
foreach ($guiaContents as $idx => $rData) {
    for ($col = 1; $col <= 4; $col++) {
        $wsGuia->setCellValueByColumnAndRow($col, $gRow, $rData[$col - 1]);
    }
    
    if ($idx === 0) {
        $wsGuia->getStyle("A{$gRow}:D{$gRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '101B31'], 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CBD5E1']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]]
        ]);
        $wsGuia->getRowDimension($gRow)->setRowHeight(24);
    } else {
        $isReq = (strpos($rData[1], 'SÍ') !== false);
        $wsGuia->getStyle("A{$gRow}:D{$gRow}")->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']]]
        ]);
        $wsGuia->getStyle("A{$gRow}")->getFont()->setBold(true);
        $wsGuia->getStyle("B{$gRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $isReq ? 'DC2626' : '0284C7']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        $wsGuia->getRowDimension($gRow)->setRowHeight(22);
    }
    $gRow++;
}

// Nota especial sobre CARGA EN BULK (Múltiples Equipos)
$gRow += 2;
$wsGuia->mergeCells("A{$gRow}:D{$gRow}");
$wsGuia->setCellValue("A{$gRow}", "⚡ ¿CÓMO CARGAR MÚLTIPLES EQUIPOS EN BULK EN LA MISMA PLANTILLA?");
$wsGuia->getStyle("A{$gRow}:D{$gRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']]
]);
$wsGuia->getRowDimension($gRow)->setRowHeight(24);
$gRow++;

$bulkInstructions = [
    "1. En la hoja 'PORTMAPPING_EQUIPOS', simplemente continúe escribiendo las filas del siguiente equipo debajo del anterior.",
    "2. Cada equipo se distingue automáticamente por su Número de Serie y/o Nombre de Equipo y Unidad de Rack (UR).",
    "3. En la hoja 'RACKS_DATACENTER', liste todos los equipos pertenecientes a cada Rack para armar su elevación 2D.",
    "4. El sistema agrupará y creará cada equipo y sus puertos en una sola transacción atómica, verificando duplicados para cada uno."
];

foreach ($bulkInstructions as $bi) {
    $wsGuia->mergeCells("A{$gRow}:D{$gRow}");
    $wsGuia->setCellValue("A{$gRow}", $bi);
    $wsGuia->getStyle("A{$gRow}:D{$gRow}")->applyFromArray([
        'font' => ['size' => 9, 'color' => ['rgb' => '334155']],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ]);
    $gRow++;
}

for ($c = 1; $c <= 4; $c++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    $wsGuia->getColumnDimension($colLetter)->setAutoSize(true);
}

// Volver la hoja activa a la primera
$spreadsheet->setActiveSheetIndex(0);

// Guardar archivo oficial
$targetPath = __DIR__ . '/../portmapping/Plantilla_Oficial_Portmapping_Bulk.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($targetPath);

echo "Plantilla Oficial generada exitosamente en: " . $targetPath . " (" . filesize($targetPath) . " bytes)\n";
