<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!isStudent()) { jsonResponse(false, 'Unauthorized'); }

$sid = getCurrentUserId();
$quizId = (int)($_POST['quiz_id'] ?? 0);

// Get this student's results
$att = $pdo->prepare("SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
$att->execute([$quizId, $sid]);
$attempt = $att->fetch();

$correctCount = 0;
$totalCount = 0;
if ($attempt) {
    $sc = $pdo->prepare("SELECT SUM(is_correct) as c, COUNT(*) as t FROM student_answers WHERE attempt_id = ?");
    $sc->execute([$attempt['id']]);
    $scores = $sc->fetch();
    $correctCount = (int)($scores['c'] ?? 0);
    $totalCount = (int)($scores['t'] ?? 0);

    // Update attempt totals
    $pdo->prepare("UPDATE quiz_attempts SET total_correct = ?, total_questions = ?, total_score = ? WHERE id = ?")
        ->execute([$correctCount, $totalCount, $correctCount, $attempt['id']]);
}

// Leaderboard
$lb = $pdo->prepare("SELECT qa.total_correct as score, qa.total_questions as total, s.name, s.id as sid FROM quiz_attempts qa JOIN students s ON qa.student_id = s.id WHERE qa.quiz_id = ? ORDER BY qa.total_correct DESC, qa.joined_at ASC LIMIT 10");
$lb->execute([$quizId]);
$leaderboard = [];
foreach ($lb->fetchAll() as $row) {
    $leaderboard[] = [
        'name' => $row['name'],
        'score' => (int)$row['score'],
        'total' => (int)$row['total'],
        'is_you' => $row['sid'] == $sid
    ];
}

jsonResponse(true, '', [
    'correct' => $correctCount,
    'total' => $totalCount,
    'leaderboard' => $leaderboard
]);
