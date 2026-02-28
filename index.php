<?php
/**
 * Home URL de la Zoom App - Pagina principal de la encuesta
 *
 * Esta es la pagina que Zoom carga en su navegador embebido.
 * Incluye los headers OWASP obligatorios y carga el Zoom Apps SDK.
 */
require_once __DIR__ . '/config.php';
sendSecurityHeaders();

$options = json_decode(SURVEY_OPTIONS, true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encuesta Zoom</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://appssdk.zoom.us/sdk.min.js"></script>
</head>
<body>
    <div class="app-container">
        <!-- Pantalla de la encuesta -->
        <div id="survey-screen" class="screen">
            <div class="card">
                <h1 class="question"><?= htmlspecialchars(SURVEY_QUESTION) ?></h1>
                <div class="options" id="options">
                    <?php foreach ($options as $value => $opt): ?>
                    <button
                        class="option-btn"
                        data-answer="<?= $value ?>"
                        style="--btn-color: <?= $opt['color'] ?>"
                    >
                        <span class="option-emoji"><?= $opt['emoji'] ?></span>
                        <span class="option-label"><?= htmlspecialchars($opt['label']) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Pantalla de resultados -->
        <div id="results-screen" class="screen hidden">
            <div class="card">
                <h2 class="results-title">Resultados</h2>
                <div id="results-chart" class="results-chart">
                    <?php foreach ($options as $value => $opt): ?>
                    <div class="result-row" data-answer="<?= $value ?>">
                        <div class="result-label">
                            <span><?= $opt['emoji'] ?></span>
                            <span><?= htmlspecialchars($opt['label']) ?></span>
                        </div>
                        <div class="result-bar-container">
                            <div class="result-bar" style="--bar-color: <?= $opt['color'] ?>; width: 0%"></div>
                        </div>
                        <span class="result-count">0</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="total-votes">Total: <span id="total-votes">0</span> votos</p>
                <button class="btn-change" id="btn-change">Cambiar mi respuesta</button>
            </div>
        </div>

        <!-- Estado de conexion -->
        <div id="status-bar" class="status-bar">
            <span id="status-text">Conectando con Zoom...</span>
        </div>
    </div>

    <script>
        // Opciones de la encuesta (para uso en JS)
        window.SURVEY_OPTIONS = <?= SURVEY_OPTIONS ?>;
    </script>
    <script src="app.js"></script>
</body>
</html>
