<?php
/**
 * Saytda istifadə olunan bütün şəkilləri caucasuscl.com-dan
 * lokal `assets/img/` qovluğuna endirir.
 *
 * İstifadə:  php tools/download-images.php
 * Sonra config.php-də:  define('IMG_BASE', BASE_URL.'/assets/img');
 *
 * Qeyd: fayllar qovluq strukturu olmadan, yalnız fayl adı ilə saxlanılır,
 * ona görə config.php-dəki yollar da sadələşdirilməlidir (aşağıdakı cədvələ bax).
 */

require __DIR__ . '/../includes/config.php';

$files = [
    'Caucasus-1.svg',
    '2022/08/slider-1.jpg',
    '2022/07/slide3-1.jpg',
    '2022/07/slide3-2.jpg',
    '2022/07/slide3-3.jpg',
    '2022/07/about2.jpg',
    $NEWS_IMG,
];
foreach ($SERVICES as $s) { $files[] = $s['img']; }
foreach ($CLIENTS as $c)  { $files[] = $c; }

$dest = __DIR__ . '/../assets/img';
$ok = $fail = 0;

foreach (array_unique($files) as $f) {
    $url  = 'https://caucasuscl.com/wp-content/uploads/' . ltrim($f, '/');
    $path = $dest . '/' . $f;
    $dir  = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    if (is_file($path)) { echo "· mövcuddur: $f\n"; $ok++; continue; }

    $data = @file_get_contents($url);
    if ($data === false) { echo "✗ alınmadı:  $f\n"; $fail++; continue; }

    file_put_contents($path, $data);
    echo "✓ endirildi: $f (" . round(strlen($data) / 1024) . " KB)\n";
    $ok++;
}

echo "\nHazırdır. Uğurlu: $ok, xəta: $fail\n";
echo "İndi includes/config.php faylında IMG_BASE dəyişin:\n";
echo "  define('IMG_BASE', BASE_URL.'/assets/img');\n";
