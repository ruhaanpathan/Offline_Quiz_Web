<?php
/**
 * QuizLAN — Helper Functions
 */

/**
 * Sanitize user input
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * Set a flash message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type,    // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate a random quiz code (4 alphanumeric characters, uppercase)
 */
function generateQuizCode(PDO $pdo): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No I, O, 0, 1 to avoid confusion
    do {
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        // Make sure code is unique
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE quiz_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() > 0);

    return $code;
}

/**
 * Get the base URL of the application (works on LAN)
 */
function getBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_ADDR'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Find the project root directory in the URL
    $path = $_SERVER['REQUEST_URI'];
    $projectRoot = '';
    
    // Walk up from current script to find the project root
    $parts = explode('/', trim($scriptDir, '/'));
    // We want the base path up to the project folder
    $baseParts = [];
    foreach ($parts as $part) {
        $baseParts[] = $part;
    }
    $projectRoot = '/' . implode('/', $baseParts);
    
    return $protocol . '://' . $host . $projectRoot;
}

/**
 * Get the server's LAN IP address
 */
function getLanIp(): string {
    // Try to get the server's IP address
    if (!empty($_SERVER['SERVER_ADDR'])) {
        return $_SERVER['SERVER_ADDR'];
    }
    
    // Fallback: try to detect from hostname
    $hostname = gethostname();
    $ip = gethostbyname($hostname);
    
    return $ip ?: '127.0.0.1';
}

/**
 * Format a datetime string for display
 */
function formatDate(string $datetime): string {
    return date('M j, Y g:i A', strtotime($datetime));
}

/**
 * Calculate percentage safely (avoids division by zero)
 */
function percentage(int $part, int $total): float {
    if ($total === 0) return 0;
    return round(($part / $total) * 100, 1);
}

/**
 * JSON response helper (for AJAX endpoints)
 */
function jsonResponse(bool $success, string $message = '', array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}
