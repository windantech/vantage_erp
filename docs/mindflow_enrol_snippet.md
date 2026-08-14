# Mindflow LMS — auto-enrol a learner into their selected unit courses

**Goal:** the moment a learner finishes academic registration/payment in the Mindflow plugin
(`local/aicoursebuilder`), enrol them into the Moodle courses for the units they selected, so
those units appear under **My Courses** on their dashboard.

We validated that the dashboard already reads Moodle enrolments — enrolling a learner into a
course makes it show. This snippet just performs that enrolment at registration time.

## Where it goes
Inside the plugin, at the point where:
- the learner's Moodle **user id** is known (their account was just created), and
- the **selected units** are known (you already store them / their fees).

Each unit maps to a Moodle course via its `course_id` (the number in `/course/view.php?id=NN`).
So you enrol the user into each selected unit's `course_id`.

## The code (uses Moodle's enrol API — handles context, role, events and caches for you)

```php
require_once($CFG->dirroot . '/lib/enrollib.php');

/**
 * Enrol a user into a course as a student. Idempotent, safe to call repeatedly.
 * @param int $userid   the learner's mdl_user.id
 * @param int $courseid the unit's course_id (mdl_course.id)
 */
function vasl_enrol_student($userid, $courseid) {
    global $DB;
    $userid = (int)$userid; $courseid = (int)$courseid;
    if (!$userid || !$courseid || !$DB->record_exists('course', ['id' => $courseid])) {
        return false;
    }
    $manual = enrol_get_plugin('manual');
    // find (or create) the course's manual enrolment instance
    $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
    if (!$instance) {
        $course = get_course($courseid);
        $instanceid = $manual->add_default_instance($course);
        if (!$instanceid) { $instanceid = $manual->add_instance($course); }
        $instance = $DB->get_record('enrol', ['id' => $instanceid]);
    }
    if (!$instance) { return false; }
    $studentrole = $DB->get_record('role', ['shortname' => 'student']);
    $roleid = $studentrole ? (int)$studentrole->id : 5; // 5 = default student
    $manual->enrol_user($instance, $userid, $roleid);    // idempotent
    return true;
}

// ---- call it once per selected unit, after registration/payment succeeds ----
// $learner_userid    = the just-created learner's Moodle user id
// $selected_course_ids = array of course_id for each unit they paid for
foreach ($selected_course_ids as $cid) {
    vasl_enrol_student($learner_userid, (int)$cid);
}
```

## Notes
- **Idempotent:** `enrol_user()` is safe to re-run (it updates rather than duplicates), so it's fine
  to call again on repeat payments/installments.
- **Only paid units:** pass only the `course_id`s of the units the learner actually paid for.
- The equivalent raw-SQL logic (for reference / if you can't use the API) is in
  `admin/includes/moodle_enrol_functions.php` → `moodle_enrol_user_in_courses()`.
- To verify without the plugin, the admin tool `admin/moodle_enrol_test.php?email=…&courses=…`
  enrols the same way and prints the result.
