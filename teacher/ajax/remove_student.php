<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isTeacher()) { jsonResponse(false, 'Unauthorized'); }

$classId = (int)($_POST['class_id'] ?? 0);
$studentId = (int)($_POST['student_id'] ?? 0);
$tid = getCurrentUserId();

// Verify class ownership
$stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$classId, $tid]);
if (!$stmt->fetch()) { jsonResponse(false, 'Class not found.'); }

// Remove from class (not deleting student account)
$del = $pdo->prepare("DELETE FROM class_students WHERE class_id = ? AND student_id = ?");
$del->execute([$classId, $studentId]);

jsonResponse(true, 'Student removed from class.');
