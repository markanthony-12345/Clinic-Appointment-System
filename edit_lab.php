<?php
require_once 'config.php';
requireLogin();

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM laboratory WHERE lab_id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    die("Not found");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Lab</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Edit Lab Record</h1>
            <a href="laboratory.php" class="btn primary">Back</a>
        </header>
        <main>
            <div class="card">
                <form action="update_lab.php" method="POST">
                    <input type="hidden" name="lab_id" value="<?= $data['lab_id'] ?>">

                    <div class="form-group">
                        <label>Patient ID</label>
                        <input type="number" name="patient_id" value="<?= $data['patient_id'] ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Test Type</label>
                        <select name="laboratory_type">
                            <?php
                            $types = ['X-ray', 'Ultrasound', 'CBC', 'Urinalysis', 'Blood Chemistry', 'ECG'];
                            foreach ($types as $t):
                            ?>
                                <option <?= $data['laboratory_type'] == $t ? 'selected' : '' ?>>
                                    <?= $t ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <?php
                            $statuses = ['Not Yet Taken', 'Ongoing', 'Completed'];
                            foreach ($statuses as $s):
                            ?>
                                <option <?= $data['status'] == $s ? 'selected' : '' ?>>
                                    <?= $s ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Result</label>
                        <textarea name="result"><?= htmlspecialchars($data['result']) ?></textarea>
                    </div>

                    <button type="submit" class="btn primary">Update</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>