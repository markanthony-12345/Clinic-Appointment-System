<?php
class Auth {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_logged'] = [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'fullname' => $user['fullname'],
                'role' => $user['role']
            ];
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_logged']);
    }

    public function logout() {
        session_unset();
        session_destroy();
    }

    public function register($username, $password, $fullname, $role = 'User') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$username, $hashed, $fullname, $role]);
    }
}
?>