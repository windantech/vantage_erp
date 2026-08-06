<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
// Never cache this page: its send/preview logic is inline JS, so a cached copy (browser
// OR LiteSpeed server cache) runs stale code — the exact cause of the persistent
// "network error" after the fix was already deployed.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-LiteSpeed-Cache-Control: no-cache');
require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';

if (!in_array(777, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Supervisors only.</div></div>';
    require_once 'footer.php';
    exit;
}

wa_templates_autosync($conn);              // keep the dropdown current with the Hub (throttled)
$templates = array_values(array_filter(wa_templates_list($conn), function ($t) { return $t['status'] === 'approved'; }));
$courses   = wa_active_courses($conn);
$events    = wa_active_events($conn);
$dialogReady = WA_DIALOG_KEY && WA_DIALOG_KEY !== '' && strpos(WA_DIALOG_KEY, 'YOUR_') !== 0;

// Templates for the JS (name, language, var count)
$tplJs = array_map(function ($t) {
    return ['name' => $t['name'], 'language' => $t['language'], 'vars' => wa_template_var_count($t['body']), 'body' => $t['body']];
}, $templates);
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-megaphone me-2"></i>Broadcast</h4>
                    <p class="text-muted mb-0">Send an approved template to many contacts (works outside the 24h window).</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="wa_contacts.php" class="btn btn-outline-primary"><i class="bi bi-people me-1"></i>Contacts</a>
                    <a href="wa_broadcasts.php" class="btn btn-outline-primary"><i class="bi bi-bar-chart me-1"></i>History</a>
                    <a href="wa_templates.php" class="btn btn-outline-primary"><i class="bi bi-file-earmark-text me-1"></i>Templates</a>
                    <a href="wa_inbox.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inbox</a>
                </div>
            </div>

            <?php if (!$dialogReady): ?>
                <div class="alert alert-danger">Set <code>WA_DIALOG_KEY</code> in wa_config.php before broadcasting.</div>
            <?php endif; ?>
            <?php if (!$templates): ?>
                <div class="alert alert-warning">No approved templates yet — add one on the <a href="wa_templates.php">Templates</a> page first.</div>
            <?php endif; ?>

            <div class="alert alert-warning small">
                <i class="bi bi-shield-exclamation me-1"></i><strong>Use responsibly:</strong> only message contacts who opted in. Bulk sends affect your number's quality rating. Test on your own number first.
            </div>

            <div class="card shadow-sm border-0" style="max-width:760px">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Template</label>
                        <select id="bTemplate" class="form-select">
                            <option value="">— Select an approved template —</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?php echo wa_e($t['name'] . '|' . $t['language']); ?>">
                                    <?php echo wa_e($t['name'] . ' (' . $t['language'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="bVars" class="mb-3"></div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">Audience</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check"><input class="form-check-input" type="radio" name="aud" value="all" id="audAll" checked><label class="form-check-label" for="audAll">All contacts</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="aud" value="optedin" id="audOpt"><label class="form-check-label" for="audOpt">Opted-in only</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="aud" value="course" id="audCourse"><label class="form-check-label" for="audCourse">By course</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="aud" value="event" id="audEvent"><label class="form-check-label" for="audEvent">By event (onsite)</label></div>
                        </div>
                        <select id="bCourse" class="form-select mt-2 d-none" style="max-width:360px">
                            <option value="">— Select course —</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo wa_e($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="bEvent" class="form-select mt-2 d-none" style="max-width:360px">
                            <option value="">— Select event —</option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int)$ev['id']; ?>"><?php echo wa_e($ev['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted">When</label>
                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <div class="form-check"><input class="form-check-input" type="radio" name="when" value="now" id="whenNow" checked><label class="form-check-label" for="whenNow">Send now</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="when" value="later" id="whenLater"><label class="form-check-label" for="whenLater">Schedule for later</label></div>
                            <input type="datetime-local" id="bWhen" class="form-control d-none" style="max-width:240px">
                        </div>
                        <div class="small text-muted mt-1">Scheduled sends are fired by the server — no need to keep this page open.</div>
                    </div>

                    <button id="bSend" class="btn btn-primary" <?php echo (!$dialogReady || !$templates) ? 'disabled' : ''; ?>>
                        <i class="bi bi-send me-1"></i>Review &amp; send
                    </button>
                    <a href="wa_broadcasts.php" class="btn btn-outline-secondary">Scheduled &amp; history</a>
                    <span id="bStatus" class="ms-2 small text-muted"></span>

                    <div id="bProgWrap" class="mt-3 d-none">
                        <div class="progress" style="height:20px"><div id="bProg" class="progress-bar" style="width:0%">0%</div></div>
                        <div id="bResult" class="small text-muted mt-1"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipient preview: see exactly who will receive this before it goes out -->
        <div class="modal fade" id="bPreview" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-people me-2"></i>Recipients
                            <span id="bpCount" class="badge bg-primary ms-2">0</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-2" id="bpSummary"></p>
                        <div class="table-responsive" style="max-height:52vh">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="bg-light"><tr><th style="width:3rem">#</th><th>Name</th><th>Phone</th></tr></thead>
                                <tbody id="bpRows"></tbody>
                            </table>
                        </div>
                        <div id="bpMore" class="small text-muted mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="bpConfirm" class="btn btn-primary"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var TEMPLATES = <?php echo json_encode($tplJs); ?>;
    var tplSel = document.getElementById('bTemplate');
    var varsBox = document.getElementById('bVars');
    var courseSel = document.getElementById('bCourse');
    var eventSel = document.getElementById('bEvent');
    var sendBtn = document.getElementById('bSend');
    var statusEl = document.getElementById('bStatus');

    function currentTpl() {
        var v = tplSel.value; if (!v) return null;
        var parts = v.split('|');
        for (var i = 0; i < TEMPLATES.length; i++) {
            if (TEMPLATES[i].name === parts[0] && TEMPLATES[i].language === parts[1]) return TEMPLATES[i];
        }
        return null;
    }
    function renderVars() {
        var t = currentTpl();
        varsBox.innerHTML = '';
        if (!t || !t.vars) return;
        var help = document.createElement('div');
        help.className = 'small text-muted mb-1';
        help.innerHTML = 'Fill each variable. Tip: type <code>{name}</code> to insert each contact’s name.';
        varsBox.appendChild(help);
        for (var i = 1; i <= t.vars; i++) {
            var wrap = document.createElement('div'); wrap.className = 'mb-2';
            wrap.innerHTML = '<label class="form-label small text-muted">Variable {{' + i + '}}</label>'
                + '<input type="text" class="form-control bVar" data-i="' + i + '" placeholder="value for {{' + i + '}}">';
            varsBox.appendChild(wrap);
        }
    }
    tplSel.addEventListener('change', renderVars);
    document.querySelectorAll('input[name=aud]').forEach(function (r) {
        r.addEventListener('change', function () {
            courseSel.classList.toggle('d-none', !document.getElementById('audCourse').checked);
            eventSel.classList.toggle('d-none', !document.getElementById('audEvent').checked);
        });
    });
    var whenInput = document.getElementById('bWhen');
    document.querySelectorAll('input[name=when]').forEach(function (r) {
        r.addEventListener('change', function () {
            var later = document.getElementById('whenLater').checked;
            whenInput.classList.toggle('d-none', !later);
            document.getElementById('bSend').innerHTML = later
                ? '<i class="bi bi-clock me-1"></i>Schedule broadcast'
                : '<i class="bi bi-send me-1"></i>Review &amp; send';
        });
    });

    function getVars() { return Array.prototype.map.call(document.querySelectorAll('.bVar'), function (i) { return i.value; }); }
    function audience() {
        var f = document.querySelector('input[name=aud]:checked').value;
        // course_id carries the ref id for both course and event filters.
        var refId = f === 'event' ? (eventSel.value || 0) : (courseSel.value || 0);
        return { filter: f, course_id: refId };
    }
    function post(action, data) {
        var fd = new FormData(); fd.append('action', action);
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        return fetch('includes/wa_api.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
    var audLabels = { all: 'All contacts', optedin: 'Opted-in only', course: 'By course', event: 'By event (onsite)' };
    var previewEl = document.getElementById('bPreview');
    var previewModal = null, backdropEl = null;
    var pending = null;   // {t, aud, list, scheduled, when}

    // Lazily create the Bootstrap modal — this inline script runs BEFORE footer.php loads
    // bootstrap.js, so we must not touch `bootstrap` until the user actually clicks. Falls
    // back to a manual show if the bundle somehow isn't present, so the preview never dies
    // silently the way it did before.
    function openPreview() {
        // new bootstrap.Modal() exists in ALL Bootstrap 5 versions; getOrCreateInstance was
        // only added in 5.1, and this server loads an older bundle (hence the throw that got
        // mislabelled 'network error'). Guard + try/catch so we always fall back cleanly.
        if (!previewModal && window.bootstrap && typeof bootstrap.Modal === 'function') {
            try { previewModal = new bootstrap.Modal(previewEl); } catch (e) { previewModal = null; }
        }
        if (previewModal && typeof previewModal.show === 'function') { previewModal.show(); return; }
        previewEl.classList.add('show'); previewEl.style.display = 'block';
        previewEl.removeAttribute('aria-hidden'); document.body.classList.add('modal-open');
        if (!backdropEl) { backdropEl = document.createElement('div'); backdropEl.className = 'modal-backdrop fade show'; }
        document.body.appendChild(backdropEl);
    }
    function closePreview() {
        if (previewModal) { previewModal.hide(); return; }
        previewEl.classList.remove('show'); previewEl.style.display = 'none';
        previewEl.setAttribute('aria-hidden', 'true'); document.body.classList.remove('modal-open');
        if (backdropEl && backdropEl.parentNode) backdropEl.parentNode.removeChild(backdropEl);
    }
    // Make Cancel / × work in both modes (bootstrap's data-bs-dismiss + our fallback).
    previewEl.querySelectorAll('[data-bs-dismiss=modal]').forEach(function (b) { b.addEventListener('click', closePreview); });

    // Step 1 — resolve the audience and PREVIEW exactly who will receive it before sending.
    sendBtn.addEventListener('click', function () {
        var t = currentTpl();
        if (!t) { statusEl.textContent = 'Pick a template.'; return; }
        var aud = audience();
        if (aud.filter === 'course' && !aud.course_id) { statusEl.textContent = 'Pick a course.'; return; }
        if (aud.filter === 'event' && !aud.course_id) { statusEl.textContent = 'Pick an event.'; return; }
        var scheduled = document.getElementById('whenLater').checked;
        if (scheduled && !whenInput.value) { statusEl.textContent = 'Pick a date & time.'; return; }

        statusEl.textContent = 'Resolving recipients…';
        // Use GET (not POST) — the same request that works when opened directly. Some hosts'
        // firewalls block the POST, which showed up as a bogus "network error".
        var qs = 'action=broadcast_audience&filter=' + encodeURIComponent(aud.filter)
               + '&course_id=' + encodeURIComponent(aud.course_id) + '&_=' + (new Date().getTime());
        fetch('includes/wa_api.php?' + qs).then(function (r) {
            return r.text().then(function (txt) { return { status: r.status, txt: txt }; });
        }).then(function (res) {
            var d;
            try { d = JSON.parse(res.txt); } catch (e) {
                // Show what the server actually returned (a PHP fatal/redirect corrupts the JSON).
                statusEl.textContent = 'Server error (' + res.status + '): '
                    + (res.txt || '(empty)').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 240);
                return;
            }
            if (!d || !d.ok) { statusEl.textContent = 'Could not load recipients: ' + ((d && d.error) || 'unknown'); return; }
            var list = d.contacts || [];
            statusEl.textContent = '';
            if (!list.length) { statusEl.textContent = 'No contacts match that audience.'; return; }

            // Fill the preview table (cap the DOM for very large audiences).
            var CAP = 500, rows = '';
            list.slice(0, CAP).forEach(function (c, idx) {
                rows += '<tr><td class="text-muted">' + (idx + 1) + '</td><td>' + esc(c.name || '—')
                      + '</td><td>' + esc(c.wa_id) + '</td></tr>';
            });
            document.getElementById('bpRows').innerHTML = rows;
            document.getElementById('bpCount').textContent = list.length;
            document.getElementById('bpMore').textContent = list.length > CAP
                ? ('Showing the first ' + CAP + ' of ' + list.length + ' — all ' + list.length + ' will receive it.') : '';
            document.getElementById('bpSummary').innerHTML = 'Template <strong>' + esc(t.name) + '</strong> ('
                + esc(t.language) + ') · Audience: <strong>' + esc(audLabels[aud.filter] || aud.filter) + '</strong>'
                + (scheduled ? ' · Scheduled for <strong>' + esc(whenInput.value.replace('T', ' ')) + '</strong>' : '');
            var confirmBtn = document.getElementById('bpConfirm');
            confirmBtn.innerHTML = scheduled
                ? '<i class="bi bi-clock me-1"></i>Schedule for ' + list.length + ' contact(s)'
                : '<i class="bi bi-send me-1"></i>Send now to ' + list.length + ' contact(s)';
            pending = { t: t, aud: aud, list: list, scheduled: scheduled, when: whenInput.value };
            openPreview();
        }).catch(function (err) {
            statusEl.textContent = 'Could not load recipients: ' + ((err && err.message) ? err.message : 'request blocked')
                + ' — check DevTools ▸ Network ▸ wa_api.php.';
        });
    });

    // Step 2 — the user has SEEN the recipients and confirmed. Now send (or schedule).
    document.getElementById('bpConfirm').addEventListener('click', function () {
        if (!pending) return;
        var p = pending; pending = null;
        closePreview();

        if (p.scheduled) {
            statusEl.textContent = 'Scheduling…';
            post('broadcast_schedule', {
                template: p.t.name, lang: p.t.language, audience: p.aud.filter, course_id: p.aud.course_id,
                vars: JSON.stringify(getVars()), when: p.when
            }).then(function (d) {
                statusEl.innerHTML = (d && d.ok)
                    ? 'Scheduled for ' + d.when + '. <a href="wa_broadcasts.php">View scheduled →</a>'
                    : 'Could not schedule: ' + ((d && d.error) || 'error');
            });
            return;
        }
        statusEl.textContent = 'Starting…';
        post('broadcast_create', {
            template: p.t.name, lang: p.t.language, audience: p.aud.filter, course_id: p.aud.course_id, total: p.list.length
        }).then(function (c) {
            runBroadcast(p.t, getVars(), p.list, (c && c.ok) ? c.broadcast_id : 0);
        });
    });

    function runBroadcast(t, vars, list, bid) {
        sendBtn.disabled = true; statusEl.textContent = '';
        document.getElementById('bProgWrap').classList.remove('d-none');
        var prog = document.getElementById('bProg'), result = document.getElementById('bResult');
        var CHUNK = 25, i = 0, sent = 0, failed = 0, lastErr = '';
        function step() {
            if (i >= list.length) {
                prog.style.width = '100%'; prog.textContent = '100%';
                result.innerHTML = 'Done — sent ' + sent + ', failed ' + failed + '.'
                    + (failed && lastErr ? ' <span class="text-danger">Reason: ' + esc(lastErr) + '</span>' : '')
                    + ' <a href="wa_broadcasts.php">View delivery report →</a>';
                sendBtn.disabled = false; return;
            }
            var chunk = list.slice(i, i + CHUNK);
            post('broadcast_send', {
                template: t.name, lang: t.language, broadcast_id: bid || 0,
                vars: JSON.stringify(vars), wa_ids: JSON.stringify(chunk)
            }).then(function (d) {
                if (d && d.ok) { sent += d.sent; failed += d.failed; if (d.error) lastErr = d.error; }
                else { failed += chunk.length; }
                i += CHUNK;
                var pct = Math.round(i / list.length * 100); if (pct > 100) pct = 100;
                prog.style.width = pct + '%'; prog.textContent = pct + '%';
                result.textContent = 'Sent ' + sent + ', failed ' + failed + ' of ' + list.length + '…';
                step();
            }).catch(function () { failed += chunk.length; i += CHUNK; step(); });
        }
        step();
    }
})();
</script>

<?php require_once 'footer.php'; ?>
