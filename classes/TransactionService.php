<?php
class TransactionService {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function generateTransactionNumber() {
        $date = date('Ymd');
        $prefix = "TRX-$date-";
        $stmt = $this->pdo->prepare("
            SELECT MAX(CAST(SUBSTRING(transaction_number, -4) AS UNSIGNED)) AS last_seq
            FROM transactions
            WHERE transaction_number LIKE ?
        ");
        $stmt->execute([$prefix . '%']);
        $row = $stmt->fetch();
        $next = ($row['last_seq'] ?? 0) + 1;
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function createTransaction($data) {
        $consultation = (float)($data['consultation_fee'] ?? 0);
        $lab = (float)($data['lab_fee'] ?? 0);
        $medicine = (float)($data['medicine_fee'] ?? 0);
        $other = (float)($data['other_charges'] ?? 0);
        $discount = (float)($data['discount'] ?? 0);
        $total = $consultation + $lab + $medicine + $other - $discount;
        if ($total < 0) $total = 0;

        $paid = (float)($data['amount_paid'] ?? 0);
        $change = ($paid > $total) ? ($paid - $total) : 0;
        $paid = min($paid, $total);

        $status = ($paid == 0) ? 'Unpaid' : (($paid >= $total) ? 'Paid' : 'Partially Paid');

        $txnNumber = $this->generateTransactionNumber();

        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (
                transaction_number, patient_id, doctor_id, appointment_id, user_id,
                consultation_fee, lab_fee, medicine_fee, other_charges, discount,
                total_amount, amount_paid, change_amount,
                payment_method, payment_status, reference_number, notes,
                transaction_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $txnNumber,
            $data['patient_id'],
            $data['doctor_id'] ?? null,
            $data['appointment_id'] ?? null,
            $data['user_id'],
            $consultation,
            $lab,
            $medicine,
            $other,
            $discount,
            $total,
            $paid,
            $change,
            $data['payment_method'],
            $status,
            $data['reference_number'] ?? null,
            $data['notes'] ?? null,
            $data['transaction_date'] ?? date('Y-m-d H:i:s')
        ]);

        return $this->pdo->lastInsertId();
    }

    public function getTransaction($id) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, p.fullname AS patient_name, d.doctor_name, u.fullname AS cashier_name
            FROM transactions t
            LEFT JOIN patients p ON t.patient_id = p.patient_id
            LEFT JOIN doctors d ON t.doctor_id = d.doctor_id
            LEFT JOIN users u ON t.user_id = u.user_id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getTransactions($filters = []) {
        $sql = "
            SELECT t.*, p.fullname AS patient_name, d.doctor_name, u.fullname AS cashier_name
            FROM transactions t
            LEFT JOIN patients p ON t.patient_id = p.patient_id
            LEFT JOIN doctors d ON t.doctor_id = d.doctor_id
            LEFT JOIN users u ON t.user_id = u.user_id
            WHERE t.deleted_at IS NULL
        ";
        $params = [];

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND t.transaction_date BETWEEN ? AND ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
            $params[] = $filters['end_date'] . ' 23:59:59';
        }
        if (!empty($filters['patient_id'])) {
            $sql .= " AND t.patient_id = ?";
            $params[] = $filters['patient_id'];
        }
        if (!empty($filters['doctor_id'])) {
            $sql .= " AND t.doctor_id = ?";
            $params[] = $filters['doctor_id'];
        }
        if (!empty($filters['payment_status'])) {
            $sql .= " AND t.payment_status = ?";
            $params[] = $filters['payment_status'];
        }
        if (!empty($filters['payment_method'])) {
            $sql .= " AND t.payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (t.transaction_number LIKE ? OR p.fullname LIKE ? OR d.doctor_name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY t.transaction_date DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateTransaction($id, $data) {
        $txn = $this->getTransaction($id);
        $total = $txn['total_amount'];
        $paid = min((float)$data['amount_paid'], $total);
        $status = ($paid == 0) ? 'Unpaid' : (($paid >= $total) ? 'Paid' : 'Partially Paid');

        $stmt = $this->pdo->prepare("
            UPDATE transactions
            SET amount_paid = ?, payment_method = ?, reference_number = ?, notes = ?, payment_status = ?
            WHERE id = ?
        ");
        return $stmt->execute([$paid, $data['payment_method'], $data['reference_number'], $data['notes'], $status, $id]);
    }

    public function refundTransaction($id) {
        $stmt = $this->pdo->prepare("UPDATE transactions SET is_refunded = 1, payment_status = 'Refunded' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function deleteTransaction($id) {
        $stmt = $this->pdo->prepare("UPDATE transactions SET deleted_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getStats() {
        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS count, SUM(amount_paid) AS revenue
            FROM transactions
            WHERE DATE(transaction_date) = ? AND deleted_at IS NULL AND payment_status != 'Refunded'
        ");
        $stmt->execute([$today]);
        $todayStats = $stmt->fetch();

        $monthStart = date('Y-m-01');
        $stmt2 = $this->pdo->prepare("
            SELECT SUM(amount_paid) AS revenue
            FROM transactions
            WHERE transaction_date >= ? AND deleted_at IS NULL AND payment_status != 'Refunded'
        ");
        $stmt2->execute([$monthStart]);
        $monthRevenue = $stmt2->fetchColumn();

        $stmt3 = $this->pdo->prepare("
            SELECT COUNT(*) FROM transactions
            WHERE payment_status IN ('Unpaid', 'Partially Paid') AND deleted_at IS NULL
        ");
        $stmt3->execute();
        $pendingPayments = $stmt3->fetchColumn();

        $stmt4 = $this->pdo->prepare("
            SELECT COUNT(*) FROM transactions
            WHERE payment_status = 'Paid' AND deleted_at IS NULL
        ");
        $stmt4->execute();
        $paidTransactions = $stmt4->fetchColumn();

        $stmt5 = $this->pdo->prepare("
            SELECT COUNT(*) FROM transactions
            WHERE payment_status = 'Unpaid' AND deleted_at IS NULL
        ");
        $stmt5->execute();
        $unpaidTransactions = $stmt5->fetchColumn();

        return [
            'today_count' => $todayStats['count'] ?? 0,
            'today_revenue' => $todayStats['revenue'] ?? 0,
            'month_revenue' => $monthRevenue ?? 0,
            'pending_payments' => $pendingPayments ?? 0,
            'paid_transactions' => $paidTransactions ?? 0,
            'unpaid_transactions' => $unpaidTransactions ?? 0
        ];
    }

    public function getRecentTransactions($limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, p.fullname AS patient_name
            FROM transactions t
            LEFT JOIN patients p ON t.patient_id = p.patient_id
            WHERE t.deleted_at IS NULL
            ORDER BY t.transaction_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function getOutstandingBalances() {
        $stmt = $this->pdo->prepare("
            SELECT 
                t.id AS transaction_id,
                t.transaction_number,
                t.patient_id,
                p.fullname AS patient_name,
                p.age,
                p.gender,
                p.contact_number,
                t.doctor_id,
                d.doctor_name,
                t.appointment_id,
                t.consultation_fee,
                t.lab_fee,
                t.medicine_fee,
                t.other_charges,
                t.discount,
                t.total_amount,
                t.amount_paid,
                (t.total_amount - t.amount_paid) AS balance,
                t.payment_status,
                t.transaction_date,
                t.payment_method,
                t.reference_number
            FROM transactions t
            LEFT JOIN patients p ON t.patient_id = p.patient_id
            LEFT JOIN doctors d ON t.doctor_id = d.doctor_id
            WHERE t.deleted_at IS NULL
              AND t.payment_status IN ('Unpaid', 'Partially Paid')
              AND t.is_refunded = 0
            ORDER BY t.transaction_date ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPatientBillingInfo($patient_id) {
        $stmt = $this->pdo->prepare("
            SELECT patient_id, fullname, age, gender, contact_number, address, email
            FROM patients
            WHERE patient_id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch();
        if (!$patient) return null;

        $stmt = $this->pdo->prepare("
            SELECT a.appointment_id, a.appointment_date, a.status, d.doctor_name, d.doctor_id
            FROM appointments a
            LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.patient_id = ? AND a.status != 'Cancelled'
            ORDER BY a.appointment_date DESC
            LIMIT 1
        ");
        $stmt->execute([$patient_id]);
        $appointment = $stmt->fetch();

        $stmt = $this->pdo->prepare("
            SELECT 
                id AS transaction_id,
                transaction_number,
                appointment_id,
                doctor_id,
                consultation_fee,
                lab_fee,
                medicine_fee,
                other_charges,
                discount,
                total_amount,
                amount_paid,
                (total_amount - amount_paid) AS balance,
                payment_status,
                payment_method,
                reference_number,
                transaction_date,
                notes
            FROM transactions
            WHERE patient_id = ? AND deleted_at IS NULL
            ORDER BY transaction_date DESC
            LIMIT 1
        ");
        $stmt->execute([$patient_id]);
        $transaction = $stmt->fetch();

        return [
            'patient' => $patient,
            'appointment' => $appointment,
            'transaction' => $transaction
        ];
    }

    public function getPatientTransactions($patient_id) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.fullname AS processed_by_name
            FROM transactions t
            LEFT JOIN users u ON t.user_id = u.user_id
            WHERE t.patient_id = ? AND t.deleted_at IS NULL
            ORDER BY t.transaction_date DESC
        ");
        $stmt->execute([$patient_id]);
        return $stmt->fetchAll();
    }
}
?>