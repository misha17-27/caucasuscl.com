<?php
/*
 * Mövcud xəbərlərin BAŞLIĞINI `content/news-titles.json`-dan bazaya yazır.
 *
 *   php tools/retitle-news.php          — yazır
 *   php tools/retitle-news.php --dry    — yalnız hesabat
 *   php tools/retitle-news.php --file=… — başqa JSON
 *
 * NİYƏ AYRI SKRİPT:
 * `import-articles.php` öz məqalələrimiz üçündür və sətri TAM yazır (o cümlədən
 * `video`, `question` sütunlarını boşaldır). `import-news-bodies.php` isə
 * qəsdən `title`-a TOXUNMUR. Burada lazım olan isə məhz başlıqdır — gövdə,
 * video, sual, slug və tərcümə açarı olduğu kimi qalmalıdır.
 *
 * Bu YALNIZ `title` və `meta_title` sütunlarını yeniləyir. `slug`, `tkey`,
 * `body`, `excerpt`, `meta_desc`, `video`, `question`, `sort`, `image`,
 * `is_own`, `visible`, `pub_date` TOXUNULMUR.
 *
 * Slug dəyişdirilmir — ünvanlar indeksdədir, dəyişsə yeni 404-lər yaranar.
 *
 * NİYƏ ÜMUMİYYƏTLƏ: köhnə saytdan gələn yeddi fərman/sərəncam xəbəri cəmi iki
 * başlıq paylaşırdı (üç dildə 21 ünvan). Google onları ayırd edə bilmir —
 * Search Console-da iki ünvan məhz bu səbəbdən «kanonik uyğun gəlmir» və
 * «obnaruzhena, ne proindeksirovana» qruplarındadır.
 *
 * `deploy.sh`-ə QOŞULMAYIB: hər yerləşdirmədə paneldən edilmiş redaktəni geri
 * qaytarardı. Birdəfəlik işdir, fayl dəyişəndə əl ilə işə salınır.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('NEWS_TITLES_IMPORT_WEB')) {
    http_response_code(403);
    exit("Bu skript yalnız komanda sətrindən işləyir.\n");
}

$ROOT = dirname(__DIR__);
chdir($ROOT);
require $ROOT . '/includes/compat.php';

$dry  = false;
$file = $ROOT . '/content/news-titles.json';
foreach (array_slice($argv ?? [], 1) as $a) {
    if ($a === '--dry' || $a === '-n') { $dry = true; continue; }
    if (str_starts_with($a, '--file=')) { $file = substr($a, 7); continue; }
    exit("Tanınmayan arqument: $a\n");
}
if (!is_file($file)) exit("Fayl tapılmadı: $file\n");

$data = json_decode((string)file_get_contents($file), true);
if (!is_array($data['pages'] ?? null)) exit("JSON oxunmadı və ya `pages` yoxdur: $file\n");

$db  = require $ROOT . '/admin/db.php';
require_once $ROOT . '/admin/schema.php';
/* `updated_at` köhnə bazalarda yoxdur — ilk işləmədə özü yaranır. */
$HAS_UPDATED = cms_ensure_news_updated($db);
echo 'Baza: ' . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . ($dry ? '   (DRY RUN)' : '') . "\n\n";

$LANGS  = ['az', 'ru', 'en'];
$errors = $rows = [];

/* bazadaki bütün sətirlər: slug+dil -> id, cari başlıqlar */
$have = [];
foreach ($db->query('SELECT id, lang, slug, title, meta_title, visible FROM `news`') as $r) {
    $have[(string)$r['slug']][(string)$r['lang']] = [
        'id'      => (int)$r['id'],
        'title'   => (string)$r['title'],
        'meta'    => (string)($r['meta_title'] ?? ''),
        'visible' => (int)$r['visible'],
    ];
}

foreach ($data['pages'] as $p) {
    $slug = (string)($p['slug'] ?? '');
    if ($slug === '') { $errors[] = 'səhifənin slug-ı boşdur'; continue; }
    if (!isset($have[$slug])) { $errors[] = "bazada belə xəbər yoxdur: $slug"; continue; }

    foreach ($LANGS as $l) {
        $t = $p['lang'][$l] ?? null;
        if (!is_array($t)) { $errors[] = "$slug/$l: dil bloku yoxdur"; continue; }
        if (!isset($have[$slug][$l])) { $errors[] = "$slug/$l: bazada bu dildə sətir yoxdur"; continue; }

        $title = trim((string)($t['title'] ?? ''));
        $meta  = trim((string)($t['metaTitle'] ?? ''));
        if ($title === '') { $errors[] = "$slug/$l: title boşdur"; continue; }

        /* başlıq düz mətndir — nə teq, nə mini-format */
        foreach (['title' => $title, 'metaTitle' => $meta] as $k => $v) {
            if ($v === '') continue;
            if (strip_tags($v) !== $v)                 $errors[] = "$slug/$l: $k-də teq var";
            if (preg_match('~[\r\n]~', $v))            $errors[] = "$slug/$l: $k çoxsətirlidir";
            if (preg_match('~^[#*\-+]\s~', $v))        $errors[] = "$slug/$l: $k mini-format işarəsi ilə başlayır";
        }
        if (mb_strlen($title) > 160) $errors[] = "$slug/$l: title uzundur (" . mb_strlen($title) . ')';
        if (mb_strlen($meta)  > 180) $errors[] = "$slug/$l: metaTitle uzundur (" . mb_strlen($meta) . ')';

        /* BÜTÜN HƏRFLƏR BÖYÜK yazılmış başlıq qəbul edilmir — köhnə
           `serencami-4` məhz belə idi və spam görünüşü verirdi. */
        if ($title !== '' && mb_strtoupper($title, 'UTF-8') === $title)
            $errors[] = "$slug/$l: title tamamilə böyük hərflərlədir";

        $cur = $have[$slug][$l];
        $set = [];
        if ($cur['title'] !== $title)             $set['title'] = $title;
        if ($meta !== '' && $cur['meta'] !== $meta) $set['meta_title'] = $meta;
        /* Başlıq da məzmundur — dəyişibsə, sitemap `lastmod` yenilənməlidir.
           Heç nə dəyişməyibsə tarixə toxunmuruq, yoxsa hər işləmə saxta
           «yeniləndi» siqnalı verərdi. */
        if ($set && $HAS_UPDATED) $set['updated_at'] = date('Y-m-d');

        $rows[] = ['id' => $cur['id'], 'slug' => $slug, 'lang' => $l, 'set' => $set,
                   'old' => $cur['title'], 'new' => $title];

        /* yoxlama üçün yaddaşdaki nüsxəni də yenilə */
        $have[$slug][$l]['title'] = $title;
        if ($meta !== '') $have[$slug][$l]['meta'] = $meta;
    }
}

/* ƏSAS YOXLAMA: dəyişiklikdən SONRA hər dildə görünən başlıqlar təkrarsız
   olmalıdır — skriptin bütün məqsədi budur. */
foreach ($LANGS as $l) {
    $seen = [];
    foreach ($have as $slug => $byLang) {
        if (!isset($byLang[$l]) || $byLang[$l]['visible'] !== 1) continue;
        /* dublikat ünvan onsuz da 301 verir, siyahıda görünmür */
        if (str_ends_with($slug, '-sertler-hansilardir-2')) continue;
        $k = mb_strtolower(trim($byLang[$l]['title']), 'UTF-8');
        if ($k === '') continue;
        if (isset($seen[$k])) $errors[] = "DƏYİŞİKLİKDƏN SONRA DA EYNİ BAŞLIQ ($l): «{$byLang[$l]['title']}» — $slug və {$seen[$k]}";
        else $seen[$k] = $slug;
    }
}

if ($errors) {
    echo 'XƏTA (' . count($errors) . "):\n";
    foreach ($errors as $e) echo "  ! $e\n";
    exit("\nHeç nə yazılmadı.\n");
}

$changed = 0;
if (!$dry) $db->beginTransaction();
foreach ($rows as $r) {
    if (!$r['set']) { printf("  = %-3s %s\n", $r['lang'], mb_substr($r['slug'], 0, 50)); continue; }
    if (!$dry) {
        $cols = array_keys($r['set']);
        $sql  = 'UPDATE `news` SET `' . implode('` = ?, `', $cols) . '` = ? WHERE `id` = ?';
        $db->prepare($sql)->execute(array_merge(array_values($r['set']), [$r['id']]));
    }
    $changed++;
    printf("  ~ %-3s %s\n      köhnə: %s\n      yeni : %s%s\n", $r['lang'], mb_substr($r['slug'], 0, 60),
        $r['old'], $r['new'], isset($r['set']['meta_title']) ? "   (+meta_title)" : '');
}
if (!$dry) $db->commit();

printf("\nyeniləndi: %d sətir (%d səhifə × 3 dil)\n", $changed, (int)(count($rows) / 3));
