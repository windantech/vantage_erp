<?php
// Include your database connection
require_once 'auth.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $event_id = intval($_POST['event_id']);
    $simple_writeup = mysqli_real_escape_string($conn, $_POST['simple_writeup'] ?? '');
    $youtube_link = mysqli_real_escape_string($conn, $_POST['youtube_link'] ?? '');
    $testimonial_video_link = mysqli_real_escape_string($conn, $_POST['testimonial_video_link'] ?? '');
    $lead_form_id = mysqli_real_escape_string($conn, $_POST['lead_form_id'] ?? '');

    // Core event fields (from the main edit modal)
    $event_title = mysqli_real_escape_string($conn, $_POST['event_title'] ?? '');
    $event_description = mysqli_real_escape_string($conn, $_POST['event_description'] ?? '');
    $start_on = mysqli_real_escape_string($conn, $_POST['start_on'] ?? '');
    $end_on = mysqli_real_escape_string($conn, $_POST['end_on'] ?? '');
    $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $host = mysqli_real_escape_string($conn, $_POST['host'] ?? '');

    $early_start_on = mysqli_real_escape_string($conn, $_POST['early_start_on'] ?? '');
    $early_end_on = mysqli_real_escape_string($conn, $_POST['early_end_on'] ?? '');
    $early_amount = mysqli_real_escape_string($conn, $_POST['early_amount'] ?? '');

    $advance_start_on = mysqli_real_escape_string($conn, $_POST['advance_start_on'] ?? '');
    $advance_end_on = mysqli_real_escape_string($conn, $_POST['advance_end_on'] ?? '');
    $advance_amount = mysqli_real_escape_string($conn, $_POST['advance_amount'] ?? '');

    $gate_start_on = mysqli_real_escape_string($conn, $_POST['gate_start_on'] ?? '');
    $gate_end_on = mysqli_real_escape_string($conn, $_POST['gate_end_on'] ?? '');
    $gate_amount = mysqli_real_escape_string($conn, $_POST['gate_amount'] ?? '');

    $currency_code = mysqli_real_escape_string($conn, $_POST['currency_code'] ?? '');
    
    // Initialize variables
    $update_fields = [];
    $upload_dir = 'uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // ==================== Handle Flag Flier Upload ====================
    if (isset($_FILES['flag_flier']) && $_FILES['flag_flier']['error'] == 0) {
        
        $file_tmp = $_FILES['flag_flier']['tmp_name'];
        $file_size = $_FILES['flag_flier']['size'];
        
        // Get file extension
        $file_info = pathinfo($_FILES['flag_flier']['name']);
        $file_ext = strtolower($file_info['extension']);
        
        // Allowed file types
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
        
        // Validate file
        if (in_array($file_ext, $allowed_types) && $file_size <= 10000000) { // 10MB limit
            
            // Generate unique filename
            $new_filename = md5(uniqid() . time()) . '.' . $file_ext;
            $flag_flier_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $flag_flier_path)) {
                $update_fields[] = "flag_flier = '" . mysqli_real_escape_string($conn, $flag_flier_path) . "'";
            } else {
                echo "<script>alert('Error uploading flier!'); window.history.back();</script>";
                exit;
            }
            
        } else {
            echo "<script>alert('Invalid file type or file too large for flier!'); window.history.back();</script>";
            exit;
        }
    }

    // ==================== Handle Poster Upload (Event Poster) ====================
    if (isset($_FILES['poster_image']) && $_FILES['poster_image']['error'] == 0) {
        $file_tmp = $_FILES['poster_image']['tmp_name'];
        $file_size = $_FILES['poster_image']['size'];
        $file_info = pathinfo($_FILES['poster_image']['name']);
        $file_ext = strtolower($file_info['extension'] ?? '');

        $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($file_ext, $allowed_image_types, true) && $file_size <= 8000000) { // 8MB limit
            $new_filename = 'poster_' . md5(uniqid() . time()) . '.' . $file_ext;
            $poster_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $poster_path)) {
                $update_fields[] = "poster_image = '" . mysqli_real_escape_string($conn, $poster_path) . "'";
            } else {
                echo "<script>alert('Error uploading poster!'); window.history.back();</script>";
                exit;
            }
        } else {
            echo "<script>alert('Poster must be an image (JPG, PNG, GIF, WEBP) and max 8MB!'); window.history.back();</script>";
            exit;
        }
    }
    
    // ==================== Handle Training Schedule PDF Upload ====================
    if (isset($_FILES['training_schedule']) && $_FILES['training_schedule']['error'] == 0) {
        
        $file_tmp = $_FILES['training_schedule']['tmp_name'];
        $file_size = $_FILES['training_schedule']['size'];
        
        // Get file extension
        $file_info = pathinfo($_FILES['training_schedule']['name']);
        $file_ext = strtolower($file_info['extension']);
        
        // Only allow PDF
        if ($file_ext == 'pdf' && $file_size <= 10000000) { // 10MB limit
            
            // Generate unique filename
            $new_filename = 'schedule_' . md5(uniqid() . time()) . '.pdf';
            $schedule_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $schedule_path)) {
                $update_fields[] = "training_schedule = '" . mysqli_real_escape_string($conn, $schedule_path) . "'";
            } else {
                echo "<script>alert('Error uploading training schedule!'); window.history.back();</script>";
                exit;
            }
            
        } else {
            echo "<script>alert('Training schedule must be a PDF file (max 10MB)!'); window.history.back();</script>";
            exit;
        }
    }
    
    // ==================== Handle Training Gallery Images ====================
    $gallery_images = [];
    
    // Get existing gallery images if available
    if (isset($_POST['existing_gallery']) && !empty($_POST['existing_gallery'])) {
        $gallery_images = json_decode($_POST['existing_gallery'], true);
        if (!is_array($gallery_images)) {
            $gallery_images = [];
        }
    }
    
    // Process new gallery uploads
    if (isset($_FILES['training_gallery']) && !empty($_FILES['training_gallery']['name'][0])) {
        
        $files = $_FILES['training_gallery'];
        $file_count = count($files['name']);
        
        // Allowed image types
        $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        for ($i = 0; $i < $file_count; $i++) {
            
            // Check if file was uploaded without errors
            if ($files['error'][$i] == 0) {
                
                $file_tmp = $files['tmp_name'][$i];
                $file_size = $files['size'][$i];
                
                // Get file extension
                $file_info = pathinfo($files['name'][$i]);
                $file_ext = strtolower($file_info['extension']);
                
                // Validate file
                if (in_array($file_ext, $allowed_image_types) && $file_size <= 5000000) { // 5MB limit per image
                    
                    // Generate unique filename
                    $new_filename = 'gallery_' . md5(uniqid() . time() . $i) . '.' . $file_ext;
                    $image_path = $upload_dir . $new_filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file_tmp, $image_path)) {
                        $gallery_images[] = $image_path;
                    }
                    
                } else {
                    echo "<script>alert('Gallery image " . ($i + 1) . " is invalid or too large (max 5MB)!'); window.history.back();</script>";
                    exit;
                }
            }
        }
    }
    
    // Update gallery field if images exist
    if (!empty($gallery_images)) {
        $gallery_json = json_encode($gallery_images);
        $update_fields[] = "training_gallery = '" . mysqli_real_escape_string($conn, $gallery_json) . "'";
    }
    
    // ==================== Validate YouTube Link (only if submitted) ====================
    if (array_key_exists('youtube_link', $_POST)) {
        if (!empty($youtube_link)) {
            $youtube_patterns = [
                '/^https?:\/\/(www\.)?youtube\.com\/watch\?v=[\w-]+/',
                '/^https?:\/\/(www\.)?youtu\.be\/[\w-]+/',
                '/^https?:\/\/(www\.)?youtube\.com\/embed\/[\w-]+/',
                '/^https?:\/\/(www\.)?youtube\.com\/v\/[\w-]+/'
            ];
            
            $is_valid_youtube = false;
            foreach ($youtube_patterns as $pattern) {
                if (preg_match($pattern, $youtube_link)) {
                    $is_valid_youtube = true;
                    break;
                }
            }
            
            if (!$is_valid_youtube) {
                echo "<script>alert('Please enter a valid YouTube URL!'); window.history.back();</script>";
                exit;
            }
            
            $update_fields[] = "youtube_link = '$youtube_link'";
        } else {
            $update_fields[] = "youtube_link = NULL";
        }
    }
    
    // ==================== Validate Testimonial Video Link (only if submitted) ====================
    if (array_key_exists('testimonial_video_link', $_POST)) {
        if (!empty($testimonial_video_link)) {
            // Accept YouTube, Vimeo, and other video platforms
            $video_patterns = [
                '/^https?:\/\/(www\.)?youtube\.com\/watch\?v=[\w-]+/',
                '/^https?:\/\/(www\.)?youtu\.be\/[\w-]+/',
                '/^https?:\/\/(www\.)?vimeo\.com\/\d+/',
                '/^https?:\/\/(www\.)?dailymotion\.com\/video\/[\w-]+/',
                '/^https?:\/\/.*\.(mp4|webm|ogg)$/' // Direct video links
            ];
            
            $is_valid_video = false;
            foreach ($video_patterns as $pattern) {
                if (preg_match($pattern, $testimonial_video_link)) {
                    $is_valid_video = true;
                    break;
                }
            }
            
            if (!$is_valid_video) {
                echo "<script>alert('Please enter a valid video URL (YouTube, Vimeo, etc.)!'); window.history.back();</script>";
                exit;
            }
            
            $update_fields[] = "testimonial_video_link = '$testimonial_video_link'";
        } else {
            $update_fields[] = "testimonial_video_link = NULL";
        }
    }
    
    // ==================== Handle Lead Form ID (only if submitted) ====================
    if (array_key_exists('lead_form_id', $_POST)) {
        if (!empty($lead_form_id)) {
            $update_fields[] = "lead_form_id = '$lead_form_id'";
        } else {
            $update_fields[] = "lead_form_id = NULL";
        }
    }
    
    // ==================== Always Update Writeup ====================
    if (array_key_exists('simple_writeup', $_POST)) {
        $update_fields[] = "simple_writeup = '$simple_writeup'";
    }

    // ==================== Core Event Fields (only update if present in POST) ====================
    if (array_key_exists('event_title', $_POST)) {
        $update_fields[] = "event_title = '$event_title'";
    }
    if (array_key_exists('event_description', $_POST)) {
        $update_fields[] = "event_description = '$event_description'";
    }
    if (array_key_exists('start_on', $_POST)) {
        $update_fields[] = "start_on = '$start_on'";
    }
    if (array_key_exists('end_on', $_POST)) {
        $update_fields[] = "end_on = '$end_on'";
    }
    if (array_key_exists('location', $_POST)) {
        $update_fields[] = "location = '$location'";
    }
    if (array_key_exists('host', $_POST)) {
        $update_fields[] = "host = '$host'";
    }

    if (array_key_exists('early_start_on', $_POST)) {
        $update_fields[] = "early_start_on = '$early_start_on'";
    }
    if (array_key_exists('early_end_on', $_POST)) {
        $update_fields[] = "early_end_on = '$early_end_on'";
    }
    if (array_key_exists('early_amount', $_POST)) {
        $update_fields[] = "early_amount = '$early_amount'";
    }

    if (array_key_exists('advance_start_on', $_POST)) {
        $update_fields[] = "advance_start_on = '$advance_start_on'";
    }
    if (array_key_exists('advance_end_on', $_POST)) {
        $update_fields[] = "advance_end_on = '$advance_end_on'";
    }
    if (array_key_exists('advance_amount', $_POST)) {
        $update_fields[] = "advance_amount = '$advance_amount'";
    }

    if (array_key_exists('gate_start_on', $_POST)) {
        $update_fields[] = "gate_start_on = '$gate_start_on'";
    }
    if (array_key_exists('gate_end_on', $_POST)) {
        $update_fields[] = "gate_end_on = '$gate_end_on'";
    }
    if (array_key_exists('gate_amount', $_POST)) {
        $update_fields[] = "gate_amount = '$gate_amount'";
    }

    if (array_key_exists('currency_code', $_POST)) {
        $update_fields[] = "currency_code = '$currency_code'";
    }
    
    // ==================== Build and Execute Update Query ====================
    if (!empty($update_fields)) {
        $sql = "UPDATE Event SET " . implode(', ', $update_fields) . " WHERE event_id = $event_id";
        
        if (mysqli_query($conn, $sql)) {
            echo "<script>
                alert('Event updated successfully!');
                window.location.href = 'view_event?event_id=" . $event_id . "';
            </script>";
        } else {
            echo "<script>
                alert('Error updating event: " . mysqli_error($conn) . "');
                window.history.back();
            </script>";
        }
    } else {
        echo "<script>
            alert('No changes made!');
            window.history.back();
        </script>";
    }
    
} else {
    // Redirect if accessed directly
    header('Location: events.php');
    exit;
}
?>