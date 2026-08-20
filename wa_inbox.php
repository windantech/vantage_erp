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
wa_channel_schema_ensure($conn);         // ensure last_channel exists before the query below reads it
wa_call_permission_schema_ensure($conn); // ensure wa_call_permissions exists for the Ready-to-Call predicate

$flash = isset($_SESSION['wa_flash']) ? $_SESSION['wa_flash'] : null;
unset($_SESSION['wa_flash']);

// The rows are no longer fetched here. This page used to select EVERY conversation
// a rep could see — fourteen correlated subqueries per row — and render them, only
// for the live poll to replace the whole table a moment later. The poll is now the
// single source of rows, one page at a time, so the markup exists once instead of
// twice and the query cost stops scaling with the size of the inbox.
//
// Two things are still read here, because they are cheap and the page needs them
// before first paint: the chip counts, and the course/event names for the filter.
$counts     = wa_inbox_counts($conn, $staff_id, $is_supervisor);
$courseOpts = wa_inbox_courses($conn, $staff_id, $is_supervisor);
$unreadTotal = (int)$counts['unread'];
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
                            <small class="text-muted">(<span id="waCount">…</span><span id="waOf"></span>)</small>
                            <span id="waLive" class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-broadcast"></i> live
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="btn-group btn-group-sm" role="group" id="waFilters">
                                <button type="button" class="btn btn-outline-danger" data-filter="closing" title="The customer's 24-hour service window shuts within the hour. After that you can only reach them with an approved template, so reply now.">Closing soon <span class="badge bg-danger ms-1" id="cntClosing"><?php echo (int)$counts['closing']; ?></span></button>
                                <button type="button" class="btn btn-outline-success" data-filter="ready" title="The customer granted permission to be called and is still inside the 24-hour calling window. Open the chat and use Call now.">Ready to call <span class="badge bg-success ms-1" id="cntReady"><?php echo (int)$counts['ready']; ?></span></button>
                                <button type="button" class="btn btn-outline-secondary active" data-filter="all">All <span class="badge bg-secondary ms-1" id="cntAll"><?php echo (int)$counts['all']; ?></span></button>
                                <button type="button" class="btn btn-outline-primary" data-filter="mine" title="Chats for courses, events and programmes you are a rep of, plus anything assigned to you — excludes the shared Triage pool.">My courses <span class="badge bg-primary ms-1" id="cntMine"><?php echo (int)$counts['mine']; ?></span></button>
                                <button type="button" class="btn btn-outline-danger" data-filter="unread">Unread <span class="badge bg-danger ms-1" id="cntUnread"><?php echo (int)$counts['unread']; ?></span></button>
                                <button type="button" class="btn btn-outline-warning" data-filter="escalated">Escalated <span class="badge bg-warning text-dark ms-1" id="cntEsc"><?php echo (int)$counts['escalated']; ?></span></button>
                                <button type="button" class="btn btn-outline-success" data-filter="reengaged" title="Clients who replied after a re-engagement template">Re-engaged <span class="badge bg-success ms-1" id="cntReeng"><?php echo (int)$counts['reengaged']; ?></span></button>
                                <button type="button" class="btn btn-outline-info" data-filter="triage" title="Nobody owns these and the bot could not work out what they want — they are invisible to every other view. Pick one up and reply.">Triage <span class="badge bg-info text-dark ms-1" id="cntTriage"><?php echo (int)$counts['triage']; ?></span></button>
                            </div>
                            <select id="waCourse" class="form-select form-select-sm" style="width:170px" title="Filter by course / event">
                                <option value="">All courses</option>
                                <?php foreach ($courseOpts as $rn) {
                                    echo '<option value="' . wa_e($rn) . '">' . wa_e($rn) . '</option>';
                                } ?>
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
                                <!-- Rows are rendered by the poll below, which is also
                                     what keeps them live. They used to be written here in
                                     PHP as well, which meant the same row existed twice in
                                     two languages and had to be kept in step by hand. -->
                                <tr id="waLoading">
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        Loading conversations…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Load more. Hidden until the server says there is another page. -->
                    <div id="waMoreWrap" class="text-center pt-3" style="display:none">
                        <button type="button" id="waMore" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-down-circle me-1"></i>Load more
                        </button>
                        <div id="waCap" class="small text-muted mt-2" style="display:none">
                            Showing the most recent <?php echo (int)WA_INBOX_MAX_ROWS; ?>.
                            Use a filter or the search box to narrow it down.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
(function () {
    var current = [];
    var currentFilter = 'all';
    var PAGE     = <?php echo (int)WA_INBOX_PAGE; ?>;
    var MAX_ROWS = <?php echo (int)WA_INBOX_MAX_ROWS; ?>;
    var limit    = PAGE;      // size of the window currently loaded
    var hasMore  = false;     // the server told us there is another page
    var rowsEl   = document.getElementById('waRows');
    var moreWrap = document.getElementById('waMoreWrap');
    var moreBtn  = document.getElementById('waMore');
    var capEl    = document.getElementById('waCap');
    var ofEl     = document.getElementById('waOf');
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
    /* Alerts compare this poll against the last one, so the comparison is only
       meaningful while the two cover the same set of chats. Changing a tab or
       widening the window pulls in conversations that were never on screen — each
       with whatever unread count it has always had — and every one of them would
       read as a brand-new message and set off the alarm. So the baseline is
       dropped whenever the window changes, and the next poll re-establishes it
       silently. */
    function resetAlertBaseline() { prevMap = null; }

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

    /* Render the page of rows the server sent.
       Filtering, sorting and counting all happen in SQL now. This used to do the
       lot in the browser, which is precisely why the browser needed every single
       conversation — and why the page got slower with every chat the business
       ever had. */
    function render(list) {
        var html = '';
        list.forEach(function (c) {
            var hc = c.handler === 'human' ? 'bg-success' : 'bg-primary';
            var rowStyle = 'cursor:pointer' + (c.escalated ? ';border-left:4px solid #ffc107' : '');
            var name = '<strong class="' + (c.unread ? 'fw-bold' : '') + '">' + esc(c.name || '—') + '</strong>'
                + (c.escalated ? ' <span class="badge bg-warning text-dark ms-1">escalated</span>' : '')
                + (c.unread ? ' <span class="badge bg-danger rounded-pill ms-1">' + c.unread + '</span>' : '')
                + (c.closing ? ' <span class="wa-cd badge bg-warning text-dark ms-1" data-left="' + c.win_left + '" title="Time left to reply without a template"></span>' : '')
                + (c.channel && c.channel !== 'messaging' ? ' <span class="badge bg-dark ms-1" title="Wrote to the calling line — replies go back on that number"><i class="bi bi-telephone"></i></span>' : '')
                + (c.ready_call ? ' <span class="badge bg-success ms-1" title="Permission granted ' + esc(c.call_granted) + ' — open the chat to call"><i class="bi bi-telephone-outbound"></i> <span class="wa-cd" data-left="' + c.call_left + '"></span></span>' : '');
            html += '<tr style="' + rowStyle + '" class="' + (c.unread ? 'table-active' : '') + '"'
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
        rowsEl.innerHTML = html || '<tr><td colspan="7" class="text-center py-5 text-muted">'
            + '<i class="bi bi-inbox fs-1 d-block mb-3"></i>No matching conversations</tr>';
        countEl.textContent = list.length;
        ofEl.textContent = hasMore ? ' of many' : '';
        moreWrap.style.display = hasMore ? '' : 'none';
        capEl.style.display = (limit >= MAX_ROWS) ? '' : 'none';
        moreBtn.disabled = (limit >= MAX_ROWS);
        // The rows were just replaced, so the new badges are empty until the next
        // tick. Paint them now, or every poll flashes a blank badge for a second.
        tickCountdowns();
    }

    /* The chip totals cover the WHOLE inbox, not the loaded page — a chip that
       counted only what happened to be on screen would read like a fact and be
       wrong. They cost more than a page of rows, so they refresh on their own
       slower cadence. */
    function applyCounts(counts) {
        if (!counts) { return; }
        document.getElementById('cntAll').textContent      = counts.all;
        document.getElementById('cntUnread').textContent   = counts.unread;
        document.getElementById('cntEsc').textContent      = counts.escalated;
        document.getElementById('cntReeng').textContent    = counts.reengaged;
        document.getElementById('cntTriage').textContent   = counts.triage;
        document.getElementById('cntMine').textContent     = counts.mine;
        document.getElementById('cntClosing').textContent  = counts.closing;
        document.getElementById('cntReady').textContent    = counts.ready;
        if (counts.unread) { unreadEl.textContent = counts.unread + ' unread'; unreadEl.style.display = ''; }
        else { unreadEl.style.display = 'none'; }
        document.title = (counts.unread ? '(' + counts.unread + ') ' : '') + 'WhatsApp Inbox';
    }

    /* One fetch, always for the top `limit` rows of the current filter.

       There is deliberately no cursor. The live poll re-reads the whole loaded
       window from the top, so rows arriving while somebody is reading cannot
       cause the duplicates and skips that offset paging produces on a list that
       reorders itself every time a customer writes. "Load more" simply widens
       the window, and MAX_ROWS bounds what a poll can ever cost. */
    function query(extra) {
        var p = new URLSearchParams();
        p.set('action', 'inbox');
        p.set('limit', String(limit));
        p.set('filter', currentFilter);
        if (courseEl  && courseEl.value)  p.set('course',  courseEl.value);
        if (handlerEl && handlerEl.value) p.set('handler', handlerEl.value);
        if (searchEl  && searchEl.value)  p.set('q',       searchEl.value);
        if (extra) { p.set(extra, '1'); }
        return 'includes/wa_api.php?' + p.toString();
    }

    var inFlight = false;
    function load(withCounts) {
        if (inFlight) { return; }          // never stack requests on a slow link
        inFlight = true;
        fetch(query(withCounts ? 'counts' : ''))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.conversations) { return; }
                current = d.conversations;
                hasMore = !!d.has_more;
                detectAndAlert(current);
                render(current);
                applyCounts(d.counts);
            })
            .catch(function () {})
            .then(function () { inFlight = false; });
    }

    /* Changing a filter resets the window: page four of "All" has nothing to do
       with page four of "Unread". */
    function refilter() {
        limit = PAGE;
        resetAlertBaseline();
        saveFilters();
        load(true);                        // the tab changed, so the totals may have too
    }

    /* Typing now costs a query, so wait for a pause rather than firing on every
       keystroke. 300ms is below the threshold where a search feels laggy and well
       above the gap between two keys. */
    var searchTimer = null;
    searchEl.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(refilter, 300);
    });
    if (courseEl)  courseEl.addEventListener('change', refilter);
    if (handlerEl) handlerEl.addEventListener('change', refilter);
    document.querySelectorAll('#waFilters button').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('#waFilters button').forEach(function (x) { x.classList.remove('active'); });
            b.classList.add('active');
            currentFilter = b.getAttribute('data-filter');
            refilter();
        });
    });

    moreBtn.addEventListener('click', function () {
        if (limit >= MAX_ROWS) { return; }
        limit = Math.min(MAX_ROWS, limit + PAGE);
        moreBtn.disabled = true;
        resetAlertBaseline();
        load(false);
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
    load(true);                     // first page + chip totals

    /* Rows stay live at six seconds. The totals sweep the whole inbox rather than
       one page, so they refresh every fifth poll instead — half a minute out of
       date on a badge is not worth five times the database work. */
    var ticks = 0;
    setInterval(function () {
        ticks++;
        load(ticks % 5 === 0);
    }, 6000);
})();
</script>

<?php require_once 'footer.php'; ?>
