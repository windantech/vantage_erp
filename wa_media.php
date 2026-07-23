<?php
/**
 * Media proxy — streams an inbound WhatsApp media file (image/doc/audio/video)
 * to a logged-in staff member. Fetches lazily from 360dialog by media id; we
 * never bulk-store files. Access-checked: role 44 + owns the chat (or 777).
 *
 *   wa_media.php?msg=<wa_messages.id>
 */
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
require_once __DIR__ . '/auth.php';                 // $conn, $role, $staff_id (no HTML)
require_once __DIR__ . '/includes/wa_config.php';
require_once __DIR__ . '/includes/wa_functions.php';

if (!in_array(WA_ROLE, $role)) { http_response_code(403); exit('Forbidden'); }
$is_supervisor = in_array(777, $role);

$msgId = (int)($_GET['msg'] ?? 0);
if ($msgId <= 0) { http_response_code(400); exit('Bad request'); }

$res = mysqli_query($conn,
    "SELECT m.media_id, m.media_mime, m.type, m.contact_id,
            (SELECT cv.assigned_user_id FROM wa_conversations cv WHERE cv.contact_id = m.contact_id LIMIT 1) AS owner
       FROM wa_messages m WHERE m.id = $msgId LIMIT 1");
$m = $res ? mysqli_fetch_assoc($res) : null;
if (!$m || empty($m['media_id'])) { http_response_code(404); exit('Not found'); }
if (!$is_supervisor && (int)$m['owner'] !== (int)$staff_id) { http_response_code(403); exit('Forbidden'); }

$media = wa_fetch_media($m['media_id']);
if (empty($media['ok'])) {
    error_log('[wa-media] serve failed msg=' . $msgId . ' media_id=' . $m['media_id'] . ' err=' . ($media['error'] ?? '?'));
    http_response_code(502);
    exit('Media unavailable');
}

$mime = $m['media_mime'] ?: $media['mime'];
$ext  = wa_ext_for_mime($mime);

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($media['body']));
header('Cache-Control: private, max-age=600');
header('Content-Disposition: inline; filename="wa-' . $msgId . $ext . '"');
echo $media['body'];
