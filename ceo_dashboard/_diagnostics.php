<?php
/**
 * _diagnostics.php  —  Standalone downtime / health diagnostic for the CEO dashboard.
 *
 * WHAT IT DOES
 *   Runs a battery of read-only checks against the things that actually take this
 *   system down: database connectivity, "too many connections", slow queries hitting
 *   the execution-time limit, memory limits, disk space, broken include paths, and
 *   recent fatal errors in the PHP error log. Nothing here writes to the database or
 *   changes any file.
 *
 * HOW TO USE
 *   1. Upload this file into the ceo_dashboard/ directory (next to header.php).
 *   2. Set $ACCESS_TOKEN below to something only you know.
 *   3. Browser:  https://your-site/admin/ceo_dashboard/_diagnostics.php?key=YOUR_TOKEN
 *      or CLI:   php _diagnostics.php
 *   4. Read the report. Anything red (FAIL) or amber (WARN) is a downtime suspect.
 *   5. DELETE THIS FILE when you're done — it exposes server internals.
 */

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
$ACCESS_TOKEN = 'CHANGE_ME_diag_2026';   // set to '' to disable the gate (NOT recommended on a live site)

// Make sure the diagnostic itself never times out or dies on a big result set.
@set_time_limit(0);
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '1');
error_reporting(E_ALL);

$IS_CLI = (php_sapi_name() === 'cli');

// Access gate (CLI is always allowed).
if (!$IS_CLI && $ACCESS_TOKEN !== '' && (($_GET['key'] ?? '') !== $ACCESS_TOKEN)) {
    http_response_code(403);
    exit("Forbidden. Append ?key=YOUR_TOKEN to the URL (see \$ACCESS_TOKEN in this file).\n");
}

// If mysqli is set to throw (PHP 8.1+ default), turn that off so a bad query
// returns false and we can report it instead of the whole script dying.
if (function_exists('mysqli_report')) {
    @mysqli_report(MYSQLI_REPORT_OFF);
}

// ---------------------------------------------------------------------------
// Result collection + rendering helpers
// ---------------------------------------------------------------------------
$RESULTS = [];               // [ ['section','label','status','detail'], ... ]
$COUNTS  = ['PASS' => 0, 'WARN' => 0, 'FAIL' => 0, 'INFO' => 0];

function add($section, $label, $status, $detail = '') {
    global $RESULTS, $COUNTS;
    $status = strtoupper($status);
    if (!isset($COUNTS[$status])) $COUNTS[$status] = 0;
    $COUNTS[$status]++;
    $RESULTS[] = compact('section', 'label', 'status', 'detail');
}

/** Run a check callback safely — a thrown error becomes a FAIL, never a white screen. */
function safe($section, $label, callable $fn) {
    try {
        $fn();
    } catch (\Throwable $e) {
        add($section, $label, 'FAIL', 'Exception: ' . $e->getMessage());
    }
}

function human_bytes($n) {
    if ($n <= 0) return '0 B';
    $u = ['B','KB','MB','GB','TB'];
    $i = (int) floor(log($n, 1024));
    return round($n / (1024 ** $i), 2) . ' ' . $u[$i];
}

function ini_bytes($val) {
    $val = trim((string)$val);
    if ($val === '' || $val === '-1') return -1;
    $last = strtolower($val[strlen($val) - 1]);
    $num = (float) $val;
    switch ($last) { case 'g': $num *= 1024; case 'm': $num *= 1024; case 'k': $num *= 1024; }
    return (int) $num;
}

// ===========================================================================
// SECTION 1 — PHP runtime
// ===========================================================================
safe('PHP runtime', 'PHP version', function () {
    $v = PHP_VERSION;
    $status = version_compare($v, '7.4', '<') ? 'WARN' : 'PASS';
    add('PHP runtime', 'PHP version', $status, "$v (" . php_sapi_name() . ")");
});

safe('PHP runtime', 'Required extensions', function () {
    $needed = ['mysqli', 'json', 'mbstring', 'curl', 'session'];
    $missing = array_values(array_filter($needed, fn($e) => !extension_loaded($e)));
    if ($missing) add('PHP runtime', 'Required extensions', 'FAIL', 'MISSING: ' . implode(', ', $missing));
    else add('PHP runtime', 'Required extensions', 'PASS', 'all present: ' . implode(', ', $needed));
});

safe('PHP runtime', 'memory_limit', function () {
    $ml = ini_get('memory_limit');
    $b  = ini_bytes($ml);
    $status = ($b !== -1 && $b < 128 * 1024 * 1024) ? 'WARN' : 'PASS';
    add('PHP runtime', 'memory_limit', $status, $ml . ($status === 'WARN' ? '  (low — big reports may exhaust it)' : ''));
});

safe('PHP runtime', 'max_execution_time', function () {
    $t = (int) ini_get('max_execution_time');
    // 0 = unlimited (CLI). Low values + slow SQL = 500 errors / blank pages.
    $status = ($t !== 0 && $t < 30) ? 'WARN' : 'PASS';
    add('PHP runtime', 'max_execution_time', $status, $t . 's' . ($status === 'WARN' ? '  (slow queries may exceed this and 500)' : ''));
});

safe('PHP runtime', 'Error logging', function () {
    $log = ini_get('error_log');
    $disp = ini_get('display_errors') ? 'On' : 'Off';
    add('PHP runtime', 'Error logging', 'INFO', "log_errors=" . (ini_get('log_errors') ? 'On' : 'Off')
        . ", display_errors=$disp, error_log=" . ($log ?: '(default/stderr)'));
});

safe('PHP runtime', 'Peak memory (this script)', function () {
    add('PHP runtime', 'Peak memory (this script)', 'INFO', human_bytes(memory_get_peak_usage(true)));
});

// ===========================================================================
// SECTION 2 — Filesystem / disk
// ===========================================================================
safe('Filesystem', 'Free disk space', function () {
    $free  = @disk_free_space(__DIR__);
    $total = @disk_total_space(__DIR__);
    if ($free === false) { add('Filesystem', 'Free disk space', 'WARN', 'could not read disk space'); return; }
    $pct = $total ? round($free / $total * 100, 1) : 0;
    $status = $free < 100 * 1024 * 1024 ? 'FAIL' : ($free < 500 * 1024 * 1024 ? 'WARN' : 'PASS');
    add('Filesystem', 'Free disk space', $status, human_bytes($free) . " free of " . human_bytes($total) . " ($pct%)"
        . ($status !== 'PASS' ? '  (a full disk crashes MySQL writes & sessions)' : ''));
});

safe('Filesystem', 'App directory writable', function () {
    $w = is_writable(__DIR__);
    add('Filesystem', 'App directory writable', $w ? 'PASS' : 'INFO', __DIR__ . ($w ? ' (writable)' : ' (read-only)'));
});

// ===========================================================================
// SECTION 3 — Critical include paths (the "files in the wrong place" class)
// ===========================================================================
safe('Includes', 'Critical include paths', function () {
    // Paths are resolved relative to THIS file, assumed to sit in ceo_dashboard/.
    $paths = [
        'conn.php'      => __DIR__ . '/../../database/conn.php',
        'function.php'  => __DIR__ . '/../function.php',
        'header.php'    => __DIR__ . '/header.php',
    ];
    foreach ($paths as $name => $p) {
        $real = realpath($p);
        if ($real && is_readable($real)) {
            add('Includes', "resolve: $name", 'PASS', $real);
        } else {
            add('Includes', "resolve: $name", 'FAIL', "NOT found/readable at: $p  (require of this path is a fatal crash)");
        }
    }
});

// ===========================================================================
// SECTION 4 — Database
// ===========================================================================
$conn = null;

safe('Database', 'Connect via conn.php', function () use (&$conn) {
    $connPath = __DIR__ . '/../../database/conn.php';
    if (!is_readable($connPath)) {
        add('Database', 'Connect via conn.php', 'FAIL', "conn.php not readable at $connPath");
        return;
    }
    $t0 = microtime(true);
    ob_start();
    include $connPath;                 // expected to define $conn (mysqli)
    ob_end_clean();
    $ms = round((microtime(true) - $t0) * 1000, 1);

    if (!isset($conn) || !($conn instanceof mysqli)) {
        add('Database', 'Connect via conn.php', 'FAIL', 'conn.php did not provide a valid $conn (mysqli). Check credentials/host.');
        $conn = null;
        return;
    }
    if (@mysqli_connect_errno()) {
        add('Database', 'Connect via conn.php', 'FAIL', 'connect error: ' . mysqli_connect_error());
        $conn = null;
        return;
    }
    $status = $ms > 3000 ? 'FAIL' : ($ms > 1000 ? 'WARN' : 'PASS');
    add('Database', 'Connect via conn.php', $status, "connected in {$ms} ms; server=" . $conn->server_info
        . ($status !== 'PASS' ? '  (slow connect — DB host under load?)' : ''));
});

// helper: run a query with timing, return [rows(array)|null, ms, error]
function timed_query($conn, $sql) {
    $t0 = microtime(true);
    $res = @mysqli_query($conn, $sql);
    $ms  = round((microtime(true) - $t0) * 1000, 1);
    if ($res === false) return [null, $ms, mysqli_error($conn)];
    $rows = [];
    if ($res instanceof mysqli_result) {
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        mysqli_free_result($res);
    }
    return [$rows, $ms, null];
}

if ($conn instanceof mysqli) {

    safe('Database', 'Current database', function () use ($conn) {
        [$rows,, $err] = timed_query($conn, 'SELECT DATABASE() AS db, VERSION() AS ver');
        if ($err) { add('Database', 'Current database', 'WARN', $err); return; }
        add('Database', 'Current database', 'INFO', 'db=' . ($rows[0]['db'] ?? '?') . ', MySQL ' . ($rows[0]['ver'] ?? '?'));
    });

    // Connection saturation — the classic "site is down: Too many connections".
    safe('Database', 'Connection saturation', function () use ($conn) {
        [$s] = timed_query($conn, "SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected','Max_used_connections','Aborted_connects')");
        [$v] = timed_query($conn, "SHOW VARIABLES WHERE Variable_name = 'max_connections'");
        $stat = [];
        foreach ((array)$s as $r) $stat[$r['Variable_name']] = $r['Value'];
        $max = (int) ($v[0]['Value'] ?? 0);
        $cur = (int) ($stat['Threads_connected'] ?? 0);
        $peak = (int) ($stat['Max_used_connections'] ?? 0);
        $aborted = (int) ($stat['Aborted_connects'] ?? 0);
        $pct = $max ? round($cur / $max * 100, 1) : 0;
        $peakPct = $max ? round($peak / $max * 100, 1) : 0;
        $status = ($peakPct >= 90 || $pct >= 80) ? 'WARN' : 'PASS';
        add('Database', 'Connection saturation', $status,
            "now $cur/$max ($pct%), peak $peak ($peakPct%), aborted_connects=$aborted"
            . ($status === 'WARN' ? '  (approaching the connection limit = intermittent downtime)' : ''));
    });

    // Slow-query & uptime signals.
    safe('Database', 'Slow query / uptime signals', function () use ($conn) {
        [$s] = timed_query($conn, "SHOW GLOBAL STATUS WHERE Variable_name IN ('Slow_queries','Uptime','Questions')");
        $stat = [];
        foreach ((array)$s as $r) $stat[$r['Variable_name']] = $r['Value'];
        $slow = (int) ($stat['Slow_queries'] ?? 0);
        $up   = (int) ($stat['Uptime'] ?? 0);
        $upTxt = $up ? sprintf('%dd %dh', intdiv($up, 86400), intdiv($up % 86400, 3600)) : '?';
        add('Database', 'Slow query / uptime signals', $slow > 0 ? 'WARN' : 'INFO',
            "Slow_queries=$slow since start; server uptime=$upTxt; total queries=" . ($stat['Questions'] ?? '?'));
    });

    // Largest tables — heavy tables are where dashboard queries bog down.
    safe('Database', 'Largest tables', function () use ($conn) {
        $sql = "SELECT table_name, table_rows,
                       (data_length + index_length) AS size_bytes
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                ORDER BY size_bytes DESC
                LIMIT 8";
        [$rows,, $err] = timed_query($conn, $sql);
        if ($err) { add('Database', 'Largest tables', 'WARN', $err); return; }
        foreach ((array)$rows as $r) {
            add('Database', 'table: ' . $r['table_name'], 'INFO',
                number_format((int)$r['table_rows']) . ' rows, ' . human_bytes((int)$r['size_bytes']));
        }
    });

    // Benchmark: time a COUNT(*) on the biggest tables — this is what actually
    // reveals a query slow enough to blow max_execution_time.
    safe('Database', 'Query timing benchmark', function () use ($conn) {
        [$big] = timed_query($conn, "SELECT table_name FROM information_schema.TABLES
                                     WHERE table_schema = DATABASE()
                                     ORDER BY (data_length + index_length) DESC LIMIT 3");
        if (empty($big)) { add('Database', 'Query timing benchmark', 'INFO', 'no tables found'); return; }
        foreach ($big as $t) {
            $tbl = $t['table_name'];
            // Backtick-quote the identifier; it comes from information_schema, not user input.
            [, $ms, $err] = timed_query($conn, "SELECT COUNT(*) AS c FROM `" . str_replace('`', '', $tbl) . "`");
            if ($err) { add('Database', "COUNT(*) $tbl", 'WARN', $err); continue; }
            $status = $ms > 5000 ? 'FAIL' : ($ms > 1000 ? 'WARN' : 'PASS');
            add('Database', "COUNT(*) $tbl", $status, "{$ms} ms"
                . ($status !== 'PASS' ? '  (slow — a page running this can time out)' : ''));
        }
    });

    // Slow-query logging config — so you know whether MySQL is even recording the culprits.
    safe('Database', 'Slow query log config', function () use ($conn) {
        [$v] = timed_query($conn, "SHOW VARIABLES WHERE Variable_name IN
            ('slow_query_log','slow_query_log_file','long_query_time','wait_timeout',
             'max_statement_time','max_execution_time','max_connections')");
        $m = [];
        foreach ((array)$v as $r) $m[$r['Variable_name']] = $r['Value'];
        $on = ($m['slow_query_log'] ?? 'OFF');
        add('Database', 'slow_query_log', $on === 'ON' ? 'PASS' : 'WARN',
            $on . ' · long_query_time=' . ($m['long_query_time'] ?? '?') . 's · file=' . ($m['slow_query_log_file'] ?? '?')
            . ($on !== 'ON' ? '  (turn ON to capture which queries are slow)' : ''));
        // MariaDB uses max_statement_time (seconds, 0=off); a per-query kill switch prevents runaway queries.
        $mst = $m['max_statement_time'] ?? null;
        if ($mst !== null) {
            add('Database', 'max_statement_time', ($mst === '0' || $mst === '0.000000') ? 'INFO' : 'PASS',
                $mst . 's' . (($mst === '0' || $mst === '0.000000') ? '  (0 = no query time limit; a runaway query can hang the page)' : ''));
        }
        add('Database', 'wait_timeout', 'INFO', ($m['wait_timeout'] ?? '?') . 's (idle connection lifetime)');
    });

    // Aborted-connection breakdown — explains the high aborted_connects number.
    safe('Database', 'Aborted connections', function () use ($conn) {
        [$s] = timed_query($conn, "SHOW GLOBAL STATUS WHERE Variable_name IN
            ('Aborted_clients','Aborted_connects','Connection_errors_max_connections',
             'Connection_errors_internal','Uptime')");
        $m = [];
        foreach ((array)$s as $r) $m[$r['Variable_name']] = (float) $r['Value'];
        $up = max(1, $m['Uptime'] ?? 1);
        $connRate = round(($m['Aborted_connects'] ?? 0) / ($up / 3600), 1);   // per hour
        $cliRate  = round(($m['Aborted_clients'] ?? 0) / ($up / 3600), 1);
        // Aborted_connects = failed handshake/auth (bad creds, bots, timeouts).
        add('Database', 'Aborted_connects', $connRate > 100 ? 'WARN' : 'INFO',
            number_format($m['Aborted_connects'] ?? 0) . " total, ~{$connRate}/hr"
            . ($connRate > 100 ? '  (high — failed logins/timeouts/port scans; check DB creds & who connects)' : ''));
        // Aborted_clients = connected but didn't close cleanly (scripts dying, timeouts mid-query).
        add('Database', 'Aborted_clients', $cliRate > 50 ? 'WARN' : 'INFO',
            number_format($m['Aborted_clients'] ?? 0) . " total, ~{$cliRate}/hr"
            . ($cliRate > 50 ? '  (scripts dying mid-query or wait_timeout hits)' : ''));
        if (!empty($m['Connection_errors_max_connections'])) {
            add('Database', 'Hit max_connections', 'FAIL',
                number_format($m['Connection_errors_max_connections']) . ' connections rejected for hitting the limit');
        }
    });

    // LIVE snapshot — what is running RIGHT NOW. Run this DURING a slow/down moment.
    safe('Database', 'Live process list', function () use ($conn) {
        [$rows,, $err] = timed_query($conn, "SHOW FULL PROCESSLIST");
        if ($err) { add('Database', 'Live process list', 'INFO', 'no PROCESS privilege — cannot see other sessions (' . $err . ')'); return; }
        $active = [];
        foreach ((array)$rows as $r) {
            $cmd  = $r['Command'] ?? '';
            $time = (int) ($r['Time'] ?? 0);
            if ($cmd === 'Sleep' || $cmd === 'Daemon') continue;        // idle
            if (($r['Info'] ?? '') === '' && $cmd !== 'Query') continue;
            $active[] = $r;
        }
        // sort longest-running first
        usort($active, fn($a, $b) => (int)$b['Time'] - (int)$a['Time']);
        add('Database', 'Active queries now', 'INFO', count($active) . ' non-idle (of ' . count((array)$rows) . ' total connections)');
        $shown = 0;
        foreach ($active as $r) {
            if ($shown++ >= 8) break;
            $time = (int) ($r['Time'] ?? 0);
            $status = $time > 10 ? 'FAIL' : ($time > 2 ? 'WARN' : 'PASS');
            $info = trim(preg_replace('/\s+/', ' ', (string)($r['Info'] ?? '')));
            add('Database', "pid {$r['Id']} · {$time}s · {$r['State']}", $status,
                ($r['db'] ?? '') . ' :: ' . mb_strimwidth($info !== '' ? $info : '(' . $r['Command'] . ')', 0, 220, '…'));
        }
    });

} else {
    add('Database', 'Database checks', 'FAIL', 'skipped — no DB connection (see Connect check above)');
}

// ===========================================================================
// SECTION 5 — Recent error log analysis (direct evidence of crashes)
// ===========================================================================
safe('Error log', 'Recent PHP errors', function () {
    // Candidate log locations, most specific first.
    $candidates = array_filter([
        ini_get('error_log') ?: null,
        __DIR__ . '/error_log',
        __DIR__ . '/../error_log',
        __DIR__ . '/../../error_log',
    ]);
    $logFile = null;
    foreach ($candidates as $c) { if ($c && is_readable($c)) { $logFile = $c; break; } }
    if (!$logFile) { add('Error log', 'Recent PHP errors', 'INFO', 'no readable error_log found at known paths'); return; }

    // Read the tail (last ~256 KB) without loading a huge file into memory.
    $size = filesize($logFile);
    $fh = fopen($logFile, 'rb');
    if (!$fh) { add('Error log', 'Recent PHP errors', 'WARN', "found $logFile but could not open it"); return; }
    $chunk = 256 * 1024;
    if ($size > $chunk) fseek($fh, -$chunk, SEEK_END);
    $data = stream_get_contents($fh);
    fclose($fh);
    $lines = array_filter(explode("\n", $data));

    $buckets = ['Fatal error' => 0, 'Parse error' => 0, 'Uncaught' => 0, 'Warning' => 0, 'Deprecated' => 0, 'Notice' => 0];
    $criticalSamples = [];
    foreach ($lines as $ln) {
        foreach ($buckets as $k => $_) {
            if (stripos($ln, $k) !== false) {
                $buckets[$k]++;
                if (in_array($k, ['Fatal error', 'Parse error', 'Uncaught'], true) && count($criticalSamples) < 12) {
                    $criticalSamples[] = trim($ln);
                }
            }
        }
    }
    add('Error log', 'Log file', 'INFO', "$logFile (" . human_bytes($size) . ", showing last " . human_bytes(min($size, $chunk)) . ")");

    $crit = $buckets['Fatal error'] + $buckets['Parse error'] + $buckets['Uncaught'];
    $status = $crit > 0 ? 'FAIL' : ($buckets['Warning'] > 0 ? 'WARN' : 'PASS');
    add('Error log', 'Recent PHP errors', $status,
        "Fatal={$buckets['Fatal error']} Parse={$buckets['Parse error']} Uncaught={$buckets['Uncaught']} "
        . "Warning={$buckets['Warning']} Deprecated={$buckets['Deprecated']} Notice={$buckets['Notice']}"
        . ($crit ? '  ← these are your crashes' : ''));

    foreach ($criticalSamples as $i => $s) {
        add('Error log', 'crash #' . ($i + 1), 'FAIL', mb_strimwidth($s, 0, 300, '…'));
    }
});

// ===========================================================================
// SECTION 6 — Session subsystem
// ===========================================================================
safe('Session', 'Session start', function () {
    if (session_status() === PHP_SESSION_DISABLED) { add('Session', 'Session start', 'FAIL', 'sessions disabled'); return; }
    $path = session_save_path() ?: sys_get_temp_dir();
    $ok = @session_start();
    $writable = is_dir($path) ? (is_writable($path) ? 'writable' : 'NOT writable') : 'path missing';
    $status = ($ok && $writable === 'writable') ? 'PASS' : 'WARN';
    add('Session', 'Session start', $status, "save_path=$path ($writable)"
        . ($status === 'WARN' ? '  (unwritable session dir = login failures / downtime)' : ''));
});

// ===========================================================================
// Render
// ===========================================================================
$total = array_sum($COUNTS);
$overall = $COUNTS['FAIL'] > 0 ? 'FAIL' : ($COUNTS['WARN'] > 0 ? 'WARN' : 'PASS');

if ($IS_CLI) {
    $c = ['PASS' => "\033[32m", 'WARN' => "\033[33m", 'FAIL' => "\033[31m", 'INFO' => "\033[36m"];
    $r = "\033[0m";
    echo "\n=== CEO Dashboard Diagnostics ===  overall: {$c[$overall]}$overall$r\n";
    echo "PASS {$COUNTS['PASS']}  WARN {$COUNTS['WARN']}  FAIL {$COUNTS['FAIL']}  INFO {$COUNTS['INFO']}\n\n";
    $sec = null;
    foreach ($RESULTS as $row) {
        if ($row['section'] !== $sec) { $sec = $row['section']; echo "\n-- $sec --\n"; }
        printf("  %s%-5s%s  %-32s %s\n", $c[$row['status']] ?? '', $row['status'], $r, $row['label'], $row['detail']);
    }
    echo "\nDone. DELETE this file when finished.\n";
    exit;
}

// HTML output
header('Content-Type: text/html; charset=utf-8');
$badge = ['PASS' => '#16a34a', 'WARN' => '#d97706', 'FAIL' => '#dc2626', 'INFO' => '#0891b2'];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CEO Dashboard — Diagnostics</title>
<style>
  :root { color-scheme: light dark; }
  body { font: 14px/1.5 system-ui, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
  .wrap { max-width: 960px; margin: 0 auto; padding: 24px; }
  h1 { font-size: 20px; margin: 0 0 4px; }
  .sub { color: #94a3b8; margin-bottom: 20px; }
  .summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 24px; }
  .pill { padding: 8px 14px; border-radius: 8px; font-weight: 600; background: #1e293b; }
  .overall { font-size: 15px; }
  section { background: #1e293b; border-radius: 10px; margin-bottom: 16px; overflow: hidden; }
  section > h2 { font-size: 14px; margin: 0; padding: 10px 16px; background: #334155; text-transform: uppercase; letter-spacing: .5px; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 8px 16px; border-top: 1px solid #334155; vertical-align: top; }
  td.st { width: 64px; }
  .tag { display: inline-block; min-width: 44px; text-align: center; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; color: #fff; }
  td.lbl { width: 230px; color: #cbd5e1; font-weight: 600; }
  td.det { color: #94a3b8; word-break: break-word; }
  .note { color: #64748b; font-size: 12px; margin-top: 20px; }
  code { background:#0f172a; padding:1px 5px; border-radius:4px; }
</style></head>
<body><div class="wrap">
  <h1>CEO Dashboard — Downtime Diagnostics</h1>
  <div class="sub">Generated <?= date('Y-m-d H:i:s') ?> · host <?= htmlspecialchars(gethostname() ?: '?') ?> · <?= htmlspecialchars(__DIR__) ?></div>

  <div class="summary">
    <span class="pill overall" style="border:2px solid <?= $badge[$overall] ?>">Overall: <span style="color:<?= $badge[$overall] ?>"><?= $overall ?></span></span>
    <span class="pill">PASS <?= $COUNTS['PASS'] ?></span>
    <span class="pill">WARN <?= $COUNTS['WARN'] ?></span>
    <span class="pill">FAIL <?= $COUNTS['FAIL'] ?></span>
    <span class="pill">INFO <?= $COUNTS['INFO'] ?></span>
  </div>

<?php
$sec = null;
foreach ($RESULTS as $row) {
    if ($row['section'] !== $sec) {
        if ($sec !== null) echo "</table></section>\n";
        $sec = $row['section'];
        echo '<section><h2>' . htmlspecialchars($sec) . "</h2><table>\n";
    }
    $col = $badge[$row['status']] ?? '#64748b';
    echo '<tr><td class="st"><span class="tag" style="background:' . $col . '">' . $row['status'] . '</span></td>'
       . '<td class="lbl">' . htmlspecialchars($row['label']) . '</td>'
       . '<td class="det">' . htmlspecialchars($row['detail']) . "</td></tr>\n";
}
if ($sec !== null) echo "</table></section>\n";
?>
  <p class="note">Read-only — nothing was written to the database or filesystem.
  <strong>Delete <code>_diagnostics.php</code> when you're done</strong> — it exposes server internals.</p>
</div></body></html>
