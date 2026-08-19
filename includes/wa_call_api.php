<?php
/**
 * 360dialog API for the CALLING channel (+254798009935) — Phase 1.1.
 *
 * Only one operation in this pilot: send the approved CALL_PERMISSION_REQUEST
 * template so the customer can grant permission to be called.
 *
 * Why template-only: the direct permission endpoint needs an open customer-service
 * window on 798, and today's customers all enquire on 796, so they have no 798
 * window at all. A template is the only thing that reaches them.
 *
 * Every request here uses the 798 key, which is loaded from outside the document
 * root. There is NO fallback to WA_DIALOG_KEY — sending with the messaging key
 * would ask the customer for permission to be called by a line we will not call
 * from, and would consume one of their two weekly requests for nothing.
 *
 * HTTP is done by wa_call_http_post() below rather than the module's shared
 * wa_http_post(): this request carries a credential for a channel that can place
 * calls, so its TLS posture is stated explicitly here instead of inherited.
 */

require_once __DIR__ . '/wa_call_config.php';

/** Connection and overall timeouts for calling-channel requests (seconds). */
if (!defined('WA_CALL_CONNECT_TIMEOUT')) { define('WA_CALL_CONNECT_TIMEOUT', 8);  }
if (!defined('WA_CALL_TIMEOUT'))         { define('WA_CALL_TIMEOUT',         25); }

/**
 * Is the in-window "direct" permission request trusted?
 *
 * OFF by default. It was enabled on the assumption that GET /calling/permissions/
 * creates a request when the customer-service window is open. Live traffic showed
 * otherwise: it answers 200 with no message id — it REPORTS permission state, it
 * does not ask for it. A customer was told a request had been sent and never
 * received one.
 *
 * Define WA_CALL_DIRECT_ENABLED once that endpoint is confirmed to create a
 * request; the message-id guard below keeps it honest either way.
 */
if (!defined('WA_CALL_DIRECT_ENABLED')) { define('WA_CALL_DIRECT_ENABLED', false); }

/** Largest response body we will read from 360dialog (bytes). */
if (!defined('WA_CALL_MAX_RESPONSE'))    { define('WA_CALL_MAX_RESPONSE', 256 * 1024); }

/**
 * POST JSON to the calling channel with an explicit security posture.
 *
 * Deliberately NOT wa_http_post(): that helper relies on cURL's default TLS
 * behaviour, which is correct today but is not stated anywhere, and this request
 * carries a credential for a channel that can place calls. Certificate and
 * hostname verification are pinned on here so that a future change to a shared
 * helper — or a permissive php.ini — cannot silently downgrade this call to an
 * unverified connection.
 *
 * @return array {status:int, body:array}
 */
function wa_call_http_post($url, $headers, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        // TLS: verify the certificate chain AND that the hostname matches.
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,     // a redirect must not carry the key elsewhere
        // Both timeouts: a connect timeout alone still allows an open socket to
        // hang for ever, which would pin the rep's request thread.
        CURLOPT_CONNECTTIMEOUT => (int)WA_CALL_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT        => (int)WA_CALL_TIMEOUT,
    ]);
    // Restrict to HTTPS. The modern constant needs libcurl >= 7.85, so fall back
    // rather than fataling on an older build.
    if (defined('CURLOPT_PROTOCOLS_STR')) {
        @curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'https');
    } elseif (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
        @curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
    }
    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'body' => ['error' => ['message' => 'Connection failed: ' . $err]]];
    }
    if (strlen($raw) > WA_CALL_MAX_RESPONSE) { $raw = substr($raw, 0, WA_CALL_MAX_RESPONSE); }
    $body = json_decode((string)$raw, true);
    return ['status' => $status, 'body' => is_array($body) ? $body : ['raw' => $raw]];
}

/**
 * The exact /messages payload for a permission-request template. Pure, so the
 * approved template's shape can be asserted in tests without a live channel.
 *
 * With no body variables the template carries NO 'components' key at all —
 * sending an empty components array is a 400 from Meta.
 */
function wa_call_template_payload($toE164, $name, $lang, $components = null) {
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => (string)$toE164,
        'type'              => 'template',
        'template'          => [
            'name'     => (string)$name,
            'language' => ['code' => (string)$lang],
        ],
    ];
    if (is_array($components) && $components !== []) {
        $payload['template']['components'] = $components;
    }
    return $payload;
}

/**
 * Why the calling channel cannot be used right now, or '' when it can.
 *
 * A permission request MUST leave from +254798009935. If it went from the
 * messaging line the customer would be asked for permission to be called by a
 * number we will never call from, they would grant it against the wrong number,
 * and every subsequent call would still be refused — while one of only two
 * requests allowed in seven days had been spent. So this refuses rather than
 * sends whenever it cannot prove which line it is on.
 */
function wa_call_channel_block_reason() {
    $s   = wa_call_secrets();
    $key = trim((string)$s['key']);

    if ($key === '')                       { return 'calling key not configured'; }
    if (strpos($key, 'YOUR_') === 0)       { return 'calling key is still a placeholder'; }

    // The two channels must be genuinely different credentials. If they are equal,
    // the "calling" key is really the messaging one and the request would go out
    // from the wrong number — silently, and looking entirely successful.
    if (defined('WA_DIALOG_KEY') && trim((string)WA_DIALOG_KEY) !== ''
        && hash_equals((string)WA_DIALOG_KEY, $key)) {
        return 'calling key is identical to the messaging key';
    }

    if (!defined('WA_CALL_PHONE_ID') || trim((string)WA_CALL_PHONE_ID) === '') {
        return 'calling phone-number id not configured';
    }
    if (!defined('WA_CALL_API_URL') || stripos((string)WA_CALL_API_URL, 'https://') !== 0) {
        return 'calling API url is not https';
    }
    return '';
}

/** Headers for a calling-channel request, or [] when it cannot be used. */
function wa_call_api_headers() {
    if (wa_call_channel_block_reason() !== '') { return []; }
    $s = wa_call_secrets();
    return ['Content-Type: application/json', 'D360-API-KEY: ' . $s['key']];
}

/**
 * Send the CALL_PERMISSION_REQUEST template from 798 to one customer.
 *
 * @param string $toE164  digits-only international number, no plus
 * @return array {ok:bool, message_id:string, error:string, status:int}
 *
 * Fails closed and explicitly: an unconfigured channel or an unapproved template
 * returns ok=false with the reason, and never attempts a send.
 */
function wa_call_send_permission_template($toE164, $components = null) {
    $to = preg_replace('/\D+/', '', (string)$toE164);
    if ($to === '') {
        return ['ok' => false, 'message_id' => '', 'error' => 'No valid destination number.', 'status' => 0];
    }
    $reason = wa_call_unavailable_reason();
    if ($reason !== '') {
        return ['ok' => false, 'message_id' => '', 'error' => $reason, 'status' => 0];
    }
    // Hard gate: never send unless we can prove this is the calling line.
    $blocked = wa_call_channel_block_reason();
    if ($blocked !== '') {
        error_log('[wa-call] refused to send a permission request: ' . $blocked);
        return ['ok' => false, 'message_id' => '',
                'error' => 'Calling line unavailable: ' . $blocked, 'status' => 0];
    }
    $headers = wa_call_api_headers();
    if (!$headers) {
        return ['ok' => false, 'message_id' => '', 'error' => 'Calling not configured.', 'status' => 0];
    }

    $s = wa_call_secrets();
    $payload = wa_call_template_payload($to, $s['template'], $s['lang'], $components);

    $res    = wa_call_http_post(rtrim(WA_CALL_API_URL, '/') . '/messages', $headers, $payload);
    $status = (int)($res['status'] ?? 0);
    $body   = is_array($res['body'] ?? null) ? $res['body'] : [];

    $mid = (string)($body['messages'][0]['id'] ?? '');
    if ($status >= 200 && $status < 300 && $mid !== '') {
        return ['ok' => true, 'message_id' => $mid, 'error' => '', 'status' => $status];
    }
    if ($status >= 200 && $status < 300 && $mid === '') {
        // Same rule as the direct route: accepted but nothing created. Do not tell
        // the customer a request is on its way.
        return ['ok' => false, 'message_id' => '',
                'error' => 'accepted but no message id returned', 'status' => $status];
    }

    $err = (string)($body['error']['message']
                 ?? $body['meta']['developer_message']
                 ?? $body['raw']
                 ?? ('HTTP ' . $status));
    // Scrub before the message can reach a flash, a log line or last_error: some
    // 360dialog failures quote the request, headers included.
    return ['ok' => false, 'message_id' => '',
            'error' => mb_substr(wa_call_scrub($err), 0, 255), 'status' => $status];
}

/**
 * Ask for call permission, choosing the cheapest route that will actually work.
 *
 * Now that the calling line receives messages, a customer who has written to it
 * inside the last 24 hours has an OPEN customer-service window there — and a
 * request sent inside that window is a session message, which costs nothing. The
 * approved template costs money on every send and exists for the case that window
 * is shut, which is still most customers, because they enquire on the messaging
 * line.
 *
 * Both routes leave from +254798009935 and both go through the hard gate above.
 *
 * The direct route is attempted only when the window is open, and ONLY its own
 * explicit success is trusted: anything ambiguous falls through to the template
 * rather than reporting a request that may never have been sent. A customer left
 * waiting for a prompt that never arrives is worse than the cost of a template.
 *
 * @return array {ok, message_id, error, status, route}
 */
function wa_call_request_permission($conn, $contactId, $toE164) {
    $windowOpen = false;
    if (function_exists('wa_channel_within_window')) {
        $windowOpen = wa_channel_within_window($conn, (int)$contactId, 'calling', null);
    }

    if ($windowOpen && WA_CALL_DIRECT_ENABLED) {
        $direct = wa_call_permission_direct($toE164);
        if (!empty($direct['ok'])) {
            $direct['route'] = 'direct_free';
            return $direct;
        }
        // Not an error worth surfacing — the template is the expected fallback.
        error_log('[wa-call] direct request unavailable (' . (string)$direct['error']
                . '), falling back to the template');
    }

    $tpl = wa_call_send_permission_template($toE164);
    $tpl['route'] = $windowOpen ? 'template_after_direct' : 'template';
    return $tpl;
}

/**
 * The in-window request. Requires an open customer-service window on the calling
 * line; 360dialog rejects it otherwise, which is exactly the signal we fall back on.
 *
 * Treated as successful ONLY on a 2xx that does not report an error. The endpoint's
 * exact semantics have not been confirmed against a live channel — it may create a
 * request or merely report existing state — so the caller must never assume a
 * prompt reached the customer on anything less than an explicit success.
 */
function wa_call_permission_direct($toE164) {
    $to = preg_replace('/\D+/', '', (string)$toE164);
    if ($to === '') {
        return ['ok' => false, 'message_id' => '', 'error' => 'No valid destination number.', 'status' => 0];
    }
    $blocked = wa_call_channel_block_reason();
    if ($blocked !== '') {
        return ['ok' => false, 'message_id' => '', 'error' => $blocked, 'status' => 0];
    }
    if (!function_exists('wa_http_get')) {
        return ['ok' => false, 'message_id' => '', 'error' => 'HTTP helper unavailable.', 'status' => 0];
    }

    $s   = wa_call_secrets();
    $url = rtrim(WA_CALL_API_URL, '/') . '/calling/permissions/' . rawurlencode($to);
    $res = wa_http_get($url, (int)WA_CALL_TIMEOUT, ['D360-API-KEY: ' . $s['key']]);

    $status = (int)($res['status'] ?? 0);
    $body   = is_array($res['body'] ?? null) ? $res['body'] : [];
    $wamid  = (string)($body['messages'][0]['id'] ?? '');

    // A 2xx is NOT proof. This endpoint answers 200 while merely reporting the
    // customer's current permission state, and that is what made us tell someone a
    // request had been sent when nothing had been. A request creates a message, so
    // a message id is the only evidence worth acting on; without one we fall back
    // to the template, which does return one.
    $ok = ($status >= 200 && $status < 300) && empty($body['error']) && $wamid !== '';

    if ($ok) {
        return ['ok' => true, 'message_id' => $wamid, 'error' => '', 'status' => $status];
    }
    if ($status >= 200 && $status < 300 && $wamid === '') {
        return ['ok' => false, 'message_id' => '',
                'error' => 'no message id returned — the endpoint reported state rather than sending a request',
                'status' => $status];
    }
    $err = (string)($body['error']['message'] ?? $body['raw'] ?? ('HTTP ' . $status));
    return ['ok' => false, 'message_id' => '',
            'error' => mb_substr(wa_call_scrub($err), 0, 255), 'status' => $status];
}
