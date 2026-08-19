<?php
/**
 * Voice API — pure request handling, validation and response shaping (Phase 2.1A).
 *
 * Everything here is a pure function of its arguments: no database, no network, no
 * superglobals, no output. wa_voice_api.php does the I/O; every decision about
 * WHETHER to act, and about what a customer's data is allowed to look like on the
 * way out, lives in this file so it can be tested offline.
 *
 * That split matters most for the redaction rules. "Do not return the enrolment
 * email" is not a property of a query — the query has to read the row to know the
 * enrolment state — it is a property of the shaping step. Putting the shaping in a
 * pure function makes the rule testable by assertion rather than by inspection.
 *
 * Requires wa_voice.php (wa_voice_e164) and, through it, wa_functions.php
 * (wa_import_normalize_phone).
 */

// ---- Limits ---------------------------------------------------------------
// Every one of these is a cap on something an outside caller controls.

if (!defined('WA_VOICE_MAX_BODY'))      { define('WA_VOICE_MAX_BODY',      16 * 1024); }
if (!defined('WA_VOICE_SKEW_SECS'))     { define('WA_VOICE_SKEW_SECS',     300);       }
if (!defined('WA_VOICE_MAX_TURNS'))     { define('WA_VOICE_MAX_TURNS',     6);         }
if (!defined('WA_VOICE_TURN_CHARS'))    { define('WA_VOICE_TURN_CHARS',    350);       }
if (!defined('WA_VOICE_MAX_RESULTS'))   { define('WA_VOICE_MAX_RESULTS',   5);         }
if (!defined('WA_VOICE_QUERY_CHARS'))   { define('WA_VOICE_QUERY_CHARS',   200);       }
if (!defined('WA_VOICE_CALL_ID_CHARS')) { define('WA_VOICE_CALL_ID_CHARS', 128);       }
if (!defined('WA_VOICE_KB_CHARS'))      { define('WA_VOICE_KB_CHARS',      6000);      }

// Rate limits. Per key is generous — the voice server is trusted-ish. Per phone is
// tight, because repeated lookups of DIFFERENT numbers is what bulk extraction
// looks like, and repeated lookups of the SAME number has no legitimate use beyond
// a retry or two within one call.
if (!defined('WA_VOICE_RATE_WINDOW'))   { define('WA_VOICE_RATE_WINDOW',   60); }
if (!defined('WA_VOICE_RATE_KEY_MAX'))  { define('WA_VOICE_RATE_KEY_MAX',  60); }
if (!defined('WA_VOICE_RATE_PHONE_MAX')){ define('WA_VOICE_RATE_PHONE_MAX', 10); }

/** How long a session is allowed to sit untouched before it stops counting as an
 *  enrolment in progress. Mirrors WA_ENROLL_STALE_HOURS in wa_enroll.php, which we
 *  deliberately do not call because reading it there also CANCELS stale rows — a
 *  write, and this phase is read-only. */
if (!defined('WA_VOICE_ENROLL_STALE_HOURS')) { define('WA_VOICE_ENROLL_STALE_HOURS', 12); }

// =====================================================================
// Small text helpers
// =====================================================================

/** mb_substr when the extension is present, substr otherwise. The module depends
 *  on mbstring in production; this only keeps the offline harness runnable. */
function wa_voice_sub($s, $start, $len) {
    return function_exists('mb_substr') ? mb_substr($s, $start, $len, 'UTF-8') : substr($s, $start, $len);
}

/** Length in characters, with the same fallback. */
function wa_voice_len($s) {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

/**
 * Cut a string to $max characters. Returns [text, wasTruncated].
 *
 * An ellipsis is appended when it was cut, because the voice assistant reads this
 * aloud and a sentence that simply stops mid-word sounds like a fault.
 *
 * The ellipsis is charged against the budget using the SAME length function used
 * to measure the result, so the guarantee `wa_voice_len(result) <= $max` holds
 * whether that function is counting characters (mbstring present) or bytes (the
 * offline harness). Assuming it costs one unit is what makes a cap overshoot by
 * two bytes on the byte-counting path — quietly, and only for multibyte text.
 */
function wa_voice_cut($s, $max) {
    $s = (string)$s;
    $max = (int)$max;
    if ($max < 1) { return ['', $s !== '']; }
    if (wa_voice_len($s) <= $max) { return [$s, false]; }
    $ell  = '…';
    $room = $max - wa_voice_len($ell);
    if ($room < 1) { return [wa_voice_sub($s, 0, $max), true]; }
    return [rtrim(wa_voice_sub($s, 0, $room)) . $ell, true];
}

/** Collapse runs of whitespace, drop control characters. Applied to anything that
 *  came from a customer or from free-text knowledge before it goes into JSON. */
function wa_voice_flatten($s, $max = 0) {
    $s = wa_voice_strip_control((string)$s);
    // A /u pattern returns NULL on invalid UTF-8 rather than throwing. Falling
    // back to the non-unicode pattern keeps a single bad byte from turning a
    // customer's name into an empty string — or, on PHP 8.1+, into a deprecation
    // notice from trim(null).
    $flat = preg_replace('/\s+/u', ' ', $s);
    if ($flat === null) { $flat = preg_replace('/\s+/', ' ', $s); }
    $flat = trim((string)$flat);
    if ($max > 0) { list($flat, ) = wa_voice_cut($flat, $max); }
    return $flat;
}

/** Remove control characters, tolerating invalid UTF-8. */
function wa_voice_strip_control($s) {
    $pattern = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';
    $out = preg_replace($pattern . 'u', '', (string)$s);
    if ($out === null) { $out = preg_replace($pattern, '', (string)$s); }
    return (string)$out;
}

// =====================================================================
// Input validation
// =====================================================================

/** Read a request header across SAPIs. $server must be passed for testability. */
function wa_voice_header($name, array $server) {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', (string)$name));
    return isset($server[$key]) ? (string)$server[$key] : '';
}

/** Key ids travel in clear and are used as a map lookup and a rate-limit bucket. */
function wa_voice_valid_key_id($v) {
    return is_string($v) && preg_match('/^[A-Za-z0-9._-]{1,32}$/', $v) === 1;
}

/** A nonce only has to be unguessable and bounded. 16 characters of hex is 64 bits,
 *  which is the floor worth accepting; 128 is a generous ceiling. */
function wa_voice_valid_nonce($v) {
    return is_string($v) && preg_match('/^[A-Za-z0-9._-]{16,128}$/', $v) === 1;
}

/** Unix seconds, digits only. Rejecting a leading '+' or whitespace here means the
 *  value that reaches the skew check is the same one that was signed. */
function wa_voice_valid_timestamp($v) {
    return is_string($v) && preg_match('/^[0-9]{1,12}$/', $v) === 1;
}

/** Lower-case hex, exactly the width of a SHA-256 digest. */
function wa_voice_valid_signature($v) {
    return is_string($v) && preg_match('/^[a-f0-9]{64}$/i', $v) === 1;
}

/**
 * The provider's call id, which we accept only in order to correlate a log line
 * with a call. It is attacker-controlled text heading for the error log, so it is
 * whitelisted and capped rather than escaped: a log file that can be made to
 * contain newlines can be made to contain fake log entries.
 *
 * @return string the id, or '' when absent or unacceptable
 */
function wa_voice_clean_call_id($v) {
    if (!is_string($v) || $v === '') { return ''; }
    if (wa_voice_len($v) > WA_VOICE_CALL_ID_CHARS) { return ''; }
    return preg_match('/^[A-Za-z0-9._:-]+$/', $v) === 1 ? $v : '';
}

/**
 * A free-text search query. Control characters are rejected outright rather than
 * stripped — a query containing one did not come from a person speaking.
 *
 * @return string|null null when unusable
 */
function wa_voice_clean_query($v) {
    if (!is_string($v)) { return null; }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $v)) { return null; }
    $v = trim(preg_replace('/\s+/u', ' ', $v));
    if ($v === '') { return null; }
    if (wa_voice_len($v) > WA_VOICE_QUERY_CHARS) { return null; }
    return $v;
}

/** The three reference types the knowledge base actually stores. */
function wa_voice_valid_ref_type($v) {
    return in_array($v, ['course', 'event', 'program'], true);
}

/** A positive row id. Accepts an int or an all-digit string, nothing else — so a
 *  float, '12 OR 1=1' and '0x0c' are all rejected before any query is built. */
function wa_voice_clean_id($v) {
    if (is_int($v)) { return $v > 0 ? $v : 0; }
    if (is_string($v) && preg_match('/^[0-9]{1,10}$/', $v)) { return (int)$v > 0 ? (int)$v : 0; }
    return 0;
}

// =====================================================================
// Authentication
// =====================================================================

/**
 * The exact bytes that get signed.
 *
 * The path is passed in from configuration, never derived from the Host header,
 * REQUEST_URI or the query string. Those are attacker-controlled: if the path in
 * the signing string were read from the request, a signature minted for one
 * endpoint could be replayed against another, and the path would stop
 * authenticating anything.
 */
function wa_voice_signing_string($timestamp, $nonce, $method, $path, $rawBody) {
    return (string)$timestamp . "\n"
         . (string)$nonce . "\n"
         . strtoupper((string)$method) . "\n"
         . (string)$path . "\n"
         . hash('sha256', (string)$rawBody);
}

/** Hex HMAC-SHA256 of the signing string. */
function wa_voice_sign($secret, $signingString) {
    return hash_hmac('sha256', (string)$signingString, (string)$secret);
}

/**
 * Verify a request. Pure — the caller passes $server in, so every branch is
 * reachable from a test.
 *
 * The client is told nothing but "unauthorized". `reason` exists for the server's
 * own log: distinguishing "clock skew" from "wrong key" is the difference between
 * a five-minute fix and an afternoon, and it costs nothing as long as it never
 * reaches the response.
 *
 * Replay protection is NOT here — a nonce has to be checked against storage, so it
 * lives in wa_voice_context.php and runs after this returns ok.
 *
 * @return array {ok, key_id, nonce, reason}
 */
function wa_voice_authenticate(array $keys, array $server, $rawBody, $now, $signingPath) {
    $fail = function ($reason) { return ['ok' => false, 'key_id' => '', 'nonce' => '', 'reason' => $reason]; };

    // No usable credential configured: refuse everything. This is checked first and
    // separately so an empty key map can never be satisfied by an empty header.
    if (!$keys) { return $fail('not_configured'); }

    $keyId = wa_voice_header('X-Vantage-Voice-Key-Id', $server);
    $ts    = wa_voice_header('X-Vantage-Voice-Timestamp', $server);
    $nonce = wa_voice_header('X-Vantage-Voice-Nonce', $server);
    $sig   = wa_voice_header('X-Vantage-Voice-Signature', $server);

    if ($keyId === '' || $ts === '' || $nonce === '' || $sig === '') { return $fail('missing_header'); }
    if (!wa_voice_valid_key_id($keyId))    { return $fail('bad_key_id'); }
    if (!wa_voice_valid_timestamp($ts))    { return $fail('bad_timestamp'); }
    if (!wa_voice_valid_nonce($nonce))     { return $fail('bad_nonce'); }
    if (!wa_voice_valid_signature($sig))   { return $fail('bad_signature_format'); }

    // Skew is rejected in BOTH directions. A future timestamp is not harmless: it
    // would let a signature be minted now and held in reserve.
    $drift = (int)$now - (int)$ts;
    if ($drift > WA_VOICE_SKEW_SECS)  { return $fail('stale_timestamp'); }
    if ($drift < -WA_VOICE_SKEW_SECS) { return $fail('future_timestamp'); }

    if (!isset($keys[$keyId])) { return $fail('unknown_key'); }

    $expected = wa_voice_sign($keys[$keyId],
        wa_voice_signing_string($ts, $nonce, 'POST', $signingPath, $rawBody));

    if (!hash_equals($expected, strtolower($sig))) { return $fail('bad_signature'); }

    return ['ok' => true, 'key_id' => $keyId, 'nonce' => $nonce, 'reason' => ''];
}

/**
 * Mask a number for logging: country code, then stars, then the last two digits.
 * Enough to correlate two log lines about the same caller, not enough to be a
 * phone number. Mirrors wa_call_mask_msisdn().
 */
function wa_voice_mask_phone($e164) {
    $d = preg_replace('/\D/', '', (string)$e164);
    if ($d === '') { return '(none)'; }
    if (strlen($d) <= 5) { return str_repeat('*', strlen($d)); }
    return substr($d, 0, 3) . str_repeat('*', strlen($d) - 5) . substr($d, -2);
}

/**
 * The keyed hash stored in the rate-limit table.
 *
 * Rate limiting has to remember which number was looked up. Storing the number
 * itself would mean this feature quietly built a second, unmanaged record of who
 * telephones the business — with no opt-out, no retention rule and no reason. A
 * keyed hash counts just as well and is not reversible without the pepper.
 */
function wa_voice_phone_bucket($e164, $pepper) {
    return hash_hmac('sha256', (string)$e164, (string)$pepper);
}

// =====================================================================
// Response shaping — the redaction boundary
// =====================================================================

/** A rate decision, so the threshold is testable without a database. */
function wa_voice_rate_allowed($hits, $max) {
    return (int)$hits <= (int)$max;
}

/**
 * Turn the insert id returned by the rate-counter upsert into a hit count.
 *
 * The counter is read back through `LAST_INSERT_ID(hits + 1)` so the statement is
 * atomic and needs no SELECT privilege on the table. On the FIRST hit in a window
 * the row is inserted rather than updated, that expression never runs, and the
 * table has no AUTO_INCREMENT column — so MySQL reports an insert id of 0. That
 * means one hit, not zero, and reading it literally would let the first request in
 * every window through uncounted.
 */
function wa_voice_rate_hits_from_insert_id($insertId) {
    return max(1, (int)$insertId);
}

/** INSERT IGNORE affected 1 row = this nonce is new; 0 = it has been seen. */
function wa_voice_nonce_is_fresh($affectedRows) {
    return (int)$affectedRows === 1;
}

/**
 * Why a set of voice database credentials is unusable, or '' when it is usable.
 *
 * Pure, so every refusal branch is reachable from a test without putting real
 * credentials anywhere. Returns the NAME of the problem, never a value.
 *
 *   incomplete       any of host/name/user/pass missing
 *   placeholder      a sample value left in place
 *   shared_account   the user is the application's own WA_DB_USER
 *   shared_password  the password is the application's own WA_DB_PASS
 *
 * The last two are the point of the whole exercise. The application account can
 * write to every table in the CRM; this endpoint is supposed to be incapable of
 * that. Quietly accepting the powerful credential when the restricted one is
 * absent would undo the least-privilege design at exactly the moment nobody is
 * watching, so it is refused instead.
 */
function wa_voice_db_check(array $db, $appUser = null, $appPass = null) {
    foreach (['host', 'name', 'user', 'pass'] as $k) {
        if (!isset($db[$k]) || trim((string)$db[$k]) === '') { return 'incomplete'; }
    }
    foreach (['user', 'pass', 'name'] as $k) {
        foreach (['YOUR_', 'CHANGE_ME', 'REPLACE_ME'] as $placeholder) {
            if (stripos((string)$db[$k], $placeholder) === 0) { return 'placeholder'; }
        }
    }
    if ($appUser !== null && (string)$db['user'] === (string)$appUser) { return 'shared_account'; }
    if ($appPass !== null && (string)$db['pass'] === (string)$appPass) { return 'shared_password'; }
    return '';
}

/** The standard error envelope. One place, so no handler invents its own wording. */
function wa_voice_error($status, $code) {
    return ['status' => (int)$status, 'body' => ['ok' => false, 'error' => (string)$code]];
}

/** The unmatched-caller result. A successful answer, not an error: the voice
 *  assistant is expected to carry on and simply not personalise. Deliberately
 *  carries nothing else — no catalogue, no hint about whether the number exists
 *  under a different form. */
function wa_voice_unmatched() {
    return ['ok' => true, 'matched' => false];
}

/**
 * Turn raw wa_messages rows into the conversation turns the assistant may see.
 *
 * Rows arrive newest-first (that is the only way to LIMIT to the recent ones) and
 * come back oldest-first, because a transcript read backwards is worse than no
 * transcript.
 *
 * Three exclusions, each deliberate:
 *   - type = 'note'      staff comments. Internal commentary written for
 *                        colleagues, not for the customer and not for a model
 *                        that might read it back down a phone line.
 *   - deleted_at set     a reply a human retracted. It is not part of the
 *                        conversation any more, so it must not steer the call.
 *   - empty body         nothing to say; media gets a short description instead so
 *                        the assistant knows something was sent.
 */
function wa_voice_shape_turns(array $rows, $maxTurns = null, $maxChars = null) {
    $maxTurns = $maxTurns === null ? WA_VOICE_MAX_TURNS : (int)$maxTurns;
    $maxChars = $maxChars === null ? WA_VOICE_TURN_CHARS : (int)$maxChars;

    $turns = [];
    foreach ($rows as $r) {
        $type = (string)($r['type'] ?? 'text');
        if ($type === 'note') { continue; }
        if (!empty($r['deleted_at'])) { continue; }

        $outbound = ((string)($r['direction'] ?? '')) === 'outbound';
        $text = wa_voice_flatten($r['body'] ?? '');
        if ($text === '' && !in_array($type, ['text', 'template'], true)) {
            $text = $outbound ? '[sent a ' . $type . ']' : '[the customer sent a ' . $type . ']';
        }
        if ($text === '') { continue; }

        list($text, ) = wa_voice_cut($text, $maxChars);
        $turns[] = ['role' => $outbound ? 'assistant' : 'customer', 'text' => $text];
        if (count($turns) >= $maxTurns) { break; }
    }
    return array_reverse($turns);
}

/**
 * The enrolment state, as an enum and nothing else.
 *
 * The session row holds the customer's name, email, telephone number, employer and
 * job title. None of that goes out: the assistant only needs to know not to start
 * a registration the customer is already halfway through, and it is talking to the
 * person — it can ask.
 *
 * Staleness is applied here rather than by calling wa_enroll_active(), which
 * cancels expired rows as a side effect of reading them. This phase writes nothing
 * to enrolment state.
 *
 * @return string none | offered | in_progress | awaiting_confirmation
 */
function wa_voice_enrolment_state($row, $now) {
    if (!is_array($row) || empty($row['status'])) { return 'none'; }

    $updated = strtotime((string)($row['updated_at'] ?? '')) ?: 0;
    if ($updated > 0 && ((int)$now - $updated) > WA_VOICE_ENROLL_STALE_HOURS * 3600) {
        return 'none';                       // abandoned; the live code would have cancelled it
    }
    switch ((string)$row['status']) {
        case 'offered':    return 'offered';
        case 'collecting': return 'in_progress';
        case 'confirm':    return 'awaiting_confirmation';
        default:           return 'none';    // done / cancelled
    }
}

/**
 * Build the matched get_caller_context response.
 *
 * This function is the whole allow-list. Every field in the reply is named here
 * explicitly, so nothing can be added to a row upstream — a new column on
 * wa_contacts, a new key in the enrolment JSON — and silently start being sent to
 * a third-party model. Passing a row through unfiltered is how that happens; this
 * never passes a row through.
 */
function wa_voice_shape_caller_context(array $in) {
    $contact = is_array($in['contact'] ?? null) ? $in['contact'] : [];
    $conv    = is_array($in['conversation'] ?? null) ? $in['conversation'] : [];

    $refType = (string)($conv['ref_type'] ?? '');
    if (!in_array($refType, ['course', 'event'], true)) { $refType = 'unknown'; }
    $refId     = (int)($conv['ref_id'] ?? 0);
    $programId = (int)($conv['program_id'] ?? 0);
    $repId     = (int)($conv['assigned_user_id'] ?? 0);

    $mode = (string)($conv['delivery_mode'] ?? 'unknown');
    if (!in_array($mode, ['unknown', 'virtual', 'onsite'], true)) { $mode = 'unknown'; }

    $out = [
        'ok'      => true,
        'matched' => true,
        'contact' => [
            'id'            => (int)($contact['id'] ?? 0),
            // The name on their WhatsApp profile. They chose it and nobody checked
            // it, so it is offered as a candidate: good enough to greet someone
            // with, not good enough to assert an identity on.
            'display_name'  => wa_voice_flatten($contact['profile_name'] ?? '', 120),
            'name_verified' => false,
            'country'       => wa_voice_flatten($contact['country'] ?? '', 64),
            'opted_out'     => !empty($contact['opted_out']),
        ],
        'interest' => [
            'ref_type'      => $refType,
            'ref_id'        => $refId > 0 ? $refId : null,
            'program_id'    => $programId > 0 ? $programId : null,
            'name'          => wa_voice_flatten($in['interest_name'] ?? '', 190),
            'delivery_mode' => $mode,
            'route_reason'  => wa_voice_flatten($conv['last_route_reason'] ?? '', 64),
        ],
        'representative' => [
            'id'   => $repId > 0 ? $repId : null,
            'name' => wa_voice_flatten($in['representative_name'] ?? '', 120),
        ],
        'state' => [
            'escalated' => !empty($conv['escalated']),
            'enrolment' => (string)($in['enrolment_state'] ?? 'none'),
        ],
        'recent_turns' => is_array($in['turns'] ?? null) ? $in['turns'] : [],
        // A POINTER to the knowledge, not the knowledge. The assistant calls
        // get_programme_details when it actually needs the text, which keeps this
        // response small and keeps a caller-identification request from doubling
        // as a bulk knowledge download.
        'knowledge_ref' => ($refType !== 'unknown' && $refId > 0)
            ? ['type' => $refType, 'id' => $refId,
               'name' => wa_voice_flatten($in['interest_name'] ?? '', 190)]
            : null,
    ];
    return $out;
}

/**
 * Shape programme search results: at most five, no invented numbers.
 *
 * Each item may carry a `confidence` ONLY when an existing classifier actually
 * produced one — wa_classify_course(), wa_classify_event() and
 * wa_classify_academic() each return a real figure. Keyword programme matching
 * produces a raw additive score with no defined range, so those results carry no
 * confidence at all rather than a number scaled to look like one. An invented
 * relevance score is worse than none: it reads as a measurement.
 */
function wa_voice_shape_results(array $items, $max = null) {
    $max = $max === null ? WA_VOICE_MAX_RESULTS : (int)$max;
    $out = [];
    foreach ($items as $it) {
        if (count($out) >= $max) { break; }
        $type = (string)($it['type'] ?? '');
        if (!wa_voice_valid_ref_type($type)) { continue; }
        $id = (int)($it['id'] ?? 0);
        if ($id < 1) { continue; }

        $row = [
            'type'          => $type,
            'id'            => $id,
            'name'          => wa_voice_flatten($it['name'] ?? '', 190),
            'delivery_mode' => in_array($it['delivery_mode'] ?? '', ['virtual', 'onsite'], true)
                               ? (string)$it['delivery_mode'] : 'unknown',
            'schedule'      => wa_voice_flatten($it['schedule'] ?? '', 300),
        ];
        // Only a real number from a real classifier survives. A string, a bool or a
        // missing key all mean "not measured", and the field is simply absent.
        if (array_key_exists('confidence', $it)
            && (is_int($it['confidence']) || is_float($it['confidence']))) {
            $row['confidence'] = round((float)$it['confidence'], 2);
        }
        $out[] = $row;
    }
    return $out;
}

/**
 * Shape a get_programme_details response, capping the knowledge text.
 *
 * `truncated` is reported rather than left implicit so the assistant can say "I
 * have the summary here" instead of confidently reading a document that stops
 * halfway through a fee table.
 */
function wa_voice_shape_details(array $in, $maxChars = null) {
    $maxChars = $maxChars === null ? WA_VOICE_KB_CHARS : (int)$maxChars;

    $knowledge = (string)($in['knowledge'] ?? '');
    // Preserve paragraph structure — this is read aloud, and the knowledge base
    // uses line breaks meaningfully — while removing control characters.
    $knowledge = wa_voice_strip_control($knowledge);
    $knowledge = trim((string)preg_replace('/\n{3,}/', "\n\n", $knowledge));
    list($knowledge, $truncated) = wa_voice_cut($knowledge, $maxChars);

    $mode = (string)($in['delivery_mode'] ?? 'unknown');
    if (!in_array($mode, ['unknown', 'virtual', 'onsite'], true)) { $mode = 'unknown'; }

    $out = [
        'ok'            => true,
        'type'          => (string)($in['type'] ?? ''),
        'id'            => (int)($in['id'] ?? 0),
        'name'          => wa_voice_flatten($in['name'] ?? '', 190),
        'delivery_mode' => $mode,
        'knowledge'     => $knowledge,
        'truncated'     => (bool)$truncated,
    ];
    // Optional fields appear only when they have a value, so the assistant is never
    // handed an empty string to read out as a fact.
    foreach (['when' => 'when', 'where' => 'where', 'fees' => 'fees',
              'register_url' => 'register_url', 'outline_url' => 'outline_url'] as $k => $dst) {
        // Capped as well as flattened: the programme branch builds `when` from
        // every scheduled session, which grows with the calendar.
        $v = wa_voice_flatten($in[$k] ?? '', 500);
        if ($v !== '') { $out[$dst] = $v; }
    }
    return $out;
}

/**
 * Score active programmes against free text.
 *
 * A deliberate, faithful port of wa_program_match() (wa_functions.php:3529) rather
 * than a call to it. The original reaches wa_programs_list(), which calls
 * wa_kb_ensure_schema() and issues ALTER TABLE / CREATE TABLE statements. Those
 * are no-ops on a deployed database, but Phase 2.1A is specified as read-only
 * apart from the two security tables, and a read request must not be able to
 * change the schema. The rows are read here with a prepared statement instead and
 * scored by this function.
 *
 * The two must be kept in step. If wa_program_match() changes, this changes with
 * it — wa_voice_api_test.php asserts the shared behaviours.
 *
 * @param array $programs rows from wa_programs (id, name, keywords)
 * @return array          [['program' => row, 'score' => int], ...] best first
 */
function wa_voice_score_programs(array $programs, $text) {
    if (!function_exists('wa_normalize') || !function_exists('wa_stopwords')) { return []; }
    $hay = ' ' . wa_normalize((string)$text) . ' ';
    if (trim($hay) === '') { return []; }
    $stop = wa_stopwords();

    $scored = [];
    foreach ($programs as $p) {
        $score = 0;
        $kws = function_exists('wa_program_keywords_arr')
             ? wa_program_keywords_arr($p)
             : array_filter(array_map('trim', explode(',', (string)($p['keywords'] ?? ''))));
        foreach ($kws as $kw) {
            $phrase = trim(wa_normalize($kw));
            if ($phrase === '') { continue; }
            if (strpos($hay, ' ' . $phrase . ' ') !== false) {
                $score += 2 * wa_voice_len($phrase);
                continue;
            }
            $words = []; $hits = [];
            foreach (explode(' ', $phrase) as $w) {
                if ($w === '' || wa_voice_len($w) < 3 || isset($stop[$w])) { continue; }
                $words[] = $w;
                if (strpos($hay, ' ' . $w . ' ') !== false) { $hits[] = $w; }
            }
            if ($words && (count($hits) / count($words)) >= 0.5) {
                foreach ($hits as $w) { $score += wa_voice_len($w); }
            }
        }
        if ($score > 0) { $scored[] = ['program' => $p, 'score' => $score]; }
    }
    usort($scored, function ($a, $b) { return $b['score'] <=> $a['score']; });
    return $scored;
}
