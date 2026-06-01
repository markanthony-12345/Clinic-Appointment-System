<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$type  = $_GET['type']       ?? '';
$pid   = (int)($_GET['patient_id'] ?? 0);
$value = ($_GET['value']     ?? 'false') === 'true';

if (!$type || !$pid) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    switch ($type) {
        case 'consult':
            $status = $value ? 'Completed' : 'Pending';
            $pdo->prepare("UPDATE appointments SET status = ? WHERE patient_id = ? AND status != 'Cancelled' ORDER BY appointment_date DESC LIMIT 1")
                ->execute([$status, $pid]);
            break;
        case 'lab':
            $status = $value ? 'Completed' : 'Not Yet Taken';
            $pdo->prepare("UPDATE laboratory SET status = ? WHERE patient_id = ? ORDER BY lab_id DESC LIMIT 1")
                ->execute([$status, $pid]);
            break;
        case 'medicine':
            $status = $value ? 'Taken' : 'Not Taken';
            $pdo->prepare("UPDATE medicines SET status = ? WHERE patient_id = ? ORDER BY medicine_id DESC LIMIT 1")
                ->execute([$status, $pid]);
            break;
        case 'payment':
            if ($value) {
                $pdo->prepare("UPDATE payments SET amount_paid = total_amount WHERE patient_id = ?")->execute([$pid]);
            } else {
                $pdo->prepare("UPDATE payments SET amount_paid = 0 WHERE patient_id = ?")->execute([$pid]);
            }
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            exit;
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
