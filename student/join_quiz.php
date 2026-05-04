<?php
$pageTitle = 'Join Quiz';
require_once __DIR__ . '/../includes/auth_check.php';
requireStudent();
require_once __DIR__ . '/../config/db.php';

$sid = getCurrentUserId();
$preCode = $_GET['code'] ?? '';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-wrapper animate-fade">
    <div style="max-width:500px;margin:40px auto;text-align:center;">
        <div style="font-size:3rem;margin-bottom:16px;"></div>
        <h1 style="margin-bottom:8px;">Join a Quiz</h1>
        <p style="color:var(--text-secondary);margin-bottom:32px;">Enter the 4-digit code shown by your teacher</p>

        <div class="card">
            <form id="joinForm">
                <div class="form-group">
                    <input type="text" id="quizCode" class="form-control" value="<?= sanitize($preCode) ?>"
                           placeholder="Enter quiz code" maxlength="4"
                           style="text-align:center;font-size:1.8rem;letter-spacing:10px;font-weight:700;text-transform:uppercase;padding:18px;"
                           required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="joinBtn">Join Quiz</button>
            </form>
            <div id="joinError" style="display:none;margin-top:16px;color:var(--accent-red);"></div>
        </div>
    </div>
</div>

<script>
document.getElementById('joinForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const code = document.getElementById('quizCode').value.trim().toUpperCase();
    if (code.length !== 4) { document.getElementById('joinError').style.display='block'; document.getElementById('joinError').textContent='Code must be 4 characters.'; return; }
    
    document.getElementById('joinBtn').textContent = 'Joining...';
    document.getElementById('joinBtn').disabled = true;

    const data = new FormData();
    data.append('quiz_code', code);
    const res = await QuizLAN.ajax('/student/ajax/poll_state.php', data);
    
    if (res.success && res.quiz_id) {
        // Join the quiz
        const joinData = new FormData();
        joinData.append('quiz_id', res.quiz_id);
        const joinRes = await QuizLAN.ajax('/student/ajax/submit_answer.php?action=join', joinData);
        if (joinRes.success) {
            window.location.href = '/student/quiz_play.php?id=' + res.quiz_id;
        } else {
            document.getElementById('joinError').style.display='block';
            document.getElementById('joinError').textContent = joinRes.message;
        }
    } else {
        document.getElementById('joinError').style.display='block';
        document.getElementById('joinError').textContent = res.message || 'Quiz not found or not accepting students.';
    }
    document.getElementById('joinBtn').textContent = 'Join Quiz';
    document.getElementById('joinBtn').disabled = false;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
