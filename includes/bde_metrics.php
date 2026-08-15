<?php
/**
 * bde_metrics.php — real per-BDE performance metrics.
 *
 * Attribution mirrors the proven logic in staff_performance.php:
 *   VIRTUAL:  intake.assigned_to = registered_users.id → register.intake_id → dpo_payment(app_id,status=2)
 *   EVENTS:   Event.assigned_to  = registered_users.id → ticket_congress(event_id,status=2) [international + corporate]
 * All money is USD in the DB; KES via commission_settings.commission_conversion_rate.
 */

if (!function_exists('bde_usd_to_kes')) {
    function bde_usd_to_kes($conn)
    {
        $r = @mysqli_query($conn, "SELECT setting_value FROM commission_settings WHERE setting_key = 'commission_conversion_rate' LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r)) && floatval($row['setting_value']) > 0) {
            return floatval($row['setting_value']);
        }
        return 129.0;
    }
}

if (!function_exists('bde_mandate')) {
    /** Per-department strategic mandate + today's focus, chosen by department name. */
    function bde_mandate($dept)
    {
        $d = strtolower((string) $dept);
        $M = [
            'digital' => ['tag' => 'Digital Solutions', 'headline' => 'Personal performance mandate',
                'mission' => 'Turn Eval360 and 360 Appraisal into visible, trusted and fast-growing recurring-revenue solutions.',
                'detail' => 'Growth requires product mastery, direct organization engagement, aggressive demonstrations, RFP intelligence, digital demand generation, reliable self-onboarding, strong adoption and proactive maintenance and renewals.',
                'focus' => 'Move qualified organizations into demos and paid onboarding while protecting product readiness and recurring revenue.'],
            'virtual' => ['tag' => 'Virtual', 'headline' => 'Personal execution dashboard',
                'mission' => 'Convert every enquiry into a managed next step and every free session into a payment opportunity.',
                'detail' => 'The Virtual Department wins through fast response, relationship building, strong free-session attendance, human calls for hot leads, accurate automation, payment guidance and disciplined CRM follow-up.',
                'focus' => 'Call every hot lead and payment promise first; then protect free-session attendance and same-day CRM updates.'],
            'corporate' => ['tag' => 'Corporate', 'headline' => 'Personal execution dashboard',
                'mission' => 'Create institutional demand, reach decision-makers and move every account toward a commercial commitment.',
                'detail' => 'Corporate growth comes from a Top-200 account system, Top-50 priorities, discovery meetings, tailored proposals, RFP discipline, open-programme innovation, cross-SBU conversion, collections and excellent delivery.',
                'focus' => 'Advance the highest-value accounts into discovery, proposal, negotiation or deposit; no important account may sit without a dated next action.'],
            'international' => ['tag' => 'International', 'headline' => 'Personal execution dashboard',
                'mission' => 'Build organization-sponsored country pipelines first, then use automation, calls, free training, alumni and local marketers to close the remaining gap.',
                'detail' => 'Each country is a mini business unit. M&E and Data Analysis require independent pipelines, organization targets, local marketers, free-session plans, payment routes, forecasts and recovery actions.',
                'focus' => 'Move organization sponsorships and payment commitments in every country; do not allow strong countries to hide weak ones.'],
            'academ' => ['tag' => 'Academics', 'headline' => 'Personal execution dashboard',
                'mission' => 'Build the conversion machine first, then increase traffic and scale toward one million African learners.',
                'detail' => 'The department owns system readiness, a self-service customer journey, digital lead quality, paid conversion, learner activation, institutional distribution, customer feedback and preparation for learner-created-course SaaS.',
                'focus' => 'Fix any customer-journey friction immediately, protect paid conversion and activation, and expand university, college and employer channels.'],
        ];
        foreach ($M as $key => $m) {
            if ($d !== '' && strpos($d, $key) !== false) { return $m; }
        }
        return ['tag' => ($dept !== '' ? $dept : 'Business Development'), 'headline' => 'Personal execution dashboard',
            'mission' => 'Convert qualified pipeline into cleared, verified revenue with disciplined daily follow-up across every channel you own.',
            'detail' => 'Fast response, relationship building, accurate CRM follow-up and payment guidance turn attributed leads into cleared revenue.',
            'focus' => 'Advance every hot lead and payment promise to a dated next action today.'];
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
            'revenue_usd' => 0.0, 'revenue_kes' => 0.0, 'expected_usd' => 0.0, 'collection_rate' => 0.0,
            'rev_virtual_usd' => 0.0, 'rev_events_usd' => 0.0,
            'paid_clients' => 0, 'total_regs' => 0, 'units' => 0, 'stale' => 0, 'total_leads' => 0, 'contacted' => 0, 'wa_open' => 0, 'wa_unread' => 0,
            'funnel' => [], 'sources' => [],
            'commission_usd' => 0.0, 'commission_kes' => 0.0,
            'mandate' => bde_mandate(''),
            'start' => $start_date, 'end' => $end_date,
        ];
        if ($ruId <= 0) { return $out; }
        $s = mysqli_real_escape_string($conn, $start_date);
        $e = mysqli_real_escape_string($conn, $end_date);
        $rate = bde_usd_to_kes($conn);

        // --- identity + mandate ---
        $iq = @mysqli_query($conn, "SELECT ru.fullname, s.job_title, d.department_name
            FROM registered_users ru
            LEFT JOIN staff s ON ru.staff_id = s.id
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE ru.id = $ruId LIMIT 1");
        if ($iq && ($ir = mysqli_fetch_assoc($iq))) {
            $out['name'] = (string) ($ir['fullname'] ?? '');
            $out['title'] = (string) ($ir['job_title'] ?? '');
            $out['dept'] = (string) ($ir['department_name'] ?? '');
        }
        $out['mandate'] = bde_mandate($out['dept']);

        // --- cleared payment totals, keyed by app_id (= register.entry_id) ---
        $paid = [];
        $pq = @mysqli_query($conn, "SELECT app_id, SUM(TransactionAmount) AS paid FROM dpo_payment WHERE status = 2 GROUP BY app_id");
        while ($pq && ($pr = mysqli_fetch_assoc($pq))) { $paid[$pr['app_id']] = (float) $pr['paid']; }

        // --- intakes assigned to this BDE ---
        $intakeIds = [];
        $tq = @mysqli_query($conn, "SELECT intake_id FROM intake WHERE assigned_to = $ruId");
        while ($tq && ($tr = mysqli_fetch_assoc($tq))) { $intakeIds[] = $tr['intake_id']; }

        // --- VIRTUAL registrations: revenue, collection, and the register-side pipeline (their leads) ---
        $now = time();
        $fLeads = $fCont = $fQual = $fEnr = $fPaid = 0; $regStale = 0; $sources = []; $seenReg = [];
        if (!empty($intakeIds)) {
            $in = implode(',', array_map(function ($x) use ($conn) { return "'" . mysqli_real_escape_string($conn, $x) . "'"; }, $intakeIds));
            $rq = @mysqli_query($conn, "SELECT r.entry_id, r.lead_status, r.last_contact_date, c.price_usd,
                    COALESCE(NULLIF(es.name,''), NULLIF(r.source,''), 'Unknown') AS src_name
                FROM register r JOIN intake i ON r.intake_id = i.intake_id
                LEFT JOIN course c ON i.course_id = c.course_id
                LEFT JOIN enquiry_sources es ON es.id = r.source
                WHERE r.intake_id IN ($in) AND r.datee BETWEEN '$s' AND '$e 23:59:59'");
            while ($rq && ($rr = mysqli_fetch_assoc($rq))) {
                $eid = (string) $rr['entry_id'];
                if (isset($seenReg[$eid])) { continue; }   // guard: an intake_id shared by >1 intake row would else double-count
                $seenReg[$eid] = true;
                $out['total_regs']++; $fLeads++;
                $ls = strtolower(trim((string) ($rr['lead_status'] ?? '')));
                $lastc = trim((string) ($rr['last_contact_date'] ?? ''));
                $hasContact = ($lastc !== '' && $lastc !== '0000-00-00' && strtotime($lastc));
                if (!empty($rr['price_usd'])) { $out['expected_usd'] += (float) $rr['price_usd']; }
                $amt = isset($paid[$rr['entry_id']]) ? $paid[$rr['entry_id']] : 0;
                $cleared = $amt > 0;
                $isEnrolled  = $cleared || $ls === 'enrolled';
                $isQualified = $isEnrolled || $ls === 'qualified';
                $isContacted = $isQualified || $ls === 'contacted' || $hasContact;
                if ($isContacted) { $fCont++; }
                if ($isQualified) { $fQual++; }
                if ($isEnrolled) { $fEnr++; }
                if ($cleared) {
                    $fPaid++; $out['paid_clients']++; $out['revenue_usd'] += $amt; $out['rev_virtual_usd'] += $amt;
                } else if (!$hasContact || strtotime($lastc) < $now - 7 * 86400) {
                    $regStale++; // unpaid lead with no recent contact → needs a next action
                }
                $srcLabel = trim((string) ($rr['src_name'] ?? '')) !== '' ? (string) $rr['src_name'] : 'Unknown';
                $sources[$srcLabel] = ($sources[$srcLabel] ?? 0) + 1;
            }
        }

        // --- EVENTS (international + corporate): Event.assigned_to → ticket_congress (status=2) ---
        $events = [];
        $evq = @mysqli_query($conn, "SELECT event_id, COALESCE(early_amount,0) AS early FROM Event WHERE assigned_to = $ruId");
        while ($evq && ($evr = mysqli_fetch_assoc($evq))) { $events[(int) $evr['event_id']] = (float) $evr['early']; }
        if (!empty($events)) {
            $ein = implode(',', array_map('intval', array_keys($events)));
            $tq2 = @mysqli_query($conn, "SELECT event_id, COUNT(*) AS regs,
                SUM(CASE WHEN status=2 AND amount>0 THEN 1 ELSE 0 END) AS paidc,
                SUM(CASE WHEN status=2 THEN amount ELSE 0 END) AS rev
                FROM ticket_congress WHERE event_id IN ($ein) AND date_sent BETWEEN '$s' AND '$e 23:59:59' GROUP BY event_id");
            while ($tq2 && ($t2 = mysqli_fetch_assoc($tq2))) {
                $regs = (int) $t2['regs']; $pc = (int) $t2['paidc']; $rev = (float) $t2['rev'];
                $out['total_regs'] += $regs; $out['paid_clients'] += $pc;
                $out['revenue_usd'] += $rev; $out['rev_events_usd'] += $rev;
                $out['expected_usd'] += $regs * ($events[(int) $t2['event_id']] ?? 0);
                $fLeads += $regs; $fCont += $pc; $fQual += $pc; $fEnr += $pc; $fPaid += $pc;
            }
        }

        // --- ADD the `enquiries` CRM pipeline where a BDE has it (assigned_to = the BDE) ---
        // Combines with the register pipeline above so both are reflected; sources merge into one list.
        $eLeads = $eCont = $eQual = $eProp = $eConv = 0; $enqStale = 0;
        $eq2 = @mysqli_query($conn, "SELECT e.status, e.updated_at, COALESCE(NULLIF(s.name,''),'Other') src
            FROM enquiries e LEFT JOIN enquiry_sources s ON s.id = e.source_id
            WHERE e.assigned_to = $ruId AND e.created_at BETWEEN '$s' AND '$e 23:59:59'");
        while ($eq2 && ($er = mysqli_fetch_assoc($eq2))) {
            $eLeads++;
            $st = strtolower(trim((string) $er['status']));
            if (in_array($st, ['contacted', 'qualified', 'proposal_sent', 'negotiating', 'converted'], true)) { $eCont++; }
            if (in_array($st, ['qualified', 'proposal_sent', 'negotiating', 'converted'], true)) { $eQual++; }
            if (in_array($st, ['proposal_sent', 'negotiating', 'converted'], true)) { $eProp++; }
            if ($st === 'converted') { $eConv++; }
            if (in_array($st, ['contacted', 'qualified', 'proposal_sent', 'negotiating'], true)) {
                $u = strtotime((string) $er['updated_at']);
                if (!$u || $u < $now - 7 * 86400) { $enqStale++; }
            }
            $lab = (string) $er['src'];
            $sources[$lab] = ($sources[$lab] ?? 0) + 1;
        }
        // WhatsApp channel: wa_contacts has no BDE field, so attribute via register_id → intake.assigned_to.
        if (!empty($intakeIds)) {
            $waq = @mysqli_query($conn, "SELECT COUNT(*) n FROM wa_contacts wc
                JOIN register r ON r.id = wc.register_id JOIN intake i ON r.intake_id = i.intake_id
                WHERE i.assigned_to = $ruId");
            if ($waq && ($war = mysqli_fetch_assoc($waq)) && (int) $war['n'] > 0) { $sources['WhatsApp'] = ($sources['WhatsApp'] ?? 0) + (int) $war['n']; }
        }

        // WhatsApp follow-ups: open conversations assigned to this BDE with an unread message
        // (opened vs not = last_message_at newer than last_read_at, or never read).
        $wq = @mysqli_query($conn, "SELECT COUNT(*) open_chats,
            SUM(CASE WHEN last_read_at IS NULL OR last_message_at > last_read_at THEN 1 ELSE 0 END) unread
            FROM wa_conversations WHERE assigned_user_id = $ruId AND status = 'open'");
        if ($wq && ($wr = mysqli_fetch_assoc($wq))) { $out['wa_open'] = (int) $wr['open_chats']; $out['wa_unread'] = (int) $wr['unread']; }

        // --- roll-ups (register + enquiries combined) ---
        $out['units'] = $out['paid_clients'];
        $out['total_leads'] = $fLeads + $eLeads;
        $out['contacted'] = $fCont + $eCont;
        $out['stale'] = $regStale + $enqStale;
        $out['funnel'] = [['Leads', $fLeads + $eLeads], ['Contacted', $fCont + $eCont], ['Qualified', $fQual + $eQual], ['Enrolled', $fEnr + $eProp], ['Paid', $fPaid + $eConv]];
        arsort($sources);
        $srcOut = [];
        foreach (array_slice($sources, 0, 8, true) as $lab => $n) { $srcOut[] = [$lab, $n]; }
        $out['sources'] = $srcOut;
        $out['collection_rate'] = $out['expected_usd'] > 0 ? min(1.0, $out['revenue_usd'] / $out['expected_usd']) : 0.0;
        $out['revenue_kes'] = $out['revenue_usd'] * $rate;

        // --- commission from the existing commission engine (commission_records) ---
        $cq = @mysqli_query($conn, "SELECT
            COALESCE(SUM(CASE WHEN is_eligible=1 THEN commission_amount ELSE 0 END),0) AS eligible,
            COALESCE(SUM(CASE WHEN status='paid' THEN commission_amount ELSE 0 END),0) AS paid
            FROM commission_records WHERE staff_user_id = $ruId");
        if ($cq && ($cr = mysqli_fetch_assoc($cq))) {
            $out['commission_usd'] = (float) $cr['eligible'];
            $out['commission_paid_usd'] = (float) $cr['paid'];
            $out['commission_kes'] = (float) $cr['eligible'] * $rate;
        }

        return $out;
    }
}

if (!function_exists('bde_team_metrics')) {
    /** The BDE's own department team: each seller's attributed cleared revenue + paid clients. */
    function bde_team_metrics($conn, $ruId, $start_date, $end_date)
    {
        $ruId = (int) $ruId;
        $team = [];
        if ($ruId <= 0) { return $team; }
        $s = mysqli_real_escape_string($conn, $start_date);
        $e = mysqli_real_escape_string($conn, $end_date);
        $rate = bde_usd_to_kes($conn);

        $deptId = 0;
        $dq = @mysqli_query($conn, "SELECT s.department_id FROM registered_users ru JOIN staff s ON ru.staff_id = s.id WHERE ru.id = $ruId LIMIT 1");
        if ($dq && ($dr = mysqli_fetch_assoc($dq))) { $deptId = (int) $dr['department_id']; }

        // Primary: sellers whose staff record shares this department.
        $members = [];
        if ($deptId > 0) {
            $mq = @mysqli_query($conn, "SELECT ru.id, ru.fullname, s.job_title FROM registered_users ru JOIN staff s ON ru.staff_id = s.id WHERE s.department_id = $deptId ORDER BY ru.fullname");
            while ($mq && ($mr = mysqli_fetch_assoc($mq))) { $members[(int) $mr['id']] = ['name' => (string) $mr['fullname'], 'title' => (string) ($mr['job_title'] ?? ''), 'rev' => 0.0, 'clients' => 0]; }
        }
        // Fallback: department linkage incomplete (teammates not tied to staff.department_id) —
        // group by selling peers instead: everyone assigned the same way you are (intakes vs events).
        if (count($members) < 2) {
            $ci = @mysqli_query($conn, "SELECT 1 FROM intake WHERE assigned_to = $ruId LIMIT 1");
            $hasIntake = $ci && mysqli_num_rows($ci) > 0;
            $peer = @mysqli_query($conn, $hasIntake
                ? "SELECT DISTINCT assigned_to FROM intake WHERE assigned_to > 0"
                : "SELECT DISTINCT assigned_to FROM Event WHERE assigned_to > 0");
            $pids = [$ruId => true];
            while ($peer && ($pr = mysqli_fetch_assoc($peer))) { $pids[(int) $pr['assigned_to']] = true; }
            $pin = implode(',', array_map('intval', array_keys($pids)));
            $members = [];
            $mq2 = @mysqli_query($conn, "SELECT ru.id, ru.fullname, s.job_title FROM registered_users ru LEFT JOIN staff s ON ru.staff_id = s.id WHERE ru.id IN ($pin)");
            while ($mq2 && ($mr = mysqli_fetch_assoc($mq2))) { $members[(int) $mr['id']] = ['name' => (string) $mr['fullname'], 'title' => (string) ($mr['job_title'] ?? ''), 'rev' => 0.0, 'clients' => 0]; }
        }
        if (empty($members)) { return $team; }
        $ids = implode(',', array_map('intval', array_keys($members)));

        $vq = @mysqli_query($conn, "SELECT i.assigned_to ru_id, COALESCE(SUM(p.paid),0) rev, COUNT(p.paid) clients
            FROM intake i JOIN register r ON r.intake_id = i.intake_id
            JOIN (SELECT app_id, SUM(TransactionAmount) paid FROM dpo_payment WHERE status = 2 GROUP BY app_id) p ON p.app_id = r.entry_id
            WHERE i.assigned_to IN ($ids) AND r.datee BETWEEN '$s' AND '$e 23:59:59' GROUP BY i.assigned_to");
        while ($vq && ($vr = mysqli_fetch_assoc($vq))) { $id = (int) $vr['ru_id']; if (isset($members[$id])) { $members[$id]['rev'] += (float) $vr['rev']; $members[$id]['clients'] += (int) $vr['clients']; } }

        $eq = @mysqli_query($conn, "SELECT e.assigned_to ru_id, SUM(CASE WHEN tc.status=2 THEN tc.amount ELSE 0 END) rev,
            SUM(CASE WHEN tc.status=2 AND tc.amount>0 THEN 1 ELSE 0 END) clients
            FROM Event e JOIN ticket_congress tc ON tc.event_id = e.event_id
            WHERE e.assigned_to IN ($ids) AND tc.date_sent BETWEEN '$s' AND '$e 23:59:59' GROUP BY e.assigned_to");
        while ($eq && ($er = mysqli_fetch_assoc($eq))) { $id = (int) $er['ru_id']; if (isset($members[$id])) { $members[$id]['rev'] += (float) $er['rev']; $members[$id]['clients'] += (int) $er['clients']; } }

        foreach ($members as $mid => $info) {
            $team[] = ['name' => $info['name'], 'title' => $info['title'] !== '' ? $info['title'] : 'BDE', 'actual' => $info['rev'] * $rate, 'clients' => $info['clients'], 'me' => ($mid === $ruId)];
        }
        usort($team, function ($a, $b) { return $b['actual'] <=> $a['actual']; });
        return $team;
    }
}
