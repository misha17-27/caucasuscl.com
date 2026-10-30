<?php
/*
 * Öz SEO məqalələrimizi `content/seo-articles.json` faylından bazaya yazır.
 *
 *   php tools/import-articles.php             — yazır (mövcud sətirləri yeniləyir)
 *   php tools/import-articles.php --only-new  — yalnız çatışmayan sətirləri əlavə edir
 *   php tools/import-articles.php --dry       — yalnız hesabat, bazaya toxunmur
 *   php tools/import-articles.php --file=...  — başqa JSON faylı
 *
 * `--only-new` avtomatik yerləşdirmə (cPanel Git deploy) üçündür: paneldən
 * edilmiş redaktə hər deploy-da geri qaytarılmasın. Fayldakı mətni bazaya
 * zorla yazmaq lazım olduqda skript bayraqsız işə salınır.
 *
 * Niyə ayrıca fayl: məqalənin mətni yalnız bazada yaşayırdı, yəni
 * repozitoriyada görünmürdü və serverə əl ilə köçürülürdü. İndi mətn
 * fayldadır, bu skript isə onu istənilən bazaya (SQLite və ya MySQL —
 * admin/config.php-dəki `driver` nə deyirsə) eyni nəticə ilə yazır.
 * Təkrar işə salmaq təhlükəsizdir: mövcud sətir yenilənir, dublikat yaranmır.
 *
 * Xidmət linkləri gövdədə `SVC:<tkey>` açarı ilə yazılır, məsələn
 * `[konsaltinq](SVC:konsaltinq-xidmeti)`. Skript onu hər dil üçün düzgün
 * ünvana çevirir — ünvan sxemi dəyişsə, mətnə toxunmaq lazım gəlmir.
 *
 * Yalnız komanda sətrindən işləyir — veb üzərindən açılmır.
 *
 * Serverdə CLI olmadıqda birdəfəlik örtük fayl işlədilir: o, əvvəlcə
 * `install_token`-i yoxlayır, sonra ARTICLES_IMPORT_WEB sabitini təyin edib
 * bu faylı require edir. Sabiti yalnız serverdəki fayl təyin edə bilər —
 * sorğu ilə onu təyin etmək mümkün deyil, ona görə qapı açıq qalmır.
 * İş bitəndən sonra örtük fayl silinir.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('ARTICLES_IMPORT_WEB')) {
    http_response_code(403);
    exit("Bu skript yalnız komanda sətrindən işləyir.\n");
}

$ROOT = dirname(__DIR__);
chdir($ROOT);

/* Server PHP 7.4-dür: polifilleri arqumentləri oxumadan ƏVVƏL yükləyirik,
   çünki aşağıda str_starts_with() işlənir. */
require $ROOT . '/includes/compat.php';

$dry     = false;
$onlyNew = false;
$file = $ROOT . '/content/seo-articles.json';
foreach (array_slice($argv ?? [], 1) as $a) {        // veb örtüyündə $argv olmur
    if ($a === '--dry' || $a === '-n') { $dry = true; continue; }
    if ($a === '--only-new')           { $onlyNew = true; continue; }
    if (str_starts_with($a, '--file=')) { $file = substr($a, 7); continue; }
    exit("Tanınmayan arqument: $a\n");
}

/* ---- saytın öz marşrutlaşdırması: ünvanları onunla qururuq ---- */
require $ROOT . '/includes/config.php';            // routes.php-i özü yükləyir
require_once $ROOT . '/includes/lang.php';         // lang_prefix(), LANG
require $ROOT . '/includes/functions.php';         // url()

if (!is_file($file)) exit("Fayl tapılmadı: $file\n");
$data = json_decode((string)file_get_contents($file), true);
if (!is_array($data) || !isset($data['articles']) || !is_array($data['articles'])) {
    exit("JSON oxunmadı və ya `articles` massivi yoxdur: $file\n");
}

$db = require $ROOT . '/admin/db.php';
$drv = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Baza: $drv" . ($dry ? "   (DRY RUN — yazılmır)" : '') . "\n\n";

/* ---- xidmətlərin dil üzrə slug-ları: SVC: açarlarını çevirmək üçün ---- */
$svc = [];                                          // [tkey][lang] = slug
foreach ($db->query('SELECT lang, tkey, slug FROM services') as $r) {
    $svc[(string)$r['tkey']][(string)$r['lang']] = (string)$r['slug'];
}
if (!$svc) exit("services cədvəli boşdur — əvvəlcə məzmunu yükləyin.\n");

$LANGS = ['az', 'ru', 'en'];
$SUFFIX = ' - Caucasuscl';

/** `SVC:<tkey>` açarlarını həmin dilin real ünvanı ilə dəyişir. */
function resolve_links(string $body, string $lang, array $svc, array &$errors, array &$used): string {
    return (string)preg_replace_callback(
        '~\]\(SVC:([a-z0-9\-]+)\)~',
        static function (array $m) use ($lang, $svc, &$errors, &$used): string {
            $tkey = $m[1];
            if (!isset($svc[$tkey][$lang])) {
                $errors[] = "bilinməyən xidmət açarı `$tkey` ($lang)";
                return '](#)';
            }
            $used[] = $tkey;
            return '](' . url('/xidmetler/' . $svc[$tkey][$lang], $lang) . ')';
        },
        $body
    );
}

$defaults = is_array($data['defaults'] ?? null) ? $data['defaults'] : [];
$errors = [];
$rows   = [];

foreach ($data['articles'] as $i => $a) {
    $key = (string)($a['key'] ?? '');
    if ($key === '' || !preg_match('~^[a-z0-9\-]+$~', $key)) {
        $errors[] = "#$i: `key` boşdur və ya yararsızdır";
        continue;
    }
    if (!isset($a['lang']) || !is_array($a['lang'])) { $errors[] = "$key: `lang` yoxdur"; continue; }

    foreach ($LANGS as $lang) {
        $t = $a['lang'][$lang] ?? null;
        if (!is_array($t)) { $errors[] = "$key: `$lang` yoxdur"; continue; }

        foreach (['title', 'excerpt', 'metaTitle', 'metaDesc', 'body'] as $f) {
            if (trim((string)($t[$f] ?? '')) === '') $errors[] = "$key/$lang: `$f` boşdur";
        }

        $used = [];
        $body = resolve_links((string)($t['body'] ?? ''), $lang, $svc, $errors, $used);

        /* format yoxlaması — mini-format pozulmasın */
        if (preg_match('~<\s*/?\s*(p|div|br|ul|ol|li|a|b|i|strong|em|h[1-6]|table|script)\b~i', $body)) {
            $errors[] = "$key/$lang: gövdədə HTML teqi var";
        }
        if (preg_match('~^#\s~m', $body))        $errors[] = "$key/$lang: gövdədə `# ` başlığı var (h1 olmaz)";
        if (preg_match('~^\d+\.\s~m', $body))    $errors[] = "$key/$lang: gövdədə `1.` nömrələməsi var (`+ ` işlədin)";
        if (str_contains($body, 'SVC:'))         $errors[] = "$key/$lang: həll olunmayan SVC: açarı qaldı";
        if (!$used)                              $errors[] = "$key/$lang: daxili xidmət linki yoxdur";

        $metaTitle = trim((string)($t['metaTitle'] ?? ''));
        if ($metaTitle !== '' && !str_ends_with($metaTitle, $SUFFIX)) $metaTitle .= $SUFFIX;

        $rows[] = [
            'lang'       => $lang,
            'slug'       => $key,
            'tkey'       => $key,
            'title'      => trim((string)($t['title'] ?? '')),
            'excerpt'    => trim((string)($t['excerpt'] ?? '')),
            'meta_title' => $metaTitle,
            'meta_desc'  => trim((string)($t['metaDesc'] ?? '')),
            'body'       => trim($body),
            'pub_date'   => (string)($a['pubDate'] ?? $defaults['pubDate'] ?? ''),
            'sort'       => (int)($a['sort'] ?? 0),
            'image'      => (string)($a['image'] ?? ''),
            'is_own'     => (int)($a['isOwn'] ?? $defaults['isOwn'] ?? 1),
            'visible'    => (int)($a['visible'] ?? $defaults['visible'] ?? 1),
            'video'      => '',
            'question'   => '',
            '_links'     => count(array_unique($used)),
        ];
    }
}

if ($errors) {
    echo "XƏTA (" . count($errors) . "):\n";
    foreach ($errors as $e) echo "  ! $e\n";
    exit("\nHeç nə yazılmadı.\n");
}

/* ---- yazma ---- */
$cols = ['lang','slug','tkey','title','excerpt','meta_title','meta_desc','body',
         'pub_date','sort','image','is_own','visible','video','question'];

$sel = $db->prepare('SELECT id FROM `news` WHERE `lang` = ? AND `slug` = ?');
$ins = $db->prepare('INSERT INTO `news` (`' . implode('`,`', $cols) . '`) VALUES ('
                  . rtrim(str_repeat('?,', count($cols)), ',') . ')');
$upd = $db->prepare('UPDATE `news` SET `'
                  . implode('` = ?, `', array_slice($cols, 2)) . '` = ? WHERE `id` = ?');

$new = $upds = $kept = 0;
if (!$dry) $db->beginTransaction();
foreach ($rows as $r) {
    $sel->execute([$r['lang'], $r['slug']]);
    $id = $sel->fetchColumn();
    $vals = [];
    foreach ($cols as $c) $vals[] = $r[$c];

    if ($id === false) {
        if (!$dry) $ins->execute($vals);
        $new++;
        printf("  + %-3s %-46s %2d link  %5d simvol\n", $r['lang'], $r['slug'], $r['_links'], mb_strlen($r['body']));
    } elseif ($onlyNew) {
        /* Avtomatik yerləşdirmədə paneldən edilmiş redaktəni geri qaytarmırıq. */
        $kept++;
        printf("  = %-3s %-46s bazadakı variant saxlanıldı\n", $r['lang'], $r['slug']);
    } else {
        if (!$dry) $upd->execute(array_merge(array_slice($vals, 2), [$id]));
        $upds++;
        printf("  ~ %-3s %-46s %2d link  %5d simvol\n", $r['lang'], $r['slug'], $r['_links'], mb_strlen($r['body']));
    }
}
if (!$dry) $db->commit();

printf("\nyeni: %d, yeniləndi: %d, toxunulmadı: %d, cəmi: %d sətir\n", $new, $upds, $kept, count($rows));
printf("bazada öz məqalə sayı: %d\n", (int)$db->query('SELECT COUNT(*) FROM `news` WHERE `is_own` = 1')->fetchColumn());
