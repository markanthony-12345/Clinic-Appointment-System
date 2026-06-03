<?php
require_once 'Database.php';

class Appointment {
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function create($patient_id, $doctor_id, $datetime, $lab_required = false) {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, status)
                VALUES (?, ?, ?, 'Pending')
            ");
            $stmt->execute([$patient_id, $doctor_id, $datetime]);
            $appointment_id = $this->pdo->lastInsertId();

            if ($lab_required) {
                $stmt2 = $this->pdo->prepare("
                    INSERT INTO laboratory (patient_id, laboratory_type, status)
                    VALUES (?, 'From Appointment', 'Not Yet Taken')
                ");
                $stmt2->execute([$patient_id]);
            }

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Appointment create error: " . $e->getMessage());
            return false;
        }
    }

    // Other methods (getAll, getByPatient, etc.) can be added here
}
?>