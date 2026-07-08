<?php
require_once 'Database.php';

class Auth {
    private $pdo;
    private $sessionKey = 'user_logged';

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // Only allow Admin role registration (for first user)
    public function register($username, $password, $fullname, $role = 'Admin') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$username, $hashed, $fullname, $role]);
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }

    // Login – only Admin allowed
    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password']) && $user['role'] === 'Admin') {
            $_SESSION[$this->sessionKey] = $user;
            return true;
        }
        return false;
    }

    public function isLoggedIn() {
        return isset($_SESSION[$this->sessionKey]);
    }

    public function getUser() {
        return $_SESSION[$this->sessionKey] ?? null;
    }

    public function logout() {
        session_destroy();
    }
}
?>