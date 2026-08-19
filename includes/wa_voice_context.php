<?php
/**
 * Voice API — database layer (Phase 2.1A).
 *
 * READ-ONLY over customer data, and NO DDL AT ALL. Nothing on any request path
 * issues CREATE TABLE, ALTER TABLE, DROP or TRUNCATE, and nothing calls a
 * schema-ensure helper. The two security tables are created once by an
 * administrator (see the deployment SQL in the Phase 2.1A notes); at runtime the
 * endpoint only checks that they exist and refuses to serve if they do not.
 *
 * That is a stronger guarantee than catching a failed ALTER. It means the
 * production database user can be granted no CREATE, ALTER, DROP or INDEX
 * privilege at all, so "this endpoint cannot change the schema" is enforced by
 * MySQL rather than promised by PHP.
 *
 * Every statement written for this phase is PREPARED. The values arriving here
 * come from outside the building, and a prepared statement removes the question
 * rather than relying on each call site remembering to escape.
 *
 * WHAT IS REUSED, AND WHAT IS NOT.
 *
 * Read-only helpers from wa_functions.php are reused wherever they cannot reach
 * DDL — that is the point of the design, so the voice assistant answers from the
 * same knowledge as the WhatsApp AI and the two cannot drift. Five are NOT reused,
 * because reading through them writes or alters:
 *
 *   wa_enroll_active()        cancels stale sessions as a side effect of reading
 *   wa_ai_history()           calls wa_message_flags_ensure() -> ALTER TABLE
 *   wa_program_match()        calls wa_programs_list()        -> wa_kb_ensure_schema()
 *   wa_ref_name()             for 'program' calls wa_program_get() -> same
 *   wa_event_effective_kb()   falls through to wa_programs_list()  -> same
 *
 * Each is replaced below by a prepared read plus, where needed, a pure function in
 * wa_voice_api_lib.php. wa_voice_event_knowledge() reproduces the event knowledge
 * assembly — including the corrective scheduling wording, verbatim, which
 * wa_voice_api_test.php compares against the original character for character so
 * the two cannot silently diverge.
 *
 * Requires: wa_functions.php, wa_voice.php, wa_voice_api_lib.php.
 */

// =====================================================================
// Prepared-statement helpers
// =====================================================================

/**
 * Prepare, bind, execute. Returns the statement or null.
 *
 * Failures are logged without the SQL parameters — those are customer phone
 * numbers and search text.
 */
function wa_voice_stmt($conn, $sql, $types = '', array $params = []) {
    $stmt = @mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('[wa-voice] prepare failed: ' . @mysqli_error($conn));
        return null;
    }
    if ($types !== '') {
        if (!@mysqli_stmt_bind_param($stmt, $types, ...$params)) {
            @mysqli_stmt_close($stmt);
            error_log('[wa-voice] bind failed');
            return null;
        }
    }
    if (!@mysqli_stmt_execute($stmt)) {
        error_log('[wa-voice] execute failed: ' . @mysqli_stmt_error($stmt));
        @mysqli_stmt_close($stmt);
        return null;
    }
    return $stmt;
}

/** Execute a write. Returns affected rows, or -1 when the statement failed. */
function wa_voice_exec($conn, $sql, $types = '', array $params = []) {
    $stmt = wa_voice_stmt($conn, $sql, $types, $params);
    if (!$stmt) { return -1; }
    $n = @mysqli_stmt_affected_rows($stmt);
    @mysqli_stmt_close($stmt);
    return (int)$n;
}

/**
 * Execute a write and report the value MySQL returns as the insert id.
 *
 * Used by the rate counter, which needs the resulting hit count without a SELECT
 * — see wa_voice_rate_allow() for why that matters.
 *
 * @return array [affectedRows, insertId]; affectedRows is -1 when the statement failed
 */
function wa_voice_exec_id($conn, $sql, $types = '', array $params = []) {
    $stmt = wa_voice_stmt($conn, $sql, $types, $params);
    if (!$stmt) { return [-1, 0]; }
    $n  = (int)@mysqli_stmt_affected_rows($stmt);
    $id = (int)@mysqli_stmt_insert_id($stmt);
    @mysqli_stmt_close($stmt);
    return [$n, $id];
}

/** Fetch one associative row, or null. */
function wa_voice_fetch($conn, $sql, $types = '', array $params = []) {
    $stmt = wa_voice_stmt($conn, $sql, $types, $params);
    if (!$stmt) { return null; }
    $res = @mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    @mysqli_stmt_close($stmt);
    return $row ?: null;
}

/**
 * Fetch all associative rows.
 *
 * $failed is set by reference because an empty array is ambiguous — "no rows" and
 * "the statement did not run" look identical otherwise, and one caller below has
 * to tell them apart to decide whether to retry against an older schema.
 */
function wa_voice_fetch_all($conn, $sql, $types = '', array $params = [], &$failed = false) {
    $failed = false;
    $stmt = wa_voice_stmt($conn, $sql, $types, $params);
    if (!$stmt) { $failed = true; return []; }
    $res = @mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    @mysqli_stmt_close($stmt);
    return $rows;
}

// =====================================================================
// Schema availability — a check, never a creation
// =====================================================================

/**
 * Are both security tables present and usable by this database user?
 *
 * Asked of information_schema rather than of the tables themselves. A user sees a
 * row there for any table it holds SOME privilege on, so this works with the
 * intended grant — INSERT/DELETE on wa_voice_nonces, INSERT/UPDATE/DELETE on
 * wa_voice_rate, and no SELECT on either — where `SELECT 1 FROM wa_voice_nonces`
 * would fail on privilege and be indistinguishable from a missing table.
 *
 * Cached for the request. A false answer makes the endpoint answer 503
 * schema_unavailable; it never triggers a CREATE.
 */
function wa_voice_schema_available($conn) {
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $rows = wa_voice_fetch_all($conn,
        "SELECT `TABLE_NAME` FROM `information_schema`.`TABLES`
          WHERE `TABLE_SCHEMA` = DATABASE()
            AND `TABLE_NAME` IN ('wa_voice_nonces', 'wa_voice_rate')");

    $seen = [];
    foreach ($rows as $r) { $seen[strtolower((string)($r['TABLE_NAME'] ?? ''))] = true; }

    $cache = isset($seen['wa_voice_nonces']) && isset($seen['wa_voice_rate']);
    if (!$cache) {
        error_log('[wa-voice] security tables missing — run the Phase 2.1A deployment SQL. '
                . 'present: ' . implode(',', array_keys($seen)));
    }
    return $cache;
}

// =====================================================================
// Replay protection and rate limiting — the only writes in this phase
// =====================================================================

/**
 * Claim a nonce. The INSERT failing on the primary key IS the replay check: there
 * is no read-then-write, so two identical requests arriving together cannot both
 * find the nonce unused. Needs INSERT only — no SELECT privilege on the table.
 *
 * @return bool true when the nonce is fresh, false when it is a replay or the
 *              statement failed (fail closed — an unverifiable nonce is refused)
 */
function wa_voice_nonce_claim($conn, $keyId, $nonce, $now) {
    $hash = hash('sha256', (string)$keyId . '|' . (string)$nonce);
    $n = wa_voice_exec($conn,
        "INSERT IGNORE INTO `wa_voice_nonces` (`nonce_hash`, `seen_at`) VALUES (?, ?)",
        'si', [$hash, (int)$now]);
    if ($n < 0) { return false; }
    return wa_voice_nonce_is_fresh($n);
}

/**
 * Count this request against a fixed window and say whether it is allowed.
 *
 * ONE statement, and no SELECT. `LAST_INSERT_ID(hits + 1)` makes MySQL hand the
 * new counter value back through the insert-id channel, so the restricted user
 * needs INSERT and UPDATE on this table and nothing more. It is also atomic,
 * which the previous upsert-then-read was not.
 *
 * On the first hit in a window the row is inserted rather than updated, the
 * LAST_INSERT_ID() branch never runs, and the table has no AUTO_INCREMENT column
 * — so the insert id is 0. wa_voice_rate_hits_from_insert_id() maps that to 1.
 *
 * @return bool false when over the limit, or when the statement failed
 */
function wa_voice_rate_allow($conn, $scope, $bucket, $max, $now, $window = null) {
    $window = $window === null ? WA_VOICE_RATE_WINDOW : (int)$window;
    $start  = (int)(floor((int)$now / $window) * $window);

    list($n, $id) = wa_voice_exec_id($conn,
        "INSERT INTO `wa_voice_rate` (`scope`, `bucket`, `window_start`, `hits`)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE `hits` = LAST_INSERT_ID(`hits` + 1)",
        'ssi', [(string)$scope, (string)$bucket, $start]);
    if ($n < 0) { return false; }

    return wa_voice_rate_allowed(wa_voice_rate_hits_from_insert_id($id), (int)$max);
}

/**
 * Delete expired security rows, opportunistically, inside an already-authenticated
 * request. Roughly one request in twenty does the work, so the cost is spread and
 * a quiet period does not leave the tables to grow. Bounded by LIMIT so it can
 * never turn one API call into a long delete.
 *
 * Deliberately not a cron job: wa_cron.php is out of scope for this phase, and
 * tables owned by this endpoint should be maintained by this endpoint.
 */
function wa_voice_gc($conn, $now, $force = false) {
    if (!$force) {
        try { if (random_int(1, 20) !== 1) { return; } }
        catch (Throwable $e) { return; }
    }
    // A nonce only has to outlive the skew window on both sides.
    $nonceCutoff = (int)$now - (2 * WA_VOICE_SKEW_SECS) - 60;
    wa_voice_exec($conn, "DELETE FROM `wa_voice_nonces` WHERE `seen_at` < ? LIMIT 500",
                  'i', [$nonceCutoff]);
    $rateCutoff = (int)$now - (10 * WA_VOICE_RATE_WINDOW);
    wa_voice_exec($conn, "DELETE FROM `wa_voice_rate` WHERE `window_start` < ? LIMIT 500",
                  'i', [$rateCutoff]);
}

// =====================================================================
// Names — prepared, and never through wa_ref_name()
// =====================================================================

/**
 * The display name of a course, event or programme.
 *
 * wa_ref_name() would do this, but its 'program' branch calls wa_program_get(),
 * which calls wa_kb_ensure_schema() and issues DDL. Rather than rely on never
 * passing it 'program', the whole helper is replaced so no reachable path can
 * arrive there at all.
 */
function wa_voice_ref_name($conn, $type, $id) {
    $id = (int)$id;
    if ($id < 1) { return ''; }
    if ($type === 'event') {
        $r = wa_voice_fetch($conn, "SELECT `event_title` FROM `Event` WHERE `event_id` = ? LIMIT 1",
                            'i', [$id]);
        return $r ? (string)$r['event_title'] : '';
    }
    if ($type === 'program') {
        $r = wa_voice_fetch($conn, "SELECT `name` FROM `wa_programs` WHERE `id` = ? LIMIT 1",
                            'i', [$id]);
        return $r ? (string)$r['name'] : '';
    }
    $r = wa_voice_fetch($conn, "SELECT `course` FROM `course` WHERE `course_id` = ? LIMIT 1",
                        'i', [$id]);
    return $r ? (string)$r['course'] : '';
}

// =====================================================================
// Caller context
// =====================================================================

/**
 * Find a contact by digits-only E.164. Prepared, exact match, no normalisation
 * here — the caller must have run wa_voice_e164() already, and re-normalising at
 * this depth would hide a bug at the boundary rather than surface it.
 *
 * @return array|null
 */
function wa_voice_contact_by_e164($conn, $e164) {
    if (!preg_match('/^[0-9]{9,15}$/', (string)$e164)) { return null; }
    return wa_voice_fetch($conn,
        "SELECT `id`, `wa_id`, `profile_name`, `country`, `opted_out`, `last_inbound_at`
           FROM `wa_contacts` WHERE `wa_id` = ? LIMIT 1",
        's', [(string)$e164]);
}

/** The single conversation row for a contact (there is only ever one — the table
 *  carries a UNIQUE key on contact_id). */
function wa_voice_conversation($conn, $contactId) {
    return wa_voice_fetch($conn,
        "SELECT `id`, `ref_type`, `ref_id`, `program_id`, `assigned_user_id`,
                `delivery_mode`, `last_route_reason`, `escalated`, `handler`, `status`
           FROM `wa_conversations` WHERE `contact_id` = ? LIMIT 1",
        'i', [(int)$contactId]);
}

/**
 * Recent messages for the turn list.
 *
 * Notes and deleted rows are excluded in SQL as well as in wa_voice_shape_turns(),
 * so a note can never occupy one of the six slots and push a real turn out of the
 * window. The limit is asked for generously and trimmed after shaping, because
 * empty-bodied rows are dropped during shaping.
 *
 * `deleted_at` is added to wa_messages by wa_message_flags_ensure(). We must not
 * call that — it is DDL — so the query falls back to one without the column if it
 * is genuinely absent. On any deployed system it is not.
 */
function wa_voice_recent_messages($conn, $contactId, $limit = 20) {
    $limit = max(1, min(50, (int)$limit));
    $failed = false;
    // Wrapped, because mysqli throws by default on PHP 8.1+: an unknown column
    // raises an exception rather than returning false, so without this the
    // fallback below could never run and the whole request would 503 instead.
    try {
        $rows = wa_voice_fetch_all($conn,
            "SELECT `direction`, `type`, `body`, `deleted_at`
               FROM `wa_messages`
              WHERE `contact_id` = ? AND `type` <> 'note' AND `deleted_at` IS NULL
              ORDER BY `id` DESC LIMIT " . $limit,
            'i', [(int)$contactId], $failed);
        if (!$failed) { return $rows; }
    } catch (Throwable $e) {
        error_log('[wa-voice] deleted_at unavailable, retrying without it');
    }

    // Any failure HERE propagates. Returning an empty turn list after a failed
    // read would hand the assistant a caller record that looks complete and is
    // not — the one outcome worse than answering 503.
    return wa_voice_fetch_all($conn,
        "SELECT `direction`, `type`, `body`
           FROM `wa_messages`
          WHERE `contact_id` = ? AND `type` <> 'note'
          ORDER BY `id` DESC LIMIT " . $limit,
        'i', [(int)$contactId]);
}

/**
 * The open enrolment session's STATUS AND TIMESTAMP ONLY.
 *
 * The select list is the redaction. The row also holds `data`, a JSON blob with
 * the customer's name, email address, telephone number, employer and job title —
 * it is simply never read. Not fetching it is stronger than fetching and filtering:
 * there is no later step that could forget.
 */
function wa_voice_enrolment_row($conn, $contactId) {
    return wa_voice_fetch($conn,
        "SELECT `status`, `updated_at` FROM `wa_enroll_sessions`
          WHERE `contact_id` = ? AND `status` IN ('offered','collecting','confirm')
          ORDER BY `id` DESC LIMIT 1",
        'i', [(int)$contactId]);
}

/** The representative's display name. Mirrors wa_user_name(), prepared. */
function wa_voice_rep_name($conn, $userId) {
    $r = wa_voice_fetch($conn,
        "SELECT COALESCE(NULLIF(s.full_name,''), ru.fullname) AS nm
           FROM `registered_users` ru
      LEFT JOIN `staff` s ON s.system_user_id = ru.id
          WHERE ru.id = ? LIMIT 1",
        'i', [(int)$userId]);
    return $r ? (string)$r['nm'] : '';
}

/**
 * Assemble the full get_caller_context response.
 *
 * An unmatched number is a successful result — the assistant carries on without
 * personalisation — so it returns wa_voice_unmatched() rather than an error.
 */
function wa_voice_caller_context($conn, $e164, $now) {
    $contact = wa_voice_contact_by_e164($conn, $e164);
    if (!$contact) { return wa_voice_unmatched(); }

    $contactId = (int)$contact['id'];
    $conv = wa_voice_conversation($conn, $contactId) ?: [];

    $turns = wa_voice_shape_turns(wa_voice_recent_messages($conn, $contactId, 20));

    $refType = (string)($conv['ref_type'] ?? '');
    $refId   = (int)($conv['ref_id'] ?? 0);
    $interestName = '';
    if (in_array($refType, ['course', 'event'], true) && $refId > 0) {
        $interestName = wa_voice_ref_name($conn, $refType, $refId);
    }

    $repName = '';
    if ((int)($conv['assigned_user_id'] ?? 0) > 0) {
        $repName = wa_voice_rep_name($conn, (int)$conv['assigned_user_id']);
    }

    return wa_voice_shape_caller_context([
        'contact'             => $contact,
        'conversation'        => $conv,
        'interest_name'       => $interestName,
        'representative_name' => $repName,
        'enrolment_state'     => wa_voice_enrolment_state(wa_voice_enrolment_row($conn, $contactId), $now),
        'turns'               => $turns,
    ]);
}

// =====================================================================
// Programme search
// =====================================================================

/** Active training programmes, read without the schema-ensure side effect of
 *  wa_programs_list(). Ordered exactly as that function orders them, so the
 *  first-keyword-wins rule below picks the same programme it would. */
function wa_voice_programs($conn) {
    return wa_voice_fetch_all($conn,
        "SELECT `id`, `name`, `keywords` FROM `wa_programs` WHERE `status` = 1 ORDER BY `name` ASC");
}

/** Title, location and dates for one Event, for labelling a search result. */
function wa_voice_event_brief($conn, $eventId) {
    return wa_voice_fetch($conn,
        "SELECT `event_id`, `event_title`, `location`, `start_on`, `end_on`
           FROM `Event` WHERE `event_id` = ? LIMIT 1",
        'i', [(int)$eventId]);
}

/** Is this Event an academic/online course rather than an in-person training? */
function wa_voice_event_is_academic($location) {
    return strpos((string)$location, 'ACADEMIC#') === 0;
}

/** A short human schedule line for an Event row. */
function wa_voice_event_schedule($row) {
    if (!is_array($row)) { return ''; }
    if (wa_voice_event_is_academic($row['location'] ?? '')) {
        return 'Online, intake-based — enrol anytime';
    }
    $when  = wa_event_when_range($row['start_on'] ?? '', $row['end_on'] ?? '');
    $where = trim((string)($row['location'] ?? ''));
    if ($when !== '' && $where !== '') { return $where . ' — ' . $when; }
    return $when !== '' ? $when : $where;
}

/**
 * Search the catalogue, best first, capped at five.
 *
 * Reuses the module's own classifiers so a caller's words are matched exactly as
 * they would be on WhatsApp:
 *
 *   wa_classify_event()     in-person events, by city and title  (real confidence)
 *   wa_classify_academic()  online/academic courses, by title    (real confidence)
 *   wa_classify_course()    virtual courses, by title            (real confidence)
 *   wa_voice_score_programs()  training programmes, by keyword   (no confidence —
 *                              the underlying score has no defined range, so
 *                              publishing one would be inventing a measurement)
 *
 * A caller-supplied delivery_mode only REORDERS results; it never removes one. A
 * person who says "in person" while the only match is an online course should hear
 * about the online course, not silence.
 */
function wa_voice_search_programmes($conn, $query, array $context = []) {
    $items = [];

    // In-person events.
    $ev = wa_classify_event($conn, $query);
    if (!empty($ev['event_id'])) {
        $row = wa_voice_event_brief($conn, (int)$ev['event_id']);
        if ($row) {
            $items[] = ['type' => 'event', 'id' => (int)$ev['event_id'],
                        'name' => (string)$row['event_title'],
                        'delivery_mode' => 'onsite',
                        'schedule' => wa_voice_event_schedule($row),
                        'confidence' => (float)$ev['confidence']];
        }
    }

    // Academic / online courses (Event rows marked ACADEMIC#).
    $ac = wa_classify_academic($conn, $query);
    if (!empty($ac['event_id'])) {
        $row = wa_voice_event_brief($conn, (int)$ac['event_id']);
        if ($row) {
            $items[] = ['type' => 'event', 'id' => (int)$ac['event_id'],
                        'name' => (string)$row['event_title'],
                        'delivery_mode' => 'virtual',
                        'schedule' => wa_voice_event_schedule($row),
                        'confidence' => (float)$ac['confidence']];
        }
    }

    // Virtual courses.
    $co = wa_classify_course($query, wa_active_courses($conn));
    if (!empty($co['course_id'])) {
        $name = wa_voice_ref_name($conn, 'course', (int)$co['course_id']);
        if ($name !== '') {
            $items[] = ['type' => 'course', 'id' => (int)$co['course_id'],
                        'name' => $name, 'delivery_mode' => 'virtual', 'schedule' => '',
                        'confidence' => (float)$co['confidence']];
        }
    }

    // Training programmes, with their next sessions as the schedule line.
    foreach (wa_voice_score_programs(wa_voice_programs($conn), $query) as $hit) {
        $p = $hit['program'];
        $schedule = '';
        $sessions = wa_program_events($conn, $p, 3);
        if ($sessions) {
            $parts = [];
            foreach ($sessions as $s) {
                $parts[] = trim(wa_event_display($s['location'], $s['when']));
            }
            $schedule = implode('; ', array_filter($parts));
        }
        // NO confidence key: the keyword score is additive and unbounded.
        $items[] = ['type' => 'program', 'id' => (int)$p['id'],
                    'name' => (string)$p['name'],
                    'delivery_mode' => 'onsite', 'schedule' => $schedule];
    }

    // De-duplicate on type+id, keeping the first (highest-signal) occurrence.
    $seen = [];
    $unique = [];
    foreach ($items as $it) {
        $k = $it['type'] . ':' . $it['id'];
        if (isset($seen[$k])) { continue; }
        $seen[$k] = true;
        $unique[] = $it;
    }

    // A stated delivery preference floats matching results up, without dropping any.
    $want = (string)($context['delivery_mode'] ?? '');
    if (in_array($want, ['virtual', 'onsite'], true)) {
        $pref = []; $rest = [];
        foreach ($unique as $it) {
            if (($it['delivery_mode'] ?? '') === $want) { $pref[] = $it; } else { $rest[] = $it; }
        }
        $unique = array_merge($pref, $rest);
    }

    return ['ok' => true, 'results' => wa_voice_shape_results($unique)];
}

// =====================================================================
// Event knowledge — a read-only reproduction of wa_event_effective_kb()
// =====================================================================

/**
 * The corrective scheduling wording that must accompany every in-person event.
 *
 * This paragraph is the reason the event knowledge is assembled rather than read
 * straight out of wa_knowledge: it exists to stop a model telling a customer the
 * three-day training is a five-week evening course, which is a thing that
 * happened. It is reproduced here CHARACTER FOR CHARACTER from
 * wa_event_effective_kb() in wa_functions.php, and wa_voice_api_test.php compares
 * the two so they cannot drift apart unnoticed.
 */
if (!defined('WA_VOICE_EVENT_SCHEDULE_RULE')) {
    define('WA_VOICE_EVENT_SCHEDULE_RULE',
        "Duration & schedule (state it EXACTLY like this; do NOT invent weeks, 'days per week', or evening times "
        . "for the in-person part):\n"
        . "  - PHASE 1 — the in-person event is 3 FULL DAYS, classes 8:30 AM to 5:00 PM each day (a hands-on "
        . "training where you build a complete M&E plan).\n"
        . "  - PHASE 2 — after the 3 physical days, the CMEP programme continues online for about 3 months, with "
        . "ONE ~1.5-hour session per week (normally Tuesday evening). The weekly-evening format applies ONLY to "
        . "this online phase, NOT to the 3 physical days.\n"
        . "  - CRITICAL: there is NO '5-week' programme, NO 'X-week' schedule, and NO 'Monday/Tuesday/Wednesday' "
        . "evening-class pattern for this in-person event. If any such schedule (e.g. '5 weeks', 'Mon/Tue/Wed', "
        . "'8:00-9:30 PM') appears anywhere in the knowledge, it belongs to a different online/virtual programme — "
        . "IGNORE it completely for this event. Never tell a client this event is a 5-week programme.\n");
}

/**
 * Which training programme an event belongs to.
 *
 * Reproduces wa_event_program_for(): the stored setting wins, otherwise the first
 * active programme whose keyword appears in the event title. The original then
 * calls wa_programs_list(), which runs wa_kb_ensure_schema() — the last DDL on any
 * voice request path. wa_voice_programs() reads the same rows, in the same order,
 * with a prepared statement and no schema-ensure, so the answer is identical.
 */
function wa_voice_event_program_id($conn, $eventId, $eventName = '') {
    $pid = (int)wa_setting_get($conn, 'event_program:' . (int)$eventId, '0');
    if ($pid > 0) { return $pid; }
    if ($eventName === '') { $eventName = wa_voice_ref_name($conn, 'event', (int)$eventId); }
    if ($eventName === '') { return 0; }
    foreach (wa_voice_programs($conn) as $p) {
        foreach (wa_program_keywords_arr($p) as $kw) {
            if ($kw !== '' && stripos($eventName, $kw) !== false) { return (int)$p['id']; }
        }
    }
    return 0;
}

/**
 * The knowledge an in-person event chat answers from — the event's live details,
 * the corrective schedule rule, the event's own notes, and its programme's general
 * knowledge.
 *
 * A read-only reproduction of wa_event_effective_kb(). Same sections, same order,
 * same headings, same trailing scrub of the stale virtual-programme outline link.
 * The only differences are mechanical: the Event row is read with a prepared
 * statement, and the programme is resolved through wa_voice_event_program_id()
 * rather than through the wa_programs_list() path that issues DDL.
 */
function wa_voice_event_knowledge($conn, $eventId) {
    $eventId = (int)$eventId;
    $name = wa_voice_ref_name($conn, 'event', $eventId);

    $ev = wa_voice_fetch($conn,
        "SELECT `start_on`, `end_on`, `location`, COALESCE(`early_amount`, 0) AS `early_amount`
           FROM `Event` WHERE `event_id` = ? LIMIT 1",
        'i', [$eventId]) ?: [];

    $dates = wa_event_when_range($ev['start_on'] ?? '', $ev['end_on'] ?? '');
    $city  = trim((string)($ev['location'] ?? ''));
    if (wa_voice_event_is_academic($city)) { $city = ''; }
    $amt  = (float)($ev['early_amount'] ?? 0);
    $cost = $amt > 0 ? ('USD ' . rtrim(rtrim(number_format($amt, 2), '0'), '.')) : '';
    $reg  = wa_register_link($conn, 'event', $eventId);

    $specs = "=== THIS EVENT (in-person M&E training) ===\n"
        . ($name  !== '' ? "Event: {$name}\n" : '')
        . ($city  !== '' ? "City / venue location: {$city}\n" : '')
        . ($dates !== '' ? "Start date: {$dates}\n" : '')
        . WA_VOICE_EVENT_SCHEDULE_RULE
        . ($cost !== '' ? "Cost / fee: {$cost}\n" : '')
        . ($reg  !== '' ? "Registration link: {$reg}\n" : '');

    $own = trim((string)wa_knowledge_get_ai($conn, 'event', $eventId));

    $pid   = wa_voice_event_program_id($conn, $eventId, $name);
    $genKb = '';
    if ($pid > 0) {
        $genKb = trim((string)wa_knowledge_get_ai($conn, 'program', $pid));
    }

    $parts = [$specs];
    if ($own !== '')   { $parts[] = "=== EVENT NOTES ===\n" . $own; }
    if ($genKb !== '') { $parts[] = "=== M&E PROGRAMME — GENERAL INFO (what the training covers) ===\n" . $genKb; }
    $out = trim(implode("\n", $parts));
    // Scrub the wrong (virtual-programme) course-outline link if it lingers in any
    // KB — events must only ever use the configured events outline.
    $out = (string)preg_replace('#https?://\S*13YfH2JH-cPu_ANk4wZuCF6wuYJ18ctLO\S*#i', '', $out);
    return trim($out);
}

// =====================================================================
// Programme details
// =====================================================================

/**
 * Approved knowledge for one course, event or programme.
 *
 * @return array|null null when the reference does not resolve
 */
function wa_voice_programme_details($conn, $type, $id) {
    $id = (int)$id;
    if ($id < 1 || !wa_voice_valid_ref_type($type)) { return null; }

    if ($type === 'event') {
        $row = wa_voice_event_brief($conn, $id);
        if (!$row) { return null; }
        $academic = wa_voice_event_is_academic($row['location'] ?? '');
        $full = wa_voice_fetch($conn,
            "SELECT `early_amount`, `early_end_on`, `advance_amount`, `advance_end_on`,
                    `gate_amount`, `gate_start_on`
               FROM `Event` WHERE `event_id` = ? LIMIT 1",
            'i', [$id]);

        return wa_voice_shape_details([
            'type' => 'event', 'id' => $id,
            'name' => (string)$row['event_title'],
            'delivery_mode' => $academic ? 'virtual' : 'onsite',
            'when'  => $academic ? 'Intake-based — enrol anytime'
                                 : wa_event_when_range($row['start_on'] ?? '', $row['end_on'] ?? ''),
            'where' => $academic ? '' : trim((string)($row['location'] ?? '')),
            'fees'  => $full ? wa_event_pricing($full) : '',
            'register_url' => wa_register_link($conn, 'event', $id),
            // The shared outline applies to in-person training events only.
            'outline_url'  => $academic ? '' : wa_event_outline_url($conn),
            'knowledge'    => $academic
                ? wa_knowledge_get_ai($conn, 'event', $id)
                : wa_voice_event_knowledge($conn, $id),
        ]);
    }

    if ($type === 'course') {
        $name = wa_voice_ref_name($conn, 'course', $id);
        if ($name === '') { return null; }
        return wa_voice_shape_details([
            'type' => 'course', 'id' => $id, 'name' => $name,
            'delivery_mode' => 'virtual',
            'register_url'  => wa_register_link($conn, 'course', $id),
            'knowledge'     => wa_knowledge_get_ai($conn, 'course', $id),
        ]);
    }

    // program
    $p = wa_voice_fetch($conn,
        "SELECT `id`, `name`, `keywords` FROM `wa_programs` WHERE `id` = ? AND `status` = 1 LIMIT 1",
        'i', [$id]);
    if (!$p) { return null; }

    $lines = [];
    foreach (wa_program_events($conn, $p, 10) as $s) {
        $line = trim(wa_event_display($s['location'], $s['when']));
        $fees = wa_event_pricing($s);
        if ($fees !== '') { $line .= ' — in-person fees: ' . $fees; }
        if ($line !== '') { $lines[] = $line; }
    }

    return wa_voice_shape_details([
        'type' => 'program', 'id' => $id, 'name' => (string)$p['name'],
        'delivery_mode' => 'onsite',
        'when'          => $lines ? implode('; ', $lines) : '',
        'register_url'  => wa_register_link($conn, 'program', $id),
        'knowledge'     => wa_knowledge_get_ai($conn, 'program', $id),
    ]);
}
