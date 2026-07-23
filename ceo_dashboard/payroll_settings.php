<?php
session_start();
require_once 'header.php';

$success_message = '';
$error_message = '';
$current_user_id = intval($_SESSION['login_id'] ?? 1);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $settings_to_update = [
        'PERSONAL_RELIEF' => floatval($_POST['personal_relief']),
        'NSSF_TIER1_LIMIT' => floatval($_POST['nssf_tier1_limit']),
        'NSSF_TIER2_LIMIT' => floatval($_POST['nssf_tier2_limit']),
        'NSSF_RATE' => floatval($_POST['nssf_rate']),
        'SHIF_RATE' => floatval($_POST['shif_rate']),
        'HOUSING_LEVY_RATE' => floatval($_POST['housing_levy_rate']),
        'INSURANCE_RELIEF_RATE' => floatval($_POST['insurance_relief_rate']),
        'INSURANCE_RELIEF_MAX' => floatval($_POST['insurance_relief_max']),
        'OVERTIME_RATE_NORMAL' => floatval($_POST['overtime_rate_normal']),
        'OVERTIME_RATE_WEEKEND' => floatval($_POST['overtime_rate_weekend']),
        'OVERTIME_RATE_HOLIDAY' => floatval($_POST['overtime_rate_holiday']),
        'STANDARD_WORKING_HOURS' => floatval($_POST['standard_working_hours']),
        'STANDARD_WORKING_DAYS' => floatval($_POST['standard_working_days'])
    ];
    
    $updated = 0;
    foreach ($settings_to_update as $key => $value) {
        $key = mysqli_real_escape_string($conn, $key);
        $sql = "UPDATE payroll_settings SET setting_value = '$value', updated_by = $current_user_id, updated_at = NOW() WHERE setting_key = '$key'";
        if (mysqli_query($conn, $sql)) {
            $updated++;
        }
    }
    
    // Handle PAYE Tax Bands
    if (isset($_POST['paye_bands'])) {
        $bands = [];
        foreach ($_POST['paye_bands'] as $band) {
            if (!empty($band['min']) || $band['min'] === '0') {
                $bands[] = [
                    'min' => floatval($band['min']),
                    'max' => $band['max'] !== '' ? floatval($band['max']) : 999999999,
                    'rate' => floatval($band['rate'])
                ];
            }
        }
        $bands_json = mysqli_real_escape_string($conn, json_encode($bands));
        mysqli_query($conn, "UPDATE payroll_settings SET setting_value = '$bands_json', updated_by = $current_user_id WHERE setting_key = 'PAYE_TAX_BANDS'");
    }
    
    $success_message = "Payroll settings updated successfully!";
}

// Get all settings
$settings = [];
$result = mysqli_query($conn, "SELECT * FROM payroll_settings WHERE is_active = 1 ORDER BY id");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row;
    }
}

// Parse PAYE bands
$paye_bands = [];
if (isset($settings['PAYE_TAX_BANDS'])) {
    $paye_bands = json_decode($settings['PAYE_TAX_BANDS']['setting_value'], true) ?: [];
}

// Helper function to get setting value
function getSetting($settings, $key, $default = 0) {
    return isset($settings[$key]) ? $settings[$key]['setting_value'] : $default;
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        
        <div class="container-fluid mt-5 pt-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fas fa-cogs me-2"></i>Payroll Settings</h4>
                    <p class="text-muted mb-0">Configure tax rates, reliefs, and statutory deductions (Kenya 2024/2025)</p>
                </div>
            </div>
            
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-0">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-0">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="row">
                    <!-- PAYE Tax Bands -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm rounded-0 h-100">
                            <div class="card-header bg-danger text-white rounded-0">
                                <i class="fas fa-percentage me-2"></i>PAYE Tax Bands (Monthly)
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">Progressive tax rates based on monthly taxable income</p>
                                
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Min (KES)</th>
                                            <th>Max (KES)</th>
                                            <th>Rate (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="payeBands">
                                        <?php foreach ($paye_bands as $i => $band): ?>
                                        <tr>
                                            <td>
                                                <input type="number" class="form-control form-control-sm rounded-0" 
                                                       name="paye_bands[<?php echo $i; ?>][min]" 
                                                       value="<?php echo $band['min']; ?>" min="0">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm rounded-0" 
                                                       name="paye_bands[<?php echo $i; ?>][max]" 
                                                       value="<?php echo ($band['max'] < 999999999) ? $band['max'] : ''; ?>" 
                                                       placeholder="No limit">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm rounded-0" 
                                                       name="paye_bands[<?php echo $i; ?>][rate]" 
                                                       value="<?php echo $band['rate']; ?>" min="0" max="100" step="0.5">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (count($paye_bands) < 5): ?>
                                        <?php for ($i = count($paye_bands); $i < 5; $i++): ?>
                                        <tr>
                                            <td><input type="number" class="form-control form-control-sm rounded-0" name="paye_bands[<?php echo $i; ?>][min]" placeholder="Min"></td>
                                            <td><input type="number" class="form-control form-control-sm rounded-0" name="paye_bands[<?php echo $i; ?>][max]" placeholder="Max"></td>
                                            <td><input type="number" class="form-control form-control-sm rounded-0" name="paye_bands[<?php echo $i; ?>][rate]" placeholder="Rate"></td>
                                        </tr>
                                        <?php endfor; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Personal Relief (KES/month)</label>
                                        <input type="number" class="form-control rounded-0" name="personal_relief" 
                                               value="<?php echo getSetting($settings, 'PERSONAL_RELIEF', 2400); ?>" min="0" step="0.01">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Insurance Relief Rate (%)</label>
                                        <input type="number" class="form-control rounded-0" name="insurance_relief_rate" 
                                               value="<?php echo getSetting($settings, 'INSURANCE_RELIEF_RATE', 15); ?>" min="0" max="100" step="0.01">
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Max Insurance Relief (KES/month)</label>
                                    <input type="number" class="form-control rounded-0" name="insurance_relief_max" 
                                           value="<?php echo getSetting($settings, 'INSURANCE_RELIEF_MAX', 5000); ?>" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- NSSF Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm rounded-0">
                            <div class="card-header bg-primary text-white rounded-0">
                                <i class="fas fa-shield-alt me-2"></i>NSSF (National Social Security Fund)
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">New NSSF Act - Tiered contributions</p>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tier I Limit (KES)</label>
                                        <input type="number" class="form-control rounded-0" name="nssf_tier1_limit" 
                                               value="<?php echo getSetting($settings, 'NSSF_TIER1_LIMIT', 7000); ?>" min="0">
                                        <small class="text-muted">Lower earnings band</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tier II Limit (KES)</label>
                                        <input type="number" class="form-control rounded-0" name="nssf_tier2_limit" 
                                               value="<?php echo getSetting($settings, 'NSSF_TIER2_LIMIT', 36000); ?>" min="0">
                                        <small class="text-muted">Upper earnings band</small>
                                    </div>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Contribution Rate (%)</label>
                                    <input type="number" class="form-control rounded-0" name="nssf_rate" 
                                           value="<?php echo getSetting($settings, 'NSSF_RATE', 6); ?>" min="0" max="100" step="0.01">
                                    <small class="text-muted">Same rate for employee & employer</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SHIF Settings -->
                        <div class="card shadow-sm rounded-0 mt-4">
                            <div class="card-header bg-success text-white rounded-0">
                                <i class="fas fa-heartbeat me-2"></i>SHIF (Social Health Insurance Fund)
                            </div>
                            <div class="card-body">
                                <div class="mb-0">
                                    <label class="form-label">SHIF Rate (% of Gross)</label>
                                    <input type="number" class="form-control rounded-0" name="shif_rate" 
                                           value="<?php echo getSetting($settings, 'SHIF_RATE', 2.75); ?>" min="0" max="100" step="0.01">
                                    <small class="text-muted">Replaces old NHIF contributions</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Housing Levy -->
                        <div class="card shadow-sm rounded-0 mt-4">
                            <div class="card-header bg-warning text-dark rounded-0">
                                <i class="fas fa-home me-2"></i>Affordable Housing Levy
                            </div>
                            <div class="card-body">
                                <div class="mb-0">
                                    <label class="form-label">Housing Levy Rate (%)</label>
                                    <input type="number" class="form-control rounded-0" name="housing_levy_rate" 
                                           value="<?php echo getSetting($settings, 'HOUSING_LEVY_RATE', 1.5); ?>" min="0" max="100" step="0.01">
                                    <small class="text-muted">Employee 1.5% + Employer 1.5%</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Overtime Rates -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm rounded-0">
                            <div class="card-header bg-info text-white rounded-0">
                                <i class="fas fa-clock me-2"></i>Overtime Rates
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Normal OT (x)</label>
                                        <input type="number" class="form-control rounded-0" name="overtime_rate_normal" 
                                               value="<?php echo getSetting($settings, 'OVERTIME_RATE_NORMAL', 1.5); ?>" min="1" step="0.1">
                                        <small class="text-muted">Weekday evenings</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Weekend OT (x)</label>
                                        <input type="number" class="form-control rounded-0" name="overtime_rate_weekend" 
                                               value="<?php echo getSetting($settings, 'OVERTIME_RATE_WEEKEND', 2); ?>" min="1" step="0.1">
                                        <small class="text-muted">Sat & Sun</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Holiday OT (x)</label>
                                        <input type="number" class="form-control rounded-0" name="overtime_rate_holiday" 
                                               value="<?php echo getSetting($settings, 'OVERTIME_RATE_HOLIDAY', 2); ?>" min="1" step="0.1">
                                        <small class="text-muted">Public holidays</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Working Hours -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm rounded-0">
                            <div class="card-header bg-secondary text-white rounded-0">
                                <i class="fas fa-business-time me-2"></i>Standard Working Time
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Working Hours/Month</label>
                                        <input type="number" class="form-control rounded-0" name="standard_working_hours" 
                                               value="<?php echo getSetting($settings, 'STANDARD_WORKING_HOURS', 176); ?>" min="1">
                                        <small class="text-muted">For hourly rate calculation</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Working Days/Month</label>
                                        <input type="number" class="form-control rounded-0" name="standard_working_days" 
                                               value="<?php echo getSetting($settings, 'STANDARD_WORKING_DAYS', 22); ?>" min="1">
                                        <small class="text-muted">For daily rate calculation</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="card shadow-sm rounded-0 mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Changes will apply to future payroll calculations. Existing processed payrolls will not be affected.
                                </p>
                            </div>
                            <button type="submit" name="save_settings" class="btn btn-primary btn-lg rounded-0">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Quick Reference -->
            <div class="card shadow-sm rounded-0 mb-4">
                <div class="card-header bg-light rounded-0">
                    <i class="fas fa-info-circle me-2"></i>Kenya Tax Reference (2024/2025)
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>PAYE Tax Bands (Monthly)</h6>
                            <table class="table table-sm table-bordered">
                                <tr><td>0 - 24,000</td><td>10%</td></tr>
                                <tr><td>24,001 - 32,333</td><td>25%</td></tr>
                                <tr><td>32,334 - 500,000</td><td>30%</td></tr>
                                <tr><td>500,001 - 800,000</td><td>32.5%</td></tr>
                                <tr><td>Above 800,000</td><td>35%</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Statutory Deductions</h6>
                            <table class="table table-sm table-bordered">
                                <tr><td>Personal Relief</td><td>KES 2,400/month</td></tr>
                                <tr><td>NSSF Rate</td><td>6% (Tiered)</td></tr>
                                <tr><td>SHIF Rate</td><td>2.75% of Gross</td></tr>
                                <tr><td>Housing Levy</td><td>1.5% + 1.5%</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>