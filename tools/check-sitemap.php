<?php
/*
 * Sitemap-ın düzgünlüyünü yoxlayır. Axtarış sistemləri üçün əsas tələblər:
 *   · hər ünvan BİRBAŞA 200 qaytarmalıdır (yönləndirmə olmamalıdır)
 *   · səhifənin canonical-ı sitemap-dakı ünvanla eyni olmalıdır
 *   · noindex olan səhifə sitemap-da olmamalıdır
 *   · təkrar ünvan olmamalıdır
 *   · lastmod gələcək tarix olmamalıdır
 *   · XML sxemə uyğun olmalıdır
 *
 *   php tools/check-sitemap.php [https://caucasuscl.com]
 */
declare(strict_types=1);
set_time_limit(1800);
$base = rtrim($argv[1] ?? 'https://caucasuscl.com', '/');

function fetch(string $u, bool $follow = false): array {
    $ctx = stream_context_create([
        'http' => ['timeout' => 30, 'ignore_errors' => true,
                   'follow_location' => $follow ? 1 : 0, 'max_redirects' => $follow ? 5 : 1,
                   'header' => "User-Agent: Mozilla/5.0 (compatible; sitemapcheck)\r\n"],
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

echo "SITEMAP YOXLAMASI — $base/sitemap.xml\n\n";

[$c, $xml] = fetch($base . '/sitemap.xml', true);
if ($c !== 200) exit("sitemap açılmadı (kod $c)\n");

/* --- XML strukturu --- */
libxml_use_internal_errors(true);
$doc = simplexml_load_string($xml);
if ($doc === false) {
    echo "XX  XML sintaksisi pozulub:\n";
    foreach (libxml_get_errors() as $e) echo '    ' . trim($e->message) . "\n";
    exit(1);
}
echo "OK  XML sintaksisi düzgündür\n";
echo 'OK  kök element: ' . $doc->getName() . "\n";
$ns = $doc->getDocNamespaces();
echo 'OK  ad fəzası: ' . ($ns[''] ?? '(yoxdur)') . "\n";

/* --- ünvanların yığılması --- */
$urls = [];
$lastmods = [];
foreach ($doc->url as $u) {
    $loc = trim((string)$u->loc);
    $urls[] = $loc;
    if (isset($u->lastmod)) $lastmods[$loc] = trim((string)$u->lastmod);
}
$total = count($urls);
echo "OK  ünvan sayı: $total\n";
printf("OK  fayl ölçüsü: %.1f KB (hədd 50 MB)\n", strlen($xml) / 1024);

$fail = 0;
$say = static function (string $m) use (&$fail) { $fail++; echo "XX  $m\n"; };

/* --- təkrarlar --- */
$dupes = array_filter(array_count_values($urls), static fn($n) => $n > 1);
if ($dupes) { foreach ($dupes as $u => $n) $say("təkrar ünvan ($n dəfə): $u"); }
else echo "OK  təkrar ünvan yoxdur\n";

/* --- gələcək tarixlər --- */
$today = date('Y-m-d');
$future = array_filter($lastmods, static fn($d) => $d > $today);
if ($future) { foreach ($future as $u => $d) $say("lastmod gələcəkdədir ($d): $u"); }
else echo 'OK  lastmod tarixləri düzgündür (' . count($lastmods) . " ədəd)\n";

/* --- hamısı eyni domendə və https --- */
$hostBad = array_filter($urls, static fn($u) => !str_starts_with($u, $base . '/') && $u !== $base . '/');
if ($hostBad) { foreach (array_slice($hostBad, 0, 5) as $u) $say("kənar domen/sxem: $u"); }
else echo "OK  bütün ünvanlar eyni domendə və https-dədir\n";

/* --- hər ünvan: 200 + canonical uyğunluğu + noindex yoxdur --- */
echo "\nÜnvanlar yoxlanılır...\n";
$redir = $err = $canon = $noindex = 0;
$i = 0;
foreach ($urls as $u) {
    $i++;
    [$code, $body, $loc] = fetch($u, false);

    if ($code >= 300 && $code < 400) {
        $redir++; $fail++;
        echo "XX  $code yönləndirmə: $u\n    -> $loc\n";
        continue;
    }
    if ($code !== 200) {
        $err++; $fail++;
        echo "XX  status $code: $u\n";
        continue;
    }
    if (preg_match('~<meta\s+name="robots"\s+content="([^"]*)"~i', $body, $m)
        && stripos($m[1], 'noindex') !== false) {
        $noindex++; $fail++;
        echo "XX  noindex, amma sitemap-dadır: $u\n";
    }
    if (preg_match('~<link\s+rel="canonical"\s+href="([^"]+)"~i', $body, $m)) {
        if (rtrim($m[1], '/') !== rtrim($u, '/')) {
            $canon++; $fail++;
            echo "XX  canonical fərqlidir: $u\n    canonical: {$m[1]}\n";
        }
    } else {
        $canon++; $fail++;
        echo "XX  canonical yoxdur: $u\n";
    }
    if ($i % 30 === 0) echo "    ... $i/$total\n";
}

echo "\n" . str_repeat('-', 62) . "\n";
printf("Yoxlanıldı: %d ünvan\n", $total);
printf("  yönləndirmə: %d, xəta: %d, canonical uyğunsuzluğu: %d, noindex: %d\n",
    $redir, $err, $canon, $noindex);
echo $fail ? "PROBLEM: $fail\n" : "Sitemap tam qaydasındadır.\n";
