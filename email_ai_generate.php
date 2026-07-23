<?php
/**
 * email_ai_generate.php
 * Receives a user's message + optional links, asks OpenAI (gpt-4o) to produce
 * an email-safe, VASL-branded HTML email body, and returns it as JSON.
 *
 * POST params:
 *   message  (required) - what the email should say / instructions
 *   links    (optional) - newline or comma separated URLs to include as buttons/links
 *   purpose  (optional) - short label e.g. "Register Today" to steer tone
 *
 * Response JSON: { ok: true, html: "<...>" }  or  { ok: false, error: "..." }
 */

header('Content-Type: application/json');

// --- Only allow POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/ai_config.php';

if (!defined('VASL_OPENAI_KEY') || VASL_OPENAI_KEY === '' || strpos(VASL_OPENAI_KEY, 'REPLACE_WITH') !== false) {
    echo json_encode(['ok' => false, 'error' => 'OpenAI key not configured. Edit ai_config.php.']);
    exit;
}

$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$links   = isset($_POST['links'])   ? trim($_POST['links'])   : '';
$purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';

if ($message === '') {
    echo json_encode(['ok' => false, 'error' => 'Please enter a message describing the email.']);
    exit;
}

// Normalise links into a clean list
$linkList = [];
if ($links !== '') {
    foreach (preg_split('/[\s,]+/', $links) as $l) {
        $l = trim($l);
        if ($l !== '' && preg_match('#^https?://#i', $l)) {
            $linkList[] = $l;
        }
    }
}
$linksText = empty($linkList) ? '(none provided)' : implode("\n", $linkList);

// ---- Strict system prompt: enforce VASL branding + email safety ----
$system = <<<SYS
You are an expert HTML email designer for "Vantage Africa School of Leadership" (VASL),
a Kenyan institution offering leadership and professional training.

Produce ONLY the inner HTML for an email body (no <html>, <head>, <body>, <style>,
<script>, no markdown, no code fences). Output raw HTML only.

STRICT REQUIREMENTS:
1. EMAIL-SAFE / GMAIL-FRIENDLY & RESPONSIVE:
   - Use table-based layout (<table>, <tr>, <td>) for any columns. No flexbox, no grid.
   - ALL styling must be INLINE via style="" attributes. Never use <style> blocks or classes for styling.
   - No external CSS, no web fonts, no <link>. Font stack: 'Segoe UI', Arial, sans-serif.
   - No JavaScript. No background images. Use solid background colors only.
   - RESPONSIVE: the email container must be FLUID — width="100%" with style="max-width:600px; margin:0 auto;".
     Do NOT use a fixed width="600". Inner tables use width="100%". This ensures it fills/centers on
     desktop and scales down on mobile with no horizontal scroll.
2. VASL BRAND COLORS (use ONLY these):
   - Maroon #7a1c2e, Wine #5a1020, Dark #3a1010, Gold #c0a040, Light gold #e8c55a,
     Ink #1a0a0a, Cream #fdf6e3, white #ffffff. Text on dark backgrounds: white or light gold.
3. STRUCTURE (compose a rich, multi-section email):
   - Wrap the ENTIRE email in a fluid, responsive outer structure:
     * Outermost: a full-width table width="100%" with a centered inner container.
     * The inner container table MUST be width="100%" with style="max-width:600px; margin:0 auto;"
       (NOT a fixed width="600"). This makes it fill nicely on desktop (capped at 600px,
       centered) and shrink cleanly on mobile without horizontal scrolling.
   - Inner section tables/cells use width="100%" (percentage), NOT fixed pixel widths.
   - Use padding for spacing (e.g. 24px 30px) rather than fixed-width spacer columns.
   - Font sizes must stay mobile-readable: body >=15px, headings >=20px.
   - Start with a header band (maroon) containing the logo:
     <img src="https://d15k2d11r6t6rl.cloudfront.net/pub/bfra/re3npkbr/uk0/cg1/09s/cropped-Vantage_africa_logo-PNG-1.png" alt="Vantage Africa" style="height:48px; max-width:100%;">
     and the text "School of Leadership".
   - A hero band (wine, gold top border) with an optional small gold badge pill and a bold headline.
   - White intro section starting with "Hi \$name," (keep the literal \$name placeholder).
   - Body sections as needed: benefit cards (dark band, 2-up table), info rows, etc.
   - For each provided link, add a gold rounded button (gold bg, dark text) linking to it.
   - A closing/signature area and a footer band (ink color) with Unsubscribe / Privacy Policy.
4. GRAPHICS (email-safe only):
   - Use simple unicode symbols/emoji for accents (e.g. ✓ ★ ▸ 🎓 📅 🔗) sparingly.
   - Use colored bands, rounded pills, and bordered cards for visual structure.
   - Keep the overall width to max 600px, centered.
5. Keep copy professional, warm, and concise. Always keep the \$name merge tag.

Return only the HTML.
SYS;

$userPrompt = "Email purpose/label: " . ($purpose !== '' ? $purpose : '(general)') . "\n\n"
            . "What the email should say:\n" . $message . "\n\n"
            . "Links to include as buttons/links:\n" . $linksText . "\n\n"
            . "Generate the full VASL-branded email body now.";

// ---- Call OpenAI Chat Completions ----
$payload = [
    'model' => defined('VASL_OPENAI_MODEL') ? VASL_OPENAI_MODEL : 'gpt-4o',
    'messages' => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' => $userPrompt],
    ],
    'temperature' => 0.7,
    'max_tokens' => 2200,
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

$html = isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : '';

if ($html === '') {
    echo json_encode(['ok' => false, 'error' => 'OpenAI returned an empty response.']);
    exit;
}

// Strip any accidental code fences or document wrappers
$html = preg_replace('/^```[a-z]*\s*/i', '', trim($html));
$html = preg_replace('/```\s*$/', '', $html);
$html = preg_replace('#</?(?:html|head|body)[^>]*>#i', '', $html);
// Remove any <style>/<script> blocks the model may have added despite instructions
$html = preg_replace('#<style[^>]*>.*?</style>#is', '', $html);
$html = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);

echo json_encode(['ok' => true, 'html' => trim($html)]);