<?php require_once 'config.php'; requireLogin(); ?>
<div class="section">
    <h2>Appointments</h2>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Date/Time</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $stmt = $pdo->query("SELECT a.*, p.fullname as patient_name, d.doctor_name FROM appointments a JOIN patients p ON a.patient_id = p.patient_id JOIN doctors d ON a.doctor_id = d.doctor_id ORDER BY a.appointment_date DESC");
        while ($row = $stmt->fetch()):
        ?>
        <tr id="appt-row-<?= $row['appointment_id'] ?>">
            <td><?= $row['appointment_id'] ?></td>
            <td><?= htmlspecialchars($row['patient_name']) ?></td>
            <td><?= htmlspecialchars($row['doctor_name']) ?></td>
            <td><?= $row['appointment_date'] ?></td>
            <td>
                <span class="status <?= strtolower($row['status']) ?>" id="appt-status-<?= $row['appointment_id'] ?>">
                    <?= $row['status'] ?>
                </span>
            </td>
            <td>
                <?php if ($row['status'] !== 'Completed' && $row['status'] !== 'Cancelled'): ?>
                    <button class="btn success" onclick="markAppointmentDone(<?= $row['appointment_id'] ?>)">
                        ✓ Mark Done
                    </button>
                <?php endif; ?>
                <button class="btn" onclick="editAppointment(<?= $row['appointment_id'] ?>)">Edit</button>
                <button class="btn danger" onclick="deleteAppointment(<?= $row['appointment_id'] ?>)">Cancel</button>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<script>
function markAppointmentDone(id) {
    if (!confirm('Mark this appointment as Completed?')) return;
    fetch(`mark_appointment_done.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('appt-status-' + id).textContent = 'Completed';
                document.getElementById('appt-status-' + id).className = 'status completed';
                // Remove the Mark Done button
                location.reload();
            } else {
                alert('Failed to update: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error. Please try again.'));
}
function editAppointment(id) { window.location.href = `edit_appointment.php?id=${id}`; }
function deleteAppointment(id) {
    if (confirm('Cancel this appointment?'))
        fetch(`delete_appointment.php?id=${id}`).then(() => location.reload());
}
</script>
