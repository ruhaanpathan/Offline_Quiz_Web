<?php
$pageTitle = 'Quiz Review';
require_once __DIR__ . '/../includes/auth_check.php';
requireStudent();
require_once __DIR__ . '/../config/db.php';

$sid = getCurrentUserId();
$quizId = (int)($_GET['id'] ?? 0);

// Get quiz info + verify student attempted it
$quizStmt = $pdo->prepare("
    SELECT q.*, c.class_name, c.subject, t.name as teacher_name,
           qa.total_score, qa.total_correct, qa.total_questions, qa.id as attempt_id
    FROM quizzes q
    JOIN classes c ON q.class_id = c.id
    JOIN teachers t ON q.teacher_id = t.id
    JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.student_id = ?
    WHERE q.id = ? AND q.status = 'completed'
");
$quizStmt->execute([$sid, $quizId]);
$quiz = $quizStmt->fetch();

if (!$quiz) {
    setFlash('error', 'Quiz not found or you did not attempt it.');
    redirect('/student/dashboard.php');
}

// Get all questions with student's answers
$questionsStmt = $pdo->prepare("
    SELECT qs.*, tp.topic_name,
           sa.selected_option, sa.is_correct, sa.time_taken_seconds
    FROM questions qs
    JOIN topics tp ON qs.topic_id = tp.id
    LEFT JOIN student_answers sa ON qs.id = sa.question_id AND sa.attempt_id = ?
    WHERE qs.quiz_id = ?
    ORDER BY qs.question_order, qs.id
");
$questionsStmt->execute([$quiz['attempt_id'], $quizId]);
$questions = $questionsStmt->fetchAll();

$accuracy = percentage($quiz['total_correct'], $quiz['total_questions']);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <a href="/student/dashboard.php" style="color:var(--text-muted);font-size:0.85rem;">← Back to Dashboard</a>
            <h1><?= sanitize($quiz['title']) ?></h1>
            <p style="color:var(--text-muted);"><?= sanitize($quiz['class_name']) ?> • <?= sanitize($quiz['subject']) ?> • Code: <?= sanitize($quiz['quiz_code']) ?></p>
        </div>
    </div>

    <!-- Score Summary -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:32px;">
        <div class="stat-card">
            <div class="stat-icon <?= $accuracy >= 70 ? 'green' : ($accuracy >= 40 ? 'orange' : 'red') ?>">
                <?= icon('award') ?>
            </div>
            <div class="stat-info"><h4>Score</h4><div class="stat-value"><?= $quiz['total_correct'] ?>/<?= $quiz['total_questions'] ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><?= icon('target') ?></div>
            <div class="stat-info"><h4>Accuracy</h4><div class="stat-value"><?= $accuracy ?>%</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><?= icon('calendar') ?></div>
            <div class="stat-info"><h4>Date</h4><div class="stat-value" style="font-size:0.9rem;"><?= formatDate($quiz['ended_at'] ?? $quiz['started_at']) ?></div></div>
        </div>
    </div>

    <!-- Questions -->
    <div class="card">
        <div class="card-header"><h3>Questions & Answers</h3></div>
        <div style="padding:8px 0;">
            <?php foreach ($questions as $i => $q):
                $selected = $q['selected_option'];
                $correct = $q['correct_option'];
                $isCorrect = $q['is_correct'];
                $unanswered = ($selected === null || $selected === '');
            ?>
            <div style="padding:20px 0;border-bottom:1px solid var(--border-glass);<?= $i === count($questions)-1 ? 'border:none;' : '' ?>">
                <!-- Question Header -->
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                    <div style="display:flex;gap:12px;align-items:flex-start;flex:1;">
                        <span style="background:<?= $isCorrect ? 'rgba(72,199,142,0.15)' : 'rgba(255,56,96,0.15)' ?>;color:<?= $isCorrect ? 'var(--accent-green)' : 'var(--accent-red, #ff3860)' ?>;padding:4px 10px;border-radius:6px;font-weight:700;font-size:0.8rem;white-space:nowrap;">
                            Q<?= $i + 1 ?>
                        </span>
                        <div>
                            <div style="font-weight:600;line-height:1.5;"><?= sanitize($q['question_text']) ?></div>
                            <span style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;display:inline-block;"><?= sanitize($q['topic_name']) ?></span>
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;margin-left:12px;">
                        <?php if ($isCorrect): ?>
                            <span style="color:var(--accent-green);font-weight:700;">Correct</span>
                        <?php elseif ($unanswered): ?>
                            <span style="color:var(--text-muted);font-weight:600;">⏭Skipped</span>
                        <?php else: ?>
                            <span style="color:var(--accent-red, #ff3860);font-weight:700;">Wrong</span>
                        <?php endif; ?>
                        <?php if ($q['time_taken_seconds'] > 0): ?>
                            <div style="font-size:0.75rem;color:var(--text-muted);"><?= $q['time_taken_seconds'] ?>s</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Options -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-left:38px;">
                    <?php foreach (['a','b','c','d'] as $opt):
                        $optText = $q['option_' . $opt];
                        $isThisCorrect = ($opt === $correct);
                        $isThisSelected = ($opt === $selected);

                        if ($isThisCorrect) {
                            $bg = 'rgba(72,199,142,0.15)';
                            $border = 'rgba(72,199,142,0.4)';
                            $color = 'var(--accent-green)';
                            $icon = icon('check', 14);
                        } elseif ($isThisSelected && !$isThisCorrect) {
                            $bg = 'rgba(255,56,96,0.15)';
                            $border = 'rgba(255,56,96,0.4)';
                            $color = 'var(--accent-red, #ff3860)';
                            $icon = icon('x', 14);
                        } else {
                            $bg = 'rgba(255,255,255,0.03)';
                            $border = 'rgba(255,255,255,0.08)';
                            $color = 'var(--text-secondary)';
                            $icon = '';
                        }
                    ?>
                    <div style="padding:10px 14px;border-radius:8px;background:<?= $bg ?>;border:1px solid <?= $border ?>;color:<?= $color ?>;font-size:0.85rem;display:flex;align-items:center;gap:8px;">
                        <span style="font-weight:700;text-transform:uppercase;opacity:0.6;"><?= $opt ?>.</span>
                        <span style="flex:1;"><?= sanitize($optText) ?></span>
                        <?php if ($icon): ?><span><?= $icon ?></span><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
