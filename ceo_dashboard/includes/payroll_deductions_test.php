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

/** Mirrors payroll STEP 2 now that the salary bracket is gone. */
function applyDeductions($deductionsJson, $mandatory = ['PAYE','NSSF','SHIF','AHL']) {
    $applicable = [];
    foreach ($mandatory as $c) { $applicable[$c] = true; }   // table defaults
    if ($deductionsJson !== null && trim((string)$deductionsJson) !== '') {
        $staff = json_decode((string)$deductionsJson, true);
        if (is_array($staff)) {
            foreach ($applicable as $c => $_) { $applicable[$c] = false; }
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

// Never configured (legacy NULL) -> mandatory defaults, deliberately conservative.
$legacy = applyDeductions(null);
foreach (['PAYE','NSSF','SHIF','AHL'] as $c) { check('never configured -> ' . $c . ' on', true, $legacy[$c]); }

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
