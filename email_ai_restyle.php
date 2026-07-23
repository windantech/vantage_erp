<?php
/**
 * email_ai_restyle.php
 * Takes an existing email's HTML and re-skins it into VASL brand colors while
 * PRESERVING the original structure (same sections, order, layout). Minor
 * polish (spacing, button styling) is allowed; no new sections are invented.
 *
 * Optionally accepts edited content to weave in (override) without changing
 * the structure.
 *
 * POST params:
 *   html    (required) - the original email HTML
 *   content (optional) - edited plain content to substitute into the same structure
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

$html    = isset($_POST['html'])    ? trim($_POST['html'])    : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if ($html === '') {
    echo json_encode(['ok' => false, 'error' => 'No email HTML provided to restyle.']);
    exit;
}

// Guard payload size (shared hosting + token limits)
$html = mb_substr($html, 0, 12000);
$content = mb_substr($content, 0, 6000);

$system = <<<SYS
You are an expert HTML email designer for "Vantage Africa School of Leadership" (VASL).
Your job is to RE-SKIN an existing email into VASL brand colors while KEEPING ITS
STRUCTURE. You are NOT redesigning or composing a new email.

ABSOLUTE RULES:
1. PRESERVE STRUCTURE: keep the same sections, the same order, the same layout and
   hierarchy as the input. Do not add new sections. Do not remove meaningful sections.
   Keep the same headings, paragraphs, lists, tables, columns, and buttons in the same places.
2. ONLY change the VISUAL STYLING to VASL branding:
   - VASL colors ONLY: Maroon #7a1c2e, Wine #5a1020, Dark #3a1010, Gold #c0a040,
     Light gold #e8c55a, Ink #1a0a0a, Cream #fdf6e3, white #ffffff.
   - Apply these as backgrounds/text/borders sensibly: dark bands get white or light-gold
     text; primary buttons become gold (#c0a040) with dark text; accents/links use maroon or gold.
   - Replace any off-brand colors with the nearest VASL color.
3. MINOR POLISH ALLOWED: you may tidy spacing/padding, round buttons, and align elements
   for a clean look, but do NOT restructure or rewrite the layout.
4. EMAIL-SAFE / GMAIL-FRIENDLY:
   - All styling INLINE via style="". No <style>, <script>, <link>, classes-for-styling, web fonts.
   - Use table-based layout for columns. Font stack 'Segoe UI', Arial, sans-serif. Max width 600px, centered.
   - Solid background colors only (no background images).
5. KEEP CONTENT: keep the existing text and the \$name merge tag. Keep all existing links/hrefs.
6. Output ONLY the inner HTML (no <html>, <head>, <body>, <style>, <script>, no markdown, no code fences).
SYS;

$userPrompt = "Here is the original email HTML. Re-skin it into VASL colors, keeping its structure:\n\n"
            . $html;

if ($content !== '') {
    $userPrompt .= "\n\nThe user edited the wording. Where it fits the SAME structure, use this updated content "
                 . "instead of the original text (do not change the layout, only the words):\n\n" . $content;
}

$userPrompt .= "\n\nReturn the recolored email HTML now, same structure, VASL colors.";

$payload = [
    'model' => defined('VASL_OPENAI_MODEL') ? VASL_OPENAI_MODEL : 'gpt-4o',
    'messages' => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' => $userPrompt],
    ],
    'temperature' => 0.3,
    'max_tokens' => 3000,
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
if ($out === '') {
    echo json_encode(['ok' => false, 'error' => 'OpenAI returned an empty response.']);
    exit;
}

// Clean up any fences/wrappers the model may add
$out = preg_replace('/^```[a-z]*\s*/i', '', trim($out));
$out = preg_replace('/```\s*$/', '', $out);
$out = preg_replace('#</?(?:html|head|body)[^>]*>#i', '', $out);
$out = preg_replace('#<style[^>]*>.*?</style>#is', '', $out);
$out = preg_replace('#<script[^>]*>.*?</script>#is', '', $out);

echo json_encode(['ok' => true, 'html' => trim($out)]);
