<?php
/**
 * Standalone AI sandbox / testing console. NO LOGIN.
 *
 *   wa_test_console.php                      -> pick a course/event
 *   wa_test_console.php?ref=course:12        -> view/edit its knowledge + chat
 *
 * Connected straight to the DB (like the webhook, via wa_db.php — no auth.php),
 * so anyone with the URL can: pick a course/event, preview or build its
 * knowledge base with the same 8-section template + AI draft generator, then
 * chat with the AI exactly as it would answer a real WhatsApp thread — using
 * the same prompt and logic (wa_ai_simulate). NOTHING is sent or stored as a
 * message; only Save writes the knowledge base.
 *
 * This is a testing tool. To lock it down, add to includes/wa_config.php:
 *     define('WA_SANDBOX_KEY', 'some-secret');
 * then open it as wa_test_console.php?key=some-secret . Remove the file (or set
 * the key) before going live.
 */
require_once __DIR__ . '/includes/wa_db.php';        // $wa_conn (no session/auth)
require_once __DIR__ . '/includes/wa_functions.php';

$conn = $wa_conn;

// Keep AJAX responses valid JSON even if the server has display_errors on —
// a stray notice printed before the JSON would otherwise break the chat.
@ini_set('display_errors', '0');

// Optional shared-secret gate (off unless WA_SANDBOX_KEY is defined in config).
if (defined('WA_SANDBOX_KEY') && WA_SANDBOX_KEY !== ''
    && !hash_equals(WA_SANDBOX_KEY, (string)($_GET['key'] ?? $_POST['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden — this testing console is protected. Append ?key=… to the URL.');
}
$keyQS = (defined('WA_SANDBOX_KEY') && WA_SANDBOX_KEY !== '')
    ? '&key=' . urlencode((string)($_GET['key'] ?? '')) : '';

// ---------------------------------------------------------------------------
// AJAX / POST actions (return JSON, then exit)
// ---------------------------------------------------------------------------
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $rt  = in_array($_POST['ref_type'] ?? '', ['event', 'program'], true) ? $_POST['ref_type'] : 'course';
    $rid = (int)($_POST['ref_id'] ?? 0);

    if ($action === 'chat') {
        @set_time_limit(200);
        try {
            $history = json_decode((string)($_POST['history'] ?? '[]'), true) ?: [];
            $kb = array_key_exists('kb', $_POST) ? (string)$_POST['kb'] : null;
            echo json_encode(wa_ai_simulate($conn, $rt, $rid, $history, $kb));
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'exception: ' . $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'kb_save') {
        @set_time_limit(200);   // saving reprocesses body_ai via the AI — needs headroom
        if ($rid <= 0) { echo json_encode(['ok' => false, 'error' => 'no_ref']); exit; }
        wa_knowledge_set($conn, $rt, $rid, (string)($_POST['body'] ?? ''));
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'kb_command') {
        @set_time_limit(200);
        try {
            echo json_encode(wa_kb_command($conn, $rt, $rid, (string)($_POST['kb'] ?? ''), (string)($_POST['command'] ?? '')));
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'exception: ' . $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'kb_chat') {
        @set_time_limit(200);
        try {
            $hist = json_decode((string)($_POST['history'] ?? '[]'), true) ?: [];
            $img  = wa_image_from_dataurl($_POST['image'] ?? '');
            echo json_encode(wa_kb_chat($conn, $rt, $rid, $hist, $img));
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'exception: ' . $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'kb_generate') {
        @set_time_limit(200);
        $raw  = (string)($_POST['urls'] ?? '');
        $urls = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        echo json_encode(wa_kb_generate($conn, $rt, $rid, $urls));
        exit;
    }
    // Guided-enrollment sandbox: drive the real capture state machine, but
    // capture the bot's replies (nothing sent) and dry-run finalize (nothing
    // written to ticket_congress/register, no email/Moodle).
    if ($action === 'enroll') {
        @set_time_limit(200);
        if ($rid <= 0) { echo json_encode(['ok' => false, 'error' => 'no_ref']); exit; }
        $waId = 'SANDBOX_' . strtoupper(substr($rt, 0, 1)) . $rid;   // stable per course/event
        $contactId = wa_upsert_contact($conn, $waId, 'Sandbox Tester');
        $GLOBALS['WA_CAPTURE']     = [];
        $GLOBALS['WA_ENROLL_DRY']  = true;
        $reset = !empty($_POST['reset']);
        $msg   = trim((string)($_POST['message'] ?? ''));
        if ($reset) {
            mysqli_query($conn, "DELETE FROM wa_enroll_sessions WHERE contact_id = " . (int)$contactId);
            wa_enroll_start($conn, $contactId, $waId, $rt, $rid);
        } else {
            if (!wa_enroll_active($conn, $contactId)) { wa_enroll_start($conn, $contactId, $waId, $rt, $rid); }
            if ($msg !== '') { wa_enroll_handle($conn, $contactId, $waId, $msg); }
        }
        $done = wa_enroll_active($conn, $contactId) === null;
        echo json_encode(['ok' => true, 'messages' => $GLOBALS['WA_CAPTURE'], 'done' => $done]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'unknown_action']);
    exit;
}

// ---------------------------------------------------------------------------
// Page render
// ---------------------------------------------------------------------------
$selType = null; $selId = 0;
$ref = $_GET['ref'] ?? '';
if (strpos($ref, ':') !== false) {
    list($t, $i) = explode(':', $ref, 2);
    $selType = in_array($t, ['event', 'program'], true) ? $t : 'course';
    $selId   = (int)$i;
}

$courses  = wa_active_courses($conn);
$events   = wa_active_events($conn);
$programs = wa_programs_list($conn, true);
$kbBody  = ($selType && $selId) ? wa_knowledge_get($conn, $selType, $selId) : '';
$selName = ($selType && $selId) ? wa_ref_name($conn, $selType, $selId) : '';
$providerReady = wa_provider_ready(wa_active_provider($conn));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Sandbox — Vantage</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    body { background:#eef1f6; }
    .chat-body { height:60vh; overflow-y:auto; background:#f3f5f9; }
    .bubble { max-width:80%; }
    .bubble .stamp { font-size:11px; }
</style>
</head>
<body>
<div class="container-fluid py-4" style="max-width:1200px">

    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <h4 class="mb-1"><i class="bi bi-robot me-2"></i>AI Sandbox</h4>
            <p class="text-muted mb-0">Pick a course, event or training programme, build its knowledge, then chat with the AI. Nothing is sent — this is a test bench.</p>
        </div>
    </div>
    <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>Testing tool — no login, no real WhatsApp messages. Knowledge you <strong>Save</strong> is written to the live database.</div>

    <div class="mb-3" style="max-width:560px">
        <label class="form-label small text-muted">Choose a course, event or training programme</label>
        <select class="form-select" onchange="if(this.value){location.href='wa_test_console.php?ref='+this.value+'<?php echo $keyQS; ?>';}">
            <option value="">— Select —</option>
            <?php if ($programs): ?>
            <optgroup label="Training programmes">
                <?php foreach ($programs as $p): $v = 'program:' . (int)$p['id']; ?>
                    <option value="<?php echo $v; ?>" <?php echo ($selType === 'program' && $selId === (int)$p['id']) ? 'selected' : ''; ?>><?php echo wa_e($p['name']); ?></option>
                <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <optgroup label="Courses">
                <?php foreach ($courses as $c): $v = 'course:' . (int)$c['id']; ?>
                    <option value="<?php echo $v; ?>" <?php echo ($selType === 'course' && $selId === (int)$c['id']) ? 'selected' : ''; ?>><?php echo wa_e($c['name']); ?></option>
                <?php endforeach; ?>
            </optgroup>
            <?php if ($events): ?>
            <optgroup label="Events">
                <?php foreach ($events as $ev): $v = 'event:' . (int)$ev['id']; ?>
                    <option value="<?php echo $v; ?>" <?php echo ($selType === 'event' && $selId === (int)$ev['id']) ? 'selected' : ''; ?>><?php echo wa_e($ev['name']); ?></option>
                <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
        </select>
    </div>

    <?php if (!$providerReady): ?>
        <div class="alert alert-danger">No AI provider key is configured in <code>wa_config.php</code>, so the AI can't reply. Set it, then reload.</div>
    <?php endif; ?>

    <?php if ($selType && $selId): ?>
    <div class="row g-3">
        <!-- Knowledge base -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-journal-text me-1"></i>Knowledge — <?php echo wa_e($selName); ?></span>
                    <button type="button" id="kbTemplate" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-check me-1"></i>Insert template</button>
                </div>
                <div class="card-body">
                    <?php if (!$kbBody): ?>
                        <div class="alert alert-info py-2 small">No knowledge base yet for this one. Insert the template (or generate from your website), fill it in, then Save.</div>
                    <?php endif; ?>
                    <?php if ($providerReady): ?>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" id="kbUrls" class="form-control" placeholder="Or bootstrap from your website — course page URL(s), blank auto-searches">
                        <button type="button" id="kbGen" class="btn btn-outline-primary"><i class="bi bi-magic me-1"></i>Generate</button>
                    </div>
                    <div id="kbGenStatus" class="small text-muted mb-2"></div>
                    <?php endif; ?>
                    <textarea id="kbBody" class="form-control" rows="18" style="font-family:inherit; font-size:13px"><?php echo wa_e($kbBody); ?></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted">Edit directly and click Save. Sections 7 (Escalation) &amp; 8 (Do-Not-Say) are rules the AI follows, never reads aloud.</small>
                        <button type="button" id="kbSave" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save</button>
                    </div>
                    <div id="kbSaveMsg" class="small mt-1"></div>
                </div>
            </div>
        </div>

        <!-- Chat simulator -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div class="btn-group btn-group-sm" role="group" aria-label="mode">
                        <button type="button" id="modeAi" class="btn btn-success"><i class="bi bi-robot me-1"></i>AI chat</button>
                        <?php if ($selType !== 'program'): ?>
                        <button type="button" id="modeEnroll" class="btn btn-outline-success"><i class="bi bi-clipboard-check me-1"></i>Enrollment</button>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="chatReset" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                </div>
                <div class="card-body chat-body" id="chatBody">
                    <div class="text-muted small text-center mt-4" id="chatEmpty">Type a message below as if you were a prospect.<br>The AI answers from the knowledge on the left (including unsaved edits).</div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex gap-2 align-items-end">
                        <textarea id="chatInput" class="form-control" rows="2" placeholder="e.g. Hi, I'm interested in this programme"></textarea>
                        <button type="button" id="chatSend" class="btn btn-success px-3"><i class="bi bi-send"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="text-muted py-5 text-center"><i class="bi bi-arrow-up-circle fs-3 d-block mb-2"></i>Pick a course, event or programme above to begin.</div>
    <?php endif; ?>
</div>

<script>
var REF_TYPE = <?php echo json_encode($selType ?? ''); ?>;
var REF_ID   = <?php echo (int)$selId; ?>;

/* ---- 8-section knowledge template (identical to the Knowledge page) ---- */
var TEMPLATE = [
'=== 1. COURSE BASICS ===','Full name (and nicknames): ','One-line description: ',
'One-line OUTCOME hook (the concrete result they walk away with — the AI leads with this): ',
'Who it\'s for: ','Format (online/onsite/hybrid): ','Duration & schedule (start dates, session times, length): ',
'Language of instruction: ','',
'=== 2. PRICING & PAYMENT ===','Full fee (and currency): ','Deposit to secure a place (amount): ',
'Installment terms (balance paid in installments, completed before the course ends): ',
'Discounts / scholarships / early-bird / group rates: ','Payment methods (M-Pesa, bank, card): ',
'Refund policy: ','Fee includes / excludes: ','',
'=== 3. ENROLLMENT & LOGISTICS ===','How to register (exact steps): ','Documents / prerequisites: ',
'Application deadline / rolling admission: ','What happens after payment: ',
'For onsite — venue, address, directions, parking, what to bring: ','',
'=== 4. CONTENT & OUTCOMES ===','Topics / modules covered: ','What a participant achieves by the end: ',
'Certificate / accreditation (recognised by whom?): ','Instructor(s) and credentials: ','Sample materials / syllabus link: ','',
'=== 5. FREQUENTLY ASKED QUESTIONS (aim for 15-20) ===','Q: ','A: ','','Q: ','A: ','',
'=== 6. OBJECTIONS & HESITATIONS ===','Too expensive -> ','Not sure it\'s relevant to me -> ','No time -> ','Need to ask my employer/spouse -> ','',
'=== 7. ESCALATION (rules for the AI — never shown to the prospect) ===','Questions the AI must NOT answer / must escalate: ',
'Hand off to a human immediately when: (complaints, refund disputes, custom corporate deals, ...) ','Sensitive / easily-misstated topics: ','',
'=== 8. TONE & DO-NOT-SAY (rules for the AI) ===','Preferred greeting & sign-off: ',
'Always-use words / correct brand & course names: ',
'Never say / never promise (guarantees, medical/legal claims, competitor comparisons): ',''
].join('\n');

function post(data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch('wa_test_console.php', { method:'POST', body:fd }).then(function (r) {
        // Read as text first so a server error page becomes a visible message
        // instead of a silent JSON-parse failure.
        return r.text().then(function (t) {
            try { return JSON.parse(t); }
            catch (e) { return { ok:false, error:'server said: ' + t.slice(0, 400) }; }
        });
    });
}
function esc(s) { var d = document.createElement('div'); d.textContent = (s==null?'':s); return d.innerHTML; }

/* ---- Knowledge editor ---- */
var kbBox = document.getElementById('kbBody');

/* ---- Section index: click a labelled heading to jump to it in the preview ---- */
function kbHeadings(text) {
    var out = [], lines = (text || '').split('\n');
    for (var i = 0; i < lines.length; i++) {
        var s = lines[i].trim(), label = null;
        if (/^={2,}.*={2,}$/.test(s)) label = s.replace(/^=+|=+$/g, '').trim();
        else if (/^#{1,6}\s+\S/.test(s)) label = s.replace(/^#+\s*/, '').trim();
        if (label) out.push(label);
    }
    return out;
}
function scrollToText(needle) {
    if (!needle || !kbBox) return;
    var val = kbBox.value, low = val.toLowerCase(), n = String(needle).toLowerCase().replace(/^[=#\s]+|[=#\s]+$/g, '');
    var pos = low.indexOf(n);
    if (pos < 0) {
        var words = n.replace(/[^a-z0-9 ]/g, ' ').split(/\s+/).filter(function (w) { return w.length > 3; });
        for (var i = 0; i < words.length && pos < 0; i++) pos = low.indexOf(words[i]);
    }
    if (pos < 0) return;
    var lineStart = val.lastIndexOf('\n', pos - 1) + 1;
    var lineEnd = val.indexOf('\n', pos); if (lineEnd < 0) lineEnd = val.length;
    var lh = parseFloat(getComputedStyle(kbBox).lineHeight) || 18;
    var lineNo = val.slice(0, pos).split('\n').length - 1;
    try { kbBox.focus({ preventScroll: true }); kbBox.setSelectionRange(lineStart, lineEnd); } catch (e) {}
    kbBox.scrollTop = Math.max(0, lineNo * lh - 24);
}
function renderSections() {
    var box = document.getElementById('kbSections'); if (!box || !kbBox) return;
    var hs = kbHeadings(kbBox.value);
    box.innerHTML = hs.length ? '<span class="small text-muted me-1 align-self-center">Jump to:</span>' : '';
    hs.forEach(function (h) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'btn btn-sm btn-outline-secondary py-0 px-1'; b.style.fontSize = '11px';
        b.textContent = h; b.addEventListener('click', function () { scrollToText(h); });
        box.appendChild(b);
    });
}
renderSections();

var tplBtn = document.getElementById('kbTemplate');
if (tplBtn) tplBtn.addEventListener('click', function () {
    if (kbBox.value.trim() !== '' && !confirm('Replace the current text with the blank template?')) return;
    kbBox.value = TEMPLATE; kbBox.focus();
});
var saveBtn = document.getElementById('kbSave');
if (saveBtn) saveBtn.addEventListener('click', function () {
    var msg = document.getElementById('kbSaveMsg');
    saveBtn.disabled = true; msg.textContent = 'Saving…'; msg.className = 'small mt-1 text-muted';
    post({ action:'kb_save', ref_type:REF_TYPE, ref_id:REF_ID, body:kbBox.value })
        .then(function (d) {
            saveBtn.disabled = false;
            if (d && d.ok) { msg.textContent = 'Saved.'; msg.className = 'small mt-1 text-success'; }
            else { msg.textContent = 'Could not save.'; msg.className = 'small mt-1 text-danger'; }
        }).catch(function () { saveBtn.disabled = false; msg.textContent = 'Request failed.'; msg.className = 'small mt-1 text-danger'; });
});
var genBtn = document.getElementById('kbGen');
if (genBtn) genBtn.addEventListener('click', function () {
    var st = document.getElementById('kbGenStatus');
    genBtn.disabled = true; st.textContent = 'Searching your website & drafting… (~10s)';
    post({ action:'kb_generate', ref_type:REF_TYPE, ref_id:REF_ID, urls:document.getElementById('kbUrls').value })
        .then(function (d) {
            genBtn.disabled = false;
            if (d && d.ok) {
                kbBox.value = d.text;
                post({ action:'kb_save', ref_type:REF_TYPE, ref_id:REF_ID, body:kbBox.value })
                    .then(function (s) { st.textContent = (s && s.ok) ? 'Draft generated & saved.' : 'Draft ready — saving failed, try again.'; });
            }
            else {
                var m = { no_content:'Could not read any page — paste the course URL.', no_provider:'No AI key.', ai_failed:'AI request failed.' }[d && d.error] || (d && d.error) || 'error';
                st.textContent = 'Could not generate: ' + m;
            }
        }).catch(function () { genBtn.disabled = false; st.textContent = 'Request failed.'; });
});

/* ---- Chat simulator (mirrors a real thread; sends nothing) ---- */
/* NB: must NOT be named `history` — that collides with window.history. */
var chatLog = [];
var chatMode = 'ai';           // 'ai' (wa_ai_simulate) | 'enroll' (guided capture)
var body = document.getElementById('chatBody');
var input = document.getElementById('chatInput');
var sendBtn = document.getElementById('chatSend');

function stamp() { var d = new Date(); return d.toTimeString().slice(0,5); }
function addBubble(role, text, escalate) {
    var empty = document.getElementById('chatEmpty'); if (empty) empty.remove();
    var out = role === 'assistant';
    var wrap = document.createElement('div');
    wrap.className = 'd-flex mb-2 ' + (out ? 'justify-content-start' : 'justify-content-end');
    var badge = (out && escalate) ? ' <span class="badge bg-warning text-dark">would escalate to a human</span>' : '';
    wrap.innerHTML = '<div class="bubble p-2 px-3 rounded-3 ' + (out ? 'bg-white border' : 'bg-success text-white') + '">'
        + '<div style="white-space:pre-wrap;word-wrap:break-word">' + esc(text).replace(/\n/g,'<br>') + '</div>'
        + '<div class="stamp ' + (out ? 'text-muted' : 'text-white-50') + '">' + (out ? 'AI' : 'You') + ' · ' + stamp() + badge + '</div>'
        + '</div>';
    body.appendChild(wrap); body.scrollTop = body.scrollHeight;
}
function thinking(on) {
    var t = document.getElementById('chatThinking');
    if (on && !t) {
        t = document.createElement('div'); t.id = 'chatThinking';
        t.className = 'd-flex mb-2 justify-content-start';
        t.innerHTML = '<div class="bubble p-2 px-3 rounded-3 bg-white border text-muted">'
            + '<span class="spinner-border spinner-border-sm" style="width:.9rem;height:.9rem"></span> AI is typing…</div>';
        body.appendChild(t); body.scrollTop = body.scrollHeight;
    } else if (!on && t) { t.remove(); }
}
function send() {
    if (chatMode === 'enroll') { return enrollSend(); }
    var text = input.value.trim(); if (!text) return;
    addBubble('user', text); chatLog.push({ role:'user', content:text });
    input.value = ''; sendBtn.disabled = true; thinking(true);
    post({ action:'chat', ref_type:REF_TYPE, ref_id:REF_ID, kb:kbBox.value, history:JSON.stringify(chatLog) })
        .then(function (d) {
            thinking(false); sendBtn.disabled = false;
            if (d && d.ok) { addBubble('assistant', d.reply, d.escalate); chatLog.push({ role:'assistant', content:d.reply }); }
            else {
                var m = { no_provider:'No AI key configured.', ai_failed:'AI request failed.', no_history:'Say something first.', empty_reply:'AI returned nothing.' }[d && d.error] || (d && d.error) || 'error';
                addBubble('assistant', '⚠️ ' + m, false);
            }
            input.focus();
        }).catch(function () { thinking(false); sendBtn.disabled = false; addBubble('assistant', '⚠️ Request failed.', false); });
}
function clearChat(msg) {
    body.innerHTML = '<div class="text-muted small text-center mt-4" id="chatEmpty">' + esc(msg) + '</div>';
}
function renderEnroll(d) {
    thinking(false); sendBtn.disabled = false;
    if (d && d.ok && Array.isArray(d.messages)) {
        d.messages.forEach(function (m) { addBubble('assistant', m, false); });
        if (d.done) { addBubble('assistant', '— test complete. Click Reset to run it again. —', false); }
    } else {
        var m = { no_ref:'Pick a course/event first.' }[d && d.error] || (d && d.error) || 'error';
        addBubble('assistant', '⚠️ ' + m, false);
    }
    input.focus();
}
function enrollStart() {
    clearChat('Starting a test registration…');
    thinking(true); sendBtn.disabled = true;
    post({ action:'enroll', ref_type:REF_TYPE, ref_id:REF_ID, reset:1 }).then(renderEnroll)
        .catch(function () { thinking(false); sendBtn.disabled = false; addBubble('assistant', '⚠️ Request failed.', false); });
}
function enrollSend() {
    var text = input.value.trim(); if (!text) return;
    addBubble('user', text); input.value = ''; sendBtn.disabled = true; thinking(true);
    post({ action:'enroll', ref_type:REF_TYPE, ref_id:REF_ID, message:text }).then(renderEnroll)
        .catch(function () { thinking(false); sendBtn.disabled = false; addBubble('assistant', '⚠️ Request failed.', false); });
}
function setMode(mode) {
    chatMode = mode;
    document.getElementById('modeAi').className = 'btn ' + (mode === 'ai' ? 'btn-success' : 'btn-outline-success');
    var me = document.getElementById('modeEnroll');
    if (me) { me.className = 'btn ' + (mode === 'enroll' ? 'btn-success' : 'btn-outline-success'); }
    input.placeholder = (mode === 'enroll')
        ? 'Reply with all details in one message (name, email, phone, country, org, title), then "yes"'
        : "e.g. Hi, I'm interested in this programme";
    if (mode === 'enroll') { enrollStart(); }
    else { chatLog = []; clearChat('AI chat. Type a message as a prospect.'); }
}
if (sendBtn) {
    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });
    document.getElementById('chatReset').addEventListener('click', function () {
        if (chatMode === 'enroll') { enrollStart(); }
        else { chatLog = []; clearChat('Chat reset. Type a message to start again.'); }
    });
    document.getElementById('modeAi').addEventListener('click', function () { setMode('ai'); });
    var meBtn = document.getElementById('modeEnroll');
    if (meBtn) { meBtn.addEventListener('click', function () { setMode('enroll'); }); }
}
</script>
</body>
</html>
