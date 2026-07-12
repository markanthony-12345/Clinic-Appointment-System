<?php
class AppointmentService {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function checkAvailability($doctor_id, $date) {
        $docStmt = $this->pdo->prepare("SELECT max_patients FROM doctors WHERE doctor_id = ?");
        $docStmt->execute([$doctor_id]);
        $doctor = $docStmt->fetch();
        if (!$doctor) {
            return ['available' => false, 'remaining' => 0, 'reason' => 'Doctor not found'];
        }

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments 
            WHERE doctor_id = ? 
                AND DATE(appointment_date) = ? 
                AND status != 'Cancelled'
        ");
        $countStmt->execute([$doctor_id, $date]);
        $count = (int)$countStmt->fetchColumn();

        $max = (int)$doctor['max_patients'];
        $remaining = max(0, $max - $count);

        return [
            'available' => ($remaining > 0),
            'remaining' => $remaining
        ];
    }

    public function getAvailableTimeSlots($doctor_id, $date) {
        $allSlots = ['09:00 AM', '09:30 AM', '10:00 AM', '10:30 AM', '11:00 AM', '11:30 AM', 
                     '01:00 PM', '01:30 PM', '02:00 PM', '02:30 PM', '03:00 PM', '03:30 PM', '04:00 PM'];
        return $allSlots;
    }

    public function createAppointment($patient_id, $doctor_id, $datetime, $lab_req) {
        $stmt = $this->pdo->prepare("
            INSERT INTO appointments (patient_id, doctor_id, appointment_date, status, lab_required)
            VALUES (?, ?, ?, 'Pending', ?)
        ");
        return $stmt->execute([$patient_id, $doctor_id, $datetime, $lab_req ? 'Yes' : 'No']);
    }

    // ===== ADD THIS METHOD =====
    public function getAppointmentsByPatient($patient_id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, d.doctor_name 
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.patient_id = ? 
            ORDER BY a.appointment_date DESC
        ");
        $stmt->execute([$patient_id]);
        return $stmt->fetchAll();
    }
}
?>