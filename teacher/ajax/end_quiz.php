<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!isTeacher()) { jsonResponse(false, 'Unauthorized'); }

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$quizId = (int)($input['quiz_id'] ?? 0);

// End quiz
$pdo->prepare("UPDATE quizzes SET status = 'completed', ended_at = NOW() WHERE id = ?")->execute([$quizId]);
$pdo->prepare("UPDATE quiz_live_state SET phase = 'completed', phase_changed_at = NOW() WHERE quiz_id = ?")->execute([$quizId]);

// Calculate scores for all attempts
$attempts = $pdo->prepare("SELECT id FROM quiz_attempts WHERE quiz_id = ?");
$attempts->execute([$quizId]);
foreach ($attempts->fetchAll() as $att) {
    $score = $pdo->prepare("SELECT COUNT(*) as correct, (SELECT COUNT(*) FROM questions WHERE quiz_id = ?) as total FROM student_answers WHERE attempt_id = ? AND is_correct = 1");
    $score->execute([$quizId, $att['id']]);
    $s = $score->fetch();
    $pdo->prepare("UPDATE quiz_attempts SET total_correct = ?, total_questions = ?, total_score = ? WHERE id = ?")->execute([$s['correct'], $s['total'], $s['correct'], $att['id']]);
}

jsonResponse(true, 'Quiz ended.');
