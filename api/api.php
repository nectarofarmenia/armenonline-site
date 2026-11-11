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
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $t = kv_get($db, '__apri_token'); if (!$t) { out(['ok' => false, 'err' => 'no_token']); }
    if ($a === 'apri_drivers') { $fn = 'nofa_drivers'; $args = []; }
    elseif ($a === 'apri_push') { $b = body(); $rows = array_slice(is_array($b['rows'] ?? null) ? $b['rows'] : [], 0, 500); foreach ($rows as &$rw) { if (isset($rw['data']['phone'])) { unset($rw['data']['phone']); } } unset($rw); $fn = 'nofa_push_deliveries'; $args = ['p_rows' => $rows]; }
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
  case 'pub': // anyone: public site settings (theme, stock, cities)
    $res = []; foreach ($PUBLIC as $k) { $r = kv_get($db, $k); if ($r) { $res[$k] = $r['v']; } }
    // wholesale prices from the admin cost/price page (prices only, never costs or recipes)
    $cr = kv_get($db, 'noa-cost-v2'); if ($cr) { $cv = json_decode($cr['v'], true) ?: []; $wp = []; $all = [];
      foreach (($cv['prod'] ?? []) as $pd) { if (!is_array($pd) || !isset($pd['id'])) { continue; } $pr = $pd['pr'] ?? []; $wp[(string) $pd['id']] = [round((float) ($pr[2] ?? 0), 2), round((float) ($pr[3] ?? 0), 2), !empty($pd['noBulk'])]; $all[(string) $pd['id']] = [round((float) ($pr[0] ?? 0), 2), round((float) ($pr[1] ?? 0), 2), round((float) ($pr[2] ?? 0), 2), round((float) ($pr[3] ?? 0), 2)]; }
      if ($wp) { $res['noa-wsprices'] = json_encode($wp); $res['noa-prices'] = json_encode($all); } }
    if (isset($res['noa-paycfg'])) { $p = json_decode($res['noa-paycfg'], true) ?: []; $res['noa-paycfg'] = json_encode(['zelle' => $p['zelle'] ?? '', 'zphone' => $p['zphone'] ?? '', 'acctName' => $p['acctName'] ?? ''], JSON_UNESCAPED_UNICODE); }
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
