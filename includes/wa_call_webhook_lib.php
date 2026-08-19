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
 * Decide what to do with a decoded webhook body. Pure — no database, no I/O.
 *
 * The payload is documented at the ROOT of the body, not nested in
 * entry/changes/value the way message webhooks are:
 *
 *   { "event":"call_permission_status", "status":"GRANTED",
 *     "waba_id":"…", "recipient":"2547…" }
 *
 * @return array {action:'apply'|'ignore', code:int, reason:string,
 *                status:string, recipient:string, waba_id:string}
 */
function wa_call_webhook_classify($payload, $expectedWabaId) {
    $out = ['action' => 'ignore', 'code' => 200, 'reason' => '',
            'status' => '', 'recipient' => '', 'waba_id' => ''];

    if (!is_array($payload)) { $out['reason'] = 'unparsable_body'; return $out; }

    $event = strtolower(trim((string)($payload['event'] ?? '')));
    if ($event !== 'call_permission_status') {
        // Ordinary call-status events (ringing / answered / ended) arrive here too.
        // They are not permission events and must never be mistaken for one.
        $out['reason'] = 'not_a_permission_event:' . ($event !== '' ? $event : 'none');
        return $out;
    }

    $waba = trim((string)($payload['waba_id'] ?? ''));
    $out['waba_id'] = $waba;
    if ($waba === '' || !hash_equals((string)$expectedWabaId, $waba)) {
        // The reason names the value received on purpose: a silent drop here is the
        // failure mode that leaves every contact pending for ever with nothing in
        // the logs to explain why.
        $out['reason'] = 'waba_mismatch:' . ($waba !== '' ? $waba : 'none');
        return $out;
    }

    $status = strtoupper(trim((string)($payload['status'] ?? '')));
    if (wa_call_map_status($status) === '') {
        $out['reason'] = 'unknown_status:' . ($status !== '' ? $status : 'none');
        return $out;
    }

    // wa_voice_e164() refuses anything that is not a plain telephone number, so a
    // hostile "recipient" can never become a database lookup key.
    $recipient = wa_voice_e164($payload['recipient'] ?? '');
    if ($recipient === '') { $out['reason'] = 'bad_recipient'; return $out; }

    $out['action']    = 'apply';
    $out['status']    = $status;
    $out['recipient'] = $recipient;
    return $out;
}
