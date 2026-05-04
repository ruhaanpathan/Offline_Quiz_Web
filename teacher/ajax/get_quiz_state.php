<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!isTeacher()) { jsonResponse(false, 'Unauthorized'); }

$quizId = (int)($_GET['quiz_id'] ?? 0);
if (!$quizId) { jsonResponse(false, 'Missing quiz_id.'); }

$stmt = $pdo->prepare("SELECT current_question_index, phase FROM quiz_live_state WHERE quiz_id = ?");
$stmt->execute([$quizId]);
$state = $stmt->fetch();

if (!$state) {
    jsonResponse(true, '', ['question_index' => 0, 'phase' => 'lobby']);
} else {
    jsonResponse(true, '', ['question_index' => (int)$state['current_question_index'], 'phase' => $state['phase']]);
}
