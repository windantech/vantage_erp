<?php
/**
 * Voice API credentials — Phase 2.1A.
 *
 * A SEPARATE, VOICE-ONLY secret. It is not the messaging key (WA_DIALOG_KEY), not
 * the calling key (wa_call_secrets()['key']), and not the calling webhook token.
 * There is deliberately no fallback between any of them.
 *
 * The reason is the shape of what this endpoint does. The messaging key can send
 * a message; the calling key can ask for call permission. The voice key can read
 * out a named customer's conversation history, one number at a time. That is a
 * different kind of exposure, so it gets its own credential, its own rotation and
 * its own blast radius.
 *
 * As shipped this file contains NO SECRETS and is safe to commit. Values are
 * resolved, in order:
 *
 *   0. constants  — WA_VOICE_KEY_ID / WA_VOICE_SECRET, defined in wa_config.php
 *                   (WARNING: wa_config.php is tracked in Git — see the note below)
 *   1. environment — WA_VOICE_KEY_ID / WA_VOICE_SECRET, nothing on disk at all
 *   2. a file named by the WA_VOICE_SECRETS_FILE environment variable
 *   3. the first readable path in $candidates below — all OUTSIDE the document root
 *
 * See includes/wa_voice_secrets.sample.php for the file shape, which also supports
 * several key ids at once so a key can be rotated without downtime.
 *
 * FAILS CLOSED. When nothing resolves — or the value is a placeholder, or is too
 * short to be a real secret — wa_voice_configured() is false and every request is
 * answered 401. A missing secret must disable the endpoint, never weaken it.
 */

// ---- Non-secret configuration (safe in Git) -------------------------------

/**
 * The canonical path that is signed. NOT derived from the Host header, the
 * request URI or any query string: those are attacker-controlled, and letting one
 * of them into the signing string lets a signature minted for one path be
 * replayed against another. Override in wa_config.php only if the deployment
 * genuinely serves the endpoint from somewhere else.
 */
if (!defined('WA_VOICE_SIGNING_PATH')) { define('WA_VOICE_SIGNING_PATH', '/admin/wa_voice_api.php'); }

/** Anything shorter than this is treated as unconfigured rather than accepted.
 *  A 12-character "secret" is not a secret, and failing closed on it is kinder
 *  than authenticating with it. 32 hex chars = 128 bits. */
if (!defined('WA_VOICE_MIN_SECRET_LEN')) { define('WA_VOICE_MIN_SECRET_LEN', 32); }

/** Require TLS. Off only for a local harness; never in production. */
if (!defined('WA_VOICE_REQUIRE_HTTPS')) { define('WA_VOICE_REQUIRE_HTTPS', true); }

/** Honour X-Forwarded-Proto when deciding whether the request arrived over TLS.
 *  Default false: that header is a client-supplied string unless a proxy you
 *  control overwrites it, and trusting it by default would make the HTTPS check
 *  a formality. */
if (!defined('WA_VOICE_TRUST_PROXY')) { define('WA_VOICE_TRUST_PROXY', false); }

// ---- Secret resolution ----------------------------------------------------

/**
 * Load the voice credentials exactly once.
 *
 * @return array {
 *     keys:         [key_id => secret, ...]   empty when unconfigured
 *     phone_pepper: string                    keyed hash input for the rate table
 *     signing_path: string
 *     db:           {host, name, user, pass}  the restricted voice account
 * }
 */
function wa_voice_secrets() {
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $out = ['keys' => [], 'phone_pepper' => '', 'signing_path' => WA_VOICE_SIGNING_PATH,
            'db' => ['host' => '', 'name' => '', 'user' => '', 'pass' => '']];

    // 0. Constants — the route a server admin reaches for first.
    if (defined('WA_VOICE_KEY_ID') && defined('WA_VOICE_SECRET')) {
        wa_voice_secret_add($out['keys'], (string)constant('WA_VOICE_KEY_ID'), (string)constant('WA_VOICE_SECRET'));
    }
    if (defined('WA_VOICE_PHONE_PEPPER')) { $out['phone_pepper'] = trim((string)constant('WA_VOICE_PHONE_PEPPER')); }

    // 1. Environment — puts nothing on disk.
    $envId  = getenv('WA_VOICE_KEY_ID');
    $envSec = getenv('WA_VOICE_SECRET');
    if (is_string($envId) && is_string($envSec)) {
        wa_voice_secret_add($out['keys'], $envId, $envSec);
    }
    $envPep = getenv('WA_VOICE_PHONE_PEPPER');
    if (is_string($envPep) && trim($envPep) !== '') { $out['phone_pepper'] = trim($envPep); }

    // Database credentials come from the environment or the server-only file ONLY.
    // Deliberately no constants route: the two files that carry constants —
    // wa_config.php and wa_call_config.php — are both tracked in Git, and a
    // database password put in either would be committed. Leaving that door shut
    // is cheaper than documenting why not to use it.
    foreach (['host' => 'WA_VOICE_DB_HOST', 'name' => 'WA_VOICE_DB_NAME',
              'user' => 'WA_VOICE_DB_USER', 'pass' => 'WA_VOICE_DB_PASS'] as $k => $env) {
        $v = getenv($env);
        if (is_string($v) && trim($v) !== '') { $out['db'][$k] = trim($v); }
    }

    // 2/3. A PHP file OUTSIDE the document root that returns the same shape.
    $candidates = [];
    $fromEnv = getenv('WA_VOICE_SECRETS_FILE');
    if (is_string($fromEnv) && trim($fromEnv) !== '') { $candidates[] = trim($fromEnv); }
    $candidates[] = '/home/vantage/private/wa_voice_secrets.php';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') : '';
    if ($docRoot !== '') { $candidates[] = dirname($docRoot) . '/private/wa_voice_secrets.php'; }

    foreach ($candidates as $path) {
        if ($path === '' || !is_readable($path)) { continue; }
        $loaded = @include $path;                 // must `return [...]`
        if (is_array($loaded)) {
            if (isset($loaded['keys']) && is_array($loaded['keys'])) {
                foreach ($loaded['keys'] as $id => $sec) {
                    wa_voice_secret_add($out['keys'], (string)$id, (string)$sec);
                }
            }
            // Single-key shorthand.
            if (isset($loaded['key_id'], $loaded['secret'])) {
                wa_voice_secret_add($out['keys'], (string)$loaded['key_id'], (string)$loaded['secret']);
            }
            if ($out['phone_pepper'] === '' && !empty($loaded['phone_pepper'])) {
                $out['phone_pepper'] = trim((string)$loaded['phone_pepper']);
            }
            if (isset($loaded['db']) && is_array($loaded['db'])) {
                foreach (['host', 'name', 'user', 'pass'] as $k) {
                    if ($out['db'][$k] === '' && isset($loaded['db'][$k])) {
                        $out['db'][$k] = trim((string)$loaded['db'][$k]);
                    }
                }
            }
            if (!empty($loaded['signing_path'])) {
                $out['signing_path'] = (string)$loaded['signing_path'];
            }
        }
        break;                                    // first readable candidate wins, found or not
    }

    // The pepper only has to be stable and unguessable. Deriving it from the
    // configured secrets when none is set means the rate table never stores a raw
    // phone number even on a deployment that skipped the setting — the cost is
    // that rotating every key resets the rate counters, which is harmless.
    if ($out['phone_pepper'] === '' && $out['keys']) {
        $out['phone_pepper'] = hash('sha256', 'wa_voice_phone_pepper|' . implode('|', $out['keys']));
    }

    $cache = $out;
    return $cache;
}

/**
 * Accept a key id / secret pair into the map, or reject it outright.
 *
 * Everything that makes a credential unusable is checked HERE, once, so no caller
 * has to remember: an unset value, a sample placeholder, or a secret too short to
 * be worth verifying. A rejected pair leaves the map untouched, which is what
 * makes the whole endpoint fail closed.
 */
function wa_voice_secret_add(array &$keys, $keyId, $secret) {
    $keyId  = trim((string)$keyId);
    $secret = trim((string)$secret);
    if ($keyId === '' || $secret === '') { return; }
    if (!preg_match('/^[A-Za-z0-9._-]{1,32}$/', $keyId)) { return; }
    foreach (['YOUR_', 'CHANGE_ME', 'REPLACE_ME'] as $placeholder) {
        if (stripos($secret, $placeholder) === 0) { return; }
    }
    if (strlen($secret) < WA_VOICE_MIN_SECRET_LEN) { return; }
    $keys[$keyId] = $secret;
}

/** True when at least one usable key is configured. */
function wa_voice_configured() {
    $s = wa_voice_secrets();
    return !empty($s['keys']);
}

/** The secret for a key id, or '' when that key is unknown. Never logged. */
function wa_voice_secret_for($keyId) {
    $s = wa_voice_secrets();
    $keyId = (string)$keyId;
    return isset($s['keys'][$keyId]) ? (string)$s['keys'][$keyId] : '';
}

/** The canonical signed path. */
function wa_voice_signing_path() {
    $s = wa_voice_secrets();
    return (string)$s['signing_path'];
}

/** Keyed-hash input for the rate-limit table, so a raw phone number is never stored. */
function wa_voice_phone_pepper() {
    $s = wa_voice_secrets();
    return (string)$s['phone_pepper'];
}

// =====================================================================
// The restricted database account
// =====================================================================

/** The four connection values. Each '' when not configured. Never logged. */
function wa_voice_db_config() {
    $s = wa_voice_secrets();
    return $s['db'];
}

/**
 * Why the voice database account is unusable, or '' when it is usable.
 *
 * The string names the PROBLEM, never a value, and is written to the error log
 * only — the client is told 503 and nothing else.
 *
 * Three ways to fail, all closed:
 *
 *   incomplete   any of host/name/user/pass missing
 *   placeholder  a sample value left in place
 *   shared       the credentials are the application's own WA_DB_* pair
 *
 * The last one is the point of this whole section. The general WhatsApp account
 * can write to every table in the CRM; this endpoint is supposed to be incapable
 * of that, and silently falling back to the powerful credential when the
 * restricted one is missing would undo the entire least-privilege design at
 * exactly the moment nobody is watching. So it is checked for and refused.
 */
function wa_voice_db_reason() {
    return wa_voice_db_check(
        wa_voice_db_config(),
        defined('WA_DB_USER') ? (string)constant('WA_DB_USER') : null,
        defined('WA_DB_PASS') ? (string)constant('WA_DB_PASS') : null
    );
}

/** True when a dedicated, non-placeholder, non-shared voice account is configured. */
function wa_voice_db_configured() {
    return wa_voice_db_reason() === '';
}
