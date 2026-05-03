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

// All quiz attempts (chronological)
$attempts = $pdo->prepare("
    SELECT qa.*, q.title, q.quiz_code, q.id as quiz_id, q.created_at as quiz_date
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.student_id = ? AND q.class_id = ? AND qa.total_questions > 0
    ORDER BY qa.joined_at ASC
");
$attempts->execute([$studentId, $classId]);
$quizAttempts = $attempts->fetchAll();

// Topic-wise performance
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

// Total quizzes in this class (for attendance)
$totalClassQuizzes = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE class_id = ? AND status = 'completed'");
$totalClassQuizzes->execute([$classId]);
$totalClassQuizzes = $totalClassQuizzes->fetchColumn();

// Stats
$totalAttempts = count($quizAttempts);
$totalCorrect = array_sum(array_column($quizAttempts, 'total_correct'));
$totalQuestions = array_sum(array_column($quizAttempts, 'total_questions'));
$overallAccuracy = $totalQuestions > 0 ? round($totalCorrect / $totalQuestions * 100, 1) : 0;
$bestScore = $totalAttempts > 0 ? max(array_map(fn($a) => round($a['total_correct'] / $a['total_questions'] * 100), $quizAttempts)) : 0;
$attendancePct = $totalClassQuizzes > 0 ? round($totalAttempts / $totalClassQuizzes * 100) : 0;

// Score distribution
$dist = ['high' => 0, 'mid' => 0, 'low' => 0];
foreach ($quizAttempts as $a) {
    $p = round($a['total_correct'] / $a['total_questions'] * 100);
    if ($p >= 70) $dist['high']++;
    elseif ($p >= 40) $dist['mid']++;
    else $dist['low']++;
}

// Trend detection
$trend = 'stable';
if ($totalAttempts >= 4) {
    $half = intdiv($totalAttempts, 2);
    $avgFirst = array_sum(array_map(fn($a) => $a['total_correct'] / $a['total_questions'] * 100, array_slice($quizAttempts, 0, $half))) / $half;
    $avgSecond = array_sum(array_map(fn($a) => $a['total_correct'] / $a['total_questions'] * 100, array_slice($quizAttempts, $half))) / ($totalAttempts - $half);
    if ($avgSecond - $avgFirst > 5) $trend = 'improving';
    elseif ($avgFirst - $avgSecond > 5) $trend = 'declining';
}

// Class rank
$classRank = $pdo->prepare("
    SELECT student_id, SUM(total_correct)/SUM(total_questions)*100 as acc
    FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id
    WHERE q.class_id = ? AND qa.total_questions > 0
    GROUP BY student_id ORDER BY acc DESC
");
$classRank->execute([$classId]);
$ranks = $classRank->fetchAll();
$rank = 0;
foreach ($ranks as $i => $r) { if ($r['student_id'] == $studentId) { $rank = $i + 1; break; } }
$totalRanked = count($ranks);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <div class="breadcrumb"><a href="/teacher/dashboard.php">Dashboard</a> / <a href="/teacher/class_detail.php?id=<?= $classId ?>"><?= sanitize($class['class_name'] ?? '') ?></a> / <?= sanitize($student['name']) ?></div>
            <h1><?= sanitize($student['name']) ?></h1>
            <div style="display:flex;gap:8px;margin-top:6px;">
                <span class="badge badge-blue"><?= sanitize($student['enrollment_no']) ?></span>
                <?php if ($rank > 0): ?>
                <span class="badge badge-purple">Rank #<?= $rank ?>/<?= $totalRanked ?></span>
                <?php endif; ?>
                <span class="badge <?= $trend === 'improving' ? 'badge-green' : ($trend === 'declining' ? 'badge-red' : 'badge-yellow') ?>"><?= $trend === 'improving' ? '' : ($trend === 'declining' ? '' : '') ?> <?= ucfirst($trend) ?></span>
            </div>
        </div>
        <a href="/teacher/ajax/export_csv.php?quiz_id=0&type=results&student_id=<?= $studentId ?>" class="btn btn-outline">Export CSV</a>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:28px;">
        <div class="stat-card"><div class="stat-icon purple"><?= icon('file-text') ?></div><div class="stat-info"><h4>Quizzes</h4><div class="stat-value"><?= $totalAttempts ?></div></div></div>
        <div class="stat-card"><div class="stat-icon green"><?= icon('check-circle') ?></div><div class="stat-info"><h4>Accuracy</h4><div class="stat-value"><?= $overallAccuracy ?>%</div></div></div>
        <div class="stat-card"><div class="stat-icon blue"><?= icon('target') ?></div><div class="stat-info"><h4>Correct</h4><div class="stat-value"><?= $totalCorrect ?>/<?= $totalQuestions ?></div></div></div>
        <div class="stat-card"><div class="stat-icon orange"><?= icon('trophy') ?></div><div class="stat-info"><h4>Best</h4><div class="stat-value"><?= $bestScore ?>%</div></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(6,182,212,0.15);color:#06B6D4;"><?= icon('clipboard') ?></div><div class="stat-info"><h4>Attendance</h4><div class="stat-value"><?= $totalAttempts ?>/<?= $totalClassQuizzes ?> <span style="font-size:0.7rem;color:var(--text-muted);">(<?= $attendancePct ?>%)</span></div></div></div>
    </div>

    <!-- Row 1: Score Trend (combo) + Score Distribution -->
    <div style="display:grid;grid-template-columns:5fr 2fr;gap:24px;margin-bottom:24px;">
        <div class="card">
            <div class="card-header"><h3>Score Trend</h3><span style="font-size:0.75rem;color:var(--text-muted);">Bar = Quiz score · Line = Running average</span></div>
            <?php if ($totalAttempts > 0): ?>
            <div style="position:relative;height:280px;"><canvas id="comboChart"></canvas></div>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><p>No quiz data yet.</p></div>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><h3>Score Spread</h3></div>
            <?php if ($totalAttempts > 0): ?>
            <div style="position:relative;height:200px;"><canvas id="distChart"></canvas></div>
            <div style="margin-top:16px;font-size:0.8rem;">
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:var(--accent-green);">70-100%</span><strong><?= $dist['high'] ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:#ffc107;">40-69%</span><strong><?= $dist['mid'] ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:#ff3860;">0-39%</span><strong><?= $dist['low'] ?></strong></div>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><p>No data.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Row 2: Topic Horizontal Bar + Strengths/Weaknesses -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
        <div class="card">
            <div class="card-header"><h3>Topic Accuracy</h3></div>
            <?php if (!empty($topicData)): ?>
            <div style="position:relative;height:<?= max(200, count($topicData) * 36) ?>px;"><canvas id="topicBarChart"></canvas></div>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><p>No topic data yet.</p></div>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><h3>Strengths & Weaknesses</h3></div>
            <?php if (!empty($topicData)):
                $sorted = $topicData;
                usort($sorted, fn($a, $b) => $b['accuracy'] <=> $a['accuracy']);
                $strengths = array_filter($sorted, fn($s) => $s['accuracy'] >= 50);
                $strengths = array_slice($strengths, 0, 3);
                $weaknesses = array_filter($sorted, fn($w) => $w['accuracy'] < 70);
                usort($weaknesses, fn($a, $b) => $a['accuracy'] <=> $b['accuracy']);
                $weaknesses = array_slice($weaknesses, 0, 3);
            ?>
            <div style="margin-bottom:16px;">
                <div style="font-size:0.78rem;font-weight:600;color:var(--accent-green);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Strongest</div>
                <?php foreach ($strengths as $s): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;margin-bottom:6px;background:rgba(16,185,129,0.05);border:1px solid rgba(16,185,129,0.12);border-radius:10px;">
                    <div><div style="font-weight:600;font-size:0.88rem;"><?= sanitize($s['topic_name']) ?></div><div style="font-size:0.72rem;color:var(--text-muted);"><?= $s['correct'] ?>/<?= $s['total'] ?> correct</div></div>
                    <span style="font-weight:700;font-size:1.05rem;color:var(--accent-green);"><?= $s['accuracy'] ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
            <div>
                <div style="font-size:0.78rem;font-weight:600;color:#ff3860;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Needs Work</div>
                <?php if (empty($weaknesses)): ?>
                    <div style="padding:16px;text-align:center;color:var(--accent-green);font-size:0.85rem;">All topics above 80%!</div>
                <?php else: foreach ($weaknesses as $w): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;margin-bottom:6px;background:rgba(255,56,96,0.04);border:1px solid rgba(255,56,96,0.12);border-radius:10px;">
                    <div><div style="font-weight:600;font-size:0.88rem;"><?= sanitize($w['topic_name']) ?></div><div style="font-size:0.72rem;color:var(--text-muted);"><?= $w['correct'] ?>/<?= $w['total'] ?> correct</div></div>
                    <span style="font-weight:700;font-size:1.05rem;color:#ff3860;"><?= $w['accuracy'] ?>%</span>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><p>No data yet.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quiz History Table -->
    <div class="card">
        <div class="card-header"><h3>Quiz History</h3></div>
        <?php if (empty($quizAttempts)): ?>
        <div class="empty-state" style="padding:30px;"><p>No quizzes taken.</p></div>
        <?php else: ?>
        <div class="table-wrapper">
        <table>
            <thead><tr><th>#</th><th>Quiz</th><th>Code</th><th>Score</th><th>Accuracy</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($quizAttempts) as $i => $a):
                $pct = percentage($a['total_correct'], $a['total_questions']);
                $color = $pct >= 70 ? 'var(--accent-green)' : ($pct >= 40 ? '#ffc107' : '#ff3860');
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= sanitize($a['title']) ?></strong></td>
                <td><span class="badge badge-purple"><?= $a['quiz_code'] ?></span></td>
                <td><strong><?= $a['total_correct'] ?>/<?= $a['total_questions'] ?></strong></td>
                <td><span style="font-weight:700;color:<?= $color ?>;"><?= $pct ?>%</span></td>
                <td style="font-size:0.82rem;color:var(--text-muted);"><?= formatDate($a['joined_at']) ?></td>
                <td><a href="/teacher/quiz_detail.php?id=<?= $a['quiz_id'] ?>" class="btn btn-outline btn-sm">Detail</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="/assets/js/chart.min.js"></script>
<script>
const TS = { backgroundColor:'#1a1d27', titleColor:'#e8eaed', bodyColor:'#9aa0a8', borderColor:'rgba(255,255,255,0.1)', borderWidth:1 };

// ===== COMBO: Bar + Running Avg Line =====
const comboCtx = document.getElementById('comboChart');
if (comboCtx) {
    const raw = <?= json_encode(array_map(fn($a) => [
        'title' => $a['title'], 'pct' => round($a['total_correct'] / $a['total_questions'] * 100),
        'c' => (int)$a['total_correct'], 't' => (int)$a['total_questions'],
        'date' => date('M d', strtotime($a['joined_at']))
    ], $quizAttempts)) ?>;
    let sum = 0;
    const runAvg = raw.map((d,i) => { sum += d.pct; return Math.round(sum / (i+1)); });

    new Chart(comboCtx, {
        data: {
            labels: raw.map(d => d.date),
            datasets: [{
                type: 'bar', label: 'Score %', data: raw.map(d => d.pct),
                backgroundColor: raw.map(d => d.pct >= 70 ? 'rgba(16,185,129,0.6)' : d.pct >= 40 ? 'rgba(245,158,11,0.6)' : 'rgba(239,68,68,0.6)'),
                borderColor: raw.map(d => d.pct >= 70 ? '#10B981' : d.pct >= 40 ? '#F59E0B' : '#EF4444'),
                borderWidth: 1.5, borderRadius: 4, borderSkipped: false, order: 2
            },{
                type: 'line', label: 'Running Avg', data: runAvg,
                borderColor: '#8B5CF6', borderWidth: 2.5, tension: 0.3, fill: false,
                pointBackgroundColor: '#8B5CF6', pointBorderColor: '#1a1d27', pointBorderWidth: 2, pointRadius: 4, order: 1
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: { legend: { labels: { color:'#9aa0a8', font:{size:10}, usePointStyle:true, pointStyle:'circle' } }, tooltip: { ...TS, callbacks: { title: ctx => raw[ctx[0].dataIndex].title, label: ctx => ctx.dataset.type==='line' ? 'Avg: '+ctx.parsed.y+'%' : ctx.parsed.y+'% ('+raw[ctx.dataIndex].c+'/'+raw[ctx.dataIndex].t+')' } } },
            scales: { x: { ticks:{color:'#5f6572',font:{size:10}}, grid:{color:'rgba(255,255,255,0.03)'} }, y: { min:0, max:100, ticks:{color:'#5f6572',callback:v=>v+'%'}, grid:{color:'rgba(255,255,255,0.04)'} } }
        }
    });
}

// ===== DISTRIBUTION DOUGHNUT =====
const distCtx = document.getElementById('distChart');
if (distCtx) {
    new Chart(distCtx, {
        type: 'doughnut',
        data: { labels: ['70-100%','40-69%','0-39%'], datasets: [{ data: [<?= $dist['high'] ?>,<?= $dist['mid'] ?>,<?= $dist['low'] ?>], backgroundColor: ['rgba(16,185,129,0.7)','rgba(255,193,7,0.7)','rgba(255,56,96,0.7)'], borderColor:'#0f1117', borderWidth:3, hoverOffset:6 }] },
        options: { responsive:true, maintainAspectRatio:false, cutout:'55%', plugins: { legend:{display:false}, tooltip:TS } }
    });
}

// ===== TOPIC HORIZONTAL BAR =====
const topicCtx = document.getElementById('topicBarChart');
if (topicCtx) {
    const topics = <?= json_encode(array_map(fn($t) => ['name'=>$t['topic_name'],'pct'=>(float)$t['accuracy'],'c'=>(int)$t['correct'],'t'=>(int)$t['total']], $topicData)) ?>;
    new Chart(topicCtx, {
        type: 'bar',
        data: {
            labels: topics.map(t => t.name.length > 22 ? t.name.substring(0,22)+'..' : t.name),
            datasets: [{ data: topics.map(t => t.pct), backgroundColor: topics.map(t => t.pct >= 70 ? 'rgba(16,185,129,0.65)' : t.pct >= 40 ? 'rgba(245,158,11,0.65)' : 'rgba(239,68,68,0.65)'), borderColor: topics.map(t => t.pct >= 70 ? '#10B981' : t.pct >= 40 ? '#F59E0B' : '#EF4444'), borderWidth:1.5, borderRadius:5, borderSkipped:false }]
        },
        options: {
            indexAxis:'y', responsive:true, maintainAspectRatio:false,
            plugins: { legend:{display:false}, tooltip:{ ...TS, callbacks:{ label: ctx => ctx.parsed.x+'% ('+topics[ctx.dataIndex].c+'/'+topics[ctx.dataIndex].t+')' } } },
            scales: { x:{min:0,max:100,ticks:{color:'#5f6572',callback:v=>v+'%'},grid:{color:'rgba(255,255,255,0.04)'}}, y:{ticks:{color:'#9aa0a8',font:{size:10}},grid:{display:false}} }
        }
    });
}
</script>
