<?php
/*
 * Orijinal caucasuscl.com ilə lokal saytı müqayisə edir:
 *   - hər orijinal ünvan bizdə 200 qaytarırmı
 *   - <title> eynidirmi
 *   - meta description boş deyilmi
 *
 *   php tools/verify-seo.php [http://localhost:8010]
 */
declare(strict_types=1);
set_time_limit(900);
$base = $argv[1] ?? 'http://localhost:8010';
const SRC = 'https://caucasuscl.com';

function grab(string $u, int $t = 25): array {
    $ctx = stream_context_create(['http' => [
        'timeout' => $t, 'ignore_errors' => true,
        'header' => "User-Agent: Mozilla/5.0 (compatible; verify)\r\n",
        'follow_location' => 1, 'max_redirects' => 5,
    ]]);
    $b = @file_get_contents($u, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int)$m[1];
    return [$code, is_string($b) ? $b : ''];
}
function title(string $h): string {
    return preg_match('~<title>([\s\S]*?)</title>~i', $h, $m)
        ? trim(preg_replace('/\s+/u', ' ', html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'))) : '';
}
function desc(string $h): string {
    return preg_match('~<meta\s+name="description"\s+content="([^"]*)"~i', $h, $m)
        ? trim(preg_replace('/\s+/u', ' ', html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'))) : '';
}

/* orijinal ünvanlar */
[$c, $idx] = grab(SRC . '/sitemap_index.xml');
$urls = [];
foreach (preg_match_all('~<loc>([^<]+)</loc>~', $idx, $mm) ? $mm[1] : [] as $sm) {
    [$c2, $x] = grab($sm);
    if (preg_match_all('~<loc>([^<]+)</loc>~', $x, $m2)) {
        foreach ($m2[1] as $u) $urls[] = str_replace(SRC, '', $u);
    }
}
$urls = array_values(array_unique($urls));
echo "Orijinal ünvan: " . count($urls) . "\n\n";

$bad = [];
$ok = 0;
foreach ($urls as $u) {
    if ($u === '/category/xeberler/') continue;      // 301 gözlənilir
    [$oc, $oh] = grab(SRC . $u);
    [$mc, $mh] = grab($base . $u);

    if ($mc !== 200) { $bad[] = "HTTP $mc   $u"; continue; }
    $ot = title($oh); $mt = title($mh);
    $md = desc($mh);
    if ($mt === '')        { $bad[] = "TITLE-BOŞ   $u"; continue; }
    if ($ot !== '' && $mt !== $ot) {
        $bad[] = "TITLE-FƏRQ  $u\n      orijinal: " . mb_substr($ot, 0, 80) . "\n      bizdə   : " . mb_substr($mt, 0, 80);
        continue;
    }
    if ($md === '')        { $bad[] = "DESC-BOŞ    $u"; continue; }
    $ok++;
}

echo "Uyğun: $ok\n";
echo "Problem: " . count($bad) . "\n";
foreach ($bad as $b) echo "  $b\n";

/* 301 yoxlaması */
echo "\nYönləndirmə yoxlaması:\n";
foreach (['/category/xeberler/', '/xeberler/', '/haqqimizda', '/ru/xeberler/'] as $u) {
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true, 'follow_location' => 0]]);
    @file_get_contents($base . $u, false, $ctx);
    $code = 0; $loc = '';
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int)$m[1];
        if (stripos($h, 'Location:') === 0) $loc = trim(substr($h, 9));
    }
    printf("  %-24s %s %s\n", $u, $code, $loc);
}
