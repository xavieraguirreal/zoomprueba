<?php
/**
 * Migracion v4 - Sistema de rondas para wordcloud
 *
 * Ejecutar UNA SOLA VEZ para:
 * 1. Agregar columna round a wordcloud_entries
 * 2. Agregar columna wordcloud_round a meeting_active_section
 *
 * Despues de ejecutar, ELIMINAR este archivo del servidor.
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();

    // 1. Agregar columna round a wordcloud_entries
    $columns = $pdo->query("SHOW COLUMNS FROM wordcloud_entries LIKE 'round'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE wordcloud_entries ADD COLUMN round INT NOT NULL DEFAULT 1");
        echo "<p>Columna <code>round</code> agregada a wordcloud_entries.</p>";
    } else {
        echo "<p>Columna <code>round</code> ya existe en wordcloud_entries, saltando.</p>";
    }

    // 2. Agregar columna wordcloud_round a meeting_active_section
    $columns = $pdo->query("SHOW COLUMNS FROM meeting_active_section LIKE 'wordcloud_round'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE meeting_active_section ADD COLUMN wordcloud_round INT NOT NULL DEFAULT 1");
        echo "<p>Columna <code>wordcloud_round</code> agregada a meeting_active_section.</p>";
    } else {
        echo "<p>Columna <code>wordcloud_round</code> ya existe en meeting_active_section, saltando.</p>";
    }

    echo "<h1 style='color:green;'>Migracion v4 exitosa</h1>";
    echo "<p style='color:red;font-weight:bold;'>IMPORTANTE: Elimina este archivo (install_v4.php) del servidor ahora.</p>";

} catch (PDOException $e) {
    echo "<h1 style='color:red;'>Error en migracion</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
