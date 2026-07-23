<?php
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = mysqli_real_escape_string($conn, $_POST['event_id']);
    
    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Get existing content data
    $existing_query = mysqli_query($conn, "SELECT content_sections FROM course WHERE course_id = '$event_id'");
    $existing_row = mysqli_fetch_assoc($existing_query);
    $existing_data = !empty($existing_row['content_sections']) ? json_decode($existing_row['content_sections'], true) : [];
    
    // Collect testimonials
    $testimonials = [];
    for ($t = 1; $t <= 3; $t++) {
        $testimonials[] = [
            'word' => mysqli_real_escape_string($conn, $_POST["testimonial_{$t}_word"]),
            'name' => mysqli_real_escape_string($conn, $_POST["testimonial_{$t}_name"]),
            'country' => mysqli_real_escape_string($conn, $_POST["testimonial_{$t}_country"])
        ];
    }
    
    // Text fields
    $background_expertise = mysqli_real_escape_string($conn, $_POST['background_expertise']);
    $philosophy = mysqli_real_escape_string($conn, $_POST['philosophy']);
    $case_study = mysqli_real_escape_string($conn, $_POST['case_study']);
    $unique_approach_intro = mysqli_real_escape_string($conn, $_POST['unique_approach_intro']);
    $number_of_approaches = (int)$_POST['number_of_approaches'];
    $coordinator_whatsapp = mysqli_real_escape_string($conn, $_POST['coordinator_whatsapp']);
    $lead_trainer_intro = mysqli_real_escape_string($conn, $_POST['lead_trainer_intro']);
    
    // Collect approaches
    $approaches = [];
    for ($i = 1; $i <= $number_of_approaches; $i++) {
        $approaches[] = [
            'title' => mysqli_real_escape_string($conn, $_POST["approach_{$i}_title"]),
            'description' => mysqli_real_escape_string($conn, $_POST["approach_{$i}_description"])
        ];
    }
    
    // Handle file uploads
    $case_study_graphic = $existing_data['case_study_graphic'] ?? '';
    $elearning_screenshot = $existing_data['elearning_screenshot'] ?? '';
    $sample_cert_screenshot = $existing_data['sample_cert_screenshot'] ?? '';
    $lead_trainer_image = $existing_data['lead_trainer_image'] ?? '';
    
    // Upload case study graphic
    if (isset($_FILES['case_study_graphic']) && $_FILES['case_study_graphic']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['case_study_graphic']['name'], PATHINFO_EXTENSION));
        $new_filename = 'case_study_' . uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['case_study_graphic']['tmp_name'], $upload_path)) {
            if (!empty($case_study_graphic) && file_exists($case_study_graphic)) {
                unlink($case_study_graphic);
            }
            $case_study_graphic = $upload_path;
        }
    }
    
    // Upload e-learning screenshot
    if (isset($_FILES['elearning_screenshot']) && $_FILES['elearning_screenshot']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['elearning_screenshot']['name'], PATHINFO_EXTENSION));
        $new_filename = 'elearning_' . uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['elearning_screenshot']['tmp_name'], $upload_path)) {
            if (!empty($elearning_screenshot) && file_exists($elearning_screenshot)) {
                unlink($elearning_screenshot);
            }
            $elearning_screenshot = $upload_path;
        }
    }
    
    // Upload sample certificate screenshot
    if (isset($_FILES['sample_cert_screenshot']) && $_FILES['sample_cert_screenshot']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['sample_cert_screenshot']['name'], PATHINFO_EXTENSION));
        $new_filename = 'cert_' . uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['sample_cert_screenshot']['tmp_name'], $upload_path)) {
            if (!empty($sample_cert_screenshot) && file_exists($sample_cert_screenshot)) {
                unlink($sample_cert_screenshot);
            }
            $sample_cert_screenshot = $upload_path;
        }
    }
    
    // Upload lead trainer image
    if (isset($_FILES['lead_trainer_image']) && $_FILES['lead_trainer_image']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['lead_trainer_image']['name'], PATHINFO_EXTENSION));
        $new_filename = 'trainer_' . uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['lead_trainer_image']['tmp_name'], $upload_path)) {
            if (!empty($lead_trainer_image) && file_exists($lead_trainer_image)) {
                unlink($lead_trainer_image);
            }
            $lead_trainer_image = $upload_path;
        }
    }
    
    // Prepare content data array
    $content_data = [
        'testimonials' => $testimonials,
        'background_expertise' => $background_expertise,
        'philosophy' => $philosophy,
        'case_study' => $case_study,
        'case_study_graphic' => $case_study_graphic,
        'unique_approach_intro' => $unique_approach_intro,
        'number_of_approaches' => $number_of_approaches,
        'approaches' => $approaches,
        'elearning_screenshot' => $elearning_screenshot,
        'sample_cert_screenshot' => $sample_cert_screenshot,
        'lead_trainer_image' => $lead_trainer_image,
        'lead_trainer_intro' => $lead_trainer_intro,
        'coordinator_whatsapp' => $coordinator_whatsapp
    ];
    
    $content_data_json = mysqli_real_escape_string($conn, json_encode($content_data));
    
    // Update database
    $update_query = "UPDATE course SET content_sections = '$content_data_json' WHERE course_id = '$event_id'";
    
    if (mysqli_query($conn, $update_query)) {
       echo "<script>
                alert('Cohort configuration updated successfully!');
                window.location.href = 'view_assigned_user?id=" . $event_id . "';
            </script>";
    } else {
        $_SESSION['error'] = "Error updating content sections: " . mysqli_error($conn);
    }
    
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}
?>