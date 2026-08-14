# Prompt for the Mindflow LMS (local_aicoursebuilder) project's Claude

Copy everything below the line into that project's Claude.

---

You are working on the **Mindflow LMS**, a Moodle local plugin at `local/aicoursebuilder`
(served at `system.vantageafricaleaders.com/local/aicoursebuilder/`). This Moodle runs on the
`vantage_system` database.

## Objective
When an academic learner finishes registration/payment in this plugin, **automatically enrol them
into the Moodle courses for the units they selected**, so those units appear under **My Courses**
on their dashboard. Today the plugin creates the account and records the selected units + fees, but
it does **not** enrol the learner into the unit courses — so their dashboard shows "0 enrolled units."

## Proven facts (already verified against this live LMS)
- The dashboard's **My Courses / Enrolled Units reads standard Moodle course enrolments**
  (`mdl_user_enrolments`). We manually enrolled a test learner into course id 9 and the unit
  "PL1.01 Understanding Organisational Environment (FOUNDATION, 9 topics)" immediately appeared.
- Each academic unit maps to a Moodle course via a **`course_id`** = `mdl_course.id`, i.e. the
  number in `/course/view.php?id=NN`. In the CRM these are stored per unit in
  `program_curriculum.course_id` (typed into the "Course ID" field of the academic curriculum CMS).
- So the fix is purely: **enrol the learner into each selected unit's `course_id` as a student.**

## The task
1. Add this helper (uses Moodle's enrol API — handles context, role, events, caches; idempotent):

```php
require_once($CFG->dirroot . '/lib/enrollib.php');

/** Enrol a user into a Moodle course as a student. Safe to call repeatedly. */
function vasl_enrol_student($userid, $courseid) {
    global $DB;
    $userid = (int)$userid; $courseid = (int)$courseid;
    if (!$userid || !$courseid || !$DB->record_exists('course', ['id' => $courseid])) return false;
    $manual = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
    if (!$instance) {
        $course = get_course($courseid);
        $iid = $manual->add_default_instance($course) ?: $manual->add_instance($course);
        $instance = $DB->get_record('enrol', ['id' => $iid]);
    }
    if (!$instance) return false;
    $srole = $DB->get_record('role', ['shortname' => 'student']);
    $manual->enrol_user($instance, $userid, $srole ? (int)$srole->id : 5);
    return true;
}
```

2. **Find the point in this plugin where a learner's academic registration/payment succeeds** —
   the code that creates their user account and/or records their selected units + fees (look for
   user creation, the selection/fees insert, or the payment-success/callback path).

3. At that point, once the learner's Moodle **user id** and their **selected units' `course_id`s**
   are known, enrol them:

```php
// $learner_userid    = the learner's mdl_user.id (the account just created / $USER->id)
// $selected_course_ids = array of course_id for each unit the learner PAID for
foreach ($selected_course_ids as $cid) {
    vasl_enrol_student($learner_userid, (int)$cid);
}
```

## Sourcing `$selected_course_ids`
The plugin already stores which units the learner chose. Each unit's `course_id` must be available
here. In order of preference:
- If the stored selection already carries each unit's `course_id`, use it directly.
- If the selection payload from the frontend includes the `course_id`s, thread them through.
- Otherwise the value lives in `program_curriculum.course_id` in the **CRM** database
  (`vantage_crm`). If this plugin can reach that DB, look it up for the selected units; if not,
  make the frontend include the `course_id` for each selected unit in what it sends the plugin.

## Requirements
- **Only paid units** — enrol only the units the learner actually paid for.
- **Idempotent** — `enrol_user()` is safe on repeat payments/installments; don't guard-duplicate.
- **Non-fatal** — if a single course fails, log and continue; never break registration or payment.
- **Test:** register a fresh academic learner end-to-end → confirm the selected units show under
  My Courses, and that they can open them.

## Report back
Tell the CRM side (a) which file/function you added the enrol call to, (b) how you sourced the
`course_id`s, and (c) the result of a fresh test registration. The equivalent raw-SQL reference
implementation lives in the CRM repo at `admin/includes/moodle_enrol_functions.php`.
