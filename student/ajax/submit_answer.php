<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';
if (!isStudent()) { jsonResponse(false, 'Unauthorized'); }

$sid = getCurrentUserId();

// Join action
if (isset($_GET['action']) && $_GET['action'] === 'join') {
    $quizId = (int)($_POST['quiz_id'] ?? 0);
    if (!$quizId) { jsonResponse(false, 'Invalid quiz.'); }

    // Check quiz exists and is joinable
    $stmt = $pdo->prepare("SELECT id, class_id, status FROM quizzes WHERE id = ?");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();
    if (!$quiz || !in_array($quiz['status'], ['lobby', 'active'])) { jsonResponse(false, 'Quiz not available.'); }

    // Check student is enrolled in the class
    $enrolled = $pdo->prepare("SELECT id FROM class_students WHERE class_id = ? AND student_id = ?");
    $enrolled->execute([$quiz['class_id'], $sid]);
    if (!$enrolled->fetch()) { jsonResponse(false, 'You are not enrolled in this class.'); }

    // Create attempt if not exists
    try {
        $pdo->prepare("INSERT INTO quiz_attempts (quiz_id, student_id) VALUES (?, ?)")->execute([$quizId, $sid]);
    } catch (PDOException $e) {
        // Already joined, that's fine
    }
    jsonResponse(true, 'Joined successfully.');
}

// Submit answer
$attemptId = (int)($_POST['attempt_id'] ?? 0);
$questionId = (int)($_POST['question_id'] ?? 0);
$selected = $_POST['selected_option'] ?? null;

if (!$attemptId || !$questionId) { jsonResponse(false, 'Missing data.'); }

// Verify attempt belongs to student
$att = $pdo->prepare("SELECT id FROM quiz_attempts WHERE id = ? AND student_id = ?");
$att->execute([$attemptId, $sid]);
if (!$att->fetch()) { jsonResponse(false, 'Invalid attempt.'); }

// Get correct answer + time limit
$qStmt = $pdo->prepare("SELECT qs.correct_option, qs.time_limit_seconds, qs.quiz_id FROM questions qs WHERE qs.id = ?");
$qStmt->execute([$questionId]);
$q = $qStmt->fetch();
if (!$q) { jsonResponse(false, 'Question not found.'); }

// Server-side time check: reject answer if question time has expired (with 3s grace period for network lag)
$timeCheck = $pdo->prepare("
    SELECT TIMESTAMPDIFF(SECOND, phase_changed_at, NOW()) as elapsed 
    FROM quiz_live_state WHERE quiz_id = ?
");
$timeCheck->execute([$q['quiz_id']]);
$tc = $timeCheck->fetch();
if ($tc && (int)$tc['elapsed'] > ((int)$q['time_limit_seconds'] + 3)) {
    jsonResponse(false, "Time's up! Answer not accepted.", ['time_expired' => true]);
}

$isCorrect = ($selected === $q['correct_option']) ? 1 : 0;

// Insert answer ONLY if not already answered (first answer is final)
try {
    $check = $pdo->prepare("SELECT id FROM student_answers WHERE attempt_id = ? AND question_id = ?");
    $check->execute([$attemptId, $questionId]);
    if ($check->fetch()) {
        jsonResponse(true, 'Already answered.', ['already_answered' => true]);
    }

    $pdo->prepare("INSERT INTO student_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?, ?, ?, ?)")
        ->execute([$attemptId, $questionId, $selected, $isCorrect]);
    jsonResponse(true, 'Answer submitted.', ['already_answered' => false, 'is_correct' => (bool)$isCorrect]);
} catch (PDOException $e) {
    jsonResponse(false, 'Error saving answer.');
}
