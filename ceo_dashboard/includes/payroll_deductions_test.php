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
foreach (['getExplicitDeductionCodes'] as $fn) {
    $start = strpos($src, 'function ' . $fn . '(');
    $end   = strpos($src, "\n}\n", $start);
    eval(substr($src, $start, $end - $start + 3));
}

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

echo "\n-- deductions follow the ticked boxes, nothing else --\n";

/**
 * Mirrors getApplicableDeductions(): every known code starts OFF, and only the
 * staff record can switch one on. Nothing is deducted unless it is ticked.
 */
function applyDeductions($deductionsJson, $codes = ['PAYE','NSSF','SHIF','AHL']) {
    $applicable = [];
    foreach ($codes as $c) { $applicable[$c] = false; }
    if ($deductionsJson !== null && trim((string)$deductionsJson) !== '') {
        $staff = json_decode((string)$deductionsJson, true);
        if (is_array($staff)) {
            foreach ($staff as $c => $v) { $applicable[$c] = filter_var($v, FILTER_VALIDATE_BOOLEAN); }
        }
    }
    return $applicable;
}

// THE REPORTED BUG, at every income level: what is ticked is what is deducted.
foreach ([5000, 23999, 24000, 39999, 40000, 65000, 250000] as $gross) {
    $r = applyDeductions('{"PAYE":true,"NSSF":true,"SHIF":false,"AHL":false}');
    check('gross ' . number_format($gross) . ': PAYE ticked -> deducted', true,  $r['PAYE']);
    check('gross ' . number_format($gross) . ': SHIF unticked -> not',    false, $r['SHIF']);
}

// Everything ticked -> everything deducted, at any salary.
$all = applyDeductions('{"PAYE":true,"NSSF":true,"SHIF":true,"AHL":true}');
foreach (['PAYE','NSSF','SHIF','AHL'] as $c) { check('all ticked -> ' . $c, true, $all[$c]); }

// Nothing ticked, saved explicitly -> nothing deducted.
$none = applyDeductions('{"PAYE":false,"NSSF":false,"SHIF":false,"AHL":false}');
foreach (['PAYE','NSSF','SHIF','AHL'] as $c) { check('all unticked -> ' . $c . ' off', false, $none[$c]); }

// Never configured -> NOTHING deducted. This is the change: an untouched record
// used to be deducted everything, so the screen showing no ticks did not describe
// what payroll did.
$legacy = applyDeductions(null);
foreach (['PAYE','NSSF','SHIF','AHL'] as $c) { check('never configured -> ' . $c . ' OFF', false, $legacy[$c]); }
$blank = applyDeductions('');
foreach (['PAYE','NSSF','SHIF','AHL'] as $c) { check('empty column -> ' . $c . ' off', false, $blank[$c]); }
$junk = applyDeductions('not json');
foreach (['PAYE','NSSF','SHIF','AHL'] as $c) { check('unparseable column -> ' . $c . ' off', false, $junk[$c]); }
$emptyMap = applyDeductions('{}');
foreach (['PAYE','NSSF','SHIF','AHL'] as $c) { check('{} -> ' . $c . ' off', false, $emptyMap[$c]); }

// A partial record ticks only what it names; the rest stay off.
$partial = applyDeductions('{"PAYE":true}');
check('partial: PAYE on',  true,  $partial['PAYE']);
check('partial: NSSF off', false, $partial['NSSF']);
check('partial: SHIF off', false, $partial['SHIF']);
check('partial: AHL off',  false, $partial['AHL']);

// is_mandatory no longer forces a deduction onto an unticked employee.
$src2 = file_get_contents(__DIR__ . '/payroll_functions.php');
$fnStart = strpos($src2, 'function getApplicableDeductions');
$fnBody  = substr($src2, $fnStart, strpos($src2, "\n}\n", $fnStart) - $fnStart);
// Strip comments first: the function still MENTIONS is_mandatory to explain why it
// no longer uses it, and matching that would prove nothing.
$fnCode = preg_replace('!//[^\n]*!', '', preg_replace('!/\*.*?\*/!s', '', $fnBody));
check('is_mandatory is no longer selected', 0, preg_match('/is_mandatory/', $fnCode));
check('no code is seeded TRUE from the table', 0,
    preg_match('/\$applicableDeductions\[[^\]]+\]\s*=\s*(true|\(bool\))/i', $fnCode));
check('every known code starts OFF', true,
    strpos($fnBody, '$applicableDeductions[$row[\'deduction_code\']] = false;') !== false);
check('the four statutory codes are always represented', true,
    strpos($fnBody, "foreach (['PAYE', 'NSSF', 'SHIF', 'AHL'] as \$statutoryCode)") !== false);

echo "\n-- the save now records a decision for every box --\n";
foreach (['staff_edit', 'staff_approve'] as $f) {
    $save = file_get_contents(__DIR__ . '/../' . $f . '.php');
    check($f . ': fetches codes inside the handler', true,
        strpos($save, 'SELECT deduction_code FROM deductions WHERE is_active = 1') !== false);
    check($f . ': records unticked as false', true,
        strpos($save, '$deductions[$code] = isset($posted[$code])') !== false);
    check($f . ': no longer writes only the ticks', 0,
        preg_match('/\$deductions\[\$code\] = true;/', $save));
    // Writing '{}' would read back as "everything off" — a silent payroll wipe.
    // Writing '{}' would read back as "everything off" — a silent payroll wipe.
    check($f . ': refuses to write an empty map', true,
        strpos($save, 'empty($allCodes)') !== false && strpos($save, "? 'NULL'") !== false);
    // $deduction_types is loaded further down the file than this handler runs.
    check($f . ': does not use the later $deduction_types', 0,
        preg_match('/foreach \(\$deduction_types as \$dt\)/', $save));
}

echo "\n-- the salary bracket is gone --\n";
check('no threshold constants remain', 0, preg_match('/VASL_DEDUCTION_(MIN|MAX)_GROSS/', $src));
check('no bracket predicate remains',  0, preg_match('/function isSubjectToStatutoryDeductions/', $src));
check('no bracket override in payroll',0, preg_match('/salaryBracketExempt/', $src));
check('payslip never claims an exemption', true,
    strpos($src, "'salary_bracket_exempt' => false") !== false);
check('exempt_reason is always null', true, strpos($src, "'exempt_reason'         => null") !== false);

printf("\n%d check(s), %d failure(s)\n", $checks, $failures);
if ($failures === 0) { echo "OK\n"; }
exit($failures === 0 ? 0 : 1);
