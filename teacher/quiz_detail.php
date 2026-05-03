<?php
$pageTitle = 'Quiz Detail';
require_once __DIR__ . '/../includes/auth_check.php';
requireTeacher();
require_once __DIR__ . '/../config/db.php';

$tid = getCurrentUserId();
$quizId = (int)($_GET['id'] ?? 0);

// Get quiz info + verify teacher owns it
$quizStmt = $pdo->prepare("
    SELECT q.*, c.class_name, c.subject
    FROM quizzes q
    JOIN classes c ON q.class_id = c.id
    WHERE q.id = ? AND q.teacher_id = ?
");
$quizStmt->execute([$quizId, $tid]);
$quiz = $quizStmt->fetch();

if (!$quiz) {
    setFlash('error', 'Quiz not found.');
    redirect('/teacher/dashboard.php');
}

// Get all questions with topics
$questionsStmt = $pdo->prepare("
    SELECT qs.*, tp.topic_name
    FROM questions qs
    JOIN topics tp ON qs.topic_id = tp.id
    WHERE qs.quiz_id = ?
    ORDER BY qs.question_order, qs.id
");
$questionsStmt->execute([$quizId]);
$questions = $questionsStmt->fetchAll();

// Get all students who attempted this quiz
$studentsStmt = $pdo->prepare("
    SELECT qa.id as attempt_id, qa.total_correct, qa.total_questions, qa.total_score, qa.joined_at,
           s.id as student_id, s.name, s.enrollment_no
    FROM quiz_attempts qa
    JOIN students s ON qa.student_id = s.id
    WHERE qa.quiz_id = ?
    ORDER BY qa.total_correct DESC, s.name
");
$studentsStmt->execute([$quizId]);
$students = $studentsStmt->fetchAll();

// Get ALL student answers for this quiz (grouped for easy lookup)
$answersStmt = $pdo->prepare("
    SELECT sa.attempt_id, sa.question_id, sa.selected_option, sa.is_correct, sa.time_taken_seconds
    FROM student_answers sa
    JOIN quiz_attempts qa ON sa.attempt_id = qa.id
    WHERE qa.quiz_id = ?
");
$answersStmt->execute([$quizId]);
$allAnswers = [];
foreach ($answersStmt->fetchAll() as $a) {
    $allAnswers[$a['question_id']][$a['attempt_id']] = $a;
}

// Count option distribution per question
$distStmt = $pdo->prepare("
    SELECT sa.question_id, sa.selected_option, COUNT(*) as cnt
    FROM student_answers sa
    JOIN quiz_attempts qa ON sa.attempt_id = qa.id
    WHERE qa.quiz_id = ?
    GROUP BY sa.question_id, sa.selected_option
");
$distStmt->execute([$quizId]);
$distribution = [];
foreach ($distStmt->fetchAll() as $d) {
    $distribution[$d['question_id']][$d['selected_option']] = $d['cnt'];
}

$totalStudents = count($students);

// Get ALL students enrolled in this class (for attendance)
$enrolledStmt = $pdo->prepare("
    SELECT s.id, s.name, s.enrollment_no
    FROM class_students cs
    JOIN students s ON cs.student_id = s.id
    WHERE cs.class_id = ?
    ORDER BY s.name
");
$enrolledStmt->execute([$quiz['class_id']]);
$enrolledStudents = $enrolledStmt->fetchAll();

// Build attended student IDs set
$attendedIds = array_column($students, 'student_id');
$presentCount = count($attendedIds);
$absentCount = count($enrolledStudents) - $presentCount;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <a href="/teacher/quiz_report.php?id=<?= $quizId ?>" style="color:var(--text-muted);font-size:0.85rem;">← Back to Report</a>
            <h1><?= sanitize($quiz['title']) ?> — Full Detail</h1>
            <p style="color:var(--text-muted);"><?= sanitize($quiz['class_name']) ?> • <?= sanitize($quiz['subject']) ?> • Code: <?= sanitize($quiz['quiz_code']) ?> • <?= $totalStudents ?> students</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="/teacher/ajax/export_csv.php?quiz_id=<?= $quizId ?>&type=results" class="btn btn-outline btn-sm">Results CSV</a>
            <a href="/teacher/ajax/export_csv.php?quiz_id=<?= $quizId ?>&type=attendance" class="btn btn-outline btn-sm">Attendance CSV</a>
            <a href="/teacher/ajax/export_csv.php?quiz_id=<?= $quizId ?>&type=answers" class="btn btn-outline btn-sm">Answers CSV</a>
            <button onclick="window.print()" class="btn btn-outline btn-sm">Print / PDF</button>
        </div>
    </div>

    <!-- Attendance -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <h3>Attendance</h3>
            <div style="display:flex;gap:12px;">
                <span style="display:flex;align-items:center;gap:6px;font-size:0.85rem;"><span style="width:10px;height:10px;border-radius:50%;background:var(--accent-green);display:inline-block;"></span> Present: <strong><?= $presentCount ?></strong></span>
                <span style="display:flex;align-items:center;gap:6px;font-size:0.85rem;"><span style="width:10px;height:10px;border-radius:50%;background:#ff3860;display:inline-block;"></span> Absent: <strong><?= $absentCount ?></strong></span>
                <span style="font-size:0.85rem;color:var(--text-muted);">Total: <strong><?= count($enrolledStudents) ?></strong></span>
            </div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:8px 0;">
            <?php foreach ($enrolledStudents as $es):
                $isPresent = in_array($es['id'], $attendedIds);
            ?>
            <div style="display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:8px;background:<?= $isPresent ? 'rgba(72,199,142,0.1)' : 'rgba(255,56,96,0.08)' ?>;border:1px solid <?= $isPresent ? 'rgba(72,199,142,0.2)' : 'rgba(255,56,96,0.15)' ?>;font-size:0.85rem;">
                <span style="font-size:0.9rem;"><?= $isPresent ? '' : '' ?></span>
                <div>
                    <div style="font-weight:600;color:<?= $isPresent ? 'var(--text-primary)' : 'var(--text-muted)' ?>;"><?= sanitize($es['name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= sanitize($es['enrollment_no']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Student Rankings -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h3>Student Rankings</h3></div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Enrollment</th>
                        <th>Score</th>
                        <th>Accuracy</th>
                        <?php foreach ($questions as $qi => $q): ?>
                            <th style="text-align:center;font-size:0.75rem;min-width:45px;">Q<?= $qi + 1 ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $rank => $s): ?>
                    <tr>
                        <td>
                            <?php if ($rank === 0): ?>                            <?php elseif ($rank === 1): ?>                            <?php elseif ($rank === 2): ?>                            <?php else: echo $rank + 1; endif; ?>
                        </td>
                        <td><strong><?= sanitize($s['name']) ?></strong></td>
                        <td style="color:var(--text-muted);font-size:0.85rem;"><?= sanitize($s['enrollment_no']) ?></td>
                        <td><strong><?= $s['total_correct'] ?>/<?= $s['total_questions'] ?></strong></td>
                        <td>
                            <?php $pct = percentage($s['total_correct'], $s['total_questions']); ?>
                            <span style="color:<?= $pct >= 70 ? 'var(--accent-green)' : ($pct >= 40 ? '#ffc107' : '#ff3860') ?>;font-weight:600;">
                                <?= $pct ?>%
                            </span>
                        </td>
                        <?php foreach ($questions as $q):
                            $ans = $allAnswers[$q['id']][$s['attempt_id']] ?? null;
                            if (!$ans || $ans['selected_option'] === null) {
                                $cellBg = 'rgba(255,255,255,0.03)';
                                $cellText = '—';
                                $cellColor = 'var(--text-muted)';
                            } elseif ($ans['is_correct']) {
                                $cellBg = 'rgba(72,199,142,0.15)';
                                $cellText = strtoupper($ans['selected_option']);
                                $cellColor = 'var(--accent-green)';
                            } else {
                                $cellBg = 'rgba(255,56,96,0.12)';
                                $cellText = strtoupper($ans['selected_option']);
                                $cellColor = '#ff3860';
                            }
                        ?>
                        <td style="text-align:center;background:<?= $cellBg ?>;color:<?= $cellColor ?>;font-weight:700;font-size:0.8rem;">
                            <?= $cellText ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Question-by-Question Breakdown -->
    <h2 style="margin-bottom:16px;">Question-by-Question Breakdown</h2>

    <?php foreach ($questions as $i => $q):
        $correct = $q['correct_option'];
        $dist = $distribution[$q['id']] ?? [];
        $totalResponses = array_sum($dist);
        $correctCount = $dist[$correct] ?? 0;
        $qAccuracy = percentage($correctCount, $totalStudents);
    ?>
    <div class="card" style="margin-bottom:16px;">
        <div style="padding:4px 0;">
            <!-- Question header -->
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
                <div style="display:flex;gap:12px;align-items:flex-start;flex:1;">
                    <span style="background:<?= $qAccuracy >= 60 ? 'rgba(72,199,142,0.15)' : 'rgba(255,56,96,0.15)' ?>;color:<?= $qAccuracy >= 60 ? 'var(--accent-green)' : '#ff3860' ?>;padding:6px 12px;border-radius:8px;font-weight:700;font-size:0.85rem;white-space:nowrap;">
                        Q<?= $i + 1 ?>
                    </span>
                    <div>
                        <div style="font-weight:600;font-size:1rem;line-height:1.5;"><?= sanitize($q['question_text']) ?></div>
                        <span style="font-size:0.75rem;color:var(--text-muted);"><?= sanitize($q['topic_name']) ?> • ⏱ <?= $q['time_limit_seconds'] ?>s</span>
                    </div>
                </div>
                <div style="text-align:right;flex-shrink:0;margin-left:16px;">
                    <div style="font-size:1.3rem;font-weight:700;color:<?= $qAccuracy >= 60 ? 'var(--accent-green)' : '#ff3860' ?>;"><?= $qAccuracy ?>%</div>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= $correctCount ?>/<?= $totalStudents ?> correct</div>
                </div>
            </div>

            <!-- Options with distribution bars -->
            <div style="display:grid;gap:8px;margin-bottom:16px;">
                <?php foreach (['a','b','c','d'] as $opt):
                    $optText = $q['option_' . $opt];
                    $isCorrect = ($opt === $correct);
                    $count = $dist[$opt] ?? 0;
                    $optPct = $totalStudents > 0 ? round(($count / $totalStudents) * 100) : 0;

                    if ($isCorrect) {
                        $bg = 'rgba(72,199,142,0.12)';
                        $border = 'rgba(72,199,142,0.3)';
                        $barColor = 'var(--accent-green)';
                        $icon = '';
                    } elseif ($count > 0) {
                        $bg = 'rgba(255,56,96,0.06)';
                        $border = 'rgba(255,56,96,0.15)';
                        $barColor = 'rgba(255,56,96,0.4)';
                        $icon = '';
                    } else {
                        $bg = 'rgba(255,255,255,0.02)';
                        $border = 'rgba(255,255,255,0.06)';
                        $barColor = 'rgba(255,255,255,0.1)';
                        $icon = '';
                    }
                ?>
                <div style="position:relative;padding:12px 16px;border-radius:8px;background:<?= $bg ?>;border:1px solid <?= $border ?>;overflow:hidden;">
                    <!-- Background bar -->
                    <div style="position:absolute;top:0;left:0;bottom:0;width:<?= $optPct ?>%;background:<?= $barColor ?>;opacity:0.15;transition:width 0.6s;"></div>
                    <div style="position:relative;display:flex;justify-content:space-between;align-items:center;">
                        <div style="display:flex;gap:8px;align-items:center;">
                            <span style="font-weight:700;text-transform:uppercase;opacity:0.5;font-size:0.85rem;"><?= $opt ?>.</span>
                            <span style="font-size:0.9rem;"><?= sanitize($optText) ?></span>
                            <?php if ($icon): ?><span><?= $icon ?></span><?php endif; ?>
                        </div>
                        <div style="font-weight:700;font-size:0.85rem;white-space:nowrap;">
                            <?= $count ?> <span style="opacity:0.5;font-weight:400;">(<?= $optPct ?>%)</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Who picked what -->
            <details style="margin-top:8px;">
                <summary style="cursor:pointer;color:var(--accent-blue);font-size:0.85rem;font-weight:600;padding:4px 0;">
                    Show individual student responses
                </summary>
                <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:6px;">
                    <?php foreach ($students as $s):
                        $ans = $allAnswers[$q['id']][$s['attempt_id']] ?? null;
                        $sel = $ans['selected_option'] ?? null;
                        if ($sel === null) {
                            $chipBg = 'rgba(255,255,255,0.06)';
                            $chipColor = 'var(--text-muted)';
                            $chipLabel = 'skip';
                        } elseif ($ans['is_correct']) {
                            $chipBg = 'rgba(72,199,142,0.15)';
                            $chipColor = 'var(--accent-green)';
                            $chipLabel = strtoupper($sel);
                        } else {
                            $chipBg = 'rgba(255,56,96,0.12)';
                            $chipColor = '#ff3860';
                            $chipLabel = strtoupper($sel);
                        }
                    ?>
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;background:<?= $chipBg ?>;font-size:0.75rem;" title="<?= sanitize($s['enrollment_no']) ?>">
                        <span style="color:var(--text-secondary);font-weight:500;"><?= sanitize($s['name']) ?></span>
                        <span style="color:<?= $chipColor ?>;font-weight:700;"><?= $chipLabel ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
