<?php
/*
 * caucasuscl.com saytındakı BÜTÜN xəbərləri bazaya köçürür (AZ dili).
 *
 *   /admin/import-news.php?token=<install_token>
 *   /admin/import-news.php?token=...&dry=1   — yalnız siyahını göstərir, yazmır
 *
 * Slug-lar orijinal saytdakı kimi saxlanılır — köhnə ünvanlar sınmasın.
 * Təkrar işə salmaq təhlükəsizdir: mövcud sətirlər yenilənir, dublikat yaranmır.
 */
declare(strict_types=1);
set_time_limit(600);
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

function fetch_html(string $url): ?string {
    $ctx = stream_context_create(['http' => [
        'timeout' => 25,
        'header'  => "User-Agent: Mozilla/5.0 (compatible; caucasuscl-import)\r\n",
        'ignore_errors' => true,
    ]]);
    $h = @file_get_contents($url, false, $ctx);
    return is_string($h) && $h !== '' ? $h : null;
}

function dom(string $html): DOMDocument {
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    return $d;
}

/* ---------- 1. xəbərlərin siyahısı ---------- */
echo "1) Xəbər siyahısı yığılır...\n";
$SKIP = ['gomruk-xeberleri', 'haqqimizda', 'xidmetler', 'elaqe', 'faydali-linkler', 'ru', 'en'];
$list = [];
for ($p = 1; $p <= 8; $p++) {
    $url  = $p === 1 ? SRC . '/gomruk-xeberleri/' : SRC . '/gomruk-xeberleri/page/' . $p . '/';
    $html = fetch_html($url);
    if ($html === null) break;
    $x = new DOMXPath(dom($html));
    $before = count($list);
    foreach ($x->query('//a[@href]') as $a) {
        $href = $a->getAttribute('href');
        if (!preg_match('~^https?://caucasuscl\.com/([a-z0-9\-]{12,})/$~', $href, $m)) continue;
        $slug = $m[1];
        if (in_array($slug, $SKIP, true) || isset($list[$slug])) continue;
        $txt = trim(preg_replace('/\s+/u', ' ', $a->textContent));
        if (mb_strlen($txt) < 10) continue;
        $list[$slug] = $txt;
    }
    if (count($list) === $before) break;
}
echo "   tapıldı: " . count($list) . "\n";

/* ---------- 2. hər xəbərin məzmunu ---------- */
echo "\n2) Məzmun oxunur...\n";
$rows = [];
$i = 0;
foreach ($list as $slug => $title) {
    $i++;
    $html = fetch_html(SRC . '/' . $slug . '/');
    if ($html === null) { echo "   ! $slug — açılmadı\n"; continue; }
    $d = dom($html);
    $x = new DOMXPath($d);

    // tarix
    $date = '';
    foreach ($x->query('//meta[@property="article:published_time"]/@content') as $n) { $date = substr($n->nodeValue, 0, 10); break; }

    // video
    $video = '';
    foreach ($x->query('//iframe/@src') as $n) {
        if (preg_match('~embed/([A-Za-z0-9_\-]{6,})~', $n->nodeValue, $m)) { $video = $m[1]; break; }
    }

    // mətn
    $scope = $x->query('//*[contains(@class,"elementor-widget-theme-post-content")]');
    $ctx   = $scope->length ? $scope->item(0) : $d->documentElement;
    $ps = [];
    foreach ($x->query('.//p', $ctx) as $n) {
        $t = trim(preg_replace('/\s+/u', ' ', $n->textContent));
        if (mb_strlen($t) < 4) continue;
        if (str_contains($t, 'data-mce-type')) continue;
        if (str_contains($t, 'videoçarxda cavab tapa bilərsiniz')) continue;
        if (str_contains($t, 'İllik 13.99%')) continue;          // reklam qalığı
        $ps[] = $t;
    }

    // başlıqdan sonra təkrarlanan hissəni təmizləyirik
    $clean = preg_replace('/\s+/u', ' ', $title);
    foreach ($ps as $k => $t) {
        if (mb_strpos($t, $clean) === 0) $ps[$k] = trim(mb_substr($t, mb_strlen($clean)));
    }
    $ps = array_values(array_filter($ps, fn($t) => mb_strlen($t) > 3));

    // sual / gövdə
    $question = '';
    foreach ($ps as $k => $t) {
        if (preg_match('/^Sual\s*:\s*(.+)$/u', $t, $m)) { $question = trim($m[1]); unset($ps[$k]); break; }
    }
    $ps = array_values($ps);
    if ($question === '' && $video !== '' && $ps) { $question = $ps[0]; array_shift($ps); }

    $body    = implode("\n", $ps);
    $excerpt = $question !== '' ? $question : ($ps[0] ?? '');
    $excerpt = mb_strlen($excerpt) > 200 ? mb_substr($excerpt, 0, 200) . '…' : $excerpt;

    $rows[] = [
        'slug' => $slug, 'title' => $title, 'date' => $date ?: date('Y-m-d'),
        'video' => $video, 'question' => $question, 'excerpt' => $excerpt, 'body' => $body,
    ];
    echo sprintf("   %2d. %-52s %s %s\n", $i, mb_substr($slug, 0, 52), $date ?: '----------', $video ? 'video' : '');
}

/* ---------- 3. bazaya yazılır ---------- */
echo "\n3) Bazaya yazılır" . ($dry ? " (DRY — yazılmır)" : "") . "...\n";

// köhnə (qısaldılmış) slug -> orijinal slug uyğunluğu
$rename = [
    'dini-teyinatli-edebiyyata-icaze-senedi'         => 'dini-teyinatli-edebiyyata-azerbaycan-respublikasinin-gomruk-serhedinden-kecirilerken-hansi-dovlet-orqani-terefinden-verilen-icaze-senedi-teleb-olunur',
    'mallarin-rusum-dereceleri-haqqinda-melumat'     => 'mallarin-rusum-dereceleri-haqqinda-nece-melumat-almaq-olar',
    'musayiet-olunmayan-baqajda-gomruk-beyannamesi'  => 'fiziki-sexsler-terefinden-musayiet-olunmayan-baqajda-getirilen-ve-aparilan-mallara-dair-gomruk-beyannamesi-hansi-muddetde-verilmelidir',
    'gomruk-borcu-hansi-qaydada-odenilir'            => 'gomruk-odenisleri-uzre-yaranmis-gomruk-borcu-hansi-qaydada-odenilir',
    'gomruk-rusumlarinin-hesablanmasi'               => 'gomruk-rusumlari-hesablanmasi',
];
if (!$dry) {
    $u = $db->prepare('UPDATE news SET slug=? WHERE slug=?');
    foreach ($rename as $old => $new) {
        try { $u->execute([$new, $old]); } catch (Throwable $e) {}   // artıq varsa buraxırıq
    }
}

$has = $db->prepare('SELECT id FROM news WHERE lang=? AND slug=?');
$ins = $db->prepare('INSERT INTO news (lang,slug,title,pub_date,video,question,excerpt,body,sort,visible) VALUES (?,?,?,?,?,?,?,?,?,1)');
$upd = $db->prepare('UPDATE news SET title=?, pub_date=?, video=?, question=?, excerpt=?, body=?, sort=? WHERE lang=? AND slug=?');

// tarixə görə yeni-dən köhnəyə
usort($rows, fn($a, $b) => strcmp($b['date'], $a['date']));

$added = $updated = 0;
foreach ($rows as $sort => $r) {
    if ($dry) continue;
    $has->execute(['az', $r['slug']]);
    if ($has->fetch()) {
        // mövcud tərcümə edilmiş mətnə toxunmuruq — yalnız boş sahələri tamamlayırıq
        $cur = $db->prepare('SELECT question, excerpt, body FROM news WHERE lang=? AND slug=?');
        $cur->execute(['az', $r['slug']]);
        $c = $cur->fetch();
        $upd->execute([
            $r['title'], $r['date'], $r['video'],
            trim((string)$c['question']) !== '' ? $c['question'] : $r['question'],
            trim((string)$c['excerpt'])  !== '' ? $c['excerpt']  : $r['excerpt'],
            trim((string)($c['body'] ?? '')) !== '' ? $c['body'] : $r['body'],
            $sort, 'az', $r['slug'],
        ]);
        $updated++;
    } else {
        $ins->execute(['az', $r['slug'], $r['title'], $r['date'], $r['video'], $r['question'], $r['excerpt'], $r['body'], $sort]);
        $added++;
    }
}

echo "   yeni: $added, yeniləndi: $updated\n";
if (!$dry) {
    $c = $db->query("SELECT COUNT(*) c FROM news WHERE lang='az'")->fetch()['c'];
    echo "   bazada AZ xəbər: $c\n";
}
echo "\nHAZIRDIR.\n";
echo "RU/EN üçün xəbərlər panel vasitəsilə ayrıca tərcümə olunmalıdır.\n";
