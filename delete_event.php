<?php
// delete_event.php — delete an international event (from view_event.php's Delete modal).
//
// Registrations and payment records are intentionally NOT cascaded: they are financial
// history keyed by app_id/purpose and must be preserved. We remove the Event row so the
// event leaves the list. Same auth/redirect style as update_event.php.
require_once 'auth.php';   // provides $conn + session

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_event'])) {
    $event_id = intval($_POST['event_id'] ?? 0);
    if ($event_id > 0) {
        $stmt = $conn->prepare("DELETE FROM `Event` WHERE `event_id` = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $event_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

header('Location: post_events.php');
exit;
