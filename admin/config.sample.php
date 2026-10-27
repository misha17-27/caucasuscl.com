<?php
/*
 * Bu faylı  config.php  adı ilə kopyalayın və məlumatlarınızı yazın.
 * config.php git-ə düşmür — parollarınız repozitoriyada saxlanılmır.
 *
 * driver: 'sqlite'  — heç bir quraşdırma tələb etmir (lokal iş üçün ideal)
 *         'mysql'   — cPanel hostinqi üçün (MySQL Databases bölməsindən)
 */
return [
    'driver' => 'sqlite',

    // --- sqlite üçün ---
    'sqlite_path' => __DIR__ . '/../storage/admin.sqlite',

    // --- mysql üçün ---
    'db_host'    => 'localhost',
    'db_name'    => 'caucasus_admin',   // məs. cpaneluser_dbname
    'db_user'    => 'caucasus_admin',
    'db_pass'    => 'CHANGE_ME',
    'db_charset' => 'utf8mb4',

    // install.php ilk admin hesabını bu məlumatlarla yaradır:
    'install_admin_email' => 'admin@caucasuscl.com',
    'install_admin_pass'  => '',            // quraşdırmadan əvvəl doldurun

    // install.php / migrate.php-i brauzerdə işə salmaq üçün gizli açar
    'install_token' => '',          // təsadüfi uzun sətir yazın
];
