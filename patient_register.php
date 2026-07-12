<?php
require_once 'config.php';
requireLogin();

// Helper validation functions (unchanged)
function validateFullname($name) {
    if (!preg_match('/^[A-Za-z\s\-]+$/', $name)) {
        return "Full name must contain only letters, spaces, and hyphens.";
    }
    if (str_word_count($name) < 2) {
        return "Please enter your full name (first and last name).";
    }
    return true;
}

function validateAge($age) {
    if (!is_numeric($age) || $age < 0 || $age > 150) {
        return "Age must be a number between 0 and 150.";
    }
    return true;
}

function validateContact($contact) {
    $digits = preg_replace('/\D/', '', $contact);
    if (strlen($digits) !== 11) {
        return "Contact number must be exactly 11 digits (e.g., 09123456789).";
    }
    return true;
}

function validateEmail($email) {
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email address.";
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $civil_status = sanitize($_POST['civil_status'] ?? '');
    $citizenship = sanitize($_POST['citizenship'] ?? '');
    $place_of_birth = sanitize($_POST['place_of_birth'] ?? '');
    $terms = isset($_POST['terms']) ? true : false;

    $errors = [];
    $fullnameCheck = validateFullname($fullname);
    if ($fullnameCheck !== true) $errors[] = $fullnameCheck;
    $ageCheck = validateAge($age);
    if ($ageCheck !== true) $errors[] = $ageCheck;
    $contactCheck = validateContact($contact);
    if ($contactCheck !== true) $errors[] = $contactCheck;
    $emailCheck = validateEmail($email);
    if ($emailCheck !== true) $errors[] = $emailCheck;
    if (!$terms) $errors[] = "You must agree to the terms and conditions.";
    if (empty($gender)) $errors[] = "Gender is required.";
    if (empty($address)) $errors[] = "Address is required.";

    if (empty($errors)) {
        $contact_clean = preg_replace('/\D/', '', $contact);
        try {
            $pdo->beginTransaction();

            // Insert patient
            $stmt = $pdo->prepare("
                INSERT INTO patients (fullname, age, gender, address, contact_number, email, civil_status, citizenship, place_of_birth) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $fullname, $age, $gender, $address, $contact_clean,
                $email, $civil_status, $citizenship, $place_of_birth
            ]);
            $patient_id = $pdo->lastInsertId();

            // ✅ Insert payment record with zero fees (no initial charge)
            $stmt2 = $pdo->prepare("
                INSERT INTO payments (patient_id, consultation_fee, laboratory_fee, amount_paid, total_amount) 
                VALUES (?, 0, 0, 0, 0)
            ");
            $stmt2->execute([$patient_id]);

            $pdo->commit();
            header("Location: dashboard.php?success=Patient registered successfully");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Database error: " . $e->getMessage();
        }
    } else {
        $error_msg = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Patient</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1rem; border: none; }
        .form-label { font-weight: 500; color: #2c5f8a; }
        .btn-primary { background: #1e6f9f; border-color: #1e6f9f; border-radius: 2rem; }
        .btn-primary:hover { background: #155d85; }
        .form-text { font-size: 0.8rem; color: #6c757d; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container py-4">
            <header class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-user-plus me-2"></i>Register Patient</h1>
                <a href="dashboard.php" class="btn btn-outline-primary">← Back</a>
            </header>
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <?php if (isset($error_msg)): ?>
                                <div class="alert alert-danger"><?= $error_msg ?></div>
                            <?php endif; ?>
                            <form method="POST" id="registerForm">
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="fullname" class="form-control" required pattern="[A-Za-z\s\-]+" title="Only letters, spaces, and hyphens allowed.">
                                    <div class="form-text">At least first and last name, letters only.</div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Age *</label>
                                        <input type="number" name="age" class="form-control" required min="0" max="150">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender *</label>
                                        <select name="gender" class="form-select" required>
                                            <option value="">Select...</option>
                                            <option>Male</option>
                                            <option>Female</option>
                                            <option>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Address *</label>
                                    <textarea name="address" class="form-control" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contact Number *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">+63</span>
                                        <input type="tel" name="contact_number" class="form-control" required pattern="[0-9]{11}" title="Exactly 11 digits (e.g., 09123456789)" placeholder="09123456789">
                                    </div>
                                    <div class="form-text">Exactly 11 digits (no +63).</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Civil Status</label>
                                    <select name="civil_status" class="form-select">
                                        <option value="">Select...</option>
                                        <option>Single</option>
                                        <option>Married</option>
                                        <option>Divorced</option>
                                        <option>Widowed</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Citizenship</label>
                                    <input type="text" name="citizenship" class="form-control" placeholder="Filipino">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Place of Birth</label>
                                    <input type="text" name="place_of_birth" class="form-control" placeholder="City, Province">
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                    <label class="form-check-label" for="terms">I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>.</label>
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Register Patient</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5>Terms and Conditions</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>By registering, you agree to the clinic's policies regarding patient data privacy, appointment scheduling, and payment terms.</p>
                    <p>All information provided will be kept confidential and used solely for medical and administrative purposes.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const contact = document.querySelector('input[name="contact_number"]');
            const digits = contact.value.replace(/\D/g, '');
            if (digits.length !== 11) {
                alert('Contact number must be exactly 11 digits.');
                e.preventDefault();
                return false;
            }
            return true;
        });
    </script>
</body>
</html> 