<?php
/*
 * Bir dəfəlik quraşdırma: cədvəlləri yaradır, saytın məzmununu bazaya köçürür
 * və ilk admin hesabını açır.
 *
 *   /admin/install.php?token=<install_token>
 *
 * Token admin/config.php faylındakı 'install_token' ilə eyni olmalıdır.
 * Quraşdırma bitəndən sonra bu faylı serverdən silmək tövsiyə olunur.
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('Content-Type: text/plain; charset=utf-8');

/* Diqqət: boş açar hamıya qapı açır — ona görə əvvəlcə doluluğu yoxlanılır. */
$__tok = (string)cfg('install_token');
if ($__tok === '' || !hash_equals($__tok, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit("Forbidden.\nadmin/config.php faylında 'install_token' doldurulmalı, sonra ?token=... əlavə edilməlidir.\n");
}

$db = pdo();
echo "Driver: " . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n\n";

echo "1) Cədvəllər yaradılır...\n";
foreach (cms_create_tables($db) as $t) echo "   + $t\n";

echo "\n2) Saytın məzmunu köçürülür...\n";
foreach (cms_seed($db) as $line) echo "   + $line\n";

echo "\n3) İlk admin hesabı...\n";
$email = (string)cfg('install_admin_email', 'admin@caucasuscl.com');
$pass  = (string)cfg('install_admin_pass', '');
if ($pass === '') {
    exit("admin/config.php faylında install_admin_pass boşdur.\n"
       . "Quraşdırmadan əvvəl ora güclü parol yazın — defolt parol qoyulmur.\n");
}
$st = $db->prepare('SELECT id FROM admins WHERE email = ?');
$st->execute([$email]);
if ($st->fetch()) {
    echo "   · $email artıq mövcuddur — toxunulmadı\n";
} else {
    $db->prepare('INSERT INTO admins (name,email,pass_hash,role,active) VALUES (?,?,?,?,1)')
       ->execute(['Administrator', $email, password_hash($pass, PASSWORD_DEFAULT), 'admin']);
    echo "   + $email yaradıldı\n";
    echo "     parol: $pass\n";
}

echo "\nHAZIRDIR.\n";
echo "Panelə giriş:  /admin/\n";
echo "Təhlükəsizlik üçün bu faylı (install.php) serverdən silin.\n";
