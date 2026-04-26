<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!isTeacher()) { jsonResponse(false, 'Unauthorized'); }

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$quizId = (int)($input['quiz_id'] ?? 0);

$stmt = $pdo->prepare("SELECT s.name, s.enrollment_no FROM quiz_attempts qa JOIN students s ON qa.student_id = s.id WHERE qa.quiz_id = ? ORDER BY qa.joined_at DESC");
$stmt->execute([$quizId]);
jsonResponse(true, '', ['students' => $stmt->fetchAll()]);
