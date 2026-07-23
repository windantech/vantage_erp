<?php
/**
 * email_ai_extract.php
 * Takes an email's HTML and returns the plain, design-free CONTENT
 * (the message text + any links), suitable for dropping into the AI
 * "describe the email" box so it can be edited and re-designed.
 *
 * POST params:
 *   html (required) - the saved email HTML
 *
 * Response JSON: { ok:true, content:"...", links:["...", ...] }
 *                or { ok:false, error:"..." }
 *
 * Strategy: do a fast local strip first (no API cost). Only call OpenAI to
 * tidy/summarise into clean prose if a key is configured AND the local strip
 * is messy. This keeps it cheap and quick.
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$html = isset($_POST['html']) ? $_POST['html'] : '';
if (trim($html) === '') {
    echo json_encode(['ok' => false, 'error' => 'No HTML provided.']);
    exit;
}

/* ---- 1) Pull out links (href + button text) before stripping ---- */
$links = [];
if (preg_match_all('#href\s*=\s*"([^"]+)"#i', $html, $m)) {
    foreach ($m[1] as $href) {
        $href = trim($href);
        if ($href !== '' && $href !== '#' && stripos($href, 'http') === 0) {
            $links[] = $href;
        }
    }
}
$links = array_values(array_unique($links));

/* ---- 2) Local strip to readable text ---- */
// Drop scripts/styles, turn block boundaries into newlines, decode entities.
$text = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html);
$text = preg_replace('#<br\s*/?>#i', "\n", $text);
$text = preg_replace('#</(p|div|h[1-6]|tr|li|td)>#i', "\n", $text);
$text = strip_tags($text);
$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
// Collapse excessive whitespace/newlines
$text = preg_replace('#[ \t]+#', ' ', $text);
$text = preg_replace('#\n\s*\n\s*\n+#', "\n\n", $text);
$lines = array_filter(array_map('trim', explode("\n", $text)), function ($l) {
    // Drop boilerplate-only lines
    if ($l === '') return false;
    $low = strtolower($l);
    if ($low === 'school of leadership') return false;
    if (strpos($low, 'unsubscribe') !== false && strlen($l) < 60) return false;
    if (strpos($low, 'privacy policy') !== false && strlen($l) < 60) return false;
    return true;
});
$localContent = trim(implode("\n", $lines));

/* ---- 3) Optionally tidy with OpenAI (only if key set) ---- */
$content = $localContent;

$cfg = __DIR__ . '/ai_config.php';
if (file_exists($cfg)) {
    require_once $cfg;
}

$haveKey = defined('VASL_OPENAI_KEY') && VASL_OPENAI_KEY !== '' && strpos(VASL_OPENAI_KEY, 'REPLACE_WITH') === false;

if ($haveKey && $localContent !== '') {
    $system = "You clean up email text. Given the raw text extracted from an HTML email, "
            . "return ONLY the human-readable message content as clean prose/paragraphs. "
            . "Remove leftover navigation words, button labels repeated as text, and design "
            . "artifacts. Keep the greeting (e.g. 'Hi \$name,'), the core message, and any "
            . "calls to action as plain sentences. Do not add new information. Do not output HTML. "
            . "Keep it concise and faithful to the original.";

    $payload = [
        'model' => defined('VASL_OPENAI_MODEL') ? VASL_OPENAI_MODEL : 'gpt-4o',
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "Raw extracted text:\n\n" . mb_substr($localContent, 0, 6000)],
        ],
        'temperature' => 0.2,
        'max_tokens' => 800,
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
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp !== false && $code === 200) {
        $d = json_decode($resp, true);
        $tidied = isset($d['choices'][0]['message']['content']) ? trim($d['choices'][0]['message']['content']) : '';
        if ($tidied !== '') {
            $content = $tidied;
        }
    }
    // If the API call fails, we silently keep the local strip (no hard error).
}

echo json_encode(['ok' => true, 'content' => $content, 'links' => $links]);
