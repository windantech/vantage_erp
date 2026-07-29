<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';

// Supervisors only (role 777).
if (!in_array(777, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Supervisors only.</div></div>';
    require_once 'footer.php';
    exit;
}

$flash = isset($_SESSION['wa_flash']) ? $_SESSION['wa_flash'] : null;
unset($_SESSION['wa_flash']);

$active    = wa_active_provider($conn);
$autoreply = wa_setting_get($conn, 'ai_autoreply', '0') === '1';
$enroll    = wa_setting_get($conn, 'enroll_enabled', '0') === '1';
$has = [
    'claude' => WA_ANTHROPIC_KEY && strpos(WA_ANTHROPIC_KEY, 'YOUR_') !== 0,
    'openai' => WA_OPENAI_KEY && strpos(WA_OPENAI_KEY, 'YOUR_') !== 0,
];
$model = ['claude' => WA_ANTHROPIC_MODEL, 'openai' => WA_OPENAI_MODEL];
$dialogReady = WA_DIALOG_KEY && WA_DIALOG_KEY !== '' && strpos(WA_DIALOG_KEY, 'YOUR_') !== 0;
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-gear me-2"></i>WhatsApp Settings</h4>
                    <p class="text-muted mb-0">AI provider and auto-reply — applies system-wide</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="wa_broadcast.php" class="btn btn-outline-primary"><i class="bi bi-megaphone me-1"></i>Broadcast</a>
                    <a href="wa_assignments.php" class="btn btn-outline-primary"><i class="bi bi-people me-1"></i>Assignments</a>
                    <a href="wa_knowledge.php" class="btn btn-outline-primary"><i class="bi bi-journal-text me-1"></i>Knowledge Base</a>
                    <a href="wa_inbox.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to inbox</a>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo wa_e($flash[0]); ?>"><?php echo wa_e($flash[1]); ?></div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-uppercase">Configuration</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge <?php echo $dialogReady ? 'bg-success' : 'bg-danger'; ?>">
                            <i class="bi bi-send me-1"></i>Sending (360dialog): <?php echo $dialogReady ? 'configured' : 'NOT configured'; ?>
                        </span>
                        <?php if (!$dialogReady): ?>
                            <div class="small text-danger mt-1">
                                Set a real <code>WA_DIALOG_KEY</code> in <code>includes/wa_config.php</code> —
                                until then <strong>no replies (AI or human) can be sent</strong>, even though messages are received.
                            </div>
                        <?php endif; ?>
                    </div>

                    <h6 class="fw-bold">AI Provider</h6>
                    <p class="text-muted">Choose which model powers AI replies and course inference.</p>
                    <form method="post" action="includes/wa_process.php">
                        <input type="hidden" name="action" value="save_provider">
                        <div class="row">
                            <?php foreach (['claude' => 'Claude (Anthropic)', 'openai' => 'OpenAI'] as $id => $label): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="card p-3 h-100 <?php echo $active===$id?'border-success':''; ?>" style="cursor:pointer">
                                        <div class="d-flex align-items-center">
                                            <input type="radio" name="provider" value="<?php echo $id; ?>" <?php echo $active===$id?'checked':''; ?> class="form-check-input me-2">
                                            <strong><?php echo wa_e($label); ?></strong>
                                            <?php if ($active===$id): ?><span class="badge bg-success ms-2">active</span><?php endif; ?>
                                        </div>
                                        <div class="text-muted small mt-1"><?php echo wa_e($model[$id]); ?></div>
                                        <div class="small mt-1 <?php echo $has[$id]?'text-success':'text-warning'; ?>">
                                            <?php echo $has[$id] ? '● API key configured' : '○ API key missing in wa_config.php'; ?>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save provider</button>
                    </form>

                    <hr class="my-4">

                    <h6 class="fw-bold">Auto-reply</h6>
                    <form method="post" action="includes/wa_process.php">
                        <input type="hidden" name="action" value="save_autoreply">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="ai_autoreply" id="ar" value="1" <?php echo $autoreply?'checked':''; ?>>
                            <label class="form-check-label" for="ar">
                                Auto-route new inbound messages to the right staff owner (and, once keys are set, let the AI answer).
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary mt-2"><i class="bi bi-check-lg me-1"></i>Save</button>
                        <p class="text-muted small mt-2">When off, the webhook only stores messages; no routing or replies are triggered.</p>
                    </form>

                    <hr class="my-4">

                    <h6 class="fw-bold">Enrollment</h6>
                    <form method="post" action="includes/wa_process.php">
                        <input type="hidden" name="action" value="save_enroll">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="enroll_enabled" id="enr" value="1" <?php echo $enroll?'checked':''; ?>>
                            <label class="form-check-label" for="enr">
                                When a contact says <em>register/enroll</em>, the bot shares the registration link and offers to walk them through it on WhatsApp — collecting their details, enrolling them and sending the admission letter/invoice.
                            </label>
                        </div>
                        <div class="mt-3">
                            <label for="regurl" class="form-label mb-1">Default registration link <span class="text-muted fw-normal">(fallback)</span></label>
                            <input type="text" class="form-control" name="register_url" id="regurl"
                                   value="<?php echo wa_e(wa_setting_get($conn, 'register_url', '')); ?>"
                                   placeholder="https://vantageafricaleaders.com/course-details.php?course_id={id}">
                            <p class="text-muted small mt-1 mb-0">The registration link comes from each course's <a href="wa_knowledge.php">Knowledge Base</a> — add a line like <code>Registration link: https://…</code>. This field is only a fallback for courses whose KB has no link. Use <code>{id}</code>/<code>{type}</code> for a course-specific pattern, or leave blank to share the site home page.</p>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save</button>
                        <p class="text-muted small mt-2">A rep can always start enrollment from a chat with the <strong>Start enrollment</strong> button, even when this is off. This toggle only controls the automatic "register" trigger. Requires the migration <code>2026-07-15_enrollment.sql</code>.</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
