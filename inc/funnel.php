<?php
/**
 * Mockup + follow-up funnel for Standard Issue.
 *
 *  1. A mockup request comes in (order.php). notify_art() emails art@mlxlpro.com and crew@
 *     with the details, the logo link and a one-click "Send mockup" link for that lead.
 *  2. Ally opens that link (mockup.php), uploads the mockup, confirms pieces and price, clicks Send.
 *     send_mockup_email() emails the customer from crew@ with the mockup and an Approve & pay button,
 *     and Airtable Status becomes "Mockup sent".
 *  3. send_followups() (runs with the existing reminder tick / cron in pay.php) emails day 2, 5 and 9
 *     after the mockup, only while Status is still "Mockup sent" and they have not paid.
 *     The team stops follow-ups any time by changing Status to Replied, Ordered or Lost.
 *
 * Links are signed with an HMAC of the record id, keyed by the Airtable token in si-config.php,
 * so nobody can open another lead's page or prefill.
 */

require_once __DIR__ . '/mail.php';

const FN_BASE  = 'appRVSSHWg3NVmkgq';
const FN_TABLE = 'tblR2n3jDru94J6tp';
const FN_SITE  = 'https://standardissueshirt.com';
const FN_ART   = 'art@mlxlpro.com';
const FN_OWNER = 'mbrown@mlxlpro.com';
const FN_DAYS  = [2, 5, 9];          // follow-up days after the mockup is sent

function fn_cfg() {
    static $cfg = null;
    if ($cfg === null) { $p = dirname(__DIR__, 2) . '/si-config.php'; $cfg = is_file($p) ? (require $p) : []; }
    return $cfg;
}
function fn_token() { return trim((string)(fn_cfg()['airtable_token'] ?? '')); }
function fn_sign($rec, $purpose) { return substr(hash_hmac('sha256', $purpose . '|' . $rec, 'si-funnel|' . fn_token()), 0, 24); }
function fn_check($rec, $purpose, $sig) { return fn_token() !== '' && preg_match('/^rec[A-Za-z0-9]{14}$/', (string)$rec) && hash_equals(fn_sign($rec, $purpose), (string)$sig); }
function fn_money($n) { return '$' . number_format((float)$n, 2); }
function fn_first($name) { $f = trim(explode(' ', trim((string)$name))[0] ?? ''); return $f !== '' ? $f : 'there'; }

/* ---------- Airtable ---------- */
function fn_at($method, $path = '', $body = null, $query = []) {
    $url = 'https://api.airtable.com/v0/' . FN_BASE . '/' . FN_TABLE . $path . ($query ? '?' . http_build_query($query) : '');
    $ch = curl_init($url);
    $opt = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . fn_token(), 'Content-Type: application/json']];
    if ($body !== null) $opt[CURLOPT_POSTFIELDS] = json_encode($body);
    curl_setopt_array($ch, $opt);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code < 200 || $code >= 300) { error_log("Airtable $method $path $code: $resp"); return null; }
    return json_decode($resp, true);
}
/* Local copy of each lead, saved when the request comes in, so the Send mockup page still works
   if the Airtable token cannot read records (it only needs write access for this). */
function fn_lead_dir() {
    $d = dirname(__DIR__, 2) . '/si-orders/leads';
    if (!is_dir($d)) @mkdir($d, 0700, true);
    if (is_dir($d) && is_writable($d)) return $d;
    $d = __DIR__ . '/orders/leads';
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}
function fn_cache_put($rec, $fields) {
    if (!preg_match('/^rec[A-Za-z0-9]{14}$/', (string)$rec)) return;
    $p = fn_lead_dir() . "/$rec.json";
    $old = is_file($p) ? (json_decode((string)file_get_contents($p), true) ?: []) : [];
    file_put_contents($p, json_encode(['id' => $rec, 'fields' => array_merge($old['fields'] ?? [], $fields)]), LOCK_EX);
}
function fn_get($rec) {
    $r = fn_at('GET', '/' . $rec);
    if ($r) { fn_cache_put($rec, $r['fields'] ?? []); return $r; }
    $p = fn_lead_dir() . "/$rec.json";
    return is_file($p) ? json_decode((string)file_get_contents($p), true) : null;
}
function fn_patch($rec, $fields) { $r = fn_at('PATCH', '/' . $rec, ['fields' => $fields, 'typecast' => true]); if ($r) fn_cache_put($rec, $r['fields'] ?? $fields); return $r; }

/* ---------- email ---------- */
function fn_send($to, $subject, $text, $html = null) {
    $cfg = fn_cfg();
    $user = $cfg['smtp_user'] ?? 'crew@standardissueshirt.com';
    if (!empty($cfg['smtp_pass'])) return smtp_send($user, $cfg['smtp_pass'], $to, $subject, $text, $html);
    $h = "From: Standard Issue Shirt Co. <$user>\r\nReply-To: $user\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
    return @mail($to, $subject, $text, $h, '-f' . $user);
}
function fn_html($inner) {
    return '<!doctype html><html><body style="margin:0;background:#f4f4ef;font-family:Arial,Helvetica,sans-serif;color:#1c1f17">'
        . '<div style="max-width:600px;margin:0 auto;background:#fff;padding:28px 26px;border-top:6px solid #3F4A2A">' . $inner
        . '<p style="margin:26px 0 0;font-size:14px;line-height:1.5">Michael Brown<br>Standard Issue Shirt Co.<br>'
        . '<a href="' . FN_SITE . '" style="color:#3F4A2A">standardissueshirt.com</a> &middot; 904-382-5885</p></div></body></html>';
}
function fn_button($url, $label) {
    return '<p style="margin:22px 0"><a href="' . htmlspecialchars($url) . '" style="display:inline-block;background:#F26B1D;color:#fff;'
        . 'text-decoration:none;font-weight:bold;padding:14px 26px;font-size:16px">' . htmlspecialchars($label) . '</a></p>';
}
function fn_p($t) { return '<p style="font-size:16px;line-height:1.55;margin:0 0 14px">' . $t . '</p>'; }

/** Approve & pay link: opens the order form with their contact info filled in (and their pack, if picked). */
function fn_pay_link($rec, $pack = '') {
    $q = ['lead' => $rec, 'k' => fn_sign($rec, 'lead')];
    if (in_array($pack, ['starter', 'premium', 'custom'], true)) $q = ['pack' => $pack] + $q;
    return FN_SITE . '/order.html?' . http_build_query($q);
}

/* ---------- 1. new request -> art@ ---------- */
function notify_art($rec, $f) {
    if (!$rec) return;
    fn_cache_put($rec, $f);
    $link = FN_SITE . '/mockup.php?id=' . $rec . '&k=' . fn_sign($rec, 'mockup');
    $logo = $f['Logo file'][0]['url'] ?? '';
    $lines = [
        'NEW MOCKUP REQUEST, Standard Issue',
        '',
        'Business: ' . ($f['Business Name'] ?? ''),
        'Contact:  ' . ($f['Contact Name'] ?? '') . ', ' . ($f['Email'] ?? '') . (!empty($f['Phone']) ? ', ' . $f['Phone'] : ''),
        'Needs:    ' . implode(', ', (array)($f['What do you need?'] ?? [])),
        'Pieces:   ' . ($f['Estimated quantity'] ?? 'not given'),
        'Print:    ' . ($f['Print locations'] ?? 'not given'),
        'Need by:  ' . ($f['Need by'] ?? 'not given'),
        'Logo:     ' . ($logo !== '' ? $logo : 'NONE ATTACHED (they may reply to crew@ with it)'),
        '',
        'Notes:',
        (string)($f['Notes'] ?? ''),
        '',
        'WHEN THE MOCKUP IS READY, SEND IT HERE (one click, no email to write):',
        $link,
    ];
    $subject = 'Mockup request: ' . ($f['Business Name'] ?? 'new lead');
    $text = implode("\n", $lines);
    fn_send(FN_ART, $subject, $text);
    fn_send(fn_cfg()['notify_email'] ?? 'crew@standardissueshirt.com', $subject, $text);
    $needs = (array)($f['What do you need?'] ?? []);
    if (in_array('Branded webstore', $needs, true) || (int)($f['Estimated quantity'] ?? 0) >= 50) {
        fn_send(FN_OWNER, 'Standard Issue lead for you: ' . ($f['Business Name'] ?? ''), "This one needs your touch (webstore or 50+ pieces).\n\n" . $text);
    }
}

/* ---------- 2. mockup email to the customer ---------- */
function send_mockup_email($rec, $r, $mockupUrl, $isImage, $pieces, $total, $pack, $note) {
    $f = $r['fields'];
    $first = fn_first($f['Contact Name'] ?? '');
    $paid = ($f['Payment'] ?? '') === 'Paid';
    $pay = fn_pay_link($rec, $pack);
    $img = $isImage
        ? '<p style="margin:18px 0"><img src="' . htmlspecialchars($mockupUrl) . '" alt="Mockup of your logo on the shirts" style="max-width:100%;border:1px solid #ddd"></p>'
        : '<p style="margin:18px 0"><a href="' . htmlspecialchars($mockupUrl) . '" style="color:#3F4A2A;font-weight:bold">Open your mockup (PDF)</a></p>';
    $noteH = $note !== '' ? fn_p(nl2br(htmlspecialchars($note))) : '';
    if ($paid) {
        $subject = "Your mockup is ready, $first";
        $html = fn_html(fn_p("Hi " . htmlspecialchars($first) . ",") . fn_p("Here's your logo on the shirts. Take a look below.") . $img . $noteH
            . fn_p("<b>Reply \"approved\"</b> and we start printing. Want a change? Just reply with what to fix and we'll send an updated mockup."));
        $text = "Hi $first,\n\nHere's your logo on the shirts: $mockupUrl\n\n" . ($note !== '' ? "$note\n\n" : '')
            . "Reply \"approved\" and we start printing. Want a change? Just reply with what to fix.\n\nMichael Brown\nStandard Issue Shirt Co.\n";
    } else {
        $subject = "Your crew's shirts, $first";
        $priceLine = $total > 0 ? "Your price: <b>" . fn_money($total) . "</b>" . ($pieces > 0 ? " for $pieces pieces" : '') . ", tax included, hand delivered in 7 to 10 days." : '';
        $html = fn_html(fn_p("Hi " . htmlspecialchars($first) . ",") . fn_p("Here's your logo on the shirts. Take a look below.") . $img . $noteH
            . ($priceLine !== '' ? fn_p($priceLine) : '') . fn_button($pay, 'Approve & pay')
            . fn_p("Want a change? Just reply and we'll update it."));
        $text = "Hi $first,\n\nHere's your logo on the shirts: $mockupUrl\n\n" . ($note !== '' ? "$note\n\n" : '')
            . ($total > 0 ? "Your price: " . fn_money($total) . ($pieces > 0 ? " for $pieces pieces" : '') . ", tax included, hand delivered in 7 to 10 days.\n\n" : '')
            . "Approve & pay: $pay\n\nWant a change? Just reply and we'll update it.\n\nMichael Brown\nStandard Issue Shirt Co.\n";
    }
    return fn_send($f['Email'], $subject, $text, $html);
}

/* ---------- 3. follow-ups ---------- */
function fn_followup_copy($n, $first, $pay) {
    $f = htmlspecialchars($first);
    if ($n === 1) return ["Any changes, $first?",
        fn_p("Hi $f,") . fn_p("Most crews tweak one thing, like a color, the logo size or adding a phone number on the back. Reply with what you want and we'll send a new mockup the same day.")
        . fn_p("Or if it's good to go:") . fn_button($pay, 'Approve & pay'),
        "Hi $first,\n\nMost crews tweak one thing, like a color, the logo size or adding a phone number on the back. Reply with what you want and we'll send a new mockup the same day.\n\nOr if it's good to go: $pay\n"];
    if ($n === 2) return ["Printed right here in Jacksonville",
        fn_p("Hi $f,") . fn_p("Standard Issue shirts are printed in our shop on Talleyrand Ave by a team that's been decorating apparel for over 20 years. No overseas wait, no middleman, and we hand deliver.")
        . fn_p("Your mockup is saved and ready:") . fn_button($pay, 'Approve & pay'),
        "Hi $first,\n\nStandard Issue shirts are printed in our shop on Talleyrand Ave by a team that's been decorating apparel for over 20 years. No overseas wait, no middleman, and we hand deliver.\n\nYour mockup is saved and ready: $pay\n"];
    return ["Holding your Crew Pack price",
        fn_p("Hi $f,") . fn_p("Quick note: Crew Pack pricing is good through Dec 31. Your mockup and price are saved here:") . fn_button($pay, 'Approve & pay')
        . fn_p("If now's not the right time, no problem. Just reply \"later\" and we'll check back in the new year."),
        "Hi $first,\n\nQuick note: Crew Pack pricing is good through Dec 31. Your mockup and price are saved here: $pay\n\nIf now's not the right time, no problem. Just reply \"later\" and we'll check back in the new year.\n"];
}

/** Has this email paid for any order? (paid online orders are saved as their own Inquiries rows) */
function fn_has_paid($email) {
    $e = str_replace(["'", '\\'], '', strtolower(trim($email)));
    $r = fn_at('GET', '', null, ['filterByFormula' => "AND(LOWER({Email})='$e',{Payment}='Paid')", 'maxRecords' => 1, 'fields[]' => 'Email']);
    return !empty($r['records']);
}

function send_followups($limit = 10) {
    if (fn_token() === '') return 0;
    $formula = "AND({Status}='Mockup sent',{Payment}!='Paid',{Email}!='',{Mockup sent at}!='',{Follow-ups sent}<3)";
    $r = fn_at('GET', '', null, ['filterByFormula' => $formula, 'pageSize' => 50]);
    $sent = 0;
    foreach (($r['records'] ?? []) as $rec) {
        if ($sent >= $limit) break;
        $f = $rec['fields'];
        $done = (int)($f['Follow-ups sent'] ?? 0);
        $days = (time() - strtotime($f['Mockup sent at'])) / 86400;
        if ($done >= 3 || $days < FN_DAYS[$done]) continue;
        if ($days > FN_DAYS[$done] + 7) { fn_patch($rec['id'], ['Follow-ups sent' => $done + 1]); continue; }   // missed window: skip, don't blast late
        if (fn_has_paid($f['Email'])) { fn_patch($rec['id'], ['Status' => 'Ordered']); continue; }
        $n = $done + 1;
        $pack = '';
        if (preg_match('/\[pack:(starter|premium|custom)\]/', (string)($f['Notes'] ?? ''), $m)) $pack = $m[1];
        [$subject, $inner, $text] = fn_followup_copy($n, fn_first($f['Contact Name'] ?? ''), fn_pay_link($rec['id'], $pack));
        // claim the step first so two runs never send the same follow-up twice
        if (!fn_patch($rec['id'], ['Follow-ups sent' => $n])) continue;
        if (fn_send($f['Email'], $subject, $text . "\nMichael Brown\nStandard Issue Shirt Co.\nstandardissueshirt.com\n", fn_html($inner))) $sent++;
    }
    return $sent;
}
