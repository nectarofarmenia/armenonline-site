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
$SHARED = '/^noa-(accounts|orders|ws-orders(\|.*)?|chat(\|.*)?|cost-v2|inv2|invites(\|.*)?|drivers|paycfg|admin-ov|stock|stock-full|theme-sel|theme-dates|notify|cities|city-req|members\|.*|deposit\|.*|depsign\|.*|rewards\|.*|refs|geo|invoices)$/';
$PUBLIC = ['noa-theme-sel', 'noa-theme-dates', 'noa-stock', 'noa-stock-full', 'noa-cities', 'noa-paycfg'];
function kv_get($db, $k) { $s = $db->prepare('SELECT v, t FROM nofa_kv WHERE k = ?'); $s->execute([$k]); $r = $s->fetch(PDO::FETCH_ASSOC); return $r ?: null; }
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
    $s = $db->prepare('SELECT k, v, t FROM nofa_kv WHERE t > ?'); $s->execute([$since]);
    $res = []; $mx = $since; foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $res[$r['k']] = ['v' => $r['v'], 't' => (int) $r['t']]; if ($r['t'] > $mx) { $mx = (int) $r['t']; } }
    $n = (int) $db->query('SELECT COUNT(*) FROM nofa_kv')->fetchColumn();
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
  case 'pub': // anyone: public site settings (theme, stock, cities)
    $res = []; foreach ($PUBLIC as $k) { $r = kv_get($db, $k); if ($r) { $res[$k] = $r['v']; } }
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
