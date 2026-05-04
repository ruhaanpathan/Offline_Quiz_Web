<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/auth_check.php';
requireTeacher();
require_once __DIR__ . '/../config/db.php';

$tid = getCurrentUserId();

$teacher = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
$teacher->execute([$tid]);
$teacher = $teacher->fetch();

// Stats
$classCount = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ?");
$classCount->execute([$tid]);
$totalClasses = $classCount->fetchColumn();

$studentCount = $pdo->prepare("SELECT COUNT(DISTINCT cs.student_id) FROM class_students cs JOIN classes c ON cs.class_id = c.id WHERE c.teacher_id = ?");
$studentCount->execute([$tid]);
$totalStudents = $studentCount->fetchColumn();

$quizCount = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE teacher_id = ?");
$quizCount->execute([$tid]);
$totalQuizzes = $quizCount->fetchColumn();

$totalQuestions = $pdo->prepare("SELECT COUNT(*) FROM questions qs JOIN quizzes q ON qs.quiz_id = q.id WHERE q.teacher_id = ?");
$totalQuestions->execute([$tid]);
$totalQs = $totalQuestions->fetchColumn();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name && $email) {
            $stmt = $pdo->prepare("UPDATE teachers SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $tid]);
            $_SESSION['user_name'] = $name;
            setFlash('success', 'Profile updated!');
            redirect('/teacher/profile.php');
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $teacher['password'])) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            setFlash('error', 'New passwords do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE teachers SET password = ? WHERE id = ?")->execute([$hash, $tid]);
            setFlash('success', 'Password changed successfully!');
        }
        redirect('/teacher/profile.php');
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
                <?= strtoupper(substr($teacher['name'], 0, 1)) ?>
            </div>
            <h2 style="font-size:1.3rem;margin-bottom:4px;"><?= sanitize($teacher['name']) ?></h2>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:4px;"><?= sanitize($teacher['email']) ?></p>
            <span class="badge badge-blue" style="margin-bottom:20px;">Teacher</span>

            <div style="border-top:1px solid var(--border);padding-top:20px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div><div style="font-size:1.4rem;font-weight:700;"><?= $totalClasses ?></div><div style="font-size:0.75rem;color:var(--text-muted);">Classes</div></div>
                <div><div style="font-size:1.4rem;font-weight:700;"><?= $totalStudents ?></div><div style="font-size:0.75rem;color:var(--text-muted);">Students</div></div>
                <div><div style="font-size:1.4rem;font-weight:700;"><?= $totalQuizzes ?></div><div style="font-size:0.75rem;color:var(--text-muted);">Quizzes</div></div>
                <div><div style="font-size:1.4rem;font-weight:700;"><?= $totalQs ?></div><div style="font-size:0.75rem;color:var(--text-muted);">Questions</div></div>
            </div>
            <div style="border-top:1px solid var(--border);padding-top:16px;margin-top:16px;font-size:0.78rem;color:var(--text-muted);">
                Joined <?= date('M Y', strtotime($teacher['created_at'])) ?>
            </div>
        </div>

        <!-- Edit Forms -->
        <div>
            <div class="card" style="margin-bottom:24px;">
                <div class="card-header"><h3>Edit Profile</h3></div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= sanitize($teacher['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= sanitize($teacher['email']) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>

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
                            <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
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
