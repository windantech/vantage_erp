<?php
/**
 * Voice CRM Context API — Phase 2.1A. READ ONLY.
 *
 * Public URL:  https://vantageafricaleaders.com/admin/wa_voice_api.php
 * (the repository root is deployed to public_html/admin)
 *
 * Machine-to-machine only. Called by the OpenAI Realtime voice application on
 * +254798009935 so a caller is recognised and answered from the same knowledge the
 * WhatsApp AI uses. There is no session, no cookie, no browser and no CORS: a page
 * in a browser must never be able to reach this, and nothing here renders HTML.
 *
 * Three actions, all reads:
 *
 *   get_caller_context     who is calling, what they are interested in, who owns
 *                          them, and at most six recent conversation turns
 *   search_programmes      match spoken words to courses / events / programmes
 *   get_programme_details  the approved knowledge for one of them
 *
 * WHAT THIS ENDPOINT WILL NOT DO. It does not update contacts, conversations,
 * assignments, interests, messages, enrolments, routing, knowledge, call
 * permissions or follow-up state. The only rows it writes are its own two security
 * tables — wa_voice_nonces and wa_voice_rate — and neither holds customer data.
 *
 * WHY IT IS GUARDED THIS HEAVILY. Every other credential in this system lets the
 * holder DO something: send a message, request call permission. This one lets the
 * holder READ — a named customer's conversation history, one telephone number at a
 * time. That is the shape of a data-extraction tool, so the controls are sized for
 * that rather than for the traffic: HMAC with a nonce, a tight per-phone rate
 * limit, and a log line for every lookup.
 *
 * Response contract:
 *   200  the action succeeded. An unrecognised caller is a 200 with
 *        {"ok":true,"matched":false} — not an error; the assistant carries on
 *        without personalisation.
 *   400  bad_request                unreadable JSON, unknown action, bad argument
 *   401  unauthorized               any authentication failure, including replay
 *   403  forbidden                  not over TLS
 *   405  method_not_allowed
 *   413  request_too_large
 *   415  unsupported_media_type
 *   429  rate_limited
 *   503  crm_unavailable            database unreachable, or the dedicated voice
 *                                   account is not configured. Never a partial answer.
 *   503  schema_unavailable         wa_voice_nonces / wa_voice_rate have not been
 *                                   created. Run the Phase 2.1A deployment SQL.
 *
 * NO DDL, EVER. Nothing on this request path issues CREATE TABLE, ALTER TABLE,
 * DROP or TRUNCATE, and nothing calls a schema-ensure helper. The two security
 * tables are created once by an administrator; the endpoint checks they exist and
 * refuses to serve if they do not. The database account it connects as is granted
 * no CREATE, ALTER, DROP or INDEX privilege, so that is enforced by MySQL rather
 * than promised by PHP.
 *
 * Client errors are deliberately uninformative. "unauthorized" covers a wrong key,
 * a stale clock, a replayed nonce and a forged signature alike; which of those it
 * was goes to the server's error log and nowhere else.
 */

require_once __DIR__ . '/includes/wa_config.php';        // DB + module constants
require_once __DIR__ . '/includes/wa_functions.php';     // read-only helpers
require_once __DIR__ . '/includes/wa_voice.php';         // wa_voice_e164()
require_once __DIR__ . '/includes/wa_voice_secrets.php';
require_once __DIR__ . '/includes/wa_voice_api_lib.php';
require_once __DIR__ . '/includes/wa_voice_context.php';

// =====================================================================
// Output
// =====================================================================

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
// No Access-Control-Allow-* headers, anywhere, deliberately. A browser has no
// business calling this, and adding CORS would be the difference between a
// server-to-server endpoint and one reachable from any page a customer visits.

/** Emit the whole response at once and stop.
 *
 *  Built and encoded in a single call so a failure part-way through assembling an
 *  answer cannot leave half a customer's record already written to the socket. */
function wa_voice_out($status, array $body) {
    http_response_code((int)$status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Emit one of the standard errors. */
function wa_voice_fail($status, $code) {
    $e = wa_voice_error($status, $code);
    wa_voice_out($e['status'], $e['body']);
}

/**
 * One log line per request.
 *
 * Carries the key id, the action, the outcome and a MASKED number. Never the
 * request body, the signature, the nonce, a secret or a whole telephone number —
 * an access log that accumulates customer numbers is the same disclosure this
 * endpoint's rate limiter exists to prevent, arriving by a different route.
 */
function wa_voice_log($outcome, array $fields = []) {
    $parts = ['outcome=' . $outcome];
    foreach ($fields as $k => $v) {
        if ($v === '' || $v === null) { continue; }
        $parts[] = $k . '=' . $v;
    }
    error_log('[wa-voice] ' . implode(' ', $parts));
}

// =====================================================================
// Transport-level checks — before anything expensive, and before the database
// =====================================================================

$server = $_SERVER;
$now    = time();

if (($server['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    wa_voice_fail(405, 'method_not_allowed');
}

// TLS. The voice server is code we control, so there is no reason to accept plain
// HTTP; the signature would still be valid but the response — a customer's
// conversation — would cross the network in clear.
if (WA_VOICE_REQUIRE_HTTPS) {
    $https = (!empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off');
    if (!$https && WA_VOICE_TRUST_PROXY) {
        $https = strtolower((string)($server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
    if (!$https) {
        wa_voice_log('forbidden', ['why' => 'not_https']);
        wa_voice_fail(403, 'forbidden');
    }
}

// JSON only. Accepting a form encoding would mean the signed bytes and the parsed
// arguments could be assembled differently by two parties.
$ctype = strtolower(trim((string)($server['CONTENT_TYPE'] ?? '')));
if (strpos($ctype, 'application/json') !== 0) {
    wa_voice_fail(415, 'unsupported_media_type');
}

// Size cap, checked twice: the declared length first so an oversized body is
// refused before it is read, then the bytes actually read, because Content-Length
// is a claim and not a fact.
if ((int)($server['CONTENT_LENGTH'] ?? 0) > WA_VOICE_MAX_BODY) {
    wa_voice_fail(413, 'request_too_large');
}
$rawBody = (string)file_get_contents('php://input', false, null, 0, WA_VOICE_MAX_BODY + 1);
if (strlen($rawBody) > WA_VOICE_MAX_BODY) {
    wa_voice_fail(413, 'request_too_large');
}

// =====================================================================
// Authentication — no database access happens before this passes
// =====================================================================

$secrets = wa_voice_secrets();
if (!wa_voice_configured()) {
    // Fail closed. An endpoint with no secret must be shut, not open.
    wa_voice_log('unauthorized', ['why' => 'not_configured']);
    wa_voice_fail(401, 'unauthorized');
}

$auth = wa_voice_authenticate($secrets['keys'], $server, $rawBody, $now, wa_voice_signing_path());
if (empty($auth['ok'])) {
    wa_voice_log('unauthorized', ['why' => $auth['reason']]);
    wa_voice_fail(401, 'unauthorized');
}
$keyId = (string)$auth['key_id'];

// =====================================================================
// Request body
// =====================================================================

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    wa_voice_log('bad_request', ['key' => $keyId, 'why' => 'json']);
    wa_voice_fail(400, 'bad_request');
}

$action = isset($payload['action']) && is_string($payload['action']) ? $payload['action'] : '';
$callId = wa_voice_clean_call_id($payload['call_id'] ?? '');

// =====================================================================
// Database
// =====================================================================

// This endpoint uses its OWN restricted MySQL account — never WA_DB_USER, and
// never includes/wa_db.php (which connects as the application and exits with a
// bare 500 on failure). The credentials live only in the server-only secrets file
// or the environment, outside Git.
//
// There is NO FALLBACK. If the restricted account is missing, incomplete, still a
// placeholder, or turns out to be the application's own credential, the request
// is refused. Falling back to the powerful account when the restricted one is
// absent would quietly undo the least-privilege design at precisely the moment
// nobody is looking at it.
$dbReason = wa_voice_db_reason();
if ($dbReason !== '') {
    wa_voice_log('crm_unavailable', ['key' => $keyId, 'action' => $action, 'why' => 'db_' . $dbReason]);
    wa_voice_fail(503, 'crm_unavailable');
}
$dbCfg = wa_voice_db_config();

$conn = null;
try {
    $conn = @mysqli_connect($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['name']);
} catch (Throwable $e) {
    // The message can quote the DSN, so it is not logged — only the fact.
    $conn = null;
}
if (!$conn) {
    wa_voice_log('crm_unavailable', ['key' => $keyId, 'action' => $action, 'why' => 'connect']);
    wa_voice_fail(503, 'crm_unavailable');
}
@mysqli_set_charset($conn, 'utf8mb4');
// Align PHP and MySQL on Africa/Nairobi so a stored DATETIME and time() can be
// compared. A session variable, not a data write.
wa_use_nairobi_time($conn);

// The two security tables are created ONCE by an administrator, never by a
// request. If either is absent the endpoint refuses to serve rather than
// attempting to create it: a public request path that can create tables is a
// request path that needs CREATE privilege, and this account deliberately has
// none. Answering 503 makes a missed deployment step loud instead of silently
// disabling replay protection.
try {
    if (!wa_voice_schema_available($conn)) {
        wa_voice_log('schema_unavailable', ['key' => $keyId, 'action' => $action]);
        wa_voice_fail(503, 'schema_unavailable');
    }
} catch (Throwable $e) {
    error_log('[wa-voice] schema probe failed: ' . $e->getMessage());
    wa_voice_fail(503, 'schema_unavailable');
}

// Replay protection. The nonce is claimed by an INSERT that fails on its own
// primary key, so two copies of one request racing each other cannot both win.
try {
    if (!wa_voice_nonce_claim($conn, $keyId, (string)$auth['nonce'], $now)) {
        wa_voice_log('unauthorized', ['key' => $keyId, 'why' => 'replay_or_store_failure']);
        wa_voice_fail(401, 'unauthorized');
    }
    if (!wa_voice_rate_allow($conn, 'key', $keyId, WA_VOICE_RATE_KEY_MAX, $now)) {
        wa_voice_log('rate_limited', ['key' => $keyId, 'scope' => 'key']);
        wa_voice_fail(429, 'rate_limited');
    }
} catch (Throwable $e) {
    error_log('[wa-voice] security store failed: ' . $e->getMessage());
    wa_voice_fail(503, 'crm_unavailable');
}

// =====================================================================
// Actions
// =====================================================================

try {
    switch ($action) {

        // ---- 1. Who is calling? -------------------------------------------
        case 'get_caller_context':
            // The number is taken from the verified SIP caller id and nothing else.
            // A caller-supplied "actually my WhatsApp number is ..." is not accepted:
            // it would turn a lookup of the line someone is calling from into a
            // lookup of any number they care to name, which is the whole extraction
            // risk this endpoint is guarded against.
            // Typed before it is validated: JSON can carry an array or an object
            // here, and casting one to a string inside the validator produces a
            // warning and the literal text "Array" rather than a clean refusal.
            $phoneIn = $payload['phone'] ?? '';
            $e164 = (is_string($phoneIn) || is_int($phoneIn)) ? wa_voice_e164((string)$phoneIn) : '';
            if ($e164 === '') {
                wa_voice_log('bad_request', ['key' => $keyId, 'action' => $action,
                                             'call' => $callId, 'why' => 'phone']);
                wa_voice_fail(400, 'bad_request');
            }

            // Per-phone limiting, on a keyed hash so the table never holds a number.
            $bucket = wa_voice_phone_bucket($e164, wa_voice_phone_pepper());
            if (!wa_voice_rate_allow($conn, 'phone', $bucket, WA_VOICE_RATE_PHONE_MAX, $now)) {
                wa_voice_log('rate_limited', ['key' => $keyId, 'scope' => 'phone',
                                              'to' => wa_voice_mask_phone($e164), 'call' => $callId]);
                wa_voice_fail(429, 'rate_limited');
            }

            $result = wa_voice_caller_context($conn, $e164, $now);
            wa_voice_log('ok', ['key' => $keyId, 'action' => $action, 'call' => $callId,
                                'to' => wa_voice_mask_phone($e164),
                                'matched' => !empty($result['matched']) ? '1' : '0']);
            wa_voice_gc($conn, $now);
            wa_voice_out(200, $result);
            break;

        // ---- 2. What are they asking about? -------------------------------
        case 'search_programmes':
            $query = wa_voice_clean_query($payload['query'] ?? '');
            if ($query === null) {
                wa_voice_log('bad_request', ['key' => $keyId, 'action' => $action,
                                             'call' => $callId, 'why' => 'query']);
                wa_voice_fail(400, 'bad_request');
            }
            $ctx = [];
            $ctxIn = is_array($payload['context'] ?? null) ? $payload['context'] : [];
            if (in_array($ctxIn['delivery_mode'] ?? '', ['virtual', 'onsite'], true)) {
                $ctx['delivery_mode'] = (string)$ctxIn['delivery_mode'];
            }

            $result = wa_voice_search_programmes($conn, $query, $ctx);
            wa_voice_log('ok', ['key' => $keyId, 'action' => $action, 'call' => $callId,
                                'results' => (string)count($result['results'])]);
            wa_voice_gc($conn, $now);
            wa_voice_out(200, $result);
            break;

        // ---- 3. Tell me about it ------------------------------------------
        case 'get_programme_details':
            $type = is_string($payload['type'] ?? null) ? $payload['type'] : '';
            $id   = wa_voice_clean_id($payload['id'] ?? 0);
            if (!wa_voice_valid_ref_type($type) || $id < 1) {
                wa_voice_log('bad_request', ['key' => $keyId, 'action' => $action,
                                             'call' => $callId, 'why' => 'ref']);
                wa_voice_fail(400, 'bad_request');
            }

            $result = wa_voice_programme_details($conn, $type, $id);
            if ($result === null) {
                // The reference does not resolve. Reported as a bad argument rather
                // than as an empty success, so the assistant does not read out a
                // blank record as though it were a real one.
                wa_voice_log('bad_request', ['key' => $keyId, 'action' => $action,
                                             'call' => $callId, 'why' => 'not_found']);
                wa_voice_fail(400, 'bad_request');
            }
            wa_voice_log('ok', ['key' => $keyId, 'action' => $action, 'call' => $callId,
                                'ref' => $type . ':' . $id,
                                'truncated' => !empty($result['truncated']) ? '1' : '0']);
            wa_voice_gc($conn, $now);
            wa_voice_out(200, $result);
            break;

        default:
            wa_voice_log('bad_request', ['key' => $keyId, 'call' => $callId, 'why' => 'action']);
            wa_voice_fail(400, 'bad_request');
    }
} catch (Throwable $e) {
    // mysqli throws by default on PHP 8.1+, so a dropped connection mid-query lands
    // here rather than blanking the response. Nothing assembled so far is emitted:
    // a partial customer record is worse than no answer, because the assistant
    // cannot tell that it is partial.
    error_log('[wa-voice] action failed: ' . $e->getMessage());
    wa_voice_log('crm_unavailable', ['key' => $keyId, 'action' => $action, 'call' => $callId]);
    wa_voice_fail(503, 'crm_unavailable');
}
