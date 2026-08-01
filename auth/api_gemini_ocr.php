<?php
/**
 * auth/api_gemini_ocr.php
 * ─────────────────────────────────────────────────────────────
 * Secure server-side proxy for Google Gemini Vision API.
 * Accepts a base64-encoded ID image from the browser,
 * calls Gemini to extract structured ID data,
 * and returns a clean JSON response.
 *
 * The API key is NEVER exposed to the browser.
 * ─────────────────────────────────────────────────────────────
 */

// ── CORS & Content-Type ──────────────────────────────────────
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// ── Only allow POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── Rate limiting: max 10 OCR scans per session ───────────────
session_start();
$_SESSION['gemini_scan_count'] = ($_SESSION['gemini_scan_count'] ?? 0) + 1;
if ($_SESSION['gemini_scan_count'] > 10) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many scan attempts. Please refresh the page.']);
    exit;
}

// ── Load .env for API key ────────────────────────────────────
$envPath = __DIR__ . '/../.env';
$env     = [];
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        // Strip inline comments
        if (strpos($line, ' #') !== false) {
            $line = trim(substr($line, 0, strpos($line, ' #')));
        }
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $env[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
        }
    }
}

$apiKey = $env['GEMINI_API_KEY'] ?? '';
$model  = $env['GEMINI_MODEL']   ?? 'gemini-2.0-flash';

if (empty($apiKey) || strlen($apiKey) < 10) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Gemini API key is not configured. Please add GEMINI_API_KEY to your .env file.'
    ]);
    exit;
}

// ── Parse incoming JSON body ─────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (empty($body['image_base64'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No image data received.']);
    exit;
}

$imageBase64 = $body['image_base64'];
$mimeType    = $body['mime_type'] ?? 'image/jpeg';

// ── Validate mime type ────────────────────────────────────────
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mimeType, $allowedMimes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid image type. Only JPG, PNG, and WebP are supported.']);
    exit;
}

// Rough size check — base64 of 5MB ~ 6.8MB string
if (strlen($imageBase64) > 7000000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Image is too large. Please use an image under 4MB.']);
    exit;
}

// ── Build Gemini prompt ──────────────────────────────────────
$prompt = 'You are an expert ID document scanner for a Philippine public transportation system called PARE.' . "\n\n" .
'Carefully analyze the Philippine government-issued ID image provided and extract the following information.' . "\n\n" .
'Return ONLY a valid JSON object with these exact keys. Do not include any explanation, markdown code fences, or extra text — just the raw JSON:' . "\n\n" .
'{' . "\n" .
'  "full_name": "The persons full name exactly as printed on the ID",' . "\n" .
'  "id_number": "The ID number or document number exactly as printed",' . "\n" .
'  "id_type": "One of: PhilSys | UMID | Drivers License | Passport | Senior Citizen | PWD | Student ID | Voters ID | Postal ID | Other",' . "\n" .
'  "discount_type": "One of: none | senior | pwd | student | teacher | nurse — Senior Citizen card = senior, PWD card = pwd, Student ID = student, otherwise none",' . "\n" .
'  "confidence": "One of: high | medium | low"' . "\n" .
'}' . "\n\n" .
'Rules:' . "\n" .
'- full_name: Extract name exactly as printed. Normalize to "Given Name Middle Name Surname" format if possible.' . "\n" .
'- id_number: Keep original formatting (e.g. QR-12-34567890-0).' . "\n" .
'- If a field is not visible or unreadable, use null.' . "\n" .
'- Never guess or fabricate data. If unsure, use null and set confidence to low.';

// ── Build Gemini API request ──────────────────────────────────
$requestBody = [
    'contents' => [[
        'parts' => [
            [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data'      => $imageBase64,
                ]
            ],
            [
                'text' => $prompt
            ]
        ]
    ]],
    'generationConfig' => [
        'temperature'     => 0.1,
        'topK'            => 1,
        'topP'            => 0.95,
        'maxOutputTokens' => 512,
    ]
];

// ── Call Gemini API via cURL ──────────────────────────────────
// AQ. prefix keys work fine with ?key= param (confirmed via 429 quota response)
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($requestBody),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,  // Disabled for XAMPP localhost (no trusted CA bundle)
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$response   = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

// ── Handle cURL errors ────────────────────────────────────────
if ($curlError) {
    error_log("Gemini cURL error: $curlError");
    echo json_encode(['success' => false, 'error' => 'Could not connect to Gemini API. Check your internet connection.']);
    exit;
}

// ── Parse Gemini response ─────────────────────────────────────
$geminiResponse = json_decode($response, true);

if ($httpCode !== 200) {
    $errMsg  = $geminiResponse['error']['message'] ?? 'Unknown Gemini API error.';
    $errCode = $geminiResponse['error']['code']    ?? $httpCode;
    $errStatus = $geminiResponse['error']['status'] ?? '';
    error_log("Gemini API error (HTTP $httpCode | $errStatus): $errMsg");
    // Return the REAL Google error so we can diagnose
    echo json_encode([
        'success' => false,
        'error'   => "Gemini Error ($httpCode $errStatus): $errMsg",
    ]);
    exit;
}

// ── Extract text from Gemini response ────────────────────────
$rawText = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$rawText) {
    echo json_encode(['success' => false, 'error' => 'Gemini returned an empty response. Please try a clearer photo.']);
    exit;
}

// ── Parse JSON from Gemini output ────────────────────────────
$cleanText = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
$cleanText = preg_replace('/\s*```$/i', '', $cleanText);
$cleanText = trim($cleanText);

$extracted = json_decode($cleanText, true);

if (!is_array($extracted)) {
    error_log("Gemini non-JSON response: $rawText");
    echo json_encode([
        'success' => false,
        'error'   => 'Could not parse ID data. Please try a clearer, well-lit photo.'
    ]);
    exit;
}

// ── Sanitize & return ─────────────────────────────────────────
$allowedDiscounts = ['none', 'senior', 'pwd', 'student', 'teacher', 'nurse'];
$discountType = in_array($extracted['discount_type'] ?? '', $allowedDiscounts)
    ? $extracted['discount_type']
    : 'none';

echo json_encode([
    'success'       => true,
    'full_name'     => isset($extracted['full_name'])  ? htmlspecialchars(strip_tags(trim($extracted['full_name'])))  : null,
    'id_number'     => isset($extracted['id_number'])  ? htmlspecialchars(strip_tags(trim($extracted['id_number'])))  : null,
    'id_type'       => isset($extracted['id_type'])    ? htmlspecialchars(strip_tags(trim($extracted['id_type'])))    : null,
    'discount_type' => $discountType,
    'confidence'    => in_array($extracted['confidence'] ?? '', ['high','medium','low'])
                        ? $extracted['confidence'] : 'low',
]);
