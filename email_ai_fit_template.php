<?php
/**
 * email_ai_fit_template.php
 * Takes (a) the chosen template's HTML structure and (b) content extracted from
 * the existing email, and asks AI to populate the template with that content —
 * keeping the template's exact design/structure, only swapping in the real words.
 *
 * POST params:
 *   template_html (required) - the chosen template's HTML (the design to keep)
 *   content       (required) - plain content from the existing email
 *   links         (optional) - comma/space separated URLs to place on buttons
 *
 * Response JSON: { ok:true, html:"<...>" } or { ok:false, error:"..." }
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/ai_config.php';

if (!defined('VASL_OPENAI_KEY') || VASL_OPENAI_KEY === '' || strpos(VASL_OPENAI_KEY, 'REPLACE_WITH') !== false) {
    echo json_encode(['ok' => false, 'error' => 'OpenAI key not configured. Edit ai_config.php.']);
    exit;
}

$template = isset($_POST['template_html']) ? trim($_POST['template_html']) : '';
$content  = isset($_POST['content'])       ? trim($_POST['content'])       : '';
$links    = isset($_POST['links'])         ? trim($_POST['links'])         : '';

if ($template === '') { echo json_encode(['ok' => false, 'error' => 'No template provided.']); exit; }
if ($content === '')  { echo json_encode(['ok' => false, 'error' => 'No content to fit.']); exit; }

// Size guards (tokens / shared hosting)
$template = mb_substr($template, 0, 14000);
$content  = mb_substr($content, 0, 12000);
$links    = mb_substr($links, 0, 1000);

$system = <<<SYS
You are an expert HTML email designer for "Vantage Africa School of Leadership" (VASL).
You are given a TEMPLATE (an existing branded HTML email design) and CONTENT (the real
message extracted from another email). Your job is to PLACE the content into the template,
keeping the template's design EXACTLY, only swapping the words.

ABSOLUTE RULES:
1. KEEP THE TEMPLATE'S STRUCTURE AND STYLING EXACTLY: same sections, same order, same colors,
   same inline styles, same layout. Do NOT redesign.
2. NEVER DROP ANY SECTION. Every structural section that exists in the template MUST exist in
   your output — especially the HEADER and the FOOTER. The footer (the final band with copyright /
   unsubscribe / signature) must be reproduced in full, byte-for-byte, at the end. If you are
   unsure whether something is a section, KEEP IT. Removing a section is a critical failure.
3. NEVER TRUNCATE OR SUMMARISE THE CONTENT. Every point, paragraph, sentence, link, date, price,
   and detail from the CONTENT must appear in the output. Do not shorten, drop, or compress the
   message to make it fit. All of the user's information must be preserved.
4. EXPAND THE TEMPLATE WHEN NEEDED. If the CONTENT has MORE material than the template has slots
   (e.g. more bullet points than benefit cards, more paragraphs than the intro holds), then ADD
   MORE rows/cards/blocks by DUPLICATING the template's existing block markup (same tags, same
   inline styles, same colours) and filling them with the extra content. Keep the visual style of
   the duplicated blocks identical to the originals. It is better to make the email longer than to
   lose any information.
5. REPLACE the placeholder/sample text with the provided CONTENT, fitted sensibly to each section
   (headline -> hero, intro -> intro, supporting points -> benefit cards / body, call to action ->
   buttons). If the content has FEWER points than the template has slots, keep the extra template
   slots but give them generic on-topic text (never obvious fake placeholders).
6. Keep the literal \$name merge tag in the greeting.
7. Place provided links on the appropriate buttons/links (href). Keep existing template hrefs if
   no link is provided for that slot.
8. EMAIL-SAFE: keep everything inline-styled and table/structure as in the template. Do NOT add
   <style>, <script>, <link>, classes-for-styling, or web fonts beyond what the template already has.
9. Output ONLY the resulting inner HTML (no <html>, <head>, <body>, no markdown, no code fences).

SELF-CHECK before answering: (a) Is the footer present and complete? (b) Is every section from the
template still there? (c) Is every detail from the CONTENT included with nothing summarised away?
If any answer is "no", fix it before returning.
SYS;

$linksText = $links !== '' ? $links : '(none provided — keep template hrefs)';

$userPrompt = "TEMPLATE (keep this design exactly, keep ALL sections including the footer, only change the words):\n\n" . $template
            . "\n\n----\n\nCONTENT to place into the template (include ALL of it — do not drop or summarise anything; add more rows if needed):\n\n" . $content
            . "\n\nLinks to use on buttons where appropriate:\n" . $linksText
            . "\n\nReturn the FULL template populated with ALL this content now, footer included.";

$payload = [
    'model' => defined('VASL_OPENAI_MODEL') ? VASL_OPENAI_MODEL : 'gpt-4o',
    'messages' => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' => $userPrompt],
    ],
    'temperature' => 0.3,
    'max_tokens' => 8000,
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
    CURLOPT_TIMEOUT => 90,
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
if ($out === '') { echo json_encode(['ok' => false, 'error' => 'OpenAI returned an empty response.']); exit; }

$out = preg_replace('/^```[a-z]*\s*/i', '', trim($out));
$out = preg_replace('/```\s*$/', '', $out);
$out = preg_replace('#</?(?:html|head|body)[^>]*>#i', '', $out);
$out = preg_replace('#<script[^>]*>.*?</script>#is', '', $out);
$out = trim($out);

// ---- Integrity checks: warn the client if the AI likely cut something ----
$warnings = [];

// 1) Did the API stop because it hit the token cap? (output truncated)
$finish = isset($data['choices'][0]['finish_reason']) ? $data['choices'][0]['finish_reason'] : '';
if ($finish === 'length') {
    $warnings[] = 'The email was long and may have been cut off at the end. Please review the footer/bottom before saving.';
}

// 2) Footer heuristic: if the template clearly had a footer area but the output
//    is much shorter than the template, flag possible loss.
$tpl_low = strtolower($template);
$out_low = strtolower($out);
$tpl_has_footer = (strpos($tpl_low, 'unsubscribe') !== false)
               || (strpos($tpl_low, 'privacy policy') !== false)
               || (strpos($tpl_low, 'all rights reserved') !== false)
               || (strpos($tpl_low, 'footer') !== false);
$out_has_footer = (strpos($out_low, 'unsubscribe') !== false)
               || (strpos($out_low, 'privacy policy') !== false)
               || (strpos($out_low, 'all rights reserved') !== false)
               || (strpos($out_low, 'footer') !== false);
if ($tpl_has_footer && !$out_has_footer) {
    $warnings[] = 'The template footer may not have been preserved. Please check the bottom of the email before saving.';
}

// 3) Big shrink heuristic: output dramatically shorter than the template.
if (mb_strlen($out) < (mb_strlen($template) * 0.5)) {
    $warnings[] = 'The result is noticeably shorter than the template — some sections may be missing. Please review before saving.';
}

echo json_encode([
    'ok' => true,
    'html' => $out,
    'warnings' => $warnings,
    'truncated' => ($finish === 'length'),
]);