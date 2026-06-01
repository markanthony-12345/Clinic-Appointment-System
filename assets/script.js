// ==================== MODAL HANDLING ====================
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

// ==================== APPOINTMENT MODAL ====================
function openModal() {
    document.getElementById('appointmentModal').classList.add('active');
    document.body.style.overflow = 'hidden';
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
        <div class="time-placeholder">Select a doctor and date first to see available times</div>
    `;
    document.getElementById('selectedDate').value = '';
    document.getElementById('selectedTime').value = '';
    const bookBtn = document.querySelector('.btn-book');
    if (bookBtn) bookBtn.disabled = true;
}

document.getElementById('appointmentModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// ==================== WEEKLY CALENDAR ====================
function loadWeekView() {
    const doctorId = document.getElementById('doctorSelect')?.value;
    const container = document.getElementById('weekDays');
    if (!container) return;

    if (!doctorId) {
        container.innerHTML = '<p style="color:#718096;text-align:center;padding:20px;">Select a doctor first</p>';
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

        const slots = getSimulatedSlots(fullDate, doctorId);
        const isFull = slots === 0;
        const statusText = isFull ? 'Full' : `${slots} slots`;

        html += `
            <div class="day-card ${isFull ? 'full' : ''}"
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

    // Clear date/time selection when week reloads
    document.getElementById('selectedDate').value = '';
    document.getElementById('selectedTime').value = '';
    document.getElementById('timeContainer').innerHTML = `
        <div class="time-placeholder">Select a date to see available times</div>
    `;
    validateForm();
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

// ==================== DATE SELECTION (FIXED) ====================
function selectDate(date, element) {
    // Get fresh list of day cards every time (fixes the "can't click other dates" bug)
    const allCards = document.querySelectorAll('#weekDays .day-card');
    allCards.forEach(d => {
        d.classList.remove('selected');
        // Re-enable cursor for non-full cards
        if (!d.classList.contains('full')) {
            d.style.cursor = 'pointer';
        }
    });

    // Select clicked card
    element.classList.add('selected');
    element.style.cursor = 'default';

    document.getElementById('selectedDate').value = date;
    document.getElementById('selectedTime').value = '';

    loadTimes(date);
    validateForm();
}

// ==================== TIME SLOTS ====================
function loadTimes(date) {
    const doctorId = document.getElementById('doctorSelect')?.value;
    const container = document.getElementById('timeContainer');
    if (!container) return;

    if (!doctorId) {
        container.innerHTML = `<div class="time-placeholder">Please select a doctor first</div>`;
        return;
    }

    const allSlots = [
        '09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM',
        '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM', '05:00 PM'
    ];

    const availableSlots = getSimulatedAvailableSlots(date, doctorId, allSlots);

    if (availableSlots.length === 0) {
        container.innerHTML = `
            <div class="time-placeholder" style="color:#e53e3e;border-color:#feb2b2;">
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

function getSimulatedAvailableSlots(date, doctorId, allSlots) {
    const seed = date + doctorId;
    let hash = 0;
    for (let i = 0; i < seed.length; i++) {
        hash = ((hash << 5) - hash) + seed.charCodeAt(i);
        hash = hash & hash;
    }
    const takenCount = 2 + (Math.abs(hash) % 3);
    const taken = new Set();
    for (let i = 0; i < takenCount; i++) {
        taken.add(allSlots[Math.abs((hash + i * 7) % allSlots.length)]);
    }
    return allSlots.filter(slot => !taken.has(slot));
}

// ==================== TIME SELECTION ====================
function selectTime(time, element) {
    // Get fresh list every time (same fix as date selection)
    document.querySelectorAll('#timeContainer .time-slot').forEach(t => {
        t.classList.remove('selected');
        if (!t.classList.contains('taken')) {
            t.style.cursor = 'pointer';
        }
    });

    element.classList.add('selected');
    element.style.cursor = 'default';

    document.getElementById('selectedTime').value = time;
    validateForm();
}

// ==================== FORM VALIDATION ====================
function validateForm() {
    const patientId = document.querySelector('input[name="patient_id"]')?.value.trim();
    const doctorId = document.getElementById('doctorSelect')?.value;
    const date = document.getElementById('selectedDate')?.value;
    const time = document.getElementById('selectedTime')?.value;
    const bookBtn = document.querySelector('.btn-book');
    if (bookBtn) bookBtn.disabled = !(patientId && doctorId && date && time);
}

document.addEventListener('DOMContentLoaded', function() {
    const patientInput = document.querySelector('input[name="patient_id"]');
    const doctorSelect = document.getElementById('doctorSelect');

    if (patientInput) patientInput.addEventListener('input', validateForm);
    if (doctorSelect) {
        doctorSelect.addEventListener('change', function() {
            loadWeekView();
            validateForm();
        });
    }

    const bookBtn = document.querySelector('.btn-book');
    if (bookBtn) bookBtn.disabled = true;
});

// ==================== TABLE ACTIONS ====================
function markDone(id) {
    if (!confirm('Mark this appointment as Completed?')) return;
    fetch(`mark_appointment_done.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) location.reload();
            else alert('Failed: ' + (data.message || 'Unknown error'));
        })
        .catch(() => alert('Network error'));
}

function editAppt(id) { window.location.href = `edit_appointment.php?id=${id}`; }

function cancelAppt(id) {
    if (!confirm('Cancel this appointment?')) return;
    fetch(`delete_appointment.php?id=${id}`)
        .then(() => location.reload())
        .catch(() => alert('Network error'));
}

function deletePatient(id, name) {
    if (!confirm(`DELETE patient "${name}"?\n\nThis will permanently remove ALL records.\n\nThis CANNOT be undone!`)) return;
    fetch(`delete_patient.php?patient_id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('patient-row-' + id)?.remove();
                alert('Patient deleted successfully.');
            } else {
                alert('Delete failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error. Please try again.'));
}

function printClearance(id) {
    window.open(`print_clearance.php?patient_id=${id}`, '_blank');
}

function checkAvailability() {}