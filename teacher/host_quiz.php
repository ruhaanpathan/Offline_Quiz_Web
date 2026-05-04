<?php
$pageTitle = 'Host Quiz';
require_once __DIR__ . '/../includes/auth_check.php';
requireTeacher();
require_once __DIR__ . '/../config/db.php';

$tid = getCurrentUserId();
$quizId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT q.*, c.class_name, c.subject FROM quizzes q JOIN classes c ON q.class_id = c.id WHERE q.id = ? AND q.teacher_id = ?");
$stmt->execute([$quizId, $tid]);
$quiz = $stmt->fetch();
if (!$quiz) { setFlash('error', 'Quiz not found.'); redirect('/teacher/dashboard.php'); }

// Get questions
$qStmt = $pdo->prepare("SELECT q.*, t.topic_name FROM questions q JOIN topics t ON q.topic_id = t.id WHERE q.quiz_id = ? ORDER BY q.question_order");
$qStmt->execute([$quizId]);
$questions = $qStmt->fetchAll();

// Get LAN IP for QR
$lanIp = getLanIp();
$joinUrl = "http://$lanIp/student/join_quiz.php?code=" . $quiz['quiz_code'];

require_once __DIR__ . '/../includes/header.php';
$extraScripts = '<script src="/assets/js/qrcode.min.js"></script>';
?>

<div class="page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <h1><?= sanitize($quiz['title']) ?></h1>
            <div class="breadcrumb"><?= sanitize($quiz['class_name']) ?> • <?= sanitize($quiz['subject']) ?> • <?= count($questions) ?> questions</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <span class="badge badge-blue" style="font-size:1rem;padding:8px 16px;" id="statusBadge"><?= strtoupper($quiz['status']) ?></span>
        </div>
    </div>

    <!-- Lobby Phase -->
    <div id="lobbyPhase" style="display:<?= in_array($quiz['status'],['draft','lobby'])?'block':'none' ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
            <div class="card" style="text-align:center;">
                <h3 style="margin-bottom:20px;">Join Code</h3>
                <div style="font-size:3rem;font-weight:800;letter-spacing:12px;color:var(--accent-blue);margin-bottom:16px;"><?= $quiz['quiz_code'] ?></div>
                <div id="qrContainer" style="display:inline-block;background:#fff;padding:16px;border-radius:var(--radius-md);margin-bottom:16px;"></div>
                <p style="font-size:0.85rem;color:var(--text-muted);"><?= $joinUrl ?></p>
                <div style="margin-top:20px;">
                    <?php if ($quiz['status'] === 'draft'): ?>
                        <button class="btn btn-primary btn-lg" onclick="openLobby()">Open Lobby</button>
                    <?php endif; ?>
                    <button class="btn btn-success btn-lg" id="beginBtn" onclick="beginQuiz()" style="display:<?= $quiz['status']==='lobby'?'inline-flex':'none' ?>">▶ Begin Quiz</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3>Lobby</h3><span id="studentCount" class="badge badge-green">0 joined</span></div>
                <div id="lobbyList" style="max-height:400px;overflow-y:auto;">
                    <div class="empty-state" style="padding:30px;"><p>Waiting for students to join...</p></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Quiz Phase -->
    <div id="activePhase" style="display:<?= $quiz['status']==='active'?'block':'none' ?>">
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
            <div class="card">
                <div id="currentQuestion">
                    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
                        <span class="badge badge-purple" id="qNumber">Q1</span>
                        <span class="badge badge-blue" id="qTopic">Topic</span>
                    </div>
                    <h2 id="qText" style="font-size:1.3rem;margin-bottom:24px;"></h2>
                    <div id="optionsDisplay" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:24px;padding-top:16px;border-top:1px solid var(--border);">
                    <span id="timerDisplay" style="font-size:1.5rem;font-weight:700;color:var(--accent-yellow);">⏱ 30s</span>
                    <button class="btn btn-primary" onclick="nextQuestion()" id="nextBtn">Next Question →</button>
                </div>
            </div>
            <div class="card">
                <h3 style="margin-bottom:16px;">Live Stats</h3>
                <div id="answerStats">
                    <div style="margin-bottom:12px;" class="optBar"><div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>A</span><span id="statA">0</span></div><div style="background:var(--bg-secondary);border-radius:4px;height:8px;overflow:hidden;"><div id="barA" style="height:100%;background:var(--accent-blue);width:0%;transition:width 0.5s;"></div></div></div>
                    <div style="margin-bottom:12px;" class="optBar"><div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>B</span><span id="statB">0</span></div><div style="background:var(--bg-secondary);border-radius:4px;height:8px;overflow:hidden;"><div id="barB" style="height:100%;background:var(--accent-purple);width:0%;transition:width 0.5s;"></div></div></div>
                    <div style="margin-bottom:12px;" class="optBar"><div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>C</span><span id="statC">0</span></div><div style="background:var(--bg-secondary);border-radius:4px;height:8px;overflow:hidden;"><div id="barC" style="height:100%;background:var(--accent-green);width:0%;transition:width 0.5s;"></div></div></div>
                    <div style="margin-bottom:12px;" class="optBar"><div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>D</span><span id="statD">0</span></div><div style="background:var(--bg-secondary);border-radius:4px;height:8px;overflow:hidden;"><div id="barD" style="height:100%;background:var(--accent-yellow);width:0%;transition:width 0.5s;"></div></div></div>
                </div>
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span style="color:var(--text-muted);">Answered</span><span id="answeredCount">0</span></div>
                    <div style="display:flex;justify-content:space-between;"><span style="color:var(--text-muted);">Total Students</span><span id="totalStudents">0</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Completed Phase -->
    <div id="completedPhase" style="display:<?= $quiz['status']==='completed'?'block':'none' ?>">
        <div class="card" style="text-align:center;padding:40px;">
            <div style="font-size:3rem;margin-bottom:16px;"></div>
            <h2>Quiz Completed!</h2>
            <p style="color:var(--text-secondary);margin:12px 0 24px;">View the detailed report below.</p>
            <a href="/teacher/quiz_report.php?id=<?= $quizId ?>" class="btn btn-primary btn-lg">View Report</a>
        </div>
    </div>
</div>

<script>
const QUIZ_ID = <?= $quizId ?>;
const QUESTIONS = <?= json_encode($questions) ?>;
let currentQIdx = 0;
let pollTimer = null;
let countdownTimer = null;

// QR Code
function renderQR() {
    const container = document.getElementById('qrContainer');
    if (!container || typeof qrcode === 'undefined') return;
    const qr = qrcode(0, 'M');
    qr.addData('<?= $joinUrl ?>');
    qr.make();
    container.innerHTML = qr.createImgTag(5, 0);
}
setTimeout(renderQR, 100);

// Open lobby
async function openLobby() {
    const res = await QuizLAN.ajax('/teacher/ajax/start_quiz.php', { quiz_id: QUIZ_ID, action: 'open_lobby' });
    if (res.success) {
        document.getElementById('beginBtn').style.display = 'inline-flex';
        document.getElementById('statusBadge').textContent = 'LOBBY';
        startLobbyPoll();
    }
}

// Poll lobby for joined students
function startLobbyPoll() {
    pollTimer = setInterval(async () => {
        const res = await QuizLAN.ajax('/teacher/ajax/lobby_poll.php', { quiz_id: QUIZ_ID });
        if (res.success) {
            document.getElementById('studentCount').textContent = res.students.length + ' joined';
            if (res.students.length > 0) {
                document.getElementById('lobbyList').innerHTML = res.students.map((s, i) =>
                    `<div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                        <div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;">${s.name.charAt(0).toUpperCase()}</div>
                        <div><strong>${s.name}</strong><div style="font-size:0.75rem;color:var(--text-muted);">${s.enrollment_no}</div></div>
                    </div>`
                ).join('');
            }
        }
    }, 2000);
}

// Begin quiz
async function beginQuiz() {
    if (!confirm('Start the quiz? All joined students will see Question 1.')) return;
    clearInterval(pollTimer);
    const res = await QuizLAN.ajax('/teacher/ajax/start_quiz.php', { quiz_id: QUIZ_ID, action: 'begin' });
    if (res.success) {
        document.getElementById('lobbyPhase').style.display = 'none';
        document.getElementById('activePhase').style.display = 'block';
        document.getElementById('statusBadge').textContent = 'ACTIVE';
        document.getElementById('statusBadge').className = 'badge badge-green';
        currentQIdx = 0;
        showQuestion(0);
        startStatsPoll();
    }
}

// Show question
function showQuestion(idx) {
    if (idx >= QUESTIONS.length) { endQuiz(); return; }
    const q = QUESTIONS[idx];
    document.getElementById('qNumber').textContent = `Q${idx + 1} of ${QUESTIONS.length}`;
    document.getElementById('qTopic').textContent = q.topic_name;
    document.getElementById('qText').textContent = q.question_text;
    document.getElementById('optionsDisplay').innerHTML = ['a','b','c','d'].map(opt => {
        const isCorrect = q.correct_option === opt;
        return `<div style="padding:14px 18px;background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-sm);${isCorrect?'border-color:var(--accent-green);':''}">
            <strong>${opt.toUpperCase()}.</strong> ${q['option_' + opt]}
            ${isCorrect ? ' ' : ''}
        </div>`;
    }).join('');
    if (idx >= QUESTIONS.length - 1) {
        document.getElementById('nextBtn').textContent = 'End Quiz ';
    }
    startCountdown(q.time_limit_seconds);
    // Reset stats
    ['A','B','C','D'].forEach(o => { document.getElementById('stat'+o).textContent='0'; document.getElementById('bar'+o).style.width='0%'; });
}

// Countdown
function startCountdown(seconds) {
    clearInterval(countdownTimer);
    let remaining = seconds;
    const display = document.getElementById('timerDisplay');
    display.textContent = `⏱ ${remaining}s`;
    countdownTimer = setInterval(() => {
        remaining--;
        display.textContent = `⏱ ${remaining}s`;
        if (remaining <= 5) display.style.color = 'var(--accent-red)';
        else display.style.color = 'var(--accent-yellow)';
        if (remaining <= 0) { clearInterval(countdownTimer); display.textContent = '⏱ Time\'s up!'; }
    }, 1000);
}

// Next question
async function nextQuestion() {
    clearInterval(countdownTimer);
    currentQIdx++;
    if (currentQIdx >= QUESTIONS.length) { endQuiz(); return; }
    await QuizLAN.ajax('/teacher/ajax/next_question.php', { quiz_id: QUIZ_ID, question_index: currentQIdx });
    showQuestion(currentQIdx);
}

// End quiz
async function endQuiz() {
    clearInterval(pollTimer);
    clearInterval(countdownTimer);
    await QuizLAN.ajax('/teacher/ajax/end_quiz.php', { quiz_id: QUIZ_ID });
    document.getElementById('activePhase').style.display = 'none';
    document.getElementById('completedPhase').style.display = 'block';
    document.getElementById('statusBadge').textContent = 'COMPLETED';
}

// Poll live stats
function startStatsPoll() {
    pollTimer = setInterval(async () => {
        const res = await QuizLAN.ajax('/teacher/ajax/live_stats.php', { quiz_id: QUIZ_ID, question_index: currentQIdx });
        if (res.success) {
            const total = res.total_answered || 1;
            ['a','b','c','d'].forEach(opt => {
                const count = res.counts[opt] || 0;
                document.getElementById('stat' + opt.toUpperCase()).textContent = count;
                document.getElementById('bar' + opt.toUpperCase()).style.width = (count/total*100) + '%';
            });
            document.getElementById('answeredCount').textContent = res.total_answered;
            document.getElementById('totalStudents').textContent = res.total_students;
        }
    }, 2000);
}

// Auto-start lobby poll if status is lobby
if ('<?= $quiz['status'] ?>' === 'lobby') { startLobbyPoll(); }
if ('<?= $quiz['status'] ?>' === 'active') {
    // Restore correct question index from DB on page reload
    (async () => {
        const res = await QuizLAN.ajax('/teacher/ajax/live_stats.php', { quiz_id: QUIZ_ID, question_index: 0, get_state: 1 });
        // Fetch live state to get current question index
        const stateRes = await fetch('/teacher/ajax/get_quiz_state.php?quiz_id=' + QUIZ_ID);
        const state = await stateRes.json();
        if (state.success && typeof state.question_index !== 'undefined') {
            currentQIdx = state.question_index;
        }
        showQuestion(currentQIdx);
        startStatsPoll();
    })();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
