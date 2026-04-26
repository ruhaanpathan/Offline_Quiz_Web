<?php
$pageTitle = 'Live Quiz';
require_once __DIR__ . '/../includes/auth_check.php';
requireStudent();
require_once __DIR__ . '/../config/db.php';

$sid = getCurrentUserId();
$quizId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT q.*, c.class_name, c.subject FROM quizzes q JOIN classes c ON q.class_id = c.id WHERE q.id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();
if (!$quiz) { setFlash('error', 'Quiz not found.'); redirect('/student/dashboard.php'); }

// Ensure student has an attempt
$attStmt = $pdo->prepare("SELECT id FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
$attStmt->execute([$quizId, $sid]);
$attempt = $attStmt->fetch();
if (!$attempt) {
    $pdo->prepare("INSERT INTO quiz_attempts (quiz_id, student_id) VALUES (?, ?)")->execute([$quizId, $sid]);
    $attemptId = $pdo->lastInsertId();
} else {
    $attemptId = $attempt['id'];
}

$totalQ = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
$totalQ->execute([$quizId]);
$totalQuestions = $totalQ->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page-wrapper animate-fade">
    <!-- Waiting / Lobby -->
    <div id="waitingPhase" style="text-align:center;padding:60px 0;">
        <div style="font-size:3rem;margin-bottom:16px;animation:pulse 2s infinite;">⏳</div>
        <h2>Waiting for teacher to start...</h2>
        <p style="color:var(--text-secondary);margin-top:8px;"><?= sanitize($quiz['title']) ?> • <?= sanitize($quiz['class_name']) ?></p>
        <div style="margin-top:24px;">
            <span class="badge badge-green" style="font-size:1rem;padding:8px 20px;">You're in! 🎉</span>
        </div>
    </div>

    <!-- Question Phase -->
    <div id="questionPhase" style="display:none;">
        <div style="max-width:700px;margin:0 auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <span class="badge badge-purple" id="qNum">Q1 of <?= $totalQuestions ?></span>
                <span id="timer" style="font-size:1.4rem;font-weight:700;color:var(--accent-yellow);">⏱ 30s</span>
            </div>
            <div class="card" style="margin-bottom:20px;">
                <h2 id="questionText" style="font-size:1.2rem;line-height:1.5;"></h2>
                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:8px;" id="topicLabel"></div>
            </div>
            <div id="optionsGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;"></div>
            <div id="feedback" style="display:none;text-align:center;margin-top:20px;padding:16px;border-radius:var(--radius-md);"></div>
        </div>
    </div>

    <!-- Results Phase -->
    <div id="resultsPhase" style="display:none;text-align:center;padding:40px 0;">
        <div style="font-size:3rem;margin-bottom:16px;">🎉</div>
        <h2>Quiz Completed!</h2>
        <div class="card" style="max-width:400px;margin:24px auto;">
            <div style="font-size:3rem;font-weight:800;color:var(--accent-green);" id="finalScore">0/0</div>
            <div style="color:var(--text-secondary);margin-top:8px;" id="finalPercent">0%</div>
        </div>
        <div id="leaderboard" style="max-width:500px;margin:24px auto;"></div>
        <a href="/student/dashboard.php" class="btn btn-primary btn-lg" style="margin-top:24px;">Back to Dashboard</a>
    </div>
</div>

<script>
const QUIZ_ID = <?= $quizId ?>;
const ATTEMPT_ID = <?= $attemptId ?>;
const TOTAL_Q = <?= $totalQuestions ?>;
let lastQIdx = -1;
let answered = false;
let countdownTimer = null;
let score = 0;
let correct = 0;

// Poll for quiz state every 2 seconds
function startPolling() {
    setInterval(async () => {
        const data = new FormData();
        data.append('quiz_id', QUIZ_ID);
        data.append('poll', '1');
        const res = await QuizLAN.ajax('/student/ajax/poll_state.php', data);
        if (!res.success) return;

        if (res.phase === 'question' && res.question_index !== lastQIdx) {
            lastQIdx = res.question_index;
            answered = false;
            showQuestion(res.question);
        }
        if (res.phase === 'completed') {
            showResults();
        }
    }, 2000);
}

function showQuestion(q) {
    document.getElementById('waitingPhase').style.display = 'none';
    document.getElementById('questionPhase').style.display = 'block';
    document.getElementById('qNum').textContent = `Q${lastQIdx + 1} of ${TOTAL_Q}`;
    document.getElementById('questionText').textContent = q.question_text;
    document.getElementById('topicLabel').textContent = '📎 ' + q.topic_name;
    document.getElementById('feedback').style.display = 'none';

    const colors = { a: '#4F8EF7', b: '#8B5CF6', c: '#10B981', d: '#F59E0B' };
    document.getElementById('optionsGrid').innerHTML = ['a','b','c','d'].map(opt =>
        `<button class="btn" id="opt_${opt}" onclick="submitAnswer('${opt}', '${q.correct_option}', ${q.id})"
            style="padding:18px;font-size:1rem;background:rgba(${opt==='a'?'79,142,247':opt==='b'?'139,92,246':opt==='c'?'16,185,129':'245,158,11'},0.15);
            border:2px solid transparent;color:var(--text-primary);text-align:left;border-radius:var(--radius-md);transition:var(--transition);">
            <strong>${opt.toUpperCase()}.</strong> ${q['option_' + opt]}
        </button>`
    ).join('');

    startCountdown(q.time_limit_seconds);
}

function startCountdown(seconds) {
    clearInterval(countdownTimer);
    let rem = seconds;
    const el = document.getElementById('timer');
    el.textContent = `⏱ ${rem}s`;
    countdownTimer = setInterval(() => {
        rem--;
        el.textContent = `⏱ ${rem}s`;
        el.style.color = rem <= 5 ? 'var(--accent-red)' : 'var(--accent-yellow)';
        if (rem <= 0) { clearInterval(countdownTimer); el.textContent = "⏱ Time's up!"; if (!answered) { autoSubmit(); } }
    }, 1000);
}

async function submitAnswer(selected, correctOpt, questionId) {
    if (answered) return;
    answered = true;
    clearInterval(countdownTimer);

    // Highlight selected
    document.querySelectorAll('#optionsGrid button').forEach(b => { b.disabled = true; b.style.opacity = '0.5'; });
    document.getElementById('opt_' + selected).style.opacity = '1';
    document.getElementById('opt_' + selected).style.borderColor = selected === correctOpt ? 'var(--accent-green)' : 'var(--accent-red)';
    document.getElementById('opt_' + correctOpt).style.opacity = '1';
    document.getElementById('opt_' + correctOpt).style.borderColor = 'var(--accent-green)';

    const isCorrect = selected === correctOpt;
    if (isCorrect) { correct++; score++; }
    const fb = document.getElementById('feedback');
    fb.style.display = 'block';
    fb.style.background = isCorrect ? 'var(--accent-green-glow)' : 'rgba(239,68,68,0.1)';
    fb.style.color = isCorrect ? 'var(--accent-green)' : 'var(--accent-red)';
    fb.textContent = isCorrect ? '✅ Correct!' : '❌ Incorrect — Answer: ' + correctOpt.toUpperCase();

    const data = new FormData();
    data.append('attempt_id', ATTEMPT_ID);
    data.append('question_id', questionId);
    data.append('selected_option', selected);
    await QuizLAN.ajax('/student/ajax/submit_answer.php', data);
}

function autoSubmit() {
    answered = true;
    document.querySelectorAll('#optionsGrid button').forEach(b => b.disabled = true);
    const fb = document.getElementById('feedback');
    fb.style.display = 'block';
    fb.style.background = 'rgba(239,68,68,0.1)';
    fb.style.color = 'var(--accent-red)';
    fb.textContent = "⏱ Time's up! No answer submitted.";
}

async function showResults() {
    document.getElementById('waitingPhase').style.display = 'none';
    document.getElementById('questionPhase').style.display = 'none';
    document.getElementById('resultsPhase').style.display = 'block';
    clearInterval(countdownTimer);

    const data = new FormData();
    data.append('quiz_id', QUIZ_ID);
    const res = await QuizLAN.ajax('/student/ajax/check_results.php', data);
    if (res.success) {
        document.getElementById('finalScore').textContent = res.correct + '/' + res.total;
        document.getElementById('finalPercent').textContent = (res.total > 0 ? Math.round(res.correct/res.total*100) : 0) + '% accuracy';
        if (res.leaderboard && res.leaderboard.length) {
            document.getElementById('leaderboard').innerHTML = '<div class="card"><h3 style="margin-bottom:16px;">🏆 Leaderboard</h3>' +
                res.leaderboard.map((s, i) =>
                    `<div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border-glass);${s.is_you?'color:var(--accent-blue);font-weight:700;':''}">
                        <span>${i+1}. ${s.name}</span><span>${s.score}/${s.total}</span>
                    </div>`
                ).join('') + '</div>';
        }
    }
}

// Start
if ('<?= $quiz['status'] ?>' === 'active') {
    document.getElementById('waitingPhase').style.display = 'none';
}
startPolling();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
