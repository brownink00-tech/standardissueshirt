<?php
/*
 * Standard Issue Shirt Co. - order inquiry handler
 * Receives the form on index.html and creates a record in Airtable
 * (base "MLXL Q4 Retail Prospects 2026", table "Inquiries").
 *
 * The Airtable token is NOT stored in this repo. It lives in si-config.php,
 * one folder ABOVE public_html, so it is never served or committed:
 *   <?php return ['airtable_token' => 'pat....'];
 *
 * Optional Cloudflare Turnstile ("not a robot" check), same file:
 *   'turnstile_site_key' => '0x4AAAA...',   // public, sent to the page
 *   'turnstile_secret'   => '0x4AAAA...',   // private, checked here
 * If the secret is missing, the form still works with the built-in spam traps only.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Minimal SMTP client for smtp.hostinger.com (SSL, port 465, AUTH LOGIN)
function smtp_send($user, $pass, $to, $subject, $body) {
    $fp = @stream_socket_client('ssl://smtp.hostinger.com:465', $errno, $errstr, 15);
    if (!$fp) { error_log("SMTP connect failed: $errstr"); return false; }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) { $data .= $line; if (isset($line[3]) && $line[3] === ' ') break; }
        return $data;
    };
    $cmd = function ($c, $expect) use ($fp, $read) {
        if ($c !== null) fwrite($fp, $c . "\r\n");
        $r = $read();
        if (substr($r, 0, 3) !== (string)$expect) { error_log('SMTP error after "' . explode(' ', (string)$c)[0] . '": ' . trim($r)); return false; }
        return true;
    };
    $ok = $cmd(null, 220) && $cmd('EHLO standardissueshirt.com', 250) && $cmd('AUTH LOGIN', 334)
        && $cmd(base64_encode($user), 334) && $cmd(base64_encode($pass), 235)
        && $cmd("MAIL FROM:<$user>", 250) && $cmd("RCPT TO:<$to>", 250) && $cmd('DATA', 354);
    if ($ok) {
        $msg = implode("\r\n", [
            'Date: ' . date('r'),
            'From: Standard Issue Shirt Co. <' . $user . '>',
            'Reply-To: ' . $user,
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@standardissueshirt.com>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ]) . "\r\n\r\n" . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body)) . "\r\n.";
        $ok = $cmd($msg, 250);
    }
    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$cfgPath = dirname(__DIR__) . '/si-config.php';
$cfg = is_file($cfgPath) ? (require $cfgPath) : [];

// The page asks for the public Turnstile site key here (GET order.php?config=1).
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['config'])) {
    header('Cache-Control: no-store');
    echo json_encode(['siteKey' => trim((string)($cfg['turnstile_site_key'] ?? ''))]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

// Spam traps: hidden field must be empty, and the form must have been open > 3 seconds
if (!empty($_POST['company_website'])) { echo json_encode(['ok' => true]); exit; }
$started = (int)($_POST['t'] ?? 0);
if ($started > 0 && (time() - $started) < 3) { echo json_encode(['ok' => true]); exit; }

if (!$cfg) fail('Form is not configured yet', 503);

// Cloudflare Turnstile check, once the secret is in si-config.php
$tsSecret = trim((string)($cfg['turnstile_secret'] ?? ''));
if ($tsSecret !== '') {
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret'   => $tsSecret,
            'response' => (string)($_POST['cf-turnstile-response'] ?? ''),
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $ts = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    if (empty($ts['success'])) fail('Please complete the security check and try again.');
}

$token = trim((string)($cfg['airtable_token'] ?? ''));
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

// One QR tag per piece type, e.g. standardissueshirt.com/?src=sample
$srcMap = ['sample' => 'Sample drop', 'letter' => 'Letter drop', 'flyer' => 'Flyer drop', 'postcard' => 'Postcard drop',
           'gbp' => 'Google profile', 'social' => 'Social', 'ads' => 'Google Ads', 'email' => 'Email campaign', 'referral' => 'Referral'];
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

$saveRecord = function ($fields) use ($token) {
    $ch = curl_init('https://api.airtable.com/v0/' . BASE_ID . '/' . TABLE_ID);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['records' => [['fields' => $fields]], 'typecast' => true]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp];
};
[$code, $resp] = $saveRecord($fields);
// If Airtable won't add a new Source option, save the order anyway as "Other" (the exact tag stays in Notes)
if ($code === 422 && $fields['Source'] !== 'Other') {
    error_log('Airtable 422, retrying with Source=Other: ' . $resp);
    $fields['Source'] = 'Other';
    [$code, $resp] = $saveRecord($fields);
}

if ($code < 200 || $code >= 300) {
    error_log('Airtable error ' . $code . ': ' . $resp);
    fail('Something went wrong on our end. Please call or text 904-350-0048.', 502);
}
$recordId = json_decode($resp, true)['records'][0]['id'] ?? null;

// Confirmation email to the customer, from crew@ so replies come back to us
$oneLine = fn($s) => trim(preg_replace('/[\r\n]+/', ' ', $s));
$first = $oneLine(explode(' ', $name)[0]);
$hasLogo = isset($fields['Logo file']);
$body = "Hi $first,\n\n"
    . "Thanks for reaching out to Standard Issue Shirt Co. We got your request for " . $oneLine($business) . " (" . implode(', ', $needs) . ") and we're on it.\n\n"
    . "Here's what happens next:\n"
    . "1. We clean up your logo and send you a free mockup within one business day.\n"
    . "2. Your final price comes with the mockup. No setup fees, no surprises.\n"
    . "3. Once you approve, we print here in Jacksonville and hand deliver in 7 to 10 days.\n\n"
    . ($hasLogo ? "" : "Didn't get a chance to attach your logo? Just reply to this email with it. Any file works.\n\n")
    . "Questions? Reply here or call or text 904-350-0048.\n\n"
    . "Michael Brown\nStandard Issue Shirt Co.\nstandardissueshirt.com\n";
$headers = implode("\r\n", [
    'From: Standard Issue Shirt Co. <crew@standardissueshirt.com>',
    'Reply-To: crew@standardissueshirt.com',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
]);
$subject = 'Got your request, ' . $first . '. Mockup coming within 1 business day';
if (!empty($cfg['smtp_pass'])) {
    // Authenticated send through the crew@ mailbox (DKIM-signed, lands in inbox)
    $sent = smtp_send($cfg['smtp_user'] ?? 'crew@standardissueshirt.com', $cfg['smtp_pass'], $email, $subject, $body);
} else {
    $sent = @mail($email, $subject, $body, $headers, '-fcrew@standardissueshirt.com');
}
if (!$sent) error_log('Confirmation email failed for ' . $email);

if ($sent && $recordId) {
    $ch = curl_init('https://api.airtable.com/v0/' . BASE_ID . '/' . TABLE_ID . '/' . $recordId);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode(['fields' => ['Auto-reply sent' => true]]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
    ]);
    curl_exec($ch);
    curl_close($ch);
}
echo json_encode(['ok' => true]);
