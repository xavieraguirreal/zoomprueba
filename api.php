<?php
/**
 * API endpoint para la encuesta
 *
 * POST /api.php  - Guardar una respuesta
 * GET  /api.php  - Obtener resultados
 *
 * Parametros POST (JSON body):
 *   meeting_id: string (ID de la reunion)
 *   user_id:    string (ID del usuario Zoom)
 *   user_name:  string (nombre del usuario)
 *   answer:     int    (1-4)
 *
 * Parametros GET (query string):
 *   meeting_id: string (filtrar por reunion, opcional)
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDB();

    if ($method === 'POST') {
        // ---- GUARDAR RESPUESTA ----
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['answer'])) {
            jsonResponse(['error' => 'Falta el campo answer'], 400);
        }

        $answer = (int) $input['answer'];
        if ($answer < 1 || $answer > 4) {
            jsonResponse(['error' => 'answer debe ser entre 1 y 4'], 400);
        }

        $meetingId = $input['meeting_id'] ?? 'standalone';
        $userId    = $input['user_id'] ?? 'anonymous';
        $userName  = $input['user_name'] ?? null;

        // Verificar si el usuario ya voto en esta reunion
        $checkStmt = $pdo->prepare(
            "SELECT id FROM survey_responses WHERE meeting_id = ? AND user_id = ?"
        );
        $checkStmt->execute([$meetingId, $userId]);

        if ($checkStmt->fetch()) {
            // Actualizar voto existente
            $stmt = $pdo->prepare(
                "UPDATE survey_responses SET answer = ?, user_name = ? WHERE meeting_id = ? AND user_id = ?"
            );
            $stmt->execute([$answer, $userName, $meetingId, $userId]);
            $message = 'Respuesta actualizada';
        } else {
            // Insertar nuevo voto
            $stmt = $pdo->prepare(
                "INSERT INTO survey_responses (meeting_id, user_id, user_name, answer) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$meetingId, $userId, $userName, $answer]);
            $message = 'Respuesta guardada';
        }

        // Devolver resultados actualizados
        $results = getResults($pdo, $meetingId);
        jsonResponse([
            'success' => true,
            'message' => $message,
            'results' => $results,
        ]);

    } elseif ($method === 'GET') {
        // ---- OBTENER RESULTADOS ----
        $meetingId = $_GET['meeting_id'] ?? 'standalone';
        $results = getResults($pdo, $meetingId);
        jsonResponse(['results' => $results]);

    } else {
        jsonResponse(['error' => 'Metodo no permitido'], 405);
    }

} catch (PDOException $e) {
    jsonResponse(['error' => 'Error de base de datos'], 500);
}

/**
 * Obtener resultados de la encuesta para una reunion
 */
function getResults(PDO $pdo, string $meetingId): array {
    $stmt = $pdo->prepare(
        "SELECT answer, COUNT(*) as count FROM survey_responses WHERE meeting_id = ? GROUP BY answer"
    );
    $stmt->execute([$meetingId]);
    $rows = $stmt->fetchAll();

    $totalStmt = $pdo->prepare(
        "SELECT COUNT(*) as total FROM survey_responses WHERE meeting_id = ?"
    );
    $totalStmt->execute([$meetingId]);
    $total = (int) $totalStmt->fetchColumn();

    // Inicializar todas las opciones en 0
    $results = [
        'total' => $total,
        'answers' => [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
        ],
    ];

    foreach ($rows as $row) {
        $results['answers'][(int) $row['answer']] = (int) $row['count'];
    }

    return $results;
}
