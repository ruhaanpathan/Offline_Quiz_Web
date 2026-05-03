<?php
$pageTitle = 'My Performance';
require_once __DIR__ . '/../includes/auth_check.php';
requireStudent();
require_once __DIR__ . '/../config/db.php';

$sid = getCurrentUserId();

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
    ORDER BY accuracy ASC
");
$topicPerf->execute([$sid]);
$topicData = $topicPerf->fetchAll();

// All attempts chronological
$allAttempts = $pdo->prepare("
    SELECT qa.*, q.title, q.quiz_code, q.id as quiz_id, c.class_name, c.subject
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN classes c ON q.class_id = c.id
    WHERE qa.student_id = ? AND qa.total_questions > 0
    ORDER BY qa.joined_at ASC
");
$allAttempts->execute([$sid]);
$attempts = $allAttempts->fetchAll();

// Subject-wise
$subjectPerf = $pdo->prepare("
    SELECT c.subject,
           SUM(qa.total_correct) as correct,
           SUM(qa.total_questions) as total,
           COUNT(qa.id) as quizzes
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN classes c ON q.class_id = c.id
    WHERE qa.student_id = ? AND qa.total_questions > 0
    GROUP BY c.subject ORDER BY c.subject
");
$subjectPerf->execute([$sid]);
$subjectData = $subjectPerf->fetchAll();

$totalQuizzes = count($attempts);
$totalCorrect = array_sum(array_column($attempts, 'total_correct'));
$totalQuestions = array_sum(array_column($attempts, 'total_questions'));
$overallAccuracy = percentage($totalCorrect, $totalQuestions);
$bestScore = $totalQuizzes > 0 ? max(array_map(fn($a) => round($a['total_correct'] / $a['total_questions'] * 100), $attempts)) : 0;

// Score distribution buckets
$dist = ['high' => 0, 'mid' => 0, 'low' => 0];
foreach ($attempts as $a) {
    $p = round($a['total_correct'] / $a['total_questions'] * 100);
    if ($p >= 70) $dist['high']++;
    elseif ($p >= 40) $dist['mid']++;
    else $dist['low']++;
}

// Improvement: compare first half avg vs second half avg
$trend = 'stable';
if ($totalQuizzes >= 4) {
    $half = intdiv($totalQuizzes, 2);
    $firstHalf = array_slice($attempts, 0, $half);
    $secondHalf = array_slice($attempts, $half);
    $avgFirst = array_sum(array_map(fn($a) => $a['total_correct'] / $a['total_questions'] * 100, $firstHalf)) / count($firstHalf);
    $avgSecond = array_sum(array_map(fn($a) => $a['total_correct'] / $a['total_questions'] * 100, $secondHalf)) / count($secondHalf);
    if ($avgSecond - $avgFirst > 5) $trend = 'improving';
    elseif ($avgFirst - $avgSecond > 5) $trend = 'declining';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <a href="/student/dashboard.php" style="color:var(--text-muted);font-size:0.85rem;">← Back to Dashboard</a>
            <h1>My Performance</h1>
        </div>
        <a href="/student/ajax/export_csv.php" class="btn btn-outline"><?= icon('download', 16) ?> Download CSV</a>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;">
        <div class="stat-card"><div class="stat-icon purple"><?= icon('file-text') ?></div><div class="stat-info"><h4>Quizzes</h4><div class="stat-value"><?= $totalQuizzes ?></div></div></div>
        <div class="stat-card"><div class="stat-icon green"><?= icon('check-circle') ?></div><div class="stat-info"><h4>Accuracy</h4><div class="stat-value"><?= $overallAccuracy ?>%</div></div></div>
        <div class="stat-card"><div class="stat-icon blue"><?= icon('target') ?></div><div class="stat-info"><h4>Correct</h4><div class="stat-value"><?= $totalCorrect ?>/<?= $totalQuestions ?></div></div></div>
        <div class="stat-card"><div class="stat-icon orange"><?= icon('trophy') ?></div><div class="stat-info"><h4>Best Score</h4><div class="stat-value"><?= $bestScore ?>%</div></div></div>
        <div class="stat-card" style="border-color:<?= $trend === 'improving' ? 'rgba(16,185,129,0.4)' : ($trend === 'declining' ? 'rgba(239,68,68,0.3)' : 'var(--border-glass)') ?>;">
            <div class="stat-icon" style="background:<?= $trend === 'improving' ? 'rgba(16,185,129,0.15)' : ($trend === 'declining' ? 'rgba(239,68,68,0.15)' : 'rgba(139,92,246,0.15)') ?>;color:<?= $trend === 'improving' ? 'var(--accent-green)' : ($trend === 'declining' ? 'var(--accent-red)' : 'var(--accent-purple)') ?>;">
                <?= icon($trend === 'improving' ? 'trending-up' : ($trend === 'declining' ? 'trending-down' : 'minus')) ?>
            </div>
            <div class="stat-info"><h4>Trend</h4><div class="stat-value" style="font-size:1.2rem;text-transform:capitalize;"><?= $trend ?></div></div>
        </div>
    </div>

    <!-- Row 1: Score Trend (bar+line combo) + Score Distribution -->
    <div style="display:grid;grid-template-columns:5fr 2fr;gap:24px;margin-bottom:24px;">
        <div class="card">
            <div class="card-header"><h3>Score Trend</h3><span style="font-size:0.75rem;color:var(--text-muted);">Bar = Score per quiz · Line = Running average</span></div>
            <?php if ($totalQuizzes > 0): ?>
            <div style="position:relative;height:280px;"><canvas id="comboChart"></canvas></div>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><p>Take quizzes to see your trend!</p></div>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><h3>Score Spread</h3></div>
            <?php if ($totalQuizzes > 0): ?>
            <div style="position:relative;height:200px;"><canvas id="distChart"></canvas></div>
            <div style="margin-top:16px;font-size:0.8rem;">
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:var(--accent-green);">70-100%</span><strong><?= $dist['high'] ?> quiz<?= $dist['high']!=1?'zes':'' ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:#ffc107;">40-69%</span><strong><?= $dist['mid'] ?> quiz<?= $dist['mid']!=1?'zes':'' ?></strong></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:#ff3860;">0-39%</span><strong><?= $dist['low'] ?> quiz<?= $dist['low']!=1?'zes':'' ?></strong></div>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><p>No data.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Row 2: Subject Grouped Bar + Topic Horizontal Bar -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
        <div class="card">
            <div class="card-header"><h3>Subject Comparison</h3></div>
            <?php if (!empty($subjectData)): ?>
            <div style="position:relative;height:280px;"><canvas id="subjectBarChart"></canvas></div>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><p>No data yet.</p></div>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><h3>Topic Accuracy</h3></div>
            <?php if (!empty($topicData)): ?>
            <div style="position:relative;height:280px;"><canvas id="topicBarChart"></canvas></div>
            <?php else: ?>
            <div class="empty-state" style="padding:30px;"><p>No data yet.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Row 3: Strengths & Weaknesses -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
        <?php if (!empty($topicData)):
            $sorted = $topicData;
            usort($sorted, fn($a, $b) => $b['accuracy'] <=> $a['accuracy']);
            $strengths = array_filter($sorted, fn($s) => $s['accuracy'] >= 50);
            $strengths = array_slice($strengths, 0, 3);
            $weaknesses = array_filter($sorted, fn($w) => $w['accuracy'] < 70);
            usort($weaknesses, fn($a, $b) => $a['accuracy'] <=> $b['accuracy']);
            $weaknesses = array_slice($weaknesses, 0, 3);
        ?>
        <div class="card">
            <div class="card-header"><h3>Strongest Topics</h3></div>
            <?php foreach ($strengths as $s): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;margin-bottom:8px;background:rgba(16,185,129,0.05);border:1px solid rgba(16,185,129,0.12);border-radius:10px;">
                <div>
                    <div style="font-weight:600;font-size:0.9rem;"><?= sanitize($s['topic_name']) ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= sanitize($s['subject']) ?> · <?= $s['correct'] ?>/<?= $s['total'] ?> correct</div>
                </div>
                <span style="font-weight:700;font-size:1.1rem;color:var(--accent-green);"><?= $s['accuracy'] ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <div class="card-header"><h3>Needs Improvement</h3></div>
            <?php if (empty($weaknesses)): ?>
                <div style="padding:30px;text-align:center;color:var(--accent-green);font-weight:600;">All topics above 80%! Great job!</div>
            <?php else: foreach ($weaknesses as $w): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;margin-bottom:8px;background:rgba(255,56,96,0.04);border:1px solid rgba(255,56,96,0.12);border-radius:10px;">
                <div>
                    <div style="font-weight:600;font-size:0.9rem;"><?= sanitize($w['topic_name']) ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= sanitize($w['subject']) ?> · <?= $w['correct'] ?>/<?= $w['total'] ?> correct</div>
                </div>
                <span style="font-weight:700;font-size:1.1rem;color:#ff3860;"><?= $w['accuracy'] ?>%</span>
            </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quiz History Table -->
    <div class="card">
        <div class="card-header"><h3>Complete Quiz History</h3></div>
        <?php if (empty($attempts)): ?>
        <div class="empty-state" style="padding:30px;"><p>No quizzes taken yet.</p></div>
        <?php else: ?>
        <div class="table-wrapper">
        <table>
            <thead><tr><th>#</th><th>Quiz</th><th>Class</th><th>Score</th><th>Accuracy</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($attempts) as $i => $a):
                $pct = percentage($a['total_correct'], $a['total_questions']);
                $color = $pct >= 70 ? 'var(--accent-green)' : ($pct >= 40 ? '#ffc107' : '#ff3860');
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><a href="/student/quiz_review.php?id=<?= $a['quiz_id'] ?>" style="color:var(--accent-blue);font-weight:600;"><?= sanitize($a['title']) ?></a></td>
                <td style="color:var(--text-secondary);font-size:0.85rem;"><?= sanitize($a['class_name']) ?></td>
                <td><strong><?= $a['total_correct'] ?>/<?= $a['total_questions'] ?></strong></td>
                <td><span style="font-weight:700;color:<?= $color ?>;"><?= $pct ?>%</span></td>
                <td style="font-size:0.82rem;color:var(--text-muted);"><?= formatDate($a['joined_at']) ?></td>
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
const CC = ['#4F8EF7','#10B981','#8B5CF6','#F59E0B','#EF4444','#EC4899','#06B6D4','#84CC16'];
const TS = { backgroundColor:'#1a1d27', titleColor:'#e8eaed', bodyColor:'#9aa0a8', borderColor:'rgba(255,255,255,0.1)', borderWidth:1 };

// ===== COMBO: Bar (individual) + Line (running avg) =====
const comboCtx = document.getElementById('comboChart');
if (comboCtx) {
    const raw = <?= json_encode(array_map(fn($a) => [
        'title' => $a['title'], 'pct' => round($a['total_correct'] / $a['total_questions'] * 100),
        'c' => (int)$a['total_correct'], 't' => (int)$a['total_questions'],
        'date' => date('M d', strtotime($a['joined_at']))
    ], $attempts)) ?>;
    // Running average
    let sum = 0;
    const runAvg = raw.map((d,i) => { sum += d.pct; return Math.round(sum / (i+1)); });

    new Chart(comboCtx, {
        data: {
            labels: raw.map(d => d.date),
            datasets: [{
                type: 'bar',
                label: 'Score %',
                data: raw.map(d => d.pct),
                backgroundColor: raw.map(d => d.pct >= 70 ? 'rgba(16,185,129,0.6)' : d.pct >= 40 ? 'rgba(245,158,11,0.6)' : 'rgba(239,68,68,0.6)'),
                borderColor: raw.map(d => d.pct >= 70 ? '#10B981' : d.pct >= 40 ? '#F59E0B' : '#EF4444'),
                borderWidth: 1.5, borderRadius: 4, borderSkipped: false,
                order: 2
            },{
                type: 'line',
                label: 'Running Avg',
                data: runAvg,
                borderColor: '#8B5CF6',
                backgroundColor: 'rgba(139,92,246,0.05)',
                borderWidth: 2.5, tension: 0.3, fill: false,
                pointBackgroundColor: '#8B5CF6', pointBorderColor: '#1a1d27',
                pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6,
                order: 1
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { labels: { color:'#9aa0a8', font:{size:10}, usePointStyle:true, pointStyle:'circle' } },
                tooltip: { ...TS, callbacks: { title: ctx => raw[ctx[0].dataIndex].title, label: ctx => ctx.dataset.type === 'line' ? 'Avg: '+ctx.parsed.y+'%' : ctx.parsed.y+'% ('+raw[ctx.dataIndex].c+'/'+raw[ctx.dataIndex].t+')' } }
            },
            scales: {
                x: { ticks:{color:'#5f6572',font:{size:10}}, grid:{color:'rgba(255,255,255,0.03)'} },
                y: { min:0, max:100, ticks:{color:'#5f6572',callback:v=>v+'%'}, grid:{color:'rgba(255,255,255,0.04)'} }
            }
        }
    });
}

// ===== SCORE DISTRIBUTION DOUGHNUT =====
const distCtx = document.getElementById('distChart');
if (distCtx) {
    new Chart(distCtx, {
        type: 'doughnut',
        data: {
            labels: ['70-100%','40-69%','0-39%'],
            datasets: [{ data: [<?= $dist['high'] ?>,<?= $dist['mid'] ?>,<?= $dist['low'] ?>], backgroundColor: ['rgba(16,185,129,0.7)','rgba(255,193,7,0.7)','rgba(255,56,96,0.7)'], borderColor:'#0f1117', borderWidth:3, hoverOffset:6 }]
        },
        options: { responsive:true, maintainAspectRatio:false, cutout:'55%', plugins: { legend:{display:false}, tooltip:TS } }
    });
}

// ===== SUBJECT GROUPED BAR (correct vs total) =====
const subCtx = document.getElementById('subjectBarChart');
if (subCtx) {
    const subs = <?= json_encode(array_map(fn($s) => ['subject'=>$s['subject'],'correct'=>(int)$s['correct'],'total'=>(int)$s['total'],'quizzes'=>(int)$s['quizzes']], $subjectData)) ?>;
    new Chart(subCtx, {
        type: 'bar',
        data: {
            labels: subs.map(s => s.subject),
            datasets: [
                { label:'Correct', data:subs.map(s=>s.correct), backgroundColor:'rgba(16,185,129,0.7)', borderColor:'#10B981', borderWidth:1, borderRadius:4 },
                { label:'Wrong', data:subs.map(s=>s.total - s.correct), backgroundColor:'rgba(239,68,68,0.5)', borderColor:'#EF4444', borderWidth:1, borderRadius:4 }
            ]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins: { legend:{ labels:{color:'#9aa0a8',font:{size:10},usePointStyle:true,pointStyle:'rect'} }, tooltip:{ ...TS, callbacks:{ afterLabel: ctx => { const s = subs[ctx.dataIndex]; return 'Accuracy: '+Math.round(s.correct/s.total*100)+'% ('+s.quizzes+' quizzes)'; } } } },
            scales: {
                x: { stacked:true, ticks:{color:'#9aa0a8'}, grid:{display:false} },
                y: { stacked:true, ticks:{color:'#5f6572'}, grid:{color:'rgba(255,255,255,0.04)'} }
            }
        }
    });
}

// ===== TOPIC HORIZONTAL BAR =====
const topicCtx = document.getElementById('topicBarChart');
if (topicCtx) {
    const topics = <?= json_encode(array_map(fn($t) => ['name'=>$t['topic_name'].' ('.$t['subject'].')','pct'=>(float)$t['accuracy'],'c'=>(int)$t['correct'],'t'=>(int)$t['total']], $topicData)) ?>;
    // Sort by accuracy descending for display
    topics.sort((a,b) => a.pct - b.pct);
    new Chart(topicCtx, {
        type: 'bar',
        data: {
            labels: topics.map(t => t.name.length > 25 ? t.name.substring(0,25)+'..' : t.name),
            datasets: [{
                label: 'Accuracy %',
                data: topics.map(t => t.pct),
                backgroundColor: topics.map((t,i) => {
                    const c = t.pct >= 70 ? [16,185,129] : t.pct >= 40 ? [245,158,11] : [239,68,68];
                    return `rgba(${c[0]},${c[1]},${c[2]},0.65)`;
                }),
                borderColor: topics.map(t => t.pct >= 70 ? '#10B981' : t.pct >= 40 ? '#F59E0B' : '#EF4444'),
                borderWidth: 1.5, borderRadius: 5, borderSkipped: false
            }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend:{display:false}, tooltip:{ ...TS, callbacks:{ label: ctx => ctx.parsed.x+'% ('+topics[ctx.dataIndex].c+'/'+topics[ctx.dataIndex].t+')' } } },
            scales: {
                x: { min:0, max:100, ticks:{color:'#5f6572',callback:v=>v+'%'}, grid:{color:'rgba(255,255,255,0.04)'} },
                y: { ticks:{color:'#9aa0a8',font:{size:10}}, grid:{display:false} }
            }
        }
    });
}
</script>
