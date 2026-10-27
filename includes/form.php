<?php
/**
 * Əlaqə formalarının emalı.
 * Səhifənin ən başında (header-dən əvvəl) daxil edilir.
 *
 * Nəticə: $form = ['status' => 'ok|err|', 'msg' => '...', 'old' => [...]]
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$form = ['status' => '', 'msg' => '', 'old' => []];

/** Sahələrin maksimum uzunluğu — bazadakı sütun ölçüləri ilə uzlaşır */
const FORM_LIMITS = [
    'ad' => 120, 'soyad' => 120, 'telefon' => 60,
    'email' => 190, 'movzu' => 190, 'mesaj' => 5000,
];

/**
 * Eyni ziyarətçidən qısa müddətdə çox göndəriş olmasın.
 * Turnstile əsas müdafiədir; bu, əlavə sadə həddir.
 */
function form_rate_ok(int $maxPerHour = 5): bool {
    if (session_status() === PHP_SESSION_NONE) {
        @session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
                          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        ]);
        @session_start();
    }
    $now  = time();
    $list = array_values(array_filter((array)($_SESSION['form_hits'] ?? []),
        static fn($t) => is_int($t) && $t > $now - 3600));
    if (count($list) >= $maxPerHour) { $_SESSION['form_hits'] = $list; return false; }
    $list[] = $now;
    $_SESSION['form_hits'] = $list;
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cl_form'])) {

    /* Sətri təmizləyir: idarəedici simvollar (CR/LF daxil) silinir, uzunluq kəsilir */
    $get = static function (string $k): string {
        $v = (string)($_POST[$k] ?? '');
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);   // \t, \n təbii mətndə qalır
        $v = trim((string)$v);
        $max = FORM_LIMITS[$k] ?? 200;
        return mb_substr($v, 0, $max);
    };

    /* Bir sətirlik sahələrdə sətir keçidi olmamalıdır (məktub başlığına inyeksiya) */
    $line = static function (string $v): string {
        return trim(str_replace(["\r", "\n", "\t"], ' ', $v));
    };

    $data = [
        'ad'      => $line($get('ad')),
        'soyad'   => $line($get('soyad')),
        'telefon' => $line($get('telefon')),
        'email'   => $line($get('email')),
        'movzu'   => $line($get('movzu')),
        'mesaj'   => $get('mesaj'),
    ];
    $form['old'] = $data;

    $errors = [];

    // Bot tələsi
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        $errors[] = t('msg.rejected');
    }
    if ($data['ad'] === '') {
        $errors[] = t('msg.needname');
    }
    if ($data['telefon'] === '' && $data['email'] === '') {
        $errors[] = t('msg.needcontact');
    }
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('msg.bademail');
    }
    if ($data['telefon'] !== '' && !preg_match('/^[0-9+()\s.\-]{5,60}$/u', $data['telefon'])) {
        $errors[] = t('msg.badphone');
    }
    if (!$errors && !form_rate_ok()) {
        $errors[] = t('msg.toomany');
    }

    // Cloudflare Turnstile yoxlaması
    if (!$errors && !turnstile_verify($get('cf-turnstile-response'), $_SERVER['REMOTE_ADDR'] ?? '')) {
        $errors[] = t('msg.captcha');
    }

    if ($errors) {
        $form['status'] = 'err';
        $form['msg'] = implode(' ', $errors);
    } else {
        $subject = 'Saytdan müraciət: ' . ($data['movzu'] !== '' ? $data['movzu'] : 'Ümumi sorğu');

        $body = "Yeni müraciət — " . SITE_NAME . "\n"
              . str_repeat('-', 40) . "\n"
              . "Ad:      {$data['ad']} {$data['soyad']}\n"
              . "Telefon: {$data['telefon']}\n"
              . "Email:   {$data['email']}\n"
              . "Mövzu:   {$data['movzu']}\n"
              . str_repeat('-', 40) . "\n"
              . $data['mesaj'] . "\n\n"
              . 'Tarix: ' . date('d.m.Y H:i') . "\n"
              . 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-') . "\n";

        /* Göndərən domen sabitdir: HTTP_HOST ziyarətçi tərəfindən idarə oluna bilər
           və məktub başlığına düşməməlidir. */
        $fromDomain = defined('MAIL_DOMAIN') && MAIL_DOMAIN !== '' ? MAIL_DOMAIN : 'caucasuscl.com';
        $headers = 'From: =?UTF-8?B?' . base64_encode(SITE_SHORT) . "?= <no-reply@{$fromDomain}>\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";
        /* email FILTER_VALIDATE_EMAIL-dən keçib — içində CR/LF ola bilməz */
        if ($data['email'] !== '' && !preg_match('/[\r\n]/', $data['email'])) {
            $headers .= "Reply-To: {$data['email']}\r\n";
        }

        // 1) Bazaya yazırıq — admin panelinin "Заявки" bölməsində görünsün
        $stored = function_exists('cms_save_submission') ? cms_save_submission($data) : false;

        // 2) Poçt ünvanı: admin panelindən, yoxdursa config.php-dən
        $mailTo = function_exists('cms_text') ? cms_text('mail_to', MAIL_TO) : MAIL_TO;
        if ($mailTo === '') $mailTo = MAIL_TO;

        $sent = @mail($mailTo, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);

        // Lokal serverdə mail() işləmir — bazaya da düşməyibsə, fayla yazırıq ki, itməsin.
        if (!$sent && !$stored) {
            $dir = __DIR__ . '/../storage';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            @file_put_contents($dir . '/muracietler.log', $body . "\n" . str_repeat('=', 60) . "\n", FILE_APPEND);
        }

        $form['status'] = 'ok';
        $form['msg'] = t('msg.ok');
        $form['old'] = [];
    }
}

/** Formada köhnə dəyəri geri qaytarır */
function old(string $key): string {
    global $form;
    return htmlspecialchars((string)($form['old'][$key] ?? ''), ENT_QUOTES, 'UTF-8');
}
