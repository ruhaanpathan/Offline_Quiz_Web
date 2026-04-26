<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!isTeacher()) { jsonResponse(false, 'Unauthorized'); }

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$quizId = (int)($input['quiz_id'] ?? 0);
$action = $input['action'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$quizId, getCurrentUserId()]);
if (!$stmt->fetch()) { jsonResponse(false, 'Quiz not found.'); }

if ($action === 'open_lobby') {
    $pdo->prepare("UPDATE quizzes SET status = 'lobby' WHERE id = ?")->execute([$quizId]);
    // Create live state
    $pdo->prepare("INSERT INTO quiz_live_state (quiz_id, current_question_index, phase) VALUES (?, 0, 'lobby') ON DUPLICATE KEY UPDATE phase = 'lobby', current_question_index = 0")->execute([$quizId]);
    jsonResponse(true, 'Lobby opened.');
}

if ($action === 'begin') {
    $pdo->prepare("UPDATE quizzes SET status = 'active', started_at = NOW() WHERE id = ?")->execute([$quizId]);
    $pdo->prepare("UPDATE quiz_live_state SET phase = 'question', current_question_index = 0, phase_changed_at = NOW() WHERE quiz_id = ?")->execute([$quizId]);
    jsonResponse(true, 'Quiz started.');
}

jsonResponse(false, 'Invalid action.');
