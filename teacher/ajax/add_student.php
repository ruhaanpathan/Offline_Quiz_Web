<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isTeacher()) { jsonResponse(false, 'Unauthorized'); }

$classId = (int)($_POST['class_id'] ?? 0);
$tid = getCurrentUserId();

// Verify class ownership
$stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$classId, $tid]);
if (!$stmt->fetch()) { jsonResponse(false, 'Class not found.'); }

// Bulk CSV import
if (!empty($_POST['bulk']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (!$file) { jsonResponse(false, 'No file uploaded.'); }
    $rows = array_map('str_getcsv', file($file));
    $added = 0; $skipped = 0;
    foreach ($rows as $row) {
        if (count($row) < 2) { $skipped++; continue; }
        $enrollment = trim($row[0]);
        $name = trim($row[1]);
        if (!$enrollment || !$name) { $skipped++; continue; }
        // Check if student exists
        $check = $pdo->prepare("SELECT id FROM students WHERE enrollment_no = ?");
        $check->execute([$enrollment]);
        $student = $check->fetch();
        if ($student) {
            $studentId = $student['id'];
        } else {
            // Create student WITHOUT password (they'll register themselves)
            $ins = $pdo->prepare("INSERT INTO students (name, enrollment_no, password) VALUES (?, ?, '')");
            $ins->execute([$name, $enrollment]);
            $studentId = $pdo->lastInsertId();
        }
        // Enroll in class (ignore duplicate)
        try {
            $enroll = $pdo->prepare("INSERT INTO class_students (class_id, student_id) VALUES (?, ?)");
            $enroll->execute([$classId, $studentId]);
            $added++;
        } catch (PDOException $e) {
            $skipped++; // duplicate
        }
    }
    jsonResponse(true, "Imported $added students. $skipped skipped.");
}

// Single student add
$name = trim($_POST['name'] ?? '');
$enrollment = trim($_POST['enrollment_no'] ?? '');

if (!$name || !$enrollment) {
    jsonResponse(false, 'Name and Enrollment Number are required.');
}

// Check if student already exists by enrollment
$check = $pdo->prepare("SELECT id FROM students WHERE enrollment_no = ?");
$check->execute([$enrollment]);
$existing = $check->fetch();

if ($existing) {
    $studentId = $existing['id'];
} else {
    // Create student WITHOUT password (they'll register themselves)
    $ins = $pdo->prepare("INSERT INTO students (name, enrollment_no, password) VALUES (?, ?, '')");
    $ins->execute([$name, $enrollment]);
    $studentId = $pdo->lastInsertId();
}

// Enroll in class
try {
    $enroll = $pdo->prepare("INSERT INTO class_students (class_id, student_id) VALUES (?, ?)");
    $enroll->execute([$classId, $studentId]);
    jsonResponse(true, "Student '$name' added. They can register on the login page.");
} catch (PDOException $e) {
    jsonResponse(false, 'Student is already in this class.');
}
