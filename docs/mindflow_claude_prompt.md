# Prompt for the Mindflow LMS project's Claude — auto-enrol via direct DB (no Moodle API)

Copy everything below the line.

---

You are working on the Mindflow LMS (`local/aicoursebuilder`, on the `vantage_system` Moodle DB).

## Objective
When an academic learner's account is created (the same place you inject their **email/password**
into `mdl_user`), also **enrol them into the Moodle courses for the units they paid for**, so those
units show under **My Courses** on their dashboard. Do it with **direct DB writes on the same
connection** — NOT the Moodle enrol API, NOT a plugin call. Exactly the style you already use to
insert the user row.

## Proven facts (verified on the live LMS)
- The dashboard's My Courses reads standard Moodle enrolments (`mdl_user_enrolments`). We manually
  inserted an enrolment for course id 9 and the unit appeared immediately (FOUNDATION, 9 topics).
- Each unit maps to a Moodle course by **`course_id` = `mdl_course.id`** (the number in
  `/course/view.php?id=NN`), stored per unit in the CRM as `program_curriculum.course_id`.

## Drop-in function (raw SQL via the same mysqli connection you use for the user row)

```php
/**
 * Enrol a Moodle user into courses as a student — DIRECT DB writes, no Moodle API.
 * $conn = the SAME mysqli connection you use to insert the mdl_user (email/password) row.
 * Idempotent (safe on repeat/installment calls); skips anything already there.
 */
function vasl_enrol_user_in_courses($conn, $userid, array $courseIds) {
    $userid = (int)$userid;
    if (!$conn || $userid <= 0) return;
    $roleId = 5; // student
    if (($r = @mysqli_query($conn, "SELECT id FROM mdl_role WHERE shortname='student' LIMIT 1")) && ($x = mysqli_fetch_assoc($r))) {
        $roleId = (int)$x['id'];
    }
    $now = time();
    foreach (array_unique(array_map('intval', $courseIds)) as $cid) {
        if ($cid <= 0) continue;
        // course must exist
        if (!(($cq = @mysqli_query($conn, "SELECT id FROM mdl_course WHERE id=$cid LIMIT 1")) && mysqli_fetch_assoc($cq))) continue;
        // manual enrol instance for the course
        $enrolid = 0;
        if (($eq = @mysqli_query($conn, "SELECT id FROM mdl_enrol WHERE courseid=$cid AND enrol='manual' ORDER BY status ASC, id ASC LIMIT 1")) && ($e = mysqli_fetch_assoc($eq))) {
            $enrolid = (int)$e['id'];
        }
        if (!$enrolid) continue; // course has no manual enrolment method enabled
        // course context (contextlevel 50 = course)
        $ctx = 0;
        if (($xq = @mysqli_query($conn, "SELECT id FROM mdl_context WHERE contextlevel=50 AND instanceid=$cid LIMIT 1")) && ($xr = mysqli_fetch_assoc($xq))) {
            $ctx = (int)$xr['id'];
        }
        // user_enrolments (idempotent)
        $has = ($ue = @mysqli_query($conn, "SELECT id FROM mdl_user_enrolments WHERE enrolid=$enrolid AND userid=$userid LIMIT 1")) && mysqli_fetch_assoc($ue);
        if (!$has) {
            @mysqli_query($conn, "INSERT INTO mdl_user_enrolments (status,enrolid,userid,timestart,timeend,modifierid,timecreated,timemodified) VALUES (0,$enrolid,$userid,$now,0,2,$now,$now)");
        }
        // role assignment (idempotent)
        if ($ctx) {
            $hasr = ($ra = @mysqli_query($conn, "SELECT id FROM mdl_role_assignments WHERE roleid=$roleId AND contextid=$ctx AND userid=$userid AND component='' LIMIT 1")) && mysqli_fetch_assoc($ra);
            if (!$hasr) {
                @mysqli_query($conn, "INSERT INTO mdl_role_assignments (roleid,contextid,userid,timemodified,modifierid,component,itemid,sortorder) VALUES ($roleId,$ctx,$userid,$now,2,'',0,0)");
            }
        }
    }
}
```

## Where to call it
Right after you create the learner's account / send credentials (same spot, same `$conn`):

```php
// $userid              = the learner's mdl_user.id you just created
// $selected_course_ids = the course_id of each unit they PAID for
vasl_enrol_user_in_courses($conn, $userid, $selected_course_ids);
```

## Sourcing `$selected_course_ids`
Each selected unit has a `course_id` (the number typed into the CMS curriculum "Course ID" field,
stored in `program_curriculum.course_id` in the CRM). Use whatever your selection already carries:
if it stores the `course_id` per chosen unit, pass those; otherwise carry the `course_id` through
from the frontend selection payload. Pass only the units the learner actually paid for.

## Requirements
- Direct DB only (no Moodle API); idempotent; never fatal — if one course fails, continue.
- Test: register a fresh academic learner → the units appear under My Courses and open normally.

## Report back
Which file/function you placed the call in, and how you sourced the `course_id`s.
The identical reference implementation on the CRM side is
`admin/includes/moodle_enrol_functions.php → moodle_enrol_user_in_courses()`.
