<?php
// Nectar of Armenia — data API
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/db.php';
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_name('NOFASESS'); session_start();
function out($a, $code = 200) { http_response_code($code); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function body() { $r = file_get_contents('php://input'); if (strlen($r) > 6 * 1024 * 1024) { out(['ok' => false, 'err' => 'too_big'], 413); } $j = json_decode($r, true); return is_array($j) ? $j : []; }
function now_ms() { return (int) round(microtime(true) * 1000); }
$a = $_GET['a'] ?? '';
$cfg = nofa_cfg();
if ($a === 'ping') { out(['ok' => true, 'configured' => (bool) $cfg, 'admin' => !empty($_SESSION['nofa_admin'])]); }
if (!$cfg) { out(['ok' => false, 'err' => 'not_configured'], 503); }
try { $db = nofa_db(); } catch (Exception $e) { out(['ok' => false, 'err' => 'db'], 503); }
$isAdmin = !empty($_SESSION['nofa_admin']);
$SHARED = '/^noa-(accounts|orders|ws-orders(\|.*)?|chat(\|.*)?|cost-v2|inv2|invites(\|.*)?|drivers|paycfg|admin-ov|stock|stock-full|stock-pcs|prod|theme-sel|theme-dates|notify|cities|city-req|members\|.*|deposit\|.*|depsign\|.*|rewards\|.*|refs|geo|invoices|dlv)$/';
$PUBLIC = ['noa-theme-sel', 'noa-theme-dates', 'noa-stock', 'noa-stock-full', 'noa-cities', 'noa-paycfg'];
function kv_get($db, $k) { $s = $db->prepare('SELECT v, t FROM nofa_kv WHERE k = ?'); $s->execute([$k]); $r = $s->fetch(PDO::FETCH_ASSOC); return $r ?: null; }
function apri_rpc($fn, $args, &$code) {
  $url = 'https://oddqwjvqqvoeqdrnasqc.supabase.co/rest/v1/rpc/' . $fn; $key = 'sb_publishable_ZVvJKCJXM5ylUlICX-57vw__ZZqQul0';
  $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15, 'ignore_errors' => true, 'header' => "Content-Type: application/json\r\napikey: $key\r\nAuthorization: Bearer $key\r\n", 'content' => json_encode($args, JSON_UNESCAPED_UNICODE)]]);
  $r = @file_get_contents(getenv('NOFA_APRI_URL') ? getenv('NOFA_APRI_URL') . $fn : $url, false, $ctx); $code = 0;
  if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $mm)) { $code = (int) $mm[1]; }
  return $r;
}

// Customer account verification (email + type + token), used by account linking.
function cust_rec($db, $em, $type, $tok) {
  $em = strtolower(trim((string) $em)); $type = $type === 'biz' ? 'biz' : 'retail'; $tok = (string) $tok;
  if ($em === '' || strlen($tok) < 16) { return null; }
  $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: [];
  foreach ($A as $key => $rec) { $kt = substr((string) $key, -4) === '|biz' ? 'biz' : ((isset($rec['type']) && $rec['type'] === 'biz') ? 'biz' : 'retail'); if (is_array($rec) && strtolower($rec['email'] ?? '') === $em && $kt === $type && isset($rec['tok']) && hash_equals((string) $rec['tok'], $tok)) { return [$em . '|' . $type, $rec]; } }
  return null;
}
function stripe_sk($db) { $r = kv_get($db, '__stripe'); $j = $r ? (json_decode($r['v'], true) ?: []) : []; return (string) ($j['sk'] ?? ''); }
function stripe_api($sk, $method, $path, $form, &$code) {
  $url = (getenv('NOFA_STRIPE_URL') ?: 'https://api.stripe.com/v1/') . $path; $q = http_build_query($form); $code = 0;
  if (function_exists('curl_init')) { $ch = curl_init($method === 'GET' && $q ? $url . '?' . $q : $url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_USERPWD => $sk . ':', CURLOPT_HTTPHEADER => ['Stripe-Version: 2024-06-20']]);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $q); } $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); return $r; }
  $ctx = stream_context_create(['http' => ['method' => $method, 'timeout' => 25, 'ignore_errors' => true, 'header' => "Authorization: Bearer $sk\r\nStripe-Version: 2024-06-20\r\nContent-Type: application/x-www-form-urlencoded\r\n", 'content' => $method === 'POST' ? $q : '']]);
  $r = @file_get_contents($method === 'GET' && $q ? $url . '?' . $q : $url, false, $ctx); if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $mm)) { $code = (int) $mm[1]; } return $r;
}
function sms_conf($db) { $r = kv_get($db, '__sms'); return $r ? (json_decode($r['v'], true) ?: []) : []; }
function sms_e164($p) { $d = preg_replace('/\D/', '', (string) $p); if (strlen($d) === 10) { return '+1' . $d; } if (strlen($d) === 11 && $d[0] === '1') { return '+' . $d; } return ''; }
function sms_one($c, $to, $text, &$err) {
  if (($c['provider'] ?? '') === 'twilio') { $url = (getenv('NOFA_SMS_URL') ?: 'https://api.twilio.com/2010-04-01/Accounts/') . rawurlencode($c['sid']) . '/Messages.json'; $hdr = ['Authorization: Basic ' . base64_encode($c['sid'] . ':' . $c['key']), 'Content-Type: application/x-www-form-urlencoded']; $payload = http_build_query(['To' => $to, 'From' => $c['from'], 'Body' => $text]); }
  else { $url = getenv('NOFA_SMS_URL') ?: 'https://api.telnyx.com/v2/messages'; $hdr = ['Authorization: Bearer ' . $c['key'], 'Content-Type: application/json']; $payload = json_encode(['from' => $c['from'], 'to' => $to, 'text' => $text], JSON_UNESCAPED_UNICODE); }
  $code = 0; $r = false;
  if (function_exists('curl_init')) { $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $hdr]); $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); }
  else { $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 20, 'ignore_errors' => true, 'header' => implode("\r\n", $hdr) . "\r\n", 'content' => $payload]]); $r = @file_get_contents($url, false, $ctx); if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $mm)) { $code = (int) $mm[1]; } }
  if ($code >= 200 && $code < 300) { return true; }
  $j = json_decode((string) $r, true); $err = (string) ($j['errors'][0]['detail'] ?? $j['message'] ?? ('http ' . $code)); $err = substr($err, 0, 160); return false;
}
function mail_cfg($db) { $r = kv_get($db, '__mail'); return $r ? (json_decode($r['v'], true) ?: []) : []; }
function smtp_send($m, $to, $subject, $html, &$err = null) {
  $host = (string) ($m['host'] ?? 'smtp.hostinger.com'); $port = (int) ($m['port'] ?? 465); $user = (string) ($m['user'] ?? ''); $pass = (string) ($m['pass'] ?? '');
  if ($user === '' || $pass === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) { $err = 'cfg'; return false; }
  $plain = getenv('NOFA_SMTP_PLAIN') ? true : false; $fp = @stream_socket_client(($plain ? 'tcp://' : 'ssl://') . $host . ':' . $port, $en, $es, 15); if (!$fp) { $err = 'connect ' . $es; return false; }
  stream_set_timeout($fp, 15);
  $rd = function () use ($fp) { $d = ''; while (($l = fgets($fp, 515)) !== false) { $d .= $l; if (strlen($l) < 4 || $l[3] === ' ') { break; } } return $d; };
  $cmd = function ($c, $ok) use ($fp, $rd, &$err) { if ($c !== null) { fwrite($fp, $c . "\r\n"); } $r = $rd(); if (!in_array(substr($r, 0, 3), (array) $ok, true)) { $err = trim($r); return false; } return true; };
  $dom = substr(strrchr($user, '@'), 1) ?: 'localhost';
  if (!$cmd(null, '220') || !$cmd('EHLO ' . $dom, '250') || !$cmd('AUTH LOGIN', '334') || !$cmd(base64_encode($user), '334') || !$cmd(base64_encode($pass), '235') || !$cmd('MAIL FROM:<' . $user . '>', '250') || !$cmd('RCPT TO:<' . $to . '>', ['250', '251']) || !$cmd('DATA', '354')) { fclose($fp); return false; }
  $enc = function ($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; };
  $h = 'Date: ' . date('r') . "\r\nFrom: " . $enc('Nectar of Armenia') . ' <' . $user . ">\r\nTo: <" . $to . ">\r\nReply-To: <" . $user . ">\r\nSubject: " . $enc($subject) . "\r\nMessage-ID: <" . bin2hex(random_bytes(12)) . '@' . $dom . ">\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
  $body = chunk_split(base64_encode($html));
  fwrite($fp, $h . $body . "\r\n.\r\n"); $ok = $cmd(null, '250'); $cmd('QUIT', ['221', '250']); fclose($fp); return $ok;
}
function mail_tpl($title, $inner) {
  return '<!doctype html><html><body style="margin:0;background:#F7F3EA;font-family:Arial,Helvetica,sans-serif;color:#23342C"><div style="max-width:560px;margin:0 auto;padding:24px 14px"><div style="background:#1D5545;border-radius:18px 18px 0 0;padding:18px 22px;color:#F6E3B4;font-size:20px;font-weight:bold">Nectar of Armenia</div><div style="background:#ffffff;border-radius:0 0 18px 18px;padding:22px;border:1px solid #E4DCC8;border-top:0"><h2 style="margin:0 0 12px;color:#1D5545;font-size:20px">' . $title . '</h2>' . $inner . '</div><p style="text-align:center;color:#6B756F;font-size:12px;margin:14px 0 0">Nectar of Armenia Inc · 6222 San Fernando Rd, Glendale, CA 91201 · +1 (515) 588-5555</p></div></body></html>';
}
function mail_esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function pay_mails($db, $ref, $amt) { // after a confirmed payment: receipt to the customer + notice to the owner (once)
  $m = mail_cfg($db); if (empty($m['user'])) { return; } $pk = '__pay|' . $ref; $pr = kv_get($db, $pk); $pv = $pr ? (json_decode($pr['v'], true) ?: []) : []; if (!empty($pv['mailed'])) { return; }
  $pv['mailed'] = now_ms(); kv_put($db, $pk, json_encode($pv, JSON_UNESCAPED_UNICODE));
  $rows = ''; foreach ((array) ($pv['lines'] ?? []) as $l) { $rows .= '<tr><td style="padding:6px 0">' . mail_esc($l[0]) . ' × ' . (int) $l[1] . '</td><td style="padding:6px 0;text-align:right">$' . number_format((float) $l[2], 2) . '</td></tr>'; }
  if (!empty($pv['fee']) && strpos($ref, 'INV:') !== 0) { $rows .= '<tr><td style="padding:6px 0">Delivery</td><td style="padding:6px 0;text-align:right">$' . number_format((float) $pv['fee'], 2) . '</td></tr>'; }
  $tbl = '<table style="width:100%;border-collapse:collapse;font-size:15px">' . $rows . '<tr><td style="padding:10px 0;border-top:1px solid #E4DCC8;font-weight:bold">Total paid</td><td style="padding:10px 0;border-top:1px solid #E4DCC8;text-align:right;font-weight:bold;color:#1D5545">$' . number_format((float) $amt, 2) . '</td></tr></table>';
  $isInv = strpos($ref, 'INV:') === 0; $label = $isInv ? ('Invoice #' . ($pv['no'] ?? substr($ref, 4))) : ('Order ' . $ref);
  $extra = !$isInv ? ('<p style="margin:12px 0 0;font-size:15px">' . (($pv['fm'] ?? '') === 'pick' ? 'Pickup: 6222 San Fernando Rd, Glendale, CA 91201' : 'Delivery: ' . mail_esc($pv['addr'] ?? '')) . (!empty($pv['day']) ? '<br>Date: ' . mail_esc(substr((string) $pv['day'], 0, 10)) : '') . '</p>') : '';
  $cust = (string) ($pv['email'] ?? ''); $site = (string) ($pv['site'] ?? '');
  if (filter_var($cust, FILTER_VALIDATE_EMAIL)) { smtp_send($m, $cust, 'Payment received — ' . $label, mail_tpl('Thank you! Payment received ✓', '<p style="margin:0 0 14px;font-size:15px">' . mail_esc($label) . ' is confirmed. We are getting it ready.</p>' . $tbl . $extra . ($site ? '<p style="margin:18px 0 0"><a href="' . mail_esc($site) . '" style="display:inline-block;background:#EBA93A;color:#133B30;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:999px">View my order</a></p>' : '') . '<p style="margin:16px 0 0;color:#6B756F;font-size:13px">Questions? Reply to this email or call +1 (515) 588-5555.</p>')); }
  $own = (string) ($m['notify'] ?? $m['user']); if (filter_var($own, FILTER_VALIDATE_EMAIL)) { smtp_send($m, $own, '💳 New payment $' . number_format((float) $amt, 2) . ' — ' . $label, mail_tpl('New payment received', '<p style="margin:0 0 14px;font-size:15px"><b>' . mail_esc($label) . '</b><br>' . mail_esc($pv['name'] ?? '') . ' · ' . mail_esc($cust) . ' · ' . mail_esc($pv['phone'] ?? '') . '</p>' . $tbl . $extra)); }
}
function links_get($db) { return json_decode((kv_get($db, '__links') ?: ['v' => '{}'])['v'], true) ?: []; }
function kv_put($db, $k, $v) { $t = now_ms(); $s = $db->prepare('REPLACE INTO nofa_kv (k, v, t) VALUES (?, ?, ?)'); $s->execute([$k, $v, $t]); return $t; }

// One-time price update (Nov 10): gata unpackaged $1.50/piece; pelmeni wholesale $22 / 900 g, packaged only.
$__c = kv_get($db, 'noa-cost-v2');
if ($__c) { $__v = json_decode($__c['v'], true); if (is_array($__v) && empty($__v['prNov10']) && !empty($__v['prod']) && is_array($__v['prod'])) {
  foreach ($__v['prod'] as &$__p) { if (!is_array($__p) || !isset($__p['id'])) { continue; } if (!isset($__p['pr']) || !is_array($__p['pr'])) { $__p['pr'] = [0, 0, 0, 0]; } while (count($__p['pr']) < 4) { $__p['pr'][] = 0; }
    if ($__p['id'] === 'gata') { $__p['pr'][3] = 1.5; }
    if ($__p['id'] === 'pelmeni') { $__p['pr'][2] = 22; $__p['pr'][3] = 0; $__p['noBulk'] = true; } }
  unset($__p); $__v['prNov10'] = 1; kv_put($db, 'noa-cost-v2', json_encode($__v, JSON_UNESCAPED_UNICODE)); } }
// One-time payment details update (Nov 10): Zelle by phone only; Bank of America routing numbers (public bank numbers). The account number is entered by the owner in Admin → Settings.
$__c = kv_get($db, 'noa-paycfg'); $__v = $__c ? (json_decode($__c['v'], true) ?: []) : [];
if (empty($__v['payNov10'])) {
  if (empty($__v['zelle']) || strpos((string) $__v['zelle'], '@') !== false) { $__v['zelle'] = !empty($__v['zphone']) ? $__v['zphone'] : '+1 (515) 588 5555'; }
  if (empty($__v['zphone'])) { $__v['zphone'] = $__v['zelle']; }
  if (empty($__v['bank'])) { $__v['bank'] = 'Bank of America'; }
  if (empty($__v['acctName'])) { $__v['acctName'] = 'Nectar of Armenia Inc'; }
  if (empty($__v['routing'])) { $__v['routing'] = '121000358'; }
  if (empty($__v['wireRouting'])) { $__v['wireRouting'] = '026009593'; }
  $__v['payNov10'] = 1; kv_put($db, 'noa-paycfg', json_encode($__v, JSON_UNESCAPED_UNICODE));
}

switch ($a) {
  case 'login':
    $b = body(); $pw = (string) ($b['pw'] ?? '');
    if ($pw !== '' && password_verify($pw, $cfg['admin_hash'])) { session_regenerate_id(true); $_SESSION['nofa_admin'] = 1; out(['ok' => true]); }
    sleep(1); out(['ok' => false, 'err' => 'bad_password'], 401);
  case 'cpw_set': // customer: set or change the account password (needs the account token)
    $b = body(); $em = strtolower(trim((string) ($b['em'] ?? ''))); $tok = (string) ($b['tok'] ?? ''); $pw = (string) ($b['pw'] ?? '');
    if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $em) || strlen($tok) < 16 || strlen($pw) < 8 || strlen($pw) > 200) { out(['ok' => false, 'err' => 'bad'], 400); }
    $k = '__cpw|' . $em; $old = kv_get($db, $k); $old = $old ? (json_decode($old['v'], true) ?: []) : [];
    if ($old) { if (!hash_equals((string) ($old['tok'] ?? ''), $tok)) { out(['ok' => false, 'err' => 'exists'], 403); } }
    else { $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; foreach ($A as $key => $rec) { if (is_array($rec) && strtolower((string) ($rec['email'] ?? '')) === $em && !empty($rec['tok']) && !hash_equals((string) $rec['tok'], $tok)) { out(['ok' => false, 'err' => 'exists'], 403); } } }
    $acc = []; foreach ((array) ($b['acc'] ?? []) as $key => $rec) { $key = strtolower((string) $key); if (is_array($rec) && strpos($key, $em . '|') === 0 && count($acc) < 4) { unset($rec['pw'], $rec['pass']); $acc[$key] = $rec; } }
    if (!$acc && !empty($old['acc'])) { $acc = $old['acc']; }
    kv_put($db, $k, json_encode(['h' => password_hash($pw, PASSWORD_DEFAULT), 'tok' => $tok, 'acc' => $acc, 't' => now_ms()], JSON_UNESCAPED_UNICODE));
    if (!$old) { $m = mail_cfg($db); if (!empty($m['user']) && !empty($m['pass'])) { // welcome email for a brand-new account (once per email)
      $first = ''; foreach ($acc as $rec) { if (!empty($rec['first'])) { $first = (string) $rec['first']; break; } }
      $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); $host = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'armenonline.com'); $base = $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/api.php')), '/');
      $hy = (($b['lang'] ?? 'hy') === 'hy'); $btn = '<p style="margin:18px 0 0"><a href="' . mail_esc($base . '/Account.dc.html') . '" style="display:inline-block;background:#1D5545;color:#F6E3B4;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:700">' . ($hy ? 'Մուտք գործել' : 'Sign in') . '</a></p>';
      $inner = $hy ? '<p style="font-size:15px;margin:0 0 10px">Բարև' . ($first !== '' ? ' ' . mail_esc($first) : '') . ',</p><p style="font-size:15px;margin:0">Ձեր հաշիվը Nectar of Armenia-ում ստեղծված է։ Այստեղից կարող եք պատվիրել, հետևել առաքմանը և տեսնել ձեր պատվերները։</p><p style="font-size:13px;color:#6B756F;margin:12px 0 0">Եթե դուք չեք գրանցվել, պարզապես անտեսեք այս նամակը կամ զանգահարեք +1 (515) 588-5555։</p>' . $btn
        : '<p style="font-size:15px;margin:0 0 10px">Hello' . ($first !== '' ? ' ' . mail_esc($first) : '') . ',</p><p style="font-size:15px;margin:0">Your Nectar of Armenia account is ready. You can order, track deliveries and see your orders there.</p><p style="font-size:13px;color:#6B756F;margin:12px 0 0">If you did not sign up, just ignore this email or call +1 (515) 588-5555.</p>' . $btn;
      $e = null; @smtp_send($m, $em, $hy ? 'Բարի գալուստ Nectar of Armenia' : 'Welcome to Nectar of Armenia', mail_tpl($hy ? 'Բարի գալուստ' : 'Welcome', $inner), $e); } }
    out(['ok' => true]);
  case 'cpw_admin': // admin: give a customer a temporary password (forgot-password help)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $b = body(); $em = strtolower(trim((string) ($b['em'] ?? ''))); $pw = (string) ($b['pw'] ?? '');
    if ($em === '' || strlen($pw) < 8) { out(['ok' => false, 'err' => 'bad'], 400); }
    $k = '__cpw|' . $em; $old = kv_get($db, $k); $old = $old ? (json_decode($old['v'], true) ?: []) : [];
    $tok = (string) ($old['tok'] ?? ''); $acc = (array) ($old['acc'] ?? []);
    $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: [];
    foreach ($A as $key => $x) { if (is_array($x) && strtolower((string) ($x['email'] ?? '')) === $em) { $acc[strtolower((string) $key)] = $x; if ($tok === '' && !empty($x['tok'])) { $tok = (string) $x['tok']; } } }
    if (!$acc) { out(['ok' => false, 'err' => 'nouser'], 404); }
    if ($tok === '') { $tok = bin2hex(random_bytes(16)); }
    kv_put($db, $k, json_encode(['h' => password_hash($pw, PASSWORD_DEFAULT), 'tok' => $tok, 'acc' => $acc, 't' => now_ms()], JSON_UNESCAPED_UNICODE));
    out(['ok' => true]);
  case 'cexists': // checkout: is this email already a customer account? (so a guest order doesn't clash with it)
    $b = body(); $em = strtolower(trim((string) ($b['em'] ?? ''))); usleep(300000); if ($em === '') { out(['ok' => true, 'exists' => false]); }
    $has = (bool) kv_get($db, '__cpw|' . $em); if (!$has) { $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; foreach ($A as $key => $x) { if (is_array($x) && strtolower((string) ($x['email'] ?? '')) === $em) { $has = true; break; } } }
    out(['ok' => true, 'exists' => $has]);
  case 'clogin': // customer: sign in with email + password on any device
    $b = body(); $em = strtolower(trim((string) ($b['em'] ?? ''))); $pw = (string) ($b['pw'] ?? '');
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); $fk = '__clfail|' . $ip; $fr = kv_get($db, $fk); $fv = $fr ? (json_decode($fr['v'], true) ?: []) : [];
    $fv = array_values(array_filter((array) $fv, function ($t) { return $t > now_ms() - 3600000; }));
    if (count($fv) >= 20) { out(['ok' => false, 'err' => 'rate'], 429); }
    $r = kv_get($db, '__cpw|' . $em); $rec = $r ? (json_decode($r['v'], true) ?: []) : [];
    if (!$rec) { $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; $has = false; foreach ($A as $key => $x) { if (is_array($x) && strtolower((string) ($x['email'] ?? '')) === $em) { $has = true; } } if ($has) { out(['ok' => false, 'err' => 'nopw']); } }
    if (!$rec || $pw === '' || !password_verify($pw, (string) ($rec['h'] ?? ''))) { $fv[] = now_ms(); kv_put($db, $fk, json_encode($fv)); sleep(1); out(['ok' => false, 'err' => 'bad'], 401); }
    $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; $mine = (array) ($rec['acc'] ?? []);
    foreach ($A as $key => $x) { if (is_array($x) && strtolower((string) ($x['email'] ?? '')) === $em) { $mine[strtolower((string) $key)] = $x; } }
    foreach ($mine as $key => $x) { $mine[$key]['tok'] = $rec['tok']; }
    out(['ok' => true, 'tok' => $rec['tok'], 'accounts' => $mine]);
  case 'logout':
    $_SESSION = []; session_destroy(); out(['ok' => true]);
  case 'all': // admin: everything
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $since = (int) ($_GET['since'] ?? 0);
    $s = $db->prepare("SELECT k, v, t FROM nofa_kv WHERE t > ? AND k NOT LIKE '\\_\\_%' ESCAPE '\\'"); $s->execute([$since]);
    $res = []; $mx = $since; foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $res[$r['k']] = ['v' => $r['v'], 't' => (int) $r['t']]; if ($r['t'] > $mx) { $mx = (int) $r['t']; } }
    $n = (int) $db->query("SELECT COUNT(*) FROM nofa_kv WHERE k NOT LIKE '\\_\\_%' ESCAPE '\\'")->fetchColumn();
    out(['ok' => true, 'kv' => $res, 'now' => $mx, 'count' => $n]);
  case 'put': // admin: save keys
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $b = body(); $done = [];
    foreach (($b['items'] ?? []) as $it) {
      $k = (string) ($it['k'] ?? ''); if (!preg_match($SHARED, $k)) { continue; }
      $v = $it['v'] ?? null;
      if (!empty($it['backup'])) { $old = kv_get($db, $k); if ($old && $old['v'] !== $v) { $s = $db->prepare('INSERT INTO nofa_backup (k, v, t) VALUES (?, ?, ?)'); $s->execute([$k, $old['v'], now_ms()]); } }
      if ($v === null) { $db->prepare('DELETE FROM nofa_kv WHERE k = ?')->execute([$k]); $done[$k] = now_ms(); }
      else { $done[$k] = kv_put($db, $k, (string) $v); }
    }
    out(['ok' => true, 't' => $done]);
  case 'inbox': // admin: read customer submissions
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $rows = $db->query('SELECT id, kind, em, data, t FROM nofa_inbox ORDER BY id ASC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
    out(['ok' => true, 'items' => $rows]);
  case 'ack':
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $b = body(); $ids = array_map('intval', $b['ids'] ?? []);
    if ($ids) { $q = implode(',', array_fill(0, count($ids), '?')); $db->prepare("DELETE FROM nofa_inbox WHERE id IN ($q)")->execute($ids); }
    out(['ok' => true]);
  case 'submit': // anyone: customer data goes to inbox (append-only)
    $b = body(); $items = $b['items'] ?? []; $em = strtolower(substr((string) ($b['em'] ?? ''), 0, 190)); $tok = substr((string) ($b['tok'] ?? ''), 0, 64);
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64);
    $s = $db->prepare('SELECT COUNT(*) FROM nofa_inbox WHERE ip = ? AND t > ?'); $s->execute([$ip, now_ms() - 86400000]);
    if ((int) $s->fetchColumn() > 800) { out(['ok' => false, 'err' => 'rate'], 429); }
    $ins = $db->prepare((nofa_is_sqlite() ? 'INSERT OR IGNORE' : 'INSERT IGNORE') . ' INTO nofa_inbox (kind, em, h, data, ip, t) VALUES (?, ?, ?, ?, ?, ?)');
    $n = 0;
    foreach (array_slice($items, 0, 40) as $it) {
      $kind = substr((string) ($it['kind'] ?? 'key'), 0, 20); $data = json_encode(['k' => $it['k'] ?? '', 'v' => $it['v'] ?? null, 'tok' => $tok], JSON_UNESCAPED_UNICODE);
      if (strlen($data) > 400000) { continue; }
      $ins->execute([$kind, $em, hash('sha256', $em . '|' . $data), $data, $ip, now_ms()]); $n++;
    }
    out(['ok' => true, 'n' => $n]);
  case 'apri_cfg': // admin: save My Apri link token (stored server-side only)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $b = body(); $tok = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($b['token'] ?? ''));
    if (strlen($tok) < 16) { out(['ok' => false, 'err' => 'bad_token'], 400); }
    kv_put($db, '__apri_token', $tok); out(['ok' => true]);
  case 'apri_pull': // admin: approved production from My Apri Work (sums only)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $t = kv_get($db, '__apri_token'); if (!$t) { out(['ok' => false, 'err' => 'no_token']); }
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', time() - 60 * 86400);
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d', time() + 86400);
    $url = 'https://oddqwjvqqvoeqdrnasqc.supabase.co/rest/v1/rpc/nofa_production'; $key = 'sb_publishable_ZVvJKCJXM5ylUlICX-57vw__ZZqQul0';
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 12, 'ignore_errors' => true, 'header' => "Content-Type: application/json\r\napikey: $key\r\nAuthorization: Bearer $key\r\n", 'content' => json_encode(['p_token' => $t['v'], 'p_from' => $from, 'p_to' => $to])]]);
    $r = @file_get_contents($url, false, $ctx); $code = 0; if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $mm)) { $code = (int) $mm[1]; }
    if ($r === false || $code >= 400) { out(['ok' => false, 'err' => 'apri_http', 'code' => $code, 'body' => substr((string) $r, 0, 300)]); }
    $rows = json_decode($r, true); if (!is_array($rows)) { out(['ok' => false, 'err' => 'apri_bad']); }
    out(['ok' => true, 'rows' => $rows, 'from' => $from, 'to' => $to]);
  case 'apri_drivers': // admin: drivers registered in My Apri Work (names only)
  case 'apri_push':    // admin: open stops for My Apri drivers (full snapshot)
  case 'apri_dstatus': // admin: delivery results since a time
  case 'apri_proof':   // admin: photo + signature of one stop
  case 'apri_routes':  // admin: drivers' planned routes (order, miles, times, earnings)
  case 'apri_stock':   // admin: warehouse from My Apri Work (made, packed, lots with expiry)
  case 'apri_stock_push': // admin: this site's warehouse (packs + loose pieces) to My Apri Work
  case 'apri_cost_push':  // admin: cost sheet summary (cost per piece/pack, price list, recipe cost) to My Apri Work (admin-only there)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $t = kv_get($db, '__apri_token'); if (!$t) { out(['ok' => false, 'err' => 'no_token']); }
    if ($a === 'apri_drivers') { $fn = 'nofa_drivers'; $args = []; }
    elseif ($a === 'apri_push') { $b = body(); $rows = array_slice(is_array($b['rows'] ?? null) ? $b['rows'] : [], 0, 500); foreach ($rows as &$rw) { if (isset($rw['data']['phone'])) { unset($rw['data']['phone']); } } unset($rw); $fn = 'nofa_push_deliveries'; $args = ['p_rows' => $rows]; }
    elseif ($a === 'apri_routes') { $fn = 'nofa_routes'; $args = []; }
    elseif ($a === 'apri_stock') { $fn = 'nofa_stock'; $args = []; }
    elseif ($a === 'apri_cost_push') { $b = body(); $d = is_array($b['data'] ?? null) ? $b['data'] : []; $fn = 'nofa_push_cost'; $args = ['p_data' => $d]; }
    elseif ($a === 'apri_stock_push') { $b = body(); $d = is_array($b['data'] ?? null) ? $b['data'] : []; $fn = 'nofa_push_stock'; $args = ['p_data' => $d]; }
    elseif ($a === 'apri_dstatus') { $since = (string) ($_GET['since'] ?? ''); $fn = 'nofa_delivery_status'; $args = ['p_since' => preg_match('/^[0-9T:\.\-\+ Z]{10,40}$/', $since) ? $since : null]; }
    else { $fn = 'nofa_delivery_proof'; $args = ['p_id' => substr((string) ($_GET['id'] ?? ''), 0, 60)]; }
    $r = apri_rpc($fn, ['p_token' => $t['v']] + $args, $code);
    if ($r === false || $code >= 400) { out(['ok' => false, 'err' => 'apri_http', 'code' => $code, 'body' => substr((string) $r, 0, 300)]); }
    $j = json_decode($r, true);
    if ($a === 'apri_proof' && ($_GET['html'] ?? '') === '1') {
      header('Content-Type: text/html; charset=utf-8'); header("Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'");
      $h = fn($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8'); $img = fn($x) => (is_string($x) && preg_match('#^data:image/(jpeg|png);base64,[A-Za-z0-9+/=]+$#', $x)) ? $x : '';
      echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Առաքման ապացույց</title><body style="margin:0;padding:18px;font:15px system-ui,sans-serif;background:#F6F1E3;color:#133B30"><h2 style="margin:0 0 4px">🚚 ' . $h($_GET['id'] ?? '') . '</h2><p style="margin:0 0 14px;color:#5E6A63">' . $h($j['at'] ?? '') . ($j['signer'] ?? '' ? ' · ստացավ՝ ' . $h($j['signer']) : '') . '</p>';
      if ($img($j['photo'] ?? '')) { echo '<h3>📷 Նկար</h3><img style="max-width:100%;border-radius:12px" src="' . $img($j['photo']) . '">'; }
      if ($img($j['sig'] ?? '')) { echo '<h3>✍️ Ստորագրություն</h3><img style="max-width:100%;background:#fff;border-radius:12px;border:1px solid #ddd" src="' . $img($j['sig']) . '">'; }
      if (!$img($j['photo'] ?? '') && !$img($j['sig'] ?? '')) { echo '<p>Ապացույց չկա։</p>'; }
      exit;
    }
    out(['ok' => true, 'data' => $j]);
  case 'track': // public: one delivery's status for the customer tracking page (Track.html?t=...)
    $tk = (string) ($_GET['t'] ?? ''); if (!preg_match('/^[a-f0-9]{20}$/', $tk)) { out(['ok' => false, 'err' => 'bad'], 400); }
    $t = kv_get($db, '__apri_token'); if (!$t) { out(['ok' => false, 'err' => 'off'], 503); }
    $r = apri_rpc('nofa_track', ['p_token' => $t['v'], 'p_tok' => $tk], $code);
    if ($r === false || $code >= 400) { out(['ok' => false, 'err' => 'apri_http'], 502); }
    $j = json_decode($r, true); if (!is_array($j)) { out(['ok' => false, 'err' => 'none'], 404); }
    out(['ok' => true, 'd' => $j]);
  case 'apri_track': // admin: tracking codes + times for stops (to copy the link or text the customer)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $t = kv_get($db, '__apri_token'); if (!$t) { out(['ok' => false, 'err' => 'no_token']); }
    $b = body(); $ids = array_values(array_filter(array_map(function ($x) { return substr((string) $x, 0, 60); }, array_slice(is_array($b['ids'] ?? null) ? $b['ids'] : [], 0, 500)), 'strlen'));
    $r = apri_rpc('nofa_track_tokens', ['p_token' => $t['v'], 'p_ids' => $ids], $code);
    if ($r === false || $code >= 400) { out(['ok' => false, 'err' => 'apri_http', 'code' => $code, 'body' => substr((string) $r, 0, 300)]); }
    out(['ok' => true, 'data' => json_decode($r, true) ?: []]);
  case 'sms_cfg': // admin: SMS provider (Telnyx or Twilio); the key stays on the server and is never returned
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $b = body(); $pv = in_array($b['provider'] ?? '', ['telnyx', 'twilio'], true) ? $b['provider'] : '';
    if ($pv === '') { kv_put($db, '__sms', json_encode([])); out(['ok' => true, 'on' => false]); }
    $key = trim((string) ($b['key'] ?? '')); $sid = trim((string) ($b['sid'] ?? '')); $from = sms_e164($b['from'] ?? '');
    if ($key === '' || !$from || ($pv === 'twilio' && !preg_match('/^AC[a-f0-9]{32}$/', $sid))) { out(['ok' => false, 'err' => 'format']); }
    kv_put($db, '__sms', json_encode(['provider' => $pv, 'key' => $key, 'sid' => $sid, 'from' => $from, 't' => now_ms()])); out(['ok' => true, 'on' => true]);
  case 'sms_info':
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $c = sms_conf($db); out(['ok' => true, 'on' => !empty($c['key']), 'provider' => $c['provider'] ?? '', 'from' => isset($c['from']) ? substr($c['from'], 0, -4) . '••••' : '']);
  case 'sms_send': // admin: [{to, text}] up to 100 per call
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $c = sms_conf($db); if (empty($c['key'])) { out(['ok' => false, 'err' => 'no_sms']); }
    $b = body(); $res = [];
    foreach (array_slice(is_array($b['msgs'] ?? null) ? $b['msgs'] : [], 0, 100) as $m) {
      $to = sms_e164($m['to'] ?? ''); $tx = mb_substr(trim((string) ($m['text'] ?? '')), 0, 480); $id = substr((string) ($m['id'] ?? ''), 0, 60);
      if (!$to || $tx === '') { $res[] = ['id' => $id, 'ok' => false, 'err' => 'bad']; continue; }
      $e = null; $ok = sms_one($c, $to, $tx, $e); $res[] = ['id' => $id, 'ok' => $ok, 'err' => $ok ? '' : $e];
    }
    out(['ok' => true, 'res' => $res]);
  case 'stripe_cfg': // admin: save Stripe secret key (server-only, never returned)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $b = body(); $sk = trim((string) ($b['sk'] ?? ''));
    if ($sk === '') { kv_put($db, '__stripe', json_encode([])); out(['ok' => true, 'mode' => '']); }
    if (!preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]{10,}$/', $sk)) { out(['ok' => false, 'err' => 'format']); }
    $r = stripe_api($sk, 'GET', 'balance', [], $code); if ($code !== 200) { out(['ok' => false, 'err' => 'stripe', 'code' => $code]); }
    kv_put($db, '__stripe', json_encode(['sk' => $sk, 't' => now_ms()])); out(['ok' => true, 'mode' => strpos($sk, '_live_') !== false ? 'live' : 'test']);
  case 'stripe_pk': // admin: save Stripe publishable key (public by design)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $b = body(); $pk = trim((string) ($b['pk'] ?? '')); if ($pk !== '' && !preg_match('/^pk_(test|live)_[A-Za-z0-9]{10,}$/', $pk)) { out(['ok' => false, 'err' => 'format']); }
    kv_put($db, '__stripe_pk', json_encode(['pk' => $pk])); out(['ok' => true]);
  case 'stripe_info': // admin: is a key set, which mode (never the key)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $sk = stripe_sk($db); $pkr = kv_get($db, '__stripe_pk'); $pkv = $pkr ? ((json_decode($pkr['v'], true) ?: [])['pk'] ?? '') : ''; out(['ok' => true, 'pk' => $pkv !== '', 'pkmode' => $pkv === '' ? '' : (strpos($pkv, '_live_') !== false ? 'live' : 'test'), 'on' => $sk !== '', 'mode' => $sk === '' ? '' : (strpos($sk, '_live_') !== false ? 'live' : 'test'), 'tail' => $sk === '' ? '' : substr($sk, -4)]);
  case 'pay_create': // customer: Stripe Checkout for a retail web order; amounts computed here, not trusted from the browser
    $sk = stripe_sk($db); if ($sk === '') { out(['ok' => false, 'err' => 'off']); }
    $b = body(); $no = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($b['no'] ?? '')); if ($no === '') { out(['ok' => false, 'err' => 'no']); }
    $P = [22.00, 19.95, 15.95, 60.00, 24.95, 24.95, 34.95, 34.95, 20.95, 0, 0]; $ids = ['gata', 'khash', '', '', 'khinkali', 'pelmeni', 'blinchik', 'kufta', 'kiev', 'chcut', 'beefcut'];
    $N = ['Gata', 'Khash', 'Khash Broth', 'Khash Set', 'Khinkali', 'Pelmeni', 'Blinchik', 'Mijukov Kufta', 'Chicken Kiev', 'Chicken Cutlets', 'Beef Cutlets'];
    $cr = kv_get($db, 'noa-cost-v2'); if ($cr) { $cv = json_decode($cr['v'], true) ?: []; foreach (($cv['prod'] ?? []) as $pd) { $k = array_search((string) ($pd['id'] ?? ''), $ids, true); $v = (float) (($pd['pr'] ?? [])[0] ?? 0); if ($k !== false && $v > 0) { $P[$k] = round($v, 2); } } }
    $lines = []; $mlines = []; $sub = 0; $form = ['mode' => 'payment', 'client_reference_id' => $no, 'metadata[order]' => $no, 'payment_intent_data[metadata][order]' => $no, 'payment_intent_data[statement_descriptor_suffix]' => substr($no, -10)];
    $i = 0; foreach ((array) ($b['items'] ?? []) as $it) { $pi = (int) ($it[0] ?? -1); $q = (int) ($it[1] ?? 0); if ($pi < 0 || $pi > 10 || $q < 1 || $q > 200 || $P[$pi] <= 0) { continue; }
      $form["line_items[$i][price_data][currency]"] = 'usd'; $form["line_items[$i][price_data][product_data][name]"] = $N[$pi]; $form["line_items[$i][price_data][unit_amount]"] = (int) round($P[$pi] * 100); $form["line_items[$i][quantity]"] = $q; $sub += $P[$pi] * $q; $mlines[] = [$N[$pi], $q, $P[$pi] * $q]; $i++; }
    if (!$i) { out(['ok' => false, 'err' => 'empty']); }
    $fee = 0; if (($b['fm'] ?? 'del') === 'del') {
      $pc = json_decode((kv_get($db, 'noa-paycfg') ?: ['v' => '{}'])['v'], true) ?: []; $f1 = isset($pc['delFee']) && $pc['delFee'] !== '' ? (float) $pc['delFee'] : 7; $f2 = isset($pc['delFee2']) && $pc['delFee2'] !== '' ? (float) $pc['delFee2'] : 14;
      $Z = ['Glendale' => 1, 'Burbank' => 1, 'Montrose' => 1, 'La Crescenta' => 1, 'La Cañada Flintridge' => 1, 'Eagle Rock' => 1, 'Atwater Village' => 1, 'Sun Valley' => 1, 'Toluca Lake' => 1, 'Universal City' => 1, 'Valley Village' => 1, 'North Hollywood' => 1, 'Studio City' => 1, 'Sunland' => 1, 'Tujunga' => 1, 'Hollywood' => 1, 'Pasadena' => 1, 'Altadena' => 1, 'South Pasadena' => 1, 'Los Angeles' => 2, 'West Hollywood' => 1, 'Pacoima' => 1, 'Arleta' => 1, 'Panorama City' => 1, 'Van Nuys' => 2, 'Sherman Oaks' => 2, 'Encino' => 2, 'Tarzana' => 2, 'Reseda' => 2, 'Northridge' => 2, 'Granada Hills' => 2, 'Porter Ranch' => 2, 'Chatsworth' => 2, 'Woodland Hills' => 2, 'Sylmar' => 2, 'Mission Hills' => 2, 'North Hills' => 2, 'Lake Balboa' => 2, 'Canoga Park' => 2]; $zr = kv_get($db, 'noa-cities'); if ($zr) { $zc = json_decode($zr['v'], true) ?: []; if (!empty($zc['cities']) && is_array($zc['cities'])) { $Z = $zc['cities']; } }
      $city = strtolower(trim(explode(',', (string) ($b['city'] ?? ''))[0])); $zone = 0; foreach ($Z as $zk => $zv) { if (strtolower($zk) === $city) { $zone = (int) $zv; } }
      if (!$zone) { out(['ok' => false, 'err' => 'zone']); } $fee = $zone === 2 ? $f2 : $f1;
      $ff = isset($pc['freeFrom']) ? (float) $pc['freeFrom'] : 0; if ($ff > 0 && $sub >= $ff) { $fee = 0; } }
    if ($fee > 0) { $form["line_items[$i][price_data][currency]"] = 'usd'; $form["line_items[$i][price_data][product_data][name]"] = 'Delivery'; $form["line_items[$i][price_data][unit_amount]"] = (int) round($fee * 100); $form["line_items[$i][quantity]"] = 1; }
    $em = trim((string) ($b['email'] ?? '')); if (filter_var($em, FILTER_VALIDATE_EMAIL)) { $form['customer_email'] = $em; }
    $host = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'armenonline.com'); $base = $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/api.php')), '/');
    if (!empty($b['embed'])) { $form['ui_mode'] = 'embedded'; $form['return_url'] = $base . '/Checkout.dc.html?paid={CHECKOUT_SESSION_ID}&no=' . $no; } else { $form['success_url'] = $base . '/Checkout.dc.html?paid={CHECKOUT_SESSION_ID}&no=' . $no; $form['cancel_url'] = $base . '/Checkout.dc.html?paycancel=' . $no; } $form['submit_type'] = 'pay'; $lc = (string) ($b['lang'] ?? ''); if (in_array($lc, ['en', 'ru', 'es'], true)) { $form['locale'] = $lc; }
    $r = stripe_api($sk, 'POST', 'checkout/sessions', $form, $code); $j = json_decode((string) $r, true) ?: [];
    if ($code !== 200 || (empty($j['url']) && empty($j['client_secret']))) { out(['ok' => false, 'err' => 'stripe', 'msg' => $j['error']['message'] ?? '']); }
    kv_put($db, '__pay|' . $no, json_encode(['sid' => $j['id'], 'amt' => round($sub + $fee, 2), 'fee' => $fee, 't' => now_ms(), 'lines' => $mlines, 'email' => $em, 'name' => (string) ($b['name'] ?? ''), 'phone' => (string) ($b['phone'] ?? ''), 'addr' => (string) ($b['addr'] ?? ''), 'day' => (string) ($b['day'] ?? ''), 'fm' => (string) ($b['fm'] ?? 'del'), 'site' => $base . '/MyAccount.dc.html'], JSON_UNESCAPED_UNICODE));
    out(['ok' => true, 'url' => $j['url'] ?? '', 'cs' => $j['client_secret'] ?? '', 'total' => round($sub + $fee, 2), 'fee' => $fee]);
  case 'pay_check': // anyone with the session id: confirm with Stripe and record the order as paid (server-side)
    $sk = stripe_sk($db); $b = body(); $sid = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($b['sid'] ?? '')); if ($sk === '' || $sid === '') { out(['ok' => false]); }
    $r = stripe_api($sk, 'GET', 'checkout/sessions/' . $sid, [], $code); $j = json_decode((string) $r, true) ?: [];
    if ($code !== 200) { out(['ok' => false, 'err' => 'stripe']); }
    $no = (string) ($j['client_reference_id'] ?? ''); $paid = ($j['payment_status'] ?? '') === 'paid';
    if ($paid && $no !== '') { $L = json_decode((kv_get($db, '__paidweb') ?: ['v' => '{}'])['v'], true) ?: []; if (empty($L[$no])) { $pr0 = json_decode((kv_get($db, '__pay|' . $no) ?: ['v' => '{}'])['v'], true) ?: []; $L[$no] = ['amt' => ((int) ($j['amount_total'] ?? 0)) / 100, 'base' => isset($pr0['amt']) ? (float) $pr0['amt'] : null, 'fee' => (float) ($pr0['fee'] ?? 0), 'pi' => (string) ($j['payment_intent'] ?? ''), 't' => now_ms()]; kv_put($db, '__paidweb', json_encode($L)); pay_mails($db, $no, ((int) ($j['amount_total'] ?? 0)) / 100); } }
    out(['ok' => true, 'paid' => $paid, 'processing' => !$paid && ($j['status'] ?? '') === 'complete', 'no' => $no, 'amt' => ((int) ($j['amount_total'] ?? 0)) / 100]);
  case 'mail_cfg': // admin: save the order mailbox (password stays on the server)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $b = body(); $m = mail_cfg($db); foreach (['user', 'notify', 'host'] as $f) { if (isset($b[$f])) { $m[$f] = trim((string) $b[$f]); } } if (isset($b['port'])) { $m['port'] = (int) $b['port']; } if (!empty($b['pass'])) { $m['pass'] = (string) $b['pass']; }
    kv_put($db, '__mail', json_encode($m)); out(['ok' => true]);
  case 'mail_info':
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $m = mail_cfg($db); out(['ok' => true, 'user' => $m['user'] ?? '', 'notify' => $m['notify'] ?? '', 'host' => $m['host'] ?? 'smtp.hostinger.com', 'port' => $m['port'] ?? 465, 'pass' => !empty($m['pass'])]);
  case 'mail_test':
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $m = mail_cfg($db); $to = (string) ($m['notify'] ?? '') ?: (string) ($m['user'] ?? ''); $e = null;
    $ok = smtp_send($m, $to, 'Test — Nectar of Armenia website', mail_tpl('It works ✓', '<p style="font-size:15px;margin:0">The website can now send emails from ' . mail_esc($m['user'] ?? '') . '.</p>'), $e); out(['ok' => $ok, 'to' => $to, 'err' => $ok ? '' : $e]);
  case 'pw_reset_req': // anyone: email a password-reset link (same answer whether or not the email exists)
    $b = body(); $em = strtolower(trim((string) ($b['em'] ?? ''))); $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); $fk = '__pwrr|' . $ip; $fr = kv_get($db, $fk); $fv = $fr ? (json_decode($fr['v'], true) ?: []) : [];
    $fv = array_values(array_filter((array) $fv, function ($t) { return $t > now_ms() - 3600000; })); if (count($fv) >= 6) { out(['ok' => false, 'err' => 'rate'], 429); } $fv[] = now_ms(); kv_put($db, $fk, json_encode($fv));
    $m = mail_cfg($db); if (empty($m['user'])) { out(['ok' => false, 'err' => 'nomail']); }
    $has = (bool) kv_get($db, '__cpw|' . $em); $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; foreach ($A as $key => $x) { if (is_array($x) && strtolower((string) ($x['email'] ?? '')) === $em) { $has = true; } }
    if ($has && filter_var($em, FILTER_VALIDATE_EMAIL)) { $t = bin2hex(random_bytes(20)); kv_put($db, '__pwr|' . $t, json_encode(['em' => $em, 't' => now_ms()]));
      $host = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'armenonline.com'); $base = $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/api.php')), '/'); $link = $base . '/Account.dc.html?reset=' . $t;
      smtp_send($m, $em, 'Reset your password — Nectar of Armenia', mail_tpl('Reset your password', '<p style="font-size:15px;margin:0 0 16px">Press the button to choose a new password. The link works for 1 hour.<br><span style="color:#6B756F">Սեղմեք կոճակը՝ նոր գաղտնաբառ ընտրելու համար։ Հղումը գործում է 1 ժամ։</span></p><p style="margin:0"><a href="' . mail_esc($link) . '" style="display:inline-block;background:#EBA93A;color:#133B30;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:999px">Choose a new password</a></p><p style="margin:16px 0 0;color:#6B756F;font-size:13px">If you did not ask for this, you can ignore this email.</p>')); }
    out(['ok' => true]);
  case 'pw_reset_do':
    $b = body(); $t = preg_replace('/[^a-f0-9]/', '', (string) ($b['t'] ?? '')); $pw = (string) ($b['pw'] ?? ''); if ($t === '' || strlen($pw) < 8 || strlen($pw) > 200) { out(['ok' => false, 'err' => 'bad']); }
    $r = kv_get($db, '__pwr|' . $t); $v = $r ? (json_decode($r['v'], true) ?: []) : []; if (!$v || now_ms() - (int) ($v['t'] ?? 0) > 3600000) { out(['ok' => false, 'err' => 'expired']); }
    $em = (string) $v['em']; $k = '__cpw|' . $em; $old = kv_get($db, $k); $old = $old ? (json_decode($old['v'], true) ?: []) : []; $tok = (string) ($old['tok'] ?? ''); $acc = (array) ($old['acc'] ?? []);
    if ($tok === '') { $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; foreach ($A as $key => $x) { if (is_array($x) && strtolower((string) ($x['email'] ?? '')) === $em) { if (!empty($x['tok'])) { $tok = (string) $x['tok']; } $acc[strtolower((string) $key)] = $x; } } }
    if ($tok === '') { $tok = bin2hex(random_bytes(16)); }
    kv_put($db, $k, json_encode(['h' => password_hash($pw, PASSWORD_DEFAULT), 'tok' => $tok, 'acc' => $acc, 't' => now_ms()], JSON_UNESCAPED_UNICODE)); kv_put($db, '__pwr|' . $t, json_encode(['used' => now_ms()]));
    out(['ok' => true, 'em' => $em]);
  case 'pay_inv': // wholesale customer: pay an invoice balance by card; amount computed here from the stored invoice
    $sk = stripe_sk($db); if ($sk === '') { out(['ok' => false, 'err' => 'off']); }
    $b = body(); $me = cust_rec($db, $b['em'] ?? '', 'biz', $b['tok'] ?? ''); if (!$me) { $me = cust_rec($db, $b['em'] ?? '', 'retail', $b['tok'] ?? ''); } if (!$me) { out(['ok' => false, 'err' => 'auth'], 401); }
    $em = strtolower(trim((string) $b['em'])); $id = (string) ($b['id'] ?? ''); $rec = $me[1]; $w = substr($me[0], -4) === '|biz';
    $nm = $w ? ($rec['biz'] ?? '') : trim(($rec['first'] ?? '') . ' ' . ($rec['last'] ?? '')); $ck = preg_replace('/[^a-z0-9\x{0531}-\x{0587}]+/u', '', mb_strtolower($nm)) . '|' . ($w ? 'w' : 'r');
    $inv = json_decode((kv_get($db, 'noa-inv2') ?: ['v' => '{}'])['v'], true) ?: []; $v = null;
    foreach (($inv['list'] ?? []) as $x) { if (is_array($x) && (string) ($x['id'] ?? '') === $id && !in_array($x['st'] ?? '', ['draft', 'void', 'paid'], true) && (($x['ck'] ?? '') === $ck || strtolower($x['c']['e'] ?? '') === $em)) { $v = $x; } }
    if (!$v) { out(['ok' => false, 'err' => 'noinv']); }
    $sub = 0; foreach (($v['lines'] ?? []) as $l) { $sub += (float) ($l['q'] ?? 0) * (float) ($l['pr'] ?? 0); } $base = max(0, $sub - (float) ($v['disc'] ?? 0)); $tot = $base + $base * (float) ($v['tax'] ?? 0) / 100 + (float) ($v['ship'] ?? 0);
    $bal = round(max(0, $tot - (float) (($v['paid'] ?? [])['amt'] ?? 0)), 2); if ($bal < 0.5) { out(['ok' => false, 'err' => 'zero']); }
    $no = (string) ($v['no'] ?? $id); $ach = ($b['m'] ?? '') === 'ach'; $fee = $ach ? 0 : round($bal * 0.03, 2);
    $host = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'armenonline.com'); $base0 = $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/api.php')), '/');
    $form = ['mode' => 'payment', 'client_reference_id' => 'INV:' . $id, 'metadata[invoice]' => $no, 'payment_intent_data[metadata][invoice]' => $no, 'line_items[0][price_data][currency]' => 'usd', 'line_items[0][price_data][product_data][name]' => 'Invoice #' . $no . ' — Nectar of Armenia Inc', 'line_items[0][price_data][unit_amount]' => (int) round($bal * 100), 'line_items[0][quantity]' => 1, 'payment_method_types[0]' => $ach ? 'us_bank_account' : 'card', 'submit_type' => 'pay', 'success_url' => $base0 . '/PartnerAccount.dc.html?invpaid={CHECKOUT_SESSION_ID}#invoices', 'cancel_url' => $base0 . '/PartnerAccount.dc.html#invoices'];
    if ($fee > 0) { $form['line_items[1][price_data][currency]'] = 'usd'; $form['line_items[1][price_data][product_data][name]'] = 'Card processing fee (3%)'; $form['line_items[1][price_data][unit_amount]'] = (int) round($fee * 100); $form['line_items[1][quantity]'] = 1; }
    if (filter_var($em, FILTER_VALIDATE_EMAIL)) { $form['customer_email'] = $em; }
    $r = stripe_api($sk, 'POST', 'checkout/sessions', $form, $code); $j = json_decode((string) $r, true) ?: [];
    if ($code !== 200 || empty($j['url'])) { out(['ok' => false, 'err' => 'stripe', 'msg' => $j['error']['message'] ?? '']); }
    kv_put($db, '__pay|INV:' . $id, json_encode(['sid' => $j['id'], 'amt' => $bal, 'fee' => $fee, 't' => now_ms(), 'no' => $no, 'email' => $em, 'name' => $nm, 'lines' => [['Invoice #' . $no, 1, $bal]] + ($fee > 0 ? [1 => ['Card processing fee (3%)', 1, $fee]] : []), 'site' => $base0 . '/PartnerAccount.dc.html#invoices'], JSON_UNESCAPED_UNICODE));
    out(['ok' => true, 'url' => $j['url'], 'amt' => $bal, 'fee' => $fee]);
  case 'pay_sync': // admin: confirm with Stripe any card payments whose customer never came back to the site
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $sk = stripe_sk($db); $L = json_decode((kv_get($db, '__paidweb') ?: ['v' => '{}'])['v'], true) ?: []; $n = 0;
    if ($sk !== '') { $s = $db->prepare("SELECT k, v FROM nofa_kv WHERE k LIKE '__pay|%'"); $s->execute(); foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) { $ref = substr($row['k'], 6); if (!empty($L[$ref])) { continue; } $pv = json_decode($row['v'], true) ?: []; if (empty($pv['sid']) || now_ms() - (int) ($pv['t'] ?? 0) > 86400000 * 10) { continue; }
      $r = stripe_api($sk, 'GET', 'checkout/sessions/' . $pv['sid'], [], $code); $j = json_decode((string) $r, true) ?: []; if ($code === 200 && ($j['payment_status'] ?? '') === 'paid') { $L[$ref] = ['amt' => ((int) ($j['amount_total'] ?? 0)) / 100, 'base' => isset($pv['amt']) ? (float) $pv['amt'] : null, 'fee' => (float) ($pv['fee'] ?? 0), 'pi' => (string) ($j['payment_intent'] ?? ''), 't' => now_ms()]; $n++; pay_mails($db, $ref, ((int) ($j['amount_total'] ?? 0)) / 100); } if ($n > 30) { break; } } }
    if ($n) { kv_put($db, '__paidweb', json_encode($L)); } out(['ok' => true, 'n' => $n, 'paid' => $L ?: new stdClass()]);
  case 'pay_list': // admin: orders confirmed paid by card
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    out(['ok' => true, 'paid' => json_decode((kv_get($db, '__paidweb') ?: ['v' => '{}'])['v'], true) ?: new stdClass()]);
  case 'pub': // anyone: public site settings (theme, stock, cities)
    $res = []; foreach ($PUBLIC as $k) { $r = kv_get($db, $k); if ($r) { $res[$k] = $r['v']; } }
    // wholesale prices from the admin cost/price page (prices only, never costs or recipes)
    $cr = kv_get($db, 'noa-cost-v2'); if ($cr) { $cv = json_decode($cr['v'], true) ?: []; $wp = []; $all = [];
      foreach (($cv['prod'] ?? []) as $pd) { if (!is_array($pd) || !isset($pd['id'])) { continue; } $pr = $pd['pr'] ?? []; $wp[(string) $pd['id']] = [round((float) ($pr[2] ?? 0), 2), round((float) ($pr[3] ?? 0), 2), !empty($pd['noBulk'])]; $all[(string) $pd['id']] = [round((float) ($pr[0] ?? 0), 2), round((float) ($pr[1] ?? 0), 2), round((float) ($pr[2] ?? 0), 2), round((float) ($pr[3] ?? 0), 2)]; }
      if ($wp) { $res['noa-wsprices'] = json_encode($wp); $res['noa-prices'] = json_encode($all); } }
    if (isset($res['noa-paycfg'])) { $p = json_decode($res['noa-paycfg'], true) ?: []; $res['noa-paycfg'] = json_encode(['zelle' => $p['zelle'] ?? '', 'zphone' => $p['zphone'] ?? '', 'acctName' => $p['acctName'] ?? '', 'freeFrom' => $p['freeFrom'] ?? '', 'delFee' => $p['delFee'] ?? '', 'delFee2' => $p['delFee2'] ?? ''], JSON_UNESCAPED_UNICODE); }
    $res['noa-stripe-on'] = stripe_sk($db) !== '' ? '1' : '0';
    $pkr = kv_get($db, '__stripe_pk'); $res['noa-stripe-pk'] = $pkr ? (string) ((json_decode($pkr['v'], true) ?: [])['pk'] ?? '') : '';
    out(['ok' => true, 'kv' => $res]);
  case 'link_code': // customer: one-time code to link this account with another one (wholesale <-> personal)
  case 'link_use':
  case 'link_rm':
    $b = body(); $me = cust_rec($db, $b['em'] ?? '', $b['type'] ?? '', $b['tok'] ?? ''); if (!$me) { out(['ok' => false, 'err' => 'auth'], 401); }
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); $fk = '__lkfail|' . $ip; $fr = kv_get($db, $fk); $fails = $fr ? json_decode($fr['v'], true) : ['n' => 0, 't' => now_ms()];
    if (now_ms() - ($fails['t'] ?? 0) > 3600000) { $fails = ['n' => 0, 't' => now_ms()]; }
    if ($a === 'link_code') {
      $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
      kv_put($db, '__lk|' . $code, json_encode(['key' => $me[0], 't' => now_ms()])); out(['ok' => true, 'code' => $code, 'ttl' => 15]);
    }
    $L = links_get($db);
    if ($a === 'link_rm') {
      $other = (string) ($b['other'] ?? ''); $L[$me[0]] = array_values(array_diff($L[$me[0]] ?? [], [$other])); $L[$other] = array_values(array_diff($L[$other] ?? [], [$me[0]]));
      kv_put($db, '__links', json_encode($L)); out(['ok' => true]);
    }
    if (($fails['n'] ?? 0) >= 12) { out(['ok' => false, 'err' => 'rate'], 429); }
    $code = preg_replace('/\D/', '', (string) ($b['code'] ?? '')); $cr = strlen($code) === 6 ? kv_get($db, '__lk|' . $code) : null; $c = $cr ? json_decode($cr['v'], true) : null;
    if (!$c || now_ms() - ($c['t'] ?? 0) > 900000) { $fails['n'] = ($fails['n'] ?? 0) + 1; kv_put($db, $fk, json_encode($fails)); out(['ok' => false, 'err' => 'bad_code']); }
    $db->prepare('DELETE FROM nofa_kv WHERE k = ?')->execute(['__lk|' . $code]);
    if ($c['key'] === $me[0]) { out(['ok' => false, 'err' => 'same']); }
    $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; $orec = null;
    foreach ($A as $k2 => $r2) { if (is_array($r2) && strtolower((string) $k2) === $c['key']) { $orec = $r2; } }
    if (!$orec) { out(['ok' => false, 'err' => 'bad_code']); }
    $L[$me[0]] = array_values(array_unique(array_merge($L[$me[0]] ?? [], [$c['key']]))); $L[$c['key']] = array_values(array_unique(array_merge($L[$c['key']] ?? [], [$me[0]])));
    kv_put($db, '__links', json_encode($L)); out(['ok' => true, 'other' => $c['key'], 'account' => $orec]);
  case 'my': // customer: own data, verified by email + token
    $b = body(); $em = strtolower((string) ($b['em'] ?? '')); $tok = (string) ($b['tok'] ?? '');
    if ($em === '' || strlen($tok) < 16) { out(['ok' => false, 'err' => 'auth'], 401); }
    $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: [];
    $mine = []; foreach ($A as $key => $rec) { if (is_array($rec) && strtolower($rec['email'] ?? '') === $em) { if (!isset($rec['tok']) || !hash_equals((string) $rec['tok'], $tok)) { continue; } $mine[$key] = $rec; } }
    if (!$mine) { out(['ok' => true, 'pending' => true]); }
    $keys = []; foreach (['noa-ws-orders|' . $em, 'noa-chat|' . $em, 'noa-chat|retail:' . $em, 'noa-members|' . $em, 'noa-deposit|' . $em, 'noa-depsign|' . $em, 'noa-rewards|' . $em, 'noa-invites|' . $em] as $k) { $r = kv_get($db, $k); if ($r) { $keys[$k] = $r['v']; } }
    $cks = []; foreach ($mine as $key => $rec) { $w = (substr($key, -4) === '|biz'); $nm = $w ? ($rec['biz'] ?? '') : trim(($rec['first'] ?? '') . ' ' . ($rec['last'] ?? '')); $cks[] = preg_replace('/[^a-z0-9\x{0531}-\x{0587}]+/u', '', mb_strtolower($nm)) . '|' . ($w ? 'w' : 'r'); }
    $inv = json_decode((kv_get($db, 'noa-inv2') ?: ['v' => '{}'])['v'], true) ?: []; $list = [];
    foreach (($inv['list'] ?? []) as $v) { if (!is_array($v) || in_array($v['st'] ?? '', ['draft', 'void'], true)) { continue; } if (in_array($v['ck'] ?? '', $cks, true) || strtolower($v['c']['e'] ?? '') === $em) { $list[] = $v; } }
    $cost = json_decode((kv_get($db, 'noa-cost-v2') ?: ['v' => '{}'])['v'], true) ?: []; $cp = [];
    foreach ($cks as $ck) { if (isset($cost['cprof'][$ck]['dep0'])) { $cp[$ck] = ['dep0' => $cost['cprof'][$ck]['dep0']]; } }
    $LK = links_get($db); $lk = []; foreach (array_keys($mine) as $mk) { foreach (($LK[$mk] ?? []) as $ok2) { $lk[$mk][] = $ok2; foreach ($A as $k3 => $r3) { if (is_array($r3) && strtolower((string) $k3) === $ok2) { $mine[$k3] = $r3; } } } }
    $pay = null; foreach ($mine as $key => $rec) { $isBiz = substr((string) $key, -4) === '|biz'; if (!$isBiz || in_array($rec['status'] ?? '', ['approved', 'active'], true)) { $pay = true; break; } }
    if ($pay) { $pc = json_decode((kv_get($db, 'noa-paycfg') ?: ['v' => '{}'])['v'], true) ?: []; $pay = []; foreach (['bank', 'acctName', 'routing', 'acct', 'wireRouting', 'bankAddr'] as $f) { if (trim((string) ($pc[$f] ?? '')) !== '') { $pay[$f] = (string) $pc[$f]; } } }
    out(['ok' => true, 'accounts' => $mine, 'links' => $lk, 'keys' => $keys, 'inv' => $list, 'cprof' => $cp, 'pay' => $pay]);
}
out(['ok' => false, 'err' => 'unknown'], 400);
