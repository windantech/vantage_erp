<?php
/**
 * WhatsApp form processor — the single POST endpoint for the WhatsApp pages,
 * the same pattern as includes/process_enquiry.php: every form posts here with
 * a hidden `action`, we do the work, set a flash message, and redirect back.
 *
 * It bootstraps the ERP the same way the pages do, minus the HTML: auth.php
 * gives us $conn / $role / $staff_id. (If your ERP exposes those through a
 * different bootstrap file, change the require below to match.)
 */
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once __DIR__ . '/../auth.php';        // $conn, $role, $staff_id
require_once __DIR__ . '/wa_config.php';
require_once __DIR__ . '/wa_functions.php';

function wa_redirect($to) { header('Location: ' . $to); exit; }
function wa_flash($type, $msg) { $_SESSION['wa_flash'] = [$type, $msg]; }

$action        = $_POST['action'] ?? '';
$is_supervisor = in_array(777, $role);

// Gate: only WhatsApp users (role 44) may post anything here.
if (!in_array(WA_ROLE, $role)) {
    wa_flash('danger', 'Access denied.');
    wa_redirect('../wa_inbox.php');
}

switch ($action) {

    // ---- Thread: send a free-form reply (24h-window aware) ----
    case 'reply': {
        $conv_id = (int)($_POST['id'] ?? 0);
        $conv = wa_load_conversation($conn, $conv_id);
        if (!wa_can_touch($conv, $is_supervisor, $staff_id)) {
            wa_flash('warning', 'That conversation is not assigned to you.');
            wa_redirect('../wa_inbox.php');
        }
        $body = trim((string)($_POST['body'] ?? ''));
        $hasFile = isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK && $_FILES['file']['size'] > 0;
        if (!$hasFile && $body === '') {
            wa_flash('warning', 'Message is empty.');
        } elseif (!wa_within_window($conv['last_inbound_at'])) {
            wa_flash('warning', 'Outside the 24-hour window — an approved template is required.');
        } elseif ($hasFile && $_FILES['file']['size'] > 16 * 1024 * 1024) {
            wa_flash('warning', 'File too large (max 16 MB).');
        } else {
            // Tag this outbound as a human agent's message (so it's labelled separately
            // from AI replies in the thread). AI/automated sends leave this unset.
            $GLOBALS['WA_SENT_BY_STAFF'] = (int)$staff_id;
            if ($hasFile) {
                $f = $_FILES['file'];
                $mime = $f['type'] ?: 'application/octet-stream';
                $res = wa_send_media($conn, $conv['wa_id'], $f['tmp_name'], $mime, $f['name'], $body);
            } else {
                $res = wa_send_text($conn, $conv['wa_id'], $body);
            }
            unset($GLOBALS['WA_SENT_BY_STAFF']);
            if (!empty($res['ok'])) {
                // Record the human reply (clears escalation, pauses the AI for a while)
                // but do NOT permanently mute the AI — that's the explicit Human toggle.
                mysqli_query($conn,
                    "UPDATE wa_conversations SET escalated=0, last_human_at=NOW(), last_message_at=NOW()
                      WHERE id = $conv_id");
                // Learn from the team: capture a text reply about a specific course/
                // event as a pending "learning" for a supervisor to review (never
                // auto-changes the KB). Trivial one-liners are ignored by the helper.
                if (!$hasFile && $body !== ''
                    && in_array($conv['ref_type'] ?? '', ['course', 'event'], true) && !empty($conv['ref_id'])) {
                    wa_kb_learning_add($conn, $conv['ref_type'], (int)$conv['ref_id'],
                        $conv_id, (int)$conv['contact_id'], (int)($res['message_id'] ?? 0) ?: null,
                        $body, (int)$staff_id);
                }
                wa_flash('success', $hasFile ? 'File sent.' : 'Reply sent.');
            } else {
                wa_flash('danger', 'Send failed: ' . ($res['error'] ?? 'unknown'));
            }
        }
        wa_redirect('../wa_thread.php?id=' . $conv_id);
    }

    // ---- Thread: AI / Human handler toggle ----
    // ---- Thread: retract (soft-delete) a reply from the CRM, kept for review (#19) ----
    case 'delete_message': {
        $conv_id = (int)($_POST['id'] ?? 0);
        $msg_id  = (int)($_POST['msg_id'] ?? 0);
        $conv = wa_load_conversation($conn, $conv_id);
        if (!wa_can_touch($conv, $is_supervisor, $staff_id)) {
            wa_flash('warning', 'That conversation is not assigned to you.');
            wa_redirect('../wa_inbox.php');
        }
        if ($msg_id > 0 && $conv) {
            wa_message_flags_ensure($conn);
            $cid = (int)$conv['contact_id'];
            // Only our OWN (outbound) messages, only within this chat.
            mysqli_query($conn, "UPDATE wa_messages SET deleted_at = NOW()
                WHERE id = $msg_id AND contact_id = $cid AND direction = 'outbound' AND type <> 'note'");
            wa_flash('success', 'Reply retracted from the CRM (kept internally for review).');
        }
        wa_redirect('../wa_thread.php?id=' . $conv_id);
    }
    case 'restore_message': {
        $conv_id = (int)($_POST['id'] ?? 0);
        $msg_id  = (int)($_POST['msg_id'] ?? 0);
        $conv = wa_load_conversation($conn, $conv_id);
        if (!wa_can_touch($conv, $is_supervisor, $staff_id)) {
            wa_flash('warning', 'That conversation is not assigned to you.');
            wa_redirect('../wa_inbox.php');
        }
        if ($msg_id > 0 && $conv) {
            wa_message_flags_ensure($conn);
            $cid = (int)$conv['contact_id'];
            mysqli_query($conn, "UPDATE wa_messages SET deleted_at = NULL WHERE id = $msg_id AND contact_id = $cid");
            wa_flash('success', 'Reply restored.');
        }
        wa_redirect('../wa_thread.php?id=' . $conv_id);
    }

    case 'handler': {
        $conv_id = (int)($_POST['id'] ?? 0);
        $conv = wa_load_conversation($conn, $conv_id);
        if (!wa_can_touch($conv, $is_supervisor, $staff_id)) {
            wa_flash('warning', 'That conversation is not assigned to you.');
            wa_redirect('../wa_inbox.php');
        }
        $h = ($_POST['handler'] ?? '') === 'human' ? 'human' : 'ai';
        mysqli_query($conn, "UPDATE wa_conversations SET handler = '$h' WHERE id = $conv_id");
        wa_flash('success', 'Handler set to ' . $h . '.');
        wa_redirect('../wa_thread.php?id=' . $conv_id);
    }

    // ---- Thread: reassign to another staff member (supervisor only) ----
    // ---- Thread: reassign this chat to another staff member (any WhatsApp staff) ----
    case 'reassign': {
        $conv_id = (int)($_POST['id'] ?? 0);
        $newUser = (int)($_POST['assigned_user_id'] ?? 0);
        mysqli_query($conn,
            "UPDATE wa_conversations SET assigned_user_id = $newUser, last_route_reason = 'manual' WHERE id = $conv_id");
        wa_flash('success', 'Conversation reassigned.');
        wa_redirect('../wa_thread.php?id=' . $conv_id);
    }

    // ---- Thread: link this chat to a course/event (any WhatsApp staff) ----
    case 'set_ref': {
        $conv_id = (int)($_POST['id'] ?? 0);
        $refType = ''; $refId = 0;
        $scope = (string)($_POST['scope'] ?? '');
        if (strpos($scope, ':') !== false) { list($refType, $refId) = explode(':', $scope, 2); }
        wa_set_conversation_ref($conn, $conv_id, $refType, (int)$refId);
        wa_flash('success', $refType ? ('Chat linked to ' . $refType . '.') : 'Course/event link cleared.');
        wa_redirect('../wa_thread.php?id=' . $conv_id);
    }

    // ---- Thread: send the approved re-engagement template (re-opens a closed 24h window) ----
    case 'reengage': {
        $conv_id = (int)($_POST['id'] ?? 0);
        $conv = wa_load_conversation($conn, $conv_id);
        if (!wa_can_touch($conv, $is_supervisor, $staff_id)) {
            wa_flash('warning', 'That conversation is not for one of your courses.');
            wa_redirect('../wa_inbox.php');
        }
        $tmpl = wa_setting_get($conn, 'reengage_template', '');
        if ($tmpl === '') {
            wa_flash('warning', 'No re-engagement template configured yet (set the reengage_template setting to your approved template name).');
            wa_redirect('../wa_thread.php?id=' . $conv_id);
        }
        // Language: use the configured code, else auto-detect from the synced template
        // record, else 'en'. A wrong code causes WhatsApp 132001 "does not exist in <lang>".
        $lang = trim((string)wa_setting_get($conn, 'reengage_template_lang', ''));
        if ($lang === '') {
            $lr = mysqli_query($conn, "SELECT language FROM wa_templates WHERE name = '"
                . mysqli_real_escape_string($conn, $tmpl) . "' ORDER BY id DESC LIMIT 1");
            if ($lr && ($lrow = mysqli_fetch_assoc($lr))) { $lang = trim((string)$lrow['language']); }
            if ($lang === '') { $lang = 'en'; }
        }
        // {{1}} = customer name, {{2}} = logged-in rep's name, {{3}} = course/programme.
        // NB: fetch these as strings — wa_scalar() int-casts and would turn names into 0.
        $cid = (int)$conv['contact_id'];
        $custName = '';
        $cr = mysqli_query($conn, "SELECT profile_name FROM wa_contacts WHERE id = $cid LIMIT 1");
        if ($cr && ($crow = mysqli_fetch_assoc($cr))) { $custName = trim((string)$crow['profile_name']); }
        if ($custName === '') { $custName = 'there'; }
        $staffName = '';
        $sr = mysqli_query($conn,
            "SELECT COALESCE(NULLIF(s.full_name,''), ru.fullname) AS nm
               FROM registered_users ru
          LEFT JOIN staff s ON s.system_user_id = ru.id
              WHERE ru.id = " . (int)$staff_id . " LIMIT 1");
        if ($sr && ($srow = mysqli_fetch_assoc($sr))) { $staffName = trim((string)$srow['nm']); }
        if ($staffName === '') { $staffName = 'the Vantage Africa team'; }
        $course = ($conv['ref_id'] !== null && in_array($conv['ref_type'] ?? '', ['course', 'event', 'program'], true))
            ? trim((string)wa_ref_name($conn, $conv['ref_type'], (int)$conv['ref_id'])) : '';
        if ($course === '') { $course = 'our programmes'; }
        // Match the template's ACTUAL variable count (WhatsApp 132000 if we send the wrong
        // number). 3 vars -> name, rep, course; 2 vars -> name, course; 1 var -> name.
        $nVars = 3;
        $br = mysqli_query($conn, "SELECT body FROM wa_templates WHERE name = '"
            . mysqli_real_escape_string($conn, $tmpl) . "' ORDER BY id DESC LIMIT 1");
        if ($br && ($brow = mysqli_fetch_assoc($br))
            && preg_match_all('/\{\{\s*(\d+)\s*\}\}/', (string)$brow['body'], $mm) && $mm[1]) {
            $nVars = max(array_map('intval', $mm[1]));
        }
        if ($nVars >= 3)      { $vals = [$custName, $staffName, $course]; }
        elseif ($nVars === 2) { $vals = [$custName, $course]; }
        elseif ($nVars === 1) { $vals = [$custName]; }
        else                  { $vals = []; }
        $params = [];
        foreach ($vals as $v) { $params[] = ['type' => 'text', 'text' => $v]; }
        $components = $params ? [['type' => 'body', 'parameters' => $params]] : [];
        $GLOBALS['WA_SENT_BY_STAFF'] = (int)$staff_id;   // label it as this rep's message
        $res = wa_send_template($conn, $conv['wa_id'], $tmpl, $lang, $components);
        unset($GLOBALS['WA_SENT_BY_STAFF']);
        if (!empty($res['ok'])) {
            // Stamp when we re-engaged so the inbox can surface who REPLIES after this.
            wa_conv_reengage_schema_ensure($conn);
            mysqli_query($conn, "UPDATE wa_conversations SET reengaged_at = NOW() WHERE id = " . (int)$conv_id);
        }
        wa_flash(!empty($res['ok']) ? 'success' : 'danger',
            !empty($res['ok']) ? 'Re-engagement message sent.' : ('Send failed: ' . ($res['error'] ?? 'unknown')));
        wa_redirect('../wa_thread.php?id=' . $conv_id);
    }

    // ---- Settings: active AI provider (supervisor only) ----
    case 'save_provider': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_settings.php'); }
        $provider = ($_POST['provider'] ?? '') === 'openai' ? 'openai' : 'claude';
        wa_setting_set($conn, 'ai_provider', $provider);
        wa_flash('success', 'AI provider updated.');
        wa_redirect('../wa_settings.php');
    }

    // ---- Settings: auto-reply toggle (supervisor only) ----
    case 'save_autoreply': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_settings.php'); }
        wa_setting_set($conn, 'ai_autoreply', isset($_POST['ai_autoreply']) ? '1' : '0');
        wa_flash('success', 'Auto-reply setting saved.');
        wa_redirect('../wa_settings.php');
    }

    // ---- Settings: enrollment auto-start toggle (supervisor only) ----
    case 'save_enroll': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_settings.php'); }
        wa_setting_set($conn, 'enroll_enabled', isset($_POST['enroll_enabled']) ? '1' : '0');
        wa_setting_set($conn, 'register_url', trim((string)($_POST['register_url'] ?? '')));
        wa_flash('success', 'Enrollment setting saved.');
        wa_redirect('../wa_settings.php');
    }

    // ---- Thread: start guided enrollment for this contact ----
    case 'start_enroll': {
        $conv_id = (int)($_POST['id'] ?? 0);
        $conv = wa_load_conversation($conn, $conv_id);
        if (!wa_can_touch($conv, $is_supervisor, $staff_id)) {
            wa_flash('warning', 'That conversation is not assigned to you.');
            wa_redirect('../wa_inbox.php');
        }
        if (empty($conv['ref_id']) || !in_array($conv['ref_type'], ['course', 'event'], true)) {
            wa_flash('warning', 'Link this chat to a course or event first.');
            wa_redirect('../wa_thread.php?id=' . $conv_id);
        }
        if (!wa_within_window($conv['last_inbound_at'])) {
            wa_flash('warning', 'Outside the 24-hour window — the contact needs to message first.');
            wa_redirect('../wa_thread.php?id=' . $conv_id);
        }
        wa_enroll_start($conn, (int)$conv['contact_id'], $conv['wa_id'], $conv['ref_type'], (int)$conv['ref_id']);
        wa_flash('success', 'Enrollment started — the contact has been asked for their details.');
        wa_redirect('../wa_thread.php?id=' . $conv_id);
    }

    // ---- Assign a rep to a course/event (fallback owner; supervisor only) ----
    // Also mirrored into the CEO "Performance" assignment fields so the WhatsApp
    // Assignments page and CEO Dashboard → Performance stay in sync:
    //   - event  -> Event.assigned_to            (matches ceo_dashboard/event_assignments)
    //   - course -> intake.assigned_to for EVERY intake of the course, plus the
    //               course.assigned_to rollup    (matches ceo_dashboard/intake_assignments)
    // Assigning sets the rep id; clearing (uid<=0) empties the field.
    case 'assign_owner': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_assignments.php'); }
        $rt  = ($_POST['ref_type'] ?? '') === 'event' ? 'event' : 'course';
        $rid = (int)($_POST['ref_id'] ?? 0);

        // Multiple assignees + one PRIMARY (the rep who actually routes/handles chats).
        $assignees = array_values(array_unique(array_filter(
            array_map('intval', (array)($_POST['user_ids'] ?? [])),
            function ($v) { return $v > 0; }
        )));
        $primary = (int)($_POST['primary_id'] ?? 0);
        if ($primary > 0 && !in_array($primary, $assignees, true)) { $assignees[] = $primary; }
        if ($primary <= 0 && $assignees) { $primary = $assignees[0]; }   // default primary = first

        if ($rid > 0) {
            // Full assignee list lives in the ERP comma-field (CEO Performance + FIND_IN_SET read it).
            $list = $assignees ? "'" . implode(',', $assignees) . "'" : "''";
            // Primary drives chat routing via the single wa_course_owner override.
            wa_owner_override_set($conn, $rt, $rid, $primary);           // clears when $primary <= 0

            if ($rt === 'event') {
                // International + Academics are both Event rows.
                mysqli_query($conn, "UPDATE `Event` SET assigned_to = $list WHERE event_id = $rid");
            } else {
                // Virtual: course rollup = full list; each intake takes the PRIMARY (single per intake).
                $pval = $primary > 0 ? "'" . $primary . "'" : "''";
                mysqli_query($conn, "UPDATE course SET assigned_to = $list WHERE course_id = $rid");
                mysqli_query($conn, "UPDATE intake SET assigned_to = $pval WHERE course_id = $rid");
            }
            $n = count($assignees);
            wa_flash('success', $n > 0
                ? ($n . ' rep' . ($n > 1 ? 's' : '') . ' assigned (synced to Performance).')
                : 'Assignment cleared (synced to Performance).');
        }
        wa_redirect('../wa_assignments.php');
    }

    // ---- Assignments: add / remove / set-primary ONE rep at a time ----
    // Powers the chip UI on wa_assignments.php: ✕ removes a rep, ★ makes a rep
    // primary, the dropdown adds one. All three write the same fields as
    // assign_owner (ERP comma-list + the single primary override) so CEO
    // Performance and chat routing stay in sync.
    case 'manage_owner': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_assignments.php'); }
        $rt  = ($_POST['ref_type'] ?? '') === 'event' ? 'event' : 'course';
        $rid = (int)($_POST['ref_id'] ?? 0);
        $op  = (string)($_POST['op'] ?? '');
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($rid > 0 && $uid > 0 && in_array($op, ['add', 'remove', 'primary'], true)) {
            // Current state.
            $cur = [];
            foreach (wa_owners($conn, $rt, $rid) as $o) { $cur[] = (int)$o['user_id']; }
            $primary = (int) wa_owner_override($conn, $rt, $rid);

            if ($op === 'add') {
                if (!in_array($uid, $cur, true)) { $cur[] = $uid; }
                if ($primary <= 0) { $primary = $uid; }              // first rep becomes primary
            } elseif ($op === 'remove') {
                $cur = array_values(array_filter($cur, function ($x) use ($uid) { return $x !== $uid; }));
                if ($primary === $uid) { $primary = $cur ? $cur[0] : 0; } // promote next, or clear
            } elseif ($op === 'primary') {
                if (in_array($uid, $cur, true)) { $primary = $uid; }
            }

            // Write back — same shape as assign_owner.
            $list = $cur ? "'" . implode(',', $cur) . "'" : "''";
            wa_owner_override_set($conn, $rt, $rid, $primary);
            if ($rt === 'event') {
                mysqli_query($conn, "UPDATE `Event` SET assigned_to = $list WHERE event_id = $rid");
            } else {
                $pval = $primary > 0 ? "'" . $primary . "'" : "''";
                mysqli_query($conn, "UPDATE course SET assigned_to = $list WHERE course_id = $rid");
                mysqli_query($conn, "UPDATE intake SET assigned_to = $pval WHERE course_id = $rid");
            }
            wa_flash('success', $op === 'remove' ? 'Rep removed.' : ($op === 'primary' ? 'Primary updated.' : 'Rep added.'));
        }
        wa_redirect('../wa_assignments.php');
    }

    // ---- Templates: record locally / delete (supervisor only) ----
    // (Creation + Meta submission happen in the 360dialog Hub — the messaging
    //  API key can't manage templates. Here we just record them for broadcasting.)
    case 'save_template': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_templates.php'); }
        $name = strtolower(trim((string)($_POST['name'] ?? '')));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);   // Meta names are lowercase snake_case
        $lang = trim((string)($_POST['language'] ?? 'en')) ?: 'en';
        $body = trim((string)($_POST['body'] ?? ''));
        if ($name === '' || $body === '') { wa_flash('warning', 'Name and body are required.'); wa_redirect('../wa_templates.php'); }
        $status = in_array(($_POST['status'] ?? 'pending'), ['approved', 'pending', 'rejected'], true) ? $_POST['status'] : 'pending';
        wa_template_save($conn, $name, $lang, (string)($_POST['category'] ?? 'marketing'), $body, $status);
        wa_flash('success', 'Template recorded.');
        wa_redirect('../wa_templates.php');
    }
    // ---- Templates: SUBMIT to Meta via 360dialog (creates it for real, then records it) ----
    case 'submit_template': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_templates.php'); }
        $name = strtolower(trim((string)($_POST['name'] ?? '')));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
        $lang = trim((string)($_POST['language'] ?? 'en')) ?: 'en';
        $cat  = (string)($_POST['category'] ?? 'marketing');
        $body = trim((string)($_POST['body'] ?? ''));
        if ($name === '' || $body === '') { wa_flash('warning', 'Name and body are required.'); wa_redirect('../wa_templates.php'); }
        $res = wa_template_submit($conn, $name, $lang, $cat, $body);
        if (!empty($res['ok'])) {
            wa_flash('success', 'Submitted to Meta — status: ' . ($res['status'] ?? 'pending')
                . '. Meta reviews it (usually minutes–hours); click "Sync from Meta" to refresh the status.');
        } else {
            wa_flash('danger', 'Meta rejected the submission: ' . ($res['error'] ?? 'unknown'));
        }
        wa_redirect('../wa_templates.php');
    }
    case 'delete_template': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_templates.php'); }
        wa_template_delete($conn, (string)($_POST['name'] ?? ''), (string)($_POST['language'] ?? 'en'));
        wa_flash('success', 'Template deleted.');
        wa_redirect('../wa_templates.php');
    }

    // Pull templates (and their current status) straight from the 360dialog Hub
    // into wa_templates, so ones created in the Hub appear in Broadcast.
    case 'sync_templates': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_templates.php'); }
        @set_time_limit(60);
        $res = wa_templates_sync($conn);
        if (!empty($res['ok'])) {
            wa_flash('success', 'Synced ' . (int)$res['updated'] . ' template(s) from the 360dialog Hub.');
        } else {
            wa_flash('danger', 'Sync failed: ' . ($res['error'] ?? 'unknown') . '. Check WA_DIALOG_KEY.');
        }
        wa_redirect('../wa_templates.php');
    }

    // Canned quick replies (supervisor manages; any agent uses in the thread).
    case 'save_quick': {
        // Quick Replies module — full parity for WhatsApp staff (role 44); access gated at top of file.
        // scope is "0" (global), "course:<id>" or "event:<id>".
        $refType = ''; $refId = 0;
        $scope = (string)($_POST['scope'] ?? '0');
        if (strpos($scope, ':') !== false) { list($refType, $refId) = explode(':', $scope, 2); }
        $ok = wa_quick_reply_save($conn, (int)($_POST['id'] ?? 0), (string)($_POST['title'] ?? ''), (string)($_POST['body'] ?? ''), (int)($_POST['sort'] ?? 0), $refType, (int)$refId);
        wa_flash($ok ? 'success' : 'warning', $ok ? 'Quick reply saved.' : 'Title and message are required.');
        wa_redirect('../wa_canned.php');
    }
    case 'delete_quick': {
        // Quick Replies module — full parity for WhatsApp staff (role 44).
        wa_quick_reply_delete($conn, (int)($_POST['id'] ?? 0));
        wa_flash('success', 'Quick reply deleted.');
        wa_redirect('../wa_canned.php');
    }

    // Cancel a pending scheduled broadcast (supervisor only).
    case 'cancel_scheduled': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_broadcasts.php'); }
        $ok = wa_scheduled_cancel($conn, (int)($_POST['id'] ?? 0));
        wa_flash($ok ? 'success' : 'warning', $ok ? 'Scheduled broadcast cancelled.' : 'Could not cancel (already sent?).');
        wa_redirect('../wa_broadcasts.php');
    }

    // Manually opt a contact in/out of broadcasts (supervisor override).
    case 'contact_opt': {
        // Contacts module — full parity for WhatsApp staff (role 44).
        $cid = (int)($_POST['contact_id'] ?? 0);
        $out = ($_POST['opt'] ?? '') === 'out';
        wa_contact_set_optout($conn, $cid, $out);
        wa_flash('success', $out ? 'Contact opted out of broadcasts.' : 'Contact opted back in.');
        $back = (string)($_POST['back'] ?? '');
        wa_redirect($back !== '' ? '../wa_contacts.php?' . $back : '../wa_contacts.php');
    }

    // ---- Knowledge base: save per-course/event text (supervisor only) ----
    case 'save_program': {
        // Knowledge Base module — full parity for WhatsApp staff (role 44).
        $pid  = (int)($_POST['program_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { wa_flash('warning', 'Programme name is required.'); wa_redirect('../wa_knowledge.php'); }
        $id = wa_program_save($conn, $pid, $name, (string)($_POST['keywords'] ?? ''),
                              (($_POST['status'] ?? '1') === '0') ? 0 : 1);
        wa_flash('success', 'Training programme saved.');
        wa_redirect('../wa_knowledge.php?ref=program:' . (int)$id);
    }

    case 'delete_program': {
        // Knowledge Base module — full parity for WhatsApp staff (role 44).
        wa_program_delete($conn, (int)($_POST['program_id'] ?? 0));
        wa_flash('success', 'Training programme deleted.');
        wa_redirect('../wa_knowledge.php');
    }

    case 'save_knowledge': {
        // Knowledge Base module — full parity for WhatsApp staff (role 44).
        $rt  = in_array($_POST['ref_type'] ?? '', ['event', 'program'], true) ? $_POST['ref_type'] : 'course';
        $rid = (int)($_POST['ref_id'] ?? 0);
        if ($rid > 0) {
            wa_knowledge_set($conn, $rt, $rid, (string)($_POST['body'] ?? ''));
            wa_kb_audit_log($conn, $rt, $rid, $staff_id, strlen((string)($_POST['body'] ?? '')));   // #16 audit trail
            wa_flash('success', 'Knowledge base saved.');
        } else {
            wa_flash('warning', 'Pick a course or event first.');
        }
        wa_redirect('../wa_knowledge.php?ref=' . $rt . ':' . $rid);
    }

    case 'learn_approve':
    case 'learn_dismiss': {
        // Knowledge Base module — full parity for WhatsApp staff (role 44).
        $lid = (int)($_POST['learning_id'] ?? 0);
        $rt  = ($_POST['ref_type'] ?? '') === 'event' ? 'event' : 'course';
        $rid = (int)($_POST['ref_id'] ?? 0);
        if ($lid > 0) {
            if ($action === 'learn_approve') {
                wa_kb_learning_approve($conn, $lid, (int)$staff_id);
                wa_flash('success', 'Added to the knowledge base and reprocessed.');
            } else {
                wa_kb_learning_dismiss($conn, $lid, (int)$staff_id);
                wa_flash('success', 'Dismissed.');
            }
        }
        wa_redirect('../wa_knowledge.php?ref=' . $rt . ':' . $rid);
    }

    // ---- Inbox quick actions (Actions dropdown on each conversation row) ----
    // These mirror the thread actions but redirect back to the inbox.
    case 'inbox_assign_me':
    case 'inbox_handler':
    case 'inbox_escalate':
    case 'inbox_resolve':
    case 'inbox_mark_read': {
        $conv_id = (int)($_POST['id'] ?? 0);
        $conv = wa_load_conversation($conn, $conv_id);
        if (!wa_can_touch($conv, $is_supervisor, $staff_id)) {
            wa_flash('warning', 'That conversation is not assigned to you.');
            wa_redirect('../wa_inbox.php');
        }
        switch ($action) {
            case 'inbox_assign_me':
                mysqli_query($conn, "UPDATE wa_conversations SET assigned_user_id = " . (int)$staff_id . " WHERE id = $conv_id");
                wa_flash('success', 'Conversation assigned to you.');
                break;
            case 'inbox_handler':
                $h = ($_POST['handler'] ?? '') === 'human' ? 'human' : 'ai';
                $extra = $h === 'human' ? ', last_human_at = NOW()' : '';
                mysqli_query($conn, "UPDATE wa_conversations SET handler = '$h'$extra WHERE id = $conv_id");
                wa_flash('success', 'Handler set to ' . $h . '.');
                break;
            case 'inbox_escalate':
                mysqli_query($conn, "UPDATE wa_conversations SET escalated = 1, last_message_at = NOW() WHERE id = $conv_id");
                wa_flash('success', 'Conversation escalated.');
                break;
            case 'inbox_resolve':
                mysqli_query($conn, "UPDATE wa_conversations SET escalated = 0, last_human_at = NOW() WHERE id = $conv_id");
                wa_flash('success', 'Escalation resolved.');
                break;
            case 'inbox_mark_read':
                mysqli_query($conn, "UPDATE wa_conversations SET last_read_at = NOW() WHERE id = $conv_id");
                wa_flash('success', 'Marked as read.');
                break;
        }
        wa_redirect('../wa_inbox.php');
    }

    default:
        wa_redirect('../wa_inbox.php');
}

// ---- local helpers ----

/** Load a conversation joined to its contact (wa_id, last_inbound_at). */
function wa_load_conversation($conn, $conv_id) {
    $conv_id = (int)$conv_id;
    $res = mysqli_query($conn,
        "SELECT cv.*, c.wa_id, c.last_inbound_at
           FROM wa_conversations cv
           JOIN wa_contacts c ON c.id = cv.contact_id
          WHERE cv.id = $conv_id LIMIT 1");
    return $res ? mysqli_fetch_assoc($res) : null;
}

/** Supervisors may touch any conversation; everyone else may touch chats assigned to
 *  them OR for a course/event they're a rep of (same rule as inbox visibility). */
function wa_can_touch($conv, $is_supervisor, $staff_id) {
    global $conn;
    return wa_user_can_see_conv($conn, $conv, $staff_id, $is_supervisor);
}
