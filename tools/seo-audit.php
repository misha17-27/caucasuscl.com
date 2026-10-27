<?php
/*
 * SEO auditi — nəyin olduğunu və nəyin çatışmadığını göstərir.
 *   php tools/seo-audit.php [https://caucasuscl.com]
 */
declare(strict_types=1);
set_time_limit(1800);
$base = rtrim($argv[1] ?? 'https://caucasuscl.com', '/');

function get(string $u, bool $follow = true): array {
    $ctx = stream_context_create([
        'http' => ['timeout' => 30, 'ignore_errors' => true,
                   'follow_location' => $follow ? 1 : 0, 'max_redirects' => $follow ? 5 : 1,
                   'header' => "User-Agent: Mozilla/5.0 (compatible; seoaudit)\r\n"],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $b = @file_get_contents($u, false, $ctx);
    $code = 0; $loc = '';
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int)$m[1];
        if (preg_match('~^Location:\s*(.+)$~i', $h, $m)) $loc = trim($m[1]);
    }
    return [$code, is_string($b) ? $b : '', $loc];
}

$ok = 0; $miss = [];
function good(string $l, string $n = '') { global $ok; $ok++; printf("  OK   %-44s %s\n", $l, $n); }
function bad(string $l, string $n = '')  { global $miss; $miss[] = $l; printf("  YOX  %-44s %s\n", $l, $n); }
function chk(bool $c, string $l, string $n = '') { $c ? good($l, $n) : bad($l, $n); }

[$c, $home] = get($base . '/');
[, $svc]    = get($base . '/xidmetler/logistika-xidmetleri/');
[, $news]   = get($base . '/ozunet-layihesinin-teqdimati-kecirilib/');
[, $contact]= get($base . '/elaqe/');

echo "SEO AUDİT — $base\n";

/* ---------- 1. Əsas meta ---------- */
echo "\n1) Əsas meta teqlər\n";
$tLen = preg_match('~<title>([^<]*)</title>~i', $home, $m) ? mb_strlen(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')) : 0;
chk($tLen >= 10 && $tLen <= 65, 'title uzunluğu 10-65', $tLen . ' simvol');
$dLen = preg_match('~name="description" content="([^"]*)"~i', $home, $m) ? mb_strlen(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')) : 0;
chk($dLen >= 50 && $dLen <= 175, 'description 50-175', $dLen . ' simvol');
chk(str_contains($home, 'rel="canonical"'), 'canonical');
chk(substr_count($home, 'hreflang=') >= 4, 'hreflang + x-default');
chk((bool)preg_match('~<html lang="[a-z]{2}"~i', $home), 'html lang');
chk(str_contains($home, 'name="viewport"'), 'viewport');
chk(str_contains($home, 'name="theme-color"'), 'theme-color (brauzer rəngi)');

/* ---------- 2. Sosial paylaşım ---------- */
echo "\n2) Sosial şəbəkə kartı (Open Graph)\n";
preg_match('~property="og:image" content="([^"]+)"~i', $home, $ogi);
$ogImg = $ogi[1] ?? '';
chk(str_contains($home, 'og:title'), 'og:title');
chk(str_contains($home, 'og:url'), 'og:url');
chk($ogImg !== '', 'og:image', $ogImg);
$isSvg = str_ends_with(strtolower($ogImg), '.svg');
chk(!$isSvg, 'og:image rastr formatdadır (jpg/png)',
    $isSvg ? 'SVG — Facebook/WhatsApp/Telegram göstərmir!' : '');
chk(str_contains($home, 'og:image:width'), 'og:image:width/height');
chk(str_contains($home, 'og:locale:alternate'), 'og:locale:alternate');
/* xidmət və xəbərdə öz şəkli olmalıdır */
preg_match('~property="og:image" content="([^"]+)"~i', $svc, $s2);
chk(($s2[1] ?? '') !== $ogImg, 'xidmət səhifəsində öz og:image-i', $s2[1] ?? '');
/* Xəbərlərdə şəkil ümumidir — qəsdən brendli kart işlədilir. */
preg_match('~property="og:image" content="([^"]+)"~i', $news, $n2);
chk(!str_ends_with(strtolower($n2[1] ?? ''), '.svg') && ($n2[1] ?? '') !== '',
    'xəbər səhifəsində paylaşım şəkli', basename($n2[1] ?? ''));

/* ---------- 3. Strukturlaşdırılmış məlumat ---------- */
echo "\n3) Strukturlaşdırılmış məlumat (JSON-LD)\n";
$hasLd = static fn(string $h, string $type): bool =>
    (bool)preg_match('~application/ld\+json~', $h) && str_contains($h, '"' . $type . '"');
chk((bool)preg_match('~application/ld\+json~', $home), 'JSON-LD ümumiyyətlə var');
chk($hasLd($home, 'Organization') || $hasLd($home, 'LocalBusiness') || $hasLd($home, 'ProfessionalService'),
    'Organization / LocalBusiness');
chk($hasLd($home, 'WebSite'), 'WebSite (axtarış qutusu)');
chk($hasLd($svc, 'BreadcrumbList'), 'BreadcrumbList (naviqasiya zənciri)');
chk($hasLd($svc, 'Service'), 'Service (xidmət səhifəsi)');
chk($hasLd($news, 'Article') || $hasLd($news, 'NewsArticle'), 'Article (xəbər)');
chk($hasLd($contact, 'LocalBusiness') || $hasLd($contact, 'PostalAddress'), 'ünvan / iş yeri (əlaqə)');

/* ---------- 4. Şəkillər ---------- */
echo "\n4) Şəkillər\n";
preg_match_all('~<img\b[^>]*>~i', $home, $imgs);
$noAlt = 0; $noDim = 0; $noLazy = 0;
foreach ($imgs[0] as $t) {
    if (!preg_match('~\balt=~i', $t)) $noAlt++;
    if (!preg_match('~\bwidth=~i', $t) || !preg_match('~\bheight=~i', $t)) $noDim++;
    if (!preg_match('~\bloading=~i', $t)) $noLazy++;
}
$tot = count($imgs[0]);
chk($noAlt === 0, "alt atributu ($tot şəkildən)", $noAlt ? "$noAlt-də yoxdur" : '');
chk($noDim <= 2, 'width/height (yerdəyişmə olmasın)', $noDim ? "$noDim-də yoxdur" : '');
chk($noLazy <= 3, 'loading="lazy"', $noLazy ? "$noLazy-də yoxdur" : '');

/* ---------- 5. Başlıqlar ---------- */
echo "\n5) Başlıq iyerarxiyası\n";
foreach (['ana səhifə' => $home, 'xidmət' => $svc, 'xəbər' => $news] as $lbl => $h) {
    $h1 = preg_match_all('~<h1\b~i', $h);
    chk($h1 === 1, "$lbl — bir dənə <h1>", "$h1 ədəd");
}

/* ---------- 6. Yönləndirmələr ---------- */
echo "\n6) Yönləndirmələr və domen\n";
[$c1, , $l1] = get('http://caucasuscl.com/', false);
chk($c1 === 301 || $c1 === 308, 'http -> https', "kod $c1 " . $l1);
[$c2, , $l2] = get('https://www.caucasuscl.com/', false);
chk(in_array($c2, [301, 308], true) || $c2 === 200, 'www variantı', "kod $c2 " . $l2);
[$c3, , $l3] = get($base . '/haqqimizda', false);
chk($c3 === 301, 'slash-sız -> slash-lı', "kod $c3");
[$c4] = get($base . '/yoxdur-bele-sehife/', false);
chk($c4 === 404, 'olmayan səhifə 404 qaytarır', "kod $c4");

/* ---------- 7. Texniki ---------- */
echo "\n7) Texniki\n";
[$rc, $rx] = get($base . '/robots.txt');
chk($rc === 200 && str_contains($rx, 'Sitemap:'), 'robots.txt + Sitemap sətri');
[$sc, $sx] = get($base . '/sitemap.xml');
chk($sc === 200, 'sitemap.xml', substr_count($sx, '<loc>') . ' ünvan');
chk(!str_contains($home, 'name="keywords"'), 'keywords meta yoxdur (köhnəlib)');
chk((bool)preg_match('~<link[^>]+rel="icon"~i', $home), 'favicon');
chk((bool)preg_match('~apple-touch-icon~i', $home), 'apple-touch-icon');
chk(str_contains($home, 'googletagmanager'), 'analitika qoşulub');

echo "\n" . str_repeat('-', 62) . "\n";
echo "Var: $ok, çatışmır: " . count($miss) . "\n";
if ($miss) { echo "\nÇatışmayanlar:\n"; foreach ($miss as $m) echo "  · $m\n"; }
