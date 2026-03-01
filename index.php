<?php
/**
 * Home URL de la Zoom App - Multi-seccion con control del host
 *
 * La pagina carga las secciones como JSON y el JS se encarga
 * de renderizar menu, encuestas, saludos y controles del host.
 */
require_once __DIR__ . '/config.php';
sendSecurityHeaders();
// Anti-cache headers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
$version = time(); // cache-bust dinamico
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zoom App</title>
    <link rel="stylesheet" href="style.css?v=<?= $version ?>">
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

        <!-- Nube de palabras -->
        <div id="wordcloud-screen" class="screen hidden">
            <div class="card">
                <div id="host-controls-wordcloud" class="host-controls hidden">
                    <button class="btn-host btn-back" id="btn-back-menu-wordcloud">Volver al menu</button>
                    <button class="btn-host btn-close" id="btn-close-wordcloud">Cerrar nube</button>
                    <button class="btn-host btn-reopen hidden" id="btn-reopen-wordcloud">Reabrir nube</button>
                    <button class="btn-host btn-new" id="btn-new-wordcloud">Nueva nube</button>
                </div>
                <div id="wordcloud-round-nav" class="wordcloud-round-nav hidden">
                    <button id="btn-round-prev" class="round-nav-btn" disabled>&lt;</button>
                    <span id="wordcloud-round-label">Nube #1</span>
                    <button id="btn-round-next" class="round-nav-btn" disabled>&gt;</button>
                </div>
                <h1 id="wordcloud-title"></h1>
                <div id="wordcloud-cloud" class="wordcloud-cloud"></div>
                <div id="wordcloud-input-area" class="wordcloud-input">
                    <input type="text" id="wordcloud-input" placeholder="" maxlength="30">
                    <button id="wordcloud-submit">Enviar</button>
                </div>
                <div class="wordcloud-meta">
                    <span id="wordcloud-remaining"></span>
                    <span id="wordcloud-count"></span>
                </div>
            </div>
        </div>

        <!-- Reacciones -->
        <div id="reactions-screen" class="screen hidden">
            <div class="card">
                <div id="host-controls-reactions" class="host-controls hidden">
                    <button class="btn-host btn-back" id="btn-back-menu-reactions">Volver al menu</button>
                </div>
                <h1 class="question" id="reactions-title"></h1>
                <div id="reactions-stage" class="reactions-stage"></div>
                <div id="reactions-buttons" class="reactions-buttons"></div>
            </div>
        </div>

        <!-- Quiz -->
        <div id="quiz-screen" class="screen hidden">
            <div class="card">
                <div id="host-controls-quiz" class="host-controls hidden">
                    <button class="btn-host btn-back" id="btn-back-menu-quiz">Volver al menu</button>
                    <button class="btn-host btn-close" id="btn-close-quiz">Cerrar quiz</button>
                </div>
                <div id="quiz-timer" class="quiz-timer hidden">
                    <span id="quiz-timer-value">30</span>
                </div>
                <h1 class="question" id="quiz-question"></h1>
                <div id="quiz-options" class="options"></div>
                <button id="quiz-start" class="btn-host btn-reopen hidden">Iniciar Quiz</button>
            </div>
        </div>

        <!-- Quiz Leaderboard -->
        <div id="quiz-leaderboard-screen" class="screen hidden">
            <div class="card">
                <div id="host-controls-leaderboard" class="host-controls hidden">
                    <button class="btn-host btn-back" id="btn-back-menu-leaderboard">Volver al menu</button>
                </div>
                <h2 class="results-title">Podio</h2>
                <div id="quiz-podium" class="quiz-podium"></div>
                <div id="quiz-ranking" class="quiz-ranking"></div>
            </div>
        </div>

        <!-- Escala -->
        <div id="scale-screen" class="screen hidden">
            <div class="card">
                <div id="host-controls-scale" class="host-controls hidden">
                    <button class="btn-host btn-back" id="btn-back-menu-scale">Volver al menu</button>
                    <button class="btn-host btn-close" id="btn-close-scale">Cerrar escala</button>
                </div>
                <h1 class="question" id="scale-title"></h1>
                <div class="scale-container">
                    <span id="scale-min-label" class="scale-label"></span>
                    <input type="range" id="scale-slider" class="scale-slider">
                    <span id="scale-max-label" class="scale-label"></span>
                </div>
                <div id="scale-value" class="scale-value">5</div>
                <button id="scale-submit" class="option-btn scale-submit-btn">Enviar</button>
            </div>
        </div>

        <!-- Resultados escala -->
        <div id="scale-results-screen" class="screen hidden">
            <div class="card">
                <div id="host-controls-scale-results" class="host-controls hidden">
                    <button class="btn-host btn-back" id="btn-back-menu-scale-results">Volver al menu</button>
                    <button class="btn-host btn-reopen" id="btn-reopen-scale">Reabrir escala</button>
                </div>
                <h2 id="scale-results-title" class="results-title"></h2>
                <div id="scale-average" class="scale-average"></div>
                <div id="scale-distribution" class="results-chart"></div>
                <p class="total-votes">Total: <span id="scale-total">0</span> respuestas</p>
            </div>
        </div>

        <!-- Estado de conexion -->
        <div id="status-bar" class="status-bar">
            <span id="status-text">Conectando con Zoom...</span>
        </div>
    </div>

    <script src="app.js?v=<?= $version ?>"></script>
</body>
</html>
