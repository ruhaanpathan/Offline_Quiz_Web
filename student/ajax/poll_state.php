<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!isStudent()) { jsonResponse(false, 'Unauthorized'); }

$quizId = (int)($_POST['quiz_id'] ?? 0);

// If just looking up by code
if (!$quizId) {
    $code = strtoupper(trim($_POST['quiz_code'] ?? ''));
    if (!$code) { jsonResponse(false, 'No quiz code provided.'); }
    $stmt = $pdo->prepare("SELECT id, status FROM quizzes WHERE quiz_code = ?");
    $stmt->execute([$code]);
    $quiz = $stmt->fetch();
    if (!$quiz) { jsonResponse(false, 'Quiz not found. Check the code.'); }
    if (!in_array($quiz['status'], ['lobby', 'active'])) { jsonResponse(false, 'This quiz is not accepting students right now.'); }
    jsonResponse(true, '', ['quiz_id' => $quiz['id'], 'status' => $quiz['status']]);
}

// Poll state
if (!empty($_POST['poll'])) {
    $state = $pdo->prepare("SELECT * FROM quiz_live_state WHERE quiz_id = ?");
    $state->execute([$quizId]);
    $s = $state->fetch();
    if (!$s) { jsonResponse(true, '', ['phase' => 'lobby', 'question_index' => 0]); }

    $result = ['phase' => $s['phase'], 'question_index' => $s['current_question_index']];

    if ($s['phase'] === 'question') {
        $qStmt = $pdo->prepare("SELECT q.*, t.topic_name FROM questions q JOIN topics t ON q.topic_id = t.id WHERE q.quiz_id = ? ORDER BY q.question_order LIMIT 1 OFFSET ?");
        $qStmt->execute([$quizId, $s['current_question_index']]);
        $result['question'] = $qStmt->fetch();
    }

    jsonResponse(true, '', $result);
}

jsonResponse(false, 'Invalid request.');
