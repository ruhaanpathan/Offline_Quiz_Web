<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
if (isLoggedIn()) {
    redirect(isTeacher() ? '/teacher/dashboard.php' : '/student/dashboard.php');
}
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration — LANparty</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-card animate-fade">
            <div class="auth-logo">
                <img src="/assets/retrolan.jpg" alt="LANparty" style="width:48px;height:48px;border-radius:10px;object-fit:cover;">
                <h1>LANparty</h1>
                <p class="auth-subtitle">Student Registration</p>
            </div>

            <?php if ($flash): ?>
                <div class="flash flash-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
            <?php endif; ?>

            <div style="background:rgba(102,126,234,0.1);border:1px solid rgba(102,126,234,0.2);border-radius:10px;padding:14px;margin-bottom:20px;font-size:0.85rem;color:var(--text-secondary);">
                Your teacher must add your enrollment number to a class first. If you get an error, ask your teacher to add you.
            </div>

            <form method="POST" action="/auth/process_auth.php">
                <input type="hidden" name="action" value="student_register">
                <div class="form-group">
                    <label for="reg_enrollment">Enrollment Number</label>
                    <input type="text" id="reg_enrollment" name="enrollment_no" class="form-control"
                        placeholder="e.g. 2024CS001" required>
                </div>
                <div class="form-group">
                    <label for="reg_password">Set Password</label>
                    <input type="password" id="reg_password" name="password" class="form-control"
                        placeholder="Choose a password (min 4 chars)" required minlength="4">
                </div>
                <div class="form-group">
                    <label for="reg_confirm">Confirm Password</label>
                    <input type="password" id="reg_confirm" name="confirm_password" class="form-control"
                        placeholder="Re-enter your password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Register & Sign In</button>
                <div class="auth-footer">
                    Already registered? <a href="/auth/login.php?role=student">Login here</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>
