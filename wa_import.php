<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
// Never cache — the upload/map logic is inline JS; a stale copy runs old code.
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
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-upload me-2"></i>Import Contacts</h4>
                    <p class="text-muted mb-0">Upload a spreadsheet — AI detects the columns, you confirm, then it imports.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="wa_contacts.php" class="btn btn-outline-primary"><i class="bi bi-people me-1"></i>Contacts</a>
                    <a href="wa_broadcast.php" class="btn btn-outline-primary"><i class="bi bi-megaphone me-1"></i>Broadcast</a>
                    <a href="wa_inbox.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inbox</a>
                </div>
            </div>

            <div class="alert alert-info small">
                <i class="bi bi-info-circle me-1"></i>Export your Excel sheet as <strong>CSV (Comma delimited)</strong>
                (File → Save As → CSV) and upload it here. The first row should be the column headers.
                Only import people who <strong>agreed</strong> to receive WhatsApp messages.
            </div>

            <div class="card shadow-sm border-0" style="max-width:900px">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Contact file (.csv)</label>
                        <div class="d-flex gap-2">
                            <input type="file" id="iFile" class="form-control" accept=".csv,text/csv">
                            <button id="iAnalyze" class="btn btn-primary text-nowrap"><i class="bi bi-magic me-1"></i>Analyze</button>
                        </div>
                        <div id="iStatus" class="small text-muted mt-1"></div>
                    </div>

                    <div id="iMapWrap" class="d-none">
                        <hr>
                        <h6 class="fw-bold text-uppercase small">Column mapping <span id="iRowCount" class="text-muted fw-normal"></span></h6>
                        <p class="small text-muted">AI matched your columns to these fields. Adjust any that are wrong. <strong>Phone is required.</strong></p>
                        <div class="row g-2 mb-3">
                            <div class="col-md-3"><label class="form-label small text-muted">Phone / WhatsApp *</label><select id="mapPhone" class="form-select form-select-sm"></select></div>
                            <div class="col-md-3"><label class="form-label small text-muted">Name</label><select id="mapName" class="form-select form-select-sm"></select></div>
                            <div class="col-md-3"><label class="form-label small text-muted">Email</label><select id="mapEmail" class="form-select form-select-sm"></select></div>
                            <div class="col-md-3"><label class="form-label small text-muted">Country</label><select id="mapCountry" class="form-select form-select-sm"></select></div>
                        </div>

                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Default country code</label>
                                <input type="text" id="iCc" class="form-control form-control-sm" value="254">
                                <div class="small text-muted">For local numbers (e.g. 0712… → 254712…). International numbers are kept as-is.</div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="iOptIn" checked>
                                    <label class="form-check-label small" for="iOptIn">Mark these contacts as <strong>opted-in</strong> — I confirm they agreed to receive messages.</label>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mb-3" style="max-height:40vh">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="bg-light"><tr><th>Name</th><th>Phone → WhatsApp ID</th><th>Email</th></tr></thead>
                                <tbody id="iPreview"></tbody>
                            </table>
                        </div>

                        <button id="iImport" class="btn btn-success"><i class="bi bi-cloud-arrow-up me-1"></i>Import contacts</button>
                        <span id="iImportStatus" class="ms-2 small"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var fileEl = document.getElementById('iFile');
    var statusEl = document.getElementById('iStatus');
    var mapWrap = document.getElementById('iMapWrap');
    var headers = [];

    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }

    function fillSelect(sel, chosen, includeBlank) {
        sel.innerHTML = '';
        if (includeBlank) { var o = document.createElement('option'); o.value = ''; o.textContent = '— none —'; sel.appendChild(o); }
        headers.forEach(function (h) {
            var o = document.createElement('option'); o.value = h; o.textContent = h;
            if (h === chosen) o.selected = true;
            sel.appendChild(o);
        });
    }

    function normalizePhone(raw, cc) {
        var d = String(raw == null ? '' : raw).replace(/\D+/g, '');
        cc = String(cc || '254').replace(/\D+/g, '') || '254';
        if (!d) return '';
        if (d.indexOf('00') === 0) d = d.slice(2);
        if (d[0] === '0') d = cc + d.slice(1);
        else if (d.length <= 10 && d.indexOf(cc) !== 0) d = cc + d;
        return d;
    }

    var sampleRows = [];
    function renderPreview() {
        var pCol = document.getElementById('mapPhone').value,
            nCol = document.getElementById('mapName').value,
            eCol = document.getElementById('mapEmail').value,
            cc = document.getElementById('iCc').value;
        var html = '';
        sampleRows.forEach(function (r) {
            var wa = normalizePhone(r[pCol], cc);
            html += '<tr><td>' + esc(nCol ? r[nCol] : '') + '</td><td>' + esc(pCol ? r[pCol] : '')
                  + ' <span class="text-muted">→ ' + esc(wa || '—') + '</span></td><td>' + esc(eCol ? r[eCol] : '') + '</td></tr>';
        });
        document.getElementById('iPreview').innerHTML = html || '<tr><td colspan="3" class="text-muted">No rows.</td></tr>';
    }

    document.getElementById('iAnalyze').addEventListener('click', function () {
        var f = fileEl.files && fileEl.files[0];
        if (!f) { statusEl.textContent = 'Choose a CSV file first.'; return; }
        statusEl.textContent = 'Reading file and detecting columns with AI…';
        var fd = new FormData(); fd.append('action', 'import_analyze'); fd.append('file', f);
        fetch('includes/wa_api.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) { statusEl.textContent = 'Could not analyze: ' + ((d && d.error) || 'error'); return; }
                statusEl.textContent = '';
                headers = d.headers || [];
                sampleRows = d.sample || [];
                var m = d.map || {};
                fillSelect(document.getElementById('mapPhone'), m.phone, false);
                fillSelect(document.getElementById('mapName'), m.name, true);
                fillSelect(document.getElementById('mapEmail'), m.email, true);
                fillSelect(document.getElementById('mapCountry'), m.country, true);
                document.getElementById('iRowCount').textContent = '(' + (d.rowcount || 0) + ' rows found)';
                mapWrap.classList.remove('d-none');
                renderPreview();
            }).catch(function () { statusEl.textContent = 'Upload failed (network).'; });
    });

    ['mapPhone', 'mapName', 'mapEmail', 'iCc'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', renderPreview);
        document.getElementById(id).addEventListener('input', renderPreview);
    });

    document.getElementById('iImport').addEventListener('click', function () {
        var f = fileEl.files && fileEl.files[0];
        if (!f) { return; }
        if (!document.getElementById('mapPhone').value) { document.getElementById('iImportStatus').innerHTML = '<span class="text-danger">Pick the phone column.</span>'; return; }
        var map = {
            phone: document.getElementById('mapPhone').value,
            name: document.getElementById('mapName').value,
            email: document.getElementById('mapEmail').value,
            country: document.getElementById('mapCountry').value
        };
        var st = document.getElementById('iImportStatus');
        st.innerHTML = 'Importing… (large files take a minute)';
        document.getElementById('iImport').disabled = true;
        var fd = new FormData();
        fd.append('action', 'import_commit'); fd.append('file', f);
        fd.append('map', JSON.stringify(map));
        fd.append('country_code', document.getElementById('iCc').value || '254');
        fd.append('opt_in', document.getElementById('iOptIn').checked ? '1' : '0');
        fetch('includes/wa_api.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
            .then(function (d) {
                document.getElementById('iImport').disabled = false;
                if (!d || !d.ok) { st.innerHTML = '<span class="text-danger">Import failed: ' + esc((d && d.error) || 'error') + '</span>'; return; }
                st.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Done — '
                    + d.imported + ' new, ' + d.updated + ' updated, ' + d.bad + ' skipped (bad number), of ' + d.total + '.'
                    + ' <a href="wa_broadcast.php">Broadcast to them →</a></span>';
            }).catch(function () { document.getElementById('iImport').disabled = false; st.innerHTML = '<span class="text-danger">Import failed (network).</span>'; });
    });
})();
</script>

<?php require_once 'footer.php'; ?>
