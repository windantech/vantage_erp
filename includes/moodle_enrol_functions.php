<?php
/**
 * moodle_enrol_functions.php — enrol a learner into Moodle courses (vantage_system) by course id.
 *
 * Uses Moodle's standard "manual" enrolment via direct DB writes (we already own the
 * vantage_system connection). Idempotent and defensive: never throws, skips anything already
 * present, and reports per-course what happened. Course ids are mdl_course.id — the number in
 * /course/view.php?id=NN, which is what the academic curriculum stores as `course_id`.
 */

if (!function_exists('moodle_student_role_id')) {
    function moodle_student_role_id($sys)
    {
        $r = @mysqli_query($sys, "SELECT id FROM mdl_role WHERE shortname='student' LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r))) { return (int) $row['id']; }
        return 5; // Moodle default 'student'
    }
}

if (!function_exists('moodle_enrol_preview')) {
    /**
     * Read-only: report, per course, whether we CAN enrol (course exists, manual enrol instance,
     * course context) and whether the user is already enrolled. Writes nothing.
     */
    function moodle_enrol_preview($sys, $moodleUserId, array $courseIds)
    {
        $uid = (int) $moodleUserId;
        $roleId = moodle_student_role_id($sys);
        $rows = [];
        foreach (array_unique(array_map('intval', $courseIds)) as $cid) {
            if ($cid <= 0) { continue; }
            $r = ['course_id' => $cid, 'course' => '(not found)', 'enrol_instance' => false, 'context' => false, 'already_enrolled' => false, 'ready' => false];
            $cq = @mysqli_query($sys, "SELECT fullname FROM mdl_course WHERE id=$cid LIMIT 1");
            if (!$cq || !($c = mysqli_fetch_assoc($cq))) { $rows[] = $r; continue; }
            $r['course'] = (string) $c['fullname'];
            $eq = @mysqli_query($sys, "SELECT id FROM mdl_enrol WHERE courseid=$cid AND enrol='manual' ORDER BY status ASC, id ASC LIMIT 1");
            $enrolid = ($eq && ($e = mysqli_fetch_assoc($eq))) ? (int) $e['id'] : 0;
            $r['enrol_instance'] = $enrolid > 0;
            $xq = @mysqli_query($sys, "SELECT id FROM mdl_context WHERE contextlevel=50 AND instanceid=$cid LIMIT 1");
            $r['context'] = ($xq && mysqli_num_rows($xq) > 0);
            if ($enrolid > 0 && $uid > 0) {
                $ue = @mysqli_query($sys, "SELECT id FROM mdl_user_enrolments WHERE enrolid=$enrolid AND userid=$uid LIMIT 1");
                $r['already_enrolled'] = ($ue && mysqli_num_rows($ue) > 0);
            }
            $r['ready'] = $r['enrol_instance'] && $r['context'];
            $rows[] = $r;
        }
        return ['user' => $uid, 'student_role_id' => $roleId, 'courses' => $rows];
    }
}

if (!function_exists('moodle_enrol_user_in_courses')) {
    /**
     * Enrol a Moodle user (vantage_system) into a set of course ids as a student.
     * Idempotent + defensive. Returns per-course result. Never throws.
     */
    function moodle_enrol_user_in_courses($sys, $moodleUserId, array $courseIds, $modifierId = 2)
    {
        $uid = (int) $moodleUserId;
        $out = ['user' => $uid, 'results' => []];
        if (!$sys || $uid <= 0) { $out['error'] = 'bad connection or user'; return $out; }
        $roleId = moodle_student_role_id($sys);
        $now = time();
        foreach (array_unique(array_map('intval', $courseIds)) as $cid) {
            if ($cid <= 0) { continue; }
            $res = ['course_id' => $cid, 'course' => '', 'status' => ''];
            try {
                $cq = @mysqli_query($sys, "SELECT fullname FROM mdl_course WHERE id=$cid LIMIT 1");
                if (!$cq || !($crow = mysqli_fetch_assoc($cq))) { $res['status'] = 'course_not_found'; $out['results'][] = $res; continue; }
                $res['course'] = (string) $crow['fullname'];

                $eq = @mysqli_query($sys, "SELECT id FROM mdl_enrol WHERE courseid=$cid AND enrol='manual' ORDER BY status ASC, id ASC LIMIT 1");
                if (!$eq || !($erow = mysqli_fetch_assoc($eq))) { $res['status'] = 'no_manual_enrol_instance'; $out['results'][] = $res; continue; }
                $enrolid = (int) $erow['id'];

                $xq = @mysqli_query($sys, "SELECT id FROM mdl_context WHERE contextlevel=50 AND instanceid=$cid LIMIT 1");
                if (!$xq || !($xrow = mysqli_fetch_assoc($xq))) { $res['status'] = 'no_course_context'; $out['results'][] = $res; continue; }
                $contextid = (int) $xrow['id'];

                $ue = @mysqli_query($sys, "SELECT id FROM mdl_user_enrolments WHERE enrolid=$enrolid AND userid=$uid LIMIT 1");
                $hadEnrol = ($ue && mysqli_num_rows($ue) > 0);
                if (!$hadEnrol) {
                    @mysqli_query($sys, "INSERT INTO mdl_user_enrolments (status, enrolid, userid, timestart, timeend, modifierid, timecreated, timemodified) VALUES (0, $enrolid, $uid, $now, 0, $modifierId, $now, $now)");
                }

                $ra = @mysqli_query($sys, "SELECT id FROM mdl_role_assignments WHERE roleid=$roleId AND contextid=$contextid AND userid=$uid AND component='' LIMIT 1");
                $hadRole = ($ra && mysqli_num_rows($ra) > 0);
                if (!$hadRole) {
                    @mysqli_query($sys, "INSERT INTO mdl_role_assignments (roleid, contextid, userid, timemodified, modifierid, component, itemid, sortorder) VALUES ($roleId, $contextid, $uid, $now, $modifierId, '', 0, 0)");
                }

                $res['status'] = ($hadEnrol && $hadRole) ? 'already_enrolled' : 'enrolled';
            } catch (\Throwable $e) {
                $res['status'] = 'error: ' . $e->getMessage();
            }
            $out['results'][] = $res;
        }
        return $out;
    }
}

if (!function_exists('moodle_autoenrol_from_selection')) {
    /**
     * Fail-safe: enrol a just-created/returning learner into their selected units' Moodle courses.
     * Resolves course_ids from the selection (explicit, or matched against program_curriculum via the
     * global CRM $conn), then enrols on the given vantage_system connection. Never throws.
     */
    function moodle_autoenrol_from_selection($sys, $moodleUserId, $email, $selection)
    {
        if (!$sys || !is_array($selection) || empty($selection)) { return; }
        try {
            $crm = (isset($GLOBALS['conn']) && ($GLOBALS['conn'] instanceof mysqli)) ? $GLOBALS['conn'] : null;
            $courseIds = function_exists('academic_selected_course_ids') ? academic_selected_course_ids($crm, $selection) : [];
            if (empty($courseIds)) { error_log('[moodle] auto-enrol: no course_ids resolved for ' . $email . ' | selection=' . json_encode($selection)); return; }
            $enr = moodle_enrol_user_in_courses($sys, (int) $moodleUserId, $courseIds);
            error_log('[moodle] auto-enrol user ' . (int) $moodleUserId . ' (' . $email . '): ' . json_encode($enr['results']));
        } catch (\Throwable $e) {
            error_log('[moodle] auto-enrol failed for ' . $email . ': ' . $e->getMessage());
        }
    }
}

if (!function_exists('academic_selected_course_ids')) {
    /**
     * Resolve Moodle course ids for a learner's SELECTED units.
     * $crm = vantage_crm connection. $selection carries program + selected unit names.
     * Matches selected unit names to program_curriculum.module_name for that program and returns
     * the non-empty course_id values. Returns [] if it can't resolve (caller decides what to do).
     */
    function academic_selected_course_ids($crm, array $selection)
    {
        $norm = function ($s) { return strtolower(trim(preg_replace('/\s+/', ' ', (string) $s))); };

        // (1) Explicit course ids anywhere in the payload (frontend already knows them).
        if (!empty($selection['course_ids']) && is_array($selection['course_ids'])) {
            return array_values(array_filter(array_map('intval', $selection['course_ids'])));
        }

        // (2) Units may arrive as plain names, or as objects carrying course_id / name.
        $units = (isset($selection['units']) && is_array($selection['units'])) ? $selection['units'] : [];
        $direct = [];
        $names = [];
        foreach ($units as $u) {
            if (is_array($u)) {
                if (!empty($u['course_id'])) { $direct[] = (int) $u['course_id']; continue; }
                $nm = $u['module_name'] ?? $u['name'] ?? $u['title'] ?? $u['unit'] ?? '';
                if ($nm !== '') { $names[] = (string) $nm; }
            } elseif (trim((string) $u) !== '') {
                $names[] = (string) $u;
            }
        }
        if (!empty($direct)) { return array_values(array_unique(array_filter($direct))); }
        if (empty($names) || !$crm) { return []; }

        // (3) Match unit names to program_curriculum.module_name for the learner's program.
        $program = $selection['program'] ?? '';
        $pid = 0;
        if (is_numeric($program)) {
            $pid = (int) $program; // frontend program select uses the program id
        } else {
            $pe = mysqli_real_escape_string($crm, trim((string) $program));
            $pq = @mysqli_query($crm, "SELECT id FROM academic_programs WHERE title = '$pe' LIMIT 1");
            if ($pq && ($pr = mysqli_fetch_assoc($pq))) { $pid = (int) $pr['id']; }
        }
        // Build the name→course_id map (scoped to the program if we know it, else all curricula).
        $where = $pid > 0 ? "WHERE program_id = $pid" : '';
        $map = [];
        $cq = @mysqli_query($crm, "SELECT module_name, course_id FROM program_curriculum $where");
        while ($cq && ($c = mysqli_fetch_assoc($cq))) {
            $key = $norm($c['module_name']);
            if ($key !== '' && trim((string) $c['course_id']) !== '') { $map[$key] = (int) $c['course_id']; }
        }
        $ids = [];
        foreach ($names as $n) {
            $key = $norm($n);
            if (isset($map[$key])) { $ids[] = $map[$key]; }
        }
        return array_values(array_unique(array_filter($ids)));
    }
}
