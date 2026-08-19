<?php
/**
 * Dedicated webhook for the WhatsApp CALLING channel (+254798009935).
 *
 * Public URL:  https://vantageafricaleaders.com/admin/wa_call_webhook.php
 * (the repository root is deployed to public_html/admin)
 *
 * SEPARATE from wa_webhook.php on purpose. That file is the messaging channel's
 * receiver — it stores messages, opens the 24-hour window, and drives routing and
 * the AI. Permission events must do none of those things, and a bug here must not
 * be able to disturb messaging. The two share no token, no API key and no state
 * beyond a read-only contact lookup.
 *
 * Handles exactly one payload, delivered at the ROOT of the body (not nested in
 * entry/changes/value like message webhooks):
 *
 *     { "event": "call_permission_status",
 *       "status": "GRANTED" | "REJECTED" | "REVOKED",
 *       "waba_id": "2402344606956698",
 *       "recipient": "2547XXXXXXXX" }
 *
 * The query form is supported because the 360dialog console does not always allow
 * a custom header. It is weaker — the secret is written into access logs on every
 * delivery — so rotate it if you later switch to the header.
 *
 * Response contract (what 360dialog does with each is why they differ):
 *   403  missing or invalid credentials (header or ?token=)
 *   400  unreadable body — malformed JSON, or larger than the size limit
 *   200  applied, a duplicate retry, or an authenticated event we do not care
 *        about. Retrying would change nothing, so we acknowledge.
 *   500  transient/database failure. We WANT a retry here: answering 200 would
 *        silently drop a real GRANTED and leave the customer uncallable.
 */

require_once __DIR__ . '/includes/wa_db.php';            // $wa_conn (no session, no auth.php)
require_once __DIR__ . '/includes/wa_functions.php';     // wa_use_nairobi_time, wa_http_*
require_once __DIR__ . '/includes/wa_voice.php';         // wa_voice_e164() — already hardened
require_once __DIR__ . '/includes/wa_call_config.php';
require_once __DIR__ . '/includes/wa_call_permissions.php';
require_once __DIR__ . '/includes/wa_call_webhook_lib.php';   // pure parsing/classification

// =====================================================================
// Request handling
// =====================================================================

wa_use_nairobi_time($wa_conn);

/** Emit a JSON response and stop. */
function wa_call_reply($code, $body) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// A GET is not part of this integration (360dialog channel webhooks are POST-only
// here). Answer without revealing anything.
if ($method !== 'POST') { wa_call_reply(405, ['error' => 'method_not_allowed']); }

// ---- Optional diagnostics -----------------------------------------------------
// Define WA_CALL_DEBUG_LOG in wa_call_config.php to capture what a live channel
// actually sends. The permission payload was implemented from documentation and
// has never been checked against a real event, so this is how the two get
// reconciled. It logs BEFORE authentication, so "nothing arrived" is
// distinguishable from "arrived and was rejected".
//
// Turn it off once the shape is confirmed: it writes customer phone numbers into
// the error log.
$WA_CALL_DEBUG = defined('WA_CALL_DEBUG_LOG') && WA_CALL_DEBUG_LOG;
if ($WA_CALL_DEBUG) {
    // Which credential was PRESENT, never its value.
    $hasHeader = wa_call_webhook_header(WA_CALL_WEBHOOK_HEADER) !== '' ? 'yes' : 'no';
    $hasQuery  = isset($_GET['token']) && $_GET['token'] !== ''       ? 'yes' : 'no';
    error_log('[wa-call-webhook][debug] hit: header=' . $hasHeader . ' query=' . $hasQuery
            . ' ua=' . substr((string)($_SERVER['HTTP_USER_AGENT'] ?? '-'), 0, 60));
}

// ---- Authentication: separate secret from the messaging channel ---------------
// Accepts the X-Vantage-Call-Token header or ?token=, because 360dialog's channel
// webhook configuration does not always allow a custom header.
$secrets = wa_call_secrets();
if (!wa_call_webhook_authenticate($secrets['webhook_token'])) {
    // Neither the submitted value nor our own is logged — an attacker's guess is
    // still a secret-shaped string, and our token must never reach a log file.
    error_log('[wa-call-webhook] 403 — missing or invalid credentials');
    wa_call_reply(403, ['error' => 'forbidden']);
}

// ---- Body size limit --------------------------------------------------------
// A permission event is a few hundred bytes. Refusing anything larger caps the
// memory a single unauthenticated-but-tokened POST can make us allocate, and
// stops a malformed or hostile body being parsed at all.
if (!defined('WA_CALL_MAX_BODY')) { define('WA_CALL_MAX_BODY', 64 * 1024); }

$declared = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($declared > WA_CALL_MAX_BODY) {
    error_log('[wa-call-webhook] 400 — body too large (' . $declared . ' bytes)');
    wa_call_reply(400, ['error' => 'body_too_large']);
}

// Read at most the limit plus one byte, so a missing/false Content-Length cannot
// smuggle an unbounded body past the check above.
$raw = (string)file_get_contents('php://input', false, null, 0, WA_CALL_MAX_BODY + 1);
if (strlen($raw) > WA_CALL_MAX_BODY) {
    error_log('[wa-call-webhook] 400 — body exceeded the limit while reading');
    wa_call_reply(400, ['error' => 'body_too_large']);
}

if ($WA_CALL_DEBUG) {
    // Scrubbed, so a token echoed inside the body cannot reach the log.
    error_log('[wa-call-webhook][debug] body: ' . wa_call_scrub(substr($raw, 0, 2000)));
}

$payload = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
    // Malformed rather than merely uninteresting: 400 so the sender sees it is a
    // bad request, not something we chose to ignore.
    error_log('[wa-call-webhook] 400 — unreadable body: ' . json_last_error_msg());
    wa_call_reply(400, ['error' => 'bad_request']);
}

$verdict = wa_call_webhook_classify($payload, WA_CALL_WABA_ID);

if ($WA_CALL_DEBUG) {
    error_log('[wa-call-webhook][debug] verdict: ' . $verdict['action']
            . ($verdict['reason'] !== '' ? ' (' . $verdict['reason'] . ')' : ''));
}

if ($verdict['action'] !== 'apply') {
    // Authenticated but irrelevant (or malformed): acknowledge so 360dialog stops
    // retrying, and leave a trace so a misconfiguration is visible.
    error_log('[wa-call-webhook] ignored: ' . $verdict['reason']);
    wa_call_reply(200, ['status' => 'ignored']);
}

// ---- Resolve the customer. NEVER create one. --------------------------------
// A recipient we have no contact for means a number we never messaged, so creating
// a contact would inject a stranger into the messaging CRM off the back of an
// external POST. Log and acknowledge instead.
$row = wa_call_fetch($wa_conn,
    "SELECT id FROM wa_contacts WHERE wa_id = ? LIMIT 1", 's', [$verdict['recipient']]);

if (!$row) {
    error_log('[wa-call-webhook] unknown recipient ' . wa_call_mask_msisdn($verdict['recipient'])
            . ' — not created');
    wa_call_reply(200, ['status' => 'ignored', 'reason' => 'unknown_recipient']);
}

$result = wa_call_apply_webhook(
    $wa_conn, (int)$row['id'], $verdict['status'], $verdict['waba_id'], WA_CALL_PHONE_ID
);
error_log('[wa-call-webhook] ' . $verdict['status'] . ' for contact ' . (int)$row['id']
        . ' (' . wa_call_mask_msisdn($verdict['recipient']) . ') -> ' . $result);

// A transient failure must be retried by 360dialog, so it is the one outcome that
// is not a 200: losing a GRANTED silently leaves the customer permanently uncallable.
wa_call_reply($result === 'error' ? 500 : 200, ['status' => $result]);
