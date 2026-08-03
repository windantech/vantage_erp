<?php
/**
 * Academic-programme approval email.
 *
 * Academic programmes are registered as International Events flagged with an
 * 'academic#' marker in Event.location (or matched to an academic_programs row by
 * title). They get the SAME single "Approval" email as virtual courses — admission
 * letter + invoice as two attachments on ONE email (via adm_letter.php) — but with
 * per-course content pulled from `academic_programs` (about + curriculum) and the
 * invoice relabelled "ACADEMIC INVOICE".
 *
 * NOTE on collation: Event.event_title and academic_programs.title use different
 * collations, so every cross-table title comparison forces COLLATE utf8mb4_general_ci
 * to avoid MySQL error #1267.
 */

if (!function_exists('is_academic_event')) {
    function is_academic_event($conn, $location, $event_title) {
        if (stripos((string) $location, 'academic#') !== false) {
            return true;
        }
        $t = mysqli_real_escape_string($conn, trim((string) $event_title));
        if ($t === '') { return false; }
        $q = mysqli_query($conn, "SELECT id FROM academic_programs
                WHERE (LOWER(title COLLATE utf8mb4_general_ci) = LOWER('$t')
                    OR '$t' LIKE CONCAT('%', title COLLATE utf8mb4_general_ci, '%'))
                  AND status = 'active' LIMIT 1");
        return ($q && mysqli_num_rows($q) > 0);
    }
}

if (!function_exists('academic_program_row')) {
    /** The academic_programs row matching an event title (or null). */
    function academic_program_row($conn, $event_title) {
        $t = mysqli_real_escape_string($conn, trim((string) $event_title));
        if ($t === '') { return null; }
        // Exact title first, then fuzzy (longest title wins).
        $q = mysqli_query($conn, "SELECT * FROM academic_programs
                WHERE LOWER(title COLLATE utf8mb4_general_ci) = LOWER('$t') LIMIT 1");
        if (!$q || mysqli_num_rows($q) === 0) {
            $q = mysqli_query($conn, "SELECT * FROM academic_programs
                    WHERE '$t' LIKE CONCAT('%', title COLLATE utf8mb4_general_ci, '%')
                       OR title COLLATE utf8mb4_general_ci LIKE CONCAT('%', '$t', '%')
                    ORDER BY CHAR_LENGTH(title) DESC LIMIT 1");
        }
        return ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q) : null;
    }
}

if (!function_exists('academic_program_modules')) {
    /** Curriculum module names for an academic programme id. */
    function academic_program_modules($conn, $program_id) {
        $modules = [];
        $program_id = (int) $program_id;
        if ($program_id <= 0) { return $modules; }
        $mq = mysqli_query($conn, "SELECT module_name FROM program_curriculum
                WHERE program_id = $program_id
                ORDER BY FIELD(curriculum_tier,'foundational','intermediate','advanced'), sort_order ASC, id ASC");
        if ($mq) { while ($m = mysqli_fetch_assoc($mq)) { $nm = trim((string) $m['module_name']); if ($nm !== '') { $modules[] = $nm; } } }
        return $modules;
    }
}

if (!function_exists('send_academic_approval_email')) {
    function send_academic_approval_email($conn, $event_id, $event_data, $firstname, $lastname, $email, $ticket_id) {
        require_once __DIR__ . '/../pdf_plugins/generatePdf.php';
        require_once __DIR__ . '/../email_plugins/vendor/autoload.php';
        require_once __DIR__ . '/../email_plugins/email_function.php';

        $email_address  = $email;
        $recipient_name = ucfirst(strtolower($firstname)) . ' ' . ucfirst(strtolower($lastname));
        $subject        = 'Vantage Africa School Of Leadership Approval';
        $purpose        = isset($event_data['event_title']) ? trim((string) $event_data['event_title']) : '';
        $adm_no         = 'VASL-' . $ticket_id;
        $invoice_no     = $adm_no;
        $letter_date    = date('jS F Y');
        $invoice_date   = date('jS F Y');
        $entry_id       = $ticket_id;
        $record_id      = null;

        // Fee: event's stored amount, overridden by a fee typed on the form.
        $amount = (float) (isset($event_data['early_amount']) ? $event_data['early_amount'] : 0);
        if (isset($_POST['amount']) && trim((string) $_POST['amount']) !== '') {
            $amount = (float) $_POST['amount'];
        }
        $amount = number_format($amount, 2, '.', ',');

        // Per-course content from academic_programs (matched by title).
        $prog    = academic_program_row($conn, $purpose);
        $modules = ($prog && isset($prog['id'])) ? academic_program_modules($conn, $prog['id']) : [];

        // Email body — flexible per course by default; a CRM template overrides it.
        // Default: always reflects the SELECTED course (name + the programme's own
        // "about"), clean and well-spaced, no image. Never M&E.
        $body  = '<p>Dear ' . htmlspecialchars($recipient_name) . ',</p>';
        $body .= '<p>Congratulations! You have been successfully admitted to the <strong>'
               . htmlspecialchars($purpose) . '</strong> programme at Vantage Africa School of Leadership.</p>';
        if ($prog) {
            $intro = trim((string) ($prog['solution'] ?? ''));
            if ($intro === '') { $intro = trim((string) ($prog['market_problem'] ?? '')); }
            if ($intro !== '') { $body .= '<p>' . nl2br(htmlspecialchars($intro)) . '</p>'; }
        }
        $body .= '<p>Please find your admission letter and academic invoice attached. '
               . 'Our team will be in touch with the next steps.</p>'
               . '<p>Warm regards,<br>Vantage Africa School of Leadership</p>';
        $tpl_q = mysqli_query($conn, "SELECT body FROM system_emails1 WHERE event_id = '" . (int) $event_id . "' AND email_opt = 1 ORDER BY id DESC LIMIT 1");
        if ($tpl_q && mysqli_num_rows($tpl_q) > 0) {
            $tpl_row  = mysqli_fetch_assoc($tpl_q);
            $tpl_body = json_decode($tpl_row['body'], true);
            if (is_string($tpl_body) && trim($tpl_body) !== '') {
                $body = str_replace('$name', $recipient_name, $tpl_body);
            }
        }

        // Admission-letter content — the course's OWN about + curriculum (never M&E).
        $adm  = '<p>Dear ' . htmlspecialchars($recipient_name) . ',</p>';
        $adm .= '<p>We are delighted to inform you that you have been successfully admitted to the <strong>'
              . htmlspecialchars($purpose) . '</strong> Programme at Vantage Africa School of Leadership.</p>';
        if ($prog) {
            $about = trim((string) ($prog['solution'] ?? ''));
            if ($about === '') { $about = trim((string) ($prog['market_problem'] ?? '')); }
            if ($about !== '') { $adm .= '<p>' . nl2br(htmlspecialchars($about)) . '</p>'; }
            $whofor = trim((string) ($prog['who_for'] ?? ''));
            if ($whofor !== '') { $adm .= '<p><strong>Who this programme is for:</strong> ' . nl2br(htmlspecialchars($whofor)) . '</p>'; }
        }
        if ($modules) {
            $adm .= '<p><strong>Curriculum Overview</strong></p><ol>';
            foreach ($modules as $mn) { $adm .= '<li>' . htmlspecialchars($mn) . '</li>'; }
            $adm .= '</ol>';
        }
        $adm .= '<p>Your place is confirmed upon settlement of the fees indicated below. '
              . 'We look forward to welcoming you to the programme.</p>';

        // Label the second attachment "ACADEMIC INVOICE" (adm_letter.php reads this
        // global; unset afterwards so the virtual-course flow keeps "PROFORMA INVOICE").
        $GLOBALS['WA_INVOICE_TITLE']   = 'ACADEMIC INVOICE';
        $GLOBALS['WA_LAST_EMAIL_SENT'] = false;
        try {
            if (function_exists('sendEmailWithLogging') && function_exists('generatePdf') && function_exists('generatePdf_invoice')) {
                // adm_letter.php's functions are already loaded this request — call them
                // directly instead of re-including the file (a second include would
                // fatally redeclare its functions).
                $adm_letter_path = generatePdf($email_address, $recipient_name, $subject, $adm_no, $letter_date, $amount, $purpose, $body, $adm);
                $invoice_path    = generatePdf_invoice($email_address, $recipient_name, $subject, $invoice_no, $invoice_date, $amount, $purpose, $entry_id);
                $GLOBALS['WA_LAST_EMAIL_SENT'] = sendEmailWithLogging($conn, $entry_id, $record_id, $email_address, $recipient_name, $subject, $adm_letter_path, $invoice_path, $body);
            } else {
                include __DIR__ . '/../adm_letter.php';   // defines the generators AND sends (its tail sets WA_LAST_EMAIL_SENT)
            }
        } catch (\Throwable $e) {
            error_log('[academic] approval email threw: ' . $e->getMessage());
            $GLOBALS['WA_LAST_EMAIL_SENT'] = false;
        }
        unset($GLOBALS['WA_INVOICE_TITLE']);
        return !empty($GLOBALS['WA_LAST_EMAIL_SENT']);
    }
}
