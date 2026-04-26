<?php
$pageTitle = 'My Performance';
require_once __DIR__ . '/../includes/auth_check.php';
requireStudent();
require_once __DIR__ . '/../config/db.php';

$sid = getCurrentUserId();

// Get enrolled classes with performance
$classes = $pdo->prepare("
    SELECT c.*, t.name as teacher_name,
           (SELECT COUNT(*) FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id WHERE qa.student_id = ? AND q.class_id = c.id) as quizzes_taken
    FROM class_students cs 
    JOIN classes c ON cs.class_id = c.id 
    JOIN teachers t ON c.teacher_id = t.id
    WHERE cs.student_id = ?
");
$classes->execute([$sid, $sid]);
$classList = $classes->fetchAll();

// Overall topic-wise across all classes
$topicPerf = $pdo->prepare("
    SELECT t.topic_name, c.subject,
           COUNT(sa.id) as total,
           SUM(sa.is_correct) as correct,
           ROUND(SUM(sa.is_correct)*100/COUNT(sa.id),1) as accuracy
    FROM student_answers sa
    JOIN questions qs ON sa.question_id = qs.id
    JOIN topics t ON qs.topic_id = t.id
    JOIN quiz_attempts qa ON sa.attempt_id = qa.id
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN classes c ON q.class_id = c.id
    WHERE qa.student_id = ?
    GROUP BY t.id, t.topic_name, c.subject
    ORDER BY c.subject, accuracy ASC
");
$topicPerf->execute([$sid]);
$topicData = $topicPerf->fetchAll();

// All attempts
$allAttempts = $pdo->prepare("
    SELECT qa.*, q.title, q.quiz_code, c.class_name, c.subject
    FROM quiz_attempts qa 
    JOIN quizzes q ON qa.quiz_id = q.id 
    JOIN classes c ON q.class_id = c.id
    WHERE qa.student_id = ?
    ORDER BY qa.joined_at DESC
");
$allAttempts->execute([$sid]);
$attempts = $allAttempts->fetchAll();

$totalQuizzes = count($attempts);
$totalCorrect = array_sum(array_column($attempts, 'total_correct'));
$totalQuestions = array_sum(array_column($attempts, 'total_questions'));
$overallAccuracy = percentage($totalCorrect, $totalQuestions);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page-wrapper animate-fade">
    <div class="page-header"><h1>📊 My Performance</h1></div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px;">
        <div class="stat-card"><div class="stat-icon purple">📝</div><div class="stat-info"><h4>Quizzes</h4><div class="stat-value"><?= $totalQuizzes ?></div></div></div>
        <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-info"><h4>Accuracy</h4><div class="stat-value"><?= $overallAccuracy ?>%</div></div></div>
        <div class="stat-card"><div class="stat-icon blue">🎯</div><div class="stat-info"><h4>Correct</h4><div class="stat-value"><?= $totalCorrect ?>/<?= $totalQuestions ?></div></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
        <!-- Topic Performance -->
        <div class="card">
            <div class="card-header"><h3>Topic-wise Strengths</h3></div>
            <?php if (!empty($topicData)): ?>
            <canvas id="topicChart" height="250"></canvas>
            <div style="margin-top:16px;">
                <?php foreach ($topicData as $t): ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-glass);">
                    <div>
                        <span><?= sanitize($t['topic_name']) ?></span>
                        <span style="font-size:0.75rem;color:var(--text-muted);"> • <?= sanitize($t['subject']) ?></span>
                    </div>
                    <span class="badge <?= $t['accuracy']>=80?'badge-green':($t['accuracy']>=50?'badge-yellow':'badge-red') ?>"><?= $t['accuracy'] ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><p>Take quizzes to see your performance!</p></div>
            <?php endif; ?>
        </div>

        <!-- Quiz History -->
        <div class="card">
            <div class="card-header"><h3>Quiz History</h3></div>
            <?php if (empty($attempts)): ?>
            <div class="empty-state" style="padding:30px;"><p>No quizzes taken yet.</p></div>
            <?php else: ?>
            <?php foreach ($attempts as $a): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border-glass);">
                <div>
                    <strong style="font-size:0.9rem;"><?= sanitize($a['title']) ?></strong>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= sanitize($a['class_name']) ?> • <?= formatDate($a['joined_at']) ?></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:700;color:<?= percentage($a['total_correct'],$a['total_questions'])>=80?'var(--accent-green)':'var(--text-primary)' ?>;"><?= $a['total_correct'] ?>/<?= $a['total_questions'] ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= percentage($a['total_correct'], $a['total_questions']) ?>%</div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="/assets/js/chart.min.js"></script>
<script>
const labels = <?= json_encode(array_column($topicData, 'topic_name')) ?>;
const values = <?= json_encode(array_map('floatval', array_column($topicData, 'accuracy'))) ?>;
if (labels.length && typeof Chart !== 'undefined') {
    new Chart(document.getElementById('topicChart'), {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Accuracy %', data: values, backgroundColor: values.map(v => v >= 80 ? 'rgba(16,185,129,0.6)' : v >= 50 ? 'rgba(245,158,11,0.6)' : 'rgba(239,68,68,0.6)'), borderRadius: 6 }] },
        options: { indexAxis: 'y', responsive: true, scales: { x: { beginAtZero: true, max: 100, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9aa0a8' } }, y: { grid: { display: false }, ticks: { color: '#e8eaed' } } }, plugins: { legend: { display: false } } }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
