<?php
require_once 'Database.php';

class AppointmentService {
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    // Check doctor availability on a given date (day + max patients)
    public function checkAvailability($doctor_id, $date) {
        // 1. Day-of-week check
        if (!$this->doctorWorksOnDay($doctor_id, $date)) {
            return [
                'available' => false,
                'remaining' => 0,
                'reason' => 'Doctor does not work on ' . date('l', strtotime($date))
            ];
        }
        // 2. Max patients
        $stmt = $this->pdo->prepare("SELECT max_patients FROM doctors WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $max = $stmt->fetchColumn();
        if (!$max) $max = 999; // no limit

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status != 'Cancelled'");
        $stmt->execute([$doctor_id, $date]);
        $current = $stmt->fetchColumn();

        $available = ($current < $max);
        return [
            'available' => $available,
            'remaining' => $max - $current,
            'max_patients' => $max,
            'current_count' => $current,
            'reason' => $available ? 'Available' : 'Max patients reached'
        ];
    }

    private function doctorWorksOnDay($doctor_id, $date) {
        $stmt = $this->pdo->prepare("SELECT schedule FROM doctors WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $schedule = $stmt->fetchColumn();
        if (!$schedule) return true;

        $dayOfWeek = date('l', strtotime($date));
        $scheduleLower = strtolower($schedule);
        $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $workingDays = [];
        foreach ($days as $day) {
            if (strpos($scheduleLower, $day) !== false) {
                $workingDays[] = ucfirst($day);
            }
        }
        if (!empty($workingDays)) {
            return in_array($dayOfWeek, $workingDays);
        }
        // Range like "Monday – Wednesday"
        if (preg_match('/([A-Za-z]+)\s*[–-]\s*([A-Za-z]+)/i', $schedule, $matches)) {
            $start = ucfirst(strtolower($matches[1]));
            $end   = ucfirst(strtolower($matches[2]));
            $daysOfWeek = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            $startIndex = array_search($start, $daysOfWeek);
            $endIndex   = array_search($end, $daysOfWeek);
            if ($startIndex !== false && $endIndex !== false) {
                if ($startIndex <= $endIndex) {
                    $range = array_slice($daysOfWeek, $startIndex, $endIndex - $startIndex + 1);
                } else {
                    $range = array_merge(array_slice($daysOfWeek, $startIndex), array_slice($daysOfWeek, 0, $endIndex + 1));
                }
                return in_array($dayOfWeek, $range);
            }
        }
        return true;
    }

    public function getAvailableTimeSlots($doctor_id, $date) {
        $allSlots = [
            '09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM',
            '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM'
        ];
        $map = [
            '09:00 AM' => '09:00:00', '10:00 AM' => '10:00:00', '11:00 AM' => '11:00:00',
            '12:00 PM' => '12:00:00', '01:00 PM' => '13:00:00', '02:00 PM' => '14:00:00',
            '03:00 PM' => '15:00:00', '04:00 PM' => '16:00:00', '05:00 PM' => '17:00:00'
        ];
        $stmt = $this->pdo->prepare("SELECT TIME(appointment_date) as t FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status NOT IN ('Cancelled','Completed')");
        $stmt->execute([$doctor_id, $date]);
        $booked = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $available = [];
        foreach ($allSlots as $slot) {
            if (!in_array($map[$slot], $booked)) {
                $available[] = $slot;
            }
        }
        return $available;
    }

    public function createAppointment($patient_id, $doctor_id, $datetime, $lab_required = false) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, status) VALUES (?, ?, ?, 'Pending')");
            $stmt->execute([$patient_id, $doctor_id, $datetime]);
            if ($lab_required) {
                $stmt2 = $this->pdo->prepare("INSERT INTO laboratory (patient_id, laboratory_type, status) VALUES (?, 'From Appointment', 'Not Yet Taken')");
                $stmt2->execute([$patient_id]);
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Appointment creation error: " . $e->getMessage());
            return false;
        }
    }

    public function cancelAppointment($appointment_id) {
        $stmt = $this->pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?");
        return $stmt->execute([$appointment_id]);
    }

    public function completeAppointment($appointment_id) {
        $stmt = $this->pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE appointment_id = ? AND status != 'Cancelled'");
        return $stmt->execute([$appointment_id]);
    }

    public function getAppointmentsByPatient($patient_id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, d.doctor_name, d.specialization 
            FROM appointments a 
            JOIN doctors d ON a.doctor_id = d.doctor_id 
            WHERE a.patient_id = ? 
            ORDER BY a.appointment_date DESC
        ");
        $stmt->execute([$patient_id]);
        return $stmt->fetchAll();
    }

    public function getAppointmentsByDoctor($doctor_id, $date = null) {
        $sql = "SELECT a.*, p.fullname FROM appointments a JOIN patients p ON a.patient_id = p.patient_id WHERE a.doctor_id = ?";
        $params = [$doctor_id];
        if ($date) {
            $sql .= " AND DATE(a.appointment_date) = ?";
            $params[] = $date;
        }
        $sql .= " ORDER BY a.appointment_date ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
?>