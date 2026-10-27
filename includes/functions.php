<?php
/** Köməkçi funksiyalar */

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Daxili URL qurur: url('/xidmetler') */
function url(string $path = '/', ?string $lang = null): string {
    $lang = $lang ?? (defined('LANG') ? LANG : 'az');
    $pre  = function_exists('lang_prefix') ? lang_prefix($lang) : '';
    $p    = function_exists('route_path') ? route_path($path, $lang) : ('/' . ltrim($path, '/'));
    if ($p === '/') return BASE_URL . $pre . '/';
    return BASE_URL . $pre . rtrim($p, '/') . '/';   // orijinal sayt kimi sonda slash
}

/**
 * Saytın kök ünvanı — https://caucasuscl.com (sonda slash yoxdur).
 * canonical, hreflang və og: teqləri üçün mütləq ünvan lazımdır:
 * Google nisbi hreflang ünvanlarını nəzərə almır.
 */
function site_origin(): string {
    static $o = null;
    if ($o !== null) return $o;
    if (defined('SITE_ORIGIN') && SITE_ORIGIN !== '') return $o = rtrim(SITE_ORIGIN, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
          || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $host = $_SERVER['HTTP_HOST'] ?? 'caucasuscl.com';
    return $o = ($https ? 'https' : 'http') . '://' . $host;
}

/** Nisbi yolu mütləq ünvana çevirir */
function abs_url(string $path): string {
    if ($path === '' || preg_match('~^https?://~i', $path)) return $path;
    return site_origin() . '/' . ltrim($path, '/');
}

/**
 * Bu səhifənin kanonik (mütləq) ünvanı.
 * Səhifə $page_canonical dəyişəni ilə öz dəyərini verə bilər (seo_pages.canonical),
 * verilmədikdə cari ünvanın özü kanonik sayılır.
 */
function canonical_url(string $override = ''): string {
    if ($override !== '') return abs_url($override);
    $self = function_exists('lang_switch_url') && defined('LANG')
        ? lang_switch_url(LANG) : url('/');
    return abs_url($self);
}

/**
 * Bazadan gələn xarici ünvanı təhlükəsiz hala gətirir.
 * Yalnız http(s), mailto və tel sxemlərinə icazə verilir — `javascript:` və
 * `data:` kimi sxemlər boş sətrə çevrilir.
 */
function safe_url(?string $u): string {
    $u = trim((string)$u);
    if ($u === '') return '';
    if (preg_match('/[\x00-\x1F\x7F]/', $u)) return '';
    if (str_starts_with($u, '/') || str_starts_with($u, '#')) return $u;   // daxili yol
    if (preg_match('~^(https?://|mailto:|tel:)~i', $u)) return $u;
    return '';
}

/* ==========================================================================
   MƏZMUN FORMATI
   Mətn bazada sadə şəkildə saxlanılır, HTML kimi yox — çıxışda hər parça
   escape olunur, ona görə paneldən XSS keçmir. Paneldəki vizual redaktor
   məhz bu formata çevirir.

     ## Böyük başlıq          -> <h2>
     ### Kiçik başlıq         -> <h3>
     adi sətir                -> <p>
     - punkt                  -> <ul>
     + punkt                  -> <ol>   (1. işlənmir: fərmanların mətnində
                                         "1.", "1.1." sətirləri var)
     * Kart başlığı | təsvir  -> kart (yalnız | olduqda)

   Sətir daxilində: **qalın**, *kursiv*, [mətn](ünvan)
   ========================================================================== */

/** Sətir daxilindəki işarələmə. Əvvəlcə escape, sonra təhlükəsiz teqlər. */
function cms_inline(string $s): string {
    $out = e($s);

    /* [mətn](ünvan) — ünvan safe_url-dən keçir, `javascript:` buraxılmır */
    $out = preg_replace_callback(
        '~\[([^\]\[]{1,200})\]\(([^)\s]{1,300})\)~u',
        static function (array $m): string {
            $href = safe_url(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
            if ($href === '') return $m[1];                 // ünvan yararsızdır — yalnız mətn
            $ext = (bool)preg_match('~^https?://~i', $href)
                && stripos($href, (string)parse_url(site_origin(), PHP_URL_HOST)) === false;
            return '<a href="' . e($href) . '"'
                 . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
        },
        $out
    ) ?? $out;

    $out = preg_replace('~\*\*(?=\S)(.+?)(?<=\S)\*\*~u', '<strong>$1</strong>', $out) ?? $out;
    $out = preg_replace('~(?<!\*)\*(?=\S)([^*]+?)(?<=\S)\*(?!\*)~u', '<em>$1</em>', $out) ?? $out;

    return $out;
}

/**
 * Mətni ardıcıl bloklara ayırır.
 * cms_body_decode()-dan fərqi: blokların SIRASI saxlanılır — abzas, siyahı,
 * yenə abzas ardıcıllığı redaktorda yazıldığı kimi çıxır.
 */
function cms_body_blocks(string $body): array {
    $blocks = [];
    $open = null;                                   // açıq siyahı: ['ul'|'ol'|'cards', [...]]

    $flush = static function () use (&$open, &$blocks): void {
        if ($open) { $blocks[] = ['type' => $open[0], 'items' => $open[1]]; $open = null; }
    };

    foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
        $line = trim($line);
        if ($line === '') { $flush(); continue; }

        if (str_starts_with($line, '### ')) {
            $flush(); $blocks[] = ['type' => 'h3', 'text' => trim(substr($line, 4))]; continue;
        }
        if (str_starts_with($line, '## ')) {
            $flush(); $blocks[] = ['type' => 'h2', 'text' => trim(substr($line, 3))]; continue;
        }
        /* kart yalnız "| " ayırıcısı olduqda; əks halda adi abzasdır */
        if (str_starts_with($line, '* ') && str_contains($line, '|')) {
            $p = explode('|', substr($line, 2), 2);
            if (!$open || $open[0] !== 'cards') { $flush(); $open = ['cards', []]; }
            $open[1][] = ['t' => trim($p[0]), 'd' => trim($p[1] ?? '')];
            continue;
        }
        foreach (['- ' => 'ul', '+ ' => 'ol'] as $mark => $kind) {
            if (str_starts_with($line, $mark)) {
                if (!$open || $open[0] !== $kind) { $flush(); $open = [$kind, []]; }
                $open[1][] = trim(substr($line, 2));
                continue 2;
            }
        }
        $flush();
        $blocks[] = ['type' => 'p', 'text' => $line];
    }
    $flush();
    return $blocks;
}

/**
 * Köhnə `sections` massivini bloklara çevirir.
 * Baza əlçatmaz olduqda məzmun includes/config.php-dəki massivlərdən gəlir —
 * orada mətn deyil, hazır bölmələr var.
 */
function cms_sections_to_blocks(array $sections): array {
    $blocks = [];
    foreach ($sections as $sec) {
        if (!empty($sec['h']))     $blocks[] = ['type' => 'h2', 'text' => (string)$sec['h']];
        foreach ($sec['p'] ?? [] as $p)  $blocks[] = ['type' => 'p', 'text' => (string)$p];
        if (!empty($sec['list']))  $blocks[] = ['type' => 'ul', 'items' => array_map('strval', $sec['list'])];
        if (!empty($sec['items'])) $blocks[] = ['type' => 'cards', 'items' => $sec['items']];
    }
    return $blocks;
}

/**
 * Gövdənin hazır HTML-i. Bütün mətn escape olunur.
 * $body — mətn formatı və ya köhnə `sections` massivi.
 * $cards — kartların göstərilməsi (xəbərlərdə istifadə olunmur).
 */
function cms_body_html($body, bool $cards = true): string {
    $blocks = is_array($body) ? cms_sections_to_blocks($body) : cms_body_blocks((string)$body);
    $out = '';
    foreach ($blocks as $b) {
        switch ($b['type']) {
            case 'h2': $out .= '<h2>' . cms_inline($b['text']) . '</h2>'; break;
            case 'h3': $out .= '<h3>' . cms_inline($b['text']) . '</h3>'; break;
            case 'p':  $out .= '<p>'  . cms_inline($b['text']) . '</p>';  break;
            case 'ul':
                $out .= '<ul>';
                foreach ($b['items'] as $li) $out .= '<li>' . icon('check') . ' <span>' . cms_inline($li) . '</span></li>';
                $out .= '</ul>';
                break;
            case 'ol':
                $out .= '<ol>';
                foreach ($b['items'] as $li) $out .= '<li>' . cms_inline($li) . '</li>';
                $out .= '</ol>';
                break;
            case 'cards':
                if (!$cards) {           // xəbərlərdə kart yoxdur — siyahı kimi veririk
                    $out .= '<ul>';
                    foreach ($b['items'] as $it) {
                        $out .= '<li>' . icon('check') . ' <span><b>' . cms_inline($it['t']) . '</b>'
                              . ($it['d'] !== '' ? ' — ' . cms_inline($it['d']) : '') . '</span></li>';
                    }
                    $out .= '</ul>';
                    break;
                }
                $out .= '<div class="cards2">';
                foreach ($b['items'] as $it)
                    $out .= '<div class="mini-card"><b>' . cms_inline($it['t']) . '</b><span>'
                          . cms_inline($it['d']) . '</span></div>';
                $out .= '</div>';
                break;
        }
    }
    return $out;
}

/**
 * Xəbərin üz qabığı. Öz şəkli yoxdursa — bütün xəbərlər üçün ümumi şəkil.
 * Fayl yoxa çıxıbsa da ümumiyə qayıdırıq ki, boş çərçivə qalmasın.
 */
function news_cover(array $n): string {
    $f = trim((string)($n['image'] ?? ''));
    if ($f !== '' && is_file(dirname(__DIR__) . '/assets/img/' . $f)) return $f;
    return defined('NEWS_IMG_DEFAULT') ? NEWS_IMG_DEFAULT : (string)($GLOBALS['NEWS_IMG'] ?? '');
}

/**
 * Yazı bizim öz məqaləmizdirmi?
 *
 * Xəbərlərin böyük hissəsi Dövlət Gömrük Komitəsinin materiallarıdır və
 * onun adı ilə imzalanır. Öz mətnimizi dövlət orqanının adı ilə vermək,
 * eləcə də əksini etmək olmaz — ona görə mənbə təxmin edilmir, bazadakı
 * `is_own` sahəsindən oxunur (paneldə açıq keçid var).
 */
function news_is_own(array $n): bool {
    return (int)($n['is_own'] ?? 0) === 1;
}

/** Şəkil URL-i: img('2022/08/3-1.jpg') */
function img(string $file): string {
    return rtrim(IMG_BASE, '/') . '/' . ltrim($file, '/');
}

/**
 * Şəklin ölçü atributları: width="1600" height="1000".
 * Brauzer şəkil yüklənməmişdən yer ayırır — səhifə tullanmır (CLS).
 * Ölçü fayldan oxunur, ona görə panel başqa şəkil yükləsə də düzgün qalır.
 */
function img_attrs(string $file): string {
    static $cache = [];
    $file = ltrim($file, '/');
    if ($file === '') return '';
    if (!array_key_exists($file, $cache)) {
        $path = dirname(__DIR__) . '/assets/img/' . $file;
        $s = is_file($path) ? @getimagesize($path) : false;
        $cache[$file] = $s ? ' width="' . (int)$s[0] . '" height="' . (int)$s[1] . '"' : '';
    }
    return $cache[$file];
}

/** Aktiv menyu bölməsi */
function is_active(string $key): bool {
    return (defined('PAGE_KEY') ? PAGE_KEY : '') === $key;
}

/** Tarixi cari dildə formatlayır: 2023-11-20 -> 20 Noyabr 2023 */
function az_date(string $iso): string {
    static $months = [
        'az' => [1=>'Yanvar','Fevral','Mart','Aprel','May','İyun','İyul','Avqust','Sentyabr','Oktyabr','Noyabr','Dekabr'],
        'ru' => [1=>'января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'],
        'en' => [1=>'January','February','March','April','May','June','July','August','September','October','November','December'],
    ];
    $t = strtotime($iso);
    if (!$t) return $iso;
    $lang = defined('LANG') ? LANG : 'az';
    $m = $months[$lang] ?? $months['az'];
    $name = $m[(int)date('n', $t)];
    return $lang === 'en'
        ? $name . ' ' . date('j', $t) . ', ' . date('Y', $t)
        : date('j', $t) . ' ' . $name . ' ' . date('Y', $t);
}

/** Mətni qısaldır */
function excerpt(string $s, int $len = 120): string {
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    if (mb_strlen($s) <= $len) return $s;
    return mb_substr($s, 0, $len) . '…';
}

/** Çoxsətirli mətni boş olmayan sətirlərə bölür */
function lines(string $s): array {
    $out = [];
    foreach (preg_split('/\R/u', $s) ?: [] as $l) {
        $l = trim($l);
        if ($l !== '') $out[] = $l;
    }
    return $out;
}

/** Açılan dil seçici (topbar üçün 'dark', mobil menyu üçün 'light') */
function lang_selector(string $variant = 'dark'): string {
    $cur = LANGS[LANG];
    $out = '<div class="langsel langsel--' . e($variant) . '" data-langsel>'
         . '<button type="button" class="langsel-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="'
         . e($cur['name']) . '"><span>' . e($cur['short']) . '</span>'
         . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>'
         . '</button><ul class="langsel-list" role="listbox">';
    foreach (LANGS as $lc => $li) {
        $on = $lc === LANG;
        $out .= '<li><a' . ($on ? ' class="on"' : '') . ' href="' . e(lang_switch_url($lc)) . '"'
              . ' hreflang="' . e($lc) . '" role="option" aria-selected="' . ($on ? 'true' : 'false') . '">'
              . '<b>' . e($li['short']) . '</b><span>' . e($li['name']) . '</span></a></li>';
    }
    return $out . '</ul></div>';
}

/** Sadə SVG ikon kitabxanası */
function icon(string $name, string $cls = ''): string {
    $c = $cls ? ' class="' . e($cls) . '"' : '';
    $o = '<svg' . $c . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    $paths = [
        'pin'    => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>',
        'phone'  => '<path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .4 1.9.7 2.8a2 2 0 01-.4 2.1L8.1 9.9a16 16 0 006 6l1.3-1.3a2 2 0 012.1-.5c.9.3 1.8.6 2.8.7a2 2 0 011.7 2z"/>',
        'clock'  => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'mail'   => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 6 10-6"/>',
        'check'  => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'back'   => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'ext'    => '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/>',
        'users'  => '<path d="M17 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.9"/>',
        'doc'    => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/>',
        'truck'  => '<path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'play'   => '<circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4z"/>',
    ];
    return $o . ($paths[$name] ?? '') . '</svg>';
}

/** Turnstile açarları: əvvəlcə admin panelindən, sonra config.php-dən */
function turnstile_key(string $which): string {
    $fromDb = function_exists('cms_setting') ? cms_setting('turnstile_' . $which) : '';
    if ($fromDb !== '') return $fromDb;
    return $which === 'site' ? TURNSTILE_SITE_KEY : TURNSTILE_SECRET_KEY;
}
function turnstile_on(): bool {
    return turnstile_key('site') !== '' && turnstile_key('secret') !== '';
}

/** Turnstile vidceti (forma daxilində çağırılır) */
function turnstile_widget(string $theme = 'light'): string {
    if (!turnstile_on()) return '';
    /* Turnstile azərbaycan dilini dəstəkləmir — brauzerin dilinə buraxırıq */
    $lang = defined('LANG') ? LANG : 'az';
    $tsLang = in_array($lang, ['ru', 'en'], true) ? $lang : 'auto';
    return '<div class="turnstile"><div class="cf-turnstile"'
         . ' data-sitekey="' . e(turnstile_key('site')) . '"'
         . ' data-theme="' . e($theme) . '"'
         . ' data-language="' . e($tsLang) . '"'
         . ' data-size="flexible"></div></div>';
}

/** Turnstile tokenini Cloudflare-də yoxlayır */
function turnstile_verify(string $token, string $ip = ''): bool {
    if (!turnstile_on()) return true;
    if ($token === '')   return false;

    $url  = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $post = ['secret' => turnstile_key('secret'), 'response' => $token];
    if ($ip !== '') $post['remoteip'] = $ip;

    $raw = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($post),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    }

    if ($raw === false) {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => http_build_query($post),
            'timeout'       => 10,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
    }

    if (!is_string($raw) || $raw === '') return false;

    $json = json_decode($raw, true);
    return !empty($json['success']);
}

/**
 * Sosial şəbəkə kartının şəkli (1200x630).
 * Səhifə $page_image ilə öz şəklini verə bilər.
 * SVG QƏBUL EDİLMİR: Facebook, WhatsApp və Telegram SVG-ni göstərmir.
 */
function og_image(string $override = ''): array {
    $def = ['file' => 'og-caucasuscl.jpg', 'w' => 1200, 'h' => 630];
    $f = trim($override);
    if ($f === '' || preg_match('~\.svg$~i', $f)) $f = $def['file'];

    $path = dirname(__DIR__) . '/assets/img/' . ltrim($f, '/');
    $w = $def['w']; $h = $def['h'];
    if (is_file($path) && ($s = @getimagesize($path))) { $w = $s[0]; $h = $s[1]; }

    return ['url' => abs_url(img($f)), 'w' => $w, 'h' => $h];
}

/**
 * Strukturlaşdırılmış məlumat (schema.org / JSON-LD).
 * Axtarış sistemləri şirkəti, ünvanı, iş saatlarını və səhifənin növünü
 * bu blokdan oxuyur — nəticələrdə zəngin görünüş üçün lazımdır.
 */
function json_ld(array $extra = []): string {
    global $CONTACT;
    $home = site_origin() . '/';
    $orgId = $home . '#org';

    $sameAs = array_values(array_filter([
        safe_url($CONTACT['facebook']  ?? ''),
        safe_url($CONTACT['instagram'] ?? ''),
    ]));

    $org = [
        '@type'       => 'ProfessionalService',
        '@id'         => $orgId,
        'name'        => cms_text('site_name', SITE_NAME),
        'url'         => $home,
        'logo'        => abs_url(img('Caucasus-1.svg')),
        'image'       => og_image()['url'],
        'description' => cms_text('site_slogan', SITE_SLOGAN),
        'telephone'   => (string)($CONTACT['phone_raw'] ?? ''),
        'email'       => (string)($CONTACT['email'] ?? ''),
        'foundingDate' => '2018-12-03',
        'address'     => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => (string)($CONTACT['address'] ?? ''),
            'addressLocality' => (string)($CONTACT['city'] ?? 'Bakı'),
            'addressCountry'  => 'AZ',
        ],
        'areaServed'  => ['@type' => 'Country', 'name' => 'Azerbaijan'],
        /* saytda göstərilən iş saatları ilə eynidir */
        'openingHoursSpecification' => [
            ['@type' => 'OpeningHoursSpecification',
             'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
             'opens' => '09:00', 'closes' => '18:00'],
            ['@type' => 'OpeningHoursSpecification',
             'dayOfWeek' => ['Saturday'], 'opens' => '09:00', 'closes' => '15:00'],
        ],
    ];
    if ($sameAs) $org['sameAs'] = $sameAs;

    $graph = [$org];
    foreach ($extra as $node) if ($node) $graph[] = $node;

    $data = ['@context' => 'https://schema.org', '@graph' => $graph];
    return '<script type="application/ld+json">'
         . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
         . '</script>';
}

/** Naviqasiya zənciri (schema.org BreadcrumbList) */
function ld_breadcrumbs(array $items): array {
    $list = [];
    $i = 1;
    foreach ($items as $name => $url) {
        $el = ['@type' => 'ListItem', 'position' => $i++, 'name' => (string)$name];
        if ($url !== '') $el['item'] = abs_url($url);
        $list[] = $el;
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

/**
 * Google Analytics 4 teqləri.
 * Ölçmə nömrəsi paneldən gəlir (settings.ga_id); boşdursa heç nə əlavə olunmur.
 * Google-un inline hissəsi assets/js/ga.js faylındadır — CSP inline skriptlərə
 * icazə vermir.
 */
function analytics_tags(): string {
    $id = function_exists('cms_setting') ? trim(cms_setting('ga_id')) : '';
    /* yalnız düzgün formatlı nömrə: G-XXXX, UA-XXXX-Y, GT-XXXX */
    if ($id === '' || !preg_match('/^(G|GT|UA|AW)-[A-Z0-9\-]{4,20}$/i', $id)) return '';

    return "\n" . '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e(rawurlencode($id)) . '"></script>'
         . "\n" . '<script src="' . e(BASE_URL . '/assets/js/ga.js') . '?v=1" data-ga-id="' . e($id) . '" defer></script>';
}

/**
 * «WhatsApp ilə göndər» düyməsi.
 * Formanın içində işlədilir: JS forma sahələrini yığıb wa.me ünvanını qurur.
 * Serverə sorğu getmir, ona görə kapça tələb olunmur.
 */
function whatsapp_button(): string {
    $phone = preg_replace('/\D+/', '', (string)($GLOBALS['CONTACT']['phone_raw'] ?? ''));
    if ($phone === '') return '';

    $labels = [
        'intro'   => t('wa.intro'),
        'name'    => t('f.name'),
        'surname' => t('f.surname'),
        'phone'   => t('f.phone'),
        'email'   => t('f.email'),
        'subject' => t('f.subject'),
        'message' => t('f.message'),
    ];

    return '<button type="button" class="btn btn-wa btn-full" data-wa-send'
         . ' data-wa-phone="' . e($phone) . '"'
         . ' data-wa-labels="' . e((string)json_encode($labels, JSON_UNESCAPED_UNICODE)) . '">'
         . social_icon('whatsapp') . ' <span>' . e(t('btn.whatsapp')) . '</span></button>';
}

function social_icon(string $name): string {
    switch ($name) {
        case 'facebook':
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H8v3h2v7h3v-7h3l1-3h-4v-2c0-.6.4-1 1-1z"/></svg>';
        case 'instagram':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>';
        default:
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15L2 22l5.2-1.4A10 10 0 1012 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1-.4-.1-.9-.3-1.5-.6-2.7-1.2-4.4-3.9-4.5-4-.1-.2-1-1.4-1-2.6s.6-1.8.9-2.1c.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2 0 .4-.1.5l-.3.4c-.1.2-.3.3-.1.6.1.3.6 1.1 1.4 1.8 1 .8 1.7 1.1 2 1.2.2.1.4.1.6-.1l.7-.9c.2-.2.4-.2.6-.1l1.8.9c.2.1.4.2.5.3 0 .1 0 .6-.1 1.2z"/></svg>';
    }
}
