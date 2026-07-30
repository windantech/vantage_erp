<?php
/**
 * Lightweight JSON API polled by the WhatsApp pages for live updates.
 * Bootstraps like wa_process.php: auth.php gives $conn / $role / $staff_id.
 *
 *   GET includes/wa_api.php?action=inbox
 *   GET includes/wa_api.php?action=thread&id=<conv>&after=<lastMsgId>
 */
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once __DIR__ . '/../auth.php';        // $conn, $role, $staff_id
require_once __DIR__ . '/wa_config.php';
require_once __DIR__ . '/wa_functions.php';

header('Content-Type: application/json; charset=utf-8');

function wa_json_out($data) { echo json_encode($data); exit; }

if (!in_array(WA_ROLE, $role)) { http_response_code(403); wa_json_out(['error' => 'forbidden']); }

$is_supervisor = in_array(777, $role);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ---- Generate a KB draft from the website (supervisor only) ----
if ($action === 'kb_generate') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    @set_time_limit(200);
    $rt  = in_array($_POST['ref_type'] ?? $_GET['ref_type'] ?? '', ['event','program'], true) ? ($_POST['ref_type'] ?? $_GET['ref_type']) : 'course';
    $rid = (int)($_POST['ref_id'] ?? $_GET['ref_id'] ?? 0);
    if ($rid <= 0) { wa_json_out(['ok' => false, 'error' => 'no_ref']); }
    $raw  = (string)($_POST['urls'] ?? $_GET['urls'] ?? '');
    $urls = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    wa_json_out(wa_kb_generate($conn, $rt, $rid, $urls));
}

// ---- Knowledge: dry-run the AI against a course/event's KB (supervisor only) ----
if ($action === 'kb_test') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    @set_time_limit(200);
    $rt = in_array($_POST['ref_type'] ?? '', ['event','program'], true) ? $_POST['ref_type'] : 'course';
    $rid = (int)($_POST['ref_id'] ?? 0);
    // If the editor sent its current (possibly unsaved) text, test against that.
    $kbOverride = array_key_exists('kb', $_POST) ? (string)$_POST['kb'] : null;
    wa_json_out(wa_ai_test($conn, $rt, $rid, (string)($_POST['q'] ?? ''), $kbOverride));
}

// ---- Knowledge: persist the KB body (supervisor only) ----
if ($action === 'kb_save') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    @set_time_limit(200);   // saving reprocesses body_ai via the AI — needs headroom
    $rt  = in_array($_POST['ref_type'] ?? '', ['event','program'], true) ? $_POST['ref_type'] : 'course';
    $rid = (int)($_POST['ref_id'] ?? 0);
    if ($rid <= 0) { wa_json_out(['ok' => false, 'error' => 'no_ref']); }
    wa_knowledge_set($conn, $rt, $rid, (string)($_POST['body'] ?? ''));
    wa_json_out(['ok' => true]);
}

// ---- Knowledge: apply a natural-language edit command to the KB (supervisor only) ----
if ($action === 'kb_command') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    @set_time_limit(200);
    $rt  = in_array($_POST['ref_type'] ?? '', ['event','program'], true) ? $_POST['ref_type'] : 'course';
    $rid = (int)($_POST['ref_id'] ?? 0);
    wa_json_out(wa_kb_command($conn, $rt, $rid, (string)($_POST['kb'] ?? ''), (string)($_POST['command'] ?? '')));
}

// ---- Knowledge: scan the shared course-outline PDF for its schedule (supervisor only) ----
if ($action === 'outline_import') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    @set_time_limit(200);
    wa_json_out(wa_outline_import($conn));
}

// ---- Knowledge: build an event's KB from its details + a programme (supervisor only) ----
if ($action === 'event_build') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    @set_time_limit(200);
    wa_json_out(wa_event_kb_build(
        $conn,
        (int)($_POST['ref_id'] ?? 0),
        (int)($_POST['program_id'] ?? 0),
        (string)($_POST['city'] ?? ''),
        (string)($_POST['hotel'] ?? ''),
        (string)($_POST['cost'] ?? ''),
        (string)($_POST['reglink'] ?? '')
    ));
}

// ---- Knowledge: conversational KB editor — asks or applies+saves (supervisor only) ----
if ($action === 'kb_chat') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    @set_time_limit(200);
    $rt  = in_array($_POST['ref_type'] ?? '', ['event','program'], true) ? $_POST['ref_type'] : 'course';
    $rid = (int)($_POST['ref_id'] ?? 0);
    $hist = json_decode((string)($_POST['history'] ?? '[]'), true) ?: [];
    $img  = wa_image_from_dataurl($_POST['image'] ?? '');
    wa_json_out(wa_kb_chat($conn, $rt, $rid, $hist, $img));
}

// ---- Template: AI draft from a description (supervisor only) ----
if ($action === 'template_draft') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    @set_time_limit(200);
    wa_json_out(wa_ai_template_draft($conn, (string)($_POST['desc'] ?? $_GET['desc'] ?? '')));
}

// ---- Broadcast: resolve audience (supervisor only) ----
if ($action === 'broadcast_audience') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    $filter   = $_POST['filter'] ?? $_GET['filter'] ?? 'all';
    $courseId = (int)($_POST['course_id'] ?? $_GET['course_id'] ?? 0);
    wa_json_out(['ok' => true, 'contacts' => wa_broadcast_audience($conn, $filter, $courseId)]);
}

// ---- Broadcast: open a run record, return its id (supervisor only) ----
if ($action === 'broadcast_create') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    $tpl   = (string)($_POST['template'] ?? '');
    $lang  = (string)($_POST['lang'] ?? 'en');
    $aud   = (string)($_POST['audience'] ?? 'all');
    $cid   = (int)($_POST['course_id'] ?? 0);
    $total = (int)($_POST['total'] ?? 0);
    if ($tpl === '') { wa_json_out(['ok' => false, 'error' => 'no_template']); }
    $id = wa_broadcast_create($conn, $tpl, $lang, $aud, $cid, $total, (int)$staff_id);
    wa_json_out(['ok' => true, 'broadcast_id' => $id]);
}

// ---- Broadcast: schedule for a future time (supervisor only) ----
if ($action === 'broadcast_schedule') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    $tpl   = (string)($_POST['template'] ?? '');
    $lang  = (string)($_POST['lang'] ?? 'en');
    $aud   = (string)($_POST['audience'] ?? 'all');
    $cid   = (int)($_POST['course_id'] ?? 0);
    $vars  = json_decode((string)($_POST['vars'] ?? '[]'), true) ?: [];
    $whenRaw = trim((string)($_POST['when'] ?? ''));
    if ($tpl === '')    { wa_json_out(['ok' => false, 'error' => 'no_template']); }
    if ($whenRaw === '') { wa_json_out(['ok' => false, 'error' => 'no_time']); }
    // Accept the <input type="datetime-local"> value ("Y-m-dTH:i").
    $ts = strtotime(str_replace('T', ' ', $whenRaw));
    if ($ts === false)        { wa_json_out(['ok' => false, 'error' => 'bad_time']); }
    if ($ts < time() + 30)    { wa_json_out(['ok' => false, 'error' => 'past_time']); }
    $when = date('Y-m-d H:i:s', $ts);
    $id = wa_broadcast_schedule($conn, $tpl, $lang, $vars, $aud, $cid, $when, (int)$staff_id);
    wa_json_out(['ok' => true, 'id' => $id, 'when' => $when]);
}

// ---- Broadcast: send one chunk of a template (supervisor only) ----
if ($action === 'broadcast_send') {
    if (!$is_supervisor) { http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']); }
    @set_time_limit(200);
    $tpl  = (string)($_POST['template'] ?? '');
    $lang = (string)($_POST['lang'] ?? 'en');
    $bid  = (int)($_POST['broadcast_id'] ?? 0);
    $vars = json_decode((string)($_POST['vars'] ?? '[]'), true) ?: [];
    $ids  = json_decode((string)($_POST['wa_ids'] ?? '[]'), true) ?: [];
    if ($tpl === '') { wa_json_out(['ok' => false, 'error' => 'no_template']); }

    $sent = 0; $failed = 0;
    foreach ($ids as $item) {
        $waId = is_array($item) ? ($item['wa_id'] ?? '') : $item;
        $nm   = is_array($item) ? ($item['name'] ?? '') : '';
        if ($waId === '') { continue; }
        // Substitute {name} in any variable value with the contact's name.
        $params = array_map(function ($v) use ($nm) {
            return str_replace('{name}', $nm !== '' ? $nm : 'there', (string)$v);
        }, $vars);
        $components = $params
            ? [['type' => 'body', 'parameters' => array_map(function ($t) { return ['type' => 'text', 'text' => $t]; }, $params)]]
            : [];
        $r = wa_send_template($conn, $waId, $tpl, $lang, $components);
        if (!empty($r['ok'])) {
            $sent++;
            if ($bid > 0) { wa_broadcast_tag_message($conn, $bid, $r['wa_message_id'] ?? ''); }
        } else { $failed++; }
        usleep(120000);   // ~8/sec, gentle on the rate limit
    }
    wa_json_out(['ok' => true, 'sent' => $sent, 'failed' => $failed]);
}

// ---- Retry a failed outbound message (any agent on their own chats) ----
if ($action === 'resend') {
    $msgId = (int)($_POST['msg'] ?? 0);
    if ($msgId <= 0) { wa_json_out(['ok' => false, 'error' => 'no_msg']); }
    // Only the assigned agent (or a supervisor) may retry a chat's message.
    $res = mysqli_query($conn,
        "SELECT cv.assigned_user_id
           FROM wa_messages m
           JOIN wa_conversations cv ON cv.contact_id = m.contact_id
          WHERE m.id = $msgId LIMIT 1");
    $own = $res ? mysqli_fetch_assoc($res) : null;
    if (!$own || (!$is_supervisor && (int)$own['assigned_user_id'] !== (int)$staff_id)) {
        http_response_code(403); wa_json_out(['ok' => false, 'error' => 'forbidden']);
    }
    @set_time_limit(30);
    wa_json_out(wa_resend_message($conn, $msgId));
}

// ---- Inbox list (with per-conversation unread count) ----
if ($action === 'inbox') {
    wa_message_flags_ensure($conn);   // ensure sent_by_staff exists before we read it
    $where = $is_supervisor ? '' : ' WHERE cv.assigned_user_id = ' . (int)$staff_id . ' ';
    $sql = "
        SELECT cv.id, cv.handler, cv.escalated, cv.last_message_at,
               c.wa_id, c.profile_name,
               CASE cv.ref_type
                    WHEN 'course' THEN (SELECT course FROM course WHERE course_id = cv.ref_id)
                    WHEN 'event'  THEN (SELECT event_title FROM `Event` WHERE event_id = cv.ref_id)
               END AS ref_name,
               COALESCE(NULLIF(s.full_name,''), ru.fullname) AS owner_name,
               (SELECT body FROM wa_messages m WHERE m.contact_id = c.id AND m.type <> 'note' ORDER BY m.id DESC LIMIT 1) AS last_body,
               (SELECT CASE WHEN m.direction='inbound' THEN 'in' WHEN m.sent_by_staff IS NULL THEN 'ai' ELSE 'human' END
                  FROM wa_messages m WHERE m.contact_id = c.id AND m.type <> 'note' ORDER BY m.id DESC LIMIT 1) AS last_kind,
               (SELECT COUNT(*) FROM wa_messages m
                  WHERE m.contact_id = c.id AND m.direction = 'inbound'
                    AND (cv.last_read_at IS NULL OR m.created_at > cv.last_read_at)) AS unread
          FROM wa_conversations cv
          JOIN wa_contacts c        ON c.id  = cv.contact_id
     LEFT JOIN registered_users ru  ON ru.id = cv.assigned_user_id
     LEFT JOIN staff s              ON s.system_user_id = cv.assigned_user_id
        {$where}
      ORDER BY cv.last_message_at DESC, cv.id DESC";
    $res = mysqli_query($conn, $sql);
    $rows = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'id'        => (int)$r['id'],
                'wa_id'     => $r['wa_id'],
                'name'      => $r['profile_name'] ?: '',
                'ref_name'  => $r['ref_name'] ?: '',
                'owner'     => $r['owner_name'] ?: '',
                'handler'   => $r['handler'],
                'escalated' => (int)$r['escalated'],
                'last_body' => mb_strimwidth((string)$r['last_body'], 0, 60, '…'),
                'last_kind' => $r['last_kind'] ?: 'in',
                'unread'    => (int)$r['unread'],
            ];
        }
    }
    wa_json_out(['conversations' => $rows]);
}

// ---- New messages in a thread (and mark it read) ----
if ($action === 'thread') {
    $id    = (int)($_GET['id'] ?? 0);
    $after = (int)($_GET['after'] ?? 0);

    $res = mysqli_query($conn,
        "SELECT contact_id, assigned_user_id FROM wa_conversations WHERE id = $id LIMIT 1");
    $conv = $res ? mysqli_fetch_assoc($res) : null;
    if (!$conv || (!$is_supervisor && (int)$conv['assigned_user_id'] !== (int)$staff_id)) {
        http_response_code(403); wa_json_out(['error' => 'forbidden']);
    }

    $contactId = (int)$conv['contact_id'];
    $res = mysqli_query($conn,
        "SELECT id, direction, type, body, wa_timestamp, status, created_at
           FROM wa_messages WHERE contact_id = $contactId AND id > $after ORDER BY id ASC");
    $msgs = [];
    if ($res) {
        while ($m = mysqli_fetch_assoc($res)) {
            $msgs[] = [
                'id'        => (int)$m['id'],
                'direction' => $m['direction'],
                'type'      => $m['type'],
                'body'      => (string)$m['body'],
                'ts'        => $m['wa_timestamp'] ?: $m['created_at'],
                'status'    => $m['status'] ?: '',
            ];
        }
    }

    // Viewing a chat marks it read.
    mysqli_query($conn, "UPDATE wa_conversations SET last_read_at = NOW() WHERE id = $id");
    wa_json_out(['messages' => $msgs]);
}

http_response_code(400);
wa_json_out(['error' => 'unknown_action']);
