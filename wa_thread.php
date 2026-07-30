<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';

if (!in_array(WA_ROLE, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Access denied.</div></div>';
    require_once 'footer.php';
    exit;
}
$is_supervisor = in_array(777, $role);
$conv_id = (int)($_GET['id'] ?? 0);

// Load the conversation + contact.
$res = mysqli_query($conn,
    "SELECT cv.*, c.wa_id, c.profile_name, c.last_inbound_at,
            CASE cv.ref_type
                 WHEN 'course' THEN (SELECT course FROM course WHERE course_id = cv.ref_id)
                 WHEN 'event'  THEN (SELECT event_title FROM `Event` WHERE event_id = cv.ref_id)
            END AS ref_name,
            COALESCE(NULLIF(s.full_name,''), ru.fullname) AS owner_name
       FROM wa_conversations cv
       JOIN wa_contacts c        ON c.id  = cv.contact_id
  LEFT JOIN registered_users ru  ON ru.id = cv.assigned_user_id
  LEFT JOIN staff s              ON s.system_user_id = cv.assigned_user_id
      WHERE cv.id = $conv_id LIMIT 1");
$conv = $res ? mysqli_fetch_assoc($res) : null;

// Access check: supervisor any; otherwise only your own.
if (!$conv || (!$is_supervisor && (int)$conv['assigned_user_id'] !== (int)$staff_id)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-warning">That conversation is not assigned to you.</div></div>';
    require_once 'footer.php';
    exit;
}

$flash = isset($_SESSION['wa_flash']) ? $_SESSION['wa_flash'] : null;
unset($_SESSION['wa_flash']);
// Course/event-scoped + global quick replies for this chat.
$quickReplies = wa_quick_replies_for($conn, $conv['ref_type'] ?? '', (int)($conv['ref_id'] ?? 0));
if ($is_supervisor) { $courses = wa_active_courses($conn); $events = wa_active_events($conn); }
$curScope = (in_array($conv['ref_type'] ?? '', ['course', 'event'], true) && $conv['ref_id'])
    ? $conv['ref_type'] . ':' . (int)$conv['ref_id'] : '';

// Opening the chat marks it read.
mysqli_query($conn, "UPDATE wa_conversations SET last_read_at = NOW() WHERE id = $conv_id");

$messages = wa_thread($conn, (int)$conv['contact_id']);
$open = wa_within_window($conv['last_inbound_at']);
$lastMsgId = 0;
foreach ($messages as $m) { if ((int)$m['id'] > $lastMsgId) { $lastMsgId = (int)$m['id']; } }

// Staff for the reassign dropdown (supervisor only).
$staffOptions = [];
if ($is_supervisor) {
    $r = mysqli_query($conn,
        "SELECT id, fullname FROM registered_users WHERE FIND_IN_SET('44', role) ORDER BY fullname");
    if ($r) { while ($o = mysqli_fetch_assoc($r)) { $staffOptions[] = $o; } }
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="wa_inbox.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to inbox</a>
                <?php if (!empty($conv['ref_id']) && in_array($conv['ref_type'], ['course', 'event'], true)): ?>
                <form method="post" action="includes/wa_process.php" class="m-0"
                      onsubmit="return confirm('Start guided registration? The contact will be asked for their details in the chat.');">
                    <input type="hidden" name="action" value="start_enroll">
                    <input type="hidden" name="id" value="<?php echo (int)$conv_id; ?>">
                    <button type="submit" class="btn btn-success"><i class="bi bi-clipboard-check me-1"></i>Start enrollment</button>
                </form>
                <?php endif; ?>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo wa_e($flash[0]); ?>"><?php echo wa_e($flash[1]); ?></div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 fw-bold">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo wa_e($conv['profile_name'] ?: $conv['wa_id']); ?>
                        </h6>
                        <small class="text-muted">
                            <?php echo wa_e($conv['wa_id']); ?>
                            · <i class="bi bi-mortarboard"></i> <?php echo wa_e($conv['ref_name'] ?: 'Unassigned'); ?>
                            · <i class="bi bi-person"></i> <?php echo wa_e($conv['owner_name'] ?: 'No owner'); ?>
                        </small>
                        <?php
                        // How this chat was routed (from the last routing decision)
                        $reasonLabels = [
                            'ad_referral'          => 'Ad referral',
                            'inferred'             => 'Keyword match',
                            'ai_inferred'          => 'AI match',
                            'inferred_no_owner'    => 'Keyword match (no owner)',
                            'ai_inferred_no_owner' => 'AI match (no owner)',
                            'inferred_switch'      => 'Keyword re-route',
                            'ai_inferred_switch'   => 'AI re-route',
                            'event_location'       => 'Event location match',
                        ];
                        $rr = $conv['last_route_reason'] ?? null;
                        if ($rr):
                            $label = $reasonLabels[$rr] ?? $rr;
                            $conf  = isset($conv['last_route_confidence']) && $conv['last_route_confidence'] !== null
                                     ? round(((float)$conv['last_route_confidence']) * 100) . '%' : null;
                        ?>
                            <div class="mt-1">
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-signpost-2 me-1"></i>Routed by: <?php echo wa_e($label); ?>
                                    <?php if ($conf): ?><span class="text-muted">· <?php echo $conf; ?> confidence</span><?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="includes/wa_process.php" class="m-0 d-flex align-items-center gap-2">
                        <input type="hidden" name="action" value="handler">
                        <input type="hidden" name="id" value="<?php echo (int)$conv_id; ?>">
                        <span class="text-muted small me-1">Handled by:</span>
                        <button name="handler" value="ai"    class="btn btn-sm <?php echo $conv['handler']==='ai'?'btn-primary':'btn-outline-primary'; ?>">AI</button>
                        <button name="handler" value="human" class="btn btn-sm <?php echo $conv['handler']==='human'?'btn-success':'btn-outline-success'; ?>">Human</button>
                    </form>
                </div>

                <div class="px-3 py-2 border-bottom bg-light d-flex flex-wrap gap-2 align-items-center">
                    <span class="small text-muted"><i class="bi bi-filetype-pdf me-1"></i>Export chat</span>
                    <input type="date" id="expFrom" class="form-control form-control-sm w-auto" title="From date">
                    <span class="text-muted small">→</span>
                    <input type="date" id="expTo" class="form-control form-control-sm w-auto" title="To date">
                    <button type="button" id="expBtn" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right me-1"></i>PDF
                    </button>
                    <span class="small text-muted">Leave the dates blank for the whole chat.</span>
                </div>
                <script>
                (function () {
                    var b = document.getElementById('expBtn');
                    if (!b) return;
                    b.addEventListener('click', function () {
                        var u = 'wa_thread_print.php?id=<?php echo (int)$conv_id; ?>';
                        var f = document.getElementById('expFrom').value;
                        var t = document.getElementById('expTo').value;
                        if (f) { u += '&from=' + encodeURIComponent(f); }
                        if (t) { u += '&to=' + encodeURIComponent(t); }
                        window.open(u, '_blank');
                    });
                })();
                </script>

                <div class="card-body" style="max-height:60vh; overflow-y:auto; background:#f3f5f9" id="wa_messages">
                    <?php foreach ($messages as $m): ?>
                        <?php if (($m['type'] ?? '') === 'note'): ?>
                            <?php /* Internal staff-only handoff note — never sent to the customer. */ ?>
                            <div class="d-flex justify-content-center mb-2">
                                <div class="px-3 py-2 rounded-3" style="max-width:82%; background:#fff8e1; border:1px solid #ffe08a">
                                    <div class="small fw-semibold text-warning-emphasis" style="font-size:11px; letter-spacing:.02em">
                                        <i class="bi bi-headset me-1"></i>HANDOFF — FOR STAFF
                                    </div>
                                    <div class="fst-italic text-dark" style="white-space:pre-wrap; word-wrap:break-word"><?php echo nl2br(wa_e($m['body'])); ?></div>
                                    <div class="small text-muted" style="font-size:11px"><?php echo wa_e($m['wa_timestamp'] ?: $m['created_at']); ?></div>
                                </div>
                            </div>
                            <?php continue; ?>
                        <?php endif; ?>
                        <?php
                        $out     = $m['direction'] === 'outbound';
                        $failed  = $out && $m['status'] === 'failed';
                        $deleted = !empty($m['deleted_at'] ?? null);
                        $byHuman = $out && !empty($m['sent_by_name']);   // a staff agent typed it
                        // Failed = red-outline; retracted (#19) = muted; human agent = BLUE,
                        // AI = GREEN — so the two are visually distinct at a glance.
                        $bubbleClass = $deleted ? 'bg-light border text-muted fst-italic'
                            : (!$out ? 'bg-white border'
                                : ($failed ? 'bg-white border border-danger text-dark'
                                    : ($byHuman ? 'bg-primary text-white' : 'bg-success text-white')));
                        $metaClass = ($deleted || !$out || $failed) ? 'text-muted' : 'text-white-50';
                        ?>
                        <div class="d-flex mb-2 <?php echo $out ? 'justify-content-end' : 'justify-content-start'; ?>">
                            <div class="p-2 px-3 rounded-3 <?php echo $bubbleClass; ?>" style="max-width:72%" data-msg-bubble="<?php echo (int)$m['id']; ?>">
                                <?php if ($deleted): ?>
                                    <div style="font-size:13px"><i class="bi bi-slash-circle"></i> Reply retracted <span class="text-muted">(kept for review)</span></div>
                                    <div class="small text-muted waMeta" style="font-size:11px">
                                        <?php echo wa_e($m['wa_timestamp'] ?: $m['created_at']); ?>
                                        <form method="post" action="includes/wa_process.php" class="d-inline">
                                            <input type="hidden" name="action" value="restore_message">
                                            <input type="hidden" name="id" value="<?php echo (int)$conv_id; ?>">
                                            <input type="hidden" name="msg_id" value="<?php echo (int)$m['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-link py-0 px-1 ms-1" style="font-size:11px">Restore</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <?php if (in_array($m['type'], ['text','template'], true)): ?>
                                        <div style="white-space:pre-wrap; word-wrap:break-word"><?php echo nl2br(wa_e($m['body'])); ?></div>
                                    <?php else: ?>
                                        <?php echo wa_media_html($m['type'], (int)$m['id'], $m['media_mime'] ?? '', $m['body'] ?? ''); ?>
                                    <?php endif; ?>
                                    <div class="small <?php echo $metaClass; ?> waMeta" style="font-size:11px">
                                        <?php if ($out): ?><span class="fw-semibold"><?php echo $byHuman ? '👤 ' . wa_e($m['sent_by_name']) : '🤖 AI'; ?></span> · <?php endif; ?>
                                        <?php echo wa_e($m['wa_timestamp'] ?: $m['created_at']); ?>
                                        <?php if ($failed): ?>
                                            · <span class="text-danger fw-semibold">not sent</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 ms-1 waResend"
                                                    data-msg="<?php echo (int)$m['id']; ?>" style="font-size:11px; line-height:1.4">
                                                <i class="bi bi-arrow-clockwise"></i> Resend
                                            </button>
                                        <?php elseif ($m['status']): ?>
                                            · <?php echo wa_e($m['status']); ?>
                                        <?php endif; ?>
                                        <?php if ($out): ?>
                                            <form method="post" action="includes/wa_process.php" class="d-inline"
                                                  onsubmit="return confirm('Retract this reply from the CRM? It stays saved internally for review.');">
                                                <input type="hidden" name="action" value="delete_message">
                                                <input type="hidden" name="id" value="<?php echo (int)$conv_id; ?>">
                                                <input type="hidden" name="msg_id" value="<?php echo (int)$m['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-link <?php echo $failed ? 'text-danger' : 'text-white-50'; ?> py-0 px-1 ms-1" style="font-size:11px">Retract</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$messages): ?><div class="text-muted">No messages.</div><?php endif; ?>
                </div>

                <div class="card-footer bg-white">
                    <?php if ($open): ?>
                        <?php if ($quickReplies): ?>
                        <div class="d-flex flex-wrap gap-1 mb-2" id="waQuick">
                            <span class="small text-muted me-1 align-self-center"><i class="bi bi-lightning-charge"></i></span>
                            <?php foreach ($quickReplies as $q):
                                $isScoped = !empty($q['ref_type']);
                            ?>
                                <button type="button" class="btn btn-sm <?php echo $isScoped ? 'btn-outline-info' : 'btn-outline-secondary'; ?> waQuickBtn"
                                        data-body="<?php echo wa_e($q['body']); ?>"
                                        <?php echo $isScoped ? 'title="For this ' . wa_e($q['ref_type']) . '"' : ''; ?>><?php echo wa_e($q['title']); ?></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <form method="post" action="includes/wa_process.php" enctype="multipart/form-data" class="d-flex gap-2 align-items-end">
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="id" value="<?php echo (int)$conv_id; ?>">
                            <label class="btn btn-outline-secondary mb-0" title="Attach a file (max 16 MB)">
                                <i class="bi bi-paperclip"></i>
                                <input type="file" name="file" hidden
                                       onchange="var n=this.files[0]?this.files[0].name:''; document.getElementById('waFileName').textContent = n ? ('📎 '+n) : '';">
                            </label>
                            <div class="flex-grow-1">
                                <div id="waFileName" class="small text-muted"></div>
                                <textarea name="body" class="form-control" rows="2" placeholder="Type a reply… (or attach a file)"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-send"></i></button>
                        </form>
                    <?php else: ?>
                        <div class="text-warning small">
                            <i class="bi bi-clock-history me-1"></i>24-hour window closed — free-form replies are blocked. Use an approved template to re-engage.
                        </div>
                    <?php endif; ?>

                    <?php if ($is_supervisor): ?>
                        <form method="post" action="includes/wa_process.php" class="d-flex gap-2 mt-2 align-items-center">
                            <input type="hidden" name="action" value="reassign">
                            <input type="hidden" name="id" value="<?php echo (int)$conv_id; ?>">
                            <span class="text-muted small">Reassign to:</span>
                            <select name="assigned_user_id" class="form-select form-select-sm w-auto">
                                <?php foreach ($staffOptions as $o): ?>
                                    <option value="<?php echo (int)$o['id']; ?>" <?php echo (int)$o['id']===(int)$conv['assigned_user_id']?'selected':''; ?>>
                                        <?php echo wa_e($o['fullname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button>
                        </form>
                        <form method="post" action="includes/wa_process.php" class="d-flex gap-2 mt-2 align-items-center">
                            <input type="hidden" name="action" value="set_ref">
                            <input type="hidden" name="id" value="<?php echo (int)$conv_id; ?>">
                            <span class="text-muted small">Linked to:</span>
                            <select name="scope" class="form-select form-select-sm w-auto">
                                <option value="">— None —</option>
                                <?php if (!empty($courses)): ?>
                                <optgroup label="Courses">
                                    <?php foreach ($courses as $c): ?>
                                        <option value="course:<?php echo (int)$c['id']; ?>" <?php echo $curScope === 'course:' . (int)$c['id'] ? 'selected' : ''; ?>><?php echo wa_e($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($events)): ?>
                                <optgroup label="Events (onsite)">
                                    <?php foreach ($events as $ev): ?>
                                        <option value="event:<?php echo (int)$ev['id']; ?>" <?php echo $curScope === 'event:' . (int)$ev['id'] ? 'selected' : ''; ?>><?php echo wa_e($ev['name']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Link</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var box = document.getElementById('wa_messages');
    if (!box) return;
    var convId = <?php echo (int)$conv_id; ?>;
    var lastId = <?php echo (int)$lastMsgId; ?>;
    box.scrollTop = box.scrollHeight;

    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }

    function mediaHtml(m) {
        var u = 'wa_media.php?msg=' + m.id;
        if (m.type === 'image') return '<a href="' + u + '" target="_blank"><img src="' + u + '" style="max-width:220px;max-height:220px;border-radius:8px;display:block"></a>';
        if (m.type === 'video') return '<video controls preload="none" style="max-width:240px;border-radius:8px" src="' + u + '"></video>';
        if (m.type === 'audio' || m.type === 'voice') return '<audio controls preload="none" src="' + u + '"></audio>';
        return '<a href="' + u + '" target="_blank"><i class="bi bi-file-earmark-arrow-down"></i> Download ' + esc(m.type) + '</a>';
    }
    // Inner HTML of a bubble's meta line (timestamp + status, or a Resend button
    // when an outbound message failed to go out). Mirrors the PHP render.
    function metaInner(m) {
        var out = m.direction === 'outbound';
        if (out && m.status === 'failed') {
            return esc(m.ts)
                + ' · <span class="text-danger fw-semibold">not sent</span>'
                + ' <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 ms-1 waResend"'
                + ' data-msg="' + m.id + '" style="font-size:11px; line-height:1.4">'
                + '<i class="bi bi-arrow-clockwise"></i> Resend</button>';
        }
        return esc(m.ts) + (m.status ? ' · ' + esc(m.status) : '');
    }
    function addMsg(m) {
        // Internal staff-only handoff note — mirror the italic PHP banner, not a bubble.
        if (m.type === 'note') {
            var nb = document.createElement('div');
            nb.className = 'd-flex justify-content-center mb-2';
            nb.innerHTML =
                '<div class="px-3 py-2 rounded-3" style="max-width:82%; background:#fff8e1; border:1px solid #ffe08a">'
                + '<div class="small fw-semibold text-warning-emphasis" style="font-size:11px; letter-spacing:.02em">'
                + '<i class="bi bi-headset me-1"></i>HANDOFF — FOR STAFF</div>'
                + '<div class="fst-italic text-dark" style="white-space:pre-wrap;word-wrap:break-word">' + esc(m.body).replace(/\n/g, '<br>') + '</div>'
                + '<div class="small text-muted" style="font-size:11px">' + esc(m.ts || '') + '</div>'
                + '</div>';
            box.appendChild(nb);
            return;
        }
        var out = m.direction === 'outbound';
        var failed = out && m.status === 'failed';
        var bubbleCls = !out ? 'bg-white border'
            : (failed ? 'bg-white border border-danger text-dark' : 'bg-success text-white');
        var metaCls = (!out || failed) ? 'text-muted' : 'text-white-50';
        var wrap = document.createElement('div');
        wrap.className = 'd-flex mb-2 ' + (out ? 'justify-content-end' : 'justify-content-start');
        var isMedia = (m.type !== 'text' && m.type !== 'template');
        var content = isMedia
            ? mediaHtml(m) + (m.body ? '<div class="mt-1" style="white-space:pre-wrap;word-wrap:break-word">' + esc(m.body).replace(/\n/g, '<br>') + '</div>' : '')
            : '<div style="white-space:pre-wrap; word-wrap:break-word">' + esc(m.body).replace(/\n/g, '<br>') + '</div>';
        wrap.innerHTML =
            '<div class="p-2 px-3 rounded-3 ' + bubbleCls + '" style="max-width:72%" data-msg-bubble="' + m.id + '">'
            + content
            + '<div class="small ' + metaCls + ' waMeta" style="font-size:11px">' + metaInner(m) + '</div>'
            + '</div>';
        box.appendChild(wrap);
    }

    // Resend a failed message (event-delegated so it works for server-rendered AND
    // live-polled bubbles). On success the same bubble flips from red "not sent" to
    // the normal delivered look.
    box.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.waResend') : null;
        if (!btn) return;
        var id = btn.getAttribute('data-msg');
        var bubble = box.querySelector('[data-msg-bubble="' + id + '"]');
        var meta = bubble ? bubble.querySelector('.waMeta') : null;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.8rem;height:.8rem"></span> Resending…';
        var fd = new FormData();
        fd.append('action', 'resend');
        fd.append('msg', id);
        fetch('includes/wa_api.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.ok) {
                    if (bubble) { bubble.className = 'p-2 px-3 rounded-3 bg-success text-white'; bubble.style.maxWidth = '72%'; }
                    if (meta) { meta.className = 'small text-white-50 waMeta'; meta.innerHTML = metaInner({ direction: 'outbound', status: d.status || 'sent', ts: meta.textContent.split(' · ')[0].trim() }); }
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Resend';
                    var reasons = { outside_24h_window: '24h window closed — use a template', forbidden: 'not allowed', cannot_rebuild: 'cannot rebuild message' };
                    var why = (d && (reasons[d.error] || d.error)) || 'failed';
                    var note = bubble ? bubble.querySelector('.waResendErr') : null;
                    if (!note && bubble) { note = document.createElement('div'); note.className = 'waResendErr small text-danger mt-1'; bubble.appendChild(note); }
                    if (note) note.textContent = 'Still failed: ' + why;
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Resend';
            });
    });

    // Quick replies: fill the reply box, substituting {name} with the contact's name.
    var contactName = <?php echo json_encode($conv['profile_name'] ?: ''); ?>;
    var replyBox = document.querySelector('textarea[name="body"]');
    document.querySelectorAll('.waQuickBtn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!replyBox) return;
            var text = (btn.getAttribute('data-body') || '').replace(/\{name\}/g, contactName || 'there');
            replyBox.value = text;
            replyBox.focus();
        });
    });

    function poll() {
        fetch('includes/wa_api.php?action=thread&id=' + convId + '&after=' + lastId)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.messages || !d.messages.length) return;
                var nearBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < 80;
                d.messages.forEach(function (m) { if (m.id > lastId) { lastId = m.id; addMsg(m); } });
                if (nearBottom) box.scrollTop = box.scrollHeight;
            })
            .catch(function () {});
    }
    setInterval(poll, 4000);
})();
</script>

<?php require_once 'footer.php'; ?>
