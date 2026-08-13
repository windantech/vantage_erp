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

if (!function_exists('vasl_learner_ledger_write')) {
    /**
     * Store a learner's academic selection (course, level, units, final total) in the LMS DB
     * so the LMS dev can just fetch and display it. Installments are chosen on the frontend, so
     * we store the FINAL selected price only — no payment/balance tracking here. Self-provisions
     * the table. $conn must be the vantage_system connection (same DB the mdl_user lives in).
     */
    function vasl_learner_ledger_write($conn, $moodle_user_id, $email, array $selection)
    {
        if (!$conn) {
            return false;
        }
        $conn->query(
            "CREATE TABLE IF NOT EXISTS `vasl_learner_ledger` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `moodle_user_id` INT DEFAULT NULL,
                `email` VARCHAR(190) DEFAULT NULL,
                `program` VARCHAR(255) DEFAULT NULL,
                `level` VARCHAR(190) DEFAULT NULL,
                `units` TEXT DEFAULT NULL,
                `unit_count` INT DEFAULT 0,
                `total_amount` DECIMAL(12,2) DEFAULT 0,
                `currency` VARCHAR(10) DEFAULT 'KES',
                `source_ref` VARCHAR(190) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user` (`moodle_user_id`),
                KEY `idx_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $units      = (isset($selection['units']) && is_array($selection['units'])) ? array_values($selection['units']) : [];
        $units_json = json_encode($units, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $program    = (string) ($selection['program'] ?? '');
        $level      = (string) ($selection['level'] ?? '');
        $unit_count = (int) ($selection['unit_count'] ?? count($units));
        $total      = (float) preg_replace('/[^0-9.]/', '', (string) ($selection['total_amount'] ?? 0));
        $currency   = (string) ($selection['currency'] ?? 'KES');
        $source_ref = (string) ($selection['source_ref'] ?? '');
        $uid        = (int) $moodle_user_id;
        $email      = (string) $email;

        $stmt = $conn->prepare(
            "INSERT INTO `vasl_learner_ledger`
                (moodle_user_id, email, program, level, units, unit_count, total_amount, currency, source_ref)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            error_log('[moodle] learner ledger prepare failed: ' . $conn->error);
            return false;
        }
        $stmt->bind_param('issssidss', $uid, $email, $program, $level, $units_json, $unit_count, $total, $currency, $source_ref);
        $ok = $stmt->execute();
        if (!$ok) {
            error_log('[moodle] learner ledger insert failed: ' . $stmt->error);
        }
        $stmt->close();
        return (bool) $ok;
    }
}
