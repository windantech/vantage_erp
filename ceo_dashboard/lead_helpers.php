<?php
/**
 * lead_helpers.php  —  VASL Lead Intelligence (Phase 1)
 *
 * Shared functions for the lead insights system:
 *   - Schema creation (lead_insights + lead_sync_state)
 *   - Email / country / position normalization
 *   - Segment derivation + deterministic lead scoring (NO AI)
 *   - Converted-email lookup (per-person, email-based)
 *
 * Conversion rule: an email is "converted" if it appears in dpo_payment
 * with status = 2 (paid). Matching is done on LOWER(TRIM(email)) so case /
 * whitespace differences don't cause false leads.
 *
 * Include AFTER: require_once '../../database/conn.php';  (provides $conn)
 */

if (!defined('VASL_LEAD_HELPERS')) {
    define('VASL_LEAD_HELPERS', 1);

    /* dpo_payment.status value that means PAID. Compared loosely so it works
       whether the column is INT or VARCHAR. */
    define('VASL_PAID_STATUS', '2');

    /* How many source rows the cron processes per run, per source. */
    define('VASL_SYNC_BATCH', 500);
}

/* ------------------------------------------------------------------ */
/*  SCHEMA                                                             */
/* ------------------------------------------------------------------ */

/**
 * Create the precomputed insights table and the cron cursor table.
 * Safe to call repeatedly (CREATE TABLE IF NOT EXISTS).
 */
function lead_ensure_schema(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS lead_insights (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source         ENUM('virtual','international') NOT NULL,
            source_id      VARCHAR(64) NOT NULL,
            fullname       VARCHAR(255) DEFAULT NULL,
            email          VARCHAR(255) DEFAULT NULL,
            email_norm     VARCHAR(255) DEFAULT NULL,
            phone          VARCHAR(64)  DEFAULT NULL,
            country        VARCHAR(128) DEFAULT NULL,
            country_norm   VARCHAR(128) DEFAULT NULL,
            organization   VARCHAR(255) DEFAULT NULL,
            position       VARCHAR(255) DEFAULT NULL,
            position_norm  VARCHAR(128) DEFAULT NULL,
            program_or_term VARCHAR(255) DEFAULT NULL,
            lead_segment   VARCHAR(32)  DEFAULT NULL,
            lead_score     SMALLINT     NOT NULL DEFAULT 0,
            lead_status    VARCHAR(64)  DEFAULT NULL,
            assigned_to    VARCHAR(128) DEFAULT NULL,
            last_contact_date DATE      DEFAULT NULL,
            is_converted   TINYINT(1)   NOT NULL DEFAULT 0,
            original_date  DATETIME     DEFAULT NULL,
            ai_suggestion  MEDIUMTEXT   DEFAULT NULL,
            ai_generated_at DATETIME    DEFAULT NULL,
            refreshed_at   DATETIME     NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_source_row (source, source_id),
            KEY idx_converted (is_converted),
            KEY idx_country (country_norm),
            KEY idx_segment (lead_segment),
            KEY idx_status (lead_status),
            KEY idx_assigned (assigned_to),
            KEY idx_score (lead_score),
            KEY idx_email (email_norm)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /* Migration for tables created before the AI columns existed.
       Each guarded so re-running is harmless. */
    $cols = [];
    if ($cr = $conn->query("SHOW COLUMNS FROM lead_insights")) {
        while ($c = $cr->fetch_assoc()) $cols[$c['Field']] = true;
        $cr->free();
    }
    if (!isset($cols['ai_suggestion'])) {
        $conn->query("ALTER TABLE lead_insights ADD COLUMN ai_suggestion MEDIUMTEXT DEFAULT NULL");
    }
    if (!isset($cols['ai_generated_at'])) {
        $conn->query("ALTER TABLE lead_insights ADD COLUMN ai_generated_at DATETIME DEFAULT NULL");
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS lead_sync_state (
            source      VARCHAR(32) NOT NULL,
            last_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at  DATETIME NOT NULL,
            PRIMARY KEY (source)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/* ------------------------------------------------------------------ */
/*  NORMALIZATION                                                      */
/* ------------------------------------------------------------------ */

function lead_norm_email(?string $email): string {
    return strtolower(trim((string)$email));
}

/**
 * Normalize country names. Maps common variants/ISO codes to a canonical
 * display name. Unknown values are title-cased and trimmed.
 */
function lead_norm_country(?string $raw): string {
    $c = strtolower(trim((string)$raw));
    if ($c === '') return '';
    // strip punctuation/extra spaces
    $c = preg_replace('/\s+/', ' ', $c);

    static $map = [
        'ke' => 'Kenya', 'kenya' => 'Kenya', 'kenyan' => 'Kenya',
        'ug' => 'Uganda', 'uganda' => 'Uganda',
        'tz' => 'Tanzania', 'tanzania' => 'Tanzania',
        'rw' => 'Rwanda', 'rwanda' => 'Rwanda',
        'et' => 'Ethiopia', 'ethiopia' => 'Ethiopia',
        'ng' => 'Nigeria', 'nigeria' => 'Nigeria', 'nigerian' => 'Nigeria',
        'gh' => 'Ghana', 'ghana' => 'Ghana',
        'za' => 'South Africa', 'south africa' => 'South Africa', 'rsa' => 'South Africa',
        'ss' => 'South Sudan', 'south sudan' => 'South Sudan',
        'sd' => 'Sudan', 'sudan' => 'Sudan',
        'zm' => 'Zambia', 'zambia' => 'Zambia',
        'zw' => 'Zimbabwe', 'zimbabwe' => 'Zimbabwe',
        'mw' => 'Malawi', 'malawi' => 'Malawi',
        'bw' => 'Botswana', 'botswana' => 'Botswana',
        'us' => 'United States', 'usa' => 'United States',
        'united states' => 'United States', 'u.s.a' => 'United States',
        'uk' => 'United Kingdom', 'u.k' => 'United Kingdom',
        'united kingdom' => 'United Kingdom', 'england' => 'United Kingdom',
        'drc' => 'DR Congo', 'dr congo' => 'DR Congo',
        'democratic republic of congo' => 'DR Congo',
        'somalia' => 'Somalia', 'so' => 'Somalia',
        'cm' => 'Cameroon', 'cameroon' => 'Cameroon',
    ];
    if (isset($map[$c])) return $map[$c];

    // Title-case fallback
    return ucwords($c);
}

/**
 * Normalize a job position into a canonical lowercase token used for both
 * display grouping and segment derivation.
 */
function lead_norm_position(?string $raw): string {
    $p = strtolower(trim((string)$raw));
    if ($p === '') return '';
    $p = preg_replace('/[^a-z0-9 \/&-]/', ' ', $p);
    $p = preg_replace('/\s+/', ' ', trim($p));
    return $p;
}

/* ------------------------------------------------------------------ */
/*  SEGMENTATION + SCORING (deterministic, no AI)                      */
/* ------------------------------------------------------------------ */

/**
 * Derive a coarse segment from the normalized position.
 * Returns one of: decision_maker, manager, professional, individual.
 */
function lead_segment(string $pos_norm): string {
    if ($pos_norm === '') return 'individual';

    // Decision-makers / senior leadership
    $dm = ['ceo','ceo ','chief','c-level','cfo','coo','cto','cio','director',
           'managing director','executive director','founder','co-founder',
           'owner','partner','president','vice president','vp','board',
           'head of','head ','principal','dean','permanent secretary',
           'commissioner','country director','regional director'];
    foreach ($dm as $kw) {
        if (strpos($pos_norm, $kw) !== false) return 'decision_maker';
    }

    // Managers / supervisors
    $mgr = ['manager','supervisor','lead ','team lead','coordinator',
            'administrator','superintendent'];
    foreach ($mgr as $kw) {
        if (strpos($pos_norm, $kw) !== false) return 'manager';
    }

    // Professionals / specialists
    $prof = ['officer','specialist','analyst','consultant','engineer',
             'accountant','advisor','adviser','associate','executive',
             'professional','expert','researcher','auditor','trainer',
             'facilitator','lecturer','teacher','nurse','doctor'];
    foreach ($prof as $kw) {
        if (strpos($pos_norm, $kw) !== false) return 'professional';
    }

    return 'individual';
}

/**
 * Deterministic lead score 0-100. Weighted, explainable, no AI.
 *
 * Weights CALIBRATED against 1,000 leads / 116 conversions (base rate 11.6%).
 * The calibration showed seniority predicts conversion INVERSELY to intuition:
 * managers and individual learners convert best; senior decision-makers and
 * professionals convert worst (they inquire but often don't personally enrol).
 *
 * Factor contributions (re-run lead_score_calibrate.php periodically to refresh):
 *   - Segment            8-40  (manager best, professional worst)
 *   - Source             0/+8  (international converts slightly better)
 *   - Organization         0   (no/negative signal — not scored)
 *   - Phone                0   (everyone has one — no discrimination)
 *   - Recency              0   (DORMANT: flat on current data; see note)
 *   - Prior contact        0   (DORMANT: no follow-up logged yet)
 *
 * Recency & prior-contact are intentionally kept in the formula at weight 0 so
 * they can be reactivated once the underlying data becomes discriminating
 * (i.e. once staff log contacts, and once inquiry dates span a useful range).
 */
function lead_score(array $r): int {
    $score = 0;

    // Segment — data-driven (managers/individuals reward, pros/DMs lower)
    switch ($r['lead_segment']) {
        case 'manager':        $score += 40; break;  // 24.1% conv (lift 1.78)
        case 'individual':     $score += 28; break;  // 12.4% conv (~average)
        case 'decision_maker': $score += 18; break;  //  8.5% conv (below avg)
        case 'professional':   $score += 8;  break;  //  3.5% conv (worst)
        default:               $score += 20; break;  // unknown → near base
    }

    // Source — international shows a mild positive signal (13.6% vs 9.6%)
    if (($r['source'] ?? '') === 'international') $score += 8;

    /* ---- Dormant factors (weight 0 on current data) ----------------
       Kept for transparency and easy reactivation. Uncomment/raise the
       points once lead_score_calibrate.php shows a real gradient. */

    // Organization: calibration showed leads WITHOUT an org convert slightly
    // better, so we deliberately add nothing here (not a positive predictor).

    // Phone: 998/1000 leads have a phone → no discriminating power. 0 points.

    // Recency: all current inquiry dates fall in one bucket → flat. 0 points.
    // When dates span a useful range, this block can award up to ~20:
    // if (!empty($r['original_date'])) {
    //     $days = (time() - strtotime($r['original_date'])) / 86400;
    //     if ($days <= 30) $score += 20; elseif ($days <= 90) $score += 12;
    //     elseif ($days <= 180) $score += 6;
    // }

    // Prior contact: no last_contact_date logged yet → 0 points.
    // Reactivate once follow-ups are being recorded:
    // if (!empty($r['last_contact_date'])) $score += 8;

    return max(0, min(100, $score));
}

/* ------------------------------------------------------------------ */
/*  CONVERSION LOOKUP                                                  */
/* ------------------------------------------------------------------ */

/**
 * Build a hash set of normalized emails that have a PAID dpo_payment row.
 * Uses an associative array for O(1) lookup (per the dedup principle:
 * isset($seen[$email]) not in_array()).
 *
 * Returns: ['someone@example.com' => true, ...]
 */
function lead_load_paid_emails(mysqli $conn): array {
    $paid = [];
    $status = $conn->real_escape_string(VASL_PAID_STATUS);
    // status compared as string so it works for INT or VARCHAR columns
    $sql = "SELECT email FROM dpo_payment
            WHERE email IS NOT NULL AND email <> ''
              AND CAST(status AS CHAR) = '$status'";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $e = lead_norm_email($row['email']);
            if ($e !== '') $paid[$e] = true;
        }
        $res->free();
    }
    return $paid;
}

/* ------------------------------------------------------------------ */
/*  ROLE-BASED SCOPE                                                   */
/* ------------------------------------------------------------------ */

/**
 * Returns a WHERE fragment (without the leading WHERE/AND) limiting the
 * lead_insights rows a given user may see, following the CEO/admin = all,
 * others = their own assigned leads pattern.
 *
 * $login_type and $login_name come from the session.
 */
function lead_scope_clause(mysqli $conn, ?string $login_type, ?string $login_name): string {
    $type = strtolower(trim((string)$login_type));
    $all  = ['ceo', 'admin', 'administrator', 'super', 'finance'];
    if (in_array($type, $all, true)) {
        return '1=1';
    }
    // Everyone else sees only leads assigned to them
    $name = $conn->real_escape_string((string)$login_name);
    return "assigned_to = '$name'";
}