<?php
/*
 * Mövcud xəbər səhifələrinin GÖVDƏSİNİ `content/news-bodies.json`-dan bazaya yazır.
 *
 *   php tools/import-news-bodies.php          — yazır
 *   php tools/import-news-bodies.php --dry    — yalnız hesabat
 *   php tools/import-news-bodies.php --file=… — başqa JSON
 *
 * NİYƏ AYRI SKRİPT:
 * `import-articles.php` öz məqalələrimiz üçündür — o, sətri tam yazır və
 * `video` ilə `question` sütunlarını BOŞALDIR. Dövlət Gömrük Komitəsindən
 * təkrar dərc olunan video səhifələrində isə məhz həmin iki sütun dəyərlidir:
 * sual mətni və YouTube identifikatoru. Ona görə bu skript YALNIZ `body`
 * (və açıq verilibsə `excerpt`, `meta_title`, `meta_desc`) sütununu yeniləyir.
 * `title`, `slug`, `tkey`, `video`, `question`, `sort`, `image`, `is_own`,
 * `visible` TOXUNULMUR.
 *
 * Gövdədəki keçidlər açarla yazılır və hər dilin real ünvanına çevrilir:
 *   [mətn](SVC:<xidmətin tkey-i>)   -> /xidmetler/<həmin dilin slug-ı>/
 *   [mətn](ART:<məqalənin tkey-i>)  -> xəbərin həmin dildəki ünvanı
 *
 * RƏQƏM QADAĞASI: bu səhifələrin sualı konkret rəqəmlə cavablanır (müddət,
 * faiz, məbləğ), bizdə isə təsdiqlənmiş rəqəm yoxdur. Uydurmaq olmaz, ona görə
 * gövdədə rəqəm ARDICILLIĞI ümumiyyətlə qadağandır — yalnız `$NUM_OK`
 * siyahısındakılar (şirkətin təsis ili və Incoterms buraxılışı) keçir.
 * Mexanizm izah olunur, rəqəm isə rəsmi mənbəyə yönləndirilir.
 *
 * `deploy.sh`-ə QOŞULMAYIB: hər yerləşdirmədə paneldən edilmiş redaktəni
 * geri qaytarardı. Fayl dəyişəndə əl ilə işə salınır.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('NEWS_BODIES_IMPORT_WEB')) {
    http_response_code(403);
    exit("Bu skript yalnız komanda sətrindən işləyir.\n");
}

$ROOT = dirname(__DIR__);
chdir($ROOT);
require $ROOT . '/includes/compat.php';

$dry  = false;
$file = $ROOT . '/content/news-bodies.json';
foreach (array_slice($argv ?? [], 1) as $a) {
    if ($a === '--dry' || $a === '-n') { $dry = true; continue; }
    if (str_starts_with($a, '--file=')) { $file = substr($a, 7); continue; }
    exit("Tanınmayan arqument: $a\n");
}
if (!is_file($file)) exit("Fayl tapılmadı: $file\n");

require $ROOT . '/includes/config.php';
require_once $ROOT . '/includes/lang.php';
require $ROOT . '/includes/functions.php';

$data = json_decode((string)file_get_contents($file), true);
if (!is_array($data['pages'] ?? null)) exit("JSON oxunmadı və ya `pages` yoxdur: $file\n");

$db  = require $ROOT . '/admin/db.php';
$drv = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Baza: $drv" . ($dry ? '   (DRY RUN)' : '') . "\n\n";

/* ---- açar -> ünvan xəritələri ---- */
$svcSlug = [];
foreach ($db->query('SELECT lang, tkey, slug FROM `services`') as $r)
    $svcSlug[(string)$r['tkey']][(string)$r['lang']] = (string)$r['slug'];
$artSlug = [];
foreach ($db->query("SELECT lang, tkey, slug FROM `news` WHERE `tkey` <> ''") as $r)
    $artSlug[(string)$r['tkey']][(string)$r['lang']] = (string)$r['slug'];
if (!$svcSlug) exit("services cədvəli boşdur.\n");

/* Hələ bazaya düşməmiş məqalələr də xəritəyə alınır — `import-services.php`-də
   olduğu kimi, ki işləmə sırası gizli şərtə çevrilməsin. */
$artFile = $ROOT . '/content/seo-articles.json';
if (is_file($artFile)) {
    $aj = json_decode((string)file_get_contents($artFile), true);
    foreach (($aj['articles'] ?? []) as $a) {
        $k = (string)($a['key'] ?? '');
        if ($k === '') continue;
        foreach (['az', 'ru', 'en'] as $l) if (!isset($artSlug[$k][$l])) $artSlug[$k][$l] = $k;
    }
}

function resolve_links(string $text, string $lang, array $svc, array $art, string $self,
                       array &$errors, array &$svcUsed, array &$artUsed): string {
    return (string)preg_replace_callback(
        '~\]\((SVC|ART):([a-z0-9\-]+)\)~',
        static function (array $m) use ($lang, $svc, $art, $self, &$errors, &$svcUsed, &$artUsed): string {
            [$all, $kind, $key] = $m;
            if ($kind === 'SVC') {
                if (!isset($svc[$key][$lang])) { $errors[] = "bilinməyən xidmət açarı `$key` ($lang)"; return '](#)'; }
                $svcUsed[] = $key;
                return '](' . url('/xidmetler/' . $svc[$key][$lang], $lang) . ')';
            }
            if ($key === $self) { $errors[] = "səhifə özünə keçid verir (`$key`, $lang)"; return '](#)'; }
            if (!isset($art[$key][$lang])) { $errors[] = "bilinməyən məqalə açarı `$key` ($lang)"; return '](#)'; }
            $artUsed[] = $key;
            return '](' . url('/xeberler/' . $art[$key][$lang], $lang) . ')';
        },
        $text
    );
}

/* İcazəli rəqəmlər: şirkətin təsis ili və Incoterms buraxılışı. */
$NUM_OK = ['2018', '2020'];

$LANGS  = ['az', 'ru', 'en'];
$errors = $rows = [];

/* mövcud sətirlər */
$have = [];
foreach ($db->query('SELECT id, lang, slug FROM `news`') as $r)
    $have[(string)$r['slug']][(string)$r['lang']] = (int)$r['id'];

foreach ($data['pages'] as $p) {
    $slug = (string)($p['slug'] ?? '');
    if ($slug === '') { $errors[] = 'səhifənin slug-ı boşdur'; continue; }
    if (!isset($have[$slug])) { $errors[] = "bazada belə xəbər yoxdur: $slug"; continue; }

    foreach ($LANGS as $l) {
        $t = $p['lang'][$l] ?? null;
        if (!is_array($t)) { $errors[] = "$slug/$l: dil bloku yoxdur"; continue; }
        if (!isset($have[$slug][$l])) { $errors[] = "$slug/$l: bazada bu dildə sətir yoxdur"; continue; }

        $body = trim((string)($t['body'] ?? ''));
        if ($body === '') { $errors[] = "$slug/$l: body boşdur"; continue; }

        $svcUsed = $artUsed = [];
        $body = resolve_links($body, $l, $svcSlug, $artSlug, $slug, $errors, $svcUsed, $artUsed);

        /* mini-format yoxlaması (import-articles.php ilə eyni qaydalar) */
        if (preg_match('~<\s*/?\s*(p|div|br|ul|ol|li|a|b|i|strong|em|h[1-6]|table|script)\b~i', $body))
            $errors[] = "$slug/$l: gövdədə HTML teqi var";
        if (preg_match('~^#\s~m', $body))      $errors[] = "$slug/$l: gövdədə `# ` başlığı var (h1 olmaz)";
        if (preg_match('~^\d+\.\s~m', $body))  $errors[] = "$slug/$l: gövdədə `1.` nömrələməsi var (`+ ` işlədin)";
        if (preg_match('~(SVC|ART):~', $body)) $errors[] = "$slug/$l: həll olunmayan açar qaldı";
        if (!$svcUsed)                         $errors[] = "$slug/$l: daxili xidmət linki yoxdur";
        foreach (preg_split('/\r\n|\r|\n/', $body) as $ln) {
            $ln = trim($ln);
            if (str_starts_with($ln, '* ') && !str_contains($ln, '|'))
                $errors[] = "$slug/$l: kart sətrində `|` yoxdur — abzasa çevriləcək: " . mb_substr($ln, 0, 50);
        }

        /* rəqəm yoxlaması — keçid ünvanları sayılmır (orada `2020` ola bilər) */
        $prose = preg_replace('~\]\([^)]*\)~', ']', $body);
        foreach (array_unique(preg_split('~\D+~', (string)$prose, -1, PREG_SPLIT_NO_EMPTY)) as $num)
            if (!in_array($num, $NUM_OK, true))
                $errors[] = "$slug/$l: gövdədə təsdiqlənməmiş rəqəm var: `$num`";

        $words = count(preg_split('~\s+~u', $body, -1, PREG_SPLIT_NO_EMPTY));
        if ($words < 220) $errors[] = "$slug/$l: gövdə qısadır ($words söz)";
        if ($words > 700) $errors[] = "$slug/$l: gövdə uzundur ($words söz)";

        $set = ['body' => $body];
        foreach (['excerpt' => 'excerpt', 'metaTitle' => 'meta_title', 'metaDesc' => 'meta_desc'] as $k => $col) {
            $v = trim((string)($t[$k] ?? ''));
            if ($v !== '') $set[$col] = $v;
        }

        $rows[] = ['id' => $have[$slug][$l], 'slug' => $slug, 'lang' => $l, 'set' => $set,
                   'words' => $words, 'svc' => count(array_unique($svcUsed)), 'art' => count(array_unique($artUsed))];
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
    if (!$dry) {
        $cols = array_keys($r['set']);
        $sql  = 'UPDATE `news` SET `' . implode('` = ?, `', $cols) . '` = ? WHERE `id` = ?';
        $db->prepare($sql)->execute(array_merge(array_values($r['set']), [$r['id']]));
    }
    $changed++;
    printf("  ~ %-3s %-46s %4d söz, %dx + %dm%s\n", $r['lang'], mb_substr($r['slug'], 0, 45),
        $r['words'], $r['svc'], $r['art'],
        count($r['set']) > 1 ? ' (+' . implode(', ', array_slice(array_keys($r['set']), 1)) . ')' : '');
}
if (!$dry) $db->commit();

printf("\nyeniləndi: %d sətir (%d səhifə × 3 dil)\n", $changed, (int)(count($rows) / 3));
