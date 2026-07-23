<?php
require_once 'header.php';
require_once '../function.php';

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = intval($_SESSION['login_id']);

// Helper to get setting
function getCommissionSetting($conn, $key, $default = '') {
    $key = mysqli_real_escape_string($conn, $key);
    $result = mysqli_query($conn, "SELECT setting_value FROM commission_settings WHERE setting_key = '$key'");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return $default;
}

// Helper to set setting
function setCommissionSetting($conn, $key, $value, $user_id) {
    $key = mysqli_real_escape_string($conn, $key);
    $value = mysqli_real_escape_string($conn, $value);
    return mysqli_query($conn, "
        INSERT INTO commission_settings (setting_key, setting_value, updated_by) 
        VALUES ('$key', '$value', $user_id)
        ON DUPLICATE KEY UPDATE setting_value = '$value', updated_by = $user_id
    ");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rules'])) {
    $settings = [
        'virtual_fee_collection_threshold' => floatval($_POST['virtual_fee_threshold'] ?? 80),
        'virtual_client_payment_threshold' => floatval($_POST['virtual_client_threshold'] ?? 80),
        'international_fee_collection_threshold' => floatval($_POST['intl_fee_threshold'] ?? 90),
        'international_client_payment_threshold' => floatval($_POST['intl_client_threshold'] ?? 100),
    ];
    
    foreach ($settings as $key => $value) {
        setCommissionSetting($conn, $key, $value, $current_user_id);
    }
    
    // Log the action
    mysqli_query($conn, "
        INSERT INTO commission_audit_log (action, entity_type, details, performed_by, created_at)
        VALUES ('settings_updated', 'settings', '" . mysqli_real_escape_string($conn, json_encode($settings)) . "', $current_user_id, NOW())
    ");
    
    header("Location: commission_rules.php?saved=1");
    exit;
}

// Get current settings
$virtual_fee = getCommissionSetting($conn, 'virtual_fee_collection_threshold', '80');
$virtual_client = getCommissionSetting($conn, 'virtual_client_payment_threshold', '80');
$intl_fee = getCommissionSetting($conn, 'international_fee_collection_threshold', '90');
$intl_client = getCommissionSetting($conn, 'international_client_payment_threshold', '100');
?>

<style>
.rule-card {
    transition: all 0.3s;
    border-left: 4px solid transparent;
}
.rule-card.virtual { border-left-color: #0d6efd; }
.rule-card.international { border-left-color: #dc3545; }
.threshold-input {
    font-size: 1.5rem;
    font-weight: bold;
    text-align: center;
    width: 100px;
}
.info-box {
    background: #e8f4fd;
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
}
.info-box.intl { background: #fdecea; }
.formula-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    font-family: monospace;
}
</style>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fas fa-cogs me-2"></i>Commission Rules</h4>
                    <p class="text-muted mb-0">Configure global eligibility thresholds for commission</p>
                </div>
                <div>
                    <a href="intake_assignments.php" class="btn btn-primary me-2">
                        <i class="fas fa-laptop me-1"></i>Intake Assignments
                    </a>
                    <a href="event_assignments.php" class="btn btn-danger">
                        <i class="fas fa-globe me-1"></i>Event Assignments
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>Commission rules updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row">
                    <!-- Virtual Courses Rules -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm border-0 rule-card virtual h-100">
                            <div class="card-header bg-primary text-white py-3">
                                <h5 class="mb-0"><i class="fas fa-laptop me-2"></i>Virtual Courses (Intakes)</h5>
                            </div>
                            <div class="card-body">
                                <!-- Fee Collection Threshold -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-percentage me-1"></i>Fee Collection Threshold
                                    </label>
                                    <p class="text-muted small mb-2">Minimum overall fee collection % required for eligibility</p>
                                    <div class="input-group" style="width: 150px;">
                                        <input type="number" class="form-control threshold-input" 
                                               name="virtual_fee_threshold" 
                                               value="<?php echo $virtual_fee; ?>"
                                               min="0" max="100" step="1" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                
                                <!-- Client Payment Threshold -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-user-check me-1"></i>Client Payment Threshold
                                    </label>
                                    <p class="text-muted small mb-2">Minimum % of course fee a client must pay to count as "qualifying"</p>
                                    <div class="input-group" style="width: 150px;">
                                        <input type="number" class="form-control threshold-input" 
                                               name="virtual_client_threshold" 
                                               value="<?php echo $virtual_client; ?>"
                                               min="0" max="100" step="1" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                
                                <div class="info-box">
                                    <h6><i class="fas fa-info-circle text-primary me-1"></i>How it works:</h6>
                                    <ul class="small mb-0">
                                        <li>Staff must collect ≥<strong><?php echo $virtual_fee; ?>%</strong> of expected fees</li>
                                        <li>A client "counts" if they pay ≥<strong><?php echo $virtual_client; ?>%</strong> of course fee</li>
                                        <li>Must meet <strong>both</strong> conditions + minimum clients</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- International Events Rules -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm border-0 rule-card international h-100">
                            <div class="card-header bg-danger text-white py-3">
                                <h5 class="mb-0"><i class="fas fa-globe me-2"></i>International Events</h5>
                            </div>
                            <div class="card-body">
                                <!-- Fee Collection Threshold -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-percentage me-1"></i>Fee Collection Threshold
                                    </label>
                                    <p class="text-muted small mb-2">Minimum overall fee collection % required for eligibility</p>
                                    <div class="input-group" style="width: 150px;">
                                        <input type="number" class="form-control threshold-input" 
                                               name="intl_fee_threshold" 
                                               value="<?php echo $intl_fee; ?>"
                                               min="0" max="100" step="1" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                
                                <!-- Client Payment Threshold -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-user-check me-1"></i>Client Payment Threshold
                                    </label>
                                    <p class="text-muted small mb-2">Minimum % of event fee a client must pay to count as "qualifying"</p>
                                    <div class="input-group" style="width: 150px;">
                                        <input type="number" class="form-control threshold-input" 
                                               name="intl_client_threshold" 
                                               value="<?php echo $intl_client; ?>"
                                               min="0" max="100" step="1" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                
                                <div class="info-box intl">
                                    <h6><i class="fas fa-info-circle text-danger me-1"></i>How it works:</h6>
                                    <ul class="small mb-0">
                                        <li>Staff must collect ≥<strong><?php echo $intl_fee; ?>%</strong> of expected fees</li>
                                        <li>A client "counts" if they pay ≥<strong><?php echo $intl_client; ?>%</strong> of event fee</li>
                                        <li>Must meet <strong>both</strong> conditions + minimum clients</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="text-end mb-4">
                    <button type="submit" name="save_rules" class="btn btn-success btn-lg">
                        <i class="fas fa-save me-2"></i>Save Commission Rules
                    </button>
                </div>
            </form>

            <!-- Commission Formula Explanation -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Commission Calculation Formula</h5>
                </div>
                <div class="card-body">
                    <div class="formula-box mb-4">
                        <h6 class="text-primary mb-3">Eligibility Check (Both Must Pass):</h6>
                        <div class="mb-2">1. <strong>Fee Collection %</strong> ≥ Threshold (<?php echo $virtual_fee; ?>% Virtual / <?php echo $intl_fee; ?>% International)</div>
                        <div class="mb-3">2. <strong>Qualifying Clients</strong> ≥ Minimum Target (set per intake/event)</div>
                        
                        <h6 class="text-success mb-3">If Eligible:</h6>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <strong>Commission = (Fee × Rate%) × Qualifying Clients</strong>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="fas fa-laptop me-2"></i>Virtual Example</h6>
                            <table class="table table-sm">
                                <tr><td>Course Fee:</td><td><strong>USD 1,000</strong></td></tr>
                                <tr><td>Commission Rate:</td><td><strong>5%</strong></td></tr>
                                <tr><td>Per Client:</td><td>USD 1,000 × 5% = <strong>USD 50</strong></td></tr>
                                <tr><td>Qualifying Clients:</td><td><strong>40</strong></td></tr>
                                <tr class="table-success"><td><strong>Commission:</strong></td><td><strong>USD 50 × 40 = USD 2,000</strong></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-danger"><i class="fas fa-globe me-2"></i>International Example</h6>
                            <table class="table table-sm">
                                <tr><td>Event Fee:</td><td><strong>USD 500</strong></td></tr>
                                <tr><td>Commission Rate:</td><td><strong>10%</strong></td></tr>
                                <tr><td>Per Client:</td><td>USD 500 × 10% = <strong>USD 50</strong></td></tr>
                                <tr><td>Qualifying Clients:</td><td><strong>60</strong></td></tr>
                                <tr class="table-success"><td><strong>Commission:</strong></td><td><strong>USD 50 × 60 = USD 3,000</strong></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
