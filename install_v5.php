<?php
/**
 * Migracion v5 - Panel de administracion: tabla app_sections
 *
 * Ejecutar UNA SOLA VEZ para:
 * 1. Crear tabla app_sections
 * 2. Migrar secciones desde APP_SECTIONS (config.php) a la DB
 *
 * Despues de ejecutar, ELIMINAR este archivo del servidor.
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();

    // 1. Crear tabla app_sections si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_key VARCHAR(50) UNIQUE NOT NULL,
            title VARCHAR(255) NOT NULL,
            type ENUM('survey','greeting','wordcloud','reactions','quiz','scale') NOT NULL,
            icon VARCHAR(20) DEFAULT '',
            config JSON NOT NULL,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>Tabla <code>app_sections</code> creada (o ya existia).</p>";

    // 2. Migrar secciones desde config
    $existing = $pdo->query("SELECT COUNT(*) FROM app_sections")->fetchColumn();
    if ((int) $existing > 0) {
        echo "<p>La tabla ya contiene $existing secciones, saltando migracion de datos.</p>";
    } else {
        $sections = json_decode(APP_SECTIONS, true);
        $stmt = $pdo->prepare(
            "INSERT INTO app_sections (section_key, title, type, icon, config, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );

        $sortOrder = 0;
        foreach ($sections as $key => $section) {
            $title = $section['title'];
            $type  = $section['type'];
            $icon  = $section['icon'] ?? '';

            // Config = todo lo que no es title/type/icon
            $config = $section;
            unset($config['title'], $config['type'], $config['icon']);

            $stmt->execute([
                $key,
                $title,
                $type,
                $icon,
                json_encode($config, JSON_UNESCAPED_UNICODE),
                $sortOrder++,
            ]);
            echo "<p>Seccion <code>$key</code> ($type) migrada.</p>";
        }
        echo "<p><strong>" . count($sections) . " secciones migradas exitosamente.</strong></p>";
    }

    echo "<h1 style='color:green;'>Migracion v5 exitosa</h1>";
    echo "<p style='color:red;font-weight:bold;'>IMPORTANTE: Elimina este archivo (install_v5.php) del servidor ahora.</p>";

} catch (PDOException $e) {
    echo "<h1 style='color:red;'>Error en migracion</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
