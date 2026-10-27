<?php
/*
 * WordPress-dən qalan tipik ünvanların yoxlanması.
 * Google köhnə saytdan yüzlərlə belə ünvanı indeksləmişdi; indi onların
 * hansı 404 verdiyini və hansının yönləndirilməli olduğunu göstərir.
 *
 *   php tools/check-legacy.php [https://caucasuscl.com]
 */
declare(strict_types=1);
set_time_limit(1800);
$base = rtrim($argv[1] ?? 'https://caucasuscl.com', '/');

function head(string $u): array {
    $ctx = stream_context_create([
        'http' => ['method' => 'HEAD', 'timeout' => 20, 'ignore_errors' => true,
                   'follow_location' => 0, 'max_redirects' => 1,
                   'header' => "User-Agent: Mozilla/5.0 (compatible; Googlebot-check)\r\n"],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    @file_get_contents($u, false, $ctx);
    $code = 0; $loc = '';
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int)$m[1];
        if (preg_match('~^Location:\s*(.+)$~i', $h, $m)) $loc = trim($m[1]);
    }
    return [$code, $loc];
}

/* Bir neçə real xəbər slug-ı — attachment/comment ünvanları üçün */
$slug1 = 'xarici-valyuta-askarlandi';
$slug2 = 'ozunet-layihesinin-teqdimati-kecirilib';

$groups = [
    'Lent (feed)' => [
        '/feed/', '/rss/', '/rss2/', '/comments/feed/',
        "/$slug1/feed/", '/gomruk-xeberleri/feed/', '/ru/feed/',
    ],
    'Kateqoriya və etiket' => [
        '/category/xeberler/', '/category/uncategorized/', '/category/gomruk/',
        '/tag/gomruk/', '/tag/logistika/', '/etiket/gomruk/',
    ],
    'Səhifələmə' => [
        '/gomruk-xeberleri/page/2/', '/gomruk-xeberleri/page/3/',
        '/xeberler/page/2/', '/page/2/', '/ru/novosti/page/2/',
    ],
    'Müəllif' => [
        '/author/admin/', '/author/caucasus/', '/muellif/admin/',
    ],
    'Qoşma (attachment)' => [
        "/$slug1/attachment/1/", "/$slug2/gomruk/",
        '/?attachment_id=123',
    ],
    'WordPress texniki' => [
        '/wp-json/', '/wp-json/wp/v2/posts', '/xmlrpc.php', '/wp-login.php',
        '/wp-admin/', '/wp-content/themes/', '/wp-includes/', '/wp-cron.php',
        '/?p=1', '/?page_id=2', '/?cat=1', '/?s=gomruk',
    ],
    'Köhnə dil prefiksləri' => [
        '/az/', '/az/haqqimizda/', '/en/haqqimizda/', '/ru/haqqimizda/',
        '/az/xeberler/', '/ru/xeberler/', '/en/xeberler/',
    ],
    'Köhnə bölmə adları' => [
        '/xeberler/', '/linkler/', '/xidmetler/gomruk-beyannamelerinin-tertib-olunmasi/',
        '/ru/linkler/', '/en/linkler/', '/elaqe-2/', '/haqqimizda-2/',
    ],
    'Sitemap variantları' => [
        '/sitemap_index.xml', '/post-sitemap.xml', '/page-sitemap.xml',
        '/category-sitemap.xml', '/author-sitemap.xml', '/sitemap.xml',
    ],
    'Media' => [
        '/wp-content/uploads/1.jpg', '/wp-content/uploads/2022/', '/wp-content/uploads/',
    ],
];

$stat = ['200' => 0, '301' => 0, '404' => 0, 'digər' => 0];
$needFix = [];

foreach ($groups as $title => $urls) {
    echo "\n$title\n";
    foreach ($urls as $p) {
        [$code, $loc] = head($base . $p);
        $key = in_array($code, [200, 301, 404], true) ? (string)$code : 'digər';
        $stat[$key]++;
        $mark = $code === 404 ? '404' : ($code === 200 ? 'OK ' : (string)$code);
        printf("  %-4s %-52s %s\n", $mark, $p, $loc !== '' ? '-> ' . $loc : '');
        if ($code === 404) $needFix[] = $p;
        if ($code >= 500) $needFix[] = $p . '  (SERVER XƏTASI)';
    }
}

echo "\n" . str_repeat('-', 62) . "\n";
printf("200: %d, 301: %d, 404: %d, digər: %d\n",
    $stat['200'], $stat['301'], $stat['404'], $stat['digər']);
if ($needFix) {
    echo "\n404 verən ünvanlar (" . count($needFix) . "):\n";
    foreach ($needFix as $u) echo "  · $u\n";
}
