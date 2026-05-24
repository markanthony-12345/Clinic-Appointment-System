<?php
require_once 'config.php';
header('Content-Type: application/json');
if (isset($_GET['id'])) {
    $name = getPatientName($pdo, $_GET['id']);
    echo json_encode(['fullname' => $name ?: '']);
}
?>