<?php
require_once 'Database.php';

class MedicineService {
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function getAllWithPatient() {
        $stmt = $this->pdo->query("
            SELECT m.*, p.fullname 
            FROM medicines m 
            JOIN patients p ON m.patient_id = p.patient_id 
            ORDER BY m.medicine_id DESC
        ");
        return $stmt->fetchAll();
    }

    public function getByPatient($patient_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM medicines WHERE patient_id = ? ORDER BY prescription_date DESC");
        $stmt->execute([$patient_id]);
        return $stmt->fetchAll();
    }

    public function create($patient_id, $name, $dosage, $frequency, $duration, $status = 'Not Taken') {
        $stmt = $this->pdo->prepare("INSERT INTO medicines (patient_id, medicine_name, dosage, frequency, duration, status) VALUES (?,?,?,?,?,?)");
        return $stmt->execute([$patient_id, $name, $dosage, $frequency, $duration, $status]);
    }

    public function updateStatus($medicine_id, $status) {
        $stmt = $this->pdo->prepare("UPDATE medicines SET status = ? WHERE medicine_id = ?");
        return $stmt->execute([$status, $medicine_id]);
    }

    public function delete($medicine_id) {
        $stmt = $this->pdo->prepare("DELETE FROM medicines WHERE medicine_id = ?");
        return $stmt->execute([$medicine_id]);
    }
}
?>