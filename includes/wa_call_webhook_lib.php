<?php
/**
 * Pure request-parsing for the calling webhook (+254798009935).
 *
 * Separated from wa_call_webhook.php so the decision logic can be tested without a
 * database connection, an HTTP request or a live channel. The endpoint file does
 * the I/O; everything that decides WHETHER to act lives here and is a pure
 * function of its arguments.
 *
 * Requires wa_voice.php (wa_voice_e164) and wa_call_permissions.php
 * (wa_call_map_status) — both already pure.
 */

/** Read a request header across SAPIs (getallheaders() is not always available). */
function wa_call_webhook_header($name, $server = null) {
    $srv = is_array($server) ? $server : $_SERVER;
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', (string)$name));
    if (isset($srv[$key])) { return (string)$srv[$key]; }
    if ($server === null && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, (string)$name) === 0) { return (string)$v; }
        }
    }
    return '';
}

/**
 * Authenticate a webhook request. Pure — pass in the arrays rather than reading
 * superglobals, so every branch is testable.
 *
 * Two accepted forms, header first:
 *
 *   1. X-Vantage-Call-Token: <secret>      preferred
 *   2. ?token=<secret>                     accepted
 *
 * The query form exists because 360dialog's channel webhook configuration does not
 * always allow a custom header, and it is the shape the messaging webhook already
 * uses. It is genuinely weaker: a URL lands in web-server access logs, proxy logs
 * and anything that records request lines, so the secret is written to disk on
 * every delivery. Prefer the header where the console allows it, and treat a
 * query-parameter token as rotatable rather than long-lived.
 *
 * @return bool
 */
function wa_call_webhook_authenticate($expected, $server = null, $get = null) {
    $expected = (string)$expected;
    // An unset secret must never authenticate anything, and must not be allowed to
    // match an equally empty submission.
    if ($expected === '') { return false; }

    $header = wa_call_webhook_header(
        defined('WA_CALL_WEBHOOK_HEADER') ? WA_CALL_WEBHOOK_HEADER : 'X-Vantage-Call-Token',
        $server
    );
    if ($header !== '' && hash_equals($expected, $header)) { return true; }

    $q = is_array($get) ? $get : $_GET;
    $query = isset($q['token']) ? (string)$q['token'] : '';
    if ($query !== '' && hash_equals($expected, $query)) { return true; }

    return false;
}

/**
 * Find a call-permission decision anywhere inside a `value` object.
 *
 * The exact key is not something we have documentation for, and the first live
 * capture showed only delivery receipts, so this matches on SHAPE rather than on
 * one guessed field name: any candidate container holding a status that maps to
 * GRANTED / REJECTED / REVOKED. A delivery receipt ("sent", "delivered") maps to
 * nothing and is therefore skipped, so ordinary message traffic cannot be
 * mistaken for a permission decision.
 *
 * @return array|null {status, recipient} or null when there is no decision here
 */
function wa_call_find_permission($value) {
    if (!is_array($value)) { return null; }

    // ---- The shape the live channel actually uses --------------------------
    // A decision arrives as an INBOUND INTERACTIVE MESSAGE, not as a status event:
    //
    //   value.messages[].type                            = "interactive"
    //   value.messages[].interactive.type                = "call_permission_reply"
    //   value.messages[].interactive.call_permission_reply
    //        .response              "accept" | "reject"
    //        .is_permanent          true when the grant does not lapse
    //        .expiration_timestamp  unix seconds, present when it does
    //
    // Handled explicitly because it is confirmed, rather than left to the
    // shape-matching fallback below.
    foreach ($value['messages'] ?? [] as $msg) {
        if (!is_array($msg)) { continue; }
        $inter = $msg['interactive'] ?? null;
        if (!is_array($inter) || ($inter['type'] ?? '') !== 'call_permission_reply') { continue; }
        $reply = $inter['call_permission_reply'] ?? null;
        if (!is_array($reply)) { continue; }

        $response = (string)($reply['response'] ?? '');
        if (wa_call_map_status($response) === '') { continue; }

        // The customer is the sender here — this is their reply to us, so 'from'
        // is the number we want, not the business number in 'context'.
        $recipient = (string)($msg['from'] ?? '');
        if ($recipient === '' && isset($value['contacts'][0]['wa_id'])) {
            $recipient = (string)$value['contacts'][0]['wa_id'];
        }

        $expires = null;
        if (!empty($reply['expiration_timestamp'])) {
            $expires = (int)$reply['expiration_timestamp'];
        }

        return ['status'      => strtoupper($response),
                'recipient'   => $recipient,
                'expires_at'  => $expires,
                'permanent'   => !empty($reply['is_permanent'])];
    }

    // ---- Fallback: a status-shaped decision in some other container ---------
    $nodes = [];
    foreach (['call_permission_update', 'call_permission_updates', 'call_permission',
              'call_permissions', 'permissions', 'user_preferences', 'calls'] as $k) {
        if (!isset($value[$k])) { continue; }
        $c = $value[$k];
        if (is_array($c) && array_key_exists(0, $c)) {
            foreach ($c as $item) { $nodes[] = $item; }   // a list of updates
        } else {
            $nodes[] = $c;                                 // a single object
        }
    }
    $nodes[] = $value;   // in case the fields sit directly on `value`

    foreach ($nodes as $node) {
        if (!is_array($node)) { continue; }
        foreach (['call_permission_status', 'permission_status', 'status',
                  'response', 'decision'] as $sk) {
            if (!isset($node[$sk]) || !is_scalar($node[$sk])) { continue; }
            if (wa_call_map_status((string)$node[$sk]) === '') { continue; }

            $recipient = '';
            foreach (['recipient', 'wa_id', 'user_wa_id', 'recipient_id', 'from'] as $rk) {
                if (isset($node[$rk]) && is_scalar($node[$rk])) {
                    $recipient = (string)$node[$rk];
                    break;
                }
            }
            // Fall back to the envelope's contacts list, which carries the customer
            // on every delivery we have actually observed.
            if ($recipient === '' && isset($value['contacts'][0]['wa_id'])) {
                $recipient = (string)$value['contacts'][0]['wa_id'];
            }
            return ['status' => strtoupper(trim((string)$node[$sk])), 'recipient' => $recipient,
                    'expires_at' => null, 'permanent' => false];
        }
    }
    return null;
}

/**
 * Decide what to do with a decoded webhook body. Pure — no database, no I/O.
 *
 * Handles the standard Meta envelope, which is what 360dialog actually delivers:
 *
 *   { "object":"whatsapp_business_account",
 *     "entry":[{ "id":"<WABA>", "changes":[{ "field":"messages",
 *                "value":{ "metadata":{"phone_number_id":"..."}, ... } }] }] }
 *
 * A root-level {event,status,waba_id,recipient} payload is still accepted, since
 * that is what the integration notes described and it costs nothing to keep.
 *
 * @return array {action:'apply'|'ignore', code:int, reason:string,
 *                status:string, recipient:string, waba_id:string}
 */
function wa_call_webhook_classify($payload, $expectedWabaId, $expectedPhoneId = null) {
    $out = ['action' => 'ignore', 'code' => 200, 'reason' => '',
            'status' => '', 'recipient' => '', 'waba_id' => '', 'expires_at' => null];

    if (!is_array($payload)) { $out['reason'] = 'unparsable_body'; return $out; }

    // ---- Form 1: root-level payload -----------------------------------------
    if (isset($payload['event'])) {
        $event = strtolower(trim((string)$payload['event']));
        if ($event !== 'call_permission_status') {
            $out['reason'] = 'not_a_permission_event:' . ($event !== '' ? $event : 'none');
            return $out;
        }
        $waba = trim((string)($payload['waba_id'] ?? ''));
        $out['waba_id'] = $waba;
        if ($waba === '' || !hash_equals((string)$expectedWabaId, $waba)) {
            $out['reason'] = 'waba_mismatch:' . ($waba !== '' ? $waba : 'none');
            return $out;
        }
        return wa_call_webhook_finish($out, (string)($payload['status'] ?? ''),
                                      $payload['recipient'] ?? '');
    }

    // ---- Form 2: the Meta envelope ------------------------------------------
    if (!isset($payload['entry']) || !is_array($payload['entry'])) {
        $out['reason'] = 'no_entry:' . implode(',', array_slice(array_keys($payload), 0, 6));
        return $out;
    }

    $seen = [];
    foreach ($payload['entry'] as $entry) {
        if (!is_array($entry)) { continue; }
        $waba = trim((string)($entry['id'] ?? ''));
        $out['waba_id'] = $waba;

        foreach ($entry['changes'] ?? [] as $change) {
            if (!is_array($change)) { continue; }
            $field = (string)($change['field'] ?? '');
            $value = is_array($change['value'] ?? null) ? $change['value'] : [];
            $phoneId = (string)($value['metadata']['phone_number_id'] ?? '');

            // Record what arrived, so an unrecognised event names its own shape in
            // the log instead of leaving us guessing again.
            $seen[] = $field . '{' . implode(',', array_slice(array_keys($value), 0, 8)) . '}';

            $perm = wa_call_find_permission($value);
            if ($perm === null) { continue; }   // ordinary traffic: statuses, messages

            // Only guard the number once we know this IS a permission decision, so
            // the reason we report is about the decision and not about routine
            // delivery receipts for some other number.
            if ($waba === '' || !hash_equals((string)$expectedWabaId, $waba)) {
                $out['reason'] = 'waba_mismatch:' . ($waba !== '' ? $waba : 'none');
                return $out;
            }
            if ($expectedPhoneId !== null && $phoneId !== ''
                && !hash_equals((string)$expectedPhoneId, $phoneId)) {
                $out['reason'] = 'phone_id_mismatch:' . $phoneId;
                return $out;
            }
            return wa_call_webhook_finish($out, $perm['status'], $perm['recipient'],
                                          $perm['expires_at'] ?? null);
        }
    }

    $out['reason'] = 'no_permission_in_payload:' . implode('|', array_slice($seen, 0, 4));
    return $out;
}

/** Shared tail: validate the status and the customer number. */
function wa_call_webhook_finish($out, $status, $recipient, $expiresAt = null) {
    $status = strtoupper(trim((string)$status));
    if (wa_call_map_status($status) === '') {
        $out['reason'] = 'unknown_status:' . ($status !== '' ? $status : 'none');
        return $out;
    }
    // wa_voice_e164() refuses anything that is not a plain telephone number, so a
    // hostile "recipient" can never become a database lookup key.
    $e164 = wa_voice_e164($recipient);
    if ($e164 === '') { $out['reason'] = 'bad_recipient'; return $out; }

    $out['action']     = 'apply';
    $out['status']     = $status;
    $out['recipient']  = $e164;
    $out['expires_at'] = $expiresAt;
    return $out;
}
