<?php
/*
 * Orijinal caucasuscl.com-un SEO məlumatını (title + description) köçürür,
 * boş qalanları isə səhifənin öz mətnindən avtomatik doldurur.
 *
 *   /admin/import-seo.php?token=<install_token>
 *   ...&dry=1  — yalnız hesabat
 *
 * Təkrar işə salmaq təhlükəsizdir.
 */
declare(strict_types=1);
set_time_limit(900);
require __DIR__ . '/lib.php';
header('Content-Type: text/plain; charset=utf-8');

$__tok = (string)cfg('install_token');
if ($__tok === '' || !hash_equals($__tok, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden. ?token=... əlavə edin');
}
$dry = isset($_GET['dry']);
$db  = pdo();
const SRC = 'https://caucasuscl.com';

function grab(string $url): ?string {
    $ctx = stream_context_create(['http' => [
        'timeout' => 25,
        'header' => "User-Agent: Mozilla/5.0 (compatible; caucasuscl-seo)\r\n",
        'ignore_errors' => true,
    ]]);
    $h = @file_get_contents($url, false, $ctx);
    return is_string($h) && $h !== '' ? $h : null;
}
function meta(string $html, string $what): string {
    if ($what === 'title') {
        return preg_match('~<title>([\s\S]*?)</title>~i', $html, $m)
            ? trim(preg_replace('/\s+/u', ' ', html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'))) : '';
    }
    return preg_match('~<meta\s+name="description"\s+content="([^"]*)"~i', $html, $m)
        ? trim(preg_replace('/\s+/u', ' ', html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'))) : '';
}
function shorten(string $s, int $len = 158): string {
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    if ($s === '') return '';
    if (mb_strlen($s) <= $len) return $s;
    $cut = mb_substr($s, 0, $len);
    $sp  = mb_strrpos($cut, ' ');
    return rtrim($sp > 60 ? mb_substr($cut, 0, $sp) : $cut, " ,.;:—-") . '…';
}

/* news üçün meta sütunları */
foreach ([['news', 'meta_title', "VARCHAR(255) DEFAULT ''"], ['news', 'meta_desc', 'TEXT']] as [$t, $c, $type]) {
    $exists = false;
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($db->query("PRAGMA table_info(`$t`)") as $r) if (strcasecmp($r['name'], $c) === 0) $exists = true;
    } else {
        $s = $db->prepare('SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $s->execute([$t, $c]); $exists = (int)$s->fetch()['c'] > 0;
    }
    if (!$exists) { $db->exec("ALTER TABLE `$t` ADD COLUMN `$c` $type"); echo "+ $t.$c\n"; }
}

/* ---------- 1. orijinaldan yığırıq ---------- */
echo "\n1) sitemap oxunur...\n";
$idx = grab(SRC . '/sitemap_index.xml');
if ($idx === null) exit("sitemap açılmadı\n");
$urls = [];
foreach (preg_match_all('~<loc>([^<]+)</loc>~', $idx, $mm) ? $mm[1] : [] as $sm) {
    $x = grab($sm);
    if ($x === null) continue;
    if (preg_match_all('~<loc>([^<]+)</loc>~', $x, $m2)) {
        foreach ($m2[1] as $u) $urls[] = str_replace(SRC, '', $u);
    }
}
$urls = array_values(array_unique($urls));
echo "   ünvan: " . count($urls) . "\n";

echo "\n2) title / description oxunur...\n";
$seo = [];
foreach ($urls as $u) {
    $h = grab(SRC . $u);
    if ($h === null) continue;
    $seo[$u] = ['t' => meta($h, 'title'), 'd' => meta($h, 'desc')];
}
echo "   oxundu: " . count($seo) . ", təsvirsiz: " . count(array_filter($seo, fn($x) => $x['d'] === '')) . "\n";

/* ---------- 2. ünvan -> bizim obyekt ---------- */
$pageMap = [
    '/'                 => ['seo', 'az', '/'],
    '/haqqimizda/'      => ['seo', 'az', 'haqqimizda'],
    '/xidmetler/'       => ['seo', 'az', 'xidmetler'],
    '/gomruk-xeberleri/' => ['seo', 'az', 'xeberler'],
    '/elaqe/'           => ['seo', 'az', 'elaqe'],
    '/ru/'              => ['seo', 'ru', '/'],
    '/ru/o-nas/'        => ['seo', 'ru', 'haqqimizda'],
    '/ru/uslugi/'       => ['seo', 'ru', 'xidmetler'],
    '/ru/novosti/'      => ['seo', 'ru', 'xeberler'],
    '/ru/kontakty/'     => ['seo', 'ru', 'elaqe'],
];

$setSeo  = $db->prepare('UPDATE seo_pages SET title = ?, descr = ? WHERE lang = ? AND slug = ?');
$setSvc  = $db->prepare('UPDATE services  SET meta_title = ?, meta_desc = ? WHERE lang = ? AND slug = ?');
$setNews = $db->prepare('UPDATE news      SET meta_title = ?, meta_desc = ? WHERE lang = ? AND slug = ?');

echo "\n3) bazaya yazılır" . ($dry ? ' (DRY)' : '') . "...\n";
$hit = ['seo' => 0, 'services' => 0, 'news' => 0, 'skip' => 0];

foreach ($seo as $u => $v) {
    $target = null;
    if (isset($pageMap[$u])) {
        $target = $pageMap[$u];
    } elseif (preg_match('~^/ru/xidmetler/([a-z0-9\-]+)/$~', $u, $m)) {
        $target = ['services', 'ru', $m[1]];
    } elseif (preg_match('~^/xidmetler/([a-z0-9\-]+)/$~', $u, $m)) {
        $target = ['services', 'az', $m[1]];
    } elseif (preg_match('~^/([a-z0-9\-]{12,})/$~', $u, $m) && !str_starts_with($u, '/category/')) {
        $target = ['news', 'az', $m[1]];
    }
    if (!$target) { $hit['skip']++; continue; }
    [$kind, $lang, $slug] = $target;
    if ($dry) { $hit[$kind === 'seo' ? 'seo' : $kind]++; continue; }

    $title = $v['t'];
    $desc  = $v['d'];

    if ($kind === 'seo') {
        if ($desc === '') {
            $q = $db->prepare('SELECT hero_text, body FROM seo_pages WHERE lang=? AND slug=?');
            $q->execute([$lang, $slug]);
            $r = $q->fetch() ?: [];
            $desc = shorten((string)($r['hero_text'] ?? '') ?: strip_tags((string)($r['body'] ?? '')));
        }
        $setSeo->execute([$title, $desc, $lang, $slug]);
        $hit['seo']++;
    } elseif ($kind === 'services') {
        if ($desc === '') {
            $q = $db->prepare('SELECT short, lead FROM services WHERE lang=? AND slug=?');
            $q->execute([$lang, $slug]);
            $r = $q->fetch() ?: [];
            $desc = shorten((string)($r['short'] ?? '') ?: (string)($r['lead'] ?? ''));
        }
        $setSvc->execute([$title, $desc, $lang, $slug]);
        $hit['services']++;
    } else {
        if ($desc === '') {
            $q = $db->prepare('SELECT excerpt, question, body FROM news WHERE lang=? AND slug=?');
            $q->execute([$lang, $slug]);
            $r = $q->fetch() ?: [];
            $desc = shorten((string)($r['excerpt'] ?? '') ?: ((string)($r['question'] ?? '') ?: (string)($r['body'] ?? '')));
        }
        $setNews->execute([$title, $desc, $lang, $slug]);
        $hit['news']++;
    }
}
echo "   səhifə: {$hit['seo']}, xidmət: {$hit['services']}, xəbər: {$hit['news']}, ötürülən: {$hit['skip']}\n";

/* ---------- 3. boş qalanları doldururuq (EN + tərcümə olunmayanlar) ---------- */
if (!$dry) {
    echo "\n4) Boş title / description tamamlanır...\n";
    $site = 'Caucasus Customs and Logistics';
    $n = 0;

    foreach ($db->query("SELECT lang, slug, title, descr, hero_title, hero_text, body FROM seo_pages") as $r) {
        $t = trim((string)$r['title']);
        $d = trim((string)$r['descr']);
        if ($t !== '' && $d !== '') continue;
        if ($t === '') $t = trim((string)$r['hero_title']) !== '' ? $r['hero_title'] . ' | ' . $site : $site;
        if ($d === '') $d = shorten((string)$r['hero_text'] ?: strip_tags((string)$r['body']));
        $setSeo->execute([$t, $d, $r['lang'], $r['slug']]);
        $n++;
    }
    foreach ($db->query("SELECT lang, slug, title, meta_title, meta_desc, short, lead FROM services") as $r) {
        $t = trim((string)$r['meta_title']);
        $d = trim((string)$r['meta_desc']);
        if ($t !== '' && $d !== '') continue;
        if ($t === '') $t = $r['title'] . ' | ' . $site;
        if ($d === '') $d = shorten((string)$r['short'] ?: (string)$r['lead']);
        $setSvc->execute([$t, $d, $r['lang'], $r['slug']]);
        $n++;
    }
    foreach ($db->query("SELECT lang, slug, title, meta_title, meta_desc, excerpt, question, body FROM news") as $r) {
        $t = trim((string)$r['meta_title']);
        $d = trim((string)$r['meta_desc']);
        if ($t !== '' && $d !== '') continue;
        if ($t === '') $t = $r['title'] . ' | ' . $site;
        if ($d === '') $d = shorten((string)$r['excerpt'] ?: ((string)$r['question'] ?: (string)$r['body']));
        $setNews->execute([$t, $d, $r['lang'], $r['slug']]);
        $n++;
    }
    echo "   tamamlandı: $n\n";

    /* yekun yoxlama */
    $empty = 0;
    foreach ([['seo_pages','title','descr'], ['services','meta_title','meta_desc'], ['news','meta_title','meta_desc']] as [$t,$a,$b]) {
        $c = (int)$db->query("SELECT COUNT(*) c FROM `$t` WHERE TRIM(COALESCE($a,'')) = '' OR TRIM(COALESCE($b,'')) = ''")->fetch()['c'];
        echo "   $t: boş qalan $c\n";
        $empty += $c;
    }
    echo $empty === 0 ? "   ✓ bütün title və description doludur\n" : "   ! hələ boş sətirlər var\n";
}

echo "\nHAZIRDIR.\n";
