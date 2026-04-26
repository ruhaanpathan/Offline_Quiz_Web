<?php
$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../includes/auth_check.php';
requireStudent();
require_once __DIR__ . '/../config/db.php';

$sid = getCurrentUserId();

// Get enrolled classes
$classesStmt = $pdo->prepare("SELECT c.*, t.name as teacher_name FROM class_students cs JOIN classes c ON cs.class_id = c.id JOIN teachers t ON c.teacher_id = t.id WHERE cs.student_id = ? ORDER BY c.class_name");
$classesStmt->execute([$sid]);
$classes = $classesStmt->fetchAll();

// Get quiz stats
$quizStats = $pdo->prepare("SELECT COUNT(*) as total, SUM(total_correct) as correct, SUM(total_questions) as questions FROM quiz_attempts WHERE student_id = ?");
$quizStats->execute([$sid]);
$stats = $quizStats->fetch();

// Recent attempts
$recentStmt = $pdo->prepare("SELECT qa.*, q.title, q.quiz_code, c.class_name, c.subject FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id JOIN classes c ON q.class_id = c.id WHERE qa.student_id = ? ORDER BY qa.joined_at DESC LIMIT 5");
$recentStmt->execute([$sid]);
$recent = $recentStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container page-wrapper animate-fade">
    <div class="page-header">
        <h1>Welcome, <?= sanitize(getCurrentUserName()) ?> 👋</h1>
        <a href="/student/join_quiz.php" class="btn btn-primary btn-lg">🎯 Join Quiz</a>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px;">
        <div class="stat-card">
            <div class="stat-icon blue">📚</div>
            <div class="stat-info"><h4>Classes</h4><div class="stat-value"><?= count($classes) ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">📝</div>
            <div class="stat-info"><h4>Quizzes Taken</h4><div class="stat-value"><?= $stats['total'] ?? 0 ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div class="stat-info"><h4>Accuracy</h4><div class="stat-value"><?= percentage($stats['correct'] ?? 0, $stats['questions'] ?? 0) ?>%</div></div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <!-- My Classes -->
        <div class="card">
            <div class="card-header"><h3>My Classes</h3></div>
            <?php if (empty($classes)): ?>
                <div class="empty-state" style="padding:30px;"><p>Not enrolled in any class yet.</p></div>
            <?php else: ?>
                <?php foreach ($classes as $c): ?>
                <a href="/student/class_view.php?id=<?= $c['id'] ?>" style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--border-glass);color:var(--text-primary);">
                    <div>
                        <strong><?= sanitize($c['class_name']) ?></strong>
                        <div style="font-size:0.8rem;color:var(--text-muted);"><?= sanitize($c['subject']) ?> • <?= sanitize($c['teacher_name']) ?></div>
                    </div>
                    <span class="badge badge-blue">View →</span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Quizzes -->
        <div class="card">
            <div class="card-header"><h3>Recent Quizzes</h3><a href="/student/performance.php" class="btn btn-outline btn-sm">View All</a></div>
            <?php if (empty($recent)): ?>
                <div class="empty-state" style="padding:30px;"><p>No quizzes taken yet. Join a quiz to get started!</p></div>
            <?php else: ?>
                <?php foreach ($recent as $r): ?>
                <a href="/student/quiz_review.php?id=<?= $r['quiz_id'] ?>" style="display:flex;justify-content:space-between;align-items:center;padding:12px 8px;border-bottom:1px solid var(--border-glass);color:var(--text-primary);text-decoration:none;border-radius:6px;transition:background 0.2s;">
                    <div>
                        <strong style="font-size:0.9rem;"><?= sanitize($r['title']) ?></strong>
                        <div style="font-size:0.8rem;color:var(--text-muted);"><?= sanitize($r['class_name']) ?> • <?= formatDate($r['joined_at']) ?></div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="text-align:right;">
                            <div style="font-weight:700;color:var(--accent-green);"><?= $r['total_correct'] ?>/<?= $r['total_questions'] ?></div>
                            <div style="font-size:0.75rem;color:var(--text-muted);"><?= percentage($r['total_correct'], $r['total_questions']) ?>%</div>
                        </div>
                        <span style="color:var(--text-muted);font-size:0.8rem;">→</span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
