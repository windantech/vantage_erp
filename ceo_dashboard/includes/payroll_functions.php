<?php
/**
 * Payroll Calculation Functions
 * Vantage Africa School Of Leadership
 * Kenya Tax Rules 2024/2025
 */

/**
 * Get payroll setting value
 */
function getPayrollSetting($conn, $key) {
    $key = mysqli_real_escape_string($conn, $key);
    $result = mysqli_query($conn, "
        SELECT setting_value, setting_type 
        FROM payroll_settings 
        WHERE setting_key = '$key' 
          AND is_active = 1 
          AND (effective_to IS NULL OR effective_to >= CURDATE())
        ORDER BY effective_from DESC 
        LIMIT 1
    ");
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        if ($row['setting_type'] == 'json') {
            return json_decode($row['setting_value'], true);
        }
        return floatval($row['setting_value']);
    }
    return null;
}

/**
 * Get all active payroll settings
 */
function getAllPayrollSettings($conn) {
    $settings = [];
    $result = mysqli_query($conn, "
        SELECT setting_key, setting_value, setting_type 
        FROM payroll_settings 
        WHERE is_active = 1 
          AND (effective_to IS NULL OR effective_to >= CURDATE())
    ");
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['setting_type'] == 'json') {
                $settings[$row['setting_key']] = json_decode($row['setting_value'], true);
            } else {
                $settings[$row['setting_key']] = floatval($row['setting_value']);
            }
        }
    }
    return $settings;
}

/**
 * Calculate NSSF Contribution (Employee & Employer)
 * Kenya NSSF Act - Tier I & Tier II
 */
function calculateNSSF($conn, $grossPay) {
    $tier1Limit = getPayrollSetting($conn, 'NSSF_TIER1_LIMIT') ?: 9000;
    $tier2Limit = getPayrollSetting($conn, 'NSSF_TIER2_LIMIT') ?: 36000;
    $rate = getPayrollSetting($conn, 'NSSF_RATE') ?: 6;
    
    $rateDecimal = $rate / 100;
    
    // Tier I: 6% of pensionable pay up to KES 9,000
    $tier1Contribution = min($grossPay, $tier1Limit) * $rateDecimal;
    
    // Tier II: 6% of pensionable pay between tier1 limit and KES 36,000
    $tier2Contribution = 0;
    if ($grossPay > $tier1Limit) {
        $tier2Base = min($grossPay, $tier2Limit) - $tier1Limit;
        $tier2Contribution = $tier2Base * $rateDecimal;
    }
    
    $totalEmployee = $tier1Contribution + $tier2Contribution;
    $totalEmployer = $totalEmployee; // Employer matches employee contribution
    
    return [
        'employee' => round($totalEmployee, 2),
        'employer' => round($totalEmployer, 2),
        'tier1' => round($tier1Contribution, 2),
        'tier2' => round($tier2Contribution, 2)
    ];
}

/**
 * Calculate SHIF (Social Health Insurance Fund)
 * 2.75% of gross salary
 */
function calculateSHIF($conn, $grossPay) {
    $rate = getPayrollSetting($conn, 'SHIF_RATE') ?: 2.75;
    $amount = $grossPay * ($rate / 100);
    return round($amount, 2);
}

/**
 * Calculate Housing Levy
 * 1.5% employee + 1.5% employer
 */
function calculateHousingLevy($conn, $grossPay) {
    $rate = getPayrollSetting($conn, 'HOUSING_LEVY_RATE') ?: 1.5;
    $amount = $grossPay * ($rate / 100);
    
    return [
        'employee' => round($amount, 2),
        'employer' => round($amount, 2)
    ];
}

/**
 * Calculate PAYE (Pay As You Earn) Tax
 * Uses progressive tax bands
 */
function calculatePAYE($conn, $taxableIncome, $insurancePremium = 0) {
    $taxBands = getPayrollSetting($conn, 'PAYE_TAX_BANDS');
    $personalRelief = getPayrollSetting($conn, 'PERSONAL_RELIEF') ?: 2400;
    $insuranceReliefRate = getPayrollSetting($conn, 'INSURANCE_RELIEF_RATE') ?: 15;
    $insuranceReliefMax = getPayrollSetting($conn, 'INSURANCE_RELIEF_MAX') ?: 5000;
    
    if (!$taxBands || $taxableIncome <= 0) {
        return [
            'tax_before_relief' => 0,
            'personal_relief' => 0,
            'insurance_relief' => 0,
            'paye' => 0
        ];
    }
    
    // Calculate tax using progressive bands
    $taxBeforeRelief = 0;
    $remainingIncome = $taxableIncome;
    $previousMax = 0;
    
    foreach ($taxBands as $band) {
        if ($remainingIncome <= 0) break;
        
        $bandMax = $band['max'];
        $bandRate = $band['rate'] / 100;
        
        // Calculate taxable amount in this band
        $bandWidth = $bandMax - $previousMax;
        $taxableInBand = min($remainingIncome, $bandWidth);
        
        $taxBeforeRelief += $taxableInBand * $bandRate;
        $remainingIncome -= $taxableInBand;
        $previousMax = $bandMax;
    }
    
    // Apply reliefs
    $insuranceRelief = 0;
    if ($insurancePremium > 0) {
        $insuranceRelief = min($insurancePremium * ($insuranceReliefRate / 100), $insuranceReliefMax);
    }
    
    $totalRelief = $personalRelief + $insuranceRelief;
    $paye = max(0, $taxBeforeRelief - $totalRelief);
    
    return [
        'tax_before_relief' => round($taxBeforeRelief, 2),
        'personal_relief' => round($personalRelief, 2),
        'insurance_relief' => round($insuranceRelief, 2),
        'paye' => round($paye, 2)
    ];
}

/**
 * Calculate Overtime Pay
 */
function calculateOvertime($conn, $basicSalary, $normalHours, $weekendHours, $holidayHours) {
    $standardHours = getPayrollSetting($conn, 'STANDARD_WORKING_HOURS') ?: 176;
    $normalRate = getPayrollSetting($conn, 'OVERTIME_RATE_NORMAL') ?: 1.5;
    $weekendRate = getPayrollSetting($conn, 'OVERTIME_RATE_WEEKEND') ?: 2;
    $holidayRate = getPayrollSetting($conn, 'OVERTIME_RATE_HOLIDAY') ?: 2;
    
    // Hourly rate
    $hourlyRate = $basicSalary / $standardHours;
    
    $normalOT   = $normalHours  * $hourlyRate * $normalRate;
    $weekendOT  = $weekendHours * $hourlyRate * $weekendRate;
    $holidayOT  = $holidayHours * $hourlyRate * $holidayRate;
    
    return [
        'normal'  => round($normalOT, 2),
        'weekend' => round($weekendOT, 2),
        'holiday' => round($holidayOT, 2),
        'total'   => round($normalOT + $weekendOT + $holidayOT, 2)
    ];
}

/**
 * Parse allowances from JSON
 */
function parseAllowances($allowancesJson) {
    if (empty($allowancesJson)) {
        return [
            'total'     => 0,
            'breakdown' => [],
            'house'     => 0,
            'transport' => 0,
            'medical'   => 0
        ];
    }
    
    $allowances = json_decode($allowancesJson, true);
    if (!is_array($allowances)) {
        return [
            'total'     => 0,
            'breakdown' => [],
            'house'     => 0,
            'transport' => 0,
            'medical'   => 0
        ];
    }
    
    $total = array_sum($allowances);
    
    return [
        'total'     => round($total, 2),
        'breakdown' => $allowances,
        'house'     => $allowances['HOUSE']     ?? 0,
        'transport' => ($allowances['TRANSPORT'] ?? 0) + ($allowances['COMMUTER'] ?? 0),
        'medical'   => $allowances['MEDICAL']   ?? 0
    ];
}

/**
 * Check which deductions apply to an employee.
 *
 * Logic (in priority order):
 *  1. If the staff record has an explicit deductions JSON (even an empty
 *     object {}), trust it completely:
 *       a. Reset ALL deduction codes to FALSE.
 *       b. Apply only what the JSON explicitly declares.
 *     This is the "HR has spoken" path — a deliberate empty {} means
 *     "no deductions for this employee."
 *
 *  2. If the staff record has NULL or no deductions JSON, fall back to
 *     the table defaults (mandatory deductions = TRUE, voluntary = FALSE).
 *     This is the "never been configured" path for legacy staff records.
 *
 *  IMPORTANT: A staff record with `deductions = NULL` is treated as
 *  "default mandatory deductions apply." If HR wants a staff member to
 *  have NO statutory deductions, the deductions field must be saved as
 *  '{"PAYE":false,"NSSF":false,"SHIF":false,"AHL":false}' (or {}), NOT NULL.
 *
 * @param mysqli      $conn           Database connection
 * @param string|null $deductionsJson JSON from staff.deductions field
 * @return array      Associative array: deduction_code => bool
 */
/**
 * The deduction codes HR has explicitly switched ON for this staff member.
 *
 * Only codes present in the staff record AND set to a true value count. A code
 * the record sets to false is an explicit OFF, and a code it does not mention at
 * all is not a decision — neither belongs here.
 *
 * Pure: no database, no side effects.
 *
 * @param  string|null $deductionsJson  the staff.deductions column
 * @return array       list of deduction codes, e.g. ['PAYE','NSSF']
 */
function getExplicitDeductionCodes($deductionsJson) {
    if ($deductionsJson === null || trim((string)$deductionsJson) === '') { return []; }
    $decoded = json_decode((string)$deductionsJson, true);
    if (!is_array($decoded)) { return []; }
    $on = [];
    foreach ($decoded as $code => $value) {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) { $on[] = (string)$code; }
    }
    return $on;
}

function getApplicableDeductions($conn, $deductionsJson = null) {

    // ── Step 1: Build defaults from the deductions table ─────────────────────
    $applicableDeductions = [];

    $result = mysqli_query($conn, "
        SELECT deduction_code, is_mandatory 
        FROM deductions 
        WHERE is_active = 1
    ");

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $applicableDeductions[$row['deduction_code']] = (bool)$row['is_mandatory'];
        }
    }

    // Fallback hardcoded defaults if the deductions table is empty / missing
    if (empty($applicableDeductions)) {
        $applicableDeductions = [
            'PAYE' => true,
            'NSSF' => true,
            'SHIF' => true,
            'AHL'  => true
        ];
    }

    // ── Step 2: Apply staff-level overrides ───────────────────────────────────
    //
    // FIX: Use a stricter check than empty(). The string '{}' is technically
    // "not empty" as a string but json_decodes to an empty array. We want to
    // honour an explicit empty {} as "HR turned everything off."
    //
    // Detection rule: if the column is NULL or literal empty string '', fall
    // through to defaults. Anything else (including '{}') is treated as an
    // explicit HR setting.
    if ($deductionsJson !== null && $deductionsJson !== '') {

        $staffDeductions = json_decode($deductionsJson, true);

        // Only treat as "explicit settings" if JSON parses to an array
        // (handles {}, {"PAYE":true}, {"PAYE":false,"NSSF":false}, etc.)
        if (is_array($staffDeductions)) {

            /*
             * The staff record has explicit deduction settings.
             * Reset ALL codes to FALSE first so that mandatory deductions
             * from the table don't silently remain active for codes the
             * staff JSON simply didn't mention.
             */
            foreach ($applicableDeductions as $code => $value) {
                $applicableDeductions[$code] = false;
            }

            // Now apply only what the staff JSON explicitly declares
            foreach ($staffDeductions as $code => $value) {
                // Handles true/false/1/0/"1"/"0"/"true"/"false"/"on"/"off"
                $applicableDeductions[$code] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }
    }

    return $applicableDeductions;
}

/**
 * VASL Salary Bracket Exemption Policy
 * ─────────────────────────────────────
 * Staff grossing BELOW  VASL_DEDUCTION_MIN_GROSS → exempt from statutory deductions
 * Staff grossing AT/ABOVE VASL_DEDUCTION_MAX_GROSS → exempt from statutory deductions
 * Between the two → normal deductions apply
 *
 * The thresholds are stated ONLY as the constants below. They used to be repeated
 * as literal figures in four comments, which had drifted to 65,000 while the code
 * used 40,000 — so the file documented a policy it did not implement.
 *
 * EXEMPTION IS A DEFAULT, NOT A VETO: a deduction explicitly marked on a staff
 * record is applied whatever the bracket says (see calculateEmployeePayroll).
 *
 * "Statutory deductions" here means PAYE, NSSF, SHIF, and Housing Levy (AHL).
 * Non-statutory deductions (salary advance, loan, SACCO, HELB, other) are
 * always applied regardless of salary bracket.
 */
define('VASL_DEDUCTION_MIN_GROSS',  24000);   // below this → exempt
define('VASL_DEDUCTION_MAX_GROSS',  40000);   // at or above this → exempt

/**
 * Determine whether statutory deductions apply based on gross pay.
 *
 * @param  float $grossPay  The employee's gross pay for the period
 * @return bool             TRUE  = deductions apply normally
 *                          FALSE = employee is exempt (zero all statutory)
 */
function isSubjectToStatutoryDeductions($grossPay) {
    // Exempt if below minimum threshold
    if ($grossPay < VASL_DEDUCTION_MIN_GROSS) {
        return false;
    }
    // Exempt if at or above upper threshold
    if ($grossPay >= VASL_DEDUCTION_MAX_GROSS) {
        return false;
    }
    // Normal band: VASL_DEDUCTION_MIN_GROSS <= gross < VASL_DEDUCTION_MAX_GROSS
    return true;
}

/**
 * MAIN FUNCTION: Calculate Full Payroll for One Employee
 */
function calculateEmployeePayroll($conn, $staffId, $periodId, $inputs = []) {
    // Get staff details
    $staffResult = mysqli_query($conn, "
        SELECT s.*, d.department_name 
        FROM staff s 
        LEFT JOIN departments d ON s.department_id = d.id 
        WHERE s.id = " . intval($staffId) . " LIMIT 1
    ");
    
    if (!$staffResult || mysqli_num_rows($staffResult) == 0) {
        return ['success' => false, 'error' => 'Staff not found'];
    }
    
    $staff = mysqli_fetch_assoc($staffResult);
    
    // Parse allowances and deductions
    $allowances          = parseAllowances($staff['allowances']);
    $applicableDeductions = getApplicableDeductions($conn, $staff['deductions']);
    
    // Get inputs (overtime, bonus, etc.)
    $overtimeNormal  = floatval($inputs['overtime_normal']  ?? 0);
    $overtimeWeekend = floatval($inputs['overtime_weekend'] ?? 0);
    $overtimeHoliday = floatval($inputs['overtime_holiday'] ?? 0);
    $bonus           = floatval($inputs['bonus']            ?? 0);
    $commission      = floatval($inputs['commission']       ?? 0);
    $otherEarnings   = floatval($inputs['other_earnings']   ?? 0);
    $salaryAdvance   = floatval($inputs['salary_advance']   ?? 0);
    $loanDeduction   = floatval($inputs['loan_deduction']   ?? 0);
    $saccoDeduction  = floatval($inputs['sacco_deduction']  ?? 0);
    $helbDeduction   = floatval($inputs['helb_deduction']   ?? 0);
    $otherDeductions = floatval($inputs['other_deductions'] ?? 0);
    $insurancePremium = floatval($inputs['insurance_premium'] ?? 0);
    
    // ============================================
    // STEP 1: Calculate Gross Pay
    // ============================================
    $basicSalary     = floatval($staff['basic_salary']);
    $totalAllowances = $allowances['total'];
    
    $overtime       = calculateOvertime($conn, $basicSalary, $overtimeNormal, $overtimeWeekend, $overtimeHoliday);
    $overtimeAmount = $overtime['total'];
    
    $grossPay = $basicSalary + $totalAllowances + $overtimeAmount + $bonus + $commission + $otherEarnings;

    // ============================================
    // STEP 2: Apply VASL Salary Bracket Policy
    // ============================================
    // Outside the VASL_DEDUCTION_MIN_GROSS..VASL_DEDUCTION_MAX_GROSS band, staff are
    // exempt from statutory deductions (PAYE, NSSF, SHIF, Housing Levy) BY DEFAULT.
    // If the bracket exempts the employee, override every statutory deduction
    // code to false — regardless of individual staff settings or table defaults.
    $salaryBracketExempt = !isSubjectToStatutoryDeductions($grossPay);

    // An explicit marking on the staff record WINS over the salary bracket.
    //
    // The bracket used to override everything, so a staff member HR had ticked for
    // PAYE and NSSF still had them zeroed purely because of their gross — with
    // nothing on the payslip to distinguish "exempt by policy" from "HR said
    // deduct". The bracket is a default for people nobody has decided about; it is
    // not a veto over a decision somebody made deliberately.
    //
    // A code the record sets to FALSE stays off either way, and a code it does not
    // mention is left to the bracket exactly as before.
    $explicitlyOn = getExplicitDeductionCodes($staff['deductions'] ?? null);

    if ($salaryBracketExempt) {
        foreach (['PAYE', 'NSSF', 'SHIF', 'AHL'] as $statutoryCode) {
            if (!in_array($statutoryCode, $explicitlyOn, true)) {
                $applicableDeductions[$statutoryCode] = false;
            }
        }
    }
    // True only when the bracket actually zeroed everything, so the payslip's
    // exemption note cannot claim a full exemption that did not happen.
    $fullyExempt = $salaryBracketExempt && empty(array_intersect(
        ['PAYE', 'NSSF', 'SHIF', 'AHL'], $explicitlyOn));

    // ============================================
    // STEP 3: Calculate Statutory Deductions
    // ============================================

    // NSSF — only if applicable after bracket check
    $nssf = ['employee' => 0, 'employer' => 0, 'tier1' => 0, 'tier2' => 0];
    if (!empty($applicableDeductions['NSSF'])) {
        $nssf = calculateNSSF($conn, $grossPay);
    }
    
    // SHIF
    $shifAmount = 0;
    if (!empty($applicableDeductions['SHIF'])) {
        $shifAmount = calculateSHIF($conn, $grossPay);
    }
    
    // Affordable Housing Levy
    $housingLevy = ['employee' => 0, 'employer' => 0];
    if (!empty($applicableDeductions['AHL'])) {
        $housingLevy = calculateHousingLevy($conn, $grossPay);
    }
    
    // ============================================
    // STEP 4: Calculate Taxable Income & PAYE
    // ============================================
    
    // Taxable Income = Gross Pay − NSSF − SHIF − Housing Levy (employee portions)
    // All statutory deductions reduce taxable income before PAYE is computed
    $taxableIncome = $grossPay - $nssf['employee'] - $shifAmount - $housingLevy['employee'];
    $taxableIncome = max(0, $taxableIncome); // cannot be negative
    
    $paye = ['paye' => 0, 'tax_before_relief' => 0, 'personal_relief' => 0, 'insurance_relief' => 0];
    if (!empty($applicableDeductions['PAYE'])) {
        $paye = calculatePAYE($conn, $taxableIncome, $insurancePremium);
    }
    
    // ============================================
    // STEP 5: Calculate Total Deductions
    // ============================================
    $totalStatutoryDeductions = $nssf['employee'] + $shifAmount + $housingLevy['employee'] + $paye['paye'];
    $totalOtherDeductions     = $salaryAdvance + $loanDeduction + $saccoDeduction + $helbDeduction + $otherDeductions;
    $totalDeductions          = $totalStatutoryDeductions + $totalOtherDeductions;
    
    // ============================================
    // STEP 6: Calculate Net Pay
    // ============================================
    $netPay = $grossPay - $totalDeductions;
    
    // ============================================
    // STEP 7: Employer Costs
    // ============================================
    $employerCosts    = $nssf['employer'] + $housingLevy['employer'];
    $totalEmployerCost = $grossPay + $employerCosts;
    
    // ============================================
    // Return Results
    // ============================================
    return [
        'success' => true,
        'staff' => [
            'id'          => $staff['id'],
            'staff_id'    => $staff['staff_id'],
            'name'        => $staff['full_name'],
            'department'  => $staff['department_name'],
            'job_title'   => $staff['job_title'],
            'kra_pin'     => $staff['kra_pin'],
            'nssf_number' => $staff['nssf_number'],
            'nhif_number' => $staff['nhif_number']
        ],
        'earnings' => [
            'basic_salary'  => $basicSalary,
            'allowances'    => $allowances,
            'overtime'      => $overtime,
            'bonus'         => $bonus,
            'commission'    => $commission,
            'other_earnings' => $otherEarnings,
            'gross_pay'     => round($grossPay, 2)
        ],
        'deductions' => [
            'applicable'            => $applicableDeductions,
            // salary_bracket_exempt now means "the bracket zeroed EVERY statutory
            // deduction". A staff member the bracket would exempt but whose record
            // explicitly marks deductions is not exempt, and must not be reported as
            // such — the statutory remittance reports read this flag.
            'salary_bracket_exempt' => $fullyExempt,
            'bracket_would_exempt'  => $salaryBracketExempt,   // before staff overrides
            'deductions_forced_on'  => $salaryBracketExempt ? $explicitlyOn : [],
            'exempt_reason'         => $fullyExempt
                                        ? ($grossPay < VASL_DEDUCTION_MIN_GROSS
                                            ? 'Gross pay below KES ' . number_format(VASL_DEDUCTION_MIN_GROSS)
                                            : 'Gross pay at or above KES ' . number_format(VASL_DEDUCTION_MAX_GROSS))
                                        : ($salaryBracketExempt
                                            ? 'Salary bracket exempt, but deductions marked on the staff record apply'
                                            : null),
            'nssf_employee'         => $nssf['employee'],
            'shif'                  => $shifAmount,
            'housing_levy_employee' => $housingLevy['employee'],
            'taxable_income'        => round($taxableIncome, 2),
            'tax_before_relief'     => $paye['tax_before_relief'],
            'personal_relief'       => $paye['personal_relief'],
            'insurance_relief'      => $paye['insurance_relief'],
            'paye'                  => $paye['paye'],
            'salary_advance'        => $salaryAdvance,
            'loan_deduction'        => $loanDeduction,
            'sacco_deduction'       => $saccoDeduction,
            'helb_deduction'        => $helbDeduction,
            'other_deductions'      => $otherDeductions,
            'total_statutory'       => round($totalStatutoryDeductions, 2),
            'total_other'           => round($totalOtherDeductions, 2),
            'total_deductions'      => round($totalDeductions, 2)
        ],
        'net_pay' => round($netPay, 2),
        'employer' => [
            'nssf'               => $nssf['employer'],
            'housing_levy'       => $housingLevy['employer'],
            'total_employer_cost' => round($totalEmployerCost, 2)
        ]
    ];
}

/**
 * Save Payroll Record to Database
 */
function savePayrollRecord($conn, $periodId, $staffId, $calculation) {
    if (!$calculation['success']) {
        return false;
    }
    
    $staff    = $calculation['staff'];
    $earnings = $calculation['earnings'];
    $deductions = $calculation['deductions'];
    $employer = $calculation['employer'];
    
    // Escape strings
    $staffCode    = mysqli_real_escape_string($conn, $staff['staff_id']    ?? '');
    $staffName    = mysqli_real_escape_string($conn, $staff['name']        ?? '');
    $department   = mysqli_real_escape_string($conn, $staff['department']  ?? '');
    $jobTitle     = mysqli_real_escape_string($conn, $staff['job_title']   ?? '');
    $kraPin       = mysqli_real_escape_string($conn, $staff['kra_pin']     ?? '');
    $nssfNumber   = mysqli_real_escape_string($conn, $staff['nssf_number'] ?? '');
    $nhifNumber   = mysqli_real_escape_string($conn, $staff['nhif_number'] ?? '');
    $allowancesJson = mysqli_real_escape_string($conn, json_encode($earnings['allowances']['breakdown'] ?? []));
    
    $houseAllowance    = $earnings['allowances']['house']      ?? 0;
    $transportAllowance = $earnings['allowances']['transport'] ?? 0;
    $medicalAllowance  = $earnings['allowances']['medical']    ?? 0;
    $totalAllowances   = $earnings['allowances']['total']      ?? 0;
    
    // Check if record exists
    $existing = mysqli_query($conn, "SELECT id FROM payroll_records WHERE period_id = $periodId AND staff_id = $staffId LIMIT 1");
    
    if ($existing && mysqli_num_rows($existing) > 0) {
        // Update existing record
        $row      = mysqli_fetch_assoc($existing);
        $recordId = $row['id'];
        
        $sql = "UPDATE payroll_records SET 
            staff_code              = '$staffCode',
            staff_name              = '$staffName',
            department_name         = '$department',
            job_title               = '$jobTitle',
            kra_pin                 = '$kraPin',
            nssf_number             = '$nssfNumber',
            nhif_number             = '$nhifNumber',
            basic_salary            = {$earnings['basic_salary']},
            house_allowance         = $houseAllowance,
            transport_allowance     = $transportAllowance,
            medical_allowance       = $medicalAllowance,
            allowances_json         = '$allowancesJson',
            total_allowances        = $totalAllowances,
            overtime_amount         = {$earnings['overtime']['total']},
            bonus                   = {$earnings['bonus']},
            commission              = {$earnings['commission']},
            other_earnings          = {$earnings['other_earnings']},
            gross_pay               = {$earnings['gross_pay']},
            nssf_employee           = {$deductions['nssf_employee']},
            nssf_employer           = {$employer['nssf']},
            shif_amount             = {$deductions['shif']},
            housing_levy_employee   = {$deductions['housing_levy_employee']},
            housing_levy_employer   = {$employer['housing_levy']},
            taxable_income          = {$deductions['taxable_income']},
            tax_before_relief       = {$deductions['tax_before_relief']},
            personal_relief         = {$deductions['personal_relief']},
            insurance_relief        = {$deductions['insurance_relief']},
            paye                    = {$deductions['paye']},
            salary_advance          = {$deductions['salary_advance']},
            loan_deduction          = {$deductions['loan_deduction']},
            sacco_deduction         = {$deductions['sacco_deduction']},
            helb_deduction          = {$deductions['helb_deduction']},
            other_deductions        = {$deductions['other_deductions']},
            total_statutory_deductions = {$deductions['total_statutory']},
            total_other_deductions  = {$deductions['total_other']},
            total_deductions        = {$deductions['total_deductions']},
            net_pay                 = {$calculation['net_pay']},
            employer_nssf           = {$employer['nssf']},
            employer_housing_levy   = {$employer['housing_levy']},
            total_employer_cost     = {$employer['total_employer_cost']},
            calculated_at           = NOW()
            WHERE id = $recordId";
    } else {
        // Insert new record
        $sql = "INSERT INTO payroll_records (
            period_id, staff_id, staff_code, staff_name, department_name, job_title,
            kra_pin, nssf_number, nhif_number,
            basic_salary, house_allowance, transport_allowance, medical_allowance,
            allowances_json, total_allowances, overtime_amount, bonus, commission, other_earnings,
            gross_pay, nssf_employee, nssf_employer, shif_amount,
            housing_levy_employee, housing_levy_employer,
            taxable_income, tax_before_relief, personal_relief, insurance_relief, paye,
            salary_advance, loan_deduction, sacco_deduction, helb_deduction, other_deductions,
            total_statutory_deductions, total_other_deductions, total_deductions, net_pay,
            employer_nssf, employer_housing_levy, total_employer_cost, calculated_at
        ) VALUES (
            $periodId, $staffId, '$staffCode', '$staffName', '$department', '$jobTitle',
            '$kraPin', '$nssfNumber', '$nhifNumber',
            {$earnings['basic_salary']}, $houseAllowance,
            $transportAllowance, $medicalAllowance,
            '$allowancesJson', $totalAllowances,
            {$earnings['overtime']['total']}, {$earnings['bonus']}, {$earnings['commission']}, {$earnings['other_earnings']},
            {$earnings['gross_pay']}, {$deductions['nssf_employee']}, {$employer['nssf']}, {$deductions['shif']},
            {$deductions['housing_levy_employee']}, {$employer['housing_levy']},
            {$deductions['taxable_income']}, {$deductions['tax_before_relief']},
            {$deductions['personal_relief']}, {$deductions['insurance_relief']}, {$deductions['paye']},
            {$deductions['salary_advance']}, {$deductions['loan_deduction']},
            {$deductions['sacco_deduction']}, {$deductions['helb_deduction']}, {$deductions['other_deductions']},
            {$deductions['total_statutory']}, {$deductions['total_other']}, {$deductions['total_deductions']},
            {$calculation['net_pay']},
            {$employer['nssf']}, {$employer['housing_levy']}, {$employer['total_employer_cost']},
            NOW()
        )";
    }
    
    return mysqli_query($conn, $sql);
}

/**
 * Process Payroll for All Active Staff in a Period
 */
function processFullPayroll($conn, $periodId, $processedBy = null) {
    // Get all active staff with a salary
    $staffResult = mysqli_query($conn, "
        SELECT id FROM staff 
        WHERE onboarding_status = 'active' 
          AND basic_salary > 0
    ");
    
    if (!$staffResult) {
        return ['success' => false, 'error' => 'Failed to fetch staff'];
    }
    
    $processed = 0;
    $failed    = 0;
    $errors    = [];
    
    while ($staff = mysqli_fetch_assoc($staffResult)) {
        $staffId = $staff['id'];
        
        // Get variable inputs for this staff member in this period
        $inputResult = mysqli_query($conn, "
            SELECT * FROM payroll_inputs 
            WHERE period_id = $periodId AND staff_id = $staffId 
            LIMIT 1
        ");
        
        $inputs = [];
        if ($inputResult && mysqli_num_rows($inputResult) > 0) {
            $inputs = mysqli_fetch_assoc($inputResult);
        }
        
        // Calculate payroll
        $calculation = calculateEmployeePayroll($conn, $staffId, $periodId, $inputs);
        
        if ($calculation['success']) {
            if (savePayrollRecord($conn, $periodId, $staffId, $calculation)) {
                $processed++;
            } else {
                $failed++;
                $errors[] = "Failed to save record for staff ID: $staffId — " . mysqli_error($conn);
            }
        } else {
            $failed++;
            $errors[] = $calculation['error'] . " (Staff ID: $staffId)";
        }
    }
    
    // Update period summary totals
    updatePeriodSummary($conn, $periodId);
    
    // Mark period as processing
    mysqli_query($conn, "
        UPDATE payroll_periods 
        SET status = 'processing', 
            hr_prepared_by = " . intval($processedBy) . ", 
            hr_prepared_at = NOW() 
        WHERE id = $periodId
    ");
    
    return [
        'success'   => $failed == 0,
        'processed' => $processed,
        'failed'    => $failed,
        'errors'    => $errors
    ];
}

/**
 * Update Period Summary Totals
 */
function updatePeriodSummary($conn, $periodId) {
    $periodId = intval($periodId);
    
    mysqli_query($conn, "
        UPDATE payroll_periods pp
        SET 
            total_employees   = (SELECT COUNT(*)                    FROM payroll_records WHERE period_id = $periodId),
            total_gross       = (SELECT COALESCE(SUM(gross_pay), 0) FROM payroll_records WHERE period_id = $periodId),
            total_deductions  = (SELECT COALESCE(SUM(total_deductions), 0) FROM payroll_records WHERE period_id = $periodId),
            total_net         = (SELECT COALESCE(SUM(net_pay), 0)   FROM payroll_records WHERE period_id = $periodId),
            total_employer_costs = (SELECT COALESCE(SUM(total_employer_cost), 0) FROM payroll_records WHERE period_id = $periodId)
        WHERE id = $periodId
    ");
}

/**
 * Create New Payroll Period
 */
function createPayrollPeriod($conn, $month, $year, $createdBy = null) {
    $month = intval($month);
    $year  = intval($year);
    
    // Check if period already exists
    $existing = mysqli_query($conn, "SELECT id FROM payroll_periods WHERE period_month = $month AND period_year = $year LIMIT 1");
    if ($existing && mysqli_num_rows($existing) > 0) {
        $row = mysqli_fetch_assoc($existing);
        return ['success' => false, 'error' => 'Period already exists', 'period_id' => $row['id']];
    }
    
    $periodCode = sprintf('%04d-%02d', $year, $month);
    $periodName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
    $startDate  = sprintf('%04d-%02d-01', $year, $month);
    $endDate    = date('Y-m-t', strtotime($startDate));
    
    $createdBy = $createdBy ? intval($createdBy) : 'NULL';
    
    $sql = "INSERT INTO payroll_periods (period_code, period_name, period_month, period_year, start_date, end_date, status, created_by)
            VALUES ('$periodCode', '$periodName', $month, $year, '$startDate', '$endDate', 'draft', $createdBy)";
    
    if (mysqli_query($conn, $sql)) {
        return ['success' => true, 'period_id' => mysqli_insert_id($conn)];
    }
    
    return ['success' => false, 'error' => mysqli_error($conn)];
}