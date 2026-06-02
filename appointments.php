<?php
require_once 'Database.php';

class Appointment {
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    // Kukunin ang lahat ng appointments kasama ang pangalan ng patient at doktor
    public function getAll() {
        $stmt = $this->pdo->query("
            SELECT a.*, p.fullname AS patient_name, d.doctor_name, d.specialization
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN doctors d  ON a.doctor_id  = d.doctor_id
            ORDER BY a.appointment_date DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Kukunin ang lahat ng appointments ng isang specific na patient
    public function getByPatient($patient_id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, d.doctor_name, d.specialization
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC
        ");
        $stmt->execute([$patient_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Gumawa ng bagong appointment — may optional na lab record kung kailangan
    public function create($patient_id, $doctor_id, $datetime, $lab_required = false) {
        $this->pdo->beginTransaction();

        try {
            // I-insert ang appointment
            $stmt = $this->pdo->prepare("
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, status, lab_required)
                VALUES (?, ?, ?, 'Scheduled', ?)
            ");
            $stmt->execute([
                $patient_id,
                $doctor_id,
                $datetime,
                $lab_required ? 'Yes' : 'No'
            ]);
            $appointment_id = $this->pdo->lastInsertId();

            // Kung kailangan ng lab, gumawa ng lab record kasabay
            if ($lab_required) {
                $stmt2 = $this->pdo->prepare("
                    INSERT INTO laboratory (patient_id, laboratory_type, status)
                    VALUES (?, 'From Appointment', 'Not Yet Taken')
                ");
                $stmt2->execute([$patient_id]);
            }

            $this->pdo->commit();
            return $appointment_id;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Appointment create error: " . $e->getMessage());
            return false;
        }
    }

    // I-update ang status ng appointment (Completed, Cancelled, Pending, Scheduled)
    public function updateStatus($appointment_id, $status) {
        $allowed = ['Scheduled', 'Pending', 'Completed', 'Cancelled'];
        if (!in_array($status, $allowed)) return false;

        $stmt = $this->pdo->prepare("
            UPDATE appointments SET status = ? WHERE appointment_id = ?
        ");
        return $stmt->execute([$status, $appointment_id]);
    }

    // I-cancel ang appointment (soft delete — hindi pinapalitan ng DELETE)
    public function cancel($appointment_id) {
        return $this->updateStatus($appointment_id, 'Cancelled');
    }

    // I-check kung may available pa bang slot ang doktor sa isang araw
    public function isSlotAvailable($doctor_id, $date) {
        // Kunin ang max_patients ng doktor
        $stmt = $this->pdo->prepare("SELECT max_patients FROM doctors WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $max = $stmt->fetchColumn();

        // Bilangin ang kasalukuyang aktibong appointments sa araw na iyon
        $stmt2 = $this->pdo->prepare("
            SELECT COUNT(*) FROM appointments
            WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status != 'Cancelled'
        ");
        $stmt2->execute([$doctor_id, $date]);
        $current = $stmt2->fetchColumn();

        return [
            'available'     => $current < $max,
            'current_count' => $current,
            'max_patients'  => $max,
            'remaining'     => $max - $current
        ];
    }
}
?>