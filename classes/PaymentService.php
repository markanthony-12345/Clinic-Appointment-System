<?php
require_once 'Database.php';

class PaymentService {
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function getByPatient($patient_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        return $stmt->fetch();
    }

    public function addPayment($patient_id, $amount) {
        $stmt = $this->pdo->prepare("SELECT amount_paid, total_amount FROM payments WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $pay = $stmt->fetch();
        if (!$pay) {
            // Create default if missing
            $this->pdo->prepare("INSERT INTO payments (patient_id, consultation_fee, total_amount) VALUES (?, 500, 500)")->execute([$patient_id]);
            $pay = ['amount_paid' => 0, 'total_amount' => 500];
        }
        $new_paid = $pay['amount_paid'] + $amount;
        if ($new_paid > $pay['total_amount']) {
            $new_paid = $pay['total_amount'];
        }
        $stmt = $this->pdo->prepare("UPDATE payments SET amount_paid = ? WHERE patient_id = ?");
        return $stmt->execute([$new_paid, $patient_id]);
    }

    public function resetPayment($patient_id) {
        $stmt = $this->pdo->prepare("UPDATE payments SET amount_paid = 0 WHERE patient_id = ?");
        return $stmt->execute([$patient_id]);
    }

    public function getAllWithPatient() {
        $stmt = $this->pdo->query("
            SELECT py.*, p.fullname,
                   (py.total_amount - py.amount_paid) AS balance,
                   CASE 
                       WHEN py.amount_paid >= py.total_amount THEN 'Paid'
                       WHEN py.amount_paid > 0 THEN 'Partial'
                       ELSE 'Unpaid'
                   END AS computed_status
            FROM payments py
            JOIN patients p ON py.patient_id = p.patient_id
            ORDER BY py.payment_id DESC
        ");
        return $stmt->fetchAll();
    }
}
?>