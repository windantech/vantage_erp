<?php
/**
 * corporate_proposal_settings.php
 * Integration settings for the website's corporate-proposal receiver
 * (includes/receive_corporate_proposal.php).
 *
 * The shared secret is GENERATED IN PHP and stored automatically in app_settings — no
 * terminal, no openssl, no SQL. This page just reveals it (with a Copy button) so it can be
 * handed to the frontend once, and lets you rotate it with one click.
 */
require_once 'header.php';   // provides $conn, $role, session + chrome

// Only corporate managers / admins may view or rotate the secret.
if (!in_array(88, $role) && !in_array(55, $role) && !in_array(777, $role)) {
    header('Location: ./');
    exit;
}

/** Ensure the settings table exists, returning the current secret (auto-creating one if unset). */
function cps_ensure_secret($conn, $force_new = false)
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS `app_settings` (
            `setting_key` VARCHAR(120) NOT NULL,
            `setting_value` TEXT DEFAULT NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!$force_new) {
        $r = $conn->query("SELECT `setting_value` FROM `app_settings` WHERE `setting_key` = 'corporate_proposal_secret' LIMIT 1");
        if ($r && ($row = $r->fetch_assoc()) && trim((string) $row['setting_value']) !== '') {
            return $row['setting_value'];
        }
    }

    // Generate + store automatically (cryptographically strong, 64 hex chars).
    $secret = bin2hex(random_bytes(32));
    $stmt = $conn->prepare(
        "INSERT INTO `app_settings` (`setting_key`, `setting_value`)
         VALUES ('corporate_proposal_secret', ?)
         ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)"
    );
    $stmt->bind_param('s', $secret);
    $stmt->execute();
    $stmt->close();
    return $secret;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regenerate') {
    cps_ensure_secret($conn, true);
    $_SESSION['cps_msg'] = 'A new secret was generated. Update the frontend with the new value.';
    header('Location: corporate_proposal_settings.php');
    exit;
}

$secret = cps_ensure_secret($conn);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'vantageafricaleaders.com') . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$endpoint_url = $base . '/includes/receive_corporate_proposal.php';

$msg = $_SESSION['cps_msg'] ?? '';
unset($_SESSION['cps_msg']);
?>
<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        <div class="container-fluid mt-5 pt-5">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h4 class="mb-0"><i class="bi bi-shield-lock"></i> Corporate Proposal — Integration</h4>
                <a href="corporate_programs.php" class="btn btn-sm btn-outline-secondary rounded-0"><i class="bi bi-arrow-left"></i> Corporate Trainings</a>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-success rounded-0"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <div class="card rounded-0 mb-3">
                <div class="card-body">
                    <p class="text-muted mb-2">The website form posts to <code>corporate-proposal.php</code>, which forwards each submission to this receiver. The frontend authenticates with the shared secret below — it never reads the database.</p>

                    <label class="fw-bold small text-muted mt-2">Receiver endpoint (give to frontend)</label>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control rounded-0" id="endpointUrl" value="<?php echo htmlspecialchars($endpoint_url); ?>" readonly>
                        <button class="btn btn-outline-secondary rounded-0" type="button" onclick="cpsCopy('endpointUrl', this)">Copy</button>
                    </div>

                    <label class="fw-bold small text-muted">Shared secret header <code>X-Vantage-Proposal-Secret</code></label>
                    <div class="input-group mb-1">
                        <input type="password" class="form-control rounded-0" id="secretVal" value="<?php echo htmlspecialchars($secret); ?>" readonly>
                        <button class="btn btn-outline-secondary rounded-0" type="button" onclick="cpsReveal(this)">Show</button>
                        <button class="btn btn-outline-secondary rounded-0" type="button" onclick="cpsCopy('secretVal', this)">Copy</button>
                    </div>
                    <p class="text-muted small mb-3">Auto-generated and stored for you — no terminal or SQL needed. Hand this value to the frontend once; they send it on every forward.</p>

                    <form method="POST" onsubmit="return confirm('Generate a NEW secret? The frontend will be rejected until you update it with the new value.');">
                        <input type="hidden" name="action" value="regenerate">
                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-0"><i class="bi bi-arrow-repeat"></i> Regenerate secret</button>
                    </form>
                </div>
            </div>

            <div class="card rounded-0">
                <div class="card-body">
                    <h6 class="fw-bold">What the frontend sends</h6>
                    <p class="text-muted small mb-2">POST (form-encoded), header <code>X-Vantage-Proposal-Secret: &lt;secret&gt;</code>. Success is <code>200 {"success":true,"id":…,"reference":"CP-…"}</code>; anything else should be logged as a forward failure (the CEO email is already sent, so no lead is lost).</p>
                    <div class="row small">
                        <div class="col-md-6">
                            <b>Required</b>
                            <ul class="mb-0"><li>contact_name</li><li>contact_email</li><li>contact_phone</li><li>org_name</li><li>org_country</li><li>org_sector</li><li>participants_count</li><li>preferred_delivery</li></ul>
                        </div>
                        <div class="col-md-6">
                            <b>Optional</b>
                            <ul class="mb-0"><li>org_size</li><li>city</li><li>preferred_dates</li><li>budget_range</li><li>audience_profile</li><li>areas_of_interest <span class="text-muted">(JSON array string, or repeated <code>areas_of_interest[]</code>)</span></li><li>additional_notes</li></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function cpsReveal(btn){var f=document.getElementById('secretVal');if(f.type==='password'){f.type='text';btn.textContent='Hide';}else{f.type='password';btn.textContent='Show';}}
function cpsCopy(id,btn){var f=document.getElementById(id);var t=f.value;var done=function(){var o=btn.textContent;btn.textContent='Copied';setTimeout(function(){btn.textContent=o;},1200);};if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(done,function(){f.type='text';f.select();document.execCommand('copy');done();});}else{f.type='text';f.select();document.execCommand('copy');done();}}
</script>

<?php require_once 'footer.php'; ?>
