<?php
require_once 'Database.php';

class Patient {
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM patients ORDER BY date_registered DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($fullname, $age, $gender, $address, $contact) {
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare("INSERT INTO patients (fullname, age, gender, address, contact_number) VALUES (?,?,?,?,?)");
        $stmt->execute([$fullname, $age, $gender, $address, $contact]);
        $patient_id = $this->pdo->lastInsertId();
        $stmt2 = $this->pdo->prepare("INSERT INTO payments (patient_id, consultation_fee, total_amount) VALUES (?, 500, 500)");
        $stmt2->execute([$patient_id]);
        $this->pdo->commit();
        return $patient_id;
    }

    public function exportToXML() {
        $patients = $this->getAll();
        $dom = new DOMDocument("1.0", "UTF-8");
        $dom->formatOutput = true;
        $root = $dom->createElement("patients");
        $dom->appendChild($root);
        foreach ($patients as $p) {
            $patientNode = $dom->createElement("patient");
            foreach (['patient_id', 'fullname', 'age', 'gender', 'address', 'contact_number'] as $field) {
                $value = $p[$field] ?? '';
                $node = $dom->createElement($field, htmlspecialchars($value));
                $patientNode->appendChild($node);
            }
            $root->appendChild($patientNode);
        }
        return $dom->saveXML();
    }

    public function importFromXML($filePath) {
        $dom = new DOMDocument();
        if (!$dom->load($filePath)) return 0;
        $patients = $dom->getElementsByTagName("patient");
        $count = 0;
        foreach ($patients as $patientNode) {
            $fullname = $patientNode->getElementsByTagName("fullname")->item(0)->nodeValue ?? '';
            $age = $patientNode->getElementsByTagName("age")->item(0)->nodeValue ?? 0;
            $gender = $patientNode->getElementsByTagName("gender")->item(0)->nodeValue ?? '';
            $address = $patientNode->getElementsByTagName("address")->item(0)->nodeValue ?? '';
            $contact = $patientNode->getElementsByTagName("contact_number")->item(0)->nodeValue ?? '';
            if ($fullname && $age && $gender) {
                $this->create($fullname, $age, $gender, $address, $contact);
                $count++;
            }
        }
        return $count;
    }
}
?>