<?php
/**
 * Task Manager Functions
 * Core helper functions for task CRUD, ID generation, permissions
 * Vantage Africa School of Leadership
 */

// ============================================
// ID GENERATION
// ============================================

/**
 * Generate next Task ID: TSK-2026-000001
 */
function tm_generate_task_id($conn, $year = null) {
    if (!$year) $year = date('Y');
    
    // Lock and increment
    $conn->query("INSERT INTO tm_task_sequence (year, last_number) VALUES ($year, 1) 
                   ON DUPLICATE KEY UPDATE last_number = last_number + 1");
    
    $result = $conn->query("SELECT last_number FROM tm_task_sequence WHERE year = $year");
    $row = $result->fetch_assoc();
    $num = $row['last_number'];
    
    // Get prefix from settings
    $prefix = tm_get_setting($conn, 'task_id_prefix', 'TSK');
    
    return $prefix . '-' . $year . '-' . str_pad($num, 6, '0', STR_PAD_LEFT);
}

/**
 * Generate next Support Request ID: SR-2026-000001
 */
function tm_generate_support_id($conn, $year = null) {
    if (!$year) $year = date('Y');
    
    $conn->query("INSERT INTO tm_support_sequence (year, last_number) VALUES ($year, 1) 
                   ON DUPLICATE KEY UPDATE last_number = last_number + 1");
    
    $result = $conn->query("SELECT last_number FROM tm_support_sequence WHERE year = $year");
    $row = $result->fetch_assoc();
    
    return 'SR-' . $year . '-' . str_pad($row['last_number'], 6, '0', STR_PAD_LEFT);
}

// ============================================
// SETTINGS
// ============================================

/**
 * Get a task manager setting value
 */
function tm_get_setting($conn, $key, $default = null) {
    $key = $conn->real_escape_string($key);
    $result = $conn->query("SELECT setting_value FROM tm_settings WHERE setting_key = '$key' LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['setting_value'] ?? $default;
    }
    return $default;
}

/**
 * Update a setting
 */
function tm_update_setting($conn, $key, $value, $updated_by = null) {
    $key = $conn->real_escape_string($key);
    $value = $conn->real_escape_string($value);
    $by = $updated_by ? intval($updated_by) : 'NULL';
    
    return $conn->query("UPDATE tm_settings SET setting_value = '$value', updated_by = $by WHERE setting_key = '$key'");
}

/**
 * Get CEO user ID from settings
 */
function tm_get_ceo_id($conn) {
    $id = tm_get_setting($conn, 'ceo_user_id');
    return $id ? intval($id) : null;
}

// ============================================
// PERMISSION CHECKS
// ============================================

/**
 * Check if user is the CEO
 */
function tm_is_ceo($conn, $user_id = null) {
    if (!$user_id) $user_id = $_SESSION['login_id'] ?? 0;
    $ceo_id = tm_get_ceo_id($conn);
    return $ceo_id && intval($user_id) === $ceo_id;
}

/**
 * Check if user is admin
 */
function tm_is_admin($conn, $user_id = null) {
    if (!$user_id) $user_id = $_SESSION['login_id'] ?? 0;
    $user_id = intval($user_id);
    $result = $conn->query("SELECT role FROM registered_users WHERE id = $user_id LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['role'] === 'admin';
    }
    return false;
}

/**
 * Check if user is HOD (department head)
 */
function tm_is_hod($conn, $user_id = null) {
    if (!$user_id) $user_id = $_SESSION['login_id'] ?? 0;
    $user_id = intval($user_id);
    $result = $conn->query("SELECT id FROM departments WHERE department_head = $user_id AND status = 1 LIMIT 1");
    return $result && $result->num_rows > 0;
}

/**
 * Check if user is workstream lead
 */
function tm_is_workstream_lead($conn, $user_id = null) {
    if (!$user_id) $user_id = $_SESSION['login_id'] ?? 0;
    $user_id = intval($user_id);
    $result = $conn->query("SELECT id FROM tm_workstreams WHERE hod_user_id = $user_id AND status = 1 LIMIT 1");
    return $result && $result->num_rows > 0;
}

/**
 * Get workstream IDs led by a user
 */
function tm_get_led_workstreams($conn, $user_id = null) {
    if (!$user_id) $user_id = $_SESSION['login_id'] ?? 0;
    $user_id = intval($user_id);
    $result = $conn->query("SELECT id FROM tm_workstreams WHERE hod_user_id = $user_id AND status = 1");
    $ids = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ids[] = $row['id'];
        }
    }
    return $ids;
}

/**
 * Check if user has full access (CEO or Admin)
 */
function tm_has_full_access($conn, $user_id = null) {
    return tm_is_ceo($conn, $user_id) || tm_is_admin($conn, $user_id);
}

/**
 * Get user's role context for task manager
 * Returns: 'ceo', 'admin', 'hod', 'workstream_lead', 'staff'
 */
function tm_get_user_role($conn, $user_id = null) {
    if (tm_is_ceo($conn, $user_id)) return 'ceo';
    if (tm_is_admin($conn, $user_id)) return 'admin';
    if (tm_is_hod($conn, $user_id)) return 'hod';
    if (tm_is_workstream_lead($conn, $user_id)) return 'workstream_lead';
    return 'staff';
}

// ============================================
// TASK CRUD
// ============================================

/**
 * Create a new task
 * @param array $data - associative array of task fields
 * @return int|false - new task ID or false on failure
 */
function tm_create_task($conn, $data, $created_by = null) {
    if (!$created_by) $created_by = $_SESSION['login_id'] ?? 0;
    
    $year = $data['strategy_year'] ?? date('Y');
    $task_id_code = tm_generate_task_id($conn, $year);
    
    // Build insert
    $fields = [
        'task_id' => $task_id_code,
        'strategy_year' => $year,
        'pillar_id' => $data['pillar_id'] ?? null,
        'workstream_id' => $data['workstream_id'] ?? null,
        'phase_id' => $data['phase_id'] ?? null,
        'sn' => $data['sn'] ?? null,
        'task_title' => $data['task_title'],
        'task_description' => $data['task_description'] ?? null,
        'deliverable' => $data['deliverable'],
        'evidence_requirement' => $data['evidence_requirement'] ?? null,
        'owner_role' => $data['owner_role'] ?? null,
        'owner_id' => $data['owner_id'],
        'watchers' => $data['watchers'] ?? null,
        'priority' => $data['priority'] ?? 'Medium',
        'priority_rank' => $data['priority_rank'] ?? 0,
        'start_date' => $data['start_date'],
        'due_date' => $data['due_date'],
        'cadence' => $data['cadence'] ?? 'None',
        'recurrence_rules' => $data['recurrence_rules'] ?? null,
        'recurrence_parent_id' => $data['recurrence_parent_id'] ?? null,
        'occurrence_number' => $data['occurrence_number'] ?? null,
        'dependencies_tasks' => $data['dependencies_tasks'] ?? null,
        'dependencies_other' => $data['dependencies_other'] ?? null,
        'budget_kes' => $data['budget_kes'] ?? null,
        'kpi_target' => $data['kpi_target'] ?? null,
        'kpi_impact_weight' => $data['kpi_impact_weight'] ?? null,
        'status' => $data['status'] ?? 'Assigned',
        'progress_pct' => $data['progress_pct'] ?? 0,
        'support_required' => $data['support_required'] ?? null,
        'notes' => $data['notes'] ?? null,
        'import_batch_id' => $data['import_batch_id'] ?? null,
        'import_row_number' => $data['import_row_number'] ?? null,
        'created_by' => $created_by,
    ];
    
    $columns = [];
    $values = [];
    foreach ($fields as $col => $val) {
        $columns[] = "`$col`";
        if ($val === null) {
            $values[] = "NULL";
        } else {
            $values[] = "'" . $conn->real_escape_string($val) . "'";
        }
    }
    
    $sql = "INSERT INTO tm_tasks (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")";
    
    if ($conn->query($sql)) {
        $new_id = $conn->insert_id;
        
        // Log activity
        tm_log_activity($conn, $new_id, 'general', "Task created: $task_id_code - " . $data['task_title'], null, null, null, $created_by);
        
        return $new_id;
    }
    
    return false;
}

/**
 * Update a task
 */
function tm_update_task($conn, $task_db_id, $data, $updated_by = null, $reason = null) {
    if (!$updated_by) $updated_by = $_SESSION['login_id'] ?? 0;
    $task_db_id = intval($task_db_id);
    
    // Get current values for audit
    $current = tm_get_task($conn, $task_db_id);
    if (!$current) return false;
    
    $sets = [];
    $changes = [];
    
    // Fields that can be updated
    $updatable = [
        'pillar_id', 'workstream_id', 'phase_id', 'task_title', 'task_description',
        'deliverable', 'evidence_requirement', 'owner_role', 'owner_id', 'watchers',
        'priority', 'priority_rank', 'start_date', 'due_date', 'cadence', 'recurrence_rules',
        'dependencies_tasks', 'dependencies_other', 'budget_kes', 'kpi_target',
        'kpi_impact_weight', 'status', 'progress_pct', 'support_required', 'notes'
    ];
    
    foreach ($updatable as $field) {
        if (array_key_exists($field, $data) && $data[$field] != $current[$field]) {
            $old_val = $current[$field];
            $new_val = $data[$field];
            
            if ($new_val === null) {
                $sets[] = "`$field` = NULL";
            } else {
                $sets[] = "`$field` = '" . $conn->real_escape_string($new_val) . "'";
            }
            
            // Determine activity type
            $activity_type = 'general';
            if ($field === 'status') $activity_type = 'status_change';
            if ($field === 'priority' || $field === 'priority_rank') $activity_type = 'priority_change';
            if ($field === 'due_date' || $field === 'start_date') $activity_type = 'date_change';
            if ($field === 'owner_id') $activity_type = 'owner_change';
            if ($field === 'progress_pct') $activity_type = 'progress_update';
            if ($field === 'budget_kes') $activity_type = 'budget_change';
            
            $changes[] = [
                'type' => $activity_type,
                'field' => $field,
                'old' => $old_val,
                'new' => $new_val
            ];
        }
    }
    
    if (empty($sets)) return true; // Nothing to update
    
    $sets[] = "`updated_by` = " . intval($updated_by);
    $sql = "UPDATE tm_tasks SET " . implode(', ', $sets) . " WHERE id = $task_db_id";
    
    if ($conn->query($sql)) {
        // Log each change
        foreach ($changes as $change) {
            $desc = ucfirst(str_replace('_', ' ', $change['field'])) . " changed from '{$change['old']}' to '{$change['new']}'";
            tm_log_activity($conn, $task_db_id, $change['type'], $desc, $change['old'], $change['new'], $reason, $updated_by);
        }
        return true;
    }
    return false;
}

/**
 * Get a single task by database ID
 */
function tm_get_task($conn, $id) {
    $id = intval($id);
    $result = $conn->query("SELECT * FROM v_tm_tasks WHERE id = $id LIMIT 1");
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get task by task_id code (e.g., TSK-2026-000001)
 */
function tm_get_task_by_code($conn, $task_id_code) {
    $code = $conn->real_escape_string($task_id_code);
    $result = $conn->query("SELECT * FROM v_tm_tasks WHERE task_id = '$code' LIMIT 1");
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get tasks with filters
 */
function tm_get_tasks($conn, $filters = [], $limit = 100, $offset = 0) {
    $where = ["1=1"];
    
    if (!empty($filters['strategy_year'])) {
        $where[] = "t.strategy_year = " . intval($filters['strategy_year']);
    }
    if (!empty($filters['pillar_id'])) {
        $where[] = "t.pillar_id = " . intval($filters['pillar_id']);
    }
    if (!empty($filters['workstream_id'])) {
        $where[] = "t.workstream_id = " . intval($filters['workstream_id']);
    }
    if (!empty($filters['phase_id'])) {
        $where[] = "t.phase_id = " . intval($filters['phase_id']);
    }
    if (!empty($filters['owner_id'])) {
        $where[] = "t.owner_id = " . intval($filters['owner_id']);
    }
    if (!empty($filters['status'])) {
        if (is_array($filters['status'])) {
            $statuses = implode("','", array_map([$conn, 'real_escape_string'], $filters['status']));
            $where[] = "t.status IN ('$statuses')";
        } else {
            $where[] = "t.status = '" . $conn->real_escape_string($filters['status']) . "'";
        }
    }
    if (!empty($filters['priority'])) {
        $where[] = "t.priority = '" . $conn->real_escape_string($filters['priority']) . "'";
    }
    if (isset($filters['is_overdue']) && $filters['is_overdue']) {
        $where[] = "t.status NOT IN ('Completed','Verified','Cancelled') AND CURDATE() > t.due_date";
    }
    if (!empty($filters['due_from'])) {
        $where[] = "t.due_date >= '" . $conn->real_escape_string($filters['due_from']) . "'";
    }
    if (!empty($filters['due_to'])) {
        $where[] = "t.due_date <= '" . $conn->real_escape_string($filters['due_to']) . "'";
    }
    if (!empty($filters['search'])) {
        $search = $conn->real_escape_string($filters['search']);
        $where[] = "(t.task_id LIKE '%$search%' OR t.task_title LIKE '%$search%' OR t.task_description LIKE '%$search%')";
    }
    // Scope by workstream IDs (for HOD/workstream lead)
    if (!empty($filters['workstream_ids'])) {
        $ws_ids = implode(',', array_map('intval', $filters['workstream_ids']));
        $where[] = "t.workstream_id IN ($ws_ids)";
    }
    // Exclude recurrence parents that are templates only
    if (!empty($filters['exclude_templates'])) {
        $where[] = "(t.cadence = 'None' OR t.recurrence_parent_id IS NOT NULL)";
    }
    
    $where_sql = implode(' AND ', $where);
    
    $order = "t.priority_rank ASC, FIELD(t.priority, 'Critical','High','Medium','Low'), t.due_date ASC";
    if (!empty($filters['order_by'])) {
        $order = $conn->real_escape_string($filters['order_by']);
    }
    
    $sql = "SELECT t.*, 
                p.pillar_name, p.pillar_code, p.color AS pillar_color,
                w.workstream_name, w.workstream_code,
                ph.phase_name,
                u.fullname AS owner_name, u.email AS owner_email,
                DATEDIFF(CURDATE(), t.due_date) AS computed_days_overdue,
                CASE WHEN t.status NOT IN ('Completed','Verified','Cancelled') AND CURDATE() > t.due_date THEN 1 ELSE 0 END AS computed_is_overdue
            FROM tm_tasks t
            LEFT JOIN tm_pillars p ON t.pillar_id = p.id
            LEFT JOIN tm_workstreams w ON t.workstream_id = w.id
            LEFT JOIN tm_phases ph ON t.phase_id = ph.id
            LEFT JOIN registered_users u ON t.owner_id = u.id
            WHERE $where_sql
            ORDER BY $order
            LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($sql);
    $tasks = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
    }
    return $tasks;
}

/**
 * Count tasks with filters (for pagination / stats)
 */
function tm_count_tasks($conn, $filters = []) {
    $where = ["1=1"];
    
    if (!empty($filters['strategy_year'])) $where[] = "strategy_year = " . intval($filters['strategy_year']);
    if (!empty($filters['pillar_id'])) $where[] = "pillar_id = " . intval($filters['pillar_id']);
    if (!empty($filters['workstream_id'])) $where[] = "workstream_id = " . intval($filters['workstream_id']);
    if (!empty($filters['owner_id'])) $where[] = "owner_id = " . intval($filters['owner_id']);
    if (!empty($filters['status'])) {
        if (is_array($filters['status'])) {
            $statuses = implode("','", array_map([$conn, 'real_escape_string'], $filters['status']));
            $where[] = "status IN ('$statuses')";
        } else {
            $where[] = "status = '" . $conn->real_escape_string($filters['status']) . "'";
        }
    }
    if (!empty($filters['priority'])) $where[] = "priority = '" . $conn->real_escape_string($filters['priority']) . "'";
    if (isset($filters['is_overdue']) && $filters['is_overdue']) {
        $where[] = "status NOT IN ('Completed','Verified','Cancelled') AND CURDATE() > due_date";
    }
    if (!empty($filters['workstream_ids'])) {
        $ws_ids = implode(',', array_map('intval', $filters['workstream_ids']));
        $where[] = "workstream_id IN ($ws_ids)";
    }
    
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM tm_tasks WHERE " . implode(' AND ', $where));
    return $result ? $result->fetch_assoc()['cnt'] : 0;
}

/**
 * Get task statistics summary
 */
function tm_get_stats($conn, $filters = []) {
    $base_where = ["1=1"];
    if (!empty($filters['strategy_year'])) $base_where[] = "strategy_year = " . intval($filters['strategy_year']);
    if (!empty($filters['pillar_id'])) $base_where[] = "pillar_id = " . intval($filters['pillar_id']);
    if (!empty($filters['workstream_id'])) $base_where[] = "workstream_id = " . intval($filters['workstream_id']);
    if (!empty($filters['owner_id'])) $base_where[] = "owner_id = " . intval($filters['owner_id']);
    if (!empty($filters['workstream_ids'])) {
        $ws_ids = implode(',', array_map('intval', $filters['workstream_ids']));
        $base_where[] = "workstream_id IN ($ws_ids)";
    }
    $where_sql = implode(' AND ', $base_where);
    
    $stats = [
        'total' => 0,
        'completed' => 0,
        'verified' => 0,
        'in_progress' => 0,
        'assigned' => 0,
        'blocked' => 0,
        'overdue' => 0,
        'on_hold' => 0,
        'cancelled' => 0,
        'completion_pct' => 0,
    ];
    
    // Total
    $r = $conn->query("SELECT COUNT(*) AS c FROM tm_tasks WHERE $where_sql AND status != 'Cancelled'");
    $stats['total'] = $r ? $r->fetch_assoc()['c'] : 0;
    
    // By status
    $r = $conn->query("SELECT status, COUNT(*) AS c FROM tm_tasks WHERE $where_sql GROUP BY status");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $key = strtolower(str_replace(' ', '_', $row['status']));
            $stats[$key] = $row['c'];
        }
    }
    
    // Overdue (computed)
    $r = $conn->query("SELECT COUNT(*) AS c FROM tm_tasks WHERE $where_sql AND status NOT IN ('Completed','Verified','Cancelled') AND CURDATE() > due_date");
    $stats['overdue'] = $r ? $r->fetch_assoc()['c'] : 0;
    
    // Completion %
    if ($stats['total'] > 0) {
        $done = ($stats['completed'] ?? 0) + ($stats['verified'] ?? 0);
        $stats['completion_pct'] = round(($done / $stats['total']) * 100, 1);
    }
    
    return $stats;
}

// ============================================
// ACTIVITY LOG
// ============================================

/**
 * Log an activity/audit entry for a task
 */
function tm_log_activity($conn, $task_id, $type, $description, $old_value = null, $new_value = null, $reason = null, $performed_by = null) {
    $task_id = intval($task_id);
    if (!$performed_by) $performed_by = $_SESSION['login_id'] ?? null;
    
    $desc = $conn->real_escape_string($description);
    $old = $old_value !== null ? "'" . $conn->real_escape_string($old_value) . "'" : "NULL";
    $new = $new_value !== null ? "'" . $conn->real_escape_string($new_value) . "'" : "NULL";
    $rsn = $reason !== null ? "'" . $conn->real_escape_string($reason) . "'" : "NULL";
    $by = $performed_by ? intval($performed_by) : "NULL";
    $ip = isset($_SERVER['REMOTE_ADDR']) ? "'" . $conn->real_escape_string($_SERVER['REMOTE_ADDR']) . "'" : "NULL";
    
    $sql = "INSERT INTO tm_task_activity (task_id, activity_type, description, old_value, new_value, reason, performed_by, ip_address)
            VALUES ($task_id, '$type', '$desc', $old, $new, $rsn, $by, $ip)";
    
    return $conn->query($sql);
}

/**
 * Get activity log for a task
 */
function tm_get_activity($conn, $task_id, $limit = 50) {
    $task_id = intval($task_id);
    $result = $conn->query("
        SELECT a.*, u.fullname AS performed_by_name 
        FROM tm_task_activity a
        LEFT JOIN registered_users u ON a.performed_by = u.id
        WHERE a.task_id = $task_id
        ORDER BY a.performed_at DESC
        LIMIT $limit
    ");
    $activities = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
    }
    return $activities;
}

// ============================================
// DROPDOWNS / LOOKUPS
// ============================================

/**
 * Get all active pillars
 */
function tm_get_pillars($conn) {
    $result = $conn->query("SELECT * FROM tm_pillars WHERE status = 1 ORDER BY sort_order, pillar_name");
    $items = [];
    if ($result) while ($row = $result->fetch_assoc()) $items[] = $row;
    return $items;
}

/**
 * Get workstreams, optionally filtered by pillar
 */
function tm_get_workstreams($conn, $pillar_id = null) {
    $where = "status = 1";
    if ($pillar_id) $where .= " AND pillar_id = " . intval($pillar_id);
    $result = $conn->query("SELECT * FROM tm_workstreams WHERE $where ORDER BY sort_order, workstream_name");
    $items = [];
    if ($result) while ($row = $result->fetch_assoc()) $items[] = $row;
    return $items;
}

/**
 * Get phases
 */
function tm_get_phases($conn) {
    $result = $conn->query("SELECT * FROM tm_phases WHERE status = 1 ORDER BY sort_order, phase_name");
    $items = [];
    if ($result) while ($row = $result->fetch_assoc()) $items[] = $row;
    return $items;
}

/**
 * Get all staff for owner dropdown
 */
function tm_get_staff_list($conn) {
    $result = $conn->query("SELECT id, fullname, email FROM registered_users WHERE status = 1 ORDER BY fullname");
    $items = [];
    if ($result) while ($row = $result->fetch_assoc()) $items[] = $row;
    return $items;
}

// ============================================
// NOTIFICATIONS
// ============================================

/**
 * Create a notification
 */
function tm_create_notification($conn, $recipient_id, $type, $subject, $message, $task_id = null, $support_request_id = null, $priority = 'Normal') {
    $recipient_id = intval($recipient_id);
    $type = $conn->real_escape_string($type);
    $subject = $conn->real_escape_string($subject);
    $message = $conn->real_escape_string($message);
    $task_ref = $task_id ? intval($task_id) : 'NULL';
    $sr_ref = $support_request_id ? intval($support_request_id) : 'NULL';
    $priority = $conn->real_escape_string($priority);
    
    return $conn->query("INSERT INTO tm_notifications (task_id, support_request_id, recipient_id, notification_type, subject, message, priority)
                          VALUES ($task_ref, $sr_ref, $recipient_id, '$type', '$subject', '$message', '$priority')");
}

/**
 * Get unread notifications for a user
 */
function tm_get_notifications($conn, $user_id, $unread_only = true, $limit = 20) {
    $user_id = intval($user_id);
    $where = "recipient_id = $user_id";
    if ($unread_only) $where .= " AND is_read = 0";
    
    $result = $conn->query("SELECT * FROM tm_notifications WHERE $where ORDER BY created_at DESC LIMIT $limit");
    $items = [];
    if ($result) while ($row = $result->fetch_assoc()) $items[] = $row;
    return $items;
}

/**
 * Mark notification as read
 */
function tm_mark_notification_read($conn, $notification_id, $user_id = null) {
    $id = intval($notification_id);
    $where = "id = $id";
    if ($user_id) $where .= " AND recipient_id = " . intval($user_id);
    return $conn->query("UPDATE tm_notifications SET is_read = 1, read_at = NOW() WHERE $where");
}

/**
 * Count unread notifications
 */
function tm_count_unread_notifications($conn, $user_id) {
    $user_id = intval($user_id);
    $result = $conn->query("SELECT COUNT(*) AS c FROM tm_notifications WHERE recipient_id = $user_id AND is_read = 0");
    return $result ? $result->fetch_assoc()['c'] : 0;
}

// ============================================
// EVIDENCE / ATTACHMENTS
// ============================================

/**
 * Add evidence to a task
 */
function tm_add_evidence($conn, $task_id, $data, $uploaded_by = null) {
    if (!$uploaded_by) $uploaded_by = $_SESSION['login_id'] ?? 0;
    $task_id = intval($task_id);
    
    $type = $conn->real_escape_string($data['evidence_type'] ?? 'file');
    $file_name = isset($data['file_name']) ? "'" . $conn->real_escape_string($data['file_name']) . "'" : "NULL";
    $file_path = isset($data['file_path']) ? "'" . $conn->real_escape_string($data['file_path']) . "'" : "NULL";
    $file_size = isset($data['file_size']) ? intval($data['file_size']) : "NULL";
    $mime = isset($data['mime_type']) ? "'" . $conn->real_escape_string($data['mime_type']) . "'" : "NULL";
    $url = isset($data['link_url']) ? "'" . $conn->real_escape_string($data['link_url']) . "'" : "NULL";
    $note = isset($data['note_text']) ? "'" . $conn->real_escape_string($data['note_text']) . "'" : "NULL";
    $desc = isset($data['description']) ? "'" . $conn->real_escape_string($data['description']) . "'" : "NULL";
    
    $sql = "INSERT INTO tm_task_evidence (task_id, evidence_type, file_name, file_path, file_size, mime_type, link_url, note_text, description, uploaded_by)
            VALUES ($task_id, '$type', $file_name, $file_path, $file_size, $mime, $url, $note, $desc, " . intval($uploaded_by) . ")";
    
    if ($conn->query($sql)) {
        tm_log_activity($conn, $task_id, 'evidence_upload', "Evidence uploaded: " . ($data['file_name'] ?? $data['link_url'] ?? 'note'), null, null, null, $uploaded_by);
        return $conn->insert_id;
    }
    return false;
}

/**
 * Get evidence for a task
 */
function tm_get_evidence($conn, $task_id) {
    $task_id = intval($task_id);
    $result = $conn->query("
        SELECT e.*, u.fullname AS uploaded_by_name 
        FROM tm_task_evidence e 
        LEFT JOIN registered_users u ON e.uploaded_by = u.id
        WHERE e.task_id = $task_id 
        ORDER BY e.uploaded_at DESC
    ");
    $items = [];
    if ($result) while ($row = $result->fetch_assoc()) $items[] = $row;
    return $items;
}

// ============================================
// IMPORT HELPERS
// ============================================

/**
 * Create an import batch record
 */
function tm_create_import_batch($conn, $file_name, $imported_by) {
    $file_name = $conn->real_escape_string($file_name);
    $imported_by = intval($imported_by);
    $conn->query("INSERT INTO tm_import_batches (file_name, imported_by) VALUES ('$file_name', $imported_by)");
    return $conn->insert_id;
}

/**
 * Update import batch status
 */
function tm_update_import_batch($conn, $batch_id, $data) {
    $batch_id = intval($batch_id);
    $sets = [];
    foreach ($data as $key => $val) {
        if ($val === null) {
            $sets[] = "`$key` = NULL";
        } else {
            $sets[] = "`$key` = '" . $conn->real_escape_string($val) . "'";
        }
    }
    return $conn->query("UPDATE tm_import_batches SET " . implode(', ', $sets) . " WHERE id = $batch_id");
}