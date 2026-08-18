<?php
/**
 * SAMPLE ONLY — this file is committed and contains no real values.
 *
 * Copy it to a location OUTSIDE the web document root, fill in the real values,
 * and make it readable only by the web user:
 *
 *     cp includes/wa_call_secrets.sample.php /home/vantage/private/wa_call_secrets.php
 *     chmod 600 /home/vantage/private/wa_call_secrets.php
 *     chown <web-user> /home/vantage/private/wa_call_secrets.php
 *
 * Do NOT place the real file anywhere under public_html — it must never be
 * serveable, and it must never be committed.
 *
 * Alternatively set these as environment variables and skip the file entirely:
 *     WA_CALL_DIALOG_KEY, WA_CALL_WEBHOOK_TOKEN,
 *     WA_CALL_TEMPLATE_NAME, WA_CALL_TEMPLATE_LANG
 *
 * These belong to the CALLING channel (+254798009935) only. The messaging
 * channel's WA_DIALOG_KEY is a different credential and is never a fallback.
 */

return [
    // 360dialog API key for the +254798009935 channel (WABA 2402344606956698).
    'key'           => '',

    // Shared secret the 798 webhook must send in the X-Vantage-Call-Token header.
    // Generate a long random value, e.g.  php -r 'echo bin2hex(random_bytes(24));'
    'webhook_token' => '',

    // The APPROVED template used to ask a customer for call permission, and its
    // language code. Kept out of Git so the name can change with Meta approvals
    // without a deploy.
    'template'      => '',      // 'course_call_permission_v1'
    'lang'          => 'en',    // must match the Meta approval exactly ('en', not 'en_US')
];
