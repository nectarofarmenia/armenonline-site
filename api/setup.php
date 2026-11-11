<?php
// Nectar of Armenia — one-time setup: database + admin password
require __DIR__ . '/db.php';
$done = (bool) nofa_cfg(); $msg = ''; $ok = false;
if (!$done && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $host = trim($_POST['host'] ?? 'localhost'); $name = trim($_POST['name'] ?? ''); $user = trim($_POST['user'] ?? ''); $pass = (string) ($_POST['pass'] ?? '');
  $pw = (string) ($_POST['pw'] ?? ''); $pw2 = (string) ($_POST['pw2'] ?? '');
  if (strlen($pw) < 8) { $msg = 'Ադմինի գաղտնաբառը պետք է լինի առնվազն 8 նիշ։'; }
  elseif ($pw !== $pw2) { $msg = 'Գաղտնաբառերը չեն համընկնում։'; }
  else {
    try {
      $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [PDO::ATTR_TIMEOUT => 5]); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      nofa_schema($pdo, false);
      $cfg = "<?php\nreturn " . var_export(['host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass, 'admin_hash' => password_hash($pw, PASSWORD_DEFAULT)], true) . ";\n";
      $P = nofa_cfg_paths(); $w = @file_put_contents($P[0], $cfg); if ($w === false) { $w = @file_put_contents($P[1], $cfg); } if ($w === false) { $msg = 'Չհաջողվեց գրել կարգավորման ֆայլը (թույլտվություն)։'; } else { $ok = true; $done = true; }
    } catch (Exception $e) { $msg = 'Չհաջողվեց միանալ բազային․ ստուգեք տվյալները։ (' . htmlspecialchars($e->getMessage()) . ')'; }
  }
}
?><!doctype html><html lang="hy"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>NofA — Setup</title>
<style>body{margin:0;background:#F7F3EA;font:15px/1.5 system-ui,sans-serif;color:#1E2A24}.c{max-width:520px;margin:40px auto;background:#fff;border-radius:20px;padding:24px;box-shadow:0 10px 30px rgba(29,85,69,.1)}h1{color:#1D5545;font-size:22px;margin:0 0 6px}label{display:block;font-weight:700;font-size:13px;margin-top:12px;color:#5B4A22}input{width:100%;box-sizing:border-box;padding:10px 12px;border:1.5px solid #E4DCC8;border-radius:10px;font-size:15px}button{margin-top:18px;width:100%;padding:12px;border:0;border-radius:999px;background:#1D5545;color:#F6E3B4;font-weight:800;font-size:15px;cursor:pointer}.e{color:#A1362A;font-weight:700;margin-top:10px}.ok{color:#22704A;font-weight:800}h3{margin:18px 0 0;color:#1D5545;font-size:15px}</style></head><body><div class="c">
<h1>Nectar of Armenia — կարգավորում</h1>
<?php if ($ok): ?><p class="ok">✓ Պատրաստ է։ Բազան միացված է, ադմինի գաղտնաբառը պահված է։</p><p>Հիմա բացեք <a href="../Admin.dc.html">ադմինը</a> և մուտք գործեք նոր գաղտնաբառով։ Այս էջն այլևս չի աշխատի։</p>
<?php elseif ($done): ?><p>Կարգավորումն արդեն արված է։ Փոխելու համար Hostinger-ի File Manager-ում ջնջեք <b>nofa_config.php</b> ֆայլը (public_html-ի կողքին) կամ <b>api/config.php</b>։</p>
<?php else: ?><p>Լրացրեք Hostinger-ում ստեղծած MySQL բազայի տվյալները և ընտրեք ադմինի գաղտնաբառը։ Սա արվում է միայն մեկ անգամ։</p>
<?php if ($msg): ?><p class="e"><?= $msg ?></p><?php endif; ?>
<form method="post"><h3>1. MySQL բազա</h3>
<label>Host<input name="host" value="localhost"></label><label>Database name<input name="name" required placeholder="u123456789_nofa"></label><label>Username<input name="user" required placeholder="u123456789_nofa"></label><label>Password<input name="pass" type="password"></label>
<h3>2. Ադմինի գաղտնաբառ</h3><label>Գաղտնաբառ (8+ նիշ)<input name="pw" type="password" required minlength="8"></label><label>Կրկնել<input name="pw2" type="password" required></label>
<button>Պահել և միացնել</button></form><?php endif; ?>
</div><script id="nofa-pw-eye">
(function () {
  /* Eye button next to every password field: tap to show / hide the password. */
  var css = document.createElement("style");
  css.textContent = ".nz-pw{position:relative;display:block}.nz-pw>input{padding-right:46px!important;width:100%;box-sizing:border-box}.nz-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:36px;height:36px;border:0;border-radius:50%;background:transparent;color:#5E6A63;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0}.nz-eye:hover{background:rgba(29,85,69,.08)}.nz-eye svg{width:20px;height:20px}";
  document.head.appendChild(css);
  var OPEN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var SHUT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-7 0-11-7-11-7a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';
  function lbl(show) { var l = (document.documentElement.lang || "hy").slice(0, 2); var T = { hy: ["Ցույց տալ գաղտնաբառը", "Թաքցնել գաղտնաբառը"], ru: ["Показать пароль", "Скрыть пароль"], es: ["Mostrar contraseña", "Ocultar contraseña"], en: ["Show password", "Hide password"] }; return (T[l] || T.en)[show ? 1 : 0]; }
  function add(inp) {
    if (inp.__nzEye) { return; } inp.__nzEye = 1;
    var p = inp.parentElement; if (!p || p.querySelector(".pw-eye, .nz-eye")) { return; }
    var w = document.createElement("span"); w.className = "nz-pw"; p.insertBefore(w, inp); w.appendChild(inp);
    var b = document.createElement("button"); b.type = "button"; b.className = "nz-eye"; b.innerHTML = OPEN; b.setAttribute("aria-label", lbl(false)); b.title = lbl(false);
    b.addEventListener("click", function (e) { e.preventDefault(); e.stopPropagation(); var show = inp.type === "password"; inp.type = show ? "text" : "password"; b.innerHTML = show ? SHUT : OPEN; b.setAttribute("aria-label", lbl(show)); b.title = lbl(show); inp.focus(); });
    w.appendChild(b);
  }
  function scan() { document.querySelectorAll('input[type="password"]').forEach(add); }
  new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true }); scan();
})();
</script>

</body></html>
