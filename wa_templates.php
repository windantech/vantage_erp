<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';

if (!in_array(777, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Supervisors only.</div></div>';
    require_once 'footer.php';
    exit;
}

$flash = isset($_SESSION['wa_flash']) ? $_SESSION['wa_flash'] : null;
unset($_SESSION['wa_flash']);
wa_templates_autosync($conn);              // keep local records current with the Hub (throttled)
$templates     = wa_templates_list($conn);
$providerReady = wa_provider_ready(wa_active_provider($conn));
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-file-earmark-text me-2"></i>Message Templates</h4>
                    <p class="text-muted mb-0">Draft the copy with AI, create it in your 360dialog Hub, then record it here so you can broadcast it.</p>
                </div>
                <div class="d-flex gap-2">
                    <form method="post" action="includes/wa_process.php" class="d-inline">
                        <input type="hidden" name="action" value="sync_templates">
                        <button type="submit" class="btn btn-outline-success"><i class="bi bi-arrow-repeat me-1"></i>Sync from Meta</button>
                    </form>
                    <a href="wa_broadcast.php" class="btn btn-outline-primary"><i class="bi bi-megaphone me-1"></i>Broadcast</a>
                    <a href="wa_inbox.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inbox</a>
                </div>
            </div>

            <?php if ($flash): ?><div class="alert alert-<?php echo wa_e($flash[0]); ?>"><?php echo wa_e($flash[1]); ?></div><?php endif; ?>

            <div class="alert alert-info small">
                <i class="bi bi-info-circle me-1"></i><strong>How template approval works:</strong>
                templates are created &amp; submitted to Meta inside your
                <a href="https://hub.360dialog.com" target="_blank">360dialog Hub</a> (your messaging API key can't do this).
                Use the AI drafter below to write the copy, paste it into the Hub to submit — this page
                <strong>auto-syncs from Meta</strong> (every few minutes) so it and its approval status appear here and in
                Broadcast automatically. Hit <strong>Sync from Meta</strong> to refresh right now. You can still record one
                by hand below; keep the <strong>name &amp; language identical</strong> to the Hub.
            </div>

            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-uppercase">Draft &amp; record</h6></div>
                        <div class="card-body">
                            <!-- AI draft -->
                            <div class="border rounded p-3 mb-3 bg-light">
                                <div class="fw-semibold mb-1"><i class="bi bi-magic me-1"></i>Draft with AI</div>
                                <?php if ($providerReady): ?>
                                    <textarea id="tDesc" class="form-control mb-2" rows="2" placeholder="Describe it, e.g. 'Remind a lead the Senior Management intake opens next week and invite them to reserve a spot.'"></textarea>
                                    <button type="button" id="tGen" class="btn btn-outline-primary btn-sm"><i class="bi bi-magic me-1"></i>Generate draft</button>
                                    <span id="tGenStatus" class="small text-muted ms-2"></span>
                                <?php else: ?>
                                    <p class="small text-warning mb-0">Set an AI key on <a href="wa_settings.php">Settings</a> to use AI drafting.</p>
                                <?php endif; ?>
                            </div>

                            <form method="post" action="includes/wa_process.php">
                                <input type="hidden" name="action" value="save_template">
                                <div class="mb-2">
                                    <label class="form-label small text-muted">Template name (must match the Hub — lowercase, no spaces)</label>
                                    <input type="text" id="tName" name="name" class="form-control" placeholder="intake_reminder" required>
                                </div>
                                <div class="row g-2">
                                    <div class="col-4 mb-2">
                                        <label class="form-label small text-muted">Language</label>
                                        <input type="text" name="language" class="form-control" value="en" placeholder="en">
                                    </div>
                                    <div class="col-4 mb-2">
                                        <label class="form-label small text-muted">Category</label>
                                        <select id="tCategory" name="category" class="form-select">
                                            <option value="marketing">Marketing</option>
                                            <option value="utility">Utility</option>
                                            <option value="authentication">Auth</option>
                                        </select>
                                    </div>
                                    <div class="col-4 mb-2">
                                        <label class="form-label small text-muted">Status (from Hub)</label>
                                        <select name="status" class="form-select">
                                            <option value="pending">Pending</option>
                                            <option value="approved">Approved</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Body (with {{1}} variables) — copy this into the Hub</label>
                                    <textarea id="tBody" name="body" class="form-control" rows="5" required placeholder="Hi {{1}}, our next intake for {{2}} opens soon. Reply to reserve your spot."></textarea>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-save me-1"></i>Record locally</button>
                                    <button type="submit" class="btn btn-success"
                                            onclick="this.form.querySelector('[name=action]').value='submit_template';">
                                        <i class="bi bi-send me-1"></i>Submit to Meta
                                    </button>
                                </div>
                                <p class="text-muted small mt-2 mb-0"><strong>Submit to Meta</strong> creates the template on WhatsApp for review. Only <strong>Approved</strong> templates can be sent. (Variable examples are added automatically.)</p>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-uppercase">Recorded templates</h6></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="bg-light"><tr><th>Name</th><th>Lang</th><th>Vars</th><th>Status</th><th>Body</th><th></th></tr></thead>
                                    <tbody>
                                        <?php if ($templates): foreach ($templates as $t):
                                            $st = $t['status'];
                                            $cls = $st === 'approved' ? 'bg-success' : ($st === 'rejected' ? 'bg-danger' : 'bg-warning text-dark');
                                        ?>
                                        <tr>
                                            <td><strong><?php echo wa_e($t['name']); ?></strong></td>
                                            <td><?php echo wa_e($t['language']); ?></td>
                                            <td><?php echo wa_template_var_count($t['body']); ?></td>
                                            <td><span class="badge <?php echo $cls; ?>"><?php echo wa_e($st); ?></span></td>
                                            <td><small class="text-muted"><?php echo wa_e(mb_strimwidth((string)$t['body'], 0, 55, '…')); ?></small></td>
                                            <td>
                                                <form method="post" action="includes/wa_process.php" onsubmit="return confirm('Delete this local record?');">
                                                    <input type="hidden" name="action" value="delete_template">
                                                    <input type="hidden" name="name" value="<?php echo wa_e($t['name']); ?>">
                                                    <input type="hidden" name="language" value="<?php echo wa_e($t['language']); ?>">
                                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No templates recorded yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($providerReady): ?>
<script>
(function () {
    var btn = document.getElementById('tGen');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var st = document.getElementById('tGenStatus');
        var fd = new FormData();
        fd.append('action', 'template_draft');
        fd.append('desc', document.getElementById('tDesc').value);
        btn.disabled = true; st.textContent = 'Drafting…';
        fetch('includes/wa_api.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                btn.disabled = false;
                if (d && d.ok) {
                    if (d.body) document.getElementById('tBody').value = d.body;
                    if (d.name && !document.getElementById('tName').value) document.getElementById('tName').value = d.name;
                    if (d.category) {
                        var sel = document.getElementById('tCategory');
                        for (var i = 0; i < sel.options.length; i++) if (sel.options[i].value === d.category.toLowerCase()) sel.selectedIndex = i;
                    }
                    st.textContent = 'Draft ready — paste into the Hub, then record it here.';
                } else {
                    st.textContent = 'Could not draft: ' + ((d && d.error) || 'error');
                }
            })
            .catch(function () { btn.disabled = false; st.textContent = 'Request failed.'; });
    });
})();
</script>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
