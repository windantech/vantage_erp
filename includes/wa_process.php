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
            if ($hasFile) {
                $f = $_FILES['file'];
                $mime = $f['type'] ?: 'application/octet-stream';
                $res = wa_send_media($conn, $conv['wa_id'], $f['tmp_name'], $mime, $f['name'], $body);
            } else {
                $res = wa_send_text($conn, $conv['wa_id'], $body);
            }
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
    case 'reassign': {
        $conv_id = (int)($_POST['id'] ?? 0);
        if (!$is_supervisor) {
            wa_flash('danger', 'Supervisors only.');
            wa_redirect('../wa_thread.php?id=' . $conv_id);
        }
        $newUser = (int)($_POST['assigned_user_id'] ?? 0);
        mysqli_query($conn,
            "UPDATE wa_conversations SET assigned_user_id = $newUser WHERE id = $conv_id");
        wa_flash('success', 'Conversation reassigned.');
        wa_redirect('../wa_thread.php?id=' . $conv_id);
    }

    // ---- Thread: link this chat to a course/event (supervisor only) ----
    case 'set_ref': {
        $conv_id = (int)($_POST['id'] ?? 0);
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_thread.php?id=' . $conv_id); }
        $refType = ''; $refId = 0;
        $scope = (string)($_POST['scope'] ?? '');
        if (strpos($scope, ':') !== false) { list($refType, $refId) = explode(':', $scope, 2); }
        wa_set_conversation_ref($conn, $conv_id, $refType, (int)$refId);
        wa_flash('success', $refType ? ('Chat linked to ' . $refType . '.') : 'Course/event link cleared.');
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
    case 'assign_owner': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_assignments.php'); }
        $rt  = ($_POST['ref_type'] ?? '') === 'event' ? 'event' : 'course';
        $rid = (int)($_POST['ref_id'] ?? 0);
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($rid > 0) {
            wa_owner_override_set($conn, $rt, $rid, $uid);
            wa_flash('success', $uid > 0 ? 'Rep assigned.' : 'Assignment cleared.');
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
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_canned.php'); }
        // scope is "0" (global), "course:<id>" or "event:<id>".
        $refType = ''; $refId = 0;
        $scope = (string)($_POST['scope'] ?? '0');
        if (strpos($scope, ':') !== false) { list($refType, $refId) = explode(':', $scope, 2); }
        $ok = wa_quick_reply_save($conn, (int)($_POST['id'] ?? 0), (string)($_POST['title'] ?? ''), (string)($_POST['body'] ?? ''), (int)($_POST['sort'] ?? 0), $refType, (int)$refId);
        wa_flash($ok ? 'success' : 'warning', $ok ? 'Quick reply saved.' : 'Title and message are required.');
        wa_redirect('../wa_canned.php');
    }
    case 'delete_quick': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_canned.php'); }
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
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_contacts.php'); }
        $cid = (int)($_POST['contact_id'] ?? 0);
        $out = ($_POST['opt'] ?? '') === 'out';
        wa_contact_set_optout($conn, $cid, $out);
        wa_flash('success', $out ? 'Contact opted out of broadcasts.' : 'Contact opted back in.');
        $back = (string)($_POST['back'] ?? '');
        wa_redirect($back !== '' ? '../wa_contacts.php?' . $back : '../wa_contacts.php');
    }

    // ---- Knowledge base: save per-course/event text (supervisor only) ----
    case 'save_program': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_knowledge.php'); }
        $pid  = (int)($_POST['program_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { wa_flash('warning', 'Programme name is required.'); wa_redirect('../wa_knowledge.php'); }
        $id = wa_program_save($conn, $pid, $name, (string)($_POST['keywords'] ?? ''),
                              (($_POST['status'] ?? '1') === '0') ? 0 : 1);
        wa_flash('success', 'Training programme saved.');
        wa_redirect('../wa_knowledge.php?ref=program:' . (int)$id);
    }

    case 'delete_program': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_knowledge.php'); }
        wa_program_delete($conn, (int)($_POST['program_id'] ?? 0));
        wa_flash('success', 'Training programme deleted.');
        wa_redirect('../wa_knowledge.php');
    }

    case 'save_knowledge': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_knowledge.php'); }
        $rt  = in_array($_POST['ref_type'] ?? '', ['event', 'program'], true) ? $_POST['ref_type'] : 'course';
        $rid = (int)($_POST['ref_id'] ?? 0);
        if ($rid > 0) {
            wa_knowledge_set($conn, $rt, $rid, (string)($_POST['body'] ?? ''));
            wa_flash('success', 'Knowledge base saved.');
        } else {
            wa_flash('warning', 'Pick a course or event first.');
        }
        wa_redirect('../wa_knowledge.php?ref=' . $rt . ':' . $rid);
    }

    case 'learn_approve':
    case 'learn_dismiss': {
        if (!$is_supervisor) { wa_flash('danger', 'Supervisors only.'); wa_redirect('../wa_knowledge.php'); }
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

/** Supervisors may touch any conversation; everyone else only their own. */
function wa_can_touch($conv, $is_supervisor, $staff_id) {
    if (!$conv) { return false; }
    return $is_supervisor || (int)$conv['assigned_user_id'] === (int)$staff_id;
}
