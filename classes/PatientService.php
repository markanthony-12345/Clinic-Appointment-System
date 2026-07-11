<?php
require_once 'Database.php';

class PatientService {
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function getAll($limit = 10) {
        $limit = (int)$limit;
        $stmt = $this->pdo->query("
            SELECT 
                p.*, 
                COALESCE(py.total_amount, 0) AS total_amount, 
                COALESCE(py.amount_paid, 0) AS amount_paid
            FROM patients p 
            LEFT JOIN payments py ON p.patient_id = py.patient_id
            WHERE p.is_archived = 0 
            ORDER BY p.date_registered DESC 
            LIMIT $limit
        ");
        return $stmt->fetchAll();
    }

    public function getAllIncludingArchived($limit = 10) {
        $limit = (int)$limit;
        $stmt = $this->pdo->query("
            SELECT 
                p.*, 
                COALESCE(py.total_amount, 0) AS total_amount, 
                COALESCE(py.amount_paid, 0) AS amount_paid
            FROM patients p 
            LEFT JOIN payments py ON p.patient_id = py.patient_id
            ORDER BY p.is_archived ASC, p.date_registered DESC 
            LIMIT $limit
        ");
        return $stmt->fetchAll();
    }

    public function getArchived() {
        $stmt = $this->pdo->query("
            SELECT 
                p.*, 
                COALESCE(py.total_amount, 0) AS total_amount, 
                COALESCE(py.amount_paid, 0) AS amount_paid
            FROM patients p 
            LEFT JOIN payments py ON p.patient_id = py.patient_id
            WHERE p.is_archived = 1 
            ORDER BY p.date_registered DESC
        ");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.*, 
                COALESCE(py.total_amount, 0) AS total_amount, 
                COALESCE(py.amount_paid, 0) AS amount_paid
            FROM patients p 
            LEFT JOIN payments py ON p.patient_id = py.patient_id
            WHERE p.patient_id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getPatientName($id) {
        $stmt = $this->pdo->prepare("SELECT fullname FROM patients WHERE patient_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    public function create($fullname, $age, $sex, $address, $contact, $email, $civil_status, $citizenship, $place_of_birth) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO patients 
                (fullname, age, sex, address, contact_number, email, civil_status, citizenship, place_of_birth, is_archived) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$fullname, $age, $sex, $address, $contact, $email, $civil_status, $citizenship, $place_of_birth]);
            $pid = $this->pdo->lastInsertId();
            $stmt2 = $this->pdo->prepare("
                INSERT INTO payments (patient_id, consultation_fee, total_amount, amount_paid) 
                VALUES (?, 500, 500, 0)
            ");
            $stmt2->execute([$pid]);
            $this->pdo->commit();
            return $pid;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update($id, $fullname, $age, $sex, $address, $contact, $email, $civil_status, $citizenship, $place_of_birth) {
        $stmt = $this->pdo->prepare("
            UPDATE patients 
            SET fullname=?, age=?, sex=?, address=?, contact_number=?, 
                email=?, civil_status=?, citizenship=?, place_of_birth=?
            WHERE patient_id=?
        ");
        return $stmt->execute([
            $fullname, $age, $sex, $address, $contact,
            $email, $civil_status, $citizenship, $place_of_birth,
            $id
        ]);
    }

    public function archive($id) {
        $stmt = $this->pdo->prepare("UPDATE patients SET is_archived = 1 WHERE patient_id = ?");
        return $stmt->execute([$id]);
    }

    public function restore($id) {
        $stmt = $this->pdo->prepare("UPDATE patients SET is_archived = 0 WHERE patient_id = ?");
        return $stmt->execute([$id]);
    }

    public function permanentlyDelete($id) {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM appointments WHERE patient_id = ?")->execute([$id]);
            $this->pdo->prepare("DELETE FROM laboratory WHERE patient_id = ?")->execute([$id]);
            $this->pdo->prepare("DELETE FROM medicines WHERE patient_id = ?")->execute([$id]);
            $this->pdo->prepare("DELETE FROM payments WHERE patient_id = ?")->execute([$id]);
            $this->pdo->prepare("DELETE FROM patients WHERE patient_id = ?")->execute([$id]);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getFullRecord($patient_id) {
        $patient = $this->getById($patient_id);
        if (!$patient) return null;

        $stmt = $this->pdo->prepare("
            SELECT total_amount, amount_paid, (total_amount - amount_paid) AS balance 
            FROM payments WHERE patient_id = ?
        ");
        $stmt->execute([$patient_id]);
        $payment = $stmt->fetch();
        if (!$payment) {
            $payment = ['total_amount' => 500, 'amount_paid' => 0, 'balance' => 500];
        }

        $consult = $this->pdo->prepare("
            SELECT 1 FROM appointments WHERE patient_id = ? AND status = 'Completed' LIMIT 1
        ");
        $consult->execute([$patient_id]);
        $consult_done = $consult->rowCount() > 0;

        $lab = $this->pdo->prepare("
            SELECT 1 FROM laboratory WHERE patient_id = ? AND status = 'Completed' LIMIT 1
        ");
        $lab->execute([$patient_id]);
        $lab_done = $lab->rowCount() > 0;

        $med = $this->pdo->prepare("
            SELECT 1 FROM medicines WHERE patient_id = ? AND status = 'Taken' LIMIT 1
        ");
        $med->execute([$patient_id]);
        $med_done = $med->rowCount() > 0;

        $pay_done = ($payment['balance'] <= 0);
        $cleared = $consult_done && $lab_done && $med_done && $pay_done;

        $apptService = new AppointmentService();
        $appointments = $apptService->getAppointmentsByPatient($patient_id);

        $medService = new MedicineService();
        $medicines = $medService->getByPatient($patient_id);

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