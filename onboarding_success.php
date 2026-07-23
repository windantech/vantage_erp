<?php
session_start();

// Check if coming from successful submission
if (!isset($_SESSION['onboarding_success']) || $_SESSION['onboarding_success'] !== true) {
    header('Location: staff_onboarding.php');
    exit;
}

$staff_id = $_SESSION['onboarding_staff_id'] ?? '';
$name = $_SESSION['onboarding_name'] ?? '';
$email = $_SESSION['onboarding_email'] ?? '';

// Clear session data
unset($_SESSION['onboarding_success']);
unset($_SESSION['onboarding_staff_id']);
unset($_SESSION['onboarding_name']);
unset($_SESSION['onboarding_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Successful | Vantage Africa School Of Leadership</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #1a3a5c;
            --secondary-color: #c9a227;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .success-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 550px;
            width: 100%;
            margin: 20px;
            overflow: hidden;
        }
        
        .success-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .success-body {
            padding: 30px;
        }
        
        .info-item {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            width: 120px;
            flex-shrink: 0;
        }
        
        .info-value {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .staff-id-box {
            background: #f8f9fa;
            border: 2px dashed var(--secondary-color);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        
        .staff-id-box label {
            display: block;
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .staff-id-box .staff-id {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            letter-spacing: 2px;
        }
        
        .next-steps {
            background: #e7f3ff;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .next-steps h6 {
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .next-steps ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .next-steps li {
            margin-bottom: 8px;
            color: #555;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: #0d2840;
            border-color: #0d2840;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-header">
            <div class="success-icon">
                <i class="bi bi-check-lg"></i>
            </div>
            <h3 class="mb-2">Submission Successful!</h3>
            <p class="mb-0 opacity-75">Your onboarding form has been received</p>
        </div>
        
        <div class="success-body">
            <div class="staff-id-box">
                <label>Your Staff Reference ID</label>
                <div class="staff-id"><?php echo htmlspecialchars($staff_id); ?></div>
                <small class="text-muted">Please save this for your records</small>
            </div>
            
            <div class="info-item">
                <span class="info-label"><i class="bi bi-person me-2"></i>Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($name); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label"><i class="bi bi-envelope me-2"></i>Email:</span>
                <span class="info-value"><?php echo htmlspecialchars($email); ?></span>
            </div>
            
            <div class="next-steps">
                <h6><i class="bi bi-info-circle me-2"></i>What happens next?</h6>
                <ul>
                    <li>Our HR team will review your submission</li>
                    <li>You will receive a confirmation email shortly</li>
                    
                    <li>Employment details will be completed by the admin team</li>
                </ul>
            </div>
            
            <div class="d-grid gap-2 mt-4">
                <a href="staff_onboarding.html" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Form
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>