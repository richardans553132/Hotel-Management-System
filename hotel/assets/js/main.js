/**
 * Hotel Booking System JS Utilities
 * 
 * Includes dynamic price calculation for reservations, form confirmation dialogs, 
 * and generic client-side form validation.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize Reservation Price Calculators
    setupReservationPriceCalculator('addReservationForm');
    setupReservationPriceCalculator('editReservationForm');

    // Fade out bootstrap alerts automatically after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bootstrapAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bootstrapAlert) {
                bootstrapAlert.close();
            }
        }, 5000);
    });
});

/**
 * Attaches real-time cost calculation to a reservation form
 * 
 * @param {string} formId ID of the HTML form
 */
function setupReservationPriceCalculator(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    const roomSelect = form.querySelector('.room-select');
    const checkInInput = form.querySelector('.check-in-date');
    const checkOutInput = form.querySelector('.check-out-date');
    const totalInput = form.querySelector('.total-price-input');
    const nightCountSpan = form.querySelector('.nights-count-badge');

    if (!roomSelect || !checkInInput || !checkOutInput || !totalInput) return;

    function calculateTotal() {
        const checkInVal = checkInInput.value;
        const checkOutVal = checkOutInput.value;
        const selectedOption = roomSelect.options[roomSelect.selectedIndex];
        
        if (!checkInVal || !checkOutVal || !selectedOption) {
            totalInput.value = '0.00';
            if (nightCountSpan) nightCountSpan.textContent = '0 nights';
            return;
        }

        const checkIn = new Date(checkInVal);
        const checkOut = new Date(checkOutVal);
        
        // Calculate difference in milliseconds and convert to days
        const diffTime = checkOut - checkIn;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (isNaN(diffDays) || diffDays <= 0) {
            totalInput.value = '0.00';
            if (nightCountSpan) {
                nightCountSpan.textContent = 'Invalid dates';
                nightCountSpan.className = 'badge bg-danger ms-2 nights-count-badge';
            }
            return;
        }

        const pricePerNight = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        const total = pricePerNight * diffDays;
        
        // Update price input and badge
        totalInput.value = total.toFixed(2);
        if (nightCountSpan) {
            nightCountSpan.textContent = diffDays + (diffDays === 1 ? ' night' : ' nights');
            nightCountSpan.className = 'badge bg-primary ms-2 nights-count-badge';
        }
    }

    // Bind event listeners
    roomSelect.addEventListener('change', calculateTotal);
    checkInInput.addEventListener('change', calculateTotal);
    checkOutInput.addEventListener('change', calculateTotal);
}

/**
 * Confirm delete action with standard browser prompt
 * 
 * @param {string} entityName The name of the entity being deleted (e.g. "Room")
 * @returns {boolean}
 */
function confirmDelete(entityName) {
    return confirm("Are you sure you want to permanently delete this " + entityName + "? All linked details (including payments/reservations if applicable) will be modified or deleted.");
}
