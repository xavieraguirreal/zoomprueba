<?php
/**
 * Home URL de la Zoom App - Multi-seccion con control del host
 *
 * La pagina carga las secciones como JSON y el JS se encarga
 * de renderizar menu, encuestas, saludos y controles del host.
 */
require_once __DIR__ . '/config.php';
sendSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zoom App</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://appssdk.zoom.us/sdk.min.js"></script>
</head>
<body>
    <div class="app-container">
        <!-- Pantalla de carga -->
        <div id="loading-screen" class="screen">
            <div class="card" style="text-align:center;">
                <p class="loading-text">Cargando...</p>
            </div>
        </div>

        <!-- Menu de secciones (solo host) -->
        <div id="menu-screen" class="screen hidden">
            <div class="card">
                <h1 class="menu-title">Secciones</h1>
                <div class="menu-grid" id="menu-grid">
                    <!-- Generado por JS -->
                </div>
            </div>
        </div>

        <!-- Pantalla de encuesta (dinamica) -->
        <div id="survey-screen" class="screen hidden">
            <div class="card">
                <div id="host-controls" class="host-controls hidden">
                    <button class="btn-host btn-back" id="btn-back-menu">Volver al menu</button>
                    <button class="btn-host btn-close" id="btn-close-survey">Cerrar encuesta</button>
                </div>
                <h1 class="question" id="survey-question"></h1>
                <div class="options" id="survey-options">
                    <!-- Generado por JS -->
                </div>
            </div>
        </div>

        <!-- Pantalla de resultados -->
        <div id="results-screen" class="screen hidden">
            <div class="card">
                <div id="host-controls-results" class="host-controls hidden">
                    <button class="btn-host btn-back" id="btn-back-menu-results">Volver al menu</button>
                    <button class="btn-host btn-reopen" id="btn-reopen-survey">Reabrir encuesta</button>
                </div>
                <h2 class="results-title" id="results-title">Resultados</h2>
                <div id="results-chart" class="results-chart">
                    <!-- Generado por JS -->
                </div>
                <p class="total-votes">Total: <span id="total-votes">0</span> votos</p>
                <button class="btn-change" id="btn-change">Cambiar mi respuesta</button>
            </div>
        </div>

        <!-- Pantalla de saludo -->
        <div id="greeting-screen" class="screen hidden">
            <div class="card">
                <div id="host-controls-greeting" class="host-controls hidden">
                    <button class="btn-host btn-back" id="btn-back-menu-greeting">Volver al menu</button>
                </div>
                <div class="greeting-content">
                    <div class="greeting-icon" id="greeting-icon"></div>
                    <h1 class="greeting-title" id="greeting-title"></h1>
                    <p class="greeting-text" id="greeting-text"></p>
                </div>
            </div>
        </div>

        <!-- Estado de conexion -->
        <div id="status-bar" class="status-bar">
            <span id="status-text">Conectando con Zoom...</span>
        </div>
    </div>

    <script>
        // Secciones de la app (desde PHP config)
        window.APP_SECTIONS = <?= APP_SECTIONS ?>;
    </script>
    <script src="app.js"></script>
</body>
</html>
