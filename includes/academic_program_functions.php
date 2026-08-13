<?php
/**
 * Academic programs (certificates & diplomas) — CRUD helpers for admin.
 */

if (!function_exists('academic_get_all_programs')) {

    /**
     * @param mysqli $conn
     * @param string|null $program_type
     * @return array
     */
    function academic_get_all_programs($conn, $program_type = null)
    {
        if ($program_type === 'certificate' || $program_type === 'diploma') {
            $type = $conn->real_escape_string($program_type);
            $sql = "SELECT * FROM `academic_programs` WHERE `program_type` = '$type' ORDER BY `sort_order` ASC, `id` ASC";
        } else {
            $sql = "SELECT * FROM `academic_programs` ORDER BY `program_type` ASC, `sort_order` ASC, `id` ASC";
        }
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

    /**
     * @param mysqli $conn
     * @param int $id
     * @return array|null
     */
    /** Idempotent: adds program_curriculum.unit_id if an older install is missing it. */
    function academic_ensure_schema($conn)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $r = mysqli_query($conn, "SHOW COLUMNS FROM `program_curriculum` LIKE 'unit_id'");
            if ($r && mysqli_num_rows($r) === 0) {
                mysqli_query($conn, "ALTER TABLE `program_curriculum` ADD COLUMN `unit_id` VARCHAR(64) NULL AFTER `module_name`");
            }
        } catch (\Throwable $e) {
            error_log('academic_ensure_schema: ' . $e->getMessage());
        }
    }

    function academic_get_program($conn, $id)
    {
        $id = (int) $id;
        academic_ensure_schema($conn);
        $res = mysqli_query($conn, "SELECT * FROM `academic_programs` WHERE `id` = $id LIMIT 1");
        if (!$res || mysqli_num_rows($res) === 0) {
            return null;
        }
        $program = mysqli_fetch_assoc($res);

        $program['curriculum_rows'] = [];
        $cr = mysqli_query($conn, "SELECT `id`, `module_name`, `unit_id`, `curriculum_tier`, `sort_order` FROM `program_curriculum` WHERE `program_id` = $id ORDER BY FIELD(`curriculum_tier`, 'foundational', 'intermediate', 'advanced'), `sort_order` ASC, `id` ASC");
        if ($cr) {
            while ($r = mysqli_fetch_assoc($cr)) {
                $program['curriculum_rows'][] = $r;
            }
        }

        $program['outcome_rows'] = [];
        $or = mysqli_query($conn, "SELECT `id`, `outcome_text`, `sort_order` FROM `program_outcomes` WHERE `program_id` = $id ORDER BY `sort_order` ASC, `id` ASC");
        if ($or) {
            while ($r = mysqli_fetch_assoc($or)) {
                $program['outcome_rows'][] = $r;
            }
        }

        $program['lecturer_rows'] = [];
        $lr = mysqli_query($conn, "SELECT `id`, `photo_url`, `name`, `title`, `description`, `qualifications`, `sort_order` FROM `program_lecturers` WHERE `program_id` = $id ORDER BY `sort_order` ASC, `id` ASC");
        if ($lr) {
            while ($r = mysqli_fetch_assoc($lr)) {
                $program['lecturer_rows'][] = $r;
            }
        }

        return $program;
    }

    /**
     * @param mysqli $conn
     * @param int $program_id
     * @param array $module_names
     * @return bool
     */
    function academic_save_curriculum($conn, $program_id, $module_rows)
    {
        $program_id = (int) $program_id;
        academic_ensure_schema($conn);
        mysqli_query($conn, "DELETE FROM `program_curriculum` WHERE `program_id` = $program_id");
        $sort = 0;
        $stmt = $conn->prepare("INSERT INTO `program_curriculum` (`program_id`, `module_name`, `unit_id`, `curriculum_tier`, `sort_order`) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        foreach ($module_rows as $row) {
            $name = isset($row['module_name']) ? trim((string) $row['module_name']) : '';
            $unit = isset($row['unit_id']) ? trim((string) $row['unit_id']) : '';
            $tier = isset($row['curriculum_tier']) ? trim((string) $row['curriculum_tier']) : 'foundational';
            if ($name === '') {
                continue;
            }
            if (!in_array($tier, array('foundational', 'intermediate', 'advanced'), true)) {
                $tier = 'foundational';
            }
            $sort++;
            $stmt->bind_param('isssi', $program_id, $name, $unit, $tier, $sort);
            $stmt->execute();
        }
        $stmt->close();
        return true;
    }

    /**
     * @param mysqli $conn
     * @param int $program_id
     * @param array $outcome_texts
     * @return bool
     */
    function academic_save_outcomes($conn, $program_id, $outcome_texts)
    {
        $program_id = (int) $program_id;
        mysqli_query($conn, "DELETE FROM `program_outcomes` WHERE `program_id` = $program_id");
        $sort = 0;
        $stmt = $conn->prepare("INSERT INTO `program_outcomes` (`program_id`, `outcome_text`, `sort_order`) VALUES (?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        foreach ($outcome_texts as $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            $sort++;
            $stmt->bind_param('isi', $program_id, $text, $sort);
            $stmt->execute();
        }
        $stmt->close();
        return true;
    }

    /**
     * @param mysqli $conn
     * @param int $program_id
     * @param array $lecturer_rows
     * @return bool
     */
    function academic_save_lecturers($conn, $program_id, $lecturer_rows)
    {
        $program_id = (int) $program_id;
        mysqli_query($conn, "DELETE FROM `program_lecturers` WHERE `program_id` = $program_id");
        $sort = 0;
        $stmt = $conn->prepare("INSERT INTO `program_lecturers` (`program_id`, `photo_url`, `name`, `title`, `description`, `qualifications`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }

        foreach ($lecturer_rows as $row) {
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $photo = isset($row['photo_url']) ? trim((string) $row['photo_url']) : '';
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            $description = isset($row['description']) ? trim((string) $row['description']) : '';
            $qualifications = isset($row['qualifications']) ? trim((string) $row['qualifications']) : '';

            if ($name === '' && $photo === '' && $title === '' && $description === '' && $qualifications === '') {
                continue;
            }
            if ($name === '') {
                // Skip incomplete row (name is required to display a lecturer)
                continue;
            }

            $sort++;
            $stmt->bind_param('isssssi', $program_id, $photo, $name, $title, $description, $qualifications, $sort);
            $stmt->execute();
        }

        $stmt->close();
        return true;
    }

    /**
     * @param mysqli $conn
     * @param int $id
     * @return bool
     */
    function academic_delete_program($conn, $id)
    {
        $id = (int) $id;
        return (bool) mysqli_query($conn, "DELETE FROM `academic_programs` WHERE `id` = $id LIMIT 1");
    }

    /**
     * @param mysqli $conn
     * @param int $id
     * @param string $status
     * @return bool
     */
    function academic_update_program_status($conn, $id, $status)
    {
        if (!in_array($status, ['active', 'inactive', 'draft'], true)) {
            return false;
        }
        $id = (int) $id;
        $st = $conn->real_escape_string($status);
        return (bool) mysqli_query($conn, "UPDATE `academic_programs` SET `status` = '$st' WHERE `id` = $id LIMIT 1");
    }

    /**
     * @param array $data associative keys matching academic_programs columns (except id)
     */
    /**
     * @param mysqli $conn
     * @param array $data
     * @param array $curriculum_modules
     * @param array $outcomes
     * @return int|null
     */
    function academic_create_program($conn, $data, $curriculum_modules, $outcomes, $lecturers = [])
    {
        $sql = "INSERT INTO `academic_programs` (
            `program_type`, `title`, `image_url`, `duration`, `mode`, `entry_requirements`, `fee`, `certification`, `registration_link`,
            `market_problem`, `solution`, `who_for`, `what_different`, `trainer_profile`, `fees_intakes`,
            `governing_body_name`, `governing_body_full_name`, `governing_body_logo_url`,
            `issuing_institution`, `accreditation_basis`, `professional_alignment`,
            `sort_order`, `status`
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $program_type = isset($data['program_type']) ? $data['program_type'] : 'certificate';
        $title = isset($data['title']) ? $data['title'] : '';
        $image_url = isset($data['image_url']) ? $data['image_url'] : '';
        $duration = isset($data['duration']) ? $data['duration'] : '';
        $mode = isset($data['mode']) ? $data['mode'] : '';
        $entry_requirements = isset($data['entry_requirements']) ? $data['entry_requirements'] : '';
        $fee = isset($data['fee']) ? $data['fee'] : '';
        $certification = isset($data['certification']) ? $data['certification'] : '';
        $registration_link = isset($data['registration_link']) ? $data['registration_link'] : '#';
        $market_problem = isset($data['market_problem']) ? $data['market_problem'] : '';
        $solution = isset($data['solution']) ? $data['solution'] : '';
        $who_for = isset($data['who_for']) ? $data['who_for'] : '';
        $what_different = isset($data['what_different']) ? $data['what_different'] : '';
        $trainer_profile = isset($data['trainer_profile']) ? $data['trainer_profile'] : '';
        $fees_intakes = isset($data['fees_intakes']) ? $data['fees_intakes'] : '';
        $governing_body_name = isset($data['governing_body_name']) ? $data['governing_body_name'] : null;
        $governing_body_full_name = isset($data['governing_body_full_name']) ? $data['governing_body_full_name'] : null;
        $governing_body_logo_url = isset($data['governing_body_logo_url']) ? $data['governing_body_logo_url'] : null;
        $issuing_institution = isset($data['issuing_institution']) ? $data['issuing_institution'] : '';
        $accreditation_basis = isset($data['accreditation_basis']) ? $data['accreditation_basis'] : '';
        $professional_alignment = isset($data['professional_alignment']) ? $data['professional_alignment'] : '';
        $sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $status = isset($data['status']) ? $data['status'] : 'active';

        if (!in_array($program_type, ['certificate', 'diploma'], true)) {
            $program_type = 'certificate';
        }
        if (!in_array($status, ['active', 'inactive', 'draft'], true)) {
            $status = 'active';
        }

        $gbn = (string) ($governing_body_name !== null ? $governing_body_name : '');
        $gbfn = (string) ($governing_body_full_name !== null ? $governing_body_full_name : '');
        $gbl = (string) ($governing_body_logo_url !== null ? $governing_body_logo_url : '');

        $types = str_repeat('s', 21) . 'is';
        $stmt->bind_param(
            $types,
            $program_type,
            $title,
            $image_url,
            $duration,
            $mode,
            $entry_requirements,
            $fee,
            $certification,
            $registration_link,
            $market_problem,
            $solution,
            $who_for,
            $what_different,
            $trainer_profile,
            $fees_intakes,
            $gbn,
            $gbfn,
            $gbl,
            $issuing_institution,
            $accreditation_basis,
            $professional_alignment,
            $sort_order,
            $status
        );

        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $newId = (int) $stmt->insert_id;
        $stmt->close();

        academic_save_curriculum($conn, $newId, $curriculum_modules);
        academic_save_outcomes($conn, $newId, $outcomes);
        academic_save_lecturers($conn, $newId, is_array($lecturers) ? $lecturers : []);

        return $newId;
    }

    /**
     * @param mysqli $conn
     * @param int $id
     * @param array $data
     * @param array $curriculum_modules
     * @param array $outcomes
     * @return bool
     */
    function academic_update_program($conn, $id, $data, $curriculum_modules, $outcomes, $lecturers = [])
    {
        $id = (int) $id;
        $sql = "UPDATE `academic_programs` SET
            `program_type` = ?, `title` = ?, `image_url` = ?, `duration` = ?, `mode` = ?, `entry_requirements` = ?, `fee` = ?, `certification` = ?, `registration_link` = ?,
            `market_problem` = ?, `solution` = ?, `who_for` = ?, `what_different` = ?, `trainer_profile` = ?, `fees_intakes` = ?,
            `governing_body_name` = ?, `governing_body_full_name` = ?, `governing_body_logo_url` = ?,
            `issuing_institution` = ?, `accreditation_basis` = ?, `professional_alignment` = ?,
            `sort_order` = ?, `status` = ?
            WHERE `id` = ? LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $program_type = isset($data['program_type']) ? $data['program_type'] : 'certificate';
        $title = isset($data['title']) ? $data['title'] : '';
        $image_url = isset($data['image_url']) ? $data['image_url'] : '';
        $duration = isset($data['duration']) ? $data['duration'] : '';
        $mode = isset($data['mode']) ? $data['mode'] : '';
        $entry_requirements = isset($data['entry_requirements']) ? $data['entry_requirements'] : '';
        $fee = isset($data['fee']) ? $data['fee'] : '';
        $certification = isset($data['certification']) ? $data['certification'] : '';
        $registration_link = isset($data['registration_link']) ? $data['registration_link'] : '#';
        $market_problem = isset($data['market_problem']) ? $data['market_problem'] : '';
        $solution = isset($data['solution']) ? $data['solution'] : '';
        $who_for = isset($data['who_for']) ? $data['who_for'] : '';
        $what_different = isset($data['what_different']) ? $data['what_different'] : '';
        $trainer_profile = isset($data['trainer_profile']) ? $data['trainer_profile'] : '';
        $fees_intakes = isset($data['fees_intakes']) ? $data['fees_intakes'] : '';
        $governing_body_name = isset($data['governing_body_name']) ? $data['governing_body_name'] : null;
        $governing_body_full_name = isset($data['governing_body_full_name']) ? $data['governing_body_full_name'] : null;
        $governing_body_logo_url = isset($data['governing_body_logo_url']) ? $data['governing_body_logo_url'] : null;
        $issuing_institution = isset($data['issuing_institution']) ? $data['issuing_institution'] : '';
        $accreditation_basis = isset($data['accreditation_basis']) ? $data['accreditation_basis'] : '';
        $professional_alignment = isset($data['professional_alignment']) ? $data['professional_alignment'] : '';
        $sort_order = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;
        $status = isset($data['status']) ? $data['status'] : 'active';

        if (!in_array($program_type, ['certificate', 'diploma'], true)) {
            $program_type = 'certificate';
        }
        if (!in_array($status, ['active', 'inactive', 'draft'], true)) {
            $status = 'active';
        }

        $gbn = (string) ($governing_body_name !== null ? $governing_body_name : '');
        $gbfn = (string) ($governing_body_full_name !== null ? $governing_body_full_name : '');
        $gbl = (string) ($governing_body_logo_url !== null ? $governing_body_logo_url : '');

        $types = str_repeat('s', 21) . 'isi';
        $stmt->bind_param(
            $types,
            $program_type,
            $title,
            $image_url,
            $duration,
            $mode,
            $entry_requirements,
            $fee,
            $certification,
            $registration_link,
            $market_problem,
            $solution,
            $who_for,
            $what_different,
            $trainer_profile,
            $fees_intakes,
            $gbn,
            $gbfn,
            $gbl,
            $issuing_institution,
            $accreditation_basis,
            $professional_alignment,
            $sort_order,
            $status,
            $id
        );

        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return false;
        }

        academic_save_curriculum($conn, $id, $curriculum_modules);
        academic_save_outcomes($conn, $id, $outcomes);
        academic_save_lecturers($conn, $id, is_array($lecturers) ? $lecturers : []);

        return true;
    }

    /**
     * @return array
     */
    function academic_collect_post_data()
    {
        $f = static function ($key, $default = '') {
            return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
        };

        return [
            'program_type' => $f('program_type', 'certificate'),
            'title' => $f('title'),
            'image_url' => $f('image_url'),
            'duration' => $f('duration'),
            'mode' => $f('mode'),
            'entry_requirements' => $f('entry_requirements'),
            'fee' => $f('fee'),
            'certification' => $f('certification'),
            'registration_link' => $f('registration_link', '#'),
            'market_problem' => $f('market_problem'),
            'solution' => $f('solution'),
            'who_for' => $f('who_for'),
            'what_different' => $f('what_different'),
            'trainer_profile' => $f('trainer_profile'),
            'fees_intakes' => $f('fees_intakes'),
            'governing_body_name' => $f('governing_body_name'),
            'governing_body_full_name' => $f('governing_body_full_name'),
            'governing_body_logo_url' => $f('governing_body_logo_url'),
            'issuing_institution' => $f('issuing_institution'),
            'accreditation_basis' => $f('accreditation_basis'),
            'professional_alignment' => $f('professional_alignment'),
            'sort_order' => isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0,
            'status' => $f('status', 'active'),
        ];
    }

    /**
     * @return array
     */
    function academic_collect_curriculum_from_post()
    {
        if (empty($_POST['curriculum']) || !is_array($_POST['curriculum'])) {
            return [];
        }
        $modules = $_POST['curriculum'];
        $tiers = isset($_POST['curriculum_tier']) && is_array($_POST['curriculum_tier']) ? $_POST['curriculum_tier'] : array();
        $unitIds = isset($_POST['curriculum_unit_id']) && is_array($_POST['curriculum_unit_id']) ? $_POST['curriculum_unit_id'] : array();
        $rows = array();

        foreach ($modules as $idx => $moduleName) {
            $name = trim((string) $moduleName);
            if ($name === '') {
                continue;
            }
            $tier = isset($tiers[$idx]) ? trim((string) $tiers[$idx]) : 'foundational';
            if (!in_array($tier, array('foundational', 'intermediate', 'advanced'), true)) {
                $tier = 'foundational';
            }
            $rows[] = array(
                'module_name' => $name,
                'unit_id' => isset($unitIds[$idx]) ? trim((string) $unitIds[$idx]) : '',
                'curriculum_tier' => $tier
            );
        }

        return $rows;
    }

    /**
     * @return array
     */
    function academic_collect_outcomes_from_post()
    {
        if (empty($_POST['outcomes']) || !is_array($_POST['outcomes'])) {
            return [];
        }
        return array_values(array_filter(array_map('trim', $_POST['outcomes'])));
    }

    /**
     * @return array
     */
    function academic_collect_lecturers_from_post()
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
