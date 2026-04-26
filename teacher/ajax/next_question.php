<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!isTeacher()) { jsonResponse(false, 'Unauthorized'); }

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$quizId = (int)($input['quiz_id'] ?? 0);
$qIdx = (int)($input['question_index'] ?? 0);

$pdo->prepare("UPDATE quiz_live_state SET current_question_index = ?, phase = 'question', phase_changed_at = NOW() WHERE quiz_id = ?")->execute([$qIdx, $quizId]);
jsonResponse(true, 'Advanced.');
