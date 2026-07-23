<?php
/**
 * Enquiry Management System - Helper Functions
 * UPDATED: is_manager() now checks departments table for HOD status
 * UPDATED: get_all_enquiries() now honors program_interest / event_interest filters
 *          (stacked on top of access scope - cannot widen what a user can see)
 */

// ============================================
// STAFF PERMISSION FUNCTIONS  
// ============================================

/**
 * Check if current user is an admin
 * Checks user_type column in registered_users
 */
function is_admin($conn) {
    if (!isset($_SESSION['login_id'])) return false;
    $staff_id = intval($_SESSION['login_id']);
    $result = mysqli_query($conn, "SELECT user_type, role FROM registered_users WHERE id = $staff_id");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        // Check new user_type column first
        if (isset($row['user_type']) && $row['user_type'] === 'admin') {
            return true;
        }
        // Fallback to old role column for backward compatibility
        if (isset($row['role']) && strtolower($row['role']) === 'admin') {
            return true;
        }
    }
    return false;
}

/**
 * Check if current user is a manager (Department Head / HOD)
 * A user is a manager if they are listed as department_head in any active department
 * 
 * @param mysqli $conn Database connection
 * @return bool True if user is a department head
 */
function is_manager($conn) {
    if (!isset($_SESSION['login_id'])) return false;
    
    $user_id = intval($_SESSION['login_id']);
    
    // Check if this user is a department head in any active department
    $result = mysqli_query($conn, "
        SELECT d.id, d.department_name 
        FROM departments d 
        WHERE d.department_head = $user_id 
        AND d.status = 1
        LIMIT 1
    ");
    
    if ($result && mysqli_num_rows($result) > 0) {
        return true;
    }
    
    return false;
}

/**
 * Check if current user is a department head
 * Returns array of department IDs they head, or empty array if not a head
 */
function get_headed_departments($conn) {
    if (!isset($_SESSION['login_id'])) return [];
    $staff_id = intval($_SESSION['login_id']);
    
    $result = mysqli_query($conn, "SELECT id FROM departments WHERE department_head = $staff_id AND status = 1");
    $departments = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $departments[] = $row['id'];
        }
    }
    return $departments;
}

/**
 * Check if current user is a department head (alias)
 */
function is_department_head($conn) {
    return is_manager($conn);
}

/**
 * Get the department(s) where user is the head
 * Returns array of departments with full details
 * 
 * @param mysqli $conn Database connection
 * @return array Array of departments where user is HOD
 */
function get_managed_departments($conn) {
    if (!isset($_SESSION['login_id'])) return [];
    
    $user_id = intval($_SESSION['login_id']);
    $departments = [];
    
    $result = mysqli_query($conn, "
        SELECT d.id, d.department_id, d.department_name, d.description 
        FROM departments d 
        WHERE d.department_head = $user_id 
        AND d.status = 1
    ");
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $departments[] = $row;
        }
    }
    
    return $departments;
}

/**
 * Check if user is HOD of Virtual department (id=3)
 * 
 * @param mysqli $conn Database connection
 * @return bool True if user heads Virtual department
 */
function is_virtual_hod($conn) {
    $headed_depts = get_headed_departments($conn);
    return in_array(3, $headed_depts);
}

/**
 * Check if user is HOD of International department (id=2)
 * 
 * @param mysqli $conn Database connection
 * @return bool True if user heads International department
 */
function is_international_hod($conn) {
    $headed_depts = get_headed_departments($conn);
    return in_array(2, $headed_depts);
}

/**
 * Get enquiry types accessible to current user based on HOD status
 * Returns array of enquiry types: 'register', 'ticket_congress', 'enquiry'
 * 
 * @param mysqli $conn Database connection
 * @return array|string Array of types or 'all' for admin
 */
function get_accessible_enquiry_types($conn) {
    if (is_admin($conn)) return 'all';
    
    $types = [];
    $headed_depts = get_headed_departments($conn);
    
    // Virtual HOD (3) sees register (courses)
    if (in_array(3, $headed_depts)) {
        $types[] = 'register';
    }
    
    // International HOD (2) sees ticket_congress (events)
    if (in_array(2, $headed_depts)) {
        $types[] = 'ticket_congress';
    }
    
    // All HODs and staff with any access see undecided enquiries
    if (!empty($headed_depts) || staff_has_course_access($conn) || staff_has_event_access($conn)) {
        $types[] = 'enquiry';
    }
    
    return $types;
}

/**
 * Get all staff IDs under departments headed by current user
 * Returns array of staff IDs (including the department head themselves)
 */
function get_department_staff_ids($conn) {
    if (!isset($_SESSION['login_id'])) return [];
    $staff_id = intval($_SESSION['login_id']);
    
    // Get departments this user heads
    $headed_depts = get_headed_departments($conn);
    
    if (empty($headed_depts)) {
        // Not a department head, return only their own ID
        return [$staff_id];
    }
    
    // Get all staff in those departments
    $dept_ids = implode(',', array_map('intval', $headed_depts));
    $result = mysqli_query($conn, "SELECT id FROM registered_users WHERE department_id IN ($dept_ids) AND status = 1");
    
    $staff_ids = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $staff_ids[] = $row['id'];
        }
    }
    
    // Always include the department head themselves
    if (!in_array($staff_id, $staff_ids)) {
        $staff_ids[] = $staff_id;
    }
    
    return $staff_ids;
}

/**
 * Get current user's department ID
 */
function get_user_department($conn) {
    if (!isset($_SESSION['login_id'])) return null;
    $staff_id = intval($_SESSION['login_id']);
    $result = mysqli_query($conn, "SELECT department_id FROM registered_users WHERE id = $staff_id");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['department_id'] ?? null;
    }
    return null;
}

/**
 * Get current user's type (admin, manager, staff, viewer)
 */
function get_user_type($conn) {
    if (!isset($_SESSION['login_id'])) return 'viewer';
    
    // Check if admin first
    if (is_admin($conn)) {
        return 'admin';
    }
    
    // Check if department head/manager
    if (is_manager($conn)) {
        return 'manager';
    }
    
    // Check user_type in registered_users as fallback
    $staff_id = intval($_SESSION['login_id']);
    $result = mysqli_query($conn, "SELECT user_type FROM registered_users WHERE id = $staff_id");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['user_type'] ?? 'staff';
    }
    return 'staff';
}

/**
 * Check if user has elevated permissions (admin OR manager/HOD)
 * Useful for pages that both admin and managers can access
 * 
 * @param mysqli $conn Database connection
 * @return bool True if user is admin or manager
 */
function has_elevated_access($conn) {
    return is_admin($conn) || is_manager($conn);
}

/**
 * Check if user can assign staff to courses/events
 * Admin, Managers (HOD), and HR can assign
 * 
 * @param mysqli $conn Database connection
 * @return bool True if user can assign
 */
function can_assign_staff($conn) {
    if (!isset($_SESSION['login_id'])) return false;
    
    // Admin can always assign
    if (is_admin($conn)) return true;
    
    // Department heads can assign
    if (is_manager($conn)) return true;
    
    // Check if user is HR
    $user_id = intval($_SESSION['login_id']);
    $result = mysqli_query($conn, "SELECT user_type FROM registered_users WHERE id = $user_id");
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        if (isset($row['user_type']) && $row['user_type'] === 'hr') {
            return true;
        }
    }
    
    return false;
}

/**
 * Get all departments
 */
function get_departments($conn) {
    $result = mysqli_query($conn, "SELECT d.*, ru.fullname AS head_name 
                                   FROM departments d 
                                   LEFT JOIN registered_users ru ON d.department_head = ru.id 
                                   WHERE d.status = 1 
                                   ORDER BY d.department_name ASC");
    $departments = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $departments[] = $row;
        }
    }
    return $departments;
}

/**
 * Build SQL filter for followups based on user role
 * - Admin: sees all
 * - Department Head: sees only follow-ups created by their department staff
 * - Staff: sees only their own
 * 
 * Returns SQL WHERE clause addition (e.g., " AND staff_id IN (1,2,3)")
 */
function get_followup_permission_sql($conn, $table_alias = 'f') {
    if (!isset($_SESSION['login_id'])) return " AND 1=0"; // No access
    
    // Admin sees all
    if (is_admin($conn)) {
        return "";
    }
    
    $staff_id = intval($_SESSION['login_id']);
    
    // Department head sees only follow-ups created by their department staff
    if (is_department_head($conn)) {
        $staff_ids = get_department_staff_ids($conn);
        if (!empty($staff_ids)) {
            $ids = implode(',', array_map('intval', $staff_ids));
            return " AND {$table_alias}.staff_id IN ($ids)";
        }
    }
    
    // Regular staff sees only their own
    return " AND {$table_alias}.staff_id = $staff_id";
}

/**
 * Get staff list based on user role
 * - Admin: all staff
 * - Department Head: staff in their department
 * - Staff: only themselves
 */
function get_accessible_staff_list($conn) {
    if (!isset($_SESSION['login_id'])) return [];
    
    $staff_id = intval($_SESSION['login_id']);
    
    // Admin sees all staff
    if (is_admin($conn)) {
        return get_staff_list($conn);
    }
    
    // Department head sees their department staff
    if (is_department_head($conn)) {
        $staff_ids = get_department_staff_ids($conn);
        if (!empty($staff_ids)) {
            $ids = implode(',', array_map('intval', $staff_ids));
            $result = mysqli_query($conn, "SELECT id, fullname, role, user_type, department_id FROM registered_users WHERE id IN ($ids) AND status = 1 ORDER BY fullname ASC");
            $staff = [];
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $staff[] = $row;
                }
            }
            return $staff;
        }
    }
    
    // Regular staff - return only themselves
    $result = mysqli_query($conn, "SELECT id, fullname, role, user_type, department_id FROM registered_users WHERE id = $staff_id");
    $staff = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $staff[] = $row;
        }
    }
    return $staff;
}

/**
 * Get courses assigned to current staff
 * Checks course.assigned_to field (comma-separated IDs like "6,16,50")
 * Returns array of course IDs or 'all' if admin or HOD of Virtual department (id=3)
 */
function get_staff_courses($conn) {
    if (!isset($_SESSION['login_id'])) return [];
    if (is_admin($conn)) return 'all';
    
    $staff_id = intval($_SESSION['login_id']);
    
    // Check if HOD of Virtual department (id=3) - sees ALL courses
    $headed_depts = get_headed_departments($conn);
    if (in_array(3, $headed_depts)) {
        return 'all';
    }
    
    $courses = [];
    
    // Get course_id (not id) because register.program references course_id
    $result = mysqli_query($conn, "SELECT id, course_id, assigned_to FROM course WHERE status = 1");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $assigned = $row['assigned_to'] ?? '';
            if (empty($assigned)) continue;
            
            $assigned_ids = array_map('trim', explode(',', $assigned));
            // Check both int and string comparison
            if (in_array($staff_id, $assigned_ids) || in_array((string)$staff_id, $assigned_ids)) {
                // Return course_id since register.program uses course_id
                $courses[] = $row['course_id'];
            }
        }
    }
    
    return $courses;
}

/**
 * Get events assigned to current staff
 * Checks Event.assigned_to field (comma-separated IDs like "6,16,50")
 * Returns array of event IDs or 'all' if admin or HOD of International department (id=2)
 */
function get_staff_events($conn) {
    if (!isset($_SESSION['login_id'])) return [];
    if (is_admin($conn)) return 'all';
    
    $staff_id = intval($_SESSION['login_id']);
    
    // Check if HOD of International department (id=2) - sees ALL events
    $headed_depts = get_headed_departments($conn);
    if (in_array(2, $headed_depts)) {
        return 'all';
    }
    
    $events = [];
    
    $result = mysqli_query($conn, "SELECT event_id, assigned_to FROM Event WHERE status = 1");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $assigned = $row['assigned_to'] ?? '';
            if (empty($assigned)) continue;
            
            $assigned_ids = array_map('trim', explode(',', $assigned));
            if (in_array($staff_id, $assigned_ids) || in_array((string)$staff_id, $assigned_ids)) {
                $events[] = $row['event_id'];
            }
        }
    }
    
    return $events;
}

/**
 * Check if staff has any course access
 */
function staff_has_course_access($conn) {
    $courses = get_staff_courses($conn);
    return $courses === 'all' || !empty($courses);
}

/**
 * Check if staff has any event access
 */
function staff_has_event_access($conn) {
    $events = get_staff_events($conn);
    return $events === 'all' || !empty($events);
}

// ============================================
// ENQUIRY FUNCTIONS
// ============================================

function get_enquiry_sources($conn) {
    $result = mysqli_query($conn, "SELECT * FROM enquiry_sources WHERE is_active = 1 ORDER BY sort_order ASC");
    $sources = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) $sources[] = $row;
    return $sources;
}

function get_source_name($conn, $source_id) {
    if (!$source_id) return 'Unknown';
    $result = mysqli_query($conn, "SELECT name FROM enquiry_sources WHERE id = " . intval($source_id));
    return ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result)['name'] : 'Unknown';
}

function generate_enquiry_ref($conn) {
    $year = date('Y');
    $result = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(enquiry_ref, 10) AS UNSIGNED)) AS max_num FROM enquiries WHERE enquiry_ref LIKE 'ENQ-$year-%'");
    $next = ($result && $row = mysqli_fetch_assoc($result)) ? intval($row['max_num']) + 1 : 1;
    return 'ENQ-' . $year . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function get_staff_list($conn) {
    $result = mysqli_query($conn, "SELECT id, fullname, role FROM registered_users WHERE status = 1 ORDER BY fullname ASC");
    $staff = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) $staff[] = $row;
    return $staff;
}

function get_course_name($conn, $program_id) {
    if (!$program_id) return '-';
    // Check by id first
    $result = mysqli_query($conn, "SELECT course FROM course WHERE id = " . intval($program_id) . " LIMIT 1");
    if ($result && mysqli_num_rows($result) > 0) return mysqli_fetch_assoc($result)['course'];
    // Then check by course_id
    $result = mysqli_query($conn, "SELECT course FROM course WHERE course_id = " . intval($program_id) . " LIMIT 1");
    if ($result && mysqli_num_rows($result) > 0) return mysqli_fetch_assoc($result)['course'];
    return '-';
}

// ============================================
// FOLLOW-UP FUNCTIONS
// ============================================

function add_followup($conn, $data) {
    if (empty($data['enquiry_type']) || empty($data['enquiry_id']) || empty($data['next_step']) || empty($data['reminder_date'])) {
        return ['success' => false, 'error' => 'Missing required fields'];
    }
    $query = "INSERT INTO enquiry_followups (enquiry_type, enquiry_id, staff_id, action_taken, client_response, next_step, reminder_date, reminder_time) VALUES (
        '" . mysqli_real_escape_string($conn, $data['enquiry_type']) . "',
        '" . mysqli_real_escape_string($conn, $data['enquiry_id']) . "',
        " . (isset($data['staff_id']) && $data['staff_id'] ? intval($data['staff_id']) : "NULL") . ",
        " . (isset($data['action_taken']) && $data['action_taken'] ? "'" . mysqli_real_escape_string($conn, $data['action_taken']) . "'" : "NULL") . ",
        " . (isset($data['client_response']) && $data['client_response'] ? "'" . mysqli_real_escape_string($conn, $data['client_response']) . "'" : "NULL") . ",
        '" . mysqli_real_escape_string($conn, $data['next_step']) . "',
        '" . mysqli_real_escape_string($conn, $data['reminder_date']) . "',
        '" . (isset($data['reminder_time']) ? mysqli_real_escape_string($conn, $data['reminder_time']) : '09:00:00') . "'
    )";
    return mysqli_query($conn, $query) ? ['success' => true, 'followup_id' => mysqli_insert_id($conn)] : ['success' => false, 'error' => mysqli_error($conn)];
}

function complete_followup($conn, $followup_id) {
    return mysqli_query($conn, "UPDATE enquiry_followups SET is_completed = 1, completed_at = NOW() WHERE id = " . intval($followup_id)) ? ['success' => true] : ['success' => false, 'error' => mysqli_error($conn)];
}

function get_followups($conn, $enquiry_type, $enquiry_id) {
    $result = mysqli_query($conn, "SELECT f.*, ru.fullname AS staff_name FROM enquiry_followups f LEFT JOIN registered_users ru ON f.staff_id = ru.id WHERE f.enquiry_type = '" . mysqli_real_escape_string($conn, $enquiry_type) . "' AND f.enquiry_id = '" . mysqli_real_escape_string($conn, $enquiry_id) . "' ORDER BY f.created_at DESC");
    $followups = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) $followups[] = $row;
    return $followups;
}

function get_todays_followups($conn, $staff_id = null) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'enquiry_followups'");
    if (!$check || mysqli_num_rows($check) == 0) return [];
    
    $query = "SELECT f.*, ru.fullname AS staff_name FROM enquiry_followups f LEFT JOIN registered_users ru ON f.staff_id = ru.id WHERE f.reminder_date = CURDATE() AND f.is_completed = 0";
    
    // If staff_id provided, use it
    if ($staff_id) {
        $query .= " AND f.staff_id = " . intval($staff_id);
    } else {
        // Apply permission-based filtering (admin/dept head/staff)
        $query .= get_followup_permission_sql($conn, 'f');
    }
    
    $query .= " ORDER BY f.reminder_time ASC LIMIT 50";
    $result = mysqli_query($conn, $query);
    $followups = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) $followups[] = $row;
    return $followups;
}

function get_overdue_followups($conn, $staff_id = null) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'enquiry_followups'");
    if (!$check || mysqli_num_rows($check) == 0) return [];
    
    $query = "SELECT f.*, ru.fullname AS staff_name, DATEDIFF(CURDATE(), f.reminder_date) AS days_overdue FROM enquiry_followups f LEFT JOIN registered_users ru ON f.staff_id = ru.id WHERE f.reminder_date < CURDATE() AND f.is_completed = 0";
    
    // If staff_id provided, use it
    if ($staff_id) {
        $query .= " AND f.staff_id = " . intval($staff_id);
    } else {
        // Apply permission-based filtering (admin/dept head/staff)
        $query .= get_followup_permission_sql($conn, 'f');
    }
    
    $query .= " ORDER BY f.reminder_date ASC LIMIT 50";
    $result = mysqli_query($conn, $query);
    $followups = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) $followups[] = $row;
    return $followups;
}

// ============================================
// FLAG FUNCTIONS
// ============================================

function add_flag($conn, $enquiry_type, $enquiry_id, $flag_type, $flagged_by = null, $notes = null) {
    $query = "INSERT INTO enquiry_flags (enquiry_type, enquiry_id, flag_type, flagged_by, notes) VALUES (
        '" . mysqli_real_escape_string($conn, $enquiry_type) . "',
        '" . mysqli_real_escape_string($conn, $enquiry_id) . "',
        '" . mysqli_real_escape_string($conn, $flag_type) . "',
        " . ($flagged_by ? intval($flagged_by) : "NULL") . ",
        " . ($notes ? "'" . mysqli_real_escape_string($conn, $notes) . "'" : "NULL") . "
    ) ON DUPLICATE KEY UPDATE flagged_by = VALUES(flagged_by), notes = VALUES(notes), created_at = NOW()";
    return mysqli_query($conn, $query) ? ['success' => true] : ['success' => false, 'error' => mysqli_error($conn)];
}

function remove_flag($conn, $enquiry_type, $enquiry_id, $flag_type) {
    return mysqli_query($conn, "DELETE FROM enquiry_flags WHERE enquiry_type = '" . mysqli_real_escape_string($conn, $enquiry_type) . "' AND enquiry_id = '" . mysqli_real_escape_string($conn, $enquiry_id) . "' AND flag_type = '" . mysqli_real_escape_string($conn, $flag_type) . "'") ? ['success' => true] : ['success' => false, 'error' => mysqli_error($conn)];
}

function get_flags($conn, $enquiry_type, $enquiry_id) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'enquiry_flags'");
    if (!$check || mysqli_num_rows($check) == 0) return [];
    $result = mysqli_query($conn, "SELECT flag_type FROM enquiry_flags WHERE enquiry_type = '" . mysqli_real_escape_string($conn, $enquiry_type) . "' AND enquiry_id = '" . mysqli_real_escape_string($conn, $enquiry_id) . "'");
    $flags = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) $flags[] = $row['flag_type'];
    return $flags;
}

function get_flag_display($flag_type) {
    $flags = [
        'high_potential' => ['label' => 'High Potential', 'color' => 'success', 'icon' => 'bi-star-fill'],
        'urgent' => ['label' => 'Urgent', 'color' => 'danger', 'icon' => 'bi-exclamation-triangle-fill'],
        'vip' => ['label' => 'VIP', 'color' => 'warning', 'icon' => 'bi-gem'],
        'needs_attention' => ['label' => 'Needs Attention', 'color' => 'info', 'icon' => 'bi-bell-fill'],
        'cold_lead' => ['label' => 'Cold Lead', 'color' => 'secondary', 'icon' => 'bi-snow']
    ];
    return isset($flags[$flag_type]) ? $flags[$flag_type] : ['label' => $flag_type, 'color' => 'secondary', 'icon' => 'bi-flag'];
}

// ============================================
// DASHBOARD STATS WITH PERMISSIONS
// ============================================

function get_dashboard_stats($conn) {
    $stats = [
        'total_enquiries' => 0, 
        'new_today' => 0, 
        'by_type' => ['virtual' => 0, 'international' => 0, 'undecided' => 0], 
        'paid_count' => 0, 
        'unpaid_count' => 0, 
        'followups_today' => 0, 
        'followups_overdue' => 0
    ];
    
    // Get staff permissions
    $is_admin_user = is_admin($conn);
    $staff_courses = get_staff_courses($conn);
    $staff_events = get_staff_events($conn);
    
    $has_course_access = ($staff_courses === 'all' || !empty($staff_courses));
    $has_event_access = ($staff_events === 'all' || !empty($staff_events));
    
    // Build course filter
    $course_filter = '';
    if (!$is_admin_user) {
        if ($staff_courses === 'all') {
            $course_filter = '';
        } elseif (!empty($staff_courses)) {
            $course_filter = " AND r.program IN (" . implode(',', array_map('intval', $staff_courses)) . ")";
        } else {
            $course_filter = " AND 1=0"; // No access
        }
    }
    
    // Build event filter
    $event_filter = '';
    if (!$is_admin_user) {
        if ($staff_events === 'all') {
            $event_filter = '';
        } elseif (!empty($staff_events)) {
            $event_filter = " AND t.event_id IN (" . implode(',', array_map('intval', $staff_events)) . ")";
        } else {
            $event_filter = " AND 1=0"; // No access
        }
    }
    
    // Count from register table (Virtual) - only if staff has course access
    $register_count = 0;
    if ($has_course_access) {
        $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM register r WHERE 1=1 $course_filter");
        $register_count = ($result && $row = mysqli_fetch_assoc($result)) ? intval($row['cnt']) : 0;
    }
    
    // Count from ticket_congress table (International) - only if staff has event access
    $ticket_count = 0;
    if ($has_event_access) {
        $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM ticket_congress t WHERE 1=1 $event_filter");
        $ticket_count = ($result && $row = mysqli_fetch_assoc($result)) ? intval($row['cnt']) : 0;
    }
    
    // Count from enquiries table (undecided) - show to all staff who have any access
    $enquiry_count = 0;
    $enquiries_exists = mysqli_query($conn, "SHOW TABLES LIKE 'enquiries'");
    if ($enquiries_exists && mysqli_num_rows($enquiries_exists) > 0) {
        if ($has_course_access || $has_event_access) {
            $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM enquiries e WHERE e.converted_to IS NULL");
            $enquiry_count = ($result && $row = mysqli_fetch_assoc($result)) ? intval($row['cnt']) : 0;
        }
    }
    
    $stats['total_enquiries'] = $enquiry_count + $register_count + $ticket_count;
    $stats['by_type']['virtual'] = $register_count;
    $stats['by_type']['international'] = $ticket_count;
    $stats['by_type']['undecided'] = $enquiry_count;
    
    // Payment stats
    $ticket_paid = 0;
    $register_paid = 0;
    
    if ($has_event_access) {
        $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM ticket_congress t WHERE t.status = 2 $event_filter");
        $ticket_paid = ($result && $row = mysqli_fetch_assoc($result)) ? intval($row['cnt']) : 0;
    }
    
    if ($has_course_access) {
        $result = mysqli_query($conn, "SELECT COUNT(DISTINCT r.id) AS cnt FROM register r INNER JOIN dpo_payment p ON r.entry_id = p.app_id WHERE p.TransactionAmount > 0 $course_filter");
        $register_paid = ($result && $row = mysqli_fetch_assoc($result)) ? intval($row['cnt']) : 0;
    }
    
    $stats['paid_count'] = $ticket_paid + $register_paid;
    $stats['unpaid_count'] = ($register_count + $ticket_count) - $stats['paid_count'];
    
    // Follow-ups - use permission-based filtering (admin/dept head/staff)
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'enquiry_followups'");
    if ($check && mysqli_num_rows($check) > 0) {
        $followup_filter = get_followup_permission_sql($conn, 'f');
        
        $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM enquiry_followups f WHERE f.reminder_date = CURDATE() AND f.is_completed = 0 $followup_filter");
        $stats['followups_today'] = ($result && $row = mysqli_fetch_assoc($result)) ? intval($row['cnt']) : 0;
        
        $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM enquiry_followups f WHERE f.reminder_date < CURDATE() AND f.is_completed = 0 $followup_filter");
        $stats['followups_overdue'] = ($result && $row = mysqli_fetch_assoc($result)) ? intval($row['cnt']) : 0;
    }
    
    return $stats;
}

// ============================================
// UNIFIED ENQUIRY LIST WITH CORRECT PERMISSIONS
// ============================================

/**
 * Get all enquiries based on staff permissions
 * 
 * Logic:
 * - Admin: Sees everything
 * - Staff with course access: Sees all Virtual enquiries for their courses
 * - Staff with event access: Sees all International enquiries for their events
 * - Staff with any access: Sees all Undecided enquiries (leads without specific course/event)
 * - Staff with NO access: Sees nothing
 *
 * Optional filters['program_interest'] / filters['event_interest'] narrow the result
 * to a single course/event. These stack ON TOP of the access scope - a staff member
 * can never use them to see a course/event they aren't assigned to, because the
 * IN(...) access filter is still applied to every query.
 */
function get_all_enquiries($conn, $filters = [], $page = 1, $per_page = 250) {
    $enquiries = [];
    
    // Get staff permissions
    $is_admin_user = is_admin($conn);
    $staff_courses = get_staff_courses($conn);
    $staff_events = get_staff_events($conn);
    
    $has_course_access = ($staff_courses === 'all' || !empty($staff_courses));
    $has_event_access = ($staff_events === 'all' || !empty($staff_events));
    
    // If staff has no access at all, return empty
    if (!$is_admin_user && !$has_course_access && !$has_event_access) {
        return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $per_page, 'total_pages' => 0];
    }
    
    // Build course filter for register table (access scope)
    $course_filter = '';
    if (!$is_admin_user && $staff_courses !== 'all' && !empty($staff_courses)) {
        $course_filter = " AND r.program IN (" . implode(',', array_map('intval', $staff_courses)) . ")";
    }
    
    // Build event filter for ticket_congress table (access scope)
    $event_filter = '';
    if (!$is_admin_user && $staff_events !== 'all' && !empty($staff_events)) {
        $event_filter = " AND t.event_id IN (" . implode(',', array_map('intval', $staff_events)) . ")";
    }
    
    // Specific course/event filters (from dropdowns) - intval makes them injection-safe
    $filter_program = (!empty($filters['program_interest'])) ? intval($filters['program_interest']) : 0;
    $filter_event   = (!empty($filters['event_interest']))   ? intval($filters['event_interest'])   : 0;

    // Intake filter (virtual only). Values look like 'I-123456', so escape as a string.
    // NOTE: change the column name in the REGISTER block below if your column isn't `intake`.
    $filter_intake  = (!empty($filters['intake'])) ? mysqli_real_escape_string($conn, $filters['intake']) : '';
    
    $limit = ceil($per_page / 3);
    
    // Search filter helper
    $search_term = '';
    if (!empty($filters['search'])) {
        $search_term = mysqli_real_escape_string($conn, $filters['search']);
    }
    
    // ==========================================
    // ENQUIRIES TABLE (Undecided leads)
    // Show to anyone who has course OR event access.
    // When a specific course/event filter is active, only show undecided leads
    // tagged with that course/event (otherwise unrelated leads would appear).
    // ==========================================
    $enquiries_exists = mysqli_query($conn, "SHOW TABLES LIKE 'enquiries'");
    if ($enquiries_exists && mysqli_num_rows($enquiries_exists) > 0) {
        // Undecided leads have no intake, so skip them entirely when an intake filter is active.
        if (($has_course_access || $has_event_access) && $filter_intake === '') {
            $q = "SELECT 
                'enquiry' AS source_table, 
                e.id AS record_id, 
                e.enquiry_ref AS reference, 
                CONCAT(COALESCE(e.firstname,''),' ',COALESCE(e.lastname,'')) AS fullname, 
                e.firstname, 
                e.lastname, 
                e.email, 
                e.phone, 
                e.country, 
                e.organization, 
                e.interest_type, 
                e.priority, 
                e.status, 
                e.assigned_to, 
                e.program_interest, 
                e.event_interest, 
                e.created_at, 
                es.name AS source_name, 
                c.course AS program_name, 
                ev.event_title AS event_name, 
                0 AS amount_paid, 
                0 AS is_paid 
            FROM enquiries e 
            LEFT JOIN enquiry_sources es ON e.source_id = es.id 
            LEFT JOIN course c ON (e.program_interest = c.id OR e.program_interest = c.course_id)
            LEFT JOIN Event ev ON e.event_interest = ev.event_id 
            WHERE e.converted_to IS NULL";
            
            // Interest type filter
            if (!empty($filters['interest_type'])) {
                $q .= " AND e.interest_type = '" . mysqli_real_escape_string($conn, $filters['interest_type']) . "'";
            }
            
            // Specific course filter
            if ($filter_program > 0) {
                $q .= " AND e.program_interest = $filter_program";
            }
            
            // Specific event filter
            if ($filter_event > 0) {
                $q .= " AND e.event_interest = $filter_event";
            }
            
            // Search filter
            if ($search_term) {
                $q .= " AND (e.firstname LIKE '%$search_term%' OR e.lastname LIKE '%$search_term%' OR e.email LIKE '%$search_term%' OR e.phone LIKE '%$search_term%' OR e.enquiry_ref LIKE '%$search_term%')";
            }
            
            $q .= " ORDER BY e.created_at DESC LIMIT $limit";
            
            $result = mysqli_query($conn, $q);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $row['flags'] = [];
                    $enquiries[] = $row;
                }
            }
        }
    }
    
    // ==========================================
    // REGISTER TABLE (Virtual courses)
    // Only show if staff has course access.
    // Skip entirely if a specific EVENT filter is active (events aren't virtual).
    // ==========================================
    if ($has_course_access
        && (empty($filters['interest_type']) || $filters['interest_type'] == 'virtual')
        && $filter_event === 0) {
        $q = "SELECT 
            'register' AS source_table, 
            r.id AS record_id, 
            r.entry_id AS reference, 
            CONCAT(r.firstname,' ',r.lastname) AS fullname, 
            r.firstname, 
            r.lastname, 
            r.email, 
            r.phone_number AS phone, 
            r.country, 
            '' AS organization, 
            'virtual' AS interest_type, 
            'medium' AS priority, 
            COALESCE(r.lead_status, 'new') AS status, 
            r.assigned_to, 
            r.program AS program_interest, 
            NULL AS event_interest, 
            r.datee AS created_at, 
            CASE r.source WHEN 1 THEN 'Website' WHEN 4 THEN 'WhatsApp' WHEN 5 THEN 'Facebook' ELSE 'Other' END AS source_name, 
            c.course AS program_name, 
            NULL AS event_name, 
            0 AS amount_paid, 
            0 AS is_paid 
        FROM register r 
        LEFT JOIN course c ON r.program = c.course_id
        WHERE 1=1 $course_filter";
        
        // Specific course filter (stacks on top of access scope)
        if ($filter_program > 0) {
            $q .= " AND r.program = $filter_program";
        }

        // Specific intake filter (virtual only). Change `r.intake` if your column differs.
        if ($filter_intake !== '') {
            $q .= " AND r.intake = '$filter_intake'";
        }
        
        // Search filter
        if ($search_term) {
            $q .= " AND (r.firstname LIKE '%$search_term%' OR r.lastname LIKE '%$search_term%' OR r.email LIKE '%$search_term%' OR r.phone_number LIKE '%$search_term%' OR r.entry_id LIKE '%$search_term%')";
        }
        
        $q .= " ORDER BY r.datee DESC LIMIT $limit";
        
        $result = mysqli_query($conn, $q);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $row['flags'] = [];
                $enquiries[] = $row;
            }
        }
    }
    
    // ==========================================
    // TICKET_CONGRESS TABLE (International events)
    // Only show if staff has event access.
    // Skip entirely if a specific COURSE filter is active (courses aren't international).
    // ==========================================
    if ($has_event_access
        && (empty($filters['interest_type']) || $filters['interest_type'] == 'international')
        && $filter_program === 0
        && $filter_intake === '') {
        $q = "SELECT 
            'ticket_congress' AS source_table, 
            t.id AS record_id, 
            t.ticket_id AS reference, 
            t.fullname, 
            SUBSTRING_INDEX(t.fullname,' ',1) AS firstname, 
            SUBSTRING_INDEX(t.fullname,' ',-1) AS lastname, 
            t.email, 
            t.phone_number AS phone, 
            t.country, 
            t.organization, 
            'international' AS interest_type, 
            'medium' AS priority, 
            CASE t.status WHEN 2 THEN 'qualified' ELSE COALESCE(t.lead_status, 'new') END AS status, 
            t.assigned_to, 
            NULL AS program_interest, 
            t.event_id AS event_interest, 
            t.date_sent AS created_at, 
            'Website' AS source_name, 
            NULL AS program_name, 
            ev.event_title AS event_name, 
            t.amount AS amount_paid, 
            CASE WHEN t.status = 2 THEN 1 ELSE 0 END AS is_paid 
        FROM ticket_congress t 
        LEFT JOIN Event ev ON t.event_id = ev.event_id 
        WHERE 1=1 $event_filter";
        
        // Specific event filter (stacks on top of access scope)
        if ($filter_event > 0) {
            $q .= " AND t.event_id = $filter_event";
        }
        
        // Search filter
        if ($search_term) {
            $q .= " AND (t.fullname LIKE '%$search_term%' OR t.email LIKE '%$search_term%' OR t.phone_number LIKE '%$search_term%' OR t.ticket_id LIKE '%$search_term%')";
        }
        
        $q .= " ORDER BY t.date_sent DESC LIMIT $limit";
        
        $result = mysqli_query($conn, $q);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $row['flags'] = [];
                $enquiries[] = $row;
            }
        }
    }
    
    // Sort by date (newest first)
    usort($enquiries, function($a, $b) { 
        return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'); 
    });
    
    // Additional filters (applied in PHP)
    if (!empty($filters['is_paid'])) {
        $enquiries = array_values(array_filter($enquiries, function($e) use ($filters) {
            return ($filters['is_paid'] == 'paid') ? ($e['is_paid'] == 1) : ($e['is_paid'] == 0);
        }));
    }
    
    if (!empty($filters['assigned_to'])) {
        $enquiries = array_values(array_filter($enquiries, function($e) use ($filters) {
            return $e['assigned_to'] == $filters['assigned_to'];
        }));
    }
    
    if (!empty($filters['status'])) {
        $enquiries = array_values(array_filter($enquiries, function($e) use ($filters) {
            return $e['status'] == $filters['status'];
        }));
    }
    
    $total = count($enquiries);
    $enquiries = array_slice($enquiries, 0, $per_page);
    $enquiries = load_flags_batch($conn, $enquiries);
    
    return [
        'data' => $enquiries, 
        'total' => $total, 
        'page' => $page, 
        'per_page' => $per_page, 
        'total_pages' => ceil($total / $per_page)
    ];
}

function load_flags_batch($conn, $enquiries) {
    if (empty($enquiries)) return $enquiries;
    
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'enquiry_flags'");
    if (!$check || mysqli_num_rows($check) == 0) return $enquiries;
    
    $conditions = [];
    foreach ($enquiries as $e) {
        $type = mysqli_real_escape_string($conn, $e['source_table']);
        $ref = mysqli_real_escape_string($conn, $e['reference']);
        $conditions[] = "(enquiry_type = '$type' AND enquiry_id = '$ref')";
    }
    
    if (empty($conditions)) return $enquiries;
    
    $result = mysqli_query($conn, "SELECT enquiry_type, enquiry_id, flag_type FROM enquiry_flags WHERE " . implode(' OR ', $conditions));
    
    $lookup = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $key = $row['enquiry_type'] . '_' . $row['enquiry_id'];
            if (!isset($lookup[$key])) $lookup[$key] = [];
            $lookup[$key][] = $row['flag_type'];
        }
    }
    
    foreach ($enquiries as &$e) {
        $key = $e['source_table'] . '_' . $e['reference'];
        $e['flags'] = isset($lookup[$key]) ? $lookup[$key] : [];
    }
    
    return $enquiries;
}