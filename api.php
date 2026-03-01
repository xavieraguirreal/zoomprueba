<?php
/**
 * API endpoint multi-seccion con control del host
 *
 * GET  action=sections                          → lista secciones desde config
 * GET  action=get_active&meeting_id=X           → seccion activa + estado
 * POST action=set_active  {meeting_id, section_id} → host establece seccion activa
 * POST action=close       {meeting_id}          → host cierra encuesta
 * POST action=reopen      {meeting_id}          → host reabre encuesta
 * POST action=vote        {meeting_id, section_id, user_id, answer} → guardar voto (survey/scale)
 * GET  action=results&meeting_id=X&section_id=Y → resultados + estado
 * GET  action=poll&meeting_id=X                 → participante consulta estado
 * POST action=submit_word {meeting_id, section_id, user_id, word}  → guardar palabra (wordcloud)
 * GET  action=get_words&meeting_id=X&section_id=Y → palabras con frecuencia (ronda actual)
 * POST action=new_wordcloud {meeting_id}          → host crea nueva nube (incrementa ronda)
 * POST action=send_reaction {meeting_id, emoji, user_id}           → guardar reaccion
 * GET  action=get_reactions&meeting_id=X&since=T → reacciones recientes
 * POST action=start_quiz {meeting_id}           → host inicia quiz timer
 * POST action=quiz_answer {meeting_id, section_id, user_id, answer, time_ms} → respuesta quiz
 * GET  action=quiz_results&meeting_id=X&section_id=Y → leaderboard quiz
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
                "SELECT section_id, status, started_at FROM meeting_active_section WHERE meeting_id = ?"
            );
            $stmt->execute([$meetingId]);
            $row = $stmt->fetch();

            if ($row) {
                $response = [
                    'active' => true,
                    'section_id' => $row['section_id'],
                    'status' => $row['status'],
                ];
                // Incluir started_at y time_limit para quiz
                $sections = json_decode(APP_SECTIONS, true);
                $sType = isset($sections[$row['section_id']]) ? $sections[$row['section_id']]['type'] : '';
                if ($sType === 'quiz') {
                    $response['started_at'] = $row['started_at'];
                    $response['time_limit'] = $sections[$row['section_id']]['time_limit'] ?? 30;
                }
                jsonResponse($response);
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
                "INSERT INTO meeting_active_section (meeting_id, section_id, status, started_at)
                 VALUES (?, ?, 'voting', NULL)
                 ON DUPLICATE KEY UPDATE section_id = VALUES(section_id), status = 'voting', started_at = NULL"
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

            // Validar que la seccion existe y es tipo survey o scale
            $sections = json_decode(APP_SECTIONS, true);
            $sectionType = $sections[$sectionId]['type'] ?? '';
            if (!isset($sections[$sectionId]) || !in_array($sectionType, ['survey', 'scale'])) {
                jsonResponse(['error' => 'Seccion no valida para votar'], 400);
            }

            // Validar que answer esta dentro del rango
            if ($sectionType === 'scale') {
                $minVal = $sections[$sectionId]['min'] ?? 1;
                $maxVal = $sections[$sectionId]['max'] ?? 10;
                if ($answer < $minVal || $answer > $maxVal) {
                    jsonResponse(['error' => "answer debe ser entre $minVal y $maxVal"], 400);
                }
            } else {
                $maxOption = count($sections[$sectionId]['options']);
                if ($answer > $maxOption) {
                    jsonResponse(['error' => "answer debe ser entre 1 y $maxOption"], 400);
                }
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

            $sections = json_decode(APP_SECTIONS, true);
            $sectionType = isset($sections[$row['section_id']]) ? $sections[$row['section_id']]['type'] : '';

            // Datos extra segun tipo de seccion
            if ($sectionType === 'wordcloud') {
                // Obtener ronda actual
                $wcRoundStmt = $pdo->prepare(
                    "SELECT wordcloud_round FROM meeting_active_section WHERE meeting_id = ?"
                );
                $wcRoundStmt->execute([$meetingId]);
                $wcRoundRow = $wcRoundStmt->fetch();
                $wcRound = $wcRoundRow ? (int) $wcRoundRow['wordcloud_round'] : 1;
                $response['round'] = $wcRound;
                $response['words'] = getWordFrequencies($pdo, $meetingId, $row['section_id'], $wcRound);
            } elseif ($sectionType === 'reactions') {
                $since = date('Y-m-d H:i:s', time() - 10);
                $response['reactions'] = getRecentReactions($pdo, $meetingId, $since);
            } elseif ($sectionType === 'quiz') {
                // Incluir started_at y time_limit para sincronizar timer
                $activeStmt = $pdo->prepare(
                    "SELECT started_at FROM meeting_active_section WHERE meeting_id = ?"
                );
                $activeStmt->execute([$meetingId]);
                $activeRow = $activeStmt->fetch();
                $response['started_at'] = $activeRow['started_at'] ?? null;
                $response['time_limit'] = $sections[$row['section_id']]['time_limit'] ?? 30;
                if ($row['status'] === 'closed') {
                    $response['quiz_results'] = getQuizResults($pdo, $meetingId, $row['section_id']);
                }
            } elseif ($sectionType === 'scale') {
                if ($row['status'] === 'closed') {
                    $response['results'] = getScaleResults($pdo, $meetingId, $row['section_id'], $sections[$row['section_id']]);
                }
            } elseif ($sectionType === 'survey' && $row['status'] === 'closed') {
                $response['results'] = getResults($pdo, $meetingId, $row['section_id']);
            }

            jsonResponse($response);
            break;

        // ---- Word Cloud: guardar palabra ----
        case 'submit_word':
            $meetingId = $input['meeting_id'] ?? '';
            $sectionId = $input['section_id'] ?? '';
            $userId    = $input['user_id'] ?? 'anonymous';
            $word      = trim($input['word'] ?? '');

            if (!$meetingId || !$sectionId || $word === '') {
                jsonResponse(['error' => 'Faltan meeting_id, section_id o word'], 400);
            }

            $sections = json_decode(APP_SECTIONS, true);
            if (!isset($sections[$sectionId]) || $sections[$sectionId]['type'] !== 'wordcloud') {
                jsonResponse(['error' => 'Seccion no valida para wordcloud'], 400);
            }

            // Verificar si la seccion esta cerrada
            $statusStmt = $pdo->prepare(
                "SELECT status FROM meeting_active_section WHERE meeting_id = ?"
            );
            $statusStmt->execute([$meetingId]);
            $statusRow = $statusStmt->fetch();
            if ($statusRow && $statusRow['status'] === 'closed') {
                jsonResponse(['error' => 'La nube de palabras esta cerrada'], 403);
            }

            // Sanitizar: solo una palabra, max 30 chars
            $word = mb_substr($word, 0, 30);

            // Obtener ronda actual
            $roundStmt = $pdo->prepare(
                "SELECT wordcloud_round FROM meeting_active_section WHERE meeting_id = ?"
            );
            $roundStmt->execute([$meetingId]);
            $roundRow = $roundStmt->fetch();
            $currentRound = $roundRow ? (int) $roundRow['wordcloud_round'] : 1;

            // Verificar limite de palabras por usuario EN ESTA RONDA
            $maxWords = $sections[$sectionId]['max_words'] ?? 3;
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM wordcloud_entries WHERE meeting_id = ? AND section_id = ? AND user_id = ? AND round = ?"
            );
            $countStmt->execute([$meetingId, $sectionId, $userId, $currentRound]);
            $wordCount = (int) $countStmt->fetchColumn();

            if ($wordCount >= $maxWords) {
                jsonResponse(['error' => "Maximo $maxWords palabras permitidas"], 400);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO wordcloud_entries (meeting_id, section_id, user_id, word, round) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$meetingId, $sectionId, $userId, $word, $currentRound]);

            $words = getWordFrequencies($pdo, $meetingId, $sectionId, $currentRound);
            jsonResponse(['success' => true, 'words' => $words]);
            break;

        // ---- Word Cloud: obtener palabras con frecuencia (ronda actual) ----
        case 'get_words':
            $meetingId = $_GET['meeting_id'] ?? '';
            $sectionId = $_GET['section_id'] ?? '';
            if (!$meetingId || !$sectionId) {
                jsonResponse(['error' => 'Faltan meeting_id o section_id'], 400);
            }
            // Obtener ronda actual
            $roundStmt = $pdo->prepare(
                "SELECT wordcloud_round FROM meeting_active_section WHERE meeting_id = ?"
            );
            $roundStmt->execute([$meetingId]);
            $roundRow = $roundStmt->fetch();
            $currentRound = $roundRow ? (int) $roundRow['wordcloud_round'] : 1;
            $words = getWordFrequencies($pdo, $meetingId, $sectionId, $currentRound);
            jsonResponse(['words' => $words, 'round' => $currentRound]);
            break;

        // ---- Word Cloud: nueva nube (incrementar ronda) ----
        case 'new_wordcloud':
            $meetingId = $input['meeting_id'] ?? '';
            if (!$meetingId) {
                jsonResponse(['error' => 'Falta meeting_id'], 400);
            }
            $stmt = $pdo->prepare(
                "UPDATE meeting_active_section SET wordcloud_round = wordcloud_round + 1, status = 'voting' WHERE meeting_id = ?"
            );
            $stmt->execute([$meetingId]);

            // Obtener ronda actual
            $roundStmt = $pdo->prepare(
                "SELECT wordcloud_round FROM meeting_active_section WHERE meeting_id = ?"
            );
            $roundStmt->execute([$meetingId]);
            $roundRow = $roundStmt->fetch();
            $currentRound = $roundRow ? (int) $roundRow['wordcloud_round'] : 1;

            jsonResponse(['success' => true, 'round' => $currentRound]);
            break;

        // ---- Reactions: enviar reaccion ----
        case 'send_reaction':
            $meetingId = $input['meeting_id'] ?? '';
            $emoji     = $input['emoji'] ?? '';
            $userId    = $input['user_id'] ?? 'anonymous';

            if (!$meetingId || $emoji === '') {
                jsonResponse(['error' => 'Faltan meeting_id o emoji'], 400);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO reaction_events (meeting_id, emoji, user_id) VALUES (?, ?, ?)"
            );
            $stmt->execute([$meetingId, $emoji, $userId]);
            jsonResponse(['success' => true]);
            break;

        // ---- Reactions: obtener reacciones recientes ----
        case 'get_reactions':
            $meetingId = $_GET['meeting_id'] ?? '';
            $since     = $_GET['since'] ?? date('Y-m-d H:i:s', time() - 10);
            if (!$meetingId) {
                jsonResponse(['error' => 'Falta meeting_id'], 400);
            }
            $reactions = getRecentReactions($pdo, $meetingId, $since);
            jsonResponse(['reactions' => $reactions, 'server_time' => date('Y-m-d H:i:s')]);
            break;

        // ---- Quiz: host inicia timer ----
        case 'start_quiz':
            $meetingId = $input['meeting_id'] ?? '';
            if (!$meetingId) {
                jsonResponse(['error' => 'Falta meeting_id'], 400);
            }
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare(
                "UPDATE meeting_active_section SET started_at = ?, status = 'voting' WHERE meeting_id = ?"
            );
            $stmt->execute([$now, $meetingId]);
            jsonResponse(['success' => true, 'started_at' => $now]);
            break;

        // ---- Quiz: guardar respuesta con tiempo ----
        case 'quiz_answer':
            $meetingId = $input['meeting_id'] ?? '';
            $sectionId = $input['section_id'] ?? '';
            $userId    = $input['user_id'] ?? 'anonymous';
            $userName  = $input['user_name'] ?? null;
            $answer    = isset($input['answer']) ? (int) $input['answer'] : 0;
            $timeMs    = isset($input['time_ms']) ? (int) $input['time_ms'] : 0;

            if (!$meetingId || !$sectionId || $answer < 1) {
                jsonResponse(['error' => 'Faltan datos del quiz'], 400);
            }

            $sections = json_decode(APP_SECTIONS, true);
            if (!isset($sections[$sectionId]) || $sections[$sectionId]['type'] !== 'quiz') {
                jsonResponse(['error' => 'Seccion no valida para quiz'], 400);
            }

            // Verificar que no haya respondido ya
            $checkStmt = $pdo->prepare(
                "SELECT id FROM survey_responses WHERE meeting_id = ? AND section_id = ? AND user_id = ?"
            );
            $checkStmt->execute([$meetingId, $sectionId, $userId]);
            if ($checkStmt->fetch()) {
                jsonResponse(['error' => 'Ya respondiste este quiz'], 400);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO survey_responses (meeting_id, section_id, user_id, user_name, answer, response_time_ms) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$meetingId, $sectionId, $userId, $userName, $answer, $timeMs]);
            jsonResponse(['success' => true, 'message' => 'Respuesta guardada']);
            break;

        // ---- Quiz: resultados / leaderboard ----
        case 'quiz_results':
            $meetingId = $_GET['meeting_id'] ?? '';
            $sectionId = $_GET['section_id'] ?? '';
            if (!$meetingId || !$sectionId) {
                jsonResponse(['error' => 'Faltan meeting_id o section_id'], 400);
            }
            $quizResults = getQuizResults($pdo, $meetingId, $sectionId);
            jsonResponse(['results' => $quizResults]);
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

/**
 * Obtener palabras con frecuencia para wordcloud
 */
function getWordFrequencies(PDO $pdo, string $meetingId, string $sectionId, int $round = 0): array {
    if ($round > 0) {
        $stmt = $pdo->prepare(
            "SELECT LOWER(word) as word, COUNT(*) as count FROM wordcloud_entries WHERE meeting_id = ? AND section_id = ? AND round = ? GROUP BY LOWER(word) ORDER BY count DESC"
        );
        $stmt->execute([$meetingId, $sectionId, $round]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT LOWER(word) as word, COUNT(*) as count FROM wordcloud_entries WHERE meeting_id = ? AND section_id = ? GROUP BY LOWER(word) ORDER BY count DESC"
        );
        $stmt->execute([$meetingId, $sectionId]);
    }
    return $stmt->fetchAll();
}

/**
 * Obtener reacciones recientes (ultimos N segundos)
 */
function getRecentReactions(PDO $pdo, string $meetingId, string $since): array {
    $stmt = $pdo->prepare(
        "SELECT emoji, user_id, created_at FROM reaction_events WHERE meeting_id = ? AND created_at >= ? ORDER BY created_at ASC"
    );
    $stmt->execute([$meetingId, $since]);
    return $stmt->fetchAll();
}

/**
 * Obtener resultados del quiz con leaderboard
 */
function getQuizResults(PDO $pdo, string $meetingId, string $sectionId): array {
    $sections = json_decode(APP_SECTIONS, true);
    $correct = $sections[$sectionId]['correct'] ?? 0;
    $timeLimit = $sections[$sectionId]['time_limit'] ?? 30;

    $stmt = $pdo->prepare(
        "SELECT user_id, user_name, answer, response_time_ms FROM survey_responses WHERE meeting_id = ? AND section_id = ? ORDER BY response_time_ms ASC"
    );
    $stmt->execute([$meetingId, $sectionId]);
    $rows = $stmt->fetchAll();

    $leaderboard = [];
    foreach ($rows as $row) {
        $isCorrect = (int) $row['answer'] === $correct;
        $timeMs = (int) $row['response_time_ms'];
        // Score: correcto = base 1000 - (time_ms / time_limit_ms * 500), incorrecto = 0
        $score = 0;
        if ($isCorrect && $timeMs > 0) {
            $timeLimitMs = $timeLimit * 1000;
            $score = max(100, (int) (1000 - ($timeMs / $timeLimitMs * 500)));
        }
        $leaderboard[] = [
            'user_id' => $row['user_id'],
            'user_name' => $row['user_name'] ?? $row['user_id'],
            'answer' => (int) $row['answer'],
            'correct' => $isCorrect,
            'time_ms' => $timeMs,
            'score' => $score,
        ];
    }

    // Ordenar por score descendente, luego por tiempo ascendente
    usort($leaderboard, function ($a, $b) {
        if ($b['score'] !== $a['score']) return $b['score'] - $a['score'];
        return $a['time_ms'] - $b['time_ms'];
    });

    return [
        'correct_answer' => $correct,
        'total' => count($leaderboard),
        'leaderboard' => $leaderboard,
    ];
}

/**
 * Obtener resultados de escala (promedio + distribucion)
 */
function getScaleResults(PDO $pdo, string $meetingId, string $sectionId, array $sectionConfig): array {
    $minVal = $sectionConfig['min'] ?? 1;
    $maxVal = $sectionConfig['max'] ?? 10;

    $stmt = $pdo->prepare(
        "SELECT answer, COUNT(*) as count FROM survey_responses WHERE meeting_id = ? AND section_id = ? GROUP BY answer ORDER BY answer ASC"
    );
    $stmt->execute([$meetingId, $sectionId]);
    $rows = $stmt->fetchAll();

    $avgStmt = $pdo->prepare(
        "SELECT AVG(answer) as avg_val, COUNT(*) as total FROM survey_responses WHERE meeting_id = ? AND section_id = ?"
    );
    $avgStmt->execute([$meetingId, $sectionId]);
    $avgRow = $avgStmt->fetch();

    // Inicializar distribucion
    $distribution = [];
    for ($i = $minVal; $i <= $maxVal; $i++) {
        $distribution[$i] = 0;
    }
    foreach ($rows as $row) {
        $distribution[(int) $row['answer']] = (int) $row['count'];
    }

    return [
        'average' => $avgRow['avg_val'] !== null ? round((float) $avgRow['avg_val'], 1) : 0,
        'total' => (int) $avgRow['total'],
        'distribution' => $distribution,
    ];
}
