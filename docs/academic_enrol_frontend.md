# Academic auto-enrolment — the one change needed on the frontend

## Context
Academic LMS access works: the frontend's `process_academic_registration.php` (~line 288) calls the
ERP function `create_moodle_user_and_send_email()`. Because the `portalOverride.login_url` contains
`system.vantageafricaleaders.com`, that function creates the account in **`vantage_system`** (Mindflow)
and emails the credentials. ✅

The **unit enrolment is already implemented inside that same ERP function** — it enrols the learner
into the courses for their selected units, on the `vantage_system` connection, idempotent, right after
the credentials email. It runs **only when the call passes the selected `course_id`s**. Today the call
doesn't pass them, so no enrolment happens.

## The change (frontend only)
At `process_academic_registration.php:288`, add a 10th argument — a `$selection` array carrying the
`course_id` of each unit the learner PAID for (you already have these; they're the
`program_curriculum.course_id` values you read to build the course-content email):

```php
$selection = [
    'course_ids'   => $selected_course_ids,   // REQUIRED: [9, 44, 45, ...] course ids of the paid units
    'program'      => $program,               // optional (id or title)
    'level'        => $level,                 // optional
    'units'        => $selected_unit_names,   // optional
    'total_amount' => $total,                 // optional
    'currency'     => 'KES',                  // optional
    'source_ref'   => $entry_id,              // optional
];

create_moodle_user_and_send_email(
    $moodle_conn, $email, $firstname, $lastname, $phone, $country, $organization,
    $send_email, $portalOverride, $selection   // <-- add $selection
);
```

That's the entire change. Nothing else on the frontend, nothing in the Moodle plugin.

## Result (handled by the ERP function, already live)
Account in `vantage_system` → credentials email (unchanged) → learner **enrolled into each unit's
course** → units show under "My Courses" on the Mindflow dashboard. Idempotent (safe on repeat /
installment calls) and fail-safe (a hiccup never blocks the account or email).

## Notes
- Pass only the units the learner actually paid for.
- `course_id` = `mdl_course.id` (the number in `/course/view.php?id=NN`) = the value typed into the
  CMS curriculum "Course ID" field = `program_curriculum.course_id`.
- The ERP-side enrolment reference is `admin/includes/moodle_enrol_functions.php`.
