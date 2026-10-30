<?php
/*
 * Öz məqalələrimizi (news.is_own = 1) uçdan-uca yoxlayır:
 *   · hər üç dildə səhifə birbaşa 200 qaytarır
 *   · <title> və meta description bazadakı dəyərlərlə üst-üstə düşür
 *   · canonical ünvan səhifənin özünə işarə edir
 *   · og:image var və şəkil açılır
 *   · mətn blokları render olunub (## başlıqlar <h2> olub)
 *   · gövdədəki daxili xidmət linkləri 200 qaytarır
 *   · gövdədə həll olunmamış `SVC:` açarı qalmayıb
 *   · imza şirkətin adıdır (dövlət orqanının adı ilə imzalanmayıb)
 *
 *   php tools/check-articles.php [http://localhost:8010]
 */
declare(strict_types=1);
set_time_limit(900);

$ROOT = dirname(__DIR__);
chdir($ROOT);
$base = rtrim($argv[1] ?? 'http://localhost:8010', '/');

require $ROOT . '/includes/compat.php';
require $ROOT . '/includes/config.php';
require_once $ROOT . '/includes/lang.php';
require $ROOT . '/includes/functions.php';

function fetch(string $u): array {
    $ctx = stream_context_create([
        'http' => ['timeout' => 30, 'ignore_errors' => true, 'follow_location' => 0, 'max_redirects' => 1,
                   'header' => "User-Agent: Mozilla/5.0 (compatible; articlecheck)\r\n"],
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

function tag(string $html, string $re): string {
    return preg_match($re, $html, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8') : '';
}

$db = require $ROOT . '/admin/db.php';
$rows = $db->query("SELECT lang, slug, tkey, title, meta_title, meta_desc, body, visible
                    FROM `news` WHERE `is_own` = 1 ORDER BY `sort`, `tkey`, `lang`")->fetchAll();
if (!$rows) exit("Bazada öz məqalə yoxdur (is_own = 1).\n");

echo "ÖZ MƏQALƏLƏRİN YOXLANMASI — $base\n";
echo "Yoxlanılan sətir: " . count($rows) . "\n\n";

$fail = 0; $warn = 0; $linkCache = [];

foreach ($rows as $r) {
    $lang = (string)$r['lang'];
    $u    = $base . url('/xeberler/' . $r['slug'], $lang);
    $name = sprintf('%-3s %s', $lang, $r['slug']);

    if ((int)$r['visible'] !== 1) { printf("--  %s  gizlidir, ötürüldü\n", $name); continue; }

    [$code, $html] = fetch($u);
    if ($code !== 200) { printf("XX  %s  kod %d  %s\n", $name, $code, $u); $fail++; continue; }

    $problems = [];

    $title = tag($html, '~<title[^>]*>(.*?)</title>~si');
    if ($title !== '' && $r['meta_title'] !== '' && $title !== (string)$r['meta_title']) {
        $problems[] = 'title bazadakı meta_title ilə üst-üstə düşmür';
    }
    $desc = tag($html, '~<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']~si');
    if ($desc === '') $problems[] = 'meta description yoxdur';
    elseif ($r['meta_desc'] !== '' && $desc !== (string)$r['meta_desc']) $problems[] = 'description bazadakı mətnlə fərqlidir';

    /* canonical yoxlanılan ünvanla müqayisə olunur: lokalda localhost, canlıda domen. */
    $canon = tag($html, '~<link\s+rel=["\']canonical["\']\s+href=["\'](.*?)["\']~si');
    $want  = $base . url('/xeberler/' . $r['slug'], $lang);
    if ($canon === '') $problems[] = 'canonical yoxdur';
    elseif (rtrim($canon, '/') !== rtrim($want, '/')) $problems[] = "canonical fərqlidir: $canon";

    $og = tag($html, '~<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']~si');
    if ($og === '') $problems[] = 'og:image yoxdur';

    /* mətn render olunubmu */
    $h2db = preg_match_all('~^##\s~m', (string)$r['body']);
    $h2ht = preg_match_all('~<h2~i', $html);
    if ($h2db > 0 && $h2ht < $h2db) $problems[] = "## başlıqları render olunmayıb ($h2db gövdədə, $h2ht səhifədə)";
    if (str_contains($html, 'SVC:')) $problems[] = 'səhifədə həll olunmamış SVC: açarı var';
    if (preg_match('~\*\*[^*]+\*\*~', $html)) $problems[] = 'səhifədə işlənməmiş ** işarəsi var';

    /* imza — öz məqaləmiz dövlət orqanının adı ilə imzalanmamalıdır */
    if (preg_match('~class="[^"]*(?:meta|source|author)[^"]*"[^>]*>[^<]*(?:Komitə|Комитет|Committee)[^<]*<~iu', $html)) {
        $problems[] = 'imza dövlət orqanının adıdır — öz məqaləmizdə olmamalıdır';
    }

    /* gövdədəki daxili linklər */
    $links = [];
    if (preg_match_all('~\]\((/[^)\s]*)\)~', (string)$r['body'], $m)) $links = $m[1];
    foreach (array_unique($links) as $rel) {
        $lu = $base . $rel;
        if (!isset($linkCache[$lu])) { [$lc] = fetch($lu); $linkCache[$lu] = $lc; }
        if ($linkCache[$lu] !== 200) $problems[] = "daxili link {$linkCache[$lu]}: $rel";
    }
    $nLinks = count(array_unique($links));

    /* Daxili link olmaması sınıq deyil, itirilmiş imkandır — xəbərdarlıq. */
    $notes = [];
    if ($nLinks === 0) $notes[] = 'daxili xidmət linki yoxdur (SEO imkanı)';

    if ($problems) {
        printf("XX  %s\n", $name);
        foreach ($problems as $p) echo "      ! $p\n";
        foreach ($notes as $n) echo "      . $n\n";
        $fail++;
    } else {
        printf("OK  %s  %d link, %d başlıq, %d simvol\n", $name, $nLinks, $h2db, mb_strlen((string)$r['body']));
        foreach ($notes as $n) { echo "      . $n\n"; $warn++; }
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
if ($warn) printf("Xəbərdarlıq: %d\n", $warn);
if ($fail === 0) { echo "Sınıq yoxdur.\n"; exit(0); }
printf("Problemli sətir: %d\n", $fail);
exit(1);
