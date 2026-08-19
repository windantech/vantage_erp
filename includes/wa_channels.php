<?php
/**
 * WhatsApp channel registry — one entry per business number we send and receive on.
 *
 *   254796128454  messaging   the original enquiry line
 *   254798009935  calling     added for WhatsApp calling; now also answers messages
 *
 * A customer who writes to either number is answered FROM THAT NUMBER. Everything
 * else — routing, the knowledge base, the AI, enrolment, opt-out, escalation — is
 * shared and unchanged; only the credential used to send, and the 24-hour window
 * that governs it, are per channel.
 *
 * DEFAULTS PRESERVE TODAY'S BEHAVIOUR. Any code path that does not name a channel
 * gets the messaging line, exactly as before, so the thirty-odd existing send call
 * sites keep working untouched and broadcasts do not silently move to another
 * number.
 *
 * No secrets here: the messaging key comes from wa_config.php as it always has,
 * and the calling key from the Phase 1.1 loader, which reads it from outside the
 * document root or from the environment.
 */

require_once __DIR__ . '/wa_call_config.php';

/** The channel used when nothing says otherwise. */
if (!defined('WA_CHANNEL_DEFAULT')) { define('WA_CHANNEL_DEFAULT', 'messaging'); }

/**
 * Every configured channel, keyed by a short stable name.
 *
 * 'phone_id' is Meta's phone_number_id. We know the calling line's; the messaging
 * line's has never been recorded anywhere, so it stays empty and that channel is
 * matched on its display number instead — which the webhook payload also carries.
 * Set WA_PHONE_ID in wa_config.php if you have it and matching becomes exact.
 *
 * @return array name => {phone, phone_id, key, label}
 */
function wa_channels() {
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $callSecrets = function_exists('wa_call_secrets') ? wa_call_secrets() : ['key' => ''];

    $cache = [
        'messaging' => [
            'phone'    => defined('WA_PHONE') ? (string)WA_PHONE : '',
            'phone_id' => defined('WA_PHONE_ID') ? (string)WA_PHONE_ID : '',
            'key'      => defined('WA_DIALOG_KEY') ? (string)WA_DIALOG_KEY : '',
            'url'      => defined('WA_DIALOG_URL') ? (string)WA_DIALOG_URL : '',
            'label'    => 'Messaging',
        ],
        'calling' => [
            'phone'    => defined('WA_CALL_PHONE') ? (string)WA_CALL_PHONE : '',
            'phone_id' => defined('WA_CALL_PHONE_ID') ? (string)WA_CALL_PHONE_ID : '',
            'key'      => (string)($callSecrets['key'] ?? ''),
            'url'      => defined('WA_CALL_API_URL') ? (string)WA_CALL_API_URL : '',
            'label'    => 'Calling line',
        ],
    ];
    return $cache;
}

/** One channel by name, falling back to the default. Never returns null. */
function wa_channel($name = null) {
    $all  = wa_channels();
    $name = (string)$name;
    if ($name !== '' && isset($all[$name])) { return $all[$name] + ['name' => $name]; }
    return $all[WA_CHANNEL_DEFAULT] + ['name' => WA_CHANNEL_DEFAULT];
}

/** True when a channel has a usable API key. */
function wa_channel_ready($name) {
    $c = wa_channel($name);
    return trim((string)$c['key']) !== '' && strpos((string)$c['key'], 'YOUR_') !== 0;
}

/**
 * Which channel did this webhook payload arrive on? Pure.
 *
 * Matches Meta's phone_number_id first, then the display number, because the
 * messaging line's phone_number_id has never been recorded. Returns the DEFAULT
 * channel name when neither matches — an unrecognised number must behave exactly
 * as the system did before there were channels, not go silent.
 *
 * @param array $metadata  value.metadata from the webhook envelope
 * @return string channel name
 */
function wa_channel_from_metadata($metadata) {
    if (!is_array($metadata)) { return WA_CHANNEL_DEFAULT; }

    $id  = trim((string)($metadata['phone_number_id'] ?? ''));
    $num = preg_replace('/\D+/', '', (string)($metadata['display_phone_number'] ?? ''));

    foreach (wa_channels() as $name => $c) {
        if ($id !== '' && $c['phone_id'] !== '' && hash_equals((string)$c['phone_id'], $id)) {
            return $name;
        }
    }
    foreach (wa_channels() as $name => $c) {
        $cn = preg_replace('/\D+/', '', (string)$c['phone']);
        if ($num !== '' && $cn !== '' && $num === $cn) { return $name; }
    }
    return WA_CHANNEL_DEFAULT;
}

/** HTTP headers for sending on a channel, or [] when it has no key. */
function wa_channel_headers($name) {
    $c = wa_channel($name);
    if (!wa_channel_ready($c['name'])) { return []; }
    return ['Content-Type' => 'application/json', 'D360-API-KEY' => $c['key']];
}

// =====================================================================
// Per-channel state
// =====================================================================

/**
 * The 24-hour service window is per BUSINESS NUMBER, not per customer.
 *
 * wa_contacts.last_inbound_at cannot express that: a message to the calling line
 * would refresh it and the system would believe it could reply freely on the
 * messaging line, where the window may have closed days earlier. WhatsApp rejects
 * that send and the customer hears nothing. This table keeps them apart.
 */
function wa_channel_schema_ensure($conn) {
    static $done = false;
    if ($done) { return; }
    $done = true;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wa_channel_windows` (
        `contact_id`      INT UNSIGNED NOT NULL,
        `channel`         VARCHAR(24) NOT NULL,
        `last_inbound_at` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`contact_id`, `channel`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @mysqli_query($conn, "ALTER TABLE `wa_messages`
        ADD COLUMN IF NOT EXISTS `channel` VARCHAR(24) NULL DEFAULT NULL");
    // Which number this customer last wrote to — the one we answer on.
    @mysqli_query($conn, "ALTER TABLE `wa_conversations`
        ADD COLUMN IF NOT EXISTS `last_channel` VARCHAR(24) NULL DEFAULT NULL");
}

/** Record an inbound message's arrival on a channel. */
function wa_channel_touch_inbound($conn, $contactId, $channel, $when = null) {
    wa_channel_schema_ensure($conn);
    $cid = (int)$contactId;
    $ch  = "'" . mysqli_real_escape_string($conn, (string)$channel) . "'";
    $ts  = ($when === null || $when === '')
         ? 'NOW()' : "'" . mysqli_real_escape_string($conn, (string)$when) . "'";
    mysqli_query($conn,
        "INSERT INTO wa_channel_windows (contact_id, channel, last_inbound_at)
         VALUES ($cid, $ch, $ts)
         ON DUPLICATE KEY UPDATE last_inbound_at = GREATEST(COALESCE(last_inbound_at, $ts), $ts)");
    mysqli_query($conn,
        "UPDATE wa_conversations SET last_channel = $ch WHERE contact_id = $cid");
}

/** Last inbound on this channel, or null. */
function wa_channel_last_inbound($conn, $contactId, $channel) {
    wa_channel_schema_ensure($conn);
    $cid = (int)$contactId;
    $ch  = "'" . mysqli_real_escape_string($conn, (string)$channel) . "'";
    $res = mysqli_query($conn,
        "SELECT last_inbound_at FROM wa_channel_windows
          WHERE contact_id = $cid AND channel = $ch LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return ($row && $row['last_inbound_at']) ? $row['last_inbound_at'] : null;
}

/**
 * Is the window open on this channel for this contact?
 *
 * Falls back to wa_contacts.last_inbound_at when the channel has no row yet, so
 * every conversation that predates this table keeps working on the messaging line
 * instead of appearing shut.
 */
function wa_channel_within_window($conn, $contactId, $channel, $contactLastInbound = null) {
    $ts = wa_channel_last_inbound($conn, $contactId, $channel);
    if ($ts === null && $channel === WA_CHANNEL_DEFAULT) { $ts = $contactLastInbound; }
    return $ts !== null && (time() - strtotime($ts)) < 24 * 3600;
}

/** The channel to answer this customer on: whichever they last wrote to. */
function wa_reply_channel($conn, $contactId) {
    wa_channel_schema_ensure($conn);
    $cid = (int)$contactId;
    $res = mysqli_query($conn,
        "SELECT last_channel FROM wa_conversations WHERE contact_id = $cid LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $ch  = $row ? trim((string)$row['last_channel']) : '';
    return ($ch !== '' && isset(wa_channels()[$ch])) ? $ch : WA_CHANNEL_DEFAULT;
}
