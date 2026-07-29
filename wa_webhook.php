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

function wa_webhook_process($conn, $payload) {
    foreach ($payload['entry'] ?? [] as $entry) {
        foreach ($entry['changes'] ?? [] as $change) {
            $value = $change['value'] ?? [];
            $names = [];
            foreach ($value['contacts'] ?? [] as $c) {
                if (isset($c['wa_id'])) { $names[$c['wa_id']] = $c['profile']['name'] ?? null; }
            }
            foreach ($value['messages'] ?? [] as $message) {
                wa_webhook_store($conn, $message, $names);
            }
            // Delivery receipts for our outbound messages (sent/delivered/read/failed).
            foreach ($value['statuses'] ?? [] as $st) {
                wa_apply_status($conn, $st['id'] ?? '', $st['status'] ?? '');
            }
        }
    }
}

function wa_webhook_store($conn, $message, $names) {
    $waId = (string)($message['from'] ?? '');
    if ($waId === '') { return; }
    $contactId = wa_upsert_contact($conn, $waId, $names[$waId] ?? null);

    $type = (string)($message['type'] ?? 'text');
    $body = null; $mediaId = null; $mediaMime = null;
    switch ($type) {
        case 'text': $body = $message['text']['body'] ?? null; break;
        case 'image': case 'audio': case 'video': case 'document': case 'sticker':
            $media = $message[$type] ?? [];
            $body = $media['caption'] ?? null; $mediaId = $media['id'] ?? null; $mediaMime = $media['mime_type'] ?? null;
            break;
        case 'interactive':
            $i = $message['interactive'] ?? [];
            $body = $i['button_reply']['title'] ?? $i['list_reply']['title'] ?? null;
            break;
        case 'button': $body = $message['button']['text'] ?? null; break;
    }
    $adId = $message['referral']['source_id'] ?? null;
    $ts   = isset($message['timestamp']) ? date('Y-m-d H:i:s', (int)$message['timestamp']) : date('Y-m-d H:i:s');

    $new = wa_save_inbound($conn, $contactId, [
        'wa_message_id' => $message['id'] ?? null,
        'type' => $type, 'body' => $body, 'media_id' => $mediaId, 'media_mime' => $mediaMime,
        'referral_ad_id' => $adId, 'wa_timestamp' => $ts, 'raw_payload' => $message,
    ]);
    if ($new) {
        wa_touch_last_inbound($conn, $contactId, $ts);
        // If a registration capture is ALREADY in progress, let it handle the
        // message FIRST — so "cancel"/"stop" ends the FORM (not the whole
        // subscription) and questions can be diverted to the AI. Runs before
        // opt-out precisely so the form owns those control words while it's open.
        if ($body && wa_enroll_active($conn, $contactId)) {
            if (wa_enroll_intercept($conn, $contactId, $waId, (string)$body)) {
                return;   // enrollment consumed (or diverted) this message
            }
        }
        // Honour STOP / START (WhatsApp opt-out compliance). If the message
        // was an opt-out/opt-in command we confirm it and skip the AI entirely.
        $optAction = $body ? wa_handle_optout($conn, $contactId, $waId, (string)$body) : '';
        if ($optAction !== '') {
            error_log('[wa-optout] ' . $waId . ' -> ' . $optAction);
            return;
        }
        // Auto-start a guided enrollment on intent (no active session yet).
        // No-op unless enroll_enabled=1 and the chat is on a course/event.
        if ($body && wa_enroll_intercept($conn, $contactId, $waId, (string)$body)) {
            return;   // enrollment consumed this message
        }
        // Auto-route when enabled (assigns the conversation to the right staff owner),
        // then let the AI answer from the course knowledge base (escalates when unsure).
        if (wa_setting_get($conn, 'ai_autoreply', '0') === '1' && $body) {
            wa_route_inbound($conn, $waId, (string)$body, $adId, $names[$waId] ?? null);
            // Batch window (reply_window_secs > 0): don't answer inline — schedule a reply
            // after a short quiet period so rapid successive messages are gathered into
            // ONE reply, and this webhook returns fast (no blocking AI call). The cron
            // (wa_cron.php) sends it. 0 = immediate reply, exactly as before.
            $windowSecs = (int)wa_setting_get($conn, 'reply_window_secs', '0');
            if ($windowSecs > 0) {
                wa_schedule_ai_reply($conn, $contactId, $windowSecs);
                error_log('[wa-ai] batched reply scheduled in ' . $windowSecs . 's for ' . $waId);
            } else {
                $aiRes = wa_maybe_ai_answer($conn, $waId, (string)$body);
                error_log('[wa-ai] ' . json_encode($aiRes));   // shows why it replied or skipped
            }
        }
    }
}
