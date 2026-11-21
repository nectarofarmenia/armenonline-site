<?php
// Nectar of Armenia — data API
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/db.php';
require __DIR__ . '/push.php';
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_name('NOFASESS'); session_start();
if (function_exists('header_remove')) { @header_remove('X-Powered-By'); }
function out($a, $code = 200) { http_response_code($code); $j = json_encode($a, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR); if ($j === false || $j === '') { $GLOBALS['__outerr'] = json_last_error_msg(); $j = '{"ok":false,"err":"encode"}'; } echo $j; exit; }
register_shutdown_function(function () { $e = error_get_last(); $oe = $GLOBALS['__outerr'] ?? ''; if ((!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) && $oe === '') { return; } try { $db = $GLOBALS['db'] ?? null; if (!$db) { return; } $r = $db->prepare('SELECT v FROM nofa_kv WHERE k = ?'); $r->execute(['__phperr']); $L = json_decode((string) $r->fetchColumn(), true) ?: []; $L[] = ['at' => gmdate('c'), 'a' => (string) ($_GET['a'] ?? ''), 'm' => $e ? substr($e['message'], 0, 300) . ' @' . ($e['line'] ?? '') : 'encode: ' . $oe]; $L = array_slice($L, -30); $db->prepare('REPLACE INTO nofa_kv (k, v, t) VALUES (?, ?, ?)')->execute(['__phperr', json_encode($L), (int) round(microtime(true) * 1000)]); } catch (Throwable $x) {} });
function body() { $r = file_get_contents('php://input'); if (strlen($r) > 6 * 1024 * 1024) { out(['ok' => false, 'err' => 'too_big'], 413); } $j = json_decode($r, true); return is_array($j) ? $j : []; }
function now_ms() { return (int) round(microtime(true) * 1000); }
$a = $_GET['a'] ?? '';
$cfg = nofa_cfg();
if ($a === 'ping') { $d = []; if (!empty($_GET['diag']) && $cfg) { $P = nofa_cfg_paths(); $d = ['cfg_idx' => $GLOBALS['NOFA_CFG_IDX'] ?? null, 'cfg_n' => count($P), 'cfg_kinds' => array_map(function ($f) { return basename(dirname($f)) . '/' . basename($f) . (@file_exists($f) ? ':y' : ':n'); }, $P), 'hfp' => substr(hash('sha256', (string) ($cfg['admin_hash'] ?? '')), 0, 8)]; try { $dbx = nofa_db(); $d['apw'] = (bool) kv_get($dbx, '__adminpw'); } catch (Exception $e) { $d['apw'] = 'dberr'; } } out(['ok' => true, 'configured' => (bool) $cfg, 'admin' => !empty($_SESSION['nofa_admin'])] + $d); }
if (!$cfg) { out(['ok' => false, 'err' => 'not_configured'], 503); }
try { $db = nofa_db(); } catch (Exception $e) { out(['ok' => false, 'err' => 'db'], 503); }
$isAdmin = !empty($_SESSION['nofa_admin']);
if (!kv_get($db, '__mig_bot1')) { $mr = kv_get($db, '__stats|2026-11'); $M = $mr ? (json_decode($mr['v'], true) ?: null) : null;
  if (is_array($M)) { $nb = 0; foreach ((array) ($M['ci'] ?? []) as $ck => $cv) { if (stripos($ck, 'Manchester') === 0) { $nb += (int) $cv; unset($M['ci'][$ck]); } }
    if ($nb > 0) { $M['v'] = max(0, ($M['v'] ?? 0) - $nb); if (isset($M['cc']['GB'])) { $M['cc']['GB'] = max(0, $M['cc']['GB'] - $nb); if (!$M['cc']['GB']) { unset($M['cc']['GB']); } } if (isset($M['dv']['d'])) { $M['dv']['d'] = max(0, $M['dv']['d'] - $nb); } if (isset($M['lg']['en'])) { $M['lg']['en'] = max(0, $M['lg']['en'] - $nb); } if (isset($M['p']['index.html'])) { $M['p']['index.html'] = max(0, $M['p']['index.html'] - $nb); }
      $M['bots'] = ($M['bots'] ?? 0) + $nb; $M['bo'] = array_merge((array) ($M['bo'] ?? []), ['Manchester data-centre (removed) · Manchester' => $nb]); kv_put($db, '__stats|2026-11', json_encode($M, JSON_UNESCAPED_UNICODE)); } }
  kv_put($db, '__mig_bot1', '1'); }
if (!kv_get($db, '__mig_pel3')) { $cr = kv_get($db, 'noa-cost-v2'); $cv = $cr ? (json_decode($cr['v'], true) ?: null) : null;
  if (is_array($cv) && isset($cv['prod']) && is_array($cv['prod'])) { foreach ($cv['prod'] as &$pp) { if (($pp['id'] ?? '') === 'pelmeni') { if (!isset($pp['pr']) || !is_array($pp['pr'])) { $pp['pr'] = [0, 0, 0, 0]; } $pp['pr'][0] = 32; $pp['pr'][2] = 24; } } unset($pp); $cv['prPel2'] = 1; $cv['prPel3'] = 1; kv_put($db, 'noa-cost-v2', json_encode($cv, JSON_UNESCAPED_UNICODE)); }
  kv_put($db, '__mig_pel3', '1'); }

if (!kv_get($db, '__mig_vosk_addr')) { kv_put($db, '__mig_vosk_addr', '1'); try {
  $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; $chA = false;
  foreach ($A as $k => $r) { if (is_array($r) && preg_replace('/[^a-z]/', '', strtolower((string) ($r['biz'] ?? ''))) === 'voskevazmarket') { $A[$k]['addr'] = '1317 E Colorado St'; $A[$k]['city'] = 'Glendale'; $A[$k]['state'] = 'CA'; $A[$k]['zip'] = '91205'; $chA = true; } }
  if ($chA) { kv_put($db, 'noa-accounts', json_encode($A, JSON_UNESCAPED_UNICODE)); }
  $C = json_decode((kv_get($db, 'noa-cost-v2') ?: ['v' => '{}'])['v'], true);
  if (is_array($C)) { if (!isset($C['cprof']) || !is_array($C['cprof'])) { $C['cprof'] = []; } $pk = 'voskevazmarket|w'; if (!isset($C['cprof'][$pk]) || !is_array($C['cprof'][$pk])) { $C['cprof'][$pk] = []; } $C['cprof'][$pk]['addr'] = '1317 E Colorado St, Glendale, CA 91205'; kv_put($db, 'noa-cost-v2', json_encode($C, JSON_UNESCAPED_UNICODE)); }
} catch (Throwable $e) {} }
if (!kv_get($db, '__acc_bump1')) { kv_put($db, '__acc_bump1', '1'); $r9 = kv_get($db, 'noa-accounts'); if ($r9) { kv_put($db, 'noa-accounts', $r9['v']); } }
if (!kv_get($db, '__acc_gone')) { kv_put($db, '__acc_gone', json_encode(['gary-s-mom-s-bakery.pending@nectarofarmenia.com|biz'])); }
if (kv_get($db, '__mig_stores2') && !kv_get($db, '__mig_gary_em')) { kv_put($db, '__mig_gary_em', '1'); try { cinvite_do($db, ['acc' => ['type' => 'biz', 'biz' => "Gary's Mom's Bakery", 'email' => 'garysmomsbakery@gmail.com'], 'importName' => "Gary's Mom's Bakery"], $https); } catch (Throwable $e) {} }
if (kv_get($db, '__mig_stores1') && !kv_get($db, '__mig_stores2')) { kv_put($db, '__mig_stores2', '1'); try { foreach (['gary-s-mom-s-bakery', 'voskevaz-market'] as $sl2) { $k2 = 'noa-ws-orders|' . $sl2 . '.pending@nectarofarmenia.com'; $L2 = json_decode((kv_get($db, $k2) ?: ['v' => '[]'])['v'], true) ?: []; $L2 = array_values(array_filter($L2, function ($o) { return !(is_array($o) && !empty($o['imported'])); })); kv_put($db, $k2, json_encode($L2, JSON_UNESCAPED_UNICODE)); }
  foreach (["Gary's Mom's Bakery", 'Voskevaz Market'] as $sn2) { $sl2 = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($sn2)), '-'); cinvite_do($db, ['acc' => ['type' => 'biz', 'biz' => $sn2, 'email' => $sl2 . '.pending@nectarofarmenia.com'], 'importName' => $sn2, 'noEmail' => 1], $https); } } catch (Throwable $e) {} }
if (!kv_get($db, '__mig_stores1')) { kv_put($db, '__mig_stores1', '1'); try { $cv9 = json_decode((kv_get($db, 'noa-cost-v2') ?: ['v' => '{}'])['v'], true) ?: [];
  foreach (["Gary's Mom's Bakery", 'Voskevaz Market'] as $sn) { $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($sn)); $slug = trim($slug, '-'); $ph = ''; $ad = ''; $ci = '';
    foreach ((array) ($cv9['calls'] ?? []) as $c9) { if (is_array($c9) && ($c9['type'] ?? '') === 'w' && preg_replace('/[^a-z0-9]/', '', strtolower((string) ($c9['name'] ?? ''))) === preg_replace('/[^a-z0-9]/', '', strtolower($sn))) { if ($ph === '' && !empty($c9['phone'])) { $ph = (string) $c9['phone']; } if ($ad === '' && !empty($c9['addr'])) { $ad = (string) $c9['addr']; } if ($ci === '' && !empty($c9['city'])) { $ci = (string) $c9['city']; } } }
    cinvite_do($db, ['acc' => ['type' => 'biz', 'biz' => $sn, 'email' => $slug . '.pending@nectarofarmenia.com', 'phone' => $ph, 'addr' => $ad, 'city' => $ci ?: 'Glendale', 'note' => 'Ստեղծված առանց email-ի · հրավիրելու համար գրեք խանութի email-ը'], 'importName' => $sn, 'noEmail' => 1], $https); } } catch (Throwable $e) {} }
$SHARED = '/^noa-(accounts|orders|ws-orders(\|.*)?|chat(\|.*)?|cost-v2|inv2|invites(\|.*)?|drivers|paycfg|admin-ov|stock|stock-full|stock-pcs|soldout|apri-lots|prod|theme-sel|theme-dates|notify|cities|city-req|members\|.*|deposit\|.*|depsign\|.*|rewards\|.*|refs|geo|invoices|dlv)$/';
$PUBLIC = ['noa-theme-sel', 'noa-theme-dates', 'noa-stock', 'noa-stock-pcs', 'noa-stock-full', 'noa-soldout', 'noa-cities', 'noa-paycfg'];
function kv_get($db, $k) { $s = $db->prepare('SELECT v, t FROM nofa_kv WHERE k = ?'); $s->execute([$k]); $r = $s->fetch(PDO::FETCH_ASSOC); return $r ?: null; }
function apri_rpc($fn, $args, &$code) {
  $url = 'https://oddqwjvqqvoeqdrnasqc.supabase.co/rest/v1/rpc/' . $fn; $key = 'sb_publishable_ZVvJKCJXM5ylUlICX-57vw__ZZqQul0';
  $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15, 'ignore_errors' => true, 'header' => "Content-Type: application/json\r\napikey: $key\r\nAuthorization: Bearer $key\r\n", 'content' => json_encode($args, JSON_UNESCAPED_UNICODE)]]);
  $r = @file_get_contents(getenv('NOFA_APRI_URL') ? getenv('NOFA_APRI_URL') . $fn : $url, false, $ctx); $code = 0;
  if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $mm)) { $code = (int) $mm[1]; }
  return $r;
}

// ---- rich owner notifications (title = who, body = what happened), like a phone app notice ----
function pn_names() { return ['Գաթա', 'Խաշ', 'Խաշի արգանակ', 'Խաշի հավաքածու', 'Խինկալի', 'Պելմենի', 'Բլինչիկ', 'Միջուկով քուֆթա', 'Կիևյան կոտլետ', 'Հավի կոտլետ', 'Տավարի կոտլետ']; }
function pn_who($db, $em) {
  $em = strtolower(trim((string) $em)); if ($em === '') { return 'Nectar of Armenia'; }
  $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: [];
  foreach ($A as $rec) { if (is_array($rec) && strtolower((string) ($rec['email'] ?? '')) === $em) { $n = trim((string) ($rec['biz'] ?? ($rec['company'] ?? ''))); if ($n === '') { $n = trim((string) ($rec['name'] ?? (($rec['first'] ?? '') . ' ' . ($rec['last'] ?? '')))); } if ($n !== '') { return mb_substr($n, 0, 60); } } }
  return $em;
}
function pn_items($its, $pk) { $N = pn_names(); $o = []; $tot = 0; foreach ((array) $its as $it) { $pi = (int) ($it[0] ?? -1); $q = (int) ($it[1] ?? 0); if ($q < 1) { continue; } $tot += $q; if (count($o) < 4) { $o[] = ($N[$pi] ?? ('#' . $pi)) . ' ' . $q; } } return [$o ? implode(', ', $o) . (count((array) $its) > 4 ? '…' : '') : '', $tot . ($pk === 'pcs' ? ' հատ' : ' տուփ')]; }
// wholesale orders: notice for a new order, a cancel, a payment step; old orders are recorded silently
function pn_wso($db, $em, $v) {
  $L = is_string($v) ? json_decode($v, true) : $v; if (!is_array($L)) { return 0; } $who = pn_who($db, $em); $n = 0;
  $S = json_decode((kv_get($db, '__pnws|' . $em) ?: ['v' => '{}'])['v'], true) ?: []; $ch = false;
  foreach (array_slice($L, -60) as $o) { if (!is_array($o) || empty($o['no'])) { continue; } $no = (string) $o['no'];
    $sig = (int) ($o['st'] ?? 0) . '|' . (!empty($o['paid']) ? 1 : 0) . '|' . (!empty($o['payProc']) ? 1 : 0) . '|' . (!empty($o['zelleAt']) ? 1 : 0);
    if (($S[$no] ?? null) === $sig) { continue; } $was = $S[$no] ?? null; $S[$no] = $sig; $ch = true;
    $d = strtotime((string) ($o['at'] ?? ($o['date'] ?? ''))) ?: 0; if ($was === null && ($d < time() - 3 * 86400 || !empty($o['imported']))) { continue; }
    [$lst, $cnt] = pn_items($o['items'] ?? [], (string) ($o['pack'] ?? '')); $amt = (float) ($o['amt'] ?? 0); $A = $amt > 0 ? ' · $' . number_format($amt, 2) : '';
    $day = (string) ($o['reqDate'] ?? ''); $day = $day !== '' ? ' · առաքում ' . date('m/d', strtotime($day) ?: time()) : '';
    if ($was === null) { push_admin($db, '🏪 ' . $who, 'Նոր պատվեր ' . $no . $A . ' · ' . $cnt . ($lst !== '' ? ' — ' . $lst : '') . $day, 'Admin.dc.html#ws'); $n++; }
    elseif ((int) ($o['st'] ?? 0) === 2 && strpos((string) $was, '2|') !== 0) { push_admin($db, '❌ ' . $who, 'Չեղարկեց պատվերը ' . $no . $A, 'Admin.dc.html#ws'); $n++; }
    elseif (!empty($o['payProc']) && (explode('|', (string) $was)[2] ?? '0') !== '1') { push_admin($db, '⏳ ' . $who, 'Վճարումը մշակվում է (ACH) · ' . $no . $A, 'Admin.dc.html#ws'); $n++; }
  }
  if ($ch) { if (count($S) > 300) { $S = array_slice($S, -300, null, true); } kv_put($db, '__pnws|' . $em, json_encode($S)); }
  return $n;
}
function pn_retail($db, $em, $v) {
  $L = is_string($v) ? json_decode($v, true) : $v; if (!is_array($L)) { return 0; } $n = 0;
  foreach (array_slice($L, -10) as $o) { if (!is_array($o) || empty($o['no'])) { continue; } $k = '__pnr|' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $o['no']); if (kv_get($db, $k)) { continue; } kv_put($db, $k, '1');
    $c = 0; $nm = []; foreach ((array) ($o['items'] ?? []) as $it) { $q = (int) (is_array($it) ? ($it['qty'] ?? ($it['q'] ?? ($it[1] ?? 1))) : 1); $c += max(1, $q); $t0 = is_array($it) ? (string) ($it['name'] ?? ($it['n'] ?? '')) : ''; if ($t0 !== '' && count($nm) < 3) { $nm[] = $t0 . ' ' . max(1, $q); } }
    push_admin($db, '🛒 ' . ($em !== '' ? pn_who($db, $em) : 'Նոր հաճախորդ'), 'Նոր պատվեր ' . $o['no'] . ' · ' . (string) ($o['total'] ?? '') . ' · ' . $c . ' ապրանք' . ($nm ? ' — ' . implode(', ', $nm) : '') . (!empty($o['day']) ? ' · ' . date('m/d', strtotime((string) $o['day']) ?: time()) : ''), 'Admin.dc.html'); $n++; }
  return $n;
}
// Chef Apri: production approved, packed boxes, finished deliveries (read here with the server's own token, so a ping can't inject data)
// Chef Apri product name → this site's warehouse slot (same rules as the admin page's apriIdx)
function apri_idx($name) { $n = mb_strtolower((string) $name);
  if (preg_match('/kiev|կիև|կիեվ/u', $n)) { return 8; } if (preg_match('/gata|գաթա/u', $n)) { return 0; } if (preg_match('/khinkal|խինկալ/u', $n)) { return 4; }
  if (preg_match('/pelmen|պելմեն/u', $n)) { return 5; } if (preg_match('/blinch|բլինչ/u', $n)) { return 6; } if (preg_match('/kuft|kyuft|քուֆթ|քյուֆթ/u', $n)) { return 7; }
  if (preg_match('/beef|տավար/u', $n)) { return 10; } if (preg_match('/chick|հավ/u', $n)) { return 9; } if (preg_match('/khash|խաշ/u', $n)) { return 1; } return -1; }
// set the loose (unpacked) pieces from Chef Apri's 'un' (production − packed + admin corrections); boxes are left alone
function apri_loose_set($db, $products) {
  $UN = []; $has = false;
  foreach ($products as $pr) { if (!is_array($pr) || !array_key_exists('un', $pr)) { continue; } $has = true; $i = apri_idx($pr['name'] ?? ''); if ($i < 0 || in_array($i, [1, 2, 3], true)) { continue; } $UN[$i] = ($UN[$i] ?? 0) + max(0, (int) round((float) $pr['un'])); }
  if (!$has) { return false; }
  $L = json_decode((kv_get($db, 'noa-stock-pcs') ?: ['v' => 'null'])['v'], true); if (!is_array($L) || count($L) !== 11) { $L = array_fill(0, 11, 0); }
  $ch = false; foreach ($UN as $i => $v) { if ((int) ($L[$i] ?? 0) !== $v) { $L[$i] = $v; $ch = true; } }
  if ($ch) { kv_put($db, 'noa-stock-pcs', json_encode(array_values($L))); apri_stock_sync($db); }
  return $ch;
}
function apri_watch($db, $force = false) {
  $last = (int) ((kv_get($db, '__apriw_t') ?: ['v' => '0'])['v']); if (!$force && time() - $last < 40) { return ['skip' => 1]; } kv_put($db, '__apriw_t', (string) time());
  $t = kv_get($db, '__apri_token'); if (!$t) { return ['err' => 'no_token']; }
  $P = json_decode((kv_get($db, '__apriw') ?: ['v' => '{}'])['v'], true) ?: []; $first = empty($P); $out = [];
  $code = 0; $r = apri_rpc('nofa_stock', ['p_token' => $t['v']], $code); $sk = json_decode((string) $r, true);
  if (is_array($sk) && isset($sk['products'])) { $mk = []; $pkd = []; $un = [];
    foreach ((array) $sk['products'] as $pr) { $id = (string) ($pr['id'] ?? ''); if ($id === '') { continue; } $nm = (string) ($pr['name'] ?? $id); $m = (float) ($pr['made'] ?? 0); $pp = (float) ($pr['packedPcs'] ?? 0); $u = (float) ($pr['un'] ?? 0);
      if (!$first && isset($P['m'][$id])) { $dm = $m - (float) $P['m'][$id]; $dp = $pp - (float) ($P['p'][$id] ?? $pp);
        if ($dm >= 1) { $out[] = ['👨‍🍳 Chef Apri · արտադրություն', '+' . (int) $dm . ' ' . $nm . ' հաստատվեց · պահեստում հիմա՝ ' . (int) $u . ' հատ', 'Admin.dc.html#stock']; }
        elseif ($dm <= -1) { $out[] = ['⚠️ Chef Apri · արտադրություն', $nm . '՝ ' . (int) $dm . ' հատ (ուղղում կամ մերժում) · պահեստում՝ ' . (int) $u, 'Admin.dc.html#stock']; }
        if ($dp >= 1) { $out[] = ['📦 Chef Apri · փաթեթավորում', $nm . '՝ +' . (int) $dp . ' հատ փաթեթավորվեց · չփաթեթավորված մնաց՝ ' . (int) $u, 'Admin.dc.html#stock']; } }
      $mk[$id] = $m; $pkd[$id] = $pp; $un[$id] = $u; }
    $P['m'] = $mk; $P['p'] = $pkd; $P['u'] = $un;
    // warehouse: unpacked pieces are Chef Apri's own count, set here on the server (no admin page needed)
    try { apri_loose_set($db, (array) $sk['products']); } catch (Throwable $e) {} }
  $c2 = 0; $since = (string) ($P['ds'] ?? gmdate('c', time() - 3600)); $r2 = apri_rpc('nofa_delivery_status', ['p_token' => $t['v'], 'p_since' => $since], $c2); $ds = json_decode((string) $r2, true);
  if (is_array($ds)) { $seen = (array) ($P['dn'] ?? []); $mx = $since;
    foreach ($ds as $d) { $id = (string) ($d['id'] ?? ''); $st = (string) ($d['status'] ?? ''); $u0 = (string) ($d['updated_at'] ?? ''); if ($u0 > $mx) { $mx = $u0; } if ($id === '' || ($seen[$id] ?? '') === $st) { continue; } $seen[$id] = $st;
      if ($first) { continue; } $rs = (array) ($d['result'] ?? []);
      if (in_array($st, ['done', 'delivered'], true)) { $out[] = ['🚚 Առաքված · ' . $id, 'Ստացավ՝ ' . mb_substr((string) ($rs['signer'] ?? '—'), 0, 40) . (!empty($d['has_photo']) ? ' · 📷' : '') . (!empty($d['has_sig']) ? ' · ✍️' : ''), 'Admin.dc.html']; }
      elseif (in_array($st, ['failed', 'skipped', 'problem'], true)) { $out[] = ['⚠️ Առաքումը չկայացավ · ' . $id, mb_substr((string) ($rs['note'] ?? ($rs['reason'] ?? $st)), 0, 120), 'Admin.dc.html']; } }
    if (count($seen) > 400) { $seen = array_slice($seen, -400, null, true); } $P['dn'] = $seen; $P['ds'] = $mx; }
  kv_put($db, '__apriw', json_encode($P, JSON_UNESCAPED_UNICODE));
  foreach (array_slice($out, 0, 8) as $x) { push_admin($db, $x[0], $x[1], $x[2]); }
  return ['n' => count($out), 'first' => $first];
}
function cinvite_do($db, $b, $https) {
    $in = is_array($b['acc'] ?? null) ? $b['acc'] : []; $em = strtolower(trim((string) ($in['email'] ?? '')));
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) { return ['ok' => false, 'err' => 'email', '_code' => 400]; }
    $ty = ($in['type'] ?? '') === 'biz' ? 'biz' : 'retail'; if ($ty === 'biz' && trim((string) ($in['biz'] ?? '')) === '') { return ['ok' => false, 'err' => 'biz', '_code' => 400]; }
    $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; $key = $em . '|' . $ty; $rec = isset($A[$key]) && is_array($A[$key]) ? $A[$key] : [];
    $impName0 = trim((string) ($b['importName'] ?? '')); $nrm0 = function ($x) { return preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $x)); };
    if ($ty === 'biz' && empty($b['noEmail'])) { foreach ($A as $k0 => $r0) { if (!is_array($r0) || empty($r0['noEmail']) || $k0 === $key) { continue; } if (($impName0 !== '' && $nrm0($r0['importedFrom'] ?? '') === $nrm0($impName0)) || $nrm0($r0['biz'] ?? '') === $nrm0($in['biz'] ?? '')) { $old = (string) ($r0['email'] ?? '');
        $rec = array_merge($r0, $rec); unset($rec['noEmail'], $rec['invitedAt']); $rec['email'] = $em; unset($A[$k0]);
        $oo = json_decode((kv_get($db, 'noa-ws-orders|' . $old) ?: ['v' => '[]'])['v'], true) ?: []; $nn = json_decode((kv_get($db, 'noa-ws-orders|' . $em) ?: ['v' => '[]'])['v'], true) ?: []; $hv = []; foreach ($nn as $o1) { if (is_array($o1)) { $hv[(string) ($o1['no'] ?? '')] = 1; } } foreach ($oo as $o1) { if (is_array($o1) && empty($hv[(string) ($o1['no'] ?? '')])) { $nn[] = $o1; } }
        if ($nn) { kv_put($db, 'noa-ws-orders|' . $em, json_encode($nn, JSON_UNESCAPED_UNICODE)); } kv_put($db, 'noa-ws-orders|' . $old, '[]');
        $cv0 = json_decode((kv_get($db, 'noa-cost-v2') ?: ['v' => '{}'])['v'], true) ?: []; $ch0 = false; foreach ((array) ($cv0['cprof'] ?? []) as $ck0 => $q0) { if (is_array($q0) && ($q0['em'] ?? '') === $old) { $cv0['cprof'][$ck0]['em'] = $em; $ch0 = true; } } if ($ch0) { kv_put($db, 'noa-cost-v2', json_encode($cv0, JSON_UNESCAPED_UNICODE)); }
        $rec['movedFrom'] = $old; $GN = json_decode((kv_get($db, '__acc_gone') ?: ['v' => '[]'])['v'], true) ?: []; $GN[] = $k0; kv_put($db, '__acc_gone', json_encode(array_values(array_unique($GN)))); break; } } }
    if (!empty($b['noEmail'])) { $rec['noEmail'] = 1; }
    foreach (['first', 'last', 'contact', 'role', 'phone', 'biz', 'bizType', 'addr', 'city', 'zip', 'permit', 'ein', 'note'] as $f) { if (array_key_exists($f, $in) && trim((string) $in[$f]) !== '') { $rec[$f] = mb_substr(trim((string) $in[$f]), 0, 200); } }
    $rec['type'] = $ty; $rec['email'] = $em; if (empty($rec['tok'])) { $rec['tok'] = bin2hex(random_bytes(16)); }
    if (empty($rec['status']) || $rec['status'] === 'pending') { $rec['status'] = 'approved'; $rec['decidedAt'] = gmdate('c'); }
    if (empty($rec['createdAt'])) { $rec['createdAt'] = gmdate('c'); $rec['via'] = 'admin'; $rec['byAdmin'] = 1; if ($ty === 'biz' && empty($rec['pid'])) { $usedP = []; foreach ($A as $x0) { if (is_array($x0) && !empty($x0['pid'])) { $usedP[(string) $x0['pid']] = 1; } } for ($pi0 = 0; $pi0 < 50; $pi0++) { $rec['pid'] = 'W-' . random_int(1100, 1899); if (empty($usedP[$rec['pid']])) { break; } } if (!empty($usedP[$rec['pid']])) { $rec['pid'] = 'W-' . random_int(1900, 9999); } } }
    $has = (bool) kv_get($db, '__cpw|' . $em); $link = ''; $sent = false; $merr = '';
    if (!$has && empty($b['noEmail'])) { $t = bin2hex(random_bytes(20)); kv_put($db, '__pwr|' . $t, json_encode(['em' => $em, 't' => now_ms(), 'inv' => 1]));
      $host = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'nectarofarmenia.com'); $base = $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/api.php')), '/'); $link = $base . '/Account.dc.html?reset=' . $t . '&inv=' . $ty;
      $rec['invitedAt'] = gmdate('c');
      if (!empty($b['send'])) { $m = mail_cfg($db); if (!empty($m['user']) && !empty($m['pass'])) { $hy = (($b['lang'] ?? 'hy') === 'hy'); $who = $ty === 'biz' ? (string) ($rec['biz'] ?? '') : trim(($rec['first'] ?? '') . ' ' . ($rec['last'] ?? ''));
        $btn = '<p style="margin:22px 0 0"><a href="' . mail_esc($link) . '" style="display:inline-block;background:#EBA93A;color:#133B30;padding:14px 30px;border-radius:999px;text-decoration:none;font-weight:700;font-size:16px;border-bottom:3px solid #C98A1E">' . ($hy ? 'Ընտրել գաղտնաբառ և մուտք գործել →' : 'Choose a password and sign in →') . '</a></p>';
        $inner = $hy ? '<p style="margin:0 0 12px;font-size:16px">Բարև' . ($who !== '' ? ' ' . mail_esc($who) : '') . ',</p><p style="margin:0 0 14px"><b>Nectar of Armenia</b>-ն ձեզ համար ստեղծել է ' . ($ty === 'biz' ? 'մեծածախ գործընկերոջ' : 'անձնական') . ' էջ։ Այնտեղ կտեսնեք ձեր բոլոր գնումները, հաշիվները և փաստաթղթերը, կարող եք նաև պատվիրել։</p><p style="margin:0">Սեղմեք կոճակը, ընտրեք ձեր գաղտնաբառը, և էջը կդառնա ձերը։ Հղումը գործում է 14 օր։</p>' . $btn . '<p style="font-size:12.5px;color:#7A817C;margin:18px 0 0">Հարցերի դեպքում զանգահարեք +1 (515) 588-5555։</p>'
          : '<p style="margin:0 0 12px;font-size:16px">Hello' . ($who !== '' ? ' ' . mail_esc($who) : '') . ',</p><p style="margin:0 0 14px"><b>Nectar of Armenia</b> has created a ' . ($ty === 'biz' ? 'wholesale partner' : 'personal') . ' account for you. There you can see all your purchases, invoices and documents, and place orders.</p><p style="margin:0">Press the button, choose your password and the account is yours. The link works for 14 days.</p>' . $btn . '<p style="font-size:12.5px;color:#7A817C;margin:18px 0 0">Questions? Call +1 (515) 588-5555.</p>';
        $sent = smtp_send($m, $em, $hy ? 'Ձեր էջը Nectar of Armenia-ում պատրաստ է' : 'Your Nectar of Armenia account is ready', mail_tpl($hy ? 'Բարի գալուստ 🌿' : 'Welcome 🌿', $inner, $hy ? 'hy' : 'en'), $merr); if ($sent) { $rec['invSentAt'] = gmdate('c'); } } else { $merr = 'nomail'; } } }
    $imp = 0; $impName = trim((string) ($b['importName'] ?? ''));
    if ($ty === 'biz' && $impName !== '') { // previous phone orders of this store (cost sheet) become the store's order history
      $nrm = function ($x) { return preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $x)); }; $want = $nrm($impName);
      $cv = json_decode((kv_get($db, 'noa-cost-v2') ?: ['v' => '{}'])['v'], true) ?: []; $PR = (array) ($cv['prod'] ?? []);
      $SI = ['gata' => 0, 'khash' => 1, 'khbroth' => 2, 'khset' => 3, 'khinkali' => 4, 'pelmeni' => 5, 'blinchik' => 6, 'kufta' => 7, 'kiev' => 8, 'chcut' => 9, 'beefcut' => 10];
      $ok0 = 'noa-ws-orders|' . $em; $RO = json_decode((kv_get($db, $ok0) ?: ['v' => '[]'])['v'], true) ?: []; $have = []; foreach ($RO as $o0) { if (is_array($o0)) { $have[(string) ($o0['no'] ?? '')] = 1; } }
      $cprofKey = null; foreach ((array) ($cv['cprof'] ?? []) as $ck => $q) { if (substr((string) $ck, -2) === '|w' && $nrm(substr((string) $ck, 0, -2)) === $want) { $cprofKey = $ck; } }
      foreach ((array) ($cv['calls'] ?? []) as $c) { if (!is_array($c) || ($c['type'] ?? '') !== 'w' || $nrm($c['name'] ?? '') !== $want) { continue; }
        $no = 'NofA-T-' . strtoupper(substr(md5((string) ($c['id'] ?? json_encode($c))), 0, 6)); if (isset($have[$no])) { continue; }
        $items = []; $tot = 0; $allPcs = true; foreach ((array) ($c['lines'] ?? []) as $l) { $pr = $PR[(int) ($l[0] ?? -1)] ?? null; if (!is_array($pr)) { continue; } $pcs = ((int) ($l[1] ?? 0)) === 1; if (!$pcs) { $allPcs = false; } $q = (float) ($l[2] ?? 0); $u = isset($l[3]) && $l[3] !== null ? (float) $l[3] : (float) (($pr['pr'] ?? [])[2 + ($pcs ? 1 : 0)] ?? 0); $tot += $q * $u; $si = $SI[(string) ($pr['id'] ?? '')] ?? null; if ($si !== null) { $items[] = [$si, $q]; } }
        if (!$items) { continue; } $d = (string) ($c['ddate'] ?? ($c['date'] ?? '')); $iso = preg_match('/^\d{4}-\d{2}-\d{2}/', $d) ? substr($d, 0, 10) . 'T12:00:00.000Z' : gmdate('c');
        if (empty($rec['phone']) && !empty($c['phone'])) { $rec['phone'] = mb_substr((string) $c['phone'], 0, 40); } if (empty($rec['addr']) && !empty($c['addr'])) { $rec['addr'] = mb_substr((string) $c['addr'], 0, 160); }
        $RO[] = ['no' => $no, 'date' => $iso, 'reqDate' => $iso, 'at' => $iso, 'items' => $items, 'pack' => $allPcs ? 'pcs' : 'pk', 'amt' => round($tot, 2), 'st' => !empty($c['past']) ? 2 : 1, 'ap' => 'approved', 'paid' => !empty($c['paid']), 'pm' => !empty($c['paid']) ? (string) ($c['pm'] ?? 'Paid') : 'Invoice', 'by' => 'Nectar of Armenia', 'imported' => 1, 'src' => 'phone']; $have[$no] = 1; $imp++; }
      if ($imp) { usort($RO, function ($x, $y) { return strcmp((string) ($x['date'] ?? ''), (string) ($y['date'] ?? '')); }); kv_put($db, $ok0, json_encode($RO, JSON_UNESCAPED_UNICODE)); }
      if ($cprofKey) { $cv['cprof'][$cprofKey]['em'] = $em; kv_put($db, 'noa-cost-v2', json_encode($cv, JSON_UNESCAPED_UNICODE)); }
      $rec['importedFrom'] = $impName; }
    $A[$key] = $rec; kv_put($db, 'noa-accounts', json_encode($A, JSON_UNESCAPED_UNICODE));
    return ['ok' => true, 'key' => $key, 'acct' => $rec, 'imported' => $imp, 'has' => $has, 'link' => $link, 'sent' => $sent, 'merr' => $sent ? '' : (string) $merr];
}
// accounts sent by an admin browser replace the server copy; keep accounts the server created meanwhile (invites, store imports) and never bring back moved ones
function acc_merge($db, $v) {
  $IN = json_decode($v, true); if (!is_array($IN)) { return $v; } $CUR = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: [];
  $GONE = json_decode((kv_get($db, '__acc_gone') ?: ['v' => '[]'])['v'], true) ?: []; $ch = false;
  foreach ($CUR as $k => $r) { if (isset($IN[$k]) || !is_array($r)) { continue; } $t = max(strtotime((string) ($r['createdAt'] ?? '')) ?: 0, strtotime((string) ($r['invitedAt'] ?? '')) ?: 0, strtotime((string) ($r['decidedAt'] ?? '')) ?: 0); if (!empty($r['byAdmin']) || !empty($r['importedFrom']) || $t > time() - 30 * 86400) { $IN[$k] = $r; $ch = true; } }
  foreach ($CUR as $k => $r) { if (!is_array($r) || !isset($IN[$k]) || !is_array($IN[$k])) { continue; } foreach (['addr', 'city', 'state', 'zip', 'phone'] as $f9) { if (!empty($r[$f9]) && empty($IN[$k][$f9])) { $IN[$k][$f9] = $r[$f9]; $ch = true; } } }
  foreach ($GONE as $g) { if (isset($IN[$g])) { unset($IN[$g]); $ch = true; } }
  return $ch ? json_encode($IN, JSON_UNESCAPED_UNICODE) : $v;
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
function mail_tpl($title, $inner, $lang = '') {
  $host = $_SERVER['HTTP_HOST'] ?? ''; if ($host === '' || preg_match('/^(127\.|localhost)/', $host)) { $host = 'nectarofarmenia.com'; } $u = 'https://' . $host;
  $hy = $lang === 'hy' || ($lang === '' && preg_match('/[\x{0531}-\x{0587}]/u', $title . $inner));
  $say = $hy ? 'Շնորհակալություն, որ ընտրում եք մեզ։ Ամեն ինչ պատրաստում ենք սիրով՝ հայկական ավանդական բաղադրատոմսերով։' : 'Thank you for choosing us. Everything is made with love, from traditional Armenian recipes.';
  $sig = $hy ? '— Շեֆ Ապրի' : '— Chef Apri';
  $f = 'font-family:Georgia,\'Times New Roman\',serif'; $g = 'font-family:Arial,Helvetica,sans-serif';
  return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><title>' . $title . '</title></head>'
  . '<body style="margin:0;padding:0;background:#F3EEE2;' . $g . ';color:#23342C;-webkit-text-size-adjust:100%">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F3EEE2"><tr><td align="center" style="padding:24px 10px">'
  . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#FFFDF7;border-radius:22px;overflow:hidden;border:1px solid #E6DCC4">'
  // header banner: logo, areva, khinkali
  . '<tr><td style="padding:0;line-height:0;background:#1D5545"><a href="' . $u . '" style="text-decoration:none"><img src="' . $u . '/img/email/header.jpg" width="600" alt="Nectar of Armenia" style="display:block;width:100%;max-width:600px;height:auto;border:0"></a></td></tr>'
  // body
  . '<tr><td style="padding:30px 32px 8px">'
  . '<h1 style="margin:0 0 6px;' . $f . ';font-size:26px;line-height:1.25;font-weight:600;color:#1D5545">' . $title . '</h1>'
  . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:10px 0 18px"><tr><td style="width:56px;height:3px;background:#EBA93A;border-radius:3px;font-size:0;line-height:0">&nbsp;</td></tr></table>'
  . '<div style="font-size:15.5px;line-height:1.6;color:#33403A">' . $inner . '</div></td></tr>'
  // Chef Apri speaks
  . '<tr><td style="padding:18px 24px 26px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FBF3E1;border:1px solid #F0DDB0;border-radius:18px"><tr>'
  . '<td width="96" valign="bottom" style="padding:12px 0 0 14px;line-height:0"><img src="' . $u . '/img/email/apri.png" width="86" alt="Chef Apri" style="display:block;width:86px;height:auto;border:0"></td>'
  . '<td valign="middle" style="padding:16px 18px 16px 12px;font-size:14.5px;line-height:1.55;color:#4A3B17"><span style="' . $f . ';font-style:italic">“' . $say . '”</span><br><b style="display:inline-block;margin-top:6px;color:#B07A1A;font-size:13.5px">' . $sig . '</b></td>'
  . '</tr></table></td></tr>'
  // footer
  . '<tr><td align="center" style="background:#1D5545;padding:24px 22px 26px;color:#CFE0D8;font-size:13px;line-height:1.7">'
  . '<img src="' . $u . '/img/email/arev.png" width="34" height="34" alt="" style="display:block;margin:0 auto 8px;border:0;opacity:.9">'
  . '<div style="' . $f . ';font-size:17px;color:#F6E3B4;font-weight:600">Nectar of Armenia Inc</div>'
  . '<div>6222 San Fernando Rd, Glendale, CA 91201</div>'
  . '<div><a href="tel:+15155885555" style="color:#F6C35A;text-decoration:none;font-weight:bold">+1 (515) 588-5555</a> · <a href="mailto:orders@nectarofarmenia.com" style="color:#F6C35A;text-decoration:none">orders@nectarofarmenia.com</a></div>'
  . '<div style="margin-top:12px"><a href="' . $u . '" style="color:#FFFFFF;text-decoration:none;font-weight:bold">' . ($hy ? 'Կայք' : 'Website') . '</a> &nbsp;·&nbsp; <a href="https://www.instagram.com/nectar_of_armenia/" style="color:#FFFFFF;text-decoration:none">Instagram</a> &nbsp;·&nbsp; <a href="https://www.facebook.com/profile.php?id=61574622277482" style="color:#FFFFFF;text-decoration:none">Facebook</a> &nbsp;·&nbsp; <a href="https://www.youtube.com/channel/UCgOrvhFCGnhkaffhzbVLzkg" style="color:#FFFFFF;text-decoration:none">YouTube</a></div>'
  . '</td></tr></table>'
  . '<div style="max-width:600px;padding:14px 10px 0;color:#8A8F86;font-size:11.5px;line-height:1.5">' . ($hy ? 'Այս նամակն ուղարկվել է Nectar of Armenia-ի կայքից։' : 'This email was sent by the Nectar of Armenia website.') . '</div>'
  . '</td></tr></table></body></html>';
}
function mail_esc($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function pay_mails($db, $ref, $amt) { // after a confirmed payment: receipt to the customer + notice to the owner (once)
  $pk0 = '__pushpaid|' . $ref; if (!kv_get($db, $pk0)) { kv_put($db, $pk0, '1'); push_admin($db, '💳 Վճարված պատվեր · $' . number_format((float) $amt, 2), (strpos($ref, 'INV:') === 0 ? 'Հաշիվ #' . substr($ref, 4) : (strpos($ref, 'WSO:') === 0 ? 'Մեծածախ պատվեր ' . substr($ref, 4) : $ref)) . ' · վճարումը ստացված է'); }
  $m = mail_cfg($db); if (empty($m['user'])) { return; } $pk = '__pay|' . $ref; $pr = kv_get($db, $pk); $pv = $pr ? (json_decode($pr['v'], true) ?: []) : []; if (!empty($pv['mailed'])) { return; }
  $pv['mailed'] = now_ms(); kv_put($db, $pk, json_encode($pv, JSON_UNESCAPED_UNICODE));
  $rows = ''; foreach ((array) ($pv['lines'] ?? []) as $l) { $rows .= '<tr><td style="padding:6px 0">' . mail_esc($l[0]) . ' × ' . (int) $l[1] . '</td><td style="padding:6px 0;text-align:right">$' . number_format((float) $l[2], 2) . '</td></tr>'; }
  if (!empty($pv['fee']) && strpos($ref, 'INV:') !== 0) { $rows .= '<tr><td style="padding:6px 0">Delivery</td><td style="padding:6px 0;text-align:right">$' . number_format((float) $pv['fee'], 2) . '</td></tr>'; }
  $tbl = '<table style="width:100%;border-collapse:collapse;font-size:15px">' . $rows . '<tr><td style="padding:10px 0;border-top:1px solid #E4DCC8;font-weight:bold">Total paid</td><td style="padding:10px 0;border-top:1px solid #E4DCC8;text-align:right;font-weight:bold;color:#1D5545">$' . number_format((float) $amt, 2) . '</td></tr></table>';
  $isWso = strpos($ref, 'WSO:') === 0; $isInv = strpos($ref, 'INV:') === 0 || $isWso; $label = $isWso ? ('Wholesale order ' . implode(', ', (array) ($pv['nos'] ?? []))) : ($isInv ? ('Invoice #' . ($pv['no'] ?? substr($ref, 4))) : ('Order ' . $ref));
  $extra = !$isInv ? ('<p style="margin:12px 0 0;font-size:15px">' . (($pv['fm'] ?? '') === 'pick' ? 'Pickup: 6222 San Fernando Rd, Glendale, CA 91201' : 'Delivery: ' . mail_esc($pv['addr'] ?? '')) . (!empty($pv['day']) ? '<br>Date: ' . mail_esc(substr((string) $pv['day'], 0, 10)) : '') . '</p>') : '';
  $cust = (string) ($pv['email'] ?? ''); $site = (string) ($pv['site'] ?? '');
  if (filter_var($cust, FILTER_VALIDATE_EMAIL)) { smtp_send($m, $cust, 'Payment received — ' . $label, mail_tpl('Thank you! Payment received ✓', '<p style="margin:0 0 14px;font-size:15px">' . mail_esc($label) . ' is confirmed. We are getting it ready.</p>' . $tbl . $extra . ($site ? '<p style="margin:18px 0 0"><a href="' . mail_esc($site) . '" style="display:inline-block;background:#EBA93A;color:#133B30;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:999px">View my order</a></p>' : '') . '<p style="margin:16px 0 0;color:#6B756F;font-size:13px">Questions? Reply to this email or call +1 (515) 588-5555.</p>')); }
  $own = (string) ($m['notify'] ?? $m['user']); if (filter_var($own, FILTER_VALIDATE_EMAIL)) { smtp_send($m, $own, '💳 New payment $' . number_format((float) $amt, 2) . ' — ' . $label, mail_tpl('New payment received', '<p style="margin:0 0 14px;font-size:15px"><b>' . mail_esc($label) . '</b><br>' . mail_esc($pv['name'] ?? '') . ' · ' . mail_esc($cust) . ' · ' . mail_esc($pv['phone'] ?? '') . '</p>' . $tbl . $extra)); }
}
function links_get($db) { return json_decode((kv_get($db, '__links') ?: ['v' => '{}'])['v'], true) ?: []; }
function nofa_docs() { return ['1' => ['f' => __DIR__ . '/private/glendale-business-registration.pdf', 'no' => 'BRC-003767-2025', 'exp' => '2027-05-14'], '3' => ['f' => __DIR__ . '/private/cdph-processed-food-registration.pdf', 'no' => '126550', 'exp' => '2027-10-06']]; }
function doc_secret($db) { $r = kv_get($db, '__docsec'); if ($r && strlen($r['v']) >= 32) { return $r['v']; } $k = bin2hex(random_bytes(32)); kv_put($db, '__docsec', $k); return $k; }
function geo_ip($db, $ip) { // country/city of a visitor's IP, cached 14 days by a hash (the IP itself is never stored)
  if ($ip === '' || preg_match('/^(127\.|10\.|192\.168\.|::1$)/', $ip)) { return ['cc' => '', 'c' => '', 'ci' => '', 'rg' => '']; }
  $hk = '__geo|' . substr(hash('sha256', 'nofa-geo|' . $ip), 0, 24); $r = kv_get($db, $hk);
  if ($r) { $g = json_decode($r['v'], true); if (is_array($g) && ($g['t'] ?? 0) > time() - 14 * 86400 && (array_key_exists('dc', $g) || ($g['t'] ?? 0) > time() - 86400)) { return $g; } }
  $g = ['cc' => '', 'c' => '', 'ci' => '', 'rg' => '', 't' => time()];
  $get = function ($url) { if (!function_exists('curl_init')) { $c = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 3]])); return $c ?: ''; } $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_USERAGENT => 'NofA-site']); $c = curl_exec($ch); curl_close($ch); return is_string($c) ? $c : ''; };
  $j = json_decode($get('https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country,country_code,region,city,connection'), true);
  if (is_array($j) && !empty($j['success'])) { $org = trim((string) ($j['connection']['org'] ?? '') . ' ' . (string) ($j['connection']['isp'] ?? '')); $g = ['cc' => (string) ($j['country_code'] ?? ''), 'c' => (string) ($j['country'] ?? ''), 'ci' => (string) ($j['city'] ?? ''), 'rg' => (string) ($j['region'] ?? ''), 'org' => mb_substr($org, 0, 80), 'dc' => (int) (bool) preg_match('/amazon|aws|google cloud|google llc|microsoft|azure|digitalocean|ovh|hetzner|linode|akamai|m247|datacamp|leaseweb|choopa|vultr|contabo|scaleway|oracle|alibaba|tencent|hosting|server|cloud|data ?cent|colo|ionos|godaddy|hostinger|cdn77|quadranet|psychz|zenlayer|g-core|stark|clouvider|pulsant|iomart|ukfast|redstation|fasthosts/i', $org), 't' => time()]; }
  else { $j = json_decode($get('https://ipapi.co/' . rawurlencode($ip) . '/json/'), true); if (is_array($j) && empty($j['error'])) { $g = ['cc' => (string) ($j['country_code'] ?? ''), 'c' => (string) ($j['country_name'] ?? ''), 'ci' => (string) ($j['city'] ?? ''), 'rg' => (string) ($j['region'] ?? ''), 't' => time()]; } }
  kv_put($db, $hk, json_encode($g, JSON_UNESCAPED_UNICODE)); return $g;
}
function sec_log($db, $type, $alertAt = 0) { // security events by day for the agent; push alert to the owner when a burst happens
  try { $tz = new DateTimeZone('America/Los_Angeles'); $d = (new DateTime('now', $tz))->format('Y-m-d'); $r = kv_get($db, '__sec'); $S = $r ? (json_decode($r['v'], true) ?: []) : [];
    $S[$d][$type] = ($S[$d][$type] ?? 0) + 1; if (count($S) > 45) { ksort($S); $S = array_slice($S, -40, null, true); } kv_put($db, '__sec', json_encode($S));
    if ($alertAt > 0) { $wk = '__secw|' . $type; $w = kv_get($db, $wk); $W = $w ? (json_decode($w['v'], true) ?: []) : []; $W = array_values(array_filter((array) ($W['t'] ?? []), function ($t) { return $t > time() - 900; })); $W[] = time(); $last = (int) (($w ? (json_decode($w['v'], true) ?: []) : [])['al'] ?? 0);
      $al = $last; if (count($W) >= $alertAt && $last < time() - 3600) { $al = time(); $TT = ['admin_login_fail' => 'Ադմինի մուտքի անհաջող փորձեր', 'cust_login_fail' => 'Հաճախորդների մուտքի անհաջող փորձեր', 'submit_reject' => 'Կասկածելի տվյալների ուղարկում', 'agent_auth_fail' => 'Ագենտի բանալու սխալ փորձեր']; push_admin($db, '🛡 Անվտանգություն · ' . ($TT[$type] ?? $type), count($W) . ' փորձ վերջին 15 րոպեում։ Կայքը արգելափակել է դրանք. ստուգեք Ագենտ բաժինը։', 'Admin.dc.html#agent'); }
      kv_put($db, $wk, json_encode(['t' => $W, 'al' => $al])); } } catch (Throwable $e) {}
}
// Warehouse → Chef Apri, done by the server itself whenever the stock keys change (no admin page needs to be open).
// Same shape as the admin page's push: 11 fixed products, packs + loose pieces + pieces per pack.
function apri_stock_items($db) {
  $S = json_decode((kv_get($db, 'noa-stock') ?: ['v' => 'null'])['v'], true); if (!is_array($S) || count($S) !== 11) { return null; }
  $L = json_decode((kv_get($db, 'noa-stock-pcs') ?: ['v' => 'null'])['v'], true); if (!is_array($L) || count($L) !== 11) { $L = array_fill(0, 11, 0); }
  $P = [12, 1, 1, 1, 10, 35, 10, 10, 6, 6, 6]; $C = json_decode((kv_get($db, 'noa-cost-v2') ?: ['v' => '{}'])['v'], true) ?: [];
  $idm = ['gata' => 0, 'khinkali' => 4, 'pelmeni' => 5, 'blinchik' => 6, 'kufta' => 7, 'kiev' => 8, 'chcut' => 9, 'beefcut' => 10];
  foreach ((array) ($C['prod'] ?? []) as $pp) { $id = $pp['id'] ?? ''; if (isset($idm[$id]) && (float) ($pp['packPcs'] ?? 0) > 0) { $P[$idm[$id]] = (float) $pp['packPcs']; } }
  $N = ['Գաթա', 'Խաշ', 'Խաշի արգանակ', 'Խաշի հավաքածու', 'Խինկալի', 'Պելմենի', 'Բլինչիկ', 'Միջուկով քուֆթա', 'Կիևյան կոտլետ', 'Հավի կոտլետ', 'Տավարի կոտլետ'];
  $it = []; for ($i = 0; $i < 11; $i++) { $it[] = ['i' => $i, 'name' => $N[$i], 'packs' => (float) ($S[$i] ?? 0), 'loose' => (float) ($L[$i] ?? 0), 'perPack' => $P[$i]]; }
  return $it;
}
function apri_stock_sync($db, $force = false) {
  $t = kv_get($db, '__apri_token'); if (!$t) { return false; }
  $it = apri_stock_items($db); if ($it === null) { return false; }
  $key = md5(json_encode($it)); $last = kv_get($db, '__apri_stock_last'); if (!$force && $last && $last['v'] === $key) { return true; }
  $r = apri_rpc('nofa_push_stock', ['p_token' => $t['v'], 'p_data' => ['at' => gmdate('c'), 'items' => $it]], $code);
  if ($r === false || $code >= 400) { return false; }
  kv_put($db, '__apri_stock_last', $key); return true;
}
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
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); $fk = '__alfail|' . $ip; $fr = kv_get($db, $fk); $fv = $fr ? (json_decode($fr['v'], true) ?: []) : [];
    $fv = array_values(array_filter((array) $fv, function ($t) { return $t > now_ms() - 900000; })); if (count($fv) >= 8) { sec_log($db, 'admin_login_blocked'); out(['ok' => false, 'err' => 'rate'], 429); }
    $apw = kv_get($db, '__adminpw'); $okpw = $pw !== '' && (password_verify($pw, (string) ($cfg['admin_hash'] ?? '')) || ($apw && password_verify($pw, (string) $apw['v'])));
    if ($okpw) { session_regenerate_id(true); $_SESSION['nofa_admin'] = 1; if ($fv) { kv_put($db, $fk, '[]'); } out(['ok' => true]); }
    $fv[] = now_ms(); kv_put($db, $fk, json_encode($fv)); sec_log($db, 'admin_login_fail', 5); sleep(1); out(['ok' => false, 'err' => 'bad_password'], 401);
  case 'cpw_set': // customer: set or change the account password (needs the account token)
    $b = body(); $em = strtolower(trim((string) ($b['em'] ?? ''))); $tok = (string) ($b['tok'] ?? ''); $pw = (string) ($b['pw'] ?? '');
    if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $em) || strlen($tok) < 16 || strlen($pw) < 8 || strlen($pw) > 200) { out(['ok' => false, 'err' => 'bad'], 400); }
    $k = '__cpw|' . $em; $old = kv_get($db, $k); $old = $old ? (json_decode($old['v'], true) ?: []) : [];
    if (!$old) { $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); $rk = '__cpwr|' . $ip; $rr = kv_get($db, $rk); $rv = $rr ? (json_decode($rr['v'], true) ?: []) : []; $rv = array_values(array_filter((array) $rv, function ($t) { return $t > now_ms() - 3600000; })); if (count($rv) >= 10) { out(['ok' => false, 'err' => 'rate'], 429); } $rv[] = now_ms(); kv_put($db, $rk, json_encode($rv)); }
    if ($old) { if (!hash_equals((string) ($old['tok'] ?? ''), $tok)) { out(['ok' => false, 'err' => 'exists'], 403); } }
    else { $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; foreach ($A as $key => $rec) { if (is_array($rec) && strtolower((string) ($rec['email'] ?? '')) === $em && !empty($rec['tok']) && !hash_equals((string) $rec['tok'], $tok)) { out(['ok' => false, 'err' => 'exists'], 403); } } }
    $acc = []; foreach ((array) ($b['acc'] ?? []) as $key => $rec) { $key = strtolower((string) $key); if (is_array($rec) && strpos($key, $em . '|') === 0 && count($acc) < 4) { unset($rec['pw'], $rec['pass']); $acc[$key] = $rec; } }
    if (!$acc && !empty($old['acc'])) { $acc = $old['acc']; }
    kv_put($db, $k, json_encode(['h' => password_hash($pw, PASSWORD_DEFAULT), 'tok' => $tok, 'acc' => $acc, 't' => now_ms()], JSON_UNESCAPED_UNICODE));
    if (!$old) { $m = mail_cfg($db); if (!empty($m['user']) && !empty($m['pass'])) { // welcome email for a brand-new account (once per email)
      $first = ''; foreach ($acc as $rec) { if (!empty($rec['first'])) { $first = (string) $rec['first']; break; } }
      $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); $host = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'nectarofarmenia.com'); $base = $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/api.php')), '/');
      $hy = (($b['lang'] ?? 'hy') === 'hy'); $btn = '<p style="margin:22px 0 0"><a href="' . mail_esc($base . '/Account.dc.html') . '" style="display:inline-block;background:#EBA93A;color:#133B30;padding:14px 30px;border-radius:999px;text-decoration:none;font-weight:700;font-size:16px;border-bottom:3px solid #C98A1E">' . ($hy ? 'Մուտք գործել իմ էջ →' : 'Go to my account →') . '</a></p>';
      $inner = $hy ? '<p style="margin:0 0 12px;font-size:16px">Բարև' . ($first !== '' ? ' ' . mail_esc($first) : '') . ',</p><p style="margin:0 0 14px">Ուրախ ենք ձեզ տեսնել մեր ընտանիքում։ Ձեր հաշիվը <b>Nectar of Armenia</b>-ում պատրաստ է։</p><table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 6px"><tr><td width="34" valign="top" style="font-size:20px;padding:6px 0">🛒</td><td style="padding:6px 0;font-size:15px;color:#33403A">Պատվիրեք մի քանի կտտոցով՝ հասցեն ու տվյալները պահված են։</td></tr><tr><td width="34" valign="top" style="font-size:20px;padding:6px 0">🚚</td><td style="padding:6px 0;font-size:15px;color:#33403A">Առաքում մինչև 48 ժամում կամ անվճար վերցնել Գլենդելից։</td></tr><tr><td width="34" valign="top" style="font-size:20px;padding:6px 0">📦</td><td style="padding:6px 0;font-size:15px;color:#33403A">Հետևեք պատվերին և տեսեք ձեր բոլոր պատվերները մեկ տեղում։</td></tr></table>' . $btn . '<p style="font-size:12.5px;color:#7A817C;margin:18px 0 0">Եթե դուք չեք գրանցվել, պարզապես անտեսեք այս նամակը կամ զանգահարեք +1 (515) 588-5555։</p>'
        : '<p style="margin:0 0 12px;font-size:16px">Hello' . ($first !== '' ? ' ' . mail_esc($first) : '') . ',</p><p style="margin:0 0 14px">We are glad to welcome you to our family. Your <b>Nectar of Armenia</b> account is ready.</p><table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 6px"><tr><td width="34" valign="top" style="font-size:20px;padding:6px 0">🛒</td><td style="padding:6px 0;font-size:15px;color:#33403A">Order in a few taps — your address and details are saved.</td></tr><tr><td width="34" valign="top" style="font-size:20px;padding:6px 0">🚚</td><td style="padding:6px 0;font-size:15px;color:#33403A">Delivery within 48 hours, or free pickup in Glendale.</td></tr><tr><td width="34" valign="top" style="font-size:20px;padding:6px 0">📦</td><td style="padding:6px 0;font-size:15px;color:#33403A">Track your order and see all your orders in one place.</td></tr></table>' . $btn . '<p style="font-size:12.5px;color:#7A817C;margin:18px 0 0">If you did not sign up, just ignore this email or call +1 (515) 588-5555.</p>';
      $e = null; @smtp_send($m, $em, $hy ? 'Բարի գալուստ Nectar of Armenia' : 'Welcome to Nectar of Armenia', mail_tpl($hy ? 'Բարի գալուստ 🌿' : 'Welcome 🌿', $inner, $hy ? 'hy' : 'en'), $e); } }
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
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); $xk = '__cexr|' . $ip; $xr = kv_get($db, $xk); $xv = $xr ? (json_decode($xr['v'], true) ?: []) : []; $xv = array_values(array_filter((array) $xv, function ($t) { return $t > now_ms() - 3600000; })); if (count($xv) >= 60) { out(['ok' => true, 'exists' => false]); } $xv[] = now_ms(); kv_put($db, $xk, json_encode($xv));
    $has = (bool) kv_get($db, '__cpw|' . $em); if (!$has) { $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; foreach ($A as $key => $x) { if (is_array($x) && strtolower((string) ($x['email'] ?? '')) === $em) { $has = true; break; } } }
    out(['ok' => true, 'exists' => $has]);
  case 'clogin': // customer: sign in with email + password on any device
    $b = body(); $em = strtolower(trim((string) ($b['em'] ?? ''))); $pw = (string) ($b['pw'] ?? '');
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); $fk = '__clfail|' . $ip; $fr = kv_get($db, $fk); $fv = $fr ? (json_decode($fr['v'], true) ?: []) : [];
    $fv = array_values(array_filter((array) $fv, function ($t) { return $t > now_ms() - 3600000; }));
    if (count($fv) >= 20) { out(['ok' => false, 'err' => 'rate'], 429); }
    $r = kv_get($db, '__cpw|' . $em); $rec = $r ? (json_decode($r['v'], true) ?: []) : [];
    if (!$rec) { $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; $has = false; foreach ($A as $key => $x) { if (is_array($x) && strtolower((string) ($x['email'] ?? '')) === $em) { $has = true; } } if ($has) { out(['ok' => false, 'err' => 'nopw']); } }
    if (!$rec || $pw === '' || !password_verify($pw, (string) ($rec['h'] ?? ''))) { $fv[] = now_ms(); kv_put($db, $fk, json_encode($fv)); sec_log($db, 'cust_login_fail', 15); sleep(1); out(['ok' => false, 'err' => 'bad'], 401); }
    $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; $mine = (array) ($rec['acc'] ?? []);
    foreach ($A as $key => $x) { if (is_array($x) && strtolower((string) ($x['email'] ?? '')) === $em) { $mine[strtolower((string) $key)] = $x; } }
    foreach ($mine as $key => $x) { $mine[$key]['tok'] = $rec['tok']; }
    out(['ok' => true, 'tok' => $rec['tok'], 'accounts' => $mine]);
  case 'admin_reset_req': // anyone: email a one-time admin reset link to the owner's notice mailbox (fixed in admin settings, never from the request)
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); $fk = '__arr|' . $ip; $fr = kv_get($db, $fk); $fv = $fr ? (json_decode($fr['v'], true) ?: []) : [];
    $fv = array_values(array_filter((array) $fv, function ($t) { return $t > now_ms() - 3600000; })); if (count($fv) >= 3) { out(['ok' => false, 'err' => 'rate'], 429); } $fv[] = now_ms(); kv_put($db, $fk, json_encode($fv));
    $m = mail_cfg($db); if (empty($m['user'])) { out(['ok' => false, 'err' => 'nomail']); } $TO = array_values(array_unique(array_filter(array_map('strtolower', array_map('trim', [(string) ($m['notify'] ?? ''), (string) ($m['user'] ?? ''), 'nectarofarmenia@gmail.com']))))); $to = $TO[0];
    $tok = bin2hex(random_bytes(24)); kv_put($db, '__adminrst', json_encode(['h' => hash('sha256', $tok), 'exp' => now_ms() + 86400000]));
    $https2 = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); $host2 = preg_replace('/[^a-z0-9.:-]/i', '', $_SERVER['HTTP_HOST'] ?? 'nectarofarmenia.com');
    $link = ($https2 ? 'https://' : 'http://') . $host2 . '/Admin.dc.html?areset=' . $tok;
    $e = null; $ok = false; $sent = []; foreach ($TO as $rcp) { if (smtp_send($m, $rcp, 'Admin password reset — Nectar of Armenia', mail_tpl('Admin password reset', '<p style="font-size:15px;margin:0 0 14px">Someone asked to reset the admin password of the Nectar of Armenia website. If it was you, open this link and choose a new password. The link works once, for 24 hours:</p><p style="margin:0 0 14px"><a href="' . mail_esc($link) . '" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#EBA93A;color:#133B30;font-weight:bold;text-decoration:none">Set a new admin password</a></p><p style="font-size:13px;color:#6B756F;margin:0">If it was not you, ignore this email. The current password keeps working.</p>'), $e)) { $ok = true; $sent[] = $rcp; } }
    $mask = implode(', ', array_map(function ($a) { $at = strpos($a, '@'); return $at > 2 ? substr($a, 0, 2) . str_repeat('•', max(1, $at - 2)) . substr($a, $at) : $a; }, $sent));
    out(['ok' => $ok, 'to' => $mask]);
  case 'admin_reset_do': // anyone holding a valid emailed token: set a new admin password
    $b = body(); $tok = (string) ($b['tok'] ?? ''); $pw = (string) ($b['pw'] ?? ''); $r = kv_get($db, '__adminrst'); $v = $r ? (json_decode($r['v'], true) ?: []) : [];
    if ($tok === '' || empty($v['h']) || (int) ($v['exp'] ?? 0) < now_ms() || !hash_equals((string) $v['h'], hash('sha256', $tok))) { sleep(1); out(['ok' => false, 'err' => 'token'], 400); }
    if (strlen($pw) < 8) { out(['ok' => false, 'err' => 'short'], 400); }
    kv_put($db, '__adminpw', password_hash($pw, PASSWORD_DEFAULT)); kv_put($db, '__adminrst', '{}');
    session_regenerate_id(true); $_SESSION['nofa_admin'] = 1; out(['ok' => true]);
  case 'push_key': // admin: VAPID public key for subscribing this device
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); } $v = vapid_keys($db); out(['ok' => (bool) $v, 'key' => $v ? $v['pub'] : '']);
  case 'push_sub': // admin: save this device's push subscription
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); } $b = body(); $ep = (string) ($b['endpoint'] ?? ''); if (!preg_match('#^https://[a-z0-9.-]+/#i', $ep)) { out(['ok' => false, 'err' => 'bad'], 400); }
    $S = json_decode((kv_get($db, '__pushsubs') ?: ['v' => '[]'])['v'], true) ?: []; $S = array_values(array_filter($S, function ($x) use ($ep) { return ($x['endpoint'] ?? '') !== $ep; })); $S[] = ['endpoint' => $ep, 'at' => now_ms(), 'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 120)]; kv_put($db, '__pushsubs', json_encode(array_slice($S, -10)));
    out(['ok' => true, 'n' => count($S)]);
  case 'push_test': // admin: send a test notification to all admin devices
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); } push_admin($db, '🔔 Ծանուցումները աշխատում են', 'Nectar of Armenia · ադմին'); out(['ok' => true]);
  case 'apri_ping': // anyone (Chef Apri app after a save): check Chef Apri now; reads with our own token, takes no input
    if (function_exists('fastcgi_finish_request')) { header('Content-Type: application/json'); echo '{"ok":true}'; fastcgi_finish_request(); try { apri_watch($db); } catch (Throwable $e) {} exit; }
    $w = []; try { $w = apri_watch($db); } catch (Throwable $e) {} out(['ok' => true] + ($isAdmin ? $w : []));
  case 'push_latest': // admin: text for the newest notification (used by the service worker)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); } $Q = json_decode((kv_get($db, '__pushq') ?: ['v' => '[]'])['v'], true) ?: []; out(['ok' => true, 'n' => $Q[0] ?? null, 'q' => array_slice($Q, 0, 6)]);
  case 'my_dlv': // customer: who received an own order, when, signature and photo
    $b = body(); $em = strtolower(trim((string) ($b['em'] ?? ''))); $tok = (string) ($b['tok'] ?? ''); $no = preg_replace('/[^A-Za-z0-9\-:]/', '', (string) ($b['no'] ?? ''));
    if ($no === '' || (!cust_rec($db, $em, 'biz', $tok) && !cust_rec($db, $em, 'retail', $tok))) { out(['ok' => false, 'err' => 'auth'], 401); }
    $own = false; $W = json_decode((kv_get($db, 'noa-ws-orders|' . $em) ?: ['v' => '[]'])['v'], true) ?: []; foreach ($W as $o) { if (is_array($o) && strcasecmp((string) ($o['no'] ?? ''), $no) === 0) { $own = true; break; } }
    if (!$own) { $pr = json_decode((kv_get($db, '__pay|' . $no) ?: ['v' => '{}'])['v'], true) ?: []; $own = strtolower((string) ($pr['email'] ?? '')) === $em; }
    if (!$own) { out(['ok' => false, 'err' => 'notyours'], 403); }
    $t = kv_get($db, '__apri_token'); if (!$t) { out(['ok' => true, 'none' => true]); }
    $r = apri_rpc('nofa_delivery_proof', ['p_token' => $t['v'], 'p_id' => $no], $code); $j = json_decode((string) $r, true);
    if (!is_array($j) || (empty($j['at']) && empty($j['sig']) && empty($j['photo']))) { out(['ok' => true, 'none' => true]); }
    $img = function ($x) { return (is_string($x) && preg_match('#^data:image/(jpeg|png);base64,[A-Za-z0-9+/=]+$#', $x)) ? $x : ''; };
    out(['ok' => true, 'at' => (string) ($j['at'] ?? ''), 'signer' => (string) ($j['signer'] ?? ''), 'sig' => $img($j['sig'] ?? ''), 'photo' => $img($j['photo'] ?? '')]);
  case 'admin_pw': // admin: set a new admin password (stored as a hash, server-only key)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 403); }
    $b = body(); $pw = (string) ($b['pw'] ?? '');
    if (strlen($pw) < 8) { out(['ok' => false, 'err' => 'short'], 400); }
    kv_put($db, '__adminpw', password_hash($pw, PASSWORD_DEFAULT)); out(['ok' => true]);
  case 'logout':
    $_SESSION = []; session_destroy(); out(['ok' => true]);
  case 'hit': // public: one page view for visitor statistics (aggregated by month; no IPs or personal data kept)
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''); if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|lighthouse|python|curl|wget/i', $ua)) { out(['ok' => true]); }
    $b = body(); $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); $hk = '__hitr|' . substr(hash('sha256', $ip), 0, 16); $hr = kv_get($db, $hk); $hv = $hr ? (json_decode($hr['v'], true) ?: []) : [];
    $hv = array_values(array_filter((array) $hv, function ($t) { return $t > time() - 3600; })); if (count($hv) >= 300) { out(['ok' => true]); } $hv[] = time(); kv_put($db, $hk, json_encode($hv));
    $pg = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($b['p'] ?? '')); $pg = substr($pg, 0, 40) ?: 'index.html'; if ($pg === 'Main.dc.html' || $pg === '') { $pg = 'index.html'; }
    $ref = strtolower(preg_replace('/[^A-Za-z0-9.-]/', '', (string) ($b['r'] ?? ''))); $ref = substr(preg_replace('/^www\./', '', $ref), 0, 60);
    $g = geo_ip($db, $ip); $tz = new DateTimeZone('America/Los_Angeles'); $now = new DateTime('now', $tz); $mk = '__stats|' . $now->format('Y-m'); $day = $now->format('j');
    $sr = kv_get($db, $mk); $st = $sr ? (json_decode($sr['v'], true) ?: []) : [];
    if (!empty($g['dc'])) { $st['bots'] = ($st['bots'] ?? 0) + 1; $bo = mb_substr((string) ($g['org'] ?? '?'), 0, 40) . ' · ' . ($g['ci'] ?? ''); if (!isset($st['bo']) || !is_array($st['bo'])) { $st['bo'] = []; } $st['bo'][$bo] = ($st['bo'][$bo] ?? 0) + 1; if (count($st['bo']) > 60) { arsort($st['bo']); $st['bo'] = array_slice($st['bo'], 0, 50, true); } kv_put($db, $mk, json_encode($st, JSON_UNESCAPED_UNICODE)); out(['ok' => true]); }
    $inc = function (&$arr, $k, $n = 1) { if ($k === '' ) { $k = '?'; } $arr[$k] = ($arr[$k] ?? 0) + $n; };
    foreach (['d', 'cc', 'cn', 'ci', 'p', 'r', 'dv', 'lg', 'pu'] as $f) { if (!isset($st[$f]) || !is_array($st[$f])) { $st[$f] = []; } }
    $new = !empty($b['n']);
    if (!isset($st['d'][$day])) { $st['d'][$day] = ['v' => 0, 'u' => 0]; } $st['d'][$day]['v']++; if ($new) { $st['d'][$day]['u']++; }
    $st['v'] = ($st['v'] ?? 0) + 1; if ($new) { $st['u'] = ($st['u'] ?? 0) + 1; }
    $cc = $g['cc'] ?: '?'; $inc($st['cc'], $cc); if ($g['c']) { $st['cn'][$cc] = $g['c']; }
    if ($g['ci']) { $inc($st['ci'], $g['ci'] . ($g['rg'] ? ', ' . $g['rg'] : '') . ' · ' . $cc); }
    $inc($st['p'], $pg); if ($new) { $inc($st['pu'], $pg); }
    if ($ref !== '' && $ref !== strtolower(preg_replace('/^www\./', '', (string) ($_SERVER['HTTP_HOST'] ?? '')))) { $inc($st['r'], $ref); } elseif ($new) { $inc($st['r'], '(direct)'); }
    $inc($st['dv'], !empty($b['m']) ? 'm' : 'd'); $lg = preg_replace('/[^a-z]/', '', strtolower(substr((string) ($b['l'] ?? ''), 0, 2))); $inc($st['lg'], $lg ?: '?');
    foreach (['ci', 'r'] as $f) { if (count($st[$f]) > 400) { arsort($st[$f]); $st[$f] = array_slice($st[$f], 0, 300, true); } }
    kv_put($db, $mk, json_encode($st, JSON_UNESCAPED_UNICODE)); out(['ok' => true]);
  case 'ux': // public: anonymous user-experience events (clicks, rage clicks, scroll depth, load time, JS errors) aggregated by month; no personal data
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''); if ($ua === '' || preg_match('/bot|crawl|spider|headless|lighthouse|python|curl|wget/i', $ua)) { out(['ok' => true]); }
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); $hk = '__uxr|' . substr(hash('sha256', $ip), 0, 16); $hr = kv_get($db, $hk); $hv = $hr ? (json_decode($hr['v'], true) ?: []) : [];
    $hv = array_values(array_filter((array) $hv, function ($t) { return $t > time() - 3600; })); if (count($hv) >= 120) { out(['ok' => true]); } $hv[] = time(); kv_put($db, $hk, json_encode($hv));
    $b = body(); $cl = function ($x, $n) { return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $x)), 0, $n); };
    $pg = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($b['p'] ?? '')); $pg = substr($pg, 0, 40) ?: 'index.html'; if ($pg === 'Main.dc.html') { $pg = 'index.html'; }
    $tz = new DateTimeZone('America/Los_Angeles'); $mk = '__ux|' . (new DateTime('now', $tz))->format('Y-m'); $ur = kv_get($db, $mk); $U = $ur ? (json_decode($ur['v'], true) ?: []) : [];
    if (!isset($U[$pg]) || !is_array($U[$pg])) { $U[$pg] = ['n' => 0, 'c' => [], 'rage' => [], 'sd' => [0, 0, 0, 0], 'lt' => [0, 0, 0, 0], 'm' => 0]; } $P = &$U[$pg]; $P['n']++; if (!empty($b['m'])) { $P['m'] = ($P['m'] ?? 0) + 1; }
    foreach (array_slice((array) ($b['c'] ?? []), 0, 30) as $c) { $k = $cl($c, 50); if ($k !== '') { $P['c'][$k] = ($P['c'][$k] ?? 0) + 1; } }
    foreach (array_slice((array) ($b['rg'] ?? []), 0, 10) as $c) { $k = $cl($c, 50); if ($k !== '') { $P['rage'][$k] = ($P['rage'][$k] ?? 0) + 1; } }
    $sd = (int) ($b['sd'] ?? 0); $P['sd'][$sd >= 90 ? 3 : ($sd >= 50 ? 2 : ($sd >= 25 ? 1 : 0))]++;
    $lt = (int) ($b['lt'] ?? 0); if ($lt > 0) { $P['lt'][$lt < 1500 ? 0 : ($lt < 3000 ? 1 : ($lt < 6000 ? 2 : 3))]++; }
    foreach (['c', 'rage'] as $f) { if (count($P[$f]) > 150) { arsort($P[$f]); $P[$f] = array_slice($P[$f], 0, 120, true); } }
    unset($P); kv_put($db, $mk, json_encode($U, JSON_UNESCAPED_UNICODE));
    $errs = array_slice((array) ($b['e'] ?? []), 0, 5); if ($errs) { $er = kv_get($db, '__jserr'); $E = $er ? (json_decode($er['v'], true) ?: []) : [];
      foreach ($errs as $e0) { $msg = $cl(is_array($e0) ? ($e0['m'] ?? '') : $e0, 200); if ($msg === '') { continue; } $key = md5($pg . $msg); if (isset($E[$key])) { $E[$key]['n']++; $E[$key]['last'] = gmdate('c'); } else { $E[$key] = ['p' => $pg, 'm' => $msg, 's' => $cl(is_array($e0) ? ($e0['s'] ?? '') : '', 80), 'n' => 1, 'first' => gmdate('c'), 'last' => gmdate('c'), 'ua' => $cl($ua, 90)]; } }
      if (count($E) > 200) { uasort($E, function ($x, $y) { return strcmp($y['last'], $x['last']); }); $E = array_slice($E, 0, 150, true); } kv_put($db, '__jserr', json_encode($E, JSON_UNESCAPED_UNICODE)); }
    out(['ok' => true]);
  case 'agent_feed': // site agent (key): read-only business + technical snapshot, customer personal data masked
  case 'agent_post': // site agent (key): add a report / proposal / question to the owner's agent thread
    $AGH = '9cd31ccb3f827c86107a109ba384bd2ccf20f0b0980431d197c23c92266240bb'; $AGA = 'c07c120d8154cadce353c8cd33a8a574f467da61f9768e1b8724706fa81da197'; $ak = (string) ($_SERVER['HTTP_X_AGENT_KEY'] ?? ($_GET['k'] ?? '')); $agFrom = (strlen($ak) >= 20 && $a === 'agent_post' && hash_equals($AGA, hash('sha256', $ak))) ? 'apri' : 'nectar'; if (strlen($ak) < 20 || ($agFrom === 'nectar' && !hash_equals($AGH, hash('sha256', $ak)))) { sec_log($db, 'agent_auth_fail', 10); usleep(400000); out(['ok' => false, 'err' => 'auth'], 401); }
    $J = function ($k, $d) use ($db) { $r = kv_get($db, $k); return $r ? (json_decode($r['v'], true) ?? $d) : $d; };
    if ($a === 'agent_feed') { try { apri_watch($db); } catch (Throwable $e) {} }
    if ($a === 'agent_feed' && ($_GET['diag'] ?? '') === 'err') { out(json_decode((kv_get($db, '__phperr') ?: ['v' => '[]'])['v'], true)); }
    if ($a === 'agent_feed' && ($_GET['diag'] ?? '') === 'acc') { $ra = kv_get($db, 'noa-accounts'); $A0 = json_decode($ra['v'] ?? '{}', true) ?: []; $o = []; foreach ($A0 as $k => $r) { $o[] = [$k, $r['type'] ?? '', $r['status'] ?? '', $r['biz'] ?? '', isset($r['email']) ? 'em' : 'NOEMAIL']; } out(['t' => $ra['t'] ?? null, 'len' => strlen($ra['v'] ?? ''), 'acc' => $o, 'gone' => json_decode((kv_get($db, '__acc_gone') ?: ['v' => '[]'])['v'], true)]); }
    if ($a === 'agent_feed' && ($_GET['diag'] ?? '') === 'sync') { $q = $db->query("SELECT k, v, t FROM nofa_kv"); $bad = []; $tot = 0; $big = []; foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { if (substr($r['k'], 0, 2) === '__') { continue; } $tot += strlen($r['v']); if (json_encode(['v' => $r['v']]) === false) { $bad[] = $r['k'] . ' : ' . json_last_error_msg(); } if (strlen($r['v']) > 200000) { $big[$r['k']] = strlen($r['v']); } } $ib = $db->query('SELECT id, kind, em, data, t FROM nofa_inbox ORDER BY id LIMIT 500')->fetchAll(PDO::FETCH_ASSOC); $ibad = []; foreach ($ib as $r) { if (json_encode($r) === false) { $ibad[] = $r['id']; } } $pend = []; foreach ($ib as $r) { $d0 = json_decode($r['data'], true); $pend[] = [$r['id'], $r['kind'], substr((string) ($d0['k'] ?? ''), 0, 40), strlen($r['data'])]; } out(['ok' => true, 'bytes' => $tot, 'bad' => $bad, 'big' => $big, 'mem' => ini_get('memory_limit'), 'inbox_n' => count($ib), 'inbox_bad' => $ibad, 'inbox' => array_slice($pend, 0, 40), 'keys' => array_values(array_filter(array_map(function ($r) { return $r['k']; }, $db->query('SELECT k FROM nofa_kv')->fetchAll(PDO::FETCH_ASSOC)), function ($k) { return substr($k, 0, 2) !== '__'; }))]); }
    if ($a === 'agent_post') { $b = body(); $T = $J('__agent_thread', []); $n = 0;
      $seenId = []; foreach ((array) $T as $x0) { if (is_array($x0) && isset($x0['id'])) { $seenId[(string) $x0['id']] = 1; } } foreach (array_slice((array) ($b['msgs'] ?? [$b]), 0, 20) as $m) { $ty = in_array(($m['type'] ?? ''), ['report', 'proposal', 'question', 'done', 'alert', 'delegate'], true) ? $m['type'] : 'report'; $to = in_array(($m['to'] ?? ''), ['apri', 'nectar'], true) ? $m['to'] : ''; if ($ty === 'delegate') { if ($agFrom !== 'nectar') { $ty = 'report'; } else { $to = 'apri'; $m['notify'] = false; } } $ttl = mb_substr(trim((string) ($m['title'] ?? '')), 0, 160); $bd = mb_substr(trim((string) ($m['body'] ?? '')), 0, 8000); if ($ttl === '' && $bd === '') { continue; }
        do { $id = 'a' . now_ms() . random_int(10, 99); } while (isset($seenId[$id])); $seenId[$id] = 1; $T[] = ['id' => $id, 'from' => 'agent', 'ag' => $agFrom, 'type' => $ty, 'title' => $ttl, 'body' => $bd, 'ref' => mb_substr((string) ($m['ref'] ?? ''), 0, 40), 'prio' => in_array(($m['prio'] ?? ''), ['high', 'med', 'low'], true) ? $m['prio'] : 'med', 'at' => gmdate('c'), 'st' => $ty === 'proposal' || $ty === 'question' ? 'open' : ($ty === 'delegate' ? 'todo' : 'info')] + ($to !== '' ? ['to' => $to] : []); $n++;
        if (($m['notify'] ?? true) !== false) { $ic = ['alert' => '🚨', 'proposal' => '💡', 'question' => '❓', 'done' => '✅', 'report' => '📋'][$ty]; push_admin($db, $ic . ' ' . ($agFrom === 'apri' ? 'Chef Apri ագենտ' : 'Nectar ագենտ'), ($ttl !== '' ? $ttl . ($bd !== '' ? ' — ' : '') : '') . mb_substr(preg_replace('/\s+/u', ' ', $bd), 0, 150), 'Admin.dc.html#agent'); } }
      if (count($T) > 400) { $T = array_slice($T, -400); } kv_put($db, '__agent_thread', json_encode($T, JSON_UNESCAPED_UNICODE)); out(['ok' => true, 'n' => $n]); }
    @ini_set('memory_limit', '512M'); set_error_handler(function ($no, $str, $file, $line) { if (!(error_reporting() & $no)) { return false; } throw new ErrorException($str, 0, $no, $file, $line); }, E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
    try {
    $since = time() - 86400 * 62; $tz = new DateTimeZone('America/Los_Angeles'); $m0 = (new DateTime('now', $tz))->format('Y-m'); $m1 = (new DateTime('first day of last month', $tz))->format('Y-m');
    $num = function ($x) { return (float) preg_replace('/[^0-9.\-]/', '', (string) $x); }; $ts = function ($o) { foreach (['at', 'd', 'created', 'createdAt', 'day', 'date'] as $f) { if (!empty($o[$f])) { $t = is_numeric($o[$f]) ? ((float) $o[$f] > 1e11 ? (float) $o[$f] / 1000 : (float) $o[$f]) : strtotime((string) $o[$f]); if ($t) { return (int) $t; } } } return 0; };
    $ret = []; foreach ((array) $J('noa-orders', []) as $o) { if (!is_array($o)) { continue; } $t = $ts($o); if ($t && $t < $since) { continue; } $ret[] = ['no' => $o['no'] ?? '', 'when' => $t ? gmdate('Y-m-d', $t) : '', 'total' => $num($o['total'] ?? 0), 'st' => $o['st'] ?? ($o['status'] ?? ''), 'paid' => !empty($o['paid']) || !empty($o['paidAt']), 'pay' => $o['pay'] ?? ($o['pm'] ?? ''), 'city' => $o['city'] ?? '', 'items' => $o['items'] ?? ($o['lines'] ?? null), 'del' => $o['del'] ?? ($o['delivery'] ?? '')]; }
    $ws = []; $q = $db->query("SELECT k, v FROM nofa_kv WHERE k LIKE 'noa-ws-orders|%'"); $ACC = $J('noa-accounts', []); $bizOf = function ($em) use ($ACC) { foreach ((array) $ACC as $k => $r) { if (is_array($r) && strtolower((string) ($r['email'] ?? '')) === $em && !empty($r['biz'])) { return $r['biz']; } } return 'store-' . substr(md5($em), 0, 6); };
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { $em = strtolower(substr($r['k'], 14)); $biz = $bizOf($em); foreach ((array) (json_decode($r['v'], true) ?: []) as $o) { if (!is_array($o)) { continue; } $t = $ts($o); if ($t && $t < $since) { continue; } $ws[] = ['store' => $biz, 'no' => $o['no'] ?? '', 'when' => $t ? gmdate('Y-m-d', $t) : '', 'day' => $o['reqDate'] ?? '', 'amt' => $num($o['amt'] ?? 0), 'ap' => $o['ap'] ?? '', 'st' => $o['st'] ?? 0, 'paid' => !empty($o['paid']), 'payDue' => !empty($o['payDue']) || !empty($o['auto']), 'pm' => $o['pm'] ?? '', 'zelle' => !empty($o['zelleAt']), 'rush' => !empty($o['rush']), 'items' => $o['items'] ?? null]; } }
    $inv = []; foreach ((array) $J('noa-inv2', []) as $iv) { if (!is_array($iv)) { continue; } $inv[] = ['no' => $iv['no'] ?? ($iv['id'] ?? ''), 'store' => $iv['c']['n'] ?? ($iv['c']['biz'] ?? ''), 'date' => $iv['d'] ?? ($iv['date'] ?? ''), 'total' => $num($iv['total'] ?? ($iv['t'] ?? 0)), 'paid' => $num($iv['paid'] ?? 0), 'st' => $iv['st'] ?? '']; } $inv = array_slice($inv, -150);
    $pays = []; $q = $db->query("SELECT k, v, t FROM nofa_kv WHERE SUBSTR(k, 1, 6) = '__pay|' ORDER BY t DESC LIMIT 80"); $PW = $J('__paidweb', []); foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { $v = json_decode($r['v'], true) ?: []; $ref = substr($r['k'], 6); $pays[] = ['ref' => $ref, 'amt' => $v['amt'] ?? 0, 'm' => $v['m'] ?? 'Card', 'when' => gmdate('Y-m-d H:i', (int) ($r['t'] / 1000)), 'paid' => !empty($PW[$ref])]; }
    $zl = []; $q = $db->query("SELECT k, v FROM nofa_kv WHERE SUBSTR(k, 1, 8) = '__zelle|'"); foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { $v = json_decode($r['v'], true) ?: []; $zl[] = ['orders' => substr($r['k'], 8), 'amt' => $v['amt'] ?? 0, 'from' => $v['from'] ?? '', 'when' => gmdate('Y-m-d H:i', (int) (($v['t'] ?? 0) / 1000))]; }
    $prod = $J('noa-prod', []); $names = []; foreach ((array) $prod as $i => $p) { $names[$i] = is_array($p) ? ($p['n'] ?? ($p['name'] ?? ($p['en'] ?? ('#' . $i)))) : ('#' . $i); }
    $acc = ['retail' => 0, 'biz' => 0, 'biz_pending' => 0, 'invited_not_joined' => 0]; foreach ((array) $ACC as $r) { if (!is_array($r)) { continue; } if (($r['type'] ?? '') === 'biz') { $acc['biz']++; if (($r['status'] ?? '') === 'pending') { $acc['biz_pending']++; } } else { $acc['retail']++; } if (!empty($r['invitedAt']) && empty($r['joinedAt'])) { $acc['invited_not_joined']++; } }
    $T = $J('__agent_thread', []);
    echo json_encode(['ok' => true, 'now' => gmdate('c'), 'site' => ['php' => PHP_VERSION, 'kv' => (int) $db->query('SELECT COUNT(*) FROM nofa_kv')->fetchColumn()],
      'visitors' => [$m1 => $J('__stats|' . $m1, null), $m0 => $J('__stats|' . $m0, null)], 'ux' => [$m1 => $J('__ux|' . $m1, null), $m0 => $J('__ux|' . $m0, null)], 'js_errors' => array_values($J('__jserr', [])),
      'retail_orders' => $ret, 'wholesale_orders' => $ws, 'invoices' => $inv, 'payments' => $pays, 'zelle_claims' => $zl,
      'stock' => ['names' => $names, 'packs' => $J('noa-stock', null), 'pcs' => $J('noa-stock-pcs', null), 'soldout' => $J('noa-soldout', null), 'lots' => array_slice((array) $J('noa-apri-lots', []), -40)],
      'prices' => array_map(function ($p) { return ['id' => $p['id'] ?? '', 'pr' => $p['pr'] ?? null]; }, (array) ($J('noa-cost-v2', [])['prod'] ?? [])), 'accounts' => $acc, 'customers_index' => (function () use ($J, $ACC) { $cv = $J('noa-cost-v2', []); $w = []; $r = 0; $src = [];
      foreach ((array) ($cv['cprof'] ?? []) as $k => $q) { if (substr((string) $k, -2) === '|w') { $w[substr((string) $k, 0, -2)][] = 'cost-profile'; } }
      foreach ((array) ($cv['calls'] ?? []) as $c) { if (!is_array($c)) { continue; } if (($c['type'] ?? '') === 'w') { $w[mb_strtolower(trim((string) ($c['name'] ?? '?')))][] = 'phone-order'; } else { $r++; } }
      foreach ((array) ($cv['cust'] ?? ($cv['customers'] ?? [])) as $c) { if (is_array($c)) { $w[mb_strtolower(trim((string) ($c['n'] ?? ($c['name'] ?? '?'))))][] = 'cost-customer-list'; } }
      foreach ((array) $J('noa-inv2', []) as $iv) { if (is_array($iv) && is_array($iv['c'] ?? null)) { $w[mb_strtolower(trim((string) ($iv['c']['n'] ?? ($iv['c']['biz'] ?? '?'))))][] = 'invoice'; } }
      foreach ((array) $ACC as $k => $a0) { if (is_array($a0) && ($a0['type'] ?? '') === 'biz') { $w[mb_strtolower(trim((string) ($a0['biz'] ?? '?')))][] = 'account'; } }
      $out = []; foreach ($w as $n => $ss) { $out[$n] = array_count_values($ss); } ksort($out); return ['wholesale' => $out, 'retail_phone_orders' => $r, 'cost_keys' => array_keys((array) $cv), 'kv_keys_customers' => null]; })(), 'security' => $J('__sec', []), 'apri' => (function () use ($db) { $t = kv_get($db, '__apri_token'); if (!$t) { return ['ok' => false, 'err' => 'no_token']; } $code = 0; $r = apri_rpc('nofa_production', ['p_token' => $t['v'], 'p_from' => date('Y-m-d', time() - 21 * 86400), 'p_to' => date('Y-m-d', time() + 86400)], $code); $rows = json_decode((string) $r, true); $c2 = 0; $r2 = apri_rpc('nofa_stock', ['p_token' => $t['v']], $c2); $sk = json_decode((string) $r2, true); return ['ok' => is_array($rows) && $code < 400, 'code' => $code, 'production_approved' => is_array($rows) ? $rows : substr((string) $r, 0, 300), 'stock' => is_array($sk) ? $sk : substr((string) $r2, 0, 300)]; })(), 'apri_sync_state' => ['prod_log_tail' => array_slice((array) $J('noa-prod', []), -15)],
      'agent_thread' => array_slice((array) $T, -60)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR); exit;
    } catch (Throwable $e) { out(['ok' => false, 'err' => 'feed', 'msg' => $e->getMessage(), 'line' => $e->getLine()], 500); }
  case 'agent_list': // admin: the agent thread
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $r = kv_get($db, '__agent_thread'); out(['ok' => true, 'thread' => $r ? (json_decode($r['v'], true) ?: []) : []]);
  case 'agent_reply': // admin: approve / reject / comment on an agent message, or write a new task
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $b = body(); $r = kv_get($db, '__agent_thread'); $T = $r ? (json_decode($r['v'], true) ?: []) : []; $txt = mb_substr(trim((string) ($b['text'] ?? '')), 0, 4000); $dec = in_array(($b['decision'] ?? ''), ['approve', 'reject', 'comment', 'task', 'readall'], true) ? $b['decision'] : 'comment'; $to = (string) ($b['id'] ?? '');
    if ($dec === 'readall') { foreach ($T as &$m) { if (($m['from'] ?? '') === 'agent' && ($m['st'] ?? '') === 'open') { $m['st'] = 'seen'; $m['seenAt'] = gmdate('c'); } } unset($m); kv_put($db, '__agent_thread', json_encode($T, JSON_UNESCAPED_UNICODE)); out(['ok' => true, 'thread' => $T]); }
    if ($to !== '') { foreach ($T as &$m) { if (($m['id'] ?? '') === $to) { if ($dec === 'approve') { $m['st'] = 'approved'; } elseif ($dec === 'reject') { $m['st'] = 'rejected'; } elseif ($dec === 'comment' && $txt !== '' && ($m['st'] ?? '') === 'open') { $m['st'] = 'answered'; } $m['decAt'] = gmdate('c'); } } unset($m); }
    if ($txt !== '' || $dec !== 'comment') { $T[] = ['id' => 'o' . now_ms() . random_int(10, 99), 'from' => 'owner', 'type' => $dec, 're' => $to, 'body' => $txt, 'at' => gmdate('c'), 'st' => $dec === 'task' ? 'open' : 'info']; }
    if (count($T) > 400) { $T = array_slice($T, -400); } kv_put($db, '__agent_thread', json_encode($T, JSON_UNESCAPED_UNICODE)); out(['ok' => true, 'thread' => $T]);
  case 'stats': // admin: visitor statistics by month
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $q = $db->query("SELECT k, v FROM nofa_kv WHERE SUBSTR(k, 1, 8) = '__stats|'"); $res = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { $res[substr($r['k'], 8)] = json_decode($r['v'], true); }
    ksort($res); out(['ok' => true, 'months' => $res]);
  case 'pay_rec': // admin: the server copy of a card order (to restore an order removed from the admin list by mistake)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $no = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($_GET['no'] ?? '')); $r = kv_get($db, '__pay|' . $no); if (!$r) { out(['ok' => false, 'err' => 'none']); }
    $v = json_decode($r['v'], true) ?: []; $pw = json_decode((kv_get($db, '__paidweb') ?: ['v' => '{}'])['v'], true) ?: [];
    unset($v['sid'], $v['site']); out(['ok' => true, 'rec' => $v, 'paid' => isset($pw[$no]) || !empty($v['paid'])]);
  case 'all': // admin: everything
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $since = (int) ($_GET['since'] ?? 0);
    $s = $db->prepare("SELECT k, v, t FROM nofa_kv WHERE t > ? AND SUBSTR(k, 1, 2) <> '__'"); $s->execute([$since]);
    $res = []; $mx = $since; foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $res[$r['k']] = ['v' => $r['v'], 't' => (int) $r['t']]; if ($r['t'] > $mx) { $mx = (int) $r['t']; } }
    if (!isset($res['noa-accounts'])) { $ra = kv_get($db, 'noa-accounts'); if ($ra) { $res['noa-accounts'] = ['v' => $ra['v'], 't' => (int) $ra['t']]; } } // always send the customer list so every admin browser converges
    $n = (int) $db->query("SELECT COUNT(*) FROM nofa_kv WHERE SUBSTR(k, 1, 2) <> '__'")->fetchColumn();
    out(['ok' => true, 'kv' => $res, 'now' => $mx, 'count' => $n]);
  case 'put': // admin: save keys
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $b = body(); $done = []; $merged = [];
    foreach (($b['items'] ?? []) as $it) {
      $k = (string) ($it['k'] ?? ''); if (!preg_match($SHARED, $k)) { continue; }
      $v = $it['v'] ?? null;
      if (!empty($it['backup'])) { $old = kv_get($db, $k); if ($old && $old['v'] !== $v) { $s = $db->prepare('INSERT INTO nofa_backup (k, v, t) VALUES (?, ?, ?)'); $s->execute([$k, $old['v'], now_ms()]); } }
      if ($v === null) { $db->prepare('DELETE FROM nofa_kv WHERE k = ?')->execute([$k]); $done[$k] = now_ms(); }
      else { $v0 = (string) $v; if ($k === 'noa-accounts') { $v = acc_merge($db, $v0); if ($v !== $v0) { $merged[$k] = $v; } } $done[$k] = kv_put($db, $k, (string) $v); }
    }
    if (array_intersect(array_keys($done), ['noa-stock', 'noa-stock-pcs', 'noa-cost-v2'])) { try { apri_stock_sync($db); } catch (Throwable $e) {} }
    out(['ok' => true, 't' => $done, 'v' => $merged]);
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
    $n = 0; $ev = [];
    foreach (array_slice($items, 0, 40) as $it) {
      $kk0 = (string) ($it['k'] ?? ''); if (!preg_match($em !== '' ? '/^noa-(accounts|orders|(ws-orders|chat|members|deposit|depsign|rewards)\|.+)$/' : '/^noa-orders$/', $kk0)) { $GLOBALS['__secrej'] = ($GLOBALS['__secrej'] ?? 0) + 1; continue; }
      $kind = substr((string) ($it['kind'] ?? 'key'), 0, 20); $data = json_encode(['k' => $it['k'] ?? '', 'v' => $it['v'] ?? null, 'tok' => $tok], JSON_UNESCAPED_UNICODE);
      if (strlen($data) > 400000) { continue; }
      $ins->execute([$kind, $em, hash('sha256', $em . '|' . $data), $data, $ip, now_ms()]); if ($ins->rowCount() > 0) { $n++; $kk = (string) ($it['k'] ?? ''); if (preg_match('/^noa-(ws-orders|chat|accounts|orders|city-req|deposit|depsign|rewards)/', $kk, $mm)) { $ev[$mm[1]] = 1; if ($mm[1] === 'ws-orders' && $em !== '') { try { pn_wso($db, $em, $it['v'] ?? null); } catch (Throwable $e) {} unset($ev['ws-orders']); } elseif ($mm[1] === 'orders') { try { pn_retail($db, $em, $it['v'] ?? null); } catch (Throwable $e) {} unset($ev['orders']); } } }
    }
    if (!empty($GLOBALS['__secrej'])) { sec_log($db, 'submit_reject', 3); }
    if (!empty($ev)) { $TT = ['ws-orders' => ['🏪 Մեծածախ պատվեր', 'Նոր կամ թարմացված մեծածախ պատվեր'], 'orders' => ['🛒 Նոր պատվեր', 'Նոր մանրածախ պատվեր'], 'chat' => ['💬 Նոր նամակ', 'Հաճախորդը գրել է ձեզ'], 'accounts' => ['👤 Նոր գրանցում', 'Նոր կամ թարմացված հաճախորդի հաշիվ'], 'city-req' => ['📍 Նոր քաղաքի հայտ', 'Հաճախորդը առաջարկել է քաղաք'], 'deposit' => ['🤝 Դեպոզիտ', 'Դեպոզիտի թարմացում'], 'depsign' => ['✍️ Ստորագրություն', 'Խանութը ստորագրել է'], 'rewards' => ['🎁 Անվճար տուփի հայտ', 'Հաճախորդը ընտրել է անվճար տուփ']];
      $lk = json_decode((kv_get($db, '__pushlast') ?: ['v' => '{}'])['v'], true) ?: []; foreach (array_keys($ev) as $e1) { if (now_ms() - (int) ($lk[$e1 . '|' . $em] ?? 0) < 60000) { continue; } $lk[$e1 . '|' . $em] = now_ms(); push_admin($db, mb_substr($TT[$e1][0], 0, 2) . ' ' . ($em !== '' ? pn_who($db, $em) : 'Nectar of Armenia'), $TT[$e1][1]); } kv_put($db, '__pushlast', json_encode($lk)); }
    out(['ok' => true, 'n' => $n]);
  case 'apri_cfg': // admin: save Chef Apri link token (stored server-side only)
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $b = body(); $tok = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($b['token'] ?? ''));
    if (strlen($tok) < 16) { out(['ok' => false, 'err' => 'bad_token'], 400); }
    kv_put($db, '__apri_token', $tok); out(['ok' => true]);
  case 'apri_pull': // admin: approved production from Chef Apri (sums only)
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
  case 'apri_drivers': // admin: drivers registered in Chef Apri (names only)
  case 'apri_push':    // admin: open stops for Chef Apri drivers (full snapshot)
  case 'apri_dstatus': // admin: delivery results since a time
  case 'apri_proof':   // admin: photo + signature of one stop
  case 'apri_routes':  // admin: drivers' planned routes (order, miles, times, earnings)
  case 'apri_stock':   // admin: warehouse from Chef Apri (made, packed, lots with expiry)
  case 'apri_stock_push': // admin: this site's warehouse (packs + loose pieces) to Chef Apri
  case 'apri_cost_push':  // admin: cost sheet summary (cost per piece/pack, price list, recipe cost) to Chef Apri (admin-only there)
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
    $P = [22.00, 19.95, 15.95, 60.00, 24.95, 24.95, 34.95, 34.95, 20.95, 0, 0]; $ids = ['gata', 'khash', 'khbroth', 'khset', 'khinkali', 'pelmeni', 'blinchik', 'kufta', 'kiev', 'chcut', 'beefcut'];
    $N = ['Gata', 'Khash', 'Khash Broth', 'Khash Set', 'Khinkali', 'Pelmeni', 'Blinchik', 'Mijukov Kufta', 'Chicken Kiev', 'Chicken Cutlets', 'Beef Cutlets'];
    $cr = kv_get($db, 'noa-cost-v2'); if ($cr) { $cv = json_decode($cr['v'], true) ?: []; foreach (($cv['prod'] ?? []) as $pd) { $k = array_search((string) ($pd['id'] ?? ''), $ids, true); $v = (float) (($pd['pr'] ?? [])[0] ?? 0); if ($k !== false && $v > 0) { $P[$k] = round($v, 2); } } }
    $lines = []; $mlines = []; $sub = 0; $form = ['mode' => 'payment', 'client_reference_id' => $no, 'metadata[order]' => $no, 'payment_intent_data[metadata][order]' => $no, 'payment_intent_data[statement_descriptor_suffix]' => substr($no, -10)];
    $SO = json_decode((kv_get($db, 'noa-soldout') ?: ['v' => '[]'])['v'], true) ?: []; foreach ((array) ($b['items'] ?? []) as $it) { $pi0 = (int) ($it[0] ?? -1); if ($pi0 >= 0 && !empty($SO[$pi0]) && (int) ($it[1] ?? 0) > 0) { out(['ok' => false, 'err' => 'soldout', 'i' => $pi0]); } }
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
    $host = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'nectarofarmenia.com'); $base = $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/api.php')), '/');
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
    if (($paid || ($j['status'] ?? '') === 'complete') && strpos($no, 'WSO:') === 0) { $pw0 = json_decode((kv_get($db, '__pay|' . $no) ?: ['v' => '{}'])['v'], true) ?: []; $wem = strtolower((string) ($pw0['email'] ?? '')); if ($wem !== '') { $ok0 = 'noa-ws-orders|' . $wem; $RO = json_decode((kv_get($db, $ok0) ?: ['v' => '[]'])['v'], true) ?: []; $chg = false; foreach ($RO as $i0 => $o0) { if (is_array($o0) && in_array((string) ($o0['no'] ?? ''), (array) ($pw0['nos'] ?? []), true) && empty($o0['paid'])) { if ($paid) { $RO[$i0]['paid'] = true; $RO[$i0]['paidAt'] = gmdate('c'); unset($RO[$i0]['payDue']); } else { $RO[$i0]['payProc'] = true; } $RO[$i0]['pm'] = (string) ($pw0['m'] ?? 'Card'); $chg = true; $RO[$i0]['u'] = now_ms(); } } if ($chg) { kv_put($db, $ok0, json_encode($RO, JSON_UNESCAPED_UNICODE)); } } }
    out(['ok' => true, 'wso' => strpos($no, 'WSO:') === 0, 'paid' => $paid, 'processing' => !$paid && ($j['status'] ?? '') === 'complete', 'no' => $no, 'amt' => ((int) ($j['amount_total'] ?? 0)) / 100]);
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
      $host = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'nectarofarmenia.com'); $base = $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/api.php')), '/'); $link = $base . '/Account.dc.html?reset=' . $t;
      smtp_send($m, $em, 'Reset your password — Nectar of Armenia', mail_tpl('Reset your password', '<p style="font-size:15px;margin:0 0 16px">Press the button to choose a new password. The link works for 1 hour.<br><span style="color:#6B756F">Սեղմեք կոճակը՝ նոր գաղտնաբառ ընտրելու համար։ Հղումը գործում է 1 ժամ։</span></p><p style="margin:0"><a href="' . mail_esc($link) . '" style="display:inline-block;background:#EBA93A;color:#133B30;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:999px">Choose a new password</a></p><p style="margin:16px 0 0;color:#6B756F;font-size:13px">If you did not ask for this, you can ignore this email.</p>')); }
    out(['ok' => true]);
  case 'pw_reset_do':
    $b = body(); $t = preg_replace('/[^a-f0-9]/', '', (string) ($b['t'] ?? '')); $pw = (string) ($b['pw'] ?? ''); if ($t === '' || strlen($pw) < 8 || strlen($pw) > 200) { out(['ok' => false, 'err' => 'bad']); }
    $r = kv_get($db, '__pwr|' . $t); $v = $r ? (json_decode($r['v'], true) ?: []) : []; if (!$v || now_ms() - (int) ($v['t'] ?? 0) > (!empty($v['inv']) ? 1209600000 : 3600000)) { out(['ok' => false, 'err' => 'expired']); }
    $em = (string) $v['em']; $k = '__cpw|' . $em; $old = kv_get($db, $k); $old = $old ? (json_decode($old['v'], true) ?: []) : []; $tok = (string) ($old['tok'] ?? ''); $acc = (array) ($old['acc'] ?? []);
    if ($tok === '') { $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; foreach ($A as $key => $x) { if (is_array($x) && strtolower((string) ($x['email'] ?? '')) === $em) { if (!empty($x['tok'])) { $tok = (string) $x['tok']; } $acc[strtolower((string) $key)] = $x; } } }
    if ($tok === '') { $tok = bin2hex(random_bytes(16)); }
    kv_put($db, $k, json_encode(['h' => password_hash($pw, PASSWORD_DEFAULT), 'tok' => $tok, 'acc' => $acc, 't' => now_ms()], JSON_UNESCAPED_UNICODE)); kv_put($db, '__pwr|' . $t, json_encode(['used' => now_ms()]));
    if (!empty($v['inv'])) { $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; $chg = false; foreach ($A as $key => $x) { if (is_array($x) && strtolower((string) ($x['email'] ?? '')) === $em && empty($x['joinedAt'])) { $A[$key]['joinedAt'] = gmdate('c'); $chg = true; } } if ($chg) { kv_put($db, 'noa-accounts', json_encode($A, JSON_UNESCAPED_UNICODE)); } }
    out(['ok' => true, 'em' => $em, 'inv' => !empty($v['inv'])]);
  case 'cinvite': // admin: create (or update) a customer account from the owner's data and make a one-time invite link for the customer to take it over
    if (!$isAdmin) { out(['ok' => false, 'err' => 'auth'], 401); }
    $r0 = cinvite_do($db, body(), $https); $c0 = (int) ($r0['_code'] ?? 200); unset($r0['_code']); out($r0, $c0);
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
    $host = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'nectarofarmenia.com'); $base0 = $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/api.php')), '/');
    $form = ['mode' => 'payment', 'client_reference_id' => 'INV:' . $id, 'metadata[invoice]' => $no, 'payment_intent_data[metadata][invoice]' => $no, 'line_items[0][price_data][currency]' => 'usd', 'line_items[0][price_data][product_data][name]' => 'Invoice #' . $no . ' — Nectar of Armenia Inc', 'line_items[0][price_data][unit_amount]' => (int) round($bal * 100), 'line_items[0][quantity]' => 1, 'payment_method_types[0]' => $ach ? 'us_bank_account' : 'card', 'submit_type' => 'pay', 'success_url' => $base0 . '/PartnerAccount.dc.html?invpaid={CHECKOUT_SESSION_ID}#invoices', 'cancel_url' => $base0 . '/PartnerAccount.dc.html#invoices'];
    if (!empty($b['embed'])) { unset($form['success_url'], $form['cancel_url']); $form['ui_mode'] = 'embedded'; $form['return_url'] = $base0 . '/PartnerAccount.dc.html?invpaid={CHECKOUT_SESSION_ID}#invoices'; }
    if ($fee > 0) { $form['line_items[1][price_data][currency]'] = 'usd'; $form['line_items[1][price_data][product_data][name]'] = 'Card processing fee (3%)'; $form['line_items[1][price_data][unit_amount]'] = (int) round($fee * 100); $form['line_items[1][quantity]'] = 1; }
    if (filter_var($em, FILTER_VALIDATE_EMAIL)) { $form['customer_email'] = $em; }
    $r = stripe_api($sk, 'POST', 'checkout/sessions', $form, $code); $j = json_decode((string) $r, true) ?: [];
    if ($code !== 200 || (empty($j['url']) && empty($j['client_secret']))) { out(['ok' => false, 'err' => 'stripe', 'msg' => $j['error']['message'] ?? '']); }
    kv_put($db, '__pay|INV:' . $id, json_encode(['sid' => $j['id'], 'amt' => $bal, 'fee' => $fee, 't' => now_ms(), 'no' => $no, 'email' => $em, 'name' => $nm, 'lines' => [['Invoice #' . $no, 1, $bal]] + ($fee > 0 ? [1 => ['Card processing fee (3%)', 1, $fee]] : []), 'site' => $base0 . '/PartnerAccount.dc.html#invoices'], JSON_UNESCAPED_UNICODE));
    out(['ok' => true, 'url' => $j['url'] ?? '', 'cs' => $j['client_secret'] ?? '', 'amt' => $bal, 'fee' => $fee, 'no' => $no]);
  case 'pay_wso': // wholesale customer: pay a new order before delivery (card +3% or ACH); amount computed here from our wholesale prices
    $sk = stripe_sk($db); if ($sk === '') { out(['ok' => false, 'err' => 'off']); }
    $b = body(); $me = cust_rec($db, $b['em'] ?? '', 'biz', $b['tok'] ?? ''); if (!$me) { out(['ok' => false, 'err' => 'auth'], 401); }
    $em = strtolower(trim((string) $b['em'])); $rec = $me[1];
    $nos = []; foreach ((array) ($b['nos'] ?? []) as $n0) { $n0 = preg_replace('/[^A-Za-z0-9-]/', '', (string) $n0); if ($n0 !== '' && count($nos) < 10) { $nos[] = $n0; } } if (!$nos) { out(['ok' => false, 'err' => 'no']); }
    $ids = ['gata', 'khash', 'khbroth', 'khset', 'khinkali', 'pelmeni', 'blinchik', 'kufta', 'kiev', 'chcut', 'beefcut']; $N = ['Gata', 'Khash', 'Khash Broth', 'Khash Set', 'Khinkali', 'Pelmeni', 'Blinchik', 'Mijukov Kufta', 'Chicken Kiev', 'Chicken Cutlets', 'Beef Cutlets'];
    $IDX = [0, 4, 5, 6, 7, 8, 9, 10]; $WP0 = [18.00, 22.00, 22.00, 32.00, 33.00, 18.00, 13.20, 15.00]; $WH0 = [1.50, 2.00, 0, 3.00, 3.15, 2.75, 1.90, 2.10]; $PK = []; $PC = [];
    foreach ($IDX as $k => $pi) { $PK[$pi] = $WP0[$k]; $PC[$pi] = $WH0[$k]; }
    $cv = json_decode((kv_get($db, 'noa-cost-v2') ?: ['v' => '{}'])['v'], true) ?: []; foreach (($cv['prod'] ?? []) as $pd) { $k = array_search((string) ($pd['id'] ?? ''), $ids, true); if ($k === false || !in_array($k, $IDX, true)) { continue; } $r = (array) ($pd['pr'] ?? []); if ((float) ($r[2] ?? 0) > 0) { $PK[$k] = (float) $r[2]; } $PC[$k] = (float) ($r[3] ?? 0) > 0 ? (float) $r[3] : 0; }
    $pcs = ($b['pack'] ?? '') === 'pcs'; $days = max(1, min(10, (int) ($b['days'] ?? count($nos)))); $sub = 0; $lines = [];
    foreach ((array) ($b['items'] ?? []) as $it) { $pi = (int) ($it[0] ?? -1); $q = (int) ($it[1] ?? 0); if (!in_array($pi, $IDX, true) || $q < 1 || $q > 5000) { continue; } $u = $pcs ? $PC[$pi] : $PK[$pi]; if ($u <= 0) { continue; } $sub += $u * $q * $days; $lines[] = [$N[$pi] . ($pcs ? ' · by the piece' : ' · pack') . ($days > 1 ? ' · ' . $days . ' deliveries' : ''), $q * $days, round($u * $q * $days, 2), $u, $pi]; }
    $sub = round($sub, 2); if ($sub < 0.5) { out(['ok' => false, 'err' => 'empty']); }
    $ach = ($b['m'] ?? '') === 'ach'; $fee = $ach ? 0 : round($sub * 0.03, 2); $ref = 'WSO:' . substr(implode(',', $nos), 0, 180);
    $host = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'nectarofarmenia.com'); $base0 = $host . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/api.php')), '/');
    $form = ['mode' => 'payment', 'client_reference_id' => $ref, 'metadata[order]' => $ref, 'payment_intent_data[metadata][order]' => $ref, 'payment_method_types[0]' => $ach ? 'us_bank_account' : 'card', 'submit_type' => 'pay', 'success_url' => $base0 . '/PartnerAccount.dc.html?wspaid={CHECKOUT_SESSION_ID}', 'cancel_url' => $base0 . '/PartnerAccount.dc.html?wscancel=1'];
    $IMG = ['img/6830d83c7ec9c8d627f12f1f2a8c5eeb.jpg', 'img/c8b0737505aa785eed93371b09c8e049.jpg', 'img/c8b0737505aa785eed93371b09c8e049.jpg', 'img/d3fbe1930c2f9c5d58eab675b2128cfd.png', 'img/65940d08dd8a527a15d113ecd1854f3a.webp', 'img/9eb33fd2c3ff435a1314ecd2f69f9245.webp', 'img/645bf576eb7a0134b08d93cb48d381e7.webp', 'img/a6aaa8aa5db20ba68bc42c3406095e44.webp', 'img/6a86ac441cd9dd29b3e3ba1a16934988.webp', 'img/0f5ce6e80b23a0e775ad415e85b7ef0d.webp', 'img/135d0cd80fa69f4a118392eea9b8bcd6.webp'];
    foreach ($lines as $i => $l) { $form["line_items[$i][price_data][currency]"] = 'usd'; $form["line_items[$i][price_data][product_data][name]"] = $l[0]; $form["line_items[$i][price_data][unit_amount]"] = (int) round($l[3] * 100); $form["line_items[$i][quantity]"] = (int) $l[1]; if ($https && !empty($IMG[$l[4]])) { $form["line_items[$i][price_data][product_data][images][0]"] = $base0 . '/' . $IMG[$l[4]]; } }
    if (!empty($b['embed'])) { unset($form['success_url'], $form['cancel_url']); $form['ui_mode'] = 'embedded'; $form['return_url'] = $base0 . '/PartnerAccount.dc.html?wspaid={CHECKOUT_SESSION_ID}'; }
    if ($fee > 0) { $i = count($lines); $form["line_items[$i][price_data][currency]"] = 'usd'; $form["line_items[$i][price_data][product_data][name]"] = 'Card processing fee (3%)'; $form["line_items[$i][price_data][unit_amount]"] = (int) round($fee * 100); $form["line_items[$i][quantity]"] = 1; $lines[] = ['Card processing fee (3%)', 1, $fee]; }
    if (filter_var($em, FILTER_VALIDATE_EMAIL)) { $form['customer_email'] = $em; }
    $r = stripe_api($sk, 'POST', 'checkout/sessions', $form, $code); $j = json_decode((string) $r, true) ?: [];
    if ($code !== 200 || (empty($j['url']) && empty($j['client_secret']))) { out(['ok' => false, 'err' => 'stripe', 'msg' => $j['error']['message'] ?? '']); }
    $lines = array_map(function ($l) { return [$l[0], $l[1], $l[2]]; }, $lines);
    kv_put($db, '__pay|' . $ref, json_encode(['sid' => $j['id'], 'amt' => $sub, 'fee' => 0, 'cfee' => $fee, 't' => now_ms(), 'nos' => $nos, 'm' => $ach ? 'ACH' : 'Card', 'email' => $em, 'name' => (string) ($rec['biz'] ?? ''), 'phone' => (string) ($rec['phone'] ?? ''), 'lines' => $lines, 'site' => $base0 . '/PartnerAccount.dc.html'], JSON_UNESCAPED_UNICODE));
    out(['ok' => true, 'url' => $j['url'] ?? '', 'cs' => $j['client_secret'] ?? '', 'amt' => $sub, 'fee' => $fee, 'lines' => $lines]);
  case 'zelle_sent': // wholesale customer says a Zelle transfer was sent: notify the owner to check the bank and confirm in admin
    $b = body(); $me = cust_rec($db, $b['em'] ?? '', 'biz', $b['tok'] ?? ''); if (!$me) { out(['ok' => false, 'err' => 'auth'], 401); }
    $rec = $me[1]; $nos = []; foreach ((array) ($b['nos'] ?? []) as $n0) { $n0 = preg_replace('/[^A-Za-z0-9-]/', '', (string) $n0); if ($n0 !== '' && count($nos) < 10) { $nos[] = $n0; } } if (!$nos) { out(['ok' => false]); }
    $amt = round(max(0, (float) ($b['amt'] ?? 0)), 2); $from = mb_substr(trim((string) ($b['from'] ?? '')), 0, 120); $biz = (string) ($rec['biz'] ?? $b['em']);
    $kz = '__zelle|' . implode(',', $nos); if (!kv_get($db, $kz)) { kv_put($db, $kz, json_encode(['t' => now_ms(), 'em' => strtolower((string) $b['em']), 'amt' => $amt, 'from' => $from])); push_admin($db, '⚡ Zelle վճարում · $' . number_format($amt, 2) . ' · ստուգեք', $biz . ' · ' . implode(', ', $nos) . ($from !== '' ? ' · ուղարկող՝ ' . $from : '') . ' · հաստատեք ադմինում');
      $m = mail_cfg($db); $own = (string) ($m['notify'] ?? ($m['user'] ?? '')); if (!empty($m['user']) && filter_var($own, FILTER_VALIDATE_EMAIL)) { $e = null; smtp_send($m, $own, '⚡ Zelle to confirm $' . number_format($amt, 2) . ' — ' . $biz, mail_tpl('Zelle payment to confirm', '<p style="margin:0 0 12px;font-size:15px"><b>' . mail_esc($biz) . '</b> says it sent a Zelle payment.</p><p style="margin:0;font-size:15px">Orders: <b>' . mail_esc(implode(', ', $nos)) . '</b><br>Amount: <b>$' . number_format($amt, 2) . '</b><br>Sent from: <b>' . mail_esc($from !== '' ? $from : '—') . '</b></p><p style="margin:14px 0 0;color:#6B756F;font-size:13px">Check the bank, then press “✓ Payment received” in the admin page (Wholesale orders). Do not deliver before confirming.</p>'), $e); } }
    out(['ok' => true]);
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
    // safety net: at most every 10 minutes a site visit re-checks the warehouse sent to Chef Apri (sends only if it changed)
    try { $ck = kv_get($db, '__apri_stock_chk'); if (!$ck || now_ms() - (int) $ck['t'] > 600000) { kv_put($db, '__apri_stock_chk', '1'); apri_stock_sync($db); } } catch (Throwable $e) {}
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
  case 'doc_link': // approved wholesale customer: short-lived links to business documents (files are not public)
    $b = body(); $em = strtolower((string) ($b['em'] ?? '')); $tok = (string) ($b['tok'] ?? '');
    if ($em === '' || strlen($tok) < 16) { out(['ok' => false, 'err' => 'auth'], 401); }
    $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; $okB = false;
    foreach ($A as $key => $rec) { if (!is_array($rec) || strtolower($rec['email'] ?? '') !== $em || !isset($rec['tok']) || !hash_equals((string) $rec['tok'], $tok)) { continue; } $isBiz = substr((string) $key, -4) === '|biz' || ($rec['type'] ?? '') === 'biz'; if ($isBiz && in_array($rec['status'] ?? '', ['approved', 'active'], true)) { $okB = true; } }
    if (!$okB) { out(['ok' => true, 'docs' => []]); }
    $x = time() + 1800; $docs = [];
    foreach (nofa_docs() as $id => $d) { if (is_file($d['f'])) { $docs[$id] = ['url' => 'api/api.php?a=doc&id=' . $id . '&x=' . $x . '&s=' . hash_hmac('sha256', $id . '|' . $x, doc_secret($db)), 'no' => $d['no'], 'exp' => $d['exp']]; } }
    out(['ok' => true, 'docs' => $docs]);
  case 'doc': // signed link from doc_link (expires in 30 minutes)
    $id = (string) ($_GET['id'] ?? ''); $x = (int) ($_GET['x'] ?? 0); $sg = (string) ($_GET['s'] ?? ''); $D = nofa_docs();
    if (!isset($D[$id]) || $x < time() || !hash_equals(hash_hmac('sha256', $id . '|' . $x, doc_secret($db)), $sg) || !is_file($D[$id]['f'])) { out(['ok' => false, 'err' => 'expired'], 403); }
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . basename($D[$id]['f']) . '"'); header('Cache-Control: private, no-store'); header('X-Robots-Tag: noindex');
    readfile($D[$id]['f']); exit;
  case 'acct_upd': // customer: update own profile fields right away (name, store name, phone...), verified by email + token
    $b = body(); $em = strtolower(trim((string) ($b['em'] ?? ''))); $tok = (string) ($b['tok'] ?? ''); $ty = ($b['type'] ?? '') === 'biz' ? 'biz' : 'retail';
    if ($em === '' || strlen($tok) < 16) { out(['ok' => false, 'err' => 'auth'], 401); }
    $allow = $ty === 'biz' ? ['biz', 'bizType', 'opened', 'contact', 'role', 'phone', 'permit', 'ein', 'recv', 'freezer', 'web', 'dock'] : ['first', 'last', 'phone', 'dob'];
    $f = is_array($b['f'] ?? null) ? $b['f'] : [];
    $A = json_decode((kv_get($db, 'noa-accounts') ?: ['v' => '{}'])['v'], true) ?: []; $hit = null;
    foreach ([$em . '|' . $ty, $em] as $k0) { if (isset($A[$k0]) && is_array($A[$k0]) && isset($A[$k0]['tok']) && hash_equals((string) $A[$k0]['tok'], $tok)) { $hit = $k0; break; } }
    if ($hit === null) { out(['ok' => false, 'err' => 'pending']); }
    foreach ($allow as $k1) { if (array_key_exists($k1, $f)) { $A[$hit][$k1] = mb_substr(trim((string) $f[$k1]), 0, 200); } }
    if ($ty === 'biz' && trim((string) ($A[$hit]['biz'] ?? '')) === '') { out(['ok' => false, 'err' => 'empty'], 400); }
    $A[$hit]['updatedAt'] = gmdate('c');
    kv_put($db, 'noa-accounts', json_encode($A, JSON_UNESCAPED_UNICODE));
    $ret = $A[$hit]; unset($ret['tok'], $ret['pwh'], $ret['pw']); out(['ok' => true, 'acct' => $ret]);
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
    foreach (($cost['cprof'] ?? []) as $ck0 => $q0) { if (is_array($q0) && strtolower((string) ($q0['em'] ?? '')) === $em && substr((string) $ck0, -2) === '|w' && !in_array($ck0, $cks, true)) { $cks[] = $ck0; } }
    foreach ($cks as $ck) { if (isset($cost['cprof'][$ck]['dep0'])) { $q = $cost['cprof'][$ck]; $cp[$ck] = ['dep0' => $q['dep0'], 'dep0d' => (string) ($q['dep0d'] ?? ''), 'dep0n' => (string) ($q['dep0n'] ?? ''), 'dep0m' => (string) ($q['dep0m'] ?? 'at')]; } }
    $LK = links_get($db); $lk = []; foreach (array_keys($mine) as $mk) { foreach (($LK[$mk] ?? []) as $ok2) { $lk[$mk][] = $ok2; foreach ($A as $k3 => $r3) { if (is_array($r3) && strtolower((string) $k3) === $ok2) { $mine[$k3] = $r3; } } } }
    $pay = null; foreach ($mine as $key => $rec) { $isBiz = substr((string) $key, -4) === '|biz' || ($rec['type'] ?? '') === 'biz'; if ($isBiz && in_array($rec['status'] ?? '', ['approved', 'active'], true)) { $pay = true; break; } } // bank details: approved wholesale partners only
    if ($pay) { $pc = json_decode((kv_get($db, 'noa-paycfg') ?: ['v' => '{}'])['v'], true) ?: []; $pay = []; foreach (['bank', 'acctName', 'routing', 'acct', 'wireRouting', 'bankAddr'] as $f) { if (trim((string) ($pc[$f] ?? '')) !== '') { $pay[$f] = (string) $pc[$f]; } } }
    $RF = json_decode((kv_get($db, 'noa-refs') ?: ['v' => '[]'])['v'], true) ?: []; $want = []; foreach ($RF as $rr) { if (is_array($rr) && strtolower((string) ($rr['ref'] ?? '')) === $em && !empty($rr['email'])) { $want[strtolower($rr['email'])] = 1; } }
    $refPaid = []; if ($want) { $PW = json_decode((kv_get($db, '__paidweb') ?: ['v' => '{}'])['v'], true) ?: []; foreach (array_keys($PW) as $pno) { $pr2 = json_decode((kv_get($db, '__pay|' . $pno) ?: ['v' => '{}'])['v'], true) ?: []; $pe = strtolower((string) ($pr2['email'] ?? '')); if ($pe !== '' && isset($want[$pe])) { $refPaid[$pe] = 1; } }
      $OR = json_decode((kv_get($db, 'noa-orders') ?: ['v' => '[]'])['v'], true) ?: []; foreach ($OR as $oo) { if (!is_array($oo)) { continue; } $oe = strtolower((string) ($oo['email'] ?? '')); $stt = (string) ($oo['status'] ?? ''); if ($oe !== '' && isset($want[$oe]) && (!empty($oo['paid']) || in_array($stt, ['paid', 'delivered', 'done', 'completed'], true))) { $refPaid[$oe] = 1; } } }
    out(['ok' => true, 'refPaid' => array_keys($refPaid), 'accounts' => $mine, 'links' => $lk, 'keys' => $keys, 'inv' => $list, 'cprof' => $cp, 'pay' => $pay]);
}
out(['ok' => false, 'err' => 'unknown'], 400);
