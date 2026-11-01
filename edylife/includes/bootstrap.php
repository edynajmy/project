<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Kuala_Lumpur');

$configFile = __DIR__ . '/../config/database.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration file is missing. Copy config/database.example.php to config/database.php and update your database settings.');
}
$config = require $configFile;

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Unable to connect to MySQL. Check config/database.php and confirm MySQL is running in XAMPP.');
}

// Small local profile record; created automatically so no manual SQL import is needed.
$pdo->exec("CREATE TABLE IF NOT EXISTS profile_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    display_name VARCHAR(80) NOT NULL DEFAULT 'Edy',
    tagline VARCHAR(160) NOT NULL DEFAULT 'Your world. Your story.',
    avatar_path VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("INSERT IGNORE INTO profile_settings (id, display_name, tagline) VALUES (1, 'Edy', 'Your world. Your story.')");
$profileSettings = $pdo->query('SELECT display_name, tagline, avatar_path FROM profile_settings WHERE id = 1')->fetch() ?: ['display_name' => 'Edy', 'tagline' => 'Your world. Your story.', 'avatar_path' => null];

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Invalid or expired form token. Refresh the page and try again.');
    }
}

function redirect_home(string $status): never {
    header('Location: ' . app_url('index.php?status=' . rawurlencode($status)));
    exit;
}

function app_url(string $path = ''): string {
    $scriptDir = str_replace('\\\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/noirlife/index.php'));
    $base = preg_replace('~/(pages|actions)$~', '', $scriptDir) ?? $scriptDir;
    $base = rtrim($base, '/');
    if ($base === '.' || $base === '/') {
        $base = '';
    }
    return $base . '/' . ltrim($path, '/');
}
