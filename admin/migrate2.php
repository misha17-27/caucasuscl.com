<?php
/*
 * Miqrasiya 2:
 *   1) news cədvəlinə `body` sütunu (uzun xəbər mətnləri üçün)
 *   2) seo_pages cədvəlinə `hero_title`, `hero_text`, `body`
 *      — səhifələrin məzmunu artıq paneldən redaktə olunur
 *   3) mövcud səhifə mətnlərinin bazaya köçürülməsi (hər üç dil üçün)
 *
 *   /admin/migrate2.php?token=<install_token>
 *
 * Təkrar işə salmaq təhlükəsizdir.
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('Content-Type: text/plain; charset=utf-8');

$__tok = (string)cfg('install_token');
if ($__tok === '' || !hash_equals($__tok, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden. ?token=... əlavə edin');
}

$db = pdo();
echo "Driver: " . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n\n";

function col_ok(PDO $db, string $t, string $c): bool {
    try {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($db->query("PRAGMA table_info(`$t`)") as $r) if (strcasecmp($r['name'], $c) === 0) return true;
            return false;
        }
        $s = $db->prepare('SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $s->execute([$t, $c]);
        return (int)$s->fetch()['c'] > 0;
    } catch (Throwable $e) { return false; }
}

echo "1) Yeni sütunlar\n";
foreach ([['news', 'body', 'TEXT'],
          ['seo_pages', 'hero_title', "VARCHAR(255) DEFAULT ''"],
          ['seo_pages', 'hero_text', 'TEXT'],
          ['seo_pages', 'body', 'TEXT']] as [$t, $c, $type]) {
    if (col_ok($db, $t, $c)) { echo "   · $t.$c var\n"; continue; }
    $db->exec("ALTER TABLE `$t` ADD COLUMN `$c` $type");
    echo "   + $t.$c\n";
}

/* ---------------- 2) səhifə məzmunu ---------------- */
echo "\n2) Səhifə məzmunu\n";

$LANGS = ['az', 'ru', 'en'];
$dict  = [];
foreach ($LANGS as $lc) {
    $f = __DIR__ . '/../includes/lang/' . $lc . '.php';
    $dict[$lc] = is_file($f) ? require $f : [];
}
$T = function (string $lang, string $key) use ($dict): string {
    return $dict[$lang][$key] ?? ($dict['az'][$key] ?? '');
};

/* Hər səhifə üçün hero + gövdə (sadə işarələmə: ## başlıq, sətir = abzas) */
$NL = "\n";
$pageContent = function (string $lang, string $slug) use ($T, $NL): array {
    switch ($slug) {
        case '/':
            return [$T($lang, 'hero.title') . ' ' . $T($lang, 'hero.accent'), $T($lang, 'hero.text'), ''];

        case 'haqqimizda':
            $body = '## ' . $T($lang, 'about.h.princip') . $NL . $T($lang, 'about.p.princip') . $NL . $NL
                  . '## ' . $T($lang, 'about.h.team')    . $NL . $T($lang, 'about.p.team')    . $NL . $NL
                  . '## ' . $T($lang, 'about.h.legal')   . $NL . 'Caucasus Customs and Logistics ' . $T($lang, 'about.p.legal');
            return [$T($lang, 'page.about.h1'), $T($lang, 'page.about.text'), $body];

        case 'xidmetler':
            return [$T($lang, 'page.svc.h1'), $T($lang, 'page.svc.text'), ''];

        case 'xeberler':
            return [$T($lang, 'page.news.h1'), $T($lang, 'page.news.text'), ''];

        case 'elaqe':
            $body = '## ' . $T($lang, 'faq.title') . $NL
                  . '* ' . $T($lang, 'faq.q1') . ' | ' . $T($lang, 'faq.a1') . $NL
                  . '* ' . $T($lang, 'faq.q2') . ' | ' . $T($lang, 'faq.a2') . $NL
                  . '* ' . $T($lang, 'faq.q3') . ' | ' . $T($lang, 'faq.a3') . $NL
                  . '* ' . $T($lang, 'faq.q4') . ' | ' . $T($lang, 'faq.a4');
            return [$T($lang, 'page.contact.h1'), $T($lang, 'page.contact.text'), $body];

        case 'linkler':
            $body = '## ' . $T($lang, 'page.links.help') . $NL . $T($lang, 'page.links.helptext');
            return [$T($lang, 'page.links.h1'), $T($lang, 'page.links.text'), $body];
    }
    return ['', '', ''];
};

$sel = $db->prepare('SELECT hero_title, hero_text, body FROM seo_pages WHERE lang=? AND slug=?');
$upd = $db->prepare('UPDATE seo_pages SET hero_title=?, hero_text=?, body=? WHERE lang=? AND slug=?');
$n = 0;
foreach ($LANGS as $lang) {
    foreach (['/', 'haqqimizda', 'xidmetler', 'xeberler', 'elaqe', 'linkler'] as $slug) {
        $sel->execute([$lang, $slug]);
        $cur = $sel->fetch();
        if (!$cur) { echo "   ! $lang/$slug sətri yoxdur\n"; continue; }
        // Artıq doldurulubsa toxunmuruq
        if (trim((string)$cur['hero_title']) !== '' || trim((string)$cur['body']) !== '') {
            echo "   · $lang/$slug doludur\n";
            continue;
        }
        [$h, $ht, $body] = $pageContent($lang, $slug);
        $upd->execute([$h, $ht, $body, $lang, $slug]);
        $n++;
    }
}
echo "   + $n səhifə dolduruldu\n";

echo "\nHAZIRDIR.\n";
