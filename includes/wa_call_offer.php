<?php
/**
 * Phase 1.2 — AI call handoff.
 *
 * When a customer plainly says they want to join, register or learn more about a
 * course we have already identified, the system asks WhatsApp for permission to
 * call them, then tells them so in the chat they are already in.
 *
 * There is no intermediate "would you like a call?" question. The customer's
 * answer to Meta's own permission prompt IS the decision, which keeps one
 * authoritative record instead of two that can disagree.
 *
 * TWO GATES, BOTH REQUIRED. The model may set request_call_permission, but the
 * model alone can never send anything: wa_call_interest_detected() has to agree,
 * on the customer's own words. A prompt is guidance; this is a rule. Without the
 * second gate a single confused completion could message a customer and spend one
 * of only two requests allowed in seven days.
 *
 * Everything about eligibility, leasing, throttling, configuration and the API
 * call is reused from Phase 1.1 unchanged — this file decides WHETHER to ask, and
 * wa_call_permissions.php decides whether it is allowed and does the work.
 */

require_once __DIR__ . '/wa_call_config.php';
require_once __DIR__ . '/wa_call_permissions.php';
require_once __DIR__ . '/wa_call_api.php';

/** Wording sent through the 796 conversation once 360dialog accepts the request. */
if (!defined('WA_CALL_OFFER_NOTICE')) {
    define('WA_CALL_OFFER_NOTICE',
        'We have sent a WhatsApp call permission request from our official calling line, '
      . '+254 798 009935. Approve it if you would like an admissions advisor to call you. '
      . 'You can continue chatting with us here if you prefer.');
}

// =====================================================================
// Deterministic interest detection (pure)
// =====================================================================

/**
 * Does this message explicitly say the customer wants to join / register / learn
 * more? Pure, and deliberately conservative: a false positive costs a real
 * customer a real message and one of two weekly requests, while a false negative
 * costs nothing — the rep can still ask by hand, and the customer usually says it
 * again more plainly.
 *
 * English and Swahili. Matching is on word boundaries over a normalised string,
 * the same approach as wa_detect_delivery_mode(), rather than substring
 * containment which would fire on "not interested".
 *
 * DELIBERATELY EXCLUDED: "I want more information", "tell me more", "nataka
 * maelezo zaidi". Those are the weakest possible signal — the prefilled text of
 * our own click-to-WhatsApp adverts is literally a request for more info, so
 * treating it as intent would fire a permission request at every ad click. Those
 * messages still get a normal AI answer; only the automatic handoff is withheld,
 * and a rep can always ask by hand.
 *
 * @return bool
 */
function wa_call_interest_detected($text) {
    $t = ' ' . mb_strtolower(trim((string)$text)) . ' ';
    if (trim($t) === '') { return false; }
    // Collapse punctuation to spaces so "interested!" and "join?" still match.
    $t = ' ' . trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t)) . ' ';

    // Negations first. "not interested" and "sitaki" must never read as interest,
    // and they contain the very words the positive patterns look for.
    if (preg_match('/\b(not|dont|don t|no longer|never|nisi|si|sitaki|siwezi)\s+'
                 . '(interested|interest|want|wish|join|register|enrol|enroll|kujiunga)\b/u', $t)) {
        return false;
    }
    if (preg_match('/\b(sitaki|sipendi|hapana asante)\b/u', $t)) { return false; }

    $patterns = [
        // --- English -------------------------------------------------------
        '/\bi(?:\s*a?m|\s*was)?\s+(?:very\s+|really\s+|quite\s+)?interested\b/u',
        '/\bam\s+interested\b/u',
        '/\bi\s+want\s+to\s+(?:join|register|enrol|enroll|apply|start|study|attend)\b/u',
        '/\bi\s+(?:would\s+like|wanna|wish)\s+to\s+(?:join|register|enrol|enroll|apply|start|attend)\b/u',
        '/\bhow\s+(?:can|do)\s+i\s+(?:join|register|enrol|enroll|apply|start|pay|sign\s*up)\b/u',
        '/\bhow\s+do\s+i\s+get\s+(?:started|in)\b/u',
        '/\b(?:sign|signing)\s*me\s*up\b/u',
        '/\bsign\s*up\b/u',
        '/\b(?:register|enrol|enroll)\s+me\b/u',
        '/\bi\s+want\s+(?:this|the)\s+(?:course|training|programme|program)\b/u',
        '/\bready\s+to\s+(?:join|register|enrol|enroll|start)\b/u',
        '/\bcount\s+me\s+in\b/u',
        // --- Swahili -------------------------------------------------------
        '/\bnataka\s+kujiunga\b/u',
        '/\bningependa\s+kujiunga\b/u',
        '/\bnataka\s+ku(?:jisajili|soma|anza)\b/u',
        '/\bningependa\s+ku(?:jisajili|soma|anza)\b/u',
        '/\bnataka\s+kusajili\b/u',
        '/\bnina(?:taka|penda)\s+kujiunga\b/u',
        '/\bnaomba\s+kujiunga\b/u',
        '/\bnitajiunga\s*je\b/u',
        '/\bnitajiungaje\b/u',
        '/\bnijiungeje\b/u',
        '/\bnina\s*hamu\s+ya\s+kujiunga\b/u',
    ];
    foreach ($patterns as $re) {
        if (preg_match($re, $t)) { return true; }
    }
    return false;
}

/** Has routing actually identified a topic to call the customer about? */
function wa_call_offer_topic_known($conv) {
    if (!is_array($conv)) { return false; }
    $rt = (string)($conv['ref_type'] ?? '');
    $ri = (int)($conv['ref_id'] ?? 0);
    if (in_array($rt, ['course', 'event'], true) && $ri > 0) { return true; }
    // A training programme is a legitimate topic too — an onsite enquiry that has
    // not yet bound to a country's event still belongs to a programme's reps.
    return (int)($conv['program_id'] ?? 0) > 0;
}

/**
 * May the AUTOMATIC path request permission, given the Phase 1.1 state?
 *
 * Stricter than the manual button on one point: a customer who told Meta "no" is
 * never asked again automatically. Phase 1.1 lets a rep try again within the
 * throttle, which is a judgement call a person can make and a bot should not.
 * Revoked and expired remain open, because reaching here at all means the
 * customer has just explicitly asked for something.
 *
 * @return array {allowed:bool, reason:string}
 */
function wa_call_offer_auto_allowed($derivedState, $throttle) {
    if ($derivedState === 'rejected') {
        return ['allowed' => false, 'reason' => 'declined_previously'];
    }
    if (empty($throttle['allowed'])) {
        // pending / granted / window_closed / throttled all land here.
        return ['allowed' => false, 'reason' => 'phase11_' . str_replace(' ', '_',
                strtolower(rtrim((string)($throttle['reason'] ?? 'blocked'), '.')))];
    }
    return ['allowed' => true, 'reason' => ''];
}

// =====================================================================
// Orchestration
// =====================================================================

/**
 * Called once, from the single point where an AI reply has just been delivered —
 * so it covers the immediate webhook reply AND the scheduled reply worker without
 * either needing to know about it.
 *
 * Returns a short status for the log. Never throws, never blocks the chat: a
 * failure here must leave the customer with a normal AI conversation.
 *
 * @param bool $aiFlag  the model's request_call_permission
 * @return array {sent:bool, skip:string, error:string}
 */
function wa_call_offer_maybe_request($conn, $conv, $inboundText, $aiFlag) {
    $out = ['sent' => false, 'skip' => '', 'error' => ''];

    // --- Gate 1: the model asked for it -----------------------------------
    // Absent means false, so an older prompt or a fallback raw-text reply simply
    // never triggers this. Backward compatible by construction.
    if (empty($aiFlag)) {
        // Report whether the customer's words WOULD have qualified. When the
        // detector agrees but the model never raised the flag, the prompt is the
        // problem; when neither agrees, the message simply was not a joining
        // request. Those need opposite fixes, so the log has to tell them apart.
        $out['skip'] = wa_call_interest_detected($inboundText)
                     ? 'ai_flag_false_but_words_qualify'
                     : 'ai_flag_false';
        return $out;
    }

    // --- Gate 2: the customer's own words ---------------------------------
    if (!wa_call_interest_detected($inboundText)) {
        $out['skip'] = 'no_explicit_interest';
        return $out;
    }

    if (!is_array($conv)) { $out['skip'] = 'no_conversation'; return $out; }
    if ((string)($conv['handler'] ?? 'ai') === 'human') { $out['skip'] = 'handler_human'; return $out; }
    if (!wa_call_offer_topic_known($conv)) { $out['skip'] = 'no_topic'; return $out; }

    $contactId = (int)($conv['contact_id'] ?? 0);
    $waId      = (string)($conv['wa_id'] ?? '');
    if ($contactId < 1 || $waId === '') { $out['skip'] = 'no_contact'; return $out; }

    // A customer who has opted out of our messages is not asked for anything.
    $opted = wa_call_fetch($conn, "SELECT opted_out FROM wa_contacts WHERE id = ? LIMIT 1",
                           'i', [$contactId]);
    if ($opted && (int)$opted['opted_out'] === 1) { $out['skip'] = 'opted_out'; return $out; }

    // --- Configuration: fail closed, exactly as the manual button does -----
    if (wa_call_unavailable_reason() !== '') { $out['skip'] = 'not_configured'; return $out; }

    // --- The number we would ask about must be the number we could dial ----
    if (!function_exists('wa_voice_e164')) { $out['skip'] = 'no_voice_helper'; return $out; }
    $e164 = wa_voice_e164($waId);
    if ($e164 === '') { $out['skip'] = 'invalid_number'; return $out; }

    // --- Phase 1.1 state: pending / granted / rejected / throttled ----------
    $status = wa_call_status($conn, $contactId, WA_CALL_PHONE_ID);
    $auto   = wa_call_offer_auto_allowed($status['derived']['state'], $status['throttle']);
    if (empty($auto['allowed'])) { $out['skip'] = $auto['reason']; return $out; }

    return wa_call_offer_do_request($conn, $contactId, $waId, $e164);
}

/**
 * Claim, send and explain — the part shared by every automated request.
 *
 * Split out so the forced calling-line request runs the IDENTICAL sequence as the
 * interest-driven one: same Phase 1.1 lease, same throttle, same 'api' attribution,
 * same rollback, and the same rule that the customer is only told once 360dialog
 * has accepted it.
 *
 * @return array {sent:bool, skip:string, error:string}
 */
function wa_call_offer_do_request($conn, $contactId, $waId, $e164) {
    $out = ['sent' => false, 'skip' => '', 'error' => ''];

    // --- Reuse Phase 1.1 end to end. actor_id NULL marks it automated. -----
    $claim = wa_call_claim_request($conn, $contactId, null, WA_CALL_PHONE_ID);
    if (empty($claim['ok'])) {
        // Lost a race with the rep's button or a second reply — correct outcome.
        $out['skip'] = 'claim_refused';
        return $out;
    }

    // Free inside an open 798 window, the approved template otherwise. Both leave
    // from the calling line.
    $send = wa_call_request_permission($conn, $contactId, $e164);
    if (empty($send['ok'])) {
        // Release the lease and record why, without consuming a throttle slot.
        wa_call_fail_request($conn, $contactId, null, $claim['previous'], $send['error'],
                             WA_CALL_PHONE_ID, 'api');
        // Deliberately silent to the customer: telling them a request was sent when
        // it was not is worse than saying nothing, and the chat continues normally.
        $out['error'] = wa_call_scrub((string)$send['error']);
        error_log('[wa-call-offer] request failed for contact ' . $contactId . ': ' . $out['error']);
        return $out;
    }

    // source='api', actor NULL — attributed at the point of record, so exactly ONE
    // 'requested' row exists. A second event to correct attribution would double the
    // throttle count, which reads its window from these rows.
    wa_call_confirm_request($conn, $contactId, null, $send['message_id'], WA_CALL_PHONE_ID, 'api');
    error_log('[wa-call-offer] permission requested via ' . (string)($send['route'] ?? '?')
            . ' for contact ' . $contactId);

    // --- Only now do we tell them, through the 796 conversation ------------
    $notice = wa_send_text($conn, $waId, WA_CALL_OFFER_NOTICE);
    if (empty($notice['ok'])) {
        // The request is genuinely out; the explanation is not. Log it — a customer
        // receiving an unexplained permission prompt is confusing but recoverable.
        error_log('[wa-call-offer] notice failed for contact ' . $contactId
                . ': ' . (string)($notice['error'] ?? 'unknown'));
    }
    $out['sent'] = true;
    return $out;
}

// =====================================================================
// Ready to Call
// =====================================================================

/**
 * SQL predicate: this conversation's customer has granted permission on the
 * calling line and is dialable right now.
 *
 * Derived entirely from Phase 1.1 state — no new column, no new table, and so a
 * grant obtained through the manual button qualifies exactly like an automated
 * one. The 24-hour pilot rule is applied here in SQL to match
 * wa_call_derive_state(), which is what the button itself uses.
 */
function wa_ready_to_call_sql($a = 'cv') {
    $pid = "'" . WA_CALL_PHONE_ID . "'";
    $win = (int)WA_CALL_WINDOW_TTL;
    return "EXISTS (SELECT 1 FROM wa_call_permissions rp
                     WHERE rp.contact_id = $a.contact_id
                       AND rp.business_phone_id = $pid
                       AND rp.status = 'granted'
                       AND rp.expires_at IS NOT NULL
                       AND rp.expires_at > NOW()
                       AND rp.responded_at IS NOT NULL
                       AND rp.responded_at > (NOW() - INTERVAL $win SECOND))";
}

/** Seconds left in the callable window, or NULL. Mirrors the countdown the
 *  Closing-soon tab already uses, so both tick from server time. */
function wa_ready_to_call_left_sql($a = 'cv') {
    $pid = "'" . WA_CALL_PHONE_ID . "'";
    $win = (int)WA_CALL_WINDOW_TTL;
    return "(SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(rp2.responded_at, INTERVAL $win SECOND))
               FROM wa_call_permissions rp2
              WHERE rp2.contact_id = $a.contact_id
                AND rp2.business_phone_id = $pid
                AND rp2.status = 'granted' LIMIT 1)";
}

/** When permission was granted, for the queue display. */
function wa_ready_to_call_granted_sql($a = 'cv') {
    $pid = "'" . WA_CALL_PHONE_ID . "'";
    return "(SELECT rp3.responded_at FROM wa_call_permissions rp3
              WHERE rp3.contact_id = $a.contact_id
                AND rp3.business_phone_id = $pid
                AND rp3.status = 'granted' LIMIT 1)";
}

/**
 * A first enquiry on the CALLING line always gets a permission request.
 *
 * Someone who writes to +254798009935 has gone to the calling number rather than
 * the enquiry number, which is intent enough on its own — so this deliberately
 * does NOT wait for the model's flag or for wa_call_interest_detected() to agree,
 * and does not require a course to have been identified yet. Any prior history on
 * the messaging line is irrelevant.
 *
 * It fires on their FIRST inbound on that line only. Every later message is
 * ignored here, so a conversation on the calling line does not re-ask; a repeat
 * would in any case be refused by the Phase 1.1 lease and throttle, but not asking
 * is better than being refused.
 *
 * What it does NOT bypass: the Phase 1.1 eligibility, lease, throttle and
 * configuration checks. Those exist because Meta counts the requests and the
 * customer only tolerates so many.
 *
 * A side benefit of the timing: they have just messaged that line, so its window
 * is open and the request goes by the free in-window route rather than a paid
 * template.
 *
 * @return array {sent:bool, skip:string, error:string}
 */
function wa_call_offer_force_on_calling_line($conn, $contactId, $waId, $channel) {
    $out = ['sent' => false, 'skip' => '', 'error' => ''];

    if ((string)$channel !== 'calling') { $out['skip'] = 'not_calling_line'; return $out; }

    $contactId = (int)$contactId;
    if ($contactId < 1 || trim((string)$waId) === '') { $out['skip'] = 'no_contact'; return $out; }

    // First message on this line only. The row for THIS message is already stored,
    // so the first one counts exactly 1.
    $n = wa_call_fetch($conn,
        "SELECT COUNT(*) AS n FROM wa_messages
          WHERE contact_id = ? AND direction = 'inbound' AND type <> 'note' AND channel = 'calling'",
        'i', [$contactId]);
    if ((int)($n['n'] ?? 0) !== 1) { $out['skip'] = 'not_first_on_line'; return $out; }

    $opted = wa_call_fetch($conn, "SELECT opted_out FROM wa_contacts WHERE id = ? LIMIT 1",
                           'i', [$contactId]);
    if ($opted && (int)$opted['opted_out'] === 1) { $out['skip'] = 'opted_out'; return $out; }

    if (wa_call_unavailable_reason() !== '') { $out['skip'] = 'not_configured'; return $out; }

    if (!function_exists('wa_voice_e164')) { $out['skip'] = 'no_voice_helper'; return $out; }
    $e164 = wa_voice_e164($waId);
    if ($e164 === '') { $out['skip'] = 'invalid_number'; return $out; }

    $status = wa_call_status($conn, $contactId, WA_CALL_PHONE_ID);
    $auto   = wa_call_offer_auto_allowed($status['derived']['state'], $status['throttle']);
    if (empty($auto['allowed'])) { $out['skip'] = $auto['reason']; return $out; }

    return wa_call_offer_do_request($conn, $contactId, $waId, $e164);
}
