function calculateRental() {
    const carSelect = document.getElementById('car_id');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const display = document.getElementById('total_display');
    const hiddenInput = document.getElementById('total_price_val');

    // Get values
    const pricePerDay = parseFloat(carSelect.options[carSelect.selectedIndex].getAttribute('data-price'));
    const start = new Date(startDateInput.value);
    const end = new Date(endDateInput.value);

    // Validation: Ensure dates are valid and end is after start
    if (pricePerDay && startDateInput.value && endDateInput.value) {
        if (end > start) {
            // Calculate difference in milliseconds
            const diffInMs = end - start;
            
            // Math: Convert ms to days (1000ms * 60s * 60m * 24h)
            const diffInDays = diffInMs / (1000 * 60 * 60 * 24);
            
            // Total calculation
            const total = diffInDays * pricePerDay;

            // Update UI
            display.innerHTML = `<strong>Total Days:</strong> ${diffInDays} <br> <strong>Total Cost:</strong> $${total.toFixed(2)}`;
            
            // Set hidden input value for PHP submission
            hiddenInput.value = total.toFixed(2);
        } else {
            display.innerHTML = "<span style='color:red'>End date must be after start date!</span>";
            hiddenInput.value = "";
        }
    }
}

// Attach event listeners when the DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const inputs = [document.getElementById('car_id'), document.getElementById('start_date'), document.getElementById('end_date')];
    inputs.forEach(input => {
        input.addEventListener('change', calculateRental);
    });
});