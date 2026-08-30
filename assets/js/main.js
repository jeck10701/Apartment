/**
 * ResiPro Apartment Management System - Main JavaScript
 * Handles dynamic UI interactions, real-time calculations, and modal logic
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Auto-dismiss flash alerts after 5 seconds
    const flashAlerts = document.querySelectorAll('.alert-dismissible');
    flashAlerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });

    // 2. Generic client-side table filter/search
    const tableSearchInputs = document.querySelectorAll('[data-table-search]');
    tableSearchInputs.forEach(function (input) {
        const targetTableId = input.getAttribute('data-table-search');
        const targetTable = document.getElementById(targetTableId);

        if (targetTable) {
            input.addEventListener('keyup', function () {
                const filterValue = this.value.toLowerCase().trim();
                const rows = targetTable.querySelectorAll('tbody tr');

                rows.forEach(function (row) {
                    const text = row.textContent.toLowerCase();
                    if (text.indexOf(filterValue) > -1) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    });

    // 3. Sub-meter Utility Calculation (Live Math)
    const prevWaterInput = document.getElementById('prev_water_reading');
    const currWaterInput = document.getElementById('curr_water_reading');
    const waterRateInput = document.getElementById('water_rate');
    const waterTotalDisplay = document.getElementById('water_total_cost');

    const prevElecInput = document.getElementById('prev_electric_reading');
    const currElecInput = document.getElementById('curr_electric_reading');
    const elecRateInput = document.getElementById('electric_rate');
    const elecTotalDisplay = document.getElementById('electric_total_cost');

    const grandTotalDisplay = document.getElementById('utility_grand_total');

    function calculateUtilities() {
        let waterCost = 0;
        let elecCost = 0;

        if (currWaterInput && prevWaterInput && waterRateInput) {
            const prevW = parseFloat(prevWaterInput.value) || 0;
            const currW = parseFloat(currWaterInput.value) || 0;
            const rateW = parseFloat(waterRateInput.value) || 0;

            const waterConsumption = Math.max(0, currW - prevW);
            waterCost = waterConsumption * rateW;

            const consumptionBadge = document.getElementById('water_consumption_badge');
            if (consumptionBadge) {
                consumptionBadge.textContent = waterConsumption.toFixed(2) + ' cu.m';
            }
            if (waterTotalDisplay) {
                waterTotalDisplay.textContent = '₱ ' + waterCost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        if (currElecInput && prevElecInput && elecRateInput) {
            const prevE = parseFloat(prevElecInput.value) || 0;
            const currE = parseFloat(currElecInput.value) || 0;
            const rateE = parseFloat(elecRateInput.value) || 0;

            const elecConsumption = Math.max(0, currE - prevE);
            elecCost = elecConsumption * rateE;

            const consumptionBadge = document.getElementById('elec_consumption_badge');
            if (consumptionBadge) {
                consumptionBadge.textContent = elecConsumption.toFixed(2) + ' kWh';
            }
            if (elecTotalDisplay) {
                elecTotalDisplay.textContent = '₱ ' + elecCost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        if (grandTotalDisplay) {
            const total = waterCost + elecCost;
            grandTotalDisplay.textContent = '₱ ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }

    [prevWaterInput, currWaterInput, waterRateInput, prevElecInput, currElecInput, elecRateInput].forEach(function (el) {
        if (el) {
            el.addEventListener('input', calculateUtilities);
        }
    });

    // Run initial calculation once if elements are present
    if (currWaterInput || currElecInput) {
        calculateUtilities();
    }
});

/**
 * Confirm action with SweetAlert2 or browser fallback
 */
function confirmAction(message, onConfirm) {
    if (window.Swal) {
        Swal.fire({
            title: 'Are you sure?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, proceed',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                onConfirm();
            }
        });
    } else {
        if (confirm(message)) {
            onConfirm();
        }
    }
}
