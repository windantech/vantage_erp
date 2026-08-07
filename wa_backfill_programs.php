<?php
/**
 * BACKFILL: link existing onsite chats to their training programme.
 *
 * Uses wa_program_for_course() — the SAME matcher the live router uses — so it
 * covers EVERY programme and matches word by word. The SQL backfill could not:
 * plain LIKE needs the whole keyword phrase present, so it only ever caught the
 * courses whose titles happen to contain the keyword verbatim.
 *
 * Two distinct things it can do:
 *
 *   1. program_id  — ALWAYS set (when a programme matches). This alone makes the
 *      chat visible to every rep on that programme via the inbox scope, WITHOUT
 *      taking it from whoever currently owns it. This is the important one: most
 *      onsite chats already have an owner from course routing, which is why the
 *      SQL backfill (unassigned-only) appeared to do nothing.
 *
 *   2. assigned_user_id — only for chats with NO owner, unless --reassign is
 *      given. A chat a human has taken over (handler='human') is NEVER moved.
 *
 * Usage:
 *   php wa_backfill_programs.php                 # dry run — shows what it would do
 *   php wa_backfill_programs.php --apply         # set program_id + assign unowned
 *   php wa_backfill_programs.php --apply --reassign
 *                                                # ALSO move AI-handled chats whose
 *                                                # current owner is not a rep of the
 *                                                # matched programme
 *
 * Browser (behind the normal CRM login):
 *   /admin/wa_backfill_programs.php?apply=1[&reassign=1]
 */

$IS_CLI = (PHP_SAPI === 'cli');
if ($IS_CLI) {
    require_once __DIR__ . '/includes/wa_config.php';
    require_once __DIR__ . '/includes/wa_db.php';       // $wa_conn
    require_once __DIR__ . '/includes/wa_functions.php';
    $conn = $wa_conn;
    $opt = getopt('', ['apply', 'reassign']);
    $APPLY    = array_key_exists('apply', $opt);
    $REASSIGN = array_key_exists('reassign', $opt);
} else {
    require_once __DIR__ . '/auth.php';                 // login gate + $conn
    require_once __DIR__ . '/includes/wa_config.php';
    require_once __DIR__ . '/includes/wa_functions.php';
    $APPLY    = !empty($_GET['apply']);
    $REASSIGN = !empty($_GET['reassign']);
    header('Content-Type: text/plain; charset=utf-8');
}

if (!$conn) { exit("No database connection\n"); }
mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+03:00'");
wa_conv_mode_schema_ensure($conn);
wa_kb_ensure_schema($conn);

echo "=== Link onsite chats to their training programme ===\n";
echo $APPLY ? "MODE: APPLY (writing)\n" : "MODE: DRY RUN — nothing is written. Add --apply to commit.\n";
echo $REASSIGN ? "REASSIGN: on — AI-handled chats may change owner\n" : "REASSIGN: off — owners are left alone\n\n";

// ---- programmes and their reps -------------------------------------------
$programs = wa_programs_list($conn, true);
if (!$programs) { exit("No ACTIVE training programmes defined. Nothing to do.\n"); }
echo "Programmes:\n";
foreach ($programs as $p) {
    $ids = wa_program_owner_ids($p);
    printf("  #%-3d %-34s keywords=%-28s reps=%s\n",
        $p['id'], mb_substr($p['name'], 0, 33), mb_substr((string)$p['keywords'], 0, 27),
        $ids ? implode(',', $ids) : 'NONE — chats will be linked but not assigned');
}
echo "\n";

// ---- the chats to consider ------------------------------------------------
$res = mysqli_query($conn, "
    SELECT cv.id, cv.contact_id, cv.ref_type, cv.ref_id, cv.assigned_user_id,
           cv.handler, cv.delivery_mode, cv.program_id, cv.last_route_reason,
           c.wa_id, c.profile_name
      FROM wa_conversations cv
      JOIN wa_contacts c ON c.id = cv.contact_id
     WHERE cv.delivery_mode = 'onsite'
        OR cv.last_route_reason = 'await_onsite_location'
     ORDER BY cv.last_message_at DESC");
if (!$res) { exit('Query failed: ' . mysqli_error($conn) . "\n"); }

$stat = ['seen'=>0,'linked'=>0,'assigned'=>0,'reassigned'=>0,'no_match'=>0,'no_reps'=>0,'human_skipped'=>0,'already'=>0];
$byProgram = [];
$unmatched = [];

while ($cv = mysqli_fetch_assoc($res)) {
    $stat['seen']++;
    $convId = (int)$cv['id'];
    $name   = trim((string)$cv['profile_name']) ?: '(no name)';

    // The customer's own words help when the course title is ambiguous.
    $txt = (string)wa_scalar($conn,
        "SELECT body FROM wa_messages WHERE contact_id = " . (int)$cv['contact_id'] . "
          AND direction = 'inbound' AND type <> 'note' ORDER BY id ASC LIMIT 1");

    // Match exactly as the router does: event title first, then course, then text.
    if ($cv['ref_type'] === 'event') {
        $prog = wa_program_for_course($conn, 0, $txt, (int)$cv['ref_id']);
    } else {
        $twin = ($cv['ref_type'] === 'course') ? wa_course_onsite_event($conn, (int)$cv['ref_id']) : null;
        $prog = wa_program_for_course($conn, (int)$cv['ref_id'], $txt, (int)($twin['event_id'] ?? 0));
    }

    if (!$prog) {
        $stat['no_match']++;
        // Record WHAT they were asking about — the tally at the end tells you which
        // programmes are missing, which is the only way to fix a no-match.
        $topic = '(unclassified)';
        if ($cv['ref_type'] === 'course' && (int)$cv['ref_id'] > 0) {
            $topic = (string)wa_scalar($conn, "SELECT course FROM course WHERE course_id = " . (int)$cv['ref_id'] . " LIMIT 1");
        } elseif ($cv['ref_type'] === 'event' && (int)$cv['ref_id'] > 0) {
            $topic = (string)wa_scalar($conn, "SELECT event_title FROM `Event` WHERE event_id = " . (int)$cv['ref_id'] . " LIMIT 1");
        }
        $topic = trim($topic) !== '' ? trim($topic) : '(unclassified — no topic bound)';
        $unmatched[$topic] = ($unmatched[$topic] ?? 0) + 1;
        continue;
    }

    $pid  = (int)$prog['id'];
    $reps = wa_program_owner_ids($prog);
    $byProgram[$prog['name']] = ($byProgram[$prog['name']] ?? 0) + 1;

    $actions = [];

    // 1. Link — makes it visible to every rep on the programme.
    if ((int)$cv['program_id'] !== $pid) {
        $actions[] = 'link->' . $prog['name'];
        if ($APPLY) { wa_conv_set_program($conn, $convId, $pid); }
        $stat['linked']++;
    } else {
        $stat['already']++;
    }

    // 2. Ownership.
    $curUid   = ($cv['assigned_user_id'] === null || $cv['assigned_user_id'] === '')
                ? null : (int)$cv['assigned_user_id'];
    $isHuman  = ($cv['handler'] ?? 'ai') === 'human';
    $firstRep = $reps ? $reps[0] : null;

    if (!$reps) {
        $stat['no_reps']++;
    } elseif ($curUid === null) {
        $actions[] = 'assign->' . $firstRep;
        if ($APPLY) {
            mysqli_query($conn, "UPDATE wa_conversations
                SET assigned_user_id = $firstRep, last_route_reason = 'program_backfill'
                WHERE id = $convId");
        }
        $stat['assigned']++;
    } elseif ($REASSIGN && !in_array($curUid, $reps, true)) {
        if ($isHuman) {
            $stat['human_skipped']++;
            $actions[] = 'kept (human owns it)';
        } else {
            $actions[] = 'reassign ' . $curUid . '->' . $firstRep;
            if ($APPLY) {
                mysqli_query($conn, "UPDATE wa_conversations
                    SET assigned_user_id = $firstRep, last_route_reason = 'program_backfill'
                    WHERE id = $convId");
            }
            $stat['reassigned']++;
        }
    }

    if ($actions) {
        printf("  %-14s %-22s -> %s\n", $cv['wa_id'], mb_substr($name, 0, 21), implode(', ', $actions));
    }
}

echo "\n--- matched per programme ---\n";
if (!$byProgram) { echo "  (none)\n"; }
foreach ($byProgram as $n => $c) { printf("  %-46s %d\n", $n, $c); }

if ($unmatched) {
    arsort($unmatched);
    echo "\n--- NO PROGRAMME COVERS THESE (create one, or they can never route) ---\n";
    foreach ($unmatched as $t => $c) { printf("  %-46s %d\n", mb_substr($t, 0, 45), $c); }
    echo "\n  Each line is a topic customers asked about in person that no ACTIVE\n";
    echo "  training programme matches. Add a programme (Knowledge Base -> Training\n";
    echo "  programmes) whose keywords appear in these titles, give it reps, re-run.\n";
}

echo "\n--- summary ---\n";
printf("  chats considered            %d\n", $stat['seen']);
printf("  linked to a programme       %d\n", $stat['linked']);
printf("  already linked              %d\n", $stat['already']);
printf("  assigned (had no owner)     %d\n", $stat['assigned']);
printf("  reassigned                  %d\n", $stat['reassigned']);
printf("  left with a human owner     %d\n", $stat['human_skipped']);
printf("  programme has no reps       %d\n", $stat['no_reps']);
printf("  no programme matched        %d\n", $stat['no_match']);

if (!$APPLY) {
    echo "\nDRY RUN — nothing was written. Re-run with --apply (CLI) or ?apply=1 (browser).\n";
} else {
    echo "\nDone. Linked chats are now visible to every rep on the matched programme.\n";
}
