<?php
/**
 * Ünvan sxemi — orijinal caucasuscl.com ilə eyni.
 *
 *   AZ   /  /haqqimizda/  /xidmetler/  /xidmetler/<slug>/  /gomruk-xeberleri/  /<xeber-slug>/  /elaqe/
 *   RU   /ru/  /ru/o-nas/  /ru/uslugi/  /ru/xidmetler/<slug>/  /ru/novosti/  /ru/novosti/<slug>/  /ru/kontakty/
 *   EN   /en/ ... (orijinalda yox idi, ona görə ingilis seqmentləri)
 *
 * Diqqət: AZ-də xəbərlər KÖKDƏ yerləşir — WordPress-dəki kimi.
 * Şablonlar həmişə "kanonik" yolu yazır (url('/haqqimizda')), bu fayl onu
 * cari dilin real ünvanına çevirir. Beləliklə şablonlara toxunmaq lazım gəlmir.
 */

const ROUTE_SEGMENTS = [
    'az' => [
        'about'    => 'haqqimizda',
        'services' => 'xidmetler',
        'svc_base' => 'xidmetler',      // xidmət detalının prefiksi
        'news'     => 'gomruk-xeberleri',
        'news_base' => '',              // boş = xəbər detalı kökdə
        'contact'  => 'elaqe',
        'links'    => 'faydali-linkler',
    ],
    'ru' => [
        'about'    => 'o-nas',
        'services' => 'uslugi',
        'svc_base' => 'xidmetler',      // orijinalda RU xidmətlər /ru/xidmetler/... altındadır
        'news'     => 'novosti',
        'news_base' => 'novosti',
        'contact'  => 'kontakty',
        'links'    => 'poleznye-ssylki',
    ],
    'en' => [
        'about'    => 'about-us',
        'services' => 'services',
        'svc_base' => 'services',
        'news'     => 'news',
        'news_base' => 'news',
        'contact'  => 'contact',
        'links'    => 'useful-links',
    ],
];

function seg(string $key, ?string $lang = null): string {
    $lang = $lang ?? (defined('LANG') ? LANG : 'az');
    return ROUTE_SEGMENTS[$lang][$key] ?? ROUTE_SEGMENTS['az'][$key] ?? '';
}

/**
 * Kanonik yolu cari dilin real yoluna çevirir.
 *   '/haqqimizda'            -> '/o-nas'          (ru)
 *   '/xidmetler/logistika-x' -> '/xidmetler/logistika-x'
 *   '/xeberler/<slug>'       -> '/<slug>'          (az, kökdə)
 */
function route_path(string $canonical, ?string $lang = null): string {
    $lang = $lang ?? (defined('LANG') ? LANG : 'az');
    $p = '/' . trim($canonical, '/');
    if ($p === '/') return '/';

    if ($p === '/haqqimizda') return '/' . seg('about', $lang);
    if ($p === '/xidmetler')  return '/' . seg('services', $lang);
    if ($p === '/xeberler')   return '/' . seg('news', $lang);
    if ($p === '/elaqe')      return '/' . seg('contact', $lang);
    if ($p === '/linkler')    return '/' . seg('links', $lang);

    if (preg_match('#^/xidmetler/(.+)$#', $p, $m)) {
        return '/' . seg('svc_base', $lang) . '/' . $m[1];
    }
    if (preg_match('#^/xeberler/(.+)$#', $p, $m)) {
        $base = seg('news_base', $lang);
        return $base === '' ? '/' . $m[1] : '/' . $base . '/' . $m[1];
    }
    return $p;
}

/**
 * Real yolu kanonik yola qaytarır (marşrutlayıcı üçün).
 * ['key' => 'home|about|services|service|news|newsitem|contact|links|404', 'slug' => ...]
 */
function route_match(string $path, string $lang): array {
    $p = '/' . trim($path, '/');
    if ($p === '/') return ['key' => 'home'];

    $S = ROUTE_SEGMENTS[$lang] ?? ROUTE_SEGMENTS['az'];

    if ($p === '/' . $S['about'])    return ['key' => 'about'];
    if ($p === '/' . $S['services']) return ['key' => 'services'];
    if ($p === '/' . $S['news'])     return ['key' => 'news'];
    if ($p === '/' . $S['contact'])  return ['key' => 'contact'];
    if ($p === '/' . $S['links'])    return ['key' => 'links'];

    if (preg_match('#^/' . preg_quote($S['svc_base'], '#') . '/([a-z0-9\-]+)$#', $p, $m)) {
        return ['key' => 'service', 'slug' => $m[1]];
    }
    if ($S['news_base'] !== '' && preg_match('#^/' . preg_quote($S['news_base'], '#') . '/([a-z0-9\-]+)$#', $p, $m)) {
        return ['key' => 'newsitem', 'slug' => $m[1]];
    }
    if ($S['news_base'] === '' && preg_match('#^/([a-z0-9\-]+)$#', $p, $m)) {
        return ['key' => 'newsitem', 'slug' => $m[1]];   // AZ: xəbər kökdə
    }
    return ['key' => '404'];
}
