<?php
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "../../function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';

if (!in_array(777, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Supervisors only.</div></div>';
    require_once 'footer.php';
    exit;
}

$flash = isset($_SESSION['wa_flash']) ? $_SESSION['wa_flash'] : null;
unset($_SESSION['wa_flash']);
$replies = wa_quick_replies_list($conn);
$courses = wa_active_courses($conn);
$events  = wa_active_events($conn);
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="bi bi-chat-left-text me-2"></i>Quick Replies</h4>
                    <p class="text-muted mb-0">Saved responses agents can insert into a chat with one click. Use <code>{name}</code> to drop in the contact's name.</p>
                </div>
                <a href="wa_inbox.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inbox</a>
            </div>

            <?php if ($flash): ?><div class="alert alert-<?php echo wa_e($flash[0]); ?>"><?php echo wa_e($flash[1]); ?></div><?php endif; ?>

            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-uppercase">Add a quick reply</h6></div>
                        <div class="card-body">
                            <form method="post" action="includes/wa_process.php">
                                <input type="hidden" name="action" value="save_quick">
                                <div class="mb-2">
                                    <label class="form-label small text-muted">Title (shown on the button)</label>
                                    <input type="text" name="title" class="form-control" placeholder="Opening hours" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small text-muted">Message</label>
                                    <textarea name="body" class="form-control" rows="4" placeholder="Hi {name}, our office is open Mon–Fri, 8am–5pm. How can we help?" required></textarea>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small text-muted">Show on</label>
                                    <select name="scope" class="form-select">
                                        <option value="0">All chats (global)</option>
                                        <?php if ($courses): ?>
                                        <optgroup label="Courses">
                                            <?php foreach ($courses as $c): ?>
                                                <option value="course:<?php echo (int)$c['id']; ?>"><?php echo wa_e($c['name']); ?> chats only</option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endif; ?>
                                        <?php if ($events): ?>
                                        <optgroup label="Events (onsite)">
                                            <?php foreach ($events as $ev): ?>
                                                <option value="event:<?php echo (int)$ev['id']; ?>"><?php echo wa_e($ev['name']); ?> chats only</option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="mb-3" style="max-width:140px">
                                    <label class="form-label small text-muted">Sort order</label>
                                    <input type="number" name="sort" class="form-control" value="0">
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-uppercase">Saved replies</h6></div>
                        <div class="card-body">
                            <?php if ($replies): foreach ($replies as $q): ?>
                            <div class="border rounded p-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="pe-2">
                                        <div class="fw-semibold">
                                            <?php echo wa_e($q['title']); ?>
                                            <?php if (!empty($q['ref_type'])): ?>
                                                <span class="badge bg-info"><?php echo wa_e($q['ref_type']); ?>: <?php echo wa_e($q['ref_name'] ?: ('#' . (int)$q['ref_id'])); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">All chats</span>
                                            <?php endif; ?>
                                            <span class="text-muted small">#<?php echo (int)$q['sort']; ?></span>
                                        </div>
                                        <div class="small text-muted"><?php echo nl2br(wa_e($q['body'])); ?></div>
                                    </div>
                                    <form method="post" action="includes/wa_process.php" onsubmit="return confirm('Delete this quick reply?');">
                                        <input type="hidden" name="action" value="delete_quick">
                                        <input type="hidden" name="id" value="<?php echo (int)$q['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; else: ?>
                            <p class="text-muted text-center py-4 mb-0">No quick replies yet — add one on the left.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
