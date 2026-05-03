<?php
/**
 * LANparty — Session Management
 * Start session and provide helper functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Check if the logged-in user is a teacher
 */
function isTeacher(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === 'teacher';
}

/**
 * Check if the logged-in user is a student
 */
function isStudent(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === 'student';
}

/**
 * Get current user ID
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user name
 */
function getCurrentUserName(): ?string {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Set session data after login
 */
function setUserSession(int $id, string $name, string $role): void {
    $_SESSION['user_id']   = $id;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_role'] = $role;
}

/**
 * Destroy session (logout)
 */
function destroySession(): void {
    session_unset();
    session_destroy();
}
