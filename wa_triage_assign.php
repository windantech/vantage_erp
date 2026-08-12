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
 * Keyword matching is free and deterministic, but it drops a lot: when two titles
 * tie on the top score the classifiers rate the match 0.35, under their own 0.60
 * threshold, so messages that plainly name a product ("more info on the Senior
 * Management Leadership Programme") are reported as no match. --ai adds a step
 * that READS the conversation and picks from the whole catalogue in one call —
 * courses, academic courses, events and programmes together — which is what
 * recovers those. It only runs where keyword matching found nothing, its answer
 * is rejected unless the id is really in the catalogue, and results are cached by
 * message text: the backlog is mostly ad referrals repeating one prefilled line,
 * so hundreds of chats cost a few dozen calls.
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
 *   php wa_triage_assign.php --ai                 # let the AI read each chat and match it
 *                                                 # against the full catalogue (costs tokens)
 *   php wa_triage_assign.php --apply --fallback=121,134
 *                                                 # round-robin whatever still has no match
 *                                                 # across these staff ids, so nothing is left
 *   php wa_triage_assign.php --limit=50           # try a small batch first
 *   php wa_triage_assign.php --staff              # just list eligible staff ids and exit
 *   php wa_triage_assign.php --days=90 --explain  # for every unrouted chat, show which
 *                                                 # titles it nearly matched and why it
 *                                                 # was dropped (usually a scoring tie)
 *   php wa_triage_assign.php --days=90 --apply \\
 *       --match="senior management" --to=event:123
 *                                                 # route every unrouted chat whose text
 *                                                 # contains that phrase. --to accepts
 *                                                 # course:ID, event:ID, program:ID or
 *                                                 # user:ID. This is how the ad-referral
 *                                                 # backlog gets cleared in a few passes.
 *
 * Browser (behind the normal CRM login):
 *   /admin/wa_triage_assign.php?apply=1&days=90&fallback=121,134
 */

$IS_CLI = (PHP_SAPI === 'cli');

if ($IS_CLI) {
    $opt = getopt('', ['apply', 'ai', 'staff', 'explain', 'days::', 'limit::',
                       'minchars::', 'fallback::', 'match::', 'to::']);
    $APPLY    = array_key_exists('apply', $opt);
    $USE_AI   = array_key_exists('ai', $opt);
    $SHOW_STF = array_key_exists('staff', $opt);
    $DAYS     = isset($opt['days'])     ? max(1, (int)$opt['days'])     : 30;
    $LIMIT    = isset($opt['limit'])    ? max(1, (int)$opt['limit'])    : 0;
    $MINCHARS = isset($opt['minchars']) ? max(1, (int)$opt['minchars']) : 8;
    $FALLBACK = isset($opt['fallback']) ? (string)$opt['fallback']      : '';
    $EXPLAIN  = array_key_exists('explain', $opt);
    $MATCH    = isset($opt['match']) ? trim((string)$opt['match']) : '';
    $TOSPEC   = isset($opt['to'])    ? trim((string)$opt['to'])    : '';
} else {
    $APPLY    = !empty($_GET['apply']);
    $USE_AI   = !empty($_GET['ai']);
    $SHOW_STF = !empty($_GET['staff']);
    $DAYS     = isset($_GET['days'])     ? max(1, (int)$_GET['days'])     : 30;
    $LIMIT    = isset($_GET['limit'])    ? max(1, (int)$_GET['limit'])    : 0;
    $MINCHARS = isset($_GET['minchars']) ? max(1, (int)$_GET['minchars']) : 8;
    $FALLBACK = isset($_GET['fallback']) ? (string)$_GET['fallback']      : '';
    $EXPLAIN  = !empty($_GET['explain']);
    $MATCH    = isset($_GET['match']) ? trim((string)$_GET['match']) : '';
    $TOSPEC   = isset($_GET['to'])    ? trim((string)$_GET['to'])    : '';
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

/* ------------------------------------------------------- resolve --match/--to */

// --to=course:9 | event:123 | program:4 | user:121. Resolved once, up front, so a
// bad target fails before it can half-apply across hundreds of chats.
$TO = null;
if ($MATCH !== '' || $TOSPEC !== '') {
    if ($MATCH === '' || $TOSPEC === '') {
        exit("ERROR: --match and --to must be given together.\n"
           . "  e.g. --match=\"senior management\" --to=event:123\n");
    }
    $bits = explode(':', $TOSPEC, 2);
    $kind = strtolower(trim($bits[0]));
    $tid  = isset($bits[1]) ? (int)$bits[1] : 0;
    if ($tid < 1) { exit("ERROR: --to needs an id, e.g. --to=course:9\n"); }

    if ($kind === 'course' || $kind === 'event') {
        $uid = wa_first_owner($conn, $kind, $tid);
        if ($uid === null) {
            exit("ERROR: $kind $tid has no rep, so assigning chats to it would hide them\n"
               . "       from everyone. Give it a rep first, or use --to=user:ID.\n");
        }
        $TO = ['type' => $kind, 'id' => $tid, 'uid' => $uid, 'prog' => null,
               'label' => $kind . ' ' . $tid];
    } elseif ($kind === 'program') {
        $prog = wa_program_get($conn, $tid);
        if (!$prog) { exit("ERROR: no training programme with id $tid\n"); }
        $uid = wa_program_first_owner($prog);
        if ($uid === null) {
            exit("ERROR: programme '" . (string)$prog['name'] . "' has no reps assigned.\n"
               . "       Assign reps to it first, or use --to=user:ID.\n");
        }
        $TO = ['type' => 'program', 'id' => null, 'uid' => $uid, 'prog' => $tid,
               'label' => (string)$prog['name']];
    } elseif ($kind === 'user') {
        if (!isset($valid[$tid])) {
            exit("ERROR: $tid is not WhatsApp staff (ERP role 44). Run --staff for the ids.\n");
        }
        $TO = ['type' => 'unclassified', 'id' => null, 'uid' => $tid, 'prog' => null,
               'label' => $valid[$tid]];
    } else {
        exit("ERROR: --to must be course:ID, event:ID, program:ID or user:ID\n");
    }
}

/* -------------------------------------------------------------------- header */

echo "=== Triage sweep: route the chats nobody owns ===\n";
echo $APPLY ? "MODE: APPLY (writing)\n" : "MODE: DRY RUN — nothing is written. Add --apply to commit.\n";
echo "WINDOW: last {$DAYS} days, inbound message of at least {$MINCHARS} characters\n";
echo "AI CLASSIFIERS: " . ($USE_AI ? "on (costs tokens)" : "off — keyword matching only") . "\n";
if ($TO) {
    echo "RULE: chats containing \"" . $MATCH . "\" -> " . $TOSPEC . " (" . $TO['label'] . ")\n";
}
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

/* ------------------------------------------------------------- why-no-match */

/**
 * Diagnostic scorer. Mirrors the token scoring inside wa_classify_course() /
 * wa_classify_academic() so --explain can show WHICH titles the message hit and by
 * how much. It exists because the classifiers return only a verdict: when several
 * titles tie on the top score they score the match 0.35, which is under the 0.60
 * threshold, so a message that plainly names a product is reported as "no match"
 * with no clue why.
 */
function sweep_candidates($msg, $items, $minLen = 3) {
    $stop = wa_stopwords();
    $hay  = ' ' . wa_normalize($msg) . ' ';
    $out  = [];
    foreach ($items as $it) {
        $hits = [];
        $tot  = 0;
        foreach (explode(' ', wa_normalize((string)$it['name'])) as $w) {
            if (mb_strlen($w) < $minLen || isset($stop[$w])) { continue; }
            $tot++;
            if (strpos($hay, ' ' . $w . ' ') !== false) { $hits[] = $w; }
        }
        if ($hits) {
            $out[] = ['id' => (int)$it['id'], 'name' => (string)$it['name'],
                      'hits' => count($hits), 'of' => $tot, 'words' => $hits];
        }
    }
    usort($out, function ($a, $b) { return $b['hits'] <=> $a['hits']; });
    return $out;
}

$academics = [];
$ares = mysqli_query($conn,
    "SELECT event_id AS id, event_title AS name FROM `Event`
      WHERE status = 1 AND location LIKE 'ACADEMIC#%'");
while ($ares && ($a = mysqli_fetch_assoc($ares))) { $academics[] = $a; }

/* ------------------------------------------------------- names, not bare ids */

// Every route was being reported as "event_location:953", which tells you nothing
// about whether the match is right. Resolve the names once, up front.
$NAME = ['course' => [], 'event' => [], 'program' => []];
foreach ($courses as $c)   { $NAME['course'][(int)$c['id']] = (string)$c['name']; }
foreach ($academics as $a) { $NAME['event'][(int)$a['id']]  = (string)$a['name'] . ' [academic]'; }
$eres = mysqli_query($conn,
    "SELECT event_id, event_title, location FROM `Event`
      WHERE status = 1 AND location NOT LIKE 'ACADEMIC#%'");
while ($eres && ($e = mysqli_fetch_assoc($eres))) {
    $loc = trim((string)$e['location']);
    $NAME['event'][(int)$e['event_id']] = (string)$e['event_title'] . ($loc !== '' ? ' — ' . $loc : '');
}
$programs = wa_programs_list($conn);
foreach ($programs as $pg) { $NAME['program'][(int)$pg['id']] = (string)$pg['name']; }

/** Human-readable target for a hit, e.g. "event 953 · Leadership Summit — Nairobi". */
function sweep_label($NAME, $kind, $id) {
    $id = (int)$id;
    $nm = $NAME[$kind][$id] ?? '';
    return $kind . ' ' . $id . ($nm !== '' ? ' · ' . $nm : ' · (name not found)');
}

/* ------------------------------------------------------- read the chat with AI */

/**
 * One AI call that READS the conversation and picks from the whole catalogue at
 * once — courses, academic courses, in-person events and training programmes.
 *
 * This replaces the router's three separate AI classifiers for the sweep. They fire
 * one after another, each blind to the others' options, and each inherits the same
 * scoring threshold that already dropped these chats. One call with everything on
 * the table both costs a third as much and can say "this is the Senior Management
 * event, not the course with a similar name".
 *
 * Results are cached by message text. The backlog is overwhelmingly ad referrals
 * repeating the same prefilled line, so a few hundred chats collapse into a few
 * dozen distinct texts — the difference between a handful of calls and 800.
 */
function sweep_ai_pick($conn, $provider, $text, $catalog, &$cache, &$calls) {
    $key = md5(mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text))));
    if (isset($cache[$key])) { return $cache[$key]; }
    $none = ['kind' => 'none', 'id' => 0, 'confidence' => 0.0, 'why' => ''];

    $system = "You route WhatsApp enquiries for Vantage Africa School of Leadership.\n"
        . "Read the customer's messages and decide which ONE catalogue item they are asking about.\n\n"
        . "CATALOGUE (id and exact title):\n" . $catalog . "\n"
        . "Notes:\n"
        . "- Many messages are the prefilled text of a click-to-WhatsApp advert and name the "
        . "product directly (e.g. 'Can I get more info on the Senior Management ...'). Match those "
        . "to the item they name, even when several titles share a generic word.\n"
        . "- Match on MEANING, not exact wording: abbreviations (M&E, CPA, TOT, SLDP, AI), "
        . "misspellings and other languages (Swahili, Somali, Amharic, French) all count.\n"
        . "- Prefer a 'program' when the customer names a subject we run in several countries "
        . "but has not named a city or a specific session.\n"
        . "- Return kind 'none' when the messages are an auto-reply, another business's bot, "
        . "spam, a forwarded link, small talk, or simply too vague to tell. Guessing is worse "
        . "than leaving the chat in triage — a wrong owner means nobody follows up.\n\n"
        . "Reply with ONLY JSON: {\"kind\":\"course|event|program|none\", \"id\":<catalogue id or 0>, "
        . "\"confidence\":<0..1>, \"why\":\"<a few words>\"}";

    // Say so before the call, not after: a cache miss can sit on the provider for
    // tens of seconds, and silence at that point reads as a hang.
    $calls++;
    printf("       ... new wording, asking the AI (call %d)\n", $calls);
    wa_tick();
    $res = wa_ai_complete($provider, $system,
        [['role' => 'user', 'content' => "Customer messages:\n" . $text]],
        ['json' => true, 'max_tokens' => 150, 'timeout' => 30]);
    if (empty($res['ok'])) { return $cache[$key] = $none; }
    $d = wa_json_extract((string)($res['text'] ?? ''));
    if (!is_array($d)) { return $cache[$key] = $none; }

    $kind = strtolower(trim((string)($d['kind'] ?? 'none')));
    $id   = (int)($d['id'] ?? 0);
    if (!in_array($kind, ['course', 'event', 'program'], true) || $id < 1) {
        return $cache[$key] = $none;
    }
    return $cache[$key] = ['kind' => $kind, 'id' => $id,
                           'confidence' => (float)($d['confidence'] ?? 0),
                           'why' => trim((string)($d['why'] ?? ''))];
}

$AI_CATALOG = '';
$AI_CACHE   = [];
$AI_CALLS   = 0;
$PROVIDER   = '';
if ($USE_AI) {
    $PROVIDER = wa_active_provider($conn);
    if (!wa_provider_ready($PROVIDER)) {
        exit("ERROR: --ai was given but the '$PROVIDER' provider has no API key in wa_config.php.\n");
    }
    $lines = [];
    foreach ($NAME['course']  as $id => $nm) { $lines[] = "course  $id: $nm"; }
    foreach ($NAME['event']   as $id => $nm) { $lines[] = "event   $id: $nm"; }
    foreach ($NAME['program'] as $id => $nm) { $lines[] = "program $id: $nm"; }
    $AI_CATALOG = implode("\n", $lines);
    echo "AI catalogue: " . count($lines) . " item(s); provider $PROVIDER\n";
}

/** Pad to a visible width. printf's %-Ns counts bytes, so one accented name or an
 *  em dash silently shifts every column after it. */
function wa_pad($s, $w) { $n = mb_strlen((string)$s); return $s . str_repeat(' ', max(0, $w - $n)); }

/** Push output to the terminal now. Classifying 800 chats with --ai takes minutes,
 *  and PHP's buffering made that look like a hang: nothing appeared until the whole
 *  run had finished. Rows are printed as they are decided instead. */
function wa_tick() { if (PHP_SAPI !== 'cli') { @ob_flush(); } @flush(); }

/* ------------------------------------------------------------- classify each */

printf("%-6s %-14s %s %s %s %s\n",
       'CONV', 'NUMBER', wa_pad('NAME', 16), wa_pad('HOW', 16),
       wa_pad('MATCHED TO', 54), wa_pad('OWNER', 20));
echo str_repeat('-', 160) . "\n";
wa_tick();

$plan  = [];
$done  = 0;
$began = time();
$stats = ['event' => 0, 'course' => 0, 'academic' => 0, 'program' => 0,
          'fallback' => 0, 'nomatch' => 0, 'silent' => 0, 'mode_set' => 0,
          'human' => 0, 'norep' => 0, 'rule' => 0, 'ai' => 0];
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
    if ($ev['event_id'] !== null && $ev['confidence'] >= 0.60) {
        $hit = ['type' => 'event', 'id' => (int)$ev['event_id'],
                'uid' => wa_first_owner($conn, 'event', (int)$ev['event_id']),
                'prog' => null, 'method' => 'event_location',
                'conf' => (float)$ev['confidence'], 'label' => ''];
    }

    // 2. Course by keyword.
    if ($hit['type'] === null && $courses) {
        $g = wa_classify_course($text, $courses);
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

    // 4b. Keyword matching found nothing. Let the AI read the whole conversation and
    //     pick from the entire catalogue at once — this is what recovers the chats the
    //     token classifiers drop on a scoring tie.
    if ($hit['type'] === null && $USE_AI) {
        $pick = sweep_ai_pick($conn, $PROVIDER, $text, $AI_CATALOG, $AI_CACHE, $AI_CALLS);
        if ($pick['kind'] !== 'none' && $pick['confidence'] >= 0.60) {
            $k = $pick['kind'];
            $id = (int)$pick['id'];
            // Only trust an id the catalogue actually contains — a hallucinated one
            // would assign the chat to a topic that does not exist.
            if (isset($NAME[$k][$id])) {
                if ($k === 'program') {
                    $pg = wa_program_get($conn, $id);
                    $hit = ['type' => 'program', 'id' => null,
                            'uid' => $pg ? wa_program_first_owner($pg) : null, 'prog' => $id,
                            'method' => 'ai_read', 'conf' => (float)$pick['confidence'],
                            'label' => $pick['why']];
                } else {
                    $hit = ['type' => $k, 'id' => $id,
                            'uid' => wa_first_owner($conn, $k, $id), 'prog' => null,
                            'method' => 'ai_read', 'conf' => (float)$pick['confidence'],
                            'label' => $pick['why']];
                }
                $stats['ai']++;
            }
        }
    }

    // 5. A rule you supplied on the command line: --match=<text> --to=<target>. This is
    //    how the ad-referral backlog gets cleared — hundreds of chats whose whole first
    //    message is the ad's prefilled text ("Hi! Can I get more info on the X"), which
    //    the token classifiers tie on and therefore drop.
    if ($hit['type'] === null && $MATCH !== '' && $TO !== null
        && mb_stripos($text, $MATCH) !== false) {
        $hit = ['type' => $TO['type'], 'id' => $TO['id'], 'uid' => $TO['uid'],
                'prog' => $TO['prog'], 'method' => 'manual_rule', 'conf' => 1.0,
                'label' => $TO['label']];
        $stats['rule']++;
    }

    // 6. Still nothing to go on — hand it to a human from the pool so it stops rotting.
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

    $h = $hit;
    // Say WHAT it matched, not just an id: "event 953" gives you no way to judge
    // whether the route is right, which is the whole point of the dry run.
    if ($h['type'] === null) {
        $target = '(nothing — left in triage)';
    } elseif ($h['type'] === 'unclassified') {
        $target = '(unclassified — handed to a rep)';
    } elseif ($h['type'] === 'program') {
        $target = sweep_label($NAME, 'program', (int)$h['prog']);
    } else {
        $target = sweep_label($NAME, $h['type'], (int)$h['id']);
        if ($h['prog'] !== null) { $target .= ' + ' . sweep_label($NAME, 'program', (int)$h['prog']); }
    }
    $owner = $h['uid'] === null
        ? '-- NO REP --'
        : mb_substr((string) wa_user_name($conn, (int)$h['uid']) ?: ('#' . (int)$h['uid']), 0, 20);
    printf("%-6d %-14s %s %s %s %s\n",
        (int)$r['id'],
        mb_substr((string)$r['wa_id'], 0, 14),
        wa_pad(mb_substr((string)($r['profile_name'] ?: '(no name)'), 0, 16), 16),
        wa_pad($h['method'] !== '' ? $h['method'] : '-', 16),
        wa_pad(mb_substr($target, 0, 54), 54),
        wa_pad($owner, 20));
    // The message itself on its own line — it is the evidence for the route above,
    // and squeezing it into a column meant seeing none of it.
    printf("       \"%s\"%s\n",
        mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 110),
        ($outbound === 0 ? '   [never answered]' : ''));

    if ($EXPLAIN && $h['type'] === null) {
        $cc = array_slice(sweep_candidates($text, $courses), 0, 3);
        $ac = array_slice(sweep_candidates($text, $academics), 0, 3);
        if (!$cc && !$ac) {
            echo "         why: the message shares no distinctive word with any course or academic title\n";
        } else {
            foreach ([['course', $cc], ['academic', $ac]] as $pair) {
                list($what, $list) = $pair;
                foreach ($list as $i => $cand) {
                    printf("         why: %-8s #%-5d %-38s %d/%d hit(s) on: %s%s\n",
                        $what, $cand['id'], mb_substr($cand['name'], 0, 38),
                        $cand['hits'], $cand['of'], implode(', ', $cand['words']),
                        ($i === 0 && isset($list[1]) && $list[1]['hits'] === $cand['hits'])
                            ? '   <- TIED with the next one, so scored 0.35 and dropped' : '');
                }
            }
        }
    }
    // A periodic heartbeat, so a long run shows how far along it is and roughly how
    // much longer it will take, rather than just scrolling.
    $done++;
    if ($done % 50 === 0 && $done < count($rows)) {
        $el = max(1, time() - $began);
        printf("  --- %d/%d classified, %ds elapsed, ~%ds left%s ---\n",
               $done, count($rows), $el,
               (int)round($el / $done * (count($rows) - $done)),
               $USE_AI ? ', ' . $AI_CALLS . ' AI call(s)' : '');
    }
    wa_tick();
}


/* ------------------------------------------------- what the leftovers look like */

// Hundreds of unmatched rows are not hundreds of decisions. Nearly all of them are
// click-to-WhatsApp ad referrals whose entire first message is the ad's prefilled
// text, so grouping by that text turns the backlog into a short list you can map
// with --match/--to one line at a time.
$patterns = [];
foreach ($plan as $p) {
    if ($p['hit']['type'] !== null && $p['hit']['method'] !== 'triage_fallback') { continue; }
    $first = trim(preg_replace('/\s+/u', ' ', explode(' | ', $p['text'])[0]));
    $key   = mb_strtolower(mb_substr($first, 0, 55));
    if ($key === '') { $key = '(empty)'; }
    if (!isset($patterns[$key])) { $patterns[$key] = ['n' => 0, 'sample' => $first]; }
    $patterns[$key]['n']++;
}
if ($patterns) {
    uasort($patterns, function ($a, $b) { return $b['n'] <=> $a['n']; });
    echo "\n=== What the unrouted chats actually say (top 20 opening lines) ===\n";
    $shown = 0;
    foreach ($patterns as $pat) {
        if ($shown++ >= 20) { break; }
        printf("  %5d x  %s\n", $pat['n'], mb_substr($pat['sample'], 0, 95));
    }
    if (count($patterns) > 20) {
        printf("  ... and %d more distinct opening line(s)\n", count($patterns) - 20);
    }
    echo "\n  Map a whole group in one go, e.g.:\n";
    echo "    php wa_triage_assign.php --days=90 --match=\"senior management\" --to=event:123 --apply\n";
    echo "  Run --explain to see which titles each message nearly matched.\n";
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
if ($stats['ai']) { printf("  matched by AI reading it .. %d\n", $stats['ai']); }
if ($stats['rule']) { printf("  matched your --match rule ... %d\n", $stats['rule']); }
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
if ($USE_AI) {
    printf("\nAI: %d call(s) for %d distinct message text(s) across %d chat(s).\n",
           $AI_CALLS, count($AI_CACHE), count($rows));
}
echo "\nDone.\n";
