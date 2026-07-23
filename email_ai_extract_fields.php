<?php
/**
 * email_ai_extract_fields.php
 * --------------------------------------------------------------------------
 * Reads an existing email's HTML and returns its MESSAGE as small text fields:
 *   headline, greeting, intro, body, cta_text, cta_link
 * The AI only ever produces these short text values — never HTML. The values
 * are later merged into a marked template by PHP (email_fill_template.php),
 * so the template structure and footer can never be lost.
 *
 * POST: html  (the existing email body HTML)
 * Returns JSON: { ok:true, fields:{...} }  or  { ok:false, error:"..." }
 */

header('Content-Type: application/json');
require_once __DIR__ . '/ai_config.php';

$srcHtml = isset($_POST['html']) ? (string)$_POST['html'] : '';
if (trim($srcHtml) === '') {
    echo json_encode(['ok' => false, 'error' => 'No email content provided to read.']);
    exit;
}

// Strip tags to give the model the readable message (keeps it cheap & focused).
$plain = preg_replace('/<\s*(script|style)[^>]*>.*?<\/\s*\1\s*>/is', ' ', $srcHtml);
$plain = preg_replace('/<[^>]+>/', ' ', $plain);
$plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$plain = preg_replace('/\s+/', ' ', $plain);
$plain = trim(mb_substr($plain, 0, 12000));

// Also pull any links so the AI can suggest a CTA link.
$links = [];
if (preg_match_all('/href\s*=\s*"([^"]+)"/i', $srcHtml, $m)) {
    foreach ($m[1] as $href) {
        $href = trim($href);
        if ($href !== '' && $href !== '#' && stripos($href, 'mailto:') !== 0 && stripos($href, 'tel:') !== 0) {
            $links[$href] = true;
        }
    }
}
$linksText = !empty($links) ? implode(', ', array_keys($links)) : '(none found)';

$sys = <<<SYS
You read a marketing/training email and return its message as SHORT TEXT FIELDS only.
You NEVER return HTML. You return ONLY a JSON object with exactly these keys:

{
  "headline": "a short punchy hero headline (max ~8 words)",
  "greeting": "the greeting line, keeping the literal \$name merge tag, e.g. 'Hi \$name,'",
  "intro": "the opening paragraph in 1-3 sentences",
  "body": "the closing/encouragement message in 1-3 sentences",
  "cta_text": "short call-to-action button label (max ~5 words)",
  "cta_link": "the most relevant URL from the provided links, or empty string",
  "benefits_title": "lead-in for the benefits section, e.g. 'This training will'",
  "benefits_title_hl": "the highlighted end of that title, e.g. 'elevate your career'",
  "benefit1_title": "short benefit heading (max ~4 words)",
  "benefit1_desc": "one-sentence benefit description",
  "benefit2_title": "...", "benefit2_desc": "...",
  "benefit3_title": "...", "benefit3_desc": "...",
  "benefit4_title": "...", "benefit4_desc": "...",
  "benefit5_title": "...", "benefit5_desc": "...",
  "benefit6_title": "...", "benefit6_desc": "...",
  "benefit7_title": "...", "benefit7_desc": "...",
  "benefit8_title": "...", "benefit8_desc": "...",
  "price1_title": "label for the free/intro option",
  "price1_desc": "one-sentence description",
  "price2_title": "label for the individual registration option",
  "price2_amount": "the price, e.g. '\$195 only'",
  "price3_title": "label for the team training option",
  "price3_desc": "one-sentence description",
  "price4_title": "label for the referral option",
  "price4_desc": "one-sentence description",
  "incl1": "first item included in the registration (e.g. '5 weeks of training')",
  "incl2": "second item included",
  "incl3": "third item included",
  "incl4": "fourth item included",
  "programme_name": "short name of this programme for the footer (e.g. 'Trainer of Trainers Programme')",
  "subject_title": "a short page title for this email"
}

RULES:
- Output ONLY the JSON object. No markdown, no code fences, no commentary.
- Keep the literal \$name token in greeting if a name greeting exists; otherwise use "Hi \$name,".
- Plain text values only (no HTML tags). Keep VASL's warm, professional tone.
- Base the benefits and pricing on THIS email's actual subject/topic — if it is a
  Trainer of Trainers email, the benefits must be about training others, NOT about M&E.
  Make all 8 benefits and the pricing relevant to the email's real course/topic.
- If a field can't be found, write a sensible on-topic value (do not invent fake links).
SYS;

$userPrompt = "EMAIL MESSAGE (plain text):\n\n" . $plain
            . "\n\nLINKS FOUND IN THE EMAIL:\n" . $linksText
            . "\n\nReturn the JSON now.";

$payload = [
    'model' => defined('VASL_OPENAI_MODEL') ? VASL_OPENAI_MODEL : 'gpt-4o',
    'messages' => [
        ['role' => 'system', 'content' => $sys],
        ['role' => 'user', 'content' => $userPrompt],
    ],
    'temperature' => 0.3,
    'max_tokens' => 2500,
    'response_format' => ['type' => 'json_object'],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . VASL_OPENAI_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 60,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['ok' => false, 'error' => 'Connection to OpenAI failed: ' . $curlErr]);
    exit;
}

$data = json_decode($response, true);
if ($httpCode !== 200) {
    $msg = isset($data['error']['message']) ? $data['error']['message'] : ('OpenAI returned HTTP ' . $httpCode);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$out = isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : '';
$fields = json_decode($out, true);
if (!is_array($fields)) {
    echo json_encode(['ok' => false, 'error' => 'Could not parse the extracted fields.']);
    exit;
}

// Normalise + ensure all keys exist as plain strings (strip any stray HTML).
$keys = [
    'headline', 'greeting', 'intro', 'body', 'cta_text', 'cta_link',
    'benefits_title', 'benefits_title_hl',
    'benefit1_title','benefit1_desc','benefit2_title','benefit2_desc',
    'benefit3_title','benefit3_desc','benefit4_title','benefit4_desc',
    'benefit5_title','benefit5_desc','benefit6_title','benefit6_desc',
    'benefit7_title','benefit7_desc','benefit8_title','benefit8_desc',
    'price1_title','price1_desc','price2_title','price2_amount',
    'price3_title','price3_desc','price4_title','price4_desc',
    'incl1','incl2','incl3','incl4','programme_name','subject_title',
];
$clean = [];
foreach ($keys as $k) {
    $v = isset($fields[$k]) ? (string)$fields[$k] : '';
    if ($k !== 'cta_link') {
        $v = trim(preg_replace('/<[^>]+>/', '', $v)); // no HTML in text fields
    } else {
        $v = trim($v);
    }
    $clean[$k] = $v;
}
if ($clean['greeting'] === '') {
    $clean['greeting'] = 'Hi $name,';
}

echo json_encode(['ok' => true, 'fields' => $clean]);
