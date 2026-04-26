<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!isTeacher()) { jsonResponse(false, 'Unauthorized'); }

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$quizId = (int)($input['quiz_id'] ?? 0);
$qIdx = (int)($input['question_index'] ?? 0);

// Get question at this index
$qStmt = $pdo->prepare("SELECT id FROM questions WHERE quiz_id = ? ORDER BY question_order LIMIT 1 OFFSET ?");
$qStmt->execute([$quizId, $qIdx]);
$question = $qStmt->fetch();
if (!$question) { jsonResponse(true, '', ['counts' => ['a'=>0,'b'=>0,'c'=>0,'d'=>0], 'total_answered' => 0, 'total_students' => 0]); }

// Count answers per option
$counts = ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0];
$cStmt = $pdo->prepare("SELECT selected_option, COUNT(*) as cnt FROM student_answers WHERE question_id = ? AND selected_option IS NOT NULL GROUP BY selected_option");
$cStmt->execute([$question['id']]);
foreach ($cStmt->fetchAll() as $row) { $counts[$row['selected_option']] = (int)$row['cnt']; }

$totalAnswered = array_sum($counts);
$totalStudents = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ?");
$totalStudents->execute([$quizId]);

jsonResponse(true, '', ['counts' => $counts, 'total_answered' => $totalAnswered, 'total_students' => $totalStudents->fetchColumn()]);
