<?php
/**
 * WhatsApp voice calling — Phase 1 helpers.
 *
 * Turns a customer's stored WhatsApp number into a SIP URI that a rep's softphone
 * (Linphone) can dial through the Vantage PBX:
 *
 *     sip:9254745811248@sip.vantageafricaleaders.com
 *          ^ outbound prefix
 *           ^^^^^^^^^^^^ the customer's E.164 number, digits only, no plus
 *
 * Function definitions only — no database, no output, no side effects. Safe to
 * require from any page. Must be required AFTER includes/wa_functions.php, which
 * provides wa_import_normalize_phone().
 *
 * SCOPE: the input is a CUSTOMER PHONE NUMBER, never an Asterisk dial string. The
 * helper does not look for, strip, or reason about an existing outbound prefix —
 * plenty of real country codes begin with 9 (92 Pakistan, 93 Afghanistan, 94 Sri
 * Lanka, 95 Myanmar, 98 Iran), so "starts with 9" carries no information about
 * whether a number has been prefixed. The prefix is added exactly once, here, to
 * an already-validated E.164 number.
 *
 * Nothing in this file is a secret: the SIP host is public DNS and the prefix is
 * dialplan syntax. Extension numbers, SIP passwords and API keys belong in the
 * softphone and the PBX, never in the CRM or its HTML.
 */

// Defensive defaults, mirroring the pattern in wa_functions.php: a deployment that
// has not defined these still works, and an installation on another PBX can
// override them from wa_config.php without touching this file.
if (!defined('WA_SIP_HOST'))   { define('WA_SIP_HOST',   'sip.vantageafricaleaders.com'); }
if (!defined('WA_SIP_PREFIX')) { define('WA_SIP_PREFIX', '9'); }

/** Shortest / longest plausible E.164 subscriber number, matching the bounds
 *  wa_import_phones() already applies to imported numbers. */
if (!defined('WA_VOICE_MIN_DIGITS')) { define('WA_VOICE_MIN_DIGITS', 9); }
if (!defined('WA_VOICE_MAX_DIGITS')) { define('WA_VOICE_MAX_DIGITS', 15); }

/**
 * A customer phone number as digits-only E.164 (no plus), or '' if it cannot be
 * dialled safely.
 *
 * The value ends up inside a URI in an HTML attribute, so this rejects anything
 * that is not a plain telephone number BEFORE normalising. wa_import_normalize_phone()
 * strips non-digits, which would silently turn "254745811248@evil.com" into a
 * plausible-looking number and "sip:254…@evil.com" into a dialable one. Discarding
 * such input is right: it is not a phone number, and quietly dialling the digits
 * we could salvage from an injection attempt would be worse than refusing.
 *
 * Accepts ordinary human formatting: digits, a leading +, spaces, hyphens,
 * parentheses, and a trailing Excel decimal ("…248.0").
 */
function wa_voice_e164($raw) {
    $s = (string)$raw;

    // Control characters, including CR and LF. A newline in a URI is header/URI
    // injection; there is no legitimate phone number that contains one.
    if (preg_match('/[\x00-\x1F\x7F]/', $s)) { return ''; }

    // Whitelist the shape of a telephone number. Anything else — letters, '@', ':',
    // '/', a scheme, a hostname — fails here rather than being stripped down to
    // digits later.
    if (!preg_match('/^ *\+?[0-9()\- ]+(\.[0-9]+)? *$/', $s)) { return ''; }

    // The pattern above can be satisfied by punctuation alone ("(-) "), so insist
    // on at least one actual digit before handing it on.
    if (!preg_match('/[0-9]/', $s)) { return ''; }

    if (!function_exists('wa_import_normalize_phone')) { return ''; }
    $e164 = wa_import_normalize_phone($s);          // '0745…' -> '254745…', drops '00', trailing '.0'

    // Belt and braces: the normaliser is digits-only by construction, but this is
    // the value that reaches the browser, so verify rather than assume.
    if ($e164 === '' || !ctype_digit($e164)) { return ''; }

    $len = strlen($e164);
    if ($len < WA_VOICE_MIN_DIGITS || $len > WA_VOICE_MAX_DIGITS) { return ''; }

    return $e164;
}

/** True if $host is a plain hostname we are willing to put after the '@'. */
function wa_voice_valid_host($host) {
    $h = trim((string)$host);
    if ($h === '' || strlen($h) > 253) { return false; }
    // Letters, digits, hyphen and dot only; labels may not start or end with a
    // hyphen. This is what keeps a mis-set constant from smuggling a port, a
    // userinfo section or a second URI into the link.
    return (bool)preg_match('/^(?=.{1,253}$)([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/', $h);
}

/**
 * Full SIP URI for calling a customer through the PBX, or '' when the number,
 * host or prefix is unusable.
 *
 * Returning '' rather than a half-built URI is deliberate: the caller decides
 * between a live button and a disabled one, and a malformed sip: link fails
 * silently in the browser, which is the hardest kind of fault to report.
 */
function wa_voice_sip_uri($raw, $host = null, $prefix = null) {
    $e164 = wa_voice_e164($raw);
    if ($e164 === '') { return ''; }

    $host = ($host === null) ? WA_SIP_HOST : $host;
    if (!wa_voice_valid_host($host)) { return ''; }

    $prefix = ($prefix === null) ? (string)WA_SIP_PREFIX : (string)$prefix;
    // A prefix is dialplan digits. Empty is allowed (a PBX that needs none);
    // anything non-numeric is a misconfiguration, not something to pass through.
    if ($prefix !== '' && !ctype_digit($prefix)) { return ''; }

    return 'sip:' . $prefix . $e164 . '@' . trim((string)$host);
}

/**
 * The number as a person reads it: '+254745811248', or '' when uncallable.
 * Display only — never use this to build a URI.
 */
function wa_voice_display_number($raw) {
    $e164 = wa_voice_e164($raw);
    return $e164 === '' ? '' : '+' . $e164;
}
