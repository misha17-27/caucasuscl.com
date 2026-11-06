<?php
/*
 * Xidmət səhifələrini uçdan-uca yoxlayır:
 *   · hər üç dildə səhifə birbaşa 200 qaytarır
 *   · <title> və meta description bazadakı dəyərlərlə üst-üstə düşür
 *   · canonical səhifənin özünə işarə edir
 *   · gövdə blokları render olunub (## -> <h2>, kartlar, siyahılar)
 *   · gövdədəki hər daxili keçid 200 qaytarır
 *   · FAQ bloku görünür və FAQPage nişanı ona uyğundur
 *     (Google nişanın səhifədə görünən məzmunla üst-üstə düşməsini tələb edir)
 *   · həll olunmamış SVC:/ART: açarı qalmayıb
 *
 *   php tools/check-services.php [http://localhost:8010]
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
                   'header' => "User-Agent: Mozilla/5.0 (compatible; servicecheck)\r\n"],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $b = @file_get_contents($u, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h)
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int)$m[1];
    return [$code, is_string($b) ? $b : ''];
}
function tag(string $h, string $re): string {
    return preg_match($re, $h, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8') : '';
}

$db = require $ROOT . '/admin/db.php';
$rows = $db->query('SELECT lang, slug, tkey, title, meta_title, meta_desc, body, faq, visible
                    FROM `services` ORDER BY `sort`, `tkey`, `lang`')->fetchAll();
if (!$rows) exit("services cədvəli boşdur.\n");

echo "XİDMƏT SƏHİFƏLƏRİNİN YOXLANMASI — $base\nSətir: " . count($rows) . "\n\n";

$fail = $warn = 0; $linkCache = [];

foreach ($rows as $r) {
    $lang = (string)$r['lang'];
    $u    = $base . url('/xidmetler/' . $r['slug'], $lang);
    $name = sprintf('%-3s %s', $lang, mb_substr((string)$r['tkey'], 0, 28));

    if ((int)$r['visible'] !== 1) { printf("--  %s  gizlidir\n", $name); continue; }

    [$code, $html] = fetch($u);
    if ($code !== 200) { printf("XX  %s  kod %d\n", $name, $code); $fail++; continue; }

    $p = [];

    $title = tag($html, '~<title[^>]*>(.*?)</title>~si');
    if ($r['meta_title'] !== '' && $title !== (string)$r['meta_title']) $p[] = 'title bazadakı ilə fərqlidir';
    $desc = tag($html, '~<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']~si');
    if ($desc === '') $p[] = 'meta description yoxdur';
    elseif ($r['meta_desc'] !== '' && $desc !== (string)$r['meta_desc']) $p[] = 'description fərqlidir';

    $canon = tag($html, '~<link\s+rel=["\']canonical["\']\s+href=["\'](.*?)["\']~si');
    if (rtrim($canon, '/') !== rtrim($u, '/')) $p[] = "canonical fərqlidir: $canon";

    /* gövdə render olunubmu */
    $h2db = preg_match_all('~^##\s~m', (string)$r['body']);
    $h2ht = preg_match_all('~<h2~i', $html);
    if ($h2db > 0 && $h2ht < $h2db) $p[] = "## başlıqları render olunmayıb ($h2db / $h2ht)";
    if (preg_match('~(SVC|ART):~', $html)) $p[] = 'səhifədə həll olunmamış açar var';
    if (preg_match('~\*\*[^*]+\*\*~', $html)) $p[] = 'işlənməmiş ** işarəsi var';
    /* kart sətri `|` olmadan abzasa çevrilir — gövdədə yoxlanılır */
    foreach (preg_split('/\r\n|\r|\n/', (string)$r['body']) as $ln) {
        $ln = trim($ln);
        if (str_starts_with($ln, '* ') && !str_contains($ln, '|')) {
            $p[] = 'kart sətrində `|` yoxdur: ' . mb_substr($ln, 0, 40);
            break;
        }
    }

    /* FAQ: blok və nişan */
    $faq = cms_faq_parse((string)($r['faq'] ?? ''));
    $det = preg_match_all('~<details~i', $html);
    if ($faq) {
        if ($det !== count($faq)) $p[] = 'FAQ blok sayı uyğun deyil (' . count($faq) . " / $det)";
        if (!preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m)) {
            $p[] = 'ld+json yoxdur';
        } else {
            $ld = json_decode(trim($m[1]), true);
            $qn = 0;
            foreach (($ld['@graph'] ?? []) as $n)
                if (($n['@type'] ?? '') === 'FAQPage') $qn = count($n['mainEntity'] ?? []);
            if ($qn === 0)              $p[] = 'FAQPage nişanı yoxdur';
            elseif ($qn !== count($faq)) $p[] = "FAQPage sual sayı fərqlidir (" . count($faq) . " / $qn)";
        }
    } else {
        $warn++;
        if ($det > 0) $p[] = 'bazada FAQ yoxdur, amma səhifədə blok var';
    }

    /* daxili keçidlər */
    $links = [];
    if (preg_match_all('~\]\((/[^)\s]*)\)~', (string)$r['body'], $m)) $links = array_unique($m[1]);
    foreach ($links as $rel) {
        $lu = $base . $rel;
        if (!isset($linkCache[$lu])) { [$lc] = fetch($lu); $linkCache[$lu] = $lc; }
        if ($linkCache[$lu] !== 200) $p[] = "daxili keçid {$linkCache[$lu]}: $rel";
    }

    $words = count(preg_split('~\s+~u', (string)$r['body'], -1, PREG_SPLIT_NO_EMPTY));
    if ($p) { printf("XX  %s\n", $name); foreach ($p as $x) echo "      ! $x\n"; $fail++; }
    else printf("OK  %s  %4d söz, %d başlıq, %d sual, %d keçid\n", $name, $words, $h2db, count($faq), count($links));
}

echo "\n" . str_repeat('-', 62) . "\n";
if ($warn) printf("FAQ-sız səhifə: %d\n", $warn);
if ($fail === 0) { echo "Sınıq yoxdur.\n"; exit(0); }
printf("Problemli sətir: %d\n", $fail);
exit(1);
