<?php
$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../includes/auth_check.php';
requireStudent();
require_once __DIR__ . '/../config/db.php';

$sid = getCurrentUserId();

// Get enrolled classes
$classesStmt = $pdo->prepare("SELECT c.*, t.name as teacher_name FROM class_students cs JOIN classes c ON cs.class_id = c.id JOIN teachers t ON c.teacher_id = t.id WHERE cs.student_id = ? ORDER BY c.class_name");
$classesStmt->execute([$sid]);
$classes = $classesStmt->fetchAll();

// Get quiz stats
$quizStats = $pdo->prepare("SELECT COUNT(*) as total, SUM(total_correct) as correct, SUM(total_questions) as questions FROM quiz_attempts WHERE student_id = ?");
$quizStats->execute([$sid]);
$stats = $quizStats->fetch();

// Recent attempts
$recentStmt = $pdo->prepare("SELECT qa.*, q.title, q.quiz_code, q.id as quiz_id, c.class_name, c.subject FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id JOIN classes c ON q.class_id = c.id WHERE qa.student_id = ? ORDER BY qa.joined_at DESC LIMIT 5");
$recentStmt->execute([$sid]);
$recent = $recentStmt->fetchAll();

// Chart: Score per quiz (last 8)
$scoreChart = $pdo->prepare("
    SELECT q.title, qa.total_correct, qa.total_questions,
           ROUND(qa.total_correct / qa.total_questions * 100, 1) as pct
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.student_id = ? AND qa.total_questions > 0
    ORDER BY qa.joined_at DESC LIMIT 8
");
$scoreChart->execute([$sid]);
$scoreData = array_reverse($scoreChart->fetchAll());

// Chart: Topic-wise accuracy
$topicChart = $pdo->prepare("
    SELECT tp.topic_name, COUNT(sa.id) as total, SUM(sa.is_correct) as correct
    FROM student_answers sa
    JOIN questions qs ON sa.question_id = qs.id
    JOIN topics tp ON qs.topic_id = tp.id
    JOIN quiz_attempts qa ON sa.attempt_id = qa.id
    WHERE qa.student_id = ?
    GROUP BY tp.id, tp.topic_name
    ORDER BY tp.topic_name
");
$topicChart->execute([$sid]);
$topicData = $topicChart->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page-wrapper animate-fade">
    <div class="page-header">
        <h1>Welcome, <?= sanitize(getCurrentUserName()) ?> </h1>
        <a href="/student/join_quiz.php" class="btn btn-primary btn-lg"><?= icon('zap', 16) ?> Join Quiz</a>
        <a href="/student/ajax/export_csv.php" class="btn btn-outline"><?= icon('download', 16) ?> Download Report</a>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px;">
        <div class="stat-card">
            <div class="stat-icon blue"><?= icon('book') ?></div>
            <div class="stat-info"><h4>Classes</h4><div class="stat-value"><?= count($classes) ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><?= icon('file-text') ?></div>
            <div class="stat-info"><h4>Quizzes Taken</h4><div class="stat-value"><?= $stats['total'] ?? 0 ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><?= icon('check-circle') ?></div>
            <div class="stat-info"><h4>Accuracy</h4><div class="stat-value"><?= percentage($stats['correct'] ?? 0, $stats['questions'] ?? 0) ?>%</div></div>
        </div>
    </div>

    <!-- Charts -->
    <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;margin-bottom:32px;">
        <div class="card">
            <div class="card-header"><h3>My Quiz Scores</h3></div>
            <div style="position:relative;height:240px;">
                <canvas id="quizScoreChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Topic Accuracy</h3></div>
            <div style="position:relative;height:240px;">
                <canvas id="topicChart"></canvas>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <!-- My Classes -->
        <div class="card">
            <div class="card-header"><h3>My Classes</h3></div>
            <?php if (empty($classes)): ?>
                <div class="empty-state" style="padding:30px;"><p>Not enrolled in any class yet.</p></div>
            <?php else: ?>
                <?php foreach ($classes as $c): ?>
                <a href="/student/class_view.php?id=<?= $c['id'] ?>" style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--border-glass);color:var(--text-primary);">
                    <div>
                        <strong><?= sanitize($c['class_name']) ?></strong>
                        <div style="font-size:0.8rem;color:var(--text-muted);"><?= sanitize($c['subject']) ?> • <?= sanitize($c['teacher_name']) ?></div>
                    </div>
                    <span class="badge badge-blue">View →</span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Quizzes -->
        <div class="card">
            <div class="card-header"><h3>Recent Quizzes</h3><a href="/student/performance.php" class="btn btn-outline btn-sm">View All</a></div>
            <?php if (empty($recent)): ?>
                <div class="empty-state" style="padding:30px;"><p>No quizzes taken yet. Join a quiz to get started!</p></div>
            <?php else: ?>
                <?php foreach ($recent as $r): ?>
                <a href="/student/quiz_review.php?id=<?= $r['quiz_id'] ?>" style="display:flex;justify-content:space-between;align-items:center;padding:12px 8px;border-bottom:1px solid var(--border-glass);color:var(--text-primary);text-decoration:none;border-radius:6px;transition:background 0.2s;">
                    <div>
                        <strong style="font-size:0.9rem;"><?= sanitize($r['title']) ?></strong>
                        <div style="font-size:0.8rem;color:var(--text-muted);"><?= sanitize($r['class_name']) ?> • <?= formatDate($r['joined_at']) ?></div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="text-align:right;">
                            <div style="font-weight:700;color:var(--accent-green);"><?= $r['total_correct'] ?>/<?= $r['total_questions'] ?></div>
                            <div style="font-size:0.75rem;color:var(--text-muted);"><?= percentage($r['total_correct'], $r['total_questions']) ?>%</div>
                        </div>
                        <span style="color:var(--text-muted);font-size:0.8rem;">→</span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="/assets/js/chart.min.js"></script>
<script>
const chartColors = ['#4F8EF7','#10B981','#8B5CF6','#F59E0B','#EF4444','#EC4899','#06B6D4','#84CC16'];

// Quiz Score Bar Chart
const scoreCtx = document.getElementById('quizScoreChart');
if (scoreCtx) {
    const data = <?= json_encode($scoreData) ?>;
    new Chart(scoreCtx, {
        type: 'bar',
        data: {
            labels: data.map(d => d.title.length > 12 ? d.title.substring(0,12)+'...' : d.title),
            datasets: [{
                label: 'Score %',
                data: data.map(d => d.pct),
                backgroundColor: data.map(d => d.pct >= 70 ? 'rgba(16,185,129,0.7)' : d.pct >= 40 ? 'rgba(245,158,11,0.7)' : 'rgba(239,68,68,0.7)'),
                borderColor: data.map(d => d.pct >= 70 ? '#10B981' : d.pct >= 40 ? '#F59E0B' : '#EF4444'),
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a1d27', titleColor: '#e8eaed', bodyColor: '#9aa0a8',
                    borderColor: 'rgba(255,255,255,0.1)', borderWidth: 1,
                    callbacks: { label: ctx => ctx.parsed.y + '% (' + data[ctx.dataIndex].total_correct + '/' + data[ctx.dataIndex].total_questions + ')' }
                }
            },
            scales: {
                x: { ticks: { color: '#5f6572', font: { size: 10 } }, grid: { display: false } },
                y: { min: 0, max: 100, ticks: { color: '#5f6572', callback: v => v+'%' }, grid: { color: 'rgba(255,255,255,0.04)' } }
            }
        }
    });
}

// Topic Radar Chart
const topicCtx = document.getElementById('topicChart');
if (topicCtx) {
    const topics = <?= json_encode($topicData) ?>;
    if (topics.length >= 3) {
        new Chart(topicCtx, {
            type: 'radar',
            data: {
                labels: topics.map(t => t.topic_name),
                datasets: [{
                    label: 'Accuracy %',
                    data: topics.map(t => t.total > 0 ? Math.round(t.correct / t.total * 100) : 0),
                    backgroundColor: 'rgba(79,142,247,0.15)',
                    borderColor: '#4F8EF7',
                    borderWidth: 2,
                    pointBackgroundColor: '#4F8EF7',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#1a1d27', titleColor: '#e8eaed', bodyColor: '#9aa0a8' }
                },
                scales: {
                    r: {
                        min: 0, max: 100,
                        ticks: { color: '#5f6572', backdropColor: 'transparent', stepSize: 25 },
                        grid: { color: 'rgba(255,255,255,0.06)' },
                        pointLabels: { color: '#9aa0a8', font: { size: 11 } },
                        angleLines: { color: 'rgba(255,255,255,0.06)' }
                    }
                }
            }
        });
    } else {
        // Fallback: horizontal bar for < 3 topics
        new Chart(topicCtx, {
            type: 'bar',
            data: {
                labels: topics.map(t => t.topic_name),
                datasets: [{
                    label: 'Accuracy %',
                    data: topics.map(t => t.total > 0 ? Math.round(t.correct / t.total * 100) : 0),
                    backgroundColor: chartColors.slice(0, topics.length),
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { min: 0, max: 100, ticks: { color: '#5f6572', callback: v => v+'%' }, grid: { color: 'rgba(255,255,255,0.04)' } },
                    y: { ticks: { color: '#9aa0a8' }, grid: { display: false } }
                }
            }
        });
    }
}
</script>
