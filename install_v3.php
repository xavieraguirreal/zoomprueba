<?php
/**
 * Migracion v3 - Nuevos tipos de seccion: wordcloud, reactions, quiz, scale
 *
 * Ejecutar UNA SOLA VEZ para:
 * 1. Crear tabla wordcloud_entries
 * 2. Crear tabla reaction_events
 * 3. Agregar columna response_time_ms a survey_responses (para quiz)
 * 4. Agregar columna started_at a meeting_active_section (para quiz timer)
 *
 * Despues de ejecutar, ELIMINAR este archivo del servidor.
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();

    // 1. Crear tabla wordcloud_entries
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wordcloud_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id VARCHAR(255) NOT NULL,
            section_id VARCHAR(50) NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            word VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_meeting_section (meeting_id, section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>Tabla <code>wordcloud_entries</code> creada.</p>";

    // 2. Crear tabla reaction_events
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reaction_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id VARCHAR(255) NOT NULL,
            emoji VARCHAR(10) NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_meeting_created (meeting_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>Tabla <code>reaction_events</code> creada.</p>";

    // 3. Agregar columna response_time_ms a survey_responses (para quiz)
    $columns = $pdo->query("SHOW COLUMNS FROM survey_responses LIKE 'response_time_ms'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE survey_responses ADD COLUMN response_time_ms INT DEFAULT NULL");
        echo "<p>Columna <code>response_time_ms</code> agregada a survey_responses.</p>";
    } else {
        echo "<p>Columna <code>response_time_ms</code> ya existe, saltando.</p>";
    }

    // 4. Agregar columna started_at a meeting_active_section (para quiz timer)
    $columns = $pdo->query("SHOW COLUMNS FROM meeting_active_section LIKE 'started_at'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE meeting_active_section ADD COLUMN started_at TIMESTAMP NULL DEFAULT NULL");
        echo "<p>Columna <code>started_at</code> agregada a meeting_active_section.</p>";
    } else {
        echo "<p>Columna <code>started_at</code> ya existe, saltando.</p>";
    }

    echo "<h1 style='color:green;'>Migracion v3 exitosa</h1>";
    echo "<p style='color:red;font-weight:bold;'>IMPORTANTE: Elimina este archivo (install_v3.php) del servidor ahora.</p>";

} catch (PDOException $e) {
    echo "<h1 style='color:red;'>Error en migracion</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
