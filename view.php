<?php
declare(strict_types=1);
require __DIR__ . '/inc.php';

/*
 * Two kinds of request reach this file:
 *  1. ?id=…&t=…  The viewer's iframe. Returns a tiny loader plus the example's HTML,
 *                encrypted with a fresh random key. The key is NOT in the response.
 *  2. ?k=…       The loader asking for that key. Answered once, then forgotten,
 *                so a saved or replayed copy of response 1 can never be decrypted.
 */

const KEY_BYTES   = 16;
const RC4_DROP    = 768;
const MAX_PENDING = 20;

function deny(): never
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit('Not found');
}

/** RC4 stream cipher (same algorithm runs in the loader). */
function rc4(string $key, string $data): string
{
    $s = range(0, 255);
    $kl = strlen($key);
    for ($i = 0, $j = 0; $i < 256; $i++) {
        $j = ($j + $s[$i] + ord($key[$i % $kl])) & 255;
        [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
    }
    $i = $j = 0;
    for ($n = 0; $n < RC4_DROP; $n++) {
        $i = ($i + 1) & 255; $j = ($j + $s[$i]) & 255;
        [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
    }
    $len = strlen($data);
    $out = str_repeat("\0", $len);
    for ($n = 0; $n < $len; $n++) {
        $i = ($i + 1) & 255; $j = ($j + $s[$i]) & 255;
        $t = $s[$i]; $s[$i] = $s[$j]; $s[$j] = $t;
        $out[$n] = chr(ord($data[$n]) ^ $s[($s[$i] + $s[$j]) & 255]);
    }
    return $out;
}

$dest = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '';
$site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';

/* ---------- 2. One-time key request ---------- */
if (isset($_GET['k'])) {
    if (($dest !== '' && $dest !== 'empty') || ($site !== '' && $site !== 'same-origin')) {
        deny();
    }
    session_start();
    $nonce = (string) $_GET['k'];
    $key = $_SESSION['viewer_keys'][$nonce] ?? null;
    unset($_SESSION['viewer_keys'][$nonce]);   // used once, then gone
    session_write_close();
    if ($key === null) {
        deny();
    }
    header('Content-Type: application/octet-stream');
    header('Cache-Control: no-store, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
    echo $key;
    exit;
}

/* ---------- 1. The example itself ---------- */
session_start();
$token    = $_SESSION['viewer_token'] ?? '';
$id       = (string) ($_GET['id'] ?? '');
$given    = (string) ($_GET['t'] ?? '');
$examples = list_examples();

// Opening this URL directly in a tab sends "document"; only our iframe is answered.
if (
    !isset($examples[$id]) ||
    $token === '' ||
    !hash_equals($token, $given) ||
    ($dest !== '' && $dest !== 'iframe')
) {
    session_write_close();
    deny();
}

$key   = random_bytes(KEY_BYTES);
$nonce = bin2hex(random_bytes(12));
$pending = $_SESSION['viewer_keys'] ?? [];
$pending[$nonce] = $key;
$_SESSION['viewer_keys'] = array_slice($pending, -MAX_PENDING, null, true);
session_write_close();

$html = (string) file_get_contents(SLIDES_DIR . '/' . $examples[$id]['file']);

$inject = '<base href="' . e(SLIDES_URL) . '">' . <<<'HTML'
<style id="__viewer_lock">
  /* Scrollbars hidden but overflow left alone: setting overflow on html + body
     would break position: sticky (pinned sections) in the page. */
  html { scrollbar-width: none !important; scroll-behavior: auto !important; }
  html::-webkit-scrollbar, body::-webkit-scrollbar { display: none !important; }
  *, *::before, *::after {
    -webkit-user-select: none !important; user-select: none !important;
    -webkit-user-drag: none !important; -webkit-touch-callout: none !important;
  }
  @media print { html { display: none !important; } }
</style>
<script>
  (function () {
    var stop = function (e) { e.preventDefault(); };
    ['contextmenu', 'selectstart', 'dragstart', 'copy', 'cut'].forEach(function (t) {
      document.addEventListener(t, stop, true);
    });
    document.addEventListener('keydown', function (e) {
      try { if (parent && parent.__viewerKey) parent.__viewerKey(e); } catch (_) {}
    }, true);
  })();
</script>
HTML;

// Injection goes first inside <head> so <base> applies to every relative URL.
$add = fn($m) => $m[0] . $inject;
if (preg_match('/<head\b[^>]*>/i', $html)) {
    $html = preg_replace_callback('/<head\b[^>]*>/i', $add, $html, 1);
} elseif (preg_match('/<html\b[^>]*>/i', $html)) {
    $html = preg_replace_callback('/<html\b[^>]*>/i', fn($m) => $m[0] . '<head>' . $inject . '</head>', $html, 1);
} else {
    $html = '<head>' . $inject . '</head>' . $html;
}

$payload = base64_encode(rc4($key, $html));
$keyUrl  = 'view.php?k=' . $nonce;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: frame-ancestors 'self'");
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');

// Compact loader: fetch the one-time key, decrypt, replace this document.
?><!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><style>html,body{margin:0;background:#fff}</style></head><body><script id="_x" type="application/octet-stream"><?= $payload ?></script><script>(async function(){var _0=document.getElementById('_x'),_1=_0.textContent;_0.remove();try{var _2=await fetch(<?= json_encode($keyUrl) ?>,{credentials:'same-origin',cache:'no-store'});if(!_2.ok)return;var k=new Uint8Array(await _2.arrayBuffer()),a=atob(_1),n=a.length,b=new Uint8Array(n),s=[],i,j=0,t,x;_1=null;for(i=0;i<256;i++)s[i]=i;for(i=0;i<256;i++){j=(j+s[i]+k[i%k.length])&255;t=s[i];s[i]=s[j];s[j]=t}i=j=0;for(x=0;x<<?= RC4_DROP ?>;x++){i=(i+1)&255;j=(j+s[i])&255;t=s[i];s[i]=s[j];s[j]=t}for(x=0;x<n;x++){i=(i+1)&255;j=(j+s[i])&255;t=s[i];s[i]=s[j];s[j]=t;b[x]=a.charCodeAt(x)^s[(s[i]+s[j])&255]}a=null;document.open();document.write(new TextDecoder('utf-8').decode(b));document.close();b=null;try{parent.__viewerReady&&parent.__viewerReady()}catch(e){}}catch(e){}})();</script></body></html>
