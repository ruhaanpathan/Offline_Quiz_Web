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
    // Use TIMESTAMPDIFF to calculate elapsed seconds entirely in MySQL (no timezone mismatch)
    $state = $pdo->prepare("
        SELECT *, TIMESTAMPDIFF(SECOND, phase_changed_at, NOW()) as elapsed_seconds 
        FROM quiz_live_state WHERE quiz_id = ?
    ");
    $state->execute([$quizId]);
    $s = $state->fetch();
    if (!$s) { jsonResponse(true, '', ['phase' => 'lobby', 'question_index' => 0]); }

    $result = [
        'phase' => $s['phase'],
        'question_index' => (int)$s['current_question_index']
    ];

    if ($s['phase'] === 'question') {
        $qStmt = $pdo->prepare("SELECT q.*, t.topic_name FROM questions q JOIN topics t ON q.topic_id = t.id WHERE q.quiz_id = ? ORDER BY q.question_order LIMIT 1 OFFSET ?");
        $qStmt->execute([$quizId, $s['current_question_index']]);
        $question = $qStmt->fetch();
        // Strip correct answer — never send it to student client
        $safeQuestion = $question;
        unset($safeQuestion['correct_option']);
        $result['question'] = $safeQuestion;

        // Calculate remaining time using MySQL elapsed (no timezone issues)
        $timeLimit = (int)$question['time_limit_seconds'];
        $elapsed = (int)($s['elapsed_seconds'] ?? 0);
        $result['remaining_seconds'] = max(0, $timeLimit - $elapsed);
        $result['time_limit'] = $timeLimit;

        // Check if this student already answered this question
        $sid = getCurrentUserId();
        $attStmt = $pdo->prepare("SELECT id FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
        $attStmt->execute([$quizId, $sid]);
        $att = $attStmt->fetch();
        if ($att) {
            $ansStmt = $pdo->prepare("SELECT selected_option, is_correct FROM student_answers WHERE attempt_id = ? AND question_id = ?");
            $ansStmt->execute([$att['id'], $question['id']]);
            $existingAns = $ansStmt->fetch();
            if ($existingAns) {
                $result['already_answered'] = true;
                $result['selected_option'] = $existingAns['selected_option'];
                $result['was_correct'] = (bool)$existingAns['is_correct'];
            } else {
                $result['already_answered'] = false;
            }
        } else {
            $result['already_answered'] = false;
        }
    }

    jsonResponse(true, '', $result);
}

jsonResponse(false, 'Invalid request.');
