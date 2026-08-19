<?php
/**
 * WhatsApp call-permission state — Phase 1.1 (template-only pilot).
 *
 * Meta requires a customer to grant permission before a business may call them.
 * This file owns that state: what we have asked, what they answered, when it
 * lapses, and whether a rep may dial right now.
 *
 * State is keyed by (contact_id, business_phone_id). The business phone id is part
 * of the key because permission is granted to a NUMBER, not to the organisation:
 * a grant on +254798009935 says nothing about any other line we might add later.
 *
 * DERIVED, NEVER STORED: 'expired' is computed at read time from the timestamps.
 * Storing it would need a cron to keep it true, and a stale 'granted' row would
 * show a rep "Call now" for a call Meta rejects.
 *
 * The decision functions at the top are PURE — no database, no clock of their own
 * (the caller passes $now) — so the whole state machine is testable offline. The
 * database functions below are thin wrappers that add locking.
 */

require_once __DIR__ . '/wa_call_config.php';

// =====================================================================
// Pure decision logic (no database, no I/O)
// =====================================================================

/**
 * Derive the presentable state of a permission row.
 *
 * @param array|null $row  row from wa_call_permissions, or null when none exists
 * @param int        $now  unix timestamp to evaluate against
 * @return array {state, callable_now, reason, expires_at, retry_after}
 *
 * state: unknown | pending | granted | window_closed | rejected | revoked | expired
 */
function wa_call_derive_state($row, $now) {
    $out = ['state' => 'unknown', 'callable_now' => false, 'reason' => '',
            'expires_at' => null, 'responded_at' => null];
    if (!is_array($row) || $row === []) { return $out; }

    $status    = (string)($row['status'] ?? 'unknown');
    $requested = wa_call_ts($row['requested_at'] ?? null);
    $responded = wa_call_ts($row['responded_at'] ?? null);
    $expires   = wa_call_ts($row['expires_at']   ?? null);
    $out['expires_at']   = $expires;
    $out['responded_at'] = $responded;

    if ($status === 'pending') {
        // Pilot rule: an unanswered request lapses seven days after we successfully
        // submitted it. Without this a customer who simply ignores the prompt would
        // pin the chat on "Permission requested" for ever, and no rep could retry.
        if ($requested !== null && ($now - $requested) >= WA_CALL_PENDING_TTL) {
            $out['state']  = 'expired';
            $out['reason'] = 'No answer within seven days.';
            return $out;
        }
        $out['state'] = 'pending';
        return $out;
    }

    if ($status === 'granted') {
        // A grant with no expiry means we never recorded a real GRANTED. Fail closed:
        // showing "Call now" here produces a call the platform refuses.
        if ($expires === null || $now >= $expires) {
            $out['state']  = 'expired';
            $out['reason'] = 'Permission has expired.';
            return $out;
        }
        // Pilot restriction: only dial inside 24 hours of the grant. Meta may refuse
        // a call later in the seven-day window, and a rejected call looks to the rep
        // exactly like a broken button. Say so instead of guessing.
        if ($responded !== null && ($now - $responded) >= WA_CALL_WINDOW_TTL) {
            $out['state']  = 'window_closed';
            $out['reason'] = 'Calling window closed.';
            return $out;
        }
        $out['state']        = 'granted';
        $out['callable_now'] = true;
        return $out;
    }

    if ($status === 'rejected' || $status === 'revoked') {
        $out['state'] = $status;
        return $out;
    }

    return $out;   // unknown
}

/** '' / null / '0000-00-00…' -> null; otherwise a unix timestamp. */
function wa_call_ts($v) {
    $s = trim((string)$v);
    if ($s === '' || strpos($s, '0000-00-00') === 0) { return null; }
    $t = strtotime($s);
    return $t === false ? null : $t;
}

/**
 * Mask a phone number for logs: 254745811248 -> 2547****1248.
 * Operational logs are read by more people, and kept longer, than the CRM itself;
 * a full customer number in a log line is personal data leaking sideways. Enough
 * digits survive to correlate a support report with a row.
 */
function wa_call_mask_msisdn($e164) {
    $d = preg_replace('/\D+/', '', (string)$e164);
    $n = strlen($d);
    if ($n === 0) { return '(none)'; }
    if ($n <= 8)  { return str_repeat('*', $n); }
    return substr($d, 0, 4) . str_repeat('*', $n - 8) . substr($d, -4);
}

/** States from which a NEW permission request may be offered at all. */
function wa_call_state_allows_request($state) {
    return in_array($state, ['unknown', 'rejected', 'revoked', 'expired'], true);
}

/**
 * May we send another request? Pure — the caller supplies the count and the
 * timestamp of the oldest request still inside the throttle window.
 *
 * @return array {allowed:bool, reason:string, retry_after:?int}
 */
function wa_call_throttle_check($state, $requestCount, $oldestRequestTs, $now) {
    if ($state === 'pending') {
        return ['allowed' => false, 'reason' => 'A request is already pending.', 'retry_after' => null];
    }
    if ($state === 'granted' || $state === 'window_closed') {
        return ['allowed' => false, 'reason' => 'Permission is already granted.', 'retry_after' => null];
    }
    if (!wa_call_state_allows_request($state)) {
        return ['allowed' => false, 'reason' => 'Not available in this state.', 'retry_after' => null];
    }
    if ((int)$requestCount < WA_CALL_MAX_REQUESTS) {
        return ['allowed' => true, 'reason' => '', 'retry_after' => null];
    }
    // Throttled. The slot frees when the OLDEST request in the window ages out.
    $retry = $oldestRequestTs !== null
        ? (int)$oldestRequestTs + (WA_CALL_THROTTLE_DAYS * 24 * 3600)
        : null;
    return ['allowed' => false,
            'reason'  => 'Limit of ' . WA_CALL_MAX_REQUESTS . ' requests in '
                         . WA_CALL_THROTTLE_DAYS . ' days reached.',
            'retry_after' => $retry];
}

/**
 * Map a webhook status to a stored status, or '' when unrecognised.
 *
 * Two vocabularies, because the live channel does not use the documented one: a
 * customer's reply carries response "accept" / "reject", while the integration
 * notes described GRANTED / REJECTED / REVOKED. Both are accepted so the parser
 * does not depend on which one a given delivery happens to use.
 */
function wa_call_map_status($status) {
    switch (strtoupper(trim((string)$status))) {
        case 'GRANTED':
        case 'ACCEPT':
        case 'ACCEPTED': return 'granted';
        case 'REJECTED':
        case 'REJECT':
        case 'DECLINE':
        case 'DECLINED': return 'rejected';
        case 'REVOKED':
        case 'REVOKE':   return 'revoked';
    }
    return '';
}

/**
 * What a webhook status should change, given what we already hold. Pure.
 *
 * Returns null when nothing should change — which is how retries are absorbed.
 * 360dialog has no event id in this payload, so the transition itself IS the
 * identity: re-delivering GRANTED while already granted must not append an event
 * and must NOT push expires_at further out, or a retry storm would silently
 * extend a customer's permission indefinitely.
 */
function wa_call_transition($currentStatus, $incomingStatus, $now, $expiresAt = null) {
    $to = wa_call_map_status($incomingStatus);
    if ($to === '') { return null; }                       // unrecognised -> ignore
    if ($to === (string)$currentStatus) { return null; }   // identical retry -> no-op

    $change = ['status' => $to, 'responded_at' => $now, 'expires_at' => null];
    if ($to === 'granted') {
        // Prefer the platform's own expiry when it sends one — a reply carries
        // expiration_timestamp whenever the grant is not permanent. Falling back to
        // our pilot window only when it tells us nothing keeps the CRM from
        // claiming permission lasts longer than WhatsApp actually allows.
        $given = ($expiresAt !== null) ? (int)$expiresAt : 0;
        $change['expires_at'] = ($given > $now) ? $given : ($now + WA_CALL_GRANT_TTL);
    }
    return $change;
}

/**
 * The button the rep should see. Pure, so every state is asserted in tests
 * rather than eyeballed in a browser.
 *
 * @return array {label, enabled, action, hint}
 *   action: 'call' | 'request' | 'none'
 */
function wa_call_button($derived, $throttle, $unavailableReason = '') {
    if ($unavailableReason !== '') {
        return ['label' => 'Calling unavailable', 'enabled' => false,
                'action' => 'none', 'hint' => $unavailableReason];
    }
    $state = $derived['state'];

    if ($state === 'granted' && !empty($derived['callable_now'])) {
        return ['label' => 'Call now', 'enabled' => true, 'action' => 'call', 'hint' => ''];
    }
    if ($state === 'window_closed') {
        return ['label' => 'Calling window closed', 'enabled' => false, 'action' => 'none',
                'hint' => 'Permission was granted more than 24 hours ago.'];
    }
    if ($state === 'pending') {
        return ['label' => 'Permission requested', 'enabled' => false, 'action' => 'none',
                'hint' => 'Waiting for the customer to respond.'];
    }
    if ($state === 'rejected' && empty($throttle['allowed'])) {
        return ['label' => 'Permission declined', 'enabled' => false, 'action' => 'none',
                'hint' => $throttle['reason'] ?? ''];
    }

    $label = ($state === 'unknown') ? 'Request call permission' : 'Request permission again';
    if (!empty($throttle['allowed'])) {
        return ['label' => $label, 'enabled' => true, 'action' => 'request', 'hint' => ''];
    }
    $hint = (string)($throttle['reason'] ?? '');
    if (!empty($throttle['retry_after'])) {
        $hint .= ' Try again after ' . date('j M Y, H:i', (int)$throttle['retry_after']) . '.';
    }
    return ['label' => $label, 'enabled' => false, 'action' => 'none', 'hint' => trim($hint)];
}

// =====================================================================
// Schema
// =====================================================================

function wa_call_permission_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wa_call_permissions` (
        `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `contact_id`         INT UNSIGNED NOT NULL,
        `business_phone_id`  VARCHAR(32) NOT NULL,
        `status`             ENUM('unknown','pending','granted','rejected','revoked')
                             NOT NULL DEFAULT 'unknown',
        `request_channel`    ENUM('template') NULL DEFAULT NULL,
        `request_message_id` VARCHAR(128) NULL DEFAULT NULL,
        `requested_at`       DATETIME NULL DEFAULT NULL,
        `responded_at`       DATETIME NULL DEFAULT NULL,
        `expires_at`         DATETIME NULL DEFAULT NULL,
        `requested_by`       INT UNSIGNED NULL DEFAULT NULL,
        `last_error`         VARCHAR(255) NULL DEFAULT NULL,
        `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_call_perm` (`contact_id`, `business_phone_id`),
        KEY `idx_call_perm_msg` (`request_message_id`),
        KEY `idx_call_perm_state` (`status`, `expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wa_call_permission_events` (
        `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `contact_id`        INT UNSIGNED NOT NULL,
        `business_phone_id` VARCHAR(32) NOT NULL,
        `waba_id`           VARCHAR(32) NULL DEFAULT NULL,
        `event`             VARCHAR(24) NOT NULL,
        `status_from`       VARCHAR(16) NULL DEFAULT NULL,
        `status_to`         VARCHAR(16) NULL DEFAULT NULL,
        `source`            ENUM('crm','webhook','api') NOT NULL,
        `actor_id`          INT UNSIGNED NULL DEFAULT NULL,
        `detail`            VARCHAR(255) NULL DEFAULT NULL,
        `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_call_evt` (`contact_id`, `business_phone_id`, `created_at`),
        KEY `idx_call_evt_thr` (`contact_id`, `business_phone_id`, `event`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// =====================================================================
// Database access
// =====================================================================

/**
 * Every statement below is prepared and parameterised. The rest of the module
 * predates that convention and interpolates escaped strings; this file does not
 * follow it, because the values here arrive from an external webhook and from
 * form input, and a prepared statement removes the question entirely rather than
 * relying on each call site remembering to escape.
 */

/** Prepare, bind, execute. Returns the statement, or null on failure. */
function wa_call_stmt($conn, $sql, $types = '', $params = []) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('[wa-call] prepare failed: ' . mysqli_error($conn));
        return null;
    }
    if ($types !== '') { mysqli_stmt_bind_param($stmt, $types, ...$params); }
    if (!mysqli_stmt_execute($stmt)) {
        error_log('[wa-call] execute failed: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return null;
    }
    return $stmt;
}

/** Run a parameterised statement for its effect. */
function wa_call_exec($conn, $sql, $types = '', $params = []) {
    $stmt = wa_call_stmt($conn, $sql, $types, $params);
    if (!$stmt) { return false; }
    mysqli_stmt_close($stmt);
    return true;
}

/** First row of a parameterised query, or null. */
function wa_call_fetch($conn, $sql, $types = '', $params = []) {
    $stmt = wa_call_stmt($conn, $sql, $types, $params);
    if (!$stmt) { return null; }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/** The permission row for a contact on a business number, or null. */
function wa_call_row($conn, $contactId, $phoneId = null) {
    wa_call_permission_schema_ensure($conn);
    return wa_call_fetch($conn,
        "SELECT * FROM wa_call_permissions WHERE contact_id = ? AND business_phone_id = ? LIMIT 1",
        'is', [(int)$contactId, (string)($phoneId ?? WA_CALL_PHONE_ID)]);
}

/** Successful requests inside the throttle window: {count, oldest}. */
function wa_call_request_stats($conn, $contactId, $phoneId = null) {
    wa_call_permission_schema_ensure($conn);
    $r = wa_call_fetch($conn,
        "SELECT COUNT(*) AS n, MIN(created_at) AS oldest
           FROM wa_call_permission_events
          WHERE contact_id = ? AND business_phone_id = ?
            AND event = 'requested'
            AND created_at > (NOW() - INTERVAL ? DAY)",
        'isi', [(int)$contactId, (string)($phoneId ?? WA_CALL_PHONE_ID), (int)WA_CALL_THROTTLE_DAYS]);
    return ['count' => (int)($r['n'] ?? 0), 'oldest' => wa_call_ts($r['oldest'] ?? null)];
}

/** Everything the UI needs for one contact: row, derived state, throttle, button. */
function wa_call_status($conn, $contactId, $phoneId = null) {
    $now = time();
    $row = wa_call_row($conn, $contactId, $phoneId);
    $der = wa_call_derive_state($row, $now);
    $st  = wa_call_request_stats($conn, $contactId, $phoneId);
    $thr = wa_call_throttle_check($der['state'], $st['count'], $st['oldest'], $now);
    $btn = wa_call_button($der, $thr, wa_call_unavailable_reason());
    return ['row' => $row, 'derived' => $der, 'throttle' => $thr, 'button' => $btn,
            'requests_in_window' => $st['count']];
}

/** Append one event row. Callers decide WHETHER to append; this only writes. */
function wa_call_event_log($conn, $contactId, $phoneId, $event, $from, $to, $source, $actorId = null, $detail = null, $wabaId = null) {
    wa_call_permission_schema_ensure($conn);
    return wa_call_exec($conn,
        "INSERT INTO wa_call_permission_events
             (contact_id, business_phone_id, waba_id, event, status_from, status_to, source, actor_id, detail)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        'issssssis',
        [(int)$contactId, (string)$phoneId, $wabaId, (string)$event, $from, $to,
         (string)$source, $actorId === null ? null : (int)$actorId, $detail]);
}

/**
 * Make sure a row exists, then lock it FOR UPDATE and return it.
 * MUST be called inside a transaction. The INSERT IGNORE is what guarantees there
 * is something to lock: SELECT ... FOR UPDATE on a missing row locks nothing, and
 * two concurrent clicks would both proceed.
 */
function wa_call_lock_row($conn, $contactId, $phoneId) {
    wa_call_permission_schema_ensure($conn);
    wa_call_exec($conn,
        "INSERT IGNORE INTO wa_call_permissions (contact_id, business_phone_id, status)
         VALUES (?, ?, 'unknown')",
        'is', [(int)$contactId, (string)$phoneId]);
    return wa_call_fetch($conn,
        "SELECT * FROM wa_call_permissions
          WHERE contact_id = ? AND business_phone_id = ? LIMIT 1 FOR UPDATE",
        'is', [(int)$contactId, (string)$phoneId]);
}

/**
 * PHASE A of a request: atomically lease the right to send.
 *
 * Sets status='pending' under a row lock so a double click cannot produce two
 * prompts to the customer, then COMMITS. The lock is released before the caller
 * touches the network: holding a row lock across an external API call would pin
 * an InnoDB row for the length of a 25-second timeout.
 *
 * Deliberately does NOT log a 'requested' event — the throttle counts successful
 * submissions only, and nothing has been submitted yet.
 *
 * @return array {ok:bool, reason:string, previous:string}
 */
function wa_call_claim_request($conn, $contactId, $staffId, $phoneId = null) {
    $pid = (string)($phoneId ?? WA_CALL_PHONE_ID);
    $cid = (int)$contactId;
    $now = time();

    mysqli_begin_transaction($conn);
    try {
        $row = wa_call_lock_row($conn, $cid, $pid);
        if (!$row) {
            mysqli_rollback($conn);
            return ['ok' => false, 'reason' => 'Could not lock permission state.', 'previous' => ''];
        }

        $der = wa_call_derive_state($row, $now);
        $st  = wa_call_request_stats($conn, $cid, $pid);
        $thr = wa_call_throttle_check($der['state'], $st['count'], $st['oldest'], $now);
        if (empty($thr['allowed'])) {
            mysqli_rollback($conn);
            return ['ok' => false, 'reason' => (string)$thr['reason'], 'previous' => (string)$row['status']];
        }

        $prev = (string)$row['status'];
        $ok = wa_call_exec($conn,
            "UPDATE wa_call_permissions
                SET status = 'pending', request_channel = 'template',
                    requested_at = NOW(), requested_by = ?,
                    responded_at = NULL, expires_at = NULL,
                    request_message_id = NULL, last_error = NULL
              WHERE contact_id = ? AND business_phone_id = ?",
            'iis', [$staffId === null ? null : (int)$staffId, $cid, $pid]);
        if (!$ok) {
            mysqli_rollback($conn);
            return ['ok' => false, 'reason' => 'Could not start the request.', 'previous' => $prev];
        }
        mysqli_commit($conn);
        return ['ok' => true, 'reason' => '', 'previous' => $prev];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        error_log('[wa-call] claim failed: ' . $e->getMessage());
        return ['ok' => false, 'reason' => 'Could not start the request.', 'previous' => ''];
    }
}

/** PHASE B success: record the sent message id and consume a throttle slot. */
function wa_call_confirm_request($conn, $contactId, $staffId, $messageId, $phoneId = null) {
    $pid = (string)($phoneId ?? WA_CALL_PHONE_ID);
    $cid = (int)$contactId;
    mysqli_begin_transaction($conn);
    try {
        wa_call_lock_row($conn, $cid, $pid);
        wa_call_exec($conn,
            "UPDATE wa_call_permissions SET request_message_id = ?
              WHERE contact_id = ? AND business_phone_id = ?",
            'sis', [$messageId !== '' ? (string)$messageId : null, $cid, $pid]);
        // Only NOW does the attempt count against the customer's limit.
        wa_call_event_log($conn, $cid, $pid, 'requested', null, 'pending', 'crm', $staffId, 'template');
        mysqli_commit($conn);
        return true;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        error_log('[wa-call] confirm failed: ' . $e->getMessage());
        return false;
    }
}

/** PHASE B failure: release the lease and record why. No throttle slot consumed. */
function wa_call_fail_request($conn, $contactId, $staffId, $previous, $error, $phoneId = null) {
    $pid  = (string)($phoneId ?? WA_CALL_PHONE_ID);
    $cid  = (int)$contactId;
    $prev = in_array($previous, ['unknown', 'pending', 'granted', 'rejected', 'revoked'], true)
            ? $previous : 'unknown';
    $msg  = mb_substr(wa_call_scrub((string)$error), 0, 255);
    mysqli_begin_transaction($conn);
    try {
        wa_call_lock_row($conn, $cid, $pid);
        wa_call_exec($conn,
            "UPDATE wa_call_permissions
                SET status = ?, requested_at = NULL, request_channel = NULL, last_error = ?
              WHERE contact_id = ? AND business_phone_id = ?",
            'ssis', [$prev, $msg, $cid, $pid]);
        wa_call_event_log($conn, $cid, $pid, 'error', 'pending', $prev, 'crm', $staffId, $msg);
        mysqli_commit($conn);
        return true;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        error_log('[wa-call] fail-revert failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Apply an inbound webhook status under a row lock.
 *
 * Returns 'applied' | 'duplicate' | 'ignored' | 'error'.
 *   duplicate — a 360dialog retry. Nothing written, no event, expiry untouched.
 *   error     — transient/database failure. The caller answers HTTP 500 so the
 *               platform retries; anything else would silently lose the event.
 */
function wa_call_apply_webhook($conn, $contactId, $status, $wabaId = null, $phoneId = null, $expiresAt = null) {
    $pid = (string)($phoneId ?? WA_CALL_PHONE_ID);
    $cid = (int)$contactId;
    $now = time();

    if (wa_call_map_status($status) === '') { return 'ignored'; }

    mysqli_begin_transaction($conn);
    try {
        $row = wa_call_lock_row($conn, $cid, $pid);
        if (!$row) { mysqli_rollback($conn); return 'error'; }

        $from   = (string)$row['status'];
        $change = wa_call_transition($from, $status, $now, $expiresAt);
        if ($change === null) { mysqli_rollback($conn); return 'duplicate'; }

        $ok = wa_call_exec($conn,
            "UPDATE wa_call_permissions
                SET status = ?, responded_at = ?, expires_at = ?, last_error = NULL
              WHERE contact_id = ? AND business_phone_id = ?",
            'sssis', [
                $change['status'],
                date('Y-m-d H:i:s', (int)$change['responded_at']),
                $change['expires_at'] === null ? null : date('Y-m-d H:i:s', (int)$change['expires_at']),
                $cid, $pid,
            ]);
        if (!$ok) { mysqli_rollback($conn); return 'error'; }

        wa_call_event_log($conn, $cid, $pid, $change['status'], $from, $change['status'],
                          'webhook', null, null, $wabaId);
        mysqli_commit($conn);
        return 'applied';
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        error_log('[wa-call] webhook apply failed: ' . $e->getMessage());
        return 'error';
    }
}
