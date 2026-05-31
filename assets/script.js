// ==================== APPOINTMENT MODAL ====================
function openAppointmentModal() {
    const modal = document.getElementById('appointmentModalOverlay');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        resetAppointmentForm();
    }
}

function closeAppointmentModal() {
    const modal = document.getElementById('appointmentModalOverlay');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        resetAppointmentForm();
    }
}

function resetAppointmentForm() {
    const form = document.getElementById('appointmentForm');
    if (form) form.reset();
    
    const calendar = document.getElementById('appointmentCalendar');
    if (calendar) calendar.innerHTML = '';
    
    const timeDisplay = document.getElementById('timeDisplay');
    if (timeDisplay) {
        timeDisplay.innerHTML = '<span>--:-- --</span><span style="font-size: 1.2rem;">&#x23F0;</span>';
        timeDisplay.classList.remove('active');
    }
    
    const selectedDate = document.getElementById('apptSelectedDate');
    if (selectedDate) selectedDate.value = '';
    
    const selectedTime = document.getElementById('apptSelectedTime');
    if (selectedTime) selectedTime.value = '';
    
    const status = document.getElementById('appointmentStatus');
    if (status) status.innerHTML = '';
    
    const bookBtn = document.getElementById('apptBookBtn');
    if (bookBtn) bookBtn.disabled = true;
}

// Close on outside click
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('appointmentModalOverlay');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeAppointmentModal();
        });
    }
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAppointmentModal();
    });
});

// ==================== CALENDAR ====================
function loadAppointmentCalendar() {
    const doctorId = document.getElementById('apptDoctorSelect').value;
    const calendar = document.getElementById('appointmentCalendar');
    
    if (!calendar) return;
    
    if (!doctorId) {
        calendar.innerHTML = '<div style="grid-column:span 7; text-align:center; color:#718096; padding:20px;">Select a doctor first</div>';
        return;
    }
    
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const months = ['1','2','3','4','5','6','7','8','9','10','11','12'];
    let html = '';
    
    for (let i = 0; i < 7; i++) {
        const date = new Date();
        date.setDate(date.getDate() + i);
        
        const dayName = days[date.getDay()];
        const month = months[date.getMonth()];
        const dayNum = date.getDate();
        const fullDate = date.toISOString().split('T')[0];
        
        // Simulate slots - REPLACE with real API call
        const slots = getSimulatedSlots(fullDate, doctorId);
        const isFull = slots === 0;
        const statusClass = isFull ? 'full' : 'available';
        const statusText = isFull ? 'Full' : `${slots} slots`;
        
        html += `
            <div class="day-card ${statusClass}" 
                 data-date="${fullDate}"
                 ${!isFull ? `onclick="selectAppointmentDate('${fullDate}', this)"` : ''}>
                <div class="day-name">${dayName}</div>
                <div class="day-date">${month}/${dayNum}</div>
                <div class="slots">${statusText}</div>
            </div>
        `;
    }
    
    calendar.innerHTML = html;
}

function getSimulatedSlots(date, doctorId) {
    const seed = date.split('-').join('') + doctorId;
    let hash = 0;
    for (let i = 0; i < seed.length; i++) {
        hash = ((hash << 5) - hash) + seed.charCodeAt(i);
        hash = hash & hash;
    }
    return Math.abs(hash % 12);
}

// ==================== DATE SELECTION ====================
function selectAppointmentDate(date, element) {
    // Remove previous selection
    document.querySelectorAll('#appointmentCalendar .day-card').forEach(d => {
        d.classList.remove('selected');
    });
    
    // Add selected
    element.classList.add('selected');
    
    // Set hidden date
    const selectedDate = document.getElementById('apptSelectedDate');
    if (selectedDate) selectedDate.value = date;
    
    // Load times
    loadAppointmentTimes(date);
    
    // Update status
    const status = document.getElementById('appointmentStatus');
    if (status) {
        status.innerHTML = `<span style="color:#2d6a4f; font-weight:500;">✓ Date selected: ${date}</span>`;
    }
    
    checkAppointmentValid();
}

// ==================== TIME SLOTS ====================
function loadAppointmentTimes(date) {
    const doctorId = document.getElementById('apptDoctorSelect').value;
    const container = document.getElementById('timeContainer');
    
    const allTimes = ['09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM', '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM'];
    
    // Simulate - REPLACE with: fetch(`get_available_time.php?doctor_id=${doctorId}&date=${date}`)
    const available = getSimulatedAvailableTimes(date, doctorId, allTimes);
    
    if (available.length === 0) {
        if (container) {
            container.innerHTML = `
                <div class="time-display-box" style="color:#c53030; border-color:#feb2b2;">
                    <span>No slots available for this date</span>
                </div>
            `;
        }
        return;
    }
    
    let html = '<div class="time-slots-grid">';
    allTimes.forEach(time => {
        const isAvailable = available.includes(time);
        const className = isAvailable ? '' : 'taken';
        const onclick = isAvailable ? `onclick="selectAppointmentTime('${time}', this)"` : '';
        
        html += `<div class="time-slot-btn ${className}" ${onclick}>${time}</div>`;
    });
    html += '</div>';
    
    if (container) container.innerHTML = html;
}

function getSimulatedAvailableTimes(date, doctorId, allTimes) {
    const seed = date + doctorId;
    let hash = 0;
    for (let i = 0; i < seed.length; i++) {
        hash = ((hash << 5) - hash) + seed.charCodeAt(i);
        hash = hash & hash;
    }
    const removeCount = 2 + (Math.abs(hash) % 3);
    const taken = new Set();
    for (let i = 0; i < removeCount; i++) {
        taken.add(allTimes[Math.abs((hash + i * 7) % allTimes.length)]);
    }
    return allTimes.filter(t => !taken.has(t));
}

function selectAppointmentTime(time, element) {
    // Remove previous
    document.querySelectorAll('.time-slot-btn').forEach(t => {
        t.classList.remove('selected');
    });
    
    // Add selected
    element.classList.add('selected');
    
    // Set hidden time
    const selectedTime = document.getElementById('apptSelectedTime');
    if (selectedTime) selectedTime.value = time;
    
    // Update display
    const timeDisplay = document.getElementById('timeDisplay');
    if (timeDisplay) {
        timeDisplay.innerHTML = `<span>${time}</span><span style="font-size: 1.2rem;">&#x23F0;</span>`;
        timeDisplay.classList.add('active');
    }
    
    // Update status
    const status = document.getElementById('appointmentStatus');
    if (status) {
        status.innerHTML = `<span style="color:#1e6f9f; font-weight:500;">✓ Time selected: ${time}</span>`;
    }
    
    checkAppointmentValid();
}

// ==================== VALIDATION ====================
function checkAppointmentValid() {
    const patientId = document.getElementById('apptPatientId');
    const doctorId = document.getElementById('apptDoctorSelect');
    const date = document.getElementById('apptSelectedDate');
    const time = document.getElementById('apptSelectedTime');
    const bookBtn = document.getElementById('apptBookBtn');
    
    if (!bookBtn) return false;
    
    const hasPatient = patientId && patientId.value.trim() !== '';
    const hasDoctor = doctorId && doctorId.value !== '';
    const hasDate = date && date.value !== '';
    const hasTime = time && time.value !== '';
    
    const isValid = hasPatient && hasDoctor && hasDate && hasTime;
    bookBtn.disabled = !isValid;
    
    return isValid;
}

function validateAppointmentForm() {
    if (!checkAppointmentValid()) {
        const status = document.getElementById('appointmentStatus');
        if (status) {
            status.innerHTML = `<span style="color:#c53030;">Please fill all required fields</span>`;
        }
        return false;
    }
    return true;
}