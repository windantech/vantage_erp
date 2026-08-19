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

if (!function_exists('bde_resolve_dept')) {
    /**
     * Resolve a BDE's department, trying every place it might live:
     *   1) registered_users.department_id
     *   2) their staff record (linked by staff_id OR email) → staff.department_id
     * Returns ['id'=>int, 'name'=>string]; id=0 if none found.
     */
    function bde_resolve_dept($conn, $ruId)
    {
        $ruId = (int) $ruId;
        $out = ['id' => 0, 'name' => ''];
        if ($ruId <= 0) { return $out; }
        // 1) directly on registered_users
        $r = @mysqli_query($conn, "SELECT ru.department_id, d.department_name
            FROM registered_users ru LEFT JOIN departments d ON ru.department_id = d.id
            WHERE ru.id = $ruId LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r)) && (int) ($row['department_id'] ?? 0) > 0) {
            return ['id' => (int) $row['department_id'], 'name' => (string) $row['department_name']];
        }
        // 2) via the staff record (staff_id or email match), where department is actually set
        $r = @mysqli_query($conn, "SELECT s.department_id, d.department_name
            FROM registered_users ru
            JOIN staff s ON (ru.staff_id = s.id OR ru.email COLLATE utf8mb4_general_ci = s.email COLLATE utf8mb4_general_ci)
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE ru.id = $ruId AND s.department_id IS NOT NULL AND s.department_id > 0
            ORDER BY (ru.staff_id = s.id) DESC LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r)) && (int) ($row['department_id'] ?? 0) > 0) {
            return ['id' => (int) $row['department_id'], 'name' => (string) $row['department_name']];
        }
        return $out;
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

if (!function_exists('bde_daily_revenue')) {
    /**
     * Cleared revenue per calendar day (KES) for a BDE over [from,to] — registrations + events.
     * Returns ['Y-m-d' => kes]. Used to draw the real month-to-date revenue trajectory.
     */
    function bde_daily_revenue($conn, $ruId, $from, $to)
    {
        $ruId = (int) $ruId; $out = [];
        if ($ruId <= 0) { return $out; }
        $rate = function_exists('bde_usd_to_kes') ? bde_usd_to_kes($conn) : 129.0;
        $s = mysqli_real_escape_string($conn, $from);
        $e = mysqli_real_escape_string($conn, $to);
        $intakeIds = [];
        $iq = @mysqli_query($conn, "SELECT intake_id FROM intake WHERE assigned_to = $ruId");
        while ($iq && ($ir = mysqli_fetch_assoc($iq))) { $intakeIds[(string) $ir['intake_id']] = true; }
        if (!empty($intakeIds)) {
            $in = implode(',', array_map(function ($x) use ($conn) { return "'" . mysqli_real_escape_string($conn, $x) . "'"; }, array_keys($intakeIds)));
            $q = @mysqli_query($conn, "SELECT DATE(dp.datee) d, SUM(dp.TransactionAmount) amt
                FROM dpo_payment dp JOIN register r ON r.entry_id = dp.app_id
                WHERE dp.status = 2 AND r.intake_id IN ($in) AND dp.datee BETWEEN '$s' AND '$e 23:59:59'
                GROUP BY DATE(dp.datee)");
            while ($q && ($r = mysqli_fetch_assoc($q))) { $out[(string) $r['d']] = ($out[(string) $r['d']] ?? 0) + (float) $r['amt'] * $rate; }
        }
        $evIds = [];
        $evq = @mysqli_query($conn, "SELECT event_id FROM Event WHERE FIND_IN_SET('$ruId', REPLACE(assigned_to,' ','')) > 0");
        while ($evq && ($er = mysqli_fetch_assoc($evq))) { $evIds[] = (int) $er['event_id']; }
        if (!empty($evIds)) {
            $ein = implode(',', $evIds);
            $q = @mysqli_query($conn, "SELECT DATE(date_sent) d, SUM(CASE WHEN status=2 THEN amount ELSE 0 END) amt
                FROM ticket_congress WHERE event_id IN ($ein) AND date_sent BETWEEN '$s' AND '$e 23:59:59' GROUP BY DATE(date_sent)");
            while ($q && ($r = mysqli_fetch_assoc($q))) { $out[(string) $r['d']] = ($out[(string) $r['d']] ?? 0) + (float) $r['amt'] * $rate; }
        }
        return $out;
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
        $iq = @mysqli_query($conn, "SELECT ru.fullname, s.job_title
            FROM registered_users ru
            LEFT JOIN staff s ON (ru.email COLLATE utf8mb4_general_ci = s.email COLLATE utf8mb4_general_ci OR ru.staff_id = s.id)
            WHERE ru.id = $ruId LIMIT 1");
        if ($iq && ($ir = mysqli_fetch_assoc($iq))) {
            $out['name'] = (string) ($ir['fullname'] ?? '');
            $out['title'] = (string) ($ir['job_title'] ?? '');
        }
        $rdI = bde_resolve_dept($conn, $ruId);
        $out['dept'] = (string) $rdI['name'];
        $out['mandate'] = bde_mandate($out['dept']);

        // --- cleared payment totals, keyed by app_id (= register.entry_id) ---
        $paid = [];
        $pq = @mysqli_query($conn, "SELECT app_id, SUM(TransactionAmount) AS paid FROM dpo_payment WHERE status = 2 GROUP BY app_id");
        while ($pq && ($pr = mysqli_fetch_assoc($pq))) { $paid[$pr['app_id']] = (float) $pr['paid']; }

        // --- intakes assigned to this BDE ---
        $intakeIds = []; $intakePrice = [];
        $tq = @mysqli_query($conn, "SELECT i.intake_id, c.price_usd FROM intake i LEFT JOIN course c ON i.course_id = c.course_id WHERE i.assigned_to = $ruId");
        while ($tq && ($tr = mysqli_fetch_assoc($tq))) {
            $iid = (string) $tr['intake_id'];
            $intakeIds[$iid] = true;   // set-keys de-dupe repeated intake_ids
            if ($tr['price_usd'] !== null && !isset($intakePrice[$iid])) { $intakePrice[$iid] = (float) $tr['price_usd']; }
        }
        $intakeIds = array_keys($intakeIds);

        // --- VIRTUAL registrations: revenue, collection, and the register-side pipeline (their leads) ---
        $now = time();
        $fLeads = $fCont = $fQual = $fEnr = $fPaid = 0; $regStale = 0; $sources = []; $seenReg = [];
        if (!empty($intakeIds)) {
            $in = implode(',', array_map(function ($x) use ($conn) { return "'" . mysqli_real_escape_string($conn, $x) . "'"; }, $intakeIds));
            // No JOIN to intake here — that multiplied a registration once per intake sharing its intake_id.
            // Filter by intake_id (one row per registration); course price comes from the $intakePrice map.
            $rq = @mysqli_query($conn, "SELECT r.entry_id, r.intake_id, r.lead_status, r.last_contact_date,
                    COALESCE(NULLIF(es.name,''), NULLIF(r.source,''), 'Unknown') AS src_name
                FROM register r LEFT JOIN enquiry_sources es ON es.id = r.source
                WHERE r.intake_id IN ($in) AND r.datee BETWEEN '$s' AND '$e 23:59:59'");
            while ($rq && ($rr = mysqli_fetch_assoc($rq))) {
                $eid = (string) $rr['entry_id'];
                if (isset($seenReg[$eid])) { continue; }   // defensive: skip any literal duplicate register row
                $seenReg[$eid] = true;
                $out['total_regs']++; $fLeads++;
                $ls = strtolower(trim((string) ($rr['lead_status'] ?? '')));
                $lastc = trim((string) ($rr['last_contact_date'] ?? ''));
                $hasContact = ($lastc !== '' && $lastc !== '0000-00-00' && strtotime($lastc));
                $unitPrice = $intakePrice[(string) $rr['intake_id']] ?? 0;
                if ($unitPrice > 0) { $out['expected_usd'] += $unitPrice; }
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
        $evq = @mysqli_query($conn, "SELECT event_id, COALESCE(early_amount,0) AS early FROM Event WHERE FIND_IN_SET('$ruId', REPLACE(assigned_to,' ','')) > 0");
        while ($evq && ($evr = mysqli_fetch_assoc($evq))) { $events[(int) $evr['event_id']] = (float) $evr['early']; }
        if (!empty($events)) {
            $ein = implode(',', array_map('intval', array_keys($events)));
            $tq2 = @mysqli_query($conn, "SELECT event_id, COUNT(*) AS regs,
                SUM(CASE WHEN status=2 AND amount>0 THEN 1 ELSE 0 END) AS paidc,
                SUM(CASE WHEN status=2 THEN amount ELSE 0 END) AS rev,
                SUM(CASE WHEN lead_status IN ('contacted','qualified','registered','attended') THEN 1 ELSE 0 END) AS contactedc,
                SUM(CASE WHEN lead_status IN ('qualified','registered','attended') THEN 1 ELSE 0 END) AS qualifiedc,
                SUM(CASE WHEN lead_status IN ('registered','attended') THEN 1 ELSE 0 END) AS enrolledc
                FROM ticket_congress WHERE event_id IN ($ein) AND date_sent BETWEEN '$s' AND '$e 23:59:59' GROUP BY event_id");
            while ($tq2 && ($t2 = mysqli_fetch_assoc($tq2))) {
                $regs = (int) $t2['regs']; $pc = (int) $t2['paidc']; $rev = (float) $t2['rev'];
                $out['total_regs'] += $regs; $out['paid_clients'] += $pc;
                $out['revenue_usd'] += $rev; $out['rev_events_usd'] += $rev;
                $out['expected_usd'] += $regs * ($events[(int) $t2['event_id']] ?? 0);
                // real staged funnel from ticket_congress.lead_status (paid always counts as reached)
                $fLeads += $regs;
                $fCont += max((int) $t2['contactedc'], $pc);
                $fQual += max((int) $t2['qualifiedc'], $pc);
                $fEnr  += max((int) $t2['enrolledc'], $pc);
                $fPaid += $pc;
                $sources['Event registration'] = ($sources['Event registration'] ?? 0) + $regs;
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
        // WhatsApp channel: only chats ESCALATED to this BDE count as leads (routed for a human) —
        // not every AI-handled conversation. This keeps the lead count realistic.
        $waq = @mysqli_query($conn, "SELECT COUNT(*) n FROM wa_conversations
            WHERE assigned_user_id = $ruId AND escalated = 1 AND created_at BETWEEN '$s' AND '$e 23:59:59'");
        if ($waq && ($war = mysqli_fetch_assoc($waq)) && (int) $war['n'] > 0) { $sources['WhatsApp'] = ($sources['WhatsApp'] ?? 0) + (int) $war['n']; }

        // WhatsApp follow-ups: open conversations assigned to this BDE with an unread message
        // (opened vs not = last_message_at newer than last_read_at, or never read).
        $wq = @mysqli_query($conn, "SELECT COUNT(*) open_chats,
            SUM(CASE WHEN last_read_at IS NULL OR last_message_at > last_read_at THEN 1 ELSE 0 END) unread
            FROM wa_conversations WHERE assigned_user_id = $ruId AND status = 'open'");
        if ($wq && ($wr = mysqli_fetch_assoc($wq))) { $out['wa_open'] = (int) $wr['open_chats']; $out['wa_unread'] = (int) $wr['unread']; }

        // --- roll-ups (register + enquiries combined) ---
        $out['units'] = $out['paid_clients'];
        // Total leads = every source channel combined (registrations + enquiries + WhatsApp chats),
        // so the funnel's "Leads" reconciles with the lead-source breakdown.
        $leadTotal = array_sum($sources);
        $out['total_leads'] = $leadTotal;
        $out['contacted'] = $fCont + $eCont;
        $out['stale'] = $regStale + $enqStale;
        // Two honest stages — Contacted/Qualified/Enrolled aren't reliably tracked for registrations.
        $out['funnel'] = [['Leads', $leadTotal], ['Paid', $fPaid + $eConv]];
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

if (!function_exists('bde_active_since')) {
    /**
     * Earliest real activity date for a BDE — first registration (on their intakes),
     * first enquiry assigned to them, or first ticket sale on their events. This is the
     * point they actually started working, taken straight from the DB (not a fixed window).
     * Returns 'Y-m-d', or '' if they have no activity yet.
     */
    function bde_active_since($conn, $ruId)
    {
        $ruId = (int) $ruId;
        if ($ruId <= 0) { return ''; }
        $dates = [];

        // first registration on an intake assigned to them
        $intakeIds = [];
        $tq = @mysqli_query($conn, "SELECT intake_id FROM intake WHERE assigned_to = $ruId");
        while ($tq && ($tr = mysqli_fetch_assoc($tq))) { $intakeIds[(string) $tr['intake_id']] = true; }
        if (!empty($intakeIds)) {
            $in = implode(',', array_map(function ($x) use ($conn) { return "'" . mysqli_real_escape_string($conn, $x) . "'"; }, array_keys($intakeIds)));
            $r = @mysqli_query($conn, "SELECT MIN(datee) d FROM register WHERE intake_id IN ($in) AND datee > '1971-01-01'");
            if ($r && ($row = mysqli_fetch_assoc($r)) && !empty($row['d'])) { $dates[] = (string) $row['d']; }
        }
        // first enquiry assigned to them
        $r = @mysqli_query($conn, "SELECT MIN(created_at) d FROM enquiries WHERE assigned_to = $ruId AND created_at > '1971-01-01'");
        if ($r && ($row = mysqli_fetch_assoc($r)) && !empty($row['d'])) { $dates[] = (string) $row['d']; }
        // first ticket sale on an event assigned to them
        $evIds = [];
        $evq = @mysqli_query($conn, "SELECT event_id FROM Event WHERE FIND_IN_SET('$ruId', REPLACE(assigned_to,' ','')) > 0");
        while ($evq && ($evr = mysqli_fetch_assoc($evq))) { $evIds[] = (int) $evr['event_id']; }
        if (!empty($evIds)) {
            $ein = implode(',', $evIds);
            $r = @mysqli_query($conn, "SELECT MIN(date_sent) d FROM ticket_congress WHERE event_id IN ($ein) AND date_sent > '1971-01-01'");
            if ($r && ($row = mysqli_fetch_assoc($r)) && !empty($row['d'])) { $dates[] = (string) $row['d']; }
        }

        if (empty($dates)) { return ''; }
        sort($dates);
        return substr($dates[0], 0, 10); // 'Y-m-d'
    }
}

if (!function_exists('bde_digital_roster')) {
    /**
     * The Digital Solutions BDEs, by name. They have no CRM assignments (their products —
     * Eval360, 360 Appraisal — aren't tracked in intake/register/event), so their team roster
     * is an explicit list rather than derived from attribution. Names are matched with LIKE
     * against registered_users.fullname. Edit this list to add / correct a member.
     */
    function bde_digital_roster()
    {
        return ['Austin', 'Ruth', 'Alein']; // Eval360 · 360 Appraisal · (3rd product TBD)
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

        // Resolve the viewed BDE's identity + department (department may live on registered_users
        // OR on their staff record — bde_resolve_dept tries both).
        $rd = bde_resolve_dept($conn, $ruId);
        $deptId = (int) $rd['id']; $deptName = (string) $rd['name']; $meName = '';
        $dq = @mysqli_query($conn, "SELECT fullname FROM registered_users WHERE id = $ruId LIMIT 1");
        if ($dq && ($dr = mysqli_fetch_assoc($dq))) { $meName = (string) ($dr['fullname'] ?? ''); }

        // Digital Solutions: its BDEs exist as users but have NO intake/event assignments (their
        // products — Eval360, 360 Appraisal — aren't tracked in the CRM revenue tables). So the
        // department-dump / selling-peer logic below surfaces the wrong people. For Digital we use
        // an explicit named roster instead. (Actuals stay 0 until a Digital data source exists.)
        $digitalRoster = bde_digital_roster();
        $isDigital = stripos($deptName, 'digital') !== false;
        if (!$isDigital && $meName !== '') {
            foreach ($digitalRoster as $dn) { if (stripos($meName, $dn) !== false) { $isDigital = true; break; } }
        }

        $members = [];
        if ($isDigital) {
            foreach ($digitalRoster as $dn) {
                $like = mysqli_real_escape_string($conn, $dn);
                $nq = @mysqli_query($conn, "SELECT ru.id, ru.fullname, s.job_title
                    FROM registered_users ru LEFT JOIN staff s ON ru.staff_id = s.id
                    WHERE ru.fullname LIKE '%$like%' ORDER BY ru.id LIMIT 1");
                if ($nq && ($nr = mysqli_fetch_assoc($nq))) {
                    $t = (string) ($nr['job_title'] ?? '');
                    $members[(int) $nr['id']] = ['name' => (string) $nr['fullname'], 'title' => $t !== '' ? $t : 'BDE', 'rev' => 0.0, 'clients' => 0];
                }
            }
            // Always include the viewed BDE even if their name isn't in the roster list.
            if (!isset($members[$ruId]) && $meName !== '') {
                $members[$ruId] = ['name' => $meName, 'title' => 'BDE', 'rev' => 0.0, 'clients' => 0];
            }
        } else {
            // Per-person cohort (preferred): your team = everyone who shares your target group — all
            // "International" people, all "Corporate" people, or all Virtual course owners. Built from
            // the seeded user-scoped targets, so it needs NO department data.
            $cohortProducts = []; $cohortCourse = false;
            $mq0 = @mysqli_query($conn, "SELECT DISTINCT product, metric_label FROM bde_targets WHERE scope_type='user' AND scope_ref='$ruId'");
            while ($mq0 && ($m0 = mysqli_fetch_assoc($mq0))) {
                $p = (string) $m0['product'];
                if (in_array($p, ['International', 'Corporate'], true)) { $cohortProducts[$p] = true; }
                if ((string) $m0['metric_label'] === 'Course revenue') { $cohortCourse = true; }
            }
            $cohortIds = [];
            if (!empty($cohortProducts)) {
                $pin = implode(',', array_map(function ($p) use ($conn) { return "'" . mysqli_real_escape_string($conn, $p) . "'"; }, array_keys($cohortProducts)));
                $cq = @mysqli_query($conn, "SELECT DISTINCT scope_ref FROM bde_targets WHERE scope_type='user' AND product IN ($pin) AND metric NOT IN ('dept_revenue','dept_participants')");
                while ($cq && ($cr = mysqli_fetch_assoc($cq))) { $cohortIds[(int) $cr['scope_ref']] = true; }
            }
            if ($cohortCourse) {
                $cq = @mysqli_query($conn, "SELECT DISTINCT scope_ref FROM bde_targets WHERE scope_type='user' AND metric_label='Course revenue'");
                while ($cq && ($cr = mysqli_fetch_assoc($cq))) { $cohortIds[(int) $cr['scope_ref']] = true; }
            }

            if (!empty($cohortIds)) {
                $cin = implode(',', array_map('intval', array_keys($cohortIds)));
                $mq = @mysqli_query($conn, "SELECT ru.id, ru.fullname, COALESCE(s.job_title,'') job_title
                    FROM registered_users ru
                    LEFT JOIN staff s ON (ru.staff_id = s.id OR ru.email COLLATE utf8mb4_general_ci = s.email COLLATE utf8mb4_general_ci)
                    WHERE ru.id IN ($cin)");
                while ($mq && ($mr = mysqli_fetch_assoc($mq))) { $members[(int) $mr['id']] = ['name' => (string) $mr['fullname'], 'title' => (string) ($mr['job_title'] ?? ''), 'rev' => 0.0, 'clients' => 0]; }
            } else {
                // No cohort: try the department's staff (if a department is set)...
                if ($deptId > 0) {
                    $mq = @mysqli_query($conn, "SELECT ru.id, ru.fullname, COALESCE(s.job_title,'') job_title
                        FROM staff s
                        JOIN registered_users ru ON (ru.staff_id = s.id OR ru.email COLLATE utf8mb4_general_ci = s.email COLLATE utf8mb4_general_ci) AND ru.status = 1
                        WHERE s.department_id = $deptId ORDER BY ru.fullname");
                    while ($mq && ($mr = mysqli_fetch_assoc($mq))) { $members[(int) $mr['id']] = ['name' => (string) $mr['fullname'], 'title' => (string) ($mr['job_title'] ?? ''), 'rev' => 0.0, 'clients' => 0]; }
                }
                // ...else fall back to selling peers (everyone assigned the same way you are).
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
            }
        }
        if (empty($members)) { return $team; }
        $ids = implode(',', array_map('intval', array_keys($members)));

        $vq = @mysqli_query($conn, "SELECT i.assigned_to ru_id, COALESCE(SUM(p.paid),0) rev, COUNT(p.paid) clients
            FROM intake i JOIN register r ON r.intake_id = i.intake_id
            JOIN (SELECT app_id, SUM(TransactionAmount) paid FROM dpo_payment WHERE status = 2 GROUP BY app_id) p ON p.app_id = r.entry_id
            WHERE i.assigned_to IN ($ids) AND r.datee BETWEEN '$s' AND '$e 23:59:59' GROUP BY i.assigned_to");
        while ($vq && ($vr = mysqli_fetch_assoc($vq))) { $id = (int) $vr['ru_id']; if (isset($members[$id])) { $members[$id]['rev'] += (float) $vr['rev']; $members[$id]['clients'] += (int) $vr['clients']; } }

        // Event.assigned_to is a comma-list varchar (e.g. '94,100,121'), so IN()/= won't match a co-led
        // training. Restrict to events involving any member, group per event, then credit each co-assignee.
        $finds = implode(' OR ', array_map(function ($mid) { return "FIND_IN_SET('" . (int) $mid . "', REPLACE(e.assigned_to,' ','')) > 0"; }, array_keys($members)));
        $eq = @mysqli_query($conn, "SELECT e.assigned_to alist, SUM(CASE WHEN tc.status=2 THEN tc.amount ELSE 0 END) rev,
            SUM(CASE WHEN tc.status=2 AND tc.amount>0 THEN 1 ELSE 0 END) clients
            FROM Event e JOIN ticket_congress tc ON tc.event_id = e.event_id
            WHERE ($finds) AND tc.date_sent BETWEEN '$s' AND '$e 23:59:59' GROUP BY e.event_id, e.assigned_to");
        while ($eq && ($er = mysqli_fetch_assoc($eq))) {
            foreach (explode(',', str_replace(' ', '', (string) $er['alist'])) as $aid) {
                $aid = (int) $aid;
                if ($aid > 0 && isset($members[$aid])) { $members[$aid]['rev'] += (float) $er['rev']; $members[$aid]['clients'] += (int) $er['clients']; }
            }
        }

        // Each member's own monthly revenue target (for the "vs target" column).
        $mtarget = [];
        $yy = (int) date('Y', strtotime($end_date)); $mm = (int) date('n', strtotime($end_date));
        $tq = @mysqli_query($conn, "SELECT scope_ref, SUM(target_value) t FROM bde_targets
            WHERE scope_type='user' AND metric='revenue' AND scope_ref IN ($ids)
            AND active=1 AND (period_year IS NULL OR (period_year=$yy AND period_month=$mm)) GROUP BY scope_ref");
        while ($tq && ($tr = mysqli_fetch_assoc($tq))) { $mtarget[(int) $tr['scope_ref']] = (float) $tr['t']; }

        // Merge duplicate logins that share a name (e.g. two "Josiah Mwangi") into one row so the
        // team lists each person once, combining their figures.
        $byName = [];
        foreach ($members as $mid => $info) {
            $key = strtolower(preg_replace('/\s+/', ' ', trim((string) $info['name'])));
            if ($key === '') { $key = 'id' . $mid; }
            if (!isset($byName[$key])) {
                $byName[$key] = ['name' => $info['name'], 'title' => $info['title'] !== '' ? $info['title'] : 'BDE', 'actual' => 0.0, 'target' => 0.0, 'clients' => 0, 'me' => false];
            }
            $byName[$key]['actual'] += $info['rev'] * $rate;
            // target is the same seed on each duplicate login — take it ONCE (max), never summed
            // (else Josiah #69+#98 shows 4M instead of his 2M).
            $byName[$key]['target'] = max($byName[$key]['target'], (float) ($mtarget[$mid] ?? 0));
            $byName[$key]['clients'] += $info['clients'];
            if ($mid === $ruId) { $byName[$key]['me'] = true; }
        }
        $team = array_values($byName);
        usort($team, function ($a, $b) { return $b['actual'] <=> $a['actual']; });
        return $team;
    }
}

if (!function_exists('bde_targets_progress')) {
    /**
     * Applicable targets for a BDE — their own (scope=user) plus their department's defaults
     * (scope=department) — for the month of $to, with the derived threshold value and, where the
     * CRM can measure it, the actual collected revenue (per course when the target names one).
     * Everything returned in KES. Returns:
     *   ['rows'=>[ {scope,scope_label,product,metric,metric_label,unit,target,threshold_pct,
     *               threshold_value,actual (nullable),notes} ... ],
     *    'revenue_target'=>float, 'revenue_actual'=>float]
     */
    function bde_targets_progress($conn, $ruId, $deptName, $from, $to)
    {
        $ruId = (int) $ruId;
        $out = ['rows' => [], 'revenue_target' => 0.0, 'revenue_actual' => 0.0];
        if ($ruId <= 0) { return $out; }

        // table may not exist yet (bde_targets.php creates it on first visit)
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'bde_targets'");
        if (!$chk || mysqli_num_rows($chk) === 0) { return $out; }

        $rate = function_exists('bde_usd_to_kes') ? bde_usd_to_kes($conn) : 129.0;

        // Resolve the BDE's department (id + name) and their name. Some BDEs aren't linked to a
        // department in staff, so we also match department targets by NAME, and fall back to the
        // Digital roster for the unlinked Digital three.
        $rd = bde_resolve_dept($conn, $ruId);
        $deptId = (int) $rd['id']; $deptName2 = (string) $rd['name']; $meName = '';
        $dq = @mysqli_query($conn, "SELECT fullname FROM registered_users WHERE id = $ruId LIMIT 1");
        if ($dq && ($dr = mysqli_fetch_assoc($dq))) {
            $meName = (string) ($dr['fullname'] ?? '');
        }
        if ($deptName2 === '' && (string) $deptName !== '') { $deptName2 = (string) $deptName; }
        if ($deptName2 === '' && function_exists('bde_digital_roster') && $meName !== '') {
            foreach (bde_digital_roster() as $dn) { if (stripos($meName, $dn) !== false) { $deptName2 = 'Digital Solutions'; break; } }
        }

        // Digital BDEs each own ONE product (Austin→Eval360, Ruth→360 Appraisal), so a Digital BDE
        // should see only their product's department targets — not the whole department's.
        $digitalProduct = '';
        if (stripos($deptName2, 'digital') !== false && $meName !== '') {
            if (stripos($meName, 'austin') !== false) { $digitalProduct = 'eval'; }
            elseif (stripos($meName, 'ruth') !== false) { $digitalProduct = 'appraisal'; }
        }

        $y = (int) date('Y', strtotime($to));
        $m = (int) date('n', strtotime($to));

        // Load this BDE's own (user) targets plus ALL department targets, then decide the department
        // match in PHP — avoids SQL collation-mix errors from comparing labels to literals.
        $tq = @mysqli_query($conn, "SELECT * FROM bde_targets
            WHERE active=1 AND (period_year IS NULL OR (period_year=$y AND period_month=$m))
            AND ((scope_type='user' AND scope_ref='$ruId') OR scope_type='department')
            ORDER BY scope_type DESC, id");
        $dnLower = strtolower(trim($deptName2));
        $targets = [];
        while ($tq && ($tr = mysqli_fetch_assoc($tq))) {
            if ($tr['scope_type'] === 'user') { $targets[] = $tr; continue; } // SQL already scoped to this BDE
            // department target: keep if it matches this BDE's department (by id, or name either direction)
            $match = ($deptId > 0 && (int) $tr['scope_ref'] === $deptId);
            if (!$match && $dnLower !== '') {
                $sl = strtolower(trim((string) $tr['scope_label']));
                $match = ($sl !== '' && ($sl === $dnLower || strpos($sl, $dnLower) !== false || strpos($dnLower, $sl) !== false));
            }
            if (!$match) { continue; }
            // Digital BDEs each own one product — keep only their product's department rows
            if ($digitalProduct !== '') {
                $p = strtolower((string) $tr['product']);
                if ($p !== '' && strpos($p, $digitalProduct) === false) { continue; }
            }
            $targets[] = $tr;
        }
        if (empty($targets)) { return $out; }

        // --- actuals: collected revenue (USD) over [from,to], total + per course name ---
        $s = mysqli_real_escape_string($conn, $from);
        $e = mysqli_real_escape_string($conn, $to);
        $paid = [];
        $pq = @mysqli_query($conn, "SELECT app_id, SUM(TransactionAmount) paid FROM dpo_payment WHERE status = 2 GROUP BY app_id");
        while ($pq && ($pr = mysqli_fetch_assoc($pq))) { $paid[(string) $pr['app_id']] = (float) $pr['paid']; }

        $intakeCourse = []; $intakeIds = [];
        $iq = @mysqli_query($conn, "SELECT i.intake_id, c.course cname FROM intake i LEFT JOIN course c ON i.course_id = c.course_id WHERE i.assigned_to = $ruId");
        while ($iq && ($ir = mysqli_fetch_assoc($iq))) { $iid = (string) $ir['intake_id']; $intakeIds[$iid] = true; $intakeCourse[$iid] = strtolower(trim((string) ($ir['cname'] ?? ''))); }

        $courseRevUsd = []; $totalUsd = 0.0; $seen = [];
        if (!empty($intakeIds)) {
            $in = implode(',', array_map(function ($x) use ($conn) { return "'" . mysqli_real_escape_string($conn, $x) . "'"; }, array_keys($intakeIds)));
            $rq = @mysqli_query($conn, "SELECT entry_id, intake_id FROM register WHERE intake_id IN ($in) AND datee BETWEEN '$s' AND '$e 23:59:59'");
            while ($rq && ($rr = mysqli_fetch_assoc($rq))) {
                $eid = (string) $rr['entry_id']; if (isset($seen[$eid])) { continue; } $seen[$eid] = true;
                $amt = $paid[$eid] ?? 0; if ($amt <= 0) { continue; }
                $cn = $intakeCourse[(string) $rr['intake_id']] ?? '';
                if ($cn !== '') { $courseRevUsd[$cn] = ($courseRevUsd[$cn] ?? 0) + $amt; }
                $totalUsd += $amt;
            }
        }
        // events (international/corporate) → add to total collected
        $evIds = [];
        $evq = @mysqli_query($conn, "SELECT event_id FROM Event WHERE FIND_IN_SET('$ruId', REPLACE(assigned_to,' ','')) > 0");
        while ($evq && ($er = mysqli_fetch_assoc($evq))) { $evIds[] = (int) $er['event_id']; }
        if (!empty($evIds)) {
            $ein = implode(',', $evIds);
            $tq2 = @mysqli_query($conn, "SELECT SUM(CASE WHEN status=2 THEN amount ELSE 0 END) rev FROM ticket_congress WHERE event_id IN ($ein) AND date_sent BETWEEN '$s' AND '$e 23:59:59'");
            if ($tq2 && ($t2 = mysqli_fetch_assoc($tq2))) { $totalUsd += (float) $t2['rev']; }
        }
        $totalKes = $totalUsd * $rate;

        // --- build rows ---
        $revTarget = 0.0;
        foreach ($targets as $t) {
            $metric = (string) $t['metric'];
            $unit   = (string) $t['unit'];
            $valRaw = (float) $t['target_value'];
            $target = ($unit === 'USD') ? $valRaw * $rate : $valRaw; // display everything in KES/count
            $unitOut = ($unit === 'USD') ? 'KES' : $unit;
            $thp = $t['threshold_pct'] !== null ? (float) $t['threshold_pct'] : null;
            $thv = $thp !== null ? $target * $thp / 100 : null;

            $actual = null;
            if ($metric === 'revenue') {
                $revTarget += $target;
                $product = (string) $t['product'];
                $pl = strtolower($product);
                if ($product === '' || $product === 'Corporate' || $product === 'International') {
                    $actual = $totalKes; // whole-revenue target → all collected revenue
                } else if (strpos($pl, 'eval') !== false || strpos($pl, 'appraisal') !== false) {
                    $actual = null; // Digital: not tracked in the CRM
                } else {
                    // Virtual course: match the course named in the product to its collected revenue
                    $pname = strtolower(trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $product)));
                    $matchUsd = null;
                    foreach ($courseRevUsd as $cn => $rv) {
                        if ($cn !== '' && ($cn === $pname || strpos($cn, $pname) !== false || strpos($pname, $cn) !== false)) { $matchUsd = ($matchUsd ?? 0) + $rv; }
                    }
                    if ($matchUsd !== null) { $actual = $matchUsd * $rate; }
                }
            }

            $out['rows'][] = [
                'scope' => (string) $t['scope_type'],
                'scope_label' => (string) $t['scope_label'],
                'product' => (string) $t['product'],
                'metric' => $metric,
                'metric_label' => (string) ($t['metric_label'] ?: $metric),
                'unit' => $unitOut,
                'target' => $target,
                'threshold_pct' => $thp,
                'threshold_value' => $thv,
                'actual' => $actual,
                'notes' => (string) $t['notes'],
            ];
        }
        $out['revenue_target'] = $revTarget;
        $out['revenue_actual'] = $totalKes;
        return $out;
    }
}

if (!function_exists('bdo_rollup')) {
    /**
     * Department roll-up for a BDO/HOD dashboard. Takes the BDO's registered_users.id, reads their
     * seeded department-total target (metric dept_revenue / dept_participants), resolves the department's
     * BDEs from bde_targets (by product family), and aggregates each BDE's REAL attributed numbers
     * (via bde_fetch_metrics — so the event comma-list fix + all attribution logic is reused).
     *
     * Returns: name, title, dept, metric ('revenue'|'participants'), unit, target, threshold (value),
     *          actual, clients, pipeline (KES), collection (0..1), team[] (per BDE, revenue departments).
     */
    function bdo_rollup($conn, $bdoId, $from, $to)
    {
        $bdoId = (int) $bdoId;
        $out = ['name' => '', 'title' => 'BDO', 'dept' => '', 'metric' => 'revenue', 'unit' => 'KES',
            'target' => 0.0, 'threshold' => 0.0, 'actual' => 0.0, 'clients' => 0, 'pipeline' => 0.0,
            'collection' => 0.0, 'team' => [], 'members' => 0];
        if ($bdoId <= 0) { return $out; }
        $rate = function_exists('bde_usd_to_kes') ? bde_usd_to_kes($conn) : 129.0;

        // identity
        $iq = @mysqli_query($conn, "SELECT ru.fullname, s.job_title FROM registered_users ru
            LEFT JOIN staff s ON (ru.email COLLATE utf8mb4_general_ci = s.email COLLATE utf8mb4_general_ci OR ru.staff_id = s.id)
            WHERE ru.id = $bdoId LIMIT 1");
        if ($iq && ($ir = mysqli_fetch_assoc($iq))) { $out['name'] = (string) $ir['fullname']; $out['title'] = (string) ($ir['job_title'] ?: 'BDO'); }

        // department-total target
        $tq = @mysqli_query($conn, "SELECT product, metric, unit, target_value, threshold_pct FROM bde_targets
            WHERE scope_type='user' AND scope_ref='$bdoId' AND metric IN ('dept_revenue','dept_participants') ORDER BY id LIMIT 1");
        if ($tq && ($tr = mysqli_fetch_assoc($tq))) {
            $out['dept'] = (string) $tr['product'];
            $out['metric'] = $tr['metric'] === 'dept_participants' ? 'participants' : 'revenue';
            $out['unit'] = (string) $tr['unit'];
            $out['target'] = (float) $tr['target_value'];
            $out['threshold'] = $tr['threshold_pct'] !== null ? (float) $tr['target_value'] * (float) $tr['threshold_pct'] / 100.0 : 0.0;
        }

        // resolve the department's BDEs by product family in bde_targets (user-scoped, excluding the BDO)
        $dl = strtolower($out['dept']);
        $filter = '';
        if (strpos($dl, 'corporate') !== false) { $filter = "product='Corporate'"; }
        elseif (strpos($dl, 'international') !== false) { $filter = "product='International'"; }
        elseif (strpos($dl, 'virtual') !== false) { $filter = "metric_label='Course revenue'"; }
        elseif (strpos($dl, 'digital') !== false) { $filter = "(product LIKE 'Eval%' OR product LIKE '%Appraisal%')"; }

        $bdeIds = [];
        if ($filter !== '') {
            $q = @mysqli_query($conn, "SELECT DISTINCT scope_ref FROM bde_targets
                WHERE scope_type='user' AND $filter AND metric NOT IN ('dept_revenue','dept_participants') AND scope_ref <> '$bdoId'");
            while ($q && ($r = mysqli_fetch_assoc($q))) { $bdeIds[] = (int) $r['scope_ref']; }
        }

        // each BDE's own revenue target (for the team table's vs-target column)
        $memTarget = [];
        if (!empty($bdeIds)) {
            $in = implode(',', array_map('intval', $bdeIds));
            $mtq = @mysqli_query($conn, "SELECT scope_ref, SUM(target_value) t FROM bde_targets
                WHERE scope_type='user' AND metric='revenue' AND scope_ref IN ($in) GROUP BY scope_ref");
            while ($mtq && ($mr = mysqli_fetch_assoc($mtq))) { $memTarget[(int) $mr['scope_ref']] = (float) $mr['t']; }
        }

        // aggregate each BDE's REAL numbers, deduped by name (duplicate logins share a person)
        $byName = [];
        foreach ($bdeIds as $bid) {
            $m = bde_fetch_metrics($conn, $bid, $from, $to);
            $key = strtolower(preg_replace('/\s+/', ' ', trim((string) $m['name'])));
            if ($key === '' || $key === 'bde') { $key = 'id' . $bid; }
            if (!isset($byName[$key])) { $byName[$key] = ['name' => ($m['name'] ?: ('#' . $bid)), 'title' => ($m['title'] ?: 'BDE'), 'target' => 0.0, 'actual' => 0.0, 'clients' => 0, 'pipeline' => 0.0, 'collN' => 0.0, 'collD' => 0.0]; }
            // target is seeded identically on each duplicate login of the same person — take it ONCE
            // (max), never summed, or a duplicate login doubles the target (e.g. Josiah #69+#98 → 4M).
            $byName[$key]['target']  = max($byName[$key]['target'], $memTarget[$bid] ?? 0.0);
            $byName[$key]['actual']  += (float) $m['revenue_kes'];
            $byName[$key]['clients'] += (int) $m['paid_clients'];
            $byName[$key]['pipeline'] += max(0.0, ((float) $m['expected_usd'] - (float) $m['revenue_usd'])) * $rate;
            $byName[$key]['collN'] += (float) $m['revenue_usd'];
            $byName[$key]['collD'] += (float) $m['expected_usd'];
        }

        $deptRevenue = 0.0; $deptClients = 0; $deptPipe = 0.0; $collN = 0.0; $collD = 0.0; $team = [];
        foreach ($byName as $t) {
            $coll = $t['collD'] > 0 ? $t['collN'] / $t['collD'] : 0.0;
            $team[] = ['name' => $t['name'], 'title' => $t['title'], 'target' => $t['target'], 'actual' => $t['actual'], 'clients' => $t['clients'], 'pipeline' => $t['pipeline'], 'collection' => $coll, 'notes' => ''];
            $deptRevenue += $t['actual']; $deptClients += $t['clients']; $deptPipe += $t['pipeline']; $collN += $t['collN']; $collD += $t['collD'];
        }

        // the BDO's own attributed numbers count toward the department too
        $bm = bde_fetch_metrics($conn, $bdoId, $from, $to);
        $deptRevenue += (float) $bm['revenue_kes']; $deptClients += (int) $bm['paid_clients'];
        $deptPipe += max(0.0, ((float) $bm['expected_usd'] - (float) $bm['revenue_usd'])) * $rate;
        $collN += (float) $bm['revenue_usd']; $collD += (float) $bm['expected_usd'];

        usort($team, function ($a, $b) { return $b['actual'] <=> $a['actual']; });
        $out['team'] = $team; $out['members'] = count($team);
        $out['actual'] = $out['metric'] === 'participants' ? (float) $deptClients : $deptRevenue;
        $out['clients'] = $deptClients;
        $out['pipeline'] = $deptPipe;
        $out['collection'] = $collD > 0 ? $collN / $collD : 0.0;
        return $out;
    }
}
