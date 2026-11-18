<?php
/*
 * Send mockup page for Ally (private: only opens from the signed link in the art@ email).
 * GET  mockup.php?id=REC&k=SIG   shows the lead and the upload form
 * POST mockup.php                uploads the mockup, emails the customer, marks Airtable "Mockup sent"
 */
require __DIR__ . '/inc/funnel.php';
require __DIR__ . '/inc/catalog.php';
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$id = (string)($_REQUEST['id'] ?? '');
$k  = (string)($_REQUEST['k'] ?? '');
$h  = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);

function page($title, $body) {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . htmlspecialchars($title) . '</title><style>'
        . 'body{margin:0;background:#f4f4ef;font:16px/1.5 Arial,Helvetica,sans-serif;color:#1c1f17}.w{max-width:640px;margin:0 auto;padding:22px}'
        . 'h1{font-size:24px;margin:0 0 4px}.card{background:#fff;border-top:6px solid #3F4A2A;padding:20px;margin:14px 0}'
        . 'label{display:block;font-weight:bold;margin:14px 0 6px}input,select,textarea{width:100%;box-sizing:border-box;padding:11px;font:inherit;border:2px solid #1c1f17;background:#fff}'
        . '.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.btn{margin-top:18px;width:100%;background:#F26B1D;color:#fff;border:0;padding:15px;font-weight:bold;font-size:17px;cursor:pointer}'
        . '.muted{color:#5b604f;font-size:14px}.ok{background:#e6f2dc;border-left:6px solid #3F4A2A;padding:14px}.bad{background:#fde8e0;border-left:6px solid #c0392b;padding:14px}'
        . 'dl{display:grid;grid-template-columns:110px 1fr;gap:4px 10px;margin:0}dt{font-weight:bold}dd{margin:0}'
        . '</style></head><body><div class="w">' . $body . '</div></body></html>';
    exit;
}

if (!fn_check($id, 'mockup', $k)) { http_response_code(403); page('Link not valid', '<div class="card bad">This link is not valid. Open the latest "Mockup request" email in art@ and use the link there.</div>'); }
$r = fn_get($id);
if (!$r) page('Not found', '<div class="card bad">Could not load this lead from Airtable. Try again in a minute.</div>');
$f = $r['fields'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($f['Email'])) page('No email', '<div class="card bad">This lead has no email address, so the mockup can\'t be emailed. Text it to them instead.</div>');
    $file = $_FILES['mockup'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) page('Missing file', '<div class="card bad">Please choose the mockup file. <a href="javascript:history.back()">Go back</a></div>');
    if ($file['size'] > 8 * 1024 * 1024) page('Too big', '<div class="card bad">That file is over 8 MB. Export a smaller JPG or PNG. <a href="javascript:history.back()">Go back</a></div>');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'pdf' => 'application/pdf'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($types[$ext]) || $types[$ext] !== $mime) page('Wrong type', '<div class="card bad">Use a PNG, JPG or PDF. <a href="javascript:history.back()">Go back</a></div>');
    $dir = __DIR__ . '/mockups';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) page('Upload failed', '<div class="card bad">Upload failed. Try again.</div>');
    $url = FN_SITE . '/mockups/' . $name;

    $pieces = max(0, (int)($_POST['pieces'] ?? 0));
    $total  = max(0, round((float)str_replace([',', '$'], '', (string)($_POST['total'] ?? '0')), 2));
    $pack   = in_array($_POST['pack'] ?? '', ['starter', 'premium', 'custom'], true) ? $_POST['pack'] : 'custom';
    $note   = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 600);
    $styles = si_catalog()['styles'];
    $style  = isset($styles[$_POST['style'] ?? '']) ? $_POST['style'] : '';
    $color  = ($style !== '' && in_array($_POST['color'] ?? '', $styles[$style]['colors'], true)) ? $_POST['color'] : '';
    $shirtLine = $style !== '' ? $styles[$style]['name'] . ($color !== '' ? ", $color" : '') : '';

    if (!send_mockup_email($id, $r, $url, $ext !== 'pdf', $pieces, $total, $pack, $note)) {
        page('Email failed', '<div class="card bad">The mockup uploaded, but the email to the customer did not send. Email it to them from crew@ for now and tell Michael.</div>');
    }
    $notes = trim((string)($f['Notes'] ?? '')) . "\n\n" . date('M j, g:ia') . ": mockup sent by Send mockup page [pack:$pack]" . ($shirtLine !== '' ? " on $shirtLine" : '') . ($note !== '' ? "\nNote to customer: $note" : '');
    $upd = ['Mockup' => [['url' => $url, 'filename' => $name]], 'Mockup sent at' => gmdate('c'), 'Notes' => $notes];
    if (($f['Payment'] ?? '') !== 'Paid') { $upd += ['Status' => 'Mockup sent', 'Follow-ups sent' => 0]; }
    if ($pieces > 0) $upd['Mockup pieces'] = $pieces;
    if ($total > 0) $upd['Mockup price'] = $total;
    fn_patch($id, $upd);
    fn_cache_put($id, ['Mockup URL' => $url, 'Mockup note' => $note, 'Mockup style' => $style, 'Mockup color' => $color]);   // our own link (Airtable's attachment links expire)
    if ($total >= 1500 || $pieces >= 50) {
        fn_send(FN_OWNER, 'Big Standard Issue mockup sent: ' . ($f['Business Name'] ?? ''), "Mockup sent to " . ($f['Contact Name'] ?? '') . " (" . $f['Email'] . ")\nPieces: $pieces\nTotal: " . fn_money($total) . "\nMockup: $url\n\nWorth a personal call.");
    }
    page('Sent', '<div class="card ok"><b>Sent!</b> ' . $h($f['Contact Name'] ?? 'The customer') . ' just got the mockup' . (($f['Payment'] ?? '') === 'Paid' ? ' (already paid, so it asks them to reply "approved").' : ' with the Approve &amp; pay button. Follow-ups go out automatically on day 2, 5 and 9 unless they pay or you change Status in Airtable.') . '</div>'
        . '<p class="muted">Need to send a revised mockup? Use the same link again from the art@ email.</p>');
}

$cat = si_catalog()['styles'];
$styleOpts = '';
foreach ($cat as $key => $st) $styleOpts .= '<option value="' . $h($key) . '">' . $h($st['name']) . '</option>';
$colorJson = json_encode(array_map(fn($st) => $st['colors'], $cat));
$already = !empty($f['Mockup sent at']);
$logo = $f['Logo file'][0]['url'] ?? '';
$est = (int)($f['Estimated quantity'] ?? 0);
page('Send mockup: ' . ($f['Business Name'] ?? ''),
    '<h1>Send mockup</h1><p class="muted">Standard Issue Shirt Co.</p>'
    . ($already ? '<div class="card ok">A mockup was already sent on ' . $h(date('M j, g:ia', strtotime($f['Mockup sent at']))) . '. Sending again emails the customer the new version.</div>' : '')
    . '<div class="card"><dl>'
    . '<dt>Business</dt><dd>' . $h($f['Business Name'] ?? '') . '</dd>'
    . '<dt>Contact</dt><dd>' . $h($f['Contact Name'] ?? '') . '</dd>'
    . '<dt>Email</dt><dd>' . $h($f['Email'] ?? 'none') . '</dd>'
    . '<dt>Needs</dt><dd>' . $h(implode(', ', (array)($f['What do you need?'] ?? []))) . '</dd>'
    . '<dt>Pieces</dt><dd>' . ($est ?: 'not given') . '</dd>'
    . '<dt>Logo</dt><dd>' . ($logo ? '<a href="' . $h($logo) . '">Download logo</a>' : 'none attached') . '</dd>'
    . (($f['Payment'] ?? '') === 'Paid' ? '<dt>Payment</dt><dd><b>Already paid</b> (' . $h(fn_money($f['Amount paid'] ?? 0)) . ')</dd>' : '')
    . '</dl></div>'
    . '<form class="card" method="post" enctype="multipart/form-data">'
    . '<input type="hidden" name="id" value="' . $h($id) . '"><input type="hidden" name="k" value="' . $h($k) . '">'
    . '<label for="m">Mockup file (JPG or PNG best, PDF ok, under 8 MB)</label><input id="m" type="file" name="mockup" accept=".png,.jpg,.jpeg,.pdf" required>'
    . '<div class="row"><div><label for="st">Shirt in the mockup</label><select id="st" name="style">' . $styleOpts . '</select></div>'
    . '<div><label>Shirt color</label><div id="sw"></div></div></div>'
    . (($f['Payment'] ?? '') === 'Paid' ? '' :
        '<label for="pk">Offer</label><select id="pk" name="pack"><option value="starter">Starter Crew Pack</option><option value="premium">Premium Crew Pack</option><option value="custom" selected>Custom order</option></select>'
        . '<div class="row"><div><label for="pc">Pieces</label><input id="pc" type="number" name="pieces" min="0" value="' . ($est ?: '') . '"></div>'
        . '<div><label for="tt">Total price, tax included</label><input id="tt" name="total" inputmode="decimal" placeholder="595.00"></div></div>')
    . '<label for="nt">Note to the customer (optional)</label><textarea id="nt" name="note" rows="3" placeholder="e.g. We matched your orange to the logo. Happy to try it on black too."></textarea>'
    . '<button class="btn" type="submit">Send mockup</button>'
    . '<p class="muted">The customer gets it from crew@standardissueshirt.com. Replies come back to crew@.</p></form>'
    . '<script src="/js/swatches.js"></script><script>(function(){var C=' . $colorJson . ',sel=document.getElementById("st"),box=document.getElementById("sw");'
    . 'function draw(){SISwatch.picker(box,C[sel.value],"White",null,{name:"color"});}sel.addEventListener("change",draw);draw();})();</script>');
