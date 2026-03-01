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
// SECCIONES DE LA APP
// =============================================
define('APP_SECTIONS', json_encode([
    'encuesta1' => [
        'title' => 'Como estas hoy?',
        'type' => 'survey',
        'icon' => "\xF0\x9F\x93\x8A",
        'options' => [
            1 => ['label' => 'Genial',      'emoji' => "\xF0\x9F\x98\x84", 'color' => '#22c55e'],
            2 => ['label' => 'Bien',        'emoji' => "\xF0\x9F\x98\x8A", 'color' => '#3b82f6'],
            3 => ['label' => 'Mas o menos', 'emoji' => "\xF0\x9F\x98\x90", 'color' => '#eab308'],
            4 => ['label' => 'Agotado',     'emoji' => "\xF0\x9F\x98\xA9", 'color' => '#ef4444'],
        ],
    ],
    'saludo' => [
        'title' => 'Bienvenida',
        'type' => 'greeting',
        'icon' => "\xF0\x9F\x91\x8B",
        'content' => 'Bienvenidos a la reunion de hoy!',
    ],
    'encuesta2' => [
        'title' => 'Que tema vemos hoy?',
        'type' => 'survey',
        'icon' => "\xF0\x9F\x93\x8A",
        'options' => [
            1 => ['label' => 'Repaso',     'emoji' => "\xF0\x9F\x93\x96", 'color' => '#8b5cf6'],
            2 => ['label' => 'Tema nuevo', 'emoji' => "\xF0\x9F\x86\x95", 'color' => '#06b6d4'],
            3 => ['label' => 'Ejercicios', 'emoji' => "\xE2\x9C\x8F\xEF\xB8\x8F", 'color' => '#f97316'],
        ],
    ],
    'nube' => [
        'title' => 'Describe el curso en una palabra',
        'type' => 'wordcloud',
        'icon' => "\xE2\x98\x81\xEF\xB8\x8F",
        'placeholder' => 'Escribe una palabra...',
        'max_words' => 3,
    ],
    'reacciones' => [
        'title' => 'Reacciones en vivo',
        'type' => 'reactions',
        'icon' => "\xF0\x9F\x8E\x89",
        'emojis' => ["\xF0\x9F\x94\xA5", "\xE2\x9D\xA4\xEF\xB8\x8F", "\xF0\x9F\x91\x8F", "\xF0\x9F\x98\x82", "\xF0\x9F\x8E\x89", "\xF0\x9F\x91\x8D"],
    ],
    'quiz1' => [
        'title' => 'Que lenguaje se usa para logica web?',
        'type' => 'quiz',
        'icon' => "\xF0\x9F\xA7\xA0",
        'time_limit' => 30,
        'correct' => 2,
        'options' => [
            1 => ['label' => 'HTML',       'color' => '#ef4444'],
            2 => ['label' => 'JavaScript', 'color' => '#22c55e'],
            3 => ['label' => 'CSS',        'color' => '#3b82f6'],
            4 => ['label' => 'Python',     'color' => '#eab308'],
        ],
    ],
    'escala1' => [
        'title' => 'Cuanto entendiste el tema?',
        'type' => 'scale',
        'icon' => "\xF0\x9F\x93\x8F",
        'min' => 1,
        'max' => 10,
        'min_label' => 'Nada',
        'max_label' => 'Todo',
    ],
]));

// =============================================
// ADMIN PANEL
// =============================================
define('ADMIN_PASSWORD', 'tu_password_admin');

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

/**
 * Obtener secciones desde DB (con fallback a APP_SECTIONS config)
 */
function getSections(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->query(
            "SELECT * FROM app_sections WHERE is_active = 1 ORDER BY sort_order ASC"
        );
        $rows = $stmt->fetchAll();

        if (!empty($rows)) {
            $sections = [];
            foreach ($rows as $row) {
                $section = [
                    'title' => $row['title'],
                    'type'  => $row['type'],
                    'icon'  => $row['icon'],
                ];
                $config = json_decode($row['config'], true) ?: [];
                $section = array_merge($section, $config);
                $sections[$row['section_key']] = $section;
            }
            $cache = $sections;
            return $sections;
        }
    } catch (Exception $e) {
        // Table doesn't exist or DB error — fall back to config
    }

    $cache = json_decode(APP_SECTIONS, true);
    return $cache;
}

/**
 * Validar token de administrador (valido por el dia actual)
 */
function validateAdminToken(string $token): bool {
    $expected = hash('sha256', ADMIN_PASSWORD . date('Y-m-d') . ZOOM_CLIENT_SECRET);
    return hash_equals($expected, $token);
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
