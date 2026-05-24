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
