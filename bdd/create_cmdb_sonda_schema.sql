-- ====================================================================
-- DDL Y ESQUEMA AUTÓNOMO: MÓDULO CMDB_SONDA
-- Base de Datos: CMDBVilaseca2 (o la configurada en SYNAPSE)
-- Módulo independiente: CMDB orientada a Servicios y Dependencias (ITIL)
-- ====================================================================

-- 1. TABLA: SERVICIOS CRÍTICOS / APLICACIONES EMPRESARIALES
CREATE TABLE IF NOT EXISTS `cmdb_sonda_services` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABLA PRINCIPAL: ELEMENTOS DE CONFIGURACIÓN (CIs) CON LAS 5 CATEGORÍAS
CREATE TABLE IF NOT EXISTS `cmdb_sonda_cis` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  
  -- CATEGORÍA 1: Identificación y Características técnicas
  `id_ci` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador único inequívoco del CI (ej: CI-SND-001)',
  `hostname_nombre` VARCHAR(255) NOT NULL COMMENT 'Nombre de host o nombre del activo',
  `tipo_ci` VARCHAR(100) NOT NULL COMMENT 'Servidor Físico, Servidor Virtual, Switch, Router, Firewall, etc.',
  `fabricante` VARCHAR(100) DEFAULT NULL COMMENT 'Cisco, Dell, HPE, VMware, Fortinet, etc. (Fase 2)',
  `modelo` VARCHAR(100) DEFAULT NULL COMMENT 'Modelo exacto (Fase 2)',
  `numero_serie` VARCHAR(100) DEFAULT NULL COMMENT 'Número de serie único de hardware (Fase 2)',
  `version_firmware_so` VARCHAR(100) DEFAULT NULL COMMENT 'Versión de SO o Firmware (Fase 2)',
  
  -- CATEGORÍA 2: Negocio, Organización y Gobierno
  `cliente` VARCHAR(150) NOT NULL COMMENT 'Cliente o entidad dueña del servicio',
  `servicio` VARCHAR(150) NOT NULL COMMENT 'Servicio de negocio al que da soporte',
  `responsable_cliente` VARCHAR(150) DEFAULT NULL COMMENT 'Contacto o líder del cliente (Fase 2)',
  `contrato_proyecto` VARCHAR(150) DEFAULT NULL COMMENT 'Código de contrato o proyecto (Fase 2)',
  `propietario_tecnico` VARCHAR(150) DEFAULT NULL COMMENT 'Especialista o equipo SONDA responsable',
  `service_id` INT(11) DEFAULT NULL COMMENT 'FK opcional a cmdb_sonda_services',
  
  -- CATEGORÍA 3: Ubicación y Topología
  `sede_site` VARCHAR(150) NOT NULL COMMENT 'Centro de datos, campus o sede física',
  `pais` VARCHAR(100) DEFAULT NULL COMMENT 'País (Fase 2)',
  `ciudad` VARCHAR(100) DEFAULT NULL COMMENT 'Ciudad (Fase 2)',
  `rack` VARCHAR(100) DEFAULT NULL COMMENT 'Identificador y unidades U en rack (Fase 2)',
  
  -- CATEGORÍA 4: Operaciones, Monitoreo y Estado
  `ip_administracion` VARCHAR(50) NOT NULL COMMENT 'Dirección IP de gestión/administración',
  `ambiente` ENUM('Producción', 'Contingencia / DR', 'Preproducción', 'Desarrollo', 'QA', 'Laboratorio') NOT NULL DEFAULT 'Producción',
  `estado_ci` ENUM('Operativo', 'Mantenimiento', 'Planificación', 'Retirado / Decomisado', 'Falla') NOT NULL DEFAULT 'Operativo',
  `criticidad` ENUM('Crítica', 'Alta', 'Media', 'Baja') NOT NULL DEFAULT 'Alta',
  `monitoreado` ENUM('Sí', 'No') NOT NULL DEFAULT 'Sí' COMMENT 'Integración con Zabbix / Monitoreo',
  
  -- CATEGORÍA 5: Ciclo de Vida, Soporte y Licenciamiento
  `inicio_soporte` DATE NOT NULL COMMENT 'Fecha de inicio de soporte contractual',
  `fin_soporte` DATE NOT NULL COMMENT 'Fecha de fin de soporte contractual',
  `dias_fin_soporte` INT(11) DEFAULT NULL COMMENT 'Días restantes de soporte calculados automáticamente',
  `garantia_hasta` DATE DEFAULT NULL COMMENT 'Fecha de vencimiento de garantía de fabricante (Fase 2)',
  `licencia` VARCHAR(150) DEFAULT NULL COMMENT 'Detalle o clave de licenciamiento (Fase 2)',
  `fin_licencia` DATE DEFAULT NULL COMMENT 'Vigencia de licencia de software (Fase 2)',
  `fecha_eol` DATE DEFAULT NULL COMMENT 'End of Life del activo (Fase 2)',
  `fecha_eos` DATE DEFAULT NULL COMMENT 'End of Support del fabricante (Fase 2)',
  
  -- Auditoría y Metadatos
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABLA: RELACIONES Y DEPENDENCIAS ENTRE CIs (ITIL DEPENDENCY MAPPING)
CREATE TABLE IF NOT EXISTS `cmdb_sonda_relationships` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `source_ci_id` INT(11) NOT NULL COMMENT 'CI origen',
  `target_ci_id` INT(11) NOT NULL COMMENT 'CI destino (del cual depende o al que conecta)',
  `relationship_type` ENUM('depende_de', 'aloja_a', 'conecta_con', 'ejecuta_en', 'respalda_a', 'alimenta_electricamente') NOT NULL DEFAULT 'depende_de',
  `descripcion` VARCHAR(255) DEFAULT NULL COMMENT 'Contexto del enlace, puerto, VLAN o protocolo',
  `impacto_falla` ENUM('Crítico', 'Alto', 'Medio', 'Bajo') NOT NULL DEFAULT 'Alto',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ci_relation` (`source_ci_id`, `target_ci_id`, `relationship_type`),
  INDEX `idx_rel_source` (`source_ci_id`),
  INDEX `idx_rel_target` (`target_ci_id`),
  CONSTRAINT `fk_rel_source` FOREIGN KEY (`source_ci_id`) REFERENCES `cmdb_sonda_cis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rel_target` FOREIGN KEY (`target_ci_id`) REFERENCES `cmdb_sonda_cis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABLA: AUDITORÍA Y TRAZABILIDAD DE CAMBIOS
CREATE TABLE IF NOT EXISTS `cmdb_sonda_audit_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ci_id` INT(11) DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL COMMENT 'CREATE, UPDATE, DELETE, RECALCULATE, AUDIT',
  `user_name` VARCHAR(100) DEFAULT 'sistema',
  `details_json` LONGTEXT DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_audit_ci` (`ci_id`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- TRIGGERS DE AUTOMATIZACIÓN: CÁLCULO DE DIAS_FIN_SOPORTE
-- ====================================================================
DROP TRIGGER IF EXISTS `trg_cmdb_sonda_before_insert`;
DELIMITER $$
CREATE TRIGGER `trg_cmdb_sonda_before_insert` 
BEFORE INSERT ON `cmdb_sonda_cis`
FOR EACH ROW
BEGIN
  IF NEW.fin_soporte IS NOT NULL THEN
    SET NEW.dias_fin_soporte = DATEDIFF(NEW.fin_soporte, CURDATE());
  ELSE
    SET NEW.dias_fin_soporte = NULL;
  END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_cmdb_sonda_before_update`;
DELIMITER $$
CREATE TRIGGER `trg_cmdb_sonda_before_update` 
BEFORE UPDATE ON `cmdb_sonda_cis`
FOR EACH ROW
BEGIN
  IF NEW.fin_soporte IS NOT NULL THEN
    SET NEW.dias_fin_soporte = DATEDIFF(NEW.fin_soporte, CURDATE());
  ELSE
    SET NEW.dias_fin_soporte = NULL;
  END IF;
END$$
DELIMITER ;

-- ====================================================================
-- CONSULTA 1: RECÁLCULO AUTOMÁTICO EN LOTE DE DÍAS DE SOPORTE
-- (Puede ejecutarse vía Cron diario o botón en la interfaz)
-- ====================================================================
-- UPDATE cmdb_sonda_cis 
-- SET dias_fin_soporte = DATEDIFF(fin_soporte, CURDATE())
-- WHERE fin_soporte IS NOT NULL;

-- ====================================================================
-- CONSULTA 2: VALIDACIÓN DE INTEGRIDAD Y COMPLETITUD FASE 1 (CORE)
-- ====================================================================
-- SELECT 
--     id, id_ci, hostname_nombre, cliente, servicio,
--     ROUND((
--       (CASE WHEN id_ci IS NOT NULL AND TRIM(id_ci) != '' THEN 1 ELSE 0 END) +
--       (CASE WHEN hostname_nombre IS NOT NULL AND TRIM(hostname_nombre) != '' THEN 1 ELSE 0 END) +
--       (CASE WHEN tipo_ci IS NOT NULL AND TRIM(tipo_ci) != '' THEN 1 ELSE 0 END) +
--       (CASE WHEN cliente IS NOT NULL AND TRIM(cliente) != '' THEN 1 ELSE 0 END) +
--       (CASE WHEN servicio IS NOT NULL AND TRIM(servicio) != '' THEN 1 ELSE 0 END) +
--       (CASE WHEN sede_site IS NOT NULL AND TRIM(sede_site) != '' THEN 1 ELSE 0 END) +
--       (CASE WHEN ip_administracion IS NOT NULL AND TRIM(ip_administracion) != '' THEN 1 ELSE 0 END) +
--       (CASE WHEN ambiente IS NOT NULL AND TRIM(ambiente) != '' THEN 1 ELSE 0 END) +
--       (CASE WHEN estado_ci IS NOT NULL AND TRIM(estado_ci) != '' THEN 1 ELSE 0 END) +
--       (CASE WHEN criticidad IS NOT NULL AND TRIM(criticidad) != '' THEN 1 ELSE 0 END) +
--       (CASE WHEN monitoreado IS NOT NULL AND TRIM(monitoreado) != '' THEN 1 ELSE 0 END) +
--       (CASE WHEN inicio_soporte IS NOT NULL THEN 1 ELSE 0 END) +
--       (CASE WHEN fin_soporte IS NOT NULL THEN 1 ELSE 0 END)
--     ) * 100.0 / 13, 1) AS pct_integridad_fase1
-- FROM cmdb_sonda_cis;
