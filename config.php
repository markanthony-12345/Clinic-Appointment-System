<?php
// Enable error display for debugging (remove later)
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

// Database credentials – EDIT THESE for your local MySQL
$servername = "localhost";
$db_username = "root";
$db_password = "";      // Change to your MySQL password (XAMPP default is empty)
$dbname = "clinic_management";

// Connect with friendly errors
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'Access denied') !== false)
        die("❌ DB Access denied. Check password in config.php");
    elseif (strpos($msg, 'Unknown database') !== false)
        die("❌ Database 'clinic_management' not found. Import the SQL file.");
    elseif (strpos($msg, 'Connection refused') !== false)
        die("❌ MySQL not running. Start XAMPP MySQL.");
    else
        die("❌ DB connection failed: " . $msg);
}

// ========== NEW: Doctor day‑of‑week validation ==========
function doctorWorksOnDay($pdo, $doctor_id, $date) {
    $stmt = $pdo->prepare("SELECT schedule FROM doctors WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $schedule = $stmt->fetchColumn();
    if (!$schedule) return true; // If no schedule is stored, assume any day is allowed

    $dayOfWeek = date('l', strtotime($date));
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $workingDays = [];
    foreach ($days as $day) {
        if (stripos($schedule, $day) !== false) {
            $workingDays[] = $day;
        }
    }
    return in_array($dayOfWeek, $workingDays);
}

// ========== Modified doctorAvailable with day check ==========
function doctorAvailable($pdo, $doctor_id, $date) {
    // First, check if the doctor works on this day
    if (!doctorWorksOnDay($pdo, $doctor_id, $date)) {
        return [
            'available' => false,
            'remaining' => 0,
            'max_patients' => 0,
            'current_count' => 0,
            'reason' => 'Doctor does not work on ' . date('l', strtotime($date))
        ];
    }
    // Then check daily patient limit
    $stmt = $pdo->prepare("SELECT max_patients FROM doctors WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $max = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status != 'Cancelled'");
    $stmt->execute([$doctor_id, $date]);
    $current = $stmt->fetchColumn();
    return [
        'available' => $current < $max,
        'remaining' => $max - $current,
        'max_patients' => $max,
        'current_count' => $current
    ];
}

// Auth guards
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset(); session_destroy();
        header("Location: login.php?timeout=1"); exit;
    }
    $_SESSION['last_activity'] = time();
    if (!isset($_SESSION['user_logged'])) { header("Location: login.php"); exit; }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_logged']['role'] !== 'Admin') die("Access denied. Admin only.");
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

// Sanitizer
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Helpers
function getPatientName($pdo, $id) {
    $stmt = $pdo->prepare("SELECT fullname FROM patients WHERE patient_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}
function recalcTotal($pdo, $patient_id) {
    $pdo->prepare("UPDATE payments SET total_amount = consultation_fee + laboratory_fee WHERE patient_id = ?")->execute([$patient_id]);
}
?>