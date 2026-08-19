<?php
/**
 * Shared inbound-message pipeline.
 *
 * Extracted verbatim from wa_webhook.php so BOTH business numbers can use it. The
 * messaging line and the calling line receive on different endpoints with
 * different credentials, but a customer message means the same thing on either:
 * same storage, same routing, same knowledge base, same AI, same enrolment and
 * opt-out handling. Only the channel it arrived on — and therefore the number we
 * answer from — differs.
 *
 * These functions used to live inside the webhook script itself, which meant the
 * calling endpoint could only reuse them by including a file that would run the
 * messaging endpoint's whole request handler: read the body, check the token, and
 * exit. This file exists so that cannot happen.
 *
 * No behaviour change: the bodies are unchanged apart from the $channel argument.
 */

function wa_webhook_process($conn, $payload) {
    foreach ($payload['entry'] ?? [] as $entry) {
        foreach ($entry['changes'] ?? [] as $change) {
            $value = $change['value'] ?? [];
            // Which of our business numbers did this arrive on? Unrecognised metadata
            // resolves to the messaging line, so anything unexpected behaves exactly
            // as it did before there were channels.
            $channel = wa_channel_from_metadata($value['metadata'] ?? []);
            $names = [];
            foreach ($value['contacts'] ?? [] as $c) {
                if (isset($c['wa_id'])) { $names[$c['wa_id']] = $c['profile']['name'] ?? null; }
            }
            foreach ($value['messages'] ?? [] as $message) {
                wa_webhook_store($conn, $message, $names, $channel);
            }
            // Delivery receipts for our outbound messages (sent/delivered/read/failed).
            foreach ($value['statuses'] ?? [] as $st) {
                wa_apply_status($conn, $st['id'] ?? '', $st['status'] ?? '');
            }
        }
    }
}

function wa_webhook_store($conn, $message, $names, $channel = null) {
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

    $channel = ($channel === null || $channel === '') ? WA_CHANNEL_DEFAULT : $channel;

    $new = wa_save_inbound($conn, $contactId, [
        'wa_message_id' => $message['id'] ?? null,
        'type' => $type, 'body' => $body, 'media_id' => $mediaId, 'media_mime' => $mediaMime,
        'referral_ad_id' => $adId, 'wa_timestamp' => $ts, 'raw_payload' => $message,
        'channel' => $channel,
    ]);
    if ($new) {
        wa_touch_last_inbound($conn, $contactId, $ts);
        // Open the window on THIS number and remember it as the line to answer on.
        // Kept alongside the contact-wide timestamp rather than replacing it, so
        // every existing reader kept working while channels were introduced.
        wa_ensure_conversation($conn, $contactId);
        wa_channel_touch_inbound($conn, $contactId, $channel, $ts);

        // A FIRST enquiry on the calling line always gets a permission request.
        // Going to the calling number rather than the enquiry number is intent
        // enough, so this does not wait for the AI to judge it, and does not care
        // whether they have talked to us on the messaging line before.
        //
        // Placed here, before the interceptors, because it must run whatever else
        // this message turns out to be — an enrolment step, an opt-out, a bare
        // greeting. It only ever fires once per contact, and still obeys the
        // Phase 1.1 eligibility, lease and throttle.
        if (function_exists('wa_call_offer_force_on_calling_line')) {
            try {
                $forced = wa_call_offer_force_on_calling_line($conn, $contactId, $waId, $channel);
                if (!empty($forced['sent']) || ($forced['skip'] ?? '') !== 'not_calling_line') {
                    error_log('[wa-call-force] ' . json_encode($forced));
                }
            } catch (Throwable $e) {
                // Must never stop the message being handled normally.
                error_log('[wa-call-force] ' . $e->getMessage());
            }
        }
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
        // A message with NO readable text — voice note, image/video without a caption,
        // location pin, contact card, document. The AI can't read it, so the text-only
        // path below would skip it and go silent. Never dodge a customer: acknowledge and
        // escalate to a human. Same auto-reply gate as the AI path.
        if (wa_setting_get($conn, 'ai_autoreply', '0') === '1' && trim((string)$body) === '' && $type !== 'text') {
            $mRes = wa_handle_media_message($conn, $contactId, $waId, $type);
            error_log('[wa-ai] media ' . json_encode($mRes));
            return;
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
