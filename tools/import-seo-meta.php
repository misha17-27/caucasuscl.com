<?php
/*
 * Səhifələrin title / description-ını `content/seo-meta.json` faylından
 * bazaya yazır.
 *
 *   php tools/import-seo-meta.php         — yazır
 *   php tools/import-seo-meta.php --dry   — yalnız hesabat
 *
 * Niyə ayrıca fayl: köhnə mətnlər WordPress/Yoast-dan qalmışdı (vergüllü
 * açar söz yığını), yeniləri isə yalnız bazada yaşasaydı, nə repozitoriyada
 * görünərdi, nə də başqa mühitə köçürülə bilərdi.
 *
 * DİQQƏT: bu skript `tools/deploy.sh`-ə QOŞULMAYIB. Qoşulsaydı, hər
 * yerləşdirmədə paneldən edilmiş meta redaktəsini geri qaytarardı.
 * Fayl dəyişəndə əl ilə bir dəfə işə salınır.
 *
 * Yalnız komanda sətrindən işləyir.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('SEO_META_IMPORT_WEB')) {
    http_response_code(403);
    exit("Bu skript yalnız komanda sətrindən işləyir.\n");
}

$ROOT = dirname(__DIR__);
chdir($ROOT);
require $ROOT . '/includes/compat.php';

$dry  = false;
$file = $ROOT . '/content/seo-meta.json';
foreach (array_slice($argv ?? [], 1) as $a) {
    if ($a === '--dry' || $a === '-n') { $dry = true; continue; }
    if (str_starts_with($a, '--file=')) { $file = substr($a, 7); continue; }
    exit("Tanınmayan arqument: $a\n");
}
if (!is_file($file)) exit("Fayl tapılmadı: $file\n");

$data = json_decode((string)file_get_contents($file), true);
if (!is_array($data)) exit("JSON oxunmadı: $file\n");

$db  = require $ROOT . '/admin/db.php';
$drv = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Baza: $drv" . ($dry ? '   (DRY RUN)' : '') . "\n\n";

$LANGS = ['az', 'ru', 'en'];
$T_MIN = 45; $T_MAX = 62;        // importda bir az boş yer buraxılır
$D_MIN = 120; $D_MAX = 165;

/* ---- əvvəlcə hamısını yoxlayırıq: bir xəta varsa heç nə yazılmır ---- */
$errors = [];
$rows   = [];

foreach (($data['services'] ?? []) as $s) {
    $tkey = (string)($s['tkey'] ?? '');
    if ($tkey === '') { $errors[] = 'xidmətin tkey-i boşdur'; continue; }
    foreach ($LANGS as $l) {
        $t = trim((string)($s['lang'][$l]['title'] ?? ''));
        $d = trim((string)($s['lang'][$l]['description'] ?? ''));
        if ($t === '' || $d === '') { $errors[] = "$tkey/$l: title və ya description boşdur"; continue; }
        $lt = mb_strlen($t); $ld = mb_strlen($d);
        if ($lt < $T_MIN || $lt > $T_MAX)   $errors[] = "$tkey/$l: title $lt simvol (hədd $T_MIN-$T_MAX)";
        if ($ld < $D_MIN || $ld > $D_MAX)   $errors[] = "$tkey/$l: description $ld simvol (hədd $D_MIN-$D_MAX)";
        $rows[] = ['kind' => 'service', 'key' => $tkey, 'lang' => $l, 'title' => $t, 'desc' => $d];
    }
}

foreach (($data['pages'] ?? []) as $p) {
    $slug = (string)($p['slug'] ?? '');
    if ($slug === '') { $errors[] = 'səhifənin slug-ı boşdur'; continue; }
    foreach ($LANGS as $l) {
        $t = trim((string)($p['lang'][$l]['title'] ?? ''));
        $d = trim((string)($p['lang'][$l]['description'] ?? ''));
        if ($t === '' || $d === '') { $errors[] = "$slug/$l: title və ya description boşdur"; continue; }
        $lt = mb_strlen($t); $ld = mb_strlen($d);
        if ($lt < $T_MIN || $lt > $T_MAX)   $errors[] = "$slug/$l: title $lt simvol (hədd $T_MIN-$T_MAX)";
        if ($ld < $D_MIN || $ld > $D_MAX)   $errors[] = "$slug/$l: description $ld simvol (hədd $D_MIN-$D_MAX)";
        $rows[] = ['kind' => 'page', 'key' => $slug, 'lang' => $l, 'title' => $t, 'desc' => $d];
    }
}

/* başlıqlar unikal olmalıdır — eyni başlıq iki səhifədə kannibalizasiya yaradır */
$seen = [];
foreach ($rows as $r) {
    $k = mb_strtolower($r['title']);
    if (isset($seen[$k])) $errors[] = "başlıq təkrarlanır: «{$r['title']}» ({$seen[$k]} və {$r['kind']}/{$r['key']}/{$r['lang']})";
    $seen[$k] = $r['kind'] . '/' . $r['key'] . '/' . $r['lang'];
}

if ($errors) {
    echo 'XƏTA (' . count($errors) . "):\n";
    foreach ($errors as $e) echo "  ! $e\n";
    exit("\nHeç nə yazılmadı.\n");
}

/* ---- yazma ---- */
$svcSel = $db->prepare('SELECT `id`, `meta_title`, `meta_desc` FROM `services` WHERE `lang` = ? AND `tkey` = ?');
$svcUpd = $db->prepare('UPDATE `services` SET `meta_title` = ?, `meta_desc` = ? WHERE `id` = ?');
$pgSel  = $db->prepare('SELECT `title`, `descr` FROM `seo_pages` WHERE `lang` = ? AND `slug` = ?');
$pgUpd  = $db->prepare('UPDATE `seo_pages` SET `title` = ?, `descr` = ? WHERE `lang` = ? AND `slug` = ?');

$changed = $same = $missing = 0;
if (!$dry) $db->beginTransaction();

foreach ($rows as $r) {
    if ($r['kind'] === 'service') {
        $svcSel->execute([$r['lang'], $r['key']]);
        $cur = $svcSel->fetch(PDO::FETCH_ASSOC);
        if (!$cur) { printf("  ? %-2s %-30s bazada yoxdur\n", $r['lang'], $r['key']); $missing++; continue; }
        if ((string)$cur['meta_title'] === $r['title'] && (string)$cur['meta_desc'] === $r['desc']) { $same++; continue; }
        if (!$dry) $svcUpd->execute([$r['title'], $r['desc'], $cur['id']]);
    } else {
        $pgSel->execute([$r['lang'], $r['key']]);
        $cur = $pgSel->fetch(PDO::FETCH_ASSOC);
        if (!$cur) { printf("  ? %-2s %-30s bazada yoxdur\n", $r['lang'], $r['key']); $missing++; continue; }
        if ((string)$cur['title'] === $r['title'] && (string)$cur['descr'] === $r['desc']) { $same++; continue; }
        if (!$dry) $pgUpd->execute([$r['title'], $r['desc'], $r['lang'], $r['key']]);
    }
    $changed++;
    printf("  ~ %-2s %-30s %3d/%3d  %s\n", $r['lang'], mb_substr($r['key'], 0, 29),
        mb_strlen($r['title']), mb_strlen($r['desc']), mb_substr($r['title'], 0, 52));
}

if (!$dry) $db->commit();
printf("\ndəyişdi: %d, eyni qaldı: %d, bazada tapılmadı: %d, cəmi: %d\n",
    $changed, $same, $missing, count($rows));
