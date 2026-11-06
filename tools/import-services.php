<?php
/*
 * Xidmət səhifələrinin mətnini `content/services.json` faylından bazaya yazır.
 *
 *   php tools/import-services.php          — yazır
 *   php tools/import-services.php --dry    — yalnız hesabat
 *   php tools/import-services.php --file=… — başqa JSON
 *
 * Nə yazılır: `lead`, `body`, `faq`. `title`, `short`, `img`, `meta_*` və `sort`
 * TOXUNULMUR — onlar ayrıca idarə olunur (metalar `import-seo-meta.php` ilə).
 *
 * Gövdədəki keçidlər açarla yazılır və burada hər dilin real ünvanına çevrilir:
 *   [mətn](SVC:<xidmətin tkey-i>)   -> /xidmetler/<həmin dilin slug-ı>/
 *   [mətn](ART:<məqalənin tkey-i>)  -> xəbərin həmin dildəki ünvanı
 * Slug-lar dilə görə fərqlənir (RU xidmətləri başqa slug-dadır), ona görə
 * mətndə ünvan saxlamaq olmaz.
 *
 * `deploy.sh`-ə QOŞULMAYIB: hər yerləşdirmədə paneldən edilmiş redaktəni
 * geri qaytarardı. Fayl dəyişəndə əl ilə işə salınır.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('SERVICES_IMPORT_WEB')) {
    http_response_code(403);
    exit("Bu skript yalnız komanda sətrindən işləyir.\n");
}

$ROOT = dirname(__DIR__);
chdir($ROOT);
require $ROOT . '/includes/compat.php';

$dry  = false;
$file = $ROOT . '/content/services.json';
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
if (!is_array($data['services'] ?? null)) exit("JSON oxunmadı və ya `services` yoxdur: $file\n");

$db  = require $ROOT . '/admin/db.php';
$drv = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Baza: $drv" . ($dry ? '   (DRY RUN)' : '') . "\n\n";

/* ---- `faq` sütunu yoxdursa əlavə olunur (köhnə bazalar üçün) ---- */
$hasFaq = false;
try {
    if ($drv === 'sqlite') {
        foreach ($db->query('PRAGMA table_info(`services`)') as $c)
            if (strcasecmp((string)$c['name'], 'faq') === 0) { $hasFaq = true; break; }
    } else {
        $hasFaq = (bool)$db->query("SHOW COLUMNS FROM `services` LIKE 'faq'")->fetch();
    }
} catch (Throwable $e) { exit("sütunlar oxunmadı: " . $e->getMessage() . "\n"); }

if (!$hasFaq) {
    if ($dry) { echo "  (faq sütunu yoxdur — real işləmədə əlavə olunacaq)\n\n"; }
    else {
        $db->exec('ALTER TABLE `services` ADD COLUMN `faq` TEXT');
        echo "  + `faq` sütunu əlavə olundu\n\n";
    }
}

/* ---- açar -> ünvan xəritələri ---- */
$svcSlug = [];                                   // [tkey][lang] = slug
foreach ($db->query('SELECT lang, tkey, slug FROM `services`') as $r)
    $svcSlug[(string)$r['tkey']][(string)$r['lang']] = (string)$r['slug'];
$artSlug = [];
foreach ($db->query("SELECT lang, tkey, slug FROM `news` WHERE `tkey` <> ''") as $r)
    $artSlug[(string)$r['tkey']][(string)$r['lang']] = (string)$r['slug'];

if (!$svcSlug) exit("services cədvəli boşdur.\n");

function resolve_links(string $text, string $lang, array $svc, array $art, array &$errors, array &$used): string {
    return (string)preg_replace_callback(
        '~\]\((SVC|ART):([a-z0-9\-]+)\)~',
        static function (array $m) use ($lang, $svc, $art, &$errors, &$used): string {
            [$all, $kind, $key] = $m;
            if ($kind === 'SVC') {
                if (!isset($svc[$key][$lang])) { $errors[] = "bilinməyən xidmət açarı `$key` ($lang)"; return '](#)'; }
                $used[] = $all;
                return '](' . url('/xidmetler/' . $svc[$key][$lang], $lang) . ')';
            }
            if (!isset($art[$key][$lang])) { $errors[] = "bilinməyən məqalə açarı `$key` ($lang)"; return '](#)'; }
            $used[] = $all;
            return '](' . url('/xeberler/' . $art[$key][$lang], $lang) . ')';
        },
        $text
    );
}

$LANGS = ['az', 'ru', 'en'];
$errors = $rows = [];

foreach ($data['services'] as $s) {
    $tkey = (string)($s['tkey'] ?? '');
    if ($tkey === '') { $errors[] = 'xidmətin tkey-i boşdur'; continue; }
    if (!isset($svcSlug[$tkey])) { $errors[] = "bazada belə xidmət yoxdur: $tkey"; continue; }

    foreach ($LANGS as $l) {
        $t = $s['lang'][$l] ?? null;
        if (!is_array($t)) { $errors[] = "$tkey/$l: dil bloku yoxdur"; continue; }

        $lead = trim((string)($t['lead'] ?? ''));
        $body = trim((string)($t['body'] ?? ''));
        if ($lead === '') $errors[] = "$tkey/$l: lead boşdur";
        if ($body === '') { $errors[] = "$tkey/$l: body boşdur"; continue; }

        $used = [];
        $body = resolve_links($body, $l, $svcSlug, $artSlug, $errors, $used);

        /* mini-format yoxlaması */
        if (preg_match('~<\s*/?\s*(p|div|br|ul|ol|li|a|b|i|strong|em|h[1-6]|table|script)\b~i', $body))
            $errors[] = "$tkey/$l: gövdədə HTML teqi var";
        if (preg_match('~^#\s~m', $body))     $errors[] = "$tkey/$l: gövdədə `# ` başlığı var";
        if (preg_match('~^\d+\.\s~m', $body)) $errors[] = "$tkey/$l: gövdədə `1.` nömrələməsi var";
        if (preg_match('~(SVC|ART):~', $body)) $errors[] = "$tkey/$l: həll olunmayan açar qaldı";
        /* kart sətrində `|` olmalıdır, yoxsa abzasa çevrilir */
        foreach (preg_split('/\r\n|\r|\n/', $body) as $ln) {
            $ln = trim($ln);
            if (str_starts_with($ln, '* ') && !str_contains($ln, '|'))
                $errors[] = "$tkey/$l: kart sətrində `|` yoxdur — abzasa çevriləcək: " . mb_substr($ln, 0, 50);
        }

        $words = count(preg_split('~\s+~u', strip_tags($body), -1, PREG_SPLIT_NO_EMPTY));
        if ($words < 450) $errors[] = "$tkey/$l: gövdə qısadır ($words söz)";
        if ($words > 1100) $errors[] = "$tkey/$l: gövdə uzundur ($words söz)";

        /* FAQ: massivdən mətn formatına */
        $faqRaw = '';
        $faqN = 0;
        foreach (($t['faq'] ?? []) as $f) {
            $q = trim((string)($f['q'] ?? ''));
            $a = trim((string)($f['a'] ?? ''));
            if ($q === '' || $a === '') continue;
            if (preg_match('~\]\((SVC|ART):~', $a)) $errors[] = "$tkey/$l: FAQ cavabında link var";
            $faqRaw .= '? ' . $q . "\n" . $a . "\n";
            $faqN++;
        }
        if ($faqN < 3) $errors[] = "$tkey/$l: FAQ azdır ($faqN sual)";

        $rows[] = ['tkey' => $tkey, 'lang' => $l, 'lead' => $lead, 'body' => $body,
                   'faq' => trim($faqRaw), 'words' => $words, 'faqN' => $faqN,
                   'links' => count(array_unique($used))];
    }
}

if ($errors) {
    echo 'XƏTA (' . count($errors) . "):\n";
    foreach ($errors as $e) echo "  ! $e\n";
    exit("\nHeç nə yazılmadı.\n");
}

$upd = $db->prepare('UPDATE `services` SET `lead` = ?, `body` = ?, `faq` = ? WHERE `lang` = ? AND `tkey` = ?');
$changed = $same = 0;
if (!$dry) $db->beginTransaction();
foreach ($rows as $r) {
    if (!$dry) $upd->execute([$r['lead'], $r['body'], $r['faq'], $r['lang'], $r['tkey']]);
    $changed++;
    printf("  ~ %-2s %-28s %4d söz, %d sual, %d keçid\n",
        $r['lang'], mb_substr($r['tkey'], 0, 27), $r['words'], $r['faqN'], $r['links']);
}
if (!$dry) $db->commit();

printf("\nyazıldı: %d sətir (%d xidmət × 3 dil)\n", $changed, (int)(count($rows) / 3));
