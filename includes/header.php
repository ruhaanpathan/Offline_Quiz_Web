<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/functions.php';
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="QuizLAN — Offline-first live quiz platform for colleges">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — QuizLAN' : 'QuizLAN' ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
    <nav class="navbar">
        <a href="/" class="navbar-brand">
            <div class="logo-icon">⚡</div>
            QuizLAN
        </a>
        <?php if (isLoggedIn()): ?>
            <ul class="navbar-nav">
                <?php if (isTeacher()): ?>
                    <li><a href="/teacher/dashboard.php"
                            class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                    </li>
                    <li><a href="/teacher/classes.php" class="<?= $currentPage === 'classes' ? 'active' : '' ?>">Classes</a>
                    </li>
                <?php else: ?>
                    <li><a href="/student/dashboard.php"
                            class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                    </li>
                    <li><a href="/student/join_quiz.php" class="<?= $currentPage === 'join_quiz' ? 'active' : '' ?>">Join
                            Quiz</a>
                    </li>
                    <li><a href="/student/performance.php"
                            class="<?= $currentPage === 'performance' ? 'active' : '' ?>">Performance</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-user">
                <span><?= sanitize(getCurrentUserName()) ?></span>
                <div class="user-avatar"><?= strtoupper(substr(getCurrentUserName(), 0, 1)) ?></div>
                <a href="/auth/logout.php" class="btn btn-outline btn-sm">Logout</a>
            </div>
        <?php else: ?>
            <div class="navbar-nav">
                <a href="/auth/login.php" class="btn btn-outline btn-sm">Login</a>
            </div>
        <?php endif; ?>
    </nav>

    <?php if ($flash): ?>
        <div class="container" style="padding-top:20px;">
            <div class="flash flash-<?= $flash['type'] ?>">
                <?= sanitize($flash['message']) ?>
            </div>
        </div>
    <?php endif; ?>