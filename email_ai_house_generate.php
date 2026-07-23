<?php
/**
 * email_ai_house_generate.php
 * --------------------------------------------------------------------------
 * For the NEW email page. Takes a plain-language DESCRIPTION (what the email
 * should say) + optional links, asks the AI to produce the house template's
 * text field values, then PHP fills house.html (footer + structure safe).
 *
 * POST: message (description), links (optional, comma separated)
 * Returns JSON: { ok:true, html:"...assembled..." } | { ok:false, error:"..." }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/ai_config.php';

define('VASL_HOUSE_TEMPLATE', __DIR__ . '/email_templates_marked/house.html');

$message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
$linksIn = isset($_POST['links']) ? trim((string)$_POST['links']) : '';
if ($message === '') {
    echo json_encode(['ok' => false, 'error' => 'Please describe what the email should say.']);
    exit;
}

$TEXT_FIELDS = [
    'subject_title','hero_badge','headline','greeting','intro',
    'action1_text','action1_btn','action2_text','trainer_name','action3_text','action3_btn',
    'benefits_title','benefits_title_hl',
    'benefit1_title','benefit1_desc','benefit2_title','benefit2_desc',
    'benefit3_title','benefit3_desc','benefit4_title','benefit4_desc',
    'benefit5_title','benefit5_desc','benefit6_title','benefit6_desc',
    'benefit7_title','benefit7_desc','benefit8_title','benefit8_desc',
    'cta_lead','cta_text',
    'price1_title','price1_desc','price1_btn',
    'price2_title','price2_amount','incl1','incl2','incl3','incl4','price2_btn',
    'price3_title','price3_desc','price3_btn',
    'price4_title','price4_desc','price4_btn',
    'body','programme_name',
    'link1_text','link2_text','link3_text','link4_text','link5_text',
];
$LINK_FIELDS = [
    'action1_link','action3_link','cta_link',
    'price1_link','price2_link','price3_link','price4_link',
    'link1_url','link2_url','link3_url','link4_url','link5_url',
];

$DEFAULTS = [
    'subject_title' => 'Vantage Africa Training', 'hero_badge' => 'Vantage Africa',
    'headline' => 'Advance Your Skills With Us', 'greeting' => 'Hi $name,',
    'intro' => 'We are excited to share this with you.',
    'action1_text' => 'See Training Schedule', 'action1_btn' => 'View Schedule', 'action1_link' => 'http://test_link',
    'action2_text' => 'Meet your trainer', 'trainer_name' => 'Dr. Benson Kiarie, PhD',
    'action3_text' => 'Register on our vibrant E-Learning Platform', 'action3_btn' => 'Register Now', 'action3_link' => 'http://portal.vantageafricaleaders.com',
    'benefits_title' => 'This training will', 'benefits_title_hl' => 'elevate your career',
    'benefit1_title' => 'Practical Skills', 'benefit1_desc' => 'Gain hands-on skills you can apply immediately.',
    'benefit2_title' => 'Expert Guidance', 'benefit2_desc' => 'Learn from experienced industry trainers.',
    'benefit3_title' => 'Real Strategies', 'benefit3_desc' => 'Master strategies that deliver real results.',
    'benefit4_title' => 'Networking', 'benefit4_desc' => 'Connect with professionals across the continent.',
    'benefit5_title' => 'Modern Tools', 'benefit5_desc' => 'Work with the latest tools and techniques.',
    'benefit6_title' => 'Better Decisions', 'benefit6_desc' => 'Enhance your decision-making for impact.',
    'benefit7_title' => 'Career Growth', 'benefit7_desc' => 'Set yourself on a path to growth.',
    'benefit8_title' => 'Lasting Support', 'benefit8_desc' => 'Enjoy ongoing support after training.',
    'cta_lead' => 'Hear from professionals whose careers were transformed by this training',
    'cta_text' => 'Register Now', 'cta_link' => 'http://test_link',
    'price1_title' => 'Free with Referral', 'price1_desc' => 'Refer 5 clients and do the course for free.', 'price1_btn' => 'Watch Free', 'price1_link' => 'http://test_link',
    'price2_title' => 'Individual Registration', 'price2_amount' => '$195 only',
    'incl1' => '4 weeks of training', 'incl2' => 'Training materials', 'incl3' => 'Certificates', 'incl4' => 'Alumni Circle',
    'price2_btn' => 'Pay & Register', 'price2_link' => 'http://test_link',
    'price3_title' => 'Team Training', 'price3_desc' => 'Contact the coordinator for team options.', 'price3_btn' => 'Contact Us', 'price3_link' => 'http://test_link',
    'price4_title' => 'Referral Option', 'price4_desc' => 'Refer 5 clients to access the course for free.', 'price4_btn' => 'Learn More', 'price4_link' => 'http://test_link',
    'body' => 'I look forward to an exceptional time with you. See you soon.',
    'programme_name' => 'Training Programme',
    'link1_text' => 'Registration', 'link1_url' => 'http://test_link',
    'link2_text' => 'Payment', 'link2_url' => 'http://test_link',
    'link3_text' => 'Training Schedule', 'link3_url' => 'http://test_link',
    'link4_text' => 'Contact Coordinator', 'link4_url' => 'http://test_link',
    'link5_text' => 'E-Learning Portal', 'link5_url' => 'http://portal.vantageafricaleaders.com',
];

/* normalise provided links into a list */
$providedLinks = [];
if ($linksIn !== '') {
    foreach (preg_split('/[\s,]+/', $linksIn) as $l) {
        $l = trim($l);
        if ($l !== '' && preg_match('#^https?://#i', $l)) $providedLinks[] = $l;
    }
}
$linksText = !empty($providedLinks) ? implode(', ', $providedLinks) : '(none provided)';

function vasl_claude_json($sys, $user, $maxTokens) {
    // Claude's Messages API. The system prompt is a TOP-LEVEL field (not a
    // message). We ask for JSON in the prompt and parse it from content[0].text.
    $payload = [
        'model'      => defined('VASL_ANTHROPIC_MODEL') ? VASL_ANTHROPIC_MODEL : 'claude-sonnet-4-6',
        'max_tokens' => $maxTokens,
        'temperature' => 0.6,
        'system'     => $sys,
        'messages'   => [
            ['role' => 'user', 'content' => $user],
        ],
    ];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'content-type: application/json',
            'x-api-key: ' . VASL_ANTHROPIC_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 90,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp === false) return ['__err' => 'Connection to Claude failed: ' . $err];
    $data = json_decode($resp, true);
    if ($code !== 200) return ['__err' => isset($data['error']['message']) ? $data['error']['message'] : ('Claude HTTP ' . $code)];

    // Claude returns content as an array of blocks; collect the text blocks.
    $text = '';
    if (isset($data['content']) && is_array($data['content'])) {
        foreach ($data['content'] as $block) {
            if (isset($block['type'], $block['text']) && $block['type'] === 'text') {
                $text .= $block['text'];
            }
        }
    }
    if ($text === '') return ['__err' => 'Empty response from Claude.'];

    // Be tolerant if the model wraps JSON in ```json fences or stray prose:
    // extract the first {...} block and parse that.
    $jsonStr = trim($text);
    $jsonStr = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $jsonStr);
    if (preg_match('/\{.*\}/s', $jsonStr, $mm)) {
        $jsonStr = $mm[0];
    }
    $parsed = json_decode($jsonStr, true);
    return is_array($parsed) ? $parsed : ['__err' => 'Could not parse AI response.'];
}

$schemaKeys = array_merge($TEXT_FIELDS, $LINK_FIELDS);
$lines = [];
foreach ($schemaKeys as $k) $lines[] = '  "' . $k . '": "text"';
$schemaJson = "{\n" . implode(",\n", $lines) . "\n}";

$sys = "You write a branded Vantage Africa training email by producing TEXT field values "
     . "for a fixed HTML template. Return ONLY a JSON object with EXACTLY these keys (no others), "
     . "all plain text (no HTML):\n" . $schemaJson . "\n"
     . "RULES:\n"
     . "1. Write all content based on the user's description below. Keep VASL's warm, professional, "
     . "encouraging tone.\n"
     . "2. The template has 3 action buttons (schedule / meet trainer / register), an 8-card benefits "
     . "grid (what the training covers), a 4-column pricing band, a closing message, and 5 footer links. "
     . "Fill ALL of them with relevant, on-topic content for this email's subject.\n"
     . "3. Keep the literal \$name token in greeting (e.g. 'Hi \$name,').\n"
     . "4. For *_link / *_url fields, use one of the provided links if relevant, else leave empty.\n"
     . "5. trainer_name = 'Dr. Benson Kiarie, PhD' unless the description names another trainer.\n"
     . "6. Keep titles/badges/buttons short; descriptions one sentence; intro 1-3 sentences.";

$user = "DESCRIPTION:\n\n" . $message . "\n\nLINKS:\n" . $linksText . "\n\nReturn ONLY the raw JSON object (no markdown, no code fences, no commentary).";
$ex = vasl_claude_json($sys, $user, 4000);
if (isset($ex['__err'])) { echo json_encode(['ok' => false, 'error' => $ex['__err']]); exit; }

if (!is_file(VASL_HOUSE_TEMPLATE)) { echo json_encode(['ok' => false, 'error' => 'house.html not found on server.']); exit; }
$template = file_get_contents(VASL_HOUSE_TEMPLATE);
if ($template === false) { echo json_encode(['ok' => false, 'error' => 'Could not read house.html.']); exit; }

$out = $template;
foreach ($TEXT_FIELDS as $f) {
    $val = (isset($ex[$f]) && trim((string)$ex[$f]) !== '') ? (string)$ex[$f] : (isset($DEFAULTS[$f]) ? $DEFAULTS[$f] : '');
    $val = trim(preg_replace('/<[^>]+>/', '', $val));
    $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    $out = str_replace('{{' . $f . '}}', $val, $out);
}
foreach ($LINK_FIELDS as $f) {
    $val = (isset($ex[$f]) && trim((string)$ex[$f]) !== '') ? trim((string)$ex[$f]) : (isset($DEFAULTS[$f]) ? $DEFAULTS[$f] : 'http://test_link');
    if (!preg_match('#^(https?:|mailto:|/|http://test_link)#i', $val)) {
        $val = isset($DEFAULTS[$f]) ? $DEFAULTS[$f] : 'http://test_link';
    }
    $out = str_replace('{{' . $f . '}}', htmlspecialchars($val, ENT_QUOTES, 'UTF-8'), $out);
}
$out = preg_replace('/\{\{[a-z0-9_]+\}\}/i', '', $out);

echo json_encode(['ok' => true, 'html' => $out]);