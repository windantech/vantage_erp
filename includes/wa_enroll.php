<?php
/**
 * Guided WhatsApp enrollment (Phase: capture-in-chat -> enroll directly).
 *
 * Collects the person's details one field at a time, shows a confirmation
 * summary, then enrolls them by reusing the SAME server pipeline the website
 * uses (process_registration.php / course-details.php):
 *
 *   EVENT  -> ticket_congress insert + generateAdmissionWithInvoice()
 *             + create_moodle_user_and_send_email()      (admin/ helpers)
 *   COURSE -> register insert + admission email + Moodle          (admin/ helpers)
 *
 * Gated behind the wa_settings 'enroll_enabled' flag (off by default). The
 * capture half is provider-agnostic and testable offline; finalize runs on the
 * server where the admin/ helpers and $moodle_conn exist.
 *
 * Requires wa_functions.php (loaded alongside it).
 */

if (!defined('WA_ADMIN_DIR')) {
    // wa_enroll.php lives in <erp>/includes, so the ERP/admin root is one up.
    define('WA_ADMIN_DIR', dirname(__DIR__));
}

/** The fields we collect, in order. */
function wa_enroll_fields() {
    return [
        ['key' => 'fullname',     'label' => 'Full name',    'prompt' => "Great — let's get you registered.\n\nWhat's your *full name*?"],
        ['key' => 'email',        'label' => 'Email',        'prompt' => "Thanks! What's your *email address*? (your admission letter and invoice will be sent here)"],
        ['key' => 'phone',        'label' => 'Phone',        'prompt' => "What's the best *phone number* to reach you on?\n\nReply *same* to use this WhatsApp number."],
        ['key' => 'country',      'label' => 'Country',      'prompt' => "Which *country* are you registering from?"],
        ['key' => 'organization', 'label' => 'Organization', 'prompt' => "What's your *organization / institution*?\n\nReply *skip* if this doesn't apply."],
        ['key' => 'position',     'label' => 'Position',     'prompt' => "And your *position / job title*?\n\nReply *skip* if this doesn't apply."],
    ];
}

/** Active (still collecting) enrollment session for a contact, or null. */
function wa_enroll_active($conn, $contactId) {
    $contactId = (int)$contactId;
    $res = mysqli_query($conn, "SELECT * FROM wa_enroll_sessions
        WHERE contact_id = $contactId AND status IN ('offered','collecting','confirm') LIMIT 1");
    return $res ? mysqli_fetch_assoc($res) : null;
}

/** Idempotently ensure the 'offered' status exists (added after the first
 *  release). Safe to call often; runs once per request. */
function wa_enroll_ensure_schema($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "ALTER TABLE `wa_enroll_sessions`
        MODIFY COLUMN `status` ENUM('offered','collecting','confirm','done','cancelled')
        NOT NULL DEFAULT 'collecting'");
}

/** Does this message look like an intent to register/enroll? Deliberately broad
 *  so typos and variants ("registration", "enroled", "get me enrolled") all count. */
function wa_enroll_intent($text) {
    return (bool)preg_match('/\b(enrol\w*|regist\w*|sign\s?up|apply|join)\b/i', (string)$text);
}

/** Start a capture session for the course/event the chat is about. */
function wa_enroll_start($conn, $contactId, $waId, $refType, $refId) {
    $contactId = (int)$contactId; $refId = (int)$refId;
    $refType = ($refType === 'event') ? 'event' : 'course';
    // Clear any stale session, then open a fresh one.
    mysqli_query($conn, "DELETE FROM wa_enroll_sessions WHERE contact_id = $contactId");
    $stmt = mysqli_prepare($conn,
        "INSERT INTO wa_enroll_sessions (contact_id, wa_id, ref_type, ref_id, step, data, status)
         VALUES (?, ?, ?, ?, 0, '{}', 'collecting')");
    mysqli_stmt_bind_param($stmt, 'issi', $contactId, $waId, $refType, $refId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $name = wa_ref_name($conn, $refType, $refId) ?: 'this programme';
    wa_send_text($conn, $waId, wa_enroll_ask($name));
    return true;
}

/**
 * Someone asked to register: share the website registration link FIRST, and offer
 * to walk them through right here on WhatsApp. Opens an 'offered' session so their
 * next reply is interpreted (see wa_enroll_handle). This is the intent path; the
 * rep's "Start enrollment" button still calls wa_enroll_start() to collect directly.
 */
function wa_enroll_offer($conn, $contactId, $waId, $refType, $refId) {
    wa_enroll_ensure_schema($conn);
    $contactId = (int)$contactId; $refId = (int)$refId;
    $refType = ($refType === 'event') ? 'event' : 'course';
    mysqli_query($conn, "DELETE FROM wa_enroll_sessions WHERE contact_id = $contactId");
    $stmt = mysqli_prepare($conn,
        "INSERT INTO wa_enroll_sessions (contact_id, wa_id, ref_type, ref_id, step, data, status)
         VALUES (?, ?, ?, ?, 0, '{}', 'offered')");
    mysqli_stmt_bind_param($stmt, 'issi', $contactId, $waId, $refType, $refId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $name = wa_ref_name($conn, $refType, $refId) ?: 'this programme';
    $link = function_exists('wa_register_link') ? wa_register_link($conn, $refType, $refId) : '';
    $msg  = "Wonderful — I'd be glad to help you register for *{$name}*.\n\n";
    if ($link !== '') {
        $msg .= "You can register online here:\n{$link}\n\n"
              . "Or if you'd prefer, I can take your details right here on WhatsApp and get you registered — "
              . "just reply *HERE* and I'll walk you through it. _(or use the link above)_";
    } else {
        $msg .= "I can take your details right here on WhatsApp and get you registered — reply *HERE* to begin, "
              . "or reply *CANCEL* if you'd rather not right now.";
    }
    wa_send_text($conn, $waId, $msg);
    return true;
}

/** The single "send all your details in one message" prompt. */
function wa_enroll_ask($name, $lead = '') {
    $lead = $lead !== '' ? $lead . "\n\n" : "Perfect — let's get you registered for *{$name}*.\n\n";
    return $lead
        . "Please reply with all of these *in one message*:\n\n"
        . "• Full name\n• Email address\n• Phone number\n• Country\n• Organization (or write _none_)\n• Job title (or _none_)\n\n"
        . "For example:\n_Jane Doe, jane@example.com, 0712345678, Kenya, Acme Ltd, Programs Manager_\n\n"
        . "_You can ask me a question anytime, or reply *CANCEL* to stop._";
}

/** Does the message ask to stop / bail out of registration? Deliberately broad
 *  (phrase-level, not exact) so people are never trapped in the form. */
function wa_enroll_wants_out($text) {
    return (bool)preg_match(
        '/\b(cancel|stop|quit|exit|unsubscribe|no thanks|not now|nevermind|never mind|forget it|maybe later|another time|later|pause|hold on|change my mind|don\'?t want)\b/i',
        (string)$text);
}

/** Does the message read like a question / general query rather than an attempt
 *  to give registration details? Used to divert it to the AI instead of nagging. */
function wa_enroll_is_question($text) {
    $t = (string)$text;
    if (strpos($t, '?') !== false) { return true; }
    return (bool)preg_match(
        '/\b(what|whats|how|when|where|why|which|who|can i|do you|does|is there|are there|cost|price|fee|how much|deposit|discount|scholarship|schedule|venue|date|dates|duration|refund|help|explain|tell me|difference|available)\b/i',
        $t);
}

/** Does the message contain an actual data signal (email or a long digit run)?
 *  If so, treat it as a registration attempt (parse/nag) even if it also looks
 *  question-ish, so we don't divert a real "my email is x, phone 0712..." reply. */
function wa_enroll_has_data_signal($text) {
    $t = (string)$text;
    if (preg_match('/[^\s@]+@[^\s@]+\.[^\s@]+/', $t)) { return true; }         // an email
    if (preg_match('/\d[\d\s\-]{6,}\d/', $t)) { return true; }                  // a phone-length digit run
    return false;
}

/** Answer a mid-registration question via the normal AI, keeping the form open.
 *  The AI reply is sent by wa_maybe_ai_answer; we add a short "still open" nudge. */
function wa_enroll_divert_to_ai($conn, $waId, $text, $reminder = null) {
    $aiOn = wa_setting_get($conn, 'ai_autoreply', '0') === '1';
    $res = ($aiOn && function_exists('wa_maybe_ai_answer'))
        ? wa_maybe_ai_answer($conn, $waId, $text) : ['ok' => false];
    // If the AI is off / couldn't answer (no provider, muted), hand off gracefully.
    if (empty($res['ok'])) {
        wa_send_text($conn, $waId, "Good question — let me get a colleague to help you with that.");
    }
    if ($reminder === null) {
        $reminder = "_Your registration is still open — send your details anytime to finish, or reply *CANCEL* to stop._";
    }
    if ($reminder !== '') { wa_send_text($conn, $waId, $reminder); }
}

/**
 * Feed an inbound message to the active capture session.
 * Returns true if the message was consumed by the enrollment flow.
 */
function wa_enroll_handle($conn, $contactId, $waId, $text) {
    $sess = wa_enroll_active($conn, $contactId);
    if (!$sess) { return false; }
    $text = trim((string)$text);
    $data = json_decode($sess['data'] ?: '{}', true);
    if (!is_array($data)) { $data = []; }

    // Universal bail-out — advertised in every prompt so nobody is ever stuck.
    if (wa_enroll_wants_out($text)) {
        wa_enroll_cancel($conn, (int)$sess['id']);
        wa_send_text($conn, $waId, "No problem — I've stopped the registration. You can ask me anything, or message *register* any time to start again.");
        return true;
    }

    $name = wa_ref_name($conn, $sess['ref_type'], (int)$sess['ref_id']) ?: 'the programme';

    // Offered step: we shared the link and asked if they want to do it on WhatsApp.
    // ONLY a clear "yes, on WhatsApp" starts collecting. Everything else — a
    // different course, "share the link", another question — is DEFERRED (return
    // false) so normal routing + AI handle it (switch topic, reshare the RIGHT
    // link, answer). This stops the offer from trapping the conversation.
    if ($sess['status'] === 'offered') {
        $wantsHere = preg_match('/\b(here|on ?whats?app|via ?whats?app|walk me|guide me|step[\s-]?by[\s-]?step|take my details|register here|go ahead)\b/i', $text)
            || preg_match('/^\s*(yes|yeah|yep|sure|ok|okay|proceed|please do|do it)\b[.! ]*$/i', $text);
        if ($wantsHere) {
            // Collect for the CURRENT topic — routing may have switched the course
            // since we made the offer, so trust the live conversation ref.
            $conv = function_exists('wa_get_conversation') ? wa_get_conversation($conn, (int)$contactId) : null;
            $rt = ($conv && in_array($conv['ref_type'] ?? '', ['course', 'event'], true) && ($conv['ref_id'] ?? null) !== null)
                ? $conv['ref_type'] : $sess['ref_type'];
            $rid = ($conv && ($conv['ref_id'] ?? null) !== null) ? (int)$conv['ref_id'] : (int)$sess['ref_id'];
            $rt = ($rt === 'event') ? 'event' : 'course';
            mysqli_query($conn, "UPDATE wa_enroll_sessions
                SET status = 'collecting', ref_type = '$rt', ref_id = " . (int)$rid . "
                WHERE id = " . (int)$sess['id']);
            $name = wa_ref_name($conn, $rt, $rid) ?: 'the programme';
            wa_send_text($conn, $waId, wa_enroll_ask($name));
            return true;
        }
        // If the AI is off we can't defer to it — reshare the link/options ourselves.
        if (wa_setting_get($conn, 'ai_autoreply', '0') !== '1') {
            $link = function_exists('wa_register_link') ? wa_register_link($conn, $sess['ref_type'], (int)$sess['ref_id']) : '';
            $msg = $link !== ''
                ? "Here's the registration link for *{$name}*:\n{$link}\n\nOr reply *HERE* to register on WhatsApp instead."
                : "Reply *HERE* to register on WhatsApp, or *CANCEL* to stop.";
            wa_send_text($conn, $waId, $msg);
            return true;
        }
        return false;   // defer to routing + AI
    }

    // Confirmation step.
    if ($sess['status'] === 'confirm') {
        if (preg_match('/^\s*(yes|y|confirm|correct|yeah|yep|sawa|ndio|ndiyo|proceed|go ahead)\s*$/i', $text)) {
            // Remember the customer's email on the contact so a completed payment
            // (dpo_payment, matched by email) can later be confirmed back to them (#15).
            if (function_exists('wa_contact_set_email') && !empty($data['email'])) {
                wa_contact_set_email($conn, (int)$sess['contact_id'], (string)$data['email']);
            }
            wa_enroll_finalize($conn, $sess, $data);
            return true;
        }
        if (preg_match('/^\s*(no|n|edit|change|hapana|wrong)\s*$/i', $text)) {
            mysqli_query($conn, "UPDATE wa_enroll_sessions SET data = '{}', status = 'collecting' WHERE id = " . (int)$sess['id']);
            wa_send_text($conn, $waId, wa_enroll_ask($name, "No problem — let's redo it."));
            return true;
        }
        // Not yes/no — if they're asking something, answer it and keep the summary open.
        if (wa_enroll_is_question($text) && !wa_enroll_has_data_signal($text)) {
            wa_enroll_divert_to_ai($conn, $waId, $text);
            wa_send_text($conn, $waId, "When you're ready, reply *YES* to confirm your registration or *NO* to re-enter.");
            return true;
        }
        wa_send_text($conn, $waId, "Please reply *YES* to confirm, or *NO* to re-enter your details. _(or *CANCEL* to stop)_");
        return true;
    }

    // Collecting: a question with no data in it -> answer it and keep the form
    // open (checked BEFORE parsing so the greedy parser can't eat the question
    // as a "name"). Anything carrying an email/phone is treated as real details.
    if (wa_enroll_is_question($text) && !wa_enroll_has_data_signal($text)) {
        wa_enroll_divert_to_ai($conn, $waId, $text);
        return true;
    }

    // Parse the whole message into fields, merged with what we have.
    $data    = wa_enroll_parse($conn, $text, $data, $waId);
    $missing = wa_enroll_missing($data);

    if (!$missing) {
        wa_enroll_to_confirm($conn, $sess, $data, $waId);
    } else {
        mysqli_query($conn, "UPDATE wa_enroll_sessions SET data = " . wa_sql($conn, json_encode($data)) . " WHERE id = " . (int)$sess['id']);
        $labels = ['fullname' => 'full name', 'email' => 'a valid email', 'phone' => 'phone number', 'country' => 'country'];
        $need = array_map(function ($k) use ($labels) { return $labels[$k] ?? $k; }, $missing);
        $gotKeys = array_keys(array_filter([
            'name'    => $data['fullname'] ?? '',
            'email'   => (filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL) ? '1' : ''),
            'phone'   => $data['phone'] ?? '',
            'country' => $data['country'] ?? '',
        ]));
        $got = $gotKeys ? "Got your " . wa_enroll_join($gotKeys) . ". " : '';
        wa_send_text($conn, $waId, $got . "I still need your *" . wa_enroll_join($need) . "* — please send " . (count($need) > 1 ? 'them' : 'it') . ". _(or reply *CANCEL* to stop)_");
    }
    return true;
}

/** Required registration fields still missing or invalid. */
function wa_enroll_missing($data) {
    $missing = [];
    if (trim((string)($data['fullname'] ?? '')) === '') { $missing[] = 'fullname'; }
    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) { $missing[] = 'email'; }
    if (strlen(preg_replace('/[^\d]/', '', $data['phone'] ?? '')) < 7) { $missing[] = 'phone'; }
    if (trim((string)($data['country'] ?? '')) === '') { $missing[] = 'country'; }
    return $missing;
}

/** Join items into "a, b and c". */
function wa_enroll_join($items) {
    $items = array_values($items);
    if (count($items) <= 1) { return $items[0] ?? ''; }
    $last = array_pop($items);
    return implode(', ', $items) . ' and ' . $last;
}

/**
 * Parse all registration fields out of ONE free-text message, merged onto
 * $existing (so a follow-up message fills gaps rather than overwrites). Uses the
 * AI provider when a key is set; falls back to a heuristic otherwise.
 */
function wa_enroll_parse($conn, $message, $existing, $waId) {
    $out = is_array($existing) ? $existing : [];
    $keys = ['fullname', 'email', 'phone', 'country', 'organization', 'position'];

    $parsed = null;
    if (wa_provider_ready(wa_active_provider($conn))) {
        $system = "Extract registration details from the message. Return ONLY JSON with exactly these keys: "
            . "fullname, email, phone, country, organization, position. Use an empty string for any field that is "
            . "not present. Treat 'none', 'n/a', 'na', 'skip' as empty. Never invent or infer values that aren't there.";
        $res = wa_ai_complete(wa_active_provider($conn), $system,
            [['role' => 'user', 'content' => $message]], ['json' => true, 'max_tokens' => 300]);
        if (!empty($res['ok'])) { $parsed = wa_json_extract($res['text']); }
    }
    if (!is_array($parsed)) { $parsed = wa_enroll_parse_heuristic($message); }

    foreach ($keys as $k) {
        $v = trim((string)($parsed[$k] ?? ''));
        if (preg_match('/^(none|n\/a|na|skip|-)$/i', $v)) { $v = ''; }
        if ($v !== '') { $out[$k] = $v; }              // only fill when we actually extracted something
    }
    if (isset($out['phone']) && preg_match('/^\s*same\s*$/i', $out['phone'])) { $out['phone'] = $waId; }
    if (isset($out['email']) && !filter_var($out['email'], FILTER_VALIDATE_EMAIL)) { unset($out['email']); }
    return $out;
}

/** No-AI fallback: pull email + phone by pattern, then positional name/country/org/title. */
function wa_enroll_parse_heuristic($message) {
    $r = ['fullname' => '', 'email' => '', 'phone' => '', 'country' => '', 'organization' => '', 'position' => ''];
    $msg = trim((string)$message);
    if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/', $msg, $m)) { $r['email'] = $m[0]; }
    if (preg_match('/(\+?\d[\d\s().-]{6,}\d)/', $msg, $m)) { $r['phone'] = trim($m[1]); }
    $rest = [];
    foreach (preg_split('/[,\n;|]+/', $msg) as $p) {
        $p = trim($p);
        if ($p === '') { continue; }
        if ($r['email'] !== '' && stripos($p, $r['email']) !== false) { continue; }
        if ($r['phone'] !== '' && preg_replace('/\D/', '', $p) === preg_replace('/\D/', '', $r['phone'])) { continue; }
        $rest[] = $p;
    }
    if (isset($rest[0])) { $r['fullname']     = $rest[0]; }
    if (isset($rest[1])) { $r['country']      = $rest[1]; }
    if (isset($rest[2])) { $r['organization'] = $rest[2]; }
    if (isset($rest[3])) { $r['position']     = $rest[3]; }
    return $r;
}

/** Validate + normalise one field. Returns [ok, value, errorMsg]. */
function wa_enroll_validate($key, $text, $waId) {
    $t = trim($text);
    switch ($key) {
        case 'fullname':
            if (mb_strlen($t) < 2 || !preg_match('/[A-Za-z]/', $t)) {
                return [false, null, "That doesn't look like a name."];
            }
            return [true, $t, null];
        case 'email':
            if (!filter_var($t, FILTER_VALIDATE_EMAIL)) {
                return [false, null, "Hmm, that email doesn't look valid."];
            }
            return [true, $t, null];
        case 'phone':
            if (preg_match('/^\s*same\s*$/i', $t)) { return [true, $waId, null]; }
            $digits = preg_replace('/[^\d]/', '', $t);
            if (strlen($digits) < 7) { return [false, null, "That phone number looks too short."]; }
            return [true, $t, null];
        case 'organization':
        case 'position':
            if (preg_match('/^\s*(skip|none|n\/a|na)\s*$/i', $t)) { return [true, '', null]; }
            return [true, $t, null];
        case 'country':
        default:
            if ($t === '') { return [false, null, "Please enter a value."]; }
            return [true, $t, null];
    }
}

/** Move the session to the confirmation step and send the summary. */
function wa_enroll_to_confirm($conn, $sess, $data, $waId) {
    $name = wa_ref_name($conn, $sess['ref_type'], (int)$sess['ref_id']) ?: 'the programme';
    $summary  = "Please check your details for *{$name}*:\n\n";
    $summary .= "• Name: " . ($data['fullname'] ?? '-') . "\n";
    $summary .= "• Email: " . ($data['email'] ?? '-') . "\n";
    $summary .= "• Phone: " . ($data['phone'] ?? '-') . "\n";
    $summary .= "• Country: " . ($data['country'] ?? '-') . "\n";
    $summary .= "• Organization: " . (($data['organization'] ?? '') !== '' ? $data['organization'] : '—') . "\n";
    $summary .= "• Position: " . (($data['position'] ?? '') !== '' ? $data['position'] : '—') . "\n\n";
    $summary .= "Reply *YES* to confirm and complete your registration, or *NO* to re-enter.\n_(or reply *CANCEL* to stop)_";
    mysqli_query($conn, "UPDATE wa_enroll_sessions SET status = 'confirm', data = " . wa_sql($conn, json_encode($data)) . " WHERE id = " . (int)$sess['id']);
    wa_send_text($conn, $waId, $summary);
}

function wa_enroll_cancel($conn, $sessId) {
    mysqli_query($conn, "UPDATE wa_enroll_sessions SET status = 'cancelled' WHERE id = " . (int)$sessId);
}

/** The customer's last few inbound messages (most recent first) joined with the
 *  current one — used to detect which programme they're actually on when the
 *  enrol trigger itself is a bare "yes / enrol me" that names no course. */
function wa_enroll_recent_context($conn, $contactId, $current) {
    $contactId = (int)$contactId;
    $parts = [(string)$current];
    $res = mysqli_query($conn,
        "SELECT body FROM wa_messages
          WHERE contact_id = $contactId AND direction = 'inbound' AND body <> ''
          ORDER BY id DESC LIMIT 4");
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $parts[] = (string)$r['body']; } }
    return implode('  ', $parts);
}

/**
 * The single hook the webhook calls. Returns true if enrollment consumed the
 * message (so the caller skips the AI answer). No-op unless enroll_enabled=1.
 */
function wa_enroll_intercept($conn, $contactId, $waId, $body) {
    // Always continue an active session — once a rep (or intent) started it, the
    // contact's replies must finish the flow regardless of the global toggle.
    if (wa_enroll_active($conn, $contactId)) {
        return wa_enroll_handle($conn, $contactId, $waId, $body);
    }
    // Auto-start on intent only when enabled + the chat is on a course/event.
    if (wa_setting_get($conn, 'enroll_enabled', '0') === '1'
        && $body !== null && $body !== '' && wa_enroll_intent($body)) {
        // Route to the programme they're ACTUALLY discussing, so we offer the RIGHT
        // one. A bare "yes, enrol me" names no course, so classify over their recent
        // messages (not just this line) — that catches a switch made a turn or two ago
        // (e.g. moved from Senior Management to AI for Leaders) instead of offering the
        // stale programme the chat was first about.
        if (function_exists('wa_route_inbound')) {
            $ctx = wa_enroll_recent_context($conn, (int)$contactId, (string)$body);
            @wa_route_inbound($conn, $waId, $ctx);
        }
        $conv = wa_get_conversation($conn, (int)$contactId);
        if ($conv && $conv['ref_id'] !== null && in_array($conv['ref_type'], ['course','event'], true)) {
            // Share the link + offer to walk them through here (not straight to collecting).
            return wa_enroll_offer($conn, $contactId, $waId, $conv['ref_type'], (int)$conv['ref_id']);
        }
    }
    return false;
}

// =====================================================================
// Finalize — reuse the website's real pipeline. Faithful port of
// process_registration.php (events) and course-details.php (courses).
// Every external helper call is wrapped so a failure never loses the lead.
// =====================================================================

function wa_enroll_finalize($conn, $sess, $data) {
    $waId = $sess['wa_id'];
    mysqli_query($conn, "UPDATE wa_enroll_sessions SET status = 'done' WHERE id = " . (int)$sess['id']);

    // Sandbox dry-run (test console): preview only — no ticket_congress/register
    // write, no admission/invoice email, no Moodle.
    if (!empty($GLOBALS['WA_ENROLL_DRY'])) {
        $name = wa_ref_name($conn, $sess['ref_type'], (int)$sess['ref_id']) ?: 'the programme';
        wa_send_text($conn, $waId,
            "✅ (TEST) You'd be registered for *{$name}* with:\n"
            . "• Name: " . ($data['fullname'] ?? '-') . "\n"
            . "• Email: " . ($data['email'] ?? '-') . "\n"
            . "• Phone: " . ($data['phone'] ?? '-') . "\n"
            . "• Country: " . ($data['country'] ?? '-') . "\n"
            . "• Organization: " . (($data['organization'] ?? '') !== '' ? $data['organization'] : '—') . "\n"
            . "• Position: " . (($data['position'] ?? '') !== '' ? $data['position'] : '—') . "\n\n"
            . "_Sandbox: no real record, email or Moodle account was created._");
        return;
    }

    if ($sess['ref_type'] === 'event') {
        wa_enroll_finalize_event($conn, $sess, $data);
    } else {
        wa_enroll_finalize_course($conn, $sess, $data);
    }
}

/** EVENT: ticket_congress + admission/invoice + Moodle (mirrors process_registration.php). */
function wa_enroll_finalize_event($conn, $sess, $data) {
    $waId     = $sess['wa_id'];
    $eventId  = (int)$sess['ref_id'];
    $fullname = $data['fullname'] ?? '';
    $email    = $data['email'] ?? '';
    $phone    = $data['phone'] ?? '';
    $country  = $data['country'] ?? '';
    $org      = $data['organization'] ?? '';
    $position = $data['position'] ?? '';

    // Already registered for this event?
    $emailEsc = mysqli_real_escape_string($conn, $email);
    $dup = mysqli_query($conn, "SELECT ticket_id FROM ticket_congress WHERE email = '$emailEsc' AND event_id = $eventId LIMIT 1");
    if ($dup && mysqli_num_rows($dup) > 0) {
        $row = mysqli_fetch_assoc($dup);
        mysqli_query($conn, "UPDATE wa_enroll_sessions SET result_ref = " . wa_sql($conn, $row['ticket_id']) . " WHERE id = " . (int)$sess['id']);
        wa_send_text($conn, $waId, "You're already registered for this event. We've re-sent your details to {$email}. Our team will follow up on payment.");
        return;
    }

    $ticket_id    = 'VASL' . time();
    $admission_no = 'VASL ' . rand(1111111, 9999999);
    $date_sent    = date('Y-m-d H:i:s');
    $term = 'terms'; $status = '1'; $amount = '0'; $ticket_number = 1; $confirmation = 'pending';

    $stmt = mysqli_prepare($conn, "INSERT INTO `ticket_congress`
        (`fullname`,`email`,`term`,`phone_number`,`ticket_id`,`status`,`amount`,`ticket_number`,`confirmation`,`date_sent`,`organization`,`position`,`event_id`,`country`,`admission_no`)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    if (!$stmt) { wa_send_text($conn, $waId, "Sorry, something went wrong saving your registration. A team member will reach out shortly."); return; }
    mysqli_stmt_bind_param($stmt, 'sssssssissssiss',
        $fullname, $email, $term, $phone, $ticket_id, $status, $amount, $ticket_number, $confirmation, $date_sent,
        $org, $position, $eventId, $country, $admission_no);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) { wa_send_text($conn, $waId, "Sorry, something went wrong saving your registration. A team member will reach out shortly."); return; }

    mysqli_query($conn, "UPDATE wa_enroll_sessions SET result_ref = " . wa_sql($conn, $ticket_id) . " WHERE id = " . (int)$sess['id']);

    // Admission letter + invoice (uses $conn; same call as process_registration.php).
    $eventTitle = '';
    try {
        $ev = mysqli_query($conn, "SELECT event_id, start_on, end_on, location, event_title, COALESCE(early_amount,0) AS early_amount FROM Event WHERE event_id = $eventId LIMIT 1");
        $er = $ev ? mysqli_fetch_assoc($ev) : null;
        $invoice_file   = WA_ADMIN_DIR . '/invoice_international_.php';
        $email_log_file = WA_ADMIN_DIR . '/includes/email_log_functions.php';
        if (!$er) {
            error_log('[wa-enroll] event ' . $eventId . ' not found — admission/invoice skipped');
        } elseif (!is_file($invoice_file)) {
            error_log('[wa-enroll] invoice helper not found at ' . $invoice_file . ' — admission/invoice NOT sent');
        } else {
            $eventTitle = $er['event_title'];
            if (is_file($email_log_file) && !function_exists('log_email')) { include_once $email_log_file; }
            include_once $invoice_file;
            $title = $er['event_title'];
            $corporate_variant = '';
            if (stripos($title, 'SLDP') !== false || stripos($title, 'Strategic Leadership') !== false) { $corporate_variant = 'corporate_sldp'; }
            if ($corporate_variant === '') {
                $is_me_or_cmep = (stripos($title, 'CMEP') !== false) || (stripos($title, 'Monitoring') !== false && stripos($title, 'Evaluation') !== false);
                if (stripos($title, 'Singapore') !== false && $is_me_or_cmep) { $corporate_variant = 'singapore_me'; }
                elseif (stripos($title, 'CMEP') !== false) { $corporate_variant = 'corporate_me'; }
                elseif (stripos($title, 'Corporate') !== false && stripos($title, 'Monitoring') !== false && stripos($title, 'Evaluation') !== false) { $corporate_variant = 'corporate_me'; }
            }
            $startFmt = $er['start_on'] ? (new DateTime($er['start_on']))->format('jS M') : '';
            $endFmt   = $er['end_on']   ? (new DateTime($er['end_on']))->format('jS M')   : '';
            if (function_exists('generateAdmissionWithInvoice')) {
                generateAdmissionWithInvoice($email, $fullname, $er['event_title'], [], 0, [], $startFmt, $endFmt,
                    $er['location'], $conn, $ticket_id, null, $corporate_variant, null, $position, $org, $country, $eventId);
                error_log('[wa-enroll] admission+invoice generated for ticket ' . $ticket_id . ' (' . $email . ')');
            } else {
                error_log('[wa-enroll] generateAdmissionWithInvoice() missing after include — admission/invoice NOT sent');
            }
        }
    } catch (Throwable $e) { error_log('[wa-enroll] invoice: ' . $e->getMessage()); }

    // Moodle account + credentials email (optional; needs $moodle_conn).
    wa_enroll_moodle($fullname, $email, $phone, $country, $org);

    $title = $eventTitle ?: 'the training';
    wa_send_text($conn, $waId,
        "You're registered for *{}*.\n\nYour admission letter and invoice are on the way to *{$email}* (please check spam too). Our team will follow up on payment shortly. Karibu!");
}

/** COURSE: register insert + admission email + Moodle (mirrors course-details.php). */
function wa_enroll_finalize_course($conn, $sess, $data) {
    $waId     = $sess['wa_id'];
    $courseId = (int)$sess['ref_id'];
    $fullname = $data['fullname'] ?? '';
    $parts = preg_split('/\s+/', trim($fullname), 2);
    $firstname = $parts[0] ?? '';
    $lastname  = $parts[1] ?? '';
    $email    = $data['email'] ?? '';
    $phone    = $data['phone'] ?? '';
    $country  = $data['country'] ?? '';
    $org      = $data['organization'] ?? '';
    $position = $data['position'] ?? '';

    // Unique entry_id (same approach as course-details.php).
    $entry_id = rand(1000011, 9999999);
    $chk = mysqli_query($conn, "SELECT entry_id FROM register WHERE entry_id = '$entry_id'");
    while ($chk && mysqli_num_rows($chk) > 0) {
        $entry_id = rand(1000011, 9999999);
        $chk = mysqli_query($conn, "SELECT entry_id FROM register WHERE entry_id = '$entry_id'");
    }
    // Latest active intake for the course.
    $intake = '';
    $ci = mysqli_query($conn, "SELECT intake_id FROM intake WHERE course_id = '$courseId' AND status = 1 ORDER BY date_created DESC LIMIT 1");
    if ($ci && mysqli_num_rows($ci) > 0) { $intake = mysqli_fetch_assoc($ci)['intake_id']; }

    $stmt = mysqli_prepare($conn, "INSERT INTO `register`
        (entry_id, email, firstname, lastname, phone_number, program, country, intake_id, organization, position)
        VALUES (?,?,?,?,?,?,?,?,?,?)");
    if (!$stmt) { wa_send_text($conn, $waId, "Sorry, something went wrong saving your registration. A team member will reach out shortly."); return; }
    mysqli_stmt_bind_param($stmt, 'isssssssss',
        $entry_id, $email, $firstname, $lastname, $phone, $courseId, $country, $intake, $org, $position);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) { wa_send_text($conn, $waId, "Sorry, something went wrong saving your registration. A team member will reach out shortly."); return; }

    mysqli_query($conn, "UPDATE wa_enroll_sessions SET result_ref = " . wa_sql($conn, (string)$entry_id) . " WHERE id = " . (int)$sess['id']);

    // Course name for the message.
    $courseName = wa_course_name($conn, $courseId) ?: 'your course';

    // Admission letter + invoice + Moodle (mirrors course-details.php).
    wa_enroll_course_materials($conn, $courseId, $entry_id, [
        'firstname' => $firstname, 'lastname' => $lastname, 'email' => $email,
        'phone' => $phone, 'country' => $country, 'organization' => $org, 'position' => $position,
    ]);

    wa_send_text($conn, $waId,
        "You're registered for *{$courseName}*. Your admission letter and invoice are on the way to *{$email}* (please check spam too). Our team will share payment details shortly. Karibu!");
}

/**
 * COURSE admission letter + invoice + Moodle course-enrolment. Faithful to
 * course-details.php: pulls the system_emails1 template, sets the same variables
 * adm_letter.php expects, and includes it (which builds + sends the letter +
 * invoice). Every step is logged; never throws.
 */
function wa_enroll_course_materials($conn, $courseId, $entry_id, $data) {
    try {
        $firstname = $data['firstname']; $lastname = $data['lastname'];
        $email = $data['email']; $phone = $data['phone'];
        $country = $data['country']; $org = $data['organization']; $position = $data['position'];

        $cr = mysqli_query($conn, "SELECT * FROM course WHERE course_id = " . (int)$courseId . " LIMIT 1");
        $course = $cr ? mysqli_fetch_assoc($cr) : null;
        if (!$course) { error_log('[wa-enroll] course ' . $courseId . ' not found — admission letter NOT sent'); return; }

        // Same helpers course-details.php requires before including adm_letter.php.
        foreach ([
            WA_ADMIN_DIR . '/email_plugins/vendor/autoload.php',
            WA_ADMIN_DIR . '/email_plugins/email_function.php',
            WA_ADMIN_DIR . '/amount_to_words/formatter.php',
            WA_ADMIN_DIR . '/pdf_plugins/generatePdf.php',
        ] as $f) {
            if (is_file($f)) { require_once $f; }
            else { error_log('[wa-enroll] course admission helper missing: ' . $f); }
        }

        $program_name = $course['course'];
        $pnEsc = mysqli_real_escape_string($conn, $program_name);
        $sel = mysqli_query($conn, "SELECT * FROM system_emails1 WHERE course_opt = '$pnEsc' AND email_opt = 1 ORDER BY id DESC LIMIT 1");
        if (!$sel || mysqli_num_rows($sel) === 0) {
            error_log('[wa-enroll] no active system_emails1 template for course "' . $program_name . '" — admission letter NOT sent');
        } else {
            $row_result     = mysqli_fetch_assoc($sel);
            // Variables adm_letter.php reads from scope (same names as course-details.php).
            $adm_no         = "VASL-" . $entry_id;
            $adm            = $course['adm_letter'] ?? '';
            $amount         = number_format((float)($course['price_usd'] ?? 0), 2, '.', ',');
            $course_id_new  = $course['course_id_new'] ?? '';
            $email_address  = $email;
            $subject        = "Vantage Africa School Of Leadership Approval";
            $recipient_name = ucfirst(strtolower($firstname)) . " " . ucfirst(strtolower($lastname));
            $body           = json_decode($row_result['body'], true);
            $body           = is_string($body) ? str_replace('$name', $recipient_name, $body) : $body;
            $invoice_no     = $adm_no;
            $purpose        = $program_name;
            $adm_letter_file = WA_ADMIN_DIR . '/adm_letter.php';
            if (is_file($adm_letter_file)) {
                include $adm_letter_file;   // builds + sends the admission letter + invoice
                error_log('[wa-enroll] course admission letter sent to ' . $email . ' for "' . $program_name . '"');
            } else {
                error_log('[wa-enroll] adm_letter.php not found at ' . $adm_letter_file);
            }
        }

        // Moodle: course-details.php uses createAndEnrollUser() (defined inline there).
        // If a shared version exists, use it (enrols in the Moodle course); else
        // fall back to creating the account + credentials email only.
        $moodle_conn = wa_moodle_conn();
        if ($moodle_conn && function_exists('createAndEnrollUser')) {
            $userDetails = ['email' => $email, 'firstname' => $firstname, 'lastname' => $lastname,
                'phone_number' => $phone, 'organization' => $org, 'position' => $position, 'country' => $country];
            createAndEnrollUser($userDetails, $course['course_id_new'] ?? '', $program_name, $moodle_conn);
            error_log('[wa-enroll] Moodle course-enrol done for ' . $email);
        } else {
            wa_enroll_moodle($firstname . ' ' . $lastname, $email, $phone, $country, $org);
        }
    } catch (Throwable $e) { error_log('[wa-enroll] course materials: ' . $e->getMessage()); }
}

/** Create a Moodle user + send credentials, if Moodle is configured. Never throws. */
function wa_enroll_moodle($fullname, $email, $phone, $country, $org) {
    try {
        $moodle_conn = wa_moodle_conn();
        if (!$moodle_conn) { error_log('[wa-enroll] Moodle not configured (WA_MOODLE_*) — account/login email NOT created'); return; }
        $parts = preg_split('/\s+/', trim($fullname), 2);
        $firstname = $parts[0] ?? '';
        $lastname  = $parts[1] ?? '';
        $fn = WA_ADMIN_DIR . '/includes/moodle_user_functions.php';
        $autoload = WA_ADMIN_DIR . '/email_plugins/vendor/autoload.php';
        $emailfn  = WA_ADMIN_DIR . '/email_plugins/email_function.php';
        if (!is_file($fn)) { error_log('[wa-enroll] Moodle helper not found at ' . $fn); return; }
        include_once $fn;
        $can_email = is_file($autoload) && is_file($emailfn);
        if ($can_email) { require_once $autoload; require_once $emailfn; }
        if (function_exists('create_moodle_user_and_send_email')) {
            create_moodle_user_and_send_email($moodle_conn, $email, $firstname, $lastname, $phone, $country, $org, $can_email);
            error_log('[wa-enroll] Moodle account + credentials email done for ' . $email);
        } else {
            error_log('[wa-enroll] create_moodle_user_and_send_email() missing after include');
        }
    } catch (Throwable $e) { error_log('[wa-enroll] moodle: ' . $e->getMessage()); }
}

/** Connect to the Moodle DB from WA_MOODLE_* constants, or null if not configured. */
function wa_moodle_conn() {
    static $conn = null; static $tried = false;
    if ($tried) { return $conn; }
    $tried = true;
    if (!defined('WA_MOODLE_HOST') || !WA_MOODLE_HOST || !defined('WA_MOODLE_NAME') || !WA_MOODLE_NAME) { return null; }
    $c = @mysqli_connect(WA_MOODLE_HOST, WA_MOODLE_USER, WA_MOODLE_PASS, WA_MOODLE_NAME);
    if ($c) { @mysqli_set_charset($c, 'utf8mb4'); $conn = $c; }
    return $conn;
}
