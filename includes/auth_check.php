<?php
/**
 * QuizLAN — Auth Middleware
 * Include this at the top of any protected page.
 * Usage:
 *   require_once __DIR__ . '/../includes/auth_check.php';
 *   requireLogin();           // any logged-in user
 *   requireTeacher();         // teacher only
 *   requireStudent();         // student only
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('error', 'Please login to continue.');
        redirect('/auth/login.php');
    }
}

function requireTeacher(): void {
    requireLogin();
    if (!isTeacher()) {
        setFlash('error', 'Access denied. Teachers only.');
        redirect('/auth/login.php');
    }
}

function requireStudent(): void {
    requireLogin();
    if (!isStudent()) {
        setFlash('error', 'Access denied. Students only.');
        redirect('/auth/login.php');
    }
}
