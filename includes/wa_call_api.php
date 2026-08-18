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

/** Headers for a calling-channel request, or [] when the key is missing. */
function wa_call_api_headers() {
    $s = wa_call_secrets();
    if ($s['key'] === '') { return []; }
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
    $headers = wa_call_api_headers();
    if (!$headers) {
        return ['ok' => false, 'message_id' => '', 'error' => 'Calling not configured.', 'status' => 0];
    }

    $s = wa_call_secrets();
    $payload = wa_call_template_payload($to, $s['template'], $s['lang'], $components);

    $res    = wa_call_http_post(rtrim(WA_CALL_API_URL, '/') . '/messages', $headers, $payload);
    $status = (int)($res['status'] ?? 0);
    $body   = is_array($res['body'] ?? null) ? $res['body'] : [];

    if ($status >= 200 && $status < 300) {
        $mid = (string)($body['messages'][0]['id'] ?? '');
        return ['ok' => true, 'message_id' => $mid, 'error' => '', 'status' => $status];
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
