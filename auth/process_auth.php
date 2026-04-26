<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'teacher_login':
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) {
            setFlash('error', 'All fields are required.');
            redirect('/auth/login.php?role=teacher');
        }
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE email = ?");
        $stmt->execute([$email]);
        $teacher = $stmt->fetch();
        if ($teacher && password_verify($password, $teacher['password'])) {
            setUserSession($teacher['id'], $teacher['name'], 'teacher');
            redirect('/teacher/dashboard.php');
        }
        setFlash('error', 'Invalid email or password.');
        redirect('/auth/login.php?role=teacher');
        break;

    case 'student_login':
        $enrollment = trim($_POST['enrollment_no'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$enrollment || !$password) {
            setFlash('error', 'All fields are required.');
            redirect('/auth/login.php?role=student');
        }
        $stmt = $pdo->prepare("SELECT * FROM students WHERE enrollment_no = ?");
        $stmt->execute([$enrollment]);
        $student = $stmt->fetch();
        if (!$student) {
            setFlash('error', 'Enrollment number not found. Ask your teacher to add you first.');
            redirect('/auth/login.php?role=student');
        }
        if ($student['password'] === '') {
            setFlash('error', 'You haven\'t registered yet. Please register first to set your password.');
            redirect('/auth/student_register.php');
        }
        if (password_verify($password, $student['password'])) {
            setUserSession($student['id'], $student['name'], 'student');
            redirect('/student/dashboard.php');
        }
        setFlash('error', 'Invalid password.');
        redirect('/auth/login.php?role=student');
        break;

    case 'teacher_register':
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!$name || !$email || !$password) {
            setFlash('error', 'All fields are required.');
            redirect('/auth/register.php');
        }
        if ($password !== $confirm) {
            setFlash('error', 'Passwords do not match.');
            redirect('/auth/register.php');
        }
        if (strlen($password) < 6) {
            setFlash('error', 'Password must be at least 6 characters.');
            redirect('/auth/register.php');
        }
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            setFlash('error', 'Email already registered.');
            redirect('/auth/register.php');
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO teachers (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $hash]);
        setUserSession($pdo->lastInsertId(), $name, 'teacher');
        setFlash('success', 'Account created successfully!');
        redirect('/teacher/dashboard.php');
        break;

    case 'student_register':
        $enrollment = trim($_POST['enrollment_no'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!$enrollment || !$password) {
            setFlash('error', 'All fields are required.');
            redirect('/auth/student_register.php');
        }
        if ($password !== $confirm) {
            setFlash('error', 'Passwords do not match.');
            redirect('/auth/student_register.php');
        }
        if (strlen($password) < 4) {
            setFlash('error', 'Password must be at least 4 characters.');
            redirect('/auth/student_register.php');
        }
        // Check if enrollment exists (added by a teacher)
        $stmt = $pdo->prepare("SELECT * FROM students WHERE enrollment_no = ?");
        $stmt->execute([$enrollment]);
        $student = $stmt->fetch();
        if (!$student) {
            setFlash('error', 'Enrollment number not found. Your teacher must add you to a class first.');
            redirect('/auth/student_register.php');
        }
        if ($student['password'] !== '') {
            setFlash('error', 'This enrollment is already registered. Please login instead.');
            redirect('/auth/login.php?role=student');
        }
        // Set the password
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $student['id']]);
        // Auto-login
        setUserSession($student['id'], $student['name'], 'student');
        setFlash('success', 'Registered successfully! Welcome, ' . $student['name'] . '!');
        redirect('/student/dashboard.php');
        break;

    default:
        redirect('/auth/login.php');
}
