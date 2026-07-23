<?php
/**
 * email_house_default.php
 * Returns house.html filled with placeholder DEFAULT text (no AI), so the
 * GrapesJS builder can load the branded structure instantly as a starting point.
 * GET/POST: none. Returns JSON: { ok:true, html:"..." }
 */
header('Content-Type: application/json');

define('VASL_HOUSE_TEMPLATE', __DIR__ . '/email_templates_marked/house.html');

if (!is_file(VASL_HOUSE_TEMPLATE)) { echo json_encode(['ok' => false, 'error' => 'house.html not found.']); exit; }
$tpl = file_get_contents(VASL_HOUSE_TEMPLATE);
if ($tpl === false) { echo json_encode(['ok' => false, 'error' => 'Could not read house.html.']); exit; }

$D = [
    'subject_title' => 'Vantage Africa Training', 'hero_badge' => 'Vantage Africa',
    'headline' => 'Your Headline Goes Here', 'greeting' => 'Hi $name,',
    'intro' => 'Write your opening message here. Replace this with what you want to say.',
    'action1_text' => 'See Training Schedule', 'action1_btn' => 'View Schedule', 'action1_link' => 'http://test_link',
    'action2_text' => 'Meet your trainer', 'trainer_name' => 'Dr. Benson Kiarie, PhD',
    'action3_text' => 'Register on our vibrant E-Learning Platform', 'action3_btn' => 'Register Now', 'action3_link' => 'http://portal.vantageafricaleaders.com',
    'benefits_title' => 'This training will', 'benefits_title_hl' => 'elevate your career',
    'benefit1_title' => 'Benefit One', 'benefit1_desc' => 'Describe this benefit in one short sentence.',
    'benefit2_title' => 'Benefit Two', 'benefit2_desc' => 'Describe this benefit in one short sentence.',
    'benefit3_title' => 'Benefit Three', 'benefit3_desc' => 'Describe this benefit in one short sentence.',
    'benefit4_title' => 'Benefit Four', 'benefit4_desc' => 'Describe this benefit in one short sentence.',
    'benefit5_title' => 'Benefit Five', 'benefit5_desc' => 'Describe this benefit in one short sentence.',
    'benefit6_title' => 'Benefit Six', 'benefit6_desc' => 'Describe this benefit in one short sentence.',
    'benefit7_title' => 'Benefit Seven', 'benefit7_desc' => 'Describe this benefit in one short sentence.',
    'benefit8_title' => 'Benefit Eight', 'benefit8_desc' => 'Describe this benefit in one short sentence.',
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

$out = $tpl;
foreach ($D as $k => $v) {
    $out = str_replace('{{' . $k . '}}', htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), $out);
}
$out = preg_replace('/\{\{[a-z0-9_]+\}\}/i', '', $out);

echo json_encode(['ok' => true, 'html' => $out]);
