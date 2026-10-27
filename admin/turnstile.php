<?php
/*
 * Cloudflare Turnstile — admin panelinin girişi üçün.
 * Açarlar `settings` cədvəlində saxlanılır (Безопасность bölməsi).
 * Açar yoxdursa kapça sadəcə söndürülür.
 */

function _ts_pdo(): ?PDO {
    static $p = null;
    if ($p !== null) return $p ?: null;
    try { $p = require __DIR__ . '/db.php'; } catch (Throwable $e) { $p = false; }
    return $p ?: null;
}
function ts_setting(string $k): string {
    $p = _ts_pdo();
    if (!$p) return '';
    try {
        $s = $p->prepare('SELECT v FROM settings WHERE k=? LIMIT 1');
        $s->execute([$k]);
        $r = $s->fetch();
        return $r ? (string)$r['v'] : '';
    } catch (Throwable $e) { return ''; }
}
function turnstile_site(): string   { return ts_setting('turnstile_site'); }
function turnstile_secret(): string { return ts_setting('turnstile_secret'); }

/** Vidceti çıxarır. Site key yoxdursa heç nə etmir. */
function turnstile_widget(): void {
    $site = turnstile_site();
    if ($site === '') return;
    echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    echo '<div class="cf-turnstile" data-sitekey="' . htmlspecialchars($site, ENT_QUOTES) . '" data-theme="light"></div>';
}

/** Tokeni yoxlayır. Açar qurulmayıbsa true qaytarır (giriş bloklanmasın). */
function turnstile_verify(string $token): bool {
    $secret = turnstile_secret();
    if ($secret === '') return true;
    if ($token === '')  return false;

    $post = ['secret' => $secret, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''];
    $url  = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $raw  = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    }
    if ($raw === false) {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'timeout' => 10,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($post), 'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
    }
    if (!is_string($raw) || $raw === '') return false;
    $d = json_decode($raw, true);
    return !empty($d['success']);
}
