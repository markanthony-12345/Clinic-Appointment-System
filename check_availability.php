<?php
require_once 'config.php';
header('Content-Type: application/json');
if (isset($_GET['doctor_id']) && isset($_GET['date'])) {
    $result = doctorAvailable($pdo, $_GET['doctor_id'], $_GET['date']);
    echo json_encode($result);
} else {
    echo json_encode(['available' => false, 'message' => 'Missing parameters']);
}
?>