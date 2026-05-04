<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/auth_check.php';
requireStudent();
require_once __DIR__ . '/../config/db.php';

$sid = getCurrentUserId();

$student = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$student->execute([$sid]);
$student = $student->fetch();

// Stats
$classCount = $pdo->prepare("SELECT COUNT(*) FROM class_students WHERE student_id = ?");
$classCount->execute([$sid]);
$totalClasses = $classCount->fetchColumn();

$quizStats = $pdo->prepare("SELECT COUNT(*) as total, SUM(total_correct) as correct, SUM(total_questions) as questions FROM quiz_attempts WHERE student_id = ?");
$quizStats->execute([$sid]);
$stats = $quizStats->fetch();
$accuracy = $stats['questions'] > 0 ? round($stats['correct'] / $stats['questions'] * 100, 1) : 0;

// Classes enrolled
$classes = $pdo->prepare("SELECT c.class_name, c.subject, t.name as teacher FROM class_students cs JOIN classes c ON cs.class_id = c.id JOIN teachers t ON c.teacher_id = t.id WHERE cs.student_id = ? ORDER BY c.class_name");
$classes->execute([$sid]);
$classList = $classes->fetchAll();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $student['password'])) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 4) {
            setFlash('error', 'New password must be at least 4 characters.');
        } elseif ($new !== $confirm) {
            setFlash('error', 'New passwords do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE students SET password = ? WHERE id = ?")->execute([$hash, $sid]);
            setFlash('success', 'Password changed successfully!');
        }
        redirect('/student/profile.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-wrapper animate-fade">
    <div class="page-header"><h1>My Profile</h1></div>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;">
        <!-- Profile Card -->
        <div class="card" style="text-align:center;">
            <div style="width:80px;height:80px;border-radius:50%;background:var(--gradient-primary);display:inline-flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#fff;margin-bottom:16px;">
                <?= strtoupper(substr($student['name'], 0, 1)) ?>
            </div>
            <h2 style="font-size:1.3rem;margin-bottom:4px;"><?= sanitize($student['name']) ?></h2>
            <span class="badge badge-blue" style="margin-bottom:6px;"><?= sanitize($student['enrollment_no']) ?></span><br>
            <span class="badge badge-purple">Student</span>

            <div style="border-top:1px solid var(--border);padding-top:20px;margin-top:20px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div><div style="font-size:1.4rem;font-weight:700;"><?= $totalClasses ?></div><div style="font-size:0.75rem;color:var(--text-muted);">Classes</div></div>
                <div><div style="font-size:1.4rem;font-weight:700;"><?= $stats['total'] ?? 0 ?></div><div style="font-size:0.75rem;color:var(--text-muted);">Quizzes</div></div>
                <div><div style="font-size:1.4rem;font-weight:700;"><?= $accuracy ?>%</div><div style="font-size:0.75rem;color:var(--text-muted);">Accuracy</div></div>
                <div><div style="font-size:1.4rem;font-weight:700;"><?= $stats['correct'] ?? 0 ?>/<?= $stats['questions'] ?? 0 ?></div><div style="font-size:0.75rem;color:var(--text-muted);">Correct</div></div>
            </div>
            <div style="border-top:1px solid var(--border);padding-top:16px;margin-top:16px;font-size:0.78rem;color:var(--text-muted);">
                Joined <?= date('M Y', strtotime($student['created_at'])) ?>
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Enrolled Classes -->
            <div class="card" style="margin-bottom:24px;">
                <div class="card-header"><h3>Enrolled Classes</h3></div>
                <?php if (empty($classList)): ?>
                    <div class="empty-state" style="padding:20px;"><p>Not enrolled in any classes yet.</p></div>
                <?php else: ?>
                    <?php foreach ($classList as $c): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
                        <div>
                            <div style="font-weight:600;font-size:0.9rem;"><?= sanitize($c['class_name']) ?></div>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?= sanitize($c['subject']) ?></div>
                        </div>
                        <span style="font-size:0.78rem;color:var(--text-muted);"><?= sanitize($c['teacher']) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header"><h3>Change Password</h3></div>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Min 4 characters" required minlength="4">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
