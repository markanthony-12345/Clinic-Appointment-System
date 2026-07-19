<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM laboratory");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $alterQueries = [];
    $desiredColumns = [
        'appointment_id'   => "INT NULL AFTER lab_id",
        'appointment_date' => "DATE NULL AFTER appointment_id",
        'appointment_time' => "TIME NULL AFTER appointment_date",
        'procedure_name'   => "VARCHAR(100) NULL AFTER laboratory_type",
        'procedure_fee'    => "DECIMAL(10,2) NULL AFTER procedure_name"
    ];

    foreach ($desiredColumns as $colName => $colDef) {
        if (!in_array($colName, $columns)) {
            $alterQueries[] = "ADD COLUMN $colName $colDef";
        }
    }

    $indexStmt = $pdo->query("SHOW INDEX FROM laboratory WHERE Key_name = 'idx_appointment'");
    if ($indexStmt->rowCount() == 0) {
        $alterQueries[] = "ADD INDEX idx_appointment (appointment_id)";
    }

    $fkStmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME = 'laboratory' 
        AND COLUMN_NAME = 'appointment_id' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    if ($fkStmt->rowCount() == 0) {
        $alterQueries[] = "ADD FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL";
    }

    if (empty($alterQueries)) {
        echo "✅ All columns and constraints already exist. No changes needed.";
    } else {
        $sql = "ALTER TABLE laboratory " . implode(", ", $alterQueries);
        $pdo->exec($sql);
        echo "✅ Laboratory table updated successfully.<br>";
        echo "Added: " . implode(", ", array_keys($desiredColumns));
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>