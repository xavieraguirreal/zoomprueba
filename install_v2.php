<?php
/**
 * Migracion v2 - Multi-seccion con control del host
 *
 * Ejecutar UNA SOLA VEZ para:
 * 1. Crear la tabla meeting_active_section
 * 2. Agregar columna section_id a survey_responses
 * 3. Actualizar indices
 *
 * Despues de ejecutar, ELIMINAR este archivo del servidor.
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();

    // 1. Crear tabla para seccion activa del meeting
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS meeting_active_section (
            meeting_id VARCHAR(255) PRIMARY KEY,
            section_id VARCHAR(50) NOT NULL,
            status ENUM('voting','closed') DEFAULT 'voting',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>Tabla <code>meeting_active_section</code> creada.</p>";

    // 2. Agregar columna section_id a survey_responses (si no existe)
    $columns = $pdo->query("SHOW COLUMNS FROM survey_responses LIKE 'section_id'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE survey_responses ADD COLUMN section_id VARCHAR(50) NOT NULL DEFAULT 'encuesta1'");
        echo "<p>Columna <code>section_id</code> agregada a survey_responses.</p>";
    } else {
        echo "<p>Columna <code>section_id</code> ya existe, saltando.</p>";
    }

    // 3. Actualizar indices
    // Eliminar indice viejo si existe
    try {
        $pdo->exec("ALTER TABLE survey_responses DROP INDEX idx_meeting");
        echo "<p>Indice <code>idx_meeting</code> eliminado.</p>";
    } catch (PDOException $e) {
        echo "<p>Indice <code>idx_meeting</code> no existia, saltando.</p>";
    }

    // Crear nuevo indice compuesto (si no existe)
    try {
        $pdo->exec("ALTER TABLE survey_responses ADD INDEX idx_meeting_section (meeting_id, section_id)");
        echo "<p>Indice <code>idx_meeting_section</code> creado.</p>";
    } catch (PDOException $e) {
        echo "<p>Indice <code>idx_meeting_section</code> ya existe, saltando.</p>";
    }

    echo "<h1 style='color:green;'>Migracion v2 exitosa</h1>";
    echo "<p style='color:red;font-weight:bold;'>IMPORTANTE: Elimina este archivo (install_v2.php) del servidor ahora.</p>";

} catch (PDOException $e) {
    echo "<h1 style='color:red;'>Error en migracion</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
