<?php
require_once 'config.php';
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Database connection successful!";
} catch (Exception $e) {
    echo "❌ DB connection failed: " . $e->getMessage();
}
?>