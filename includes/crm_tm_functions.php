<?php
/**
 * CRM Task Manager Functions
 * Standalone helper functions for CRM tasks (do not touch existing tm_* or project/task_list modules)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ROLE HELPERS
// ============================================

function crm_tm_current_user_id(): int
{
    return isset($_SESSION['login_id']) ? (int) $_SESSION['login_id'] : 0;
}

/**
 * Admin = registered_users.user_type = 'admin'
 */
function crm_tm_is_admin(mysqli $conn, ?int $user_id = null): bool
{
    if ($user_id === null) {
        $user_id = crm_tm_current_user_id();
    }
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    $res = $conn->query("SELECT user_type FROM registered_users WHERE id = {$user_id} LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        return ($row['user_type'] ?? '') === 'admin';
    }
    return false;
}

/**
 * HOD (manager) = registered_users.user_type = 'manager'
 */
function crm_tm_is_hod(mysqli $conn, ?int $user_id = null): bool
{
    if ($user_id === null) {
        $user_id = crm_tm_current_user_id();
    }
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    $res = $conn->query("SELECT user_type FROM registered_users WHERE id = {$user_id} LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        return ($row['user_type'] ?? '') === 'manager';
    }
    return false;
}

/**
 * Get department ID for a user from registered_users.department_id (if present)
 */
function crm_tm_get_user_department_id(mysqli $conn, ?int $user_id = null): ?int
{
    if ($user_id === null) {
        $user_id = crm_tm_current_user_id();
    }
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }
    $res = $conn->query("SELECT department_id FROM registered_users WHERE id = {$user_id} LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        if (!empty($row['department_id'])) {
            return (int) $row['department_id'];
        }
    }
    return null;
}

/**
 * Role context for CRM task manager
 * Returns: 'admin', 'hod', 'staff'
 */
function crm_tm_get_user_role(mysqli $conn, ?int $user_id = null): string
{
    if (crm_tm_is_admin($conn, $user_id)) {
        return 'admin';
    }
    if (crm_tm_is_hod($conn, $user_id)) {
        return 'hod';
    }
    return 'staff';
}

// ============================================
// ID GENERATION
// ============================================

/**
 * Generate next CRM Task Code: CRM-2026-000001
 */
function crm_tm_generate_task_code(mysqli $conn, ?int $year = null): string
{
    if ($year === null) {
        $year = (int) date('Y');
    }
    $year = (int) $year;

    $conn->query("INSERT INTO crm_tm_task_sequence (`year`, `last_number`) VALUES ({$year}, 1)
                  ON DUPLICATE KEY UPDATE last_number = last_number + 1");

    $res = $conn->query("SELECT last_number FROM crm_tm_task_sequence WHERE `year` = {$year} LIMIT 1");
    $num = 1;
    if ($res && ($row = $res->fetch_assoc())) {
        $num = (int) $row['last_number'];
    }

    $prefix = 'CRM';
    return $prefix . '-' . $year . '-' . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
}

// ============================================
// CRUD HELPERS
// ============================================

/**
 * Sanitize rich-text HTML coming from a WYSIWYG editor (e.g. Summernote).
 * - Removes disallowed tags and attributes
 * - Blocks scripts and event handler attributes
 * - Restricts links to safe protocols
 */
function crm_tm_sanitize_rich_text(string $html): string
{
    $html = str_replace("\0", '', $html);
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowed_tags = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's',
        'ul', 'ol', 'li',
        'blockquote', 'code', 'pre',
        'a',
    ];

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $walker = function (DOMNode $node) use (&$walker, $allowed_tags): void {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            $tag_name = strtolower((string) $node->nodeName);

            if (!in_array($tag_name, $allowed_tags, true)) {
                $parent = $node->parentNode;
                if ($parent !== null) {
                    while ($node->firstChild !== null) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                }
                return;
            }

            if ($node->hasAttributes()) {
                $attributes_to_remove = [];
                foreach ($node->attributes as $attr) {
                    $attr_name = strtolower((string) $attr->nodeName);

                    if (str_starts_with($attr_name, 'on')) {
                        $attributes_to_remove[] = $attr_name;
                        continue;
                    }

                    if ($tag_name !== 'a') {
                        $attributes_to_remove[] = $attr_name;
                        continue;
                    }

                    if (!in_array($attr_name, ['href', 'target', 'rel'], true)) {
                        $attributes_to_remove[] = $attr_name;
                        continue;
                    }

                    if ($attr_name === 'href') {
                        $href = trim((string) $attr->nodeValue);
                        $href_lower = strtolower($href);
                        $is_safe =
                            $href === '' ||
                            str_starts_with($href_lower, 'http://') ||
                            str_starts_with($href_lower, 'https://') ||
                            str_starts_with($href_lower, 'mailto:') ||
                            str_starts_with($href_lower, '#');
                        if (!$is_safe) {
                            $node->removeAttribute('href');
                        }
                    }
                }
                foreach ($attributes_to_remove as $attr_name) {
                    $node->removeAttribute($attr_name);
                }
            }

            if ($tag_name === 'a') {
                if (!$node->hasAttribute('rel')) {
                    $node->setAttribute('rel', 'noopener noreferrer');
                }
                if ($node->hasAttribute('target')) {
                    $target = strtolower((string) $node->getAttribute('target'));
                    if (!in_array($target, ['_blank', '_self'], true)) {
                        $node->removeAttribute('target');
                    }
                }
            }
        }

        if ($node->hasChildNodes()) {
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }
            foreach ($children as $child) {
                $walker($child);
            }
        }
    };

    $walker($dom);

    $sanitized = $dom->saveHTML() ?: '';
    $sanitized = trim($sanitized);

    return $sanitized;
}

function crm_tm_rich_text_has_content(string $html): bool
{
    $text = trim(strip_tags($html));
    return $text !== '';
}

function crm_tm_render_rich_text(string $html): string
{
    $sanitized = crm_tm_sanitize_rich_text($html);
    if ($sanitized === '') {
        return '';
    }
    return $sanitized;
}

/**
 * Create a new CRM task
 * @param array $data
 * @param int|null $created_by
 * @return int|false new task ID or false
 */
function crm_tm_create_task(mysqli $conn, array $data, ?int $created_by = null)
{
    if ($created_by === null) {
        $created_by = crm_tm_current_user_id();
    }
    $created_by = (int) $created_by;
    $strategy_year = isset($data['year']) ? (int) $data['year'] : (int) date('Y');
    $task_code = crm_tm_generate_task_code($conn, $strategy_year);

    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        return false;
    }

    $assigned_to = (int) ($data['assigned_to_user_id'] ?? 0);
    if ($assigned_to <= 0) {
        return false;
    }

    $description = $conn->real_escape_string((string) ($data['description'] ?? ''));
    $status = $conn->real_escape_string((string) ($data['status'] ?? 'pending'));
    $priority = $conn->real_escape_string((string) ($data['priority'] ?? 'medium'));
    $requesting_user_id = isset($data['requesting_user_id']) ? (int) $data['requesting_user_id'] : null;
    $hod_owner_id = isset($data['hod_owner_id']) ? (int) $data['hod_owner_id'] : null;
    $department_id = isset($data['department_id']) ? (int) $data['department_id'] : null;
    $cross_department_flag = !empty($data['cross_department_flag']) ? 1 : 0;
    $start_date = !empty($data['start_date']) ? "'" . $conn->real_escape_string($data['start_date']) . "'" : "NULL";
    $due_date = !empty($data['due_date']) ? "'" . $conn->real_escape_string($data['due_date']) . "'" : "NULL";
    $support_summary = !empty($data['support_summary']) ? "'" . $conn->real_escape_string($data['support_summary']) . "'" : "NULL";

    $requesting_sql = $requesting_user_id ? (string) $requesting_user_id : "NULL";
    $hod_sql = $hod_owner_id ? (string) $hod_owner_id : "NULL";
    $dept_sql = $department_id ? (string) $department_id : "NULL";

    $title_sql = $conn->real_escape_string($title);

    $sql = "
        INSERT INTO crm_tm_tasks
        (task_code, title, description, status, priority,
         created_by, assigned_to_user_id, requesting_user_id, hod_owner_id, department_id,
         cross_department_flag, start_date, due_date, progress_pct, needs_support, support_summary)
        VALUES
        (
            '{$task_code}',
            '{$title_sql}',
            '{$description}',
            '{$status}',
            '{$priority}',
            {$created_by},
            {$assigned_to},
            {$requesting_sql},
            {$hod_sql},
            {$dept_sql},
            {$cross_department_flag},
            {$start_date},
            {$due_date},
            0,
            0,
            {$support_summary}
        )";

    if ($conn->query($sql)) {
        return (int) $conn->insert_id;
    }

    return false;
}

/**
 * Add an update (feedback / comment / status change) for a task
 */
function crm_tm_add_task_update(mysqli $conn, int $task_id, int $user_id, string $update_type, string $message, ?int $progress_pct = null): bool
{
    $task_id = (int) $task_id;
    $user_id = (int) $user_id;
    if ($task_id <= 0 || $user_id <= 0) {
        return false;
    }
    $update_type = $conn->real_escape_string($update_type);
    $message = crm_tm_sanitize_rich_text($message);
    $message_sql = $conn->real_escape_string($message);
    $progress_sql = $progress_pct !== null ? (int) $progress_pct : 'NULL';

    $sql = "
        INSERT INTO crm_tm_task_updates (task_id, user_id, update_type, progress_pct, message, created_at)
        VALUES ({$task_id}, {$user_id}, '{$update_type}', {$progress_sql}, '{$message_sql}', NOW())
    ";

    if (!$conn->query($sql)) {
        return false;
    }

    // Optionally update progress and last_feedback_at on main task
    $progress_set = $progress_pct !== null ? ", progress_pct = " . (int) $progress_pct : "";
    $conn->query("
        UPDATE crm_tm_tasks
        SET last_feedback_at = NOW() {$progress_set}
        WHERE id = {$task_id}
    ");

    return true;
}

/**
 * Create a requirement record (what is needed to achieve the task)
 */
function crm_tm_add_requirement(mysqli $conn, int $task_id, int $requested_by_user_id, string $text): bool
{
    $task_id = (int) $task_id;
    $requested_by_user_id = (int) $requested_by_user_id;
    if ($task_id <= 0 || $requested_by_user_id <= 0) {
        return false;
    }
    $text = crm_tm_sanitize_rich_text($text);
    $text_sql = $conn->real_escape_string($text);

    $sql = "
        INSERT INTO crm_tm_task_requirements (task_id, requested_by_user_id, requirement_text, status, created_at)
        VALUES ({$task_id}, {$requested_by_user_id}, '{$text_sql}', 'open', NOW())
    ";

    return (bool) $conn->query($sql);
}

/**
 * Fetch tasks list scoped by role and optional filters
 * $filters: ['status' => ..., 'department_id' => ..., 'assigned_to' => ..., 'search' => ...]
 */
function crm_tm_get_tasks(mysqli $conn, ?int $user_id = null, array $filters = []): array
{
    if ($user_id === null) {
        $user_id = crm_tm_current_user_id();
    }
    $user_id = (int) $user_id;
    $role = crm_tm_get_user_role($conn, $user_id);
    $dept_id = crm_tm_get_user_department_id($conn, $user_id);

    $where = ["1=1"];

    if ($role === 'staff') {
        // Staff: only see tasks explicitly assigned to them
        $where[] = "t.assigned_to_user_id = {$user_id}";
    } elseif ($role === 'hod' && $dept_id) {
        $where[] = "(t.department_id = {$dept_id} OR t.assigned_to_user_id = {$user_id} OR t.requesting_user_id = {$user_id})";
    } else {
        // admin: no additional restriction
    }

    if (!empty($filters['status'])) {
        $status = (string) $filters['status'];
        if ($status === 'overdue') {
            $where[] = "t.due_date IS NOT NULL
                        AND t.due_date <> '0000-00-00'
                        AND t.due_date < CURDATE()
                        AND t.status <> 'completed'
                        AND t.status <> 'cancelled'";
        } else {
            $status_sql = $conn->real_escape_string($status);
            $where[] = "t.status = '{$status_sql}'";
        }
    }
    if (!empty($filters['department_id'])) {
        $d = (int) $filters['department_id'];
        $where[] = "t.department_id = {$d}";
    }
    if (!empty($filters['assigned_to'])) {
        $a = (int) $filters['assigned_to'];
        $where[] = "t.assigned_to_user_id = {$a}";
    }
    if (!empty($filters['search'])) {
        $s = $conn->real_escape_string((string) $filters['search']);
        $where[] = "(title LIKE '%{$s}%' OR task_code LIKE '%{$s}%')";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "
        SELECT t.*, u.fullname AS assignee_name, d.department_name
        FROM crm_tm_tasks t
        LEFT JOIN registered_users u ON u.id = t.assigned_to_user_id
        LEFT JOIN departments d ON d.id = t.department_id
        WHERE {$where_sql}
        ORDER BY t.created_at DESC
        LIMIT 500
    ";

    $rows = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Fetch the latest task update (progress/feedback) per task for the given task IDs.
 * Returns map: task_id => [ 'message' => ..., 'progress_pct' => ...|null, 'created_at' => ..., 'update_type' => ... ]
 */
function crm_tm_get_last_task_updates(mysqli $conn, array $task_ids): array
{
    $task_ids = array_filter(array_map('intval', $task_ids));
    if (empty($task_ids)) {
        return [];
    }
    $ids_sql = implode(',', $task_ids);

    $sql = "
        SELECT u.task_id, u.message, u.progress_pct, u.created_at, u.update_type
        FROM crm_tm_task_updates u
        INNER JOIN (
            SELECT task_id, MAX(created_at) AS max_at
            FROM crm_tm_task_updates
            WHERE task_id IN ({$ids_sql})
            GROUP BY task_id
        ) latest ON latest.task_id = u.task_id AND latest.max_at = u.created_at
        WHERE u.task_id IN ({$ids_sql})
        ORDER BY u.task_id, u.created_at DESC
    ";
    $out = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $tid = (int) $row['task_id'];
            if (!isset($out[$tid])) {
                $out[$tid] = [
                    'message' => $row['message'] ?? '',
                    'progress_pct' => isset($row['progress_pct']) ? (int) $row['progress_pct'] : null,
                    'created_at' => $row['created_at'] ?? '',
                    'update_type' => $row['update_type'] ?? 'comment',
                ];
            }
        }
    }
    return $out;
}

/**
 * Update a task's status and/or progress in a consistent way.
 * Optionally logs an update entry.
 */
function crm_tm_update_task_status(
    mysqli $conn,
    int $task_id,
    int $user_id,
    ?string $new_status = null,
    ?int $new_progress_pct = null,
    ?string $message = null,
    string $update_type = 'status_change'
): bool {
    $task_id = (int) $task_id;
    $user_id = (int) $user_id;
    if ($task_id <= 0 || $user_id <= 0) {
        return false;
    }

    $sets = [];
    if ($new_status !== null) {
        $status_sql = $conn->real_escape_string($new_status);
        $sets[] = "status = '{$status_sql}'";
    }
    if ($new_progress_pct !== null) {
        if ($new_progress_pct < 0) {
            $new_progress_pct = 0;
        } elseif ($new_progress_pct > 100) {
            $new_progress_pct = 100;
        }
        $sets[] = 'progress_pct = ' . (int) $new_progress_pct;
    }
    if (empty($sets)) {
        return false;
    }

    $sets[] = 'last_feedback_at = NOW()';
    $sql = "
        UPDATE crm_tm_tasks
        SET " . implode(', ', $sets) . "
        WHERE id = {$task_id}
        LIMIT 1
    ";

    if (!$conn->query($sql)) {
        return false;
    }

    if ($message !== null && $message !== '') {
        crm_tm_add_task_update(
            $conn,
            $task_id,
            $user_id,
            $update_type,
            $message,
            $new_progress_pct
        );
    }

    return true;
}

/**
 * Update a task's priority and optionally log it as an update.
 */
function crm_tm_update_task_priority(
    mysqli $conn,
    int $task_id,
    int $user_id,
    string $new_priority,
    bool $log_update = true
): bool {
    $task_id = (int) $task_id;
    $user_id = (int) $user_id;
    if ($task_id <= 0 || $user_id <= 0) {
        return false;
    }

    $allowed = ['low', 'medium', 'high', 'critical'];
    if (!in_array($new_priority, $allowed, true)) {
        return false;
    }

    $priority_sql = $conn->real_escape_string($new_priority);
    $sql = "
        UPDATE crm_tm_tasks
        SET priority = '{$priority_sql}'
        WHERE id = {$task_id}
        LIMIT 1
    ";

    if (!$conn->query($sql)) {
        return false;
    }

    if ($log_update) {
        $message = 'Priority changed to ' . ucfirst($new_priority) . '.';
        crm_tm_add_task_update(
            $conn,
            $task_id,
            $user_id,
            'priority_change',
            $message,
            null
        );
    }

    return true;
}

