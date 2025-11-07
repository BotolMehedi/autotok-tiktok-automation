<?php
session_start();

if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}

// db
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'autoposting_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// tiktok
define('TIKTOK_CLIENT_KEY', $_ENV['TIKTOK_CLIENT_KEY'] ?? '');
define('TIKTOK_CLIENT_SECRET', $_ENV['TIKTOK_CLIENT_SECRET'] ?? '');
define('TIKTOK_REDIRECT_URI', $_ENV['TIKTOK_REDIRECT_URI'] ?? '');

// app
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost/autotok');
define('APP_NAME', $_ENV['APP_NAME'] ?? 'AUTOTOK');
define('APP_EMAIL', $_ENV['APP_EMAIL'] ?? 'hello@nexatechstudio.com');
define('TIMEZONE', $_ENV['TIMEZONE'] ?? 'UTC');

// sec
define('SESSION_LIFETIME', $_ENV['SESSION_LIFETIME'] ?? 86400);

// upload
define('MAX_VIDEO_SIZE', $_ENV['MAX_VIDEO_SIZE'] ?? 524288000); // 500 mb
define('ALLOWED_VIDEO_TYPES', $_ENV['ALLOWED_VIDEO_TYPES'] ?? 'mp4');

// cron
define('CRON_TIME_TOLERANCE', $_ENV['CRON_TIME_TOLERANCE'] ?? 3);

// timezone
date_default_timezone_set(TIMEZONE);

// connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['open_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function logActivity($pdo, $userId, $username, $action, $videoName = null, $caption = null, $status = 'success', $message = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, username, action, video_name, caption, status, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $username, $action, $videoName, $caption, $status, $message]);
        
        
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/' . $username . '.log';
        $logMessage = date('Y-m-d H:i:s') . " | $action | Status: $status";
        if ($videoName) $logMessage .= " | Video: $videoName";
        if ($message) $logMessage .= " | Message: $message";
        $logMessage .= PHP_EOL;
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        error_log("Logging failed: " . $e->getMessage());
    }
}

function updateStats($pdo) {
    try {
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $totalProfiles = $stmt->fetch()['count'];
        
        
        $stmt = $pdo->query("SELECT SUM(videos_count) as count FROM schedules WHERE status = 'active'");
        $totalVideos = $stmt->fetch()['count'] ?? 0;
        
        
        $stmt = $pdo->query("SELECT MAX(last_uploaded_at) as last_upload FROM schedules");
        $lastUpload = $stmt->fetch()['last_upload'];
        
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM schedules WHERE status = 'active' AND DATE(next_scheduled_at) = CURDATE()");
        $activeToday = $stmt->fetch()['count'];
        
        
        $stmt = $pdo->prepare("UPDATE stats SET total_profiles = ?, total_videos_scheduled = ?, last_video_uploaded_at = ?, active_schedules_today = ? WHERE id = 1");
        $stmt->execute([$totalProfiles, $totalVideos, $lastUpload, $activeToday]);
    } catch (Exception $e) {
        error_log("Stats update failed: " . $e->getMessage());
    }
}
?>
