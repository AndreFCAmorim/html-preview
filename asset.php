<?php
declare(strict_types=1);
require __DIR__ . '/inc.php';

/*
 * Serves images, CSS, fonts, etc. from the slides folder at asset.php/<token>/<path>,
 * so the slides' real location is never part of any URL. Only the viewer's own
 * session (token) can fetch them, and the slides themselves (.html) are never served here.
 */

const MIME = [
    'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8', 'mjs' => 'text/javascript; charset=utf-8',
    'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon',
    'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav',
];

function not_found(): never
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit('Not found');
}

$site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
$path = (string) ($_SERVER['PATH_INFO'] ?? '');
if (($site !== '' && $site !== 'same-origin') || !preg_match('#^/([0-9a-f]{32})/(.+)$#', $path, $m)) {
    not_found();
}

session_start();
$token = (string) ($_SESSION['viewer_token'] ?? '');
session_write_close();
if ($token === '' || !hash_equals($token, $m[1])) {
    not_found();
}

$rel = rawurldecode($m[2]);
$ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
if (!isset(MIME[$ext]) || str_contains($rel, "\0") || str_contains($rel, '\\') || in_array('..', explode('/', $rel), true)) {
    not_found();
}

$root = realpath(SLIDES_DIR);
$file = realpath(SLIDES_DIR . '/' . $rel);
if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
    not_found();
}

header('Content-Type: ' . MIME[$ext]);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
readfile($file);
