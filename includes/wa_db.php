<?php
/**
 * Headless mysqli connection for the webhook (no session / no auth.php).
 * Admin pages do NOT use this — they use $conn from auth.php via header.php.
 */
require_once __DIR__ . '/wa_config.php';

$wa_conn = mysqli_connect(WA_DB_HOST, WA_DB_USER, WA_DB_PASS, WA_DB_NAME);
if (!$wa_conn) {
    error_log('[wa] DB connect failed: ' . mysqli_connect_error());
    http_response_code(500);
    exit;
}
mysqli_set_charset($wa_conn, 'utf8mb4');
