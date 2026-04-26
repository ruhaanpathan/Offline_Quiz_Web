<?php
$pageTitle = 'Student Detail';
require_once __DIR__ . '/../includes/auth_check.php';
requireTeacher();
require_once __DIR__ . '/../config/db.php';

$tid = getCurrentUserId();
$studentId = (int)($_GET['student_id'] ?? 0);
$classId = (int)($_GET['class_id'] ?? 0);

$student = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$student->execute([$studentId]);
$student = $student->fetch();
if (!$student) { setFlash('error', 'Student not found.'); redirect('/teacher/dashboard.php'); }

$class = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$class->execute([$classId, $tid]);
$class = $class->fetch();

// All quiz attempts for this student in this class
$attempts = $pdo->prepare("
    SELECT qa.*, q.title, q.quiz_code, q.created_at as quiz_date
    FROM quiz_attempts qa 
    JOIN quizzes q ON qa.quiz_id = q.id 
    WHERE qa.student_id = ? AND q.class_id = ?
    ORDER BY qa.joined_at DESC
");
$attempts->execute([$studentId, $classId]);
$quizAttempts = $attempts->fetchAll();

// Topic-wise performance across ALL quizzes in this class
$topicPerf = $pdo->prepare("
    SELECT t.topic_name,
           COUNT(sa.id) as total,
           SUM(sa.is_correct) as correct,
           ROUND(SUM(sa.is_correct)*100/COUNT(sa.id),1) as accuracy
    FROM student_answers sa
    JOIN questions qs ON sa.question_id = qs.id
    JOIN topics t ON qs.topic_id = t.id
    JOIN quiz_attempts qa ON sa.attempt_id = qa.id
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.student_id = ? AND q.class_id = ?
    GROUP BY t.id, t.topic_name
    ORDER BY accuracy ASC
");
$topicPerf->execute([$studentId, $classId]);
$topicData = $topicPerf->fetchAll();

// Quiz score trend
$trend = $pdo->prepare("
    SELECT q.title, qa.total_correct, qa.total_questions,
           ROUND(qa.total_correct*100/NULLIF(qa.total_questions,0),1) as accuracy
    FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.student_id = ? AND q.class_id = ?
    ORDER BY qa.joined_at ASC
");
$trend->execute([$studentId, $classId]);
$trendData = $trend->fetchAll();

$totalAttempts = count($quizAttempts);
$overallAccuracy = $totalAttempts > 0 ? round(array_sum(array_column($quizAttempts, 'total_correct')) / max(array_sum(array_column($quizAttempts, 'total_questions')), 1) * 100, 1) : 0;
$attendance = $totalAttempts;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <h1><?= sanitize($student['name']) ?></h1>
            <div class="breadcrumb"><a href="/teacher/dashboard.php">Dashboard</a> / <a href="/teacher/class_detail.php?id=<?= $classId ?>"><?= sanitize($class['class_name'] ?? '') ?></a> / <?= sanitize($student['name']) ?></div>
            <div style="margin-top:4px;"><span class="badge badge-blue"><?= sanitize($student['enrollment_no']) ?></span></div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px;">
        <div class="stat-card"><div class="stat-icon purple">📝</div><div class="stat-info"><h4>Quizzes Taken</h4><div class="stat-value"><?= $totalAttempts ?></div></div></div>
        <div class="stat-card"><div class="stat-icon green">📊</div><div class="stat-info"><h4>Overall Accuracy</h4><div class="stat-value"><?= $overallAccuracy ?>%</div></div></div>
        <div class="stat-card"><div class="stat-icon blue">📋</div><div class="stat-info"><h4>Attendance</h4><div class="stat-value"><?= $attendance ?></div></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
        <!-- Topic Performance -->
        <div class="card">
            <div class="card-header"><h3>Topic-wise Performance</h3></div>
            <?php if (!empty($topicData)): ?>
            <canvas id="topicRadar" height="250"></canvas>
            <div style="margin-top:16px;">
                <?php foreach ($topicData as $t): ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-glass);">
                    <span><?= sanitize($t['topic_name']) ?></span>
                    <span class="badge <?= $t['accuracy']>=80?'badge-green':($t['accuracy']>=50?'badge-yellow':'badge-red') ?>"><?= $t['accuracy'] ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><p>No quiz data yet.</p></div>
            <?php endif; ?>
        </div>

        <!-- Score Trend -->
        <div class="card">
            <div class="card-header"><h3>Score Trend</h3></div>
            <?php if (!empty($trendData)): ?>
            <canvas id="trendChart" height="250"></canvas>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><p>No trend data yet.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quiz History -->
    <div class="card">
        <div class="card-header"><h3>Quiz History</h3></div>
        <div class="table-wrapper">
        <table>
            <thead><tr><th>Quiz</th><th>Code</th><th>Score</th><th>Accuracy</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($quizAttempts as $a): ?>
            <tr>
                <td><strong><?= sanitize($a['title']) ?></strong></td>
                <td><span class="badge badge-purple"><?= $a['quiz_code'] ?></span></td>
                <td><?= $a['total_correct'] ?>/<?= $a['total_questions'] ?></td>
                <td><span class="badge <?= percentage($a['total_correct'],$a['total_questions'])>=80?'badge-green':(percentage($a['total_correct'],$a['total_questions'])>=50?'badge-yellow':'badge-red') ?>"><?= percentage($a['total_correct'], $a['total_questions']) ?>%</span></td>
                <td style="font-size:0.8rem;color:var(--text-muted);"><?= formatDate($a['joined_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script src="/assets/js/chart.min.js"></script>
<script>
const topicLabels = <?= json_encode(array_column($topicData, 'topic_name')) ?>;
const topicValues = <?= json_encode(array_column($topicData, 'accuracy')) ?>;
const trendLabels = <?= json_encode(array_column($trendData, 'title')) ?>;
const trendValues = <?= json_encode(array_column($trendData, 'accuracy')) ?>;

if (topicLabels.length && typeof Chart !== 'undefined') {
    new Chart(document.getElementById('topicRadar'), {
        type: 'radar',
        data: { labels: topicLabels, datasets: [{ label: 'Accuracy %', data: topicValues, borderColor: '#4F8EF7', backgroundColor: 'rgba(79,142,247,0.15)', pointBackgroundColor: '#4F8EF7' }] },
        options: { responsive: true, scales: { r: { beginAtZero: true, max: 100, grid: { color: 'rgba(255,255,255,0.08)' }, ticks: { color: '#9aa0a8', backdropColor: 'transparent' }, pointLabels: { color: '#e8eaed' } } }, plugins: { legend: { display: false } } }
    });
}
if (trendLabels.length && typeof Chart !== 'undefined') {
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: { labels: trendLabels, datasets: [{ label: 'Accuracy %', data: trendValues, borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, tension: 0.4, pointBackgroundColor: '#10B981' }] },
        options: { responsive: true, scales: { y: { beginAtZero: true, max: 100, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9aa0a8' } }, x: { grid: { display: false }, ticks: { color: '#9aa0a8', maxRotation: 45 } } }, plugins: { legend: { display: false } } }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
