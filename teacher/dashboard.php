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

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page-wrapper animate-fade">
    <div class="page-header">
        <h1>Welcome back, <?= sanitize(getCurrentUserName()) ?> </h1>
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
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:32px;">
        <div class="card">
            <div class="card-header"><h3>Class Average Score Trend</h3></div>
            <div style="position:relative;height:250px;">
                <canvas id="scoreTrendChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Students per Class</h3></div>
            <div style="position:relative;height:250px;">
                <canvas id="classPieChart"></canvas>
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
                <a href="/teacher/class_detail.php?id=<?= $c['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border-glass);color:var(--text-primary);">
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
                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border-glass);">
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
const chartColors = ['#4F8EF7','#10B981','#8B5CF6','#F59E0B','#EF4444','#EC4899','#06B6D4','#84CC16'];

// Score Trend Line Chart
const trendCtx = document.getElementById('scoreTrendChart');
if (trendCtx) {
    const trendData = <?= json_encode($chartData) ?>;
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendData.map(d => d.title.length > 15 ? d.title.substring(0,15)+'...' : d.title),
            datasets: [{
                label: 'Avg Score %',
                data: trendData.map(d => d.avg_score),
                borderColor: '#4F8EF7',
                backgroundColor: 'rgba(79,142,247,0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#4F8EF7',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            },{
                label: 'Participants',
                data: trendData.map(d => d.participants),
                borderColor: '#8B5CF6',
                backgroundColor: 'rgba(139,92,246,0.05)',
                fill: false,
                tension: 0.4,
                borderDash: [5,5],
                pointBackgroundColor: '#8B5CF6',
                pointRadius: 4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { labels: { color: '#9aa0a8', font: { size: 11 } } },
                tooltip: { backgroundColor: '#1a1d27', titleColor: '#e8eaed', bodyColor: '#9aa0a8', borderColor: 'rgba(255,255,255,0.1)', borderWidth: 1 }
            },
            scales: {
                x: { ticks: { color: '#5f6572', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.04)' } },
                y: { min: 0, max: 100, ticks: { color: '#5f6572', callback: v => v+'%' }, grid: { color: 'rgba(255,255,255,0.04)' } },
                y1: { position: 'right', ticks: { color: '#5f6572' }, grid: { display: false } }
            }
        }
    });
}

// Class Doughnut Chart
const pieCtx = document.getElementById('classPieChart');
if (pieCtx) {
    const classData = <?= json_encode($classChartData) ?>;
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: classData.map(d => d.name),
            datasets: [{
                data: classData.map(d => d.students),
                backgroundColor: chartColors.slice(0, classData.length),
                borderColor: '#0f1117',
                borderWidth: 3,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { color: '#9aa0a8', padding: 12, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' } },
                tooltip: { backgroundColor: '#1a1d27', titleColor: '#e8eaed', bodyColor: '#9aa0a8', borderColor: 'rgba(255,255,255,0.1)', borderWidth: 1 }
            }
        }
    });
}
</script>
