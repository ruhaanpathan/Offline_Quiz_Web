<?php
$pageTitle = 'Class View';
require_once __DIR__ . '/../includes/auth_check.php';
requireStudent();
require_once __DIR__ . '/../config/db.php';

$sid = getCurrentUserId();
$classId = (int)($_GET['id'] ?? 0);

// Verify student is enrolled in this class
$classStmt = $pdo->prepare("
    SELECT c.*, t.name as teacher_name, t.email as teacher_email
    FROM class_students cs
    JOIN classes c ON cs.class_id = c.id
    JOIN teachers t ON c.teacher_id = t.id
    WHERE cs.student_id = ? AND cs.class_id = ?
");
$classStmt->execute([$sid, $classId]);
$class = $classStmt->fetch();

if (!$class) {
    setFlash('error', 'Class not found or you are not enrolled.');
    redirect('/student/dashboard.php');
}

// Get all completed quizzes for this class that this student attempted
$quizzesStmt = $pdo->prepare("
    SELECT q.*, qa.total_score, qa.total_correct, qa.total_questions, qa.joined_at as attempt_date
    FROM quizzes q
    LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.student_id = ?
    WHERE q.class_id = ? AND q.status = 'completed'
    ORDER BY q.ended_at DESC
");
$quizzesStmt->execute([$sid, $classId]);
$quizzes = $quizzesStmt->fetchAll();

// Topic-wise performance for this class
$topicStmt = $pdo->prepare("
    SELECT tp.topic_name,
           COUNT(sa.id) as total,
           SUM(sa.is_correct) as correct
    FROM student_answers sa
    JOIN questions qs ON sa.question_id = qs.id
    JOIN topics tp ON qs.topic_id = tp.id
    JOIN quiz_attempts qa ON sa.attempt_id = qa.id
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.student_id = ? AND q.class_id = ?
    GROUP BY tp.id, tp.topic_name
    ORDER BY tp.topic_name
");
$topicStmt->execute([$sid, $classId]);
$topics = $topicStmt->fetchAll();

// Calculate overall stats for this class
$totalCorrect = 0;
$totalQuestions = 0;
$quizCount = 0;
foreach ($quizzes as $q) {
    if ($q['total_questions'] > 0) {
        $totalCorrect += $q['total_correct'];
        $totalQuestions += $q['total_questions'];
        $quizCount++;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <a href="/student/dashboard.php" style="color:var(--text-muted);font-size:0.85rem;">← Back to Dashboard</a>
            <h1><?= sanitize($class['class_name']) ?></h1>
            <p style="color:var(--text-muted);"><?= sanitize($class['subject']) ?><?= $class['section'] ? ' • Section ' . sanitize($class['section']) : '' ?> • <?= sanitize($class['teacher_name']) ?></p>
        </div>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:32px;">
        <a href="#quizHistory" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer;transition:transform 0.2s;">
            <div class="stat-icon purple">📝</div>
            <div class="stat-info"><h4>Quizzes Taken</h4><div class="stat-value"><?= $quizCount ?></div></div>
        </a>
        <a href="#quizHistory" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer;transition:transform 0.2s;">
            <div class="stat-icon green">✅</div>
            <div class="stat-info"><h4>Correct Answers</h4><div class="stat-value"><?= $totalCorrect ?>/<?= $totalQuestions ?></div></div>
        </a>
        <a href="/student/performance.php" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer;transition:transform 0.2s;">
            <div class="stat-icon blue">📊</div>
            <div class="stat-info"><h4>Accuracy</h4><div class="stat-value"><?= percentage($totalCorrect, $totalQuestions) ?>%</div></div>
        </a>
        <a href="#topicPerf" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer;transition:transform 0.2s;">
            <div class="stat-icon orange">🏷️</div>
            <div class="stat-info"><h4>Topics Covered</h4><div class="stat-value"><?= count($topics) ?></div></div>
        </a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

        <!-- Topic Performance -->
        <div class="card">
            <div class="card-header"><h3 id="topicPerf">Topic Performance</h3></div>
            <?php if (empty($topics)): ?>
                <div class="empty-state" style="padding:30px;"><p>No quiz data yet for this class.</p></div>
            <?php else: ?>
                <div style="padding:16px 0;">
                    <?php foreach ($topics as $t):
                        $pct = percentage($t['correct'], $t['total']);
                        $color = $pct >= 70 ? 'var(--accent-green)' : ($pct >= 40 ? 'var(--accent-yellow, #ffc107)' : 'var(--accent-red, #ff3860)');
                    ?>
                    <div style="margin-bottom:16px;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:0.85rem;">
                            <span><?= sanitize($t['topic_name']) ?></span>
                            <span style="color:<?= $color ?>;font-weight:600;"><?= $pct ?>% (<?= $t['correct'] ?>/<?= $t['total'] ?>)</span>
                        </div>
                        <div style="height:8px;background:rgba(255,255,255,0.08);border-radius:4px;overflow:hidden;">
                            <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:4px;transition:width 0.6s ease;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quiz History -->
        <div class="card">
            <div class="card-header"><h3 id="quizHistory">Quiz History</h3></div>
            <?php if (empty($quizzes)): ?>
                <div class="empty-state" style="padding:30px;"><p>No quizzes completed in this class yet.</p></div>
            <?php else: ?>
                <?php foreach ($quizzes as $q): ?>
                <a href="<?= $q['total_questions'] > 0 ? '/student/quiz_review.php?id=' . $q['id'] : '#' ?>" style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--border-glass);color:var(--text-primary);text-decoration:none;transition:background 0.2s;border-radius:6px;padding-left:8px;padding-right:8px;<?= $q['total_questions'] > 0 ? '' : 'pointer-events:none;opacity:0.6;' ?>">
                    <div>
                        <strong style="font-size:0.9rem;"><?= sanitize($q['title']) ?></strong>
                        <div style="font-size:0.8rem;color:var(--text-muted);">
                            Code: <?= sanitize($q['quiz_code']) ?>
                            <?php if ($q['attempt_date']): ?>
                                • <?= formatDate($q['attempt_date']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="text-align:right;">
                            <?php if ($q['total_questions'] > 0): ?>
                                <?php $pct = percentage($q['total_correct'], $q['total_questions']); ?>
                                <div style="font-weight:700;font-size:1.1rem;color:<?= $pct >= 70 ? 'var(--accent-green)' : ($pct >= 40 ? 'var(--accent-yellow, #ffc107)' : 'var(--accent-red, #ff3860)') ?>;">
                                    <?= $q['total_correct'] ?>/<?= $q['total_questions'] ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);"><?= $pct ?>%</div>
                            <?php else: ?>
                                <span class="badge" style="background:rgba(255,255,255,0.1);color:var(--text-muted);">Missed</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($q['total_questions'] > 0): ?>
                            <span style="color:var(--text-muted);font-size:0.8rem;">→</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
