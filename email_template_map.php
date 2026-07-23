<?php
/**
 * email_template_map.php
 * --------------------------------------------------------------------------
 * Maps an email (by type + email number) to a MARKED template file in
 * email_templates_marked/. Markers in those files ({{headline}}, {{greeting}},
 * {{intro}}, {{body}}, {{cta_text}}, {{cta_link}}) are filled by PHP via
 * str_replace — the AI only ever supplies those small text values, never HTML,
 * so the template's structure and footer can never be lost.
 *
 * To add/learn a new template: drop a marked .html file in
 * email_templates_marked/ and add a line to $VASL_TEMPLATE_MAP below.
 */

// Folder holding the marked templates (auto-loaded, no manual selection).
if (!defined('VASL_MARKED_TEMPLATE_DIR')) {
    define('VASL_MARKED_TEMPLATE_DIR', __DIR__ . '/email_templates_marked/');
}

/*
 * Map: "<type>:<email_number>" => marked template filename.
 * type is 'virtual' or 'international'. Use 'default' as a fallback per type.
 * Adjust the numbers to match your real email schedule.
 */
$VASL_TEMPLATE_MAP = [
    // Virtual M&E course sequence
    'virtual:default' => 'me_email_template.html',
    'virtual:1'       => 'me_email_template.html',
    'virtual:6'       => 'me_email_template.html',   // "You can still register"
    // International events fall back to the same house design for now
    'international:default' => 'me_email_template.html',
];

/**
 * Resolve a marked template file path for a given type + email number.
 * Returns an absolute path, or '' if no template file is found on disk.
 */
function vasl_resolve_template($email_type, $email_no)
{
    global $VASL_TEMPLATE_MAP;

    $type = ($email_type === 'international') ? 'international' : 'virtual';
    $no   = trim((string)$email_no);

    $candidates = [];
    if ($no !== '') {
        $candidates[] = $type . ':' . $no;
    }
    $candidates[] = $type . ':default';
    $candidates[] = 'virtual:default'; // last resort

    foreach ($candidates as $key) {
        if (isset($VASL_TEMPLATE_MAP[$key])) {
            $path = VASL_MARKED_TEMPLATE_DIR . $VASL_TEMPLATE_MAP[$key];
            if (is_file($path)) {
                return $path;
            }
        }
    }
    return '';
}
