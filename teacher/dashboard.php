<?php
$pageTitle = 'Teacher Dashboard';
require_once __DIR__ . '/../includes/auth_check.php';
requireTeacher();
require_once __DIR__ . '/../config/db.php';

$tid = getCurrentUserId();

// Get stats
$classCount = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ?");
$classCount->execute([$tid]);
$totalClasses = $classCount->fetchColumn();

$studentCount = $pdo->prepare("SELECT COUNT(DISTINCT cs.student_id) FROM class_students cs JOIN classes c ON cs.class_id = c.id WHERE c.teacher_id = ?");
$studentCount->execute([$tid]);
$totalStudents = $studentCount->fetchColumn();

$quizCount = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE teacher_id = ?");
$quizCount->execute([$tid]);
$totalQuizzes = $quizCount->fetchColumn();

$activeQuiz = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE teacher_id = ? AND status IN ('lobby','active')");
$activeQuiz->execute([$tid]);
$liveQuizzes = $activeQuiz->fetchColumn();

// Recent quizzes
$recentQuizzes = $pdo->prepare("SELECT q.*, c.class_name, c.subject FROM quizzes q JOIN classes c ON q.class_id = c.id WHERE q.teacher_id = ? ORDER BY q.created_at DESC LIMIT 5");
$recentQuizzes->execute([$tid]);
$recent = $recentQuizzes->fetchAll();

// Classes
$classesList = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM class_students WHERE class_id = c.id) as student_count, (SELECT COUNT(*) FROM quizzes WHERE class_id = c.id) as quiz_count FROM classes c WHERE c.teacher_id = ? ORDER BY c.created_at DESC");
$classesList->execute([$tid]);
$classes = $classesList->fetchAll();

// Chart: Average scores per quiz (last 10 completed)
$chartQuizzes = $pdo->prepare("
    SELECT q.title, ROUND(AVG(qa.total_correct / qa.total_questions * 100), 1) as avg_score, COUNT(qa.id) as participants
    FROM quizzes q
    JOIN quiz_attempts qa ON q.id = qa.quiz_id
    WHERE q.teacher_id = ? AND q.status = 'completed' AND qa.total_questions > 0
    GROUP BY q.id, q.title
    ORDER BY q.ended_at DESC
    LIMIT 10
");
$chartQuizzes->execute([$tid]);
$chartData = array_reverse($chartQuizzes->fetchAll());

// Chart: Class-wise student count
$classChartData = [];
foreach ($classes as $c) {
    $classChartData[] = ['name' => $c['class_name'], 'students' => (int)$c['student_count'], 'quizzes' => (int)$c['quiz_count']];
}

// Chart: Topic-wise accuracy across all quizzes (teacher's classes)
$topicAnalysis = $pdo->prepare("
    SELECT tp.topic_name,
           COUNT(sa.id) as total_answers,
           SUM(sa.is_correct) as correct_answers,
           ROUND(SUM(sa.is_correct) / COUNT(sa.id) * 100) as accuracy
    FROM student_answers sa
    JOIN questions qs ON sa.question_id = qs.id
    JOIN topics tp ON qs.topic_id = tp.id
    JOIN quiz_attempts qa ON sa.attempt_id = qa.id
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE q.teacher_id = ? AND q.status = 'completed'
    GROUP BY tp.id, tp.topic_name
    ORDER BY accuracy ASC
");
$topicAnalysis->execute([$tid]);
$topicChartData = $topicAnalysis->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-wrapper animate-fade">
    <div class="page-header">
        <h1>Welcome back, <?= sanitize(getCurrentUserName()) ?></h1>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:32px;">
        <a href="/teacher/classes.php" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer;transition:transform 0.2s;">
            <div class="stat-icon blue"><?= icon('book') ?></div>
            <div class="stat-info"><h4>Classes</h4><div class="stat-value"><?= $totalClasses ?></div></div>
        </a>
        <a href="/teacher/classes.php" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer;transition:transform 0.2s;">
            <div class="stat-icon green"><?= icon('users') ?></div>
            <div class="stat-info"><h4>Students</h4><div class="stat-value"><?= $totalStudents ?></div></div>
        </a>
        <a href="/teacher/classes.php" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer;transition:transform 0.2s;">
            <div class="stat-icon purple"><?= icon('file-text') ?></div>
            <div class="stat-info"><h4>Quizzes</h4><div class="stat-value"><?= $totalQuizzes ?></div></div>
        </a>
        <?php
        // Get a live quiz to link to (if any)
        $liveLink = '#';
        if ($liveQuizzes > 0) {
            $liveQ = $pdo->prepare("SELECT id FROM quizzes WHERE teacher_id = ? AND status IN ('lobby','active') LIMIT 1");
            $liveQ->execute([$tid]);
            $liveRow = $liveQ->fetch();
            if ($liveRow) $liveLink = '/teacher/host_quiz.php?id=' . $liveRow['id'];
        }
        ?>
        <a href="<?= $liveLink ?>" class="stat-card" style="text-decoration:none;color:inherit;cursor:pointer;transition:transform 0.2s;<?= $liveQuizzes > 0 ? 'border-color:var(--accent-green);' : '' ?>">
            <div class="stat-icon yellow"><?= icon('radio') ?></div>
            <div class="stat-info"><h4>Live Now</h4><div class="stat-value"><?= $liveQuizzes ?></div></div>
        </a>
    </div>

    <!-- Charts -->
    <div style="display:grid;grid-template-columns:3fr 2fr;gap:24px;margin-bottom:32px;">
        <div class="card">
            <div class="card-header"><h3>Class Average Score Trend</h3></div>
            <div style="position:relative;height:300px;padding:8px 4px 0;">
                <canvas id="scoreTrendChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Topic Analysis</h3></div>
            <div style="position:relative;height:<?= max(280, count($topicChartData) * 40) ?>px;padding:8px 4px 0;">
                <canvas id="topicAnalysisChart"></canvas>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <!-- Classes -->
        <div class="card">
            <div class="card-header">
                <h3>Your Classes</h3>
                <a href="/teacher/classes.php" class="btn btn-primary btn-sm">+ New Class</a>
            </div>
            <?php if (empty($classes)): ?>
                <div class="empty-state"><div class="empty-icon"></div><h3>No classes yet</h3><p>Create your first class to get started.</p></div>
            <?php else: ?>
                <?php foreach ($classes as $c): ?>
                <a href="/teacher/class_detail.php?id=<?= $c['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border);color:var(--text-primary);">
                    <div>
                        <strong><?= sanitize($c['class_name']) ?></strong>
                        <div style="font-size:0.8rem;color:var(--text-muted);"><?= sanitize($c['subject']) ?> <?= $c['section'] ? '• ' . sanitize($c['section']) : '' ?></div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <span class="badge badge-blue"><?= $c['student_count'] ?> students</span>
                        <span class="badge badge-purple"><?= $c['quiz_count'] ?> quizzes</span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Quizzes -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Quizzes</h3>
            </div>
            <?php if (empty($recent)): ?>
                <div class="empty-state"><div class="empty-icon"></div><h3>No quizzes yet</h3><p>Create a quiz from a class page.</p></div>
            <?php else: ?>
                <?php foreach ($recent as $q): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border);">
                    <div>
                        <strong><?= sanitize($q['title']) ?></strong>
                        <div style="font-size:0.8rem;color:var(--text-muted);"><?= sanitize($q['class_name']) ?> • <?= sanitize($q['subject']) ?></div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <?php
                        $badgeClass = match($q['status']) {
                            'draft' => 'badge-yellow',
                            'lobby','active' => 'badge-green',
                            'completed' => 'badge-blue',
                            default => 'badge-blue'
                        };
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= $q['status'] ?></span>
                        <?php if ($q['status'] === 'completed'): ?>
                            <a href="/teacher/quiz_detail.php?id=<?= $q['id'] ?>" class="btn btn-outline btn-sm">Detail</a>
                            <a href="/teacher/quiz_report.php?id=<?= $q['id'] ?>" class="btn btn-outline btn-sm">Report</a>
                        <?php elseif ($q['status'] === 'draft'): ?>
                            <a href="/teacher/host_quiz.php?id=<?= $q['id'] ?>" class="btn btn-success btn-sm">Host</a>
                        <?php elseif (in_array($q['status'], ['lobby', 'active'])): ?>
                            <a href="/teacher/host_quiz.php?id=<?= $q['id'] ?>" class="btn btn-success btn-sm" style="animation:pulse 2s infinite;">▶ Continue</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="/assets/js/chart.min.js"></script>
<script>
// Premium color palette
const chartColors = ['#4F8EF7','#10B981','#8B5CF6','#F59E0B','#EF4444','#EC4899','#06B6D4','#84CC16'];

// Global Chart.js defaults for clean light theme
Chart.defaults.font.family = "'Montserrat', sans-serif";
Chart.defaults.font.weight = 500;
Chart.defaults.plugins.tooltip.padding = 12;
Chart.defaults.plugins.tooltip.cornerRadius = 10;
Chart.defaults.plugins.tooltip.displayColors = true;
Chart.defaults.plugins.tooltip.boxPadding = 6;

// Score Trend — Area/Line Chart
const trendCtx = document.getElementById('scoreTrendChart');
if (trendCtx) {
    const trendData = <?= json_encode($chartData) ?>;
    const ctx2d = trendCtx.getContext('2d');

    // Gradient fill for area
    const gradient = ctx2d.createLinearGradient(0, 0, 0, 250);
    gradient.addColorStop(0, 'rgba(79, 142, 247, 0.25)');
    gradient.addColorStop(0.5, 'rgba(79, 142, 247, 0.08)');
    gradient.addColorStop(1, 'rgba(79, 142, 247, 0.0)');

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendData.map(d => d.title.length > 12 ? d.title.substring(0,12)+'…' : d.title),
            datasets: [{
                label: 'Avg Score',
                data: trendData.map(d => d.avg_score),
                borderColor: '#4F8EF7',
                backgroundColor: gradient,
                fill: true,
                tension: 0.45,
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#4F8EF7',
                pointBorderWidth: 2.5,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointHoverBorderWidth: 3,
                pointHoverBackgroundColor: '#4F8EF7'
            },{
                label: 'Participants',
                data: trendData.map(d => d.participants),
                borderColor: '#8B5CF6',
                backgroundColor: 'transparent',
                fill: false,
                tension: 0.45,
                borderWidth: 2,
                borderDash: [6, 4],
                pointBackgroundColor: '#fff',
                pointBorderColor: '#8B5CF6',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { right: window.innerWidth < 768 ? 8 : 0 } },
            interaction: { intersect: false, mode: 'index' },
            animation: {
                duration: 800,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#6B7280',
                        font: { size: 11, weight: 600 },
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: '#2D2A43',
                    titleColor: '#fff',
                    titleFont: { size: 13, weight: 700 },
                    bodyColor: '#d1d5db',
                    bodyFont: { size: 12 },
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    callbacks: {
                        label: function(ctx) {
                            if (ctx.dataset.yAxisID === 'y1') return ' ' + ctx.parsed.y + ' students';
                            return ' ' + ctx.parsed.y + '%';
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#9CA3AF', font: { size: 10, weight: 500 } },
                    grid: { display: false },
                    border: { display: false }
                },
                y: {
                    min: 0, max: 100,
                    ticks: { color: '#9CA3AF', font: { size: 10 }, callback: v => v + '%', stepSize: 25 },
                    grid: { color: '#F3F4F6', lineWidth: 1 },
                    border: { display: false, dash: [4,4] }
                },
                y1: {
                    position: 'right',
                    ticks: { color: '#C4B5FD', font: { size: 10 }, stepSize: 1 },
                    grid: { display: false },
                    border: { display: false }
                }
            }
        }
    });
}

// Topic Analysis — Horizontal Bar Chart
const topicCtx = document.getElementById('topicAnalysisChart');
if (topicCtx) {
    const topics = <?= json_encode(array_map(fn($t) => [
        'name' => $t['topic_name'],
        'accuracy' => (int)$t['accuracy'],
        'correct' => (int)$t['correct_answers'],
        'total' => (int)$t['total_answers']
    ], $topicChartData)) ?>;

    new Chart(topicCtx, {
        type: 'bar',
        data: {
            labels: topics.map(t => { const max = window.innerWidth < 768 ? 14 : 30; return t.name.length > max ? t.name.substring(0,max)+'…' : t.name; }),
            datasets: [{
                label: 'Accuracy',
                data: topics.map(t => t.accuracy),
                backgroundColor: topics.map(t =>
                    t.accuracy >= 70 ? '#10B981' :
                    t.accuracy >= 40 ? '#FBBF24' : '#EF4444'
                ),
                borderRadius: 6,
                borderSkipped: false,
                barPercentage: 0.55,
                categoryPercentage: 0.8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            layout: { padding: { right: window.innerWidth < 768 ? 10 : 0 } },
            animation: { duration: 800, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#2D2A43',
                    titleColor: '#fff',
                    titleFont: { size: 13, weight: 700 },
                    bodyColor: '#d1d5db',
                    bodyFont: { size: 12 },
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    callbacks: {
                        label: function(ctx) {
                            const t = topics[ctx.dataIndex];
                            return ' ' + t.accuracy + '% (' + t.correct + '/' + t.total + ' correct)';
                        }
                    }
                }
            },
            scales: {
                x: {
                    min: 0, max: 100,
                    ticks: { color: '#9CA3AF', font: { size: 10 }, callback: v => v + '%', stepSize: 25 },
                    grid: { color: '#F3F4F6' },
                    border: { display: false }
                },
                y: {
                    ticks: { color: '#4B5563', font: { size: 11, weight: 600 } },
                    grid: { display: false },
                    border: { display: false }
                }
            }
        }
    });
}
</script>
