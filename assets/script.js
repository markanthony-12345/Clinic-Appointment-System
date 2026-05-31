// ==================== MODAL FUNCTIONS ====================
function openModal() {
    const modal = document.getElementById('appointmentModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        loadWeekView();
    }
}

function closeModal() {
    const modal = document.getElementById('appointmentModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        resetForm();
    }
}

function resetForm() {
    const form = document.getElementById('appointmentForm');
    if (form) form.reset();
    
    const weekDays = document.getElementById('weekDays');
    if (weekDays) weekDays.innerHTML = '';
    
    const timeContainer = document.getElementById('timeContainer');
    if (timeContainer) {
        timeContainer.innerHTML = `
            <input type="text" id="timeDisplay" value="--:-- --" readonly disabled>
            <span class="time-icon">&#x23F0;</span>
        `;
    }
    
    const selectedDate = document.getElementById('selectedDate');
    if (selectedDate) selectedDate.value = '';
    
    const selectedTime = document.getElementById('selectedTime');
    if (selectedTime) selectedTime.value = '';
    
    const bookBtn = document.getElementById('bookBtn');
    if (bookBtn) bookBtn.disabled = true;
}

// Close modal on outside click
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('appointmentModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    }
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    
    // Doctor change handler
    const doctorSelect = document.getElementById('doctorSelect');
    if (doctorSelect) {
        doctorSelect.addEventListener('change', function() {
            loadWeekView();
            // Reset time display
            const timeContainer = document.getElementById('timeContainer');
            if (timeContainer) {
                timeContainer.innerHTML = `
                    <input type="text" id="timeDisplay" value="--:-- --" readonly disabled>
                    <span class="time-icon">&#x23F0;</span>
                `;
            }
            const selectedDate = document.getElementById('selectedDate');
            if (selectedDate) selectedDate.value = '';
            const selectedTime = document.getElementById('selectedTime');
            if (selectedTime) selectedTime.value = '';
            checkFormValid();
        });
    }
    
    // Patient ID input listener
    const patientId = document.getElementById('patientId');
    if (patientId) {
        patientId.addEventListener('input', checkFormValid);
    }
    
    // Initialize book button as disabled
    const bookBtn = document.getElementById('bookBtn');
    if (bookBtn) bookBtn.disabled = true;
});

// ==================== WEEKLY CALENDAR ====================
function loadWeekView() {
    const doctorId = document.getElementById('doctorSelect').value;
    const container = document.getElementById('weekDays');
    
    if (!container) return;
    
    if (!doctorId) {
        container.innerHTML = '<p style="color: #718096; text-align: center; padding: 20px;">Select a doctor first</p>';
        return;
    }
    
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const months = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
    let html = '';
    
    for (let i = 0; i < 7; i++) {
        const date = new Date();
        date.setDate(date.getDate() + i);
        
        const dayName = days[date.getDay()];
        const month = months[date.getMonth()];
        const dayNum = date.getDate();
        const fullDate = date.toISOString().split('T')[0];
        
        // Simulate slot availability
        const slots = getSimulatedSlots(fullDate, doctorId);
        
        const isFull = slots === 0;
        const statusClass = isFull ? 'full' : 'available';
        const statusText = isFull ? 'Full' : `${slots} slots`;
        
        html += `
            <div class="day-card ${statusClass}" 
                 data-date="${fullDate}"
                 data-slots="${slots}"
                 ${!isFull ? `onclick="selectDate('${fullDate}', this)"` : ''}>
                <div class="day-name">${dayName}</div>
                <div class="day-date">${month}/${dayNum}</div>
                <div class="slots">${statusText}</div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// Simulate slot availability
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
function selectDate(date, element) {
    // Remove previous selection
    document.querySelectorAll('.day-card').forEach(d => {
        d.classList.remove('selected');
    });
    
    // Add selected class to clicked element
    element.classList.add('selected');
    
    // Update hidden date input
    const selectedDate = document.getElementById('selectedDate');
    if (selectedDate) selectedDate.value = date;
    
    // Enable and show time picker
    const timeContainer = document.getElementById('timeContainer');
    if (timeContainer) {
        // Get available times for this date
        const availableTimes = getAvailableTimes(date);
        
        if (availableTimes.length === 0) {
            timeContainer.innerHTML = `
                <input type="text" value="No slots available" readonly disabled style="color: #e53e3e;">
                <span class="time-icon">&#x23F0;</span>
            `;
        } else {
            // Build time slot buttons
            let timeHtml = '<div class="time-slots-grid">';
            availableTimes.forEach(time => {
                timeHtml += `<div class="time-slot" onclick="selectTime('${time}', this)">${time}</div>`;
            });
            timeHtml += '</div>';
            timeContainer.innerHTML = timeHtml;
        }
    }
    
    checkFormValid();
}

// Get available times (simulated)
function getAvailableTimes(date) {
    const allTimes = ['09:00 AM', '10:00 AM', '11:00 AM', '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM'];
    const seed = date;
    let hash = 0;
    for (let i = 0; i < seed.length; i++) {
        hash = ((hash << 5) - hash) + seed.charCodeAt(i);
        hash = hash & hash;
    }
    
    // Randomly remove 2-4 slots
    const removeCount = 2 + (Math.abs(hash) % 3);
    const taken = new Set();
    for (let i = 0; i < removeCount; i++) {
        taken.add(allTimes[Math.abs((hash + i * 7) % allTimes.length)]);
    }
    
    return allTimes.filter(t => !taken.has(t));
}

// ==================== TIME SELECTION ====================
function selectTime(time, element) {
    // Remove previous selection
    document.querySelectorAll('.time-slot').forEach(t => {
        t.classList.remove('selected');
    });
    
    // Add selected class
    element.classList.add('selected');
    
    // Update hidden time input
    const selectedTime = document.getElementById('selectedTime');
    if (selectedTime) selectedTime.value = time;
    
    // Update display
    const timeDisplay = document.getElementById('timeDisplay');
    if (timeDisplay) timeDisplay.value = time;
    
    checkFormValid();
}

// ==================== FORM VALIDATION ====================
function checkFormValid() {
    const patientId = document.getElementById('patientId');
    const doctorId = document.getElementById('doctorSelect');
    const date = document.getElementById('selectedDate');
    const time = document.getElementById('selectedTime');
    const bookBtn = document.getElementById('bookBtn');
    
    if (!bookBtn) return;
    
    // Check all required fields
    const hasPatient = patientId && patientId.value.trim() !== '';
    const hasDoctor = doctorId && doctorId.value !== '';
    const hasDate = date && date.value !== '';
    const hasTime = time && time.value !== '';
    
    console.log('Validation:', { hasPatient, hasDoctor, hasDate, hasTime });
    console.log('Values:', { 
        patient: patientId ? patientId.value : 'null', 
        doctor: doctorId ? doctorId.value : 'null',
        date: date ? date.value : 'null',
        time: time ? time.value : 'null'
    });
    
    const isValid = hasPatient && hasDoctor && hasDate && hasTime;
    
    bookBtn.disabled = !isValid;
    
    if (isValid) {
        bookBtn.style.opacity = '1';
        bookBtn.style.cursor = 'pointer';
    } else {
        bookBtn.style.opacity = '0.5';
        bookBtn.style.cursor = 'not-allowed';
    }
}

// ==================== TABLE ACTIONS ====================
function markDone(id) {
    if (!confirm('Mark this appointment as Completed?')) return;
    
    fetch(`mark_appointment_done.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error'));
}

function editAppt(id) {
    window.location.href = `edit_appointment.php?id=${id}`;
}

function cancelAppt(id) {
    if (!confirm('Cancel this appointment?')) return;
    fetch(`delete_appointment.php?id=${id}`)
        .then(() => location.reload())
        .catch(() => alert('Network error'));
}