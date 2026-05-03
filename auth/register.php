<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
if (isLoggedIn()) { redirect(isTeacher() ? '/teacher/dashboard.php' : '/student/dashboard.php'); }
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — LANparty</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card animate-fade">
        <div class="auth-logo">
            <img src="/assets/retrolan.jpg" alt="LANparty" style="width:48px;height:48px;border-radius:10px;object-fit:cover;">
            <h1>Create Account</h1>
            <p class="auth-subtitle">Register as a Teacher</p>
        </div>
        <?php if ($flash): ?>
            <div class="flash flash-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
        <?php endif; ?>
        <form method="POST" action="/auth/process_auth.php">
            <input type="hidden" name="action" value="teacher_register">
            <div class="form-group">
                <label for="reg_name">Full Name</label>
                <input type="text" id="reg_name" name="name" class="form-control" placeholder="Dr. John Smith" required>
            </div>
            <div class="form-group">
                <label for="reg_email">Email</label>
                <input type="email" id="reg_email" name="email" class="form-control" placeholder="john@college.edu" required>
            </div>
            <div class="form-group">
                <label for="reg_password">Password</label>
                <input type="password" id="reg_password" name="password" class="form-control" placeholder="Min 6 characters" required minlength="6">
            </div>
            <div class="form-group">
                <label for="reg_confirm">Confirm Password</label>
                <input type="password" id="reg_confirm" name="confirm_password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
            <div class="auth-footer">Already have an account? <a href="/auth/login.php?role=teacher">Sign in</a></div>
        </form>
    </div>
</div>
</body>
</html>
