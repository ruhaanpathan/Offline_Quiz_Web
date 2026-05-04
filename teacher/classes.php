<?php
$pageTitle = 'My Classes';
require_once __DIR__ . '/../includes/auth_check.php';
requireTeacher();
require_once __DIR__ . '/../config/db.php';

$tid = getCurrentUserId();

// Handle create class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_class') {
    $name = trim($_POST['class_name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $section = trim($_POST['section'] ?? '');
    if ($name && $subject) {
        $stmt = $pdo->prepare("INSERT INTO classes (teacher_id, class_name, subject, section) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tid, $name, $subject, $section]);
        setFlash('success', "Class '$name' created successfully!");
        redirect('/teacher/classes.php');
    } else {
        setFlash('error', 'Class name and subject are required.');
    }
}

// Handle delete class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_class') {
    $classId = (int)($_POST['class_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$classId, $tid]);
    setFlash('success', 'Class deleted.');
    redirect('/teacher/classes.php');
}

$classesList = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM class_students WHERE class_id = c.id) as student_count, (SELECT COUNT(*) FROM quizzes WHERE class_id = c.id) as quiz_count FROM classes c WHERE c.teacher_id = ? ORDER BY c.created_at DESC");
$classesList->execute([$tid]);
$classes = $classesList->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <h1>My Classes</h1>
            <div class="breadcrumb"><a href="/teacher/dashboard.php">Dashboard</a> / Classes</div>
        </div>
        <button class="btn btn-primary" onclick="QuizLAN.openModal('createClassModal')">+ Create Class</button>
    </div>

    <?php if (empty($classes)): ?>
        <div class="card"><div class="empty-state"><div class="empty-icon"></div><h3>No classes yet</h3><p>Create your first class to start adding students and quizzes.</p>
        <button class="btn btn-primary" onclick="QuizLAN.openModal('createClassModal')">+ Create Class</button></div></div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($classes as $c): ?>
            <div class="card" style="cursor:pointer;" onclick="window.location='/teacher/class_detail.php?id=<?= $c['id'] ?>'">
                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:16px;">
                    <div>
                        <h3 style="margin-bottom:4px;"><?= sanitize($c['class_name']) ?></h3>
                        <div style="color:var(--text-muted);font-size:0.85rem;"><?= sanitize($c['subject']) ?> <?= $c['section'] ? '• ' . sanitize($c['section']) : '' ?></div>
                    </div>
                    <form method="POST" style="display:inline;" onclick="event.stopPropagation();">
                        <input type="hidden" name="action" value="delete_class">
                        <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn-icon btn btn-outline" onclick="return confirm('Delete this class and all its data?')" title="Delete" style="color:var(--accent-red);"><?= icon('trash', 16) ?></button>
                    </form>
                </div>
                <div style="display:flex;gap:12px;">
                    <span class="badge badge-blue"><?= $c['student_count'] ?> students</span>
                    <span class="badge badge-purple"><?= $c['quiz_count'] ?> quizzes</span>
                </div>
                <div style="margin-top:12px;font-size:0.8rem;color:var(--text-muted);">Created <?= formatDate($c['created_at']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Class Modal -->
<div class="modal-overlay" id="createClassModal">
    <div class="modal">
        <div class="modal-header"><h3>Create New Class</h3><button class="modal-close">&times;</button></div>
        <form method="POST" class="modal-body">
            <input type="hidden" name="action" value="create_class">
            <div class="form-group">
                <label>Class Name</label>
                <input type="text" name="class_name" class="form-control" placeholder="e.g. CS-301 Data Structures" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" class="form-control" placeholder="e.g. Computer Science" required>
                </div>
                <div class="form-group">
                    <label>Section (optional)</label>
                    <input type="text" name="section" class="form-control" placeholder="e.g. A">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Create Class</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
