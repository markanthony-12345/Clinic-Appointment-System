<?php
// Enable error display (remove later)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session security
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 1800);
    session_start();
}

// Security headers
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Database credentials
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "clinic_management";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

// Auto-load service classes (simple)
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/AppointmentService.php';
require_once __DIR__ . '/classes/PatientService.php';
require_once __DIR__ . '/classes/PaymentService.php';
require_once __DIR__ . '/classes/LabService.php';
require_once __DIR__ . '/classes/MedicineService.php';
require_once __DIR__ . '/classes/ReportService.php';

// Auth guards – now only Admin allowed
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset(); session_destroy();
        header("Location: login.php?timeout=1"); exit;
    }
    $_SESSION['last_activity'] = time();
    if (!isset($_SESSION['user_logged'])) { header("Location: login.php"); exit; }
    // Enforce Admin role
    if ($_SESSION['user_logged']['role'] !== 'Admin') {
        session_destroy();
        header("Location: login.php?error=access_denied"); exit;
    }
}

function requireAdmin() {
    requireLogin(); // already enforces admin
}

// CSRF helpers
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// Rate limiting
function checkLoginRateLimit() {
    $max = 5; $lockout = 300;
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_lockout_time'] = 0;
    }
    if ($_SESSION['login_attempts'] >= $max) {
        $elapsed = time() - $_SESSION['login_lockout_time'];
        if ($elapsed < $lockout) return ['locked' => true, 'remaining' => $lockout - $elapsed];
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_lockout_time'] = 0;
    }
    return ['locked' => false, 'remaining' => 0];
}
function recordFailedLogin() {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_lockout_time'] = time();
}
function clearLoginAttempts() {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_lockout_time'] = 0;
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}
?>