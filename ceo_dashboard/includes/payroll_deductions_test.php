<?php
/**
 * Offline tests for the statutory-deduction rules.
 *
 *   php ceo_dashboard/includes/payroll_deductions_test.php
 *
 * Covers the two defects that caused staff marked for deduction not to be
 * deducted, and pins the bracket boundaries so the thresholds cannot drift
 * silently again.
 *
 * Pure: the functions under test take a JSON string and a number. No database.
 */

// Extract only the pure pieces — the file as a whole expects a live $conn.
$src = file_get_contents(__DIR__ . '/payroll_functions.php');
foreach (['getExplicitDeductionCodes', 'isSubjectToStatutoryDeductions'] as $fn) {
    $start = strpos($src, 'function ' . $fn . '(');
    $end   = strpos($src, "\n}\n", $start);
    eval(substr($src, $start, $end - $start + 3));
}
preg_match_all("/define\('(VASL_DEDUCTION_(?:MIN|MAX)_GROSS)',\s*(\d+)\)/", $src, $m, PREG_SET_ORDER);
foreach ($m as $d) { define($d[1], (int)$d[2]); }

$failures = 0; $checks = 0;
function check($label, $expected, $actual) {
    global $failures, $checks;
    $checks++;
    $ok = ($expected === $actual);
    if (!$ok) { $failures++; }
    printf("[%s] %s%s", $ok ? 'PASS' : 'FAIL', $label,
        $ok ? "\n" : sprintf("\n        expected %s\n        got      %s\n",
                             var_export($expected, true), var_export($actual, true)));
}

echo "=== statutory deductions ===\n\n-- which codes did HR explicitly switch ON --\n";

check('NULL column -> no explicit decision', [], getExplicitDeductionCodes(null));
check('empty string -> no explicit decision', [], getExplicitDeductionCodes(''));
check('whitespace -> no explicit decision',   [], getExplicitDeductionCodes('   '));
check('{} -> no code switched on',            [], getExplicitDeductionCodes('{}'));
check('malformed JSON -> nothing',            [], getExplicitDeductionCodes('not json'));
check('one code on',        ['PAYE'],         getExplicitDeductionCodes('{"PAYE":true}'));
check('several on',         ['PAYE','NSSF'],  getExplicitDeductionCodes('{"PAYE":true,"NSSF":true}'));
// An explicit OFF is a decision too, but it is not an ON — it must not be forced.
check('explicit false is not "on"', ['NSSF'],
    getExplicitDeductionCodes('{"PAYE":false,"NSSF":true}'));
check('all false -> nothing on', [], getExplicitDeductionCodes('{"PAYE":false,"NSSF":false}'));
// The saved form writes booleans, but tolerate the other truthy spellings.
check('"1" counts as on',    ['PAYE'], getExplicitDeductionCodes('{"PAYE":"1"}'));
check('"true" counts as on', ['PAYE'], getExplicitDeductionCodes('{"PAYE":"true"}'));
check('"on" counts as on',   ['PAYE'], getExplicitDeductionCodes('{"PAYE":"on"}'));
check('"0" does not',        [],       getExplicitDeductionCodes('{"PAYE":"0"}'));
check('"false" does not',    [],       getExplicitDeductionCodes('{"PAYE":"false"}'));

echo "\n-- salary bracket boundaries --\n";
$MIN = VASL_DEDUCTION_MIN_GROSS;
$MAX = VASL_DEDUCTION_MAX_GROSS;
printf("   (thresholds in force: MIN=%s  MAX=%s)\n", number_format($MIN), number_format($MAX));

check('below the floor -> exempt',        false, isSubjectToStatutoryDeductions($MIN - 1));
check('exactly at the floor -> deducted', true,  isSubjectToStatutoryDeductions($MIN));
check('mid band -> deducted',             true,  isSubjectToStatutoryDeductions(($MIN + $MAX) / 2));
check('one below the ceiling -> deducted',true,  isSubjectToStatutoryDeductions($MAX - 1));
check('exactly at the ceiling -> exempt', false, isSubjectToStatutoryDeductions($MAX));
check('well above -> exempt',             false, isSubjectToStatutoryDeductions($MAX + 50000));
check('zero gross -> exempt',             false, isSubjectToStatutoryDeductions(0));

echo "\n-- the fix: an explicit marking beats the bracket --\n";

/** Mirrors calculateEmployeePayroll()'s STEP 2 exactly. */
function bracketApply($grossPay, $deductionsJson, $startOn = ['PAYE','NSSF','SHIF','AHL']) {
    $applicable = [];
    foreach (['PAYE','NSSF','SHIF','AHL'] as $c) { $applicable[$c] = in_array($c, $startOn, true); }
    $exempt   = !isSubjectToStatutoryDeductions($grossPay);
    $explicit = getExplicitDeductionCodes($deductionsJson);
    if ($exempt) {
        foreach (['PAYE','NSSF','SHIF','AHL'] as $c) {
            if (!in_array($c, $explicit, true)) { $applicable[$c] = false; }
        }
    }
    $fullyExempt = $exempt && empty(array_intersect(['PAYE','NSSF','SHIF','AHL'], $explicit));
    return ['applicable' => $applicable, 'fully_exempt' => $fullyExempt, 'bracket_exempt' => $exempt];
}

// THE REPORTED BUG: gross above the ceiling, HR ticked PAYE and NSSF, nothing deducted.
$r = bracketApply($MAX + 10000, '{"PAYE":true,"NSSF":true}', ['PAYE','NSSF']);
check('high earner, PAYE marked -> PAYE deducted', true,  $r['applicable']['PAYE']);
check('high earner, NSSF marked -> NSSF deducted', true,  $r['applicable']['NSSF']);
check('unmarked SHIF stays exempt',                false, $r['applicable']['SHIF']);
check('not reported as fully exempt',              false, $r['fully_exempt']);
check('but the bracket did apply, and is reported',true,  $r['bracket_exempt']);

// Same below the floor.
$r2 = bracketApply($MIN - 5000, '{"SHIF":true}', ['SHIF']);
check('low earner, SHIF marked -> SHIF deducted',  true,  $r2['applicable']['SHIF']);
check('low earner, PAYE unmarked -> exempt',       false, $r2['applicable']['PAYE']);

// Nobody decided: the bracket still governs, exactly as before.
$r3 = bracketApply($MAX + 10000, null);
check('no staff setting -> bracket exempts PAYE',  false, $r3['applicable']['PAYE']);
check('no staff setting -> fully exempt',          true,  $r3['fully_exempt']);

// An explicit OFF must never be forced back on.
$r4 = bracketApply($MAX + 10000, '{"PAYE":false}');
check('explicit false stays off under exemption',  false, $r4['applicable']['PAYE']);
check('explicit false is still fully exempt',      true,  $r4['fully_exempt']);

// Inside the band nothing changes — this fix must not alter normal payroll.
$r5 = bracketApply(($MIN + $MAX) / 2, null);
foreach (['PAYE','NSSF','SHIF','AHL'] as $c) {
    check('in-band, no setting -> ' . $c . ' deducted', true, $r5['applicable'][$c]);
}
$r6 = bracketApply(($MIN + $MAX) / 2, '{"PAYE":true}', ['PAYE']);
check('in-band, only PAYE ticked -> PAYE on',  true,  $r6['applicable']['PAYE']);
check('in-band, only PAYE ticked -> NSSF off', false, $r6['applicable']['NSSF']);
check('in-band is never reported exempt',      false, $r6['fully_exempt']);

echo "\n-- thresholds have exactly one source of truth --\n";
check('no stray 65,000 literal in the logic', 0,
    preg_match('/\b65000\b/', preg_replace('!/\*.*?\*/!s', '', $src)));
check('no stale "FIXED: was" note', 0, preg_match('/FIXED: was/', $src));
check('MIN is defined once', 1, preg_match_all("/define\('VASL_DEDUCTION_MIN_GROSS'/", $src));
check('MAX is defined once', 1, preg_match_all("/define\('VASL_DEDUCTION_MAX_GROSS'/", $src));

printf("\n%d check(s), %d failure(s)\n", $checks, $failures);
if ($failures === 0) { echo "OK\n"; }
exit($failures === 0 ? 0 : 1);
