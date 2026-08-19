<?php
/**
 * SAMPLE ONLY — this file is committed and contains no real values.
 *
 * Copy it to a location OUTSIDE the web document root, fill it in, and make it
 * readable only by the web user:
 *
 *     cp includes/wa_voice_secrets.sample.php /home/vantage/private/wa_voice_secrets.php
 *     chmod 600 /home/vantage/private/wa_voice_secrets.php
 *     chown <web-user> /home/vantage/private/wa_voice_secrets.php
 *
 * Do NOT place the real file anywhere under public_html, and never commit it.
 *
 * Environment variables work too and put nothing on disk:
 *     WA_VOICE_KEY_ID, WA_VOICE_SECRET, WA_VOICE_PHONE_PEPPER
 *
 * Generate each value with:
 *     php -r 'echo bin2hex(random_bytes(32)), "\n";'
 *
 * This credential belongs to the VOICE API only. It is not the messaging key, not
 * the calling channel key, and not the calling webhook token — and none of those
 * is ever a fallback for it. Anyone holding this secret can read a customer's
 * conversation history by phone number, so scope it accordingly: give it to the
 * voice server and to nothing else.
 */

return [
    /**
     * key_id => secret.
     *
     * More than one entry may be present at a time, which is how a key is rotated
     * without downtime: add the new id, deploy, switch the voice server over,
     * then remove the old id on the next deploy. The key id is sent in clear in
     * the X-Vantage-Voice-Key-Id header, so it must not itself be secret —
     * 'voice-2026-08' is a good id, the secret is not.
     *
     * Secrets shorter than 32 characters are IGNORED, not accepted: a weak key
     * reads as unconfigured and the endpoint stays closed.
     */
    'keys' => [
        // 'voice-2026-08' => '',
    ],

    /**
     * Salt for the keyed phone hash stored in the rate-limit table, so that table
     * never holds a raw customer number. Optional — when blank it is derived from
     * the secrets above, which is fine; setting it explicitly just means the rate
     * counters survive a key rotation.
     */
    'phone_pepper' => '',

    /**
     * The canonical path included in the HMAC signing string. Only change this if
     * the endpoint is genuinely served from somewhere other than
     * /admin/wa_voice_api.php — it must match what the voice server signs, exactly.
     */
    // 'signing_path' => '/admin/wa_voice_api.php',

    /**
     * The DEDICATED, RESTRICTED database account this endpoint connects as.
     *
     * It must NOT be the application's WA_DB_USER. That account can write to every
     * table in the CRM; this one is granted SELECT on the tables the three actions
     * read, plus INSERT/DELETE on wa_voice_nonces and INSERT/UPDATE/DELETE on
     * wa_voice_rate — and no CREATE, ALTER, DROP or INDEX at all. That is what
     * makes "the voice API cannot change the schema or alter a customer record" a
     * fact enforced by MySQL rather than a promise made in PHP.
     *
     * The endpoint refuses to start if any value is missing, if any is still a
     * placeholder, or if the user or password matches WA_DB_USER / WA_DB_PASS.
     * There is deliberately no fallback to the application credentials.
     *
     * Create the account and its grants with the Phase 2.1A deployment SQL, which
     * also creates the two tables. Environment variables work instead of this
     * section: WA_VOICE_DB_HOST, WA_VOICE_DB_NAME, WA_VOICE_DB_USER,
     * WA_VOICE_DB_PASS.
     *
     * There is no constants route for these. wa_config.php and wa_call_config.php
     * are both tracked in Git, so a password placed in either would be committed.
     */
    'db' => [
        'host' => 'localhost',
        'name' => 'vantage_crm',
        'user' => '',        // e.g. 'vantage_voice' — never the application's user
        'pass' => '',
    ],
];
