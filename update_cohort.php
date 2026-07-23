<?php
// Include your database connection
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = mysqli_real_escape_string($conn, $_POST['event_id']);
    $country_name = mysqli_real_escape_string($conn, $_POST['country_name']);
    $cohort_title = mysqli_real_escape_string($conn, $_POST['cohort_title']);
    $introduction_text = mysqli_real_escape_string($conn, $_POST['introduction_text']);
    $number_of_cohorts = (int)$_POST['number_of_cohorts'];
    $professionals_trained = (int)$_POST['professionals_trained'];
    
    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Get existing cohort data
    $existing_query = mysqli_query($conn, "SELECT cohort_data FROM Event WHERE event_id = '$event_id'");
    $existing_row = mysqli_fetch_assoc($existing_query);
    $existing_data = !empty($existing_row['cohort_data']) ? json_decode($existing_row['cohort_data'], true) : [];
    
    $intro_image = $existing_data['intro_image'] ?? '';
    $cohort_images = $existing_data['cohort_images'] ?? [];
    
    // Handle introduction image upload
    if (isset($_FILES['intro_image']) && $_FILES['intro_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['intro_image']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['intro_image']['name'], PATHINFO_EXTENSION));
        $new_filename = 'intro_' . uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_tmp, $upload_path)) {
            // Delete old intro image if exists
            if (!empty($intro_image) && file_exists($intro_image)) {
                unlink($intro_image);
            }
            $intro_image = $upload_path;
        }
    }
    
    // Handle cohort images upload
    $new_cohort_images = [];
    for ($i = 1; $i <= $number_of_cohorts; $i++) {
        $field_name = 'cohort_image_' . $i;
        $existing_field = 'existing_cohort_image_' . $i;
        
        // Check if new image uploaded
        if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES[$field_name]['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
            $new_filename = 'cohort_' . $i . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Delete old image if exists
                if (!empty($cohort_images[$i-1]) && file_exists($cohort_images[$i-1])) {
                    unlink($cohort_images[$i-1]);
                }
                $new_cohort_images[] = $upload_path;
            }
        } elseif (isset($_POST[$existing_field]) && !empty($_POST[$existing_field])) {
            // Keep existing image
            $new_cohort_images[] = $_POST[$existing_field];
        }
    }
    
    // Prepare cohort data array
    $cohort_data = [
        'country_name' => $country_name,
        'title' => $cohort_title,
        'introduction_text' => $introduction_text,
        'intro_image' => $intro_image,
        'number_of_cohorts' => $number_of_cohorts,
        'professionals_trained' => $professionals_trained,
        'cohort_images' => $new_cohort_images
    ];
    
    $cohort_data_json = json_encode($cohort_data);
    
    // Update database
    $update_query = "UPDATE Event SET cohort_data = '$cohort_data_json' WHERE event_id = '$event_id'";
    
    if (mysqli_query($conn, $update_query)) {
        echo "<script>
                alert('Cohort configuration updated successfully!');
                window.location.href = 'view_event?event_id=" . $event_id . "';
            </script>";
      
    } else {
         echo "<script>
                alert('Error updating event: " . mysqli_error($conn) . "');
                window.history.back();
            </script>";
       
    }
    

}
?>