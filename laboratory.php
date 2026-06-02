<?php
require_once 'config.php';
requireLogin();
if ($_SESSION["user_logged"]["role"] !== "Admin") { header("Location: dashboard.php?error=access_denied"); exit; }

//dito fine-Fetch ung laboratory records
$stmt = $pdo->query("
    SELECT l.*, p.fullname 
    FROM laboratory l 
    JOIN patients p ON l.patient_id = p.patient_id 
    ORDER BY l.lab_id DESC
");
$labRecords = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laboratory</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-vial"></i> Laboratory</h1>
            <a href="dashboard.php" class="btn primary">Back</a>
        </header>
        <main>
            <!-- Assign Lab Test Form -->
            <div class="card">
                <h3>Assign Lab Test</h3>
                <form action="lab_process.php" method="POST">
                    <div class="form-group">
                        <label>Patient ID</label>
                        <input type="number" name="patient_id" id="lab_patient_id" required>
                    </div>
                    <div class="form-group">
                        <label>Patient Name</label>
                        <input type="text" id="lab_patient_name" readonly>
                    </div>
                    <div class="form-group">
                        <label>Test Type</label>
                        <select name="laboratory_type" required>
                            <option>X-ray</option>
                            <option>Ultrasound</option>
                            <option>CBC</option>
                            <option>Urinalysis</option>
                            <option>Blood Chemistry</option>
                            <option>ECG</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option>Not Yet Taken</option>
                            <option>Ongoing</option>
                            <option>Completed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Results</label>
                        <textarea name="result"></textarea>
                    </div>
                    <button type="submit" class="btn primary">Save</button>
                </form>
            </div>

            <!-- Laboratory Records Table -->
            <div class="card">
                <h3>Lab Records</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Test</th>
                            <th>Status</th>
                            <th>Result</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
<<<<<<< HEAD
                        <?php foreach ($labRecords as $row): ?>
                            <tr>
                                <td><?= $row['lab_id'] ?></td>
                                <td><?= htmlspecialchars($row['fullname']) ?></td>
                                <td><?= $row['laboratory_type'] ?></td>
                                <td>
                                    <span class="status <?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['result']) ?></td>
                                <td><?= $row['created_at'] ?></td>
                                <td>
                                    <button class="btn" onclick="editLab(<?= $row['lab_id'] ?>)">Edit</button>
                                    <button class="btn danger" onclick="deleteLab(<?= $row['lab_id'] ?>)">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
=======
    <?php foreach ($labRecords as $row): ?>
        <tr>
            <td><?= $row['lab_id'] ?></td>
            <td><?= htmlspecialchars($row['fullname'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['laboratory_type'] ?? '') ?></td>
            <td>
                <span class="status <?= strtolower(str_replace(' ', '-', $row['status'] ?? '')) ?>">
                    <?= htmlspecialchars($row['status'] ?? '') ?>
                </span>
            </td>
            <td><?= htmlspecialchars($row['result'] ?? '') ?></td>
            <td><?= $row['created_at'] ?></td>
            <td>
                <button class="btn" onclick="editLab(<?= $row['lab_id'] ?>)">Edit</button>
                <button class="btn danger" onclick="deleteLab(<?= $row['lab_id'] ?>)">Delete</button>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
>>>>>>> e47bb6c16c358686372866dd16fcde1ea2f9833b
                </table>
            </div>
        </main>
    </div>

    <script>
        // nag auauto-fill ng patient name kapag Patient ID is entered
        document.getElementById('lab_patient_id')?.addEventListener('blur', function() {
            fetch(`get_patient.php?id=${this.value}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('lab_patient_name').value = data.fullname;
                });
        });

        // nag eedit ng lab record
        function editLab(id) {
            window.location.href = `edit_lab.php?id=${id}`;
        }

        // eto nag dedelete ng lab record with confirmation
        function deleteLab(id) {
            if (confirm('Delete this record?')) {
                window.location.href = `delete_lab.php?id=${id}`;
            }
        }
    </script>
</body>
</html>