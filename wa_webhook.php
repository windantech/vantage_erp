<?php
/**
 * 360dialog webhook receiver. Place at the ERP root.
 * URL: https://<erp-domain>/wa_webhook.php
 *
 * Acknowledges HTTP 200 first, then stores into wa_contacts / wa_messages.
 * Runs WITHOUT auth.php (no session) — uses wa_db.php for the connection.
 */

require_once __DIR__ . '/includes/wa_db.php';        // $wa_conn
require_once __DIR__ . '/includes/wa_functions.php';
require_once __DIR__ . '/includes/wa_inbound.php';   // wa_webhook_process/_store
wa_use_nairobi_time($wa_conn);

// ---- Meta GET verification handshake ----
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $mode  = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? null;
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? null;
    $chall = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';
    if ($mode === 'subscribe' && WA_VERIFY_TOKEN !== '' && hash_equals(WA_VERIFY_TOKEN, (string)$token)) {
        http_response_code(200); header('Content-Type: text/plain'); echo $chall;
    } else {
        http_response_code(403); echo 'Forbidden';
    }
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';

if (WA_VERIFY_TOKEN !== '' && !hash_equals(WA_VERIFY_TOKEN, (string)($_GET['token'] ?? ''))) {
    http_response_code(403); echo json_encode(['error' => 'forbidden']); exit;
}

// Process synchronously, THEN acknowledge.
// On LiteSpeed/LSAPI the "ack early + finish in background" trick is unreliable:
// when 360dialog disconnects after the early 200, the server ABORTS the still-
// running script (kill), so the AI reply never gets sent. Processing before we
// respond keeps the connection open. De-dup on wa_message_id makes any 360dialog
// retry a harmless no-op (routing + AI only run for newly-stored messages).
ignore_user_abort(true);
@set_time_limit(60);
try {
    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    wa_webhook_process($wa_conn, $payload);
} catch (Throwable $e) {
    error_log('[wa-webhook] ' . $e->getMessage());
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'received']);
