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
    <title><?= $pageTitle ?? 'LANparty' ?> — LANparty</title>
    <link rel="icon" type="image/jpeg" href="/assets/retrolan.jpg">
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>

<?php if (isLoggedIn()): ?>
<!-- ===== SIDEBAR LAYOUT ===== -->
<div class="app-layout">
    <aside class="sidebar" id="mainSidebar" onmouseenter="document.body.classList.add('sidebar-open')" onmouseleave="document.body.classList.remove('sidebar-open')">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <a href="/" class="sidebar-brand">
                <img src="/assets/retrolan.jpg" alt="LANparty" style="width:34px;height:34px;border-radius:8px;object-fit:cover;flex-shrink:0;">
                <span>LANparty</span>
            </a>
            <button class="sidebar-toggle-close" onclick="document.body.classList.remove('sidebar-open')"><?= icon('x', 20) ?></button>
        </div>

        <nav class="sidebar-nav">
            <?php if (isTeacher()): ?>
                <a href="/teacher/dashboard.php" class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <?= icon('bar-chart', 18) ?> <span class="sidebar-link-text">Dashboard</span>
                </a>
                <a href="/teacher/classes.php" class="sidebar-link <?= $currentPage === 'classes' || $currentPage === 'class_detail' ? 'active' : '' ?>">
                    <?= icon('book', 18) ?> <span class="sidebar-link-text">Classes</span>
                </a>
            <?php else: ?>
                <a href="/student/dashboard.php" class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <?= icon('bar-chart', 18) ?> <span class="sidebar-link-text">Dashboard</span>
                </a>
                <a href="/student/join_quiz.php" class="sidebar-link <?= $currentPage === 'join_quiz' ? 'active' : '' ?>">
                    <?= icon('zap', 18) ?> <span class="sidebar-link-text">Join Quiz</span>
                </a>
                <a href="/student/performance.php" class="sidebar-link <?= $currentPage === 'performance' ? 'active' : '' ?>">
                    <?= icon('trending-up', 18) ?> <span class="sidebar-link-text">Performance</span>
                </a>
            <?php endif; ?>

            <div class="sidebar-divider"></div>

            <a href="<?= isTeacher() ? '/teacher/profile.php' : '/student/profile.php' ?>" class="sidebar-link <?= $currentPage === 'profile' ? 'active' : '' ?>">
                <?= icon('user', 18) ?> <span class="sidebar-link-text">Profile</span>
            </a>

        </nav>

        <div class="sidebar-user">
            <div class="profile-toggle" onclick="document.getElementById('profileMenu').classList.toggle('active')">
                <div style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <div class="user-avatar"><?= strtoupper(substr(getCurrentUserName(), 0, 1)) ?></div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?= sanitize(getCurrentUserName()) ?></div>
                        <div class="sidebar-user-role"><?= isTeacher() ? 'Teacher' : 'Student' ?></div>
                    </div>
                </div>
            </div>
            <div id="profileMenu" class="profile-dropdown">
                <a href="<?= isTeacher() ? '/teacher/profile.php' : '/student/profile.php' ?>" class="profile-dropdown-item">My Profile</a>
                <a href="<?= isTeacher() ? '/teacher/dashboard.php' : '/student/dashboard.php' ?>" class="profile-dropdown-item">Dashboard</a>
                <div style="border-top:1px solid var(--border);margin:4px 0;"></div>
                <a href="/auth/logout.php" class="profile-dropdown-item" style="color:var(--accent-red);">Logout</a>
            </div>
        </div>
    </aside>
    <div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>

    <div class="sidebar-hover-zone" onmouseenter="document.body.classList.add('sidebar-open')"></div>

    <main class="main-content">
        <div class="mobile-topbar">
            <button class="sidebar-toggle" onclick="document.body.classList.add('sidebar-open')" aria-label="Menu"><?= icon('menu', 22) ?></button>
            <span class="mobile-topbar-title"><?= $pageTitle ?? 'LANparty' ?></span>
        </div>
<?php else: ?>
<!-- ===== NO-SIDEBAR LAYOUT (logged out) ===== -->
<?php endif; ?>

<?php if ($flash): ?>
    <div class="flash flash-<?= $flash['type'] ?>" style="<?= isLoggedIn() ? 'margin:16px 32px 0;' : 'max-width:440px;margin:16px auto;' ?>">
        <?= sanitize($flash['message']) ?>
    </div>
<?php endif; ?>