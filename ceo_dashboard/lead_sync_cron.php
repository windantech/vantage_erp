<?php
/**
 * lead_sync_cron.php  —  VASL Lead Intelligence (Phase 1)
 *
 * Batch processor that mirrors bridge_range.php: processes up to
 * VASL_SYNC_BATCH (500) rows per source per run, tracks a cursor in
 * lead_sync_state, and upserts normalized rows into lead_insights.
 *
 * Idempotent: re-running re-processes the same window safely thanks to the
 * UNIQUE KEY (source, source_id) + ON DUPLICATE KEY UPDATE. The cursor only
 * advances forward; when it reaches the end of a table it wraps to 0 so new
 * inquiries (and updated follow-up fields) get refreshed on the next pass.
 *
 * Run via cron, e.g. every 15 minutes:
 *   *\/15 * * * * /usr/bin/php /home/vantage/.../lead_sync_cron.php >> /home/vantage/lead_sync.log 2>&1
 *
 * Can also be hit in the browser by an admin (?web=1) to see a summary.
 */

$IS_CLI = (php_sapi_name() === 'cli');

require_once __DIR__ . '/../../database/conn.php';   // provides $conn
require_once __DIR__ . '/lead_helpers.php';

lead_ensure_schema($conn);

/* Preload the paid-email hash set once for this run (O(1) lookups). */
$paidEmails = lead_load_paid_emails($conn);

/* Preload FK → title lookup maps once (O(1) lookups, no per-row queries).
   Virtual:       register.program  → course.course   (key: course.course_id)
   International:  ticket_congress.event_id → Event.event_title (key: Event.event_id) */
$courseMap = [];
if ($cr = $conn->query("SELECT course_id, course FROM course")) {
    while ($row = $cr->fetch_assoc()) {
        $courseMap[(string)$row['course_id']] = $row['course'];
    }
    $cr->free();
}
$eventMap = [];
if ($er = $conn->query("SELECT event_id, event_title FROM Event")) {
    while ($row = $er->fetch_assoc()) {
        $eventMap[(string)$row['event_id']] = $row['event_title'];
    }
    $er->free();
}

$summary = ['virtual' => 0, 'international' => 0, 'converted_flagged' => 0];

/* ------------------------------------------------------------------ */
/*  Helper: read + advance the per-source cursor                       */
/* ------------------------------------------------------------------ */
function lead_get_cursor(mysqli $conn, string $source): int {
    $s = $conn->real_escape_string($source);
    $res = $conn->query("SELECT last_id FROM lead_sync_state WHERE source = '$s'");
    if ($res && ($row = $res->fetch_assoc())) return (int)$row['last_id'];
    return 0;
}
function lead_set_cursor(mysqli $conn, string $source, int $lastId): void {
    $s = $conn->real_escape_string($source);
    $now = date('Y-m-d H:i:s');
    $conn->query("
        INSERT INTO lead_sync_state (source, last_id, updated_at)
        VALUES ('$s', $lastId, '$now')
        ON DUPLICATE KEY UPDATE last_id = VALUES(last_id), updated_at = VALUES(updated_at)
    ");
}

/* ------------------------------------------------------------------ */
/*  Upsert one normalized lead row                                     */
/* ------------------------------------------------------------------ */
function lead_upsert(mysqli $conn, array $r): void {
    $now = date('Y-m-d H:i:s');
    $cols = [
        'source','source_id','fullname','email','email_norm','phone',
        'country','country_norm','organization','position','position_norm',
        'program_or_term','lead_segment','lead_score','lead_status',
        'assigned_to','last_contact_date','is_converted','original_date','refreshed_at'
    ];
    $vals = [];
    foreach ($cols as $c) {
        if ($c === 'refreshed_at') { $vals[] = "'$now'"; continue; }
        $v = $r[$c] ?? null;
        if ($v === null || $v === '') {
            // numeric columns default to 0
            $vals[] = in_array($c, ['lead_score','is_converted'], true) ? '0' : 'NULL';
        } else {
            $vals[] = "'" . $conn->real_escape_string((string)$v) . "'";
        }
    }
    $colList = '`' . implode('`,`', $cols) . '`';
    $valList = implode(',', $vals);

    // On duplicate: refresh everything EXCEPT the human-managed follow-up
    // fields would be overwritten — but those live in the source tables,
    // so we DO refresh them here (source tables are the source of truth).
    $updates = [];
    foreach ($cols as $c) {
        if ($c === 'source' || $c === 'source_id') continue;
        $updates[] = "`$c` = VALUES(`$c`)";
    }
    $updateList = implode(',', $updates);

    $conn->query("
        INSERT INTO lead_insights ($colList) VALUES ($valList)
        ON DUPLICATE KEY UPDATE $updateList
    ");
}

/* ------------------------------------------------------------------ */
/*  SOURCE 1: register (virtual)                                       */
/* ------------------------------------------------------------------ */
function sync_virtual(mysqli $conn, array $paidEmails, array $courseMap, array &$summary): void {
    $cursor = lead_get_cursor($conn, 'virtual');
    $batch  = (int)VASL_SYNC_BATCH;

    $sql = "SELECT id, entry_id, email, firstname, lastname, phone_number,
                   program, organization, position, country, datee,
                   lead_status, assigned_to, last_contact_date
            FROM register
            WHERE id > $cursor
            ORDER BY id ASC
            LIMIT $batch";
    $res = $conn->query($sql);
    if (!$res) return;

    $maxId = $cursor;
    while ($row = $res->fetch_assoc()) {
        $maxId = max($maxId, (int)$row['id']);

        $fullname = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
        $emailNorm = lead_norm_email($row['email']);
        $posNorm   = lead_norm_position($row['position']);
        $seg       = lead_segment($posNorm);
        $converted = ($emailNorm !== '' && isset($paidEmails[$emailNorm])) ? 1 : 0;
        if ($converted) $summary['converted_flagged']++;

        // Resolve program FK → readable course title (fallback to raw value)
        $progKey   = (string)($row['program'] ?? '');
        $courseName = $courseMap[$progKey] ?? ($progKey !== '' ? $progKey : null);

        // Use entry_id as the stable source_id; fall back to id.
        $sourceId = ($row['entry_id'] !== null && $row['entry_id'] !== '')
                    ? $row['entry_id'] : ('reg-' . $row['id']);

        $rec = [
            'source'          => 'virtual',
            'source_id'       => $sourceId,
            'fullname'        => $fullname,
            'email'           => $row['email'],
            'email_norm'      => $emailNorm,
            'phone'           => $row['phone_number'],
            'country'         => $row['country'],
            'country_norm'    => lead_norm_country($row['country']),
            'organization'    => $row['organization'],
            'position'        => $row['position'],
            'position_norm'   => $posNorm,
            'program_or_term' => $courseName,
            'lead_segment'    => $seg,
            'lead_status'     => $row['lead_status'],
            'assigned_to'     => $row['assigned_to'],
            'last_contact_date' => $row['last_contact_date'],
            'is_converted'    => $converted,
            'original_date'   => $row['datee'],
        ];
        $rec['lead_score'] = lead_score($rec);

        lead_upsert($conn, $rec);
        $summary['virtual']++;
    }
    $res->free();

    // Advance cursor; wrap to 0 if we processed fewer than a full batch (end of table)
    if ($summary['virtual'] > 0 && $summary['virtual'] < $batch) {
        lead_set_cursor($conn, 'virtual', 0);          // wrap → refresh from top next run
    } else {
        lead_set_cursor($conn, 'virtual', $maxId);
    }
}

/* ------------------------------------------------------------------ */
/*  SOURCE 2: ticket_congress (international)                           */
/* ------------------------------------------------------------------ */
function sync_international(mysqli $conn, array $paidEmails, array $eventMap, array &$summary): void {
    $cursor = lead_get_cursor($conn, 'international');
    $batch  = (int)VASL_SYNC_BATCH;

    $sql = "SELECT id, ticket_id, fullname, email, event_id, phone_number,
                   organization, position, country, date_sent,
                   lead_status, assigned_to, last_contact_date
            FROM ticket_congress
            WHERE id > $cursor
            ORDER BY id ASC
            LIMIT $batch";
    $res = $conn->query($sql);
    if (!$res) return;

    $maxId = $cursor;
    while ($row = $res->fetch_assoc()) {
        $maxId = max($maxId, (int)$row['id']);

        $emailNorm = lead_norm_email($row['email']);
        $posNorm   = lead_norm_position($row['position']);
        $seg       = lead_segment($posNorm);
        $converted = ($emailNorm !== '' && isset($paidEmails[$emailNorm])) ? 1 : 0;
        if ($converted) $summary['converted_flagged']++;

        // Resolve event_id FK → readable event title (fallback to raw value)
        $evKey     = (string)($row['event_id'] ?? '');
        $eventName = $eventMap[$evKey] ?? ($evKey !== '' ? $evKey : null);

        $sourceId = ($row['ticket_id'] !== null && $row['ticket_id'] !== '')
                    ? $row['ticket_id'] : ('tkt-' . $row['id']);

        $rec = [
            'source'          => 'international',
            'source_id'       => $sourceId,
            'fullname'        => $row['fullname'],
            'email'           => $row['email'],
            'email_norm'      => $emailNorm,
            'phone'           => $row['phone_number'],
            'country'         => $row['country'],
            'country_norm'    => lead_norm_country($row['country']),
            'organization'    => $row['organization'],
            'position'        => $row['position'],
            'position_norm'   => $posNorm,
            'program_or_term' => $eventName,
            'lead_segment'    => $seg,
            'lead_status'     => $row['lead_status'],
            'assigned_to'     => $row['assigned_to'],
            'last_contact_date' => $row['last_contact_date'],
            'is_converted'    => $converted,
            'original_date'   => $row['date_sent'],
        ];
        $rec['lead_score'] = lead_score($rec);

        lead_upsert($conn, $rec);
        $summary['international']++;
    }
    $res->free();

    if ($summary['international'] > 0 && $summary['international'] < $batch) {
        lead_set_cursor($conn, 'international', 0);
    } else {
        lead_set_cursor($conn, 'international', $maxId);
    }
}

/* ------------------------------------------------------------------ */
/*  RUN                                                                */
/* ------------------------------------------------------------------ */
sync_virtual($conn, $paidEmails, $courseMap, $summary);
sync_international($conn, $paidEmails, $eventMap, $summary);

$msg = sprintf(
    "[%s] lead_sync done — virtual:%d international:%d converted_flagged:%d paid_emails:%d",
    date('Y-m-d H:i:s'),
    $summary['virtual'], $summary['international'],
    $summary['converted_flagged'], count($paidEmails)
);

if ($IS_CLI) {
    echo $msg . PHP_EOL;
} else {
    // Browser run by admin — clear any buffered template output, emit JSON
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'summary' => $summary,
                      'paid_emails' => count($paidEmails), 'message' => $msg]);
}