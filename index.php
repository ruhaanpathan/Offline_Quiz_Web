<?php
require_once __DIR__ . '/config/session.php';
if (isTeacher()) {
    header('Location: /teacher/dashboard.php');
    exit;
}
if (isStudent()) {
    header('Location: /student/dashboard.php');
    exit;
}
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Live Quizzes.<br><span>No Internet Needed.</span></h1>
        <p>Host real-time quizzes in your classroom over LAN. Students join via QR code, get instant feedback, and track
            topic-wise performance, all without internet.</p>
        <div class="hero-actions">
            <a href="/auth/login.php?role=teacher" class="btn btn-primary btn-lg">I'm a Teacher</a>
            <a href="/auth/login.php?role=student" class="btn btn-outline btn-lg">I'm a Student</a>
        </div>
    </div>
</section>



<?php require_once __DIR__ . '/includes/footer.php'; ?>