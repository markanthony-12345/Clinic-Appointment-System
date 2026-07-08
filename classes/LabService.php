<?php
require_once 'Database.php';

class LabService {
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function getAllWithPatient() {
        $stmt = $this->pdo->query("
            SELECT l.*, p.fullname 
            FROM laboratory l 
            JOIN patients p ON l.patient_id = p.patient_id 
            ORDER BY l.lab_id DESC
        ");
        return $stmt->fetchAll();
    }

    public function getByPatient($patient_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM laboratory WHERE patient_id = ? ORDER BY created_at DESC");
        $stmt->execute([$patient_id]);
        return $stmt->fetchAll();
    }

    public function create($patient_id, $type, $status = 'Not Yet Taken', $result = null) {
        $stmt = $this->pdo->prepare("INSERT INTO laboratory (patient_id, laboratory_type, status, result) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$patient_id, $type, $status, $result]);
    }

    public function update($lab_id, $patient_id, $type, $status, $result) {
        $stmt = $this->pdo->prepare("UPDATE laboratory SET patient_id=?, laboratory_type=?, status=?, result=? WHERE lab_id=?");
        return $stmt->execute([$patient_id, $type, $status, $result, $lab_id]);
    }

    public function delete($lab_id) {
        $stmt = $this->pdo->prepare("DELETE FROM laboratory WHERE lab_id = ?");
        return $stmt->execute([$lab_id]);
    }

    public function getStats() {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM laboratory GROUP BY status");
        return $stmt->fetchAll();
    }
}
?>