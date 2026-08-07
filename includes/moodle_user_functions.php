<?php
/**
 * Shared logic: create Moodle (mdl_user) account and optionally send credentials email.
 * Username: firstnamelastname (lowercase). Password: firstnamelastname@year.
 * Returns: ['success' => bool, 'username' => string, 'password' => string, 'error' => string|null]
 */

if (!function_exists('send_moodle_existing_user_reset_email')) {
    /**
     * Send email to existing Moodle user with login URL and password reset link.
     * Requires send_mail_function to be available. Returns true if sent.
     */
    function send_moodle_existing_user_reset_email($email, $recipient_name, $username = '', $portalOverride = null) {
        if (!function_exists('send_mail_function')) {
            return false;
        }
        $year = date('Y');
        // Optional per-registration portal override (e.g. academic registrations
        // pass the LMS at system.vantageafricaleaders.com). When absent, keep the
        // default e-learning portal wording + vantageafricaleaders.com/moodle links.
        $has_override = (is_array($portalOverride) && !empty($portalOverride['login_url']));
        $portal_title = (is_array($portalOverride) && !empty($portalOverride['portal_title'])) ? $portalOverride['portal_title'] : 'Your E-learning Portal Access';
        $portal_name  = $has_override ? 'learning management system' : 'e-learning portal';
        if ($has_override) {
            $login_url = $portalOverride['login_url'];
            $pu        = parse_url($login_url);
            $host_base = ($pu['scheme'] ?? 'https') . '://' . ($pu['host'] ?? '');
            $reset_url = $host_base . '/login/forgot_password.php';
        } else {
            $login_url = 'https://vantageafricaleaders.com/moodle/login/index.php';
            $reset_url = 'https://vantageafricaleaders.com/moodle/login/forgot_password.php';
        }
        $subject = $portal_title . ' - Vantage Africa School of Leadership';
        $support_phone = '+254796393864';
        $support_whatsapp = 'https://wa.me/254796393864';
        $body = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px;">
        <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="background: linear-gradient(135deg, #8B4513, #A0522D); padding: 20px; text-align: center;">
                <img src="https://vantageafricaleaders.com/assets/logo.png" style="height: 50px; padding: 0.5rem 0;" alt="Vantage Africa">
            </div>
            <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
            <div style="padding: 0 1.5rem 1rem;">
                <h5 style="color: #2B5470;"><b>' . htmlspecialchars($portal_title) . '</b></h5>
                <p><strong>Dear ' . htmlspecialchars($recipient_name) . ',</strong></p>
                <p>You already have an account on our ' . htmlspecialchars($portal_name) . '. To log in or reset your password, use the links below:</p>
                <div style="background-color: #f0f8ff; padding: 15px; border: 1px solid #A85431; margin: 15px 0; border-radius: 5px;">
                    <p style="margin: 5px 0;"><strong>🌐 Log in:</strong> <a href="' . htmlspecialchars($login_url) . '" style="color: #2B5470; font-weight: bold;">' . htmlspecialchars($login_url) . '</a></p>
                    <p style="margin: 5px 0;"><strong>🔑 Forgot password?</strong> <a href="' . htmlspecialchars($reset_url) . '" style="color: #2B5470; font-weight: bold;">Reset your password here</a></p>
                    ' . ($username !== '' ? '<p style="margin: 5px 0;"><strong>👤 Username:</strong> ' . htmlspecialchars($username) . '</p>' : '') . '
                </div>
                                <p style="font-size: 12px; color: #6b5b47;">Policies:
                    <a href="https://vantageafricaleaders.com/policies.php#terms" style="color: #8B4513;">Terms</a> |
                    <a href="https://vantageafricaleaders.com/policies.php#refund" style="color: #8B4513;">Refund</a> |
                    <a href="https://vantageafricaleaders.com/policies.php#privacy" style="color: #8B4513;">Privacy</a>
                </p>
                <p><strong>Warm regards,</strong><br><span style="color: #A85431; font-weight: bold;">Vantage Africa School of Leadership</span></p>
            </div>
            <div style="padding: .5rem 1.5rem; border-top: solid 1px #d1d3e2; text-align: center;">
                <div style="color: #9ba4b3; font-size: .8rem;">We sent this email to <span>' . htmlspecialchars($email) . '</span></div>
                <div style="color: #9ba4b3; font-size: .8rem;">&copy; ' . $year . ' Vantage Africa School of Leadership. All Rights Reserved.</div>
            </div>
        </div>
    </body></html>';
        return (bool) send_mail_function($email, $body, $subject, []);
    }
}

if (!function_exists('create_moodle_user_and_send_email')) {
    function create_moodle_user_and_send_email($moodle_conn, $email, $firstname, $lastname, $phone = '', $country = '', $organization = '', $send_email = true, $portalOverride = null) {
        $firstname = trim((string) $firstname);
        $lastname = trim((string) $lastname);
        $email = trim((string) $email);
        $phone = trim((string) $phone);
        $country = trim((string) $country);
        $organization = trim((string) $organization);

        if ($firstname === '' && $lastname === '') {
            return ['success' => false, 'username' => '', 'password' => '', 'error' => 'Name required.'];
        }
        if ($email === '') {
            return ['success' => false, 'username' => '', 'password' => '', 'error' => 'Email required.'];
        }
        if (!$moodle_conn || mysqli_connect_error()) {
            return ['success' => false, 'username' => '', 'password' => '', 'error' => 'E-learning database unavailable.'];
        }

        $year = date('Y');
        $username = strtolower(preg_replace('/\s+/', '', $firstname . $lastname));
        $plain_password = $username . '@' . $year;
        $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);

        $table = 'mdl_user';
        $user_esc = mysqli_real_escape_string($moodle_conn, $username);
        $email_esc = mysqli_real_escape_string($moodle_conn, $email);
        $check = mysqli_query($moodle_conn, "SELECT id FROM $table WHERE username = '$user_esc' OR email = '$email_esc' LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            $email_sent = false;
            if ($send_email && function_exists('send_moodle_existing_user_reset_email')) {
                $recipient_name = trim($firstname . ' ' . $lastname);
                $email_sent = send_moodle_existing_user_reset_email($email, $recipient_name, $username, $portalOverride);
            }
            return ['success' => false, 'username' => $username, 'password' => $plain_password, 'error' => 'already_created', 'email_sent' => $email_sent];
        }

        $country_code = strlen($country) === 2 ? $country : substr(preg_replace('/[^A-Za-z]/', '', $country), 0, 2);
        if ($country_code === '') {
            $country_code = 'US';
        }
        $country_code = strtoupper(substr($country_code, 0, 2));

        $firstname_esc = mysqli_real_escape_string($moodle_conn, $firstname);
        $lastname_esc = mysqli_real_escape_string($moodle_conn, $lastname);
        $phone_esc = mysqli_real_escape_string($moodle_conn, $phone);
        $org_esc = mysqli_real_escape_string($moodle_conn, $organization);
        $country_esc = mysqli_real_escape_string($moodle_conn, $country_code);
        $pass_esc = mysqli_real_escape_string($moodle_conn, $password_hash);
        $time = time();

        $sql = "INSERT INTO $table (
    auth, confirmed, policyagreed, deleted, suspended, mnethostid,
    username, password, idnumber, firstname, lastname, email, emailstop,
    phone1, phone2, institution, department, address, city, country,
    lang, calendartype, theme, timezone, firstaccess, lastaccess, lastlogin,
    currentlogin, lastip, secret, picture, description, descriptionformat,
    mailformat, maildigest, maildisplay, autosubscribe, trackforums,
    timecreated, timemodified, trustbitmask
) VALUES (
    'manual', 1, 0, 0, 0, 1,
    '$user_esc', '$pass_esc', '', '$firstname_esc', '$lastname_esc', '$email_esc', 0,
    '$phone_esc', '', '$org_esc', '', '', '', '$country_esc',
    'en', 'gregorian', '', '99', 0, 0, 0,
    0, '', '', 0, NULL, 1,
    1, 0, 2, 1, 0,
    $time, $time, 0
)";

        if (!mysqli_query($moodle_conn, $sql)) {
            return ['success' => false, 'username' => $username, 'password' => $plain_password, 'error' => mysqli_error($moodle_conn)];
        }

        $email_sent = false;
        if ($send_email && function_exists('send_mail_function')) {
            $recipient_name = trim($firstname . ' ' . $lastname);
            $has_override = (is_array($portalOverride) && !empty($portalOverride['login_url']));
            $portal_title = (is_array($portalOverride) && !empty($portalOverride['portal_title'])) ? $portalOverride['portal_title'] : 'Your E-learning Portal Access';
            $created_line = $has_override ? 'Your learning management system account has been created. Here are your login credentials:' : 'Your learning portal account has been created. Here are your login credentials:';
            if ($has_override) {
                $login_url   = $portalOverride['login_url'];
                $login_label = parse_url($login_url, PHP_URL_HOST) ?: $login_url;
            } else {
                $login_url   = 'https://vantageafricaleaders.com/moodle/login/index.php';
                $login_label = 'vantageafricaleaders.com/moodle/login';
            }
            $subject = $portal_title . ' - Vantage Africa School of Leadership';
            $support_phone = '+254796393864';
            $support_whatsapp = 'https://wa.me/254796393864';
            $body = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px;">
        <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="background: linear-gradient(135deg, #8B4513, #A0522D); padding: 20px; text-align: center;">
                <img src="https://vantageafricaleaders.com/assets/logo.png" style="height: 50px; padding: 0.5rem 0;" alt="Vantage Africa">
            </div>
            <hr style="border: solid 1px #d1d3e2; margin: 8px 0">
            <div style="padding: 0 1.5rem 1rem;">
                <h5 style="color: #2B5470;"><b>' . htmlspecialchars($portal_title) . '</b></h5>
                <p><strong>Dear ' . htmlspecialchars($recipient_name) . ',</strong></p>
                <p>' . htmlspecialchars($created_line) . '</p>
                <div style="background-color: #f0f8ff; padding: 15px; border: 1px solid #A85431; margin: 15px 0; border-radius: 5px;">
                    <p style="margin: 5px 0;"><strong>🌐 Portal URL:</strong> <a href="' . htmlspecialchars($login_url) . '" style="color: #2B5470; font-weight: bold;">' . htmlspecialchars($login_label) . '</a></p>
                    <p style="margin: 5px 0;"><strong>👤 Username:</strong> ' . htmlspecialchars($username) . '</p>
                    <p style="margin: 5px 0;"><strong>🔑 Password:</strong> ' . htmlspecialchars($plain_password) . '</p>
                </div>
                <div style="background-color: #fff3cd; padding: 15px; border: 1px solid #ffc107; margin: 15px 0; border-radius: 5px;">
                    <p style="margin: 0; font-weight: bold; color: #856404;">🔒 Please change your password after your first login for security.</p>
                </div>
                                <p style="font-size: 12px; color: #6b5b47;">Policies:
                    <a href="https://vantageafricaleaders.com/policies.php#terms" style="color: #8B4513;">Terms</a> |
                    <a href="https://vantageafricaleaders.com/policies.php#refund" style="color: #8B4513;">Refund</a> |
                    <a href="https://vantageafricaleaders.com/policies.php#privacy" style="color: #8B4513;">Privacy</a>
                </p>
                <p><strong>Warm regards,</strong><br><span style="color: #A85431; font-weight: bold;">Vantage Africa School of Leadership</span></p>
            </div>
            <div style="padding: .5rem 1.5rem; border-top: solid 1px #d1d3e2; text-align: center;">
                <div style="color: #9ba4b3; font-size: .8rem;">We sent this email to <span>' . htmlspecialchars($email) . '</span></div>
                <div style="color: #9ba4b3; font-size: .8rem;">&copy; ' . $year . ' Vantage Africa School of Leadership. All Rights Reserved.</div>
            </div>
        </div>
    </body></html>';
            $email_sent = send_mail_function($email, $body, $subject, []);
        }

        return ['success' => true, 'username' => $username, 'password' => $plain_password, 'error' => null, 'email_sent' => $email_sent];
    }
}
