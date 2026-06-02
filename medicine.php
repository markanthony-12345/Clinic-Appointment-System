<?php
require_once 'config.php';
requireLogin();
if ($_SESSION["user_logged"]["role"] !== "Admin") { header("Location: dashboard.php?error=access_denied"); exit; }

//dito fine-Fetch lahat ng medicine records
$stmt = $pdo->query("
    SELECT m.*, p.fullname 
    FROM medicines m 
    JOIN patients p ON m.patient_id = p.patient_id 
    ORDER BY m.medicine_id DESC
");
$medicines = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Medicines</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Medicines</h1>
            <a href="dashboard.php" class="btn primary">Back</a>
        </header>
        <main>
            <!-- Assign Medicine Form -->
            <div class="card">
                <h3>Assign Medicine</h3>
                <form action="medicine_process.php" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Patient ID</label>
                            <input type="number" name="patient_id" id="med_patient_id" required>
                        </div>
                        <div class="form-group">
                            <label>Patient Name</label>
                            <input type="text" id="med_patient_name" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Medicine Name</label>
                        <input type="text" name="medicine_name" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Dosage</label>
                            <input type="text" name="dosage" placeholder="500mg">
                        </div>
                        <div class="form-group">
                            <label>Frequency</label>
                            <input type="text" name="frequency" placeholder="Twice daily">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Duration</label>
                        <input type="text" name="duration" placeholder="7 days">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option>Not Taken</option>
                            <option>Taken</option>
                        </select>
                    </div>
                    <button type="submit" class="btn primary">Save</button>
                </form>
            </div>

            <!-- Medicine Records Table -->
            <div class="card">
                <h3>Medicine Records</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicines as $m): ?>
                            <tr id="row-<?= $m['medicine_id'] ?>">
                                <td><?= htmlspecialchars($m['fullname']) ?></td>
                                <td><?= htmlspecialchars($m['medicine_name']) ?></td>
                                <td><?= htmlspecialchars($m['dosage']) ?> - <?= htmlspecialchars($m['frequency']) ?></td>
                                <td>
                                    <span class="status <?= strtolower(str_replace(' ', '-', $m['status'])) ?>" id="status-<?= $m['medicine_id'] ?>">
                                        <?= htmlspecialchars($m['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn" id="btn-<?= $m['medicine_id'] ?>"
                                        onclick="markTaken(<?= $m['medicine_id'] ?>)"
                                        <?= $m['status'] === 'Taken' ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                                        ✓ Mark Taken
                                    </button>
                                    <button class="btn delete-btn" id="del-btn-<?= $m['medicine_id'] ?>"
                                        onclick="deleteMedicine(<?= $m['medicine_id'] ?>, '<?= htmlspecialchars($m['medicine_name'], ENT_QUOTES) ?>')">
                                        🗑 Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        // eto nag Auto-fill ng patient name kapag Patient ID is entered
        document.getElementById('med_patient_id')?.addEventListener('blur', function() {
            fetch(`get_patient.php?id=${this.value}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('med_patient_name').value = data.fullname;
                });
        });

        // Delete a medicine record
        function deleteMedicine(id, name) {
            if (!confirm(`Delete medicine "${name}"? This cannot be undone.`)) return;
            fetch(`delete_medicine.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const row = document.getElementById('row-' + id);
                        if (row) row.remove();
                    } else {
                        alert('Failed to delete: ' + data.message);
                    }
                })
                .catch(() => alert('Network error. Please try again.'));
        }

        // Mark medicine as taken
        function markTaken(id) {
            fetch(`update_medicine_status.php?id=${id}&status=Taken`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update status text
                    const statusSpan = document.getElementById('status-' + id);
                    statusSpan.textContent = 'Taken';
                    statusSpan.className = 'status taken';

                    // eto dini-disable ung button para hindi maclick ulit
                    const btn = document.getElementById('btn-' + id);
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                } else {
                    alert('Failed to update status. Please try again.');
                }
            })
            .catch(() => {
                alert('Network error. Please try again.');
            });
        }
    </script>
</body>
</html>