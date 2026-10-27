<?php
/**
 * Admin panelindən gələn məzmun.
 *
 * Bu fayl config.php-nin sonundan yüklənir. Baza qurulubsa — config.php-dəki
 * massivləri bazadakı dəyərlərlə əvəz edir. Baza yoxdursa sayt config.php-dəki
 * ilkin dəyərlərlə, əvvəlki kimi işləyir.
 */

if (defined('CMS_NO_DB')) return;   // seed zamanı dövrəyə girməsin

/** Bazaya bağlantı (yoxdursa null) */
function cms_db(): ?PDO {
    static $db = false;
    if ($db !== false) return $db;
    $db = null;
    $cfg = __DIR__ . '/../admin/config.php';
    if (!is_file($cfg)) return $db;
    try {
        $p = require __DIR__ . '/../admin/db.php';
        $p->query('SELECT 1 FROM texts LIMIT 1');       // quraşdırılıb?
        $db = $p;
    } catch (Throwable $e) {
        $db = null;
    }
    return $db;
}

/** key-value cədvəlini bir dəfə oxuyub yaddaşda saxlayır */
function cms_kv(string $table): array {
    static $cache = [];
    $lang = defined('LANG') ? LANG : 'az';
    $key  = $table . ':' . $lang;
    if (isset($cache[$key])) return $cache[$key];

    $out = [];
    if ($db = cms_db()) {
        try {
            // settings dilsizdir, qalanlar dilə görə (çatışmayan açar AZ-dan götürülür)
            if ($table === 'settings') {
                foreach ($db->query("SELECT k, v FROM `settings`") as $r) $out[$r['k']] = (string)$r['v'];
            } else {
                $st = $db->prepare("SELECT k, v FROM `$table` WHERE lang = ?");
                $st->execute(['az']);
                foreach ($st as $r) $out[$r['k']] = (string)$r['v'];
                if ($lang !== 'az') {
                    $st = $db->prepare("SELECT k, v FROM `$table` WHERE lang = ?");
                    $st->execute([$lang]);
                    foreach ($st as $r) if (trim((string)$r['v']) !== '') $out[$r['k']] = (string)$r['v'];
                }
            }
        } catch (Throwable $e) {}
    }
    return $cache[$key] = $out;
}

function cms_text(string $k, string $default = ''): string    { return cms_kv('texts')[$k]    ?? $default; }
function cms_setting(string $k, string $default = ''): string { return cms_kv('settings')[$k] ?? $default; }

/** Səhifənin SEO məlumatı: ['title'=>…, 'descr'=>…, 'robots'=>…, 'canonical'=>…] */
function cms_seo(string $slug): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        if ($db = cms_db()) {
            try {
                $st = $db->prepare('SELECT * FROM seo_pages WHERE lang = ?');
                $st->execute([defined('LANG') ? LANG : 'az']);
                foreach ($st as $r) $cache[$r['slug']] = $r;
            } catch (Throwable $e) {}
        }
    }
    return $cache[$slug] ?? [];
}

/** Səhifənin paneldən idarə olunan mətni: cms_page('haqqimizda', 'hero_title') */
function cms_page(string $slug, string $field, string $default = ''): string {
    $p = cms_seo($slug);
    $v = trim((string)($p[$field] ?? ''));
    return $v !== '' ? $v : $default;
}

/** Eyni məzmunun başqa dildəki slug-ı (tkey vasitəsilə) */
function cms_translate_slug(string $table, string $slug, string $from, string $to): string {
    if ($slug === '' || $from === $to) return $slug;
    $db = cms_db();
    if (!$db) return '';
    try {
        $st = $db->prepare("SELECT tkey FROM `$table` WHERE lang = ? AND slug = ? LIMIT 1");
        $st->execute([$from, $slug]);
        $k = $st->fetch()['tkey'] ?? '';
        if ($k === '') return '';
        $st = $db->prepare("SELECT slug FROM `$table` WHERE lang = ? AND tkey = ? LIMIT 1");
        $st->execute([$to, $k]);
        return (string)($st->fetch()['slug'] ?? '');
    } catch (Throwable $e) { return ''; }
}

/**
 * Slug-ı HƏR HANSI dildə axtarıb cari dildəki qarşılığını qaytarır.
 *
 * Köhnə WordPress saytında eyni xidmət iki ünvanda idi — məsələn
 * /xidmetler/logistika-xidmetleri/ və /xidmetler/logistika-xidmeti/.
 * İkincisi indi bizdə yalnız rus dilindədir, ona görə axtarış sistemlərindən
 * gələn köhnə ünvan 404 verirdi. Bu funksiya həmin halda düzgün ünvanı tapır
 * ki, 404 yerinə 301 verək.
 *
 * Qaytarır: cari dildəki slug, tapılmasa boş sətir.
 */
function cms_find_slug(string $table, string $slug, string $toLang): string {
    $slug = trim($slug);
    if ($slug === '') return '';
    $db = cms_db();
    if (!$db) return '';
    try {
        /* slug hansı dildə olursa olsun, tkey-i tapırıq */
        $st = $db->prepare("SELECT tkey FROM `$table` WHERE slug = ? AND tkey <> '' LIMIT 1");
        $st->execute([$slug]);
        $k = (string)($st->fetch()['tkey'] ?? '');
        if ($k === '') return '';

        $st = $db->prepare("SELECT slug FROM `$table` WHERE lang = ? AND tkey = ? LIMIT 1");
        $st->execute([$toLang, $k]);
        $found = (string)($st->fetch()['slug'] ?? '');
        return $found === $slug ? '' : $found;      // eynidirsə yönləndirməyə ehtiyac yoxdur
    } catch (Throwable $e) { return ''; }
}

/** Sayta gələn müraciəti bazaya yazır */
function cms_save_submission(array $d): bool {
    $db = cms_db();
    if (!$db) return false;
    try {
        $db->prepare('INSERT INTO submissions (name,surname,phone,email,subject,message,is_read,lang,ip,created_at) VALUES (?,?,?,?,?,?,0,?,?,?)')
           ->execute([
               $d['ad'] ?? '', $d['soyad'] ?? '', $d['telefon'] ?? '', $d['email'] ?? '',
               $d['movzu'] ?? '', $d['mesaj'] ?? '',
               defined('LANG') ? LANG : 'az',
               $_SERVER['REMOTE_ADDR'] ?? '', date('Y-m-d H:i'),
           ]);
        return true;
    } catch (Throwable $e) { return false; }
}

/* ==================================================================
   Massivlərin bazadan doldurulması
   ================================================================== */
$__db = cms_db();
if (!$__db) return;

$__lang = defined('LANG') ? LANG : 'az';
/** Cari dildə sətirlər; boşdursa AZ-a qayıdır */
$__rows = static function (string $sql) use ($__db, $__lang): array {
    $st = $__db->prepare($sql);
    $st->execute([$__lang]);
    $r = $st->fetchAll();
    if (!$r && $__lang !== 'az') { $st->execute(['az']); $r = $st->fetchAll(); }
    return $r;
};

try {
    /* --- əlaqə --- */
    $kv = cms_kv('contacts');
    if ($kv) $CONTACT = array_merge($CONTACT, $kv);

    /* --- rəqəmlər --- */
    $rows = $__rows('SELECT * FROM stats WHERE lang = ? ORDER BY sort, id');
    if ($rows) {
        $STATS = [];
        foreach ($rows as $r) $STATS[] = ['n' => (int)$r['num'], 'suffix' => (string)$r['suffix'], 'label' => (string)$r['label']];
    }

    /* --- proses --- */
    $rows = $__rows('SELECT * FROM process WHERE lang = ? ORDER BY sort, id');
    if ($rows) {
        $PROCESS = [];
        foreach ($rows as $r) $PROCESS[] = ['n' => (string)$r['num'], 't' => (string)$r['title'], 'd' => (string)$r['descr']];
    }

    /* --- niyə biz --- */
    $rows = $__rows('SELECT * FROM whyus WHERE lang = ? ORDER BY sort, id');
    if ($rows) {
        $WHY_US = [];
        foreach ($rows as $r) $WHY_US[] = ['t' => (string)$r['title'], 'd' => (string)$r['descr']];
    }

    /* --- müştərilər --- */
    $rows = $__db->query('SELECT * FROM clients ORDER BY sort, id')->fetchAll();
    if ($rows) {
        $CLIENTS = [];
        foreach ($rows as $r) if (trim((string)$r['image']) !== '') $CLIENTS[] = (string)$r['image'];
    }

    /* --- faydalı linklər --- */
    $rows = $__rows('SELECT * FROM links WHERE lang = ? ORDER BY sort, id');
    if ($rows) {
        $USEFUL_LINKS = [];
        foreach ($rows as $r) $USEFUL_LINKS[] = ['t' => (string)$r['title'], 'u' => (string)$r['url'], 'd' => (string)$r['descr']];
    }

    /* --- xidmətlər --- */
    require_once __DIR__ . '/../admin/schema.php';   // cms_body_decode()
    $rows = $__rows('SELECT * FROM services WHERE lang = ? AND visible=1 ORDER BY sort, id');
    if ($rows) {
        $SERVICES = [];
        foreach ($rows as $r) {
            $SERVICES[$r['slug']] = [
                'no'         => (string)$r['no'],
                'title'      => (string)$r['title'],
                'short'      => (string)$r['short'],
                'img'        => (string)$r['img'],
                'meta_title' => (string)$r['meta_title'],
                'meta_desc'  => (string)$r['meta_desc'],
                'lead'       => (string)$r['lead'],
                'body'       => (string)$r['body'],
                'sections'   => cms_body_decode((string)$r['body']),
            ];
        }
    }

    /* --- xəbərlər --- */
    $rows = $__rows('SELECT * FROM news WHERE lang = ? AND visible=1 ORDER BY sort, id');
    if ($rows) {
        /* Üz qabığı bir dəfə — hansı dildə yüklənibsə, qalan dillər də onu
           götürür (tkey ilə). Dil üçün ayrıca şəkil qoyulubsa, o üstündür. */
        $__cover = [];
        try {
            foreach ($__db->query("SELECT tkey, image FROM news WHERE image <> '' AND tkey <> ''") as $c) {
                if (!isset($__cover[$c['tkey']])) $__cover[$c['tkey']] = (string)$c['image'];
            }
        } catch (Throwable $e) { $__cover = []; }

        $NEWS = [];
        foreach ($rows as $r) {
            $img = trim((string)($r['image'] ?? ''));
            if ($img === '') $img = $__cover[(string)($r['tkey'] ?? '')] ?? '';
            $NEWS[$r['slug']] = [
                'title'    => (string)$r['title'],
                'date'     => (string)$r['pub_date'],
                'video'    => (string)$r['video'],
                'question' => (string)$r['question'],
                'excerpt'  => (string)$r['excerpt'],
                'body'     => (string)($r['body'] ?? ''),
                'meta_title' => (string)($r['meta_title'] ?? ''),
                'meta_desc'  => (string)($r['meta_desc'] ?? ''),
                'is_own'     => (int)($r['is_own'] ?? 0),
                'image'      => $img,
            ];
        }
    }
} catch (Throwable $e) {
    // Baza natamamdırsa sayt config.php-dəki dəyərlərlə işləməyə davam edir.
}
