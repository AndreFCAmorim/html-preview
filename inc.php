<?php
declare(strict_types=1);

/** Folder that holds the HTML examples (and their images/CSS/fonts). */
const SLIDES_DIR = __DIR__ . '/slides';

/** Script that serves the files in that folder, so their real URL is never exposed. */
const ASSET_URL = 'asset.php/';

/**
 * Returns every .html/.htm file in SLIDES_DIR as
 * [id => ['id' => ..., 'file' => ..., 'title' => ...]], sorted by file name.
 */
function list_examples(): array
{
    $items = [];
    $files = is_dir(SLIDES_DIR) ? scandir(SLIDES_DIR) : [];

    foreach ($files as $file) {
        if (!preg_match('/^([A-Za-z0-9._-]+)\.html?$/i', $file, $m)) {
            continue; // only plain, safe file names
        }
        $path = SLIDES_DIR . '/' . $file;
        if (!is_file($path)) {
            continue;
        }
        $id = $m[1];
        $items[$id] = [
            'id'    => $id,
            'file'  => $file,
            'title' => extract_title($path, $id),
        ];
    }

    uasort($items, fn($a, $b) => strnatcasecmp($a['file'], $b['file']));
    return $items;
}

/**
 * Title priority: <title>, then <meta name="title">, then og:title,
 * then a prettified file name.
 */
function extract_title(string $path, string $fallback): string
{
    $html = (string) @file_get_contents($path, false, null, 0, 200_000);

    if ($html !== '') {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $title = $dom->getElementsByTagName('title')->item(0);
        if ($title && trim($title->textContent) !== '') {
            return clean_text($title->textContent);
        }

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $key = strtolower($meta->getAttribute('name') ?: $meta->getAttribute('property'));
            if (in_array($key, ['title', 'og:title'], true) && trim($meta->getAttribute('content')) !== '') {
                return clean_text($meta->getAttribute('content'));
            }
        }
    }

    return ucfirst(trim(preg_replace('/[-_.]+/', ' ', $fallback)));
}

function clean_text(string $s): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function viewer_token(): string
{
    if (empty($_SESSION['viewer_token'])) {
        $_SESSION['viewer_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['viewer_token'];
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
