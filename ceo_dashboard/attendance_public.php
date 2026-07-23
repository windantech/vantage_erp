<?php
/**
 * attendance_public.php
 * --------------------------------------------------------------------------
 * Public (no-login) mobile-friendly daily attendance viewer.
 * Filter by date and device. Place in a subfolder (path ../../database/conn.php).
 * URL is unlisted; anyone with the link can view.
 */
session_start();
require_once '../../database/conn.php';

$day     = $_GET['day'] ?? date('Y-m-d');
$fDevice = $_GET['device'] ?? '';

// validate date (fallback to today if malformed)
$d = DateTime::createFromFormat('Y-m-d', $day);
if (!$d || $d->format('Y-m-d') !== $day) { $day = date('Y-m-d'); }

// ---- Pull punches for the day, optional device filter ----
$where  = ["a.punch_time >= ?", "a.punch_time <= ?"];
$params = [$day.' 00:00:00', $day.' 23:59:59'];
$types  = 'ss';
if ($fDevice !== '') { $where[] = 'a.device_id = ?'; $params[] = $fDevice; $types .= 's'; }

$sql = "SELECT a.device_id, a.device_user_id, a.staff_id, a.punch_time,
               s.full_name AS staff_name,
               m.full_name AS device_name
        FROM attendance_logs a
        LEFT JOIN staff s ON s.staff_id = a.staff_id
        LEFT JOIN device_user_map m
               ON m.device_id = a.device_id AND m.device_user_id = a.device_user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.punch_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$people = [];
while ($r = $res->fetch_assoc()) {
    // Group by device + user together: device_user_id is only unique WITHIN a
    // device, so the same number on two devices is two different people.
    // Prefer staff_id when present (a mapped person), else fall back to the
    // device+user composite.
    if (!empty($r['staff_id'])) {
        $key = 'staff:' . $r['staff_id'];
    } else {
        $key = 'dev:' . $r['device_id'] . ':' . $r['device_user_id'];
    }
    if (!isset($people[$key])) {
        $people[$key] = [
            'device_user_id' => $r['device_user_id'],
            'staff_id'       => $r['staff_id'],
            'name'           => $r['staff_name'] ?: ($r['device_name'] ?: ''),
            'device_id'      => $r['device_id'],
            'first'          => $r['punch_time'],
            'last'           => $r['punch_time'],
            'count'          => 0,
        ];
    }
    // true earliest / latest punch for this person
    if (strtotime($r['punch_time']) < strtotime($people[$key]['first'])) $people[$key]['first'] = $r['punch_time'];
    if (strtotime($r['punch_time']) > strtotime($people[$key]['last']))  $people[$key]['last']  = $r['punch_time'];
    $people[$key]['count']++;
}
$stmt->close();

// ---- Mark lateness (first punch after 8:20) and sort earliest-first ----
$LATE_AFTER = '08:20:00';   // on time up to and including 08:20; 08:21+ is late
foreach ($people as $k => $p) {
    $firstTimeOnly = date('H:i:s', strtotime($p['first']));
    $people[$k]['late'] = ($firstTimeOnly > $LATE_AFTER);
    // minutes past 8:00, for display
    $base = strtotime(date('Y-m-d', strtotime($p['first'])) . ' 08:00:00');
    $diff = (strtotime($p['first']) - $base) / 60;
    $people[$k]['late_mins'] = $diff > 0 ? (int)round($diff) : 0;
}
// sort by first-in ascending (earliest arrival first)
uasort($people, function($a, $b){
    return strtotime($a['first']) - strtotime($b['first']);
});

$lateCount = 0;
foreach ($people as $p) { if ($p['late']) $lateCount++; }

$devices = [];
if ($dq = $conn->query("SELECT DISTINCT device_id FROM attendance_logs ORDER BY device_id")) {
    while ($dr = $dq->fetch_assoc()) $devices[] = $dr['device_id'];
}

$presentCount = count($people);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hhmm($dt){ return $dt ? date('H:i', strtotime($dt)) : '-'; }
function worked($a, $b){
    if (!$a || !$b || $a === $b) return '-';
    $mins = (int) round((strtotime($b) - strtotime($a)) / 60);
    if ($mins <= 0) return '-';
    return intdiv($mins, 60) . 'h ' . str_pad((string)($mins % 60), 2, '0', STR_PAD_LEFT) . 'm';
}
function mins_hm($mins){
    $mins = (int)$mins;
    if ($mins < 60) return $mins . ' min';
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return $m > 0 ? ($h . ' hour' . ($h>1?'s':'') . ' ' . $m . ' min') : ($h . ' hour' . ($h>1?'s':''));
}

$prevDay = date('Y-m-d', strtotime($day.' -1 day'));
$nextDay = date('Y-m-d', strtotime($day.' +1 day'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daily Attendance &mdash; <?= h(date('d M Y', strtotime($day))) ?></title>
<style>
  :root { --maroon:#7a1c2e; --wine:#5a1020; --gold:#c0a040; --cream:#fdf6e3; --ink:#1a0a0a; }
  * { box-sizing: border-box; }
  body { margin:0; background:#f3efe7; font-family:'Segoe UI', system-ui, Arial, sans-serif; color:#222; }
  .wrap { max-width: 820px; margin: 0 auto; padding: 14px; }
  .topbar { background: var(--maroon); color:#fff; border-radius:10px; padding:16px 18px; display:flex; align-items:center; justify-content:space-between; }
  .topbar h1 { font-size:18px; margin:0; font-weight:700; }
  .topbar .logo { height:30px; }
  .stats { display:flex; gap:10px; margin:14px 0; }
  .stat { flex:1; background:#fff; border-radius:10px; padding:12px 14px; box-shadow:0 1px 3px rgba(0,0,0,.06); border-left:4px solid var(--gold); }
  .stat .lbl { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#888; }
  .stat .val { font-size:20px; font-weight:700; color:var(--maroon); margin-top:2px; }
  form.filters { background:#fff; border-radius:10px; padding:12px; box-shadow:0 1px 3px rgba(0,0,0,.06); margin-bottom:14px; }
  .filters .row { display:flex; gap:8px; flex-wrap:wrap; align-items:end; }
  .filters .fld { flex:1; min-width:130px; }
  .filters label { display:block; font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
  .filters input, .filters select { width:100%; padding:9px 10px; border:1px solid #d8cfc0; border-radius:8px; font-size:15px; background:#fff; }
  .filters button { padding:9px 16px; background:var(--maroon); color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; }
  .daynav { display:flex; align-items:center; justify-content:space-between; margin:0 2px 12px; }
  .daynav a { text-decoration:none; color:var(--maroon); font-weight:600; font-size:14px; background:#fff; padding:8px 14px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.06); }
  .daynav .cur { font-weight:700; color:#444; font-size:15px; }

  /* Card list (mobile-first) */
  .list { display:flex; flex-direction:column; gap:10px; }
  .pcard { background:#fff; border-radius:10px; padding:14px; box-shadow:0 1px 3px rgba(0,0,0,.06); }
  .pcard .nm { font-weight:700; font-size:16px; color:#2a2a2a; }
  .pcard .sub { font-size:12px; color:#999; margin-top:1px; }
  .pcard .times { display:flex; gap:10px; margin-top:10px; }
  .pcard .t { flex:1; text-align:center; background:var(--cream); border-radius:8px; padding:8px 4px; }
  .pcard .t .k { font-size:10px; text-transform:uppercase; letter-spacing:.5px; color:#9a7d3a; }
  .pcard .t .v { font-size:15px; font-weight:700; color:#3a1010; margin-top:2px; }
  .pcard .meta { display:flex; justify-content:space-between; margin-top:10px; font-size:12px; color:#888; }
  .badge { display:inline-block; background:#eee; border-radius:20px; padding:2px 10px; font-size:11px; color:#555; }
  .empty { background:#fff; border-radius:10px; padding:30px; text-align:center; color:#999; }
  .foot { text-align:center; color:#aaa; font-size:11px; margin:18px 0; }

  /* Late highlighting */
  .pcard.late { border-left:5px solid #c0392b; background:#fff6f5; }
  .pcard.late .t.first { background:#f7d9d5; }
  .pcard.late .t.first .v { color:#a5281b; }
  .badge.late-badge { background:#c0392b; color:#fff; font-weight:600; }
  .stat.late-stat { border-left-color:#c0392b; }
  .stat.late-stat .val { color:#c0392b; }
</style>
</head>
<body>
<div class="wrap">

  <div class="topbar">
    <h1>Daily Attendance</h1>
    <img class="logo" src="https://d15k2d11r6t6rl.cloudfront.net/pub/bfra/re3npkbr/uk0/cg1/09s/cropped-Vantage_africa_logo-PNG-1.png" alt="Vantage Africa">
  </div>

  <div class="stats">
    <div class="stat">
      <div class="lbl">Date</div>
      <div class="val"><?= h(date('d M Y', strtotime($day))) ?></div>
    </div>
    <div class="stat">
      <div class="lbl">Staff Present</div>
      <div class="val"><?= (int)$presentCount ?></div>
    </div>
    <div class="stat late-stat">
      <div class="lbl">Late (after 8:20)</div>
      <div class="val"><?= (int)$lateCount ?></div>
    </div>
  </div>

  <form class="filters" method="get">
    <div class="row">
      <div class="fld">
        <label>Date</label>
        <input type="date" name="day" value="<?= h($day) ?>">
      </div>
      <div class="fld">
        <label>Device</label>
        <select name="device">
          <option value="">All Devices</option>
          <?php foreach ($devices as $dv): ?>
            <option value="<?= h($dv) ?>" <?= $fDevice===$dv?'selected':'' ?>><?= h($dv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fld" style="flex:0 0 auto;">
        <label>&nbsp;</label>
        <button type="submit">View</button>
      </div>
    </div>
  </form>

  <div class="daynav">
    <a href="?day=<?= h($prevDay) ?><?= $fDevice!==''?'&device='.h($fDevice):'' ?>">&larr; Prev</a>
    <span class="cur"><?= h(date('D, d M Y', strtotime($day))) ?></span>
    <a href="?day=<?= h($nextDay) ?><?= $fDevice!==''?'&device='.h($fDevice):'' ?>">Next &rarr;</a>
  </div>

  <?php if ($people): ?>
    <div class="list">
      <?php foreach ($people as $p):
        $hasOut = $p['count'] > 1;
        $name   = $p['name'] ?: 'Unknown';
        $isLate = !empty($p['late']);
      ?>
        <div class="pcard<?= $isLate ? ' late' : '' ?>">
          <div class="nm">
            <?= h($name) ?>
            <?php if ($isLate): ?>
              <span class="badge late-badge">Late <?= h(mins_hm($p['late_mins'])) ?></span>
            <?php endif; ?>
          </div>
          <div class="sub">
            <?= $p['staff_id'] ? 'Staff ID: '.h($p['staff_id']).' &middot; ' : '' ?>User <?= h($p['device_user_id']) ?>
          </div>
          <div class="times">
            <div class="t first">
              <div class="k">First In</div>
              <div class="v"><?= hhmm($p['first']) ?></div>
            </div>
            <div class="t">
              <div class="k">Last Out</div>
              <div class="v"><?= $hasOut ? hhmm($p['last']) : '-' ?></div>
            </div>
            <div class="t">
              <div class="k">Hours</div>
              <div class="v"><?= worked($p['first'], $hasOut ? $p['last'] : null) ?></div>
            </div>
          </div>
          <div class="meta">
            <span class="badge"><?= (int)$p['count'] ?> punch<?= $p['count']==1?'':'es' ?></span>
            <span><?= h($p['device_id']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty">No attendance recorded for this day.</div>
  <?php endif; ?>

  <div class="foot">Vantage Africa School of Leadership &middot; Attendance</div>

</div>
</body>
</html>