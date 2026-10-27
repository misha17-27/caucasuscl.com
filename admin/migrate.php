<?php
/*
 * Bazanın çoxdilli sxemə keçirilməsi və RU/EN məzmununun doldurulması.
 *
 *   /admin/migrate.php?token=<install_token>
 *
 * Təkrar işə salmaq təhlükəsizdir — mövcud sətirlərə toxunmur.
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('Content-Type: text/plain; charset=utf-8');

$__tok = (string)cfg('install_token');
if ($__tok === '' || !hash_equals($__tok, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden. ?token=... əlavə edin');
}

$db  = pdo();
$drv = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Driver: $drv\n\n";

/* ---------- 1. lang sütunu ---------- */
function has_col(PDO $db, string $table, string $col): bool {
    try {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($db->query("PRAGMA table_info(`$table`)") as $r) {
                if (strcasecmp($r['name'], $col) === 0) return true;
            }
            return false;
        }
        $st = $db->prepare('SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $st->execute([$table, $col]);
        return (int)$st->fetch()['c'] > 0;
    } catch (Throwable $e) { return false; }
}

echo "1) lang sütunları\n";

/* Sadə cədvəllər — ALTER kifayətdir */
foreach (['links', 'stats', 'process', 'whyus', 'submissions'] as $t) {
    if (has_col($db, $t, 'lang')) { echo "   · $t.lang var\n"; continue; }
    $db->exec("ALTER TABLE `$t` ADD COLUMN lang VARCHAR(5) NOT NULL DEFAULT 'az'");
    echo "   + $t.lang\n";
}

/* PRIMARY KEY / UNIQUE dəyişən cədvəllər — yenidən qurulur */
$rebuild = [
    'texts' => [
        'cols' => 'lang, k, v',
        'ddl'  => "lang VARCHAR(5) NOT NULL DEFAULT 'az', k VARCHAR(80) NOT NULL, v TEXT, PRIMARY KEY (lang, k)",
        'from' => "'az', k, v",
    ],
    'contacts' => [
        'cols' => 'lang, k, v',
        'ddl'  => "lang VARCHAR(5) NOT NULL DEFAULT 'az', k VARCHAR(80) NOT NULL, v TEXT, PRIMARY KEY (lang, k)",
        'from' => "'az', k, v",
    ],
    'seo_pages' => [
        'cols' => 'lang, slug, title, descr, robots, canonical',
        'ddl'  => "lang VARCHAR(5) NOT NULL DEFAULT 'az', slug VARCHAR(120) NOT NULL, title VARCHAR(255) DEFAULT '', descr TEXT, robots VARCHAR(60) DEFAULT 'index,follow', canonical VARCHAR(255) DEFAULT '', PRIMARY KEY (lang, slug)",
        'from' => "'az', slug, title, descr, robots, canonical",
    ],
    'services' => [
        'cols' => 'id, lang, slug, no, title, short, img, lead, body, meta_title, meta_desc, sort, visible',
        'ddl'  => cms_pk($db) . ", lang VARCHAR(5) NOT NULL DEFAULT 'az', slug VARCHAR(120) NOT NULL, no VARCHAR(8) DEFAULT '', title VARCHAR(255) DEFAULT '', short TEXT, img VARCHAR(255) DEFAULT '', lead TEXT, body TEXT, meta_title VARCHAR(255) DEFAULT '', meta_desc TEXT, sort INT DEFAULT 0, visible INT DEFAULT 1, UNIQUE (lang, slug)",
        'from' => "id, 'az', slug, no, title, short, img, lead, body, meta_title, meta_desc, sort, visible",
    ],
    'news' => [
        'cols' => 'id, lang, slug, title, pub_date, video, question, excerpt, sort, visible',
        'ddl'  => cms_pk($db) . ", lang VARCHAR(5) NOT NULL DEFAULT 'az', slug VARCHAR(160) NOT NULL, title VARCHAR(255) DEFAULT '', pub_date VARCHAR(20) DEFAULT '', video VARCHAR(60) DEFAULT '', question TEXT, excerpt TEXT, sort INT DEFAULT 0, visible INT DEFAULT 1, UNIQUE (lang, slug)",
        'from' => "id, 'az', slug, title, pub_date, video, question, excerpt, sort, visible",
    ],
];

$suf = cms_is_sqlite($db) ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
foreach ($rebuild as $t => $spec) {
    if (has_col($db, $t, 'lang')) { echo "   · $t.lang var\n"; continue; }
    $db->exec("CREATE TABLE `{$t}__new` ({$spec['ddl']})$suf");
    $db->exec("INSERT INTO `{$t}__new` ({$spec['cols']}) SELECT {$spec['from']} FROM `$t`");
    $db->exec("DROP TABLE `$t`");
    $db->exec("ALTER TABLE `{$t}__new` RENAME TO `$t`");
    echo "   + $t yenidən quruldu (lang əlavə olundu)\n";
}

/* ---------- 2. RU / EN məzmunu ---------- */
echo "\n2) Tərcümələr\n";
$T = require __DIR__ . '/seed-i18n.php';

$hasLang = function (string $table, string $lang) use ($db): bool {
    $st = $db->prepare("SELECT COUNT(*) c FROM `$table` WHERE lang = ?");
    $st->execute([$lang]);
    return (int)$st->fetch()['c'] > 0;
};

foreach (['ru', 'en'] as $lang) {
    $d = $T[$lang];
    echo "  [$lang]\n";

    /* --- texts: AZ sətirlərini kopyalayıb tərcümə ilə üzərinə yazırıq --- */
    if (!$hasLang('texts', $lang)) {
        $az = $db->query("SELECT k, v FROM texts WHERE lang='az'")->fetchAll();
        $ins = $db->prepare('INSERT INTO texts (lang, k, v) VALUES (?,?,?)');
        foreach ($az as $r) {
            $ins->execute([$lang, $r['k'], $d['texts'][$r['k']] ?? $r['v']]);
        }
        echo "     + texts: " . count($az) . "\n";
    } else echo "     · texts var\n";

    /* --- contacts: tərcümə olunanlar dəyişir, qalanı eyni --- */
    if (!$hasLang('contacts', $lang)) {
        $az = $db->query("SELECT k, v FROM contacts WHERE lang='az'")->fetchAll();
        $ins = $db->prepare('INSERT INTO contacts (lang, k, v) VALUES (?,?,?)');
        foreach ($az as $r) {
            $ins->execute([$lang, $r['k'], $d['contacts'][$r['k']] ?? $r['v']]);
        }
        echo "     + contacts: " . count($az) . "\n";
    } else echo "     · contacts var\n";

    /* --- stats --- */
    if (!$hasLang('stats', $lang)) {
        $az = $db->query("SELECT * FROM stats WHERE lang='az' ORDER BY sort, id")->fetchAll();
        $ins = $db->prepare('INSERT INTO stats (lang, num, suffix, label, sort) VALUES (?,?,?,?,?)');
        foreach ($az as $i => $r) {
            $ins->execute([$lang, $r['num'], $r['suffix'], $d['stats'][$i] ?? $r['label'], $r['sort']]);
        }
        echo "     + stats: " . count($az) . "\n";
    } else echo "     · stats var\n";

    /* --- process --- */
    if (!$hasLang('process', $lang)) {
        $az = $db->query("SELECT * FROM process WHERE lang='az' ORDER BY sort, id")->fetchAll();
        $ins = $db->prepare('INSERT INTO process (lang, num, title, descr, sort) VALUES (?,?,?,?,?)');
        foreach ($az as $i => $r) {
            $tr = $d['process'][$i] ?? [$r['title'], $r['descr']];
            $ins->execute([$lang, $r['num'], $tr[0], $tr[1], $r['sort']]);
        }
        echo "     + process: " . count($az) . "\n";
    } else echo "     · process var\n";

    /* --- whyus --- */
    if (!$hasLang('whyus', $lang)) {
        $az = $db->query("SELECT * FROM whyus WHERE lang='az' ORDER BY sort, id")->fetchAll();
        $ins = $db->prepare('INSERT INTO whyus (lang, title, descr, sort) VALUES (?,?,?,?)');
        foreach ($az as $i => $r) {
            $tr = $d['whyus'][$i] ?? [$r['title'], $r['descr']];
            $ins->execute([$lang, $tr[0], $tr[1], $r['sort']]);
        }
        echo "     + whyus: " . count($az) . "\n";
    } else echo "     · whyus var\n";

    /* --- links --- */
    if (!$hasLang('links', $lang)) {
        $az = $db->query("SELECT * FROM links WHERE lang='az' ORDER BY sort, id")->fetchAll();
        $ins = $db->prepare('INSERT INTO links (lang, title, url, descr, sort) VALUES (?,?,?,?,?)');
        foreach ($az as $i => $r) {
            $tr = $d['links'][$i] ?? [$r['title'], $r['descr']];
            $ins->execute([$lang, $tr[0], $r['url'], $tr[1], $r['sort']]);
        }
        echo "     + links: " . count($az) . "\n";
    } else echo "     · links var\n";

    /* --- services --- */
    if (!$hasLang('services', $lang)) {
        $az = $db->query("SELECT * FROM services WHERE lang='az' ORDER BY sort, id")->fetchAll();
        $ins = $db->prepare('INSERT INTO services (lang, slug, no, title, short, img, lead, body, meta_title, meta_desc, sort, visible) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($az as $r) {
            $tr = $d['services'][$r['slug']] ?? [];
            $ins->execute([
                $lang, $r['slug'], $r['no'],
                $tr['title'] ?? $r['title'],
                $tr['short'] ?? $r['short'],
                $r['img'],
                $tr['lead'] ?? $r['lead'],
                $tr['body'] ?? $r['body'],
                $tr['meta_title'] ?? $r['meta_title'],
                $tr['meta_desc'] ?? $r['meta_desc'],
                $r['sort'], $r['visible'],
            ]);
        }
        echo "     + services: " . count($az) . "\n";
    } else echo "     · services var\n";

    /* --- news --- */
    if (!$hasLang('news', $lang)) {
        $az = $db->query("SELECT * FROM news WHERE lang='az' ORDER BY sort, id")->fetchAll();
        $ins = $db->prepare('INSERT INTO news (lang, slug, title, pub_date, video, question, excerpt, sort, visible) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach ($az as $r) {
            $tr = $d['news'][$r['slug']] ?? [];
            $ins->execute([
                $lang, $r['slug'],
                $tr['title'] ?? $r['title'],
                $r['pub_date'], $r['video'],
                $tr['question'] ?? $r['question'],
                $tr['excerpt'] ?? $r['excerpt'],
                $r['sort'], $r['visible'],
            ]);
        }
        echo "     + news: " . count($az) . "\n";
    } else echo "     · news var\n";

    /* --- seo_pages --- */
    if (!$hasLang('seo_pages', $lang)) {
        $az = $db->query("SELECT * FROM seo_pages WHERE lang='az'")->fetchAll();
        $ins = $db->prepare('INSERT INTO seo_pages (lang, slug, title, descr, robots, canonical) VALUES (?,?,?,?,?,?)');
        foreach ($az as $r) {
            $tr = $d['seo'][$r['slug']] ?? [$r['title'], $r['descr']];
            $ins->execute([$lang, $r['slug'], $tr[0], $tr[1], $r['robots'], $r['canonical']]);
        }
        echo "     + seo_pages: " . count($az) . "\n";
    } else echo "     · seo_pages var\n";
}

echo "\nHAZIRDIR. Təkrar işə salmaq təhlükəsizdir.\n";
