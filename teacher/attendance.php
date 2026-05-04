<?php
$pageTitle = 'Attendance';
require_once __DIR__ . '/../includes/auth_check.php';
requireTeacher();
require_once __DIR__ . '/../config/db.php';

$tid = getCurrentUserId();
$classId = (int)($_GET['class_id'] ?? 0);

$class = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$class->execute([$classId, $tid]);
$class = $class->fetch();
if (!$class) { setFlash('error', 'Class not found.'); redirect('/teacher/dashboard.php'); }

// Get all quizzes for this class
$quizzes = $pdo->prepare("SELECT * FROM quizzes WHERE class_id = ? AND status = 'completed' ORDER BY created_at DESC");
$quizzes->execute([$classId]);
$quizList = $quizzes->fetchAll();

// Get all students
$students = $pdo->prepare("SELECT s.* FROM students s JOIN class_students cs ON s.id = cs.student_id WHERE cs.class_id = ? ORDER BY s.name");
$students->execute([$classId]);
$studentList = $students->fetchAll();

// Build attendance matrix
$attendanceMap = [];
foreach ($quizList as $q) {
    $att = $pdo->prepare("SELECT student_id FROM quiz_attempts WHERE quiz_id = ?");
    $att->execute([$q['id']]);
    $attendanceMap[$q['id']] = array_column($att->fetchAll(), 'student_id');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <h1>Attendance — <?= sanitize($class['class_name']) ?></h1>
            <div class="breadcrumb"><a href="/teacher/dashboard.php">Dashboard</a> / <a href="/teacher/class_detail.php?id=<?= $classId ?>"><?= sanitize($class['class_name']) ?></a> / Attendance</div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Enrollment</th>
                    <?php foreach ($quizList as $q): ?>
                    <th style="text-align:center;font-size:0.7rem;max-width:80px;overflow:hidden;text-overflow:ellipsis;"><?= sanitize($q['title']) ?></th>
                    <?php endforeach; ?>
                    <th>Total</th>
                    <th>%</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($studentList as $s): ?>
                <?php $present = 0; ?>
                <tr>
                    <td><strong><?= sanitize($s['name']) ?></strong></td>
                    <td><span class="badge badge-blue"><?= sanitize($s['enrollment_no']) ?></span></td>
                    <?php foreach ($quizList as $q): ?>
                        <?php $isPresent = in_array($s['id'], $attendanceMap[$q['id']] ?? []); if ($isPresent) $present++; ?>
                        <td style="text-align:center;"><?= $isPresent ? '' : '' ?></td>
                    <?php endforeach; ?>
                    <td><strong><?= $present ?>/<?= count($quizList) ?></strong></td>
                    <td><span class="badge <?= percentage($present, count($quizList))>=75?'badge-green':'badge-red' ?>"><?= percentage($present, count($quizList)) ?>%</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
