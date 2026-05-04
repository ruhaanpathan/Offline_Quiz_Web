<?php
$pageTitle = 'Quiz Report';
require_once __DIR__ . '/../includes/auth_check.php';
requireTeacher();
require_once __DIR__ . '/../config/db.php';

$tid = getCurrentUserId();
$quizId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT q.*, c.class_name, c.subject FROM quizzes q JOIN classes c ON q.class_id = c.id WHERE q.id = ? AND q.teacher_id = ?");
$stmt->execute([$quizId, $tid]);
$quiz = $stmt->fetch();
if (!$quiz) { setFlash('error', 'Quiz not found.'); redirect('/teacher/dashboard.php'); }

// Topic-wise performance
$topicStats = $pdo->prepare("
    SELECT t.topic_name, 
           COUNT(sa.id) as total_answers,
           SUM(sa.is_correct) as correct_answers,
           ROUND(SUM(sa.is_correct)*100/COUNT(sa.id),1) as accuracy
    FROM questions qs 
    JOIN topics t ON qs.topic_id = t.id 
    LEFT JOIN student_answers sa ON sa.question_id = qs.id
    WHERE qs.quiz_id = ?
    GROUP BY t.id, t.topic_name
    ORDER BY accuracy ASC
");
$topicStats->execute([$quizId]);
$topics = $topicStats->fetchAll();

// Student results
$studentResults = $pdo->prepare("
    SELECT s.name, s.enrollment_no, qa.total_correct, qa.total_questions, 
           ROUND(qa.total_correct*100/NULLIF(qa.total_questions,0),1) as accuracy,
           qa.joined_at, qa.student_id
    FROM quiz_attempts qa 
    JOIN students s ON qa.student_id = s.id 
    WHERE qa.quiz_id = ?
    ORDER BY qa.total_correct DESC
");
$studentResults->execute([$quizId]);
$students = $studentResults->fetchAll();

// Overall stats
$totalStudents = count($students);
$avgScore = $totalStudents > 0 ? round(array_sum(array_column($students, 'accuracy')) / $totalStudents, 1) : 0;
$totalQuestions = $quiz ? ($students[0]['total_questions'] ?? 0) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <h1><?= sanitize($quiz['title']) ?></h1>
            <div class="breadcrumb"><a href="/teacher/dashboard.php">Dashboard</a> / <a href="/teacher/class_detail.php?id=<?= $quiz['class_id'] ?>"><?= sanitize($quiz['class_name']) ?></a> / Report</div>
        </div>
        <span class="badge badge-blue" style="font-size:0.9rem;padding:8px 16px;">Code: <?= $quiz['quiz_code'] ?></span>
    </div>

    <!-- Overview Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px;">
        <div class="stat-card"><div class="stat-icon blue"><?= icon('users') ?></div><div class="stat-info"><h4>Students</h4><div class="stat-value"><?= $totalStudents ?></div></div></div>
        <div class="stat-card"><div class="stat-icon green"><?= icon('check-circle') ?></div><div class="stat-info"><h4>Avg Accuracy</h4><div class="stat-value"><?= $avgScore ?>%</div></div></div>
        <div class="stat-card"><div class="stat-icon purple"><?= icon('file-text') ?></div><div class="stat-info"><h4>Questions</h4><div class="stat-value"><?= $totalQuestions ?></div></div></div>
        <div class="stat-card"><div class="stat-icon yellow"><?= icon('tag') ?></div><div class="stat-info"><h4>Topics</h4><div class="stat-value"><?= count($topics) ?></div></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
        <!-- Topic Performance Chart -->
        <div class="card">
            <div class="card-header"><h3>Topic-wise Performance</h3></div>
            <canvas id="topicChart" height="250"></canvas>
            <div style="margin-top:16px;">
                <?php foreach ($topics as $t): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);">
                    <span><?= sanitize($t['topic_name']) ?></span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:100px;background:var(--bg-secondary);border-radius:4px;height:6px;overflow:hidden;">
                            <div style="height:100%;width:<?= $t['accuracy'] ?>%;background:<?= $t['accuracy']>=80?'var(--accent-green)':($t['accuracy']>=50?'var(--accent-yellow)':'var(--accent-red)') ?>;border-radius:4px;"></div>
                        </div>
                        <span class="badge <?= $t['accuracy']>=80?'badge-green':($t['accuracy']>=50?'badge-yellow':'badge-red') ?>"><?= $t['accuracy'] ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Weak/Strong Topics -->
        <div>
            <div class="card" style="margin-bottom:16px;">
                <div class="card-header"><h3>Weak Topics (&lt;50%)</h3></div>
                <?php $weak = array_filter($topics, fn($t) => $t['accuracy'] < 50); ?>
                <?php if (empty($weak)): ?>
                    <p style="color:var(--accent-green);">No weak topics! All above 50%.</p>
                <?php else: ?>
                    <?php foreach ($weak as $t): ?>
                    <div style="padding:8px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;">
                        <span style="color:var(--accent-red);"><?= sanitize($t['topic_name']) ?></span>
                        <span class="badge badge-red"><?= $t['accuracy'] ?>%</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="card-header"><h3>Strong Topics (&gt;80%)</h3></div>
                <?php $strong = array_filter($topics, fn($t) => $t['accuracy'] >= 80); ?>
                <?php if (empty($strong)): ?>
                    <p style="color:var(--text-muted);">No topics above 80% yet.</p>
                <?php else: ?>
                    <?php foreach ($strong as $t): ?>
                    <div style="padding:8px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;">
                        <span style="color:var(--accent-green);"><?= sanitize($t['topic_name']) ?></span>
                        <span class="badge badge-green"><?= $t['accuracy'] ?>%</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Student Results Table -->
    <div class="card">
        <div class="card-header"><h3>Student Rankings</h3></div>
        <div class="table-wrapper">
        <table>
            <thead><tr><th>Rank</th><th>Name</th><th>Enrollment</th><th>Score</th><th>Accuracy</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($students as $i => $s): ?>
            <tr>
                <td><strong><?= $i+1 ?></strong></td>
                <td><?= sanitize($s['name']) ?></td>
                <td><span class="badge badge-blue"><?= sanitize($s['enrollment_no']) ?></span></td>
                <td><?= $s['total_correct'] ?>/<?= $s['total_questions'] ?></td>
                <td>
                    <span class="badge <?= $s['accuracy']>=80?'badge-green':($s['accuracy']>=50?'badge-yellow':'badge-red') ?>"><?= $s['accuracy'] ?? 0 ?>%</span>
                </td>
                <td><a href="/teacher/student_detail.php?student_id=<?= $s['student_id'] ?>&class_id=<?= $quiz['class_id'] ?>" class="btn btn-outline btn-sm">Details</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script src="/assets/js/chart.min.js"></script>
<script>
Chart.defaults.font.family = "'Montserrat', sans-serif";
Chart.defaults.font.weight = 500;
const topicData = <?= json_encode($topics) ?>;
if (topicData.length > 0 && typeof Chart !== 'undefined') {
    new Chart(document.getElementById('topicChart'), {
        type: 'bar',
        data: {
            labels: topicData.map(t => { const max = window.innerWidth < 768 ? 10 : 30; return t.topic_name.length > max ? t.topic_name.substring(0,max)+'…' : t.topic_name; }),
            datasets: [{
                label: 'Accuracy %',
                data: topicData.map(t => t.accuracy),
                backgroundColor: topicData.map(t => t.accuracy >= 80 ? '#10B981' : t.accuracy >= 50 ? '#FBBF24' : '#EF4444'),
                borderRadius: 6,
                borderSkipped: false,
                barPercentage: 0.6,
                categoryPercentage: 0.7
            }]
        },
        options: {
            responsive: true,
            layout: { padding: { right: window.innerWidth < 768 ? 8 : 0 } },
            animation: { duration: 800, easing: 'easeOutQuart' },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: '#F3F4F6' }, border: { display: false }, ticks: { color: '#9CA3AF', font: { size: 10 }, callback: v => v + '%', stepSize: 25 } },
                x: { grid: { display: false }, border: { display: false }, ticks: { color: '#4B5563', font: { size: 11, weight: 600 } } }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#2D2A43', titleColor: '#fff', bodyColor: '#d1d5db',
                    borderColor: 'rgba(255,255,255,0.1)', borderWidth: 1,
                    padding: 12, cornerRadius: 10,
                    callbacks: {
                        label: function(ctx) {
                            const t = topicData[ctx.dataIndex];
                            return ' ' + t.accuracy + '% (' + t.correct_answers + '/' + t.total_answers + ' correct)';
                        }
                    }
                }
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
