<?php
/*
 * Cədvəllərin yaradılması və saytın ilkin məzmununun bazaya köçürülməsi.
 * SQLite və MySQL — hər ikisi ilə işləyir.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/compat.php';

function cms_is_sqlite(PDO $db): bool {
    return $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
}

/** id sütununun driver-ə uyğun tərifi */
function cms_pk(PDO $db): string {
    return cms_is_sqlite($db)
        ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
        : 'id INT AUTO_INCREMENT PRIMARY KEY';
}

function cms_create_tables(PDO $db): array {
    $pk   = cms_pk($db);
    $suf  = cms_is_sqlite($db) ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $made = [];

    $tables = [
        'admins' => "$pk,
            `name` VARCHAR(120) DEFAULT '',
            `email` VARCHAR(190) NOT NULL UNIQUE,
            `pass_hash` VARCHAR(255) NOT NULL,
            `role` VARCHAR(20) DEFAULT 'editor',
            `active` INT DEFAULT 1,
            `last_login` VARCHAR(40) DEFAULT NULL",

        'settings' => "`k` VARCHAR(80) PRIMARY KEY, `v` TEXT",
        'texts'    => "`lang` VARCHAR(5) NOT NULL DEFAULT 'az', `k` VARCHAR(80) NOT NULL, `v` TEXT, PRIMARY KEY (`lang`, `k`)",
        'contacts' => "`lang` VARCHAR(5) NOT NULL DEFAULT 'az', `k` VARCHAR(80) NOT NULL, `v` TEXT, PRIMARY KEY (`lang`, `k`)",

        'services' => "$pk,
            `lang` VARCHAR(5) NOT NULL DEFAULT 'az',
            `slug` VARCHAR(120) NOT NULL,
            `no` VARCHAR(8) DEFAULT '',
            `title` VARCHAR(255) DEFAULT '',
            `short` TEXT,
            `img` VARCHAR(255) DEFAULT '',
            `lead` TEXT,
            `body` TEXT,
            `meta_title` VARCHAR(255) DEFAULT '',
            `meta_desc` TEXT,
            `sort` INT DEFAULT 0,
            `visible` INT DEFAULT 1,
            `tkey` VARCHAR(160) DEFAULT '',
            UNIQUE (`lang`, `slug`)",

        'news' => "$pk,
            `lang` VARCHAR(5) NOT NULL DEFAULT 'az',
            `slug` VARCHAR(160) NOT NULL,
            `title` VARCHAR(255) DEFAULT '',
            `pub_date` VARCHAR(20) DEFAULT '',
            `video` VARCHAR(60) DEFAULT '',
            `question` TEXT,
            `excerpt` TEXT,
            `sort` INT DEFAULT 0,
            `visible` INT DEFAULT 1,
            `body` TEXT,
            `tkey` VARCHAR(160) DEFAULT '',
            `meta_title` VARCHAR(255) DEFAULT '',
            `meta_desc` TEXT,
            `is_own` INT DEFAULT 0,
            `image` VARCHAR(255) DEFAULT '',
            UNIQUE (`lang`, `slug`)",

        'clients' => "$pk, `name` VARCHAR(160) DEFAULT '', `image` VARCHAR(255) DEFAULT '', `url` VARCHAR(255) DEFAULT '', `sort` INT DEFAULT 0",
        'links'   => "$pk, `lang` VARCHAR(5) NOT NULL DEFAULT 'az', `title` VARCHAR(190) DEFAULT '', `url` VARCHAR(255) DEFAULT '', `descr` TEXT, `sort` INT DEFAULT 0",
        'stats'   => "$pk, `lang` VARCHAR(5) NOT NULL DEFAULT 'az', `num` INT DEFAULT 0, `suffix` VARCHAR(8) DEFAULT '', `label` VARCHAR(120) DEFAULT '', `sort` INT DEFAULT 0",
        'process' => "$pk, `lang` VARCHAR(5) NOT NULL DEFAULT 'az', `num` VARCHAR(8) DEFAULT '', `title` VARCHAR(190) DEFAULT '', `descr` TEXT, `sort` INT DEFAULT 0",
        'whyus'   => "$pk, `lang` VARCHAR(5) NOT NULL DEFAULT 'az', `title` VARCHAR(190) DEFAULT '', `descr` TEXT, `sort` INT DEFAULT 0",

        'seo_pages' => "`lang` VARCHAR(5) NOT NULL DEFAULT 'az',
            `slug` VARCHAR(120) NOT NULL,
            `title` VARCHAR(255) DEFAULT '',
            `descr` TEXT,
            `robots` VARCHAR(60) DEFAULT 'index,follow',
            `canonical` VARCHAR(255) DEFAULT '',
            `hero_title` VARCHAR(255) DEFAULT '',
            `hero_text` TEXT,
            `body` TEXT,
            PRIMARY KEY (`lang`, `slug`)",

        'submissions' => "$pk,
            `name` VARCHAR(120) DEFAULT '',
            `surname` VARCHAR(120) DEFAULT '',
            `phone` VARCHAR(60) DEFAULT '',
            `email` VARCHAR(190) DEFAULT '',
            `subject` VARCHAR(190) DEFAULT '',
            `message` TEXT,
            `is_read` INT DEFAULT 0,
            `lang` VARCHAR(5) DEFAULT 'az',
            `ip` VARCHAR(60) DEFAULT '',
            `created_at` VARCHAR(30) DEFAULT ''",
    ];

    foreach ($tables as $name => $cols) {
        $db->exec("CREATE TABLE IF NOT EXISTS `$name` ($cols)$suf");
        $made[] = $name;
    }
    return $made;
}

/* ---------- xidmət mətninin sadə formatı ----------
     ## Bolme basligi
     Adi abzas.
     - siyahi elementi
     * Kart basligi | Kart tesviri
--------------------------------------------------- */
function cms_body_encode(array $sections): string {
    $out = [];
    foreach ($sections as $s) {
        if (!empty($s['h'])) $out[] = '## ' . $s['h'];
        foreach ($s['p'] ?? [] as $p)   $out[] = $p;
        foreach ($s['list'] ?? [] as $l) $out[] = '- ' . $l;
        foreach ($s['items'] ?? [] as $i) $out[] = '* ' . $i['t'] . ' | ' . $i['d'];
        $out[] = '';
    }
    return trim(implode("\n", $out));
}

function cms_body_decode(string $body): array {
    $sections = [];
    $cur = null;
    $push = static function () use (&$cur, &$sections) {
        if ($cur && ($cur['h'] !== '' || $cur['p'] || $cur['list'] || $cur['items'])) $sections[] = $cur;
        $cur = null;
    };
    foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (str_starts_with($line, '## ')) {
            $push();
            $cur = ['h' => trim(substr($line, 3)), 'p' => [], 'list' => [], 'items' => []];
            continue;
        }
        if ($cur === null) $cur = ['h' => '', 'p' => [], 'list' => [], 'items' => []];
        if (str_starts_with($line, '- ')) {
            $cur['list'][] = trim(substr($line, 2));
        } elseif (str_starts_with($line, '* ')) {
            $parts = explode('|', substr($line, 2), 2);
            $cur['items'][] = ['t' => trim($parts[0]), 'd' => trim($parts[1] ?? '')];
        } else {
            $cur['p'][] = $line;
        }
    }
    $push();
    return $sections;
}

/** includes/config.php massivlərini bazaya köçürür (yalnız boş cədvəllərə) */
function cms_seed(PDO $db): array {
    if (!defined('CMS_NO_DB')) define('CMS_NO_DB', true);   // content.php dövrəyə girməsin
    require __DIR__ . '/../includes/config.php';            // massivlər bu funksiyanın sahəsinə düşür

    $log = [];
    $empty = static fn(string $t): bool => (int)$db->query("SELECT COUNT(*) c FROM `$t`")->fetch()['c'] === 0;
    $ins = static function (string $table, array $rows) use ($db, &$log) {
        if (!$rows) return;
        $cols = array_keys($rows[0]);
        $sql  = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
        $st   = $db->prepare($sql);
        foreach ($rows as $r) $st->execute(array_values($r));
        $log[] = $table . ': ' . count($rows);
    };

    /* --- key-value --- */
    if ($empty('contacts')) {
        $rows = [];
        foreach ($CONTACT as $k => $v) $rows[] = ['k' => $k, 'v' => (string)$v];
        $ins('contacts', $rows);
    }

    if ($empty('texts')) {
        $quoteOpen = html_entity_decode('&laquo;');
        $ins('texts', [
            ['k' => 'site_name',     'v' => SITE_NAME],
            ['k' => 'site_short',    'v' => SITE_SHORT],
            ['k' => 'site_slogan',   'v' => SITE_SLOGAN],
            ['k' => 'hero_badge',    'v' => '2018-ci ildən lisenziyalı gömrük brokeri'],
            ['k' => 'hero_title',    'v' => 'Gömrük rəsmiləşdirilməsi artıq'],
            ['k' => 'hero_accent',   'v' => 'problem deyil'],
            ['k' => 'hero_text',     'v' => 'Bəyannamədən son ünvana çatdırılmayadək — idxal və ixrac əməliyyatlarınızın bütün mərhələsini biz idarə edirik. Səmimi rəftar, yüksək keyfiyyət və operativlik.'],
            ['k' => 'svc_title',     'v' => 'Tam gömrük təmsilçiliyi — bir ünvandan'],
            ['k' => 'svc_text',      'v' => 'Yeddi əsas istiqamət üzrə kompleks xidmət. Hər bir yükə fərdi yanaşma və qanunvericiliyə tam uyğunluq.'],
            ['k' => 'about_title',   'v' => SITE_SLOGAN],
            ['k' => 'about_p1',      'v' => SITE_NAME . ' MMC 03.12.2018-ci il tarixindən etibarən gömrük brokeri qismində fəaliyyət göstərir. İxtisaslaşmış gömrük təmsilçiliyimiz Azərbaycan Respublikası gömrük fəaliyyəti zonasında dünya təcrübəsi əsasında yaradılmışdır.'],
            ['k' => 'about_p2',      'v' => 'Əsas prinsipimiz — şirkətlərin məxfiliyini qanunvericiliyə uyğun qorumaq, hər müştəriyə individual yanaşmaq və yaranmış istənilən suala vaxtında cavab verməkdir.'],
            ['k' => 'about_ticks',   'v' => "Təcrübəli və ixtisaslaşdırılmış işçi qrupu\nKommersiya məlumatlarının tam məxfiliyi\n18 fərqli sahə üzrə praktiki təcrübə\nVahid əlaqə nöqtəsi — bir menecer, bütün proses"],
            ['k' => 'process_title', 'v' => 'Dörd addımda gömrükdən keçin'],
            ['k' => 'process_text',  'v' => 'Şəffaf proses — hər mərhələdə sizə statusu bildiririk.'],
            ['k' => 'news_title',    'v' => 'Gömrük xəbərləri və sual-cavab'],
            ['k' => 'news_text',     'v' => 'Dövlət Gömrük Komitəsinin rəsmi izahları əsasında ən çox verilən suallar.'],
            ['k' => 'clients_title', 'v' => '18 fərqli sahədə 30-dan çox daimi tərəfdaş'],
            ['k' => 'mail_to',       'v' => MAIL_TO],
        ]);
    }

    if ($empty('settings')) {
        $ins('settings', [
            ['k' => 'turnstile_site',   'v' => TURNSTILE_SITE_KEY],
            ['k' => 'turnstile_secret', 'v' => TURNSTILE_SECRET_KEY],
            ['k' => 'smtp_secure',      'v' => 'tls'],
            ['k' => 'smtp_port',        'v' => '587'],
            ['k' => 'smtp_from_name',   'v' => SITE_SHORT],
        ]);
    }

    /* --- məzmun --- */
    if ($empty('services')) {
        $rows = []; $i = 0;
        foreach ($SERVICES as $slug => $s) {
            $rows[] = [
                'slug'  => $slug,
                'no'    => $s['no'],
                'title' => $s['title'],
                'short' => $s['short'],
                'img'   => $s['img'],
                'lead'  => $s['lead'],
                'body'  => cms_body_encode($s['sections'] ?? []),
                'meta_title' => $s['meta_title'] ?? '',
                'meta_desc'  => $s['meta_desc'] ?? '',
                'sort' => $i++, 'visible' => 1,
            ];
        }
        $ins('services', $rows);
    }

    if ($empty('news')) {
        $rows = []; $i = 0;
        foreach ($NEWS as $slug => $n) {
            $rows[] = [
                'slug' => $slug, 'title' => $n['title'], 'pub_date' => $n['date'],
                'video' => $n['video'] ?? '', 'question' => $n['question'] ?? '',
                'excerpt' => $n['excerpt'] ?? '', 'sort' => $i++, 'visible' => 1,
            ];
        }
        $ins('news', $rows);
    }

    if ($empty('clients')) {
        $rows = []; $i = 0;
        foreach ($CLIENTS as $img) $rows[] = ['name' => '', 'image' => $img, 'url' => '', 'sort' => $i++];
        $ins('clients', $rows);
    }

    if ($empty('links')) {
        $rows = []; $i = 0;
        foreach ($USEFUL_LINKS as $l) $rows[] = ['title' => $l['t'], 'url' => $l['u'], 'descr' => $l['d'], 'sort' => $i++];
        $ins('links', $rows);
    }

    if ($empty('stats')) {
        $rows = []; $i = 0;
        foreach ($STATS as $s) $rows[] = ['num' => (int)$s['n'], 'suffix' => $s['suffix'], 'label' => $s['label'], 'sort' => $i++];
        $ins('stats', $rows);
    }

    if ($empty('process')) {
        $rows = []; $i = 0;
        foreach ($PROCESS as $p) $rows[] = ['num' => $p['n'], 'title' => $p['t'], 'descr' => $p['d'], 'sort' => $i++];
        $ins('process', $rows);
    }

    if ($empty('whyus')) {
        $rows = []; $i = 0;
        foreach ($WHY_US as $w) $rows[] = ['title' => $w['t'], 'descr' => $w['d'], 'sort' => $i++];
        $ins('whyus', $rows);
    }

    if ($empty('seo_pages')) {
        $ins('seo_pages', [
            ['slug' => '/',          'title' => 'Gömrük brokeri Bakıda | ' . SITE_NAME, 'descr' => 'Bakıda lisenziyalı gömrük brokeri. Gömrük bəyannamələri, idxal-ixrac sənədləri, sertifikatlar, logistika və yüklərin çatdırılması — 2018-ci ildən.', 'robots' => 'index,follow', 'canonical' => ''],
            ['slug' => 'haqqimizda', 'title' => 'Haqqımızda | ' . SITE_NAME, 'descr' => 'Caucasus Customs and Logistics MMC 2018-ci ildən gömrük brokeri qismində fəaliyyət göstərir.', 'robots' => 'index,follow', 'canonical' => ''],
            ['slug' => 'xidmetler',  'title' => 'Gömrük və logistika xidmətləri Bakıda | ' . SITE_NAME, 'descr' => 'Bakıda gömrük və logistika xidmətləri: bəyannamələr, sənədlər, sertifikatlar, çatdırılma, logistika, konsaltinq, konsiqnasiya.', 'robots' => 'index,follow', 'canonical' => ''],
            ['slug' => 'xeberler',   'title' => 'Xəbərlər və gömrük sual-cavab | ' . SITE_NAME, 'descr' => 'Dövlət Gömrük Komitəsinin rəsmi izahları əsasında ən çox verilən gömrük sualları.', 'robots' => 'index,follow', 'canonical' => ''],
            ['slug' => 'elaqe',      'title' => 'Əlaqə | ' . SITE_NAME, 'descr' => 'Bakı, Heydər Əliyev Prospekti 95a. Telefon: (+994) 70 548 01 10.', 'robots' => 'index,follow', 'canonical' => ''],
            ['slug' => 'linkler',    'title' => 'Faydalı linklər | ' . SITE_NAME, 'descr' => 'İdxal-ixrac və gömrük rəsmiləşdirilməsi üçün dövlət orqanlarının rəsmi saytları.', 'robots' => 'index,follow', 'canonical' => ''],
        ]);
    }

    return $log;
}
