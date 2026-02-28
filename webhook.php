<?php
/**
 * Webhook para auto-deploy con Git
 *
 * Configurar en el servicio de Git (GitHub/GitLab/Bitbucket):
 *   URL: https://tudominio.com/zoom-app/webhook.php?token=TU_TOKEN_SECRETO
 *   Content Type: application/json
 *   Events: push
 *
 * IMPORTANTE: Cambiar el token secreto antes de subir al servidor
 */

// Token secreto para validar el webhook (cambiar esto)
$secret_token = 'CAMBIAR_ESTE_TOKEN_SECRETO_123';

// Validar token
$token = $_GET['token'] ?? '';
if (!hash_equals($secret_token, $token)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// Directorio del repositorio en el servidor
$repo_dir = __DIR__;

// Ejecutar git pull
$output = [];
$return_code = 0;

chdir($repo_dir);
exec('git pull origin main 2>&1', $output, $return_code);

// Log del resultado
$log = date('Y-m-d H:i:s') . " - git pull - code: $return_code\n" . implode("\n", $output) . "\n\n";
file_put_contents(__DIR__ . '/deploy.log', $log, FILE_APPEND);

if ($return_code === 0) {
    http_response_code(200);
    echo 'Deploy OK';
} else {
    http_response_code(500);
    echo 'Deploy failed. Check deploy.log';
}
