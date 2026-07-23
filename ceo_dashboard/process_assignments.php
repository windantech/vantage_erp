<?php
/**
 * Process Assignments - Handle course and event assignment updates
 * Performance Management Module
 * 
 * Logic:
 * - For each course/event, check if user is currently assigned
 * - If user should be assigned but isn't, add their ID
 * - If user shouldn't be assigned but is, remove their ID
 * - Preserve other staff assignments (comma-separated IDs)
 */

session_start();
require_once 'header.php';
require_once '../function.php';

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user_id = intval($_SESSION['login_id']);

/**
 * Add user ID to a comma-separated list
 */
function addToAssignedList($current_list, $user_id) {
    $ids = !empty($current_list) ? explode(',', $current_list) : [];
    $ids = array_map('trim', $ids);
    $ids = array_filter($ids); // Remove empty values
    
    if (!in_array($user_id, $ids)) {
        $ids[] = $user_id;
    }
    
    return implode(',', $ids);
}

/**
 * Remove user ID from a comma-separated list
 */
function removeFromAssignedList($current_list, $user_id) {
    $ids = !empty($current_list) ? explode(',', $current_list) : [];
    $ids = array_map('trim', $ids);
    $ids = array_filter($ids); // Remove empty values
    
    $ids = array_diff($ids, [$user_id]);
    
    return implode(',', $ids);
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ========================================
    // ACTION: Save Assignments
    // ========================================
    if ($action === 'save_assignments') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $system_user_id = intval($_POST['system_user_id'] ?? 0);
        $selected_courses = json_decode($_POST['courses'] ?? '[]', true);
        $selected_events = json_decode($_POST['events'] ?? '[]', true);
        
        // Validate
        if ($staff_id <= 0 || $system_user_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid staff or user ID']);
            exit;
        }
        
        // Verify staff has system access
        $staff_check = mysqli_query($conn, "
            SELECT id, system_user_id, system_access_granted 
            FROM staff 
            WHERE id = $staff_id AND system_user_id = $system_user_id AND system_access_granted = 1
        ");
        
        if (!$staff_check || mysqli_num_rows($staff_check) === 0) {
            echo json_encode(['success' => false, 'message' => 'Staff member not found or does not have system access']);
            exit;
        }
        
        // Ensure arrays
        if (!is_array($selected_courses)) $selected_courses = [];
        if (!is_array($selected_events)) $selected_events = [];
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            $courses_updated = 0;
            $events_updated = 0;
            
            // ========================================
            // Update Course Assignments
            // ========================================
            $all_courses = mysqli_query($conn, "SELECT course_id, assigned_to FROM course");
            
            while ($course = mysqli_fetch_assoc($all_courses)) {
                $course_id = $course['course_id'];
                $current_assigned = $course['assigned_to'] ?? '';
                $current_ids = !empty($current_assigned) ? explode(',', $current_assigned) : [];
                $current_ids = array_map('trim', $current_ids);
                
                $is_currently_assigned = in_array($system_user_id, $current_ids);
                $should_be_assigned = in_array($course_id, $selected_courses);
                
                if ($should_be_assigned && !$is_currently_assigned) {
                    // Add user to this course
                    $new_assigned = addToAssignedList($current_assigned, $system_user_id);
                    $new_assigned_escaped = mysqli_real_escape_string($conn, $new_assigned);
                    mysqli_query($conn, "UPDATE course SET assigned_to = '$new_assigned_escaped' WHERE course_id = '$course_id'");
                    $courses_updated++;
                } elseif (!$should_be_assigned && $is_currently_assigned) {
                    // Remove user from this course
                    $new_assigned = removeFromAssignedList($current_assigned, $system_user_id);
                    $new_assigned_escaped = mysqli_real_escape_string($conn, $new_assigned);
                    mysqli_query($conn, "UPDATE course SET assigned_to = '$new_assigned_escaped' WHERE course_id = '$course_id'");
                    $courses_updated++;
                }
            }
            
            // ========================================
            // Update Event Assignments
            // ========================================
            $all_events = mysqli_query($conn, "SELECT event_id, assigned_to FROM Event");
            
            while ($event = mysqli_fetch_assoc($all_events)) {
                $event_id = $event['event_id'];
                $current_assigned = $event['assigned_to'] ?? '';
                $current_ids = !empty($current_assigned) ? explode(',', $current_assigned) : [];
                $current_ids = array_map('trim', $current_ids);
                
                $is_currently_assigned = in_array($system_user_id, $current_ids);
                $should_be_assigned = in_array($event_id, $selected_events);
                
                if ($should_be_assigned && !$is_currently_assigned) {
                    // Add user to this event
                    $new_assigned = addToAssignedList($current_assigned, $system_user_id);
                    $new_assigned_escaped = mysqli_real_escape_string($conn, $new_assigned);
                    mysqli_query($conn, "UPDATE Event SET assigned_to = '$new_assigned_escaped' WHERE event_id = '$event_id'");
                    $events_updated++;
                } elseif (!$should_be_assigned && $is_currently_assigned) {
                    // Remove user from this event
                    $new_assigned = removeFromAssignedList($current_assigned, $system_user_id);
                    $new_assigned_escaped = mysqli_real_escape_string($conn, $new_assigned);
                    mysqli_query($conn, "UPDATE Event SET assigned_to = '$new_assigned_escaped' WHERE event_id = '$event_id'");
                    $events_updated++;
                }
            }
            
            // Log the action
            $course_count = count($selected_courses);
            $event_count = count($selected_events);
            $log_details = "Assignments updated: $course_count courses, $event_count events";
            
            mysqli_query($conn, "
                INSERT INTO staff_onboarding_log (staff_id, action, action_by, notes, created_at)
                VALUES ($staff_id, 'assignments_updated', $current_user_id, '$log_details', NOW())
            ");
            
            // Commit
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true,
                'message' => "Assignments saved successfully! ($course_count courses, $event_count events)",
                'courses_count' => $course_count,
                'events_count' => $event_count,
                'courses_updated' => $courses_updated,
                'events_updated' => $events_updated
            ]);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        
        exit;
    }
    
    // ========================================
    // ACTION: Get Staff Assignments (for AJAX loading)
    // ========================================
    if ($action === 'get_assignments') {
        $system_user_id = intval($_POST['system_user_id'] ?? 0);
        
        if ($system_user_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        
        $assigned_courses = [];
        $assigned_events = [];
        
        // Get courses assigned to this user
        $courses_query = mysqli_query($conn, "SELECT course_id, course, assigned_to FROM course");
        while ($course = mysqli_fetch_assoc($courses_query)) {
            $assigned_ids = !empty($course['assigned_to']) ? explode(',', $course['assigned_to']) : [];
            if (in_array($system_user_id, $assigned_ids)) {
                $assigned_courses[] = [
                    'course_id' => $course['course_id'],
                    'course' => $course['course']
                ];
            }
        }
        
        // Get events assigned to this user
        $events_query = mysqli_query($conn, "SELECT event_id, event_title, assigned_to FROM Event");
        while ($event = mysqli_fetch_assoc($events_query)) {
            $assigned_ids = !empty($event['assigned_to']) ? explode(',', $event['assigned_to']) : [];
            if (in_array($system_user_id, $assigned_ids)) {
                $assigned_events[] = [
                    'event_id' => $event['event_id'],
                    'event_title' => $event['event_title']
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'courses' => $assigned_courses,
            'events' => $assigned_events
        ]);
        
        exit;
    }
    
    // Unknown action
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// If not POST, redirect
header('Location: staff_assignments.php');
exit;
?>