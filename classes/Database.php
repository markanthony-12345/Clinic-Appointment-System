<?php
class Database {
    private $host = 'localhost';
    private $dbname = 'clinic_management';
    private $username = 'root';
    private $password = ''; // <- Palitan kung may password ang MySQL ng classmate mo
 
    private $pdo;
 
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Show friendly error instead of raw PHP error
            $msg = $e->getMessage();
            if (str_contains($msg, 'Access denied')) {
                die("<h2 style='font-family:sans-serif;color:red;padding:30px'>
                    ❌ Database Error: Wrong username or password.<br><br>
                    <small>Open <b>classes/Database.php</b> and change the <b>\$password</b> to match your MySQL password.</small>
                </h2>");
            } elseif (str_contains($msg, 'Unknown database')) {
                die("<h2 style='font-family:sans-serif;color:red;padding:30px'>
                    ❌ Database Error: Database '<b>clinic_management</b>' not found.<br><br>
                    <small>Open <b>phpMyAdmin</b> and import the <b>clinic_management.sql</b> file first.</small>
                </h2>");
            } elseif (str_contains($msg, 'Connection refused') || str_contains($msg, "Can't connect")) {
                die("<h2 style='font-family:sans-serif;color:red;padding:30px'>
                    ❌ Database Error: Cannot connect to MySQL.<br><br>
                    <small>Make sure <b>XAMPP is running</b> and <b>Apache + MySQL are both started</b>.</small>
                </h2>");
            } else {
                error_log("DB Error: " . $msg);
                die("<h2 style='font-family:sans-serif;color:red;padding:30px'>
                    ❌ Database connection failed.<br><br>
                    <small>Check that XAMPP is running and the database is imported.</small>
                </h2>");
            }
        }
    }
 
    public function getConnection() {
        return $this->pdo;
    }
}
?>