<?php
/**
 * Academic-programme approval email.
 *
 * Academic programmes are International Events flagged with an 'academic#' marker
 * in Event.location (case-insensitive), or matched to an academic_programs row by
 * title. They get the SAME single "Approval" email as virtual courses: admission
 * letter (with the programme's own curriculum modules + fee structure) and a
 * proforma invoice — TWO attachments on ONE email — instead of the event path's
 * two separate emails. The email body comes from the Academic template configured
 * in the CRM (system_emails1 by event_id) when present, else a standard message.
 */

if (!function_exists('is_academic_event')) {
    function is_academic_event($conn, $location, $event_title) {
        if (stripos((string) $location, 'academic#') !== false) {
            return true;
        }
        // Fallback: the event title matches a configured academic programme.
        $t = mysqli_real_escape_string($conn, trim((string) $event_title));
        if ($t === '') { return false; }
        $q = mysqli_query($conn, "SELECT id FROM academic_programs WHERE LOWER(title) = LOWER('$t') OR '$t' LIKE CONCAT('%', title, '%') LIMIT 1");
        return ($q && mysqli_num_rows($q) > 0);
    }
}

if (!function_exists('academic_program_modules')) {
    /** Curriculum module names for the academic programme matching this event title. */
    function academic_program_modules($conn, $event_title) {
        $modules = [];
        $t = mysqli_real_escape_string($conn, trim((string) $event_title));
        if ($t === '') { return $modules; }
        $pq = mysqli_query($conn, "SELECT id FROM academic_programs WHERE LOWER(title) = LOWER('$t') LIMIT 1");
        if (!$pq || mysqli_num_rows($pq) === 0) {
            $pq = mysqli_query($conn, "SELECT id FROM academic_programs WHERE '$t' LIKE CONCAT('%', title, '%') OR title LIKE CONCAT('%', '$t', '%') ORDER BY CHAR_LENGTH(title) DESC LIMIT 1");
        }
        if ($pq && mysqli_num_rows($pq) > 0) {
            $pid = (int) mysqli_fetch_assoc($pq)['id'];
            $mq = mysqli_query($conn, "SELECT module_name FROM program_curriculum WHERE program_id = $pid ORDER BY FIELD(curriculum_tier,'foundational','intermediate','advanced'), sort_order ASC, id ASC");
            if ($mq) { while ($m = mysqli_fetch_assoc($mq)) { $nm = trim((string) $m['module_name']); if ($nm !== '') { $modules[] = $nm; } } }
        }
        return $modules;
    }
}

if (!function_exists('send_academic_approval_email')) {
    function send_academic_approval_email($conn, $event_id, $event_data, $firstname, $lastname, $email, $ticket_id) {
        // These are already loaded by invoice_international_.php in the callers; the
        // require_once calls are no-ops there and safety elsewhere.
        require_once __DIR__ . '/../pdf_plugins/generatePdf.php';
        require_once __DIR__ . '/../email_plugins/vendor/autoload.php';
        require_once __DIR__ . '/../email_plugins/email_function.php';

        $email_address  = $email;
        $recipient_name = ucfirst(strtolower($firstname)) . ' ' . ucfirst(strtolower($lastname));
        $subject        = 'Vantage Africa School Of Leadership Approval';
        $purpose        = isset($event_data['event_title']) ? $event_data['event_title'] : '';
        $amount         = number_format((float) (isset($event_data['early_amount']) ? $event_data['early_amount'] : 0), 2, '.', ',');
        $adm_no         = 'VASL-' . $ticket_id;
        $invoice_no     = $adm_no;
        $letter_date    = date('jS F Y');
        $invoice_date   = date('jS F Y');
        $entry_id       = $ticket_id;   // adm_letter logs against this
        $record_id      = null;

        // Email body: the Academic template from the CRM if configured, else standard.
        $body = 'Dear ' . htmlspecialchars($recipient_name) . ',<br><br>'
              . 'Congratulations on your admission to <strong>' . htmlspecialchars($purpose) . '</strong>. '
              . 'Please find attached your admission letter and proforma invoice. '
              . 'Our team will be in touch with the next steps.<br><br>'
              . 'Warm regards,<br>Vantage Africa School of Leadership';
        $tpl_q = mysqli_query($conn, "SELECT body FROM system_emails1 WHERE event_id = '" . (int) $event_id . "' AND email_opt = 1 ORDER BY id DESC LIMIT 1");
        if ($tpl_q && mysqli_num_rows($tpl_q) > 0) {
            $tpl_row  = mysqli_fetch_assoc($tpl_q);
            $tpl_body = json_decode($tpl_row['body'], true);
            if (is_string($tpl_body) && trim($tpl_body) !== '') {
                $body = str_replace('$name', $recipient_name, $tpl_body);
            }
        }

        // Admission-letter body only — adm_letter.php prints the "Dear <name>,"
        // greeting itself, and now renders the programme's curriculum as a styled
        // "Areas to be trained" outline, so no greeting or inline module list here
        // (otherwise both would appear twice).
        $adm = '<p>We are pleased to offer you admission to <strong>' . htmlspecialchars($purpose) . '</strong> '
             . 'at Vantage Africa School of Leadership. Your place is confirmed upon settlement of the fees indicated below.</p>'
             . '<p>We look forward to welcoming you and wish you a successful journey with us.</p>';

        // adm_letter.php builds the 2 PDFs and sends the single approval email using
        // the variables set above (its relative output dirs resolve against the web
        // request's CWD, i.e. the app root — same as the virtual-course flow).
        include __DIR__ . '/../adm_letter.php';
        return true;
    }
}
