<?php
require_once 'Database.php';

class ReportService {
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function getDashboardStats() {
        $total_patients = $this->pdo->query("SELECT COUNT(*) FROM patients WHERE is_archived = 0")->fetchColumn();
        $pending_appointments = $this->pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Pending'")->fetchColumn();
        $paid_patients = $this->pdo->query("SELECT COUNT(*) FROM payments WHERE amount_paid >= total_amount")->fetchColumn();

        $cleared_patients = $this->pdo->query("
            SELECT COUNT(DISTINCT p.patient_id) FROM patients p
            WHERE p.is_archived = 0
            AND EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.patient_id AND a.status = 'Completed')
            AND EXISTS (SELECT 1 FROM laboratory l WHERE l.patient_id = p.patient_id AND l.status = 'Completed')
            AND EXISTS (SELECT 1 FROM medicines m WHERE m.patient_id = p.patient_id AND m.status = 'Taken')
            AND EXISTS (SELECT 1 FROM payments py WHERE py.patient_id = p.patient_id AND py.amount_paid >= py.total_amount)
        ")->fetchColumn();

        return [
            'total_patients' => $total_patients,
            'pending_appointments' => $pending_appointments,
            'paid_patients' => $paid_patients,
            'cleared_patients' => $cleared_patients
        ];
    }

    public function getAppointmentsByDoctor($days = 30) {
        $stmt = $this->pdo->prepare("
            SELECT d.doctor_name, COUNT(a.appointment_id) as count
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.appointment_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY d.doctor_id
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    public function getPaymentStatusBreakdown() {
        $stmt = $this->pdo->query("
            SELECT 
                SUM(CASE WHEN amount_paid >= total_amount THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN amount_paid > 0 AND amount_paid < total_amount THEN 1 ELSE 0 END) as partial,
                SUM(CASE WHEN amount_paid = 0 THEN 1 ELSE 0 END) as unpaid
            FROM payments
        ");
        return $stmt->fetch();
    }

    public function getLabStatusBreakdown() {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM laboratory GROUP BY status");
        return $stmt->fetchAll();
    }

    public function getPatientRegistrations($days = 7) {
        $stmt = $this->pdo->prepare("
            SELECT DATE(date_registered) as date, COUNT(*) as count
            FROM patients
            WHERE date_registered >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND is_archived = 0
            GROUP BY DATE(date_registered)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    public function getRecentPatients($limit = 10) {
        $limit = (int)$limit;
        $stmt = $this->pdo->query("
            SELECT p.*, COALESCE(py.total_amount,0) AS total_amount, COALESCE(py.amount_paid,0) AS amount_paid
            FROM patients p LEFT JOIN payments py ON p.patient_id = py.patient_id
            WHERE p.is_archived = 0
            ORDER BY p.date_registered DESC LIMIT $limit
        ");
        return $stmt->fetchAll();
    }
}
?>