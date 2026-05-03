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
            <div class="stat-icon purple"><?= icon('file-text') ?></div>
            <div class="stat-info"><h4>Quizzes Taken</h4><div class="stat-value"><?= $quizCount ?></div></div>
        </a>
        <a href="#quizHistory" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer;transition:transform 0.2s;">
            <div class="stat-icon green"><?= icon('check-circle') ?></div>
            <div class="stat-info"><h4>Correct Answers</h4><div class="stat-value"><?= $totalCorrect ?>/<?= $totalQuestions ?></div></div>
        </a>
        <a href="/student/performance.php" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer;transition:transform 0.2s;">
            <div class="stat-icon blue"><?= icon('target') ?></div>
            <div class="stat-info"><h4>Accuracy</h4><div class="stat-value"><?= percentage($totalCorrect, $totalQuestions) ?>%</div></div>
        </a>
        <a href="#topicPerf" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer;transition:transform 0.2s;">
            <div class="stat-icon orange"><?= icon('tag') ?></div>
            <div class="stat-info"><h4>Topics Covered</h4><div class="stat-value"><?= count($topics) ?></div></div>
        </a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">

        <!-- Topic Performance Chart -->
        <div class="card">
            <div class="card-header"><h3 id="topicPerf">Topic Performance</h3></div>
            <?php if (empty($topics)): ?>
                <div class="empty-state" style="padding:30px;"><p>No quiz data yet for this class.</p></div>
            <?php else: ?>
                <div style="position:relative;height:260px;margin-bottom:16px;">
                    <canvas id="topicPolarChart"></canvas>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($topics as $ti => $t):
                        $pct = percentage($t['correct'], $t['total']);
                    ?>
                    <div style="display:flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(255,255,255,0.04);border-radius:6px;font-size:0.8rem;">
                        <span style="width:10px;height:10px;border-radius:3px;background:<?= ['#4F8EF7','#10B981','#8B5CF6','#F59E0B','#EF4444','#EC4899','#06B6D4','#84CC16'][$ti % 8] ?>;display:inline-block;"></span>
                        <span><?= sanitize($t['topic_name']) ?></span>
                        <span style="font-weight:700;color:<?= $pct >= 70 ? 'var(--accent-green)' : ($pct >= 40 ? '#ffc107' : '#ff3860') ?>;"><?= $pct ?>%</span>
                        <span style="color:var(--text-muted);">(<?= $t['correct'] ?>/<?= $t['total'] ?>)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Score Trend Chart -->
        <div class="card">
            <div class="card-header"><h3 id="quizHistory">Score Trend</h3></div>
            <?php
            $attemptedQuizzes = array_filter($quizzes, fn($q) => $q['total_questions'] > 0);
            ?>
            <?php if (empty($attemptedQuizzes)): ?>
                <div class="empty-state" style="padding:30px;"><p>No quizzes attempted yet.</p></div>
            <?php else: ?>
                <div style="position:relative;height:260px;margin-bottom:16px;">
                    <canvas id="scoreTrendLine"></canvas>
                </div>
                <div style="display:flex;justify-content:center;gap:20px;font-size:0.8rem;color:var(--text-muted);">
                    <span>● Avg: <?= $totalQuestions > 0 ? round($totalCorrect / $totalQuestions * 100) : 0 ?>%</span>
                    <span>Best: <?= $totalQuestions > 0 ? max(array_map(fn($q) => $q['total_questions'] > 0 ? round($q['total_correct'] / $q['total_questions'] * 100) : 0, $attemptedQuizzes)) : 0 ?>%</span>
                    <span><?= count($attemptedQuizzes) ?> quizzes</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quiz History List -->
    <div class="card">
        <div class="card-header"><h3>Quiz History</h3></div>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="/assets/js/chart.min.js"></script>
<script>
const chartColors = ['#4F8EF7','#10B981','#8B5CF6','#F59E0B','#EF4444','#EC4899','#06B6D4','#84CC16'];

// Topic Polar Area Chart
const polarCtx = document.getElementById('topicPolarChart');
if (polarCtx) {
    const topics = <?= json_encode(array_map(fn($t) => [
        'name' => $t['topic_name'],
        'pct' => (int)$t['total'] > 0 ? round((int)$t['correct'] / (int)$t['total'] * 100) : 0,
        'correct' => (int)$t['correct'],
        'total' => (int)$t['total']
    ], $topics)) ?>;
    new Chart(polarCtx, {
        type: 'polarArea',
        data: {
            labels: topics.map(t => t.name),
            datasets: [{
                data: topics.map(t => t.pct),
                backgroundColor: chartColors.slice(0, topics.length).map(c => c + '55'),
                borderColor: chartColors.slice(0, topics.length),
                borderWidth: 2
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
                    callbacks: { label: ctx => ctx.parsed.r + '% (' + topics[ctx.dataIndex].correct + '/' + topics[ctx.dataIndex].total + ')' }
                }
            },
            scales: {
                r: {
                    min: 0, max: 100,
                    ticks: { color: '#5f6572', backdropColor: 'transparent', stepSize: 25, font: { size: 9 } },
                    grid: { color: 'rgba(255,255,255,0.06)' }
                }
            }
        }
    });
}

// Score Trend Line Chart
const trendCtx = document.getElementById('scoreTrendLine');
if (trendCtx) {
    const quizData = <?= json_encode(array_values(array_map(fn($q) => [
        'title' => $q['title'],
        'pct' => (int)$q['total_questions'] > 0 ? round((int)$q['total_correct'] / (int)$q['total_questions'] * 100) : null,
        'correct' => (int)$q['total_correct'],
        'total' => (int)$q['total_questions']
    ], array_reverse(array_filter($quizzes, fn($q) => $q['total_questions'] > 0))))) ?>;
    const avg = <?= $totalQuestions > 0 ? round($totalCorrect / $totalQuestions * 100) : 0 ?>;

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: quizData.map(d => d.title.length > 10 ? d.title.substring(0,10)+'..' : d.title),
            datasets: [{
                label: 'Score %',
                data: quizData.map(d => d.pct),
                borderColor: '#4F8EF7',
                backgroundColor: 'rgba(79,142,247,0.08)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: quizData.map(d => d.pct >= 70 ? '#10B981' : d.pct >= 40 ? '#F59E0B' : '#EF4444'),
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            },{
                label: 'Average',
                data: quizData.map(() => avg),
                borderColor: 'rgba(139,92,246,0.4)',
                borderDash: [6, 4],
                borderWidth: 1.5,
                pointRadius: 0,
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { labels: { color: '#9aa0a8', font: { size: 10 }, usePointStyle: true, pointStyle: 'circle' } },
                tooltip: {
                    backgroundColor: '#1a1d27', titleColor: '#e8eaed', bodyColor: '#9aa0a8',
                    borderColor: 'rgba(255,255,255,0.1)', borderWidth: 1,
                    callbacks: { label: ctx => ctx.dataset.label === 'Average' ? 'Avg: '+avg+'%' : ctx.parsed.y+'% ('+quizData[ctx.dataIndex].correct+'/'+quizData[ctx.dataIndex].total+')' }
                }
            },
            scales: {
                x: { ticks: { color: '#5f6572', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.04)' } },
                y: { min: 0, max: 100, ticks: { color: '#5f6572', callback: v => v+'%' }, grid: { color: 'rgba(255,255,255,0.04)' } }
            }
        }
    });
}
</script>
