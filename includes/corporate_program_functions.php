<?php
/**
 * Corporate trainings — CRUD helpers for admin. Parallel to the academic helpers
 * but for corporate_programs / corporate_curriculum / corporate_lecturers.
 *
 * The bullet-list sections (challenges, why_solution, features, gains,
 * whats_included, who_should_attend, why_vantage, featured_solution_points) are
 * plain TEXT columns on corporate_programs, one bullet per line — no side tables.
 * Structured tables are used only for the day-grouped outline and the trainers.
 */

if (!function_exists('corporate_get_all_programs')) {

    /** Scalar columns (excluding id / sort_order / status / timestamps). */
    function corporate_program_columns()
    {
        return [
            'title', 'tagline', 'accreditation', 'start_date', 'end_date', 'location', 'venue_details',
            'mode', 'duration', 'fee', 'fee_unit', 'overview',
            'featured_solution_title', 'featured_solution_text', 'featured_solution_points',
            'challenges', 'why_solution', 'features', 'gains', 'whats_included', 'who_should_attend', 'why_vantage',
            'registration_link', 'course_outline_link', 'group_rate_link', 'contact_whatsapp', 'image_url',
        ];
    }

    /**
     * @param mysqli $conn
     * @param string $filter '' | 'upcoming' | 'past'
     * @return array
     */
    function corporate_get_all_programs($conn, $filter = '')
    {
        $where = '';
        if ($filter === 'upcoming') {
            $where = "WHERE (`end_date` IS NULL OR `end_date` >= CURDATE())";
        } elseif ($filter === 'past') {
            $where = "WHERE (`end_date` IS NOT NULL AND `end_date` < CURDATE())";
        }
        $sql = "SELECT * FROM `corporate_programs` $where ORDER BY (`start_date` IS NULL), `start_date` DESC, `sort_order` ASC, `id` ASC";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    function corporate_get_program($conn, $id)
    {
        $id = (int) $id;
        $res = mysqli_query($conn, "SELECT * FROM `corporate_programs` WHERE `id` = $id LIMIT 1");
        if (!$res || mysqli_num_rows($res) === 0) {
            return null;
        }
        $program = mysqli_fetch_assoc($res);

        $program['curriculum_rows'] = [];
        $cr = mysqli_query($conn, "SELECT `id`, `day_label`, `module_name`, `sort_order` FROM `corporate_curriculum` WHERE `program_id` = $id ORDER BY `sort_order` ASC, `id` ASC");
        if ($cr) {
            while ($r = mysqli_fetch_assoc($cr)) {
                $program['curriculum_rows'][] = $r;
            }
        }

        $program['lecturer_rows'] = [];
        $lr = mysqli_query($conn, "SELECT `id`, `photo_url`, `name`, `title`, `description`, `qualifications`, `sort_order` FROM `corporate_lecturers` WHERE `program_id` = $id ORDER BY `sort_order` ASC, `id` ASC");
        if ($lr) {
            while ($r = mysqli_fetch_assoc($lr)) {
                $program['lecturer_rows'][] = $r;
            }
        }

        return $program;
    }

    function corporate_save_curriculum($conn, $program_id, $rows_in)
    {
        $program_id = (int) $program_id;
        mysqli_query($conn, "DELETE FROM `corporate_curriculum` WHERE `program_id` = $program_id");
        $sort = 0;
        $stmt = $conn->prepare("INSERT INTO `corporate_curriculum` (`program_id`, `day_label`, `module_name`, `sort_order`) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        foreach ($rows_in as $row) {
            $day = isset($row['day_label']) ? trim((string) $row['day_label']) : '';
            $name = isset($row['module_name']) ? trim((string) $row['module_name']) : '';
            if ($name === '') {
                continue;
            }
            $sort++;
            $stmt->bind_param('issi', $program_id, $day, $name, $sort);
            $stmt->execute();
        }
        $stmt->close();
        return true;
    }

    function corporate_save_lecturers($conn, $program_id, $lecturer_rows)
    {
        $program_id = (int) $program_id;
        mysqli_query($conn, "DELETE FROM `corporate_lecturers` WHERE `program_id` = $program_id");
        $sort = 0;
        $stmt = $conn->prepare("INSERT INTO `corporate_lecturers` (`program_id`, `photo_url`, `name`, `title`, `description`, `qualifications`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        foreach ($lecturer_rows as $row) {
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $photo = isset($row['photo_url']) ? trim((string) $row['photo_url']) : '';
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            $description = isset($row['description']) ? trim((string) $row['description']) : '';
            $qualifications = isset($row['qualifications']) ? trim((string) $row['qualifications']) : '';
            if ($name === '') {
                continue;
            }
            $sort++;
            $stmt->bind_param('isssssi', $program_id, $photo, $name, $title, $description, $qualifications, $sort);
            $stmt->execute();
        }
        $stmt->close();
        return true;
    }

    function corporate_delete_program($conn, $id)
    {
        $id = (int) $id;
        mysqli_query($conn, "DELETE FROM `corporate_curriculum` WHERE `program_id` = $id");
        mysqli_query($conn, "DELETE FROM `corporate_lecturers` WHERE `program_id` = $id");
        return (bool) mysqli_query($conn, "DELETE FROM `corporate_programs` WHERE `id` = $id LIMIT 1");
    }

    function corporate_update_program_status($conn, $id, $status)
    {
        if (!in_array($status, ['active', 'inactive', 'draft'], true)) {
            return false;
        }
        $id = (int) $id;
        $st = $conn->real_escape_string($status);
        return (bool) mysqli_query($conn, "UPDATE `corporate_programs` SET `status` = '$st' WHERE `id` = $id LIMIT 1");
    }

    /** Build the value list (scalar cols + sort_order + status) from $data, dates blank -> null. */
    function corporate_build_values($data)
    {
        $vals = [];
        foreach (corporate_program_columns() as $c) {
            $v = isset($data[$c]) ? $data[$c] : '';
            if (($c === 'start_date' || $c === 'end_date') && trim((string) $v) === '') {
                $v = null;
            }
            $vals[] = $v;
        }
        $vals[] = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $vals[] = (isset($data['status']) && in_array($data['status'], ['active', 'inactive', 'draft'], true)) ? $data['status'] : 'active';
        return $vals;
    }

    function corporate_create_program($conn, $data, $curriculum_rows, $lecturers = [])
    {
        $cols = corporate_program_columns();
        $collist = '`' . implode('`, `', $cols) . '`, `sort_order`, `status`';
        $place = implode(', ', array_fill(0, count($cols) + 2, '?'));
        $stmt = $conn->prepare("INSERT INTO `corporate_programs` ($collist) VALUES ($place)");
        if (!$stmt) {
            return null;
        }
        $vals = corporate_build_values($data);
        $types = str_repeat('s', count($cols)) . 'is';
        $bind = [$types];
        for ($i = 0; $i < count($vals); $i++) {
            $bind[] = &$vals[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $newId = (int) $stmt->insert_id;
        $stmt->close();

        corporate_save_curriculum($conn, $newId, $curriculum_rows);
        corporate_save_lecturers($conn, $newId, is_array($lecturers) ? $lecturers : []);
        return $newId;
    }

    function corporate_update_program($conn, $id, $data, $curriculum_rows, $lecturers = [])
    {
        $id = (int) $id;
        $cols = corporate_program_columns();
        $set = [];
        foreach ($cols as $c) {
            $set[] = "`$c` = ?";
        }
        $set[] = "`sort_order` = ?";
        $set[] = "`status` = ?";
        $stmt = $conn->prepare("UPDATE `corporate_programs` SET " . implode(', ', $set) . " WHERE `id` = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $vals = corporate_build_values($data);
        $vals[] = $id;
        $types = str_repeat('s', count($cols)) . 'isi';
        $bind = [$types];
        for ($i = 0; $i < count($vals); $i++) {
            $bind[] = &$vals[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return false;
        }
        corporate_save_curriculum($conn, $id, $curriculum_rows);
        corporate_save_lecturers($conn, $id, is_array($lecturers) ? $lecturers : []);
        return true;
    }

    function corporate_collect_post_data()
    {
        $f = static function ($key, $default = '') {
            return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
        };
        $data = [];
        foreach (corporate_program_columns() as $c) {
            $data[$c] = $f($c, $c === 'registration_link' ? '#' : '');
        }
        $data['sort_order'] = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
        $data['status'] = $f('status', 'active');
        return $data;
    }

    function corporate_collect_curriculum_from_post()
    {
        if (empty($_POST['module_name']) || !is_array($_POST['module_name'])) {
            return [];
        }
        $names = $_POST['module_name'];
        $days = (isset($_POST['day_label']) && is_array($_POST['day_label'])) ? $_POST['day_label'] : [];
        $rows = [];
        foreach ($names as $idx => $moduleName) {
            $name = trim((string) $moduleName);
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'module_name' => $name,
                'day_label' => isset($days[$idx]) ? trim((string) $days[$idx]) : '',
            ];
        }
        return $rows;
    }

    function corporate_collect_lecturers_from_post()
    {
        $names = (isset($_POST['lecturer_name']) && is_array($_POST['lecturer_name'])) ? $_POST['lecturer_name'] : [];
        $titles = (isset($_POST['lecturer_title']) && is_array($_POST['lecturer_title'])) ? $_POST['lecturer_title'] : [];
        $descs = (isset($_POST['lecturer_description']) && is_array($_POST['lecturer_description'])) ? $_POST['lecturer_description'] : [];
        $quals = (isset($_POST['lecturer_qualifications']) && is_array($_POST['lecturer_qualifications'])) ? $_POST['lecturer_qualifications'] : [];
        $existingPhotos = (isset($_POST['lecturer_existing_photo']) && is_array($_POST['lecturer_existing_photo'])) ? $_POST['lecturer_existing_photo'] : [];
        $rows = [];
        foreach ($names as $i => $name) {
            $rows[] = [
                'name' => trim((string) $name),
                'title' => isset($titles[$i]) ? trim((string) $titles[$i]) : '',
                'description' => isset($descs[$i]) ? trim((string) $descs[$i]) : '',
                'qualifications' => isset($quals[$i]) ? trim((string) $quals[$i]) : '',
                'existing_photo_url' => isset($existingPhotos[$i]) ? trim((string) $existingPhotos[$i]) : '',
            ];
        }
        return $rows;
    }
}
