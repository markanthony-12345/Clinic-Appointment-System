<?php
require_once 'config.php';
requireLogin();

if ($_SESSION["user_logged"]["role"] !== "Admin") {
    header("Location: dashboard.php?error=access_denied");
    exit;
}

$medicineService = new MedicineService();
$patientService = new PatientService();
$recommendationService = new RecommendationService();

$doctors = $pdo->query("SELECT doctor_id, doctor_name, specialization FROM doctors ORDER BY doctor_name")->fetchAll();
$medicines = $medicineService->getAllWithPatient();

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prescription'])) {
    $patient_id = (int)$_POST['patient_id'];
    $selected_meds = $_POST['selected_meds'] ?? [];

    if ($patient_id <= 0) {
        $error = "Please select a valid patient.";
    } elseif (empty($selected_meds)) {
        $error = "No medicines were selected. Please check at least one medication.";
    } else {
        $count = 0;
        foreach ($selected_meds as $med_json) {
            $med = json_decode($med_json, true);
            if (!$med) continue;
            $name = trim($med['name'] ?? '');
            $dosage = trim($med['dosage'] ?? '');
            $frequency = trim($med['frequency'] ?? '');
            $duration = trim($med['duration'] ?? '');
            if (empty($name)) continue;
            $stmt = $pdo->prepare("
                INSERT INTO medicines (patient_id, medicine_name, dosage, frequency, duration, status)
                VALUES (?, ?, ?, ?, ?, 'Not Taken')
            ");
            $stmt->execute([$patient_id, $name, $dosage, $frequency, $duration]);
            $count++;
        }
        if ($count > 0) {
            $success = "$count medicine(s) prescribed successfully.";
            $medicines = $medicineService->getAllWithPatient();
        } else {
            $error = "No valid medicines were found in the selection.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #f0f4f8; }
        .card { border-radius: 1.25rem; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .card-header { background: white; border-bottom: 1px solid #eef2f6; border-radius: 1.25rem 1.25rem 0 0 !important; font-weight: 600; color: #1e4a6e; padding: 1rem 1.25rem; }
        .btn-primary { background: linear-gradient(135deg, #1e6f9f, #155d85); border: none; border-radius: 2rem; }
        .btn-primary:hover { background: linear-gradient(135deg, #155d85, #0f4a6e); }
        .btn-outline-primary { border-radius: 2rem; }
        .form-label { font-weight: 500; color: #2c5f8a; font-size: 0.9rem; }
        .badge-status { padding: 0.35rem 0.75rem; border-radius: 2rem; font-weight: 500; }
        .badge-status.taken { background: #e0f2e9; color: #1e6f3f; }
        .badge-status.not-taken { background: #f0f0f0; color: #5b7f9c; }
        .btn-sm { border-radius: 2rem; padding: 0.2rem 0.8rem; }
        .medicine-name { font-weight: 500; }
        .dosage-info { font-size: 0.85rem; color: #5b7f9c; }
        #suggestions-panel { display: none; }
        .suggestion-item {
            padding: 0.4rem 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .suggestion-item:last-child { border-bottom: none; }
        .suggestion-item .form-check { margin-bottom: 0; }
        .suggestion-item .form-check-input { margin-top: 0.15rem; }
        .suggestion-item .med-details {
            font-size: 0.85rem;
            color: #6b7280;
        }
        .suggestion-item .med-details span { margin-right: 0.5rem; }
        .custom-container { display: none; margin-top: 0.5rem; }
        .custom-container.show { display: flex; }
        .table th { font-weight: 600; color: #4a6f8c; border-bottom: 2px solid #e2e8f0; }
        .select-all-row { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-top: 0.75rem; }
        .note-text { color: #6c757d; font-size: 0.85rem; font-style: italic; margin-top: 0.5rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <h1 class="h3"><i class="fas fa-pills me-2"></i>Medicines</h1>
                <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Prescribe Medicine Form -->
            <div class="card">
                <div class="card-header"><i class="fas fa-prescription me-2"></i>Prescribe Medicines</div>
                <div class="card-body">
                    <form method="POST" id="prescriptionForm">
                        <input type="hidden" name="save_prescription" value="1">

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Patient ID</label>
                                <input type="number" name="patient_id" id="patient_id_input" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Patient Name</label>
                                <input type="text" id="patient_name_display" class="form-control" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Select Doctor (for suggestions)</label>
                                <select name="doctor_id" id="doctor_select" class="form-select">
                                    <option value="">Select Doctor</option>
                                    <?php foreach ($doctors as $d): ?>
                                        <option value="<?= $d['doctor_id'] ?>" data-specialty="<?= htmlspecialchars($d['specialization']) ?>">
                                            <?= htmlspecialchars($d['doctor_name']) ?> (<?= htmlspecialchars($d['specialization']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Suggestions Panel -->
                        <div id="suggestions-panel" class="mt-3">
                            <div class="card">
                                <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-list-ul me-2 text-primary"></i>Suggested Medications</span>
                                    <span id="suggestion-note" class="text-muted small"></span>
                                </div>
                                <div class="card-body">
                                    <div id="suggestion-list">
                                        <!-- dynamically filled -->
                                    </div>
                                    <div class="select-all-row">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="select-all-meds"><i class="fas fa-check-double me-1"></i>Select All</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-all-meds"><i class="fas fa-times me-1"></i>Clear All</button>
                                        <span class="mx-2 text-muted">|</span>
                                        <button type="button" class="btn btn-sm btn-secondary" id="add-custom-medicine"><i class="fas fa-plus me-1"></i>Add Custom Medication</button>
                                        <div id="custom-container" class="custom-container">
                                            <div class="input-group input-group-sm" style="width:280px;">
                                                <input type="text" id="custom-medicine-input" class="form-control" placeholder="Enter custom medicine name">
                                                <button class="btn btn-primary" type="button" id="add-custom-btn"><i class="fas fa-plus"></i></button>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary ms-auto" id="save-prescription-btn"><i class="fas fa-save me-1"></i>Save Prescription</button>
                                    </div>
                                    <small class="text-muted mt-2 d-block"><i class="fas fa-info-circle me-1"></i>Check the medicines you want to prescribe, then click Save Prescription.</small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Medicine Records Table -->
            <div class="card mt-4">
                <div class="card-header"><i class="fas fa-table me-2"></i>Medicine Records</div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Medicine</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicines as $m): ?>
                                <?php
                                $fullname = htmlspecialchars($m['fullname'] ?? '');
                                $medicine_name = htmlspecialchars($m['medicine_name'] ?? '');
                                $dosage = htmlspecialchars($m['dosage'] ?? '');
                                $frequency = htmlspecialchars($m['frequency'] ?? '');
                                $duration = htmlspecialchars($m['duration'] ?? '');
                                $status = $m['status'] ?? 'Not Taken';
                                $statusClass = strtolower(str_replace(' ', '-', $status));
                                $isTaken = ($status === 'Taken');
                                ?>
                                <tr id="row-<?= $m['medicine_id'] ?>">
                                    <td><strong><?= $fullname ?></strong></td>
                                    <td><span class="medicine-name"><?= $medicine_name ?></span></td>
                                    <td class="dosage-info"><?= $dosage ?></td>
                                    <td class="dosage-info"><?= $frequency ?></td>
                                    <td class="dosage-info"><?= $duration ?></td>
                                    <td>
                                        <span class="badge-status <?= $statusClass ?>" id="status-<?= $m['medicine_id'] ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-success" 
                                                id="btn-<?= $m['medicine_id'] ?>" 
                                                onclick="markTaken(<?= $m['medicine_id'] ?>)" 
                                                <?= $isTaken ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                                            <i class="fas fa-check me-1"></i>Mark Taken
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteMedicine(<?= $m['medicine_id'] ?>, '<?= addslashes($medicine_name) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill patient name
        document.getElementById('patient_id_input')?.addEventListener('blur', function() {
            const pid = this.value.trim();
            if (pid) {
                fetch(`billing_ajax.php?action=get_patient_info&patient_id=${pid}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('patient_name_display').value = data.data.patient.fullname;
                        } else {
                            document.getElementById('patient_name_display').value = 'Patient not found';
                        }
                    })
                    .catch(() => {
                        document.getElementById('patient_name_display').value = 'Error';
                    });
            } else {
                document.getElementById('patient_name_display').value = '';
            }
        });

        // Load suggestions based on doctor
        const doctorSelect = document.getElementById('doctor_select');
        const suggestionPanel = document.getElementById('suggestions-panel');
        const suggestionList = document.getElementById('suggestion-list');
        const suggestionNote = document.getElementById('suggestion-note');

        function renderSuggestionItems(items, note) {
            suggestionList.innerHTML = '';
            if (items && items.length) {
                items.forEach((item, index) => {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    const dosage = item.dosage || '';
                    const frequency = item.frequency || '';
                    const duration = item.duration || '';
                    let detailsHtml = '';
                    if (dosage) detailsHtml += `<span>${dosage}</span>`;
                    if (frequency) detailsHtml += `<span>${frequency}</span>`;
                    if (duration) detailsHtml += `<span>${duration}</span>`;
                    div.innerHTML = `
                        <div class="form-check">
                            <input class="form-check-input suggestion-checkbox" type="checkbox" 
                                   name="selected_meds[]" 
                                   value='${JSON.stringify(item)}' 
                                   id="med_${index}">
                            <label class="form-check-label" for="med_${index}">
                                <strong>${item.name}</strong>
                                ${detailsHtml ? `<span class="med-details">${detailsHtml}</span>` : ''}
                            </label>
                        </div>
                    `;
                    suggestionList.appendChild(div);
                });
            } else {
                suggestionList.innerHTML = '<p class="text-muted my-2">No suggestions for this specialty.</p>';
            }
            suggestionNote.textContent = note || '';
            suggestionPanel.style.display = 'block';
        }

        doctorSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const specialty = selectedOption.getAttribute('data-specialty');
            if (!specialty) {
                suggestionPanel.style.display = 'none';
                return;
            }
            fetch(`recommendation_ajax.php?action=get_medications&specialty=${encodeURIComponent(specialty)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderSuggestionItems(data.medications, data.note);
                    } else {
                        suggestionPanel.style.display = 'none';
                    }
                })
                .catch(err => {
                    console.error(err);
                    suggestionPanel.style.display = 'none';
                });
        });

        // Select All / Clear All
        document.getElementById('select-all-meds').addEventListener('click', function() {
            document.querySelectorAll('.suggestion-checkbox').forEach(cb => cb.checked = true);
        });
        document.getElementById('clear-all-meds').addEventListener('click', function() {
            document.querySelectorAll('.suggestion-checkbox').forEach(cb => cb.checked = false);
        });

        // Add custom medication
        document.getElementById('add-custom-medicine').addEventListener('click', function() {
            const container = document.getElementById('custom-container');
            container.classList.toggle('show');
            if (container.classList.contains('show')) {
                document.getElementById('custom-medicine-input').focus();
            }
        });

        document.getElementById('add-custom-btn').addEventListener('click', function() {
            const input = document.getElementById('custom-medicine-input');
            const name = input.value.trim();
            if (!name) {
                alert('Please enter a medicine name.');
                return;
            }
            // Add a custom item to the suggestions list
            const div = document.createElement('div');
            div.className = 'suggestion-item';
            const id = 'custom_' + Date.now();
            const item = { name: name, dosage: '', frequency: '', duration: '' };
            div.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input suggestion-checkbox" type="checkbox" 
                           name="selected_meds[]" 
                           value='${JSON.stringify(item)}' 
                           id="${id}" checked>
                    <label class="form-check-label" for="${id}">
                        <strong>${name}</strong>
                        <span class="med-details"><span class="text-muted">(Custom)</span></span>
                    </label>
                </div>
                <button type="button" class="btn btn-sm btn-danger remove-custom" style="float:right;"><i class="fas fa-times"></i></button>
            `;
            suggestionList.appendChild(div);
            div.querySelector('.remove-custom').addEventListener('click', function() {
                div.remove();
            });
            input.value = '';
            document.getElementById('custom-container').classList.remove('show');
        });

        // Enter key on custom input triggers add
        document.getElementById('custom-medicine-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('add-custom-btn').click();
            }
        });

        // On form submit, check if at least one checkbox is checked
        document.getElementById('prescriptionForm').addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('.suggestion-checkbox:checked');
            if (!checkboxes.length) {
                e.preventDefault();
                alert('Please select at least one medication to prescribe.');
                return false;
            }
            return true;
        });

        // Delete medicine
        function deleteMedicine(id, name) {
            if (!confirm(`Delete medicine "${name}"? This cannot be undone.`)) return;
            fetch(`delete_medicine.php?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('row-' + id).remove();
                    } else {
                        alert('Failed: ' + data.message);
                    }
                })
                .catch(() => alert('Network error.'));
        }

        // Mark as taken
        function markTaken(id) {
            if (!confirm('Mark this medicine as Taken?')) return;
            fetch(`update_medicine_status.php?id=${id}&status=Taken`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const span = document.getElementById('status-' + id);
                    span.textContent = 'Taken';
                    span.className = 'badge-status taken';
                    const btn = document.getElementById('btn-' + id);
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                } else {
                    alert('Failed to update status.');
                }
            })
            .catch(() => alert('Network error.'));
        }
    </script>
</body>
</html>