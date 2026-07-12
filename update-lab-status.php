<?php
header('Content-Type: application/json');

try {
    require_once 'config.php';
    requireLogin();

    $lab_id = (int)($_GET['lab_id'] ?? 0);
    $status = trim($_GET['status'] ?? '');

    if (!$lab_id || !in_array($status, ['Not Yet Taken', 'Ongoing', 'Completed'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE laboratory SET status = ? WHERE lab_id = ?");
    $result = $stmt->execute([$status, $lab_id]);

    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made or record not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>