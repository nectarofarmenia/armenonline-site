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
function kv_put($db, $k, $v) { $t = now_ms(); $s = $db->prepare('REPLACE INTO nofa_kv (k, v, t) VALUES (?, ?, ?)'); $s->execute([$k, $v, $t]); return $t; }

switch ($a) {
  case 'login':
    $b = body(); $pw = (string) ($b['pw'] ?? '');
    if ($pw !== '' && password_verify($pw, $cfg['admin_hash'])) { session_regenerate_id(true); $_SESSION['nofa_admin'] = 1; out(['ok' => true]); }
    sleep(1); out(['ok' => false, 'err' => 'bad_password'], 401);
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
    $cr = kv_get($db, 'noa-cost-v2'); if ($cr) { $cv = json_decode($cr['v'], true) ?: []; $wp = [];
      foreach (($cv['prod'] ?? []) as $pd) { if (!is_array($pd) || !isset($pd['id'])) { continue; } $pr = $pd['pr'] ?? []; $wp[(string) $pd['id']] = [round((float) ($pr[2] ?? 0), 2), round((float) ($pr[3] ?? 0), 2)]; }
      if ($wp) { $res['noa-wsprices'] = json_encode($wp); } }
    if (isset($res['noa-paycfg'])) { $p = json_decode($res['noa-paycfg'], true) ?: []; $res['noa-paycfg'] = json_encode(['zelle' => $p['zelle'] ?? '', 'zphone' => $p['zphone'] ?? '', 'acctName' => $p['acctName'] ?? ''], JSON_UNESCAPED_UNICODE); }
    out(['ok' => true, 'kv' => $res]);
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
    out(['ok' => true, 'accounts' => $mine, 'keys' => $keys, 'inv' => $list, 'cprof' => $cp]);
}
out(['ok' => false, 'err' => 'unknown'], 400);
