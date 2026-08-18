<?php
/**
 * WhatsApp CALLING channel configuration (+254798009935) — Phase 1.1.
 *
 * As shipped this file contains NO SECRETS. It stays safe to commit only while the
 * four WA_CALL_* secret constants are left undefined here — see the note below.
 *
 * The calling channel is a DIFFERENT WhatsApp number from the messaging channel
 * (+254796128454). Its API key is separate and must never be confused with
 * WA_DIALOG_KEY: sending permission requests with the messaging key would request
 * permission from the wrong number, and the customer would see a prompt naming a
 * line we never intend to call them from. There is deliberately NO fallback.
 *
 * Secrets are resolved, in order:
 *   0. constants — defined here or in wa_config.php (simplest; see the warning)
 *   1. environment variables  (nothing on disk at all)
 *   2. a file named by the WA_CALL_SECRETS_FILE environment variable
 *   3. the first readable path in $candidates below — all OUTSIDE the document root
 *
 * Options 1-3 keep the values out of the repository entirely. See
 * includes/wa_call_secrets.sample.php for the shape of the external file.
 *
 * If nothing resolves, WA_CALL_CONFIGURED is false and every entry point fails
 * CLOSED — the button reports that calling is not configured and no API call is
 * attempted. A missing key must disable calling, never degrade to the wrong one.
 */

// ---- Where to put the secrets ---------------------------------------------
//
// Define these four constants and everything below picks them up. Put them either
// at the top of THIS file, or in includes/wa_config.php next to the messaging
// settings — that file loads first on every entry point, so both work:
//
//     define('WA_CALL_DIALOG_KEY',    '...');   // 360dialog key for the 798 channel
//     define('WA_CALL_WEBHOOK_TOKEN', '...');   // shared secret for X-Vantage-Call-Token
//     define('WA_CALL_TEMPLATE_NAME', 'course_call_permission_v1');
//     define('WA_CALL_TEMPLATE_LANG', 'en');
//
// WARNING: both of those files are tracked in Git, so a key placed in either is
// committed and pushed. Add includes/wa_call_config.php to .gitignore first if you
// use this route — its history is secret-free today, so untracking it now costs
// nothing and no rotation is needed later.
//
// Environment variables and an out-of-webroot file still work and take no
// precedence over each other beyond the order below; nothing has to change if you
// later move the values out of the repository.

// ---- Non-secret identifiers (safe in Git) ---------------------------------

if (!defined('WA_CALL_PHONE'))    { define('WA_CALL_PHONE',    '254798009935');    }  // calling line
if (!defined('WA_CALL_PHONE_ID')) { define('WA_CALL_PHONE_ID', '1255293457670620'); } // Meta phone-number ID
if (!defined('WA_CALL_WABA_ID'))  { define('WA_CALL_WABA_ID',  '2402344606956698'); } // WABA the webhook must match
if (!defined('WA_CALL_APP_ID'))   { define('WA_CALL_APP_ID',   '782368959283666');  } // Meta app (reference only)
if (!defined('WA_CALL_API_URL'))  { define('WA_CALL_API_URL',  'https://waba-v2.360dialog.io'); }

/** Header the 798 webhook must present. Its VALUE is the secret; the name is not. */
if (!defined('WA_CALL_WEBHOOK_HEADER')) { define('WA_CALL_WEBHOOK_HEADER', 'X-Vantage-Call-Token'); }

/** Permission lifetimes (seconds). Pilot values — see wa_call_permissions.php. */
if (!defined('WA_CALL_GRANT_TTL'))    { define('WA_CALL_GRANT_TTL',    7 * 24 * 3600); }  // GRANTED valid 7 days
if (!defined('WA_CALL_PENDING_TTL'))  { define('WA_CALL_PENDING_TTL',  7 * 24 * 3600); }  // unanswered request expires
if (!defined('WA_CALL_WINDOW_TTL'))   { define('WA_CALL_WINDOW_TTL',   24 * 3600);     }  // callable window after GRANTED
if (!defined('WA_CALL_MAX_REQUESTS')) { define('WA_CALL_MAX_REQUESTS', 2);             }  // per WA_CALL_THROTTLE_DAYS
if (!defined('WA_CALL_THROTTLE_DAYS')){ define('WA_CALL_THROTTLE_DAYS', 7);            }

// ---- Secret resolution ----------------------------------------------------

/**
 * Load the calling secrets exactly once. Returns an array of the four values,
 * each '' when absent. Never echoes, logs or exposes a value.
 */
function wa_call_secrets() {
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $out = ['key' => '', 'webhook_token' => '', 'template' => '', 'lang' => ''];

    // 0. Constants, defined in this file or in wa_config.php. Checked first because
    //    it is the option a server admin will reach for, and it needs no new file.
    foreach (['key'           => 'WA_CALL_DIALOG_KEY',
              'webhook_token' => 'WA_CALL_WEBHOOK_TOKEN',
              'template'      => 'WA_CALL_TEMPLATE_NAME',
              'lang'          => 'WA_CALL_TEMPLATE_LANG'] as $k => $const) {
        if (defined($const)) {
            $v = trim((string)constant($const));
            // A placeholder left in a sample must not read as configured.
            if ($v !== '' && strpos($v, 'YOUR_') !== 0) { $out[$k] = $v; }
        }
    }

    // 1. Environment next — the only option that puts nothing on disk.
    $env = [
        'key'           => getenv('WA_CALL_DIALOG_KEY'),
        'webhook_token' => getenv('WA_CALL_WEBHOOK_TOKEN'),
        'template'      => getenv('WA_CALL_TEMPLATE_NAME'),
        'lang'          => getenv('WA_CALL_TEMPLATE_LANG'),
    ];
    foreach ($env as $k => $v) { if (is_string($v) && trim($v) !== '') { $out[$k] = trim($v); } }

    // 2/3. A PHP file OUTSIDE the document root that returns the same array.
    if ($out['key'] === '' || $out['webhook_token'] === '' || $out['template'] === '') {
        $candidates = [];
        $fromEnv = getenv('WA_CALL_SECRETS_FILE');
        if (is_string($fromEnv) && trim($fromEnv) !== '') { $candidates[] = trim($fromEnv); }
        $candidates[] = '/home/vantage/private/wa_call_secrets.php';
        // One level above the document root, for installs laid out differently.
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') : '';
        if ($docRoot !== '') { $candidates[] = dirname($docRoot) . '/private/wa_call_secrets.php'; }

        foreach ($candidates as $path) {
            if ($path === '' || !is_readable($path)) { continue; }
            $loaded = @include $path;          // must `return [...]`
            if (is_array($loaded)) {
                foreach (['key', 'webhook_token', 'template', 'lang'] as $k) {
                    if ($out[$k] === '' && isset($loaded[$k]) && trim((string)$loaded[$k]) !== '') {
                        $out[$k] = trim((string)$loaded[$k]);
                    }
                }
            }
            break;   // first readable candidate wins, found or not
        }
    }

    if ($out['lang'] === '') { $out['lang'] = 'en'; }
    $cache = $out;
    return $cache;
}

/** True when the channel can talk to 360dialog AND authenticate its webhook. */
function wa_call_configured() {
    $s = wa_call_secrets();
    return $s['key'] !== '' && $s['webhook_token'] !== '';
}

/** True when an approved CALL_PERMISSION_REQUEST template is named in the config.
 *  Separate from wa_call_configured(): the key can be present while the template
 *  is still awaiting Meta approval, and those two failures need different messages. */
function wa_call_template_configured() {
    $s = wa_call_secrets();
    return $s['template'] !== '';
}

/**
 * Remove any configured secret from a string before it is logged, flashed to a
 * rep, or written to last_error.
 *
 * 360dialog error bodies quote the request back in some failure modes, and an API
 * key that reaches a flash message or the database has escaped its storage
 * boundary entirely. Cheap to apply at every exit point; impossible to undo once
 * a key is sitting in a log file.
 */
function wa_call_scrub($text) {
    $t = (string)$text;
    foreach (wa_call_secrets() as $v) {
        $v = (string)$v;
        if (strlen($v) >= 8) { $t = str_replace($v, '[redacted]', $t); }
    }
    return $t;
}

/**
 * Why calling is unavailable, or '' when it is available. The single source of the
 * user-facing wording, so the button, the POST action and the tests cannot drift.
 */
function wa_call_unavailable_reason() {
    if (!wa_call_configured())          { return 'Calling not configured.'; }
    if (!wa_call_template_configured()) { return 'Calling permission template not configured.'; }
    return '';
}
