<?php
/**
 * Minimal per-session CSRF tokens.
 *
 * The WhatsApp module has never had CSRF protection. Retrofitting every action at
 * once is a large, risky change, so this file exists to protect the new one first:
 * requesting call permission spends money, pushes a prompt to a real customer's
 * handset, and burns one of only two requests allowed in seven days. A forged POST
 * is therefore materially worse than the existing actions, which at most send a
 * message a rep could have sent anyway.
 *
 * Written to be reusable: wa_csrf_field() in a form, wa_csrf_check() in the
 * handler. Existing actions can adopt it later without changing this file.
 */

if (!function_exists('wa_csrf_token')) {
    /** The session's token, created on first use. */
    function wa_csrf_token() {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        if (empty($_SESSION['wa_csrf'])) {
            // random_bytes is cryptographically secure; a predictable token is no
            // protection at all, so there is deliberately no weaker fallback.
            $_SESSION['wa_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['wa_csrf'];
    }
}

if (!function_exists('wa_csrf_field')) {
    /** Hidden input to drop inside a <form>. Escaped for an HTML attribute. */
    function wa_csrf_field() {
        $t = htmlspecialchars(wa_csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="wa_csrf" value="' . $t . '">';
    }
}

if (!function_exists('wa_csrf_valid')) {
    /** Constant-time comparison against the session token. */
    function wa_csrf_valid($submitted) {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $expected = (string)($_SESSION['wa_csrf'] ?? '');
        $given    = (string)$submitted;
        // No token in the session means nothing has been issued: reject rather than
        // treating "both empty" as a match.
        if ($expected === '' || $given === '') { return false; }
        return hash_equals($expected, $given);
    }
}

if (!function_exists('wa_csrf_check')) {
    /** Validate $_POST['wa_csrf']; returns false so the caller can flash+redirect. */
    function wa_csrf_check() {
        return wa_csrf_valid($_POST['wa_csrf'] ?? '');
    }
}
