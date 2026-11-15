<?php
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

