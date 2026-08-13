<?php
/**
 * bde_metrics.php — real per-BDE performance metrics.
 *
 * Attribution mirrors the proven logic in staff_performance.php:
 *   intake.assigned_to = registered_users.id (the CRM login id)
 *   register.intake_id  = intake.intake_id           (that BDE's registrations)
 *   dpo_payment.app_id  = register.entry_id, status=2 (cleared money, in USD)
 *
 * This first slice covers VIRTUAL / intake revenue only. Events (international /
 * corporate) attribute the same way via Event.assigned_to and are added later.
 */

if (!function_exists('bde_usd_to_kes')) {
    function bde_usd_to_kes($conn)
    {
        $r = @mysqli_query($conn, "SELECT setting_value FROM commission_settings WHERE setting_key = 'commission_conversion_rate' LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r)) && floatval($row['setting_value']) > 0) {
            return floatval($row['setting_value']);
        }
        return 129.0; // sensible default if the setting is missing
    }
}

if (!function_exists('bde_fetch_metrics')) {
    /**
     * @param int    $ruId        registered_users.id (the logged-in BDE)
     * @param string $start_date  'Y-m-d'
     * @param string $end_date    'Y-m-d'
     */
    function bde_fetch_metrics($conn, $ruId, $start_date, $end_date)
    {
        $ruId = (int) $ruId;
        $out = [
            'ru_id' => $ruId, 'name' => '', 'title' => '', 'dept' => '',
            'revenue_usd' => 0.0, 'revenue_kes' => 0.0,
            'paid_clients' => 0, 'total_regs' => 0,
            'start' => $start_date, 'end' => $end_date,
        ];
        if ($ruId <= 0) {
            return $out;
        }

        // --- identity ---
        $iq = @mysqli_query($conn, "SELECT ru.fullname, s.job_title, d.department_name
            FROM registered_users ru
            LEFT JOIN staff s ON ru.staff_id = s.id
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE ru.id = $ruId LIMIT 1");
        if ($iq && ($ir = mysqli_fetch_assoc($iq))) {
            $out['name']  = (string) ($ir['fullname'] ?? '');
            $out['title'] = (string) ($ir['job_title'] ?? '');
            $out['dept']  = (string) ($ir['department_name'] ?? '');
        }

        // --- cleared payment totals, keyed by app_id (= register.entry_id) ---
        $paid = [];
        $pq = @mysqli_query($conn, "SELECT app_id, SUM(TransactionAmount) AS paid
            FROM dpo_payment WHERE status = 2 GROUP BY app_id");
        while ($pq && ($pr = mysqli_fetch_assoc($pq))) {
            $paid[$pr['app_id']] = (float) $pr['paid'];
        }

        // --- intakes assigned to this BDE ---
        $intakeIds = [];
        $tq = @mysqli_query($conn, "SELECT intake_id FROM intake WHERE assigned_to = $ruId");
        while ($tq && ($tr = mysqli_fetch_assoc($tq))) {
            $intakeIds[] = $tr['intake_id'];
        }

        // --- registrations under those intakes, in range; tally cleared money + paid clients ---
        if (!empty($intakeIds)) {
            $in = implode(',', array_map(function ($x) use ($conn) {
                return "'" . mysqli_real_escape_string($conn, $x) . "'";
            }, $intakeIds));
            $s = mysqli_real_escape_string($conn, $start_date);
            $e = mysqli_real_escape_string($conn, $end_date);
            $rq = @mysqli_query($conn, "SELECT r.entry_id FROM register r
                WHERE r.intake_id IN ($in)
                AND r.datee BETWEEN '$s' AND '$e 23:59:59'");
            while ($rq && ($rr = mysqli_fetch_assoc($rq))) {
                $out['total_regs']++;
                $amt = isset($paid[$rr['entry_id']]) ? $paid[$rr['entry_id']] : 0;
                if ($amt > 0) {
                    $out['paid_clients']++;
                    $out['revenue_usd'] += $amt;
                }
            }
        }

        $out['revenue_kes'] = $out['revenue_usd'] * bde_usd_to_kes($conn);
        return $out;
    }
}
