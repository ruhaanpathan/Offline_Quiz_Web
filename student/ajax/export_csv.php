<?php
/**
 * LANparty — Export Student Performance as CSV
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isStudent()) { die('Unauthorized'); }

$sid = getCurrentUserId();
$studentName = getCurrentUserName();

// Get all quiz attempts
$attempts = $pdo->prepare("
    SELECT qa.*, q.title, q.quiz_code, c.class_name, c.subject, q.ended_at
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN classes c ON q.class_id = c.id
    WHERE qa.student_id = ?
    ORDER BY qa.joined_at DESC
");
$attempts->execute([$sid]);

$filename = 'my_performance_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['Student', $studentName]);
fputcsv($output, ['Exported', date('Y-m-d H:i')]);
fputcsv($output, []);
fputcsv($output, ['#', 'Quiz', 'Class', 'Subject', 'Code', 'Correct', 'Total', 'Accuracy %', 'Date']);

$i = 1;
foreach ($attempts->fetchAll() as $a) {
    $acc = $a['total_questions'] > 0 ? round(($a['total_correct'] / $a['total_questions']) * 100, 1) : 0;
    fputcsv($output, [
        $i++,
        $a['title'],
        $a['class_name'],
        $a['subject'],
        $a['quiz_code'],
        $a['total_correct'],
        $a['total_questions'],
        $acc . '%',
        $a['ended_at'] ?? $a['joined_at']
    ]);
}

fclose($output);
exit;
