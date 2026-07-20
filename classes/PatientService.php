<?php

class PatientService {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // FIXED: Cast limit to int and use direct query to avoid binding issue
    public function getAll($limit = 10) {
        $limit = (int)$limit;
        $stmt = $this->pdo->query("SELECT * FROM patients ORDER BY patient_id DESC LIMIT $limit");
        return $stmt->fetchAll();
    }

    public function getPatientName($id) {
        $stmt = $this->pdo->prepare("SELECT fullname FROM patients WHERE patient_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
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
        $stmt = $this->pdo->prepare("DELETE FROM patients WHERE patient_id = ?");
        return $stmt->execute([$id]);
    }

    public function getArchived() {
        $stmt = $this->pdo->prepare("SELECT * FROM patients WHERE is_archived = 1 ORDER BY patient_id DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getFullRecord($patient_id) {
        $patient = $this->getById($patient_id);
        if (!$patient) return false;

        $apptService = new AppointmentService();
        $labService = new LabService();
        $medService = new MedicineService();

        $appointments = $apptService->getByPatient($patient_id);
        $lab_records = $labService->getByPatient($patient_id);
        $medicines = $medService->getByPatient($patient_id);

        $payment = $this->getPayment($patient_id);

        $consult_done = false;
        foreach ($appointments as $a) {
            if ($a['status'] == 'Completed') { $consult_done = true; break; }
        }
        $lab_done = false;
        foreach ($lab_records as $l) {
            if ($l['status'] == 'Completed') { $lab_done = true; break; }
        }
        $med_done = false;
        foreach ($medicines as $m) {
            if ($m['status'] == 'Taken') { $med_done = true; break; }
        }
        $pay_done = ($payment && $payment['balance'] <= 0);
        $cleared = $consult_done && $lab_done && $med_done && $pay_done;

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

    private function getPayment($patient_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                total_amount, 
                amount_paid, 
                (total_amount - amount_paid) AS balance 
            FROM payments 
            WHERE patient_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$patient_id]);
        $pay = $stmt->fetch();
        if (!$pay) {
            return ['total_amount' => 0, 'amount_paid' => 0, 'balance' => 0];
        }
        return $pay;
    }
    
    public function getAppointmentHistory($patient_id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, d.doctor_name, d.specialization
            FROM appointments a
            LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC
        ");
        $stmt->execute([$patient_id]);
        return $stmt->fetchAll();
    }
}