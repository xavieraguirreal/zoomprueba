<?php
/**
 * OAuth 2.0 callback handler para Zoom Apps
 *
 * Flujo:
 * 1. El usuario instala la app desde Zoom Marketplace
 * 2. Zoom redirige aqui con ?code=XXXX
 * 3. Intercambiamos el code por access_token
 * 4. Generamos un deep link para abrir la app dentro de Zoom
 * 5. Redirigimos al deep link
 */
session_start();
require_once __DIR__ . '/config.php';

$code = $_GET['code'] ?? null;

if (!$code) {
    http_response_code(400);
    echo "Error: No se recibio codigo de autorizacion.";
    exit;
}

// Paso 1: Intercambiar code por access_token
$ch = curl_init('https://zoom.us/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type'   => 'authorization_code',
        'code'         => $code,
        'redirect_uri' => ZOOM_REDIRECT_URI,
    ]),
    CURLOPT_HTTPHEADER => [
        'Authorization: Basic ' . base64_encode(ZOOM_CLIENT_ID . ':' . ZOOM_CLIENT_SECRET),
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 30,
]);

$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo "Error de conexion con Zoom: " . htmlspecialchars($curlError);
    exit;
}

$tokenData = json_decode($tokenResponse, true);

if ($httpCode !== 200 || !isset($tokenData['access_token'])) {
    http_response_code(500);
    echo "Error al obtener token de Zoom. Codigo HTTP: $httpCode";
    exit;
}

// Guardar tokens en sesion
$_SESSION['zoom_access_token']  = $tokenData['access_token'];
$_SESSION['zoom_refresh_token'] = $tokenData['refresh_token'];
$_SESSION['zoom_token_expires'] = time() + ($tokenData['expires_in'] ?? 3600);

// Paso 2: Generar deep link para abrir la app en Zoom
$ch = curl_init('https://api.zoom.us/v2/zoomapp/deeplink');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['action' => 'go']),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $tokenData['access_token'],
        'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 30,
]);

$deepLinkResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$deepLinkData = json_decode($deepLinkResponse, true);

if ($httpCode === 200 && isset($deepLinkData['deeplink'])) {
    // Redirigir al deep link - esto abre la app dentro del cliente Zoom
    header('Location: ' . $deepLinkData['deeplink']);
    exit;
}

// Fallback: si no se puede generar deep link, redirigir al Home URL
header('Location: ' . APP_BASE_URL . '/');
exit;
