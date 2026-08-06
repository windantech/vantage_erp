<?php
/**
 * Printable transcript for one conversation.
 *
 *   wa_thread_print.php?id=<conv>
 *
 * Opens a clean, document-style view of the whole chat and pops the browser's
 * print dialog — choose "Save as PDF" to export. No PDF library needed, so
 * emojis, Swahili and every font render exactly as the browser shows them.
 * Bootstraps exactly like wa_thread.php; a print stylesheet hides the ERP
 * chrome so only the transcript ends up in the PDF.
 */
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once 'header.php';                     // auth.php -> $conn, $role, $staff_id
require "function.php";
require_once 'includes/wa_config.php';
require_once 'includes/wa_functions.php';
wa_use_nairobi_time($conn);

if (!in_array(WA_ROLE, $role)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-danger">Access denied.</div></div>';
    require_once 'footer.php';
    exit;
}
$is_supervisor = in_array(777, $role);
$conv_id = (int)($_GET['id'] ?? 0);

$res = mysqli_query($conn,
    "SELECT cv.*, c.wa_id, c.profile_name,
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

// Supervisors may export any chat; everyone else only their own.
if (!$conv || (!$is_supervisor && (int)$conv['assigned_user_id'] !== (int)$staff_id)) {
    echo '<div class="container-fluid mt-5 pt-4"><div class="alert alert-warning">That conversation is not available.</div></div>';
    require_once 'footer.php';
    exit;
}

// Optional date-range filter (?from=YYYY-MM-DD&to=YYYY-MM-DD). Validated to a
// strict date shape so they're safe to inline. Effective date = the WhatsApp
// timestamp, falling back to when we stored the row.
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] : '';
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] : '';
$cid  = (int)$conv['contact_id'];
$dateExpr = 'COALESCE(wa_timestamp, created_at)';
// Exclude internal staff-only handoff notes — this transcript is customer-facing.
$where = "contact_id = $cid AND type <> 'note'";
if ($from !== '') { $where .= " AND $dateExpr >= '" . $from . " 00:00:00'"; }
if ($to   !== '') { $where .= " AND $dateExpr <= '" . $to   . " 23:59:59'"; }
$res = mysqli_query($conn, "SELECT * FROM wa_messages WHERE $where ORDER BY id ASC");
$messages = [];
if ($res) { while ($row = mysqli_fetch_assoc($res)) { $messages[] = $row; } }

$contactLbl = $conv['profile_name'] ?: $conv['wa_id'];
$ownerName  = $conv['owner_name'] ?: 'Unassigned';
$first = $messages ? ($messages[0]['wa_timestamp'] ?: $messages[0]['created_at']) : null;
$last  = $messages ? ($messages[count($messages)-1]['wa_timestamp'] ?: $messages[count($messages)-1]['created_at']) : null;
?>

<style>
/* On screen this sits inside the ERP; for print we isolate just the document. */
#wa_pdf { max-width: 820px; margin: 0 auto; color: #1b1f24; font-size: 13px; }
#wa_pdf .doc-head { border-bottom: 2px solid #198754; padding-bottom: 10px; margin-bottom: 16px; }
#wa_pdf .doc-head h2 { margin: 0 0 2px; font-size: 20px; }
#wa_pdf .doc-head .org { color: #198754; font-weight: 600; }
#wa_pdf .meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 12px; }
#wa_pdf .meta td { padding: 3px 8px 3px 0; vertical-align: top; }
#wa_pdf .meta td.k { color: #6b7280; white-space: nowrap; width: 130px; }
#wa_pdf .msg { margin: 0 0 10px; padding: 8px 12px; border-radius: 8px; page-break-inside: avoid; }
#wa_pdf .msg.in  { background: #f3f5f9; border: 1px solid #e5e7eb; margin-right: 18%; }
#wa_pdf .msg.out { background: #eafaf1; border: 1px solid #b7e4c7; margin-left: 18%; }
#wa_pdf .msg .who { font-weight: 600; font-size: 12px; }
#wa_pdf .msg .stamp { color: #6b7280; font-size: 11px; font-weight: 400; }
#wa_pdf .msg .body { white-space: pre-wrap; word-wrap: break-word; margin-top: 3px; }
#wa_pdf .msg .status { text-transform: capitalize; }
#wa_pdf .msg .status.failed { color: #dc3545; font-weight: 600; }
#wa_pdf .msg img { max-width: 300px; max-height: 300px; border-radius: 6px; display: block; margin-top: 4px; }
#wa_pdf .msg .media-note { color: #6b7280; font-style: italic; }
#wa_pdf .doc-foot { margin-top: 20px; padding-top: 8px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 11px; }

@media print {
    @page { margin: 14mm; }
    /* Hide all the ERP chrome; show only the transcript. */
    body * { visibility: hidden !important; }
    #wa_pdf, #wa_pdf * { visibility: visible !important; }
    #wa_pdf { position: absolute; left: 0; top: 0; width: 100%; max-width: none; }
    .noprint { display: none !important; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-4">
            <div class="noprint mb-3 d-flex gap-2 align-items-center">
                <a href="wa_thread.php?id=<?php echo (int)$conv_id; ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to chat
                </a>
                <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
                    <i class="bi bi-filetype-pdf me-1"></i>Save as PDF
                </button>
                <span class="text-muted small">Tip: in the print dialog choose “Save as PDF” as the destination.</span>
            </div>

            <div id="wa_pdf">
                <div class="doc-head">
                    <div class="org">Vantage Africa School of Leadership</div>
                    <h2>WhatsApp Conversation Transcript</h2>
                </div>

                <table class="meta">
                    <tr><td class="k">Contact</td><td><?php echo wa_e($contactLbl); ?></td></tr>
                    <tr><td class="k">WhatsApp number</td><td><?php echo wa_e($conv['wa_id']); ?></td></tr>
                    <tr><td class="k">Programme</td><td><?php echo wa_e($conv['ref_name'] ?: 'Unassigned'); ?></td></tr>
                    <tr><td class="k">Owner</td><td><?php echo wa_e($ownerName); ?></td></tr>
                    <tr><td class="k">Handled by</td><td><?php echo wa_e(ucfirst($conv['handler'] ?? 'ai')); ?><?php echo ((int)($conv['escalated'] ?? 0) === 1) ? ' (escalated)' : ''; ?></td></tr>
                    <?php if ($from !== '' || $to !== ''): ?>
                    <tr><td class="k">Date filter</td><td><?php echo wa_e($from ?: 'start') . '  →  ' . wa_e($to ?: 'today'); ?></td></tr>
                    <?php endif; ?>
                    <tr><td class="k">Messages</td><td><?php echo count($messages); ?></td></tr>
                    <tr><td class="k">Period</td><td><?php echo $first ? wa_e($first) . '  —  ' . wa_e($last) : '—'; ?></td></tr>
                    <tr><td class="k">Exported</td><td><?php echo wa_e(date('Y-m-d H:i')); ?></td></tr>
                </table>

                <?php foreach ($messages as $m): ?>
                    <?php
                    $out    = $m['direction'] === 'outbound';
                    $who    = $out ? 'Vantage' : $contactLbl;
                    $ts     = $m['wa_timestamp'] ?: $m['created_at'];
                    $isText = in_array($m['type'], ['text', 'template'], true);
                    ?>
                    <div class="msg <?php echo $out ? 'out' : 'in'; ?>">
                        <div class="who">
                            <?php echo wa_e($who); ?>
                            <span class="stamp">· <?php echo wa_e($ts); ?><?php
                                if ($out && $m['status']) {
                                    echo ' · <span class="status ' . ($m['status'] === 'failed' ? 'failed' : '') . '">'
                                       . wa_e($m['status'] === 'failed' ? 'not sent' : $m['status']) . '</span>';
                                }
                            ?></span>
                        </div>
                        <?php if ($isText): ?>
                            <div class="body"><?php echo wa_e((string)$m['body']); ?></div>
                        <?php elseif ($m['type'] === 'image'): ?>
                            <img src="wa_media.php?msg=<?php echo (int)$m['id']; ?>" alt="image">
                            <?php if (!empty($m['body'])): ?><div class="body"><?php echo wa_e((string)$m['body']); ?></div><?php endif; ?>
                        <?php else: ?>
                            <div class="body media-note">[<?php echo wa_e($m['type']); ?><?php echo !empty($m['media_mime']) ? ' · ' . wa_e($m['media_mime']) : ''; ?>]</div>
                            <?php if (!empty($m['body'])): ?><div class="body"><?php echo wa_e((string)$m['body']); ?></div><?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (!$messages): ?><div class="text-muted">No messages.</div><?php endif; ?>

                <div class="doc-foot">
                    Generated from the Vantage Africa School of Leadership WhatsApp inbox on <?php echo wa_e(date('Y-m-d H:i')); ?>.
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Auto-open the print dialog once images have had a moment to load.
window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 500); });
</script>

<?php require_once 'footer.php'; ?>
