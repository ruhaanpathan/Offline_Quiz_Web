<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/icons.php';
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="LANparty — Offline-first live quiz platform for colleges">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — LANparty' : 'LANparty' ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
    <nav class="navbar">
        <a href="/" class="navbar-brand">
            <img src="/assets/retrolan.jpg" alt="LANparty" class="logo-icon" style="width:36px;height:36px;border-radius:8px;object-fit:cover;">
            LANparty
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
            <div class="nav-user" style="position:relative;">
                <div class="profile-toggle" onclick="document.getElementById('profileMenu').classList.toggle('active')" style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:6px 10px;border-radius:8px;transition:background 0.2s;">
                    <span><?= sanitize(getCurrentUserName()) ?></span>
                    <div class="user-avatar"><?= strtoupper(substr(getCurrentUserName(), 0, 1)) ?></div>
                </div>
                <div id="profileMenu" class="profile-dropdown">
                    <div style="padding:14px 16px;border-bottom:1px solid var(--border-glass);">
                        <div style="font-weight:600;font-size:0.95rem;"><?= sanitize(getCurrentUserName()) ?></div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;"><?= isTeacher() ? 'Teacher' : 'Student' ?></div>
                    </div>
                    <a href="<?= isTeacher() ? '/teacher/profile.php' : '/student/profile.php' ?>" class="profile-dropdown-item">My Profile</a>
                    <a href="<?= isTeacher() ? '/teacher/dashboard.php' : '/student/dashboard.php' ?>" class="profile-dropdown-item">Dashboard</a>
                    <?php if (isStudent()): ?>
                    <a href="/student/performance.php" class="profile-dropdown-item">Performance</a>
                    <?php endif; ?>
                    <div style="border-top:1px solid var(--border-glass);margin:4px 0;"></div>
                    <a href="/auth/logout.php" class="profile-dropdown-item" style="color:var(--accent-red);">Logout</a>
                </div>
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