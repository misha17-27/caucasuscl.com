<?php
/*
 * Canlı saytın struktur yoxlaması: meta teqlər, loqolar, menyu, kapça,
 * hreflang, canonical, şəkillərin açılması.
 *
 *   php tools/check-live.php [https://caucasuscl.com]
 */
declare(strict_types=1);
set_time_limit(900);
$base = rtrim($argv[1] ?? 'https://caucasuscl.com', '/');

function grab(string $u, bool $head = false): array {
    $ctx = stream_context_create([
        'http' => ['timeout' => 30, 'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 5,
                   'method' => $head ? 'HEAD' : 'GET',
                   'header' => "User-Agent: Mozilla/5.0 (compatible; check)\r\n"],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $b = @file_get_contents($u, false, $ctx);
    $code = 0; $len = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int)$m[1];
        if (preg_match('~^Content-Length:\s*(\d+)~i', $h, $m)) $len = (int)$m[1];
    }
    return [$code, is_string($b) ? $b : '', $len];
}

$fail = 0;
$check = static function (string $label, bool $ok, string $note = '') use (&$fail): void {
    if (!$ok) $fail++;
    printf("  %s %-46s %s\n", $ok ? 'OK ' : 'XX ', $label, $note);
};

/* ---------------- 1. Ana səhifə ---------------- */
echo "1) Ana səhifə\n";
[$c, $h] = grab($base . '/');
$check('status 200', $c === 200, "kod $c");
$check('<title> var', (bool)preg_match('~<title>[^<]{10,}</title>~i', $h));
$check('meta description var', (bool)preg_match('~<meta name="description" content="[^"]{20,}"~i', $h));
$check('canonical mütləq ünvandır', (bool)preg_match('~rel="canonical" href="https?://~i', $h));
$check('hreflang mütləqdir', substr_count($h, 'hreflang="az" href="https') > 0);
$check('og:title var', (bool)preg_match('~property="og:title"~i', $h));
$check('hreflang az/ru/en', substr_count($h, 'hreflang=') >= 3, substr_count($h, 'hreflang=') . ' ədəd');
$check('Montserrat şrifti', str_contains($h, 'Montserrat'));
$check('header loqosu 198x80', (bool)preg_match('~<img[^>]+width="198"[^>]+height="80"~', $h)
    || (bool)preg_match('~<img[^>]+height="80"[^>]+width="198"~', $h));
$check('futer loqosu 149x60', (bool)preg_match('~<img[^>]+width="149"[^>]+height="60"~', $h)
    || (bool)preg_match('~<img[^>]+height="60"[^>]+width="149"~', $h));
$check('mobil menyu (nav.main)', str_contains($h, 'class="main"') || str_contains($h, 'nav class="main'));
$check('dil seçici (langsel)', str_contains($h, 'langsel'));
$check('WhatsApp düyməsi', str_contains($h, 'api.whatsapp.com'));
$check('PHP xətası yoxdur', !preg_match('~Fatal error|Warning:|Notice:|Deprecated:~', $h));
$check('WordPress izi yoxdur', !preg_match('~wp-content/(?!uploads)|wp-includes|wp-json~', $h));

/* ---------------- 2. Forma və kapça ---------------- */
echo "\n2) Əlaqə forması\n";
[$c2, $h2] = grab($base . '/elaqe/');
$check('status 200', $c2 === 200, "kod $c2");
$check('forma var', (bool)preg_match('~<form[^>]+method="post"~i', $h2));
preg_match('~data-sitekey="([^"]+)"~', $h2, $sk);
$key = $sk[1] ?? '';
$check('Turnstile real açar', $key !== '' && strpos($key, '1x000000') !== 0, $key);
$check('Turnstile skripti', str_contains($h2, 'challenges.cloudflare.com'));
$check('honeypot sahəsi', (bool)preg_match('~name="(website|url|_hp|company)"~i', $h2));

/* ---------------- 3. Xidmət və xəbər ---------------- */
echo "\n3) Daxili səhifələr\n";
foreach ([
    '/xidmetler/logistika-xidmetleri/'      => 'AZ xidmət',
    '/ru/xidmetler/logistika-xidmeti/'       => 'RU xidmət',
    '/en/services/logistika-xidmetleri/'     => 'EN xidmət',
    '/ozunet-layihesinin-teqdimati-kecirilib/'            => 'AZ xəbər',
    '/ru/novosti/ozunet-layihesinin-teqdimati-kecirilib/' => 'RU xəbər',
    '/en/news/ozunet-layihesinin-teqdimati-kecirilib/'    => 'EN xəbər',
] as $p => $label) {
    [$cc, $hh] = grab($base . $p);
    $t = preg_match('~<title>([^<]*)</title>~i', $hh, $m) ? trim($m[1]) : '';
    $body = preg_match('~<article|class="prose|class="body~i', $hh);
    $check($label, $cc === 200 && $t !== '' && !preg_match('~Fatal error|Warning:~', $hh),
        mb_substr($t, 0, 44));
}

/* ---------------- 4. Statik fayllar ---------------- */
echo "\n4) Statik resurslar\n";
preg_match_all('~(?:src|href)="(/assets/[^"]+)"~', $h, $mm);
$assets = array_slice(array_unique($mm[1]), 0, 12);
$bad = [];
foreach ($assets as $a) {
    [$ac] = grab($base . $a, true);
    if ($ac !== 200) $bad[] = "$a ($ac)";
}
$check(count($assets) . ' resurs açılır', !$bad, $bad ? implode(', ', $bad) : '');

/* ---------------- 5. Sitemap və robots ---------------- */
echo "\n5) SEO faylları\n";
[$sc, $sx] = grab($base . '/sitemap.xml');
$n = substr_count($sx, '<loc>');
$check('sitemap.xml', $sc === 200 && $n >= 150, "$n ünvan");
[$ic, $ix] = grab($base . '/sitemap_index.xml');
$check('sitemap_index.xml', $ic === 200 && str_contains($ix, 'sitemapindex'));
[$rc, $rx] = grab($base . '/robots.txt');
$check('robots.txt', $rc === 200 && str_contains($rx, 'Sitemap:'));
$check('sitemap sadə formatdadır', !str_contains($sx, 'changefreq') && !str_contains($sx, 'xhtml:link'));
$check('xəbərlərdə lastmod var', substr_count($sx, '<lastmod>') >= 100, substr_count($sx, '<lastmod>') . ' ədəd');

/* ---------------- 6. Təhlükəsizlik ---------------- */
echo "\n6) Bağlı olmalı olan yollar\n";
foreach (['/admin/config.php', '/admin/db.php', '/includes/config.php', '/storage/',
          '/wp-config.php', '/wp-login.php', '/wp-admin/'] as $p) {
    [$pc] = grab($base . $p, true);
    $check($p, in_array($pc, [403, 404, 410], true), "kod $pc");
}

echo "\n" . ($fail ? "PROBLEM: $fail\n" : "Hamısı qaydasındadır.\n");
