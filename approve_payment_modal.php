<!-- Approve Payment Modal -->
<div class="modal fade" id="approvePaymentModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="approvePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="approvePaymentModalLabel">
                    Record Payment
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body">
                <form id="approvePaymentForm" method="POST">
                    <!-- Hidden fields for client context -->
                    <input type="hidden" name="payment_source_type" id="payment_source_type" value="<?php echo htmlspecialchars($type); ?>">
                    <input type="hidden" name="payment_source_id" id="payment_source_id" value="<?php echo htmlspecialchars($enquiry['reference']); ?>">
                    <input type="hidden" name="payment_record_id" id="payment_record_id" value="<?php echo htmlspecialchars($enquiry['id'] ?? ''); ?>">
                    <input type="hidden" name="payment_email" id="payment_email" value="<?php echo htmlspecialchars($enquiry['email']); ?>">
                    <input type="hidden" name="payment_client_name" id="payment_client_name" value="<?php echo htmlspecialchars($enquiry['fullname']); ?>">
                    <?php if ($type == 'register'): ?>
                    <input type="hidden" name="payment_program" id="payment_program" value="<?php echo htmlspecialchars($enquiry['program']); ?>">
                    <input type="hidden" id="course_price" value="<?php echo floatval($enquiry['price_usd'] ?? 0); ?>">
                    <?php elseif ($type == 'ticket_congress'): ?>
                    <input type="hidden" name="payment_event_id" id="payment_event_id" value="<?php echo htmlspecialchars($enquiry['event_id']); ?>">
                    <input type="hidden" id="course_price" value="<?php echo floatval($enquiry['early_amount'] ?? 0); ?>">
                    <?php endif; ?>

                    <!-- Client Info Display -->
                    <div class="alert alert-info mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Client:</strong> <?php echo htmlspecialchars($enquiry['fullname']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Email:</strong> <?php echo htmlspecialchars($enquiry['email']); ?>
                            </div>
                        </div>
                        <div class="row mt-1">
                            <div class="col-md-6">
                                <strong>Program:</strong> 
                                <?php 
                                if ($type == 'register') {
                                    echo htmlspecialchars($enquiry['program_name'] ?? 'N/A');
                                } else {
                                    echo htmlspecialchars($enquiry['event_name'] ?? 'N/A');
                                }
                                ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Amount Due:</strong> $<?php 
                                if ($type == 'register') {
                                    echo number_format($enquiry['price_usd'] ?? 0, 2);
                                } else {
                                    echo number_format($enquiry['early_amount'] ?? 0, 2);
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Payment Method -->
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">
                                    Payment <span class="text-danger">*</span>
                                </span>
                                <select class="form-control rounded-0" name="payment_method" id="payment_method" required>
                                    <option value="" disabled selected>Select payment method</option>
                                    <option value="MP-code">M-Pesa (Benson)</option>
                                    <option value="TILL-code">Till Number Payments</option>
                                    <option value="WU-MTCN">Western Union</option>
                                    <option value="Mukuru-code">Mukuru</option>
                                    <option value="LocalRep-country">Local Representative</option>
                                    <option value="EQ-code">Equity Bank</option>
                                    <option value="EC-bank">Echo Bank</option>
                                    <option value="AM-code">Airtel Money</option>
                                    <option value="Dahabsh-code">Dahabshil</option>
                                    <option value="DPO">DPO</option>
                                    <option value="Money-gram">Money gram</option>
                                    <option value="Ria">Ria</option>
                                </select>
                            </div>
                        </div>

                        <!-- Confirmation Code -->
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text rounded-0 bg_main" style="width: 8rem;">Confirmation</span>
                                <input type="text" class="form-control rounded-0" name="confirmation" id="confirmation" placeholder="Transaction code">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Currency Selection -->
                        <div class="col-md-4">
                            <div class="input-group mb-3">
                                <span class="input-group-text rounded-0 bg_main" style="width: 6rem;">Currency</span>
                                <select class="form-control rounded-0" name="currency" id="currency" required onchange="calculatePaymentTotal()">
                                    <option value="USD" selected>USD ($)</option>
                                    <option value="KES">KES (KSh)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="col-md-4">
                            <div class="input-group mb-3">
                                <span class="input-group-text rounded-0 bg_main" style="width: 6rem;">
                                    Amount <span class="text-danger">*</span>
                                </span>
                                <input type="number" step="0.01" min="0" class="form-control rounded-0" name="amount" id="amount" placeholder="Enter amount" required oninput="calculatePaymentTotal()">
                            </div>
                        </div>

                        <!-- Date -->
                        <div class="col-md-4">
                            <div class="input-group mb-3">
                                <span class="input-group-text rounded-0 bg_main" style="width: 5rem;">
                                    Date <span class="text-danger">*</span>
                                </span>
                                <input type="date" class="form-control rounded-0" name="payment_date" id="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Converted Amount Display -->
                    <div class="row mb-3" id="converted_amount_row" style="display: none;">
                        <div class="col-12">
                            <div class="alert alert-warning py-2 mb-0">
                                <i class="bi bi-currency-exchange"></i>
                                <span id="conversion_text"></span>
                                <small class="text-muted">(Rate: 1 USD = 129 KES)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Discount Section -->
                    <div class="card mb-3 rounded-0">
                        <div class="card-header bg-light rounded-0 py-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="apply_discount" name="apply_discount" onchange="toggleDiscountSection()">
                                <label class="form-check-label" for="apply_discount">
                                    <strong>Apply Discount</strong>
                                </label>
                            </div>
                        </div>
                        <div class="card-body py-2" id="discount_section" style="display: none;">
                            <div class="row">
                                <!-- Discount Type -->
                                <div class="col-md-4">
                                    <div class="input-group mb-2">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 6rem;">Type</span>
                                        <select class="form-control rounded-0" name="discount_type" id="discount_type" onchange="updateDiscountSymbol(); calculatePaymentTotal();">
                                            <option value="fixed" selected>Fixed ($)</option>
                                            <option value="percentage">Percentage (%)</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Discount Value -->
                                <div class="col-md-4">
                                    <div class="input-group mb-2">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 6rem;">Value</span>
                                        <input type="number" step="0.01" min="0" class="form-control rounded-0" name="discount_value" id="discount_value" placeholder="0" oninput="calculatePaymentTotal()">
                                        <span class="input-group-text rounded-0" id="discount_symbol">$</span>
                                    </div>
                                </div>

                                <!-- Discount Reason -->
                                <div class="col-md-4">
                                    <div class="input-group mb-2">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 6rem;">Reason</span>
                                        <input type="text" class="form-control rounded-0" name="discount_reason" id="discount_reason" placeholder="e.g., Early bird">
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info py-1 mb-0" id="discount_calculation" style="display: none;">
                                <small id="discount_calc_text"></small>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Summary -->
                    <div class="card mb-3 rounded-0 border-success">
                        <div class="card-header bg-success text-white rounded-0 py-2">
                            <strong><i class="bi bi-calculator"></i> Payment Summary</strong>
                        </div>
                        <div class="card-body py-2">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td>Amount Received:</td>
                                    <td class="text-end"><strong id="summary_amount_received">$0.00</strong></td>
                                </tr>
                                <tr id="summary_discount_row" style="display: none;">
                                    <td>Discount Applied:</td>
                                    <td class="text-end text-success"><strong id="summary_discount">+$0.00</strong></td>
                                </tr>
                                <tr class="border-top">
                                    <td><strong>Total Credit to Client:</strong></td>
                                    <td class="text-end"><strong class="text-primary fs-5" id="summary_total">$0.00</strong></td>
                                </tr>
                            </table>
                            <input type="hidden" name="final_amount_usd" id="final_amount_usd" value="0">
                            <input type="hidden" name="discount_amount_usd" id="discount_amount_usd" value="0">
                        </div>
                    </div>

                    <div class="w-100 d-flex">
                        <div class="w-50">
                            <button type="button" class="btn btn-danger rounded-0" data-bs-dismiss="modal" aria-label="Close">
                                <i class="bi bi-x-lg"></i> Cancel
                            </button>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button type="submit" class="btn btn-success rounded-0" id="submitPaymentBtn">
                                <i class="bi bi-check2"></i> Record Payment
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Constants
const EXCHANGE_RATE = 129;

// Toggle discount section
function toggleDiscountSection() {
    var checkbox = document.getElementById('apply_discount');
    var section = document.getElementById('discount_section');
    
    if (checkbox.checked) {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
        document.getElementById('discount_value').value = '';
        calculatePaymentTotal();
    }
}

// Update discount symbol based on type
function updateDiscountSymbol() {
    var discountType = document.getElementById('discount_type').value;
    var symbol = document.getElementById('discount_symbol');
    
    if (discountType === 'percentage') {
        symbol.textContent = '%';
    } else {
        symbol.textContent = '$';
    }
}

// Format number with commas
function formatNumber(num) {
    return parseFloat(num).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Main calculation function
function calculatePaymentTotal() {
    var amount = parseFloat(document.getElementById('amount').value) || 0;
    var currency = document.getElementById('currency').value;
    var amountUSD = amount;
    
    // Convert KES to USD if needed
    if (currency === 'KES' && amount > 0) {
        amountUSD = amount / EXCHANGE_RATE;
        document.getElementById('converted_amount_row').style.display = 'block';
        document.getElementById('conversion_text').innerHTML = 
            '<strong>KES ' + formatNumber(amount) + '</strong> = <strong>$' + formatNumber(amountUSD) + ' USD</strong>';
    } else {
        document.getElementById('converted_amount_row').style.display = 'none';
    }
    
    // Calculate discount
    var discountUSD = 0;
    var applyDiscount = document.getElementById('apply_discount').checked;
    
    if (applyDiscount) {
        var discountValue = parseFloat(document.getElementById('discount_value').value) || 0;
        var discountType = document.getElementById('discount_type').value;
        var coursePrice = parseFloat(document.getElementById('course_price').value) || 0;
        
        if (discountType === 'percentage') {
            // Percentage of course price
            discountUSD = (coursePrice * discountValue) / 100;
            document.getElementById('discount_calculation').style.display = 'block';
            document.getElementById('discount_calc_text').innerHTML = 
                discountValue + '% of $' + formatNumber(coursePrice) + ' = <strong>$' + formatNumber(discountUSD) + ' discount</strong>';
        } else {
            // Fixed amount
            discountUSD = discountValue;
            if (discountValue > 0) {
                document.getElementById('discount_calculation').style.display = 'block';
                document.getElementById('discount_calc_text').innerHTML = 
                    'Fixed discount: <strong>$' + formatNumber(discountUSD) + '</strong>';
            } else {
                document.getElementById('discount_calculation').style.display = 'none';
            }
        }
    } else {
        document.getElementById('discount_calculation').style.display = 'none';
    }
    
    // Update summary
    document.getElementById('summary_amount_received').textContent = '$' + formatNumber(amountUSD);
    
    if (discountUSD > 0) {
        document.getElementById('summary_discount_row').style.display = 'table-row';
        document.getElementById('summary_discount').textContent = '+$' + formatNumber(discountUSD);
    } else {
        document.getElementById('summary_discount_row').style.display = 'none';
    }
    
    var totalCredit = amountUSD + discountUSD;
    document.getElementById('summary_total').textContent = '$' + formatNumber(totalCredit);
    
    // Set hidden fields
    document.getElementById('final_amount_usd').value = totalCredit.toFixed(2);
    document.getElementById('discount_amount_usd').value = discountUSD.toFixed(2);
}

// Form submission
document.getElementById('approvePaymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var btn = document.getElementById('submitPaymentBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
    
    var formData = new FormData(this);
    
    fetch('includes/process_payment.inc.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.text();
    })
    .then(function(responseText) {
        console.log('Response:', responseText);
        try {
            var data = JSON.parse(responseText);
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message || 'Payment recorded successfully.',
                    confirmButtonText: 'OK'
                }).then(function() {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to record payment.'
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2"></i> Record Payment';
            }
        } catch (e) {
            console.log('Parse error:', e);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An unexpected error occurred. Check console for details.'
            });
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2"></i> Record Payment';
        }
    })
    .catch(function(error) {
        console.log('Fetch error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to connect to server.'
        });
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2"></i> Record Payment';
    });
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Reset form when modal opens
    var modal = document.getElementById('approvePaymentModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function() {
            document.getElementById('approvePaymentForm').reset();
            document.getElementById('payment_date').value = '<?php echo date("Y-m-d"); ?>';
            document.getElementById('discount_section').style.display = 'none';
            document.getElementById('converted_amount_row').style.display = 'none';
            document.getElementById('discount_calculation').style.display = 'none';
            document.getElementById('summary_discount_row').style.display = 'none';
            document.getElementById('summary_amount_received').textContent = '$0.00';
            document.getElementById('summary_total').textContent = '$0.00';
            document.getElementById('discount_symbol').textContent = '$';
        });
    }
});
</script>