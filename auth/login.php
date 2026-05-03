<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
if (isLoggedIn()) {
    redirect(isTeacher() ? '/teacher/dashboard.php' : '/student/dashboard.php');
}
$role = $_GET['role'] ?? 'teacher';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login LANparty</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-card animate-fade">
            <div class="auth-logo">
                <div class="logo-icon">⚡</div>
                <h1>LANparty</h1>
                <p class="auth-subtitle">Sign in to continue</p>
            </div>

            <?php if ($flash): ?>
                <div class="flash flash-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
            <?php endif; ?>

            <div class="auth-tabs">
                <button class="auth-tab <?= $role === 'teacher' ? 'active' : '' ?>"
                    onclick="switchRole('teacher')">Teacher</button>
                <button class="auth-tab <?= $role === 'student' ? 'active' : '' ?>"
                    onclick="switchRole('student')">Student</button>
            </div>

            <!-- Teacher Login -->
            <form id="teacherForm" method="POST" action="/auth/process_auth.php"
                style="display:<?= $role === 'teacher' ? 'block' : 'none' ?>">
                <input type="hidden" name="action" value="teacher_login">
                <div class="form-group">
                    <label for="teacher_email">Email</label>
                    <input type="email" id="teacher_email" name="email" class="form-control"
                        placeholder="your@email.com" required>
                </div>
                <div class="form-group">
                    <label for="teacher_password">Password</label>
                    <input type="password" id="teacher_password" name="password" class="form-control"
                        placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In as Teacher</button>
                <div class="auth-footer">
                    Don't have an account? <a href="/auth/register.php">Register here</a>
                </div>
            </form>

            <!-- Student Login -->
            <form id="studentForm" method="POST" action="/auth/process_auth.php"
                style="display:<?= $role === 'student' ? 'block' : 'none' ?>">
                <input type="hidden" name="action" value="student_login">
                <div class="form-group">
                    <label for="student_enrollment">Enrollment Number</label>
                    <input type="text" id="student_enrollment" name="enrollment_no" class="form-control"
                        placeholder="e.g. 2024CS001" required>
                </div>
                <div class="form-group">
                    <label for="student_password">Password</label>
                    <input type="password" id="student_password" name="password" class="form-control"
                        placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In as Student</button>
                <div class="auth-footer" style="color:var(--text-muted);">
                    First time? <a href="/auth/student_register.php">Register here</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchRole(role) {
            document.getElementById('teacherForm').style.display = role === 'teacher' ? 'block' : 'none';
            document.getElementById('studentForm').style.display = role === 'student' ? 'block' : 'none';
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            history.replaceState(null, '', `?role=${role}`);
        }
    </script>
</body>

</html>