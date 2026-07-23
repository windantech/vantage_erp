<?php
/**
 * email_fill_template.php
 * --------------------------------------------------------------------------
 * Picks the marked template for this email (by type + number), then fills its
 * {{markers}} with the supplied TEXT fields using a plain str_replace. The
 * template's structure, bands, and FOOTER are never sent anywhere and never
 * rewritten — so they cannot be lost. This is the deterministic step that
 * makes the whole approach safe.
 *
 * POST:
 *   email_type   virtual | international
 *   email_no     the email number (1..18) — used to auto-pick the template
 *   fields       JSON: { headline, greeting, intro, body, cta_text, cta_link }
 *
 * Returns JSON: { ok:true, html:"...assembled inner HTML..." }
 *            or { ok:false, error:"..." }
 */

header('Content-Type: application/json');
require_once __DIR__ . '/email_template_map.php';

$email_type = isset($_POST['email_type']) ? trim((string)$_POST['email_type']) : 'virtual';
$email_no   = isset($_POST['email_no'])   ? trim((string)$_POST['email_no'])   : '';
$fieldsRaw  = isset($_POST['fields'])     ? (string)$_POST['fields']           : '';

$fields = json_decode($fieldsRaw, true);
if (!is_array($fields)) {
    $fields = [];
}

// Resolve the template file (auto-selected; no manual pick).
$tplPath = vasl_resolve_template($email_type, $email_no);
if ($tplPath === '') {
    echo json_encode(['ok' => false, 'error' => 'No template is mapped for this email type/number yet.']);
    exit;
}

$template = file_get_contents($tplPath);
if ($template === false) {
    echo json_encode(['ok' => false, 'error' => 'Could not read the template file.']);
    exit;
}

// Defaults so a missing field never leaves a raw {{marker}} in the output.
$defaults = [
    'headline'  => 'An update from Vantage Africa',
    'greeting'  => 'Hi $name,',
    'intro'     => '',
    'body'      => '',
    'cta_text'  => 'Learn More',
    'cta_link'  => 'http://test_link',
    'benefits_title'    => 'This training will',
    'benefits_title_hl' => 'elevate your career',
    'benefit1_title' => '', 'benefit1_desc' => '',
    'benefit2_title' => '', 'benefit2_desc' => '',
    'benefit3_title' => '', 'benefit3_desc' => '',
    'benefit4_title' => '', 'benefit4_desc' => '',
    'benefit5_title' => '', 'benefit5_desc' => '',
    'benefit6_title' => '', 'benefit6_desc' => '',
    'benefit7_title' => '', 'benefit7_desc' => '',
    'benefit8_title' => '', 'benefit8_desc' => '',
    'price1_title' => '', 'price1_desc' => '',
    'price2_title' => '', 'price2_amount' => '',
    'price3_title' => '', 'price3_desc' => '',
    'price4_title' => '', 'price4_desc' => '',
    // Pricing inclusions bullet list + footer/title labels
    'incl1' => '5 weeks of training',
    'incl2' => 'Training materials',
    'incl3' => 'Certificate of completion',
    'incl4' => 'Membership fee',
    'programme_name' => 'Training Programme',
    'subject_title'  => 'Vantage Africa School of Leadership',
];

$markers = array_keys($defaults);
$linkMarkers = ['cta_link']; // markers that hold a URL, not display text
$out = $template;
foreach ($markers as $m) {
    $val = isset($fields[$m]) && trim((string)$fields[$m]) !== ''
        ? (string)$fields[$m]
        : $defaults[$m];

    if (in_array($m, $linkMarkers, true)) {
        $val = trim($val);
        if ($val !== '' && !preg_match('#^(https?:|mailto:|/|http://test_link)#i', $val)) {
            $val = $defaults['cta_link'];
        }
        if ($val === '') $val = $defaults['cta_link'];
    } else {
        $val = trim(preg_replace('/<[^>]+>/', '', $val));
        $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    }

    $out = str_replace('{{' . $m . '}}', $val, $out);
}

// Safety: if any unfilled markers remain, blank them so they never show.
$out = preg_replace('/\{\{[a-z_]+\}\}/i', '', $out);

echo json_encode(['ok' => true, 'html' => $out]);
