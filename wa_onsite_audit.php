<?php
/**
 * AUDIT: WhatsApp enquiries where the client indicated ONSITE (in-person) interest.
 *
 * Read-only. Runs two ways:
 *
 *   Browser:  /admin/wa_onsite_audit.php?from=2026-07-01&to=2026-08-07
 *             (auth.php gates it behind the normal CRM login)
 *   CLI:      php wa_onsite_audit.php --from=2026-07-01 --to=2026-08-07
 *
 *   Add &export=csv (or --export=csv) to download/emit the gap list.
 *
 * Four sections, because one query would mislead:
 *   A  what the system RECORDED  (wa_conversations.delivery_mode = 'onsite')
 *   B  what customers actually SAID (scan of inbound message text)
 *   C  THE GAP — said onsite, never recorded, so never routed to an onsite rep
 *   D  summary counts
 *
 * Why A and B disagree: wa_conversations has UNIQUE(contact_id), so delivery_mode
 * is the CURRENT topic's mode, not a history — a client who asked about onsite in
 * June and virtual in July now reads 'virtual'. And wa_detect_delivery_mode()
 * records nothing when a message contains BOTH cues ("is it online or in person?"),
 * which is the most natural way to ask. Section E lists those separately.
 */

$IS_CLI = (PHP_SAPI === 'cli');

if ($IS_CLI) {
    require_once __DIR__ . '/includes/wa_db.php';   // $wa_conn, no session
    $conn = $wa_conn;
    $opt = getopt('', ['from::', 'to::', 'export::']);
    $FROM   = $opt['from']   ?? '';
    $TO     = $opt['to']     ?? '';
    $EXPORT = ($opt['export'] ?? '') === 'csv';
} else {
    require_once __DIR__ . '/auth.php';             // login gate + $conn
    $FROM   = (string)($_GET['from'] ?? '');
    $TO     = (string)($_GET['to']   ?? '');
    $EXPORT = (($_GET['export'] ?? '') === 'csv');
}

if (!$conn) { exit("No database connection\n"); }
mysqli_set_charset($conn, 'utf8mb4');
// The app runs Nairobi time (commit 7bab70ca); match it so date windows line up.
mysqli_query($conn, "SET time_zone = '+03:00'");

// Keep the audit honest even if the column was never created on this install.
@mysqli_query($conn, "ALTER TABLE `wa_conversations`
    ADD COLUMN IF NOT EXISTS `delivery_mode` ENUM('unknown','virtual','onsite') NOT NULL DEFAULT 'unknown'");

// ---- date window ---------------------------------------------------------
$FROM = preg_match('/^\d{4}-\d{2}-\d{2}$/', $FROM) ? $FROM : '';
$TO   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $TO)   ? $TO   : '';
$convWhere = '';
$msgWhere  = '';
if ($FROM !== '') {
    $convWhere .= " AND cv.created_at   >= '" . $FROM . " 00:00:00'";
    $msgWhere  .= " AND m.wa_timestamp  >= '" . $FROM . " 00:00:00'";
}
if ($TO !== '') {
    $convWhere .= " AND cv.created_at   <= '" . $TO . " 23:59:59'";
    $msgWhere  .= " AND m.wa_timestamp  <= '" . $TO . " 23:59:59'";
}

// Same rules as wa_detect_delivery_mode() in includes/wa_functions.php.
$RE_ONSITE  = '(on[[:space:]-]?site|in[[:space:]-]?person|physical|face[[:space:]-]?to[[:space:]-]?face|classroom|in[[:space:]-]?class|attend in|travel to)';
$RE_VIRTUAL = '(virtual|on[[:space:]-]?line|online|remote|zoom|web[[:space:]-]?based|e[[:space:]-]?learn)';

$TOPIC = "CASE cv.ref_type
            WHEN 'course' THEN (SELECT co.course     FROM course  co WHERE co.course_id = cv.ref_id)
            WHEN 'event'  THEN (SELECT e.event_title FROM `Event` e  WHERE e.event_id  = cv.ref_id)
          END";

function q($conn, $sql) {
    $r = mysqli_query($conn, $sql);
    if (!$r) { return ['__error' => mysqli_error($conn)]; }
    $out = [];
    while ($row = mysqli_fetch_assoc($r)) { $out[] = $row; }
    return $out;
}

// ---- A. what the system recorded ----------------------------------------
$A = q($conn, "
    SELECT c.wa_id, c.profile_name, cv.delivery_mode, cv.ref_type, $TOPIC AS topic,
           u.fullname AS assigned_rep, cv.handler, cv.escalated, cv.status,
           cv.last_route_reason, cv.last_message_at, cv.created_at AS first_seen
      FROM wa_conversations cv
      JOIN wa_contacts c           ON c.id = cv.contact_id
      LEFT JOIN registered_users u ON u.id = cv.assigned_user_id
     WHERE cv.delivery_mode = 'onsite' $convWhere
     ORDER BY cv.last_message_at DESC");

// ---- B. what customers actually said -------------------------------------
$B = q($conn, "
    SELECT c.wa_id, c.profile_name, COALESCE(cv.delivery_mode,'(no conv)') AS recorded_mode,
           COUNT(*) AS onsite_msgs, MIN(m.wa_timestamp) AS first_said, MAX(m.wa_timestamp) AS last_said,
           $TOPIC AS topic, u.fullname AS assigned_rep, cv.handler,
           SUBSTRING(MIN(CONCAT(m.wa_timestamp,'|',m.body)), 20, 120) AS what_they_said
      FROM wa_messages m
      JOIN wa_contacts c            ON c.id = m.contact_id
      LEFT JOIN wa_conversations cv ON cv.contact_id = c.id
      LEFT JOIN registered_users u  ON u.id = cv.assigned_user_id
     WHERE m.direction = 'inbound'
       AND m.body REGEXP '$RE_ONSITE'
       AND m.body NOT REGEXP '$RE_VIRTUAL' $msgWhere
     GROUP BY c.id, c.wa_id, c.profile_name, cv.delivery_mode, cv.ref_type, cv.ref_id,
              u.fullname, cv.handler
     ORDER BY last_said DESC");

// ---- C. the gap ----------------------------------------------------------
$C = q($conn, "
    SELECT c.wa_id, c.profile_name, COALESCE(cv.delivery_mode,'(no conv)') AS recorded_mode,
           cv.last_route_reason, u.fullname AS assigned_rep, cv.handler, cv.escalated,
           MIN(m.wa_timestamp) AS first_said, $TOPIC AS topic,
           SUBSTRING(MIN(CONCAT(m.wa_timestamp,'|',m.body)), 20, 120) AS what_they_said
      FROM wa_messages m
      JOIN wa_contacts c            ON c.id = m.contact_id
      LEFT JOIN wa_conversations cv ON cv.contact_id = c.id
      LEFT JOIN registered_users u  ON u.id = cv.assigned_user_id
     WHERE m.direction = 'inbound'
       AND m.body REGEXP '$RE_ONSITE'
       AND m.body NOT REGEXP '$RE_VIRTUAL' $msgWhere
       AND (cv.delivery_mode IS NULL OR cv.delivery_mode <> 'onsite')
     GROUP BY c.id, c.wa_id, c.profile_name, cv.delivery_mode, cv.last_route_reason,
              u.fullname, cv.handler, cv.escalated, cv.ref_type, cv.ref_id
     ORDER BY first_said DESC");

// ---- E. asked but never classified (said BOTH cues in one message) --------
$E = q($conn, "
    SELECT c.wa_id, c.profile_name, COALESCE(cv.delivery_mode,'(no conv)') AS recorded_mode,
           MIN(m.wa_timestamp) AS first_asked, cv.handler,
           SUBSTRING(MIN(CONCAT(m.wa_timestamp,'|',m.body)), 20, 120) AS what_they_said
      FROM wa_messages m
      JOIN wa_contacts c            ON c.id = m.contact_id
      LEFT JOIN wa_conversations cv ON cv.contact_id = c.id
     WHERE m.direction = 'inbound'
       AND m.body REGEXP '$RE_ONSITE'
       AND m.body REGEXP '$RE_VIRTUAL' $msgWhere
       AND (cv.delivery_mode IS NULL OR cv.delivery_mode = 'unknown')
     GROUP BY c.id, c.wa_id, c.profile_name, cv.delivery_mode, cv.handler
     ORDER BY first_asked DESC");

// ---- D. summary ----------------------------------------------------------
$D = q($conn, "
    SELECT 'recorded onsite' AS metric, COUNT(*) AS n FROM wa_conversations WHERE delivery_mode='onsite'
    UNION ALL SELECT 'recorded virtual', COUNT(*) FROM wa_conversations WHERE delivery_mode='virtual'
    UNION ALL SELECT 'still unknown',    COUNT(*) FROM wa_conversations WHERE delivery_mode='unknown'
    UNION ALL SELECT 'onsite + no rep assigned', COUNT(*) FROM wa_conversations
        WHERE delivery_mode='onsite' AND assigned_user_id IS NULL
    UNION ALL SELECT 'onsite + never reached a human', COUNT(*) FROM wa_conversations
        WHERE delivery_mode='onsite' AND handler='ai'
    UNION ALL SELECT 'onsite + awaiting location', COUNT(*) FROM wa_conversations
        WHERE delivery_mode='onsite' AND last_route_reason='await_onsite_location'");

// ---- CSV export (the gap list — the actionable one) ----------------------
if ($EXPORT) {
    if (!$IS_CLI) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="onsite_gap_' . date('Ymd_His') . '.csv"');
    }
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['wa_id','name','recorded_mode','route_reason','assigned_rep','handler','first_said','topic','what_they_said']);
    foreach ($C as $r) {
        if (isset($r['__error'])) { continue; }
        fputcsv($fh, [$r['wa_id'],$r['profile_name'],$r['recorded_mode'],$r['last_route_reason'],
                      $r['assigned_rep'],$r['handler'],$r['first_said'],$r['topic'],$r['what_they_said']]);
    }
    fclose($fh);
    exit;
}

$SECTIONS = [
    ['A. Recorded as onsite by the system',            $A, 'What the router acted on when choosing a rep.'],
    ['B. Said onsite in their own words',              $B, 'Ground truth from inbound message text.'],
    ['C. THE GAP — said onsite, never recorded',       $C, 'Never flagged, so never routed to an onsite rep. This is the finding.'],
    ['E. Asked "online or in person?" — never classified', $E, 'Both cues in one message: the detector records nothing. Needs a human.'],
];

// =========================== CLI OUTPUT ===================================
if ($IS_CLI) {
    $range = ($FROM || $TO) ? " [" . ($FROM ?: 'start') . " .. " . ($TO ?: 'now') . "]" : " [all time]";
    echo "\n=== WhatsApp ONSITE enquiry audit$range ===\n";
    foreach ($SECTIONS as [$title, $rows, $note]) {
        echo "\n" . str_repeat('=', 78) . "\n$title\n$note\n" . str_repeat('=', 78) . "\n";
        if (isset($rows[0]['__error'])) { echo "  SQL ERROR: {$rows[0]['__error']}\n"; continue; }
        if (!$rows) { echo "  (none)\n"; continue; }
        echo "  " . count($rows) . " contact(s)\n\n";
        foreach ($rows as $r) {
            $name = trim((string)($r['profile_name'] ?? '')) ?: '(no name)';
            echo "  " . str_pad($r['wa_id'], 16) . ' ' . str_pad(mb_substr($name, 0, 24), 25)
               . ' mode=' . str_pad((string)($r['recorded_mode'] ?? $r['delivery_mode'] ?? '-'), 9)
               . ' rep=' . str_pad((string)($r['assigned_rep'] ?? '-') ?: '-', 20)
               . ' handler=' . ($r['handler'] ?? '-') . "\n";
            if (!empty($r['topic']))          { echo "        topic: {$r['topic']}\n"; }
            if (!empty($r['last_route_reason'])) { echo "        route: {$r['last_route_reason']}\n"; }
            if (!empty($r['what_they_said'])) { echo "        said : \"" . trim(preg_replace('/\s+/', ' ', $r['what_they_said'])) . "\"\n"; }
        }
    }
    echo "\n" . str_repeat('=', 78) . "\nD. Summary\n" . str_repeat('=', 78) . "\n";
    foreach ($D as $r) { if (!isset($r['__error'])) { echo '  ' . str_pad($r['metric'], 34) . $r['n'] . "\n"; } }
    echo "\n";
    exit;
}

// =========================== WEB OUTPUT ===================================
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$qs = 'from=' . urlencode($FROM) . '&to=' . urlencode($TO);
?>
<!doctype html>
<meta charset="utf-8">
<title>Onsite enquiry audit</title>
<style>
  body { font-family: system-ui, Arial, sans-serif; margin: 24px; color: #222; background: #fafafa; }
  h1 { font-size: 20px; margin: 0 0 4px; }
  h2 { font-size: 15px; margin: 28px 0 2px; padding-top: 14px; border-top: 2px solid #A85431; }
  .note { color: #666; font-size: 12px; margin: 0 0 10px; }
  .bar { background: #fff; border: 1px solid #ddd; padding: 12px; border-radius: 6px; margin-bottom: 8px; }
  table { border-collapse: collapse; width: 100%; background: #fff; font-size: 12px; }
  th { background: #2B5470; color: #fff; text-align: left; padding: 6px 8px; font-weight: 600; }
  td { border-bottom: 1px solid #eee; padding: 5px 8px; vertical-align: top; }
  tr:nth-child(even) td { background: #fbfbfb; }
  .said { color: #444; font-style: italic; max-width: 380px; }
  .pill { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 11px; }
  .ai { background: #fdecea; color: #a3261a; } .human { background: #e7f5ec; color: #1c6b3a; }
  .none { color: #888; padding: 10px 0; }
  .gap h2 { border-top-color: #c0392b; } .gap th { background: #c0392b; }
  .sum td { padding: 4px 10px; } .sum td:last-child { font-weight: 700; text-align: right; }
  a.btn { display:inline-block; background:#A85431; color:#fff; padding:6px 12px; border-radius:4px;
          text-decoration:none; font-size:12px; }
</style>

<h1>WhatsApp enquiry audit — clients who indicated <em>onsite</em></h1>
<p class="note">
  Range: <?= h($FROM ?: 'start') ?> &rarr; <?= h($TO ?: 'now') ?> &middot; Nairobi time (EAT)
</p>

<div class="bar">
  <form method="get" style="display:inline">
    From <input type="date" name="from" value="<?= h($FROM) ?>">
    To <input type="date" name="to" value="<?= h($TO) ?>">
    <button type="submit">Apply</button>
  </form>
  &nbsp;&nbsp;<a class="btn" href="?<?= $qs ?>&export=csv">Download gap list (CSV)</a>
</div>

<?php foreach ($SECTIONS as $i => [$title, $rows, $note]): ?>
  <div class="<?= strpos($title, 'GAP') !== false ? 'gap' : '' ?>">
  <h2><?= h($title) ?> <span style="font-weight:400;color:#666">
      — <?= isset($rows[0]['__error']) ? 'error' : count($rows) ?> contact(s)</span></h2>
  <p class="note"><?= h($note) ?></p>
  <?php if (isset($rows[0]['__error'])): ?>
    <p class="none">SQL error: <?= h($rows[0]['__error']) ?></p>
  <?php elseif (!$rows): ?>
    <p class="none">None found in this range.</p>
  <?php else: ?>
    <table>
      <tr>
        <th>WhatsApp</th><th>Name</th><th>Recorded</th><th>Topic</th>
        <th>Assigned rep</th><th>Handler</th><th>Route reason</th><th>When</th><th>What they said</th>
      </tr>
      <?php foreach ($rows as $r): $hd = $r['handler'] ?? '-'; ?>
      <tr>
        <td><?= h($r['wa_id']) ?></td>
        <td><?= h(trim((string)($r['profile_name'] ?? '')) ?: '—') ?></td>
        <td><?= h($r['recorded_mode'] ?? $r['delivery_mode'] ?? '—') ?></td>
        <td><?= h($r['topic'] ?? '—') ?></td>
        <td><?= h($r['assigned_rep'] ?? '') ?: '<span style="color:#c0392b">unassigned</span>' ?></td>
        <td><span class="pill <?= $hd === 'ai' ? 'ai' : 'human' ?>"><?= h($hd) ?></span></td>
        <td><?= h($r['last_route_reason'] ?? '—') ?></td>
        <td><?= h($r['first_said'] ?? $r['first_asked'] ?? $r['last_message_at'] ?? '—') ?></td>
        <td class="said"><?= h(trim(preg_replace('/\s+/', ' ', (string)($r['what_they_said'] ?? '')))) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
  </div>
<?php endforeach; ?>

<h2>D. Summary</h2>
<p class="note">Counts are all-time and ignore the date filter above.</p>
<table class="sum">
  <?php foreach ($D as $r): if (isset($r['__error'])) continue; ?>
    <tr><td><?= h($r['metric']) ?></td><td><?= h($r['n']) ?></td></tr>
  <?php endforeach; ?>
</table>

<p class="note" style="margin-top:22px;line-height:1.6">
  <strong>Reading this:</strong> <em>wa_conversations</em> holds one row per contact
  (<code>UNIQUE(contact_id)</code>), so <em>Recorded</em> is the client's CURRENT topic mode,
  not a history — someone who asked about onsite in June and virtual in July now reads
  <em>virtual</em>, which is why B and C exist. <em>onsite + no rep assigned</em> in the summary
  is often expected: onsite chats deliberately wait for a named location before binding to a
  specific event and rep, so judge those by age rather than treating each as a miss.
</p>
