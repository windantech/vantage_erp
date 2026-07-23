<?php
session_save_path('/home/vantage/php_sessions');
session_start();

// Get errors and preserved data
$errors = $_SESSION['onboarding_errors'] ?? [];
$form_data = $_SESSION['onboarding_data'] ?? [];

// Clear session after reading
unset($_SESSION['onboarding_errors']);
unset($_SESSION['onboarding_data']);

// Helper function to get old value
function old($field, $default = '') {
    global $form_data;
    return htmlspecialchars($form_data[$field] ?? $default);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Onboarding | Vantage Africa School Of Leadership</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #1a3a5c;
            --secondary-color: #c9a227;
            --light-bg: #f8f9fa;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .form-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-header {
            background: var(--primary-color);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        
        .form-header img {
            max-height: 80px;
            margin-bottom: 15px;
        }
        
        .form-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .form-body {
            background: white;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .section-title {
            background: var(--light-bg);
            padding: 12px 20px;
            margin: 25px -30px 20px -30px;
            border-left: 4px solid var(--secondary-color);
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title:first-of-type { margin-top: 0; }
        .section-title i { color: var(--secondary-color); }
        
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }
        
        .form-label .required { color: #dc3545; }
        
        .form-control, .form-select {
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.15);
        }
        
        .qualification-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .qualification-item .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
        }
        
        .qualification-number {
            background: var(--primary-color);
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            margin-right: 8px;
        }
        
        .add-qualification-btn {
            background: white;
            border: 2px dashed var(--secondary-color);
            color: var(--primary-color);
            padding: 15px;
            border-radius: 8px;
            width: 100%;
            font-weight: 500;
            cursor: pointer;
        }
        
        .add-qualification-btn:hover {
            background: rgba(201, 162, 39, 0.1);
        }
        
        .submit-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
        }
        
        .submit-btn:hover {
            background: #0d2840;
        }
        
        .photo-preview {
            width: 150px;
            height: 150px;
            border: 2px dashed #ddd;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8f9fa;
            margin-bottom: 10px;
        }
        
        .photo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .photo-preview .placeholder {
            color: #aaa;
            text-align: center;
        }
        
        .photo-preview .placeholder i {
            font-size: 3rem;
            display: block;
        }
        
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .error-box h6 {
            color: #721c24;
            margin-bottom: 10px;
        }
        
        .error-box ul {
            margin: 0;
            padding-left: 20px;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .form-container { padding: 10px; }
            .form-body { padding: 20px 15px; }
            .section-title {
                margin-left: -15px;
                margin-right: -15px;
                padding: 10px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container my-4">
        <!-- Header -->
        <div class="form-header">
            <img src="logo.png" alt="Vantage Africa Logo" onerror="this.style.display='none'">
            <h1>Vantage Africa School Of Leadership</h1>
            <p>Staff Onboarding Form</p>
        </div>
        
        <!-- Form Body -->
        <div class="form-body">
            
            <!-- Error Display -->
            <?php if (!empty($errors)): ?>
            <div class="error-box">
                <h6><i class="bi bi-exclamation-triangle me-2"></i>Please fix the following errors:</h6>
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form id="onboardingForm" action="process_onboarding.php" method="POST" enctype="multipart/form-data">
                
                <!-- Section 1: Personal Details -->
                <div class="section-title">
                    <i class="bi bi-person-circle"></i> Personal Details
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="text-center">
                            <div class="photo-preview mx-auto" id="photoPreview">
                                <div class="placeholder">
                                    <i class="bi bi-camera"></i>
                                    <small>Passport Photo</small>
                                </div>
                            </div>
                            <input type="file" class="form-control form-control-sm" id="passport_photo" name="passport_photo" accept="image/*" onchange="previewPhoto(this)">
                            <small class="form-text">Max 2MB, JPG/PNG</small>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Full Name (as per ID/Passport) <span class="required">*</span></label>
                                <input type="text" class="form-control" name="full_name" required value="<?php echo old('full_name'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth <span class="required">*</span></label>
                                <input type="date" class="form-control" name="date_of_birth" required value="<?php echo old('date_of_birth'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender <span class="required">*</span></label>
                                <select class="form-select" name="gender" required>
                                    <option value="">-- Select --</option>
                                    <option value="male" <?php echo old('gender') == 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo old('gender') == 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo old('gender') == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">National ID Number <span class="required">*</span></label>
                        <input type="text" class="form-control" name="national_id" required value="<?php echo old('national_id'); ?>">
                        <small class="form-text">Or passport number for non-citizens</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nationality <span class="required">*</span></label>
                        <input type="text" class="form-control" name="nationality" required value="<?php echo old('nationality', 'Kenyan'); ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Marital Status</label>
                        <select class="form-select" name="marital_status">
                            <option value="">-- Select --</option>
                            <option value="single" <?php echo old('marital_status') == 'single' ? 'selected' : ''; ?>>Single</option>
                            <option value="married" <?php echo old('marital_status') == 'married' ? 'selected' : ''; ?>>Married</option>
                            <option value="divorced" <?php echo old('marital_status') == 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                            <option value="widowed" <?php echo old('marital_status') == 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" class="form-control" name="email" required value="<?php echo old('email'); ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number <span class="required">*</span></label>
                        <input type="tel" class="form-control" name="phone" required value="<?php echo old('phone'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Alternative Phone</label>
                        <input type="tel" class="form-control" name="phone_alt" value="<?php echo old('phone_alt'); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Home Address <span class="required">*</span></label>
                    <textarea class="form-control" name="home_address" rows="2" required><?php echo old('home_address'); ?></textarea>
                </div>
                
                
                <!-- Section 2: Legal & Compliance -->
                <div class="section-title">
                    <i class="bi bi-shield-check"></i> Legal & Compliance Details
                </div>
                
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Important:</strong> These details are mandatory for all employees in Kenya.
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">KRA PIN <span class="required">*</span></label>
                        <input type="text" class="form-control" name="kra_pin" required style="text-transform: uppercase;" value="<?php echo old('kra_pin'); ?>">
                        <small class="form-text">Kenya Revenue Authority PIN</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">NSSF Number <span class="required">*</span></label>
                        <input type="text" class="form-control" name="nssf_number" required value="<?php echo old('nssf_number'); ?>">
                        <small class="form-text">National Social Security Fund</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">NHIF Number <span class="required">*</span></label>
                        <input type="text" class="form-control" name="nhif_number" required value="<?php echo old('nhif_number'); ?>">
                        <small class="form-text">National Hospital Insurance Fund</small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">National ID / Passport Copy <span class="required">*</span></label>
                        <input type="file" class="form-control" name="id_copy" required accept=".pdf,.jpg,.jpeg,.png">
                        <small class="form-text">PDF or Image, Max 5MB</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">KRA PIN Certificate <span class="required">*</span></label>
                        <input type="file" class="form-control" name="kra_certificate" required accept=".pdf,.jpg,.jpeg,.png">
                        <small class="form-text">PDF or Image, Max 5MB</small>
                    </div>
                </div>
                
                
                <!-- Section 3: Emergency Contact -->
                <div class="section-title">
                    <i class="bi bi-heart-pulse"></i> Emergency Contact & Next of Kin
                </div>
                
                <div class="alert alert-warning mb-4">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    This information is crucial for emergencies. Please provide accurate details.
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Next of Kin Full Name <span class="required">*</span></label>
                        <input type="text" class="form-control" name="nok_name" required value="<?php echo old('nok_name'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Relationship <span class="required">*</span></label>
                        <select class="form-select" name="nok_relationship" required>
                            <option value="">-- Select --</option>
                            <option value="spouse" <?php echo old('nok_relationship') == 'spouse' ? 'selected' : ''; ?>>Spouse</option>
                            <option value="parent" <?php echo old('nok_relationship') == 'parent' ? 'selected' : ''; ?>>Parent</option>
                            <option value="sibling" <?php echo old('nok_relationship') == 'sibling' ? 'selected' : ''; ?>>Sibling</option>
                            <option value="child" <?php echo old('nok_relationship') == 'child' ? 'selected' : ''; ?>>Child</option>
                            <option value="relative" <?php echo old('nok_relationship') == 'relative' ? 'selected' : ''; ?>>Other Relative</option>
                            <option value="friend" <?php echo old('nok_relationship') == 'friend' ? 'selected' : ''; ?>>Friend</option>
                            <option value="other" <?php echo old('nok_relationship') == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Next of Kin Phone <span class="required">*</span></label>
                        <input type="tel" class="form-control" name="nok_phone" required value="<?php echo old('nok_phone'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Alternative Phone</label>
                        <input type="tel" class="form-control" name="nok_phone_alt" value="<?php echo old('nok_phone_alt'); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Next of Kin Address</label>
                    <textarea class="form-control" name="nok_address" rows="2"><?php echo old('nok_address'); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Medical Conditions / Allergies</label>
                    <textarea class="form-control" name="medical_conditions" rows="2" placeholder="Optional but recommended for safety"><?php echo old('medical_conditions'); ?></textarea>
                </div>
                
                
                <!-- Section 4: Academic Qualifications -->
                <div class="section-title">
                    <i class="bi bi-mortarboard"></i> Academic Qualifications
                </div>
                
                <p class="text-muted mb-3">Add all your academic qualifications.</p>
                
                <div id="qualificationsContainer">
                    <div class="qualification-item" data-index="1">
                        <span class="qualification-number">1</span>
                        <strong>Qualification</strong>
                        
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Qualification Type <span class="required">*</span></label>
                                <select class="form-select" name="qualifications[0][type]" required>
                                    <option value="">-- Select --</option>
                                    <option value="certificate">Certificate</option>
                                    <option value="diploma">Diploma</option>
                                    <option value="bachelors">Bachelor's Degree</option>
                                    <option value="masters">Master's Degree</option>
                                    <option value="doctorate">Doctorate (PhD)</option>
                                    <option value="professional">Professional Certification</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Year Completed <span class="required">*</span></label>
                                <input type="number" class="form-control" name="qualifications[0][year]" required min="1970" max="2030">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description / Title <span class="required">*</span></label>
                            <input type="text" class="form-control" name="qualifications[0][description]" required placeholder="e.g., Bachelor of Science in Computer Science">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Institution Name <span class="required">*</span></label>
                            <input type="text" class="form-control" name="qualifications[0][institution]" required placeholder="e.g., University of Nairobi">
                        </div>
                        
                        <div class="mb-0">
                            <label class="form-label">Upload Certificate <span class="required">*</span></label>
                            <input type="file" class="form-control" name="qualifications[0][file]" required accept=".pdf,.jpg,.jpeg,.png">
                            <small class="form-text">PDF or Image, Max 5MB</small>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="add-qualification-btn mb-4" onclick="addQualification()">
                    <i class="bi bi-plus-circle me-2"></i>Add Another Qualification
                </button>
                
                
                <!-- Declaration -->
                <div class="section-title">
                    <i class="bi bi-check-circle"></i> Declaration
                </div>
                
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="declaration" name="declaration" required>
                    <label class="form-check-label" for="declaration">
                        I hereby declare that all the information provided is true and correct.
                        I understand that any false information may lead to disqualification or termination.
                        <span class="required">*</span>
                    </label>
                </div>
                
                <!-- Submit -->
                <div class="text-center mt-4">
                    <button type="submit" class="submit-btn">
                        <i class="bi bi-send me-2"></i>Submit Onboarding Form
                    </button>
                </div>
                
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let qualificationIndex = 1;
        
        function addQualification() {
            qualificationIndex++;
            const container = document.getElementById('qualificationsContainer');
            
            const html = `
                <div class="qualification-item" data-index="${qualificationIndex}">
                    <button type="button" class="remove-btn" onclick="removeQualification(this)"><i class="bi bi-x"></i></button>
                    <span class="qualification-number">${qualificationIndex}</span>
                    <strong>Qualification</strong>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qualification Type <span class="required">*</span></label>
                            <select class="form-select" name="qualifications[${qualificationIndex - 1}][type]" required>
                                <option value="">-- Select --</option>
                                <option value="certificate">Certificate</option>
                                <option value="diploma">Diploma</option>
                                <option value="bachelors">Bachelor's Degree</option>
                                <option value="masters">Master's Degree</option>
                                <option value="doctorate">Doctorate (PhD)</option>
                                <option value="professional">Professional Certification</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Year Completed <span class="required">*</span></label>
                            <input type="number" class="form-control" name="qualifications[${qualificationIndex - 1}][year]" required min="1970" max="2030">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description / Title <span class="required">*</span></label>
                        <input type="text" class="form-control" name="qualifications[${qualificationIndex - 1}][description]" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Institution Name <span class="required">*</span></label>
                        <input type="text" class="form-control" name="qualifications[${qualificationIndex - 1}][institution]" required>
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label">Upload Certificate <span class="required">*</span></label>
                        <input type="file" class="form-control" name="qualifications[${qualificationIndex - 1}][file]" required accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', html);
            updateNumbers();
        }
        
        function removeQualification(btn) {
            btn.closest('.qualification-item').remove();
            updateNumbers();
        }
        
        function updateNumbers() {
            document.querySelectorAll('.qualification-item').forEach((item, index) => {
                item.querySelector('.qualification-number').textContent = index + 1;
            });
        }
        
        function previewPhoto(input) {
            const preview = document.getElementById('photoPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}">`;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // KRA PIN uppercase
        document.querySelector('input[name="kra_pin"]').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>