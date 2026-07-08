<?php
require_once 'Database.php';

class PatientService {
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function getAll($limit = 10) {
        $stmt = $this->pdo->query("SELECT * FROM patients ORDER BY date_registered DESC LIMIT $limit");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getPatientName($id) {
        $stmt = $this->pdo->prepare("SELECT fullname FROM patients WHERE patient_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    public function create($fullname, $age, $gender, $address, $contact) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO patients (fullname, age, gender, address, contact_number) VALUES (?,?,?,?,?)");
            $stmt->execute([$fullname, $age, $gender, $address, $contact]);
            $pid = $this->pdo->lastInsertId();
            // Create payment record with default consultation fee 500
            $stmt2 = $this->pdo->prepare("INSERT INTO payments (patient_id, consultation_fee, total_amount) VALUES (?, 500, 500)");
            $stmt2->execute([$pid]);
            $this->pdo->commit();
            return $pid;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update($id, $fullname, $age, $gender, $address, $contact) {
        $stmt = $this->pdo->prepare("UPDATE patients SET fullname=?, age=?, gender=?, address=?, contact_number=? WHERE patient_id=?");
        return $stmt->execute([$fullname, $age, $gender, $address, $contact, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM patients WHERE patient_id = ?");
        return $stmt->execute([$id]);
    }

    // Get full patient record including clearance status
    public function getFullRecord($patient_id) {
        $patient = $this->getById($patient_id);
        if (!$patient) return null;

        // Payment
        $stmt = $this->pdo->prepare("SELECT total_amount, amount_paid, (total_amount - amount_paid) AS balance FROM payments WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $payment = $stmt->fetch();
        if (!$payment) {
            $payment = ['total_amount' => 500, 'amount_paid' => 0, 'balance' => 500];
        }

        // Clearance checks
        $consult = $this->pdo->prepare("SELECT 1 FROM appointments WHERE patient_id = ? AND status = 'Completed' LIMIT 1");
        $consult->execute([$patient_id]);
        $consult_done = $consult->rowCount() > 0;

        $lab = $this->pdo->prepare("SELECT 1 FROM laboratory WHERE patient_id = ? AND status = 'Completed' LIMIT 1");
        $lab->execute([$patient_id]);
        $lab_done = $lab->rowCount() > 0;

        $med = $this->pdo->prepare("SELECT 1 FROM medicines WHERE patient_id = ? AND status = 'Taken' LIMIT 1");
        $med->execute([$patient_id]);
        $med_done = $med->rowCount() > 0;

        $pay_done = ($payment['balance'] <= 0);
        $cleared = $consult_done && $lab_done && $med_done && $pay_done;

        // Appointments
        $apptService = new AppointmentService();
        $appointments = $apptService->getAppointmentsByPatient($patient_id);

        // Medicines
        $medService = new MedicineService();
        $medicines = $medService->getByPatient($patient_id);

        // Lab
        $labService = new LabService();
        $lab_records = $labService->getByPatient($patient_id);

        return [
            'patient' => $patient,
            'payment' => $payment,
            'consult_done' => $consult_done,
            'lab_done' => $lab_done,
            'med_done' => $med_done,
            'pay_done' => $pay_done,
            'cleared' => $cleared,
            'appointments' => $appointments,
            'medicines' => $medicines,
            'lab_records' => $lab_records
        ];
    }
}
?>