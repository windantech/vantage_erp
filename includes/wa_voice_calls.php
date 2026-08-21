<?php
/**
 * Voice call memory — data layer (Phase 2.2).
 *
 * Two callers with very different rights use this file, and the split matters.
 *
 * The VOICE ENDPOINT calls wa_voice_call_record(). It runs as vantage_voice,
 * which can INSERT and UPDATE the three wa_voice_* tables and can do nothing
 * else — no write on wa_messages, wa_conversations, wa_contacts, course, Event
 * or wa_knowledge. A confirmed interest change is therefore not applied by the
 * call that reported it; it is queued as a pending action.
 *
 * The CRON calls wa_voice_actions_process(). It runs as the application, which
 * does have the rights, and it re-validates everything before it acts: the
 * reference still exists and is active, the conversation is still safe to
 * reroute, and the action has not already been applied. It uses the module's own
 * routing and ownership helpers rather than reimplementing them, so a call
 * changes a conversation exactly the way a message would.
 *
 * That separation is the whole design. A telephone call can say what happened;
 * only the CRM can decide what that means.
 *
 * NO TRANSCRIPT AND NO AUDIO reach this file. The voice service holds a bounded
 * transcript in memory for the length of one call and drops it once the summary
 * exists; what arrives here is the summary and a handful of validated fields.
 *
 * Every statement is PREPARED. The values come from outside the building.
 *
 * Requires wa_functions.php (routing, ownership, masking helpers).
 */

// Field caps, enforced here as well as in the endpoint's validator. The database
// would truncate silently; these make the boundary explicit and testable.
if (!defined('WA_VOICE_SUMMARY_MAX'))   { define('WA_VOICE_SUMMARY_MAX', 1200); }
if (!defined('WA_VOICE_FIELD_MAX'))     { define('WA_VOICE_FIELD_MAX', 600); }
if (!defined('WA_VOICE_STEP_MAX'))      { define('WA_VOICE_STEP_MAX', 255); }
if (!defined('WA_VOICE_PROGRAMME_MAX')) { define('WA_VOICE_PROGRAMME_MAX', 12); }

/** How many past calls the WhatsApp AI is told about, and how much of them. */
if (!defined('WA_VOICE_AI_SUMMARIES'))  { define('WA_VOICE_AI_SUMMARIES', 3); }
if (!defined('WA_VOICE_AI_CHARS'))      { define('WA_VOICE_AI_CHARS', 900); }

/** A pending action is abandoned after this many failed attempts. */
if (!defined('WA_VOICE_ACTION_TRIES'))  { define('WA_VOICE_ACTION_TRIES', 5); }

// =====================================================================
// Schema availability — a check, never a creation
// =====================================================================

/**
 * Are all three Phase 2.2 tables present?
 *
 * Asked of information_schema, exactly as Phase 2.1A asks about its own two: the
 * voice account may hold no SELECT on a table it can only INSERT into, so
 * `SELECT 1 FROM wa_voice_calls` would fail on privilege and be
 * indistinguishable from a missing table.
 *
 * A false answer makes the endpoint answer 503 schema_unavailable. It never
 * triggers a CREATE — this endpoint issues no DDL at all, and the account it
 * runs as holds no CREATE privilege to issue it with.
 */
function wa_voice_calls_schema_available($conn) {
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $rows = wa_voice_fetch_all($conn,
        "SELECT `TABLE_NAME` FROM `information_schema`.`TABLES`
          WHERE `TABLE_SCHEMA` = DATABASE()
            AND `TABLE_NAME` IN ('wa_voice_calls', 'wa_voice_call_programmes',
                                 'wa_voice_interest_actions')");
    $seen = [];
    foreach ($rows as $r) { $seen[strtolower((string)($r['TABLE_NAME'] ?? ''))] = true; }

    $cache = isset($seen['wa_voice_calls'])
          && isset($seen['wa_voice_call_programmes'])
          && isset($seen['wa_voice_interest_actions']);
    if (!$cache) {
        error_log('[wa-voice] Phase 2.2 tables missing — run db_schema/wa_voice_phase22.sql. '
                . 'present: ' . implode(',', array_keys($seen)));
    }
    return $cache;
}

// =====================================================================
// Reference validation
// =====================================================================

/**
 * Does this course / event / programme exist, and is it active?
 *
 * The active check is deliberate and differs per type because the tables do:
 * a course or an event carries status = 1, a programme likewise. An interest
 * confirmed against something that has been retired is not an interest anybody
 * can act on, so it is refused rather than recorded.
 *
 * Returns the display name, or '' when the reference does not resolve.
 */
function wa_voice_ref_name_active($conn, $type, $id) {
    $id = (int)$id;
    if ($id < 1) { return ''; }

    if ($type === 'event') {
        $r = wa_voice_fetch($conn,
            "SELECT `event_title` AS `nm` FROM `Event`
              WHERE `event_id` = ? AND `status` = 1 LIMIT 1", 'i', [$id]);
    } elseif ($type === 'program') {
        $r = wa_voice_fetch($conn,
            "SELECT `name` AS `nm` FROM `wa_programs`
              WHERE `id` = ? AND `status` = 1 LIMIT 1", 'i', [$id]);
    } elseif ($type === 'course') {
        $r = wa_voice_fetch($conn,
            "SELECT `course` AS `nm` FROM `course`
              WHERE `course_id` = ? AND `status` = 1 LIMIT 1", 'i', [$id]);
    } else {
        return '';
    }
    return $r ? trim((string)$r['nm']) : '';
}

// =====================================================================
// Writing a completed call
// =====================================================================

/**
 * Record one completed call. Idempotent on call_id.
 *
 * Everything lands in one transaction: the call, the programmes it touched, and
 * at most one pending interest action. A call that is half-recorded is worse
 * than one not recorded at all — a rep would read a summary with no programmes
 * against it and draw the wrong conclusion.
 *
 * @param array $data already validated and capped by wa_voice_validate_call()
 * @return array {status: created|duplicate|error, id, action_queued, error}
 */
function wa_voice_call_record($conn, array $data) {
    $callId = (string)$data['call_id'];

    // Fast path for the common retry: a spool drain, a network hiccup, or the
    // same finaliser reaching the CRM twice.
    $existing = wa_voice_call_by_call_id($conn, $callId);
    if ($existing) {
        return ['status' => 'duplicate', 'id' => (int)$existing['id'], 'action_queued' => false];
    }

    @mysqli_begin_transaction($conn);
    try {
        $n = wa_voice_exec($conn,
            "INSERT INTO `wa_voice_calls`
                (`call_id`, `contact_id`, `conversation_id`, `caller_masked`,
                 `started_at`, `ended_at`, `duration_seconds`, `outcome`, `summary`,
                 `questions_answered`, `unresolved_questions`, `objections_or_concerns`,
                 `requested_next_step`, `follow_up_required`, `follow_up_priority`,
                 `requested_callback_at`, `transfer_requested`, `transfer_completed`,
                 `summary_source`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'siisssissssssiisiis',
            [
                $callId,
                $data['contact_id'] ?: null,
                $data['conversation_id'] ?: null,
                (string)$data['caller_masked'],
                (string)$data['started_at'],
                $data['ended_at'],
                $data['duration_seconds'],
                (string)$data['outcome'],
                $data['summary'],
                $data['questions_answered'],
                $data['unresolved_questions'],
                $data['objections_or_concerns'],
                $data['requested_next_step'],
                (int)$data['follow_up_required'],
                (string)$data['follow_up_priority'],
                $data['requested_callback_at'],
                (int)$data['transfer_requested'],
                (int)$data['transfer_completed'],
                (string)$data['summary_source'],
            ]);
        if ($n < 1) { throw new RuntimeException('call insert affected no rows'); }

        $voiceCallId = (int)@mysqli_insert_id($conn);
        if ($voiceCallId < 1) { throw new RuntimeException('no insert id'); }

        // Programmes. INSERT IGNORE so a resubmission that somehow got past the
        // duplicate check above still cannot double a relation.
        //
        // The return value IS checked. wa_voice_exec() answers -1 when the
        // statement did not run, and 0 when IGNORE swallowed a duplicate — those
        // mean opposite things. Treating the first as success would commit a
        // call whose programmes are missing, and a rep would read a summary with
        // nothing recorded against it and draw exactly the wrong conclusion.
        foreach ($data['programmes'] as $p) {
            $n = wa_voice_exec($conn,
                "INSERT IGNORE INTO `wa_voice_call_programmes`
                    (`voice_call_id`, `ref_type`, `ref_id`, `relation`)
                 VALUES (?, ?, ?, ?)",
                'isis', [$voiceCallId, (string)$p['ref_type'], (int)$p['ref_id'], (string)$p['relation']]);
            if ($n < 0) { throw new RuntimeException('programme insert failed'); }
        }

        // At most one pending action, and only when the in-call state machine
        // recorded a confirmation. The summariser cannot reach this branch: the
        // endpoint's validator drops any confirmed_interest that does not carry
        // confirmation_recorded from the call itself.
        $queued = false;
        if (!empty($data['interest_action'])) {
            $a = $data['interest_action'];
            $n = wa_voice_exec($conn,
                "INSERT IGNORE INTO `wa_voice_interest_actions`
                    (`voice_call_id`, `contact_id`, `conversation_id`,
                     `from_ref_type`, `from_ref_id`, `to_ref_type`, `to_ref_id`,
                     `confirmation_recorded`, `status`, `idempotency_key`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'pending', ?)",
                'iiisisis',
                [$voiceCallId, (int)$a['contact_id'], $a['conversation_id'] ?: null,
                 $a['from_ref_type'], $a['from_ref_id'] ?: null,
                 (string)$a['to_ref_type'], (int)$a['to_ref_id'],
                 (string)$a['idempotency_key']]);
            // Same distinction as above, and it matters more here: a call
            // committed without the interest action it reported would record a
            // confirmation that silently never happens.
            if ($n < 0) { throw new RuntimeException('interest action insert failed'); }
            $queued = ($n > 0);
        }

        @mysqli_commit($conn);
        return ['status' => 'created', 'id' => $voiceCallId, 'action_queued' => $queued];
    } catch (Throwable $e) {
        @mysqli_rollback($conn);
        // A concurrent submission won the UNIQUE key. That is success, not error:
        // the record exists and exactly one of us made it.
        $again = wa_voice_call_by_call_id($conn, $callId);
        if ($again) {
            return ['status' => 'duplicate', 'id' => (int)$again['id'], 'action_queued' => false];
        }
        error_log('[wa-voice] call record failed: ' . $e->getMessage());
        return ['status' => 'error', 'id' => 0, 'action_queued' => false,
                'error' => 'write_failed'];
    }
}

/** One call by its provider id, or null. */
function wa_voice_call_by_call_id($conn, $callId) {
    return wa_voice_fetch($conn,
        "SELECT `id`, `contact_id`, `outcome`, `created_at`
           FROM `wa_voice_calls` WHERE `call_id` = ? LIMIT 1",
        's', [(string)$callId]);
}

// =====================================================================
// Reading — the thread card
// =====================================================================

/**
 * Calls for one contact, newest first, with their programmes attached.
 *
 * Deliberately does NOT select call_id. It is an internal provider identifier
 * with no meaning to a rep, and the surest way to keep it out of the rendered
 * page is never to fetch it.
 */
function wa_voice_calls_for_contact($conn, $contactId, $limit = 20) {
    // A page render must survive the tables not existing.
    //
    // mysqli throws by default on PHP 8.1+, and the `@` in wa_voice_stmt()
    // suppresses warnings, NOT exceptions — so querying a table that has not
    // been created is an uncaught fatal, and a fatal part-way through a page is
    // a blank screen with the sidebar already drawn and nothing in the log to
    // say why. Which is exactly what opening a conversation did when the code
    // was deployed ahead of db_schema/wa_voice_phase22.sql.
    //
    // Returning [] renders the thread exactly as it did before Phase 2.2, which
    // is the correct behaviour for a CRM that has the code but not the tables.
    try {
        if (!wa_voice_calls_schema_available($conn)) { return []; }
    } catch (Throwable $e) {
        return [];
    }

    $limit = max(1, min(50, (int)$limit));
    try {
        return wa_voice_calls_for_contact_unguarded($conn, $contactId, $limit);
    } catch (Throwable $e) {
        error_log('[wa-voice] thread card unavailable: ' . $e->getMessage());
        return [];
    }
}

/** The actual read. Separated so the guard above is impossible to bypass by
 *  accident and so a future reader has one obvious place to add to. */
function wa_voice_calls_for_contact_unguarded($conn, $contactId, $limit) {
    $calls = wa_voice_fetch_all($conn,
        "SELECT `id`, `started_at`, `ended_at`, `duration_seconds`, `outcome`,
                `summary`, `questions_answered`, `unresolved_questions`,
                `objections_or_concerns`, `requested_next_step`,
                `follow_up_required`, `follow_up_priority`, `requested_callback_at`,
                `transfer_requested`, `transfer_completed`, `summary_source`
           FROM `wa_voice_calls`
          WHERE `contact_id` = ?
       ORDER BY `started_at` DESC, `id` DESC
          LIMIT " . $limit,
        'i', [(int)$contactId]);
    if (!$calls) { return []; }

    $ids = [];
    foreach ($calls as $c) { $ids[] = (int)$c['id']; }
    $progs = wa_voice_programmes_for_calls($conn, $ids);

    foreach ($calls as $i => $c) {
        $calls[$i]['programmes'] = $progs[(int)$c['id']] ?? [];
    }
    return $calls;
}

/**
 * Programme rows for a set of calls, resolved to names, keyed by call.
 *
 * Names are resolved at read time rather than stored, so a course renamed after
 * the call shows its current name — which is what a rep opening the thread
 * expects to see.
 */
function wa_voice_programmes_for_calls($conn, array $voiceCallIds) {
    // Called from the thread card, so it carries the same guarantee: a missing
    // table renders an empty list rather than blanking the page.
    try {
        if (!wa_voice_calls_schema_available($conn)) { return []; }
    } catch (Throwable $e) {
        return [];
    }
    $ids = [];
    foreach ($voiceCallIds as $id) { if ((int)$id > 0) { $ids[] = (int)$id; } }
    if (!$ids) { return []; }
    $list = implode(',', $ids);

    // Concatenated rather than interpolated. $list is built above from values
    // that have each been through (int), so both forms are equally safe — but
    // the module's rule for voice code is that no SQL string interpolates, and a
    // rule with one exception is a rule nobody can check.
    $rows = wa_voice_fetch_all($conn,
        "SELECT `voice_call_id`, `ref_type`, `ref_id`, `relation`
           FROM `wa_voice_call_programmes`
          WHERE `voice_call_id` IN (" . $list . ")
       ORDER BY `id` ASC");

    $out = [];
    foreach ($rows as $r) {
        $name = wa_voice_ref_name_active($conn, (string)$r['ref_type'], (int)$r['ref_id']);
        if ($name === '') {
            // Retired since the call. Say so rather than showing a blank.
            $name = wa_voice_ref_name_any($conn, (string)$r['ref_type'], (int)$r['ref_id']);
            if ($name !== '') { $name .= ' (no longer active)'; }
        }
        if ($name === '') { continue; }
        $out[(int)$r['voice_call_id']][] = [
            'relation' => (string)$r['relation'],
            'name'     => $name,
        ];
    }
    return $out;
}

/** A reference's name ignoring its active state, for display of retired items. */
function wa_voice_ref_name_any($conn, $type, $id) {
    $id = (int)$id;
    if ($id < 1) { return ''; }
    if ($type === 'event') {
        $r = wa_voice_fetch($conn, "SELECT `event_title` AS `nm` FROM `Event` WHERE `event_id` = ? LIMIT 1", 'i', [$id]);
    } elseif ($type === 'program') {
        $r = wa_voice_fetch($conn, "SELECT `name` AS `nm` FROM `wa_programs` WHERE `id` = ? LIMIT 1", 'i', [$id]);
    } elseif ($type === 'course') {
        $r = wa_voice_fetch($conn, "SELECT `course` AS `nm` FROM `course` WHERE `course_id` = ? LIMIT 1", 'i', [$id]);
    } else {
        return '';
    }
    return $r ? trim((string)$r['nm']) : '';
}

/**
 * The last few voice summaries for the WhatsApp AI's private context.
 *
 * Bounded twice — by count and by total characters — because this text goes into
 * a system prompt. It carries no call id, no transcript and no internal
 * reference; a summary is background about a conversation that happened, not a
 * record the AI should quote or reason about as instruction.
 *
 * Returns [] when the tables are absent, so a deployment that has run the code
 * but not the migration degrades to exactly today's behaviour.
 */
function wa_voice_recent_summaries($conn, $contactId, $limit = null, $maxChars = null) {
    $limit    = $limit    === null ? WA_VOICE_AI_SUMMARIES : (int)$limit;
    $maxChars = $maxChars === null ? WA_VOICE_AI_CHARS : (int)$maxChars;
    $limit    = max(1, min(5, $limit));

    try {
        if (!wa_voice_calls_schema_available($conn)) { return []; }
        $rows = wa_voice_fetch_all($conn,
            "SELECT `started_at`, `summary`, `requested_next_step`,
                    `follow_up_required`, `outcome`
               FROM `wa_voice_calls`
              WHERE `contact_id` = ? AND `summary` IS NOT NULL AND `summary` <> ''
           ORDER BY `started_at` DESC, `id` DESC
              LIMIT " . $limit,
            'i', [(int)$contactId]);
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    $used = 0;
    foreach (array_reverse($rows) as $r) {          // oldest first: reads as a progression
        $text = trim(preg_replace('/\s+/u', ' ', (string)$r['summary']));
        if ($text === '') { continue; }
        if ($used + strlen($text) > $maxChars) {
            $room = $maxChars - $used;
            if ($room < 80) { break; }              // not enough left to be worth a fragment
            $text = rtrim(substr($text, 0, $room - 1)) . '…';
        }
        $used += strlen($text);
        $out[] = [
            'when'      => substr((string)$r['started_at'], 0, 16),
            'summary'   => $text,
            'next_step' => trim((string)($r['requested_next_step'] ?? '')),
            'outcome'   => (string)$r['outcome'],
        ];
        if ($used >= $maxChars) { break; }
    }
    return $out;
}

// =====================================================================
// Pending interest actions — applied by the PRIVILEGED cron only
// =====================================================================

/**
 * Apply queued interest changes.
 *
 * Runs from wa_cron.php as the application user. Everything the voice endpoint
 * could not be trusted to decide is decided here, in this order, and any refusal
 * is recorded with a reason rather than silently dropped:
 *
 *   1. The action carries an in-call confirmation. Without it, nothing happens.
 *   2. The reference still exists and is still active.
 *   3. The conversation is still safe to reroute — see below.
 *   4. The change goes through the module's own routing helper, so ownership is
 *      derived exactly as it would be for a WhatsApp message.
 *
 * @return array counters for the cron's JSON output
 */
function wa_voice_actions_process($conn, $limit = 25) {
    $limit = max(1, min(100, (int)$limit));
    $stats = ['examined' => 0, 'applied' => 0, 'rejected' => 0, 'failed' => 0];

    if (!wa_voice_calls_schema_available($conn)) { return $stats + ['skipped' => 'schema']; }

    $tries = (int)WA_VOICE_ACTION_TRIES;
    $rows = wa_voice_fetch_all($conn,
        "SELECT * FROM `wa_voice_interest_actions`
          WHERE `status` = 'pending' AND `attempts` < ?
       ORDER BY `created_at` ASC
          LIMIT " . $limit,
        'i', [$tries]);

    foreach ($rows as $a) {
        $stats['examined']++;
        $id = (int)$a['id'];

        // Claim it first. Two crons overlapping must not both apply one change,
        // and incrementing before the work means a row that kills the process
        // cannot be retried for ever.
        $claimed = wa_voice_exec($conn,
            "UPDATE `wa_voice_interest_actions`
                SET `attempts` = `attempts` + 1
              WHERE `id` = ? AND `status` = 'pending'", 'i', [$id]);
        if ($claimed < 1) { continue; }

        try {
            $verdict = wa_voice_action_apply($conn, $a);
        } catch (Throwable $e) {
            $verdict = ['status' => 'failed', 'reason' => 'exception', 'owner' => null];
            error_log('[wa-voice-cron] action ' . $id . ' threw: ' . $e->getMessage());
        }

        wa_voice_exec($conn,
            "UPDATE `wa_voice_interest_actions`
                SET `status` = ?, `last_error` = ?, `resulting_owner_id` = ?,
                    `previous_owner_id` = ?, `processed_at` = NOW()
              WHERE `id` = ?",
            'ssiii',
            [(string)$verdict['status'],
             $verdict['status'] === 'applied' ? null : (string)$verdict['reason'],
             $verdict['owner'], $verdict['previous_owner'] ?? null, $id]);

        if ($verdict['status'] === 'applied')      { $stats['applied']++; }
        elseif ($verdict['status'] === 'rejected') { $stats['rejected']++; }
        else                                       { $stats['failed']++; }
    }
    return $stats;
}

/**
 * Decide and apply one action. Pure decision plus one write.
 *
 * THE OWNERSHIP RULE, which is the part worth reading twice: a conversation is
 * rerouted only when nobody has taken charge of it. If a human is handling it,
 * or a rep has been assigned, or it has been escalated, the interest is left
 * exactly as it is and the action is rejected with a reason.
 *
 * A phone call must not be able to take a conversation away from the person
 * working it. The caller may well have changed their mind — but that is a thing
 * for the rep who owns them to act on, not something to do behind their back
 * because a summary said so.
 *
 * @return array {status, reason, owner, previous_owner}
 */
function wa_voice_action_apply($conn, array $a) {
    $none = function ($reason) { return ['status' => 'rejected', 'reason' => $reason,
                                         'owner' => null, 'previous_owner' => null]; };

    if ((int)$a['confirmation_recorded'] !== 1) { return $none('no_confirmation'); }

    $toType = (string)$a['to_ref_type'];
    $toId   = (int)$a['to_ref_id'];
    if (wa_voice_ref_name_active($conn, $toType, $toId) === '') {
        return $none('reference_invalid_or_inactive');
    }

    $contactId = (int)$a['contact_id'];
    $conv = wa_get_conversation($conn, $contactId);
    if (!$conv) { return $none('no_conversation'); }

    $previousOwner = $conv['assigned_user_id'] !== null ? (int)$conv['assigned_user_id'] : null;

    // ---- the ownership guard ------------------------------------------------
    if ((string)($conv['handler'] ?? 'ai') === 'human') {
        return ['status' => 'rejected', 'reason' => 'handled_by_human',
                'owner' => $previousOwner, 'previous_owner' => $previousOwner];
    }
    if ((int)($conv['escalated'] ?? 0) === 1) {
        return ['status' => 'rejected', 'reason' => 'escalated',
                'owner' => $previousOwner, 'previous_owner' => $previousOwner];
    }
    if ($previousOwner !== null && $previousOwner > 0) {
        return ['status' => 'rejected', 'reason' => 'already_assigned',
                'owner' => $previousOwner, 'previous_owner' => $previousOwner];
    }
    // A manual owner override on the CURRENT topic is a deliberate human
    // decision about this conversation's routing. Do not overrule it.
    $curType = (string)($conv['ref_type'] ?? '');
    $curId   = (int)($conv['ref_id'] ?? 0);
    if ($curId > 0 && in_array($curType, ['course', 'event'], true)
        && wa_owner_override($conn, $curType, $curId) !== null) {
        return ['status' => 'rejected', 'reason' => 'manual_owner_override',
                'owner' => $previousOwner, 'previous_owner' => $previousOwner];
    }

    // Already there. Applied, because the desired state holds — reporting a
    // failure for work that did not need doing produces noise, not safety.
    if ($curType === $toType && $curId === $toId) {
        return ['status' => 'applied', 'reason' => 'already_current',
                'owner' => $previousOwner, 'previous_owner' => $previousOwner];
    }

    // ---- apply, through the module's own routing ---------------------------
    // wa_first_owner() is what a WhatsApp message would use, so a call routes a
    // conversation to exactly the rep a message about the same programme would.
    // A 'program' reference has no conversation ref_type of its own — the
    // conversation records course/event — so it is stored as program_id and the
    // owner comes from the programme.
    $newOwner = null;
    if ($toType === 'program') {
        $prog = wa_program_get($conn, $toId);
        $ids  = $prog ? wa_program_owner_ids($prog) : [];
        $newOwner = $ids ? (int)$ids[0] : null;
        wa_conv_set_program($conn, (int)$conv['id'], $toId);
        if ($newOwner !== null) {
            wa_voice_exec($conn,
                "UPDATE `wa_conversations` SET `assigned_user_id` = ?,
                        `last_route_reason` = 'voice_confirmed'
                  WHERE `id` = ?", 'ii', [$newOwner, (int)$conv['id']]);
        }
    } else {
        $newOwner = wa_first_owner($conn, $toType, $toId);
        wa_assign_conversation($conn, $contactId, $toType, $toId, $newOwner,
                               'voice_confirmed', 1.000);
    }

    return ['status' => 'applied', 'reason' => 'ok',
            'owner' => $newOwner, 'previous_owner' => $previousOwner];
}
