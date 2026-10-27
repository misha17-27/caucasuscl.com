<?php
/**
 * XML sitemap — bütün səhifələr üç dildə.
 *
 *   /sitemap.xml         — bütün ünvanlar
 *   /sitemap_index.xml   — indeks (WordPress/Yoast-dan qalan ünvan)
 *
 * Sadə formatdır: yalnız <loc> və (xəbərlər üçün) <lastmod>.
 *   · <changefreq> və <priority> — Google onları nəzərə almır, ona görə yoxdur.
 *   · hreflang alternativləri hər səhifənin <head> hissəsində verilir
 *     (includes/header.php), sitemap-da təkrarlamağa ehtiyac yoxdur.
 *
 * Marşrut .htaccess (və router.php) tərəfindən bu fayla yönləndirilir.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/content.php';

header('Content-Type: application/xml; charset=utf-8');

$host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
      . '://' . ($_SERVER['HTTP_HOST'] ?? 'caucasuscl.com');
$codes = array_keys(LANGS);

/** kanonik yol + dil -> tam ünvan */
$abs = static function (string $canonical, string $lang) use ($host): string {
    $pre = $lang === LANG_DEFAULT ? '' : '/' . $lang;
    $p   = route_path($canonical, $lang);
    return $host . $pre . ($p === '/' ? '/' : rtrim($p, '/') . '/');
};

/** kanonik yolu verilmiş dilə uyğunlaşdırır; həmin dildə yoxdursa null */
$localize = static function (string $canon, string $lang): ?string {
    if (!preg_match('#^/(xidmetler|xeberler)/(.+)$#', $canon, $m)) return $canon;
    if ($lang === LANG_DEFAULT) return $canon;
    $table = $m[1] === 'xidmetler' ? 'services' : 'news';
    $slug  = cms_translate_slug($table, $m[2], LANG_DEFAULT, $lang);
    return $slug === '' ? null : '/' . $m[1] . '/' . $slug;
};

/* ---------- indeks variantı ---------- */
if (($_GET['index'] ?? '') === '1') {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo "  <sitemap>\n    <loc>" . e($host . '/sitemap.xml') . "</loc>\n  </sitemap>\n";
    echo '</sitemapindex>' . "\n";
    exit;
}

/* ---------- ünvanların toplanması ---------- */
$entries = [];
foreach (['/', '/haqqimizda', '/xidmetler', '/xeberler', '/elaqe', '/linkler'] as $c) {
    $entries[] = ['canon' => $c, 'date' => ''];
}
foreach (array_keys($SERVICES) as $slug) {
    $entries[] = ['canon' => '/xidmetler/' . $slug, 'date' => ''];
}
foreach ($NEWS as $slug => $n) {
    /* xəbərlərdə real dərc tarixi var — Google lastmod-u nəzərə alır */
    $entries[] = ['canon' => '/xeberler/' . $slug, 'date' => (string)($n['date'] ?? '')];
}

/* ---------- çap ---------- */
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($entries as $en) {
    foreach ($codes as $lang) {
        $path = $localize($en['canon'], $lang);
        if ($path === null) continue;          // bu dildə tərcüməsi yoxdur
        echo "  <url>\n";
        echo '    <loc>' . e($abs($path, $lang)) . "</loc>\n";
        if ($en['date'] !== '') echo '    <lastmod>' . e($en['date']) . "</lastmod>\n";
        echo "  </url>\n";
    }
}
echo '</urlset>' . "\n";
