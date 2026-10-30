<?php
/**
 * PHP-nin daxili serveri üçün marşrutlayıcı.
 *   php -S localhost:8010 router.php
 *
 * Ünvan sxemi orijinal caucasuscl.com ilə eynidir — bax includes/routes.php.
 * Apache-də bu fayl istifadə olunmur, .htaccess işə düşür.
 */
require_once __DIR__ . '/includes/compat.php';

require_once __DIR__ . '/includes/routes.php';

$root   = __DIR__;
$rawUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$query  = $_SERVER['QUERY_STRING'] ?? '';

/* ---------- gizli fayllar ---------- */
if (preg_match('#^/(storage|includes|content|tools)(/|$)#', $rawUri)
    || preg_match('#(^|/)\.(git|hg|svn|env)(/|$)|^/\.cpanel\.yml$#', $rawUri)
    || preg_match('#^/admin/(config|config\.sample|db|schema|lib|turnstile|seed-i18n)\.php$#', $rawUri)
    || preg_match('#\.(bak|bak2|sqlite|sqlite-wal|sqlite-shm|log)$#', $rawUri)) {
    http_response_code(403); echo 'Forbidden'; return true;
}

/* ---------- statik fayllar ---------- */
if ($rawUri !== '/' && file_exists($root . $rawUri) && !is_dir($root . $rawUri)) {
    return false;
}

/* ---------- sitemap ---------- */
if ($rawUri === '/sitemap.xml') { require $root . '/sitemap.php'; return true; }
if ($rawUri === '/sitemap_index.xml') { $_GET['index'] = '1'; require $root . '/sitemap.php'; return true; }
if (preg_match('#^/[a-z_-]+-sitemap[0-9]*\.xml$#', $rawUri)) {
    header('Location: /sitemap.xml', true, 301); return true;
}

/* ---------- admin ---------- */
if ($rawUri === '/admin') { header('Location: /admin/', true, 301); return true; }
if (str_starts_with($rawUri, '/admin/')) {
    $f = $root . rtrim($rawUri, '/');
    if ($rawUri === '/admin/' || is_dir($f)) $f = $root . '/admin/index.php';
    if (is_file($f)) { require $f; return true; }
    http_response_code(404); echo 'Not found'; return true;
}

/* ---------- dil prefiksi ---------- */
$lang = 'az';
$path = $rawUri;
foreach (['ru', 'en'] as $lc) {
    if ($path === '/' . $lc || str_starts_with($path, '/' . $lc . '/')) {
        $lang = $lc;
        $path = substr($path, strlen($lc) + 1);
        if ($path === '') $path = '/';
        break;
    }
}
$hadSlash = ($path === '/' || str_ends_with($path, '/'));
$path     = '/' . trim($path, '/');
$pre      = $lang === 'az' ? '' : '/' . $lang;

/* ---------- WordPress-dən qalan ünvanlar (.htaccess ilə eyni) ---------- */
if (preg_match('#^/(gomruk-xeberleri|xeberler|novosti|news)/(page/)?\d+$#', $path)
    || preg_match('#^/page/\d+$#', $path)) {
    header('Location: ' . $pre . '/' . seg('news', $lang) . '/', true, 301);
    return true;
}
if (preg_match('#/feed$#', $path) || preg_match('#^/(rss|rss2|atom)$#', $path)) {
    header('Location: ' . $pre . '/' . seg('news', $lang) . '/', true, 301);
    return true;
}
if (preg_match('#^/(category|tag|etiket)/#', $path)) {
    header('Location: ' . $pre . '/' . seg('news', $lang) . '/', true, 301);
    return true;
}
if (preg_match('#^/author/#', $path)) {
    header('Location: ' . $pre . '/' . seg('about', $lang) . '/', true, 301);
    return true;
}
if (preg_match('#^/wp-(json|admin|includes)#', $path)) {
    http_response_code(410); echo 'Gone'; return true;
}
if ($path === '/' && preg_match('/(^|&)(p|page_id|cat|tag|m|attachment_id|author|s|feed|replytocom)=/i', $query)) {
    header('Location: ' . $pre . '/', true, 301);
    return true;
}

/* ---------- köhnə ünvanlar -> yeni sxem ---------- */
if ($path === '/category/xeberler') {
    header('Location: ' . $pre . '/' . seg('news', $lang) . '/', true, 301);
    return true;
}
$aliases = ['/xeberler' => '/' . seg('news', $lang), '/linkler' => '/' . seg('links', $lang)];
if (isset($aliases[$path])) {
    header('Location: ' . $pre . $aliases[$path] . '/', true, 301);
    return true;
}
if (preg_match('#^/xeberler/([a-z0-9\-]+)$#', $path, $m)) {
    header('Location: ' . rtrim($pre . route_path('/xeberler/' . $m[1], $lang), '/') . '/', true, 301);
    return true;
}

/* ---------- marşrut ---------- */
$r = route_match($path, $lang);

// kanonik: sonda slash olsun
if ($r['key'] !== '404' && $path !== '/' && !$hadSlash) {
    header('Location: ' . $rawUri . '/' . ($query !== '' ? '?' . $query : ''), true, 301);
    return true;
}

switch ($r['key']) {
    case 'home':     require $root . '/index.php';       return true;
    case 'about':    require $root . '/haqqimizda.php';  return true;
    case 'services': require $root . '/xidmetler.php';   return true;
    case 'news':     require $root . '/xeberler.php';    return true;
    case 'contact':  require $root . '/elaqe.php';       return true;
    case 'links':    require $root . '/linkler.php';     return true;
    case 'service':  $_GET['slug'] = $r['slug']; require $root . '/xidmet.php'; return true;
    case 'newsitem': $_GET['slug'] = $r['slug']; require $root . '/xeber.php';  return true;
}

http_response_code(404);
require $root . '/404.php';
return true;
