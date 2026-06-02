<?php
require_once 'config.php';
requireLogin();
$id = $_GET['id'] ?? 0;
$pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?")->execute([$id]);
header("Location: dashboard.php?success=Appointment cancelled");
exit;
?>