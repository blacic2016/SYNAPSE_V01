<?php
/**
 * Instalador y Seeder para el Módulo Autónomo CMDB_SONDA
 * Ejecución vía CLI: php bdd/install_cmdb_sonda.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';

try {
    $pdo = getPDO();
    echo "Conexión a BD establecida exitosamente.\n";

    // 1. Crear tablas
    $sqlFile = __DIR__ . '/create_cmdb_sonda_schema.sql';
    if (!file_exists($sqlFile)) {
        die("Error: No se encuentra $sqlFile\n");
    }

    // Dividir las sentencias SQL respetando DELIMITER para triggers
    $sqlContent = file_get_contents($sqlFile);
    
    // Ejecutar tablas primero
    $tableStatements = [
        "CREATE TABLE IF NOT EXISTS `cmdb_sonda_services` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `service_code` VARCHAR(50) NOT NULL UNIQUE,
          `nombre_servicio` VARCHAR(150) NOT NULL,
          `cliente` VARCHAR(150) NOT NULL,
          `propietario_negocio` VARCHAR(150) DEFAULT NULL,
          `propietario_tecnico` VARCHAR(150) DEFAULT NULL,
          `criticidad_negocio` ENUM('Crítica', 'Alta', 'Media', 'Baja') DEFAULT 'Alta',
          `descripcion` TEXT DEFAULT NULL,
          `estado` ENUM('Operativo', 'Degradado', 'Mantenimiento', 'Inactivo') DEFAULT 'Operativo',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_service_cliente` (`cliente`),
          INDEX `idx_service_criticidad` (`criticidad_negocio`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `cmdb_sonda_cis` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `id_ci` VARCHAR(50) NOT NULL UNIQUE,
          `hostname_nombre` VARCHAR(255) NOT NULL,
          `tipo_ci` VARCHAR(100) NOT NULL,
          `fabricante` VARCHAR(100) DEFAULT NULL,
          `modelo` VARCHAR(100) DEFAULT NULL,
          `numero_serie` VARCHAR(100) DEFAULT NULL,
          `version_firmware_so` VARCHAR(100) DEFAULT NULL,
          `cliente` VARCHAR(150) NOT NULL,
          `servicio` VARCHAR(150) NOT NULL,
          `responsable_cliente` VARCHAR(150) DEFAULT NULL,
          `contrato_proyecto` VARCHAR(150) DEFAULT NULL,
          `propietario_tecnico` VARCHAR(150) DEFAULT NULL,
          `service_id` INT(11) DEFAULT NULL,
          `sede_site` VARCHAR(150) NOT NULL,
          `pais` VARCHAR(100) DEFAULT NULL,
          `ciudad` VARCHAR(100) DEFAULT NULL,
          `rack` VARCHAR(100) DEFAULT NULL,
          `ip_administracion` VARCHAR(50) NOT NULL,
          `ambiente` ENUM('Producción', 'Contingencia / DR', 'Preproducción', 'Desarrollo', 'QA', 'Laboratorio') NOT NULL DEFAULT 'Producción',
          `estado_ci` ENUM('Operativo', 'Mantenimiento', 'Planificación', 'Retirado / Decomisado', 'Falla') NOT NULL DEFAULT 'Operativo',
          `criticidad` ENUM('Crítica', 'Alta', 'Media', 'Baja') NOT NULL DEFAULT 'Alta',
          `monitoreado` ENUM('Sí', 'No') NOT NULL DEFAULT 'Sí',
          `inicio_soporte` DATE NOT NULL,
          `fin_soporte` DATE NOT NULL,
          `dias_fin_soporte` INT(11) DEFAULT NULL,
          `garantia_hasta` DATE DEFAULT NULL,
          `licencia` VARCHAR(150) DEFAULT NULL,
          `fin_licencia` DATE DEFAULT NULL,
          `fecha_eol` DATE DEFAULT NULL,
          `fecha_eos` DATE DEFAULT NULL,
          `notas_adicionales` TEXT DEFAULT NULL,
          `created_by` VARCHAR(100) DEFAULT 'admin',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_ci_hostname` (`hostname_nombre`),
          INDEX `idx_ci_tipo` (`tipo_ci`),
          INDEX `idx_ci_cliente` (`cliente`),
          INDEX `idx_ci_servicio` (`servicio`),
          INDEX `idx_ci_ambiente` (`ambiente`),
          INDEX `idx_ci_estado` (`estado_ci`),
          INDEX `idx_ci_criticidad` (`criticidad`),
          INDEX `idx_ci_fin_soporte` (`fin_soporte`),
          INDEX `idx_ci_dias_soporte` (`dias_fin_soporte`),
          CONSTRAINT `fk_cmdb_sonda_service` FOREIGN KEY (`service_id`) REFERENCES `cmdb_sonda_services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `cmdb_sonda_relationships` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `source_ci_id` INT(11) NOT NULL,
          `target_ci_id` INT(11) NOT NULL,
          `relationship_type` ENUM('depende_de', 'aloja_a', 'conecta_con', 'ejecuta_en', 'respalda_a', 'alimenta_electricamente') NOT NULL DEFAULT 'depende_de',
          `descripcion` VARCHAR(255) DEFAULT NULL,
          `impacto_falla` ENUM('Crítico', 'Alto', 'Medio', 'Bajo') NOT NULL DEFAULT 'Alto',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_ci_relation` (`source_ci_id`, `target_ci_id`, `relationship_type`),
          INDEX `idx_rel_source` (`source_ci_id`),
          INDEX `idx_rel_target` (`target_ci_id`),
          CONSTRAINT `fk_rel_source` FOREIGN KEY (`source_ci_id`) REFERENCES `cmdb_sonda_cis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `fk_rel_target` FOREIGN KEY (`target_ci_id`) REFERENCES `cmdb_sonda_cis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `cmdb_sonda_audit_logs` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `ci_id` INT(11) DEFAULT NULL,
          `action` VARCHAR(50) NOT NULL,
          `user_name` VARCHAR(100) DEFAULT 'sistema',
          `details_json` LONGTEXT DEFAULT NULL,
          `ip_address` VARCHAR(50) DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_audit_ci` (`ci_id`),
          INDEX `idx_audit_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    foreach ($tableStatements as $sql) {
        $pdo->exec($sql);
    }
    echo "Tablas cmdb_sonda_* creadas correctamente.\n";

    // 2. Triggers de cálculo automático de dias_fin_soporte
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS trg_cmdb_sonda_before_insert");
        $pdo->exec("CREATE TRIGGER trg_cmdb_sonda_before_insert BEFORE INSERT ON cmdb_sonda_cis
                    FOR EACH ROW
                    BEGIN
                      IF NEW.fin_soporte IS NOT NULL THEN
                        SET NEW.dias_fin_soporte = DATEDIFF(NEW.fin_soporte, CURDATE());
                      ELSE
                        SET NEW.dias_fin_soporte = NULL;
                      END IF;
                    END");

        $pdo->exec("DROP TRIGGER IF EXISTS trg_cmdb_sonda_before_update");
        $pdo->exec("CREATE TRIGGER trg_cmdb_sonda_before_update BEFORE UPDATE ON cmdb_sonda_cis
                    FOR EACH ROW
                    BEGIN
                      IF NEW.fin_soporte IS NOT NULL THEN
                        SET NEW.dias_fin_soporte = DATEDIFF(NEW.fin_soporte, CURDATE());
                      ELSE
                        SET NEW.dias_fin_soporte = NULL;
                      END IF;
                    END");
        echo "Triggers de cálculo automático instalados con éxito.\n";
    } catch (Exception $te) {
        echo "Aviso triggers: " . $te->getMessage() . "\n";
    }

    // 3. Insertar Servicio Crítico Piloto (Best Practice ITIL) si está vacío
    $countServices = (int)$pdo->query("SELECT COUNT(*) FROM cmdb_sonda_services")->fetchColumn();
    if ($countServices === 0) {
        $stmtSvc = $pdo->prepare("INSERT INTO cmdb_sonda_services 
            (service_code, nombre_servicio, cliente, propietario_negocio, propietario_tecnico, criticidad_negocio, descripcion, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmtSvc->execute([
            'SVC-FACT-01',
            'Servicio de Facturación Electrónica SAP',
            'SONDA Corp / Clientes',
            'Gerencia de Finanzas y Cobranzas',
            'Equipo de Infraestructura & Cloud SONDA',
            'Crítica',
            'Servicio de negocio prioritario para emisión tributaria, facturación en línea e integración con SRI/SII.',
            'Operativo'
        ]);
        $serviceId = (int)$pdo->lastInsertId();

        $stmtSvc->execute([
            'SVC-AD-CORP',
            'Active Directory Corporativo & DNS',
            'SONDA Infraestructura',
            'Gerencia de TI',
            'Especialistas Networking & SysAdmin',
            'Crítica',
            'Autenticación centralizada LDAP, DNS corporativo y políticas de dominio GPO.',
            'Operativo'
        ]);

        echo "Servicios críticos base creados (ID: $serviceId).\n";

        // CIs de ejemplo que componen la cadena de punta a punta
        $sampleCIs = [
            [
                'id_ci' => 'CI-SND-SRV-001',
                'hostname_nombre' => 'SRV-SAP-APP01',
                'tipo_ci' => 'Servidor Virtual',
                'fabricante' => 'VMware',
                'modelo' => 'vSphere ESXi VM',
                'numero_serie' => 'VMW-421A-98C3-001',
                'version_firmware_so' => 'Red Hat Enterprise Linux 9.2',
                'cliente' => 'SONDA Corp / Clientes',
                'servicio' => 'Servicio de Facturación Electrónica SAP',
                'responsable_cliente' => 'Rodrigo Morales (Subgerente Facturación)',
                'contrato_proyecto' => 'CT-SONDA-2025-089',
                'propietario_tecnico' => 'Especialistas Cloud SONDA',
                'service_id' => $serviceId,
                'sede_site' => 'Datacenter Santiago - Sonda',
                'pais' => 'Chile',
                'ciudad' => 'Santiago',
                'rack' => 'RACK-DC01 U14',
                'ip_administracion' => '10.200.10.15',
                'ambiente' => 'Producción',
                'estado_ci' => 'Operativo',
                'criticidad' => 'Crítica',
                'monitoreado' => 'Sí',
                'inicio_soporte' => date('Y-m-d', strtotime('-180 days')),
                'fin_soporte' => date('Y-m-d', strtotime('+120 days')),
                'garantia_hasta' => date('Y-m-d', strtotime('+200 days')),
                'licencia' => 'RHEL Server Premium 2-Socket',
                'fin_licencia' => date('Y-m-d', strtotime('+150 days')),
                'fecha_eol' => '2032-05-31',
                'fecha_eos' => '2034-05-31',
                'notas_adicionales' => 'Nodo primario de la aplicación SAP NetWeaver Facturación.'
            ],
            [
                'id_ci' => 'CI-SND-SRV-002',
                'hostname_nombre' => 'SRV-SAP-DB01',
                'tipo_ci' => 'Servidor Físico',
                'fabricante' => 'Dell EMC',
                'modelo' => 'PowerEdge R750',
                'numero_serie' => '7XYZ982-CL',
                'version_firmware_so' => 'SUSE Linux Enterprise Server 15 SP4',
                'cliente' => 'SONDA Corp / Clientes',
                'servicio' => 'Servicio de Facturación Electrónica SAP',
                'responsable_cliente' => 'Rodrigo Morales (Subgerente Facturación)',
                'contrato_proyecto' => 'CT-SONDA-2025-089',
                'propietario_tecnico' => 'DBA Team SONDA',
                'service_id' => $serviceId,
                'sede_site' => 'Datacenter Santiago - Sonda',
                'pais' => 'Chile',
                'ciudad' => 'Santiago',
                'rack' => 'RACK-DC01 U16',
                'ip_administracion' => '10.200.10.16',
                'ambiente' => 'Producción',
                'estado_ci' => 'Operativo',
                'criticidad' => 'Crítica',
                'monitoreado' => 'Sí',
                'inicio_soporte' => date('Y-m-d', strtotime('-340 days')),
                'fin_soporte' => date('Y-m-d', strtotime('+22 days')), // Próximo a vencer en 22 días!
                'garantia_hasta' => date('Y-m-d', strtotime('+400 days')),
                'licencia' => 'SAP HANA Database Engine Enterprise',
                'fin_licencia' => date('Y-m-d', strtotime('+60 days')),
                'fecha_eol' => '2028-12-31',
                'fecha_eos' => '2030-12-31',
                'notas_adicionales' => 'Base de datos en memoria SAP HANA. ATENCIÓN: Fin de soporte contractual en menos de 30 días.'
            ],
            [
                'id_ci' => 'CI-SND-NET-001',
                'hostname_nombre' => 'SW-CORE-01',
                'tipo_ci' => 'Switch Core',
                'fabricante' => 'Cisco',
                'modelo' => 'Catalyst 9500-48Y4C',
                'numero_serie' => 'FCW2348A09Z',
                'version_firmware_so' => 'Cisco IOS XE 17.09.04a',
                'cliente' => 'SONDA Infraestructura',
                'servicio' => 'Red y Conectividad Troncal',
                'responsable_cliente' => 'NOC / Redes SONDA',
                'contrato_proyecto' => 'CT-NET-CORE-2024',
                'propietario_tecnico' => 'Especialistas Networking SONDA',
                'service_id' => $serviceId,
                'sede_site' => 'Datacenter Santiago - Sonda',
                'pais' => 'Chile',
                'ciudad' => 'Santiago',
                'rack' => 'RACK-DC01 U40',
                'ip_administracion' => '10.200.1.1',
                'ambiente' => 'Producción',
                'estado_ci' => 'Operativo',
                'criticidad' => 'Crítica',
                'monitoreado' => 'Sí',
                'inicio_soporte' => date('Y-m-d', strtotime('-300 days')),
                'fin_soporte' => date('Y-m-d', strtotime('+365 days')),
                'garantia_hasta' => date('Y-m-d', strtotime('+500 days')),
                'licencia' => 'Cisco DNA Advantage 5-Year',
                'fin_licencia' => date('Y-m-d', strtotime('+400 days')),
                'fecha_eol' => '2030-10-31',
                'fecha_eos' => '2032-10-31',
                'notas_adicionales' => 'Switch Core troncal. Conecta servidores y almacenamiento de alta prioridad.'
            ],
            [
                'id_ci' => 'CI-SND-SEC-001',
                'hostname_nombre' => 'FW-PERIMETRAL-01',
                'tipo_ci' => 'Firewall',
                'fabricante' => 'Fortinet',
                'modelo' => 'FortiGate 200F',
                'numero_serie' => 'FGT200FT1900382',
                'version_firmware_so' => 'FortiOS 7.2.7',
                'cliente' => 'SONDA Seguridad',
                'servicio' => 'Seguridad Perimetral & VPNs',
                'responsable_cliente' => 'CISO / SOC SONDA',
                'contrato_proyecto' => 'CT-SEC-PERIM-2023',
                'propietario_tecnico' => 'Especialistas Seguridad SONDA',
                'service_id' => $serviceId,
                'sede_site' => 'Datacenter Santiago - Sonda',
                'pais' => 'Chile',
                'ciudad' => 'Santiago',
                'rack' => 'RACK-DC01 U38',
                'ip_administracion' => '10.200.0.1',
                'ambiente' => 'Producción',
                'estado_ci' => 'Operativo',
                'criticidad' => 'Crítica',
                'monitoreado' => 'Sí',
                'inicio_soporte' => date('Y-m-d', strtotime('-400 days')),
                'fin_soporte' => date('Y-m-d', strtotime('-15 days')), // Ya vencido!
                'garantia_hasta' => date('Y-m-d', strtotime('-15 days')),
                'licencia' => 'FortiCare 24x7 + UTP Bundle',
                'fin_licencia' => date('Y-m-d', strtotime('-15 days')),
                'fecha_eol' => '2029-04-15',
                'fecha_eos' => '2031-04-15',
                'notas_adicionales' => 'ALERTA: Soporte y suscripción vencidos hace 15 días. Requiere renovación urgente.'
            ],
            [
                'id_ci' => 'CI-SND-STO-001',
                'hostname_nombre' => 'SAN-STORAGE-PRIM',
                'tipo_ci' => 'Storage / Datastore',
                'fabricante' => 'HPE',
                'modelo' => 'Nimble Storage HF40',
                'numero_serie' => 'AF-203841',
                'version_firmware_so' => 'NimbleOS 6.1.2',
                'cliente' => 'SONDA Infraestructura',
                'servicio' => 'Almacenamiento SAN Centralizado',
                'responsable_cliente' => 'Jefe de Infraestructura SONDA',
                'contrato_proyecto' => 'CT-SAN-2024-001',
                'propietario_tecnico' => 'Storage Administrator SONDA',
                'service_id' => $serviceId,
                'sede_site' => 'Datacenter Santiago - Sonda',
                'pais' => 'Chile',
                'ciudad' => 'Santiago',
                'rack' => 'RACK-DC01 U20',
                'ip_administracion' => '10.200.20.5',
                'ambiente' => 'Producción',
                'estado_ci' => 'Operativo',
                'criticidad' => 'Crítica',
                'monitoreado' => 'Sí',
                'inicio_soporte' => date('Y-m-d', strtotime('-250 days')),
                'fin_soporte' => date('Y-m-d', strtotime('+220 days')),
                'garantia_hasta' => date('Y-m-d', strtotime('+300 days')),
                'licencia' => 'HPE InfoSight Predictive Analytics',
                'fin_licencia' => date('Y-m-d', strtotime('+220 days')),
                'fecha_eol' => '2028-09-30',
                'fecha_eos' => '2030-09-30',
                'notas_adicionales' => 'Arreglo SAN con LUNs dedicadas a SAP HANA y virtualización.'
            ],
            [
                'id_ci' => 'CI-SND-APP-001',
                'hostname_nombre' => 'APP-PORTAL-FACTURAS',
                'tipo_ci' => 'Aplicación / Middleware',
                'fabricante' => 'SONDA Software Solutions',
                'modelo' => 'Portal Tributario Cloud v4.5',
                'numero_serie' => 'SOFT-SND-FACT-2025',
                'version_firmware_so' => 'Node.js 20 LTS / Nginx 1.24',
                'cliente' => 'SONDA Corp / Clientes',
                'servicio' => 'Servicio de Facturación Electrónica SAP',
                'responsable_cliente' => 'Rodrigo Morales (Subgerente Facturación)',
                'contrato_proyecto' => 'CT-SONDA-2025-089',
                'propietario_tecnico' => 'Equipo Desarrollo Facturación',
                'service_id' => $serviceId,
                'sede_site' => 'Datacenter Santiago - Sonda',
                'pais' => 'Chile',
                'ciudad' => 'Santiago',
                'rack' => 'Cloud / VM Pool',
                'ip_administracion' => '10.200.10.20',
                'ambiente' => 'Producción',
                'estado_ci' => 'Operativo',
                'criticidad' => 'Crítica',
                'monitoreado' => 'Sí',
                'inicio_soporte' => date('Y-m-d', strtotime('-180 days')),
                'fin_soporte' => date('Y-m-d', strtotime('+75 days')),
                'garantia_hasta' => date('Y-m-d', strtotime('+365 days')),
                'licencia' => 'Suscripción Anual Facturación',
                'fin_licencia' => date('Y-m-d', strtotime('+75 days')),
                'fecha_eol' => '2029-12-31',
                'fecha_eos' => '2031-12-31',
                'notas_adicionales' => 'Front-end web para carga y consulta de folios tributarios.'
            ]
        ];

        $stmtCi = $pdo->prepare("INSERT INTO cmdb_sonda_cis (
            id_ci, hostname_nombre, tipo_ci, fabricante, modelo, numero_serie, version_firmware_so,
            cliente, servicio, responsable_cliente, contrato_proyecto, propietario_tecnico, service_id,
            sede_site, pais, ciudad, rack, ip_administracion, ambiente, estado_ci, criticidad, monitoreado,
            inicio_soporte, fin_soporte, dias_fin_soporte, garantia_hasta, licencia, fin_licencia,
            fecha_eol, fecha_eos, notas_adicionales, created_by
        ) VALUES (
            :id_ci, :hostname_nombre, :tipo_ci, :fabricante, :modelo, :numero_serie, :version_firmware_so,
            :cliente, :servicio, :responsable_cliente, :contrato_proyecto, :propietario_tecnico, :service_id,
            :sede_site, :pais, :ciudad, :rack, :ip_administracion, :ambiente, :estado_ci, :criticidad, :monitoreado,
            :inicio_soporte, :fin_soporte, DATEDIFF(:fin_soporte, CURDATE()), :garantia_hasta, :licencia, :fin_licencia,
            :fecha_eol, :fecha_eos, :notas_adicionales, 'instalador_sonda'
        )");

        $ciMap = [];
        foreach ($sampleCIs as $ci) {
            $stmtCi->execute($ci);
            $ciMap[$ci['hostname_nombre']] = (int)$pdo->lastInsertId();
        }
        echo "CIs base insertados (" . count($sampleCIs) . " activos).\n";

        // 4. Relaciones de dependencia (Dependency Mapping)
        $relationships = [
            [
                'src' => 'SRV-SAP-APP01',
                'tgt' => 'SRV-SAP-DB01',
                'type' => 'depende_de',
                'desc' => 'SAP NetWeaver consulta base de datos relacional SAP HANA (TCP 30015)',
                'impact' => 'Crítico'
            ],
            [
                'src' => 'SRV-SAP-APP01',
                'tgt' => 'SW-CORE-01',
                'type' => 'conecta_con',
                'desc' => 'Enlace de red VLAN 200 a través del Core Switch',
                'impact' => 'Crítico'
            ],
            [
                'src' => 'SRV-SAP-DB01',
                'tgt' => 'SAN-STORAGE-PRIM',
                'type' => 'respalda_a',
                'desc' => 'LUN de almacenamiento Fibre Channel en HPE Nimble',
                'impact' => 'Crítico'
            ],
            [
                'src' => 'SW-CORE-01',
                'tgt' => 'FW-PERIMETRAL-01',
                'type' => 'conecta_con',
                'desc' => 'Troncal a Firewall perimetral para salida a internet y conexión con el SII',
                'impact' => 'Crítico'
            ],
            [
                'src' => 'APP-PORTAL-FACTURAS',
                'tgt' => 'SRV-SAP-APP01',
                'type' => 'ejecuta_en',
                'desc' => 'Portal web consume APIs RFC / OData expuestas por el servidor de aplicaciones',
                'impact' => 'Crítico'
            ]
        ];

        $stmtRel = $pdo->prepare("INSERT INTO cmdb_sonda_relationships (source_ci_id, target_ci_id, relationship_type, descripcion, impacto_falla) VALUES (?, ?, ?, ?, ?)");
        foreach ($relationships as $rel) {
            if (isset($ciMap[$rel['src']]) && isset($ciMap[$rel['tgt']])) {
                $stmtRel->execute([
                    $ciMap[$rel['src']],
                    $ciMap[$rel['tgt']],
                    $rel['type'],
                    $rel['desc'],
                    $rel['impact']
                ]);
            }
        }
        echo "Mapeo de dependencias establecido (" . count($relationships) . " relaciones de red/servicio).\n";
    } else {
        echo "Ya existen datos en cmdb_sonda_services. No se requiere seed.\n";
    }

    // Actualizar cálculo de días de soporte
    $pdo->exec("UPDATE cmdb_sonda_cis SET dias_fin_soporte = DATEDIFF(fin_soporte, CURDATE()) WHERE fin_soporte IS NOT NULL");
    echo "Cálculo de dias_fin_soporte sincronizado.\n";

    echo "=== INSTALACIÓN CMDB_SONDA FINALIZADA CON ÉXITO ===\n";

} catch (Exception $e) {
    echo "ERROR en la instalación: " . $e->getMessage() . "\n";
    exit(1);
}
