<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireTeacher();
require_once __DIR__ . '/../config/db.php';

$tid = getCurrentUserId();
$classId = (int)($_GET['id'] ?? 0);

// Verify class belongs to teacher
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$classId, $tid]);
$class = $stmt->fetch();
if (!$class) { setFlash('error', 'Class not found.'); redirect('/teacher/classes.php'); }

$pageTitle = $class['class_name'];

// Get students
$studentsStmt = $pdo->prepare("SELECT s.*, cs.enrolled_at FROM students s JOIN class_students cs ON s.id = cs.student_id WHERE cs.class_id = ? ORDER BY s.name");
$studentsStmt->execute([$classId]);
$students = $studentsStmt->fetchAll();

// Get quizzes
$quizzesStmt = $pdo->prepare("SELECT * FROM quizzes WHERE class_id = ? ORDER BY created_at DESC");
$quizzesStmt->execute([$classId]);
$quizzes = $quizzesStmt->fetchAll();

// Get topics
$topicsStmt = $pdo->prepare("SELECT * FROM topics WHERE class_id = ? ORDER BY topic_name");
$topicsStmt->execute([$classId]);
$topics = $topicsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <h1><?= sanitize($class['class_name']) ?></h1>
            <div class="breadcrumb"><a href="/teacher/dashboard.php">Dashboard</a> / <a href="/teacher/classes.php">Classes</a> / <?= sanitize($class['class_name']) ?></div>
            <div style="margin-top:4px;font-size:0.9rem;color:var(--text-secondary);"><?= sanitize($class['subject']) ?> <?= $class['section'] ? '• Section ' . sanitize($class['section']) : '' ?></div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="/teacher/create_quiz.php?class_id=<?= $classId ?>" class="btn btn-primary">+ Create Quiz</a>
            <button class="btn btn-success" onclick="QuizLAN.openModal('addStudentModal')">+ Add Student</button>
        </div>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:28px;">
        <div class="stat-card"><div class="stat-icon blue"><?= icon('users') ?></div><div class="stat-info"><h4>Students</h4><div class="stat-value"><?= count($students) ?></div></div></div>
        <div class="stat-card"><div class="stat-icon purple"><?= icon('file-text') ?></div><div class="stat-info"><h4>Quizzes</h4><div class="stat-value"><?= count($quizzes) ?></div></div></div>
        <div class="stat-card"><div class="stat-icon green"><?= icon('tag') ?></div><div class="stat-info"><h4>Topics</h4><div class="stat-value"><?= count($topics) ?></div></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px;">
        <!-- Students Table -->
        <div class="card">
            <div class="card-header"><h3>Students (<?= count($students) ?>)</h3></div>
            <?php if (empty($students)): ?>
                <div class="empty-state"><div class="empty-icon"></div><h3>No students yet</h3><p>Add students to this class.</p></div>
            <?php else: ?>
                <div class="table-wrapper">
                <table>
                    <thead><tr><th>#</th><th>Name</th><th>Enrollment</th><th>Enrolled</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($students as $i => $s): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= sanitize($s['name']) ?></strong></td>
                        <td><span class="badge badge-blue"><?= sanitize($s['enrollment_no']) ?></span></td>
                        <td style="font-size:0.8rem;color:var(--text-muted);"><?= formatDate($s['enrolled_at']) ?></td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <a href="/teacher/student_detail.php?student_id=<?= $s['id'] ?>&class_id=<?= $classId ?>" class="btn btn-outline btn-sm">View</a>
                                <button class="btn btn-outline btn-sm" style="color:var(--accent-red);" onclick="removeStudent(<?= $s['id'] ?>)">Remove</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quizzes & Topics -->
        <div>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h3>Quizzes</h3></div>
                <?php if (empty($quizzes)): ?>
                    <div class="empty-state" style="padding:30px;"><p>No quizzes yet.</p></div>
                <?php else: ?>
                    <?php foreach ($quizzes as $q): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border);">
                        <div>
                            <strong style="font-size:0.9rem;"><?= sanitize($q['title']) ?></strong>
                            <div style="font-size:0.75rem;color:var(--text-muted);">Code: <?= $q['quiz_code'] ?></div>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <?php
                            $bc = match($q['status']) { 'draft'=>'badge-yellow','lobby'=>'badge-green','active'=>'badge-green','completed'=>'badge-blue',default=>'badge-blue' };
                            ?>
                            <span class="badge <?= $bc ?>"><?= $q['status'] ?></span>
                            <?php if ($q['status'] === 'completed'): ?>
                                <a href="/teacher/quiz_detail.php?id=<?= $q['id'] ?>" class="btn btn-outline btn-sm">Detail</a>
                                <a href="/teacher/quiz_report.php?id=<?= $q['id'] ?>" class="btn btn-outline btn-sm">Report</a>
                            <?php elseif ($q['status'] === 'draft'): ?>
                                <a href="/teacher/host_quiz.php?id=<?= $q['id'] ?>" class="btn btn-success btn-sm">Host</a>
                            <?php elseif (in_array($q['status'], ['lobby', 'active'])): ?>
                                <a href="/teacher/host_quiz.php?id=<?= $q['id'] ?>" class="btn btn-success btn-sm">▶ Continue</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header"><h3>Topics</h3></div>
                <?php if (empty($topics)): ?>
                    <div style="padding:16px 0;color:var(--text-muted);font-size:0.9rem;">Topics are added when creating quizzes.</div>
                <?php else: ?>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($topics as $t): ?>
                        <span class="badge badge-purple"><?= sanitize($t['topic_name']) ?></span>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal">
        <div class="modal-header"><h3>Add Student</h3><button class="modal-close">&times;</button></div>
        <div class="modal-body">
            <form id="addStudentForm">
                <div class="form-group">
                    <label>Student Name</label>
                    <input type="text" id="studentName" class="form-control" placeholder="Full Name" required>
                </div>
                <div class="form-group">
                    <label>Enrollment Number</label>
                    <input type="text" id="studentEnrollment" class="form-control" placeholder="e.g. 2024CS001" required>
                </div>
                <div class="form-text" style="margin-bottom:12px;color:var(--text-muted);font-size:0.8rem;">The student will set their own password when they register on the login page.</div>
                <button type="submit" class="btn btn-primary btn-block">Add Student</button>
            </form>
            <hr style="border-color:var(--border);margin:24px 0;">
            <h4 style="margin-bottom:12px;font-size:0.95rem;">Bulk Import (CSV)</h4>
            <form id="csvForm" enctype="multipart/form-data">
                <div class="form-group">
                    <input type="file" id="csvFile" accept=".csv" class="form-control">
                    <div class="form-text">CSV format: enrollment_no, name (one per line)</div>
                </div>
                <button type="submit" class="btn btn-outline btn-block">Import CSV</button>
            </form>
        </div>
    </div>
</div>

<script>
const CLASS_ID = <?= $classId ?>;

document.getElementById('addStudentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData();
    data.append('class_id', CLASS_ID);
    data.append('name', document.getElementById('studentName').value);
    data.append('enrollment_no', document.getElementById('studentEnrollment').value);
    const res = await QuizLAN.ajax('/teacher/ajax/add_student.php', data);
    if (res.success) { QuizLAN.toast(res.message, 'success'); setTimeout(() => location.reload(), 800); }
    else { QuizLAN.toast(res.message, 'error'); }
});

document.getElementById('csvForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const file = document.getElementById('csvFile').files[0];
    if (!file) { QuizLAN.toast('Please select a CSV file.', 'error'); return; }
    const data = new FormData();
    data.append('class_id', CLASS_ID);
    data.append('csv_file', file);
    data.append('bulk', '1');
    const res = await QuizLAN.ajax('/teacher/ajax/add_student.php', data);
    if (res.success) { QuizLAN.toast(res.message, 'success'); setTimeout(() => location.reload(), 800); }
    else { QuizLAN.toast(res.message, 'error'); }
});

async function removeStudent(studentId) {
    if (!confirm('Remove this student from the class?')) return;
    const data = new FormData();
    data.append('class_id', CLASS_ID);
    data.append('student_id', studentId);
    const res = await QuizLAN.ajax('/teacher/ajax/remove_student.php', data);
    if (res.success) { QuizLAN.toast(res.message, 'success'); setTimeout(() => location.reload(), 500); }
    else { QuizLAN.toast(res.message, 'error'); }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
