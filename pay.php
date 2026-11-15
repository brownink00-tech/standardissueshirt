<?php
/*
 * Standard Issue Shirt Co. - online order + PayPal checkout
 *
 * The price is ALWAYS worked out here on the server (inc/catalog.php), never trusted from the browser.
 * Payments go to the MLXL PayPal Business account; the customer sees "Standard Issue Shirt Co."
 * Every order number starts with SI- so Standard Issue sales are easy to separate from MLXL sales.
 *
 * Settings live in si-config.php, one folder ABOVE public_html (never in this repo):
 *   'paypal_client_id' => '...',
 *   'paypal_secret'    => '...',
 *   'paypal_env'       => 'sandbox',   // change to 'live' when testing is done
 *   'notify_email'     => 'crew@standardissueshirt.com',   // optional, who gets new-order emails
 *
 *   GET  pay.php?config=1      public PayPal client id + mode
 *   GET  pay.php?catalog=1     styles, colors, sizes, prices
 *   POST pay.php?a=quote       JSON order -> price breakdown
 *   POST pay.php?a=create      form + order -> PayPal order id (or tax-exempt review)
 *   POST pay.php?a=capture     {id} -> takes payment, saves to Airtable, sends emails
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require __DIR__ . '/inc/catalog.php';
require __DIR__ . '/inc/mail.php';

function out($a) { echo json_encode($a); exit; }
function fail($msg, $code = 400, $extra = []) { http_response_code($code); out(['ok' => false, 'error' => $msg] + $extra); }

$cfgPath = dirname(__DIR__) . '/si-config.php';
$cfg = is_file($cfgPath) ? (require $cfgPath) : [];
$ppId  = trim((string)($cfg['paypal_client_id'] ?? ''));
$ppSec = trim((string)($cfg['paypal_secret'] ?? ''));
$ppEnv = ($cfg['paypal_env'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
$ppApi = $ppEnv === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

const SI_BRAND = 'Standard Issue Shirt Co.';
const SI_DESCRIPTOR = 'STDISSUESHIRT';   // card statement text (PayPal adds its own prefix)
const AT_BASE  = 'appRVSSHWg3NVmkgq';
const AT_TABLE = 'tblR2n3jDru94J6tp';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['config'])) out(['ok' => true, 'clientId' => $ppId, 'env' => $ppEnv, 'ready' => $ppId !== '' && $ppSec !== '']);
    if (isset($_GET['catalog'])) out(['ok' => true] + si_catalog());
    fail('Not found', 404);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$a = $_GET['a'] ?? '';

/* ---------- helpers ---------- */
function clean_str($v, $max = 200) {
    $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$v));
    return mb_substr($v, 0, $max);
}
function json_body() { return json_decode(file_get_contents('php://input'), true) ?: []; }

function store_dir() {
    $d = dirname(__DIR__) . '/si-orders';
    if (!is_dir($d)) @mkdir($d, 0700, true);
    if (is_dir($d) && is_writable($d)) return $d;
    $d = __DIR__ . '/inc/orders';                 // fallback, blocked from the web by inc/.htaccess
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}
function store_path($ppOrderId) { return store_dir() . '/' . preg_replace('/[^A-Z0-9]/', '', strtoupper($ppOrderId)) . '.json'; }

function paypal($method, $path, $body = null, $extraHeaders = []) {
    global $ppApi, $ppId, $ppSec;
    static $tok = null;
    if ($tok === null) {
        $ch = curl_init($ppApi . '/v1/oauth2/token');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $ppId . ':' . $ppSec, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $r = json_decode((string)curl_exec($ch), true);
        curl_close($ch);
        $tok = $r['access_token'] ?? '';
        if ($tok === '') { error_log('PayPal auth failed: ' . json_encode($r)); return [0, null]; }
    }
    $ch = curl_init($ppApi . $path);
    $h = array_merge(['Authorization: Bearer ' . $tok, 'Content-Type: application/json', 'Prefer: return=representation'], $extraHeaders);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $h]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode((string)$resp, true)];
}

function save_upload($field, $allowed) {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    if ($_FILES[$field]['size'] > 15 * 1024 * 1024) fail('That file is too big. Please keep it under 15 MB.');
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) fail('That file type is not supported for ' . ($field === 'cert' ? 'the certificate' : 'your logo') . '.');
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], "$dir/$fname")) return null;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return ['url' => $scheme . '://' . $_SERVER['HTTP_HOST'] . '/uploads/' . $fname, 'filename' => basename($_FILES[$field]['name'])];
}

function airtable_save($fields) {
    global $cfg;
    $token = trim((string)($cfg['airtable_token'] ?? ''));
    if ($token === '') { error_log('No Airtable token'); return false; }
    $send = function ($f) use ($token) {
        $ch = curl_init('https://api.airtable.com/v0/' . AT_BASE . '/' . AT_TABLE);
        curl_setopt_array($ch, [CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['records' => [['fields' => $f]], 'typecast' => true]),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return [$code, $resp];
    };
    [$code, $resp] = $send($fields);
    if ($code === 422) {   // a select option it won't add: fall back to safe values, details stay in Notes
        error_log('Airtable 422 on paid order, retrying: ' . $resp);
        $fields['Source'] = 'Other';
        $fields['What do you need?'] = ['Not sure yet'];
        [$code, $resp] = $send($fields);
    }
    if ($code < 200 || $code >= 300) { error_log('Airtable error ' . $code . ': ' . $resp); return false; }
    return true;
}

function send_mail($to, $subject, $body) {
    global $cfg;
    if ($to === '') return false;
    if (!empty($cfg['smtp_pass'])) return smtp_send($cfg['smtp_user'] ?? 'crew@standardissueshirt.com', $cfg['smtp_pass'], $to, $subject, $body);
    $h = "From: Standard Issue Shirt Co. <crew@standardissueshirt.com>\r\nReply-To: crew@standardissueshirt.com\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
    return @mail($to, $subject, $body, $h, '-fcrew@standardissueshirt.com');
}

function money($n) { return '$' . number_format((float)$n, 2); }

function order_text($q, $c) {
    $t = [];
    foreach ($q['lines'] as $l) {
        if (!empty($l['info'])) $t[] = '  ' . $l['desc'];
        else $t[] = '  ' . $l['desc'] . ($l['qty'] > 1 ? ' (' . $l['qty'] . ' x ' . money($l['unit']) . ')' : '') . '  ' . money($l['amount']);
    }
    $t[] = '  Subtotal  ' . money($q['subtotal']);
    if ($q['exempt']) $t[] = '  Sales tax  exempt (certificate under review)';
    else $t[] = '  Sales tax (' . $q['county'] . ' County, ' . rtrim(rtrim(number_format($q['rate'] * 100, 2), '0'), '.') . '%)  ' . money($q['tax']);
    $t[] = '  Total  ' . money($q['total']);
    $t[] = '';
    $t[] = $c['delivery'] === 'pickup' ? 'Pickup: our Jacksonville shop' : 'Deliver to: ' . $c['address'] . ', ' . $c['city'] . ', FL ' . $c['zip'];
    if ($c['need_by']) $t[] = 'Need by: ' . $c['need_by'];
    if ($c['notes']) $t[] = 'Notes: ' . $c['notes'];
    return implode("\n", $t);
}

function airtable_fields($q, $c, $orderNo) {
    $srcMap = ['sample' => 'Sample drop', 'letter' => 'Letter drop', 'flyer' => 'Flyer drop', 'postcard' => 'Postcard drop',
               'gbp' => 'Google profile', 'social' => 'Social', 'ads' => 'Google Ads', 'email' => 'Email campaign', 'referral' => 'Referral', 'followup' => 'Rep follow-up'];
    $reps = ['dd' => 'Daryll Davis'];
    $f = [
        'Business Name' => $c['business'], 'Contact Name' => $c['name'], 'Email' => $c['email'],
        'What do you need?' => $q['needs'], 'Print locations' => $q['print_label'],
        'Estimated quantity' => $q['pieces'], 'Order #' => $orderNo,
        'Sales tax' => $q['tax'],
        'Tax county' => $q['exempt'] ? 'Exempt (review certificate)' : ($q['county'] . ' ' . ($q['rate'] * 100) . '%'),
    ];
    if ($c['phone'] !== '') $f['Phone'] = $c['phone'];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $c['need_by'])) $f['Need by'] = $c['need_by'];
    $src = strtolower($c['src']);
    if (preg_match('/^([a-z]+)-([a-z]+)$/', $src, $m) && isset($reps[$m[1]])) { $f['Rep'] = $reps[$m[1]]; $src = $m[2]; }
    $f['Source'] = $srcMap[$src] ?? 'Other';
    if (!empty($c['logo'])) $f['Logo file'] = [$c['logo']];
    if (!empty($c['cert'])) $f['Tax certificate'] = [$c['cert']];
    return $f;
}

/* ---------- quote ---------- */
if ($a === 'quote') {
    $q = si_quote(json_body());
    if (!$q['ok']) out($q);
    unset($q['needs'], $q['print_label']);
    foreach ($q['lines'] as &$l) unset($l['item']);
    out($q);
}

/* ---------- create: validate, then PayPal order (or tax-exempt review) ---------- */
if ($a === 'create') {
    if (!empty($_POST['company_website'])) fail('Please try again.');   // spam trap
    $order = json_decode((string)($_POST['order'] ?? ''), true) ?: [];
    $c = [
        'business' => clean_str($_POST['business'] ?? ''), 'name' => clean_str($_POST['name'] ?? ''),
        'email' => clean_str($_POST['email'] ?? '', 254), 'phone' => clean_str($_POST['phone'] ?? '', 40),
        'delivery' => ($order['delivery'] ?? '') === 'pickup' ? 'pickup' : 'deliver',
        'address' => clean_str($_POST['address'] ?? ''), 'city' => clean_str($_POST['city'] ?? '', 80),
        'zip' => substr(preg_replace('/\D/', '', (string)($order['zip'] ?? '')), 0, 5),
        'need_by' => clean_str($_POST['need_by'] ?? '', 10), 'notes' => clean_str($_POST['notes'] ?? '', 2000),
        'src' => clean_str($_POST['src'] ?? '', 40),
    ];
    if ($c['business'] === '' || $c['name'] === '' || $c['email'] === '') fail('Please add your business, name and email.');
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address.');
    if ($c['delivery'] === 'deliver' && ($c['address'] === '' || $c['city'] === '')) fail('Please add the delivery street address and city.');

    $q = si_quote($order);
    if (!$q['ok']) fail($q['error']);
    if (!$q['tax_ok']) fail($q['tax_error']);

    $c['logo'] = save_upload('logo', ['png', 'jpg', 'jpeg', 'gif', 'webp', 'heic', 'pdf', 'ai', 'eps', 'svg', 'psd', 'tif', 'tiff']);
    $orderNo = 'SI-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

    // Tax-exempt: no charge yet. We check the certificate, then send a PayPal invoice for the untaxed total.
    if ($q['exempt']) {
        $c['cert'] = save_upload('cert', ['pdf', 'png', 'jpg', 'jpeg', 'heic', 'webp']);
        if (!$c['cert']) fail('Please upload your Florida Consumer Exemption Certificate (or uncheck tax-exempt).');
        $f = airtable_fields($q, $c, $orderNo);
        $f['Status'] = 'New'; $f['Payment'] = 'Tax-exempt review';
        $f['Notes'] = "ONLINE ORDER $orderNo - TAX-EXEMPT, NOT PAID YET. Check the certificate, then send a PayPal invoice for " . money($q['total']) . ".\n\n" . order_text($q, $c);
        if (!airtable_save($f)) fail('Something went wrong on our end. Please call or text 904-382-5885.', 502);
        $first = explode(' ', $c['name'])[0];
        send_mail($c['email'], "Order $orderNo received, $first",
            "Hi $first,\n\nThanks for your order with Standard Issue Shirt Co. We got your tax-exempt certificate and we'll check it within one business day.\n\n"
            . "Next, we send your mockup and a PayPal invoice for " . money($q['total']) . " (no sales tax). Printing starts once it's paid and you approve the mockup.\n\n"
            . "Your order:\n" . order_text($q, $c) . "\n\nQuestions? Reply here or call or text 904-382-5885.\n\nMichael Brown\nStandard Issue Shirt Co.\nstandardissueshirt.com\n");
        send_mail($cfg['notify_email'] ?? 'crew@standardissueshirt.com', "Tax-exempt order to review: $orderNo, " . $c['business'], $f['Notes']);
        out(['ok' => true, 'exempt' => true, 'orderNo' => $orderNo]);
    }

    if ($ppId === '' || $ppSec === '') fail('Online payment is not set up yet. Please call or text 904-382-5885.', 503);

    $items = [];
    foreach ($q['lines'] as $l) {
        if (!empty($l['info']) || $l['amount'] <= 0) continue;
        $qty = max(1, (int)$l['qty']);
        $items[] = ['name' => mb_substr($l['desc'], 0, 127), 'quantity' => (string)$qty, 'category' => 'PHYSICAL_GOODS',
                    'unit_amount' => ['currency_code' => 'USD', 'value' => number_format($l['amount'] / $qty, 2, '.', '')]];
    }
    $body = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $orderNo, 'invoice_id' => $orderNo, 'custom_id' => $orderNo,
            'description' => 'Custom printed shirts - ' . SI_BRAND,
            'soft_descriptor' => SI_DESCRIPTOR,
            'amount' => ['currency_code' => 'USD', 'value' => number_format($q['total'], 2, '.', ''),
                'breakdown' => ['item_total' => ['currency_code' => 'USD', 'value' => number_format($q['subtotal'], 2, '.', '')],
                                'tax_total'  => ['currency_code' => 'USD', 'value' => number_format($q['tax'], 2, '.', '')]]],
            'items' => $items,
        ]],
        'application_context' => ['brand_name' => SI_BRAND, 'shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW'],
    ];
    [$code, $r] = paypal('POST', '/v2/checkout/orders', $body, ['PayPal-Request-Id: ' . $orderNo]);
    if ($code < 200 || $code >= 300 || empty($r['id'])) {
        error_log('PayPal create failed ' . $code . ': ' . json_encode($r));
        fail('We could not start checkout. Please try again, or call or text 904-382-5885.', 502);
    }
    file_put_contents(store_path($r['id']), json_encode(['orderNo' => $orderNo, 'quote' => $q, 'contact' => $c, 'created' => time()]), LOCK_EX);
    out(['ok' => true, 'id' => $r['id'], 'orderNo' => $orderNo]);
}

/* ---------- capture: take the payment, then record it ---------- */
if ($a === 'capture') {
    $id = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)(json_body()['id'] ?? '')));
    $path = store_path($id);
    if ($id === '' || !is_file($path)) fail('We could not find that order. Please call or text 904-382-5885.', 404);
    $p = json_decode(file_get_contents($path), true);
    if (!empty($p['captured'])) out(['ok' => true, 'orderNo' => $p['orderNo']]);   // already done

    [$code, $r] = paypal('POST', "/v2/checkout/orders/$id/capture", new stdClass(), ['PayPal-Request-Id: cap-' . $id]);
    $issue = $r['details'][0]['issue'] ?? '';
    if ($issue === 'INSTRUMENT_DECLINED') out(['ok' => false, 'retry' => true, 'error' => 'That card was declined. Please try another card or payment method.']);
    $cap = $r['purchase_units'][0]['payments']['captures'][0] ?? null;
    if ($code < 200 || $code >= 300 || !$cap) {
        error_log('PayPal capture failed ' . $code . ': ' . json_encode($r));
        fail('Payment did not go through. You have not been charged. Please try again or call 904-382-5885.', 502);
    }
    $q = $p['quote']; $c = $p['contact']; $orderNo = $p['orderNo'];
    $paid = (float)($cap['amount']['value'] ?? 0);
    $status = $cap['status'] ?? '';
    $p['captured'] = true; $p['capture_id'] = $cap['id']; $p['capture_status'] = $status;
    file_put_contents($path, json_encode($p), LOCK_EX);

    $flag = '';
    if (abs($paid - $q['total']) > 0.009) $flag .= "CHECK: PayPal amount " . money($paid) . " does not match order total " . money($q['total']) . ".\n";
    if ($status !== 'COMPLETED') $flag .= "CHECK: PayPal payment status is $status (not completed yet). Confirm in PayPal before printing.\n";

    $f = airtable_fields($q, $c, $orderNo);
    $f['Status'] = 'Ordered'; $f['Payment'] = 'Paid'; $f['Amount paid'] = $paid; $f['PayPal ID'] = $cap['id'];
    $f['Notes'] = $flag . "ONLINE ORDER $orderNo - PAID " . money($paid) . " by PayPal (" . $cap['id'] . "). Send mockup within 1 business day; print after approval.\n\n" . order_text($q, $c);
    if (!airtable_save($f)) error_log("PAID ORDER NOT SAVED TO AIRTABLE: $orderNo " . json_encode($f));

    $first = explode(' ', $c['name'])[0];
    send_mail($c['email'], "You're all set, $first. Order $orderNo is paid",
        "Hi $first,\n\nThank you for your order with Standard Issue Shirt Co. Your payment of " . money($paid) . " went through.\n\n"
        . "What happens next:\n1. We send your mockup within one business day.\n2. You approve it (or tell us what to change).\n3. We print here in Jacksonville and have it ready in 7 to 10 business days after approval.\n\n"
        . "Nothing prints until you approve your mockup. If we can't get it right, we refund you in full.\n\n"
        . (empty($c['logo']) ? "Didn't attach your logo? Just reply to this email with it. Any file works.\n\n" : '')
        . "Your order $orderNo:\n" . order_text($q, $c) . "\n\nYour card statement will show PAYPAL *" . SI_DESCRIPTOR . ".\n\n"
        . "Questions? Reply here or call or text 904-382-5885.\n\nMichael Brown\nStandard Issue Shirt Co.\nstandardissueshirt.com\n");
    send_mail($cfg['notify_email'] ?? 'crew@standardissueshirt.com', "PAID online order $orderNo, " . $c['business'] . ', ' . money($paid),
        $f['Notes'] . "\n\nCustomer: " . $c['name'] . ', ' . $c['email'] . ($c['phone'] ? ', ' . $c['phone'] : '') . (empty($c['logo']) ? "\nNo logo uploaded yet." : "\nLogo: " . $c['logo']['url']));
    out(['ok' => true, 'orderNo' => $orderNo, 'paid' => $paid]);
}

fail('Unknown action', 404);
