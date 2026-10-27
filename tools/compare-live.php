<?php
/*
 * Lokal saytı canlı serverlə müqayisə edir: status, <title>, meta description.
 * Ünvanlar lokal sitemap-dan götürülür.
 *
 *   php tools/compare-live.php [http://localhost:8010] [https://caucasuscl.com]
 */
declare(strict_types=1);
set_time_limit(1800);

$local = rtrim($argv[1] ?? 'http://localhost:8010', '/');
$live  = rtrim($argv[2] ?? 'https://caucasuscl.com', '/');

function grab(string $u, int $t = 30): array {
    $ctx = stream_context_create([
        'http' => ['timeout' => $t, 'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 5,
                   'header' => "User-Agent: Mozilla/5.0 (compatible; compare)\r\n"],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $b = @file_get_contents($u, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int)$m[1];
    return [$code, is_string($b) ? $b : ''];
}
function pick(string $h, string $re): string {
    return preg_match($re, $h, $m)
        ? trim(preg_replace('/\s+/u', ' ', html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'))) : '';
}
$title = static fn(string $h): string => pick($h, '~<title>([\s\S]*?)</title>~i');
$desc  = static fn(string $h): string => pick($h, '~<meta\s+name="description"\s+content="([^"]*)"~i');

/* ünvanlar lokal sitemap-dan */
[$c, $xml] = grab($local . '/sitemap.xml');
if ($c !== 200) exit("Lokal sitemap açılmadı ($c)\n");
preg_match_all('~<loc>([^<]+)</loc>~', $xml, $m);
$paths = [];
foreach ($m[1] as $u) $paths[] = parse_url($u, PHP_URL_PATH);
$paths = array_values(array_unique($paths));
echo count($paths) . " ünvan yoxlanılır\n\n";

$bad = 0; $i = 0;
foreach ($paths as $p) {
    $i++;
    [$lc, $lh] = grab($local . $p);
    [$vc, $vh] = grab($live . $p);
    $problems = [];
    if ($vc !== 200)                   $problems[] = "status $vc";
    if ($title($lh) !== $title($vh))   $problems[] = 'başlıq fərqlidir';
    if ($desc($lh) !== $desc($vh))     $problems[] = 'description fərqlidir';
    if ($desc($vh) === '')             $problems[] = 'description boşdur';
    if (preg_match('~Fatal error|Warning:|Notice:|Deprecated:~', $vh)) $problems[] = 'PHP xətası';

    if ($problems) {
        $bad++;
        echo "XX  $p\n    " . implode('; ', $problems) . "\n";
        if (in_array('başlıq fərqlidir', $problems, true)) {
            echo "    lokal: " . $title($lh) . "\n    canlı: " . $title($vh) . "\n";
        }
    } elseif ($i % 25 === 0) {
        echo "    ... $i/" . count($paths) . " OK\n";
    }
}
echo "\nYoxlanıldı: " . count($paths) . ", problem: $bad\n";
