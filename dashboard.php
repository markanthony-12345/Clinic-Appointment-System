<!-- NEW APPOINTMENT MODAL -->
<div id="appointmentModalOverlay" class="modal-overlay">
    <div class="modal-panel">
        <div class="modal-panel-header">
            <h2>New Appointment – Weekly View</h2>
            <button class="modal-close-btn" onclick="closeAppointmentModal()">&times;</button>
        </div>
        <div class="modal-panel-body">
            <form id="appointmentForm" action="appointment_process.php" method="POST" onsubmit="return validateAppointmentForm()">
                
                <!-- Patient ID -->
                <div class="form-group">
                    <label>Patient ID</label>
                    <input type="number" name="patient_id" id="apptPatientId" placeholder="Enter Patient ID" required oninput="checkAppointmentValid()">
                </div>
                
                <!-- Doctor -->
                <div class="form-group">
                    <label>Doctor</label>
                    <select name="doctor_id" id="apptDoctorSelect" required onchange="loadAppointmentCalendar()">
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= $d['doctor_id'] ?>"><?= htmlspecialchars($d['doctor_name']) ?> (<?= htmlspecialchars($d['specialization']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Weekly Calendar -->
                <div class="form-group">
                    <label>Select Date (Next 7 days)</label>
                    <div class="weekly-calendar-grid" id="appointmentCalendar"></div>
                    <input type="hidden" name="appointment_date" id="apptSelectedDate" required>
                </div>
                
                <!-- Time -->
                <div class="form-group">
                    <label>Time</label>
                    <div id="timeContainer">
                        <div class="time-display-box" id="timeDisplay">
                            <span>--:-- --</span>
                            <span style="font-size: 1.2rem;">&#x23F0;</span>
                        </div>
                    </div>
                    <input type="hidden" name="appointment_time" id="apptSelectedTime" required>
                </div>
                
                <!-- Lab Required -->
                <div class="form-group">
                    <label>Lab Required?</label>
                    <select name="laboratory_required">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                
                <div id="appointmentStatus"></div>
                
                <button type="submit" class="btn-book-appointment" id="apptBookBtn" disabled>Book Appointment</button>
            </form>
        </div>
    </div>
</div>