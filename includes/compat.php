<?php
/**
 * PHP 7.4 uyğunluğu.
 * Server hazırda PHP 7.4 işlədir; kod isə PHP 8-in sətir funksiyalarından
 * istifadə edir. Bu fayl həmin funksiyaları yalnız olmadıqda əlavə edir,
 * ona görə PHP 8-ə keçəndə heç nə dəyişmir.
 */

/* Saat qurşağı: serverin php.ini-si etibarsız dəyər saxlayır, ona görə açıq yazırıq. */
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Asia/Baku');
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        $len = strlen($needle);
        return $len <= strlen($haystack) && substr_compare($haystack, $needle, -$len) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
