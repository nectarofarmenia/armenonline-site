<?php
// Invitation link with a rich preview card (iMessage, WhatsApp, Facebook read og: tags), then forwards to the site.
$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['r'] ?? '')));
$code = substr($code, 0, 16);
$w = !empty($_GET['w']);
$host = 'https://nectarofarmenia.com';
$to = $w ? '/Account.dc.html?ref=' . rawurlencode($code) . '#biz' : '/Main.dc.html?ref=' . rawurlencode($code);
$img = $host . '/img/social/' . ($w ? 'invite_wholesale.jpg' : 'invite_retail.jpg');
$title = $w ? 'Nectar of Armenia · Մեծածախ համագործակցություն' : 'Ձեզ հրավիրում են Nectar of Armenia 🎁';
$desc = $w ? 'Սառեցված հայկական ապրանքներ մեծածախ գնով՝ սառնարանային առաքում, դեպոզիտ, Net 15։' : 'Իսկական հայկական ուտեստներ՝ խինկալի, պելմենի, գաթա և ավելին։ Առաքում 48 ժամում։';
$h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
?><!doctype html>
<html lang="hy"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $h($title) ?></title>
<meta name="description" content="<?= $h($desc) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Nectar of Armenia">
<meta property="og:title" content="<?= $h($title) ?>">
<meta property="og:description" content="<?= $h($desc) ?>">
<meta property="og:image" content="<?= $h($img) ?>">
<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
<meta property="og:url" content="<?= $h($host . $_SERVER['REQUEST_URI']) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= $h($img) ?>">
<link rel="icon" href="/favicon.ico">
<meta http-equiv="refresh" content="0;url=<?= $h($to) ?>">
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#133B30;color:#F6E3B4;font-family:system-ui,sans-serif}a{color:#F6C35A}</style>
</head><body><p><a href="<?= $h($to) ?>">Nectar of Armenia →</a></p>
<script>location.replace(<?= json_encode($to) ?>);</script></body></html>
