<?php
/**
 * WhatsApp helpers — procedural mysqli, matching the ERP page style
 * (mysqli_query($conn, ...), mysqli_real_escape_string, mysqli_fetch_assoc).
 *
 * Every function takes the live mysqli $conn:
 *   - admin pages / wa_process.php : $conn from auth.php (via header.php)
 *   - wa_webhook.php               : $wa_conn from wa_db.php (no session)
 *
 * Routing model (same as the ERP's own auth.php):
 *   course.assigned_to / Event.assigned_to hold registered_users.id (CSV)
 *   -> FIND_IN_SET(registered_users.id, assigned_to) -> owner (fullname/email)
 *   -> enrich from staff (system_user_id = registered_users.id) when present.
 */

if (!function_exists('wa_e')) {
    function wa_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Defensive defaults so an older/partial wa_config.php can never fatal the app.
// (wa_config.php is loaded before this file on every entry point; these only
//  fill in constants the config didn't define.)
foreach ([
    'WA_VERIFY_TOKEN'      => '',
    'WA_DEFAULT_PROVIDER'  => 'claude',
    'WA_ROLE'              => 44,
    'WA_PHONE'             => '',
    'WA_SITE_URL'          => 'https://vantageafricaleaders.com',
    'WA_DIALOG_URL'        => 'https://waba-v2.360dialog.io',
    'WA_DIALOG_KEY'        => '',
    'WA_OPENAI_KEY'        => 'YOUR_OPENAI_API_KEY',
    'WA_OPENAI_MODEL'      => 'gpt-4o-mini',
    'WA_OPENAI_URL'        => 'https://api.openai.com',
    'WA_ANTHROPIC_KEY'     => 'YOUR_ANTHROPIC_API_KEY',
    'WA_ANTHROPIC_MODEL'   => 'claude-haiku-4-5-20251001',   // fast — important for the webhook window
    'WA_ANTHROPIC_URL'     => 'https://api.anthropic.com',
    'WA_ANTHROPIC_VERSION' => '2023-06-01',
] as $waK => $waV) {
    if (!defined($waK)) { define($waK, $waV); }
}

/** Quote a value for SQL, or return the literal NULL when it is null. */
function wa_sql($conn, $v) {
    return $v === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, (string)$v) . "'";
}

// =====================================================================
// Contacts + messages
// =====================================================================

/**
 * International dialling code -> country. Longest match wins, so 27 (South Africa)
 * never steals a 254/265 number and 234 is not read as 23. Africa in full since
 * that is where the enquiries come from, plus the other codes seen in the inbox.
 */
function wa_dial_codes() {
    static $m = null;
    if ($m !== null) { return $m; }
    return $m = [
        // --- Africa ---
        '20'=>'Egypt', '211'=>'South Sudan', '212'=>'Morocco', '213'=>'Algeria',
        '216'=>'Tunisia', '218'=>'Libya', '220'=>'Gambia', '221'=>'Senegal',
        '222'=>'Mauritania', '223'=>'Mali', '224'=>'Guinea', '225'=>"Cote d'Ivoire",
        '226'=>'Burkina Faso', '227'=>'Niger', '228'=>'Togo', '229'=>'Benin',
        '230'=>'Mauritius', '231'=>'Liberia', '232'=>'Sierra Leone', '233'=>'Ghana',
        '234'=>'Nigeria', '235'=>'Chad', '236'=>'Central African Republic',
        '237'=>'Cameroon', '238'=>'Cape Verde', '239'=>'Sao Tome and Principe',
        '240'=>'Equatorial Guinea', '241'=>'Gabon', '242'=>'Congo',
        '243'=>'DR Congo', '244'=>'Angola', '245'=>'Guinea-Bissau',
        '248'=>'Seychelles', '249'=>'Sudan', '250'=>'Rwanda', '251'=>'Ethiopia',
        '252'=>'Somalia', '253'=>'Djibouti', '254'=>'Kenya', '255'=>'Tanzania',
        '256'=>'Uganda', '257'=>'Burundi', '258'=>'Mozambique', '260'=>'Zambia',
        '261'=>'Madagascar', '262'=>'Reunion', '263'=>'Zimbabwe', '264'=>'Namibia',
        '265'=>'Malawi', '266'=>'Lesotho', '267'=>'Botswana', '268'=>'Eswatini',
        '269'=>'Comoros', '27'=>'South Africa', '291'=>'Eritrea',
        // --- elsewhere, as seen in the inbox ---
        '1'=>'United States/Canada', '44'=>'United Kingdom', '353'=>'Ireland',
        '61'=>'Australia', '64'=>'New Zealand', '65'=>'Singapore', '60'=>'Malaysia',
        '90'=>'Turkey', '91'=>'India', '92'=>'Pakistan', '880'=>'Bangladesh',
        '94'=>'Sri Lanka', '63'=>'Philippines', '971'=>'United Arab Emirates',
        '966'=>'Saudi Arabia', '974'=>'Qatar', '968'=>'Oman', '973'=>'Bahrain',
        '965'=>'Kuwait', '962'=>'Jordan', '961'=>'Lebanon', '86'=>'China',
        '49'=>'Germany', '33'=>'France', '39'=>'Italy', '34'=>'Spain',
        '31'=>'Netherlands', '46'=>'Sweden', '47'=>'Norway', '45'=>'Denmark',
        '7'=>'Russia/Kazakhstan', '55'=>'Brazil', '52'=>'Mexico',
        '592'=>'Guyana', '1868'=>'Trinidad and Tobago', '1876'=>'Jamaica',
        '1246'=>'Barbados', '675'=>'Papua New Guinea', '679'=>'Fiji',
        '977'=>'Nepal', '95'=>'Myanmar', '855'=>'Cambodia', '84'=>'Vietnam',
    ];
}

/**
 * Country for a WhatsApp id (which is the full international number, digits only).
 * Returns ['code' => '254', 'country' => 'Kenya'], or empty strings when unknown.
 */
function wa_country_from_wa_id($waId) {
    $d = preg_replace('/\D/', '', (string)$waId);
    if ($d === '') { return ['code' => '', 'country' => '']; }
    $map = wa_dial_codes();
    // Longest prefix first: 1868 (Trinidad) must beat 1 (US), 254 must beat 25.
    for ($len = 4; $len >= 1; $len--) {
        $p = substr($d, 0, $len);
        if ($p !== false && isset($map[$p])) {
            return ['code' => $p, 'country' => $map[$p]];
        }
    }
    return ['code' => '', 'country' => ''];
}

/** Add the contact country columns once (idempotent). */
function wa_contact_country_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_contacts`
        ADD COLUMN IF NOT EXISTS `country` VARCHAR(64) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `dial_code` VARCHAR(8) DEFAULT NULL");
}

function wa_upsert_contact($conn, $waId, $name = null) {
    wa_contact_country_schema_ensure($conn);
    $wa   = "'" . mysqli_real_escape_string($conn, $waId) . "'";
    $nm   = wa_sql($conn, $name);
    // The wa_id IS the full international number, so we know the country from the
    // first message — no need to ask. COALESCE keeps a value a human corrected.
    $loc  = wa_country_from_wa_id($waId);
    $co   = wa_sql($conn, $loc['country'] !== '' ? $loc['country'] : null);
    $dc   = wa_sql($conn, $loc['code'] !== '' ? $loc['code'] : null);
    mysqli_query($conn,
        "INSERT INTO wa_contacts (wa_id, profile_name, country, dial_code) VALUES ($wa, $nm, $co, $dc)
         ON DUPLICATE KEY UPDATE
             profile_name = COALESCE($nm, profile_name),
             country      = COALESCE(country, $co),
             dial_code    = COALESCE(dial_code, $dc),
             id = LAST_INSERT_ID(id)");
    return (int)mysqli_insert_id($conn);
}

function wa_touch_last_inbound($conn, $contactId, $dt) {
    $contactId = (int)$contactId;
    $dt = "'" . mysqli_real_escape_string($conn, $dt) . "'";
    mysqli_query($conn, "UPDATE wa_contacts SET last_inbound_at = $dt WHERE id = $contactId");
    // The customer answered, so the nudge did its job: clear the stamp and let ONE
    // more follow-up become possible if they go quiet again. Without this the stamp
    // is permanent and a contact can only ever be nudged once in their lifetime.
    // Every inbound message funnels through here, so this is the one place to do it.
    @mysqli_query($conn, "UPDATE wa_conversations SET followup_sent_at = NULL
                           WHERE contact_id = $contactId AND followup_sent_at IS NOT NULL");
}

function wa_find_contact_by_waid($conn, $waId) {
    $wa = "'" . mysqli_real_escape_string($conn, $waId) . "'";
    $res = mysqli_query($conn, "SELECT * FROM wa_contacts WHERE wa_id = $wa LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ?: null;
}

/**
 * Use Africa/Nairobi (EAT, UTC+3) for BOTH PHP date() and the MySQL connection. This aligns
 * inbound timestamps (stored via PHP date() from the WhatsApp epoch) with outbound ones
 * (MySQL NOW()) so they no longer differ by hours, and makes every displayed time local.
 * Call at the top of every WhatsApp entry point (webhook, cron, inbox, thread, api).
 */
function wa_use_nairobi_time($conn = null) {
    @date_default_timezone_set('Africa/Nairobi');
    if ($conn) { @mysqli_query($conn, "SET time_zone = '+03:00'"); }
}

/**
 * Handle STOP / START keywords in an inbound message (WhatsApp opt-out compliance).
 * On a match it updates the contact, sends a confirmation, and returns the action
 * taken ('optout' | 'optin') so the caller can skip the normal AI auto-reply.
 * Returns '' when the message isn't an opt-out/opt-in command.
 */
function wa_handle_optout($conn, $contactId, $waId, $body) {
    // Normalise: lowercase, strip surrounding punctuation/whitespace.
    $t = strtolower(trim((string)$body));
    $t = trim(preg_replace('/[^a-z\s]/', '', $t));
    if ($t === '') { return ''; }

    // Deliberately NOT including bare "yes"/"no" — too common as real replies.
    $stop  = ['stop', 'unsubscribe', 'unsub', 'cancel', 'optout', 'opt out', 'stop promotions', 'quit'];
    $start = ['start', 'subscribe', 'unstop', 'optin', 'opt in', 'resume'];

    // Match the whole message (exact) to avoid false positives inside a sentence.
    if (in_array($t, $stop, true)) {
        $cid = (int)$contactId;
        mysqli_query($conn, "UPDATE wa_contacts SET opted_out = 1, opted_out_at = NOW(), opted_in = 0 WHERE id = $cid");
        wa_send_text($conn, $waId, "You've been unsubscribed and won't receive further broadcasts. Reply START at any time to opt back in.", true);
        return 'optout';
    }
    if (in_array($t, $start, true)) {
        $cid = (int)$contactId;
        mysqli_query($conn, "UPDATE wa_contacts SET opted_out = 0, opted_out_at = NULL, opted_in = 1 WHERE id = $cid");
        wa_send_text($conn, $waId, "You're subscribed again — thanks! Reply STOP anytime to unsubscribe.", true);
        return 'optin';
    }
    return '';
}

/** Manually set a contact's opt-out flag (supervisor override from the Contacts page). */
function wa_contact_set_optout($conn, $contactId, $optOut) {
    $cid = (int)$contactId;
    if ($cid <= 0) { return; }
    if ($optOut) {
        mysqli_query($conn, "UPDATE wa_contacts SET opted_out = 1, opted_out_at = NOW(), opted_in = 0 WHERE id = $cid");
    } else {
        mysqli_query($conn, "UPDATE wa_contacts SET opted_out = 0, opted_out_at = NULL, opted_in = 1 WHERE id = $cid");
    }
}

/**
 * Contacts for the management view, with their conversation id (for the chat
 * link), message count and linked course/event. $filter = all|optedin|optedout.
 */
function wa_contacts_list($conn, $search = '', $filter = 'all', $limit = 300) {
    $limit = (int)$limit;
    $where = '1=1';
    if ($filter === 'optedin')      { $where .= ' AND c.opted_in = 1 AND c.opted_out = 0'; }
    elseif ($filter === 'optedout') { $where .= ' AND c.opted_out = 1'; }
    if (($search = trim((string)$search)) !== '') {
        $s = mysqli_real_escape_string($conn, $search);
        $where .= " AND (c.wa_id LIKE '%$s%' OR c.profile_name LIKE '%$s%')";
    }
    $sql = "
        SELECT c.id, c.wa_id, c.profile_name, c.opted_in, c.opted_out, c.opted_out_at,
               c.last_inbound_at, c.created_at,
               cv.id AS conv_id,
               (SELECT COUNT(*) FROM wa_messages m WHERE m.contact_id = c.id) AS msg_count,
               CASE cv.ref_type
                    WHEN 'course' THEN (SELECT course FROM course WHERE course_id = cv.ref_id)
                    WHEN 'event'  THEN (SELECT event_title FROM `Event` WHERE event_id = cv.ref_id)
               END AS ref_name
          FROM wa_contacts c
     LEFT JOIN wa_conversations cv ON cv.contact_id = c.id
         WHERE $where
      ORDER BY (c.last_inbound_at IS NULL), c.last_inbound_at DESC, c.id DESC
         LIMIT $limit";
    $res = mysqli_query($conn, $sql);
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

/** Totals for the Contacts filter chips: ['all'=>, 'optedin'=>, 'optedout'=>]. */
function wa_contacts_counts($conn) {
    $res = mysqli_query($conn,
        "SELECT COUNT(*) AS all_ct,
                SUM(opted_out = 1) AS out_ct,
                SUM(opted_in = 1 AND opted_out = 0) AS in_ct
           FROM wa_contacts");
    $r = $res ? mysqli_fetch_assoc($res) : null;
    return [
        'all'      => (int)($r['all_ct'] ?? 0),
        'optedin'  => (int)($r['in_ct'] ?? 0),
        'optedout' => (int)($r['out_ct'] ?? 0),
    ];
}

// =====================================================================
// Insights / analytics
// =====================================================================

/** One-value helper for the KPI queries. */
function wa_scalar($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    $row = $res ? mysqli_fetch_row($res) : null;
    return $row ? (int)$row[0] : 0;
}

/**
 * First column of the first row as a STRING ('' when there is no row).
 *
 * wa_scalar() int-casts, so using it for a title or a name silently yields 0 —
 * a trap that has already cost us an event-title lookup and a staff note reading
 * "this is now 0". Use this for anything textual.
 */
function wa_scalar_str($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    $row = $res ? mysqli_fetch_row($res) : null;
    return ($row && $row[0] !== null) ? (string)$row[0] : '';
}

/** Headline KPIs for the Insights page. $days scopes the message/broadcast window. */
function wa_insights_summary($conn, $days = 30) {
    $days = (int)$days;
    $since = "DATE_SUB(NOW(), INTERVAL $days DAY)";
    $c = wa_contacts_counts($conn);
    return [
        'contacts'     => $c['all'],
        'opted_in'     => $c['optedin'],
        'opted_out'    => $c['optedout'],
        'conversations'=> wa_scalar($conn, "SELECT COUNT(*) FROM wa_conversations"),
        'escalated'    => wa_scalar($conn, "SELECT COUNT(*) FROM wa_conversations WHERE escalated = 1"),
        'ai_handled'   => wa_scalar($conn, "SELECT COUNT(*) FROM wa_conversations WHERE handler = 'ai'"),
        'human_handled'=> wa_scalar($conn, "SELECT COUNT(*) FROM wa_conversations WHERE handler = 'human'"),
        'in_msgs'      => wa_scalar($conn, "SELECT COUNT(*) FROM wa_messages WHERE direction = 'inbound'  AND created_at >= $since"),
        'out_msgs'     => wa_scalar($conn, "SELECT COUNT(*) FROM wa_messages WHERE direction = 'outbound' AND created_at >= $since"),
        'bcast_runs'   => wa_scalar($conn, "SELECT COUNT(*) FROM wa_broadcasts WHERE created_at >= $since"),
        'bcast_sent'   => wa_scalar($conn, "SELECT COUNT(*) FROM wa_messages WHERE broadcast_id IS NOT NULL AND created_at >= $since"),
        'bcast_reached'=> wa_scalar($conn, "SELECT COUNT(*) FROM wa_messages WHERE broadcast_id IS NOT NULL AND status IN ('delivered','read') AND created_at >= $since"),
        'bcast_read'   => wa_scalar($conn, "SELECT COUNT(*) FROM wa_messages WHERE broadcast_id IS NOT NULL AND status = 'read' AND created_at >= $since"),
        'bcast_failed' => wa_scalar($conn, "SELECT COUNT(*) FROM wa_messages WHERE broadcast_id IS NOT NULL AND status = 'failed' AND created_at >= $since"),
    ];
}

/** Inbound/outbound message counts per day for the last $days (oldest first, gaps filled). */
function wa_insights_daily($conn, $days = 14) {
    $days = (int)$days;
    $res = mysqli_query($conn,
        "SELECT DATE(created_at) AS d,
                SUM(direction = 'inbound')  AS ins,
                SUM(direction = 'outbound') AS outs
           FROM wa_messages
          WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL " . ($days - 1) . " DAY)
          GROUP BY DATE(created_at)");
    $byDay = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $byDay[$r['d']] = ['in' => (int)$r['ins'], 'out' => (int)$r['outs']]; } }
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i day"));
        $out[] = ['date' => $day, 'in' => $byDay[$day]['in'] ?? 0, 'out' => $byDay[$day]['out'] ?? 0];
    }
    return $out;
}

/** Busiest courses AND onsite events by conversation volume. */
function wa_insights_top_courses($conn, $limit = 8) {
    $limit = (int)$limit;
    $res = mysqli_query($conn,
        "SELECT cv.ref_type,
                CASE cv.ref_type
                     WHEN 'course' THEN (SELECT course FROM course WHERE course_id = cv.ref_id)
                     WHEN 'event'  THEN (SELECT event_title FROM `Event` WHERE event_id = cv.ref_id)
                END AS name,
                COUNT(*) AS ct
           FROM wa_conversations cv
          WHERE cv.ref_type IN ('course','event') AND cv.ref_id IS NOT NULL
          GROUP BY cv.ref_type, cv.ref_id
          ORDER BY ct DESC
          LIMIT $limit");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { if ($r['name']) { $rows[] = $r; } } }
    return $rows;
}

/** Insert inbound message; de-dups on wa_message_id. Returns true if newly inserted. */
function wa_save_inbound($conn, $contactId, $m) {
    if (function_exists('wa_channel_schema_ensure')) { wa_channel_schema_ensure($conn); }
    $contactId = (int)$contactId;
    $wamid = wa_sql($conn, $m['wa_message_id'] ?? null);
    $type  = wa_sql($conn, $m['type'] ?? 'text');
    $body  = wa_sql($conn, $m['body'] ?? null);
    $mid   = wa_sql($conn, $m['media_id'] ?? null);
    $mime  = wa_sql($conn, $m['media_mime'] ?? null);
    $adid  = wa_sql($conn, $m['referral_ad_id'] ?? null);
    $chan  = wa_sql($conn, $m['channel'] ?? null);
    $ts    = wa_sql($conn, $m['wa_timestamp'] ?? null);
    $raw   = wa_sql($conn, isset($m['raw_payload']) ? json_encode($m['raw_payload'], JSON_UNESCAPED_UNICODE) : null);
    mysqli_query($conn,
        "INSERT IGNORE INTO wa_messages
            (wa_message_id, contact_id, direction, type, body, media_id, media_mime,
             referral_ad_id, wa_timestamp, raw_payload, channel)
         VALUES ($wamid, $contactId, 'inbound', $type, $body, $mid, $mime, $adid, $ts, $raw, $chan)");
    return mysqli_affected_rows($conn) > 0;
}

function wa_save_outbound($conn, $contactId, $m) {
    wa_message_flags_ensure($conn);
    if (function_exists('wa_channel_schema_ensure')) { wa_channel_schema_ensure($conn); }
    $contactId = (int)$contactId;
    $wamid  = wa_sql($conn, $m['wa_message_id'] ?? null);
    $type   = wa_sql($conn, $m['type'] ?? 'text');
    $body   = wa_sql($conn, $m['body'] ?? null);
    $mid    = wa_sql($conn, $m['media_id'] ?? null);
    $mime   = wa_sql($conn, $m['media_mime'] ?? null);
    $status = wa_sql($conn, $m['status'] ?? 'sent');
    $chan   = wa_sql($conn, $m['channel'] ?? null);
    $raw    = wa_sql($conn, isset($m['raw_payload']) ? json_encode($m['raw_payload'], JSON_UNESCAPED_UNICODE) : null);
    // A human agent's reply sets $GLOBALS['WA_SENT_BY_STAFF'] just before sending; every
    // other send (AI answer, follow-up, payment confirm, broadcast) leaves it unset = AI.
    $sentBy = (isset($GLOBALS['WA_SENT_BY_STAFF']) && (int)$GLOBALS['WA_SENT_BY_STAFF'] > 0)
        ? (int)$GLOBALS['WA_SENT_BY_STAFF'] : 'NULL';
    // A broadcast send sets $GLOBALS['WA_BROADCAST_ID'] so EVERY message it produces —
    // sent OR failed — is tagged to the run. Without this, failed sends (which have no
    // wa_message_id to tag by) were never linked, so the delivery report showed "Failed 0"
    // and hid that the whole broadcast bounced.
    $bcast = (isset($GLOBALS['WA_BROADCAST_ID']) && (int)$GLOBALS['WA_BROADCAST_ID'] > 0)
        ? (int)$GLOBALS['WA_BROADCAST_ID'] : 'NULL';
    // Stamp wa_timestamp = NOW() on every outbound. Without it the column is NULL, and the
    // unanswered-sweeper's "is there a reply after the customer's message?" test
    // (wa_timestamp >= last_inbound_at) can never see the reply — so it re-sends the same
    // answer every minute. NOW() is server time, always >= the inbound timestamp.
    mysqli_query($conn,
        "INSERT INTO wa_messages (wa_message_id, contact_id, direction, type, body, media_id, media_mime, status, raw_payload, sent_by_staff, wa_timestamp, broadcast_id, channel)
         VALUES ($wamid, $contactId, 'outbound', $type, $body, $mid, $mime, $status, $raw, $sentBy, NOW(), $bcast, $chan)");
    return (int)mysqli_insert_id($conn);
}

/**
 * Apply a delivery-status callback (sent -> delivered -> read, or failed) to the
 * outbound message it references. Only advances the status — a late 'sent' never
 * overwrites a 'read'. 'failed' always wins. Matches on wa_message_id.
 */
function wa_apply_status($conn, $wamid, $status) {
    $wamid  = trim((string)$wamid);
    $status = strtolower(trim((string)$status));
    if ($wamid === '' || $status === '') { return; }
    $rank = ['sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 9];
    if (!isset($rank[$status])) { return; }
    $newRank = $rank[$status];
    $w = "'" . mysqli_real_escape_string($conn, $wamid) . "'";
    $s = "'" . mysqli_real_escape_string($conn, $status) . "'";
    // Build a CASE that only moves forward in rank (or is currently NULL).
    $curRankSql = "CASE `status` WHEN 'sent' THEN 1 WHEN 'delivered' THEN 2 WHEN 'read' THEN 3 WHEN 'failed' THEN 9 ELSE 0 END";
    mysqli_query($conn,
        "UPDATE wa_messages SET `status` = $s
          WHERE wa_message_id = $w AND $curRankSql < $newRank");
}

/** Oldest-first message thread for a contact. */
/** Idempotently ensure the KB-edit audit table (#16 — who changed what, when). */
function wa_kb_audit_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wa_kb_audit` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `ref_type` VARCHAR(16) NOT NULL,
        `ref_id` INT UNSIGNED NOT NULL,
        `changed_by` INT UNSIGNED DEFAULT NULL,
        `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `body_len` INT UNSIGNED DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_kb_audit_ref` (`ref_type`, `ref_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Record a knowledge-base edit (who/when/which entry). */
function wa_kb_audit_log($conn, $refType, $refId, $userId, $bodyLen) {
    wa_kb_audit_ensure($conn);
    $rt  = wa_sql($conn, (string)$refType);
    $rid = (int)$refId;
    $uid = $userId !== null ? (int)$userId : 'NULL';
    $len = (int)$bodyLen;
    mysqli_query($conn, "INSERT INTO wa_kb_audit (ref_type, ref_id, changed_by, body_len) VALUES ($rt, $rid, $uid, $len)");
}

/** The most recent edit (who + when) for a KB entry, or null. */
function wa_kb_last_edit($conn, $refType, $refId) {
    wa_kb_audit_ensure($conn);
    $rt  = wa_sql($conn, (string)$refType);
    $rid = (int)$refId;
    $res = mysqli_query($conn,
        "SELECT a.changed_at, COALESCE(NULLIF(s.full_name,''), ru.fullname) AS who
           FROM wa_kb_audit a
      LEFT JOIN registered_users ru ON ru.id = a.changed_by
      LEFT JOIN staff s             ON s.system_user_id = a.changed_by
          WHERE a.ref_type = $rt AND a.ref_id = $rid
          ORDER BY a.id DESC LIMIT 1");
    return $res ? mysqli_fetch_assoc($res) : null;
}

/** Idempotently add the soft-delete marker used to retract a reply from the CRM (#19). */
function wa_message_flags_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_messages`
        ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL DEFAULT NULL");
    // Which staff member typed this outbound message (NULL = AI / automated).
    @mysqli_query($conn, "ALTER TABLE `wa_messages`
        ADD COLUMN IF NOT EXISTS `sent_by_staff` INT UNSIGNED NULL DEFAULT NULL");
}

function wa_thread($conn, $contactId, $limit = 100) {
    wa_message_flags_ensure($conn);
    $contactId = (int)$contactId;
    $limit = (int)$limit;
    // Resolve who sent each outbound message: a staff name (human agent) or nobody (AI).
    $res = mysqli_query($conn,
        "SELECT m.*, COALESCE(NULLIF(s.full_name,''), ru.fullname) AS sent_by_name
           FROM wa_messages m
      LEFT JOIN registered_users ru ON ru.id = m.sent_by_staff
      LEFT JOIN staff s             ON s.system_user_id = m.sent_by_staff
          WHERE m.contact_id = $contactId ORDER BY m.id DESC LIMIT $limit");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return array_reverse($rows);
}

// =====================================================================
// Owners (the staff assignment model)
// =====================================================================

/** Owners assigned to a course/event. $kind = 'course' | 'event'. */
function wa_owners($conn, $kind, $refId) {
    $refId = (int)$refId;
    if ($kind === 'event') {
        $table = '`Event`'; $idCol = 'event_id';
    } else {
        $table = 'course';  $idCol = 'course_id';
    }
    $res = mysqli_query($conn,
        "SELECT ru.id AS user_id,
                COALESCE(NULLIF(s.full_name,''), ru.fullname)    AS full_name,
                COALESCE(NULLIF(s.corporate_email,''), ru.email) AS email,
                s.job_title
           FROM {$table} x
           JOIN registered_users ru ON FIND_IN_SET(ru.id, x.assigned_to) > 0
      LEFT JOIN staff s ON s.system_user_id = ru.id
          WHERE x.{$idCol} = {$refId}");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

/**
 * First owner's registered_users.id. A manual assignment (wa_course_owner) wins;
 * otherwise the ERP's own assignment (course.assigned_to) applies. Returns null
 * if still unassigned (→ fallback queue, no owner).
 */
function wa_first_owner($conn, $kind, $refId) {
    $ov = wa_owner_override($conn, $kind, (int)$refId);
    if ($ov !== null) { return $ov; }                      // manual override wins
    $owners = wa_owners($conn, $kind, $refId);
    return $owners ? (int)$owners[0]['user_id'] : null;
}

/** Can this staff member see/act on a conversation? Supervisors: any. Everyone else:
 *  chats assigned to them, OR chats for a course/event they are a rep of (primary or
 *  contributor). So reps see their OWN courses' enquiries — not every course's. */
function wa_user_can_see_conv($conn, $conv, $staffId, $isSupervisor) {
    if ($isSupervisor) { return true; }
    if (!$conv) { return false; }
    $sid = (int)$staffId;
    if ((int)($conv['assigned_user_id'] ?? 0) === $sid && $sid > 0) { return true; }
    $rt = $conv['ref_type'] ?? '';
    $rid = (int)($conv['ref_id'] ?? 0);
    if ($rid > 0 && in_array($rt, ['course', 'event'], true)) {
        foreach (wa_owners($conn, $rt, $rid) as $o) { if ((int)$o['user_id'] === $sid) { return true; } }
        if ((int)wa_owner_override($conn, $rt, $rid) === $sid) { return true; }
    }
    // A rep of the chat's training programme. Without this the inbox lists the chat
    // (the scope allows it) but opening it says "not one of your courses".
    $pid = (int)($conv['program_id'] ?? 0);
    if ($pid > 0) {
        $prog = wa_program_get($conn, $pid);
        if ($prog && in_array($sid, wa_program_owner_ids($prog), true)) { return true; }
    }
    // Triage: unowned, unrouted, no programme — belongs to everyone until someone
    // takes it, so any rep may open it. Mirrors wa_triage_sql().
    $owner = $conv['assigned_user_id'] ?? null;
    if (($owner === null || $owner === '') && $pid === 0 && ($rt === 'unknown' || $rid === 0)) {
        return true;
    }
    return false;
}

/** WHERE fragment (incl. leading WHERE) that limits the inbox list to what $staffId may
 *  see under the same rule as wa_user_can_see_conv(). '' for supervisors (see all).
 *  Assumes the wa_conversations alias is 'cv'. */
/**
 * SQL predicate for a TRIAGE chat: nobody can act on it and nobody owns it.
 *
 * No owner, no programme link, and no topic the router could classify — so every
 * ownership rule in wa_inbox_scope_where() fails and the chat is invisible to
 * every rep, supervisors aside. These are the "is it online or in person?",
 * "do you have a class in Abuja?" enquiries that arrive before the bot has
 * worked out what they want. Left alone they are seen by nobody at all.
 *
 * Shared so the inbox page, the live JSON feed and the scope all agree.
 */
/** Shortest inbound message that counts as a real enquiry ("Onsite" = 6, so this
 *  keeps short but meaningful answers while dropping "hi" / "ok" / a stray emoji). */
if (!defined('WA_TRIAGE_MIN_CHARS'))   { define('WA_TRIAGE_MIN_CHARS', 8); }
/** How far back a triage chat stays worth chasing. */
if (!defined('WA_TRIAGE_RECENT_DAYS')) { define('WA_TRIAGE_RECENT_DAYS', 30); }

function wa_triage_sql($a = 'cv') {
    // Only chats worth a human's time. Being unrouted is not enough on its own —
    // most unrouted contacts are stray taps, bare greetings and long-dead numbers,
    // and listing all of them buries the real enquiries. Require evidence the person
    // actually asked us something, recently, and is still reachable.
    $minChars = WA_TRIAGE_MIN_CHARS;
    $days     = WA_TRIAGE_RECENT_DAYS;
    return "(($a.assigned_user_id IS NULL OR $a.assigned_user_id = '')
             AND $a.program_id IS NULL
             AND ($a.ref_type = 'unknown' OR $a.ref_id IS NULL)
             AND $a.status = 'open'
             AND $a.last_message_at >= (NOW() - INTERVAL $days DAY)
             AND (
                  -- They wrote a real enquiry...
                  EXISTS (SELECT 1 FROM wa_messages tm
                           WHERE tm.contact_id = $a.contact_id
                             AND tm.direction = 'inbound' AND tm.type <> 'note'
                             AND CHAR_LENGTH(TRIM(COALESCE(tm.body, ''))) >= $minChars)
                  -- ...or they told us onsite/virtual, which is a direction on its own
                  -- even when the whole message is a single word such as: Onsite
                  OR $a.delivery_mode <> 'unknown'
             )
             AND NOT EXISTS (SELECT 1 FROM wa_contacts tc
                              WHERE tc.id = $a.contact_id AND tc.opted_out = 1))";
}

/**
 * SQL predicate for "this chat is MINE" — every ownership route a rep can have to a
 * conversation: assigned to them, a rep of its course or event, a manual override,
 * or a rep of its training programme.
 *
 * Deliberately excludes triage, which is the shared pool nobody owns yet. Kept
 * separate from wa_triage_sql() so the inbox can offer them as distinct tabs, and
 * so the scope below is provably "mine OR up for grabs" with nothing else.
 *
 * Distinct aliases (mc/me/mo/mp) because callers already use c for wa_contacts.
 */
function wa_mine_sql($staffId, $a = 'cv') {
    $sid = (int)$staffId;
    return "($a.assigned_user_id = $sid
        OR ($a.ref_type = 'course' AND EXISTS (SELECT 1 FROM course mc  WHERE mc.course_id = $a.ref_id AND FIND_IN_SET($sid, mc.assigned_to) > 0))
        OR ($a.ref_type = 'event'  AND EXISTS (SELECT 1 FROM `Event` me WHERE me.event_id  = $a.ref_id AND FIND_IN_SET($sid, me.assigned_to) > 0))
        OR EXISTS (SELECT 1 FROM wa_course_owner mo WHERE mo.ref_type = $a.ref_type AND mo.ref_id = $a.ref_id AND mo.user_id = $sid)
        -- Training-programme reps: an onsite enquiry with no country yet belongs to the
        -- programme, so every rep on it sees the chat, not only the one it was assigned to.
        OR EXISTS (SELECT 1 FROM wa_programs mp WHERE mp.id = $a.program_id AND FIND_IN_SET($sid, mp.assigned_to) > 0))";
}

function wa_inbox_scope_where($staffId, $isSupervisor) {
    if ($isSupervisor) { return ''; }
    // Everything a rep may see is either theirs, or in the unowned triage pool.
    return " WHERE (" . wa_mine_sql($staffId, 'cv') . " OR " . wa_triage_sql('cv') . ") ";
}

/** Manually-assigned rep for a course/event (fallback), or null. */
function wa_owner_override($conn, $kind, $refId) {
    $k = "'" . mysqli_real_escape_string($conn, $kind) . "'";
    $rid = (int)$refId;
    $res = mysqli_query($conn, "SELECT user_id FROM wa_course_owner WHERE ref_type = $k AND ref_id = $rid LIMIT 1");
    $r = $res ? mysqli_fetch_assoc($res) : null;
    return $r ? (int)$r['user_id'] : null;
}

/** Set (or clear, when $userId <= 0) the manual rep for a course/event. */
function wa_owner_override_set($conn, $kind, $refId, $userId) {
    $k = "'" . mysqli_real_escape_string($conn, $kind) . "'";
    $rid = (int)$refId;
    if ((int)$userId <= 0) {
        mysqli_query($conn, "DELETE FROM wa_course_owner WHERE ref_type = $k AND ref_id = $rid");
        return;
    }
    $uid = (int)$userId;
    mysqli_query($conn,
        "INSERT INTO wa_course_owner (ref_type, ref_id, user_id) VALUES ($k, $rid, $uid)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)");
}

/** Display name for a registered_users.id (staff name preferred). */
function wa_user_name($conn, $userId) {
    $userId = (int)$userId;
    $res = mysqli_query($conn,
        "SELECT COALESCE(NULLIF(s.full_name,''), ru.fullname) AS nm
           FROM registered_users ru LEFT JOIN staff s ON s.system_user_id = ru.id
          WHERE ru.id = $userId LIMIT 1");
    $r = $res ? mysqli_fetch_assoc($res) : null;
    return $r ? $r['nm'] : null;
}

/** Staff eligible to own WhatsApp chats (ERP role 44). */
function wa_role44_users($conn) {
    $res = mysqli_query($conn,
        "SELECT id, fullname FROM registered_users WHERE FIND_IN_SET('44', role) ORDER BY fullname");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

// =====================================================================
// Routing
// =====================================================================

function wa_active_courses($conn) {
    $res = mysqli_query($conn,
        'SELECT course_id AS id, course AS name FROM course WHERE status = 1 ORDER BY course');
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

function wa_course_name($conn, $courseId) {
    $courseId = (int)$courseId;
    $res = mysqli_query($conn, "SELECT course FROM course WHERE course_id = $courseId LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ? $row['course'] : null;
}

function wa_ad_mapping($conn, $adId) {
    $ad = "'" . mysqli_real_escape_string($conn, $adId) . "'";
    $res = mysqli_query($conn, "SELECT ref_type, ref_id FROM wa_ad_map WHERE ad_id = $ad LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ?: null;
}

function wa_get_conversation($conn, $contactId) {
    $contactId = (int)$contactId;
    $res = mysqli_query($conn, "SELECT * FROM wa_conversations WHERE contact_id = $contactId LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ?: null;
}

/**
 * Guarantee a conversation row exists for a contact so the AI can ALWAYS engage —
 * even before we've classified a course. Without this, a new contact whose first
 * message doesn't clearly name a course (e.g. "Hi", "good morning") never gets a
 * conversation row, so wa_maybe_ai_answer skips with 'no_conversation' and the AI
 * stays silent for that number forever. Creates a bare handler='ai' row
 * (ref_type='unknown'); returns the conversation id. No-op if one already exists.
 */
function wa_ensure_conversation($conn, $contactId) {
    $contactId = (int)$contactId;
    $conv = wa_get_conversation($conn, $contactId);
    if ($conv) { return (int)$conv['id']; }
    // contact_id is UNIQUE, so INSERT IGNORE makes a concurrent webhook retry a no-op.
    mysqli_query($conn,
        "INSERT IGNORE INTO wa_conversations (contact_id, ref_type, ref_id, handler, last_route_reason, last_message_at)
         VALUES ($contactId, 'unknown', NULL, 'ai', 'new_contact', NOW())");
    $id = (int)mysqli_insert_id($conn);
    if ($id > 0) { return $id; }
    $conv = wa_get_conversation($conn, $contactId);   // lost the race — read the winner's row
    return $conv ? (int)$conv['id'] : 0;
}

/**
 * Manually set (or clear) a conversation's linked course/event, without touching
 * the staff assignment or handler. $refType = 'course'|'event'|'' (clear).
 */
function wa_set_conversation_ref($conn, $convId, $refType, $refId) {
    $convId = (int)$convId;
    if ($convId <= 0) { return; }
    $valid = in_array($refType, ['course', 'event'], true) && (int)$refId > 0;
    $rt = $valid ? "'" . $refType . "'" : 'NULL';
    $ri = $valid ? (int)$refId : 'NULL';
    mysqli_query($conn,
        "UPDATE wa_conversations
            SET ref_type = $rt, ref_id = $ri, last_route_reason = 'manual', last_route_confidence = 1.000
          WHERE id = $convId");
}

function wa_assign_conversation($conn, $contactId, $refType, $refId, $userId, $reason = null, $confidence = null) {
    $contactId = (int)$contactId;
    $refType   = "'" . mysqli_real_escape_string($conn, $refType) . "'";
    $refIdSql  = $refId === null ? 'NULL' : (int)$refId;
    $userSql   = $userId === null ? 'NULL' : (int)$userId;
    $reasonSql = wa_sql($conn, $reason);
    $confSql   = $confidence === null ? 'NULL' : (float)$confidence;
    mysqli_query($conn,
        "INSERT INTO wa_conversations
              (contact_id, ref_type, ref_id, assigned_user_id, handler,
               last_route_reason, last_route_confidence, last_message_at)
              VALUES ($contactId, $refType, $refIdSql, $userSql, 'ai', $reasonSql, $confSql, NOW())
         ON DUPLICATE KEY UPDATE
              ref_type = VALUES(ref_type), ref_id = VALUES(ref_id),
              -- Adopt the NEW topic's owner on a switch — so an onsite chat goes to the
              -- ONSITE rep, never lingers on the virtual coordinator. If the new topic
              -- has no rep it becomes unassigned (visible to all reps to pick up), which
              -- is correct: better unassigned than assigned to the wrong-mode coordinator.
              assigned_user_id = VALUES(assigned_user_id),
              last_route_reason = VALUES(last_route_reason),
              last_route_confidence = VALUES(last_route_confidence),
              last_message_at = NOW()");
}

/** Generic words that must NOT drive a course match (they appear in many names). */
function wa_stopwords() {
    return array_flip([
        // generic course/product words
        'training','trainings','course','courses','programme','programme','program','programs',
        'certificate','certification','diploma','masterclass','workshop','seminar','bootcamp',
        'class','classes','module','professional','advanced','basic','intro','introduction',
        'fundamentals','online','virtual','event','events','practical','senior','level',
        // generic english / chat filler
        'the','and','for','with','you','your','our','are','can','get','how','what','about',
        'more','info','information','interested','interest','want','need','hello','hallo','hi',
        'please','would','like','know','tell','looking','join','apply','from','this','that',
        'am','is','in','on','of','to','it','me','my','we','do','does','an','a',
    ]);
}

/**
 * Keyword course inference (EN/SW), ignoring generic words so common tokens like
 * "training" can't mis-route. Returns ['course_id'=>?int,'confidence'=>float].
 */
function wa_classify_course($message, $courses) {
    $stop = wa_stopwords();
    $msg = ' ' . wa_normalize($message) . ' ';
    $scores = [];
    foreach ($courses as $c) {
        $hits = 0;
        foreach (explode(' ', wa_normalize((string)$c['name'])) as $w) {
            if (mb_strlen($w) < 3 || isset($stop[$w])) { continue; }   // skip generic tokens
            if (strpos($msg, ' ' . $w . ' ') !== false) { $hits++; }
        }
        if ($hits > 0) { $scores[(int)$c['id']] = $hits; }
    }
    if (!$scores) { return ['course_id' => null, 'confidence' => 0.0]; }
    arsort($scores);
    $ids = array_keys($scores);
    $top = $ids[0];
    $confidence = (count($ids) === 1)
        ? 0.9
        : ($scores[$ids[0]] > $scores[$ids[1]] ? 0.75 : 0.35);   // tie => low, forces confirm/AI
    return ['course_id' => $top, 'confidence' => $confidence];
}

/**
 * Match a message to a specific in-person training EVENT by its location/city
 * (e.g. "Eswatini", "Mbabane") or a distinctive word in its title. Skips academic
 * (ACADEMIC#) and past events. Returns ['event_id'=>?int, 'confidence'=>float].
 */
function wa_classify_event($conn, $text) {
    $stop = wa_stopwords();
    $msg = ' ' . wa_normalize($text) . ' ';
    $res = mysqli_query($conn,
        "SELECT event_id, event_title, location FROM `Event`
          WHERE status = 1 AND location IS NOT NULL AND location <> '' AND location NOT LIKE 'ACADEMIC#%' AND location NOT LIKE 'CORPORATE#%'
            AND (end_on IS NULL OR end_on = '0000-00-00' OR end_on >= CURDATE()
                 OR start_on IS NULL OR start_on = '0000-00-00' OR start_on >= CURDATE())");
    if (!$res) { return ['event_id' => null, 'confidence' => 0.0]; }
    $scores = [];
    while ($e = mysqli_fetch_assoc($res)) {
        $hits = 0;
        // Location tokens are the strong signal (a city/country the client named).
        foreach (explode(' ', wa_normalize((string)$e['location'])) as $w) {
            if (mb_strlen($w) < 4 || isset($stop[$w])) { continue; }
            if (strpos($msg, ' ' . $w . ' ') !== false) { $hits += 2; }
        }
        // A distinctive title word adds a little (e.g. an unusual programme name).
        foreach (explode(' ', wa_normalize((string)$e['event_title'])) as $w) {
            if (mb_strlen($w) < 5 || isset($stop[$w])) { continue; }
            if (strpos($msg, ' ' . $w . ' ') !== false) { $hits += 1; }
        }
        if ($hits > 0) { $scores[(int)$e['event_id']] = $hits; }
    }
    if (!$scores) { return ['event_id' => null, 'confidence' => 0.0]; }
    arsort($scores);
    $ids = array_keys($scores);
    $conf = (count($ids) === 1)
        ? 0.9
        : ($scores[$ids[0]] > $scores[$ids[1]] ? 0.8 : 0.4);   // tie between events -> low
    return ['event_id' => $ids[0], 'confidence' => $conf];
}

/** True if this Event row is an academic/online course ('ACADEMIC#…'), not an
 *  in-person training event. Academic courses have no venue/city/outline and must
 *  be answered like a course, not scoped like a physical event. */
function wa_is_academic_event($conn, $eventId) {
    $eventId = (int)$eventId;
    $res = mysqli_query($conn,
        "SELECT 1 FROM `Event` WHERE event_id = $eventId AND location LIKE 'ACADEMIC#%' LIMIT 1");
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * Classify an ACADEMIC / online course by title (AI for Leaders, CPA(K), etc.).
 * These live in the Event table as 'ACADEMIC#…' rows, which wa_classify_event()
 * deliberately skips (it is location-driven and academic courses have no city).
 * Without this they are invisible to routing, so a chat can never bind to them —
 * the exact reason a conversation stayed stuck on the first course while the
 * customer had clearly moved to an academic one. Matches on title tokens only.
 * Returns ['event_id'=>?int, 'confidence'=>float].
 */
function wa_classify_academic($conn, $text) {
    $stop = wa_stopwords();
    $msg = ' ' . wa_normalize($text) . ' ';
    $res = mysqli_query($conn,
        "SELECT event_id, event_title FROM `Event`
          WHERE status = 1 AND location LIKE 'ACADEMIC#%'");
    if (!$res) { return ['event_id' => null, 'confidence' => 0.0]; }
    $scores = [];
    while ($e = mysqli_fetch_assoc($res)) {
        $hits = 0;
        foreach (explode(' ', wa_normalize((string)$e['event_title'])) as $w) {
            if (mb_strlen($w) < 3 || isset($stop[$w])) { continue; }   // skip generic tokens
            if (strpos($msg, ' ' . $w . ' ') !== false) { $hits++; }
        }
        if ($hits > 0) { $scores[(int)$e['event_id']] = $hits; }
    }
    if (!$scores) { return ['event_id' => null, 'confidence' => 0.0]; }
    arsort($scores);
    $ids = array_keys($scores);
    $conf = (count($ids) === 1)
        ? 0.9
        : ($scores[$ids[0]] > $scores[$ids[1]] ? 0.75 : 0.35);   // tie -> low, no switch
    return ['event_id' => $ids[0], 'confidence' => $conf];
}

/** True if the given provider has a real (non-placeholder) API key in wa_config.php. */
function wa_provider_ready($provider) {
    if ($provider === 'openai') {
        return WA_OPENAI_KEY && strpos(WA_OPENAI_KEY, 'YOUR_') !== 0;
    }
    return WA_ANTHROPIC_KEY && strpos(WA_ANTHROPIC_KEY, 'YOUR_') !== 0;
}

/** Pull the first {...} JSON object out of a model reply. */
function wa_json_extract($text) {
    $s = strpos((string)$text, '{');
    $e = strrpos((string)$text, '}');
    if ($s === false || $e === false || $e < $s) { return null; }
    $d = json_decode(substr($text, $s, $e - $s + 1), true);
    return is_array($d) ? $d : null;
}

/**
 * AI course inference — matches on meaning (e.g. "M&E" -> Monitoring & Evaluation).
 * Only runs when the active provider has a key. Returns ['course_id'=>?int,'confidence'=>float].
 */
function wa_ai_classify_course($conn, $text, $courses) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['course_id' => null, 'confidence' => 0.0]; }

    $lines = [];
    foreach ($courses as $c) { $lines[] = (int)$c['id'] . ': ' . $c['name']; }
    $system = 'You route a prospective student\'s WhatsApp message to exactly one course from the '
            . 'provided list. Match on meaning, not just shared words (e.g. "M&E" means Monitoring '
            . 'and Evaluation). But match ONLY a course that is ACTUALLY in the list. Do NOT map an acronym '
            . 'or a distinct qualification onto a merely related course — e.g. "CPA" (Certified Public '
            . 'Accountant) is NOT "Practical Accounting", and "diploma" is not a "certificate". If the exact '
            . 'course the person named is not clearly in the list, return null (a separate step handles our '
            . 'academic/online courses). When in doubt, return null rather than guessing a near-match. '
            . 'Reply with ONLY JSON: {"course_id": <id or null>, "confidence": <0-1>}.';
    $user = "Courses:\n" . implode("\n", $lines) . "\n\nMessage: \"" . $text . "\"";

    $res = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]],
                          ['json' => true, 'max_tokens' => 150]);
    if (empty($res['ok'])) { return ['course_id' => null, 'confidence' => 0.0]; }

    $data = wa_json_extract($res['text']);
    if (!$data) { return ['course_id' => null, 'confidence' => 0.0]; }

    $cid = (isset($data['course_id']) && $data['course_id'] !== null) ? (int)$data['course_id'] : null;
    $valid = false;
    foreach ($courses as $c) { if ((int)$c['id'] === $cid) { $valid = true; break; } }
    if (!$valid) { return ['course_id' => null, 'confidence' => 0.0]; }

    $conf = isset($data['confidence']) ? (float)$data['confidence'] : 0.6;
    return ['course_id' => $cid, 'confidence' => max(0.0, min(1.0, $conf))];
}

/**
 * AI inference for IN-PERSON events, matched by MEANING — crucially by COUNTRY, not
 * just the city stored in `location`. The keyword classifier only sees the city
 * ('Maseru'), so a client saying 'in person Lesotho' never binds; the AI knows Maseru
 * is in Lesotho and picks it. Only runs when the provider has a key.
 * Returns ['event_id'=>?int, 'confidence'=>float].
 */
function wa_ai_classify_event($conn, $text) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['event_id' => null, 'confidence' => 0.0]; }
    $res = mysqli_query($conn,
        "SELECT event_id, event_title, location FROM `Event`
          WHERE status = 1 AND location IS NOT NULL AND location <> '' AND location NOT LIKE 'ACADEMIC#%' AND location NOT LIKE 'CORPORATE#%'
            AND (end_on IS NULL OR end_on = '0000-00-00' OR end_on >= CURDATE()
                 OR start_on IS NULL OR start_on = '0000-00-00' OR start_on >= CURDATE())
          ORDER BY event_title ASC LIMIT 60");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    if (!$rows) { return ['event_id' => null, 'confidence' => 0.0]; }
    $lines = [];
    foreach ($rows as $r) { $lines[] = (int)$r['event_id'] . ': ' . $r['event_title'] . ' — ' . $r['location']; }
    $system = 'You match a prospective student\'s WhatsApp message to exactly one in-person training event '
            . 'from the list, by MEANING and by COUNTRY. Each item is "id: title — city". Use your knowledge of '
            . 'which country each city is in (e.g. Maseru is in Lesotho, Douala is in Cameroon, Kampala is in '
            . 'Uganda), so "in-person Lesotho" matches the Maseru event. Match the topic AND the place. If none '
            . 'clearly fits, return null. Reply with ONLY JSON: {"event_id": <id or null>, "confidence": <0-1>}.';
    $user = "Events:\n" . implode("\n", $lines) . "\n\nMessage: \"" . $text . "\"";
    $ans = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]], ['json' => true, 'max_tokens' => 150]);
    if (empty($ans['ok'])) { return ['event_id' => null, 'confidence' => 0.0]; }
    $data = wa_json_extract($ans['text']);
    if (!$data) { return ['event_id' => null, 'confidence' => 0.0]; }
    $eid = (isset($data['event_id']) && $data['event_id'] !== null) ? (int)$data['event_id'] : null;
    $valid = false;
    foreach ($rows as $r) { if ((int)$r['event_id'] === $eid) { $valid = true; break; } }
    if (!$valid) { return ['event_id' => null, 'confidence' => 0.0]; }
    $conf = isset($data['confidence']) ? (float)$data['confidence'] : 0.6;
    return ['event_id' => $eid, 'confidence' => max(0.0, min(1.0, $conf))];
}

/**
 * AI inference for ACADEMIC / online courses (Event 'ACADEMIC#…' rows), matched by
 * MEANING rather than shared words — so abbreviations and synonyms the title keyword
 * matcher misses ("CPA" -> Certified Public Accountant, "AI course" -> AI for Leaders)
 * still bind correctly. Only runs when the provider has a key.
 * Returns ['event_id'=>?int, 'confidence'=>float].
 */
function wa_ai_classify_academic($conn, $text) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['event_id' => null, 'confidence' => 0.0]; }

    $res = mysqli_query($conn,
        "SELECT event_id, event_title FROM `Event`
          WHERE status = 1 AND location LIKE 'ACADEMIC#%'
          ORDER BY event_title ASC LIMIT 80");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    if (!$rows) { return ['event_id' => null, 'confidence' => 0.0]; }

    $lines = [];
    foreach ($rows as $r) { $lines[] = (int)$r['event_id'] . ': ' . $r['event_title']; }
    $system = 'You route a prospective student\'s WhatsApp message to exactly one online academic '
            . 'course from the provided list. Match on meaning, not just shared words (e.g. "CPA" '
            . 'means Certified Public Accountant; "AI course" means AI for Leaders). If no course '
            . 'clearly fits, use null. Reply with ONLY JSON: {"event_id": <id or null>, "confidence": <0-1>}.';
    $user = "Academic courses:\n" . implode("\n", $lines) . "\n\nMessage: \"" . $text . "\"";

    $ans = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]],
                          ['json' => true, 'max_tokens' => 150]);
    if (empty($ans['ok'])) { return ['event_id' => null, 'confidence' => 0.0]; }

    $data = wa_json_extract($ans['text']);
    if (!$data) { return ['event_id' => null, 'confidence' => 0.0]; }

    $eid = (isset($data['event_id']) && $data['event_id'] !== null) ? (int)$data['event_id'] : null;
    $valid = false;
    foreach ($rows as $r) { if ((int)$r['event_id'] === $eid) { $valid = true; break; } }
    if (!$valid) { return ['event_id' => null, 'confidence' => 0.0]; }

    $conf = isset($data['confidence']) ? (float)$data['confidence'] : 0.6;
    return ['event_id' => $eid, 'confidence' => max(0.0, min(1.0, $conf))];
}

function wa_normalize($s) {
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? $s;
    return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
}

/** Add the conversation delivery_mode column once (idempotent). Lets the router hold a
 *  dual-mode topic (one we run BOTH online and in person) until the customer says which
 *  they want, so it never locks them to the wrong-mode rep. */
function wa_conv_mode_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_conversations`
        ADD COLUMN IF NOT EXISTS `delivery_mode` ENUM('unknown','virtual','onsite') NOT NULL DEFAULT 'unknown'");
    // Which training programme (wa_programs.id) an onsite-but-unlocated chat belongs
    // to. Set when the programme's rep takes it, and kept afterwards so every rep on
    // that programme keeps seeing the chat even once it binds to a country's Event.
    @mysqli_query($conn, "ALTER TABLE `wa_conversations`
        ADD COLUMN IF NOT EXISTS `program_id` INT UNSIGNED DEFAULT NULL");
}

/** Remember the training programme a conversation belongs to (never clears it). */
function wa_conv_set_program($conn, $convId, $programId) {
    $convId = (int)$convId; $pid = (int)$programId;
    if ($convId < 1 || $pid < 1) { return; }
    wa_conv_mode_schema_ensure($conn);
    mysqli_query($conn, "UPDATE wa_conversations SET program_id = $pid WHERE id = $convId");
}

/** Add the conversation reengaged_at column once (idempotent). Stamped when a human sends
 *  the re-engagement template, so the inbox can flag clients who REPLIED afterwards. */
function wa_conv_reengage_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_conversations`
        ADD COLUMN IF NOT EXISTS `reengaged_at` DATETIME NULL DEFAULT NULL");
}

/**
 * May a message flip an already-recorded mode?
 *
 * delivery_mode was last-write-wins, so a customer who said "in person" and then
 * mentioned online in passing ("in-person instead of online", "is the online one
 * cheaper?") was silently re-recorded as VIRTUAL and handed to the virtual rep.
 * An in-person lead is the harder one to win back, so ONSITE is sticky: only a
 * real change of mind moves it — a short direct answer ("online", "zoom please")
 * or an explicit preference. Everything else keeps onsite.
 *
 * Nothing else is sticky: unknown -> anything, and onsite over virtual, still apply
 * immediately.
 */
function wa_mode_switch_allowed($currentMode, $newMode, $text) {
    if ($currentMode !== 'onsite' || $newMode !== 'virtual') { return true; }
    $t = trim(wa_normalize((string)$text));
    if ($t === '') { return false; }
    // A message still carrying an in-person cue is never a switch to virtual, whatever
    // else it says — "in-person instead of online" is a preference FOR onsite.
    if (preg_match('/\b(on[\s-]?site|onsite|in[\s-]?person|physical|face[\s-]?to[\s-]?face|classroom|in[\s-]?class)\b/i', $t)) {
        return false;
    }
    // A short, direct reply is the customer answering the question — take it.
    if (count(array_filter(explode(' ', $t))) <= 4) { return true; }
    // Otherwise require them to actually state the preference.
    return (bool)preg_match(
        '/\b(prefer|rather|instead|switch|change|go with|opt for|choose|chose|settle for|take the|do the|sign up for)\b/i',
        $t);
}

/** Persist the customer's chosen delivery mode on their conversation. */
function wa_conv_set_mode($conn, $convId, $mode) {
    $convId = (int)$convId;
    if ($convId < 1 || !in_array($mode, ['virtual', 'onsite'], true)) { return; }
    wa_conv_mode_schema_ensure($conn);
    mysqli_query($conn, "UPDATE wa_conversations SET delivery_mode = '$mode' WHERE id = $convId");
}

/** Read 'virtual' or 'onsite' from a message, else '' (unclear / says both / says neither).
 *  Used to decide which rep a dual-mode enquiry belongs to. */
function wa_detect_delivery_mode($text) {
    $t = ' ' . mb_strtolower((string)$text) . ' ';
    $virtual = (bool)preg_match('/\b(virtual|on[\s-]?line|online|remote|zoom|web[\s-]?based|e[\s-]?learn)/i', $t);
    $onsite  = (bool)preg_match('/\b(on[\s-]?site|onsite|in[\s-]?person|physical|face[\s-]?to[\s-]?face|classroom|in[\s-]?class|attend in|travel to)/i', $t);
    if ($virtual && !$onsite) { return 'virtual'; }
    if ($onsite && !$virtual) { return 'onsite'; }
    return '';
}

/** The soonest in-person (non-academic) Event whose title matches a virtual course's
 *  keywords — i.e. the ONSITE twin of a virtual course, plus its rep. null if the course
 *  is virtual-only. Lets the router recognise a dual-mode topic and send the onsite half
 *  to the onsite rep. Returns ['event_id'=>int, 'owner'=>?int]. */
function wa_course_onsite_event($conn, $courseId) {
    $courseId = (int)$courseId;
    $name = wa_course_name($conn, $courseId);
    if ($name === null || trim((string)$name) === '') { return null; }
    $likes = [];
    foreach (wa_program_keywords_arr(['name' => $name]) as $kw) {
        $kw = trim((string)$kw);
        if ($kw === '') { continue; }
        $kw = mysqli_real_escape_string($conn, $kw);
        $likes[] = "event_title LIKE '%$kw%'";
    }
    if (!$likes) { return null; }
    $match = '(' . implode(' OR ', $likes) . ')';
    // In-person only: exclude academic/online rows; keep upcoming (tolerant of legacy dates).
    $res = mysqli_query($conn,
        "SELECT event_id FROM `Event`
          WHERE status = 1 AND location NOT LIKE 'ACADEMIC#%' AND location NOT LIKE 'CORPORATE#%' AND $match
            AND (end_on IS NULL OR end_on = '0000-00-00' OR end_on >= CURDATE()
                 OR start_on IS NULL OR start_on = '0000-00-00' OR start_on >= CURDATE())
          ORDER BY (start_on IS NULL OR start_on = '0000-00-00'), start_on ASC
          LIMIT 1");
    if (!$res || mysqli_num_rows($res) === 0) { return null; }
    $eid = (int)mysqli_fetch_assoc($res)['event_id'];
    return ['event_id' => $eid, 'owner' => wa_first_owner($conn, 'event', $eid)];
}

/**
 * Route an inbound message to a course/event owner.
 * @return array {action, reason, ref_type, ref_id, ref_name, assigned_user_id, confirm_prompt?}
 */
function wa_route_inbound($conn, $waId, $text, $adId = null, $name = null) {
    $contactId = wa_upsert_contact($conn, $waId, $name);
    // Always have a conversation so the AI can engage even when we can't yet classify
    // the topic (otherwise unclassified new contacts get no row -> no AI reply, ever).
    wa_ensure_conversation($conn, $contactId);
    $conv = wa_get_conversation($conn, $contactId);
    $returning = $conv && $conv['ref_id'] !== null;

    // --- Delivery mode (virtual vs onsite) --------------------------------------
    // A topic we run BOTH online and in person must not be pinned to a mode-specific rep
    // until the customer says which they want. Capture any stated mode; and if they're on
    // a dual-mode course and now tell us the mode, finalise the RIGHT rep: onsite chats go
    // to the onsite event's rep (never linger on the virtual coordinator), virtual chats we
    // were holding get the virtual rep. Never override a chat a human has taken over.
    wa_conv_mode_schema_ensure($conn);
    $modeSaid = wa_detect_delivery_mode($text);
    if ($modeSaid !== '' && $conv) {
        $curMode = (string)($conv['delivery_mode'] ?? 'unknown');
        if (wa_mode_switch_allowed($curMode, $modeSaid, $text)) {
            wa_conv_set_mode($conn, (int)$conv['id'], $modeSaid);
        } else {
            // Keep onsite; an incidental mention of online is not a change of mind.
            $modeSaid = $curMode;
            error_log('[wa-mode] kept onsite for contact ' . (int)$contactId
                . ' (incidental virtual mention): ' . mb_substr(trim((string)$text), 0, 80));
        }
    }
    // VIRTUAL confirmed for a dual-mode topic we were holding -> now assign the virtual rep.
    // ONSITE is deliberately NOT auto-assigned: which onsite session (and its rep) depends on
    // the client's LOCATION, so we keep the chat general and let the AI ask where they are —
    // a named location then binds the specific event below. This is why we never pre-link a
    // client to (e.g.) the Malawi session they never actually chose.
    if ($modeSaid === 'virtual' && $conv && $returning && ($conv['ref_type'] ?? '') === 'course'
        && ($conv['handler'] ?? 'ai') !== 'human'
        && ($conv['assigned_user_id'] === null || $conv['assigned_user_id'] === '')) {
        $cid = (int)$conv['ref_id'];
        $uid = wa_first_owner($conn, 'course', $cid);
        wa_assign_conversation($conn, $contactId, 'course', $cid, $uid, 'mode_virtual', 0.9);
        return wa_route_result($conn, 'assigned', 'mode_virtual', 'course', $cid, $uid);
    }

    // Signal 1: ad referral
    if ($adId !== null && $adId !== '') {
        $ad = wa_ad_mapping($conn, $adId);
        if ($ad) {
            $uid = wa_first_owner($conn, $ad['ref_type'], (int)$ad['ref_id']);
            if ($uid !== null) {
                wa_assign_conversation($conn, $contactId, $ad['ref_type'], (int)$ad['ref_id'], $uid, 'ad_referral', 1.0);
                return wa_route_result($conn, 'assigned', 'ad_referral', $ad['ref_type'], (int)$ad['ref_id'], $uid);
            }
        }
    }

    // Signal 1b: a specific in-person EVENT named by its location (e.g. "Eswatini").
    // Routes the chat to that Event so it answers from the full event knowledge
    // (venue, dates, price, outline). Applies to new and returning contacts.
    $evGuess = wa_classify_event($conn, $text);
    $evMethod = 'event_location';
    // Keyword match (city-based) weak? If the message signals a place / in-person intent,
    // ask the AI, which can match by COUNTRY ('in-person Lesotho' -> the Maseru event).
    // Gated on a location cue so routine chatter doesn't spend an AI call every message.
    // Gate on a genuine LOCATION cue — NOT a bare "onsite/in-person", which says the customer
    // wants in-person but NOT which city. Binding a specific city's event off topic alone
    // pre-assigns a session they never chose (e.g. auto-linking a Lesotho enquiry to Malawi).
    if (($evGuess['event_id'] === null || $evGuess['confidence'] < 0.60)
        && preg_match('/\b(venue|country|from|based\s+in|attend|travel|located|location|city)\b/i', $text)) {
        $aiEv = wa_ai_classify_event($conn, $text);
        if ($aiEv['event_id'] !== null && $aiEv['confidence'] >= 0.60) { $evGuess = $aiEv; $evMethod = 'event_ai'; }
    }
    if ($evGuess['event_id'] !== null && $evGuess['confidence'] >= 0.60) {
        $eid = (int)$evGuess['event_id'];
        if (!$returning || (int)$conv['ref_id'] !== $eid || $conv['ref_type'] !== 'event') {
            $uid = wa_first_owner($conn, 'event', $eid);
            // A programme rep already owns this chat (they picked it up while the customer
            // had said "onsite" but not where). Binding the country's Event must not yank
            // it out from under them mid-conversation: keep the owner, move the topic, and
            // leave a staff-only note so the country's rep knows the lead is theirs to
            // share. Ownership only transfers if nobody holds it.
            $progOwned = $conv && !empty($conv['program_id'])
                      && $conv['assigned_user_id'] !== null && $conv['assigned_user_id'] !== '';
            if ($progOwned) {
                $keepUid = (int)$conv['assigned_user_id'];
                wa_assign_conversation($conn, $contactId, 'event', $eid, $keepUid, $evMethod . '_kept_owner', $evGuess['confidence']);
                if ($uid !== null && $uid !== $keepUid) {
                    $evName = wa_scalar_str($conn, "SELECT event_title FROM `Event` WHERE event_id = $eid LIMIT 1");
                    $repName = wa_scalar_str($conn, "SELECT fullname FROM registered_users WHERE id = " . (int)$uid . " LIMIT 1");
                    wa_ai_post_note($conn, $contactId,
                        'Location confirmed — this is now ' . trim((string)$evName) . '. '
                      . 'The rep for that session is ' . trim((string)$repName) . '. '
                      . 'This chat stays with you; loop them in to register the client.');
                }
                return wa_route_result($conn, 'assigned', $evMethod . '_kept_owner', 'event', $eid, $keepUid);
            }
            wa_assign_conversation($conn, $contactId, 'event', $eid, $uid, $evMethod, $evGuess['confidence']);
            return wa_route_result($conn, 'assigned', $evMethod, 'event', $eid, $uid);
        }
        // already on this event — keep it
        $uid = $conv['assigned_user_id'] !== null ? (int)$conv['assigned_user_id'] : null;
        return wa_route_result($conn, 'kept', 'continuing', 'event', $eid, $uid);
    }

    $courses = wa_active_courses($conn);
    if (!$courses) {
        return ['action' => 'unassigned', 'reason' => 'no_courses', 'ref_type' => 'unknown',
                'ref_id' => null, 'ref_name' => null, 'assigned_user_id' => null];
    }

    // Signal 2: keyword inference, with an AI fallback when the keyword guess is weak.
    $guess = wa_classify_course($text, $courses);
    $courseId = $guess['course_id'];
    $conf = $guess['confidence'];
    $method = 'inferred';                       // keyword by default
    if ($courseId === null || $conf < 0.60) {
        $ai = wa_ai_classify_course($conn, $text, $courses);   // no-op if no AI key
        if ($ai['course_id'] !== null && $ai['confidence'] >= 0.60) {
            $courseId = $ai['course_id'];
            $conf = $ai['confidence'];
            $method = 'ai_inferred';
        }
    }

    // Academic fallback: still no confident regular-course match, so check whether the
    // customer named an academic/online course (AI for Leaders, CPA(K), …) which the
    // course + location-event classifiers cannot see. Runs ONLY as a fallback, so it
    // can never hijack a clear course match like "Senior Management". Academic courses
    // are Event rows, so this binds the chat as an event ref.
    if ($courseId === null || $conf < 0.60) {
        $ac = wa_classify_academic($conn, $text);
        $acMethod = 'academic_title';
        // Keyword match weak? Ask the AI (catches "CPA", "AI course", other synonyms).
        if ($ac['event_id'] === null || $ac['confidence'] < 0.60) {
            $aiAc = wa_ai_classify_academic($conn, $text);   // no-op if no AI key
            if ($aiAc['event_id'] !== null && $aiAc['confidence'] >= 0.60) {
                $ac = $aiAc;
                $acMethod = 'academic_ai';
            }
        }
        if ($ac['event_id'] !== null && $ac['confidence'] >= 0.60) {
            $eid = (int)$ac['event_id'];
            if (!$returning || (int)$conv['ref_id'] !== $eid || $conv['ref_type'] !== 'event') {
                $uid = wa_first_owner($conn, 'event', $eid);
                wa_assign_conversation($conn, $contactId, 'event', $eid, $uid, $acMethod, $ac['confidence']);
                return wa_route_result($conn, 'assigned', $acMethod, 'event', $eid, $uid);
            }
            $uid = $conv['assigned_user_id'] !== null ? (int)$conv['assigned_user_id'] : null;
            return wa_route_result($conn, 'kept', 'continuing', 'event', $eid, $uid);
        }
    }

    if ($returning) {
        $cur = (int)$conv['ref_id'];
        // Same course, nothing detected, or too weak a signal -> stay on the current
        // topic (a bare "how much?" keeps whatever we were already discussing).
        if ($courseId === null || $courseId === $cur || $conf < 0.60) {
            $uid = $conv['assigned_user_id'] !== null ? (int)$conv['assigned_user_id'] : null;
            return wa_route_result($conn, 'kept', 'continuing', $conv['ref_type'], $cur, $uid);
        }
        // A different course is clearly the new subject -> follow the switch so the AI
        // answers from the RIGHT knowledge base (the owner + thread labels follow too).
        // We no longer stall on a "should I connect you?" confirmation that was never
        // actually sent: the AI's grounded reply about the new course is the confirmation.
        $uid = wa_first_owner($conn, 'course', (int)$courseId);
        wa_assign_conversation($conn, $contactId, 'course', (int)$courseId, $uid, $method . '_switch', $conf);
        return wa_route_result($conn, 'reassigned', $method . '_switch', 'course', (int)$courseId, $uid);
    }

    if ($courseId !== null && $conf >= 0.60) {
        // Dual-mode? If this virtual course is ALSO offered in person, don't pin a
        // mode-specific rep until the customer confirms which they want.
        $onsite = wa_course_onsite_event($conn, (int)$courseId);
        $mode   = $modeSaid !== '' ? $modeSaid
                : (($conv && isset($conv['delivery_mode'])) ? $conv['delivery_mode'] : 'unknown');
        if ($onsite && ($mode === 'unknown' || $mode === 'onsite')) {
            // Dual-mode topic. Bind it so the AI can answer, but DON'T lock a mode/location-
            // specific rep: mode unknown -> the AI asks virtual-vs-onsite; onsite -> it asks
            // WHICH location. A named location then binds the specific event + its onsite rep
            // (via the event classifier). We never pre-assign a session the client never chose.
            $reason = $mode === 'onsite' ? 'await_onsite_location' : 'await_mode';
            // ONSITE confirmed but no country yet: there is no Event to route to, and
            // leaving it unowned is why these chats went unfollowed when the customer
            // stopped replying. Hand it to the training programme's rep so it lands in
            // a real inbox; the country's own rep still takes over below once a location
            // is named. 'await_mode' stays unowned — the customer has not committed to
            // in-person yet, so it is not the onsite team's chat.
            $ownerUid = null;
            if ($mode === 'onsite') {
                // $onsite is the in-person twin of this virtual course — the event the
                // programme's keywords are actually written against, so pass it.
                $prog = wa_program_for_course($conn, (int)$courseId, $text, (int)($onsite['event_id'] ?? 0));
                if ($prog) {
                    $ownerUid = wa_program_first_owner($prog);
                    if ($conv) { wa_conv_set_program($conn, (int)$conv['id'], (int)$prog['id']); }
                }
            }
            wa_assign_conversation($conn, $contactId, 'course', (int)$courseId, $ownerUid, $reason, $conf);
            if ($ownerUid === null) {
                return wa_route_result($conn, 'assigned_unowned', $reason, 'course', (int)$courseId, null);
            }
            // The programme was matched after wa_assign_conversation created the row on a
            // first-contact chat, so stamp it once the conversation certainly exists.
            if (!$conv && isset($prog['id'])) {
                $c2 = wa_get_conversation($conn, $contactId);
                if ($c2) { wa_conv_set_program($conn, (int)$c2['id'], (int)$prog['id']); }
            }
            return wa_route_result($conn, 'assigned', $reason, 'course', (int)$courseId, $ownerUid);
        }
        // Virtual-only course, or the customer confirmed virtual -> normal assignment.
        $uid = wa_first_owner($conn, 'course', (int)$courseId);
        if ($uid !== null) {
            wa_assign_conversation($conn, $contactId, 'course', (int)$courseId, $uid, $method, $conf);
            return wa_route_result($conn, 'assigned', $method, 'course', (int)$courseId, $uid);
        }
        wa_assign_conversation($conn, $contactId, 'course', (int)$courseId, null, $method . '_no_owner', $conf);
        return wa_route_result($conn, 'assigned_unowned', 'inferred_no_owner', 'course', (int)$courseId, null);
    }
    return wa_route_confirm($conn, $courseId, false);
}

function wa_route_result($conn, $action, $reason, $refType, $refId, $uid) {
    return [
        'action' => $action, 'reason' => $reason, 'ref_type' => $refType, 'ref_id' => $refId,
        // Resolve by ref_type so event conversations don't get looked up as courses.
        'ref_name' => $refId !== null ? wa_ref_name($conn, $refType, (int)$refId) : null,
        'assigned_user_id' => $uid,
    ];
}

function wa_route_confirm($conn, $courseId, $switching) {
    $name = $courseId !== null ? wa_course_name($conn, $courseId) : null;
    $prompt = $name
        ? ($switching
            ? "It sounds like you're now asking about our {$name} — should I connect you to that instead?"
            : "Sounds like you're interested in our {$name} — is that right?")
        : 'Which of our courses are you interested in?';
    return ['action' => 'needs_confirmation', 'reason' => $switching ? 'ambiguous_switch' : 'low_confidence',
            'ref_type' => 'unknown', 'ref_id' => null, 'ref_name' => null, 'assigned_user_id' => null,
            'confirm_prompt' => $prompt];
}

// =====================================================================
// Settings (wa_settings table)
// =====================================================================

function wa_setting_get($conn, $key, $default = null) {
    $k = "'" . mysqli_real_escape_string($conn, $key) . "'";
    $res = mysqli_query($conn, "SELECT `value` FROM wa_settings WHERE `key` = $k LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ? (string)$row['value'] : $default;
}

function wa_setting_set($conn, $key, $value) {
    $k = "'" . mysqli_real_escape_string($conn, $key) . "'";
    $v = "'" . mysqli_real_escape_string($conn, $value) . "'";
    mysqli_query($conn,
        "INSERT INTO wa_settings (`key`, `value`) VALUES ($k, $v)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
}

function wa_active_provider($conn) {
    $p = wa_setting_get($conn, 'ai_provider', WA_DEFAULT_PROVIDER);
    return in_array($p, ['claude', 'openai'], true) ? $p : 'claude';
}

// =====================================================================
// Templates + broadcast
// =====================================================================

/** Approved templates only, each with the number of {{n}} variables its body uses. */
function wa_templates_approved($conn) {
    $res = mysqli_query($conn, "SELECT name, language, category, body FROM wa_templates
                                 WHERE status = 'approved' ORDER BY name, language");
    $out = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $n = 0;
            if (preg_match_all('/\{\{\s*(\d+)\s*\}\}/', (string)$r['body'], $mm) && $mm[1]) {
                $n = max(array_map('intval', $mm[1]));
            }
            $r['vars'] = $n;
            $out[] = $r;
        }
    }
    return $out;
}

/**
 * Sensible starting values for a re-engagement template's variables, in the order
 * WhatsApp numbers them. The rep can overwrite any of them before sending — these
 * are a starting point, not a rule, which is the whole reason the picker exists.
 */
function wa_reengage_defaults($conn, $conv, $staffId) {
    $cid = (int)($conv['contact_id'] ?? 0);
    $name = wa_scalar_str($conn, "SELECT profile_name FROM wa_contacts WHERE id = $cid LIMIT 1");
    $country = wa_scalar_str($conn, "SELECT country FROM wa_contacts WHERE id = $cid LIMIT 1");
    $rep  = wa_scalar_str($conn,
        "SELECT COALESCE(NULLIF(s.full_name,''), ru.fullname) FROM registered_users ru
      LEFT JOIN staff s ON s.system_user_id = ru.id WHERE ru.id = " . (int)$staffId . " LIMIT 1");
    $course = '';
    if (($conv['ref_id'] ?? null) !== null && in_array($conv['ref_type'] ?? '', ['course', 'event', 'program'], true)) {
        $course = trim((string)wa_ref_name($conn, $conv['ref_type'], (int)$conv['ref_id']));
    }
    return [
        'name'    => trim($name) !== '' ? trim($name) : 'there',
        'rep'     => trim($rep)  !== '' ? trim($rep)  : 'the Vantage Africa team',
        'course'  => $course !== '' ? $course : 'our programmes',
        'country' => trim($country),
    ];
}

/**
 * Suggest LITERAL values for one chat's re-engagement template variables.
 *
 * Deliberately not wa_broadcast_suggest_vars(): that maps placeholders to tokens
 * ({name}, {course}) because a broadcast substitutes per recipient. Here there is
 * exactly one customer and a whole conversation to read, so the AI can propose the
 * actual words — picking up where the chat stalled, which is the entire reason a
 * rep re-engages in the first place.
 *
 * Falls back to the deterministic name/rep/course defaults whenever the AI is
 * unavailable or answers with anything unusable, so the button never leaves the
 * form worse than it found it.
 */
function wa_reengage_suggest_vars($conn, $convId, $tplName, $lang, $staffId) {
    $convId = (int)$convId;
    $conv = null;
    $cr = mysqli_query($conn, "SELECT * FROM wa_conversations WHERE id = $convId LIMIT 1");
    if ($cr) { $conv = mysqli_fetch_assoc($cr); }
    if (!$conv) { return ['ok' => false, 'error' => 'no_conversation']; }

    $tplName = trim((string)$tplName);
    if ($tplName === '') { return ['ok' => false, 'error' => 'no_template']; }
    $q = "SELECT body FROM wa_templates WHERE name = '" . mysqli_real_escape_string($conn, $tplName) . "'";
    if (trim((string)$lang) !== '') { $q .= " AND language = '" . mysqli_real_escape_string($conn, $lang) . "'"; }
    $body = wa_scalar_str($conn, $q . " ORDER BY id DESC LIMIT 1");
    if ($body === '') { return ['ok' => false, 'error' => 'no_body']; }

    preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $mm);
    $nums = $mm[1] ? array_values(array_unique(array_map('intval', $mm[1]))) : [];
    sort($nums);
    if (!$nums) { return ['ok' => true, 'map' => (object)[]]; }

    // Deterministic baseline — also the fallback if the AI is off or unhelpful.
    $fill = wa_reengage_defaults($conn, $conv, $staffId);
    $n = count($nums);
    $base = ($n >= 3) ? [$fill['name'], $fill['rep'], $fill['course']]
          : (($n === 2) ? [$fill['name'], $fill['course']] : [$fill['name']]);
    $map = [];
    foreach ($nums as $i => $num) { $map[(string)$num] = $base[$i] ?? $fill['name']; }

    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['ok' => true, 'map' => $map, 'ai' => false]; }

    // Recent conversation, oldest first, so the model sees how it stalled.
    $lines = [];
    foreach (array_slice(wa_thread($conn, (int)$conv['contact_id'], 40), -12) as $m) {
        if (($m['type'] ?? '') === 'note') { continue; }
        $t = trim(preg_replace('/\s+/u', ' ', (string)($m['body'] ?? '')));
        if ($t === '') { continue; }
        $lines[] = (($m['direction'] ?? '') === 'inbound' ? 'Customer: ' : 'Us: ') . mb_substr($t, 0, 220);
    }

    $topic = $fill['course'];
    $ctx = "Customer name: {$fill['name']}\n"
         . ($fill['country'] !== '' ? "Customer country (from their phone number): {$fill['country']}\n" : '')
         . "Programme discussed: {$topic}\n"
         . "Our staff member re-engaging them: {$fill['rep']}\n"
         . 'Delivery mode they wanted: ' . (string)($conv['delivery_mode'] ?? 'unknown') . "\n"
         . 'Where routing left it: ' . (string)($conv['last_route_reason'] ?? 'n/a') . "\n\n"
         . "Recent conversation:\n" . (implode("\n", $lines) ?: '(no readable messages)');

    $system = 'You fill in the variables of an approved WhatsApp re-engagement template for ONE customer '
            . 'whose chat went quiet. Reply with ONLY a JSON object mapping each placeholder number to the '
            . 'literal text to insert, e.g. {"1":"Jane","2":"Peter Otieno"}. Rules: each value is plain text '
            . 'on a SINGLE line, no newlines, no emoji, under 60 characters, and must read naturally where the '
            . 'placeholder sits in the template. Use the real names, programme and country from the context — '
            . 'never invent dates, prices or promises, and never write a placeholder token like {name}.';
    $user = "Template body:\n\"" . $body . "\"\n\nPlaceholder numbers: " . implode(', ', $nums)
          . "\n\nContext:\n" . $ctx;

    $ans = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]],
                          ['json' => true, 'max_tokens' => 300]);
    if (empty($ans['ok'])) { return ['ok' => true, 'map' => $map, 'ai' => false]; }

    $data = wa_json_extract($ans['text'] ?? '');
    if (!is_array($data)) { return ['ok' => true, 'map' => $map, 'ai' => false]; }

    $used = false;
    foreach ($nums as $num) {
        $v = isset($data[(string)$num]) ? (string)$data[(string)$num] : '';
        $v = trim(preg_replace('/\s+/u', ' ', $v));          // template params reject newlines
        if ($v === '' || mb_strlen($v) > 60) { continue; }    // keep the deterministic default
        if (preg_match('/\{\{?\s*\w+\s*\}?\}/', $v)) { continue; }  // it echoed a token, not a value
        $map[(string)$num] = $v;
        $used = true;
    }
    return ['ok' => true, 'map' => $map, 'ai' => $used];
}

function wa_templates_list($conn) {
    $res = mysqli_query($conn, "SELECT * FROM wa_templates ORDER BY name, language");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

function wa_template_save($conn, $name, $language, $category, $body, $status) {
    $n = "'" . mysqli_real_escape_string($conn, $name) . "'";
    $l = "'" . mysqli_real_escape_string($conn, $language) . "'";
    $c = wa_sql($conn, $category);
    $b = wa_sql($conn, $body);
    $s = "'" . mysqli_real_escape_string($conn, $status) . "'";
    mysqli_query($conn,
        "INSERT INTO wa_templates (name, language, category, body, status) VALUES ($n, $l, $c, $b, $s)
         ON DUPLICATE KEY UPDATE category = VALUES(category), body = VALUES(body), status = VALUES(status)");
}

function wa_template_delete($conn, $name, $language) {
    $n = "'" . mysqli_real_escape_string($conn, $name) . "'";
    $l = "'" . mysqli_real_escape_string($conn, $language) . "'";
    mysqli_query($conn, "DELETE FROM wa_templates WHERE name = $n AND language = $l");
}

/** Number of {{n}} body variables in a template body. */
function wa_template_var_count($body) {
    if (!preg_match_all('/\{\{\s*(\d+)\s*\}\}/', (string)$body, $m)) { return 0; }
    return count(array_unique($m[1]));
}

/**
 * Contacts to broadcast to. $filter = 'all' | 'optedin' | 'course'.
 * Returns [ ['wa_id'=>..,'name'=>..], ... ].
 */
/** Parse a ref id that may be a single value or a CSV of them ("12" or "12,7,3").
 *  Returns a de-duplicated list of positive ints, in the order given. */
function wa_ref_ids($refId) {
    $out = [];
    foreach (explode(',', (string)$refId) as $p) {
        $n = (int)trim($p);
        if ($n > 0 && !in_array($n, $out, true)) { $out[] = $n; }
    }
    return $out;
}

function wa_broadcast_audience($conn, $filter, $refId = 0) {
    // $refId may be several ids: a broadcast can target more than one course at once
    // (e.g. every M&E cohort), which is one send to a de-duplicated audience rather
    // than one send per course hitting shared contacts twice.
    $ids = wa_ref_ids($refId);
    // Never broadcast to anyone who has opted out — regardless of the filter.
    $where = "c.wa_id <> '' AND c.opted_out = 0";
    // 'course'/'event' filter contacts to those whose conversation is linked to
    // those courses or onsite events (refId is the course_id / event_id).
    if ($filter === 'optedin') {
        $where .= ' AND c.opted_in = 1';
    } elseif (($filter === 'course' || $filter === 'event') && $ids) {
        $f = mysqli_real_escape_string($conn, $filter);
        $where .= " AND cv.ref_type = '$f' AND cv.ref_id IN (" . implode(',', $ids) . ")";
    } elseif ($filter === 'batch' && $ids) {
        $where .= " AND c.import_batch_id IN (" . implode(',', $ids) . ")";
    } elseif (in_array($filter, ['course', 'event', 'batch'], true)) {
        // A targeted filter with nothing selected must send to NOBODY. Falling through
        // to the unfiltered WHERE would blast every contact in the database — easy to
        // trigger now the course picker is multi-select and can be left empty.
        return [];
    }
    // Pull each contact's linked course/event, its registration link and assigned rep, so
    // template tokens ({name},{course},{link},{rep}) can be filled per-contact from the DB.
    // contact_id is unique in wa_conversations, so the LEFT JOIN yields one row per contact.
    $res = mysqli_query($conn, "
        SELECT c.wa_id, c.profile_name, cv.ref_type, cv.ref_id,
               CASE cv.ref_type
                    WHEN 'course' THEN (SELECT course FROM course WHERE course_id = cv.ref_id)
                    WHEN 'event'  THEN (SELECT event_title FROM `Event` WHERE event_id = cv.ref_id)
               END AS course_name,
               COALESCE(NULLIF(s.full_name,''), ru.fullname) AS rep_name
          FROM wa_contacts c
     LEFT JOIN wa_conversations cv ON cv.contact_id = c.id
     LEFT JOIN registered_users ru ON ru.id = cv.assigned_user_id
     LEFT JOIN staff s              ON s.system_user_id = cv.assigned_user_id
         WHERE $where
      ORDER BY c.id");
    $rows = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $link = (($r['ref_type'] ?? '') === 'event' && (int)$r['ref_id'] > 0)
                ? wa_event_register_url((int)$r['ref_id']) : '';
            $rows[] = [
                'wa_id'  => $r['wa_id'],
                'name'   => $r['profile_name'] ?: '',
                'course' => $r['course_name'] ?: '',
                'link'   => $link,
                'rep'    => $r['rep_name'] ?: '',
            ];
        }
    }
    return $rows;
}

/**
 * Fill a broadcast variable value's {tokens} from a contact's DB data. Unknown/blank
 * tokens fall back to a safe non-empty default so WhatsApp never rejects the send with
 * "(#131008) Required parameter is missing". Tokens: {name} {first_name} {course} {link} {rep}.
 */
function wa_broadcast_fill($value, $item) {
    $name  = trim((string)($item['name'] ?? ''));
    $first = $name !== '' ? preg_split('/\s+/', $name)[0] : '';
    $map = [
        '{name}'       => $name !== '' ? $name : 'there',
        '{first_name}' => $first !== '' ? $first : 'there',
        '{course}'     => trim((string)($item['course'] ?? '')) !== '' ? trim((string)$item['course']) : 'our programmes',
        '{link}'       => trim((string)($item['link'] ?? '')) !== '' ? trim((string)$item['link']) : 'https://vantageafricaleaders.com',
        '{rep}'        => trim((string)($item['rep'] ?? '')) !== '' ? trim((string)$item['rep']) : 'our team',
    ];
    $out = trim(strtr((string)$value, $map));
    // A template parameter can NEVER be empty (131008). If it resolved to nothing, use the
    // person's name as a harmless non-empty fallback.
    if ($out === '') { $out = $map['{name}']; }
    return $out;
}

/** Build the template `components` array: an optional media header (flier) + the body
 *  params. headerType is 'image' | 'video' | 'document'; the media is a 360dialog media id. */
function wa_broadcast_components($params, $headerMediaId = '', $headerType = '') {
    $components = [];
    if ($headerMediaId !== '' && in_array($headerType, ['image', 'video', 'document'], true)) {
        $components[] = ['type' => 'header', 'parameters' => [[
            'type' => $headerType, $headerType => ['id' => $headerMediaId],
        ]]];
    }
    if ($params) {
        $components[] = ['type' => 'body', 'parameters' =>
            array_map(function ($t) { return ['type' => 'text', 'text' => $t]; }, $params)];
    }
    return $components;
}

/** Idempotently add the flier (header media) columns to wa_scheduled_broadcasts. */
function wa_broadcast_header_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_scheduled_broadcasts`
        ADD COLUMN IF NOT EXISTS `header_media_id` VARCHAR(255) NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `header_type` VARCHAR(16) NULL DEFAULT NULL");
}

/** Keyword heuristic: guess a data token for each {{n}} from the words just before it.
 *  Works with no AI key; wa_broadcast_suggest_vars refines it with the model when available. */
function wa_broadcast_guess_vars($body, $nums) {
    $map = [];
    foreach ($nums as $n) {
        $pos = strpos($body, '{{' . $n . '}}');
        $before = $pos !== false ? strtolower(substr($body, max(0, $pos - 30), 30)) : '';
        $tok = '{name}';
        if (strpos($before, 'http') !== false || strpos($before, 'link') !== false
            || strpos($before, 'register') !== false || strpos($before, 'apply') !== false
            || strpos($before, 'enrol') !== false) { $tok = '{link}'; }
        elseif (strpos($before, 'course') !== false || strpos($before, 'programme') !== false
            || strpos($before, 'program') !== false || strpos($before, 'training') !== false) { $tok = '{course}'; }
        $map[(string)$n] = $tok;
    }
    return $map;
}

/**
 * Identify what each template placeholder {{n}} should be filled with, as a data token
 * ({name}/{first_name}/{course}/{link}/{rep}). Uses a keyword heuristic, then refines with
 * the AI provider when configured. Returns ['ok'=>true, 'map'=>{"1":"{name}",...}].
 */
function wa_broadcast_suggest_vars($conn, $name, $lang) {
    $name = trim((string)$name);
    if ($name === '') { return ['ok' => false, 'error' => 'no_template']; }
    $q = "SELECT body FROM wa_templates WHERE name = '" . mysqli_real_escape_string($conn, $name) . "'";
    if (trim((string)$lang) !== '') { $q .= " AND language = '" . mysqli_real_escape_string($conn, $lang) . "'"; }
    $q .= " ORDER BY id DESC LIMIT 1";
    $r = mysqli_query($conn, $q);
    $body = ($r && ($row = mysqli_fetch_assoc($r))) ? (string)$row['body'] : '';
    if ($body === '') { return ['ok' => false, 'error' => 'no_body']; }
    preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $mm);
    $nums = $mm[1] ? array_values(array_unique(array_map('intval', $mm[1]))) : [];
    if (!$nums) { return ['ok' => true, 'map' => (object)[]]; }

    $map = wa_broadcast_guess_vars($body, $nums);   // heuristic baseline

    $provider = wa_active_provider($conn);
    if (wa_provider_ready($provider)) {
        $system = 'You map WhatsApp template placeholders to data tokens. Allowed tokens ONLY: '
                . '{name}, {first_name}, {course}, {link}, {rep}. Use {link} for a URL / registration link, '
                . '{course} for a programme or course name, {name} or {first_name} for the recipient, {rep} for our '
                . 'staff contact. Reply with ONLY JSON mapping each placeholder number to one token, '
                . 'e.g. {"1":"{name}","2":"{course}"}.';
        $user = "Template body:\n\"" . $body . "\"\n\nPlaceholder numbers: " . implode(', ', $nums);
        $ans = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]], ['json' => true, 'max_tokens' => 150]);
        if (!empty($ans['ok'])) {
            $data = wa_json_extract($ans['text'] ?? '');
            if (is_array($data)) {
                foreach ($nums as $n) {
                    $val = isset($data[(string)$n]) ? trim((string)$data[(string)$n]) : '';
                    if (preg_match('/^\{(name|first_name|course|link|rep)\}$/', $val)) { $map[(string)$n] = $val; }
                }
            }
        }
    }
    return ['ok' => true, 'map' => $map];
}

/** Record the start of a broadcast run. Returns the new broadcast id. */
function wa_broadcast_create($conn, $template, $language, $audience, $courseId, $total, $createdBy) {
    wa_broadcast_refids_schema_ensure($conn);
    $t   = "'" . mysqli_real_escape_string($conn, $template) . "'";
    $l   = "'" . mysqli_real_escape_string($conn, $language ?: 'en') . "'";
    $a   = "'" . mysqli_real_escape_string($conn, $audience ?: 'all') . "'";
    // course_id is INT, so it holds the FIRST id and keeps every existing report and
    // label working; ref_ids carries the whole selection when several were chosen.
    $ids = wa_ref_ids($courseId);
    $cid = $ids ? $ids[0] : 'NULL';
    $rids = $ids ? "'" . implode(',', $ids) . "'" : 'NULL';
    $tot = (int)$total;
    $by  = ((int)$createdBy > 0) ? (int)$createdBy : 'NULL';
    mysqli_query($conn,
        "INSERT INTO wa_broadcasts (template, language, audience, course_id, ref_ids, total, created_by)
         VALUES ($t, $l, $a, $cid, $rids, $tot, $by)");
    return (int)mysqli_insert_id($conn);
}

/** Add the multi-target column once (idempotent). */
function wa_broadcast_refids_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_broadcasts`
        ADD COLUMN IF NOT EXISTS `ref_ids` VARCHAR(255) DEFAULT NULL");
}

/** Idempotently ensure the last_error column exists on wa_broadcasts. */
function wa_broadcast_error_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_broadcasts` ADD COLUMN IF NOT EXISTS `last_error` VARCHAR(300) NULL DEFAULT NULL");
}

/** Record WHY a broadcast failed (e.g. "template ... does not exist in en") so the history
 *  shows the reason instead of a silent "Failed 0". */
function wa_broadcast_set_error($conn, $bid, $err) {
    $bid = (int)$bid;
    if ($bid < 1 || trim((string)$err) === '') { return; }
    wa_broadcast_error_schema_ensure($conn);
    $e = "'" . mysqli_real_escape_string($conn, mb_substr((string)$err, 0, 290)) . "'";
    mysqli_query($conn, "UPDATE wa_broadcasts SET last_error = $e WHERE id = $bid");
}

/** Tag an already-sent outbound message as belonging to a broadcast (by wa_message_id). */
function wa_broadcast_tag_message($conn, $broadcastId, $wamid) {
    $bid = (int)$broadcastId;
    if ($bid <= 0 || !$wamid) { return; }
    $w = "'" . mysqli_real_escape_string($conn, (string)$wamid) . "'";
    mysqli_query($conn, "UPDATE wa_messages SET broadcast_id = $bid WHERE wa_message_id = $w");
}

/**
 * Broadcast runs with derived delivery counts. Each run's per-recipient status
 * is aggregated from wa_messages (status: sent -> delivered -> read / failed).
 */
function wa_broadcasts_list($conn, $limit = 50) {
    wa_broadcast_error_schema_ensure($conn);   // so b.* includes last_error
    $limit = (int)$limit;
    $sql = "
        SELECT b.*,
               (SELECT COUNT(*) FROM wa_messages m WHERE m.broadcast_id = b.id) AS attempted,
               (SELECT COUNT(*) FROM wa_messages m WHERE m.broadcast_id = b.id AND m.status = 'failed')    AS failed,
               (SELECT COUNT(*) FROM wa_messages m WHERE m.broadcast_id = b.id AND m.status = 'delivered') AS delivered,
               (SELECT COUNT(*) FROM wa_messages m WHERE m.broadcast_id = b.id AND m.status = 'read')      AS read_ct,
               (SELECT COUNT(*) FROM wa_messages m WHERE m.broadcast_id = b.id AND m.status = 'sent')      AS sent_only,
               CONCAT(
                 COALESCE(CASE b.audience
                      WHEN 'course' THEN (SELECT course FROM course WHERE course_id = b.course_id)
                      WHEN 'event'  THEN (SELECT event_title FROM `Event` WHERE event_id = b.course_id)
                 END, ''),
                 -- Several targets: say so, rather than naming only the first.
                 CASE WHEN COALESCE(b.ref_ids,'') LIKE '%,%'
                      THEN CONCAT(' +', LENGTH(b.ref_ids) - LENGTH(REPLACE(b.ref_ids, ',', '')), ' more')
                      ELSE '' END
               ) AS course_name
          FROM wa_broadcasts b
      ORDER BY b.id DESC
         LIMIT $limit";
    $res = mysqli_query($conn, $sql);
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

/** A single broadcast run with its derived delivery counts (or null). */
function wa_broadcast_get($conn, $id) {
    $id = (int)$id;
    $res = mysqli_query($conn,
        "SELECT b.*,
                (SELECT COUNT(*) FROM wa_messages m WHERE m.broadcast_id = b.id) AS attempted,
                (SELECT COUNT(*) FROM wa_messages m WHERE m.broadcast_id = b.id AND m.status = 'failed')    AS failed,
                (SELECT COUNT(*) FROM wa_messages m WHERE m.broadcast_id = b.id AND m.status = 'delivered') AS delivered,
                (SELECT COUNT(*) FROM wa_messages m WHERE m.broadcast_id = b.id AND m.status = 'read')      AS read_ct,
                (SELECT COUNT(*) FROM wa_messages m WHERE m.broadcast_id = b.id AND m.status = 'sent')      AS sent_only,
                CONCAT(
                  COALESCE(CASE b.audience
                       WHEN 'course' THEN (SELECT course FROM course WHERE course_id = b.course_id)
                       WHEN 'event'  THEN (SELECT event_title FROM `Event` WHERE event_id = b.course_id)
                  END, ''),
                  -- Several targets: say so, rather than naming only the first.
                  CASE WHEN COALESCE(b.ref_ids,'') LIKE '%,%'
                       THEN CONCAT(' +', LENGTH(b.ref_ids) - LENGTH(REPLACE(b.ref_ids, ',', '')), ' more')
                       ELSE '' END
                ) AS course_name
           FROM wa_broadcasts b WHERE b.id = $id LIMIT 1");
    return $res ? mysqli_fetch_assoc($res) : null;
}

/**
 * Per-recipient rows for a broadcast: number, name, status, time and (for
 * failures) the reason pulled from the stored 360dialog response.
 * $statusFilter = '' (all) | 'failed' | 'read' | 'delivered' | 'sent'.
 */
function wa_broadcast_recipients($conn, $id, $statusFilter = '') {
    $id = (int)$id;
    $where = "m.broadcast_id = $id";
    if (in_array($statusFilter, ['failed', 'read', 'delivered', 'sent'], true)) {
        $where .= " AND m.status = '" . $statusFilter . "'";
    }
    $res = mysqli_query($conn,
        "SELECT m.status, m.created_at, m.raw_payload, c.wa_id, c.profile_name
           FROM wa_messages m
           JOIN wa_contacts c ON c.id = m.contact_id
          WHERE $where
       ORDER BY (m.status = 'failed') DESC, m.id ASC");
    $rows = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $reason = '';
            if (($r['status'] ?? '') === 'failed' && !empty($r['raw_payload'])) {
                $p = json_decode($r['raw_payload'], true);
                $reason = $p['response']['error']['message']
                    ?? ($p['response']['error']['error_data']['details'] ?? ($p['response']['errors'][0]['detail'] ?? ''));
            }
            $rows[] = [
                'wa_id'  => $r['wa_id'],
                'name'   => $r['profile_name'] ?: '',
                'status' => $r['status'] ?: 'sent',
                'time'   => $r['created_at'],
                'reason' => $reason,
            ];
        }
    }
    return $rows;
}

/**
 * Send a template to a whole resolved audience, server-side (used by the cron
 * runner). Resolves the audience NOW (so opt-outs are honoured at send time),
 * opens a wa_broadcasts run, sends to each recipient with {name} substitution,
 * tags each message, and returns the run id + counts.
 */
function wa_broadcast_execute($conn, $template, $lang, $vars, $audience, $courseId, $createdBy, $headerMediaId = '', $headerType = '') {
    $lang = $lang ?: 'en';
    $vars = is_array($vars) ? $vars : [];
    $list = wa_broadcast_audience($conn, $audience, $courseId);
    $total = count($list);
    $bid = wa_broadcast_create($conn, $template, $lang, $audience, $courseId, $total, $createdBy);
    $sent = 0; $failed = 0; $lastErr = '';
    $GLOBALS['WA_BROADCAST_ID'] = $bid;   // tag every message (sent or failed) to this run
    foreach ($list as $item) {
        $waId = $item['wa_id'] ?? '';
        if ($waId === '') { continue; }
        $params = array_map(function ($v) use ($item) { return wa_broadcast_fill($v, $item); }, $vars);
        $components = wa_broadcast_components($params, (string)$headerMediaId, (string)$headerType);
        $r = wa_send_template($conn, $waId, $template, $lang, $components);
        if (!empty($r['ok'])) { $sent++; }
        else { $failed++; $lastErr = (string)($r['error'] ?? 'unknown'); }
        usleep(120000);   // ~8/sec, gentle on the rate limit
    }
    unset($GLOBALS['WA_BROADCAST_ID']);
    if ($lastErr !== '') { wa_broadcast_set_error($conn, $bid, $lastErr); }
    return ['ok' => true, 'broadcast_id' => $bid, 'total' => $total, 'sent' => $sent, 'failed' => $failed, 'error' => $lastErr];
}

/* ------------------------------------------------------------------------------------
 * Large-scale broadcast QUEUE — reliable background delivery for tens of thousands.
 * A broadcast is snapshotted into wa_broadcast_queue (one row per recipient), then the
 * cron drains it in batches. It survives the browser closing, timeouts and restarts:
 * every row is marked the instant it's sent, so an interrupted run just resumes.
 * ---------------------------------------------------------------------------------- */

/** Idempotent schema for the broadcast queue + the run columns the cron needs. */
function wa_broadcast_queue_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    wa_broadcast_error_schema_ensure($conn);
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wa_broadcast_queue` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `broadcast_id` INT UNSIGNED NOT NULL,
        `wa_id` VARCHAR(32) NOT NULL,
        `name` VARCHAR(190) DEFAULT NULL,
        `course` VARCHAR(255) DEFAULT NULL,
        `link` VARCHAR(255) DEFAULT NULL,
        `rep` VARCHAR(190) DEFAULT NULL,
        `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
        `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `next_attempt_at` DATETIME DEFAULT NULL,
        `error` VARCHAR(255) DEFAULT NULL,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_bq_pick` (`broadcast_id`, `status`, `id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Existing installs: add the retry columns in place.
    @mysqli_query($conn, "ALTER TABLE `wa_broadcast_queue`
        ADD COLUMN IF NOT EXISTS `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `next_attempt_at` DATETIME DEFAULT NULL");
    @mysqli_query($conn, "ALTER TABLE `wa_broadcasts`
        ADD COLUMN IF NOT EXISTS `vars` TEXT NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `header_media_id` VARCHAR(255) NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `header_type` VARCHAR(16) NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `queue_status` VARCHAR(16) NOT NULL DEFAULT 'none'");
}

/**
 * Queue a broadcast for reliable background delivery. Snapshots the whole audience into
 * wa_broadcast_queue and stores what the cron needs (template, vars, flier). Returns the
 * run id + recipient count. The cron (wa_run_broadcast_queue) does the actual sending.
 */
function wa_broadcast_enqueue($conn, $template, $lang, $vars, $audience, $courseId, $createdBy, $headerMediaId = '', $headerType = '') {
    wa_broadcast_queue_schema_ensure($conn);
    $lang = $lang ?: 'en';
    $list = wa_broadcast_audience($conn, $audience, $courseId);
    $total = count($list);
    $bid = wa_broadcast_create($conn, $template, $lang, $audience, $courseId, $total, $createdBy);
    $v  = "'" . mysqli_real_escape_string($conn, json_encode(array_values((array)$vars), JSON_UNESCAPED_UNICODE)) . "'";
    $hm = trim((string)$headerMediaId) !== '' ? "'" . mysqli_real_escape_string($conn, $headerMediaId) . "'" : 'NULL';
    $ht = in_array($headerType, ['image', 'video', 'document'], true) ? "'" . $headerType . "'" : 'NULL';
    mysqli_query($conn, "UPDATE wa_broadcasts SET vars = $v, header_media_id = $hm, header_type = $ht, queue_status = 'queued' WHERE id = " . (int)$bid);
    // Bulk-insert recipients (500 per statement) — fast even for 70k.
    $rows = [];
    foreach ($list as $it) {
        $wa = trim((string)($it['wa_id'] ?? ''));
        if ($wa === '') { continue; }
        $rows[] = "(" . (int)$bid . ", '" . mysqli_real_escape_string($conn, $wa) . "', "
            . "'" . mysqli_real_escape_string($conn, (string)($it['name'] ?? '')) . "', "
            . "'" . mysqli_real_escape_string($conn, (string)($it['course'] ?? '')) . "', "
            . "'" . mysqli_real_escape_string($conn, (string)($it['link'] ?? '')) . "', "
            . "'" . mysqli_real_escape_string($conn, (string)($it['rep'] ?? '')) . "')";
        if (count($rows) >= 500) {
            mysqli_query($conn, "INSERT INTO wa_broadcast_queue (broadcast_id, wa_id, name, course, link, rep) VALUES " . implode(',', $rows));
            $rows = [];
        }
    }
    if ($rows) { mysqli_query($conn, "INSERT INTO wa_broadcast_queue (broadcast_id, wa_id, name, course, link, rep) VALUES " . implode(',', $rows)); }
    return ['ok' => true, 'broadcast_id' => $bid, 'total' => $total];
}

/**
 * Send one batch of queued rows CONCURRENTLY (curl_multi), $concurrency at a time — the
 * big throughput win for large broadcasts. Saves each outbound (tagged to the run via the
 * caller's $GLOBALS['WA_BROADCAST_ID']) and marks its queue row sent/failed. Returns counts.
 */
/** How many times a transiently-failed recipient is retried before giving up. */
if (!defined('WA_BCAST_MAX_ATTEMPTS')) { define('WA_BCAST_MAX_ATTEMPTS', 5); }

/**
 * Is this send failure worth retrying?
 *
 * Meta throttles by throughput and answers with a transient error — the SAME
 * recipient succeeds moments later. Treating those as permanent silently drops
 * people from a broadcast, so they must go back on the queue. Everything else
 * (invalid number, unapproved template, closed 24h window) will fail identically
 * on every retry, so it is marked failed immediately rather than burning quota.
 *
 *   130429  rate limit hit — Cloud API throughput reached
 *   131056  pair rate limit — too many messages to this one recipient
 *   80007   business-account rate limit
 *   133016  account temporarily blocked/restricted, clears on its own
 *   HTTP 429  throttled;  HTTP 5xx  Meta-side fault;  HTTP 0  network/curl failure
 */
function wa_broadcast_is_retryable($code, $httpStatus) {
    if (in_array((int) $code, [130429, 131056, 80007, 133016], true)) { return true; }
    $s = (int) $httpStatus;
    return ($s === 429 || $s >= 500 || $s === 0);
}

/** Is this specifically a throughput throttle? Drives the adaptive slow-down. */
function wa_broadcast_is_throttle($code, $httpStatus) {
    return in_array((int) $code, [130429, 131056, 80007], true) || (int) $httpStatus === 429;
}

/** Exponential backoff, in seconds, for attempt N (1-based). */
function wa_broadcast_retry_delay($attempt) {
    $ladder = [30, 60, 120, 300, 600];
    $i = max(1, (int) $attempt) - 1;
    return $ladder[min($i, count($ladder) - 1)];
}

function wa_broadcast_send_batch($conn, $bid, $rows, $template, $lang, $vars, $hm, $ht, $concurrency = 10) {
    $url = rtrim(WA_DIALOG_URL, '/') . '/messages';
    $headers = ['Content-Type: application/json', 'D360-API-KEY: ' . WA_DIALOG_KEY];
    // Resolve wa_id -> contact_id once so we can store each outbound.
    $cmap = [];
    $ids = [];
    foreach ($rows as $r) { $w = trim((string)$r['wa_id']); if ($w !== '') { $ids[] = "'" . mysqli_real_escape_string($conn, $w) . "'"; } }
    if ($ids) {
        $cr = mysqli_query($conn, "SELECT id, wa_id FROM wa_contacts WHERE wa_id IN (" . implode(',', $ids) . ")");
        if ($cr) { while ($c = mysqli_fetch_assoc($cr)) { $cmap[(string)$c['wa_id']] = (int)$c['id']; } }
    }
    $sent = 0; $failed = 0; $retried = 0; $lastErr = '';

    // Adaptive pacing. Firing every window flat-out is what provokes 130429 in the
    // first place, so a throttled window halves the in-flight count and doubles the
    // pause; clean windows walk both back toward the configured maximum. This keeps
    // throughput high when Meta is happy and self-corrects the moment it is not.
    $queue   = array_values($rows);
    $total   = count($queue);
    $maxWin  = max(1, (int) $concurrency);
    $win     = $maxWin;
    $pauseMs = max(0, min(5000, (int) wa_setting_get($conn, 'broadcast_window_pause_ms', '200')));
    $basePause = $pauseMs;
    $offset  = 0;

    while ($offset < $total) {
        $window  = array_slice($queue, $offset, $win);
        $offset += count($window);
        $throttled = 0;

        $mh = curl_multi_init();
        $handles = [];
        foreach ($window as $row) {
            $params = array_map(function ($v) use ($row) { return wa_broadcast_fill($v, $row); }, $vars);
            $components = wa_broadcast_components($params, $hm, $ht);
            $tpl = ['name' => $template, 'language' => ['code' => $lang]];
            if ($components) { $tpl['components'] = $components; }
            $payload = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual',
                        'to' => (string)$row['wa_id'], 'type' => 'template', 'template' => $tpl];
            $display = wa_template_rendered($conn, $template, $lang, $components);
            if (trim($display) === '') { $display = "[template:{$template}/{$lang}]"; }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[] = ['ch' => $ch, 'row' => $row, 'payload' => $payload, 'display' => $display];
        }
        $running = null;
        do {
            $mrc = curl_multi_exec($mh, $running);
            if ($running) { curl_multi_select($mh, 1.0); }
        } while ($running > 0 && $mrc === CURLM_OK);

        foreach ($handles as $h) {
            $raw = curl_multi_getcontent($h['ch']);
            $status = (int)curl_getinfo($h['ch'], CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $h['ch']); curl_close($h['ch']);
            $data = json_decode((string)$raw, true);
            $wamid = $data['messages'][0]['id'] ?? null;
            $ok = $status >= 200 && $status < 300 && $wamid !== null;
            $qid = (int)$h['row']['id'];
            $cid = $cmap[(string)$h['row']['wa_id']] ?? 0;

            $errCode  = (int)($data['error']['code'] ?? 0);
            $attempts = (int)($h['row']['attempts'] ?? 0) + 1;
            $willRetry = !$ok
                && wa_broadcast_is_retryable($errCode, $status)
                && $attempts < WA_BCAST_MAX_ATTEMPTS;

            // Only record an outbound message for a settled result. A transient
            // throttle that we are about to retry is not a failed message to the
            // customer, and logging one would litter the chat thread with every retry.
            if ($cid > 0 && !$willRetry) {
                wa_save_outbound($conn, $cid, ['wa_message_id' => $wamid, 'type' => 'template', 'body' => $h['display'],
                    'status' => $ok ? 'sent' : 'failed',
                    'raw_payload' => ['request' => $h['payload'], 'response' => $data, 'http' => $status]]);
            }

            if ($ok) {
                mysqli_query($conn, "UPDATE wa_broadcast_queue
                    SET status = 'sent', attempts = $attempts, error = NULL, next_attempt_at = NULL
                    WHERE id = $qid");
                $sent++;
                continue;
            }

            $lastErr = $data['error']['message'] ?? ('HTTP ' . $status);
            if ($errCode > 0) { $lastErr = '(#' . $errCode . ') ' . $lastErr; }
            $e = "'" . mysqli_real_escape_string($conn, mb_substr($lastErr, 0, 240)) . "'";

            if (wa_broadcast_is_throttle($errCode, $status)) { $throttled++; }

            if ($willRetry) {
                // Back on the queue: still 'pending', but not picked up again until
                // the backoff elapses.
                $delay = wa_broadcast_retry_delay($attempts);
                mysqli_query($conn, "UPDATE wa_broadcast_queue
                    SET status = 'pending', attempts = $attempts, error = $e,
                        next_attempt_at = DATE_ADD(NOW(), INTERVAL $delay SECOND)
                    WHERE id = $qid");
                $retried++;
            } else {
                mysqli_query($conn, "UPDATE wa_broadcast_queue
                    SET status = 'failed', attempts = $attempts, error = $e, next_attempt_at = NULL
                    WHERE id = $qid");
                $failed++;
            }
        }
        curl_multi_close($mh);

        // Adapt to what that window just told us, then pause before the next one.
        if ($throttled > 0) {
            $win     = max(1, (int) floor($win / 2));
            $pauseMs = (int) min(5000, max(250, ($pauseMs > 0 ? $pauseMs : 250) * 2));
            error_log('[wa-broadcast] throttled on ' . $throttled . ' message(s) — concurrency now '
                . $win . ', pause ' . $pauseMs . 'ms');
        } elseif ($win < $maxWin) {
            $win     = min($maxWin, $win + 1);
            $pauseMs = (int) max($basePause, (int) floor($pauseMs / 2));
        }
        if ($offset < $total && $pauseMs > 0) { usleep($pauseMs * 1000); }
    }

    if ($lastErr !== '') { wa_broadcast_set_error($conn, $bid, $lastErr); }
    return ['sent' => $sent, 'failed' => $failed, 'retrying' => $retried];
}

/**
 * Cron worker: drain pending queue rows for up to $maxSeconds, sending each template.
 * A MySQL named lock ensures only ONE drainer runs at a time (so overlapping every-minute
 * ticks never double-send), and each row is marked the moment it's sent so an interrupted
 * run resumes cleanly. Tagging via $GLOBALS['WA_BROADCAST_ID'] keeps the delivery report live.
 */
function wa_run_broadcast_queue($conn, $maxSeconds = 45, $batch = 150) {
    wa_broadcast_queue_schema_ensure($conn);
    $lk = mysqli_query($conn, "SELECT GET_LOCK('wa_bcast_queue', 0) AS g");
    if (!$lk || (int)(mysqli_fetch_assoc($lk)['g'] ?? 0) !== 1) { return ['ok' => true, 'skipped' => 'locked']; }
    $deadline = time() + max(5, (int)$maxSeconds);
    $concurrency = max(1, min(30, (int)wa_setting_get($conn, 'broadcast_concurrency', '10')));
    $sent = 0; $failed = 0; $retrying = 0; $doneRuns = []; $waiting = [];
    try {
        while (time() < $deadline) {
            // Only consider a broadcast that has a recipient ready NOW: rows inside
            // their retry backoff are pending but not yet due. $waiting excludes any
            // broadcast this tick already found nothing sendable for, so the loop can
            // never spin on it until the deadline.
            $skip = $waiting ? ' AND b.id NOT IN (' . implode(',', array_map('intval', $waiting)) . ')' : '';
            $br = mysqli_query($conn,
                "SELECT b.id, b.template, b.language, b.vars, b.header_media_id, b.header_type
                   FROM wa_broadcasts b
                  WHERE b.queue_status IN ('queued','sending')
                    AND EXISTS (SELECT 1 FROM wa_broadcast_queue q
                                 WHERE q.broadcast_id = b.id AND q.status = 'pending'
                                   AND (q.next_attempt_at IS NULL OR q.next_attempt_at <= NOW()))
                    $skip
                  ORDER BY b.id ASC LIMIT 1");
            $run = $br ? mysqli_fetch_assoc($br) : null;
            if (!$run) { break; }
            $bid = (int)$run['id'];
            mysqli_query($conn, "UPDATE wa_broadcasts SET queue_status = 'sending' WHERE id = $bid AND queue_status = 'queued'");
            $vars = json_decode((string)$run['vars'], true) ?: [];
            $hm = (string)($run['header_media_id'] ?? ''); $ht = (string)($run['header_type'] ?? '');
            $GLOBALS['WA_BROADCAST_ID'] = $bid;   // tag every message this batch produces
            $rows = [];
            $rs = mysqli_query($conn, "SELECT id, wa_id, name, course, link, rep, attempts FROM wa_broadcast_queue
                WHERE broadcast_id = $bid AND status = 'pending'
                  AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
                ORDER BY id ASC LIMIT " . (int)$batch);
            if ($rs) { while ($r = mysqli_fetch_assoc($rs)) { $rows[] = $r; } }
            if (!$rows) {
                // Everything left for this broadcast is inside its backoff — leave it
                // for a later tick and move on to any other broadcast.
                unset($GLOBALS['WA_BROADCAST_ID']);
                $waiting[] = $bid;
                continue;
            }
            $r = wa_broadcast_send_batch($conn, $bid, $rows, (string)$run['template'], (string)$run['language'],
                                         $vars, $hm, $ht, $concurrency);
            $sent += (int)$r['sent']; $failed += (int)$r['failed']; $retrying += (int)($r['retrying'] ?? 0);
            unset($GLOBALS['WA_BROADCAST_ID']);
            // Done only when nothing is pending at all — a row awaiting retry is still
            // pending, so a run with outstanding retries is never reported as finished.
            $left = (int)wa_scalar($conn, "SELECT COUNT(*) FROM wa_broadcast_queue WHERE broadcast_id = $bid AND status = 'pending'");
            if ($left === 0) { mysqli_query($conn, "UPDATE wa_broadcasts SET queue_status = 'done' WHERE id = $bid"); $doneRuns[] = $bid; }
        }
    } finally {
        unset($GLOBALS['WA_BROADCAST_ID']);
        mysqli_query($conn, "SELECT RELEASE_LOCK('wa_bcast_queue')");
    }
    return ['ok' => true, 'sent' => $sent, 'failed' => $failed, 'retrying' => $retrying,
            'waiting' => $waiting, 'done' => $doneRuns];
}

/* ------------------------------------------------------------------------------------
 * Bulk contact import (CSV) with AI column detection.
 * ---------------------------------------------------------------------------------- */

/** Read a CSV into ['headers'=>[...], 'rows'=>[['Header'=>val,...], ...]]. Strips a BOM and
 *  skips blank lines. (Export/"Save As" an Excel sheet to CSV first.) */
function wa_csv_parse_file($path, $maxRows = 200000) {
    $headers = []; $rows = [];
    $fh = @fopen($path, 'r');
    if (!$fh) { return ['headers' => [], 'rows' => []]; }
    $first = true;
    while (($data = fgetcsv($fh, 0, ',')) !== false) {
        if ($first) {
            if (isset($data[0])) { $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$data[0]); }
            $headers = array_map(function ($h) { return trim((string)$h); }, $data);
            $first = false; continue;
        }
        $nonEmpty = false;
        foreach ($data as $c) { if (trim((string)$c) !== '') { $nonEmpty = true; break; } }
        if (!$nonEmpty) { continue; }
        $row = [];
        foreach ($headers as $i => $h) { if ($h !== '') { $row[$h] = isset($data[$i]) ? (string)$data[$i] : ''; } }
        $rows[] = $row;
        if (count($rows) >= $maxRows) { break; }
    }
    fclose($fh);
    return ['headers' => $headers, 'rows' => $rows];
}

/** Keyword heuristic mapping of our fields -> a source header. */
function wa_import_guess_columns($headers) {
    $map = ['phone' => '', 'name' => '', 'email' => '', 'country' => ''];
    foreach ($headers as $h) {
        $l = strtolower(trim($h));
        if ($map['phone'] === '' && preg_match('/phone|mobile|tel|whats|msisdn|cell|number|contact/', $l)) { $map['phone'] = $h; }
        if ($map['email'] === '' && strpos($l, 'mail') !== false) { $map['email'] = $h; }
        if ($map['country'] === '' && (strpos($l, 'country') !== false || strpos($l, 'nation') !== false)) { $map['country'] = $h; }
        if ($map['name'] === '' && preg_match('/\bname\b|full ?name|fullname|first ?name|client|customer/', $l)) { $map['name'] = $h; }
    }
    return $map;
}

/** Map spreadsheet columns to our contact fields — heuristic, refined by AI when available. */
function wa_import_map_columns($conn, $headers, $sampleRows) {
    $map = wa_import_guess_columns($headers);
    $provider = wa_active_provider($conn);
    if (wa_provider_ready($provider)) {
        $system = 'You map spreadsheet columns to contact fields. Fields: phone (WhatsApp / mobile number, REQUIRED), '
                . 'name, email, country. Given the column headers and a few sample rows, reply with ONLY JSON mapping each '
                . 'field to the EXACT column header that best fits, or null if none. '
                . 'Example: {"phone":"Mobile No","name":"Full Name","email":"Email","country":null}.';
        $user = "Headers: " . json_encode(array_values($headers)) . "\nSample rows: " . json_encode(array_slice($sampleRows, 0, 5));
        $ans = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]], ['json' => true, 'max_tokens' => 200]);
        if (!empty($ans['ok'])) {
            $data = wa_json_extract($ans['text'] ?? '');
            if (is_array($data)) {
                foreach (['phone', 'name', 'email', 'country'] as $f) {
                    if (isset($data[$f]) && $data[$f] !== null && in_array($data[$f], $headers, true)) { $map[$f] = $data[$f]; }
                }
            }
        }
    }
    return $map;
}

/** Normalise a raw number to a WhatsApp wa_id (digits, international, no +). Local numbers
 *  (leading 0 or short) get the default country code; already-international ones are kept. */
function wa_import_normalize_phone($raw, $defaultCc = '254') {
    $s = trim((string)$raw);
    $s = preg_replace('/\.\d+$/', '', $s);      // drop an Excel decimal fraction, e.g. "…678.0"
    $d = preg_replace('/\D+/', '', $s);
    if ($d === '') { return ''; }
    if (strpos($d, '00') === 0) { $d = substr($d, 2); }            // 00 international prefix
    $cc = preg_replace('/\D+/', '', (string)$defaultCc); if ($cc === '') { $cc = '254'; }
    if ($d[0] === '0') { $d = $cc . substr($d, 1); }               // 0712… -> 254712…
    elseif (strlen($d) <= 10 && strpos($d, $cc) !== 0) { $d = $cc . $d; }   // 712… -> 254712…
    return $d;
}

/** Read just the FIRST column of a CSV (values only) — for a direct "first column is the
 *  phone number" import with no AI/mapping. Skips blanks; a header like "Phone" normalises
 *  to a non-number and is skipped automatically. */
function wa_csv_first_column($path, $maxRows = 500000) {
    $out = [];
    $fh = @fopen($path, 'r');
    if (!$fh) { return $out; }
    while (($data = fgetcsv($fh, 0, ',')) !== false) {
        $v = isset($data[0]) ? trim((string)$data[0]) : '';
        $v = preg_replace('/^\xEF\xBB\xBF/', '', $v);   // strip BOM on the very first cell
        if ($v !== '') { $out[] = $v; }
        if (count($out) >= $maxRows) { break; }
    }
    fclose($fh);
    return $out;
}

/** Import a plain list of phone numbers into wa_contacts (no names/AI). Upserts by wa_id. */
function wa_import_phones($conn, $phones, $defaultCc = '254', $optIn = false, $batchId = 0) {
    $imported = 0; $updated = 0; $bad = 0; $samples = [];
    foreach ($phones as $raw) {
        $phone = wa_import_normalize_phone($raw, $defaultCc);
        if (strlen($phone) < 9 || strlen($phone) > 15) {
            $bad++;
            // Keep a few examples of what was skipped so the cause is visible (header row,
            // blank, Excel scientific-notation like "2.5E+11", junk, etc.).
            if (count($samples) < 15) { $samples[] = mb_substr(trim((string)$raw), 0, 40); }
            continue;
        }
        $existing = wa_find_contact_by_waid($conn, $phone);
        $cid = wa_upsert_contact($conn, $phone, null);
        if ($cid > 0) {
            wa_contact_stamp_import($conn, $cid, $optIn, $batchId);
            if ($existing) { $updated++; } else { $imported++; }
        } else { $bad++; }
    }
    return ['ok' => true, 'imported' => $imported, 'updated' => $updated, 'bad' => $bad,
            'total' => count($phones), 'skipped_samples' => $samples];
}

/** Import parsed rows into wa_contacts using a field->column map. Upserts by wa_id. */
function wa_import_contacts($conn, $rows, $map, $defaultCc = '254', $optIn = false, $batchId = 0) {
    wa_contact_email_ensure($conn);
    $phoneCol = $map['phone'] ?? '';
    if ($phoneCol === '') { return ['ok' => false, 'error' => 'No phone column selected.']; }
    $imported = 0; $updated = 0; $bad = 0;
    foreach ($rows as $r) {
        $phone = wa_import_normalize_phone($r[$phoneCol] ?? '', $defaultCc);
        if (strlen($phone) < 9 || strlen($phone) > 15) { $bad++; continue; }
        $name  = ($map['name'] ?? '')  !== '' ? trim((string)($r[$map['name']] ?? '')) : '';
        $email = ($map['email'] ?? '') !== '' ? trim((string)($r[$map['email']] ?? '')) : '';
        $existing = wa_find_contact_by_waid($conn, $phone);
        $cid = wa_upsert_contact($conn, $phone, $name !== '' ? $name : null);
        if ($cid > 0) {
            if ($email !== '') { wa_contact_set_email($conn, $cid, $email); }
            wa_contact_stamp_import($conn, $cid, $optIn, $batchId);
            if ($existing) { $updated++; } else { $imported++; }
        } else { $bad++; }
    }
    return ['ok' => true, 'imported' => $imported, 'updated' => $updated, 'bad' => $bad, 'total' => count($rows)];
}

/* ---- Import batches: tag each import so a broadcast can target that exact list ---- */

/** Idempotent schema: a batches table + import_batch_id on wa_contacts. */
function wa_import_batch_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wa_import_batches` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `label` VARCHAR(190) NOT NULL,
        `source` VARCHAR(32) DEFAULT NULL,
        `total` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_by` INT UNSIGNED DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    @mysqli_query($conn, "ALTER TABLE `wa_contacts` ADD COLUMN IF NOT EXISTS `import_batch_id` INT UNSIGNED NULL DEFAULT NULL");
    @mysqli_query($conn, "ALTER TABLE `wa_contacts` ADD INDEX IF NOT EXISTS `idx_wa_contacts_batch` (`import_batch_id`)");
}

/** Start an import batch; returns its id. */
function wa_import_batch_create($conn, $label, $source, $createdBy) {
    wa_import_batch_schema_ensure($conn);
    $l  = "'" . mysqli_real_escape_string($conn, ($label !== '' ? $label : 'Import')) . "'";
    $s  = "'" . mysqli_real_escape_string($conn, (string)$source) . "'";
    $by = ((int)$createdBy > 0) ? (int)$createdBy : 'NULL';
    mysqli_query($conn, "INSERT INTO wa_import_batches (label, source, created_by) VALUES ($l, $s, $by)");
    return (int)mysqli_insert_id($conn);
}

/** Record the final imported count on a batch. */
function wa_import_batch_finalize($conn, $batchId, $total) {
    if ((int)$batchId < 1) { return; }
    mysqli_query($conn, "UPDATE wa_import_batches SET total = " . (int)$total . " WHERE id = " . (int)$batchId);
}

/** Batches for the broadcast dropdown, newest first, with their live contact count. */
function wa_import_batches_list($conn, $limit = 100) {
    wa_import_batch_schema_ensure($conn);
    $limit = (int)$limit;
    $res = mysqli_query($conn,
        "SELECT b.id, b.label, b.created_at,
                (SELECT COUNT(*) FROM wa_contacts c WHERE c.import_batch_id = b.id AND c.opted_out = 0) AS n
           FROM wa_import_batches b
       ORDER BY b.id DESC LIMIT $limit");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

/** Stamp opt-in and/or the import batch on a just-imported contact (new OR existing). */
function wa_contact_stamp_import($conn, $cid, $optIn, $batchId) {
    $sets = [];
    if ($optIn) { $sets[] = "opted_in = 1"; }
    if ((int)$batchId > 0) { $sets[] = "import_batch_id = " . (int)$batchId; }
    if ($sets) { mysqli_query($conn, "UPDATE wa_contacts SET " . implode(', ', $sets) . " WHERE id = " . (int)$cid); }
}

/** Queue a broadcast for a future time. $scheduledAt is 'Y-m-d H:i:s'. Returns id. */
/** Add the multi-target column to scheduled broadcasts once (idempotent). */
function wa_scheduled_refids_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_scheduled_broadcasts`
        ADD COLUMN IF NOT EXISTS `ref_ids` VARCHAR(255) DEFAULT NULL");
}

function wa_broadcast_schedule($conn, $template, $lang, $vars, $audience, $courseId, $scheduledAt, $createdBy, $headerMediaId = '', $headerType = '') {
    wa_broadcast_header_schema_ensure($conn);
    wa_scheduled_refids_schema_ensure($conn);
    $t   = "'" . mysqli_real_escape_string($conn, $template) . "'";
    $l   = "'" . mysqli_real_escape_string($conn, $lang ?: 'en') . "'";
    $a   = "'" . mysqli_real_escape_string($conn, $audience ?: 'all') . "'";
    // Keep the WHOLE selection: course_id is INT and would silently drop every course
    // after the first, so a multi-course schedule would fire at a fraction of its audience.
    $ids  = wa_ref_ids($courseId);
    $cid  = $ids ? $ids[0] : 'NULL';
    $rids = $ids ? "'" . implode(',', $ids) . "'" : 'NULL';
    $v   = "'" . mysqli_real_escape_string($conn, json_encode(array_values((array)$vars), JSON_UNESCAPED_UNICODE)) . "'";
    $sa  = "'" . mysqli_real_escape_string($conn, $scheduledAt) . "'";
    $by  = ((int)$createdBy > 0) ? (int)$createdBy : 'NULL';
    $hm  = trim((string)$headerMediaId) !== '' ? "'" . mysqli_real_escape_string($conn, $headerMediaId) . "'" : 'NULL';
    $ht  = in_array($headerType, ['image', 'video', 'document'], true) ? "'" . $headerType . "'" : 'NULL';
    mysqli_query($conn,
        "INSERT INTO wa_scheduled_broadcasts (template, language, audience, course_id, ref_ids, vars, scheduled_at, created_by, header_media_id, header_type)
         VALUES ($t, $l, $a, $cid, $rids, $v, $sa, $by, $hm, $ht)");
    return (int)mysqli_insert_id($conn);
}

/** Scheduled broadcasts for the management list (most recent / soonest first). */
function wa_scheduled_list($conn, $limit = 100) {
    $limit = (int)$limit;
    $res = mysqli_query($conn,
        "SELECT s.*, CASE s.audience
                     WHEN 'course' THEN (SELECT course FROM course WHERE course_id = s.course_id)
                     WHEN 'event'  THEN (SELECT event_title FROM `Event` WHERE event_id = s.course_id)
                END AS course_name
           FROM wa_scheduled_broadcasts s
       ORDER BY (s.status = 'pending') DESC, s.scheduled_at DESC
          LIMIT $limit");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

/** Cancel a pending scheduled broadcast. */
function wa_scheduled_cancel($conn, $id) {
    $id = (int)$id;
    mysqli_query($conn, "UPDATE wa_scheduled_broadcasts SET status = 'cancelled' WHERE id = $id AND status = 'pending'");
    return mysqli_affected_rows($conn) > 0;
}

/**
 * Execute all due scheduled broadcasts (called by wa_cron.php). Claims each row
 * atomically (pending -> sending) so overlapping cron runs never double-send.
 * Returns a summary array.
 */
function wa_run_due_scheduled($conn, $limit = 5) {
    $limit = (int)$limit;
    $res = mysqli_query($conn,
        "SELECT id FROM wa_scheduled_broadcasts
          WHERE status = 'pending' AND scheduled_at <= NOW()
       ORDER BY scheduled_at ASC LIMIT $limit");
    $ids = [];
    if ($res) { while ($r = mysqli_fetch_row($res)) { $ids[] = (int)$r[0]; } }

    $done = [];
    foreach ($ids as $id) {
        // Atomically claim it; skip if another run already grabbed it.
        mysqli_query($conn, "UPDATE wa_scheduled_broadcasts SET status = 'sending', run_at = NOW() WHERE id = $id AND status = 'pending'");
        if (mysqli_affected_rows($conn) < 1) { continue; }

        $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM wa_scheduled_broadcasts WHERE id = $id LIMIT 1"));
        if (!$row) { continue; }
        try {
            $vars = json_decode((string)$row['vars'], true) ?: [];
            // Enqueue for reliable background delivery instead of sending all at once — a
            // large scheduled send then drains over many cron ticks (see wa_run_broadcast_queue).
            $r = wa_broadcast_enqueue($conn, $row['template'], $row['language'], $vars,
                                      $row['audience'],
                                      // ref_ids holds the full selection; course_id only the first.
                                      (trim((string)($row['ref_ids'] ?? '')) !== '' ? $row['ref_ids'] : $row['course_id']),
                                      $row['created_by'],
                                      (string)($row['header_media_id'] ?? ''), (string)($row['header_type'] ?? ''));
            $bid = (int)$r['broadcast_id']; $total = (int)$r['total'];
            mysqli_query($conn,
                "UPDATE wa_scheduled_broadcasts
                    SET status = 'sent', broadcast_id = $bid, total = $total, error = NULL
                  WHERE id = $id");
            $done[] = ['id' => $id, 'queued' => $total];
        } catch (Throwable $e) {
            $err = "'" . mysqli_real_escape_string($conn, substr($e->getMessage(), 0, 240)) . "'";
            mysqli_query($conn, "UPDATE wa_scheduled_broadcasts SET status = 'failed', error = $err WHERE id = $id");
            error_log('[wa-cron] scheduled ' . $id . ' failed: ' . $e->getMessage());
            $done[] = ['id' => $id, 'error' => $e->getMessage()];
        }
    }
    return ['ok' => true, 'ran' => count($done), 'runs' => $done];
}

// ---- Canned quick replies -------------------------------------------------

/** All quick replies for the management page, with their course/event name (global first). */
function wa_quick_replies_list($conn) {
    $res = mysqli_query($conn,
        "SELECT q.*,
                CASE q.ref_type
                     WHEN 'course' THEN (SELECT course FROM course WHERE course_id = q.ref_id)
                     WHEN 'event'  THEN (SELECT event_title FROM `Event` WHERE event_id = q.ref_id)
                END AS ref_name
           FROM wa_quick_replies q
       ORDER BY (q.ref_type IS NOT NULL), q.ref_type, ref_name, q.sort, q.title");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

/**
 * Quick replies to show on a chat: the global ones plus the ones scoped to
 * this conversation's course/event (the scoped ones first, so they're leftmost).
 */
function wa_quick_replies_for($conn, $refType, $refId) {
    $refId = (int)$refId;
    $cond = 'ref_type IS NULL';
    if (in_array($refType, ['course', 'event'], true) && $refId > 0) {
        $cond .= " OR (ref_type = '" . $refType . "' AND ref_id = $refId)";
    }
    $res = mysqli_query($conn,
        "SELECT * FROM wa_quick_replies WHERE $cond ORDER BY (ref_type IS NULL), sort, title");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

function wa_quick_reply_save($conn, $id, $title, $body, $sort = 0, $refType = '', $refId = 0) {
    $title = trim((string)$title); $body = trim((string)$body);
    if ($title === '' || $body === '') { return false; }
    $t = "'" . mysqli_real_escape_string($conn, $title) . "'";
    $b = "'" . mysqli_real_escape_string($conn, $body) . "'";
    $s = (int)$sort;
    $valid = in_array($refType, ['course', 'event'], true) && (int)$refId > 0;
    $rt = $valid ? "'" . $refType . "'" : 'NULL';
    $ri = $valid ? (int)$refId : 'NULL';
    if ((int)$id > 0) {
        $id = (int)$id;
        mysqli_query($conn, "UPDATE wa_quick_replies SET title = $t, body = $b, sort = $s, ref_type = $rt, ref_id = $ri WHERE id = $id");
    } else {
        mysqli_query($conn, "INSERT INTO wa_quick_replies (title, body, sort, ref_type, ref_id) VALUES ($t, $b, $s, $rt, $ri)");
    }
    return true;
}

function wa_quick_reply_delete($conn, $id) {
    $id = (int)$id;
    mysqli_query($conn, "DELETE FROM wa_quick_replies WHERE id = $id");
}

/** AI-draft a template body from a plain-English description. */
function wa_ai_template_draft($conn, $description) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['ok' => false, 'error' => 'no_provider']; }
    $system =
        "You write WhatsApp message TEMPLATES (for Meta approval) for Vantage Africa School of Leadership, a "
        . "leadership-training organisation. From the user's description, write a concise, professional template "
        . "body. Use numbered placeholders {{1}}, {{2}} for personalised parts (name, course, date, amount). "
        . "Avoid content Meta would reject (no misleading claims, no forbidden categories). Also choose a category "
        . "(MARKETING, UTILITY or AUTHENTICATION) and suggest a short lowercase snake_case name. "
        . "Reply with ONLY JSON: {\"name\":\"...\",\"category\":\"MARKETING|UTILITY|AUTHENTICATION\",\"body\":\"...\"}.";
    $res = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => (string)$description]], ['json' => true, 'max_tokens' => 500]);
    if (empty($res['ok'])) { return ['ok' => false, 'error' => $res['error'] ?? 'ai_failed']; }
    $d = wa_json_extract($res['text']);
    if (!$d) { return ['ok' => false, 'error' => 'parse']; }
    return ['ok' => true, 'name' => $d['name'] ?? '', 'category' => strtoupper($d['category'] ?? 'MARKETING'), 'body' => trim((string)($d['body'] ?? ''))];
}

/** Submit a template to Meta (via 360dialog) for approval; store it locally. */
function wa_template_submit($conn, $name, $language, $category, $body) {
    // Meta requires an example value for every {{n}} variable in the body, or it
    // rejects the template. Count the variables and supply sample values.
    preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $mm);
    $nVars = $mm[1] ? max(array_map('intval', $mm[1])) : 0;
    $bodyComp = ['type' => 'BODY', 'text' => $body];
    if ($nVars > 0) {
        $samples = ['Jane Doe', 'Dorcas Mukami', 'the Senior Management Course', 'next week',
                    'USD 380', 'Nairobi', '8 August 2026', 'Vantage Africa'];
        $ex = [];
        for ($i = 0; $i < $nVars; $i++) { $ex[] = $samples[$i % count($samples)]; }
        $bodyComp['example'] = ['body_text' => [$ex]];
    }
    $payload = [
        'name'       => $name,
        'language'   => $language,
        'category'   => strtoupper($category ?: 'MARKETING'),
        'components' => [$bodyComp],
    ];
    $resp = wa_http_post(rtrim(WA_DIALOG_URL, '/') . '/v1/configs/templates',
        ['Content-Type: application/json', 'D360-API-KEY: ' . WA_DIALOG_KEY], $payload);
    $data = $resp['body'];
    if ($resp['status'] >= 200 && $resp['status'] < 300) {
        $s = strtolower($data['status'] ?? 'pending');
        $status = in_array($s, ['approved', 'pending', 'rejected'], true) ? $s : 'pending';
        wa_template_save($conn, $name, $language, $category, $body, $status);
        return ['ok' => true, 'status' => $status];
    }
    error_log('[wa-tpl] submit ' . $resp['status'] . ' ' . substr(json_encode($data), 0, 400));
    wa_template_save($conn, $name, $language, $category, $body, 'pending');   // keep the draft
    $err = $data['error']['message'] ?? ($data['errors'][0]['detail'] ?? ($data['meta']['developer_message'] ?? ('HTTP ' . $resp['status'])));
    return ['ok' => false, 'error' => $err];
}

/** Pull current template statuses from Meta (via 360dialog) into wa_templates. */
function wa_templates_sync($conn) {
    $resp = wa_http_get(rtrim(WA_DIALOG_URL, '/') . '/v1/configs/templates', 30, ['D360-API-KEY: ' . WA_DIALOG_KEY]);
    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        error_log('[wa-tpl] sync ' . $resp['status'] . ' ' . substr($resp['body'], 0, 400));
        // Surface 360dialog's actual message so the cause is visible (suspended
        // channel, wrong key type, etc.) instead of a bare status code.
        $d = json_decode($resp['body'], true);
        $msg = '';
        if (is_array($d)) {
            $msg = $d['error']['message'] ?? $d['message'] ?? ($d['errors'][0]['detail'] ?? ($d['meta']['developer_message'] ?? ''));
        }
        if ($msg === '') { $msg = trim(strip_tags((string)$resp['body'])); }
        return ['ok' => false, 'error' => 'http_' . $resp['status'] . ($msg !== '' ? ': ' . substr($msg, 0, 300) : '')];
    }
    $j = json_decode($resp['body'], true);
    $list = $j['waba_templates'] ?? $j['templates'] ?? ((is_array($j) && isset($j[0])) ? $j : []);
    if (!is_array($list)) { return ['ok' => false, 'error' => 'parse']; }
    $n = 0;
    foreach ($list as $t) {
        $name = $t['name'] ?? null;
        if (!$name) { continue; }
        $lang = is_array($t['language'] ?? null) ? ($t['language']['code'] ?? 'en') : ($t['language'] ?? 'en');
        $status = strtolower($t['status'] ?? 'pending');
        $status = in_array($status, ['approved', 'pending', 'rejected'], true) ? $status : 'pending';
        $cat = strtolower($t['category'] ?? '');
        $body = '';
        foreach (($t['components'] ?? []) as $c) {
            if (strtoupper($c['type'] ?? '') === 'BODY') { $body = $c['text'] ?? ''; }
        }
        wa_template_save($conn, $name, $lang, $cat, $body, $status);
        $n++;
    }
    return ['ok' => true, 'updated' => $n];
}

/**
 * Sync templates from the Hub at most once per $maxAge seconds (default 5 min),
 * so the Templates list and Broadcast dropdown stay current without an API call
 * on every page load. Records the last attempt so a failing key doesn't retry
 * every request. Returns the sync result, or null when skipped (still fresh).
 */
function wa_templates_autosync($conn, $maxAge = 300) {
    if (!defined('WA_DIALOG_KEY') || !WA_DIALOG_KEY || strpos(WA_DIALOG_KEY, 'YOUR_') === 0) { return null; }
    $last = (int)wa_setting_get($conn, 'templates_synced_at', '0');
    if ($last > 0 && (time() - $last) < (int)$maxAge) { return null; }
    // Stamp the attempt up-front so a slow/failing endpoint won't retry every load.
    wa_setting_set($conn, 'templates_synced_at', (string)time());
    return wa_templates_sync($conn);
}

// =====================================================================
// 24h window + sending (360dialog)
// =====================================================================

function wa_within_window($lastInbound) {
    return $lastInbound && (time() - strtotime($lastInbound)) < 24 * 3600;
}

/** How close to the window shutting still counts as "closing soon" (seconds). */
if (!defined('WA_CLOSING_SECS')) { define('WA_CLOSING_SECS', 3600); }

/**
 * SQL expression for the seconds left on a contact's 24-hour service window.
 * NULL when they have never written to us; zero or negative once it has shut.
 *
 * Computed in SQL on purpose: the inbox countdown must be anchored to server time.
 * A rep whose laptop clock is a few minutes fast would otherwise be told a window
 * is still open after it has closed, and their free-form reply would be rejected.
 */
function wa_window_left_sql($c = 'c') {
    return "TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD($c.last_inbound_at, INTERVAL 24 HOUR))";
}

/** Send a free-form text reply (enforces the 24h window unless $force). Records outbound. */
function wa_send_text($conn, $waId, $body, $force = false, $channel = null) {
    // Sandbox capture mode: the test console sets $GLOBALS['WA_CAPTURE'] to an
    // array; outbound text is recorded there instead of sent over 360dialog.
    if (isset($GLOBALS['WA_CAPTURE']) && is_array($GLOBALS['WA_CAPTURE'])) {
        $GLOBALS['WA_CAPTURE'][] = $body;
        return ['ok' => true, 'captured' => true];
    }
    $contact = wa_find_contact_by_waid($conn, $waId);
    $contactId = $contact['id'] ?? wa_upsert_contact($conn, $waId);
    // The window belongs to the BUSINESS NUMBER, so check the one we are about to
    // send from. Using the contact-wide timestamp would let a message to one line
    // authorise a free-form reply on the other, which WhatsApp then rejects.
    $sendChannel = ($channel !== null) ? $channel : wa_reply_channel($conn, (int)$contactId);
    if (!$force && !wa_channel_within_window($conn, (int)$contactId, $sendChannel,
                                             $contact['last_inbound_at'] ?? null)) {
        return ['ok' => false, 'error' => 'outside_24h_window'];
    }
    $payload = [
        'messaging_product' => 'whatsapp', 'recipient_type' => 'individual',
        'to' => $waId, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $body],
    ];
    return wa_dialog_dispatch($conn, (int)$contactId, 'text', $body, $payload, $sendChannel);
}

/** Render a template's actual text by filling its {{1}},{{2}}… from the body-parameter
 *  values in $components. Falls back to '' if the template body isn't known locally. */
function wa_template_rendered($conn, $name, $lang, $components) {
    $n = mysqli_real_escape_string($conn, (string)$name);
    $l = mysqli_real_escape_string($conn, (string)$lang);
    $res = mysqli_query($conn, "SELECT body FROM wa_templates WHERE name = '$n' AND language = '$l' ORDER BY id DESC LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row) {   // language mismatch (e.g. en vs en_US) — fall back to name only
        $res = mysqli_query($conn, "SELECT body FROM wa_templates WHERE name = '$n' ORDER BY id DESC LIMIT 1");
        $row = $res ? mysqli_fetch_assoc($res) : null;
    }
    if (!$row) { return ''; }
    $body = (string)$row['body'];
    $vals = [];
    foreach ((array)$components as $c) {
        if (($c['type'] ?? '') === 'body' && !empty($c['parameters'])) {
            foreach ($c['parameters'] as $p) { $vals[] = (string)($p['text'] ?? ''); }
        }
    }
    foreach ($vals as $i => $val) { $body = str_replace('{{' . ($i + 1) . '}}', $val, $body); }
    return $body;
}

function wa_send_template($conn, $waId, $name, $lang = 'en', $components = []) {
    $contact = wa_find_contact_by_waid($conn, $waId);
    $contactId = $contact['id'] ?? wa_upsert_contact($conn, $waId);
    $template = ['name' => $name, 'language' => ['code' => $lang]];
    if ($components) { $template['components'] = $components; }
    $payload = [
        'messaging_product' => 'whatsapp', 'recipient_type' => 'individual',
        'to' => $waId, 'type' => 'template', 'template' => $template,
    ];
    // Store the ACTUAL rendered text (placeholders filled) so staff see what the client
    // received, not a raw "[template:…]" reference. Fall back to the reference if unknown.
    $display = wa_template_rendered($conn, $name, $lang, $components);
    if (trim($display) === '') { $display = "[template:{$name}/{$lang}]"; }
    return wa_dialog_dispatch($conn, (int)$contactId, 'template', $display, $payload);
}

/** Upload a local file to 360dialog. Returns ['ok', 'id'] or ['ok'=>false,'error']. */
function wa_upload_media($filePath, $mime) {
    $ch = curl_init(rtrim(WA_DIALOG_URL, '/') . '/media');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'messaging_product' => 'whatsapp',
            'type' => $mime,
            'file' => new CURLFile($filePath, $mime, basename($filePath)),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => ['D360-API-KEY: ' . WA_DIALOG_KEY],
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode((string)$raw, true);
    if ($status >= 200 && $status < 300 && !empty($j['id'])) { return ['ok' => true, 'id' => $j['id']]; }
    error_log('[wa-media] upload ' . $status . ' ' . substr((string)$raw, 0, 200));
    return ['ok' => false, 'error' => 'upload_' . $status];
}

/**
 * Upload + send a media message (image/video/audio/document), record outbound.
 * Enforces the 24h window (free-form). Returns ['ok', ...].
 */
function wa_send_media($conn, $waId, $filePath, $mime, $filename, $caption = '') {
    $contact = wa_find_contact_by_waid($conn, $waId);
    $contactId = $contact['id'] ?? wa_upsert_contact($conn, $waId);
    if (!wa_within_window($contact['last_inbound_at'] ?? null)) {
        return ['ok' => false, 'error' => 'outside_24h_window'];
    }
    $up = wa_upload_media($filePath, $mime);
    if (empty($up['ok'])) { return ['ok' => false, 'error' => $up['error'] ?? 'upload_failed']; }
    $mediaId = $up['id'];

    if (strpos($mime, 'image/') === 0)      { $type = 'image'; }
    elseif (strpos($mime, 'video/') === 0)  { $type = 'video'; }
    elseif (strpos($mime, 'audio/') === 0)  { $type = 'audio'; }
    else                                    { $type = 'document'; }

    $obj = ['id' => $mediaId];
    if ($caption !== '') { $obj['caption'] = $caption; }
    if ($type === 'document') { $obj['filename'] = $filename; }
    $payload = [
        'messaging_product' => 'whatsapp', 'recipient_type' => 'individual',
        'to' => $waId, 'type' => $type, $type => $obj,
    ];
    $resp = wa_http_post(rtrim(WA_DIALOG_URL, '/') . '/messages',
        ['Content-Type: application/json', 'D360-API-KEY: ' . WA_DIALOG_KEY], $payload);
    $data = $resp['body'];
    $wamid = $data['messages'][0]['id'] ?? null;
    $ok = $resp['status'] >= 200 && $resp['status'] < 300 && $wamid !== null;

    wa_save_outbound($conn, (int)$contactId, [
        'wa_message_id' => $wamid, 'type' => $type,
        'body' => $caption !== '' ? $caption : $filename,
        'media_id' => $mediaId, 'media_mime' => $mime,
        'status' => $ok ? 'sent' : 'failed',
        'raw_payload' => ['request' => $payload, 'response' => $data, 'http' => $resp['status']],
    ]);
    if ($ok) { return ['ok' => true, 'wa_message_id' => $wamid]; }
    return ['ok' => false, 'error' => $data['error']['message'] ?? ('HTTP ' . $resp['status'])];
}

/**
 * The one place an outbound message is actually sent.
 *
 * $channel names the business number to send from. Left null it resolves to
 * whichever number this customer last wrote to, so a reply always comes back on
 * the line they used — and every existing caller gets that for free, without
 * passing anything. A contact with no channel history resolves to the messaging
 * line, which is exactly what happened before channels existed.
 *
 * Broadcasts do NOT come through here; they keep their own path and their own
 * number, so nobody starts receiving marketing from the calling line.
 */
function wa_dialog_dispatch($conn, $contactId, $type, $body, $payload, $channel = null) {
    if ($channel === null && function_exists('wa_reply_channel')) {
        $channel = wa_reply_channel($conn, (int)$contactId);
    }
    $ch = function_exists('wa_channel') ? wa_channel($channel) : null;
    // Fail closed to the messaging line rather than sending with no key at all.
    $url = ($ch && trim((string)$ch['url']) !== '') ? $ch['url'] : WA_DIALOG_URL;
    $key = ($ch && trim((string)$ch['key']) !== '') ? $ch['key'] : WA_DIALOG_KEY;

    $resp = wa_http_post(
        rtrim($url, '/') . '/messages',
        ['Content-Type: application/json', 'D360-API-KEY: ' . $key],
        $payload
    );
    $data = $resp['body'];
    $wamid = $data['messages'][0]['id'] ?? null;
    $ok = $resp['status'] >= 200 && $resp['status'] < 300 && $wamid !== null;
    wa_save_outbound($conn, $contactId, [
        'wa_message_id' => $wamid, 'type' => $type, 'body' => $body,
        'status' => $ok ? 'sent' : 'failed',
        'channel' => $ch ? $ch['name'] : null,
        'raw_payload' => ['request' => $payload, 'response' => $data, 'http' => $resp['status']],
    ]);
    if ($ok) { return ['ok' => true, 'wa_message_id' => $wamid]; }
    return ['ok' => false, 'error' => $data['error']['message'] ?? ('HTTP ' . $resp['status'])];
}

/**
 * Retry a previously-failed outbound message (the WhatsApp API fails intermittently).
 * Re-POSTs the exact 360dialog request we stored on the failed attempt (so text,
 * template AND media all work — media re-uses the still-valid uploaded media id).
 * Updates the SAME row in place so the bubble simply flips failed -> sent, keeping
 * the thread clean. Returns {ok, status, wa_message_id?} or {ok:false, error}.
 */
function wa_resend_message($conn, $msgId) {
    $msgId = (int)$msgId;
    $res = mysqli_query($conn,
        "SELECT m.*, c.wa_id, c.last_inbound_at
           FROM wa_messages m
           JOIN wa_contacts c ON c.id = m.contact_id
          WHERE m.id = $msgId AND m.direction = 'outbound' LIMIT 1");
    $m = $res ? mysqli_fetch_assoc($res) : null;
    if (!$m) { return ['ok' => false, 'error' => 'not_found']; }
    if (($m['status'] ?? '') !== 'failed') { return ['ok' => false, 'error' => 'not_failed']; }

    // Prefer the exact original request; fall back to rebuilding a text payload.
    $raw = json_decode((string)($m['raw_payload'] ?? ''), true);
    $payload = (is_array($raw) && !empty($raw['request'])) ? $raw['request'] : null;
    if (!$payload && $m['type'] === 'text') {
        $payload = [
            'messaging_product' => 'whatsapp', 'recipient_type' => 'individual',
            'to' => $m['wa_id'], 'type' => 'text',
            'text' => ['preview_url' => false, 'body' => (string)$m['body']],
        ];
    }
    if (!$payload) { return ['ok' => false, 'error' => 'cannot_rebuild']; }

    // Free-form (text/media) still needs the 24h window; templates may go anytime.
    if ($m['type'] !== 'template' && !wa_within_window($m['last_inbound_at'])) {
        return ['ok' => false, 'error' => 'outside_24h_window'];
    }

    $resp  = wa_http_post(rtrim(WA_DIALOG_URL, '/') . '/messages',
        ['Content-Type: application/json', 'D360-API-KEY: ' . WA_DIALOG_KEY], $payload);
    $data  = $resp['body'];
    $wamid = $data['messages'][0]['id'] ?? null;
    $ok    = $resp['status'] >= 200 && $resp['status'] < 300 && $wamid !== null;

    $status  = $ok ? 'sent' : 'failed';
    $wamSql  = wa_sql($conn, $wamid);
    $statSql = wa_sql($conn, $status);
    $rawSql  = wa_sql($conn, json_encode(
        ['request' => $payload, 'response' => $data, 'http' => $resp['status'], 'resent' => true],
        JSON_UNESCAPED_UNICODE));
    mysqli_query($conn,
        "UPDATE wa_messages SET wa_message_id = $wamSql, status = $statSql, raw_payload = $rawSql
          WHERE id = $msgId");

    if ($ok) { return ['ok' => true, 'status' => $status, 'wa_message_id' => $wamid]; }
    return ['ok' => false, 'error' => $data['error']['message'] ?? ('HTTP ' . $resp['status'])];
}

// =====================================================================
// AI providers (Claude + OpenAI)
// =====================================================================

/**
 * Call the active (or named) provider.
 * @return array {ok:bool, text:string, error?:string}
 */
function wa_ai_complete($provider, $system, $messages, $opts = []) {
    $max = (int)($opts['max_tokens'] ?? 1024);
    $timeout = (int)($opts['timeout'] ?? 25);   // longer for big outputs (KB editing)
    // Optional vision: $opts['image'] = ['mime'=>'image/png','data'=>'<base64>'] gets
    // attached to the LAST user turn so the model can look at an uploaded example.
    $image = (!empty($opts['image']['data']) && !empty($opts['image']['mime'])) ? $opts['image'] : null;
    // Optional PDF (Claude only): $opts['document'] = ['mime'=>'application/pdf','data'=>base64].
    $document = (!empty($opts['document']['data']) && !empty($opts['document']['mime'])) ? $opts['document'] : null;
    if ($provider === 'openai') {
        $chat = array_merge([['role' => 'system', 'content' => $system]], $messages);
        if ($image) {
            for ($i = count($chat) - 1; $i >= 0; $i--) {
                if (($chat[$i]['role'] ?? '') === 'user') {
                    $txt = is_string($chat[$i]['content']) ? $chat[$i]['content'] : '';
                    $chat[$i]['content'] = [
                        ['type' => 'text', 'text' => $txt],
                        ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $image['mime'] . ';base64,' . $image['data']]],
                    ];
                    break;
                }
            }
        }
        $payload = ['model' => WA_OPENAI_MODEL, 'max_tokens' => $max, 'messages' => $chat];
        if (!empty($opts['json'])) { $payload['response_format'] = ['type' => 'json_object']; }
        $resp = wa_http_post(rtrim(WA_OPENAI_URL, '/') . '/v1/chat/completions',
            ['Content-Type: application/json', 'Authorization: Bearer ' . WA_OPENAI_KEY], $payload, $timeout);
        $d = $resp['body'];
        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            return ['ok' => false, 'text' => '', 'error' => $d['error']['message'] ?? ('HTTP ' . $resp['status'])];
        }
        return ['ok' => true, 'text' => trim((string)($d['choices'][0]['message']['content'] ?? ''))];
    }

    // Claude (Anthropic Messages API). No temperature/thinking (would 400 on opus-4-8).
    if ($image || $document) {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                $txt = is_string($messages[$i]['content']) ? $messages[$i]['content'] : '';
                $blocks = [['type' => 'text', 'text' => $txt]];
                if ($image) {
                    $blocks[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $image['mime'], 'data' => $image['data']]];
                }
                if ($document) {
                    $blocks[] = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => $document['mime'], 'data' => $document['data']]];
                }
                $messages[$i]['content'] = $blocks;
                break;
            }
        }
    }
    $payload = ['model' => WA_ANTHROPIC_MODEL, 'max_tokens' => $max, 'system' => $system, 'messages' => $messages];
    $resp = wa_http_post(rtrim(WA_ANTHROPIC_URL, '/') . '/v1/messages', [
        'Content-Type: application/json',
        'x-api-key: ' . WA_ANTHROPIC_KEY,
        'anthropic-version: ' . WA_ANTHROPIC_VERSION,
    ], $payload, $timeout);
    $d = $resp['body'];
    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        return ['ok' => false, 'text' => '', 'error' => $d['error']['message'] ?? ('HTTP ' . $resp['status'])];
    }
    if (($d['stop_reason'] ?? null) === 'refusal') {
        return ['ok' => false, 'text' => '', 'error' => 'model_refusal'];
    }
    $text = '';
    foreach ($d['content'] ?? [] as $b) {
        if (($b['type'] ?? '') === 'text') { $text .= $b['text'] ?? ''; }
    }
    return ['ok' => true, 'text' => trim($text)];
}

// =====================================================================
// Knowledge base + AI responder
// =====================================================================

/** Course/event/programme display name for routing/AI context. */
function wa_ref_name($conn, $refType, $refId) {
    if ($refId === null) { return null; }
    if ($refType === 'event') {
        $refId = (int)$refId;
        $res = mysqli_query($conn, "SELECT event_title FROM `Event` WHERE event_id = $refId LIMIT 1");
        $r = $res ? mysqli_fetch_assoc($res) : null;
        return $r ? $r['event_title'] : null;
    }
    if ($refType === 'program') {
        $p = wa_program_get($conn, (int)$refId);
        return $p ? $p['name'] : null;
    }
    return wa_course_name($conn, (int)$refId);
}

/** Active events (status = 1). */
function wa_active_events($conn) {
    $res = mysqli_query($conn,
        'SELECT event_id AS id, event_title AS name, location FROM `Event` WHERE status = 1 ORDER BY start_on DESC');
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

/** True if an Event row is an intake-based academic programme — flagged by a
 *  location marker 'ACADEMIC#<qualification>' (e.g. ACADEMIC#certificate). These
 *  are open year-round (register anytime), not date-bound like location events. */
function wa_event_is_academic($location) {
    return stripos(ltrim((string)$location), 'ACADEMIC#') === 0;
}

/** True if an Event row is a corporate training — flagged by a location marker
 *  'CORPORATE#<qualification>' (mirrors the ACADEMIC# convention). Register-anytime,
 *  not a date-bound country event. */
function wa_event_is_corporate($location) {
    return stripos(ltrim((string)$location), 'CORPORATE#') === 0;
}

/** How one Event session reads in a catalogue. Academic (intake-based) rows show
 *  their qualification + "register anytime" (no country/date); location-based
 *  events show the country and dates. */
function wa_event_display($location, $when) {
    $loc = trim((string)$location);
    if (wa_event_is_academic($loc)) {
        $qual = trim(substr($loc, strpos($loc, '#') + 1));
        $qual = $qual !== '' ? ucwords(str_replace(['_', '-'], ' ', $qual)) : 'Academic programme';
        return $qual . ' — online, intake-based (register anytime)';
    }
    $loc = $loc !== '' ? $loc : 'venue TBC';
    return $loc . ($when !== '' ? ' (' . $when . ')' : '');
}

/**
 * Canonical public registration/details link for ANY Event row — academic/online
 * or in-person/onsite alike. This is the ONE correct link to give a customer who
 * wants to register for an event; it always resolves to that event's page.
 */
function wa_event_register_url($eventId) {
    $eventId = (int)$eventId;
    return $eventId > 0 ? "https://vantageafricaleaders.com/program-details.php?id={$eventId}" : '';
}

/**
 * Compact catalogue of active, upcoming international trainings (the `Event` table
 * = our in-person trainings held in various countries). Injected into EVERY AI
 * prompt so the bot can answer "do you have <topic> training in <country>?" with
 * the real answer, instead of deflecting to "let me check with the team". '' if
 * none. Past events (end_on before today) are dropped so it stays current.
 */
function wa_events_catalog($conn, $limit = 40) {
    $limit = (int)$limit;
    // Keep active events that haven't clearly finished. Tolerant of legacy zero /
    // NULL dates so a mis-dated but active training is still shown to the AI.
    // Academic programmes (location 'ACADEMIC#…') are intake-based — always shown.
    // Location-based events keep the "not clearly finished" filter (tolerant of
    // legacy zero/NULL dates).
    $res = mysqli_query($conn,
        "SELECT event_id, event_title, location, start_on, end_on
           FROM `Event`
          WHERE status = 1
            AND (location LIKE 'ACADEMIC#%'
                 OR end_on IS NULL OR end_on = '0000-00-00' OR end_on >= CURDATE()
                 OR start_on IS NULL OR start_on = '0000-00-00' OR start_on >= CURDATE())
          ORDER BY (location LIKE 'ACADEMIC#%') DESC,
                   (start_on IS NULL OR start_on = '0000-00-00'), start_on ASC
          LIMIT $limit");
    if (!$res || mysqli_num_rows($res) === 0) { return ''; }
    $lines = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $when = '';
        $start = (string)($r['start_on'] ?? '');
        $end   = (string)($r['end_on'] ?? '');
        $isReal = function ($d) { return $d !== '' && strpos($d, '0000-00-00') !== 0; };
        if ($isReal($start)) {
            try {
                $when = (new DateTime($start))->format('j M Y');
                if ($isReal($end)) { $when .= ' – ' . (new DateTime($end))->format('j M Y'); }
            } catch (Throwable $e) { $when = ''; }
        }
        $line = '• ' . trim((string)$r['event_title']) . ' — '
            . wa_event_display($r['location'] ?? '', $when);
        $reg = wa_event_register_url((int)($r['event_id'] ?? 0));
        if ($reg !== '') { $line .= ' — register: ' . $reg; }
        $lines[] = $line;
    }
    return implode("\n", $lines);
}

/**
 * Pull a registration URL out of free-text knowledge-base content. Prefers a URL
 * that sits on a line mentioning register/enrol/apply/sign-up/book; otherwise the
 * first URL present. '' if none.
 */
function wa_extract_register_url($kb) {
    if (trim((string)$kb) === '') { return ''; }
    if (preg_match('/(?:regist|enrol|apply|sign[\s-]?up|\bbook\b|\bjoin\b)[^\n]*?(https?:\/\/[^\s<>()]+)/i', $kb, $m)) {
        return rtrim($m[1], '.,;:)');
    }
    if (preg_match('/(https?:\/\/[^\s<>()]+)/i', $kb, $m)) {
        return rtrim($m[1], '.,;:)');
    }
    return '';
}

/**
 * The public registration link for a course/event. PRIMARY source is the course's
 * own knowledge base (a "Registration link: https://…" line, or any URL in it).
 * Falls back to the global 'register_url' template setting (supports {type}/{id}
 * placeholders), then the site home page.
 */
function wa_register_link($conn, $refType, $refId) {
    // 1) From the knowledge base (raw text is authoritative).
    $link = wa_extract_register_url(wa_knowledge_get($conn, $refType, (int)$refId));
    if ($link !== '') { return $link; }
    // 2) Global fallback template, else the site home page.
    $tpl  = trim((string)wa_setting_get($conn, 'register_url', ''));
    $base = defined('WA_SITE_URL') ? rtrim(WA_SITE_URL, '/') : '';
    if ($tpl === '') { return $base; }
    if (strpos($tpl, '{') === false) { return rtrim($tpl, '/'); }   // plain URL, no placeholders
    return strtr($tpl, ['{type}' => (string)$refType, '{id}' => (string)(int)$refId]);
}

// Built-in schedule for the Certified M&E Professional Course outline, so the AI
// can answer schedule/agenda questions immediately (a fresh scan overrides this).
if (!defined('WA_OUTLINE_DEFAULT_TEXT')) {
    define('WA_OUTLINE_DEFAULT_TEXT',
"COURSE OUTLINE / SCHEDULE — Certified M&E Professional Course (in-person training events).\n"
. "There are TWO DISTINCT PHASES — never merge them, and never invent a different structure (e.g. do NOT say '5 weeks', '3 days per week', or that the training is a weekly evening class — that describes only Phase 2):\n"
. "\nPHASE 1 — IN-PERSON TRAINING: 3 FULL DAYS, classes 8:30 AM to 5:00 PM each day. A practical, hands-on training where every participant builds a complete M&E Plan for their own project.\n"
. "Day topics across the 3 days: registration & introductions; M&E Demystified; Data Management & data quality; Key Concepts in M&E (baseline, indicator, target, goal, objective, output, inputs, results, outcomes, impact, activities, assumptions; effectiveness, efficiency, validity, reliability, sustainability, reporting, feedback); Formulating Goals, Objectives & Objectively Verifiable Indicators (OVIs); M&E Frameworks & designing a Logframe; Performance Monitoring & Evaluation Plans; Performance Results Report; Developing a Complete M&E Plan; Theory of Change; Earned Value Analysis; Participatory M&E; M&E and AI; review of participants' M&E Plans; graduation & award of certificates.\n"
. "Pre-training online topics (right after registration): the Rationale for M&E; Case-Study Selection; Overview of Project Management; M&E in the Global/Continental Arena.\n"
. "\nPHASE 2 — 3-MONTH ONLINE FOLLOW-UP: about 3 months, ONE ~1.5-hour session per week (normally Tuesday evening; Zoom link shared). This is the ONLY part that is a weekly evening session.\n"
. "Weekly topics: 1 Data Analysis & Visualization; 2 Advanced Indicator Development; 3 M&E in the age of AI; 4 M&E Consulting; 5 Impact Harvesting; 6 Advanced Data Management Techniques; 7 Theory-Based Evaluation; 8 Results-Based Management; 9 Advanced Participatory M&E; 10 Advanced Reporting & Communication; 11 Advanced Impact Evaluation; 12 Induction into the Vantage Africa M&E Professionals Association (VAMEPA).\n"
. "\nCompletion: attend the 3-day physical training (or join virtually); develop a complete M&E Plan; complete the required e-learning lessons and quizzes; and clear the fees. Two certificates (M&E and Proposal Writing) are issued on completion.");
}

/** The one course-outline link shared by ALL training events. Configurable via the
 *  'event_outline_url' setting; defaults to the current Drive outline. */
function wa_event_outline_url($conn) {
    return trim((string)wa_setting_get($conn, 'event_outline_url',
        'https://drive.google.com/file/d/1iGnMBGLw_lLdazONUBW6GS8KMKyVE_47/view?usp=drive_link'));
}

/** The schedule/agenda text of the shared course outline. Uses the scanned value
 *  ('event_outline_text' setting) if present, otherwise a built-in default so the
 *  AI can always answer schedule questions even before a scan. */
function wa_event_outline_text($conn) {
    $set = trim((string)wa_setting_get($conn, 'event_outline_text', ''));
    if ($set !== '') { return $set; }
    return WA_OUTLINE_DEFAULT_TEXT;
}

/** The shared course outline (link + schedule) applies to EVENT chats only —
 *  not courses or programmes. */
function wa_outline_applies($refType, $refName = '') {
    return $refType === 'event';
}

/** Pull a Google-Drive file id out of a share/view/uc URL. '' if not a Drive URL. */
function wa_drive_file_id($url) {
    if (preg_match('#/file/d/([A-Za-z0-9_-]{10,})#', (string)$url, $m)) { return $m[1]; }
    if (preg_match('#[?&]id=([A-Za-z0-9_-]{10,})#', (string)$url, $m)) { return $m[1]; }
    return '';
}

/** Download a (publicly shared) Google-Drive PDF's raw bytes, or '' on failure. */
function wa_fetch_drive_pdf($url, $timeout = 40) {
    $id = wa_drive_file_id($url);
    if ($id === '') {
        $r = wa_http_get((string)$url, $timeout);
        $b = ($r['status'] >= 200 && $r['status'] < 300) ? (string)$r['body'] : '';
        return strncmp($b, '%PDF', 4) === 0 ? $b : '';
    }
    $dl = 'https://drive.google.com/uc?export=download&id=' . $id;
    $r  = wa_http_get($dl, $timeout);
    $b  = ($r['status'] >= 200 && $r['status'] < 300) ? (string)$r['body'] : '';
    if (strncmp($b, '%PDF', 4) === 0) { return $b; }
    // Large-file interstitial: grab the confirm token and retry once.
    if ($b !== '' && preg_match('#confirm=([0-9A-Za-z_-]+)#', $b, $m)) {
        $r2 = wa_http_get($dl . '&confirm=' . $m[1], $timeout);
        $b2 = ($r2['status'] >= 200 && $r2['status'] < 300) ? (string)$r2['body'] : '';
        if (strncmp($b2, '%PDF', 4) === 0) { return $b2; }
    }
    return '';
}

/**
 * Scan the shared course-outline PDF and cache its schedule/agenda as plain text
 * (setting 'event_outline_text') so the live AI can answer schedule questions.
 * Claude only (uses PDF document input). @return {ok, text?} or {ok:false,error}.
 */
function wa_outline_import($conn) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['ok' => false, 'error' => 'no_provider']; }
    if ($provider !== 'claude') { return ['ok' => false, 'error' => 'needs_claude']; }
    $pdf = wa_fetch_drive_pdf(wa_event_outline_url($conn));
    if ($pdf === '') { return ['ok' => false, 'error' => 'no_pdf']; }
    if (strlen($pdf) > 25 * 1024 * 1024) { return ['ok' => false, 'error' => 'too_large']; }

    $system = "You extract the SCHEDULE/AGENDA from a training course outline PDF. Output PLAIN TEXT: the full "
        . "programme structure — days (and dates if shown), session times, and the topics/modules covered in each "
        . "session or day. Be faithful and complete; no preamble or commentary.";
    $res = wa_ai_complete($provider, $system,
        [['role' => 'user', 'content' => 'Extract the complete schedule, session times and topics from this course outline.']],
        ['max_tokens' => 4000, 'timeout' => 120, 'document' => ['mime' => 'application/pdf', 'data' => base64_encode($pdf)]]);
    if (empty($res['ok'])) { return ['ok' => false, 'error' => $res['error'] ?? 'ai_failed']; }
    $text = trim((string)$res['text']);
    if ($text === '') { return ['ok' => false, 'error' => 'empty']; }
    wa_setting_set($conn, 'event_outline_text', $text);
    wa_setting_set($conn, 'event_outline_text_at', date('Y-m-d H:i:s'));
    return ['ok' => true, 'text' => $text];
}

/** Which training programme an event is linked to (its "general database"), or 0. */
function wa_event_program_get($conn, $eventId) {
    return (int)wa_setting_get($conn, 'event_program:' . (int)$eventId, '0');
}
function wa_event_program_set($conn, $eventId, $programId) {
    wa_setting_set($conn, 'event_program:' . (int)$eventId, (string)(int)$programId);
}

/** Human date range for an Event row. '' when no real dates. */
function wa_event_when_range($start, $end) {
    $isReal = function ($d) { $d = (string)$d; return $d !== '' && strpos($d, '0000-00-00') !== 0; };
    if (!$isReal($start)) { return ''; }
    try {
        $w = (new DateTime((string)$start))->format('j M Y');
        if ($isReal($end)) { $w .= ' – ' . (new DateTime((string)$end))->format('j M Y'); }
        return $w;
    } catch (Throwable $e) { return ''; }
}

/**
 * Build a specific event's knowledge base from its details + the general database
 * of the training programme it's linked to (M&E, Data Analysis, ...). Pulls the
 * event name/dates from the Event table, folds in the programme's general info and
 * the shared course-outline link, saves it as the event's KB, and remembers the
 * programme link. @return array {ok, text?} or {ok:false, error}
 */
function wa_event_kb_build($conn, $eventId, $programId, $city, $hotel, $cost, $regLink) {
    $eventId = (int)$eventId; $programId = (int)$programId;
    if ($eventId <= 0) { return ['ok' => false, 'error' => 'no_event']; }
    $name = wa_ref_name($conn, 'event', $eventId);
    if (!$name) { return ['ok' => false, 'error' => 'unknown_event']; }

    $r  = mysqli_query($conn, "SELECT start_on, end_on, location, COALESCE(early_amount,0) AS early_amount FROM `Event` WHERE event_id = $eventId LIMIT 1");
    $ev = $r ? (mysqli_fetch_assoc($r) ?: []) : [];
    $dates = wa_event_when_range($ev['start_on'] ?? '', $ev['end_on'] ?? '');

    // Pull what we can straight from the system; the caller's form values override.
    $dbCity = trim((string)($ev['location'] ?? ''));
    if (strpos($dbCity, 'ACADEMIC#') === 0) { $dbCity = ''; }        // marker, not a place
    $dbAmt  = (float)($ev['early_amount'] ?? 0);
    $dbCost = $dbAmt > 0 ? ('USD ' . rtrim(rtrim(number_format($dbAmt, 2), '0'), '.')) : '';
    $dbReg  = wa_register_link($conn, 'event', $eventId);            // KB link, else register_url setting

    $city    = trim((string)$city)    !== '' ? trim((string)$city)    : $dbCity;
    $hotel   = trim((string)$hotel);
    $cost    = trim((string)$cost)    !== '' ? trim((string)$cost)    : $dbCost;
    $regLink = trim((string)$regLink) !== '' ? trim((string)$regLink) : $dbReg;
    $outline = wa_event_outline_url($conn);

    $prog   = $programId > 0 ? wa_program_get($conn, $programId) : null;
    $genName = $prog['name'] ?? '';
    $genKb   = $programId > 0 ? trim((string)wa_knowledge_get($conn, 'program', $programId)) : '';
    if ($programId > 0) { wa_event_program_set($conn, $eventId, $programId); }

    $specs = "Event name: {$name}\n"
        . ($genName !== '' ? "Training programme: {$genName}\n" : '')
        . ($city    !== '' ? "City / country: {$city}\n" : '')
        . ($hotel   !== '' ? "Hotel / venue: {$hotel}\n" : '')
        . ($dates   !== '' ? "Dates: {$dates}\n" : '')
        . ($cost    !== '' ? "Cost / fee: {$cost}\n" : '')
        . ($regLink !== '' ? "Registration link: {$regLink}\n" : '')
        . "Course outline link: {$outline}\n";

    $provider = wa_active_provider($conn);
    // No AI key (or programme has no general info) → template it directly.
    if (!wa_provider_ready($provider)) {
        $kb = "=== 1. EVENT BASICS ===\n" . $specs
            . ($genKb !== '' ? "\n=== 2. PROGRAMME INFO ===\n" . $genKb . "\n" : '');
        wa_knowledge_set($conn, 'event', $eventId, $kb);
        return ['ok' => true, 'text' => $kb];
    }

    $system = "You build a plain-text knowledge base for ONE specific in-person training EVENT, used by a WhatsApp "
        . "admissions advisor at Vantage Africa School of Leadership. Combine the GENERAL PROGRAMME INFO (shared by "
        . "all events of this programme — what it covers, outcomes, who it's for) with the EVENT SPECIFICS (this "
        . "event's name, city, hotel, dates, cost, registration link, outline link). Output PLAIN TEXT under clearly "
        . "labelled '=== N. SECTION NAME ===' headings — at least: 1. EVENT BASICS (name, city, hotel, dates), "
        . "2. COST & REGISTRATION (cost, the registration link, and the course outline link), and sections for what "
        . "the programme covers and its outcomes drawn from the general info. Rules: use ONLY the facts provided — "
        . "never invent fees, dates, hotels or links; keep the registration and outline links EXACTLY as given; no "
        . "markdown code fences, no preamble.";
    $user = "GENERAL PROGRAMME INFO" . ($genName !== '' ? " (\"{$genName}\")" : '') . ":\n"
        . ($genKb !== '' ? $genKb : "(none provided)") . "\n\nEVENT SPECIFICS:\n" . $specs;

    $res = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]],
                          ['max_tokens' => 3000, 'timeout' => 90]);
    if (empty($res['ok'])) { return ['ok' => false, 'error' => $res['error'] ?? 'ai_failed']; }
    $kb = trim(preg_replace('/^```[a-z]*\s*\n?|\n?```\s*$/i', '', trim((string)$res['text'])));
    if ($kb === '') { return ['ok' => false, 'error' => 'empty_result']; }
    wa_knowledge_set($conn, 'event', $eventId, $kb);
    return ['ok' => true, 'text' => $kb];
}

/** The training programme an event belongs to: the explicit link if set, else the
 *  first programme whose keywords match the event title. 0 if none. */
function wa_event_program_for($conn, $eventId, $eventName = '') {
    $pid = wa_event_program_get($conn, (int)$eventId);
    if ($pid > 0) { return $pid; }
    if ($eventName === '') { $eventName = (string)wa_ref_name($conn, 'event', (int)$eventId); }
    foreach (wa_programs_list($conn, true) as $p) {
        foreach (wa_program_keywords_arr($p) as $kw) {
            if ($kw !== '' && stripos($eventName, $kw) !== false) { return (int)$p['id']; }
        }
    }
    return 0;
}

/**
 * The knowledge an event chat answers from: the event's live DB details (name,
 * city/venue, dates, cost, registration link) + its linked training programme's
 * general knowledge (e.g. M&E Trainings) + any notes saved on the event itself.
 * $ownOverride: use this text as the event's own notes instead of the saved KB
 * (the sandbox passes the editor's current text). Returns plain text.
 */
function wa_event_effective_kb($conn, $eventId, $ownOverride = null) {
    $eventId = (int)$eventId;
    $name = (string)wa_ref_name($conn, 'event', $eventId);

    $r  = mysqli_query($conn, "SELECT start_on, end_on, location, COALESCE(early_amount,0) AS early_amount FROM `Event` WHERE event_id = $eventId LIMIT 1");
    $ev = $r ? (mysqli_fetch_assoc($r) ?: []) : [];
    $dates = wa_event_when_range($ev['start_on'] ?? '', $ev['end_on'] ?? '');
    $city  = trim((string)($ev['location'] ?? '')); if (strpos($city, 'ACADEMIC#') === 0) { $city = ''; }
    $amt   = (float)($ev['early_amount'] ?? 0);
    $cost  = $amt > 0 ? ('USD ' . rtrim(rtrim(number_format($amt, 2), '0'), '.')) : '';
    $reg   = wa_register_link($conn, 'event', $eventId);

    $specs = "=== THIS EVENT (in-person M&E training) ===\n"
        . ($name !== '' ? "Event: {$name}\n" : '')
        . ($city !== '' ? "City / venue location: {$city}\n" : '')
        . ($dates !== '' ? "Start date: {$dates}\n" : '')
        . "Duration & schedule (state it EXACTLY like this; do NOT invent weeks, 'days per week', or evening times "
        . "for the in-person part):\n"
        . "  - PHASE 1 — the in-person event is 3 FULL DAYS, classes 8:30 AM to 5:00 PM each day (a hands-on "
        . "training where you build a complete M&E plan).\n"
        . "  - PHASE 2 — after the 3 physical days, the CMEP programme continues online for about 3 months, with "
        . "ONE ~1.5-hour session per week (normally Tuesday evening). The weekly-evening format applies ONLY to "
        . "this online phase, NOT to the 3 physical days.\n"
        . "  - CRITICAL: there is NO '5-week' programme, NO 'X-week' schedule, and NO 'Monday/Tuesday/Wednesday' "
        . "evening-class pattern for this in-person event. If any such schedule (e.g. '5 weeks', 'Mon/Tue/Wed', "
        . "'8:00-9:30 PM') appears anywhere in the knowledge, it belongs to a different online/virtual programme — "
        . "IGNORE it completely for this event. Never tell a client this event is a 5-week programme.\n"
        . ($cost !== '' ? "Cost / fee: {$cost}\n" : '')
        . ($reg  !== '' ? "Registration link: {$reg}\n" : '');

    $own = $ownOverride !== null ? trim((string)$ownOverride) : trim((string)wa_knowledge_get_ai($conn, 'event', $eventId));

    $pid   = wa_event_program_for($conn, $eventId, $name);
    $genKb = '';
    if ($pid > 0) {
        $genKb = trim((string)wa_knowledge_get_ai($conn, 'program', $pid));
        if ($genKb === '') { $genKb = trim((string)wa_knowledge_get($conn, 'program', $pid)); }
    }

    $parts = [$specs];
    if ($own !== '')   { $parts[] = "=== EVENT NOTES ===\n" . $own; }
    if ($genKb !== '') { $parts[] = "=== M&E PROGRAMME — GENERAL INFO (what the training covers) ===\n" . $genKb; }
    $out = trim(implode("\n", $parts));
    // Scrub the wrong (virtual-programme) course-outline link if it lingers in any KB —
    // events must only ever use the configured events outline.
    $out = preg_replace('#https?://\S*13YfH2JH-cPu_ANk4wZuCF6wuYJ18ctLO\S*#i', '', $out);
    return trim($out);
}

// =====================================================================
// Training programmes (themes) — e.g. M&E, Data Analysis, Academic Programs.
// Each has its own KB (ref_type='program'); its country/location/dates are read
// LIVE from the Event table by matching keywords against event titles.
// =====================================================================

/** All programmes (optionally active only), newest first. */
function wa_programs_list($conn, $activeOnly = false) {
    wa_kb_ensure_schema($conn);
    $where = $activeOnly ? 'WHERE status = 1' : '';
    $res = mysqli_query($conn, "SELECT * FROM wa_programs $where ORDER BY name ASC");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

/** One programme by id, or null. */
function wa_program_get($conn, $id) {
    wa_kb_ensure_schema($conn);
    $id = (int)$id;
    $res = mysqli_query($conn, "SELECT * FROM wa_programs WHERE id = $id LIMIT 1");
    return $res ? (mysqli_fetch_assoc($res) ?: null) : null;
}

/** Create/update a programme. Returns the id. */
function wa_program_save($conn, $id, $name, $keywords, $status = 1, $assignedTo = null) {
    wa_kb_ensure_schema($conn);
    $id = (int)$id;
    $n = wa_sql($conn, trim((string)$name));
    $k = wa_sql($conn, trim((string)$keywords));
    $s = $status ? 1 : 0;
    // null = "don't touch" (callers that never learned about reps keep the existing ones).
    $setA = '';
    if ($assignedTo !== null) {
        $csv  = implode(',', wa_program_owner_ids(['assigned_to' => $assignedTo]));
        $setA = ', assigned_to = ' . wa_sql($conn, $csv);
    }
    if ($id > 0) {
        mysqli_query($conn, "UPDATE wa_programs SET name = $n, keywords = $k, status = $s $setA WHERE id = $id");
        return $id;
    }
    $aCol = $assignedTo !== null ? ', assigned_to' : '';
    $aVal = $assignedTo !== null ? ', ' . wa_sql($conn, implode(',', wa_program_owner_ids(['assigned_to' => $assignedTo]))) : '';
    mysqli_query($conn, "INSERT INTO wa_programs (name, keywords, status$aCol) VALUES ($n, $k, $s$aVal)
        ON DUPLICATE KEY UPDATE keywords = VALUES(keywords), status = VALUES(status)");
    return (int)mysqli_insert_id($conn) ?: (int)(mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id FROM wa_programs WHERE name = $n LIMIT 1"))['id'] ?? 0);
}

/** Rep ids (registered_users.id) for a programme, in order. First = the one an
 *  unlocated onsite enquiry is assigned to; all of them can see it in the inbox. */
function wa_program_owner_ids($program) {
    // Accepts a programme row, a CSV string, or the raw array a multi-select posts.
    $raw = is_array($program) && array_key_exists('assigned_to', $program) ? $program['assigned_to'] : $program;
    if (is_array($raw)) { $raw = implode(',', $raw); }
    $raw = (string)$raw;
    $out = [];
    foreach (explode(',', $raw) as $p) {
        $n = (int)trim($p);
        if ($n > 0 && !in_array($n, $out, true)) { $out[] = $n; }
    }
    return $out;
}

/** Replace ONE programme's reps. Scoped to that programme by id, so setting reps
 *  on one never affects another. Empty CSV clears them. */
function wa_program_set_owners($conn, $programId, $csv) {
    wa_kb_ensure_schema($conn);
    $pid = (int)$programId;
    if ($pid < 1) { return; }
    $clean = implode(',', wa_program_owner_ids(['assigned_to' => $csv]));
    mysqli_query($conn, "UPDATE wa_programs SET assigned_to = " . wa_sql($conn, $clean) . " WHERE id = $pid");
}

/** The rep an unlocated onsite enquiry goes to, or null if the programme has none. */
function wa_program_first_owner($program) {
    $ids = wa_program_owner_ids($program);
    return $ids ? $ids[0] : null;
}

/**
 * Best-matching ACTIVE programme for a piece of text (the course title the chat was
 * bound to, plus the customer's own words). Scores by how many of the programme's
 * keywords appear, longest keyword first so "Data Analysis training" beats a bare
 * "training". Returns the programme row or null.
 *
 * Used when an onsite enquiry has confirmed in-person but not yet named a country:
 * there is no Event to route to, so the programme's rep takes it.
 */
function wa_program_match($conn, $text) {
    $hay = ' ' . wa_normalize((string)$text) . ' ';
    if (trim($hay) === '') { return null; }
    $stop = wa_stopwords();
    $best = null; $bestScore = 0;
    foreach (wa_programs_list($conn, true) as $p) {
        $score = 0;
        foreach (wa_program_keywords_arr($p) as $kw) {
            $phrase = trim(wa_normalize($kw));
            if ($phrase === '') { continue; }

            // Whole phrase present — the strongest signal, worth double.
            if (strpos($hay, ' ' . $phrase . ' ') !== false) {
                $score += 2 * mb_strlen($phrase);
                continue;
            }

            // Otherwise score the phrase's DISTINCTIVE words. A keyword like
            // "Data Analysis training" must still match a course called "Data
            // Analysis Using SPSS", so generic words ('training', 'course',
            // 'programme') are ignored on both sides via the shared stoplist.
            $words = []; $hits = [];
            foreach (explode(' ', $phrase) as $w) {
                if ($w === '' || mb_strlen($w) < 3 || isset($stop[$w])) { continue; }
                $words[] = $w;
                if (strpos($hay, ' ' . $w . ' ') !== false) { $hits[] = $w; }
            }
            // Require most of them, so a lone "data" can't claim the Data Analysis
            // programme off an unrelated sentence.
            if ($words && (count($hits) / count($words)) >= 0.5) {
                foreach ($hits as $w) { $score += mb_strlen($w); }
            }
        }
        if ($score > $bestScore) { $bestScore = $score; $best = $p; }
    }
    return $best;
}

/**
 * Programme for an onsite enquiry, most faithful signal first.
 *
 * A chat is bound to a virtual COURSE (e.g. "Data Analysis Using SPSS"), but a
 * programme groups the ONSITE trainings (e.g. "Data Analysis & Visualization"),
 * and its keywords are written to match EVENT titles. So when the router has
 * already resolved the onsite twin event, match that first; fall back to the
 * course title, then to the customer's own words.
 */
function wa_program_for_course($conn, $courseId, $text = '', $eventId = 0) {
    $eid = (int)$eventId;
    if ($eid > 0) {
        $evTitle = wa_scalar_str($conn, "SELECT event_title FROM `Event` WHERE event_id = $eid LIMIT 1");
        if (trim((string)$evTitle) !== '') {
            $p = wa_program_match($conn, $evTitle);
            if ($p) { return $p; }
        }
    }
    $cid = (int)$courseId;
    $title = '';
    if ($cid > 0) {
        $r = mysqli_query($conn, "SELECT course FROM course WHERE course_id = $cid LIMIT 1");
        $row = $r ? mysqli_fetch_assoc($r) : null;
        $title = (string)($row['course'] ?? '');
    }
    $p = $title !== '' ? wa_program_match($conn, $title) : null;
    return $p ?: wa_program_match($conn, $text);
}

/** Delete a programme and its KB. */
function wa_program_delete($conn, $id) {
    $id = (int)$id;
    mysqli_query($conn, "DELETE FROM wa_programs WHERE id = $id");
    mysqli_query($conn, "DELETE FROM wa_knowledge WHERE ref_type = 'program' AND ref_id = $id");
}

/** Keywords to match a programme's events by. Explicit keywords if set, else the
 *  programme name broken into meaningful tokens. */
function wa_program_keywords_arr($program) {
    $kw = trim((string)($program['keywords'] ?? ''));
    if ($kw !== '') {
        $parts = array_filter(array_map('trim', explode(',', $kw)), function ($s) { return $s !== ''; });
        if ($parts) { return array_values($parts); }
    }
    // Fall back to the name's own words (drop tiny/generic ones).
    $stop = wa_stopwords();
    $out = [];
    foreach (explode(' ', wa_normalize((string)($program['name'] ?? ''))) as $w) {
        if (mb_strlen($w) >= 3 && !isset($stop[$w])) { $out[] = $w; }
    }
    return $out ?: [trim((string)($program['name'] ?? ''))];
}

/** Upcoming Event rows whose title matches a programme's keywords. Each row as
 *  ['location'=>..,'when'=>..]. Reuses the tolerant date window. */
function wa_program_events($conn, $program, $limit = 20) {
    $limit = (int)$limit;
    $kws = wa_program_keywords_arr($program);
    $likes = [];
    foreach ($kws as $kw) {
        $kw = mysqli_real_escape_string($conn, $kw);
        if ($kw !== '') { $likes[] = "event_title LIKE '%$kw%'"; }
    }
    if (!$likes) { return []; }
    $match = '(' . implode(' OR ', $likes) . ')';
    // Academic programmes (location 'ACADEMIC#…') are intake-based — always
    // listed regardless of date. Location-based events keep the upcoming filter.
    $res = mysqli_query($conn,
        "SELECT event_id, event_title, location, start_on, end_on,
                early_amount, early_start_on, early_end_on,
                advance_amount, advance_start_on, advance_end_on,
                gate_amount, gate_start_on, gate_end_on
           FROM `Event`
          WHERE status = 1 AND $match
            AND (location LIKE 'ACADEMIC#%'
                 OR end_on IS NULL OR end_on = '0000-00-00' OR end_on >= CURDATE()
                 OR start_on IS NULL OR start_on = '0000-00-00' OR start_on >= CURDATE())
          ORDER BY (location LIKE 'ACADEMIC#%') DESC,
                   (start_on IS NULL OR start_on = '0000-00-00'), start_on ASC
          LIMIT $limit");
    if (!$res) { return []; }
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $when = '';
        $start = (string)($r['start_on'] ?? '');
        $end   = (string)($r['end_on'] ?? '');
        $isReal = function ($d) { return $d !== '' && strpos($d, '0000-00-00') !== 0; };
        if ($isReal($start)) {
            try {
                $when = (new DateTime($start))->format('j M Y');
                if ($isReal($end)) { $when .= ' – ' . (new DateTime($end))->format('j M Y'); }
            } catch (Throwable $e) { $when = ''; }
        }
        $out[] = ['event_id' => (int)($r['event_id'] ?? 0),
                  'location' => trim((string)($r['location'] ?? '')), 'when' => $when,
                  'title' => trim((string)$r['event_title']),
                  // All three in-person fee tiers + their date windows (raw strings).
                  'early_amount'   => trim((string)($r['early_amount']   ?? '')),
                  'early_start_on' => trim((string)($r['early_start_on'] ?? '')),
                  'early_end_on'   => trim((string)($r['early_end_on']   ?? '')),
                  'advance_amount' => trim((string)($r['advance_amount'] ?? '')),
                  'advance_end_on' => trim((string)($r['advance_end_on'] ?? '')),
                  'gate_amount'    => trim((string)($r['gate_amount']    ?? '')),
                  'gate_start_on'  => trim((string)($r['gate_start_on']  ?? ''))];
    }
    return $out;
}

/**
 * Grouped programme catalogue for the AI prompt: each active programme, its KB,
 * and its LIVE upcoming sessions (country + dates) from the Event table. Replaces
 * the old flat "list every event" catalogue. '' if no programmes are defined
 * (callers fall back to wa_events_catalog()).
 */
/** Format an in-person event's full fee schedule — early bird, advance and standard,
 *  each with its date window — from the tier fields on a wa_program_events() row.
 *  '' if no priced tier. Amounts are shown as stored (a currency word is kept; a bare
 *  number is prefixed 'USD'). */
function wa_event_pricing($e) {
    $fmtDate = function ($d) {
        $d = trim((string)$d);
        if ($d === '' || strpos($d, '0000-00-00') === 0) { return ''; }
        try { return (new DateTime($d))->format('j M Y'); } catch (Throwable $ex) { return $d; }
    };
    $money = function ($a) {
        $a = trim((string)$a);
        if ($a === '' || (float)$a == 0.0) { return ''; }
        if (preg_match('/[a-zA-Z$€£]/', $a)) { return $a; }          // already has a currency word/symbol
        // Strip trailing zeros ONLY after a decimal point (so "380" stays 380, not 38;
        // "380.00" -> 380; "380.50" -> 380.5).
        if (strpos($a, '.') !== false) { $a = rtrim(rtrim($a, '0'), '.'); }
        return 'USD ' . $a;
    };
    $tiers = [];
    if (($m = $money($e['early_amount'] ?? '')) !== '') {
        $by = $fmtDate($e['early_end_on'] ?? '');
        $tiers[] = 'early bird ' . $m . ($by !== '' ? ' (until ' . $by . ')' : '');
    }
    if (($m = $money($e['advance_amount'] ?? '')) !== '') {
        $by = $fmtDate($e['advance_end_on'] ?? '');
        $tiers[] = 'advance ' . $m . ($by !== '' ? ' (until ' . $by . ')' : '');
    }
    if (($m = $money($e['gate_amount'] ?? '')) !== '') {
        $from = $fmtDate($e['gate_start_on'] ?? '');
        $tiers[] = 'standard ' . $m . ($from !== '' ? ' (from ' . $from . ')' : '');
    }
    return $tiers ? implode(', ', $tiers) : '';
}

function wa_programs_catalog($conn) {
    $progs = wa_programs_list($conn, true);
    if (!$progs) { return ''; }
    $blocks = [];
    foreach ($progs as $p) {
        $block = $p['name'];
        $kb = wa_knowledge_get_ai($conn, 'program', (int)$p['id']);
        if (trim($kb) !== '') {
            if (mb_strlen($kb) > 1200) { $kb = mb_substr($kb, 0, 1200) . '…'; }
            $block .= "\n" . $kb;
        }
        $sessions = [];
        foreach (wa_program_events($conn, $p) as $e) {
            $line = wa_event_display($e['location'], $e['when']);
            // This session's OWN full in-person fee schedule — early bird, advance and
            // standard, each with its date window (do NOT quote the online/virtual fee
            // for an in-person session — they differ).
            $pricing = wa_event_pricing($e);
            if ($pricing !== '') { $line .= ' — in-person fees: ' . $pricing; }
            // This session's OWN canonical registration link (by event_id), so a
            // location-specific request (e.g. Eswatini) gets THAT event's link —
            // not the programme's general one.
            $sLink = wa_event_register_url((int)$e['event_id']);
            if ($sLink !== '') { $line .= ' — register: ' . $sLink; }
            $sessions[] = $line;
        }
        $block .= "\nSessions / availability (each session's OWN in-person fee + register link — use these for the "
                . "in-person option, not the online fee/link): "
                . ($sessions ? implode('; ', $sessions) : 'none scheduled yet — dates on request');
        $blocks[] = $block;
    }
    return implode("\n\n", $blocks);
}

/**
 * Every active academic/online course (Event rows marked 'ACADEMIC#…'), listed by
 * title + qualification. These are intake-based (enrol anytime) and do NOT depend
 * on a programme keyword match — so the AI always knows the full academic
 * offering and never denies a course we actually run. '' if none.
 */
function wa_academic_catalog($conn, $limit = 80) {
    $limit = (int)$limit;
    $res = mysqli_query($conn,
        "SELECT event_id, event_title, location FROM `Event`
          WHERE status = 1 AND location LIKE 'ACADEMIC#%'
          ORDER BY event_title ASC LIMIT $limit");
    if (!$res || mysqli_num_rows($res) === 0) { return ''; }
    $blocks = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $loc  = (string)($r['location'] ?? '');
        $qual = trim(substr($loc, strpos($loc, '#') + 1));
        $qual = $qual !== '' ? ucwords(str_replace(['_', '-'], ' ', $qual)) : '';
        $line = '• ' . trim((string)$r['event_title'])
              . ($qual !== '' ? ' — ' . $qual : '')
              . ' (online, intake-based — enrol anytime)';
        $reg = wa_event_register_url((int)$r['event_id']);
        if ($reg !== '') { $line .= ' — register: ' . $reg; }
        // Include the course's OWN knowledge base (fees, content, how it works) so
        // the AI can answer about it even when the chat is scoped to another course.
        $kb = wa_knowledge_get_ai($conn, 'event', (int)$r['event_id']);
        if (trim($kb) !== '') {
            if (mb_strlen($kb) > 900) { $kb = mb_substr($kb, 0, 900) . '…'; }
            $line .= "\n  " . str_replace("\n", "\n  ", trim($kb));   // indent under the course
        }
        $blocks[] = $line;
    }
    return implode("\n", $blocks);
}

/**
 * The full trainings context injected into the AI prompt: themed programmes (with
 * their KB + live location sessions) AND the standalone list of academic/online
 * courses. Falls back to the flat event list only when neither exists.
 */
function wa_trainings_catalog($conn) {
    $parts = [];
    $progs = wa_programs_catalog($conn);
    if ($progs !== '') { $parts[] = $progs; }
    $acad = wa_academic_catalog($conn);
    if ($acad !== '') { $parts[] = "ACADEMIC / ONLINE COURSES (intake-based — we enrol people into these anytime):\n" . $acad; }
    if (!$parts) { return wa_events_catalog($conn); }
    return implode("\n\n", $parts);
}

/**
 * Idempotently ensure the two-piece KB columns + the learnings queue exist, so a
 * missed manual migration never breaks a save. Cheap and safe to call often
 * (ADD COLUMN / CREATE TABLE IF NOT EXISTS). Runs once per request.
 */
function wa_kb_ensure_schema($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_knowledge`
        ADD COLUMN IF NOT EXISTS `body_ai` MEDIUMTEXT DEFAULT NULL AFTER `body`,
        ADD COLUMN IF NOT EXISTS `ai_updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `body_ai`");
    // Allow 'program' (a training theme) as a KB subject alongside course/event.
    @mysqli_query($conn, "ALTER TABLE `wa_knowledge`
        MODIFY COLUMN `ref_type` ENUM('course','event','program') NOT NULL");
    // Training programmes (themes) — e.g. M&E, Data Analysis, Academic Programs.
    // Their country/location/dates come live from the Event table via keywords.
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wa_programs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(190) NOT NULL,
        `keywords` VARCHAR(500) DEFAULT NULL,
        `assigned_to` VARCHAR(255) DEFAULT NULL,
        `status` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_wa_program_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Reps for a programme: CSV of registered_users.id, same convention as
    // course.assigned_to / Event.assigned_to. An onsite enquiry that has not yet
    // named a country is assigned to the FIRST id here, and every id in the list
    // can see the chat in their inbox.
    @mysqli_query($conn, "ALTER TABLE `wa_programs`
        ADD COLUMN IF NOT EXISTS `assigned_to` VARCHAR(255) DEFAULT NULL");
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wa_kb_learnings` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `ref_type` ENUM('course','event') NOT NULL,
        `ref_id` INT UNSIGNED NOT NULL,
        `conversation_id` INT UNSIGNED DEFAULT NULL,
        `contact_id` INT UNSIGNED DEFAULT NULL,
        `message_id` BIGINT UNSIGNED DEFAULT NULL,
        `body` MEDIUMTEXT NOT NULL,
        `status` ENUM('pending','approved','dismissed') NOT NULL DEFAULT 'pending',
        `created_by` INT UNSIGNED DEFAULT NULL,
        `reviewed_by` INT UNSIGNED DEFAULT NULL,
        `reviewed_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_wa_kb_learn_ref` (`ref_type`, `ref_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Raw KB text (what a human typed/edited). Shown in the editor. '' if none. */
function wa_knowledge_get($conn, $refType, $refId) {
    $rt = "'" . mysqli_real_escape_string($conn, $refType) . "'";
    $rid = (int)$refId;
    $res = mysqli_query($conn, "SELECT body FROM wa_knowledge WHERE ref_type = $rt AND ref_id = $rid LIMIT 1");
    $r = $res ? mysqli_fetch_assoc($res) : null;
    return $r ? (string)$r['body'] : '';
}

/**
 * The KB text the LIVE AI answers from — now always the raw text you wrote.
 *
 * It used to return an AI-rewritten copy (body_ai) built on every save. That was a
 * second, silent author between the staff who curate the knowledge base and the
 * customer: a save could quietly reword or lose a detail, and what the AI answered
 * from was not what anyone had typed or reviewed. The knowledge base is written
 * cleanly by hand, so it is used verbatim.
 *
 * Kept as a function rather than inlined because eight call sites read through it —
 * this is the single place the decision lives. body_ai is left in the table
 * untouched (unread, no longer written) so nothing is destroyed and the old
 * behaviour can be restored by reverting this function and wa_knowledge_set().
 */
function wa_knowledge_get_ai($conn, $refType, $refId) {
    return wa_knowledge_get($conn, $refType, $refId);
}

/**
 * Store the KB text exactly as written.
 *
 * No longer runs the text through the AI on save: what you type is what the live AI
 * answers from. Saving is now immediate rather than waiting on a provider call, and
 * cannot fail or silently reword your text when the provider is slow or down.
 */
function wa_knowledge_set($conn, $refType, $refId, $body) {
    wa_kb_ensure_schema($conn);
    $rt = "'" . mysqli_real_escape_string($conn, $refType) . "'";
    $rid = (int)$refId;
    $b = wa_sql($conn, $body);
    mysqli_query($conn,
        "INSERT INTO wa_knowledge (ref_type, ref_id, body) VALUES ($rt, $rid, $b)
         ON DUPLICATE KEY UPDATE body = VALUES(body)");
}

/** Regenerate body_ai from the current raw body via the AI.
 *  NOT called any more — the KB is used verbatim (see wa_knowledge_get_ai). Kept so
 *  the behaviour can be restored deliberately rather than rebuilt from scratch. */
function wa_knowledge_reprocess($conn, $refType, $refId) {
    wa_kb_ensure_schema($conn);
    $raw = wa_knowledge_get($conn, $refType, $refId);
    if (trim($raw) === '') { return false; }
    $name = wa_ref_name($conn, $refType, (int)$refId) ?: 'this programme';
    $processed = wa_kb_process($conn, $raw, $name);
    if ($processed === '') { return false; }   // AI down -> keep raw as the answer source
    $rt = "'" . mysqli_real_escape_string($conn, $refType) . "'";
    $rid = (int)$refId;
    mysqli_query($conn,
        "UPDATE wa_knowledge SET body_ai = " . wa_sql($conn, $processed) . ", ai_updated_at = NOW()
          WHERE ref_type = $rt AND ref_id = $rid");
    return true;
}

/** Distil raw KB text into clean, labelled bullets for easy AI referencing.
 *  Preserves facts AND any rule sections (Escalation / Do-Not-Say). '' on failure. */
function wa_kb_process($conn, $raw, $name) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ''; }
    $system =
        "You tidy a knowledge base for a WhatsApp admissions advisor at Vantage Africa School of Leadership. "
        . "Reformat the notes below about \"{$name}\" into clean, scannable PLAIN-TEXT bullet points grouped "
        . "under short labels (e.g. Overview, Fees, Deposit, Installments, Duration, Schedule, Delivery, "
        . "Requirements, Certification, FAQs). Rules: (1) Use ONLY facts present in the notes — never invent "
        . "fees, dates or figures. (2) Do not drop any fact. (3) Merge duplicates and fix obvious typos. "
        . "(4) Keep any 'Escalation', 'Do-Not-Say', 'Tone' or 'Objections' guidance VERBATIM under its own "
        . "heading — these are rules for the AI, not customer text. (5) No preamble or sign-off, just the bullets.";
    $res = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => "Notes:\n" . $raw]],
                          ['max_tokens' => 1100, 'timeout' => 90]);
    return empty($res['ok']) ? '' : trim((string)$res['text']);
}

// ---- "Learn from the team" review queue ----

/** Capture something a human told a client, tied to the chat's course/event, for
 *  supervisor review. Skips trivial/short replies. Returns the new id or 0. */
function wa_kb_learning_add($conn, $refType, $refId, $convId, $contactId, $messageId, $body, $createdBy = null) {
    wa_kb_ensure_schema($conn);
    if (!in_array($refType, ['course', 'event'], true) || (int)$refId <= 0) { return 0; }
    $body = trim((string)$body);
    // Ignore greetings / one-liners that carry no course knowledge.
    if (mb_strlen($body) < 25) { return 0; }
    if (preg_match('/^\s*(hi|hello|hey|thanks|thank you|ok|okay|noted|sure|great|welcome|karibu)\b[.! ]*$/i', $body)) { return 0; }
    $rt = "'" . mysqli_real_escape_string($conn, $refType) . "'";
    $stmt = mysqli_prepare($conn,
        "INSERT INTO wa_kb_learnings (ref_type, ref_id, conversation_id, contact_id, message_id, body, created_by)
         VALUES ($rt, ?, ?, ?, ?, ?, ?)");
    $rid = (int)$refId; $cv = $convId !== null ? (int)$convId : null; $ct = $contactId !== null ? (int)$contactId : null;
    $mid = $messageId !== null ? (int)$messageId : null; $cb = $createdBy !== null ? (int)$createdBy : null;
    mysqli_stmt_bind_param($stmt, 'iiiisi', $rid, $cv, $ct, $mid, $body, $cb);
    mysqli_stmt_execute($stmt);
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

/** Pending learnings for a course/event (newest first). */
function wa_kb_learnings_pending($conn, $refType, $refId) {
    wa_kb_ensure_schema($conn);
    $rt = "'" . mysqli_real_escape_string($conn, $refType) . "'";
    $rid = (int)$refId;
    $res = mysqli_query($conn,
        "SELECT l.*, COALESCE(NULLIF(s.full_name,''), ru.fullname) AS author
           FROM wa_kb_learnings l
      LEFT JOIN registered_users ru ON ru.id = l.created_by
      LEFT JOIN staff s ON s.system_user_id = l.created_by
          WHERE l.ref_type = $rt AND l.ref_id = $rid AND l.status = 'pending'
          ORDER BY l.id DESC");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    return $rows;
}

/** Approve a learning: append it to the raw KB (visible/editable) and regenerate
 *  the processed bullets so the AI picks it up. Returns true on success. */
function wa_kb_learning_approve($conn, $learningId, $reviewerId = null) {
    wa_kb_ensure_schema($conn);
    $id = (int)$learningId;
    $res = mysqli_query($conn, "SELECT * FROM wa_kb_learnings WHERE id = $id AND status = 'pending' LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row) { return false; }
    $refType = $row['ref_type']; $refId = (int)$row['ref_id'];
    $raw = wa_knowledge_get($conn, $refType, $refId);
    $add = "• " . trim((string)$row['body']);
    $section = "Learned from the team:";
    if (strpos($raw, $section) !== false) {
        $newRaw = rtrim($raw) . "\n" . $add;
    } else {
        $newRaw = rtrim($raw) . ($raw !== '' ? "\n\n" : '') . $section . "\n" . $add;
    }
    // Save the raw text; the AI reads it verbatim (no processing step any more).
    wa_knowledge_set($conn, $refType, $refId, $newRaw);
    $rv = $reviewerId !== null ? (int)$reviewerId : 'NULL';
    mysqli_query($conn,
        "UPDATE wa_kb_learnings SET status = 'approved', reviewed_by = $rv, reviewed_at = NOW() WHERE id = $id");
    return true;
}

/** Dismiss a learning without touching the KB. */
function wa_kb_learning_dismiss($conn, $learningId, $reviewerId = null) {
    wa_kb_ensure_schema($conn);
    $id = (int)$learningId;
    $rv = $reviewerId !== null ? (int)$reviewerId : 'NULL';
    mysqli_query($conn,
        "UPDATE wa_kb_learnings SET status = 'dismissed', reviewed_by = $rv, reviewed_at = NOW()
          WHERE id = $id AND status = 'pending'");
    return true;
}

/**
 * Apply a natural-language edit command to a knowledge base and return the FULL
 * updated text — e.g. "set the fee to USD 350", "add a 20% early-bird discount",
 * "remove the onsite venue section". The caller decides whether to save it.
 * @return array {ok, text?} or {ok:false, error}
 */
function wa_kb_command($conn, $refType, $refId, $currentKb, $command) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['ok' => false, 'error' => 'no_provider']; }
    $command = trim((string)$command);
    if ($command === '') { return ['ok' => false, 'error' => 'no_command']; }

    $name = '';
    if (in_array($refType, ['course', 'event'], true) && (int)$refId > 0) {
        $name = wa_ref_name($conn, $refType, (int)$refId);
    }
    $cur = trim((string)$currentKb);

    $system = "You maintain a plain-text knowledge base for the WhatsApp admissions assistant at Vantage Africa "
        . "School of Leadership" . ($name ? " for the programme \"{$name}\"" : "") . ". You are given the CURRENT "
        . "knowledge base and an EDIT INSTRUCTION. Apply ONLY that instruction and return the COMPLETE updated "
        . "knowledge base.\n"
        . "Rules:\n"
        . "- Keep all existing content and structure (including any numbered sections and the section 7/8 rules) "
        . "unless the instruction says to change or remove it.\n"
        . "- Change ONLY what the instruction asks; do not reword or reorganise unrelated lines.\n"
        . "- When adding a fact (fee, deposit, discount, date, FAQ, etc.), put it under the most appropriate "
        . "section, creating a labelled line if needed.\n"
        . "- Never invent facts that are not in the instruction or already present.\n"
        . "- Output PLAIN TEXT only — the full updated knowledge base, with no markdown fences and no commentary.";
    $user = "CURRENT KNOWLEDGE BASE:\n" . ($cur !== '' ? $cur : "(empty)") . "\n\nEDIT INSTRUCTION:\n" . $command;

    $res = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]], ['max_tokens' => 8000, 'timeout' => 90]);
    if (empty($res['ok'])) { return ['ok' => false, 'error' => $res['error'] ?? 'ai_failed']; }
    $text = trim((string)$res['text']);
    $text = preg_replace('/^```[a-z]*\s*\n?|\n?```\s*$/i', '', $text);   // strip stray code fences
    $text = trim($text);
    if ($text === '') { return ['ok' => false, 'error' => 'empty_result']; }
    return ['ok' => true, 'text' => $text];
}

/** Parse an uploaded image from a base64 data: URL into ['mime','data'] for the AI
 *  (or null). Accepts png/jpeg/webp/gif, caps at ~6MB of base64. */
function wa_image_from_dataurl($dataUrl) {
    $dataUrl = (string)$dataUrl;
    if ($dataUrl === '') { return null; }
    if (!preg_match('#^data:(image/(?:png|jpe?g|webp|gif));base64,([A-Za-z0-9+/=\s]+)$#i', $dataUrl, $m)) {
        return null;
    }
    $mime = strtolower($m[1]);
    if ($mime === 'image/jpg') { $mime = 'image/jpeg'; }
    $b64 = preg_replace('/\s+/', '', $m[2]);
    if ($b64 === '' || strlen($b64) > 6 * 1024 * 1024) { return null; }   // ~4.5MB image
    return ['mime' => $mime, 'data' => $b64];
}

/**
 * Conversational KB editor. The staff member chats to change the knowledge; each
 * turn the AI either ASKS a clarifying question or APPLIES the edit (and we save
 * it). Reads the current KB from the DB each turn so multi-step edits build on
 * each other. $history: [['role'=>'user'|'assistant','content'=>...], ...].
 * $image: optional ['mime','data'(base64)] example the model can look at.
 * @return array {ok, action:'ask'|'edit', message, kb?, location?} or {ok:false, error}
 */
function wa_kb_chat($conn, $refType, $refId, $history, $image = null) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['ok' => false, 'error' => 'no_provider']; }
    if (!in_array($refType, ['course', 'event', 'program'], true) || (int)$refId <= 0) {
        return ['ok' => false, 'error' => 'no_ref'];
    }
    $name = wa_ref_name($conn, $refType, (int)$refId);
    $cur  = trim((string)wa_knowledge_get($conn, $refType, (int)$refId));

    $transcript = '';
    foreach ((array)$history as $h) {
        $c = trim((string)($h['content'] ?? ''));
        if ($c === '') { continue; }
        $role = (($h['role'] ?? '') === 'assistant') ? 'Editor' : 'Staff';
        $transcript .= $role . ': ' . $c . "\n";
    }
    $hasImage = (!empty($image['data']) && !empty($image['mime']));
    if ($transcript === '' && !$hasImage) { return ['ok' => false, 'error' => 'no_history']; }
    if ($transcript === '') { $transcript = "Staff: (see the attached image)\n"; }

    $system = "You are a knowledge-base editor for the WhatsApp admissions assistant at Vantage Africa School of "
        . "Leadership" . ($name ? " for the programme \"{$name}\"" : "") . ". A staff member edits the knowledge "
        . "base by chatting with you. DEFAULT TO ACTING — make the change; only ask a question as a last resort. "
        . "On each turn choose ONE:\n"
        . "- EDIT (strongly prefer this): apply the change and return the COMPLETE updated knowledge base plus a "
        . "one-line confirmation of exactly what you changed AND WHERE — always name the section heading (e.g. "
        . "\"Updated the fee under '2. PRICING & PAYMENT'.\"). Make reasonable assumptions instead of asking; if you "
        . "assumed something, SAY SO in the confirmation so the staff member can correct it in their next message. "
        . "Do NOT ask which section (you decide), and do NOT ask for confirmation before applying.\n"
        . "- ASK (rare — last resort): only when a REQUIRED fact is missing that you cannot reasonably infer and "
        . "only the staff member knows (e.g. an exact new fee, date or amount they didn't state). Ask at most ONE "
        . "short, specific question, and only if you truly cannot make a sensible edit without it. For "
        . "behavioural/coaching changes you should almost NEVER need to ask — craft the rule and apply it.\n"
        . "The staff member gives TWO kinds of change:\n"
        . "1) FACTS — fees, deposit, dates, schedule, requirements, etc. Edit the relevant factual section.\n"
        . "2) COACHING — they describe how the AI should or should NOT respond to clients (tone, exact wording, how "
        . "to handle an objection like price, when to hand off), usually because they've SEEN the AI answer a "
        . "client poorly. Turn that into a concise, durable RULE and add or update it under the right rules section: "
        . "'=== 8. TONE & DO-NOT-SAY ===' (wording, always-say / never-say), '=== 6. OBJECTIONS & HESITATIONS ===' "
        . "(handling pushback), '=== 7. ESCALATION ===' (when to involve a human), or add a Q/A under "
        . "'=== 5. FREQUENTLY ASKED QUESTIONS ==='. The live AI reads sections 6-8 as RULES and follows them, so "
        . "phrase coaching as a standing instruction, not a one-off note. Recording coaching the staff member gives "
        . "you is allowed and is NOT 'inventing facts'. If the wording is rough, improve it yourself and apply it "
        . "rather than asking.\n"
        . "IMPROVE, don't just transcribe: rewrite the staff member's recommendation into a clear, specific, "
        . "well-phrased rule that fits this knowledge base's style and is directly actionable for the AI — fix "
        . "vague or rough wording and make it concrete using THIS programme's real details from the knowledge (its "
        . "format, audience, fees, schedule, trainer, etc.). First SCAN the current knowledge: if related guidance "
        . "already exists, refine or merge with it instead of duplicating or contradicting it, and flag any clash "
        . "with an existing fact. When you ASK, make the question SPECIFIC TO THIS PROGRAMME using what's in the "
        . "knowledge — never a generic question.\n"
        . "Rules: keep all existing content and structure unless told otherwise; change only what's asked; NEVER "
        . "invent facts (fees, deposits, discounts, dates) the staff member hasn't given you; keep the knowledge as "
        . "plain text (no markdown code fences). Keep everything organised under clearly LABELLED section headings "
        . "written as '=== N. SECTION NAME ===' (use the standard 8 sections where they fit; create a new labelled "
        . "section if none suits the change) so sections are easy to locate.\n"
        . "The staff member may ATTACH AN IMAGE showing an example (a screenshot of a poor AI reply, a page "
        . "layout, a document, or how something should look). Read the image and use it to understand the change "
        . "they want — describe what you took from it in your confirmation.\n"
        . "CURRENT KNOWLEDGE BASE:\n" . ($cur !== '' ? $cur : "(empty)") . "\n\n"
        . "Respond with ONLY JSON: {\"action\":\"ask\"|\"edit\", \"message\":\"<what to say to the staff member>\", "
        . "\"kb\":\"<the FULL updated knowledge base — ONLY when action is edit>\", "
        . "\"location\":\"<the exact section heading where the change is, ONLY when action is edit>\"}.";
    $user = "Conversation so far:\n" . $transcript . "\nRespond now as JSON.";

    $res = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]],
                          ['json' => true, 'max_tokens' => 8000, 'timeout' => 90,
                           'image' => $hasImage ? $image : null]);
    if (empty($res['ok'])) { return ['ok' => false, 'error' => $res['error'] ?? 'ai_failed']; }
    $data = wa_json_extract($res['text']);
    if (!$data) { return ['ok' => false, 'error' => 'bad_response']; }

    $action  = (($data['action'] ?? '') === 'edit') ? 'edit' : 'ask';
    $message = trim((string)($data['message'] ?? ''));
    $kb = null;
    if ($action === 'edit') {
        $kb = isset($data['kb']) ? trim((string)$data['kb']) : '';
        $kb = trim(preg_replace('/^```[a-z]*\s*\n?|\n?```\s*$/i', '', $kb));
        if ($kb === '') {
            $action = 'ask';
            if ($message === '') { $message = 'Could you clarify the exact change you want?'; }
        } else {
            wa_knowledge_set($conn, $refType, (int)$refId, $kb);   // persist immediately
        }
    }
    $location = trim((string)($data['location'] ?? ''));
    if ($message === '') { $message = ($action === 'edit') ? 'Done — I updated the knowledge base.' : 'What would you like to change?'; }
    return [
        'ok' => true, 'action' => $action, 'message' => $message,
        'kb' => ($action === 'edit' ? $kb : null),
        'location' => ($action === 'edit' ? $location : ''),
    ];
}

// ---- Website -> KB draft (AI) ----

/** Simple GET fetch (follows redirects). Returns ['status','body','mime']. */
function wa_http_get($url, $timeout = 20, $headers = []) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'VantageWA/1.0',
    ];
    if ($headers) { $opts[CURLOPT_HTTPHEADER] = $headers; }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $mime = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ['status' => $status, 'body' => is_string($body) ? $body : '', 'mime' => $mime];
}

/** Download a WhatsApp/360dialog media file by its media id. Returns ['ok','body','mime']. */
function wa_fetch_media($mediaId) {
    $auth = ['D360-API-KEY: ' . WA_DIALOG_KEY];
    // Step 1: media id -> temporary download URL.
    $meta = wa_http_get(rtrim(WA_DIALOG_URL, '/') . '/' . rawurlencode($mediaId), 20, $auth);
    if ($meta['status'] < 200 || $meta['status'] >= 300) {
        error_log('[wa-media] step1 ' . $meta['status'] . ' id=' . $mediaId . ' body=' . substr($meta['body'], 0, 200));
        return ['ok' => false, 'error' => 'meta_' . $meta['status']];
    }
    $j = json_decode($meta['body'], true);
    $url  = is_array($j) ? ($j['url'] ?? null) : null;
    $mime = is_array($j) ? ($j['mime_type'] ?? null) : null;
    if (!$url) {
        error_log('[wa-media] no url in step1: ' . substr($meta['body'], 0, 300));
        return ['ok' => false, 'error' => 'no_url'];
    }

    // Step 2: download the bytes. 360dialog returns a Meta lookaside URL that you
    // can't fetch directly — download it via the 360dialog host with the API key.
    $candidates = [];
    if (stripos($url, '360dialog.io') !== false) {
        $candidates[] = [$url, $auth];
    } else {
        $rewritten = preg_replace('#^https?://[^/]+#i', rtrim(WA_DIALOG_URL, '/'), $url);
        $candidates[] = [$rewritten, $auth];   // primary: 360dialog host + key
        $candidates[] = [$url, $auth];          // fallback: original + key
        $candidates[] = [$url, []];             // fallback: original, no key
    }
    $last = 0;
    foreach ($candidates as $c) {
        $bin = wa_http_get($c[0], 60, $c[1]);
        if ($bin['status'] >= 200 && $bin['status'] < 300 && $bin['body'] !== '') {
            return ['ok' => true, 'body' => $bin['body'], 'mime' => $mime ?: ($bin['mime'] ?: 'application/octet-stream')];
        }
        $last = $bin['status'];
    }
    error_log('[wa-media] step2 all failed last=' . $last . ' url=' . substr($url, 0, 120));
    return ['ok' => false, 'error' => 'dl_' . $last];
}

/** Rough file extension for a mime type (for download filenames). */
function wa_ext_for_mime($mime) {
    $map = [
        'image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp', 'image/gif' => '.gif',
        'application/pdf' => '.pdf', 'audio/ogg' => '.ogg', 'audio/mpeg' => '.mp3', 'audio/amr' => '.amr',
        'video/mp4' => '.mp4', 'application/msword' => '.doc', 'text/plain' => '.txt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
        'application/vnd.ms-excel' => '.xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
    ];
    $mime = strtolower(trim(explode(';', (string)$mime)[0]));
    return $map[$mime] ?? '';
}

/** HTML for a media message bubble (image/video/audio inline, else a download link). */
function wa_media_html($type, $msgId, $mime, $caption = '') {
    $u = 'wa_media.php?msg=' . (int)$msgId;
    if ($type === 'image') {
        $h = '<a href="' . $u . '" target="_blank"><img src="' . $u . '" alt="image" style="max-width:220px;max-height:220px;border-radius:8px;display:block"></a>';
    } elseif ($type === 'video') {
        $h = '<video controls preload="none" style="max-width:240px;border-radius:8px" src="' . $u . '"></video>';
    } elseif ($type === 'audio' || $type === 'voice') {
        $h = '<audio controls preload="none" src="' . $u . '"></audio>';
    } else {
        $h = '<a href="' . $u . '" target="_blank" class="d-inline-flex align-items-center gap-1">'
           . '<i class="bi bi-file-earmark-arrow-down"></i> Download ' . wa_e($type) . '</a>';
    }
    if ($caption !== null && $caption !== '') {
        $h .= '<div class="mt-1" style="white-space:pre-wrap;word-wrap:break-word">' . nl2br(wa_e($caption)) . '</div>';
    }
    return $h;
}

/** Strip a web page down to readable text (scripts/styles/tags removed, capped). */
function wa_html_to_text($html, $max = 8000) {
    $html = preg_replace('#<(script|style|noscript|svg)\b[^>]*>.*?</\1>#is', ' ', (string)$html);
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace('/\s*\n\s*/u', "\n", $text);
    $text = trim(preg_replace('/\n{3,}/u', "\n\n", $text));
    if (mb_strlen($text) > $max) { $text = mb_substr($text, 0, $max); }
    return $text;
}

/** Best-effort: find site pages matching a course/event name via sitemap.xml. */
function wa_discover_urls($name, $limit = 3) {
    $base = rtrim(WA_SITE_URL, '/');
    $res = wa_http_get($base . '/sitemap.xml', 15);
    if ($res['status'] < 200 || $res['status'] >= 300 || $res['body'] === '') { return []; }
    if (!preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#i', $res['body'], $m)) { return []; }
    $tokens = array_filter(explode(' ', wa_normalize($name)), function ($w) { return mb_strlen($w) >= 4; });
    if (!$tokens) { return []; }
    $scored = [];
    foreach ($m[1] as $u) {
        $slug = wa_normalize(urldecode($u));
        $score = 0;
        foreach ($tokens as $t) { if (strpos($slug, $t) !== false) { $score++; } }
        if ($score > 0) { $scored[$u] = $score; }
    }
    arsort($scored);
    return array_slice(array_keys($scored), 0, $limit);
}

/**
 * Draft a knowledge base for a course/event from website content.
 * $urls: optional explicit page URLs; if empty we auto-discover from the sitemap.
 * @return array {ok, text?, sources?, error?}
 */
function wa_kb_generate($conn, $refType, $refId, $urls = []) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['ok' => false, 'error' => 'no_provider']; }
    $name = wa_ref_name($conn, $refType, (int)$refId);
    if (!$name) { return ['ok' => false, 'error' => 'unknown_ref']; }

    $urls = array_values(array_filter(array_map('trim', (array)$urls), function ($u) {
        return $u !== '' && preg_match('#^https?://#i', $u);
    }));
    if (!$urls) { $urls = wa_discover_urls($name); }
    if (!$urls) { $urls = [rtrim(WA_SITE_URL, '/')]; }
    $urls = array_slice($urls, 0, 3);

    $content = ''; $used = [];
    foreach ($urls as $u) {
        $r = wa_http_get($u, 20);
        if ($r['status'] >= 200 && $r['status'] < 300 && $r['body'] !== '') {
            $content .= "\n\n--- Source: $u ---\n" . wa_html_to_text($r['body'], 8000);
            $used[] = $u;
        }
    }
    if ($content === '') { return ['ok' => false, 'error' => 'no_content', 'sources' => $urls]; }
    if (mb_strlen($content) > 16000) { $content = mb_substr($content, 0, 16000); }

    $system =
        "You write concise knowledge-base notes for a WhatsApp admissions advisor at Vantage Africa "
        . "School of Leadership. From the website content provided, extract only facts about the "
        . "course/programme \"{$name}\". Output PLAIN TEXT (no markdown) as short labelled lines, "
        . "covering when available: Overview, a one-line Outcome (the concrete result a graduate walks away "
        . "with), Fees, Deposit, Installment terms, Duration, Schedule/Intakes, Delivery (online/in-person), "
        . "Requirements, Certification, and any FAQs. Include ONLY facts present in the content — never "
        . "invent fees, deposits, dates or figures. Omit anything not stated.";
    $usr = "Course/Programme: {$name}\n\nWebsite content:\n" . $content;

    $res = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $usr]], ['max_tokens' => 900, 'timeout' => 60]);
    if (empty($res['ok'])) { return ['ok' => false, 'error' => $res['error'] ?? 'ai_failed', 'sources' => $used]; }
    return ['ok' => true, 'text' => trim($res['text']), 'sources' => $used];
}

/** Recent message turns for a contact, oldest-first, as [role => user|assistant, content]. */
function wa_ai_history($conn, $contactId, $limit = 12) {
    wa_message_flags_ensure($conn);
    $contactId = (int)$contactId;
    $limit = (int)$limit;
    // Retracted replies (#19) leave the AI's context, so it never treats a reply a
    // human deleted as its own prior turn.
    $res = mysqli_query($conn,
        "SELECT direction, type, body FROM wa_messages
          WHERE contact_id = $contactId AND type <> 'note' AND deleted_at IS NULL
          ORDER BY id DESC LIMIT $limit");
    $rows = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    $rows = array_reverse($rows);
    $turns = [];
    foreach ($rows as $r) {
        $c = trim((string)$r['body']);
        if ($c === '' && !in_array($r['type'], ['text', 'template'], true)) {
            // media without a caption -> describe it so the AI can respond
            $c = $r['direction'] === 'outbound' ? '[sent a ' . $r['type'] . ']' : '[the customer sent a ' . $r['type'] . ' file]';
        }
        if ($c === '') { continue; }
        $turns[] = ['role' => $r['direction'] === 'outbound' ? 'assistant' : 'user', 'content' => $c];
    }
    return $turns;
}

/** The grounded system prompt (shared by the live answerer and the KB tester).
 *  $intl = catalogue of active international trainings (Events), shown so the AI
 *  can answer location/topic questions about them instead of deflecting.
 *  $regLink = the registration link to share when someone wants to register.
 *  $eventScoped = true for a specific in-person event chat: answer ONLY from that
 *  event's knowledge and never surface the virtual programmes / online courses. */
function wa_ai_system_prompt($refName, $kb, $intl = '', $regLink = '', $eventScoped = false, $outlineUrl = '', $outlineText = '', $profile = '', $mode = 'unknown', $country = '') {
    // Once a prospect has said "in person", the online option stops existing for this
    // conversation: mentioning it reads as an upsell away from what they asked for, and
    // it is the single most common way the bot talked an onsite lead out of the sale.
    $onsite    = ($mode === 'onsite');
    // Their country comes free from the dialling code of the number they messaged from,
    // so asking "which country are you in?" wastes the reply that matters most — it is
    // where onsite leads used to stall and go cold.
    $knowsWhere = (trim((string) $country) !== '');
    return "You are the WhatsApp admissions advisor for Vantage Africa School of Leadership, a premium "
        . "leadership-training organisation based in Nairobi. "
        . ($refName ? "This prospect is interested in: {$refName}. " : "")
        . "Sound like a trusted, knowledgeable admissions advisor — not a chatbot. Your job is to build genuine "
        . "interest and trust, then guide the prospect towards registering.\n\n"

        . "UNDERSTAND BEFORE YOU REPLY (most important rule): read the customer's message(s) carefully and work out "
        . "what they ACTUALLY mean and want before writing anything. Respond specifically to THAT — never fire a "
        . "generic or templated reply that ignores what they said. If several messages arrived together, treat them "
        . "as ONE and respond once, not to each separately. Judge the kind of message first:\n"
        . "- A genuine enquiry → answer their real question.\n"
        . "- An automated / away / auto-reply message, another business's bot, spam, or clearly a wrong number → do "
        . "NOT launch into a sales script or repeat yourself. Reply once, briefly and warmly, and only offer to help "
        . "with leadership/professional training if relevant; otherwise a short, polite acknowledgement is enough.\n"
        . "- NEVER tell someone they 'reached us by mistake' when in fact WE messaged them first (e.g. after a "
        . "broadcast). If we contacted them and they're not interested, be gracious — don't imply they contacted us.\n"
        . "- If you're not sure what they want, ask ONE short clarifying question instead of guessing.\n"
        . "Never send the same message twice; if you'd only repeat yourself, say something genuinely new or stop.\n\n"

        . "Today's date is " . date('j M Y') . " — use it to work out which fee tier or upcoming session applies.\n\n"

        . ($profile !== ''
            ? "WHAT YOU ALREADY KNOW ABOUT THIS PROSPECT (use it — do NOT ask again for anything listed here):\n"
              . $profile . "\n"
              . "- Greet them by name if you have it, and never re-request a detail already listed above. Only ask "
              . "for details that are genuinely missing.\n"
              . "- If a registration is already in progress and they say 'enrol me' / 'get me registered', CONTINUE "
              . "that registration — do not restart it or divert back to programme questions.\n\n"
            : "")

        . "TONE:\n"
        . "- Be warm, reassuring, calm, confident, professional and helpful. Prioritise trust over excitement.\n"
        . "- Do NOT sound over-excited, playful, gimmicky, salesy or over-casual. Avoid exclamation-mark spam.\n"
        . "- Never open with generic enthusiasm ('Great to hear!', 'Awesome!', 'Fantastic!'). Open instead with a "
        . "warm, direct, helpful line.\n"
        . "- Use at most ONE emoji (rarely two), only when it genuinely adds warmth — most replies need none.\n"
        . "- NEVER be rude, curt, dismissive, sarcastic, impatient or condescending — not even slightly, and not "
        . "even when the customer is frustrated, repeats themselves, is blunt, or writes something odd. Stay warm, "
        . "patient and courteous in EVERY message. If they're upset, acknowledge it kindly and help; never scold, "
        . "lecture, or push back defensively. When in doubt, err on the side of extra warmth and politeness.\n\n"

        . "KEEP IT SHORT & CONVERSATIONAL:\n"
        . "- WhatsApp length: usually 2–5 short sentences. NEVER send a wall of text.\n"
        . "- Reveal information progressively across turns — answer what was asked and invite the next question, "
        . "rather than dumping the whole programme at once.\n"
        . "- Use a short bullet list only for options (e.g. payment methods).\n"
        . "- DO NOT REPEAT yourself. The prospect can see your earlier messages, so re-stating things you already "
        . "said (the deposit, installments, payment flexibility, or the same benefit wording) sounds like a script. "
        . "Unless they ask about payment directly, add NEW information or address their specific point instead.\n\n"

        . "UNDERSTANDING & ANSWERING THE CLIENT:\n"
        . "- ANSWER EVERY QUESTION THEY ASKED. If a message (or several quick messages) contains more than one "
        . "question, identify each one and answer ALL of them — never answer one and silently drop the rest. Give "
        . "each its own substantive answer, not a passing acknowledgement. When there are several, structure the "
        . "reply (e.g. short labelled lines or a tight list) so the client can see each point was addressed — while "
        . "still keeping it concise.\n"
        . "- Answer the ACTUAL question and their real intent. Do NOT volunteer unrelated information in place of "
        . "what they asked, and do not steer back to your script when they've asked something specific — address "
        . "their point first, then guide.\n"
        . "- Track where the client is in their journey (first enquiry, comparing options, ready to register, "
        . "already paid) and pitch the reply to that stage.\n"
        . "- CLARIFY WHEN UNCLEAR: if their intent is genuinely ambiguous, ask a brief, natural clarifying question "
        . "(you may ask more than one) instead of guessing or answering the wrong thing. Phrase it warmly, the way a "
        . "human agent would ('Just so I point you to the right one — did you mean X or Y?').\n"
        . ($onsite
            ? "- IN-PERSON ENQUIRY — SETTLED (this overrides every other instruction about delivery mode): this "
              . "prospect has ALREADY told us they want IN-PERSON / on-site training. The question is closed.\n"
              . "  * NEVER mention, offer, suggest, compare, or hint at an online, virtual, e-learning, remote or "
              . "Zoom version of anything — not as an alternative, not as a fallback, not as 'we also have…', not "
              . "in passing. For this conversation the online option DOES NOT EXIST. Do not ask them to choose "
              . "between modes, and never ask 'did you mean the online one?'.\n"
              . "  * NEVER quote an online fee, an online registration link, or rolling-intake / 'start any time' "
              . "wording. In-person fees and links come ONLY from the specific session in the TRAINING PROGRAMMES "
              . "list (its 'in-person fees' and 'register' entries).\n"
              . "  * NAMES DIFFER ACROSS MODES: the ONLINE version of a subject often has a specific product name "
              . "(e.g. 'Data Analysis Using SPSS') while the IN-PERSON version is listed as separate events under a "
              . "broader subject name (e.g. 'Data Analysis Training'). They are the SAME subject in two modes. "
              . "Search the in-person EVENTS by SUBJECT (data analysis, M&E, etc.), NOT by the online course's exact "
              . "title — never say 'we have no on-site [online course name]'; check the events list for that topic "
              . "and location first, and only say there's none if genuinely none exists.\n"
              . "  * If we have NO session in their country, say so plainly and offer the NEAREST scheduled "
              . "in-person session, or say a colleague will confirm upcoming dates for their region — never fall "
              . "back to the online option, and never invent a location or date.\n"
            : "- DELIVERY MODE — ASK FIRST, NEVER ASSUME: many topics run in TWO modes — online/virtual AND in-person "
              . "(on-site events). Data analysis, M&E and similar are offered both ways. When someone expresses "
              . "interest in such a topic, your VERY FIRST reply must NOT describe, pitch or assume either mode (do "
              . "NOT default to the online course). Greet them warmly and ask which they want, e.g. 'Happy to help! "
              . "Are you interested in the online or the in-person (on-site) option?'.\n"
              . "  * If they choose IN-PERSON: find the matching in-person session in the TRAINING PROGRAMMES list "
              . "and give THAT session's details (dates, venue, fee tiers, link). If there is no session in their "
              . "country, say so plainly and offer the nearest scheduled one — never invent a location or date.\n"
              . "  * NAMES DIFFER ACROSS MODES: the ONLINE version of a subject often has a specific product name "
              . "(e.g. 'Data Analysis Using SPSS') while the IN-PERSON version is listed as separate events under a "
              . "broader subject name (e.g. 'Data Analysis Training'). They are the SAME subject in two modes. For "
              . "an in-person request, search the in-person EVENTS by SUBJECT (data analysis, M&E, etc.), NOT by the "
              . "online course's exact title — never say 'we have no on-site [online course name]'; check the events "
              . "list for that topic and location first, and only say there's none if genuinely none exists.\n"
              . "  * If they choose ONLINE: give the online/virtual option's details.\n"
              . "  Only quote dates, fees, venue, schedule or a link AFTER the mode is known. Once set, keep EVERY "
              . "detail specific to that mode and never mix the two.\n")

        . ($knowsWhere
            ? "- WHERE THEY ARE — ALREADY KNOWN, NEVER ASK: their country is listed in WHAT YOU ALREADY KNOW above; "
              . "we read it from the international dialling code of the number they are messaging from. NEVER ask "
              . "'which country are you in?', 'where are you based?' or 'where would you like to attend?'. Use it "
              . "straight away to find the matching in-person session and lead with that session's details. If you "
              . "genuinely need to narrow a large country to a venue, ask for the CITY only — once, and only if it "
              . "actually changes your answer. If THEY name a different country or city themselves, believe them "
              . "over the phone number and switch to that.\n"
              . "  * This OVERRIDES the KNOWLEDGE below. Our FAQ text was written for people who phoned in, so it "
              . "says things like 'the team confirms which country/cohort you want' — that step is already done. "
              . "Never turn a line of KNOWLEDGE into a question about where they are.\n"
              . "  * Instead of asking, state it and move on: 'Since you're in " . trim((string) $country) . ", the "
              . "session that applies to you is …'. If nothing runs there, say that plainly and give the nearest "
              . "one — still without asking them to confirm their country.\n"
            : "- WHERE THEY ARE: their number gives us no country, so for an in-person request ask once, warmly, "
              . "which country or city they're in — e.g. 'Which country are you in, so I can check the sessions "
              . "available to you?' — then match the session from the TRAINING PROGRAMMES list.\n")
        . "  SOURCES (critical): the IN-PERSON fees and link come from the SPECIFIC session in the TRAINING "
        . "PROGRAMMES list above (its 'in-person fees' and 'register' entries). For an IN-PERSON event, present ALL "
        . "the fee tiers shown for that session — early bird, advance and standard — each WITH its date window, so "
        . "the customer can see the full schedule, and point out which rate currently applies based on today's date. "
        . ($onsite
            ? "If you don't have the exact in-person fees or link for their session, do the human hold (get it for "
              . "them and come back). Do NOT substitute an online figure or link — not even as an indication.\n\n"
            : "The ONLINE/virtual fee and registration link come from the programme's own KNOWLEDGE below. NEVER "
              . "quote the online fee or link for an in-person request, or the in-person fee or link for an online "
              . "request. If you don't have the exact fees or link for the mode they chose, do the human hold (get "
              . "it for them and come back) rather than borrowing the other mode's figure.\n\n")

        . "CONFIRM BEFORE COMMITTING TO A SESSION: never tell a customer they are booked, registered or 'set' for a "
        . "specific in-person session, and never treat a particular city's session as chosen, until THEY confirm it "
        . "(or give a location that clearly matches it). "
        . ($knowsWhere
            ? "You already know their country, so LEAD with the session(s) that match it — present the matching "
              . "option(s) and let them confirm, rather than asking where they are. "
            : "For an in-person request, FIRST ask where they are / which city session they want, present the "
              . "matching option(s), and only proceed once they confirm. ")
        . "If we have no session in their location, say so plainly and offer the nearest actual session"
        . ($onsite ? "" : " or the online option")
        . " — do NOT imply they're signed up for a city they never picked. If a customer changes their mind to a "
        . "different course or session, confirm that switch with them before treating the new one as chosen.\n\n"

        . "HARD RULE — NO SINGAPORE: we do NOT run any Singapore training, study tour, trip or programme for WhatsApp "
        . "enquirers. NEVER mention, offer, suggest or describe anything in or about Singapore, and never imply travel "
        . "there. If someone asks about Singapore, tell them plainly we don't offer that and point them to our actual "
        . ($onsite ? "in-person events" : "online courses and in-person events")
        . ". Only ever discuss programmes that appear in the lists above.\n\n"

        . "CONVERSATION FLOW (follow this order — do NOT skip ahead):\n"
        . ($onsite
            ? "1. First greeting or vague interest ('Hi', 'I'm interested in X') → reply briefly and warmly, staying "
              . "entirely within the in-person offering, and ask what they'd like to know. Do NOT raise the delivery "
              . "mode (it is already settled as in-person)"
              . ($knowsWhere ? " and do NOT ask where they are (you already know)" : "")
              . ". Do NOT dump the full overview, do NOT ask for personal details, and do NOT mention the fee yet.\n"
            : "1. First greeting or vague interest ('Hi', 'I'm interested in X') → reply briefly and warmly. If X is "
              . "a topic offered in BOTH online and in-person modes (see DELIVERY MODE above), your FIRST question is "
              . "which mode they want — do NOT describe a course or assume online. Otherwise ask what they'd like to "
              . "know. Either way, do NOT dump the full overview, do NOT ask for personal details, and do NOT mention "
              . "the fee yet.\n")
        . "2. Build interest FIRST — explain the value, outcomes and what makes the programme worthwhile, "
        . "concisely, guided by what they ask.\n"
        . "3. When they are ready to register / enroll / join, DO NOT collect their personal details yourself and "
        . "DO NOT say you will 'create an account' — registration is handled by the team, not by you. "
        . ($regLink !== ''
            ? "To register, use the RIGHT link — and ONLY a genuine registration/application link. If the prospect "
              . "is asking about a specific event, location or session that has its OWN 'register:' link in the "
              . "KNOWLEDGE or the sessions list, share THAT exact link (e.g. an Eswatini session gets the Eswatini "
              . "link, not the general one). Never share a different programme's link. "
              . "EVENTS AND ONLINE COURSES: every event and academic/online course in the lists above carries its "
              . "own 'register:' link (a vantageafricaleaders.com/program-details.php?id=… page). That IS the correct, "
              . "safe registration page for that exact item — when they're ready to register for a specific event or "
              . "online course, share its 'register:' link directly and with confidence (it is never a login page). "
              . "CRITICAL — NEVER present a portal LOGIN or 'sign in' page (any URL containing /login, or a page to "
              . "log into an existing account) as a way to register: a login page is only for people who ALREADY "
              . "have an account, so sending it to a new prospect is wrong and confusing. If the only URL available "
              . "is a login page, or there is no proper course-specific registration link, DO NOT send a link at "
              . "all — instead register them right here on WhatsApp: say you'll take their details here and walk "
              . "them through it (and do NOT paste a generic link alongside that offer). Use {$regLink} only if it "
              . "is genuinely this course's registration/application page and not a login page. "
            : "")
        . "Set escalate to true (INTERNAL) so a human quietly completes it — but never tell the customer a colleague "
        . "or 'the team' will do it; in your own voice, just let them know you're getting them set up and will "
        . "confirm shortly. The system may also start a short registration form automatically — do not duplicate it.\n"
        . "4. AFTER you have their details, share the schedule, modules and requirements FIRST. Only THEN present "
        . "the fee and payment options. Never lead with the fee and never pressure them to pay early.\n"
        . "5. Mention the fee only after conveying value, or when the prospect directly asks about it.\n\n"

        . "PAYMENT FRAMING (only once you reach fees):\n"
        . "- Present the deposit as the easiest, low-commitment way to secure a place — not merely an alternative.\n"
        . "- The deposit secures the seat; the balance is paid in manageable installments before the course ends; "
        . "paying in full is an optional alternative.\n"
        . "- Use ONLY the actual deposit amount, fee and installment terms from the KNOWLEDGE — never invent figures.\n\n"

        . "HANDLING A PRICING OBJECTION ('it's expensive', 'too much', 'I can't afford it', 'beyond my budget', "
        . "'why is it so expensive', 'the cost is too high', 'I expected it cheaper'):\n"
        . "Treat this as a VALUE question FIRST and a payment question second. Answer the unspoken question 'why is "
        . "this worth the price?' BEFORE returning to 'how would you like to pay?'. Keep it short and follow this order:\n"
        . "1. Acknowledge briefly, without defensiveness or over-apologising ('That's a fair question.', 'It's "
        . "reasonable to weigh the investment.').\n"
        . "2. Reinforce VALUE before any payment talk: give the programme's outcome plus TWO or THREE specific, "
        . "tangible benefits — and VARY which benefits you cite each time; never repeat the same list. Draw from: "
        . "live expert-led sessions, real-world case studies, scenario-based exercises, interactive discussions, "
        . "practical leadership frameworks, networking with other participants, an internationally verifiable "
        . "certificate, continued platform access after the programme, downloadable resources, tools they can "
        . "apply immediately.\n"
        . "3. ONLY after value, and ONLY if you have not already explained it earlier in this chat, mention the "
        . "deposit as a way to lower the upfront commitment. If you already mentioned the deposit/installments "
        . "before, do NOT repeat it — add new value or address their specific point instead.\n"
        . "4. Discounts: if the KNOWLEDGE lists one, state it. Otherwise, in your own warm voice, say you'll get them "
        . "the best current offer and come right back shortly (set escalate) — never say you'll 'check with the "
        . "team', never reveal you don't know, and never promise or imply a discount exists.\n"
        . "5. End with ONE (only one) follow-up question, e.g. 'Is your main concern the overall investment, or "
        . "paying it upfront?' / 'Is there a budget you're hoping to stay within?' / 'Would it help if I explained "
        . "more of what's included?'.\n"
        . "NEVER use defensive lines like 'it's worth every dollar', 'trust me', 'it's actually cheap', 'it's not "
        . "expensive', or 'most people think it's worth it'. Explain the value objectively and let them decide.\n\n"

        . "DISCOUNTS:\n"
        . "- If the KNOWLEDGE lists a current discount/offer, state it directly (the exact figure, e.g. an early-"
        . "registration percentage) and pair it with the deposit option. Do NOT say you'll 'check'.\n"
        . "- Only if NO discount is configured in the KNOWLEDGE, let them know in your own voice that you'll get them "
        . "the exact current offer and come right back to them shortly, and set escalate to true — never say you'll "
        . "'check with the team', never reveal a limitation, and never invent a discount.\n\n"

        . "ALWAYS END WITH A CALL TO ACTION:\n"
        . "End EVERY reply with ONE clear, natural next-step question that fits the stage — and never repeat the "
        . "same CTA you used in your previous message. Examples by stage: early → 'What would you like to know "
        . "about the programme?' / 'Any other questions I can help with?'; ready to join → 'Would you like me to "
        . "help you register today?' / 'Shall I reserve your seat for the upcoming intake?'; at payment → 'Would "
        . "you like to pay in full or start with the deposit?' / 'Shall I share the payment details?'\n\n"

        . "DIRECT REQUESTS OVERRIDE THE SALES SCRIPT:\n"
        . "- When the prospect gives a clear instruction, do THAT first — before any sales flow, pitch or further "
        . "questions:\n"
        . "  * 'send/share the link' or 'registration link' -> actually paste the correct registration link now. "
        . "NEVER say 'the link has everything you need' without sending the link itself.\n"
        . "  * 'register me' / 'enroll me' / 'get me enrolled' -> move straight to registration; do NOT loop back to "
        . "programme questions or restart the pitch.\n"
        . "  * 'talk to a human' / 'agent' / 'representative' -> hand off immediately (set escalate true) and stop "
        . "selling.\n"
        . "  * 'cancel' / 'stop' / 'not interested' -> acknowledge it and stop; do NOT keep pitching the programme.\n"
        . "- Never defer or ignore a direct request in order to continue your own script.\n\n"

        . "CONSISTENCY & ABBREVIATIONS:\n"
        . "- Never contradict a fact you or the KNOWLEDGE already stated earlier in THIS chat (e.g. don't call a "
        . "programme four weeks now and six weeks later, or name a different trainer). If unsure whether a detail is "
        . "correct, say you'll confirm it — do NOT state a new, conflicting figure, date, duration or name.\n"
        . "- If a course code or abbreviation is ambiguous or could match more than one programme (e.g. 'SSD' could "
        . "mean Supervisory Skills Development), ASK the prospect to confirm which one they mean BEFORE answering — "
        . "never guess and describe the wrong course.\n\n"

        . "PROMISES & TIMES (be truthful about what you can actually do):\n"
        . "- Never say you are registering them, creating an account, sending an email/login/invoice, or that it is "
        . "'done'. You cannot perform those actions. Say only that you have 'submitted their details for "
        . "registration' and the team will complete it.\n"
        . "- Never invent a specific delivery time ('within two hours', 'by end of day') for an email, document or "
        . "credential unless the KNOWLEDGE explicitly gives that timeframe. When you escalate you may say the team "
        . "will follow up, but do not fabricate a clock.\n"
        . "- Never share bank-account, mobile-money or other payout/payment details unless those exact details "
        . "appear in the KNOWLEDGE below.\n\n"

        . "GROUNDING & ESCALATION:\n"
        . "- Answer the prospect's MOST RECENT message first; if they switch to a different programme, follow "
        . "them there and answer that.\n"
        . "- STRICT GROUNDING: every FACT you state — fees, deposits, discounts, dates, durations, schedules, "
        . "intakes, locations/countries, venues, topics/curriculum, certification, requirements, who it's for, "
        . "outcomes, payment methods, contact details and links — MUST come from the KNOWLEDGE below or from the "
        . "TRAINING PROGRAMMES / ACADEMIC COURSES lists above (which carry our real, live locations and dates). "
        . "Those two sources are your ONLY source of facts.\n"
        . "- NEVER invent, guess, estimate, assume or 'fill in' a fact that isn't in those sources — no made-up "
        . "prices, dates, venues, durations, module lists, numbers, statistics, accreditations or promises. Do not "
        . "rely on general knowledge about the topic; rely only on what you were given here.\n"
        . "- If the answer isn't in the KNOWLEDGE or the lists: do NOT improvise and do NOT invent it. Reply the way "
        . "a warm human agent would when they need to look something up — acknowledge the question naturally and "
        . "commit to a specific next step ('Let me get you the exact figure and come right back to you shortly'). "
        . "Set escalate to true (INTERNAL only). NEVER say you 'don't have that', 'aren't sure', will 'check with "
        . "the team', 'ask a colleague', or otherwise reveal any limitation — simply get it for them like a person "
        . "would. A natural human hold is ALWAYS better than either an invented fact OR an admission of not knowing.\n"
        . "- You may still be warm, empathetic and conversational — but the moment it's a fact, it must be grounded.\n"
        . "- ESCALATION IS INVISIBLE TO THE CUSTOMER. Set escalate to true when you genuinely cannot answer from the "
        . "KNOWLEDGE, or the person needs a human action (a complaint or refund, a custom/corporate deal, a formal "
        . "invoice, confirming a payment they've already made, or completing a registration). 'escalate' is an "
        . "INTERNAL signal that quietly routes the chat to a human — it must NEVER surface in your reply. Do NOT tell "
        . "the customer you are referring or connecting them to a colleague, a human, or 'the team', and never say "
        . "you're 'checking with' anyone. Stay in the exact same warm voice you've used all along, acknowledge their "
        . "request, and let them know you're getting it sorted and will come back to them shortly. Do NOT escalate "
        . "merely because someone is interested or ready to join.\n"
        . "- ALWAYS reply — never send an empty message and never leave a message unanswered. Even when you escalate, "
        . "or when the customer repeats themselves, respond warmly in your own voice, reassure them you're on it, and "
        . "offer to help with anything else — WITHOUT ever mentioning a handoff. Keep answering every question you "
        . "CAN answer from the KNOWLEDGE.\n"
        . "- The KNOWLEDGE may include internal guidance sections ('Objections', 'Escalation', 'Tone', "
        . "'Do-Not-Say'). Treat those as RULES FOR YOU, never as text to quote or reveal. Follow the escalation "
        . "guidance, obey the do-not-say rules (no guarantees, no medical/legal claims, no competitor "
        . "comparisons), and use the preferred greeting, tone and sign-off.\n"
        . "- Whenever your reply promises that something will be sorted, sent, followed up, or that you'll come back "
        . "to them, you MUST set escalate to true (so a human actually completes it) — but phrase the promise in your "
        . "OWN voice, never attributing it to 'a colleague' or 'the team'.\n"
        . "- NEVER claim you have created an account, registered or enrolled them, or sent an email, login or "
        . "invoice — you cannot perform those actions, so never say they are done. Never tell them to log in to a "
        . "portal or check their inbox as though an account already exists. If they want to register, escalate "
        . "internally and tell the customer, in your own voice, that you're getting them set up and will confirm the "
        . "details shortly — do not pretend it is already done, and do not say 'the team' will do it.\n"
        . "- If the customer sends a document, image or file you cannot open it — warmly acknowledge you've received "
        . "it and will review it, and set escalate to true (never say you can't open files).\n\n"

        . "LANGUAGE:\n"
        . "- Detect the language of the prospect's MOST RECENT message and reply in that SAME language — "
        . "English, Swahili, French, or any other. Mirror them exactly: a French message gets a French reply, "
        . "a Swahili message gets Swahili, English gets English.\n"
        . "- NEVER answer in a different language from the one they just wrote in (e.g. never reply in Swahili to "
        . "a French or English message).\n"
        . "- If you genuinely cannot tell which language they are using, ask politely which language they'd prefer "
        . "rather than guessing.\n\n"

        . ($eventScoped
            ? "SCOPE — IMPORTANT: This conversation is about a specific IN-PERSON M&E TRAINING EVENT — a physical "
              . "training held at a particular city/venue on set dates. This is DIFFERENT from our virtual/online "
              . "M&E programme (they are similar but NOT the same): describe THIS in-person event — its venue, city, "
              . "dates, cost, on-site schedule and registration link — and never present it as, or mix in details "
              . "of, the online M&E programme. Answer ONLY from this event's KNOWLEDGE below (its registration link, "
              . "course outline, hotel, city and dates). Do NOT share, mention or recommend any of our other "
              . "programmes or online/virtual courses in this chat, and never share another event's or the "
              . "general/online registration link here. If the prospect asks about anything outside this event, "
              . "don't describe other offerings — say you'll connect them with the team (escalate) and give a timeframe.\n\n"
            : "")
        . ($outlineUrl !== ''
            ? "COURSE OUTLINE: every training event uses the SAME course outline. Whenever a prospect asks for the "
              . "outline, curriculum, programme, agenda or content of a training event, share EXACTLY and ONLY this "
              . "link: " . $outlineUrl . " — this is the ONE correct events outline link. Do NOT share any other "
              . "outline or Google Drive link, even if a different one appears elsewhere in the KNOWLEDGE (that would "
              . "be the wrong programme's outline).\n"
              . ($outlineText !== ''
                  ? "For SCHEDULE / AGENDA questions (the daily programme, session times, what is covered on each "
                    . "day, topics/modules), answer from this outline content — give the relevant days, times and "
                    . "topics, and you may also share the link above:\n" . $outlineText . "\n"
                  : "")
              . "\n"
            : "")

        . ($intl !== ''
            ? "TRAINING PROGRAMMES (our themes of in-person/international training — each with its general info and "
              . "its LIVE upcoming sessions by country/date, straight from our system; use this as fact):\n" . $intl . "\n\n"
              . "USING THE TRAINING PROGRAMMES:\n"
              . "- When a prospect asks whether we run a training on a topic, in a country, or in a city (e.g. "
              . "'Is there data analysis training in Cameroon?'), find the matching PROGRAMME, then check its "
              . "'Upcoming sessions'. Match on the topic AND the place — a location like 'Douala' or 'Yaoundé' "
              . "means Cameroon; 'Kampala' means Uganda, etc.\n"
              . "- If a session matches, answer confidently with the specifics (the programme, the city/country, "
              . "and the dates). Do NOT say you'll 'check with the team' or that you lack details — they're right "
              . "here. Then help them towards registering.\n"
              . "- If the programme exists but has NO session in that country yet, say so plainly and offer the "
              . "nearest scheduled session" . ($onsite ? "" : " or the online option") . " — don't invent a location "
              . "or date.\n"
              . ($onsite
                  ? "- The 'ACADEMIC / ONLINE COURSES' list is for online enquirers only. This prospect wants "
                    . "in-person, so treat that list as OFF LIMITS: do not read from it, quote it, or mention that "
                    . "any of it exists. It is there only so you never wrongly tell them we have no expertise in a "
                    . "subject. If they ask about a topic we currently run ONLY online, do NOT say 'we offer it "
                    . "online' and do NOT deny we cover it — say we can look at running it for their location, "
                    . "escalate, and name the topic in your handoff note.\n"
                  : "- The 'ACADEMIC / ONLINE COURSES' are real courses we currently offer and enrol people into at "
                    . "any time (rolling intakes — no country or fixed date). Treat every one of them as available "
                    . "now.\n")
              . "- CRITICAL — never deny a course we actually offer. Before telling anyone we don't have a course, "
              . "check ALL of the above (the training programmes AND the academic/online courses) AND the KNOWLEDGE. "
              . "If the course they name appears in any of them — even loosely (an abbreviation like 'CPA', 'M&E', "
              . "'SSD', or a near-match title) — CONFIRM we offer it and help them, do NOT say we don't. Only say we "
              . "don't offer something when it genuinely appears in none of these lists.\n"
              . "- Use a programme's or course's general info to explain what it covers, even before dates are set.\n"
              . "- When they want to book one of these, set escalate to true so a colleague completes it, and name "
              . "the specific programme + country in your handoff note.\n\n"
            : "")

        . "HANDOFF NOTE (staff-only, never shown to the customer):\n"
        . "- When (and only when) escalate is true, also write a 'handoff' note addressed to the human colleague "
        . "who will take over. It is NOT sent to the customer.\n"
        . "- Make it specific and actionable, based on where the conversation actually is NOW: state (a) who the "
        . "prospect is and which programme, (b) exactly what they need or asked for that you couldn't do, and "
        . "(c) the single next action the colleague should take. One or two short sentences.\n"
        . "- Good: 'Adrian is ready to register for Practical Accounting and asked about scholarships — confirm if "
        . "any current discount applies, then complete his registration.' Bad (vague): 'Customer needs help.'\n"
        . "- When escalate is false, set handoff to an empty string.\n\n"

        . "KNOWLEDGE:\n" . ($kb !== '' ? $kb
            : "(No knowledge base has been added for this one yet — so you have NO specific facts about it. Do NOT "
            . "invent any. Answer only from the programme/course lists above, otherwise say you'll confirm the "
            . "details with the team and escalate.)") . "\n\n"

        // The KNOWLEDGE above is the last thing read and carries the most weight, and it
        // was written for phone enquiries — so it asks for the country and describes the
        // online course. Restate the two hard rules AFTER it, or they get overridden.
        . (($onsite || $knowsWhere)
            ? "BEFORE YOU WRITE — RE-CHECK THE KNOWLEDGE YOU JUST READ:\n"
              . ($knowsWhere
                  ? "- It was written for people who phoned in, so parts of it assume we still have to establish "
                    . "where the customer is. We do not: they are in " . trim((string) $country) . ". Do not ask "
                    . "them to confirm it, and do not repeat any KNOWLEDGE line that asks for their country, "
                    . "location or cohort.\n"
                  : "")
              . ($onsite
                  ? "- Parts of it describe the ONLINE version (its fee, its registration link, e-learning access, "
                    . "rolling intakes, weekly online sessions). This customer is coming IN PERSON. Use the "
                    . "KNOWLEDGE only for what the training itself covers; take every fee, date, venue and link "
                    . "from the in-person session instead, and say nothing about the online version existing.\n"
                  : "")
              . "\n"
            : "")

        . "WHEN THE CUSTOMER IS READY TO JOIN:\n"
        . "- Set \"request_call_permission\" to true when THEIR LATEST MESSAGE plainly says they want to "
        . "join, register, enrol, apply, sign up, or that they are interested and want to know how to "
        . "start — for a programme we have already identified. In English or Swahili (\"nataka "
        . "kujiunga\", \"ningependa kujiunga\").\n"
        . "- Answer their actual question FIRST and normally. The flag runs a separate step; it never "
        . "replaces your reply and you must not mention it, promise a call, or ask whether they want "
        . "one. Do NOT say a request has been sent — a separate message handles that only if it "
        . "actually succeeds.\n"
        . "- Set it to FALSE for: a greeting, a bare price or date question, small talk, an answer to "
        . "something you asked, a complaint, an opt-out, anything mid-registration or mid-payment, and "
        . "anything you are not sure about. False is always the safe answer — a colleague can still "
        . "arrange a call by hand.\n\n"

        . "Respond with ONLY JSON: {\"reply\": \"<the WhatsApp message to send>\", \"escalate\": <true|false>, "
        . "\"handoff\": \"<staff-only note when escalating, else empty>\", "
        . "\"request_call_permission\": <true|false>}.";
}

/**
 * Dry-run the AI against a course/event's knowledge for a single question.
 * Same grounding as the live answerer, but sends NOTHING and records nothing —
 * for the "test the AI" box on the Knowledge page. Returns {ok, reply, escalate}.
 */
function wa_ai_test($conn, $refType, $refId, $question, $kbOverride = null) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['ok' => false, 'error' => 'no_provider']; }
    $question = trim((string)$question);
    if ($question === '') { return ['ok' => false, 'error' => 'no_question']; }

    $refName = '';
    if (in_array($refType, ['course', 'event', 'program'], true) && (int)$refId > 0) {
        $refName = wa_ref_name($conn, $refType, (int)$refId);
    }
    $isEvent = ($refType === 'event');   // event chats: only their own KB, no course/programme catalogue
    // Events answer from the linked M&E programme's knowledge + live DB details
    // (the editor's current text, when supplied, is used as the event's own notes).
    if ($isEvent && (int)$refId > 0) {
        $kb = wa_event_effective_kb($conn, (int)$refId, $kbOverride);
    } elseif ($kbOverride !== null) {
        $kb = trim((string)$kbOverride);
    } else {
        $kb = ($refName !== '') ? wa_knowledge_get($conn, $refType, (int)$refId) : '';
    }
    $regLink = ($refName !== '') ? wa_register_link($conn, $refType, (int)$refId) : '';
    $outline = wa_outline_applies($refType, $refName) ? wa_event_outline_url($conn) : '';
    $outlineTxt = $outline !== '' ? wa_event_outline_text($conn) : '';
    $system = wa_ai_system_prompt($refName, $kb, $isEvent ? '' : wa_trainings_catalog($conn), $regLink, $isEvent, $outline, $outlineTxt, '', $isEvent ? 'onsite' : 'unknown');
    $user = "Conversation so far:\nCustomer: " . $question . "\n\nWrite the next assistant reply now as JSON.";
    $res = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]],
                          ['json' => true, 'max_tokens' => 600]);
    if (empty($res['ok'])) { return ['ok' => false, 'error' => $res['error'] ?? 'ai_failed']; }
    $data = wa_json_extract($res['text']);
    if (!$data) { $data = ['reply' => trim($res['text']), 'escalate' => false]; }
    return [
        'ok'       => true,
        'reply'    => trim((string)($data['reply'] ?? '')),
        'escalate' => !empty($data['escalate']),
        'has_kb'   => $kb !== '',
    ];
}

/**
 * Simulate one AI reply for the testing console — identical prompt, transcript
 * format and parsing to the live answerer (wa_ai_answer), but driven by a
 * supplied conversation history and grounded in the given (possibly unsaved)
 * knowledge. Sends nothing and writes nothing. This is what makes the sandbox
 * chat behave exactly like a real thread.
 * $history: array of ['role'=>'user'|'assistant','content'=>string], oldest first.
 * Returns {ok, reply, escalate, has_kb} or {ok:false, error}.
 */
function wa_ai_simulate($conn, $refType, $refId, $history, $kbOverride = null) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['ok' => false, 'error' => 'no_provider']; }

    $refName = '';
    if (in_array($refType, ['course', 'event', 'program'], true) && (int)$refId > 0) {
        $refName = wa_ref_name($conn, $refType, (int)$refId);
    }
    // Events answer from the linked M&E programme's knowledge + live DB details.
    if ($refType === 'event' && (int)$refId > 0) {
        $kb = wa_event_effective_kb($conn, (int)$refId, $kbOverride);
    } else {
        $kb = ($kbOverride !== null)
            ? trim((string)$kbOverride)
            : (($refName !== '') ? wa_knowledge_get_ai($conn, $refType, (int)$refId) : '');
    }

    // Same "Customer:/Assistant:" transcript the live answerer feeds the model.
    $transcript = '';
    foreach ((array)$history as $h) {
        $content = trim((string)($h['content'] ?? ''));
        if ($content === '') { continue; }
        $role = (($h['role'] ?? '') === 'assistant') ? 'Assistant' : 'Customer';
        $transcript .= $role . ': ' . $content . "\n";
    }
    if ($transcript === '') { return ['ok' => false, 'error' => 'no_history']; }

    $regLink = ($refName !== '') ? wa_register_link($conn, $refType, (int)$refId) : '';
    $isEvent = ($refType === 'event');   // event chats: only their own KB, no course/programme catalogue
    $outline = wa_outline_applies($refType, $refName) ? wa_event_outline_url($conn) : '';
    $outlineTxt = $outline !== '' ? wa_event_outline_text($conn) : '';
    $system = wa_ai_system_prompt($refName, $kb, $isEvent ? '' : wa_trainings_catalog($conn), $regLink, $isEvent, $outline, $outlineTxt, '', $isEvent ? 'onsite' : 'unknown');
    $user = "Conversation so far:\n" . $transcript . "\nWrite the next assistant reply now as JSON.";
    $res = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]],
                          ['json' => true, 'max_tokens' => 600]);
    if (empty($res['ok'])) { return ['ok' => false, 'error' => $res['error'] ?? 'ai_failed']; }

    $data = wa_json_extract($res['text']);
    if (!$data) { $data = ['reply' => trim($res['text']), 'escalate' => false]; }
    $reply    = trim((string)($data['reply'] ?? ''));
    $escalate = !empty($data['escalate']);
    if (!$escalate && $reply !== '' && preg_match(
        '/\b(connect you|reach out|our team|admissions team|someone will|have someone|get back to you|a representative|our staff|will contact you)\b/i',
        $reply)) {
        $escalate = true;
    }
    if ($reply === '' && $escalate) {
        $reply = "Thanks for reaching out — I'm on it and will come right back to you shortly. In the meantime, is there anything else I can help you with?";
    }
    if ($reply === '') { return ['ok' => false, 'error' => 'empty_reply']; }
    return ['ok' => true, 'reply' => $reply, 'escalate' => $escalate, 'has_kb' => $kb !== ''];
}

/**
 * Generate + send one AI reply for a conversation (grounded in its KB).
 * Escalates (flags the chat for a human) when the model is unsure.
 * $conv must include: id, contact_id, ref_type, ref_id, wa_id, ref_name.
 */
/**
 * Post an internal, staff-only handoff note into the thread (type='note'). It is
 * NEVER sent over WhatsApp — it renders as an italic "what the human needs to do"
 * line in wa_thread.php so whoever picks up the chat has current context (the
 * conversation may have moved on since the escalation). De-duped against the last
 * note so a run of escalations doesn't repeat the same note. Returns true if posted.
 */
/**
 * A staff comment on a conversation — the record of anything that happened OUTSIDE
 * WhatsApp: a phone call, a meeting, a payment promised, a decision taken. Stored
 * as type='note' so it is staff-only and NEVER sent to the customer, but attributed
 * to the rep who wrote it (unlike wa_ai_post_note, which the AI writes unattributed).
 *
 * Not de-duplicated: two reps legitimately log the same-looking update, and a repeat
 * of yesterday's note is meaningful progress, not noise.
 */
function wa_note_add($conn, $contactId, $body, $staffId = null) {
    wa_message_flags_ensure($conn);
    $cid  = (int)$contactId;
    $body = trim((string)$body);
    if ($cid < 1 || $body === '') { return false; }
    $sb = ((int)$staffId > 0) ? (int)$staffId : 'NULL';
    mysqli_query($conn,
        "INSERT INTO wa_messages (contact_id, direction, type, body, wa_timestamp, status, sent_by_staff)
         VALUES ($cid, 'outbound', 'note', " . wa_sql($conn, $body) . ", NOW(), 'note', $sb)");
    return mysqli_affected_rows($conn) > 0;
}

/**
 * Recent staff comments, newest first, for the AI's context.
 *
 * These are deliberately NOT part of wa_ai_history(): a note is not a chat turn, and
 * feeding it in as one would have the AI repeat internal wording back to the
 * customer. They belong in the known-facts block instead, where the AI treats them
 * as background it must act on but never quote.
 */
function wa_notes_recent($conn, $contactId, $limit = 5) {
    $cid = (int)$contactId;
    $limit = max(1, (int)$limit);
    $res = mysqli_query($conn,
        "SELECT m.body, m.wa_timestamp, COALESCE(NULLIF(s.full_name,''), ru.fullname) AS author
           FROM wa_messages m
      LEFT JOIN registered_users ru ON ru.id = m.sent_by_staff
      LEFT JOIN staff s             ON s.system_user_id = m.sent_by_staff
          WHERE m.contact_id = $cid AND m.type = 'note' AND m.sent_by_staff IS NOT NULL
          ORDER BY m.id DESC LIMIT $limit");
    $out = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $out[] = $r; } }
    return array_reverse($out);   // oldest first, so the AI reads them as a progression
}

function wa_ai_post_note($conn, $contactId, $note) {
    $note = trim((string)$note);
    if ($note === '') { return false; }
    $cid = (int)$contactId;
    // Skip if the most recent note is identical (conversation hasn't moved on).
    $r = mysqli_query($conn,
        "SELECT body FROM wa_messages WHERE contact_id = $cid AND type = 'note' ORDER BY id DESC LIMIT 1");
    $last = $r ? mysqli_fetch_assoc($r) : null;
    if ($last && strcasecmp(trim((string)$last['body']), $note) === 0) { return false; }
    mysqli_query($conn,
        "INSERT INTO wa_messages (contact_id, direction, type, body, wa_timestamp, status)
         VALUES ($cid, 'outbound', 'note', " . wa_sql($conn, $note) . ", NOW(), 'note')");
    return true;
}

/** How many outbound (non-note) messages we've sent this contact — used to rotate
 *  the referral nudges so a repeat never comes out word-for-word identical. */
function wa_ai_outbound_count($conn, $contactId) {
    $cid = (int)$contactId;
    $r = mysqli_query($conn, "SELECT COUNT(*) AS n FROM wa_messages
        WHERE contact_id = $cid AND direction = 'outbound' AND type <> 'note'");
    $row = $r ? mysqli_fetch_assoc($r) : null;
    return $row ? (int)$row['n'] : 0;
}

/** Short, varied "I'm on it — ask me anything else" lines. Used when a handoff
 *  message would otherwise repeat the previous one verbatim, so the bot stays
 *  engaged and NEVER goes silent. Escalation stays INVISIBLE — these never mention
 *  a team/colleague/human; they keep the assistant's own warm voice. */
function wa_ai_referral_nudge($n = 0) {
    $lines = [
        "I'm getting that sorted for you now and will come right back to you. In the meantime, is there anything else I can help you with?",
        "Working on that for you — I'll come back to you shortly. Meanwhile, feel free to ask me anything else about our programmes.",
        "I've got your details and I'm on it. Is there anything else I can help you with right now?",
        "Thanks for your patience — I'll come back to you shortly. Happy to answer any other questions in the meantime.",
    ];
    return $lines[abs((int)$n) % count($lines)];
}

/**
 * A customer sent something with NO readable text — a voice note, an image/video without a
 * caption, a location pin, a contact card, a document. The AI can't read those, so the old
 * code (which needs a text $body) skipped them entirely: no reply, no escalation, total
 * silence. This is exactly the "dodged message" symptom. Here we NEVER stay silent: make
 * sure the chat exists, acknowledge warmly, escalate to a human and leave a staff note.
 * Reactions (👍) are skipped — they need no reply. Returns a small status array.
 */
function wa_handle_media_message($conn, $contactId, $waId, $type) {
    $contactId = (int)$contactId;
    if ($type === 'reaction') { return ['ok' => true, 'skipped' => 'reaction']; }
    wa_ensure_conversation($conn, $contactId);
    $conv = wa_get_conversation($conn, $contactId);
    $labels = [
        'audio'    => 'voice note', 'image'    => 'image',   'video'  => 'video',
        'document' => 'document',   'sticker'  => 'sticker', 'location' => 'location',
        'contacts' => 'contact',    'order'    => 'order',
    ];
    $label = $labels[$type] ?? 'message';
    $reply = "Thanks! I've received your {$label} — let me get a colleague to take a proper "
           . "look and come right back to you shortly.";
    if ($conv) {
        $convId = (int)$conv['id'];
        mysqli_query($conn, "UPDATE wa_conversations SET escalated = 1, last_message_at = NOW() WHERE id = $convId");
        wa_ai_post_note($conn, $contactId, "Customer sent {$label} content the AI can't read — please take a look and reply.");
    }
    error_log('[wa-ai] media-handoff (' . $type . ') for contact ' . $contactId);
    $send = wa_send_text($conn, $waId, $reply);
    return ['ok' => !empty($send['ok']), 'escalated' => true, 'type' => $type];
}

/**
 * Graceful hand-off when the AI can't produce a reply (provider error/timeout,
 * empty or unparseable output). Instead of going SILENT — which reads as the bot
 * ignoring the customer — we send a short human-hand-off line and escalate the
 * chat so staff pick it up. Reuses the same duplicate-suppression as normal
 * escalations so repeated failures don't spam the identical line.
 */
function wa_ai_soft_handoff($conn, $conv, $reason) {
    $reply = "Thanks for your message — I'm getting that sorted for you and will come right back to you shortly. In the meantime, is there anything else I can help you with?";
    $cid = (int)$conv['contact_id'];
    $r = mysqli_query($conn,
        "SELECT body FROM wa_messages WHERE contact_id = $cid AND direction = 'outbound' AND type <> 'note' ORDER BY id DESC LIMIT 1");
    $prev = $r ? mysqli_fetch_assoc($r) : null;
    $prevBody = $prev ? trim((string)$prev['body']) : '';
    // Escalate regardless (so staff see it), but only SEND if we didn't just say this.
    $convId = (int)$conv['id'];
    mysqli_query($conn, "UPDATE wa_conversations SET escalated = 1, last_message_at = NOW() WHERE id = $convId");
    // Staff note: the AI itself broke, so the human needs the customer's last question.
    $lastInbound = '';
    $ri = mysqli_query($conn,
        "SELECT body FROM wa_messages WHERE contact_id = $cid AND direction = 'inbound' AND body <> '' ORDER BY id DESC LIMIT 1");
    if ($ri && ($rowi = mysqli_fetch_assoc($ri))) { $lastInbound = trim((string)$rowi['body']); }
    $note = $reason === 'ai_failed'
        ? "AI could not generate a reply (provider error). Please take over."
        : "AI had no usable answer here. Please take over.";
    if ($lastInbound !== '') { $note .= ' Customer\'s last message: "' . mb_substr($lastInbound, 0, 160) . '"'; }
    wa_ai_post_note($conn, $cid, $note);
    // Never go silent: if the standard line would repeat verbatim, send a varied
    // "you're referred — anything else?" nudge instead of suppressing.
    if ($prevBody !== '' && strcasecmp($prevBody, $reply) === 0) {
        $reply = wa_ai_referral_nudge(wa_ai_outbound_count($conn, $cid));
        if (strcasecmp($prevBody, $reply) === 0) { $reply = wa_ai_referral_nudge(wa_ai_outbound_count($conn, $cid) + 1); }
    }
    error_log('[wa-ai] soft-handoff (' . $reason . ') for contact ' . $cid);
    $send = wa_send_text($conn, $conv['wa_id'], $reply);
    return ['ok' => !empty($send['ok']), 'escalated' => true, 'reply' => $reply, 'reason' => $reason, 'send' => $send];
}

function wa_ai_answer($conn, $conv, $inboundText) {
    $provider = wa_active_provider($conn);
    if (!wa_provider_ready($provider)) { return ['ok' => false, 'error' => 'no_provider']; }

    $refName = $conv['ref_name'] ?? '';
    $refIsEvent = (($conv['ref_type'] ?? '') === 'event') && $conv['ref_id'] !== null;
    // Academic/online courses are stored as Event rows but must NOT be scoped like an
    // in-person event (no venue/city/outline); answer them like a course.
    $isAcademicEvent = $refIsEvent && wa_is_academic_event($conn, (int)$conv['ref_id']);
    $kb = '';
    if ($refIsEvent && !$isAcademicEvent) {
        // In-person event: answer from the linked M&E programme's knowledge + live DB details.
        $kb = wa_event_effective_kb($conn, (int)$conv['ref_id']);
    } elseif ($isAcademicEvent) {
        // Academic/online course: answer from its own processed knowledge base.
        $kb = wa_knowledge_get_ai($conn, 'event', (int)$conv['ref_id']);
    } elseif (in_array($conv['ref_type'], ['course', 'program'], true) && $conv['ref_id'] !== null) {
        // Answer from the AI-processed bullets (falls back to raw if not processed yet).
        $kb = wa_knowledge_get_ai($conn, $conv['ref_type'], (int)$conv['ref_id']);
    }

    // Recent transcript as a single user turn (keeps both providers happy).
    $hist = wa_ai_history($conn, (int)$conv['contact_id'], 12);
    $transcript = '';
    foreach ($hist as $h) {
        $transcript .= ($h['role'] === 'assistant' ? 'Assistant: ' : 'Customer: ') . $h['content'] . "\n";
    }

    $regLink = (in_array($conv['ref_type'], ['course', 'event', 'program'], true) && $conv['ref_id'] !== null)
        ? wa_register_link($conn, $conv['ref_type'], (int)$conv['ref_id']) : '';
    // Never hand the AI a portal LOGIN page as a "registration" link — it's for existing
    // accounts, not new prospects. Dropping it makes the AI offer WhatsApp signup instead.
    if ($regLink !== '' && preg_match('~/(login|signin|sign-in|account/login)~i', $regLink)) { $regLink = ''; }
    // Only IN-PERSON events get the event-scoped, no-catalogue, venue/outline treatment.
    // Academic online courses keep the catalogue and skip the in-person outline.
    $isEvent = $refIsEvent && !$isAcademicEvent;
    $outline = (!$isAcademicEvent && wa_outline_applies($conv['ref_type'] ?? '', $refName)) ? wa_event_outline_url($conn) : '';
    $outlineTxt = $outline !== '' ? wa_event_outline_text($conn) : '';

    // Build a short profile of what we already know, so the AI stops re-asking for
    // details the prospect already gave (name from the contact, plus any fields
    // captured in an in-progress registration, plus the programme of interest).
    $cid = (int)$conv['contact_id'];
    $known = [];
    $pn = wa_scalar_str($conn, "SELECT profile_name FROM wa_contacts WHERE id = $cid");
    if ($pn) { $known['Name'] = $pn; }
    // Country comes free from their number, so never ask where they are. For an
    // in-person enquiry this is the whole question — knowing it up front lets the
    // AI name the nearest session instead of stalling on "which country are you in?".
    $co = wa_scalar_str($conn, "SELECT country FROM wa_contacts WHERE id = $cid");
    if ($co !== '') { $known['Country (from their phone number)'] = $co; }
    $enrolling = false;
    $es = function_exists('wa_enroll_active') ? wa_enroll_active($conn, $cid) : null;
    if ($es) {
        $enrolling = true;
        $d = !empty($es['data']) ? json_decode($es['data'], true) : null;
        if (is_array($d)) {
            if (!empty($d['fullname']))     { $known['Name'] = $d['fullname']; }
            if (!empty($d['email']))        { $known['Email'] = $d['email']; }
            if (!empty($d['phone']))        { $known['Phone'] = $d['phone']; }
            if (!empty($d['country']))      { $known['Country'] = $d['country']; }
            if (!empty($d['organization'])) { $known['Organization'] = $d['organization']; }
        }
    }
    $profileLines = [];
    if ($refName) { $profileLines[] = '- Interested in: ' . $refName; }
    foreach ($known as $label => $val) { $profileLines[] = '- ' . $label . ': ' . $val; }
    if ($enrolling) { $profileLines[] = '- A registration is already in progress — continue it, do not restart.'; }
    // Staff comments: what happened away from WhatsApp (a call, a meeting, a payment
    // promised). The AI must act on these — otherwise it contradicts a colleague who
    // already spoke to the client — but must never read them out.
    $staffNotes = wa_notes_recent($conn, $cid, 5);
    if ($staffNotes) {
        $profileLines[] = '- Internal staff updates (private — act on these, never quote or mention them to the customer):';
        foreach ($staffNotes as $sn) {
            $when = trim((string)($sn['wa_timestamp'] ?? ''));
            $who  = trim((string)($sn['author'] ?? '')) ?: 'a colleague';
            $txt  = trim(preg_replace('/\s+/u', ' ', (string)$sn['body']));
            if ($txt === '') { continue; }
            $profileLines[] = '    • ' . ($when !== '' ? substr($when, 0, 16) . ' ' : '')
                            . $who . ': ' . mb_substr($txt, 0, 300);
        }
    }
    $profile = $profileLines ? implode("\n", $profileLines) : '';

    // The router has already worked out whether this is an onsite enquiry (and made it
    // sticky). Hand that verdict to the prompt so the reply never drifts back to the
    // virtual option, and hand it the country so it never asks where they are.
    // A conversation bound to a real (non-academic) Event is in-person by definition —
    // it has a venue and dates — so treat it as onsite even if the client never typed
    // the word and the router left delivery_mode at 'unknown'.
    $convMode = (string)($conv['delivery_mode'] ?? 'unknown');
    if ($isEvent) { $convMode = 'onsite'; }
    $system = wa_ai_system_prompt($refName, $kb, $isEvent ? '' : wa_trainings_catalog($conn), $regLink, $isEvent, $outline, $outlineTxt, $profile, $convMode, $co);

    $user = "Conversation so far:\n" . $transcript . "\nWrite the next assistant reply now as JSON.";

    $res = wa_ai_complete($provider, $system, [['role' => 'user', 'content' => $user]],
                          ['json' => true, 'max_tokens' => 600]);
    // Provider error/timeout: don't go silent — hand off to a human instead.
    if (empty($res['ok'])) {
        error_log('[wa-ai] provider failed: ' . ($res['error'] ?? 'ai_failed'));
        return wa_ai_soft_handoff($conn, $conv, 'ai_failed');
    }

    $data = wa_json_extract($res['text']);
    if (!$data) { $data = ['reply' => trim($res['text']), 'escalate' => false]; }  // fallback: raw text
    $reply    = trim((string)($data['reply'] ?? ''));
    $escalate = !empty($data['escalate']);
    $handoff  = trim((string)($data['handoff'] ?? ''));   // staff-only note, when escalating
    // Phase 1.2: the model's request for a call-permission handoff. Absent means
    // false, so an older prompt or a raw-text fallback simply never triggers it.
    $wantsCall = !empty($data['request_call_permission']);
    // Safety net: if the reply defers to a human but the flag wasn't set, escalate anyway.
    if (!$escalate && $reply !== '' && preg_match(
        '/\b(connect you|reach out|our team|admissions team|someone will|have someone|get back to you|a representative|our staff|will contact you)\b/i',
        $reply)) {
        $escalate = true;
    }
    if ($reply === '' && $escalate) {
        $reply = "Thanks for reaching out — I'm on it and will come right back to you shortly. In the meantime, is there anything else I can help you with?";
    }
    // AI produced nothing usable: hand off to a human rather than going silent.
    if ($reply === '') { return wa_ai_soft_handoff($conn, $conv, 'empty_reply'); }

    // The AI keeps helping even after a handoff. We never go silent: if the reply
    // would repeat the previous outbound word-for-word (e.g. the customer re-asks
    // the same thing), we send a short VARIED "you're referred — anything else?"
    // nudge instead of suppressing — so the customer always gets a response.
    if ($escalate) {
        $cid = (int)$conv['contact_id'];
        // Staff-only note: what the human needs to pick up. Always posted so a
        // colleague sees the current, specific need (the chat may have moved on).
        if ($handoff === '') {
            $handoff = 'Escalated to a human — please review this conversation and take over.';
        }
        wa_ai_post_note($conn, $cid, $handoff);
        $r = mysqli_query($conn,
            "SELECT body FROM wa_messages WHERE contact_id = $cid AND direction = 'outbound' AND type <> 'note' ORDER BY id DESC LIMIT 1");
        $prev = $r ? mysqli_fetch_assoc($r) : null;
        $prevBody = $prev ? trim((string)$prev['body']) : '';
        if ($prevBody !== '' && strcasecmp($prevBody, $reply) === 0) {
            $reply = wa_ai_referral_nudge(wa_ai_outbound_count($conn, $cid));
            if (strcasecmp($prevBody, $reply) === 0) { $reply = wa_ai_referral_nudge(wa_ai_outbound_count($conn, $cid) + 1); }
        }
    }

    $send = wa_send_text($conn, $conv['wa_id'], $reply);
    if (!empty($send['ok'])) {
        $convId = (int)$conv['id'];
        if ($escalate) {
            // Give the escalation a real owner so it doesn't sit in a shared "escalated"
            // flag that nobody closes: if the chat isn't already assigned and we know the
            // programme, assign it to that programme's owner.
            $ownerSql = '';
            if (empty($conv['assigned_user_id'])
                && in_array($conv['ref_type'] ?? '', ['course', 'event', 'program'], true)
                && $conv['ref_id'] !== null) {
                $ownerId = wa_first_owner($conn, ($conv['ref_type'] === 'event' ? 'event' : 'course'), (int)$conv['ref_id']);
                if ($ownerId) { $ownerSql = ", assigned_user_id = " . (int)$ownerId; }
            }
            mysqli_query($conn, "UPDATE wa_conversations SET escalated = 1$ownerSql, last_message_at = NOW() WHERE id = $convId");
        } else {
            // Answered — leave the escalation flag as it was (a human item may still be pending).
            mysqli_query($conn, "UPDATE wa_conversations SET last_message_at = NOW() WHERE id = $convId");
        }
    } else {
        // The reply couldn't be delivered — never leave it silently dropped: flag the
        // chat as escalated and note it so a human picks the customer up.
        error_log('[wa-ai] send failed: ' . ($send['error'] ?? 'unknown'));
        $convId = (int)$conv['id'];
        mysqli_query($conn, "UPDATE wa_conversations SET escalated = 1, last_message_at = NOW() WHERE id = $convId");
        wa_ai_post_note($conn, (int)$conv['contact_id'],
            'Auto-reply could not be delivered (' . ($send['error'] ?? 'send failed') . '). Please follow up with this customer.');
    }
    // Phase 1.2 — ask WhatsApp for permission to call, when the customer has plainly
    // said they want to join. Placed HERE, after a successful send, because this is
    // the single point both reply paths pass through: the webhook's immediate answer
    // and wa_run_due_replies()'s batched one both arrive via wa_maybe_ai_answer().
    // Putting it in the webhook would silently skip every batched conversation.
    //
    // The model's flag is only half the decision — wa_call_offer_maybe_request()
    // re-checks the customer's own words, the topic, the number and the whole of the
    // Phase 1.1 eligibility before anything is sent.
    // Run the check on EVERY delivered reply, not only when the model raised the
    // flag. wa_call_offer_maybe_request() returns immediately with skip
    // 'ai_flag_false' when it did, and that single log line is what distinguishes
    // "the model never asked" from "the model asked and a gate refused" — without
    // it, both look identical from the outside: no request, no log, no clue.
    $callOffer = ['sent' => false, 'skip' => 'not_attempted'];
    if (!empty($send['ok']) && function_exists('wa_call_offer_maybe_request')) {
        try {
            $callOffer = wa_call_offer_maybe_request($conn, $conv, $inboundText, $wantsCall);
        } catch (Throwable $e) {
            // Never let this break a working conversation.
            error_log('[wa-call-offer] ' . $e->getMessage());
            $callOffer = ['sent' => false, 'skip' => 'exception'];
        }
        error_log('[wa-call-offer] ' . json_encode($callOffer));
    }

    return ['ok' => !empty($send['ok']), 'escalated' => $escalate, 'reply' => $reply,
            'send' => $send, 'call_offer' => $callOffer];
}

// A human is treated as "actively on the chat" for this many seconds after they
// last opened it, and this many seconds after they last replied.
if (!defined('WA_OPEN_HOLD_SECS'))  { define('WA_OPEN_HOLD_SECS', 90); }        // ~open right now
if (!defined('WA_HUMAN_HOLD_SECS')) { define('WA_HUMAN_HOLD_SECS', 15 * 60); }  // recently replied
if (!defined('WA_CRON_TOKEN'))      { define('WA_CRON_TOKEN', '');           }  // set on server; falls back to WA_VERIFY_TOKEN

// =====================================================================
// Batched-reply window (issues #4/#5/#10/#12): instead of answering each inbound
// message the instant it arrives, wait for a short quiet period so rapid successive
// messages are gathered into ONE complete reply — and the webhook returns fast
// instead of blocking on the AI call. Controlled by the 'reply_window_secs' setting:
// 0 (default) = answer immediately, as before; >0 = batch with that window (seconds).
// The wa_cron.php runner sends the due replies, so run the cron at least every minute.
// =====================================================================

/** Idempotently add the ai_reply_due_at column used to schedule batched replies. */
function wa_reply_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_conversations`
        ADD COLUMN IF NOT EXISTS `ai_reply_due_at` DATETIME NULL DEFAULT NULL");
}

/** Schedule (or push forward) the AI reply for this contact to NOW + $secs. Resetting
 *  on every inbound is what groups a burst: we only reply once they've gone quiet for
 *  $secs. No-op for a human-owned chat. */
function wa_schedule_ai_reply($conn, $contactId, $secs) {
    wa_reply_schema_ensure($conn);
    $contactId = (int)$contactId;
    $secs = max(1, (int)$secs);
    wa_ensure_conversation($conn, $contactId);
    mysqli_query($conn,
        "UPDATE wa_conversations
            SET ai_reply_due_at = DATE_ADD(NOW(), INTERVAL $secs SECOND)
          WHERE contact_id = $contactId AND handler <> 'human'");
}

/** Cron entry point: send every batched reply whose quiet window has elapsed. Claims
 *  each conversation atomically (sets ai_reply_due_at = NULL only if we win the row)
 *  so overlapping cron runs never double-answer. Returns a small status summary. */
function wa_run_due_replies($conn, $limit = 20) {
    wa_reply_schema_ensure($conn);
    $limit = (int)$limit;
    $res = mysqli_query($conn,
        "SELECT cv.id AS conv_id, cv.contact_id, c.wa_id
           FROM wa_conversations cv
           JOIN wa_contacts c ON c.id = cv.contact_id
          WHERE cv.ai_reply_due_at IS NOT NULL AND cv.ai_reply_due_at <= NOW()
            AND cv.handler <> 'human'
          ORDER BY cv.ai_reply_due_at ASC
          LIMIT $limit");
    if (!$res) { return ['ok' => true, 'processed' => 0]; }
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
    $processed = 0;
    foreach ($rows as $r) {
        $convId = (int)$r['conv_id'];
        // Atomic claim: only the worker that clears the due flag proceeds.
        mysqli_query($conn,
            "UPDATE wa_conversations SET ai_reply_due_at = NULL
              WHERE id = $convId AND ai_reply_due_at IS NOT NULL AND ai_reply_due_at <= NOW()");
        if (mysqli_affected_rows($conn) < 1) { continue; }   // another run took it
        // The AI answerer reads the FULL recent transcript, so passing the latest
        // inbound line is enough — every message in the burst is covered.
        $cid = (int)$r['contact_id'];
        $lr = mysqli_query($conn,
            "SELECT body FROM wa_messages WHERE contact_id = $cid AND direction = 'inbound' AND body <> ''
              ORDER BY id DESC LIMIT 1");
        $txt = ($lr && ($row = mysqli_fetch_assoc($lr))) ? (string)$row['body'] : '';
        wa_maybe_ai_answer($conn, (string)$r['wa_id'], $txt);
        $processed++;
    }
    return ['ok' => true, 'processed' => $processed];
}

/** Idempotently add the follow-up bookkeeping column. */
function wa_followup_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_conversations`
        ADD COLUMN IF NOT EXISTS `followup_sent_at` DATETIME NULL DEFAULT NULL");
}

/**
 * Issue #14: ONE gentle follow-up when an answered conversation goes quiet, then stop.
 * Fires ~$afterHours after the customer's LAST inbound but still INSIDE WhatsApp's 24h
 * free-form window (so a plain message is allowed — after 24h only templates work, so
 * we skip). Only for open, non-escalated, AI-handled, opted-in chats that we already
 * replied to and haven't nudged yet. Gated by the 'followup_enabled' setting (off by
 * default) so it never starts messaging customers until you switch it on.
 */
/**
 * Surface onsite leads the AI cannot close.
 *
 * Only a human can pin down WHICH country/session an in-person client wants and
 * register them — the AI can ask, but it cannot finish the job. Meanwhile the
 * inbox only counts a chat as unread when `escalated = 1 OR handler = 'human'`,
 * so an AI-handled onsite chat carries no unread badge and sits invisible among
 * thousands of others. That is how in-person leads go unfollowed even once they
 * have an owner.
 *
 * Escalate onsite chats that have gone quiet, so they appear as unread for their
 * rep, and leave a staff-only note saying what is needed. Never touches a chat a
 * human already owns, or one already escalated, so it cannot nag.
 *
 * Off by default: set onsite_escalate_enabled=1 in WhatsApp settings.
 */
function wa_run_onsite_escalation($conn, $afterMins = 60, $limit = 50) {
    if (wa_setting_get($conn, 'onsite_escalate_enabled', '0') !== '1') {
        return ['ok' => true, 'skipped' => 'disabled'];
    }
    wa_conv_mode_schema_ensure($conn);
    $after = max(5, (int)$afterMins);
    $limit = max(1, (int)$limit);

    $res = mysqli_query($conn, "
        SELECT cv.id AS conv_id, cv.contact_id, cv.assigned_user_id, cv.last_route_reason
          FROM wa_conversations cv
          JOIN wa_contacts c ON c.id = cv.contact_id
         WHERE cv.delivery_mode = 'onsite'
           AND cv.escalated = 0
           AND cv.handler <> 'human'
           AND cv.status = 'open'
           AND c.opted_out = 0
           AND c.last_inbound_at IS NOT NULL
           AND c.last_inbound_at <= (NOW() - INTERVAL $after MINUTE)
         ORDER BY c.last_inbound_at ASC
         LIMIT $limit");
    if (!$res) { return ['ok' => false, 'error' => mysqli_error($conn)]; }

    $done = 0;
    while ($r = mysqli_fetch_assoc($res)) {
        $convId = (int)$r['conv_id'];
        mysqli_query($conn, "UPDATE wa_conversations SET escalated = 1 WHERE id = $convId");
        $awaiting = ($r['last_route_reason'] ?? '') === 'await_onsite_location';
        wa_ai_post_note($conn, (int)$r['contact_id'],
            'In-person lead needing a human. ' . ($awaiting
                ? 'They have asked for onsite training but have not said which country yet — confirm their location, then register them on that session.'
                : 'They want in-person training — pick up the conversation and take them through to registration.'));
        $done++;
    }
    return ['ok' => true, 'escalated' => $done];
}

function wa_run_followups($conn, $afterHours = 23, $limit = 20) {
    if (wa_setting_get($conn, 'followup_enabled', '0') !== '1') { return ['ok' => true, 'skipped' => 'disabled']; }
    wa_followup_schema_ensure($conn);
    $afterHours = max(1, min(23, (int)$afterHours));   // keep strictly inside the 24h window
    $limit = (int)$limit;
    $res = mysqli_query($conn,
        "SELECT c.id AS contact_id, c.wa_id, c.profile_name, cv.id AS conv_id, cv.ref_type, cv.ref_id
           FROM wa_contacts c
           JOIN wa_conversations cv ON cv.contact_id = c.id
          WHERE cv.handler <> 'human'
            AND cv.escalated = 0
            AND cv.status = 'open'
            -- Exactly ONE follow-up per silence. The stamp is cleared the moment the
            -- customer replies (wa_touch_last_inbound), so a fresh one only becomes
            -- possible after they have answered — never a second nudge on silence.
            -- (wa_conversations is UNIQUE(contact_id), so this covers the contact.)
            AND cv.followup_sent_at IS NULL
            AND c.opted_out = 0
            AND c.last_inbound_at IS NOT NULL
            AND c.last_inbound_at <= (NOW() - INTERVAL $afterHours HOUR)
            AND c.last_inbound_at >  (NOW() - INTERVAL 24 HOUR)
            AND EXISTS (SELECT 1 FROM wa_messages m
                         WHERE m.contact_id = c.id AND m.direction = 'outbound' AND m.type <> 'note'
                           AND m.wa_timestamp >= c.last_inbound_at)
          ORDER BY c.last_inbound_at ASC
          LIMIT $limit");
    if (!$res) { return ['ok' => true, 'sent' => 0]; }
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
    $sent = 0;
    foreach ($rows as $r) {
        $convId = (int)$r['conv_id'];
        // Claim before sending so overlapping cron runs can't double-nudge.
        mysqli_query($conn, "UPDATE wa_conversations SET followup_sent_at = NOW() WHERE id = $convId AND followup_sent_at IS NULL");
        if (mysqli_affected_rows($conn) < 1) { continue; }
        $name  = trim((string)$r['profile_name']);
        $first = $name !== '' ? ' ' . preg_split('/\s+/', $name)[0] : '';
        $prog  = ($r['ref_id'] !== null) ? (string)wa_ref_name($conn, $r['ref_type'], (int)$r['ref_id']) : '';
        $msg   = $prog !== ''
            ? "Hi{$first}, just checking in — are you still interested in {$prog}? I'm happy to answer any questions or help you get started whenever you're ready."
            : "Hi{$first}, just checking in — are you still interested in our programmes? I'm happy to answer any questions or help you get started whenever you're ready.";
        wa_send_text($conn, (string)$r['wa_id'], $msg);
        $sent++;
    }
    return ['ok' => true, 'sent' => $sent];
}

/** Idempotently add + index an email column on wa_contacts (used to match payments). */
function wa_contact_email_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_contacts` ADD COLUMN IF NOT EXISTS `email` VARCHAR(190) NULL DEFAULT NULL");
    @mysqli_query($conn, "ALTER TABLE `wa_contacts` ADD INDEX IF NOT EXISTS `idx_wa_contacts_email` (`email`)");
}

/** Persist the customer's email on their contact (captured during registration). */
function wa_contact_set_email($conn, $contactId, $email) {
    wa_contact_email_ensure($conn);
    $contactId = (int)$contactId;
    $email = trim((string)$email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return; }
    mysqli_query($conn, "UPDATE wa_contacts SET email = " . wa_sql($conn, $email) . " WHERE id = $contactId");
}

/** Idempotently ensure the payment-confirmation dedup table + the contact email column. */
function wa_payment_confirm_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    wa_contact_email_ensure($conn);
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wa_payment_confirms` (
        `payment_ref` VARCHAR(191) NOT NULL,
        `contact_id`  INT UNSIGNED NOT NULL,
        `sent_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`payment_ref`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Issue #15: confirm a completed payment back to the WhatsApp customer. Matches a
 * dpo_payment (status=2) to a contact by the email they gave during registration and
 * sends ONE acknowledgement per payment (deduped in wa_payment_confirms). Gated by the
 * 'payment_confirm_enabled' setting (off by default). Payments usually land AFTER
 * WhatsApp's 24h free-form window, so: within window -> plain message; outside window ->
 * an approved template if 'payment_confirm_template' is set, otherwise a staff note so
 * the confirmation is not missed. Deliberately does NOT over-claim "registration
 * complete" (a payment may be a deposit) — it acknowledges receipt and that processing
 * is under way.
 */
function wa_run_payment_confirms($conn, $limit = 20) {
    if (wa_setting_get($conn, 'payment_confirm_enabled', '0') !== '1') { return ['ok' => true, 'skipped' => 'disabled']; }
    wa_payment_confirm_schema_ensure($conn);
    $limit = (int)$limit;
    $res = mysqli_query($conn,
        "SELECT c.id AS contact_id, c.wa_id, c.profile_name, c.last_inbound_at,
                dp.token AS ref, dp.TransactionAmount AS amount
           FROM wa_contacts c
           JOIN dpo_payment dp ON dp.email = c.email AND dp.status = 2
          WHERE c.email IS NOT NULL AND c.email <> '' AND c.opted_out = 0 AND dp.token <> ''
            AND NOT EXISTS (SELECT 1 FROM wa_payment_confirms w WHERE w.payment_ref = dp.token)
          ORDER BY c.id ASC
          LIMIT $limit");
    if (!$res) { return ['ok' => true, 'sent' => 0]; }
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
    $sent = 0;
    $tmpl = (string)wa_setting_get($conn, 'payment_confirm_template', '');
    foreach ($rows as $r) {
        $ref = (string)$r['ref'];
        $cid = (int)$r['contact_id'];
        // Claim via the unique PK so we never confirm the same payment twice.
        mysqli_query($conn, "INSERT IGNORE INTO wa_payment_confirms (payment_ref, contact_id) VALUES ("
            . wa_sql($conn, $ref) . ", $cid)");
        if (mysqli_affected_rows($conn) < 1) { continue; }
        $name  = trim((string)$r['profile_name']);
        $first = $name !== '' ? ' ' . preg_split('/\s+/', $name)[0] : '';
        $amt   = trim((string)$r['amount']);
        if (wa_within_window($r['last_inbound_at'] ?? null)) {
            $msg = "Hi{$first}, we've received your payment" . ($amt !== '' ? " of {$amt}" : '')
                 . " — thank you! We're processing your registration and your access details will follow shortly.";
            wa_send_text($conn, (string)$r['wa_id'], $msg);
            $sent++;
        } elseif ($tmpl !== '') {
            wa_send_template($conn, (string)$r['wa_id'], $tmpl, 'en', []);
            $sent++;
        } else {
            wa_ai_post_note($conn, $cid,
                "Payment received (ref {$ref}) but the chat is outside WhatsApp's 24h window — please send this customer their confirmation / access details.");
        }
    }
    return ['ok' => true, 'sent' => $sent];
}

/**
 * Safety net for issue #11 ("some messages get no reply at all"). Finds conversations
 * whose newest message is an inbound one that has sat UNANSWERED past $staleSecs (well
 * beyond the batch window) — i.e. a silent failure (cron missed it, provider was down,
 * a send failed) — and forces a resolution: retry the AI answer once, and if that still
 * produces nothing, escalate + post a staff note so the waiting customer surfaces in the
 * CRM instead of being left on read. Idempotent: skips human-owned, opted-out, and
 * already-escalated chats, so it never spams.
 */
function wa_run_unanswered_sweep($conn, $staleSecs = 600, $limit = 30) {
    $staleSecs = max(60, (int)$staleSecs);
    $limit = (int)$limit;
    // EVERY unanswered chat — including human-owned and already-escalated ones — because
    // a client must NEVER be left on read under any circumstance.
    $res = mysqli_query($conn,
        "SELECT c.id AS contact_id, c.wa_id, c.last_inbound_at, cv.id AS conv_id, cv.handler, cv.escalated
           FROM wa_contacts c
           JOIN wa_conversations cv ON cv.contact_id = c.id
          WHERE c.opted_out = 0
            AND c.last_inbound_at IS NOT NULL
            AND c.last_inbound_at <= (NOW() - INTERVAL $staleSecs SECOND)
            -- Only chats still INSIDE the 24h window: outside it WhatsApp blocks any send,
            -- so there's nothing to do and we'd just re-process the same chats every run.
            -- (With the 2-min threshold, chats are answered well before they hit 24h.)
            AND c.last_inbound_at > (NOW() - INTERVAL 24 HOUR)
            AND NOT EXISTS (
                SELECT 1 FROM wa_messages m
                 WHERE m.contact_id = c.id AND m.direction = 'outbound' AND m.type <> 'note'
                   -- COALESCE so replies saved before wa_timestamp was stamped (NULL) still
                   -- count as answered via their created_at, instead of looping forever.
                   AND COALESCE(m.wa_timestamp, m.created_at) >= c.last_inbound_at)
          ORDER BY c.last_inbound_at ASC
          LIMIT $limit");
    if (!$res) { return ['ok' => true, 'swept' => 0]; }
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
    $swept = 0;
    foreach ($rows as $r) {
        $convId   = (int)$r['conv_id'];
        $cid      = (int)$r['contact_id'];
        $inWindow = wa_within_window($r['last_inbound_at'] ?? null);

        // A human OWNS this chat but hasn't replied. Don't talk over them with a full AI
        // answer — but never leave the client silent either: send one brief holding line
        // (only if WhatsApp's window still allows it) and flag the chat for the human.
        if ($r['handler'] === 'human') {
            if ($inWindow) {
                wa_send_text($conn, (string)$r['wa_id'],
                    "Thanks for your patience — I'm getting this sorted for you and will come right back to you shortly.");
            }
            if ((int)$r['escalated'] !== 1) {
                mysqli_query($conn, "UPDATE wa_conversations SET escalated = 1, last_message_at = NOW() WHERE id = $convId");
            }
            wa_ai_post_note($conn, $cid, 'A client is still waiting and this chat is on Human — please reply.');
            $swept++;
            continue;
        }

        // AI-owned: drop any stale scheduled-reply flag and force the answer now.
        mysqli_query($conn, "UPDATE wa_conversations SET ai_reply_due_at = NULL WHERE id = $convId");
        $lr = mysqli_query($conn,
            "SELECT body FROM wa_messages WHERE contact_id = $cid AND direction = 'inbound' AND body <> ''
              ORDER BY id DESC LIMIT 1");
        $txt = ($lr && ($row = mysqli_fetch_assoc($lr))) ? (string)$row['body'] : '';
        $r2 = wa_maybe_ai_answer($conn, (string)$r['wa_id'], $txt);
        // If the retry neither replied nor escalated (e.g. skipped), force-escalate so a
        // human picks up the waiting customer — we never leave anyone unanswered.
        if (empty($r2['ok']) && empty($r2['escalated'])) {
            mysqli_query($conn, "UPDATE wa_conversations SET escalated = 1, last_message_at = NOW() WHERE id = $convId");
            wa_ai_post_note($conn, $cid,
                'This customer has been waiting unanswered for a while and the AI could not reply — please follow up.');
        }
        $swept++;
    }
    return ['ok' => true, 'swept' => $swept];
}

/**
 * Decide whether to auto-answer an inbound message, and do it.
 * Returns a status array (also useful for logging). The AI keeps helping unless
 * a human is actively on the chat, replied recently, escalated, or fully took over.
 */
function wa_maybe_ai_answer($conn, $waId, $inboundText) {
    $contact = wa_find_contact_by_waid($conn, $waId);
    if (!$contact) { return ['ok' => false, 'skip' => 'no_contact']; }
    $conv = wa_get_conversation($conn, (int)$contact['id']);
    if (!$conv) {
        // No topic classified yet — still engage. Create a bare conversation so the
        // AI greets and asks which programme, instead of going silent.
        wa_ensure_conversation($conn, (int)$contact['id']);
        $conv = wa_get_conversation($conn, (int)$contact['id']);
        if (!$conv) { return ['ok' => false, 'skip' => 'no_conversation']; }
    }

    $conv['wa_id']    = $waId;
    $conv['ref_name'] = ($conv['ref_id'] !== null)
        ? wa_ref_name($conn, $conv['ref_type'], (int)$conv['ref_id']) : null;

    // A guided registration in progress owns the chat — never let a (possibly batched)
    // AI reply talk over the form.
    if (function_exists('wa_enroll_active') && wa_enroll_active($conn, (int)$contact['id'])) {
        return ['ok' => false, 'skip' => 'enroll_active'];
    }

    // The ONLY case we stay silent is an explicit Human takeover — an agent clicked
    // "Human" to own the chat, so the AI must not talk over them.
    if ($conv['handler'] === 'human') { return ['ok' => false, 'skip' => 'handler_human']; }

    // Serialise answering PER CONTACT. Two messages arriving in the same second used to be
    // answered concurrently — each read the same "previous outbound", so the dedup never
    // saw the other and the customer got the IDENTICAL reply twice. A named lock makes the
    // second wait for the first; then the burst-coalesce check below skips the repeat.
    $cid = (int)$contact['id'];
    $lockName = 'wa_ai_c' . $cid;
    $gotLock = false;
    $lr = mysqli_query($conn, "SELECT GET_LOCK('" . $lockName . "', 8) AS g");
    if ($lr) { $gotLock = ((int)(mysqli_fetch_assoc($lr)['g'] ?? 0) === 1); }
    try {
        // Coalesce a burst: if we've already replied since this contact's LATEST inbound
        // (an outbound with a higher id exists), the burst is handled — don't send another
        // (often identical) reply. Id-based so it's immune to timezone skew.
        $already = (int) wa_scalar($conn, "SELECT COUNT(*) FROM wa_messages o
            WHERE o.contact_id = $cid AND o.direction = 'outbound' AND o.type <> 'note'
              AND o.id > (SELECT COALESCE(MAX(i.id), 0) FROM wa_messages i
                           WHERE i.contact_id = $cid AND i.direction = 'inbound')");
        if ($already > 0) { return ['ok' => true, 'skip' => 'already_answered']; }

        // From here the customer ALWAYS gets a response — we never leave them on read.
        // If the AI provider isn't configured/available, don't go silent: acknowledge
        // and escalate to a human instead.
        if (!wa_provider_ready(wa_active_provider($conn))) {
            return wa_ai_soft_handoff($conn, $conv, 'no_provider');
        }
        // Outside WhatsApp's 24-hour window the platform blocks a free-form reply; still
        // acknowledge + escalate so a human follows up (the send no-ops if truly outside).
        if (!wa_within_window($contact['last_inbound_at'] ?? null)) {
            return wa_ai_soft_handoff($conn, $conv, 'outside_window');
        }
        return wa_ai_answer($conn, $conv, $inboundText);
    } finally {
        if ($gotLock) { mysqli_query($conn, "SELECT RELEASE_LOCK('" . $lockName . "')"); }
    }
}

// =====================================================================
// HTTP helper
// =====================================================================

function wa_http_post($url, $headers, $payload, $timeout = 25) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => (int)$timeout,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return ['status' => 0, 'body' => ['error' => ['message' => "cURL: {$err}"]]];
    }
    $body = json_decode((string)$raw, true);
    return ['status' => $status, 'body' => is_array($body) ? $body : ['raw' => $raw]];
}

// Guided WhatsApp enrollment (capture-in-chat -> enroll directly).
require_once __DIR__ . '/wa_enroll.php';

// Business-number channels (which line we send from, and its 24h window).
require_once __DIR__ . '/wa_channels.php';

// Phase 1.2 call handoff. Loaded last: it depends on wa_voice.php and the Phase 1.1
// helpers, and is required here so BOTH reply paths have it without either caller
// needing to know it exists.
require_once __DIR__ . '/wa_voice.php';
require_once __DIR__ . '/wa_call_offer.php';
