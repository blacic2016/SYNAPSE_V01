<?php
/**
 * Instalación del esquema de la tabla femsa_bitacora y femsa_bitacora_images
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/db.php';

$pdo = getPDO();
if (!$pdo) {
    die("Error de conexión a la base de datos\n");
}

$queries = [
    "CREATE TABLE IF NOT EXISTS femsa_bitacora (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requirement_id INT NOT NULL,
        fecha DATE NOT NULL,
        tema VARCHAR(255) NOT NULL,
        descripcion TEXT NOT NULL,
        created_by VARCHAR(100) DEFAULT 'Sistema',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_req_id (requirement_id),
        INDEX idx_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS femsa_bitacora_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bitacora_id INT NOT NULL,
        requirement_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT DEFAULT 0,
        mime_type VARCHAR(100) DEFAULT 'image/png',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_bitacora_id (bitacora_id),
        INDEX idx_req_id (requirement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ Tabla creada exitosamente\n";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Schema de Bitácora instalado correctamente.\n";
