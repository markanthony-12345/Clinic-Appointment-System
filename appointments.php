<!-- New Appointment Modal -->
<div class="modal" id="appointmentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>New Appointment – Weekly View</h2>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form class="appointment-form" id="appointmentForm" action="save_appointment.php" method="POST">
                
                <!-- Patient ID -->
                <div class="form-group">
                    <label>Patient ID</label>
                    <input type="text" name="patient_id" placeholder="Enter Patient ID" required>
                </div>
                
                <!-- Doctor -->
                <div class="form-group">
                    <label>Doctor</label>
                    <select name="doctor_id" id="doctorSelect" required onchange="loadWeekView()">
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['doctor_id'] ?>">
                            <?= htmlspecialchars($doc['doctor_name']) ?> 
                            (<?= htmlspecialchars($doc['specialization'] ?? 'General') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Weekly Calendar -->
                <div class="form-group">
                    <label>Select Date (Next 7 days)</label>
                    <div class="weekly-calendar" id="weekDays">
                        <!-- JS generates day cards here -->
                    </div>
                    <input type="hidden" name="appointment_date" id="selectedDate" required>
                </div>
                
                <!-- Time Slots -->
                <div class="form-group">
                    <label>Time</label>
                    <div class="time-slots-container" id="timeContainer">
                        <div class="time-placeholder">
                            Select a doctor and date first to see available times
                        </div>
                    </div>
                    <input type="hidden" name="appointment_time" id="selectedTime" required>
                </div>
                
                <!-- Lab Required -->
                <div class="form-group">
                    <label>Lab Required?</label>
                    <select name="lab_required">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-book">Book Appointment</button>
            </form>
        </div>
    </div>
</div>