<?php
/**
 * LANparty — Export Quiz Report as CSV
 * Downloads a CSV file with student results, answers, and attendance
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isTeacher()) { die('Unauthorized'); }

$tid = getCurrentUserId();
$quizId = (int)($_GET['quiz_id'] ?? 0);
$type = $_GET['type'] ?? 'results'; // results | attendance | answers

// Verify quiz ownership
$quizStmt = $pdo->prepare("SELECT q.*, c.class_name, c.subject FROM quizzes q JOIN classes c ON q.class_id = c.id WHERE q.id = ? AND q.teacher_id = ?");
$quizStmt->execute([$quizId, $tid]);
$quiz = $quizStmt->fetch();
if (!$quiz) { die('Quiz not found.'); }

// Set CSV headers
$filename = strtolower(str_replace(' ', '_', $quiz['title'])) . '_' . $type . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

if ($type === 'results') {
    // ---- RESULTS: Student scores ----
    $students = $pdo->prepare("
        SELECT s.name, s.enrollment_no, qa.total_correct, qa.total_questions, qa.total_score, qa.joined_at
        FROM quiz_attempts qa
        JOIN students s ON qa.student_id = s.id
        WHERE qa.quiz_id = ?
        ORDER BY qa.total_correct DESC
    ");
    $students->execute([$quizId]);

    fputcsv($output, ['Quiz', $quiz['title']]);
    fputcsv($output, ['Class', $quiz['class_name'] . ' - ' . $quiz['subject']]);
    fputcsv($output, ['Code', $quiz['quiz_code']]);
    fputcsv($output, ['Date', $quiz['ended_at'] ?? $quiz['started_at'] ?? '']);
    fputcsv($output, []); // blank row
    fputcsv($output, ['Rank', 'Name', 'Enrollment', 'Correct', 'Total', 'Accuracy %', 'Joined At']);

    $rank = 1;
    foreach ($students->fetchAll() as $s) {
        $acc = $s['total_questions'] > 0 ? round(($s['total_correct'] / $s['total_questions']) * 100, 1) : 0;
        fputcsv($output, [
            $rank++,
            $s['name'],
            $s['enrollment_no'],
            $s['total_correct'],
            $s['total_questions'],
            $acc . '%',
            $s['joined_at']
        ]);
    }

} elseif ($type === 'attendance') {
    // ---- ATTENDANCE: Present/Absent ----
    $enrolled = $pdo->prepare("
        SELECT s.name, s.enrollment_no
        FROM class_students cs
        JOIN students s ON cs.student_id = s.id
        WHERE cs.class_id = ?
        ORDER BY s.name
    ");
    $enrolled->execute([$quiz['class_id']]);

    $attended = $pdo->prepare("SELECT student_id FROM quiz_attempts WHERE quiz_id = ?");
    $attended->execute([$quizId]);
    $attendedIds = array_column($attended->fetchAll(), 'student_id');

    fputcsv($output, ['Quiz', $quiz['title']]);
    fputcsv($output, ['Class', $quiz['class_name'] . ' - ' . $quiz['subject']]);
    fputcsv($output, ['Date', $quiz['ended_at'] ?? $quiz['started_at'] ?? '']);
    fputcsv($output, []);
    fputcsv($output, ['#', 'Name', 'Enrollment', 'Status']);

    $i = 1;
    foreach ($enrolled->fetchAll() as $s) {
        $present = in_array($s['enrollment_no'], $attendedIds) ? 'Present' : 'Absent';
        // Need to check by student ID, not enrollment
        $checkStmt = $pdo->prepare("SELECT id FROM students WHERE enrollment_no = ?");
        $checkStmt->execute([$s['enrollment_no']]);
        $sid = $checkStmt->fetchColumn();
        $status = in_array($sid, $attendedIds) ? 'Present' : 'Absent';
        fputcsv($output, [$i++, $s['name'], $s['enrollment_no'], $status]);
    }

} elseif ($type === 'answers') {
    // ---- ANSWERS: Per-question breakdown ----
    $questions = $pdo->prepare("SELECT qs.*, tp.topic_name FROM questions qs JOIN topics tp ON qs.topic_id = tp.id WHERE qs.quiz_id = ? ORDER BY qs.question_order, qs.id");
    $questions->execute([$quizId]);
    $qs = $questions->fetchAll();

    $students = $pdo->prepare("SELECT qa.id as attempt_id, s.name, s.enrollment_no FROM quiz_attempts qa JOIN students s ON qa.student_id = s.id WHERE qa.quiz_id = ? ORDER BY s.name");
    $students->execute([$quizId]);
    $studs = $students->fetchAll();

    $answers = $pdo->prepare("SELECT sa.attempt_id, sa.question_id, sa.selected_option, sa.is_correct FROM student_answers sa JOIN quiz_attempts qa ON sa.attempt_id = qa.id WHERE qa.quiz_id = ?");
    $answers->execute([$quizId]);
    $ansMap = [];
    foreach ($answers->fetchAll() as $a) {
        $ansMap[$a['attempt_id']][$a['question_id']] = $a;
    }

    // Header row
    $header = ['Name', 'Enrollment'];
    foreach ($qs as $qi => $q) {
        $header[] = 'Q' . ($qi + 1) . ' (Ans: ' . strtoupper($q['correct_option']) . ')';
    }
    $header[] = 'Total Correct';
    fputcsv($output, $header);

    // Student rows
    foreach ($studs as $s) {
        $row = [$s['name'], $s['enrollment_no']];
        $correct = 0;
        foreach ($qs as $q) {
            $ans = $ansMap[$s['attempt_id']][$q['id']] ?? null;
            if (!$ans || $ans['selected_option'] === null) {
                $row[] = '-';
            } else {
                $row[] = strtoupper($ans['selected_option']) . ($ans['is_correct'] ? ' ' : ' ');
                if ($ans['is_correct']) $correct++;
            }
        }
        $row[] = $correct . '/' . count($qs);
        fputcsv($output, $row);
    }
}

fclose($output);
exit;
