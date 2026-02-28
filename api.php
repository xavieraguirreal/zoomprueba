<?php
/**
 * API endpoint multi-seccion con control del host
 *
 * GET  action=sections                          → lista secciones desde config
 * GET  action=get_active&meeting_id=X           → seccion activa + estado
 * POST action=set_active  {meeting_id, section_id} → host establece seccion activa
 * POST action=close       {meeting_id}          → host cierra encuesta
 * POST action=reopen      {meeting_id}          → host reabre encuesta
 * POST action=vote        {meeting_id, section_id, user_id, answer} → guardar voto
 * GET  action=results&meeting_id=X&section_id=Y → resultados + estado
 * GET  action=poll&meeting_id=X                 → participante consulta estado
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? '') : '';

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

try {
    $pdo = getDB();

    switch ($action) {

        // ---- Lista de secciones (desde config) ----
        case 'sections':
            jsonResponse(['sections' => json_decode(APP_SECTIONS, true)]);
            break;

        // ---- Obtener seccion activa de un meeting ----
        case 'get_active':
            $meetingId = $_GET['meeting_id'] ?? '';
            if (!$meetingId) {
                jsonResponse(['error' => 'Falta meeting_id'], 400);
            }
            $stmt = $pdo->prepare(
                "SELECT section_id, status FROM meeting_active_section WHERE meeting_id = ?"
            );
            $stmt->execute([$meetingId]);
            $row = $stmt->fetch();

            if ($row) {
                jsonResponse([
                    'active' => true,
                    'section_id' => $row['section_id'],
                    'status' => $row['status'],
                ]);
            } else {
                jsonResponse(['active' => false]);
            }
            break;

        // ---- Host establece seccion activa ----
        case 'set_active':
            $meetingId = $input['meeting_id'] ?? '';
            $sectionId = $input['section_id'] ?? '';
            if (!$meetingId || !$sectionId) {
                jsonResponse(['error' => 'Faltan meeting_id o section_id'], 400);
            }
            $sections = json_decode(APP_SECTIONS, true);
            if (!isset($sections[$sectionId])) {
                jsonResponse(['error' => 'Seccion no valida'], 400);
            }
            $stmt = $pdo->prepare(
                "INSERT INTO meeting_active_section (meeting_id, section_id, status)
                 VALUES (?, ?, 'voting')
                 ON DUPLICATE KEY UPDATE section_id = VALUES(section_id), status = 'voting'"
            );
            $stmt->execute([$meetingId, $sectionId]);
            jsonResponse(['success' => true, 'section_id' => $sectionId, 'status' => 'voting']);
            break;

        // ---- Host cierra encuesta ----
        case 'close':
            $meetingId = $input['meeting_id'] ?? '';
            if (!$meetingId) {
                jsonResponse(['error' => 'Falta meeting_id'], 400);
            }
            $stmt = $pdo->prepare(
                "UPDATE meeting_active_section SET status = 'closed' WHERE meeting_id = ?"
            );
            $stmt->execute([$meetingId]);
            jsonResponse(['success' => true, 'status' => 'closed']);
            break;

        // ---- Host reabre encuesta ----
        case 'reopen':
            $meetingId = $input['meeting_id'] ?? '';
            if (!$meetingId) {
                jsonResponse(['error' => 'Falta meeting_id'], 400);
            }
            $stmt = $pdo->prepare(
                "UPDATE meeting_active_section SET status = 'voting' WHERE meeting_id = ?"
            );
            $stmt->execute([$meetingId]);
            jsonResponse(['success' => true, 'status' => 'voting']);
            break;

        // ---- Guardar voto ----
        case 'vote':
            $meetingId = $input['meeting_id'] ?? 'standalone';
            $sectionId = $input['section_id'] ?? '';
            $userId    = $input['user_id'] ?? 'anonymous';
            $userName  = $input['user_name'] ?? null;
            $answer    = isset($input['answer']) ? (int) $input['answer'] : 0;

            if (!$sectionId || $answer < 1) {
                jsonResponse(['error' => 'Faltan section_id o answer'], 400);
            }

            // Validar que la seccion existe y es tipo survey
            $sections = json_decode(APP_SECTIONS, true);
            if (!isset($sections[$sectionId]) || $sections[$sectionId]['type'] !== 'survey') {
                jsonResponse(['error' => 'Seccion no valida para votar'], 400);
            }

            // Validar que answer esta dentro del rango
            $maxOption = count($sections[$sectionId]['options']);
            if ($answer > $maxOption) {
                jsonResponse(['error' => "answer debe ser entre 1 y $maxOption"], 400);
            }

            // Verificar si la encuesta esta cerrada
            $statusStmt = $pdo->prepare(
                "SELECT status FROM meeting_active_section WHERE meeting_id = ?"
            );
            $statusStmt->execute([$meetingId]);
            $statusRow = $statusStmt->fetch();
            if ($statusRow && $statusRow['status'] === 'closed') {
                jsonResponse(['error' => 'La encuesta esta cerrada'], 403);
            }

            // Verificar si ya voto en esta seccion
            $checkStmt = $pdo->prepare(
                "SELECT id FROM survey_responses WHERE meeting_id = ? AND section_id = ? AND user_id = ?"
            );
            $checkStmt->execute([$meetingId, $sectionId, $userId]);

            if ($checkStmt->fetch()) {
                $stmt = $pdo->prepare(
                    "UPDATE survey_responses SET answer = ?, user_name = ? WHERE meeting_id = ? AND section_id = ? AND user_id = ?"
                );
                $stmt->execute([$answer, $userName, $meetingId, $sectionId, $userId]);
                $message = 'Respuesta actualizada';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO survey_responses (meeting_id, section_id, user_id, user_name, answer) VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([$meetingId, $sectionId, $userId, $userName, $answer]);
                $message = 'Respuesta guardada';
            }

            $results = getResults($pdo, $meetingId, $sectionId);
            jsonResponse([
                'success' => true,
                'message' => $message,
                'results' => $results,
            ]);
            break;

        // ---- Obtener resultados de una seccion ----
        case 'results':
            $meetingId = $_GET['meeting_id'] ?? 'standalone';
            $sectionId = $_GET['section_id'] ?? '';
            if (!$sectionId) {
                jsonResponse(['error' => 'Falta section_id'], 400);
            }

            // Obtener estado
            $statusStmt = $pdo->prepare(
                "SELECT status FROM meeting_active_section WHERE meeting_id = ?"
            );
            $statusStmt->execute([$meetingId]);
            $statusRow = $statusStmt->fetch();

            $results = getResults($pdo, $meetingId, $sectionId);
            jsonResponse([
                'results' => $results,
                'status' => $statusRow['status'] ?? 'voting',
            ]);
            break;

        // ---- Participante consulta estado (polling) ----
        case 'poll':
            $meetingId = $_GET['meeting_id'] ?? '';
            if (!$meetingId) {
                jsonResponse(['error' => 'Falta meeting_id'], 400);
            }

            $stmt = $pdo->prepare(
                "SELECT section_id, status FROM meeting_active_section WHERE meeting_id = ?"
            );
            $stmt->execute([$meetingId]);
            $row = $stmt->fetch();

            if (!$row) {
                jsonResponse(['active' => false]);
                break;
            }

            $response = [
                'active' => true,
                'section_id' => $row['section_id'],
                'status' => $row['status'],
            ];

            // Si esta cerrada y es survey, incluir resultados
            if ($row['status'] === 'closed') {
                $sections = json_decode(APP_SECTIONS, true);
                if (isset($sections[$row['section_id']]) && $sections[$row['section_id']]['type'] === 'survey') {
                    $response['results'] = getResults($pdo, $meetingId, $row['section_id']);
                }
            }

            jsonResponse($response);
            break;

        default:
            jsonResponse(['error' => 'Accion no valida: ' . $action], 400);
            break;
    }

} catch (PDOException $e) {
    jsonResponse(['error' => 'Error de base de datos'], 500);
}

/**
 * Obtener resultados de una encuesta para un meeting y seccion
 */
function getResults(PDO $pdo, string $meetingId, string $sectionId): array {
    $sections = json_decode(APP_SECTIONS, true);
    $optionCount = isset($sections[$sectionId]['options']) ? count($sections[$sectionId]['options']) : 0;

    $stmt = $pdo->prepare(
        "SELECT answer, COUNT(*) as count FROM survey_responses WHERE meeting_id = ? AND section_id = ? GROUP BY answer"
    );
    $stmt->execute([$meetingId, $sectionId]);
    $rows = $stmt->fetchAll();

    $totalStmt = $pdo->prepare(
        "SELECT COUNT(*) as total FROM survey_responses WHERE meeting_id = ? AND section_id = ?"
    );
    $totalStmt->execute([$meetingId, $sectionId]);
    $total = (int) $totalStmt->fetchColumn();

    // Inicializar todas las opciones en 0
    $answers = [];
    for ($i = 1; $i <= $optionCount; $i++) {
        $answers[$i] = 0;
    }

    foreach ($rows as $row) {
        $answers[(int) $row['answer']] = (int) $row['count'];
    }

    return [
        'total' => $total,
        'answers' => $answers,
    ];
}
