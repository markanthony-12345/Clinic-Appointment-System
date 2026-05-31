// Modal handling
const modals = document.querySelectorAll('.modal');
const triggers = document.querySelectorAll('[href*="#"]');
triggers.forEach(trigger => {
    trigger.addEventListener('click', e => {
        e.preventDefault();
        const modal = document.querySelector(trigger.getAttribute('href'));
        if (modal) modal.style.display = 'block';
    });
});
document.querySelectorAll('.close').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('.modal').style.display = 'none');
});
window.addEventListener('click', e => {
    if (e.target.classList.contains('modal')) e.target.style.display = 'none';
});

// Doctor availability check
function checkAvailability() {
    const doctor = document.getElementById('doctor_select')?.value;
    const date = document.getElementById('appointment_date')?.value;
    const statusDiv = document.getElementById('availability-status');
    const bookBtn = document.getElementById('book-btn');

    // Enable button by default so user can always try to book
    if (bookBtn) bookBtn.disabled = false;

    if (!doctor || !date) {
        if (statusDiv) statusDiv.innerHTML = '';
        return;
    }

    fetch(`check_availability.php?doctor_id=${doctor}&date=${date}`)
        .then(r => r.json())
        .then(data => {
            if (data.available) {
                statusDiv.innerHTML = `<span style="color:green;">✅ Available! ${data.remaining} slot(s) left.</span>`;
                bookBtn.disabled = false;
            } else {
                statusDiv.innerHTML = `<span style="color:red;">❌ Fully booked for this date (max ${data.max_patients}).</span>`;
                bookBtn.disabled = false; // Still allow booking, just warn
            }
        })
        .catch(() => {
            // If check fails, still allow booking
            if (statusDiv) statusDiv.innerHTML = '';
            if (bookBtn) bookBtn.disabled = false;
        });
}

// Enable book button on page load in case form already has values
document.addEventListener('DOMContentLoaded', () => {
    const bookBtn = document.getElementById('book-btn');
    if (bookBtn) bookBtn.disabled = false;
});

// Clearance print
function printClearance(id) {
    window.open(`print_clearance.php?patient_id=${id}`, '_blank');
}

// ==================== MODAL FUNCTIONS ====================
function openModal() {
    document.getElementById('appointmentModal').classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
    loadWeekView();
}

function closeModal() {
    document.getElementById('appointmentModal').classList.remove('active');
    document.body.style.overflow = '';
    resetForm();
}

function resetForm() {
    document.getElementById('appointmentForm').reset();
    document.getElementById('weekDays').innerHTML = '';
    document.getElementById('timeContainer').innerHTML = `
        <div class="time-placeholder">
            Select a doctor and date first to see available times
        </div>
    `;
    document.getElementById('selectedDate').value = '';
    document.getElementById('selectedTime').value = '';
    
    // Reset book button
    const bookBtn = document.querySelector('.btn-book');
    if (bookBtn) bookBtn.disabled = true;
}

// Close modal on outside click
document.getElementById('appointmentModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// ==================== WEEKLY CALENDAR ====================
function loadWeekView() {
    const doctorId = document.getElementById('doctorSelect').value;
    const container = document.getElementById('weekDays');
    
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
        
        // For demo: simulate slot availability
        // In production, replace with AJAX call to check real availability
        const slots = getSimulatedSlots(fullDate, doctorId);
        
        const isFull = slots === 0;
        const statusClass = isFull ? 'full' : '';
        const statusText = isFull ? 'Full' : `${slots} slots`;
        
        // Build clickable day card
        // If full, no onclick handler and 'full' class disables pointer events
        html += `
            <div class="day-card ${statusClass}" 
                 data-date="${fullDate}"
                 data-slots="${slots}"
                 ${!isFull ? `onclick="selectDate('${fullDate}', this)"` : ''}>
                <div class="day-name">${dayName}</div>
                <div class="day-date">${month}/${dayNum}</div>
                <div class="slots ${isFull ? 'full' : ''}">${statusText}</div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// Simulate slot availability (replace with real API call)
function getSimulatedSlots(date, doctorId) {
    // Generate pseudo-random but consistent slots based on date + doctor
    const seed = date.split('-').join('') + doctorId;
    let hash = 0;
    for (let i = 0; i < seed.length; i++) {
        hash = ((hash << 5) - hash) + seed.charCodeAt(i);
        hash = hash & hash;
    }
    return Math.abs(hash % 12); // 0-11 slots
}

// ==================== DATE SELECTION ====================
function selectDate(date, element) {
    // Remove previous selection
    document.querySelectorAll('.day-card').forEach(d => {
        d.classList.remove('selected');
    });
    
    // Add selected class to clicked element
    element.classList.add('selected');
    
    // Update hidden input
    document.getElementById('selectedDate').value = date;
    
    // Clear previous time selection
    document.getElementById('selectedTime').value = '';
    
    // Load available times for this date
    loadTimes(date);
    
    // Update validation
    validateForm();
}

// ==================== TIME SLOTS ====================
function loadTimes(date) {
    const doctorId = document.getElementById('doctorSelect').value;
    const container = document.getElementById('timeContainer');
    
    if (!doctorId) {
        container.innerHTML = `
            <div class="time-placeholder">
                Please select a doctor first
            </div>
        `;
        return;
    }
    
    // All possible time slots
    const allSlots = [
        '09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM',
        '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM'
    ];
    
    // In production, fetch from server:
    // fetch(`get_available_times.php?doctor_id=${doctorId}&date=${date}`)
    //     .then(r => r.json())
    //     .then(availableSlots => { ... });
    
    // Simulate: mark some slots as taken
    const availableSlots = getSimulatedAvailableSlots(date, doctorId, allSlots);
    
    if (availableSlots.length === 0) {
        container.innerHTML = `
            <div class="time-placeholder" style="color: #e53e3e; border-color: #feb2b2;">
                No available slots for this date. Please select another date.
            </div>
        `;
        return;
    }
    
    let html = '<div class="time-slots-grid">';
    allSlots.forEach(slot => {
        const isAvailable = availableSlots.includes(slot);
        const className = isAvailable ? '' : 'taken';
        const onclick = isAvailable ? `onclick="selectTime('${slot}', this)"` : '';
        
        html += `<div class="time-slot ${className}" ${onclick}>${slot}</div>`;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// Simulate available slots (replace with real API call)
function getSimulatedAvailableSlots(date, doctorId, allSlots) {
    const seed = date + doctorId;
    let hash = 0;
    for (let i = 0; i < seed.length; i++) {
        hash = ((hash << 5) - hash) + seed.charCodeAt(i);
        hash = hash & hash;
    }
    
    // Randomly mark 2-4 slots as taken
    const takenCount = 2 + (Math.abs(hash) % 3);
    const taken = new Set();
    for (let i = 0; i < takenCount; i++) {
        taken.add(allSlots[Math.abs((hash + i * 7) % allSlots.length)]);
    }
    
    return allSlots.filter(slot => !taken.has(slot));
}

// ==================== TIME SELECTION ====================
function selectTime(time, element) {
    // Remove previous selection
    document.querySelectorAll('.time-slot').forEach(t => {
        t.classList.remove('selected');
    });
    
    // Add selected class to clicked element
    element.classList.add('selected');
    
    // Update hidden input
    document.getElementById('selectedTime').value = time;
    
    // Update validation
    validateForm();
}

// ==================== FORM VALIDATION ====================
function validateForm() {
    const patientId = document.querySelector('input[name="patient_id"]').value.trim();
    const doctorId = document.getElementById('doctorSelect').value;
    const date = document.getElementById('selectedDate').value;
    const time = document.getElementById('selectedTime').value;
    
    const bookBtn = document.querySelector('.btn-book');
    
    const isValid = patientId && doctorId && date && time;
    
    if (bookBtn) {
        bookBtn.disabled = !isValid;
    }
}

// Add input listeners for real-time validation
document.addEventListener('DOMContentLoaded', function() {
    const patientInput = document.querySelector('input[name="patient_id"]');
    const doctorSelect = document.getElementById('doctorSelect');
    
    if (patientInput) {
        patientInput.addEventListener('input', validateForm);
    }
    if (doctorSelect) {
        doctorSelect.addEventListener('change', function() {
            // Reload week view when doctor changes
            loadWeekView();
            // Clear selections
            document.getElementById('selectedDate').value = '';
            document.getElementById('selectedTime').value = '';
            document.getElementById('timeContainer').innerHTML = `
                <div class="time-placeholder">
                    Select a date to see available times
                </div>
            `;
            validateForm();
        });
    }
    
    // Initialize book button as disabled
    const bookBtn = document.querySelector('.btn-book');
    if (bookBtn) bookBtn.disabled = true;
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