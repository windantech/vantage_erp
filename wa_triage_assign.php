<?php
/**
 * TRIAGE SWEEP: find the chats sitting unowned in Triage and route them in bulk.
 *
 * These are conversations the live router never classified — usually because the
 * AI call failed (no tokens / provider error / timeout) and the chat went silent,
 * so nobody was ever assigned and nobody followed up. This script re-runs the
 * SAME classification the router uses, on the WHOLE inbound history rather than
 * the single message that happened to arrive when the AI was down.
 *
 * Classification chain (identical order to wa_route_inbound):
 *   1. EVENT by location      — wa_classify_event()      -> event rep
 *   2. COURSE by keyword      — wa_classify_course()      -> course rep
 *   3. ACADEMIC by title      — wa_classify_academic()    -> academic event rep
 *   4. TRAINING PROGRAMME     — wa_program_for_course() / wa_program_match()
 *                                                         -> programme rep
 * Keyword matching is free and deterministic. The AI fallbacks the router uses
 * are OFF unless you pass --ai (they cost tokens, which is what broke these
 * chats in the first place).
 *
 * It also stamps delivery_mode when the customer clearly said onsite/virtual and
 * the router never recorded it — that is what makes the AI stop offering the
 * virtual option and stop asking which country they are in.
 *
 * WHAT IT DELIBERATELY DOES NOT DO
 *   - It never touches last_message_at. wa_assign_conversation() bumps it to
 *     NOW(), which on a bulk run would shove hundreds of old chats to the top of
 *     everyone's inbox and reset the 30-day triage window, so this writes its own
 *     UPDATE instead.
 *   - It never moves a chat a human has taken over (handler = 'human').
 *   - It never touches a chat that already has an owner — by definition those are
 *     not in Triage.
 *
 * Usage:
 *   php wa_triage_assign.php                      # dry run — shows what it would do
 *   php wa_triage_assign.php --apply              # route everything it can match
 *   php wa_triage_assign.php --days=90            # look further back than the 30-day window
 *   php wa_triage_assign.php --ai --apply         # also use the AI classifiers (costs tokens)
 *   php wa_triage_assign.php --apply --fallback=121,134
 *                                                 # round-robin whatever still has no match
 *                                                 # across these staff ids, so nothing is left
 *   php wa_triage_assign.php --limit=50           # try a small batch first
 *   php wa_triage_assign.php --staff              # just list eligible staff ids and exit
 *
 * Browser (behind the normal CRM login):
 *   /admin/wa_triage_assign.php?apply=1&days=90&fallback=121,134
 */

$IS_CLI = (PHP_SAPI === 'cli');

if ($IS_CLI) {
    $opt = getopt('', ['apply', 'ai', 'staff', 'days::', 'limit::', 'minchars::', 'fallback::']);
    $APPLY    = array_key_exists('apply', $opt);
    $USE_AI   = array_key_exists('ai', $opt);
    $SHOW_STF = array_key_exists('staff', $opt);
    $DAYS     = isset($opt['days'])     ? max(1, (int)$opt['days'])     : 30;
    $LIMIT    = isset($opt['limit'])    ? max(1, (int)$opt['limit'])    : 0;
    $MINCHARS = isset($opt['minchars']) ? max(1, (int)$opt['minchars']) : 8;
    $FALLBACK = isset($opt['fallback']) ? (string)$opt['fallback']      : '';
} else {
    $APPLY    = !empty($_GET['apply']);
    $USE_AI   = !empty($_GET['ai']);
    $SHOW_STF = !empty($_GET['staff']);
    $DAYS     = isset($_GET['days'])     ? max(1, (int)$_GET['days'])     : 30;
    $LIMIT    = isset($_GET['limit'])    ? max(1, (int)$_GET['limit'])    : 0;
    $MINCHARS = isset($_GET['minchars']) ? max(1, (int)$_GET['minchars']) : 8;
    $FALLBACK = isset($_GET['fallback']) ? (string)$_GET['fallback']      : '';
}

// wa_triage_sql() bakes these in as constants, so set them BEFORE wa_functions.php
// defines its defaults — that is how --days widens the window the inbox uses.
define('WA_TRIAGE_RECENT_DAYS', $DAYS);
define('WA_TRIAGE_MIN_CHARS',   $MINCHARS);

if ($IS_CLI) {
    require_once __DIR__ . '/includes/wa_config.php';
    require_once __DIR__ . '/includes/wa_db.php';       // $wa_conn
    require_once __DIR__ . '/includes/wa_functions.php';
    $conn = $wa_conn;
} else {
    require_once __DIR__ . '/auth.php';                 // login gate + $conn
    require_once __DIR__ . '/includes/wa_config.php';
    require_once __DIR__ . '/includes/wa_functions.php';
    header('Content-Type: text/plain; charset=utf-8');
}

if (!$conn) { exit("No database connection\n"); }
mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+03:00'");
wa_conv_mode_schema_ensure($conn);
wa_contact_country_schema_ensure($conn);

/* ---------------------------------------------------------------- staff list */

$staff = wa_role44_users($conn);
if ($SHOW_STF) {
    echo "=== Staff eligible to own WhatsApp chats (ERP role 44) ===\n\n";
    foreach ($staff as $s) { printf("  %-6d %s\n", (int)$s['id'], (string)$s['fullname']); }
    echo "\nUse the ids with --fallback=ID[,ID,...]\n";
    exit(0);
}

// Round-robin pool for chats nothing matches. Validated against role 44 so a typo
// cannot park hundreds of chats on a user who will never see the inbox.
$valid    = [];
foreach ($staff as $s) { $valid[(int)$s['id']] = (string)$s['fullname']; }
$pool     = [];
$badIds   = [];
foreach (array_filter(array_map('trim', explode(',', $FALLBACK)), 'strlen') as $raw) {
    $id = (int)$raw;
    if ($id > 0 && isset($valid[$id])) { $pool[] = $id; } else { $badIds[] = $raw; }
}
if ($badIds) {
    echo "ERROR: not valid WhatsApp staff ids (ERP role 44): " . implode(', ', $badIds) . "\n";
    echo "Run with --staff to list the ids you can use.\n";
    exit(1);
}

/* -------------------------------------------------------------------- header */

echo "=== Triage sweep: route the chats nobody owns ===\n";
echo $APPLY ? "MODE: APPLY (writing)\n" : "MODE: DRY RUN — nothing is written. Add --apply to commit.\n";
echo "WINDOW: last {$DAYS} days, inbound message of at least {$MINCHARS} characters\n";
echo "AI CLASSIFIERS: " . ($USE_AI ? "on (costs tokens)" : "off — keyword matching only") . "\n";
echo "FALLBACK: " . ($pool ? "round-robin across " . implode(', ', array_map(
        function ($id) use ($valid) { return $id . ' (' . $valid[$id] . ')'; }, $pool))
    : "none — unmatched chats are listed but left alone") . "\n\n";

/* --------------------------------------------------------- load the backlog */

$sql = "SELECT cv.id, cv.contact_id, cv.ref_type, cv.ref_id, cv.program_id, cv.handler,
               cv.delivery_mode, cv.last_message_at, cv.last_route_reason,
               c.wa_id, c.profile_name, c.country
          FROM wa_conversations cv
          JOIN wa_contacts c ON c.id = cv.contact_id
         WHERE " . wa_triage_sql('cv') . "
      ORDER BY cv.last_message_at DESC";
if ($LIMIT > 0) { $sql .= " LIMIT " . (int)$LIMIT; }

$res  = mysqli_query($conn, $sql);
if (!$res) { exit("Query failed: " . mysqli_error($conn) . "\n"); }
$rows = [];
while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }

if (!$rows) {
    echo "Nothing in Triage for this window. Try a wider --days.\n";
    exit(0);
}
echo "Found " . count($rows) . " chat(s) in Triage.\n\n";

$courses = wa_active_courses($conn);

/* ------------------------------------------------------------- classify each */

$plan  = [];
$stats = ['event' => 0, 'course' => 0, 'academic' => 0, 'program' => 0,
          'fallback' => 0, 'nomatch' => 0, 'silent' => 0, 'mode_set' => 0,
          'human' => 0, 'norep' => 0];
$rr = 0;

foreach ($rows as $r) {
    $cid = (int)$r['contact_id'];

    // Classify on the WHOLE inbound history, not one message: the router only ever
    // saw messages one at a time, and the one it saw when the AI was down may have
    // been "hi" while the topic arrived in the next message.
    $texts = [];
    $mres = mysqli_query($conn,
        "SELECT body FROM wa_messages
          WHERE contact_id = $cid AND direction = 'inbound' AND type <> 'note'
            AND TRIM(COALESCE(body, '')) <> ''
       ORDER BY id ASC LIMIT 30");
    while ($m = mysqli_fetch_assoc($mres)) { $texts[] = (string)$m['body']; }
    $text = mb_substr(trim(implode(' | ', $texts)), 0, 2000);

    $outbound = (int) wa_scalar($conn,
        "SELECT COUNT(*) FROM wa_messages
          WHERE contact_id = $cid AND direction = 'outbound' AND type <> 'note'");
    if ($outbound === 0) { $stats['silent']++; }

    // Mode the customer stated but the router never recorded (its AI call died before
    // it got there). Setting it is what silences the virtual pitch and the country question.
    $modeSaid = wa_detect_delivery_mode($text);
    $modeNow  = (string)($r['delivery_mode'] ?? 'unknown');
    $setMode  = ($modeSaid !== '' && $modeNow === 'unknown') ? $modeSaid : '';
    if ($setMode !== '') { $stats['mode_set']++; }
    $mode = $setMode !== '' ? $setMode : $modeNow;

    $hit = ['type' => null, 'id' => null, 'uid' => null, 'prog' => null,
            'method' => '', 'conf' => 0.0, 'label' => ''];

    // 1. A specific in-person event named by its location.
    $ev = wa_classify_event($conn, $text);
    if (($ev['event_id'] === null || $ev['confidence'] < 0.60) && $USE_AI
        && preg_match('/\b(venue|country|from|based\s+in|attend|travel|located|location|city)\b/i', $text)) {
        $aiEv = wa_ai_classify_event($conn, $text);
        if ($aiEv['event_id'] !== null && $aiEv['confidence'] >= 0.60) { $ev = $aiEv; }
    }
    if ($ev['event_id'] !== null && $ev['confidence'] >= 0.60) {
        $hit = ['type' => 'event', 'id' => (int)$ev['event_id'],
                'uid' => wa_first_owner($conn, 'event', (int)$ev['event_id']),
                'prog' => null, 'method' => 'event_location',
                'conf' => (float)$ev['confidence'], 'label' => ''];
    }

    // 2. Course by keyword.
    if ($hit['type'] === null && $courses) {
        $g = wa_classify_course($text, $courses);
        if (($g['course_id'] === null || $g['confidence'] < 0.60) && $USE_AI) {
            $ai = wa_ai_classify_course($conn, $text, $courses);
            if ($ai['course_id'] !== null && $ai['confidence'] >= 0.60) { $g = $ai; }
        }
        if ($g['course_id'] !== null && $g['confidence'] >= 0.60) {
            $courseId = (int)$g['course_id'];
            $hit = ['type' => 'course', 'id' => $courseId,
                    'uid' => wa_first_owner($conn, 'course', $courseId),
                    'prog' => null, 'method' => 'course_keyword',
                    'conf' => (float)$g['confidence'], 'label' => ''];

            // Onsite enquiry on a dual-mode course: the programme rep owns it until a
            // country is known, exactly as the live router decides.
            if ($mode === 'onsite') {
                $twin = wa_course_onsite_event($conn, $courseId);
                if ($twin) {
                    $prog = wa_program_for_course($conn, $courseId, $text, (int)($twin['event_id'] ?? 0));
                    if ($prog) {
                        $hit['prog']   = (int)$prog['id'];
                        $hit['method'] = 'onsite_program';
                        $pu = wa_program_first_owner($prog);
                        if ($pu !== null) { $hit['uid'] = $pu; }
                        $hit['label'] = (string)($prog['name'] ?? '');
                    }
                }
            }
        }
    }

    // 3. Academic / online course named by title (these are Event rows).
    if ($hit['type'] === null) {
        $ac = wa_classify_academic($conn, $text);
        if (($ac['event_id'] === null || $ac['confidence'] < 0.60) && $USE_AI) {
            $aiAc = wa_ai_classify_academic($conn, $text);
            if ($aiAc['event_id'] !== null && $aiAc['confidence'] >= 0.60) { $ac = $aiAc; }
        }
        if ($ac['event_id'] !== null && $ac['confidence'] >= 0.60) {
            $hit = ['type' => 'event', 'id' => (int)$ac['event_id'],
                    'uid' => wa_first_owner($conn, 'event', (int)$ac['event_id']),
                    'prog' => null, 'method' => 'academic_title',
                    'conf' => (float)$ac['confidence'], 'label' => ''];
        }
    }

    // 4. No course or event, but the text names a training programme ("public speaking",
    //    "M&E"). Programme reps are the right home for these — that is the whole point
    //    of programme ownership.
    if ($hit['type'] === null) {
        $prog = wa_program_match($conn, $text);
        if ($prog) {
            $hit = ['type' => 'program', 'id' => null,
                    'uid' => wa_program_first_owner($prog), 'prog' => (int)$prog['id'],
                    'method' => 'program_keyword', 'conf' => 0.60,
                    'label' => (string)($prog['name'] ?? '')];
        }
    }

    // 5. Still nothing to go on — hand it to a human from the pool so it stops rotting.
    if ($hit['type'] === null && $pool) {
        $hit = ['type' => 'unclassified', 'id' => null, 'uid' => $pool[$rr % count($pool)],
                'prog' => null, 'method' => 'triage_fallback', 'conf' => 0.0, 'label' => ''];
        $rr++;
    }

    if     ($hit['type'] === null)           { $stats['nomatch']++; }
    elseif ($hit['type'] === 'unclassified') { $stats['fallback']++; }
    elseif ($hit['method'] === 'onsite_program' || $hit['type'] === 'program') { $stats['program']++; }
    elseif ($hit['method'] === 'academic_title') { $stats['academic']++; }
    elseif ($hit['type'] === 'event')        { $stats['event']++; }
    else                                     { $stats['course']++; }

    // Count the two reasons a matched chat still will not be written, so the dry-run
    // numbers add up to what --apply actually does instead of overpromising.
    if ((string)($r['handler'] ?? 'ai') === 'human')            { $stats['human']++; }
    elseif ($hit['type'] !== null && $hit['uid'] === null)      { $stats['norep']++; }

    $plan[] = ['row' => $r, 'hit' => $hit, 'mode' => $setMode,
               'text' => $text, 'outbound' => $outbound];
}

/* ----------------------------------------------------------------- the plan */

/** Pad to a visible width. printf's %-Ns counts bytes, so one accented name or an
 *  em dash silently shifts every column after it. */
function wa_pad($s, $w) { $n = mb_strlen((string)$s); return $s . str_repeat(' ', max(0, $w - $n)); }

printf("%-6s %-15s %s %-8s %s %s %s\n",
       'CONV', 'NUMBER', wa_pad('NAME', 18), 'REPLIES', wa_pad('ROUTE', 26),
       wa_pad('OWNER', 22), 'FIRST WORDS');
echo str_repeat('-', 140) . "\n";

foreach ($plan as $p) {
    $r = $p['row']; $h = $p['hit'];
    $route = $h['type'] === null ? '(no match)'
        : ($h['type'] === 'unclassified' ? 'unclassified -> pool'
        : ($h['label'] !== '' ? $h['method'] . ':' . mb_substr($h['label'], 0, 14)
                              : $h['method'] . ':' . (string)$h['id']));
    $owner = $h['uid'] === null
        ? '-- NO REP --'
        : mb_substr((string) wa_user_name($conn, (int)$h['uid']) ?: ('#' . (int)$h['uid']), 0, 20);
    printf("%-6d %-15s %s %-8s %s %s %s\n",
        (int)$r['id'],
        mb_substr((string)$r['wa_id'], 0, 15),
        wa_pad(mb_substr((string)($r['profile_name'] ?: '(no name)'), 0, 18), 18),
        $p['outbound'] === 0 ? 'SILENT' : (string)$p['outbound'],
        wa_pad(mb_substr($route, 0, 26), 26),
        wa_pad($owner, 22),
        mb_substr(preg_replace('/\s+/u', ' ', $p['text']), 0, 46));
}

/* --------------------------------------------------------------- write them */

$wrote = 0; $skippedHuman = 0; $noOwner = 0;

if ($APPLY) {
    echo "\n--- applying ---\n";
    foreach ($plan as $p) {
        $r = $p['row']; $h = $p['hit'];
        $convId = (int)$r['id'];

        // A colleague is already handling this by hand — never yank it from them.
        if ((string)($r['handler'] ?? 'ai') === 'human') { $skippedHuman++; continue; }
        if ($h['type'] === null) { continue; }

        // Matched a topic, but neither that topic nor its programme has a rep. Writing
        // ref_type/program_id anyway would pull the chat OUT of Triage and into nobody's
        // inbox — strictly worse than leaving it where every rep can still see it.
        // (uid is only null here when the ref AND the matched programme are both unowned.)
        if ($h['uid'] === null) { $noOwner++; continue; }

        $sets = [];
        if ($h['type'] === 'event' || $h['type'] === 'course') {
            $sets[] = "ref_type = '" . mysqli_real_escape_string($conn, $h['type']) . "'";
            $sets[] = "ref_id = " . (int)$h['id'];
        }
        if ($h['prog'] !== null)      { $sets[] = "program_id = " . (int)$h['prog']; }
        if ($h['uid'] !== null)       { $sets[] = "assigned_user_id = " . (int)$h['uid']; }
        if ($p['mode'] !== '')        { $sets[] = "delivery_mode = '" . $p['mode'] . "'"; }
        $sets[] = "last_route_reason = '" . mysqli_real_escape_string($conn, 'sweep_' . $h['method']) . "'";
        $sets[] = "last_route_confidence = " . (float)$h['conf'];

        // NOTE: last_message_at is deliberately absent. Touching it would reorder every
        // rep's inbox and reset the triage window for chats that are months old.
        $ok = mysqli_query($conn, "UPDATE wa_conversations SET " . implode(', ', $sets)
                                . " WHERE id = $convId");
        if ($ok) { $wrote++; }
        else     { echo "  conv $convId FAILED: " . mysqli_error($conn) . "\n"; }
    }
}

/* ------------------------------------------------------------------ summary */

echo "\n=== Summary ===\n";
printf("  in triage ................. %d\n", count($rows));
printf("  never got a reply at all .. %d   <- the AI went silent on these\n", $stats['silent']);
printf("  delivery mode recovered ... %d\n", $stats['mode_set']);
echo   "  ---------------------------\n";
printf("  matched an event .......... %d\n", $stats['event']);
printf("  matched a course .......... %d\n", $stats['course']);
printf("  matched academic course ... %d\n", $stats['academic']);
printf("  matched a programme ....... %d\n", $stats['program']);
printf("  no match -> fallback pool .. %d\n", $stats['fallback']);
printf("  no match, left in triage ... %d\n", $stats['nomatch']);
if ($stats['human'] || $stats['norep']) {
    echo   "  ---------------------------\n";
    if ($stats['human']) { printf("  of those, NOT written: %d being handled by a human\n", $stats['human']); }
    if ($stats['norep']) { printf("  of those, NOT written: %d whose topic has no rep\n", $stats['norep']); }
}

if ($APPLY) {
    echo "  ---------------------------\n";
    printf("  UPDATED ................... %d\n", $wrote);
    if ($skippedHuman) { printf("  skipped (human handling) .. %d\n", $skippedHuman); }
    if ($noOwner)      { printf("  skipped (topic has no rep)  %d  <- assign a rep, then re-run\n", $noOwner); }
} else {
    echo "\nDry run only. Re-run with --apply to write.\n";
}

if ($stats['nomatch'] > 0 && !$pool) {
    echo "\nTip: " . $stats['nomatch'] . " chat(s) match no course, event or programme.\n";
    echo "     Either create the missing training programme, or hand them out with\n";
    echo "     --fallback=ID[,ID] (see --staff for the ids).\n";
}
echo "\nDone.\n";
