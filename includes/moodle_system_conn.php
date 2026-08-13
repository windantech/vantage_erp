<?php
/**
 * moodle_system_conn.php — connection to the system.vantageafricaleaders.com LMS
 * (Moodle DB `vantage_system`, table prefix mdl_).
 *
 * Why this exists: the academic LMS credentials email links users to
 * https://system.vantageafricaleaders.com, but the account was being created in a
 * different Moodle DB (vantage_elearning). Credentials arrived, but login failed because
 * the account wasn't in that LMS. This connection lets us create the account in the SAME
 * database the login link points to.
 */

if (!function_exists('moodle_system_connect')) {
    function moodle_system_connect()
    {
        $c = @mysqli_connect('localhost', 'vantage_system', 'we8GynBfHgcCwCRauZKA', 'vantage_system');
        if (!$c) {
            error_log('[moodle] vantage_system connection failed: ' . mysqli_connect_error());
            return null;
        }
        @mysqli_set_charset($c, 'utf8mb4');
        return $c;
    }
}
