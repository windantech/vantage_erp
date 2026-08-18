<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';
wa_use_nairobi_time($conn);

// Access: ERP role 44. Supervisors (777) see all; others see only their own.
if (!in_array(WA_ROLE, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Access denied.</div></div>';
    require_once 'footer.php';
    exit;
}
$is_supervisor = in_array(777, $role);
wa_message_flags_ensure($conn);   // ensure sent_by_staff exists before the inbox query reads it
wa_conv_reengage_schema_ensure($conn);   // ensure reengaged_at exists before the inbox query reads it
wa_conv_mode_schema_ensure($conn);       // ensure program_id exists before the scope/triage SQL reads it

$flash = isset($_SESSION['wa_flash']) ? $_SESSION['wa_flash'] : null;
unset($_SESSION['wa_flash']);

// Scope: supervisors see all; every other rep sees chats for courses/events they're a
// rep of (primary or contributor), plus any assigned to them — not every course's.
$where = wa_inbox_scope_where($staff_id, $is_supervisor);

$sql = "
    SELECT cv.id, cv.ref_type, cv.ref_id, cv.handler, cv.escalated, cv.last_message_at,
           c.wa_id, c.profile_name,
           (CASE WHEN cv.reengaged_at IS NOT NULL AND EXISTS(
                SELECT 1 FROM wa_messages m2 WHERE m2.contact_id = c.id
                  AND m2.direction = 'inbound' AND m2.created_at >= cv.reengaged_at)
             THEN 1 ELSE 0 END) AS reengaged_responded,
           " . wa_window_left_sql('c') . " AS win_left,
           " . wa_triage_sql('cv') . " AS is_triage,
           " . wa_mine_sql($staff_id, 'cv') . " AS is_mine,
           CASE cv.ref_type
                WHEN 'course' THEN (SELECT course FROM course WHERE course_id = cv.ref_id)
                WHEN 'event'  THEN (SELECT event_title FROM `Event` WHERE event_id = cv.ref_id)
           END AS ref_name,
           COALESCE(NULLIF(s.full_name,''), ru.fullname) AS owner_name,
           (SELECT body FROM wa_messages m WHERE m.contact_id = c.id AND m.type <> 'note' ORDER BY m.id DESC LIMIT 1) AS last_body,
           (SELECT CASE WHEN m.direction='inbound' THEN 'in' WHEN m.sent_by_staff IS NULL THEN 'ai' ELSE 'human' END
              FROM wa_messages m WHERE m.contact_id = c.id AND m.type <> 'note' ORDER BY m.id DESC LIMIT 1) AS last_kind,
           -- Unread means it needs a HUMAN. While the AI is handling a chat it is not unread;
           -- it only counts once the chat is escalated or a human owns it, so an AI
           -- conversing normally never clutters the unread badge.
           (CASE WHEN cv.escalated = 1 OR cv.handler = 'human' THEN
              (SELECT COUNT(*) FROM wa_messages m
                 WHERE m.contact_id = c.id AND m.direction = 'inbound'
                   AND (cv.last_read_at IS NULL OR m.created_at > cv.last_read_at))
            ELSE 0 END) AS unread
      FROM wa_conversations cv
      JOIN wa_contacts c        ON c.id  = cv.contact_id
 LEFT JOIN registered_users ru  ON ru.id = cv.assigned_user_id
 LEFT JOIN staff s              ON s.system_user_id = cv.assigned_user_id
    {$where}
  ORDER BY cv.last_message_at DESC, cv.id DESC";
$result = mysqli_query($conn, $sql);

$conversations = [];
$unreadTotal = 0;
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $unreadTotal += (int)$row['unread'];
        $conversations[] = $row;
    }
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-whatsapp me-2"></i>WhatsApp Inbox
                        <span id="waUnread" class="badge bg-danger align-middle ms-2" style="<?php echo $unreadTotal ? '' : 'display:none'; ?>"><?php echo (int)$unreadTotal; ?> unread</span>
                    </h4>
                    <p class="text-muted mb-0"><?php echo $is_supervisor ? 'All conversations' : 'Your assigned chats'; ?> · updates live</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($is_supervisor): ?>
                    <a href="wa_insights.php" class="btn btn-outline-primary"><i class="bi bi-graph-up-arrow me-1"></i>Insights</a>
                    <a href="wa_broadcast.php" class="btn btn-outline-primary"><i class="bi bi-megaphone me-1"></i>Broadcast</a>
                    <a href="wa_canned.php" class="btn btn-outline-secondary"><i class="bi bi-chat-left-text me-1"></i>Quick replies</a>
                    <a href="wa_settings.php" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i>Settings</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo wa_e($flash[0]); ?>"><?php echo wa_e($flash[1]); ?></div>
            <?php endif; ?>

            <!-- Actions dropdowns must not be clipped by the scrollable table container -->
            <style>#waInboxCard .table-responsive { overflow: visible; }</style>

            <!-- Conversations Table -->
            <div class="card shadow-sm border-0" id="waInboxCard">
                <div class="card-header bg-white py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="m-0 fw-bold text-uppercase d-inline">Conversations</h6>
                            <small class="text-muted">(<span id="waCount"><?php echo count($conversations); ?></span>)</small>
                            <span id="waLive" class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-broadcast"></i> live
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="btn-group btn-group-sm" role="group" id="waFilters">
                                <button type="button" class="btn btn-outline-danger" data-filter="closing" title="The customer's 24-hour service window shuts within the hour. After that you can only reach them with an approved template, so reply now.">Closing soon <span class="badge bg-danger ms-1" id="cntClosing">0</span></button>
                                <button type="button" class="btn btn-outline-secondary active" data-filter="all">All <span class="badge bg-secondary ms-1" id="cntAll">0</span></button>
                                <button type="button" class="btn btn-outline-primary" data-filter="mine" title="Chats for courses, events and programmes you are a rep of, plus anything assigned to you — excludes the shared Triage pool.">My courses <span class="badge bg-primary ms-1" id="cntMine">0</span></button>
                                <button type="button" class="btn btn-outline-danger" data-filter="unread">Unread <span class="badge bg-danger ms-1" id="cntUnread">0</span></button>
                                <button type="button" class="btn btn-outline-warning" data-filter="escalated">Escalated <span class="badge bg-warning text-dark ms-1" id="cntEsc">0</span></button>
                                <button type="button" class="btn btn-outline-success" data-filter="reengaged" title="Clients who replied after a re-engagement template">Re-engaged <span class="badge bg-success ms-1" id="cntReeng">0</span></button>
                                <button type="button" class="btn btn-outline-info" data-filter="triage" title="Nobody owns these and the bot could not work out what they want — they are invisible to every other view. Pick one up and reply.">Triage <span class="badge bg-info text-dark ms-1" id="cntTriage">0</span></button>
                            </div>
                            <select id="waCourse" class="form-select form-select-sm" style="width:170px" title="Filter by course / event">
                                <option value="">All courses</option>
                                <?php
                                $courseOpts = [];
                                foreach ($conversations as $cRow) {
                                    $rn = trim((string)($cRow['ref_name'] ?? ''));
                                    if ($rn !== '') { $courseOpts[$rn] = true; }
                                }
                                ksort($courseOpts);
                                foreach (array_keys($courseOpts) as $rn) {
                                    echo '<option value="' . wa_e($rn) . '">' . wa_e($rn) . '</option>';
                                }
                                ?>
                            </select>
                            <select id="waHandler" class="form-select form-select-sm" style="width:120px" title="Filter by handler">
                                <option value="">AI &amp; Human</option>
                                <option value="ai">AI only</option>
                                <option value="human">Human only</option>
                            </select>
                            <input type="text" id="waSearch" class="form-control form-control-sm" style="width:180px"
                                   placeholder="Search name / phone…">
                            <button type="button" id="waAlertBtn" class="btn btn-sm btn-outline-secondary"
                                    title="Sound + desktop alerts for new messages and escalations"></button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="waInboxTable" width="100%">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Contact</th>
                                    <th>Phone</th>
                                    <th>Course / Event</th>
                                    <th>Owner</th>
                                    <th>Handler</th>
                                    <th>Last Message</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="waRows">
                                <?php if (count($conversations) > 0): ?>
                                    <?php foreach ($conversations as $row): $u = (int)$row['unread'];
                                        $wl = $row['win_left'] === null ? null : (int)$row['win_left'];
                                        $closing = ($wl !== null && $wl > 0 && $wl <= WA_CLOSING_SECS); ?>
                                    <tr style="cursor:pointer;<?php echo (int)$row['escalated'] === 1 ? 'border-left:4px solid #ffc107;' : ''; ?>" class="<?php echo $u ? 'table-active' : ''; ?>"
                                        data-reengaged="<?php echo (int)$row['reengaged_responded']; ?>"
                                        data-closing="<?php echo $closing ? 1 : 0; ?>"
                                        data-triage="<?php echo (int)$row['is_triage']; ?>"
                                        data-mine="<?php echo (int)$row['is_mine']; ?>"
                                        onclick="location.href='wa_thread.php?id=<?php echo (int)$row['id']; ?>'">
                                        <td class="ps-3">
                                            <strong class="<?php echo $u ? 'fw-bold' : ''; ?>"><?php echo wa_e($row['profile_name'] ?: '—'); ?></strong>
                                            <?php if ((int)$row['escalated'] === 1): ?>
                                                <span class="badge bg-warning text-dark ms-1">escalated</span>
                                            <?php endif; ?>
                                            <?php if ($u): ?>
                                                <span class="badge bg-danger rounded-pill ms-1"><?php echo $u; ?></span>
                                            <?php endif; ?>
                                            <?php if ($closing): ?>
                                                <span class="wa-cd badge bg-warning text-dark ms-1" data-left="<?php echo $wl; ?>" title="Time left to reply without a template"></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo wa_e($row['wa_id']); ?></td>
                                        <td><?php echo wa_e($row['ref_name'] ?: 'Unassigned'); ?></td>
                                        <td><?php echo wa_e($row['owner_name'] ?: '—'); ?></td>
                                        <td>
                                            <?php $hc = $row['handler'] === 'human' ? 'bg-success' : 'bg-primary'; ?>
                                            <span class="badge <?php echo $hc; ?>"><?php echo wa_e($row['handler']); ?></span>
                                        </td>
                                        <td class="<?php echo $u ? 'fw-semibold' : ''; ?>">
                                            <?php $lk = $row['last_kind'] ?? 'in';
                                                if ($lk === 'ai')    echo '<span title="Last reply: AI">🤖</span> ';
                                                elseif ($lk === 'human') echo '<span title="Last reply: agent">👤</span> '; ?>
                                            <?php echo wa_e(mb_strimwidth((string)$row['last_body'], 0, 60, '…')); ?>
                                        </td>
                                        <td class="text-end pe-3" onclick="event.stopPropagation();">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li><a class="dropdown-item" href="wa_thread.php?id=<?php echo (int)$row['id']; ?>"><i class="bi bi-chat-dots me-2"></i>Open chat</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><form method="post" action="includes/wa_process.php" class="m-0"><input type="hidden" name="action" value="inbox_assign_me"><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>"><button type="submit" class="dropdown-item"><i class="bi bi-person-check me-2"></i>Assign to me</button></form></li>
                                                    <li><form method="post" action="includes/wa_process.php" class="m-0"><input type="hidden" name="action" value="inbox_handler"><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>"><input type="hidden" name="handler" value="<?php echo $row['handler'] === 'human' ? 'ai' : 'human'; ?>"><button type="submit" class="dropdown-item"><i class="bi bi-robot me-2"></i><?php echo $row['handler'] === 'human' ? 'Switch to AI' : 'Switch to Human'; ?></button></form></li>
                                                    <?php if ((int)$row['escalated'] === 1): ?>
                                                    <li><form method="post" action="includes/wa_process.php" class="m-0"><input type="hidden" name="action" value="inbox_resolve"><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>"><button type="submit" class="dropdown-item text-success"><i class="bi bi-check2-circle me-2"></i>Resolve escalation</button></form></li>
                                                    <?php else: ?>
                                                    <li><form method="post" action="includes/wa_process.php" class="m-0"><input type="hidden" name="action" value="inbox_escalate"><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>"><button type="submit" class="dropdown-item text-warning"><i class="bi bi-exclamation-triangle me-2"></i>Escalate</button></form></li>
                                                    <?php endif; ?>
                                                    <?php if ($u): ?>
                                                    <li><form method="post" action="includes/wa_process.php" class="m-0"><input type="hidden" name="action" value="inbox_mark_read"><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>"><button type="submit" class="dropdown-item"><i class="bi bi-envelope-open me-2"></i>Mark as read</button></form></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                                            <p class="text-muted">No conversations yet</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <script>
                    /* Pre-apply the saved filters to the server-rendered rows BEFORE first
                       paint, so returning from a chat shows the filtered list straight away
                       instead of flashing the full "All courses" list, then the live poll
                       (below) refreshes as normal. */
                    (function () {
                        var s; try { s = JSON.parse(sessionStorage.getItem('waInboxFilters') || '{}'); } catch (e) { return; }
                        if (!s) return;
                        var cEl = document.getElementById('waCourse'),
                            hEl = document.getElementById('waHandler'),
                            sEl = document.getElementById('waSearch');
                        if (cEl && s.course  != null) cEl.value = s.course;
                        if (hEl && s.handler != null) hEl.value = s.handler;
                        if (sEl && s.q       != null) sEl.value = s.q;
                        if (s.tab) document.querySelectorAll('#waFilters button').forEach(function (x) {
                            x.classList.toggle('active', x.getAttribute('data-filter') === s.tab);
                        });
                        if (!(s.course || (s.tab && s.tab !== 'all') || s.handler || s.q)) return;
                        var q = (s.q || '').toLowerCase();
                        document.querySelectorAll('#waRows tr').forEach(function (tr) {
                            var tds = tr.children; if (tds.length < 6) return;   // skip empty-state row
                            var course    = (tds[2].textContent || '').trim();
                            var handler   = (tds[4].textContent || '').trim().toLowerCase();
                            var unread    = tr.classList.contains('table-active');
                            var escalated = (tr.getAttribute('style') || '').indexOf('#ffc107') !== -1;
                            var ok = true;
                            if (s.tab === 'unread'    && !unread)             ok = false;
                            if (s.tab === 'escalated' && !escalated)          ok = false;
                            if (s.tab === 'reengaged' && tr.getAttribute('data-reengaged') !== '1') ok = false;
                            if (s.tab === 'triage'    && tr.getAttribute('data-triage')    !== '1') ok = false;
                            if (s.tab === 'closing'   && tr.getAttribute('data-closing')   !== '1') ok = false;
                            if (s.tab === 'mine'      && tr.getAttribute('data-mine')      !== '1') ok = false;
                            if (s.course  && course  !== s.course)            ok = false;
                            if (s.handler && handler !== s.handler)           ok = false;
                            if (q && (tr.textContent || '').toLowerCase().indexOf(q) === -1) ok = false;
                            if (!ok) tr.style.display = 'none';
                        });
                    })();
                    </script>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
(function () {
    var current = [];
    var currentFilter = 'all';
    var rowsEl   = document.getElementById('waRows');
    var searchEl = document.getElementById('waSearch');
    var courseEl = document.getElementById('waCourse');
    var handlerEl = document.getElementById('waHandler');
    var countEl  = document.getElementById('waCount');
    var unreadEl = document.getElementById('waUnread');

    // Remember the active filters for the session so opening a chat and coming
    // back keeps the course/tab/handler/search you had, instead of resetting.
    var FKEY = 'waInboxFilters';
    function saveFilters() {
        try {
            sessionStorage.setItem(FKEY, JSON.stringify({
                tab:     currentFilter,
                course:  courseEl  ? courseEl.value  : '',
                handler: handlerEl ? handlerEl.value : '',
                q:       searchEl  ? searchEl.value  : ''
            }));
        } catch (e) {}
    }
    function restoreFilters() {
        var s; try { s = JSON.parse(sessionStorage.getItem(FKEY) || '{}'); } catch (e) { s = {}; }
        if (!s) return;
        if (courseEl  && s.course  != null) courseEl.value  = s.course;   // unknown value -> '' (All)
        if (handlerEl && s.handler != null) handlerEl.value = s.handler;
        if (searchEl  && s.q       != null) searchEl.value  = s.q;
        if (s.tab) {
            currentFilter = s.tab;
            document.querySelectorAll('#waFilters button').forEach(function (x) {
                x.classList.toggle('active', x.getAttribute('data-filter') === s.tab);
            });
        }
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
    function kindIcon(k) { return k === 'ai' ? '🤖 ' : (k === 'human' ? '👤 ' : ''); }   // last-reply sender

    // Actions dropdown for a conversation row (mirrors the PHP-rendered one).
    function actForm(id, action, label, icon, cls, extra) {
        var hidden = '<input type="hidden" name="action" value="' + action + '">'
                   + '<input type="hidden" name="id" value="' + id + '">'
                   + (extra || '');
        return '<li><form method="post" action="includes/wa_process.php" class="m-0">' + hidden
             + '<button type="submit" class="dropdown-item ' + (cls || '') + '"><i class="bi ' + icon + ' me-2"></i>' + label + '</button></form></li>';
    }
    function actionsCell(c) {
        var toHuman = c.handler === 'human';
        var items = '<li><a class="dropdown-item" href="wa_thread.php?id=' + c.id + '"><i class="bi bi-chat-dots me-2"></i>Open chat</a></li>'
            + '<li><hr class="dropdown-divider"></li>'
            + actForm(c.id, 'inbox_assign_me', 'Assign to me', 'bi-person-check', '')
            + actForm(c.id, 'inbox_handler', toHuman ? 'Switch to AI' : 'Switch to Human', 'bi-robot', '',
                      '<input type="hidden" name="handler" value="' + (toHuman ? 'ai' : 'human') + '">')
            + (c.escalated
                ? actForm(c.id, 'inbox_resolve', 'Resolve escalation', 'bi-check2-circle', 'text-success')
                : actForm(c.id, 'inbox_escalate', 'Escalate', 'bi-exclamation-triangle', 'text-warning'))
            + (c.unread ? actForm(c.id, 'inbox_mark_read', 'Mark as read', 'bi-envelope-open', '') : '');
        return '<td class="text-end pe-3" onclick="event.stopPropagation();">'
             + '<div class="dropdown">'
             + '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>'
             + '<ul class="dropdown-menu dropdown-menu-end shadow-sm">' + items + '</ul>'
             + '</div></td>';
    }

    // ---- sound + desktop alerts ----
    var alertsOn = localStorage.getItem('waAlerts') === '1';
    var audioCtx = null, prevMap = null;
    var alertBtn = document.getElementById('waAlertBtn');

    function updateAlertBtn() {
        if (!alertBtn) return;
        alertBtn.innerHTML = alertsOn ? '<i class="bi bi-bell-fill me-1"></i>Alerts on' : '<i class="bi bi-bell-slash me-1"></i>Alerts off';
        alertBtn.className = 'btn btn-sm ' + (alertsOn ? 'btn-success' : 'btn-outline-secondary');
    }
    function beep() {
        if (!audioCtx) return;
        try {
            var o = audioCtx.createOscillator(), g = audioCtx.createGain();
            o.type = 'sine'; o.frequency.value = 880; o.connect(g); g.connect(audioCtx.destination);
            g.gain.setValueAtTime(0.0001, audioCtx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.25, audioCtx.currentTime + 0.01);
            g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.35);
            o.start(); o.stop(audioCtx.currentTime + 0.35);
        } catch (e) {}
    }
    function notify(title, body) {
        if (('Notification' in window) && Notification.permission === 'granted') {
            try { var n = new Notification(title, { body: body }); n.onclick = function () { window.focus(); }; } catch (e) {}
        }
    }
    function detectAndAlert(list) {
        var map = {};
        list.forEach(function (c) { map[c.id] = { u: c.unread, e: c.escalated }; });
        if (prevMap === null) { prevMap = map; return; }   // baseline on first load — no alert
        if (alertsOn) {
            var newMsg = 0, escalations = 0, who = '';
            list.forEach(function (c) {
                var p = prevMap[c.id];
                var pu = p ? p.u : 0, pe = p ? p.e : 0;
                if (c.unread > pu)          { newMsg += (c.unread - pu); who = c.name || c.wa_id; }
                if (c.escalated && !pe)     { escalations++;             who = c.name || c.wa_id; }
            });
            if (escalations > 0) { beep(); notify('⚠ Chat escalated', escalations === 1 ? (who + ' needs a human') : (escalations + ' chats need a human')); }
            else if (newMsg > 0) { beep(); notify('New WhatsApp message', newMsg === 1 ? ('From ' + who) : (newMsg + ' new messages')); }
        }
        prevMap = map;
    }
    if (alertBtn) {
        updateAlertBtn();
        alertBtn.addEventListener('click', function () {
            alertsOn = !alertsOn;
            localStorage.setItem('waAlerts', alertsOn ? '1' : '0');
            if (alertsOn) {
                try { audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)(); if (audioCtx.state === 'suspended') audioCtx.resume(); } catch (e) {}
                if (('Notification' in window) && Notification.permission === 'default') { Notification.requestPermission(); }
                beep();   // confirm sound works (this click is the required user gesture)
            }
            updateAlertBtn();
        });
    }

    function render(list) {
        var q = (searchEl.value || '').toLowerCase();
        var courseVal  = courseEl  ? courseEl.value  : '';
        var handlerVal = handlerEl ? handlerEl.value : '';
        var counts = { all: list.length, unread: 0, escalated: 0, reengaged: 0, triage: 0, mine: 0, closing: 0 };
        var shown = 0, html = '';
        // On the Closing-soon tab the order that matters is "who runs out first",
        // not "who spoke last" — otherwise the most urgent chat can sit at the bottom.
        if (currentFilter === 'closing') {
            list = list.slice().sort(function (a, b) {
                return (a.win_left === null ? 1e9 : a.win_left) - (b.win_left === null ? 1e9 : b.win_left);
            });
        }
        list.forEach(function (c) {
            if (c.unread)    counts.unread++;
            if (c.escalated) counts.escalated++;
            if (c.reengaged) counts.reengaged++;
            if (c.triage) counts.triage++;
            if (c.mine) counts.mine++;
            if (c.closing) counts.closing++;
            if (currentFilter === 'unread'    && !c.unread)    return;
            if (currentFilter === 'escalated' && !c.escalated) return;
            if (currentFilter === 'reengaged' && !c.reengaged) return;
            if (currentFilter === 'triage'    && !c.triage)    return;
            if (currentFilter === 'mine'      && !c.mine)      return;
            if (currentFilter === 'closing'   && !c.closing)   return;
            if (courseVal  && (c.ref_name || '') !== courseVal) return;   // filter by course/event
            if (handlerVal && c.handler !== handlerVal) return;           // filter by AI/Human
            var hay = (c.name + ' ' + c.wa_id + ' ' + c.ref_name + ' ' + c.owner + ' ' + c.last_body).toLowerCase();
            if (q && hay.indexOf(q) === -1) return;
            shown++;
            var hc = c.handler === 'human' ? 'bg-success' : 'bg-primary';
            var rowStyle = 'cursor:pointer' + (c.escalated ? ';border-left:4px solid #ffc107' : '');
            var name = '<strong class="' + (c.unread ? 'fw-bold' : '') + '">' + esc(c.name || '—') + '</strong>'
                + (c.escalated ? ' <span class="badge bg-warning text-dark ms-1">escalated</span>' : '')
                + (c.unread ? ' <span class="badge bg-danger rounded-pill ms-1">' + c.unread + '</span>' : '')
                + (c.closing ? ' <span class="wa-cd badge bg-warning text-dark ms-1" data-left="' + c.win_left + '" title="Time left to reply without a template"></span>' : '');
            html += '<tr style="' + rowStyle + '" class="' + (c.unread ? 'table-active' : '') + '"'
                 + ' data-reengaged="' + (c.reengaged ? '1' : '0') + '"'
                 + ' data-closing="' + (c.closing ? '1' : '0') + '"'
                 + ' data-triage="' + (c.triage ? '1' : '0') + '"'
                 + ' data-mine="' + (c.mine ? '1' : '0') + '"'
                 + ' onclick="location.href=\'wa_thread.php?id=' + c.id + '\'">'
                 + '<td class="ps-3">' + name + '</td>'
                 + '<td>' + esc(c.wa_id) + '</td>'
                 + '<td>' + esc(c.ref_name || 'Unassigned') + '</td>'
                 + '<td>' + esc(c.owner || '—') + '</td>'
                 + '<td><span class="badge ' + hc + '">' + esc(c.handler) + '</span></td>'
                 + '<td class="' + (c.unread ? 'fw-semibold' : '') + '">' + kindIcon(c.last_kind) + esc(c.last_body) + '</td>'
                 + actionsCell(c)
                 + '</tr>';
        });
        rowsEl.innerHTML = html || '<tr><td colspan="7" class="text-center py-4 text-muted">No matches</td></tr>';
        countEl.textContent = shown;
        document.getElementById('cntAll').textContent    = counts.all;
        document.getElementById('cntUnread').textContent = counts.unread;
        document.getElementById('cntEsc').textContent    = counts.escalated;
        document.getElementById('cntReeng').textContent  = counts.reengaged;
        document.getElementById('cntTriage').textContent = counts.triage;
        document.getElementById('cntMine').textContent   = counts.mine;
        document.getElementById('cntClosing').textContent = counts.closing;
        if (counts.unread) { unreadEl.textContent = counts.unread + ' unread'; unreadEl.style.display = ''; }
        else { unreadEl.style.display = 'none'; }
        document.title = (counts.unread ? '(' + counts.unread + ') ' : '') + 'WhatsApp Inbox';
        // The rows were just replaced, so the new badges are empty until the next tick.
        // Paint them now, or every poll flashes a blank badge for up to a second.
        tickCountdowns();
    }

    function poll() {
        fetch('includes/wa_api.php?action=inbox')
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d && d.conversations) { current = d.conversations; detectAndAlert(current); render(current); } })
            .catch(function () {});
    }

    searchEl.addEventListener('input', function () { saveFilters(); render(current); });
    if (courseEl)  courseEl.addEventListener('change', function () { saveFilters(); render(current); });
    if (handlerEl) handlerEl.addEventListener('change', function () { saveFilters(); render(current); });
    document.querySelectorAll('#waFilters button').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('#waFilters button').forEach(function (x) { x.classList.remove('active'); });
            b.classList.add('active');
            currentFilter = b.getAttribute('data-filter');
            saveFilters();
            render(current);
        });
    });

    /* Countdown to the 24-hour window shutting.

       Each badge carries the seconds left AS THE SERVER SAW THEM. On first sight we
       turn that into a local deadline and count down to it, so the display ticks every
       second instead of jumping every 6 seconds when the poll lands — and a rep with a
       skewed clock still sees the server's answer, because only the remaining time is
       measured locally, never the deadline itself. render() rebuilds the rows, so each
       poll re-anchors the badges to fresh server values and drift cannot accumulate. */
    function tickCountdowns() {
        document.querySelectorAll('.wa-cd').forEach(function (el) {
            if (!el._deadline) {
                var left = parseInt(el.getAttribute('data-left'), 10);
                if (isNaN(left)) { return; }
                el._deadline = Date.now() + left * 1000;
            }
            var s = Math.round((el._deadline - Date.now()) / 1000);
            if (s <= 0) {
                el.textContent = 'window closed';
                el.className = 'wa-cd badge bg-secondary ms-1';
                return;
            }
            var m = Math.floor(s / 60), ss = s % 60;
            el.textContent = m + ':' + (ss < 10 ? '0' : '') + ss + ' left';
            // Under 15 minutes it stops being a warning and becomes the last chance.
            el.className = 'wa-cd badge ms-1 ' + (s <= 900 ? 'bg-danger' : 'bg-warning text-dark');
        });
    }
    tickCountdowns();
    setInterval(tickCountdowns, 1000);

    restoreFilters();               // re-apply the filters you had this session
    poll();                         // hydrate immediately
    setInterval(poll, 6000);        // then live every 6s
})();
</script>

<?php require_once 'footer.php'; ?>
