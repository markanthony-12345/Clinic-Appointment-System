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
            <div class="time-input-wrapper">
                <input type="time" name="appointment_time" id="timeInput" value="--:-- --" disabled>
                <span class="time-icon">&#x23F0;</span>
            </div>
        `;
    }
    
    const selectedDate = document.getElementById('selectedDate');
    if (selectedDate) selectedDate.value = '';
    
    const selectedTime = document.getElementById('selectedTime');
    if (selectedTime) selectedTime.value = '';
    
    const bookBtn = document.querySelector('.btn-book');
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
            // Reset time input
            const timeContainer = document.getElementById('timeContainer');
            if (timeContainer) {
                timeContainer.innerHTML = `
                    <div class="time-input-wrapper">
                        <input type="time" name="appointment_time" id="timeInput" value="--:-- --" disabled>
                        <span class="time-icon">&#x23F0;</span>
                    </div>
                `;
            }
            const selectedDate = document.getElementById('selectedDate');
            if (selectedDate) selectedDate.value = '';
            const selectedTime = document.getElementById('selectedTime');
            if (selectedTime) selectedTime.value = '';
            validateForm();
        });
    }
    
    // Initialize book button as disabled
    const bookBtn = document.querySelector('.btn-book');
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
    
    // Add selected class
    element.classList.add('selected');
    
    // Update hidden input
    const selectedDate = document.getElementById('selectedDate');
    if (selectedDate) selectedDate.value = date;
    
    // Enable time input
    const timeContainer = document.getElementById('timeContainer');
    if (timeContainer) {
        timeContainer.innerHTML = `
            <div class="time-input-wrapper">
                <input type="time" name="appointment_time" id="timeInput" required>
                <span class="time-icon">&#x23F0;</span>
            </div>
        `;
        
        // Add change listener to time input
        const timeInput = document.getElementById('timeInput');
        if (timeInput) {
            timeInput.addEventListener('change', function() {
                const selectedTime = document.getElementById('selectedTime');
                if (selectedTime) selectedTime.value = this.value;
                validateForm();
            });
        }
    }
    
    validateForm();
}

// ==================== FORM VALIDATION ====================
function validateForm() {
    const patientId = document.querySelector('input[name="patient_id"]');
    const doctorId = document.getElementById('doctorSelect');
    const date = document.getElementById('selectedDate');
    const time = document.getElementById('selectedTime');
    
    const bookBtn = document.querySelector('.btn-book');
    
    const isValid = patientId && patientId.value.trim() && 
                   doctorId && doctorId.value && 
                   date && date.value && 
                   time && time.value;
    
    if (bookBtn) {
        bookBtn.disabled = !isValid;
    }
}

// Add input listeners for real-time validation
document.addEventListener('DOMContentLoaded', function() {
    const patientInput = document.querySelector('input[name="patient_id"]');
    if (patientInput) {
        patientInput.addEventListener('input', validateForm);
    }
});

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
