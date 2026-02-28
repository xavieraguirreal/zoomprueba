<?php
/**
 * Configuracion de la Zoom App - EJEMPLO
 *
 * INSTRUCCIONES:
 * 1. Copiar este archivo como config.php
 * 2. Completar los datos de Zoom y MySQL
 * 3. config.php esta en .gitignore (no se sube al repo)
 */

// =============================================
// ZOOM APP CREDENTIALS
// =============================================
define('ZOOM_CLIENT_ID', 'TU_CLIENT_ID_AQUI');
define('ZOOM_CLIENT_SECRET', 'TU_CLIENT_SECRET_AQUI');
define('ZOOM_REDIRECT_URI', 'https://tudominio.com/zoom-app/auth.php');

// URL base de tu app (sin barra final)
define('APP_BASE_URL', 'https://tudominio.com/zoom-app');

// =============================================
// BASE DE DATOS MYSQL (Ferozo)
// =============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'tu_base_de_datos');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password');

// =============================================
// CONFIGURACION DE LA ENCUESTA
// =============================================
define('SURVEY_QUESTION', 'Como estas hoy?');
define('SURVEY_OPTIONS', json_encode([
    1 => ['label' => 'Genial',      'emoji' => "\xF0\x9F\x98\x84", 'color' => '#22c55e'],
    2 => ['label' => 'Bien',        'emoji' => "\xF0\x9F\x98\x8A", 'color' => '#3b82f6'],
    3 => ['label' => 'Mas o menos', 'emoji' => "\xF0\x9F\x98\x90", 'color' => '#eab308'],
    4 => ['label' => 'Agotado',     'emoji' => "\xF0\x9F\x98\xA9", 'color' => '#ef4444'],
]));

// =============================================
// FUNCIONES AUXILIARES
// =============================================

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function sendSecurityHeaders(): void {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    header("X-Content-Type-Options: nosniff");
    header("Content-Security-Policy: default-src 'self' https://appssdk.zoom.us; script-src 'self' https://appssdk.zoom.us; style-src 'self' 'unsafe-inline'; connect-src 'self' https://api.zoom.us; img-src 'self' data:");
    header("Referrer-Policy: no-referrer");
}

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
