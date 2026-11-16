<?php
// Nectar of Armenia: Web Push for the admin's devices (VAPID, empty payload; the service worker fetches the text)
function b64u($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function vapid_keys($db) {
  $r = kv_get($db, '__vapid'); if ($r) { $v = json_decode($r['v'], true); if (!empty($v['pem'])) { return $v; } }
  $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]); if (!$k) { return null; }
  openssl_pkey_export($k, $pem); $d = openssl_pkey_get_details($k);
  $pub = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
  $v = ['pem' => $pem, 'pub' => b64u($pub)]; kv_put($db, '__vapid', json_encode($v)); return $v;
}
function der2raw($der) { // ECDSA DER signature -> 64-byte r||s
  $o = 3; $lr = ord($der[$o]); $r = substr($der, $o + 1, $lr); $o = $o + 1 + $lr + 1; $ls = ord($der[$o]); $s = substr($der, $o + 1, $ls);
  $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT); $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT); return $r . $s;
}
function vapid_jwt($v, $aud) {
  $h = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])); $c = b64u(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => 'mailto:orders@nectarofarmenia.com']));
  $sig = ''; openssl_sign($h . '.' . $c, $sig, $v['pem'], OPENSSL_ALGO_SHA256); return $h . '.' . $c . '.' . b64u(der2raw($sig));
}
function push_admin($db, $title, $body, $url = 'Admin.dc.html') {
  try {
    $Q = json_decode((kv_get($db, '__pushq') ?: ['v' => '[]'])['v'], true) ?: []; array_unshift($Q, ['t' => now_ms(), 'title' => mb_substr($title, 0, 120), 'body' => mb_substr($body, 0, 240), 'url' => $url]); kv_put($db, '__pushq', json_encode(array_slice($Q, 0, 20), JSON_UNESCAPED_UNICODE));
    $S = json_decode((kv_get($db, '__pushsubs') ?: ['v' => '[]'])['v'], true) ?: []; if (!$S) { return; }
    $v = vapid_keys($db); if (!$v) { return; } $keep = []; $changed = false;
    foreach ($S as $sub) { $ep = (string) ($sub['endpoint'] ?? ''); if (!preg_match('#^https://#', $ep)) { $changed = true; continue; }
      $p = parse_url($ep); $aud = $p['scheme'] . '://' . $p['host']; $code = 0;
      if (function_exists('curl_init')) { $ch = curl_init($ep); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => ['TTL: 86400', 'Urgency: high', 'Content-Length: 0', 'Authorization: vapid t=' . vapid_jwt($v, $aud) . ', k=' . $v['pub']]]); curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); }
      if ($code === 404 || $code === 410) { $changed = true; continue; } $keep[] = $sub; }
    if ($changed) { kv_put($db, '__pushsubs', json_encode($keep)); }
  } catch (Exception $e) {}
}
