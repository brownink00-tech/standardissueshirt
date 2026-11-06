<?php
/*
 * Standard Issue Shirt Co. - order inquiry handler
 * Receives the form on index.html and creates a record in Airtable
 * (base "MLXL Q4 Retail Prospects 2026", table "Inquiries").
 *
 * The Airtable token is NOT stored in this repo. It lives in si-config.php,
 * one folder ABOVE public_html, so it is never served or committed:
 *   <?php return ['airtable_token' => 'pat....'];
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

// Spam traps: hidden field must be empty, and the form must have been open > 3 seconds
if (!empty($_POST['company_website'])) { echo json_encode(['ok' => true]); exit; }
$started = (int)($_POST['t'] ?? 0);
if ($started > 0 && (time() - $started) < 3) { echo json_encode(['ok' => true]); exit; }

$cfgPath = dirname(__DIR__) . '/si-config.php';
if (!is_file($cfgPath)) fail('Form is not configured yet', 503);
$cfg = require $cfgPath;
$token = $cfg['airtable_token'] ?? '';
if (!$token) fail('Form is not configured yet', 503);

const BASE_ID  = 'appRVSSHWg3NVmkgq';
const TABLE_ID = 'tblR2n3jDru94J6tp';

function clean($k, $max = 200) {
    $v = trim((string)($_POST[$k] ?? ''));
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
    return mb_substr($v, 0, $max);
}

$business = clean('business');
$name     = clean('name');
$email    = clean('email', 254);
$phone    = clean('phone', 40);
$notes    = clean('notes', 3000);
$locs     = clean('locations', 40);
$qty      = clean('qty', 10);
$needBy   = clean('need_by', 10);
$src      = clean('src', 40);

if ($business === '' || $name === '' || $email === '') fail('Please fill in business, name and email.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address.');

$allowedNeeds = ['Starter Crew Pack ($595)', 'Premium Crew Pack ($850)', 'Everyday Tees', 'Premium Tees',
                 'Everyday Crewnecks', 'Premium Crewnecks', 'Branded webstore', 'Not sure yet'];
$needs = array_values(array_intersect((array)($_POST['needs'] ?? []), $allowedNeeds));
if (!$needs) fail('Please pick at least one item under "What do you need?"');

$fields = [
    'Business Name'     => $business,
    'Contact Name'      => $name,
    'Email'             => $email,
    'What do you need?' => $needs,
    'Status'            => 'New',
];
if ($phone !== '') $fields['Phone'] = $phone;
if (in_array($locs, ['Front only', 'Front + Back', 'Not sure'], true)) $fields['Print locations'] = $locs;
if ($qty !== '' && ctype_digit($qty)) $fields['Estimated quantity'] = (int)$qty;
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $needBy)) $fields['Need by'] = $needBy;

$srcMap = ['flyer' => 'Flyer drop', 'postcard' => 'Flyer drop', 'letter' => 'Flyer drop', 'email' => 'Email campaign', 'referral' => 'Referral'];
$fields['Source'] = $srcMap[strtolower($src)] ?? 'Other';
$noteParts = [];
if ($notes !== '') $noteParts[] = $notes;
$noteParts[] = 'Submitted on standardissueshirt.com' . ($src !== '' ? " (src: $src)" : '');
$fields['Notes'] = implode("\n\n", $noteParts);

// Optional logo upload: saved to /uploads with a random name, then handed to Airtable by URL
if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    if ($_FILES['logo']['size'] > 15 * 1024 * 1024) fail('Logo file is too big. Please keep it under 15 MB, or email it to crew@standardissueshirt.com.');
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $okExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'ai', 'eps', 'svg', 'psd', 'tif', 'tiff'];
    if (!in_array($ext, $okExt, true)) fail('That file type is not supported. Try PNG, JPG, PDF, AI, EPS or SVG.');
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($_FILES['logo']['tmp_name'], "$dir/$fname")) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $url = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/uploads/' . $fname;
        $fields['Logo file'] = [['url' => $url, 'filename' => basename($_FILES['logo']['name'])]];
    }
}

$payload = json_encode(['records' => [['fields' => $fields]], 'typecast' => true]);
$ch = curl_init('https://api.airtable.com/v0/' . BASE_ID . '/' . TABLE_ID);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code < 200 || $code >= 300) {
    error_log('Airtable error ' . $code . ': ' . $resp);
    fail('Something went wrong on our end. Please call or text 904-350-0048.', 502);
}
echo json_encode(['ok' => true]);
