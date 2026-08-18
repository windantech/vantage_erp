<?php
/**
 * Offline test for the Phase 1 voice helpers. Pure functions only — no database,
 * no network, no session — so it runs anywhere PHP does:
 *
 *   php includes/wa_voice_test.php
 *
 * Exits non-zero on failure, so it can gate a deploy.
 *
 * The point of the hostile cases is not that wa_import_normalize_phone() is
 * careless — it is that it strips non-digits, so "254745811248@evil.com" would
 * reduce to a perfectly dialable number. These assertions prove that input of
 * that shape is REFUSED rather than salvaged, because the result lands in a URI
 * inside an HTML attribute.
 */

require_once __DIR__ . '/wa_functions.php';   // wa_import_normalize_phone()
require_once __DIR__ . '/wa_voice.php';

$failures = 0;
$checks   = 0;

function check($label, $expected, $actual) {
    global $failures, $checks;
    $checks++;
    $ok = ($expected === $actual);
    if (!$ok) { $failures++; }
    printf("[%s] %s%s", $ok ? 'PASS' : 'FAIL', $label,
        $ok ? "\n" : sprintf("\n        expected %s\n        got      %s\n",
                             var_export($expected, true), var_export($actual, true)));
}

$HOST = 'sip.vantageafricaleaders.com';
$OK   = 'sip:9254745811248@' . $HOST;

echo "=== wa_voice_e164() / wa_voice_sip_uri() ===\n\n";
echo "-- valid customer numbers --\n";

// The worked example from the spec.
check('254745811248        -> E.164',  '254745811248', wa_voice_e164('254745811248'));
check('254745811248        -> URI',    $OK,            wa_voice_sip_uri('254745811248'));

// Local Kenyan format: leading 0 becomes the country code.
check('0745811248          -> URI',    $OK,            wa_voice_sip_uri('0745811248'));

// Human formatting: leading plus and spaces.
check('+254 745 811 248    -> URI',    $OK,            wa_voice_sip_uri('+254 745 811 248'));

// '00' international prefix.
check('00254745811248      -> URI',    $OK,            wa_voice_sip_uri('00254745811248'));

// Excel turns a phone column into a float on export.
check('254745811248.0      -> URI',    $OK,            wa_voice_sip_uri('254745811248.0'));

// A non-Kenyan number must be left alone, not re-prefixed with 254.
check('27821112222         -> E.164',  '27821112222',  wa_voice_e164('27821112222'));
check('27821112222         -> URI',    'sip:927821112222@' . $HOST, wa_voice_sip_uri('27821112222'));

echo "\n-- rejected input --\n";

check("'' (empty)          -> ''",     '', wa_voice_sip_uri(''));
check("'   ' (spaces)      -> ''",     '', wa_voice_sip_uri('   '));
check("'abc'               -> ''",     '', wa_voice_sip_uri('abc'));
check("'12' (too short)    -> ''",     '', wa_voice_sip_uri('12'));
check('null                -> \'\'',   '', wa_voice_sip_uri(null));
check("'(-) ' punctuation  -> ''",     '', wa_voice_sip_uri('(-) '));

echo "\n-- hostile input: must be refused, never salvaged into digits --\n";

// Both of these reduce to a valid-looking number if you only strip non-digits.
check('254745811248@evil.com     -> \'\'', '', wa_voice_sip_uri('254745811248@evil.com'));
check('sip:254745811248@evil.com -> \'\'', '', wa_voice_sip_uri('sip:254745811248@evil.com'));
check('CRLF injection            -> \'\'', '', wa_voice_sip_uri("254745811248\r\nTo: attacker"));
check('bare LF                   -> \'\'', '', wa_voice_sip_uri("254745811248\nx"));
check('NUL byte                  -> \'\'', '', wa_voice_sip_uri("254745811248\x00"));
check('tab                       -> \'\'', '', wa_voice_sip_uri("254745811248\t"));
check('second URI appended       -> \'\'', '', wa_voice_sip_uri('254745811248 sip:1@evil.com'));

echo "\n-- host and prefix validation --\n";

check('bad host (has @)     -> \'\'',  '', wa_voice_sip_uri('254745811248', 'evil@host.com'));
check('bad host (has port)  -> \'\'',  '', wa_voice_sip_uri('254745811248', 'host.com:5060'));
check('bad host (empty)     -> \'\'',  '', wa_voice_sip_uri('254745811248', ''));
check('bad host (spaces)    -> \'\'',  '', wa_voice_sip_uri('254745811248', 'a b.com'));
check('good host accepted',            'sip:9254745811248@pbx.example.com',
                                       wa_voice_sip_uri('254745811248', 'pbx.example.com'));
check('non-numeric prefix   -> \'\'',  '', wa_voice_sip_uri('254745811248', $HOST, '9a'));
check('empty prefix allowed',          'sip:254745811248@' . $HOST,
                                       wa_voice_sip_uri('254745811248', $HOST, ''));
check('prefix applied once',           $OK, wa_voice_sip_uri('254745811248', $HOST, '9'));

echo "\n-- display helper --\n";

check('display +E.164',               '+254745811248', wa_voice_display_number('0745811248'));
check('display invalid -> \'\'',      '',              wa_voice_display_number('abc'));

echo "\n-- boundary lengths (9-15 digits) --\n";

// These must already begin with the default country code, otherwise the normaliser
// (correctly) treats a short number as LOCAL and expands it: '12345678' is not an
// 8-digit international number, it is 0712-style shorthand for '25412345678'.
check('7 digits  rejected',  '', wa_voice_e164('2541234'));
// A short local number is expanded first, THEN length-checked: '12345' becomes
// '25412345' (8) and is still rejected; one more digit clears the 9-digit floor.
check('local 12345  -> 8 digits, rejected',  '',          wa_voice_e164('12345'));
check('local 123456 -> 9 digits, accepted',  '254123456', wa_voice_e164('123456'));
check('15 digits accepted',  '123456789012345', wa_voice_e164('123456789012345'));
check('16 digits rejected',  '', wa_voice_e164('1234567890123456'));

printf("\n%d check(s), %d failure(s)\n", $checks, $failures);
if ($failures === 0) { echo "OK\n"; }
exit($failures === 0 ? 0 : 1);
