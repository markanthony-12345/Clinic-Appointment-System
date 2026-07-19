<?php
class AppointmentService {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Check if a doctor is available on a given date
     */
    public function checkAvailability($doctor_id, $date) {
        $stmt = $this->pdo->prepare("
            SELECT max_patients FROM doctors WHERE doctor_id = ?
        ");
        $stmt->execute([$doctor_id]);
        $max = $stmt->fetchColumn();

        if (!$max) {
            return ['available' => false, 'reason' => 'Doctor not found'];
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM appointments 
            WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status != 'Cancelled'
        ");
        $stmt->execute([$doctor_id, $date]);
        $count = $stmt->fetchColumn();

        $remaining = $max - $count;
        return [
            'available' => $remaining > 0,
            'remaining' => $remaining
        ];
    }

    /**
     * Get available time slots for a doctor on a given date
     */
    public function getAvailableTimeSlots($doctor_id, $date) {
        // Generate all possible slots (9:00 AM – 5:00 PM, 30-min intervals)
        $slots = [];
        $start = strtotime('09:00');
        $end = strtotime('17:00');
        while ($start < $end) {
            $slots[] = date('g:i A', $start);
            $start = strtotime('+30 minutes', $start);
        }

        // Get already booked slots
        $stmt = $this->pdo->prepare("
            SELECT TIME(appointment_date) as t 
            FROM appointments 
            WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status != 'Cancelled'
        ");
        $stmt->execute([$doctor_id, $date]);
        $booked = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        // Convert booked times to 'g:i A' format
        $bookedFormatted = array_map(function($t) {
            return date('g:i A', strtotime($t));
        }, $booked);

        // Return available slots
        return array_values(array_diff($slots, $bookedFormatted));
    }

    /**
     * Create a new appointment (UPDATED: accepts lab and medicine totals)
     */
    public function createAppointment($patient_id, $doctor_id, $datetime, $lab_req, $lab_tests, $lab_fee_total, $medications, $med_fee_total) {
        $stmt = $this->pdo->prepare("
            INSERT INTO appointments (
                patient_id, doctor_id, appointment_date, lab_required, 
                lab_tests, lab_fee_total, medications, med_fee_total, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        return $stmt->execute([
            $patient_id,
            $doctor_id,
            $datetime,
            $lab_req ? 'Yes' : 'No',
            $lab_tests,
            $lab_fee_total,
            $medications,
            $med_fee_total
        ]);
    }

    /**
     * Update appointment status
     */
    public function updateStatus($appointment_id, $status) {
        $stmt = $this->pdo->prepare("
            UPDATE appointments SET status = ? WHERE appointment_id = ?
        ");
        return $stmt->execute([$status, $appointment_id]);
    }

    /**
     * Get appointment by ID
     */
    public function getById($appointment_id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, p.fullname AS patient_name, d.doctor_name 
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.patient_id
            LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.appointment_id = ?
        ");
        $stmt->execute([$appointment_id]);
        return $stmt->fetch();
    }

    /**
     * Get appointments for a patient
     */
    public function getByPatient($patient_id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, d.doctor_name 
            FROM appointments a
            LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC
        ");
        $stmt->execute([$patient_id]);
        return $stmt->fetchAll();
    }

    /**
     * Cancel an appointment
     */
    public function cancelAppointment($appointment_id, $reason = '') {
        $stmt = $this->pdo->prepare("
            UPDATE appointments 
            SET status = 'Cancelled', cancellation_reason = ? 
            WHERE appointment_id = ?
        ");
        return $stmt->execute([$reason, $appointment_id]);
    }
}
?>