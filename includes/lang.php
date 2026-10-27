<?php
/**
 * Çoxdillilik: AZ (əsas), RU, EN.
 *
 * URL sxemi:
 *   AZ  →  /xidmetler
 *   RU  →  /ru/xidmetler
 *   EN  →  /en/xidmetler
 *
 * Yol seqmentləri bütün dillərdə eynidir — dil dəyişəndə istifadəçi
 * eyni səhifədə qalır və tərcümə çatmayanda link sınmır.
 */
require_once __DIR__ . '/compat.php';

require_once __DIR__ . '/routes.php';

const LANG_DEFAULT = 'az';

const LANGS = [
    'az' => ['short' => 'AZ', 'name' => 'Azərbaycan', 'locale' => 'az_AZ', 'html' => 'az'],
    'ru' => ['short' => 'RU', 'name' => 'Русский',    'locale' => 'ru_RU', 'html' => 'ru'],
    'en' => ['short' => 'EN', 'name' => 'English',    'locale' => 'en_US', 'html' => 'en'],
];

/** Sorğu ünvanından dili və dilsiz yolu ayırır */
function lang_detect(): array {
    $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = defined('BASE_URL') ? BASE_URL : '';
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    $uri = '/' . ltrim($uri, '/');

    foreach (array_keys(LANGS) as $code) {
        if ($code === LANG_DEFAULT) continue;
        if ($uri === '/' . $code || str_starts_with($uri, '/' . $code . '/')) {
            return [$code, '/' . ltrim(substr($uri, strlen($code) + 1), '/')];
        }
    }
    return [LANG_DEFAULT, $uri];
}

if (!defined('LANG')) {
    [$__lang, $__path] = lang_detect();
    define('LANG', $__lang);
    define('LANG_PATH', rtrim($__path, '/') === '' ? '/' : rtrim($__path, '/')); // dilsiz cari yol
    // Cari səhifənin marşrutu — dil dəyişdirici bunun əsasında qurulur
    $__r = route_match(LANG_PATH, $__lang);
    define('ROUTE_KEY',  $__r['key']);
    define('ROUTE_SLUG', $__r['slug'] ?? '');
}

/** Cari dilin prefiksi: '' | '/ru' | '/en' */
function lang_prefix(?string $code = null): string {
    $code = $code ?? LANG;
    return $code === LANG_DEFAULT ? '' : '/' . $code;
}

/** Eyni səhifənin başqa dildəki ünvanı (yol seqmentləri dilə görə fərqlidir) */
function lang_switch_url(string $code): string {
    $key  = defined('ROUTE_KEY') ? ROUTE_KEY : 'home';
    $slug = defined('ROUTE_SLUG') ? ROUTE_SLUG : '';
    $map  = ['about' => '/haqqimizda', 'services' => '/xidmetler', 'news' => '/xeberler',
             'contact' => '/elaqe', 'links' => '/linkler'];

    if (isset($map[$key])) return url($map[$key], $code);

    if ($key === 'service' || $key === 'newsitem') {
        $table = $key === 'service' ? 'services' : 'news';
        $other = function_exists('cms_translate_slug') ? cms_translate_slug($table, $slug, LANG, $code) : '';
        if ($other !== '') {
            return url(($key === 'service' ? '/xidmetler/' : '/xeberler/') . $other, $code);
        }
        // tərcümə yoxdursa bölmənin siyahısına aparırıq
        return url($key === 'service' ? '/xidmetler' : '/xeberler', $code);
    }
    return url('/', $code);
}

/** Tərcümə lüğəti */
function lang_dict(?string $code = null): array {
    static $cache = [];
    $code = $code ?? LANG;
    if (isset($cache[$code])) return $cache[$code];
    $file = __DIR__ . '/lang/' . $code . '.php';
    $dict = is_file($file) ? (require $file) : [];
    if ($code !== LANG_DEFAULT) {
        $base = require __DIR__ . '/lang/' . LANG_DEFAULT . '.php';
        $dict = $dict + $base;          // çatışmayan açarlar əsas dildən
    }
    return $cache[$code] = $dict;
}

/** İnterfeys sətri: t('btn.send') */
function t(string $key, string $fallback = ''): string {
    $d = lang_dict();
    return $d[$key] ?? ($fallback !== '' ? $fallback : $key);
}
