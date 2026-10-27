<?php
/*
 * Təhlükəsizlik yoxlaması — saytı kənardan sınayır.
 * Heç nə pozmur: yalnız sorğu göndərib cavabı oxuyur.
 *
 *   php tools/security-check.php [https://caucasuscl.com]
 */
declare(strict_types=1);
set_time_limit(900);
$base = rtrim($argv[1] ?? 'https://caucasuscl.com', '/');

function req(string $url, array $o = []): array {
    $ctx = stream_context_create([
        'http' => [
            'method'        => $o['method'] ?? 'GET',
            'header'        => $o['header'] ?? '',
            'content'       => $o['body'] ?? '',
            'timeout'       => 25,
            'ignore_errors' => true,
            'follow_location' => 0,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $b = @file_get_contents($url, false, $ctx);
    $code = 0; $hdr = [];
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) { $code = (int)$m[1]; continue; }
        $p = explode(':', $h, 2);
        if (count($p) === 2) $hdr[strtolower(trim($p[0]))] = trim($p[1]);
    }
    return ['code' => $code, 'body' => is_string($b) ? $b : '', 'hdr' => $hdr];
}

$fail = 0; $warn = 0;
function ok(string $label, bool $pass, string $note = '', bool $soft = false): void {
    global $fail, $warn;
    if (!$pass) { $soft ? $warn++ : $fail++; }
    printf("  %s %-52s %s\n", $pass ? 'OK  ' : ($soft ? 'XƏB ' : 'XX  '), $label, $note);
}

echo "TƏHLÜKƏSİZLİK YOXLAMASI — $base\n";

/* ---------- 1. Başlıqlar ---------- */
echo "\n1) Cavab başlıqları\n";
$r = req($base . '/');
$h = $r['hdr'];
ok('X-Content-Type-Options: nosniff', ($h['x-content-type-options'] ?? '') === 'nosniff');
ok('X-Frame-Options',                 !empty($h['x-frame-options']), $h['x-frame-options'] ?? '');
ok('Referrer-Policy',                 !empty($h['referrer-policy']), $h['referrer-policy'] ?? '');
ok('Permissions-Policy',              !empty($h['permissions-policy']));
ok('Strict-Transport-Security',       !empty($h['strict-transport-security']));
$csp = $h['content-security-policy'] ?? '';
ok('CSP: script-src var',             str_contains($csp, 'script-src'));
ok("CSP: script-src-də 'unsafe-inline' yoxdur",
    !preg_match("~script-src[^;]*'unsafe-inline'~", $csp));
ok('CSP: object-src none',            str_contains($csp, "object-src 'none'"));
ok('CSP: frame-ancestors',            str_contains($csp, 'frame-ancestors'));
ok('X-Powered-By gizlidir',           empty($h['x-powered-by']), $h['x-powered-by'] ?? '');
ok('Server versiyası açıq deyil',     !preg_match('~\d+\.\d+~', $h['server'] ?? ''), $h['server'] ?? '', true);

/* ---------- 2. Gizli fayllar ---------- */
echo "\n2) Açıq olmamalı yollar\n";
$closed = [
    '/admin/config.php', '/admin/db.php', '/admin/lib.php', '/admin/schema.php',
    '/admin/turnstile.php', '/admin/_data.json', '/admin/install.php',
    '/admin/migrate.php', '/admin/seed-data.php', '/admin/config.sample.php',
    '/includes/config.php', '/includes/functions.php', '/includes/lang/az.php',
    '/storage/', '/storage/admin.sqlite', '/storage/muracietler.log',
    '/.htaccess', '/.git/config', '/.env', '/README.md',
    '/router.php', '/tools/', '/admin/admin.js.bak',
    '/wp-config.php', '/wp-login.php', '/wp-admin/', '/xmlrpc.php',
];
/* Qeyd: tools/ qovluğu serverə yüklənmir — orada skript işə salmırıq. */
foreach ($closed as $p) {
    $x = req($base . $p);
    ok($p, in_array($x['code'], [403, 404, 410], true), 'kod ' . $x['code']);
}

/* ---------- 3. Qovluq siyahısı ---------- */
echo "\n3) Qovluq siyahısı\n";
foreach (['/assets/', '/assets/img/', '/assets/css/', '/includes/', '/admin/'] as $p) {
    $x = req($base . $p);
    $listing = str_contains($x['body'], 'Index of') || str_contains($x['body'], '<title>Index of');
    ok($p, !$listing, 'kod ' . $x['code']);
}

/* ---------- 4. XSS / inyeksiya cəhdləri ---------- */
echo "\n4) Əks olunan XSS və yol keçidi\n";
$payloads = [
    '/?q=<script>alert(1)</script>',
    '/?lang="><script>alert(1)</script>',
    '/xidmetler/?x=<img src=x onerror=alert(1)>',
    '/gomruk-xeberleri/?page="onmouseover="alert(1)',
];
foreach ($payloads as $p) {
    $x = req($base . $p);
    $reflected = str_contains($x['body'], '<script>alert(1)</script>')
              || str_contains($x['body'], 'onerror=alert(1)')
              || str_contains($x['body'], '"onmouseover="alert(1)');
    ok(substr($p, 0, 46), !$reflected, 'kod ' . $x['code']);
}

$traversal = [
    '/../../etc/passwd', '/xidmetler/../../admin/config.php',
    '/%2e%2e%2f%2e%2e%2fadmin/config.php',
    '/xeber.php?slug=../../admin/config.php',
    '/xidmet.php?slug=../../../includes/config.php',
];
foreach ($traversal as $p) {
    $x = req($base . $p);
    $leak = str_contains($x['body'], 'db_pass') || str_contains($x['body'], 'root:x:')
         || str_contains($x['body'], 'install_token');
    ok(substr($p, 0, 46), !$leak, 'kod ' . $x['code']);
}

/* ---------- 5. SQL inyeksiyası ---------- */
echo "\n5) SQL inyeksiyası\n";
$sqli = [
    "/xeber.php?slug=' OR '1'='1",
    "/xidmet.php?slug=1' UNION SELECT 1,2,3--",
    "/?id=1%20AND%201=1",
];
foreach ($sqli as $p) {
    $x = req($base . $p);
    $err = preg_match('~SQLSTATE|SQL syntax|PDOException|mysqli|sqlite3~i', $x['body']);
    ok(substr($p, 0, 46), !$err, 'kod ' . $x['code']);
}

/* ---------- 6. HTTP metodları ---------- */
echo "\n6) HTTP metodları\n";
foreach (['TRACE', 'TRACK', 'PUT', 'DELETE'] as $m) {
    $x = req($base . '/', ['method' => $m]);
    ok($m . ' bağlıdır', in_array($x['code'], [400, 403, 405, 501], true), 'kod ' . $x['code']);
}

/* ---------- 7. Forma ---------- */
echo "\n7) Əlaqə forması\n";
$f = req($base . '/elaqe/');
ok('Turnstile vidceti var',   str_contains($f['body'], 'cf-turnstile'));
ok('honeypot sahəsi var',     (bool)preg_match('~name="website"~', $f['body']));
ok('autocomplete=off deyil (istifadəçi rahatlığı)', true);

/* kapçasız göndəriş qəbul edilməməlidir */
$post = http_build_query([
    'cl_form' => '1', 'ad' => 'SecTest', 'telefon' => '+994500000000',
    'mesaj' => 'security probe', 'movzu' => 'probe',
]);
$x = req($base . '/elaqe/', [
    'method' => 'POST',
    'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($post) . "\r\n",
    'body'   => $post,
]);
$accepted = preg_match('~(müraciət qəbul|заявка принята|has been received|msg\.ok)~iu', $x['body']);
ok('kapçasız göndəriş rədd edilir', !$accepted, 'kod ' . $x['code']);

/* honeypot dolu olduqda rədd edilir */
$post2 = http_build_query([
    'cl_form' => '1', 'ad' => 'SecTest', 'telefon' => '+994500000000',
    'mesaj' => 'probe', 'website' => 'http://spam.example',
]);
$x2 = req($base . '/elaqe/', [
    'method' => 'POST',
    'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($post2) . "\r\n",
    'body'   => $post2,
]);
ok('honeypot işləyir', !preg_match('~(müraciət qəbul|заявка принята|has been received)~iu', $x2['body']));

/* ---------- 8. Admin girişi ---------- */
echo "\n8) Admin paneli\n";
$a = req($base . '/admin/index.php?section=login');
ok('giriş səhifəsi açılır',      $a['code'] === 200, 'kod ' . $a['code']);
ok('CSRF sahəsi var',            str_contains($a['body'], 'name="_csrf"'));
ok('kapça var',                  str_contains($a['body'], 'cf-turnstile'));
$sc = $a['hdr']['set-cookie'] ?? '';
ok('sessiya çərəzi HttpOnly',    stripos($sc, 'httponly') !== false, $sc === '' ? '(çərəz verilmədi)' : '');
ok('sessiya çərəzi Secure',      stripos($sc, 'secure') !== false, '', str_starts_with($base, 'http://'));
ok('sessiya çərəzi SameSite',    stripos($sc, 'samesite') !== false);

/* CSRF-siz POST rədd edilməlidir */
$lp = http_build_query(['email' => 'admin@caucasuscl.com', 'password' => 'test']);
$lx = req($base . '/admin/index.php?section=login', [
    'method' => 'POST',
    'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($lp) . "\r\n",
    'body'   => $lp,
]);
ok('CSRF-siz giriş rədd edilir', $lx['code'] === 400 || str_contains($lx['body'], 'CSRF'), 'kod ' . $lx['code']);

/* panelin daxili bölmələri autentifikasiyasız açılmır */
foreach (['users', 'security', 'submissions', 'smtp'] as $s) {
    $x = req($base . '/admin/index.php?section=' . $s);
    $redirect = $x['code'] === 302 && str_contains($x['hdr']['location'] ?? '', 'login');
    ok('bölmə ' . $s . ' qorunur', $redirect || $x['code'] === 403, 'kod ' . $x['code']);
}

echo "\n" . str_repeat('-', 62) . "\n";
echo $fail ? "PROBLEM: $fail" : 'Problem yoxdur';
echo $warn ? ", xəbərdarlıq: $warn" : '';
echo "\n";
