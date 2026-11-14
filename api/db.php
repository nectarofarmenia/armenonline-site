<?php
// Nectar of Armenia — shared DB helpers
function nofa_cfg_paths() { $out = []; $root = dirname(__DIR__); $out[] = dirname($root) . '/nofa_config.php'; $dr = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'); if ($dr !== '') { $out[] = dirname($dr) . '/nofa_config.php'; } $h = strtolower(preg_replace('/^www\./', '', (string) ($_SERVER['HTTP_HOST'] ?? ''))); $home = getenv('HOME') ?: ''; if ($home === '' && preg_match('#^(/home/[^/]+)/#', __DIR__, $m)) { $home = $m[1]; } if ($home !== '' && preg_match('/^[a-z0-9.-]+$/', $h)) { $out[] = $home . '/domains/' . $h . '/nofa_config.php'; } $out[] = __DIR__ . '/config.php'; return array_values(array_unique($out)); }
function nofa_cfg() { static $c = false; if ($c !== false) { return $c; } $c = null; foreach (nofa_cfg_paths() as $f) { if (@file_exists($f)) { $c = include $f; break; } } return $c; }
function nofa_db() {
  static $pdo = null; if ($pdo) { return $pdo; }
  $c = nofa_cfg(); if (!$c) { return null; }
  if (!empty($c['sqlite'])) { $pdo = new PDO('sqlite:' . $c['sqlite']); }
  else { $pdo = new PDO('mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4', $c['user'], $c['pass'], [PDO::ATTR_TIMEOUT => 5]); }
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return $pdo;
}
function nofa_is_sqlite() { $c = nofa_cfg(); return $c && !empty($c['sqlite']); }
function nofa_schema($pdo, $sqlite) {
  $ai = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT AUTO_INCREMENT PRIMARY KEY';
  $txt = $sqlite ? 'TEXT' : 'LONGTEXT';
  $pdo->exec("CREATE TABLE IF NOT EXISTS nofa_kv (k VARCHAR(190) NOT NULL PRIMARY KEY, v $txt, t BIGINT NOT NULL DEFAULT 0)" . ($sqlite ? '' : ' DEFAULT CHARSET=utf8mb4'));
  $pdo->exec("CREATE TABLE IF NOT EXISTS nofa_inbox (id $ai, kind VARCHAR(20), em VARCHAR(190), h VARCHAR(64), data $txt, ip VARCHAR(64), t BIGINT)" . ($sqlite ? '' : ' DEFAULT CHARSET=utf8mb4'));
  $pdo->exec("CREATE TABLE IF NOT EXISTS nofa_backup (id $ai, k VARCHAR(190), v $txt, t BIGINT)" . ($sqlite ? '' : ' DEFAULT CHARSET=utf8mb4'));
  try { $pdo->exec("CREATE UNIQUE INDEX nofa_inbox_h ON nofa_inbox (h)"); } catch (Exception $e) {}
}
