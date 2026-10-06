<?php
/**
 * CMDB - Portmapping & Rack Excel Importer Engine
 * Analiza plantillas de portmapping (ej: Bodega JUTECERO), valida datos,
 * detecta duplicados y crea de forma atómica el Rack, sus componentes físicos,
 * el equipo relevado y el mapeo detallado de puertos.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PortmappingExcelImporter
{
    public static function normalizeKey(string $str): string
    {
        $unaccented = strtr(mb_strtolower(trim($str), 'UTF-8'), [
            'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n',
            'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u', 'Ñ'=>'n'
        ]);
        return preg_replace('/[^a-z0-9]/', '', $unaccented);
    }

    /**
     * Normaliza el estado de enlace desde cualquier variación de texto del Excel:
     * Soporta tanto UP / DOWN como ON / OFF, Activo / Inactivo, Conectado / Libre, etc.
     */
    public static function parseLinkStatus($val): array
    {
        $raw = trim((string)$val);
        $norm = mb_strtoupper($raw, 'UTF-8');
        
        $upKeywords = ['ON', 'UP', 'ACTIVO', 'CONECTADO', 'SI', 'SÍ', 'YES', 'TRUE', '1', 'ENABLE', 'ENABLED', 'OK'];
        $isUp = in_array($norm, $upKeywords, true);

        return [
            'is_up' => $isUp,
            'link_status' => $isUp ? 'Up' : 'Down',
            'status' => $isUp ? 'up' : 'down',
            'estado_link' => $isUp ? 'UP' : 'DOWN',
            'estado_texto' => $isUp ? 'UP / ON (Conectado)' : 'DOWN / OFF (Libre)',
            'raw' => $raw
        ];
    }

    /**
     * Analiza el archivo Excel sin escribir en la base de datos (Previsualización y Validación).
     */
    public static function analyzeExcel(string $filePath): array
    {
        $pdo = getPDO();
        $res = [
            'success' => false,
            'summary' => null,
            'errors' => [],
            'warnings' => []
        ];

        try {
            if (!file_exists($filePath)) {
                $res['errors'][] = 'El archivo no existe en la ruta temporal del servidor.';
                return $res;
            }

            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);

            $sheetCount = $spreadsheet->getSheetCount();
            if ($sheetCount === 0) {
                $res['errors'][] = 'El libro de cálculo no contiene hojas válidas.';
                return $res;
            }

            // 1. Identificar hojas de trabajo
            $portSheet = null;
            $rackSheet = null;
            $elevationSheet = null;

            for ($i = 0; $i < $sheetCount; $i++) {
                $s = $spreadsheet->getSheet($i);
                $title = mb_strtolower(trim($s->getTitle()), 'UTF-8');
                
                // Inspeccionar contenido de primeras filas
                $sampleText = '';
                for ($r = 1; $r <= min(5, $s->getHighestRow()); $r++) {
                    for ($c = 1; $c <= min(15, Coordinate::columnIndexFromString($s->getHighestColumn())); $c++) {
                        $sampleText .= ' ' . mb_strtolower((string)$s->getCellByColumnAndRow($c, $r)->getValue(), 'UTF-8');
                    }
                }

                if (strpos($sampleText, 'puerto') !== false && (strpos($sampleText, 'cable') !== false || strpos($sampleText, 'conector') !== false || strpos($sampleText, 'led') !== false)) {
                    if (!$portSheet) $portSheet = $s;
                } elseif (strpos($sampleText, 'ur totales') !== false || (strpos($sampleText, 'equipo') !== false && strpos($sampleText, 'urtequipo') !== false)) {
                    if (!$rackSheet) $rackSheet = $s;
                } elseif (strpos($sampleText, 'aereo') !== false || strpos($sampleText, 'rack') !== false && $s->getHighestRow() <= 30 && $s->getHighestColumn() <= 'H') {
                    if (!$elevationSheet) $elevationSheet = $s;
                }
            }

            // Fallback por índice si la heurística no asignó alguna hoja
            if (!$portSheet) $portSheet = $spreadsheet->getSheet(0);
            if (!$rackSheet && $sheetCount > 1) $rackSheet = $spreadsheet->getSheet(1);
            if (!$elevationSheet && $sheetCount > 2) $elevationSheet = $spreadsheet->getSheet(2);

            // 2. Procesar Hoja de Racks primero (para obtener elevación física y catálogo de dispositivos por UR)
            $parsedRackData = self::parseRackSheet($rackSheet, 'RACK 01');

            // Construir diccionario de lookup de nombres físicos en Racks [RACK_U{UR}]
            $rackLookup = [];
            if (!empty($parsedRackData['racks'])) {
                foreach ($parsedRackData['racks'] as $rkItem) {
                    $rkNameUpper = strtoupper(trim($rkItem['rack_name'] ?? ''));
                    $devs = $rkItem['devices'] ?? ($rkItem['raw_devices'] ?? []);
                    foreach ($devs as $rdItem) {
                        $rdName = trim($rdItem['name'] ?? '');
                        $rdUr = (int)($rdItem['excel_ur'] ?? ($rdItem['start_u'] ?? 0));
                        if ($rdName !== '' && $rdUr > 0) {
                            $rdUpper = strtoupper($rdName);
                            // Omitir pasivos de cableado o energía para asociar solo equipos activos
                            if (strpos($rdUpper, 'PATCH') === false && strpos($rdUpper, 'ORGANIZADOR') === false && strpos($rdUpper, 'BANDEJA') === false && strpos($rdUpper, 'REGLETA') === false && strpos($rdUpper, 'PDU') === false) {
                                $rackLookup[$rkNameUpper . '_U' . $rdUr] = $rdName;
                            }
                        }
                    }
                }
            }

            // 3. Procesar Hoja de Portmapping vinculando nombres de elevación si no están en Portmapping
            $parsedPortData = self::parsePortSheet($portSheet, $rackLookup);
            if (!empty($parsedPortData['errors'])) {
                $res['errors'] = array_merge($res['errors'], $parsedPortData['errors']);
                return $res;
            }

            // 4. Tipo de Rack desde hoja de elevación (si existe)
            $rackType = 'AEREO';
            if ($elevationSheet) {
                for ($r = 1; $r <= min(10, $elevationSheet->getHighestRow()); $r++) {
                    for ($c = 1; $c <= min(5, Coordinate::columnIndexFromString($elevationSheet->getHighestColumn())); $c++) {
                        $val = mb_strtoupper(trim((string)$elevationSheet->getCellByColumnAndRow($c, $r)->getValue()), 'UTF-8');
                        if (in_array($val, ['AEREO', 'PISO', 'BASTIDOR', 'GABINETE', 'MURAL'])) {
                            $rackType = $val;
                            break 2;
                        }
                    }
                }
            }

            // 5. Consolidación de metadatos
            $client = $parsedPortData['client'] ?: ($parsedRackData['client'] ?: 'VILASECA');
            if (stripos($client, 'VILASECA') !== false) {
                $client = 'VILASECA';
            }
            $location = $parsedPortData['location'] ?: ($parsedRackData['location'] ?: 'GENERAL');
            $area = $parsedPortData['area'] ?: ($parsedRackData['area'] ?: 'GENERAL');
            $rackName = $parsedPortData['rack_name'] ?: ($parsedRackData['rack_name'] ?: 'RACK 01');

            // 6. Detección Inteligente de Duplicados en Base de Datos (Soporte individual y bulk)
            $hasAnyDuplicate = false;
            $duplicateDetails = [];
            foreach ($parsedPortData['devices'] as &$devItem) {
                $devClient = $devItem['client'] ?: $client;
                if (stripos($devClient, 'VILASECA') !== false) $devClient = 'VILASECA';
                $devLoc = $devItem['location'] ?: $location;
                $devArea = $devItem['area'] ?: $area;
                $devRack = $devItem['rack_name'] ?: $rackName;

                $dup = self::checkDeviceDuplicate($pdo, $devClient, $devLoc, $devArea, $devRack, $devItem['serial'], $devItem['device_name'], (int)($devItem['ur'] ?? 0));
                $devItem['is_duplicate'] = !empty($dup);
                $devItem['duplicate_id'] = $dup ? (int)$dup['id'] : null;
                $devItem['duplicate_reason'] = $dup ? sprintf(
                    "El equipo '%s' (Serie: '%s') ya existe en la ubicación '%s', Rack '%s' (Levantamiento #%d).",
                    $dup['device_name'],
                    $dup['device_label'] ?: 'N/A',
                    $dup['location'],
                    $dup['rack'],
                    $dup['id']
                ) : '';
                if ($devItem['is_duplicate']) {
                    $hasAnyDuplicate = true;
                    $duplicateDetails[] = $devItem['duplicate_reason'];
                }
            }
            unset($devItem);

            $deviceDuplicate = !empty($parsedPortData['devices'][0]['is_duplicate']) ? [
                'id' => $parsedPortData['devices'][0]['duplicate_id'],
                'device_name' => $parsedPortData['devices'][0]['device_name'],
                'device_label' => $parsedPortData['devices'][0]['serial'],
                'location' => $location,
                'rack' => $rackName
            ] : null;
            $rackExisting = self::checkRackExisting($pdo, $client, $location, $rackName);

            // 7. Generar Diccionario de Mapeo Excel -> BDD para auditoría y visualización
            $databaseMapping = [
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping) / Hoja 2 (Racks)',
                    'excel_col' => 'EMPRESA',
                    'db_table' => 'dc_rooms / dc_racks / manual_portmap_surveys / ci_instances',
                    'db_field' => 'client',
                    'rule' => 'Asignación multitenant. Normaliza "GRUPO VILASECA" a "VILASECA" para sincronía de filtros',
                    'detected_value' => $client,
                    'editable' => true,
                    'input_id' => 'cust_client'
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping) / Hoja 2 (Racks)',
                    'excel_col' => 'Ubicación / Sede',
                    'db_table' => 'dc_rooms / dc_racks / manual_portmap_surveys / ci_instances',
                    'db_field' => 'location',
                    'rule' => 'Crea la sala en Datacenter (dc_rooms) si no existe y vincula todo el hardware',
                    'detected_value' => $location,
                    'editable' => true,
                    'input_id' => 'cust_location'
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping) / Hoja 2 (Racks)',
                    'excel_col' => 'AREA',
                    'db_table' => 'manual_portmap_surveys / dc_rack_devices / ci_instances',
                    'db_field' => 'area / details_json',
                    'rule' => 'Área física o departamento (ej: OFICINA, BODEGA, PLANTA)',
                    'detected_value' => $area,
                    'editable' => true,
                    'input_id' => 'cust_area'
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping) / Hoja 2 (Racks)',
                    'excel_col' => 'Nombre Rack Origen',
                    'db_table' => 'dc_racks / manual_portmap_surveys / ci_instances',
                    'db_field' => 'dc_racks.name / rack',
                    'rule' => 'Identificador del bastidor en Datacenter (dc_racks.name) y campo rack en levantamiento',
                    'detected_value' => $rackName,
                    'editable' => true,
                    'input_id' => 'cust_rack_name'
                ],
                [
                    'origin_sheet' => 'Hoja 2 (RACKS)',
                    'excel_col' => 'ur totales',
                    'db_table' => 'dc_racks',
                    'db_field' => 'total_u',
                    'rule' => 'Capacidad física vertical del rack en unidades de rack (UR)',
                    'detected_value' => ($parsedRackData['total_u'] ?: 12) . ' UR',
                    'editable' => true,
                    'input_id' => 'cust_rack_total_u'
                ],
                [
                    'origin_sheet' => 'Hoja 3 (Elevación / r)',
                    'excel_col' => 'Estructura visual',
                    'db_table' => 'dc_racks',
                    'db_field' => 'rack_type (descripción)',
                    'rule' => 'Tipo de rack detectado (AEREO, PISO, BASTIDOR, GABINETE)',
                    'detected_value' => $rackType,
                    'editable' => true,
                    'input_id' => 'cust_rack_type'
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping)',
                    'excel_col' => 'Equipo Origen / Nombre de equipo',
                    'db_table' => 'manual_portmap_surveys / ci_instances / dc_rack_devices',
                    'db_field' => 'device_name / hostname',
                    'rule' => 'Nombre del activo principal relevado y sincronizado con catálogo CMDB',
                    'detected_value' => $parsedPortData['device_name'],
                    'editable' => true,
                    'input_id' => 'cust_device_name'
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping)',
                    'excel_col' => 'Fabricante',
                    'db_table' => 'manual_portmap_surveys / ci_instances',
                    'db_field' => 'vendor / fabricante',
                    'rule' => 'Fabricante o marca del equipo relevado desde la columna Fabricante del Excel',
                    'detected_value' => $parsedPortData['manufacturer'] ?: 'Sin Fabricante',
                    'editable' => true,
                    'input_id' => 'cust_vendor'
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping)',
                    'excel_col' => 'Serie',
                    'db_table' => 'manual_portmap_surveys / ci_instances',
                    'db_field' => 'serial / numero_serie / device_label',
                    'rule' => 'Número de serie del equipo (concatenado si son múltiples). Se valida en la BDD para prevenir duplicados',
                    'detected_value' => $parsedPortData['serial'] ?: 'Sin Serie',
                    'editable' => true,
                    'input_id' => 'cust_serial'
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping)',
                    'excel_col' => 'Unidad de rack',
                    'db_table' => 'manual_portmap_surveys / dc_rack_devices',
                    'db_field' => 'ur_rack / start_u',
                    'rule' => 'Unidad vertical (UR) donde está montado el equipo en el rack',
                    'detected_value' => 'UR ' . $parsedPortData['ur'],
                    'editable' => true,
                    'input_id' => 'cust_ur'
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping)',
                    'excel_col' => 'Numero de puertos',
                    'db_table' => 'manual_portmap_surveys / ci_instances',
                    'db_field' => 'ports_count',
                    'rule' => 'Cantidad total de bocas/puertos físicos que posee el equipo',
                    'detected_value' => $parsedPortData['ports_count'] . ' Puertos',
                    'editable' => false,
                    'input_id' => null
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping)',
                    'excel_col' => 'Hostname / IP',
                    'db_table' => 'manual_portmap_surveys / ci_instances',
                    'db_field' => 'ip_address',
                    'rule' => 'Dirección IP o hostname asignado al equipo en la red',
                    'detected_value' => $parsedPortData['ip_address'] ?: 'Sin IP',
                    'editable' => true,
                    'input_id' => 'cust_ip'
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping)',
                    'excel_col' => 'Estado Led / Link (UP/DOWN u ON/OFF)',
                    'db_table' => 'manual_portmap_surveys.ports_data_json',
                    'db_field' => 'link_status / status / estado_link',
                    'rule' => 'Mapeo bivalente automático: "ON" o "UP" ➔ link_status: Up (Conectado), "OFF" o "DOWN" ➔ link_status: Down (Libre)',
                    'detected_value' => "{$parsedPortData['connected_count']} Conectados (UP/ON) / {$parsedPortData['vacant_count']} Libres (DOWN/OFF)",
                    'editable' => false,
                    'input_id' => null
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping)',
                    'excel_col' => 'Cable, Conector, Velocidad',
                    'db_table' => 'manual_portmap_surveys.ports_data_json',
                    'db_field' => 'cable_type, connector_type, speed',
                    'rule' => 'Parámetros físicos del puerto (ej: UTP Cat6A, RJ45, 1 Gbps)',
                    'detected_value' => 'UTP / RJ45 / 1GB',
                    'editable' => false,
                    'input_id' => null
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping)',
                    'excel_col' => 'Patch Panel, Modulo, Puerto/PAR',
                    'db_table' => 'manual_portmap_surveys.ports_data_json',
                    'db_field' => 'patch_panel_src, mod_pp_src, port_pp_src, dest_device',
                    'rule' => 'Puntos de conexión intermedia de parcheo y dispositivo destino',
                    'detected_value' => 'Patch Panel A/B, DVR, Routers',
                    'editable' => false,
                    'input_id' => null
                ],
                [
                    'origin_sheet' => 'Hoja 2 (RACKS)',
                    'excel_col' => 'equipo, ur, urtequipo',
                    'db_table' => 'dc_rack_devices',
                    'db_field' => 'rack_id, name, start_u, height_u',
                    'rule' => 'Generación de la elevación física completa en Datacenter (Patch panels, organizadores, switches, routers, PDUs)',
                    'detected_value' => count($parsedRackData['devices']) . ' dispositivos en rack',
                    'editable' => false,
                    'input_id' => null
                ],
                [
                    'origin_sheet' => 'Hoja 1 (Portmapping)',
                    'excel_col' => 'Todos los puertos',
                    'db_table' => 'ci_components',
                    'db_field' => 'parent_ci_id, name, attributes_json',
                    'rule' => 'Registro de cada interfaz de red como componente hijo del CI en CMDB',
                    'detected_value' => count($parsedPortData['ports']) . ' componentes CMDB',
                    'editable' => false,
                    'input_id' => null
                ]
            ];

            $res['success'] = true;
            $res['summary'] = [
                'is_bulk' => $parsedPortData['is_bulk'],
                'devices_count' => $parsedPortData['devices_count'],
                'has_duplicates' => $hasAnyDuplicate,
                'duplicate_reasons' => $duplicateDetails,
                'devices' => $parsedPortData['devices'],
                'client' => $client,
                'location' => $location,
                'area' => $area,
                'racks_count' => $parsedRackData['racks_count'] ?? 1,
                'racks' => $parsedRackData['racks'] ?? [$parsedRackData],
                'areas_count' => $parsedRackData['areas_count'] ?? 1,
                'areas' => $parsedRackData['areas'] ?? [$area],
                'has_conflicts' => !empty($parsedRackData['has_conflicts']),
                'conflicts' => $parsedRackData['conflicts'] ?? [],
                'rack' => [
                    'name' => $rackName,
                    'total_u' => $parsedRackData['total_u'] ?: 12,
                    'type' => $rackType,
                    'observations' => $parsedRackData['observations'] ?: '',
                    'devices_count' => count($parsedRackData['devices']),
                    'devices' => $parsedRackData['devices'],
                    'is_existing' => !empty($rackExisting),
                    'existing_id' => $rackExisting ? (int)$rackExisting['id'] : null
                ],
                'equipment' => [
                    'device_name' => $parsedPortData['device_name'],
                    'device_type' => $parsedPortData['device_type'],
                    'device_label' => $parsedPortData['serial'] ?: $parsedPortData['device_name'],
                    'manufacturer' => $parsedPortData['manufacturer'],
                    'model' => $parsedPortData['model'],
                    'serial' => $parsedPortData['serial'],
                    'ur' => $parsedPortData['ur'],
                    'ports_count' => $parsedPortData['ports_count'],
                    'ports_detected' => count($parsedPortData['ports']),
                    'connected_ports' => $parsedPortData['connected_count'],
                    'vacant_ports' => $parsedPortData['vacant_count'],
                    'ip_address' => $parsedPortData['ip_address'] ?: 'Sin IP',
                    'is_duplicate' => !empty($deviceDuplicate),
                    'duplicate_id' => $deviceDuplicate ? (int)$deviceDuplicate['id'] : null,
                    'duplicate_reason' => $deviceDuplicate ? sprintf(
                        "El equipo '%s' (Serie: '%s') ya existe en la ubicación '%s', Rack '%s' (Levantamiento #%d).",
                        $deviceDuplicate['device_name'],
                        $deviceDuplicate['device_label'] ?: 'N/A',
                        $deviceDuplicate['location'],
                        $deviceDuplicate['rack'],
                        $deviceDuplicate['id']
                    ) : ''
                ],
                'database_mapping' => $databaseMapping,
                'ports_preview' => array_slice($parsedPortData['ports'], 0, 10),
                'total_ports' => count($parsedPortData['ports']),
                'raw_ports' => $parsedPortData['ports']
            ];

        } catch (Throwable $e) {
            $res['success'] = false;
            $res['errors'][] = 'Error al procesar el archivo Excel: ' . $e->getMessage();
        }

        return $res;
    }

    /**
     * Ejecuta la inserción atómica en base de datos.
     * Permite recibir $customOverrides para aplicar ajustes definidos por el usuario en la interfaz antes de guardar.
     */
    public static function executeImport(string $filePath, bool $overwriteDuplicate = false, array $customOverrides = []): array
    {
        $analysis = self::analyzeExcel($filePath);
        if (!$analysis['success']) {
            return ['success' => false, 'error' => implode(' | ', $analysis['errors'])];
        }

        $summary = $analysis['summary'];
        $pdo = getPDO();

        // Control estricto de duplicados
        $isDuplicate = !empty($summary['is_bulk']) 
            ? !empty($summary['has_duplicates']) 
            : !empty($summary['equipment']['is_duplicate']);

        if ($isDuplicate && !$overwriteDuplicate) {
            $reasons = !empty($summary['duplicate_reasons']) 
                ? $summary['duplicate_reasons'] 
                : [($summary['equipment']['duplicate_reason'] ?? 'Equipo ya registrado')];
            return [
                'success' => false,
                'is_duplicate' => true,
                'duplicate_id' => $summary['equipment']['duplicate_id'] ?? null,
                'duplicate_reasons' => $reasons,
                'error' => 'Se detectaron equipos existentes en la base de datos: ' . implode(' | ', array_slice($reasons, 0, 3)) . '. Debe confirmar la opción de sobrescribir para actualizarlos.'
            ];
        }

        try {
            $pdo->beginTransaction();

            $roomCache = [];
            $rackCache = [];
            $importedRacks = [];
            $importedDevices = [];
            $totalPortsImported = 0;
            $creationDate = date('Y-m-d');

            // 1. SINCRONIZAR TODOS LOS RACKS Y SU HARDWARE FÍSICO (dc_rooms, dc_racks, dc_rack_devices)
            $racksToImport = !empty($summary['racks']) ? $summary['racks'] : [$summary['rack']];
            $isFirstRack = true;

            foreach ($racksToImport as $rkData) {
                $rkClient = $rkData['client'] ?: $summary['client'];
                if ($isFirstRack && !empty($customOverrides['client'])) {
                    $rkClient = trim((string)$customOverrides['client']);
                }
                if (stripos($rkClient, 'VILASECA') !== false) {
                    $rkClient = 'VILASECA';
                }

                $rkLoc = $rkData['location'] ?: $summary['location'];
                if ($isFirstRack && !empty($customOverrides['location'])) {
                    $rkLoc = trim((string)$customOverrides['location']);
                }

                $rkArea = $rkData['area'] ?: $summary['area'];
                if ($isFirstRack && !empty($customOverrides['area'])) {
                    $rkArea = trim((string)$customOverrides['area']);
                }

                $isMultiRack = count($racksToImport) > 1;
                $rkName = $rkData['name'] ?: ($rkData['rack_name'] ?? 'RACK 01');
                if (!$isMultiRack && $isFirstRack && !empty($customOverrides['rack_name'])) {
                    $rkName = trim((string)$customOverrides['rack_name']);
                }

                $rkTotU = (int)($rkData['total_u'] ?? 12);
                if (!$isMultiRack && $isFirstRack && !empty($customOverrides['rack_total_u']) && (int)$customOverrides['rack_total_u'] > 0) {
                    $rkTotU = (int)$customOverrides['rack_total_u'];
                }
                if ($rkTotU <= 0) $rkTotU = 12;

                $rkObs = $rkData['observations'] ?? '';
                $rkDir = $rkData['numbering_dir'] ?? 'DOWN';

                // A. Sincronizar Sala / Cuarto en Datacenter (dc_rooms)
                $roomKey = strtoupper($rkClient . '___' . $rkLoc . '___' . $rkArea);
                if (!isset($roomCache[$roomKey])) {
                    $stmt_room = $pdo->prepare("SELECT id FROM dc_rooms WHERE UPPER(client) LIKE UPPER(?) AND UPPER(location) = UPPER(?) AND UPPER(name) = UPPER(?) LIMIT 1");
                    $stmt_room->execute(['%' . $rkClient . '%', $rkLoc, $rkArea]);
                    $roomId = $stmt_room->fetchColumn();

                    if (!$roomId) {
                        $stmt_room2 = $pdo->prepare("SELECT id FROM dc_rooms WHERE UPPER(client) LIKE UPPER(?) AND UPPER(name) = UPPER(?) LIMIT 1");
                        $stmt_room2->execute(['%' . $rkClient . '%', $rkArea]);
                        $roomId = $stmt_room2->fetchColumn();
                    }

                    if (!$roomId) {
                        $stmt_ins_room = $pdo->prepare("INSERT INTO dc_rooms (name, client, city, location, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt_ins_room->execute([$rkArea, $rkClient, $rkLoc, $rkLoc]);
                        $roomId = (int)$pdo->lastInsertId();
                    }
                    $roomCache[$roomKey] = (int)$roomId;
                }
                $roomId = $roomCache[$roomKey];

                // B. Sincronizar Rack (dc_racks)
                $stmt_rk = $pdo->prepare("SELECT id FROM dc_racks WHERE UPPER(client) LIKE UPPER(?) AND UPPER(location) = UPPER(?) AND UPPER(name) = UPPER(?) LIMIT 1");
                $stmt_rk->execute(['%' . $rkClient . '%', $rkLoc, $rkName]);
                $rackId = $stmt_rk->fetchColumn();

                if ($rackId) {
                    $stmt_up_rk = $pdo->prepare("UPDATE dc_racks SET room_id = ?, total_u = ?, numbering_dir = ?, description = ? WHERE id = ?");
                    $stmt_up_rk->execute([$roomId, $rkTotU, $rkDir, $rkObs, $rackId]);
                    $rackId = (int)$rackId;
                } else {
                    $stmt_ins_rk = $pdo->prepare("INSERT INTO dc_racks (room_id, client, city, location, name, total_u, numbering_dir, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt_ins_rk->execute([$roomId, $rkClient, $rkLoc, $rkLoc, $rkName, $rkTotU, $rkDir, $rkObs]);
                    $rackId = (int)$pdo->lastInsertId();
                }

                // Registrar en caché para asociación con equipos
                $rackCache[strtoupper($rkClient . '___' . $rkLoc . '___' . $rkName)] = $rackId;
                $rackCache[strtoupper($rkLoc . '___' . $rkName)] = $rackId;
                $rackCache[strtoupper($rkName)] = $rackId;

                // C. Elevación de Hardware en Rack (dc_rack_devices)
                $rackDevices = $rkData['devices'] ?? [];
                if (!empty($rackDevices)) {
                    if ($overwriteDuplicate) {
                        $pdo->prepare("DELETE FROM dc_rack_devices WHERE rack_id = ?")->execute([$rackId]);
                    }
                    $stmt_ins_rd = $pdo->prepare("INSERT INTO dc_rack_devices (rack_id, name, start_u, height_u, orientation, details_json, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    foreach ($rackDevices as $rd) {
                        $rdOrientation = $rd['orientation'] ?? 'front';
                        $rdMounting = $rd['mounting'] ?? 'horizontal';
                        $det = json_encode([
                            'area' => $rkArea,
                            'client' => $rkClient,
                            'location' => $rkLoc,
                            'source' => 'excel_portmapping_import',
                            'excel_ur' => $rd['excel_ur'] ?? $rd['start_u'],
                            'orientation' => $rdOrientation,
                            'mounting' => $rdMounting,
                            'is_vertical' => !empty($rd['is_vertical']),
                            'observations' => $rd['observations'] ?? ''
                        ]);
                        $stmt_ins_rd->execute([$rackId, $rd['name'], $rd['start_u'], $rd['height_u'], $rdOrientation, $det]);
                    }
                }

                $importedRacks[] = [
                    'id' => $rackId,
                    'name' => $rkName,
                    'area' => $rkArea,
                    'location' => $rkLoc,
                    'total_u' => $rkTotU,
                    'devices_count' => count($rackDevices)
                ];

                $isFirstRack = false;
            }

            // 2. SINCRONIZAR EQUIPOS ACTIVOS Y PUERTOS DE PORTMAPPING (manual_portmap_surveys, ci_instances, ci_components)
            $devicesToImport = !empty($summary['devices']) ? $summary['devices'] : [$summary['equipment']];
            $isFirstDev = true;

            foreach ($devicesToImport as $dev) {
                $devClient = $dev['client'] ?: $summary['client'];
                if ($isFirstDev && !empty($customOverrides['client'])) {
                    $devClient = trim((string)$customOverrides['client']);
                }
                if (stripos($devClient, 'VILASECA') !== false) {
                    $devClient = 'VILASECA';
                }

                $devLoc = $dev['location'] ?: $summary['location'];
                if ($isFirstDev && !empty($customOverrides['location'])) {
                    $devLoc = trim((string)$customOverrides['location']);
                }

                $devArea = $dev['area'] ?: $summary['area'];
                if ($isFirstDev && !empty($customOverrides['area'])) {
                    $devArea = trim((string)$customOverrides['area']);
                }

                $isBulkDevices = !empty($summary['is_bulk']) && count($devicesToImport) > 1;
                $devRackName = $dev['rack_name'] ?: ($summary['rack']['name'] ?: 'RACK 01');
                if (!$isBulkDevices && $isFirstDev && !empty($customOverrides['rack_name'])) {
                    $devRackName = trim((string)$customOverrides['rack_name']);
                }

                $devUr = $dev['ur'] ?: 1;
                if (!$isBulkDevices && $isFirstDev && !empty($customOverrides['ur']) && (int)$customOverrides['ur'] > 0) {
                    $devUr = (int)$customOverrides['ur'];
                }

                $devName = $dev['device_name'];
                if (!$isBulkDevices && $isFirstDev && !empty($customOverrides['device_name'])) {
                    $devName = trim((string)$customOverrides['device_name']);
                }

                $devSerial = $dev['serial'];
                if (!$isBulkDevices && $isFirstDev && !empty($customOverrides['serial'])) {
                    $devSerial = trim((string)$customOverrides['serial']);
                }

                $devVendor = $dev['manufacturer'] ?: 'Cisco';
                if (!$isBulkDevices && $isFirstDev && !empty($customOverrides['vendor'])) {
                    $devVendor = trim((string)$customOverrides['vendor']);
                }

                $devType = $dev['device_type'] ?: 'Switch';
                if (!$isBulkDevices && $isFirstDev && !empty($customOverrides['device_type'])) {
                    $devType = trim((string)$customOverrides['device_type']);
                }

                $devIp = $dev['ip_address'] ?: 'Sin IP';
                if ($isFirstDev && isset($customOverrides['ip_address']) && trim((string)$customOverrides['ip_address']) !== '') {
                    $devIp = trim((string)$customOverrides['ip_address']);
                }

                $devPorts = $dev['ports'] ?? ($summary['raw_ports'] ?? []);

                // Asociar con el Rack correspondiente
                $rkKey = strtoupper($devClient . '___' . $devLoc . '___' . $devRackName);
                $matchedRackId = $rackCache[$rkKey] ?? ($rackCache[strtoupper($devLoc . '___' . $devRackName)] ?? ($rackCache[strtoupper($devRackName)] ?? null));

                // Construir matriz ports_data_json completa
                $excelPortsMap = [];
                foreach ($devPorts as $p) {
                    $excelPortsMap[(int)$p['port']] = $p;
                }

                $totalSwitchPorts = max((int)($dev['ports_count'] ?? 24), count($devPorts), 1);
                $formattedPorts = [];
                $connectedCount = 0;
                $vacantCount = 0;

                for ($pIdx = 1; $pIdx <= $totalSwitchPorts; $pIdx++) {
                    if (isset($excelPortsMap[$pIdx])) {
                        $p = $excelPortsMap[$pIdx];
                        $isUp = !empty($p['is_up']);
                        $destPP = trim((string)($p['dest_patch_panel'] ?? ''));
                        $destUR = trim((string)($p['dest_ur'] ?? ''));
                        $destMod = trim((string)($p['dest_module'] ?? ''));
                        $destPar = trim((string)($p['dest_par'] ?? ''));
                        $destDev = trim((string)($p['dest_device'] ?? ''));
                        $destPort = trim((string)($p['dest_port'] ?? ''));
                        $destRack = trim((string)($p['dest_rack'] ?? '')) ?: $devRackName;
                        $rawLed = trim((string)($p['led'] ?? ($isUp ? 'ON' : 'OFF')));
                        $speed = trim((string)($p['speed'] ?? '')) ?: '1 Gbps';
                        $cable = trim((string)($p['cable'] ?? '')) ?: 'UTP Cat6A';
                        $conn = trim((string)($p['connector'] ?? '')) ?: 'RJ45';
                        $obs = trim((string)($p['observations'] ?? ''));
                        $sfp = (strtoupper(trim((string)($p['sfp'] ?? ''))) === 'SI') ? 'Si' : 'No';
                        $trans = trim((string)($p['transceiver'] ?? '')) ?: 'N/A';
                    } else {
                        $isUp = false;
                        $destPP = '';
                        $destUR = '';
                        $destMod = '';
                        $destPar = '';
                        $destDev = '';
                        $destPort = '';
                        $destRack = $devRackName;
                        $rawLed = 'OFF';
                        $speed = '1 Gbps';
                        $cable = 'UTP Cat6A';
                        $conn = 'RJ45';
                        $obs = 'Puerto libre / sin parcheo';
                        $sfp = 'No';
                        $trans = 'N/A';
                    }

                    if ($isUp) $connectedCount++;
                    else $vacantCount++;

                    $destDeviceUnified = $destPP ?: $destDev;
                    $destPortUnified = $destPar ?: $destPort;

                    $formattedPorts[] = [
                        'rack_src' => $devRackName,
                        'location_src' => $devLoc,
                        'device_src' => $devName,
                        'vendor_src' => $devVendor,
                        'model_src' => $dev['model'] ?? $devType,
                        'serial_src' => $devSerial ?: '',
                        'ur_src' => (string)$devUr,
                        'hostname_src' => $devIp,
                        'port_name' => (string)$pIdx,
                        'nomenclature' => 'Gi1/0/' . $pIdx,
                        'cable_type' => $cable,
                        'connector_type' => $conn,
                        'speed' => $speed,
                        'link_status' => $isUp ? 'Up' : 'Down',
                        'status' => $isUp ? 'up' : 'down',
                        'estado_link' => $isUp ? 'UP' : 'DOWN',
                        'link_raw' => $rawLed,
                        'sfp_installed' => $sfp,
                        'transceiver_type' => $trans,
                        'patch_panel_src' => $destPP,
                        'ur_pp_src' => $destUR,
                        'mod_pp_src' => $destMod,
                        'port_pp_src' => $destPar,
                        'patch_panel_tgt' => $destPP,
                        'ur_pp_tgt' => $destUR,
                        'mod_pp_tgt' => $destMod,
                        'port_pp_tgt' => $destPar,
                        'rack_tgt' => $destRack,
                        'dest_device' => $destDeviceUnified,
                        'dest_dev' => $destDeviceUnified,
                        'dest_port' => $destPortUnified,
                        'dest_rack' => $destRack,
                        'dest_ur' => $destUR,
                        'dest_module' => $destMod,
                        'cable_color' => '#3b82f6',
                        'notes' => $obs ?: ($isUp ? 'Conexión activa verificada' : 'Puerto libre / sin parcheo')
                    ];
                }
                $portsJson = json_encode($formattedPorts);

                // Insertar o actualizar manual_portmap_surveys
                $surveyId = null;
                $desc = 'Levantamiento importado automáticamente desde plantilla Excel.';

                if (!empty($dev['is_duplicate']) && $overwriteDuplicate && !empty($dev['duplicate_id'])) {
                    $surveyId = (int)$dev['duplicate_id'];
                    $stmt_up_surv = $pdo->prepare("UPDATE manual_portmap_surveys SET 
                        client = ?, location = ?, area = ?, device_name = ?, device_label = ?, device_type = ?, 
                        vendor = ?, serial = ?, ports_count = ?, rack = ?, ur_rack = ?, ip_address = ?, creation_date = ?, 
                        description = ?, ports_data_json = ?, updated_at = NOW() WHERE id = ?");
                    $stmt_up_surv->execute([
                        $devClient, $devLoc, $devArea, $devName, ($devSerial ?: $devName), $devType,
                        $devVendor, $devSerial,
                        $totalSwitchPorts, $devRackName, (string)$devUr, $devIp,
                        $creationDate, $desc, $portsJson, $surveyId
                    ]);
                } else {
                    $stmt_ins_surv = $pdo->prepare("INSERT INTO manual_portmap_surveys 
                        (client, location, area, device_name, device_label, device_type, vendor, serial, ports_count, rack, ur_rack, ip_address, creation_date, description, ports_data_json, images_json, config_files_json, diagram_json, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '[]', '[]', '{}', NOW(), NOW())");
                    $stmt_ins_surv->execute([
                        $devClient, $devLoc, $devArea, $devName, ($devSerial ?: $devName), $devType,
                        $devVendor, $devSerial,
                        $totalSwitchPorts, $devRackName, (string)$devUr, $devIp,
                        $creationDate, $desc, $portsJson
                    ]);
                    $surveyId = (int)$pdo->lastInsertId();
                }

                // Sincronizar catálogo CMDB ci_instances y ci_components
                $stmt_ci = $pdo->prepare("SELECT id FROM ci_instances WHERE hostname = ?");
                $stmt_ci->execute([$devName]);
                $ciId = $stmt_ci->fetchColumn();

                $catId = (strtolower($devType) === 'switch') ? 1 : 39;
                $ciAttrs = json_encode([
                    'rack' => $devRackName,
                    'rack_id' => $matchedRackId,
                    'ur_rack' => (string)$devUr,
                    'area' => $devArea,
                    'client' => $devClient,
                    'location' => $devLoc,
                    'ports_count' => $totalSwitchPorts,
                    'vendor' => $devVendor,
                    'serial' => $devSerial,
                    'manual_survey_id' => $surveyId
                ]);

                if ($ciId) {
                    $pdo->prepare("UPDATE ci_instances SET category_id = ?, description = ?, attributes_json = ? WHERE id = ?")
                        ->execute([$catId, $desc, $ciAttrs, $ciId]);
                } else {
                    $pdo->prepare("INSERT INTO ci_instances (category_id, hostname, ip_address, source, status, description, attributes_json) VALUES (?, ?, ?, 'manual', 'Activo', ?, ?)")
                        ->execute([$catId, $devName, $devIp, $desc, $ciAttrs]);
                    $ciId = (int)$pdo->lastInsertId();
                }

                foreach ($formattedPorts as $fp) {
                    $pName = $fp['port_name'];
                    $stmt_c = $pdo->prepare("SELECT id FROM ci_components WHERE parent_ci_id = ? AND name = ?");
                    $stmt_c->execute([$ciId, $pName]);
                    if (!$stmt_c->fetchColumn()) {
                        $pdo->prepare("INSERT INTO ci_components (parent_ci_id, name, attributes_json) VALUES (?, ?, ?)")
                            ->execute([$ciId, $pName, json_encode(['imported' => true, 'cable' => $fp['cable_type']])]);
                    }
                }

                // Vincular dc_rack_devices con la referencia al survey
                if ($matchedRackId && $surveyId) {
                    $stmt_link = $pdo->prepare("UPDATE dc_rack_devices SET cmdb_reference = ? WHERE rack_id = ? AND (UPPER(name) LIKE UPPER(?) OR start_u = ?)");
                    $stmt_link->execute(['SURVEY_' . $surveyId, $matchedRackId, '%' . $devName . '%', $devUr]);
                }

                $totalPortsImported += count($formattedPorts);
                $importedDevices[] = [
                    'device_name' => $devName,
                    'serial' => $devSerial,
                    'rack' => $devRackName,
                    'rack_id' => $matchedRackId,
                    'location' => $devLoc,
                    'ur' => $devUr,
                    'ports_count' => count($formattedPorts),
                    'survey_id' => $surveyId,
                    'ci_id' => $ciId
                ];

                $isFirstDev = false;
            }

            $pdo->commit();

            $firstImportedRack = !empty($importedRacks) ? $importedRacks[0] : null;
            $firstImportedDev = !empty($importedDevices) ? $importedDevices[0] : null;

            return [
                'success' => true,
                'is_bulk' => count($importedDevices) > 1 || count($importedRacks) > 1,
                'racks_count' => count($importedRacks),
                'imported_racks' => $importedRacks,
                'devices_count' => count($importedDevices),
                'total_ports' => $totalPortsImported,
                'imported_devices' => $importedDevices,
                'survey_id' => $firstImportedDev['survey_id'] ?? null,
                'rack_id' => $firstImportedRack['id'] ?? null,
                'client' => $summary['client'],
                'location' => $summary['location'],
                'rack_name' => $firstImportedRack['name'] ?? ($summary['rack']['name'] ?? 'RACK 01'),
                'device_name' => $firstImportedDev['device_name'] ?? ($summary['equipment']['device_name'] ?? ''),
                'message' => "Importación completada exitosamente. Se sincronizaron " . count($importedRacks) . " rack(s), " . count($importedDevices) . " equipo(s) y " . $totalPortsImported . " puertos en Datacenter y CMDB."
            ];

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => 'Error durante el registro en base de datos: ' . $ex->getMessage()];
        }
    }

    /**
     * Parsea la hoja de detalle de puertos y equipo principal.
     */
    private static function parsePortSheet($ws, array $rackLookup = []): array
    {
        $res = [
            'client' => '',
            'location' => '',
            'area' => '',
            'rack_name' => '',
            'device_name' => '',
            'device_type' => 'Switch',
            'manufacturer' => '',
            'model' => '',
            'serial' => '',
            'ur' => 1,
            'ports_count' => 24,
            'ip_address' => '',
            'connected_count' => 0,
            'vacant_count' => 0,
            'ports' => [],
            'errors' => []
        ];

        if (!$ws) {
            $res['errors'][] = 'No se encontró la hoja de datos de Portmapping.';
            return $res;
        }

        $highestRow = $ws->getHighestRow();
        $highestCol = Coordinate::columnIndexFromString($ws->getHighestColumn());

        // Localizar fila de encabezados buscando palabras clave
        $headerRow = 2;
        for ($r = 1; $r <= min(5, $highestRow); $r++) {
            $rowStr = '';
            for ($c = 1; $c <= min(15, $highestCol); $c++) {
                $rowStr .= ' ' . mb_strtolower((string)$ws->getCellByColumnAndRow($c, $r)->getValue(), 'UTF-8');
            }
            if (strpos($rowStr, 'empresa') !== false && strpos($rowStr, 'puerto') !== false) {
                $headerRow = $r;
                break;
            }
        }

        // Mapear columnas normalizadas
        $colIndex = [];
        for ($c = 1; $c <= $highestCol; $c++) {
            $val = trim((string)$ws->getCellByColumnAndRow($c, $headerRow)->getValue());
            $norm = self::normalizeKey($val);
            if ($norm !== '') {
                if (!isset($colIndex[$norm])) {
                    $colIndex[$norm] = $c;
                }
            }
        }

        // Validar columnas obligatorias mínimas
        $reqKeys = ['empresa', 'nombrerackorigen', 'ubicacion', 'puerto'];
        foreach ($reqKeys as $rk) {
            if (!isset($colIndex[$rk])) {
                // Comprobaciones alternativas
                if ($rk === 'nombrerackorigen' && (isset($colIndex['rack']) || isset($colIndex['rackorigen']))) continue;
                if ($rk === 'ubicacion' && isset($colIndex['sede'])) continue;
                $res['errors'][] = "La hoja de Portmapping no contiene la columna requerida: '{$rk}'.";
            }
        }

        if (!empty($res['errors'])) return $res;

        $cEmpresa = $colIndex['empresa'] ?? 1;
        $cRack = $colIndex['nombrerackorigen'] ?? ($colIndex['rack'] ?? 2);
        $cUbicacion = $colIndex['ubicacion'] ?? ($colIndex['sede'] ?? 3);
        $cArea = $colIndex['area'] ?? 4;
        $cTipoEq = $colIndex['equipoorigen'] ?? 5;
        $cNombreEq = $colIndex['nombredeequipo'] ?? 6;
        $cFab = $colIndex['fabricante'] ?? 7;
        $cMod = $colIndex['modelo'] ?? 8;
        $cNumPuertos = $colIndex['numerodepuertos'] ?? 9;
        $cSerie = $colIndex['serie'] ?? 10;
        $cUR = $colIndex['unidadderack'] ?? 11;
        $cHost = $colIndex['hostname'] ?? 12;
        $cPuerto = $colIndex['puerto'] ?? 13;
        $cCable = $colIndex['tipodecable'] ?? 14;
        $cConector = $colIndex['tipoconector'] ?? 15;
        $cVel = $colIndex['velocidad'] ?? 16;
        $cLed = $colIndex['estadolink'] ?? ($colIndex['estadoled'] ?? ($colIndex['link'] ?? ($colIndex['status'] ?? ($colIndex['estado'] ?? ($colIndex['estadodelpuerto'] ?? ($colIndex['estadodelink'] ?? ($colIndex['estadopuerto'] ?? ($colIndex['linkstatus'] ?? 17))))))));
        $cSfp = $colIndex['sfpinstalado'] ?? 18;
        $cTrans = $colIndex['tipotransceiver'] ?? 19;
        
        // Destino
        $cDestPP = $colIndex['patchpanelodf'] ?? 20;
        $cDestUR = 21;
        $cDestMod = $colIndex['modulo'] ?? 22;
        $cDestPar = $colIndex['puertopar'] ?? 23;

        // Iterar filas y agrupar puertos por equipo (Soporte Carga Individual y Bulk Masivo)
        $startDataRow = $headerRow + 1;
        $devicesMap = [];
        $lastClient = '';
        $lastLocation = '';
        $lastArea = '';
        $lastRack = '';
        $lastType = 'Switch';
        $lastMfg = '';
        $lastModel = '';
        $lastSerial = '';
        $lastUr = 1;
        $lastPortsCount = 24;
        $lastHost = '';
        $lastCustomName = '';
        $lastDevKey = null;
        $lastPortNum = 0;

        for ($r = $startDataRow; $r <= $highestRow; $r++) {
            $pNum = $ws->getCellByColumnAndRow($cPuerto, $r)->getValue();
            if ($pNum === null || trim((string)$pNum) === '') continue;

            $rowClient = trim((string)$ws->getCellByColumnAndRow($cEmpresa, $r)->getValue());
            $rowRack = trim((string)$ws->getCellByColumnAndRow($cRack, $r)->getValue());
            $rowLoc = trim((string)$ws->getCellByColumnAndRow($cUbicacion, $r)->getValue());
            $rowArea = trim((string)$ws->getCellByColumnAndRow($cArea, $r)->getValue());
            $rowTipo = trim((string)$ws->getCellByColumnAndRow($cTipoEq, $r)->getValue());
            $rowCustName = trim((string)$ws->getCellByColumnAndRow($cNombreEq, $r)->getValue());
            $rowFab = trim((string)$ws->getCellByColumnAndRow($cFab, $r)->getValue());
            $rowMod = trim((string)$ws->getCellByColumnAndRow($cMod, $r)->getValue());
            $rowNumP = $ws->getCellByColumnAndRow($cNumPuertos, $r)->getValue();
            $rowSerial = trim((string)$ws->getCellByColumnAndRow($cSerie, $r)->getValue());
            $rowUrVal = $ws->getCellByColumnAndRow($cUR, $r)->getValue();
            $rowHost = trim((string)$ws->getCellByColumnAndRow($cHost, $r)->getValue());

            // Detectar transición hacia un nuevo dispositivo físico
            $isNewDevice = false;
            if ($lastDevKey === null) {
                $isNewDevice = true;
            } else {
                if ($rowRack !== '' && $lastRack !== '' && strtoupper($rowRack) !== strtoupper($lastRack)) {
                    $isNewDevice = true;
                } elseif ($rowUrVal !== null && trim((string)$rowUrVal) !== '' && $lastUr !== null && (int)$rowUrVal !== (int)$lastUr) {
                    $isNewDevice = true;
                } elseif ($rowSerial !== '' && $lastSerial !== '' && strtoupper($rowSerial) !== 'SIN SERIE' && strtoupper($lastSerial) !== 'SIN SERIE' && strtoupper($rowSerial) !== 'N/A' && strtoupper($lastSerial) !== 'N/A' && strtoupper($rowSerial) !== strtoupper($lastSerial)) {
                    $isNewDevice = true;
                } elseif ((int)$pNum === 1 && $lastPortNum > 1 && $lastDevKey !== null && isset($devicesMap[$lastDevKey]) && count($devicesMap[$lastDevKey]['ports']) >= 1) {
                    $isNewDevice = true;
                } elseif ($rowCustName !== '' && $lastCustomName !== '' && strtoupper($rowCustName) !== strtoupper($lastCustomName)) {
                    $isNewDevice = true;
                } elseif ($rowMod !== '' && $lastModel !== '' && strtoupper($rowMod) !== strtoupper($lastModel)) {
                    $isNewDevice = true;
                }
            }

            if ($isNewDevice) {
                if ($rowClient !== '') $lastClient = $rowClient;
                if ($rowLoc !== '') $lastLocation = $rowLoc;
                if ($rowArea !== '') $lastArea = $rowArea;
                if ($rowRack !== '') $lastRack = $rowRack;
                if ($rowTipo !== '') $lastType = $rowTipo;
                if ($rowFab !== '') $lastMfg = $rowFab;
                if ($rowMod !== '') $lastModel = $rowMod;
                $lastSerial = $rowSerial;
                if ($rowUrVal !== null && trim((string)$rowUrVal) !== '') $lastUr = (int)$rowUrVal;
                if ($rowNumP !== null && trim((string)$rowNumP) !== '') $lastPortsCount = (int)$rowNumP;
                $lastHost = $rowHost;
                $lastCustomName = $rowCustName; // Limpiar si la fila actual no especifica nombre personalizado
            } else {
                if ($rowClient !== '') $lastClient = $rowClient;
                if ($rowLoc !== '') $lastLocation = $rowLoc;
                if ($rowArea !== '') $lastArea = $rowArea;
                if ($rowRack !== '') $lastRack = $rowRack;
                if ($rowTipo !== '') $lastType = $rowTipo;
                if ($rowFab !== '') $lastMfg = $rowFab;
                if ($rowMod !== '') $lastModel = $rowMod;
                if ($rowSerial !== '') $lastSerial = $rowSerial;
                if ($rowUrVal !== null && trim((string)$rowUrVal) !== '') $lastUr = (int)$rowUrVal;
                if ($rowNumP !== null && trim((string)$rowNumP) !== '') $lastPortsCount = (int)$rowNumP;
                if ($rowHost !== '') $lastHost = $rowHost;
                if ($rowCustName !== '') $lastCustomName = $rowCustName;
            }

            // Clave única del dispositivo para agrupar en bulk
            $devKey = '';
            if ($lastSerial !== '' && strtoupper($lastSerial) !== 'N/A' && strtoupper($lastSerial) !== 'SIN SERIE') {
                $devKey = 'SERIE_' . strtoupper($lastSerial);
            } elseif ($lastCustomName !== '') {
                $devKey = 'NAME_' . strtoupper($lastCustomName) . '_U' . $lastUr . '_' . strtoupper($lastRack);
            } else {
                $devKey = 'DEV_' . strtoupper($lastMfg . '_' . $lastModel) . '_U' . $lastUr . '_' . strtoupper($lastRack);
            }

            // Si el puerto se reinicia (ej: era puerto > 8 y ahora es puerto 1), pero el devKey no cambió (porque no se llenó serie distinta), forzar nuevo devKey
            if ((int)$pNum === 1 && $lastDevKey !== null && $lastDevKey === $devKey && isset($devicesMap[$devKey]) && count($devicesMap[$devKey]['ports']) >= 8) {
                $devKey .= '_DEV' . (count($devicesMap) + 1);
            }

            $lastDevKey = $devKey;
            $lastPortNum = (int)$pNum;

            if (!isset($devicesMap[$devKey])) {
                $computedDevName = $lastCustomName;
                if ($computedDevName === '') {
                    $lookupKey = strtoupper(trim($lastRack) . '_U' . $lastUr);
                    if (!empty($rackLookup[$lookupKey])) {
                        $computedDevName = $rackLookup[$lookupKey];
                    } elseif (trim($lastMfg . ' ' . $lastModel) !== '') {
                        $computedDevName = trim($lastMfg . ' ' . $lastModel);
                    } else {
                        $computedDevName = 'EQUIPO-' . ($lastRack ?: 'RACK01') . '-U' . $lastUr;
                    }
                }

                $devicesMap[$devKey] = [
                    'device_key' => $devKey,
                    'client' => $lastClient,
                    'location' => $lastLocation,
                    'area' => $lastArea,
                    'rack_name' => $lastRack ?: 'RACK 01',
                    'device_type' => $lastType ?: 'Switch',
                    'manufacturer' => $lastMfg,
                    'model' => $lastModel,
                    'serial' => $lastSerial,
                    'all_serials' => ($lastSerial !== '' && strtoupper($lastSerial) !== 'N/A' && strtoupper($lastSerial) !== 'SIN SERIE') ? [$lastSerial] : [],
                    'all_manufacturers' => ($lastMfg !== '' && strtoupper($lastMfg) !== 'N/A') ? [$lastMfg] : [],
                    'ur' => $lastUr ?: 1,
                    'ports_count' => $lastPortsCount ?: 24,
                    'ip_address' => $lastHost,
                    'device_name' => $computedDevName,
                    'connected_count' => 0,
                    'vacant_count' => 0,
                    'ports' => []
                ];
            }

            // Acumular todas las series y fabricantes únicos de las filas del equipo
            if ($rowSerial !== '' && strtoupper($rowSerial) !== 'N/A' && strtoupper($rowSerial) !== 'SIN SERIE') {
                if (!in_array($rowSerial, $devicesMap[$devKey]['all_serials'])) {
                    $devicesMap[$devKey]['all_serials'][] = $rowSerial;
                }
            }
            if ($rowFab !== '' && strtoupper($rowFab) !== 'N/A') {
                if (!in_array($rowFab, $devicesMap[$devKey]['all_manufacturers'])) {
                    $devicesMap[$devKey]['all_manufacturers'][] = $rowFab;
                }
            }

            $rawLed = (string)$ws->getCellByColumnAndRow($cLed, $r)->getValue();
            $linkInfo = self::parseLinkStatus($rawLed);
            if ($linkInfo['is_up']) {
                $devicesMap[$devKey]['connected_count']++;
            } else {
                $devicesMap[$devKey]['vacant_count']++;
            }

            $devicesMap[$devKey]['ports'][] = [
                'port' => (int)$pNum,
                'cable' => trim((string)$ws->getCellByColumnAndRow($cCable, $r)->getValue()),
                'connector' => trim((string)$ws->getCellByColumnAndRow($cConector, $r)->getValue()),
                'speed' => trim((string)$ws->getCellByColumnAndRow($cVel, $r)->getValue()),
                'led' => $linkInfo['raw'],
                'is_up' => $linkInfo['is_up'],
                'link_status' => $linkInfo['link_status'],
                'status' => $linkInfo['status'],
                'estado_link' => $linkInfo['estado_link'],
                'status_display' => $linkInfo['estado_texto'],
                'sfp' => trim((string)$ws->getCellByColumnAndRow($cSfp, $r)->getValue()),
                'transceiver' => trim((string)$ws->getCellByColumnAndRow($cTrans, $r)->getValue()),
                'dest_patch_panel' => trim((string)$ws->getCellByColumnAndRow($cDestPP, $r)->getValue()),
                'dest_ur' => trim((string)$ws->getCellByColumnAndRow($cDestUR, $r)->getValue()),
                'dest_module' => trim((string)$ws->getCellByColumnAndRow($cDestMod, $r)->getValue()),
                'dest_par' => trim((string)$ws->getCellByColumnAndRow($cDestPar, $r)->getValue()),
                'dest_device' => trim((string)$ws->getCellByColumnAndRow(29, $r)->getValue()),
                'dest_port' => trim((string)$ws->getCellByColumnAndRow(31, $r)->getValue() ?: (string)$ws->getCellByColumnAndRow(35, $r)->getValue()),
                'dest_rack' => trim((string)$ws->getCellByColumnAndRow(28, $r)->getValue()) ?: $devicesMap[$devKey]['rack_name'],
                'observations' => trim((string)$ws->getCellByColumnAndRow(32, $r)->getValue() ?: (string)$ws->getCellByColumnAndRow(46, $r)->getValue())
            ];
        }

        // Concatenar series y fabricantes si hay múltiples en el equipo
        foreach ($devicesMap as &$d) {
            if (!empty($d['all_serials'])) {
                $d['serial'] = implode(', ', $d['all_serials']);
            }
            if (!empty($d['all_manufacturers'])) {
                $d['manufacturer'] = implode(', ', $d['all_manufacturers']);
            }
        }
        unset($d);

        $devicesList = array_values($devicesMap);
        if (empty($devicesList)) {
            $res['errors'][] = 'No se encontraron registros de puertos en la hoja de Portmapping.';
            return $res;
        }

        $firstDev = $devicesList[0];
        $res['client'] = $firstDev['client'];
        $res['location'] = $firstDev['location'];
        $res['area'] = $firstDev['area'];
        $res['rack_name'] = $firstDev['rack_name'];
        $res['device_type'] = $firstDev['device_type'];
        $res['manufacturer'] = $firstDev['manufacturer'];
        $res['model'] = $firstDev['model'];
        $res['serial'] = $firstDev['serial'];
        $res['ur'] = $firstDev['ur'];
        $res['ports_count'] = $firstDev['ports_count'];
        $res['ip_address'] = $firstDev['ip_address'];
        $res['device_name'] = $firstDev['device_name'];
        $res['connected_count'] = $firstDev['connected_count'];
        $res['vacant_count'] = $firstDev['vacant_count'];
        $res['ports'] = $firstDev['ports'];
        $res['is_bulk'] = count($devicesList) > 1;
        $res['devices_count'] = count($devicesList);
        $res['devices'] = $devicesList;

        return $res;
    }

    /**
     * Parsea la hoja de inventario y elevación del Rack (Soporte Multi-Rack y Multi-Área).
     * Calcula la orientación física (Mode A vs Mode B) y detecta colisiones de UR o excesos de capacidad.
     */
    public static function parseRackSheet($ws, string $defaultRackName = 'RACK 01'): array
    {
        $res = [
            'client' => '',
            'location' => '',
            'area' => '',
            'rack_name' => $defaultRackName,
            'total_u' => 12,
            'observations' => '',
            'devices' => [],
            'racks' => [],
            'racks_count' => 0,
            'areas' => [],
            'areas_count' => 0,
            'conflicts' => [],
            'has_conflicts' => false
        ];

        if (!$ws) return $res;

        $highestRow = $ws->getHighestRow();
        $highestCol = Coordinate::columnIndexFromString($ws->getHighestColumn());

        // Encontrar fila de cabecera buscando términos clave
        $headerRow = 4;
        for ($r = 1; $r <= min(6, $highestRow); $r++) {
            $rowStr = '';
            for ($c = 1; $c <= min(10, $highestCol); $c++) {
                $rowStr .= ' ' . mb_strtolower((string)$ws->getCellByColumnAndRow($c, $r)->getValue(), 'UTF-8');
            }
            if (strpos($rowStr, 'ur totales') !== false || (strpos($rowStr, 'equipo') !== false && strpos($rowStr, 'ur') !== false)) {
                $headerRow = $r;
                break;
            }
        }

        $colIndex = [];
        for ($c = 1; $c <= $highestCol; $c++) {
            $val = trim((string)$ws->getCellByColumnAndRow($c, $headerRow)->getValue());
            $norm = self::normalizeKey($val);
            if ($norm !== '' && !isset($colIndex[$norm])) {
                $colIndex[$norm] = $c;
            }
        }

        $cEmpresa = $colIndex['empresa'] ?? 1;
        $cRack = $colIndex['nombrerackorigen'] ?? ($colIndex['rack'] ?? 2);
        $cUbicacion = $colIndex['ubicacion'] ?? ($colIndex['sede'] ?? 3);
        $cArea = $colIndex['area'] ?? 4;
        $cUrTot = $colIndex['urtotales'] ?? 5;
        $cEq = $colIndex['equipo'] ?? 6;
        $cUr = $colIndex['ur'] ?? 7;
        $cUrEq = $colIndex['urtequipo'] ?? 8;
        $cFrontRear = $colIndex['frontrear'] ?? ($colIndex['fr'] ?? ($colIndex['orientacion'] ?? ($colIndex['lado'] ?? ($colIndex['vista'] ?? ($colIndex['cara'] ?? null)))));
        $cObs = $colIndex['observaciones'] ?? ($cFrontRear ? 10 : 9);

        $racksMap = [];
        $areasList = [];

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $rackName = trim((string)$ws->getCellByColumnAndRow($cRack, $r)->getValue());
            $eqName = trim((string)$ws->getCellByColumnAndRow($cEq, $r)->getValue());

            // Saltar filas sin nombre de rack ni equipo
            if ($rackName === '' && $eqName === '') continue;

            $empresa = trim((string)$ws->getCellByColumnAndRow($cEmpresa, $r)->getValue()) ?: 'VILASECA';
            if (stripos($empresa, 'VILASECA') !== false) $empresa = 'VILASECA';
            $ubicacion = trim((string)$ws->getCellByColumnAndRow($cUbicacion, $r)->getValue()) ?: 'GENERAL';
            $area = trim((string)$ws->getCellByColumnAndRow($cArea, $r)->getValue()) ?: 'GENERAL';
            $urTot = (int)$ws->getCellByColumnAndRow($cUrTot, $r)->getValue();
            $urVal = (int)$ws->getCellByColumnAndRow($cUr, $r)->getValue();
            $urEqVal = (int)$ws->getCellByColumnAndRow($cUrEq, $r)->getValue() ?: 1;
            $obs = trim((string)$ws->getCellByColumnAndRow($cObs, $r)->getValue());

            // Procesar columna front/rear (F = Frontal, R = Posterior, B = Ambos)
            $rawFR = $cFrontRear ? trim((string)$ws->getCellByColumnAndRow($cFrontRear, $r)->getValue()) : '';
            $rawFRUpper = mb_strtoupper($rawFR, 'UTF-8');
            $orientation = 'front';
            if (in_array($rawFRUpper, ['R', 'REAR', 'POSTERIOR', 'TRASERO', 'TRASERA', 'BACK'], true)) {
                $orientation = 'rear';
            } elseif (in_array($rawFRUpper, ['B', 'BOTH', 'AMBOS', 'DOBLE', 'FULL'], true)) {
                $orientation = 'both';
            } elseif (in_array($rawFRUpper, ['F', 'FRONT', 'FRONTAL', 'DELANTERO', 'DELANTERA'], true)) {
                $orientation = 'front';
            }

            // Detección de PDU Horizontal vs Vertical (0U Lateral)
            $eqNameUpper = mb_strtoupper($eqName, 'UTF-8');
            $obsUpper = mb_strtoupper($obs, 'UTF-8');
            $isPdu = (strpos($eqNameUpper, 'PDU') !== false || strpos($eqNameUpper, 'REGLETA') !== false || strpos($eqNameUpper, 'MULTITOMA') !== false || strpos($eqNameUpper, 'POWERCUBE') !== false);
            $isVertical = false;
            $mounting = 'horizontal';

            if ($isPdu) {
                if (strpos($eqNameUpper, 'VERTICAL') !== false || strpos($eqNameUpper, 'LATERAL') !== false || strpos($eqNameUpper, '0U') !== false ||
                    strpos($obsUpper, 'VERTICAL') !== false || strpos($obsUpper, 'LATERAL') !== false || strpos($obsUpper, '0U') !== false ||
                    strpos($rawFRUpper, 'VERT') !== false || strpos($rawFRUpper, 'V') !== false ||
                    $urVal === 0 || $urEqVal === 0) {
                    $isVertical = true;
                }
            } elseif (strpos($eqNameUpper, 'VERTICAL') !== false || strpos($obsUpper, 'VERTICAL') !== false) {
                if (strpos($eqNameUpper, 'ORGANIZADOR') !== false || strpos($eqNameUpper, 'CANALETA') !== false) {
                    $isVertical = true;
                }
            }

            if ($isVertical) {
                if (strpos($eqNameUpper, ' A') !== false || strpos($eqNameUpper, '_A') !== false || strpos($eqNameUpper, 'EXTERNA') !== false || strpos($eqNameUpper, 'IZQ') !== false || strpos($obsUpper, 'IZQ') !== false || strpos($obsUpper, 'EXTERNA') !== false) {
                    $mounting = 'vertical_left';
                } elseif (strpos($eqNameUpper, ' B') !== false || strpos($eqNameUpper, '_B') !== false || strpos($eqNameUpper, 'INTERNA') !== false || strpos($eqNameUpper, 'DER') !== false || strpos($obsUpper, 'DER') !== false || strpos($obsUpper, 'INTERNA') !== false) {
                    $mounting = 'vertical_right';
                } else {
                    $mounting = 'vertical_left';
                }
            }

            $effectiveRackName = $rackName ?: $defaultRackName;
            $rackKey = strtoupper($empresa . '___' . $ubicacion . '___' . $area . '___' . $effectiveRackName);

            if (!isset($racksMap[$rackKey])) {
                $racksMap[$rackKey] = [
                    'key' => $rackKey,
                    'client' => $empresa,
                    'location' => $ubicacion,
                    'area' => $area,
                    'name' => $effectiveRackName,
                    'rack_name' => $effectiveRackName,
                    'total_u' => $urTot > 0 ? $urTot : 12,
                    'observations' => $obs,
                    'raw_devices' => [],
                    'devices' => [],
                    'conflicts' => [],
                    'calculation_mode' => 'decreasing'
                ];
                $areasList[] = $area;
            }

            if ($urTot > 0) $racksMap[$rackKey]['total_u'] = $urTot;
            if ($obs !== '' && empty($racksMap[$rackKey]['observations'])) {
                $racksMap[$rackKey]['observations'] = $obs;
            }

            if ($eqName !== '') {
                $racksMap[$rackKey]['raw_devices'][] = [
                    'row' => $r,
                    'name' => $eqName,
                    'excel_ur' => $urVal,
                    'height_u' => $urEqVal,
                    'orientation' => $orientation,
                    'is_vertical' => $isVertical,
                    'mounting' => $mounting,
                    'observations' => $obs
                ];
            }
        }

        // Evaluar orientación vertical y colisiones por cada rack (Aislado por caras Frontal / Posterior)
        $allConflicts = [];

        foreach ($racksMap as $rk => &$rack) {
            $devs = $rack['raw_devices'];
            if (empty($devs)) continue;

            // Mode A (Ascendente: start_u = ur, end_u = ur + h - 1)
            $occA = ['front' => [], 'rear' => []];
            $confA = [];
            foreach ($devs as $d) {
                if (!empty($d['is_vertical'])) continue; // PDUs verticales van en canales laterales, no ocupan slots horizontales
                $faces = ($d['orientation'] === 'both') ? ['front', 'rear'] : [$d['orientation']];
                foreach ($faces as $f) {
                    for ($u = $d['excel_ur']; $u < $d['excel_ur'] + $d['height_u']; $u++) {
                        if (isset($occA[$f][$u])) {
                            $sideLabel = ($f === 'front') ? 'Frontal' : 'Posterior';
                            $confA[] = "En Rack '{$rack['name']}' (Área: {$rack['area']}), cara {$sideLabel}, UR $u: '{$d['name']}' colisiona con '{$occA[$f][$u]['name']}'.";
                        }
                        $occA[$f][$u] = $d;
                    }
                }
            }

            // Mode B (Descendente / Hacia arriba en rack top-down: start_u = ur - h + 1, end_u = ur)
            $occB = ['front' => [], 'rear' => []];
            $confB = [];
            foreach ($devs as $d) {
                if (!empty($d['is_vertical'])) continue;
                $faces = ($d['orientation'] === 'both') ? ['front', 'rear'] : [$d['orientation']];
                foreach ($faces as $f) {
                    for ($u = $d['excel_ur'] - $d['height_u'] + 1; $u <= $d['excel_ur']; $u++) {
                        if (isset($occB[$f][$u])) {
                            $sideLabel = ($f === 'front') ? 'Frontal' : 'Posterior';
                            $confB[] = "En Rack '{$rack['name']}' (Área: {$rack['area']}), cara {$sideLabel}, UR $u: '{$d['name']}' colisiona con '{$occB[$f][$u]['name']}'.";
                        }
                        $occB[$f][$u] = $d;
                    }
                }
            }

            // Colisiones entre PDUs verticales en el mismo canal y cara
            $vertOcc = [];
            foreach ($devs as $d) {
                if (empty($d['is_vertical'])) continue;
                $vKey = $d['mounting'] . '___' . $d['orientation'];
                if (isset($vertOcc[$vKey])) {
                    $sideLabel = ($d['mounting'] === 'vertical_left') ? 'Vertical Izquierda (A)' : 'Vertical Derecha (B)';
                    $faceLabel = ($d['orientation'] === 'rear') ? 'Posterior' : 'Frontal';
                    $confA[] = "En Rack '{$rack['name']}' (Área: {$rack['area']}), canal {$sideLabel} ({$faceLabel}): '{$d['name']}' colisiona con '{$vertOcc[$vKey]['name']}'.";
                    $confB[] = "En Rack '{$rack['name']}' (Área: {$rack['area']}), canal {$sideLabel} ({$faceLabel}): '{$d['name']}' colisiona con '{$vertOcc[$vKey]['name']}'.";
                }
                $vertOcc[$vKey] = $d;
            }

            // Seleccionar automáticamente el modo con menor índice de colisiones
            $useModeB = (count($confB) < count($confA));
            if (count($confB) === count($confA)) {
                $firstUr = $devs[0]['excel_ur'] ?? 1;
                $lastUr = $devs[count($devs) - 1]['excel_ur'] ?? 1;
                $useModeB = ($firstUr >= $lastUr);
            }

            $rack['calculation_mode'] = $useModeB ? 'decreasing' : 'increasing';
            $rack['numbering_dir'] = $useModeB ? 'DOWN' : 'UP';
            $unitConflicts = $useModeB ? $confB : $confA;
            $rack['conflicts'] = $unitConflicts;

            // Construir dispositivos con coordenadas estandarizadas para Datacenter
            foreach ($devs as $d) {
                $startU = $useModeB ? max(1, $d['excel_ur'] - $d['height_u'] + 1) : max(1, $d['excel_ur']);
                $endU = $startU + $d['height_u'] - 1;

                if (!$d['is_vertical'] && $endU > $rack['total_u']) {
                    $cMsg = "En Rack '{$rack['name']}' (Área: {$rack['area']}), el equipo '{$d['name']}' (UR {$d['excel_ur']}, Altura {$d['height_u']}U) excede la capacidad total del rack ({$rack['total_u']} UR).";
                    $rack['conflicts'][] = $cMsg;
                }
                if ($startU < 1 || $d['excel_ur'] < 1) {
                    $cMsg = "En Rack '{$rack['name']}' (Área: {$rack['area']}), el equipo '{$d['name']}' tiene una UR de inicio inválida ({$d['excel_ur']}).";
                    $rack['conflicts'][] = $cMsg;
                }

                $rack['devices'][] = [
                    'name' => $d['name'],
                    'excel_ur' => $d['excel_ur'],
                    'start_u' => $startU,
                    'end_u' => $endU,
                    'height_u' => $d['height_u'],
                    'orientation' => $d['orientation'],
                    'is_vertical' => $d['is_vertical'],
                    'mounting' => $d['mounting'],
                    'observations' => $d['observations']
                ];
            }

            if (!empty($rack['conflicts'])) {
                $allConflicts = array_merge($allConflicts, $rack['conflicts']);
            }
        }
        unset($rack);

        $racksList = array_values($racksMap);
        $areasDistinct = array_values(array_unique($areasList));

        $res['racks'] = $racksList;
        $res['racks_count'] = count($racksList);
        $res['areas'] = $areasDistinct;
        $res['areas_count'] = count($areasDistinct);
        $res['conflicts'] = $allConflicts;
        $res['has_conflicts'] = !empty($allConflicts);

        if (!empty($racksList)) {
            $first = $racksList[0];
            $res['client'] = $first['client'];
            $res['location'] = $first['location'];
            $res['area'] = $first['area'];
            $res['rack_name'] = $first['name'];
            $res['total_u'] = $first['total_u'];
            $res['observations'] = $first['observations'];
            $res['devices'] = $first['devices'];
        }

        return $res;
    }

    /**
     * Comprueba si el equipo o levantamiento ya existe para evitar duplicados.
     */
    private static function checkDeviceDuplicate($pdo, string $client, string $location, string $area, string $rack, string $serial, string $deviceName, int $ur = 0)
    {
        // 1. Por número de serie único
        if (!empty($serial) && strtoupper($serial) !== 'N/A' && strtoupper($serial) !== 'SIN SERIE') {
            $stmt = $pdo->prepare("SELECT id, client, location, area, rack, ur_rack, device_name, device_label, created_at 
                FROM manual_portmap_surveys 
                WHERE UPPER(client) LIKE UPPER(?) AND UPPER(device_label) = UPPER(?) LIMIT 1");
            $stmt->execute(['%' . $client . '%', $serial]);
            $dup = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dup) return $dup;
        }

        // 2. Por jerarquía: cliente + ubicación + (rack coincidente o genérico) + nombre de equipo
        $stmt = $pdo->prepare("SELECT id, client, location, area, rack, ur_rack, device_name, device_label, created_at 
            FROM manual_portmap_surveys 
            WHERE UPPER(client) LIKE UPPER(?) AND UPPER(location) = UPPER(?) 
              AND (UPPER(rack) = UPPER(?) OR UPPER(rack) = 'RACK 01' OR UPPER(?) LIKE CONCAT('%', UPPER(rack), '%') OR UPPER(rack) LIKE CONCAT('%', UPPER(?), '%'))
              AND UPPER(device_name) = UPPER(?) LIMIT 1");
        $stmt->execute(['%' . $client . '%', $location, $rack, $rack, $rack, $deviceName]);
        $dup = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dup) return $dup;

        // 3. Fallback por UR en el mismo rack para equipos sin serie
        if ($ur > 0 && (empty($serial) || strtoupper($serial) === 'N/A' || strtoupper($serial) === 'SIN SERIE')) {
            $stmt = $pdo->prepare("SELECT id, client, location, area, rack, ur_rack, device_name, device_label, created_at 
                FROM manual_portmap_surveys 
                WHERE UPPER(client) LIKE UPPER(?) AND UPPER(location) = UPPER(?) 
                  AND (UPPER(rack) = UPPER(?) OR UPPER(rack) = 'RACK 01' OR UPPER(?) LIKE CONCAT('%', UPPER(rack), '%') OR UPPER(rack) LIKE CONCAT('%', UPPER(?), '%'))
                  AND ur_rack = ? LIMIT 1");
            $stmt->execute(['%' . $client . '%', $location, $rack, $rack, $rack, (string)$ur]);
            $dup = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dup) return $dup;
        }

        return false;
    }

    /**
     * Comprueba si el rack ya existe en datacenter.
     */
    private static function checkRackExisting($pdo, string $client, string $location, string $rackName)
    {
        $stmt = $pdo->prepare("SELECT id, total_u, description FROM dc_racks WHERE UPPER(client) LIKE UPPER(?) AND UPPER(location) = UPPER(?) AND UPPER(name) = UPPER(?) LIMIT 1");
        $stmt->execute(['%' . $client . '%', $location, $rackName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
