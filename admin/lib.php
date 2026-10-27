<?php
/* Admin panelinin ortaq köməkçiləri: PDO, auth, CSRF, layout. */
declare(strict_types=1);

require_once __DIR__ . '/schema.php';

function pdo(): PDO {
    static $p = null;
    if (!$p) { $p = require __DIR__ . '/db.php'; }
    return $p;
}
function cfg(string $k, $default = null) {
    static $c = null;
    if ($c === null) { $c = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : []; }
    return $c[$k] ?? $default;
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $to): void { header('Location: ' . $to); exit; }

/* ---------------- sessiya / auth ---------------- */
/** HTTPS altında işləyirikmi (Cloudflare arxasında da doğru işləyir) */
function is_https(): bool {
    $h = $_SERVER['HTTPS'] ?? '';
    return ($h !== '' && strtolower((string)$h) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** Sessiyanın maksimum boşdayanma müddəti (saniyə) */
const SESSION_IDLE_MAX = 7200;      // 2 saat
/** Sessiyanın mütləq ömrü */
const SESSION_ABS_MAX  = 43200;     // 12 saat

function boot_session(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    session_name('ccladm');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',     // panel xarici keçidlərdən açılmır
        'secure'   => is_https(),
    ]);
    ini_set('session.use_strict_mode', '1');   // serverin vermədiyi ID qəbul olunmur
    ini_set('session.use_only_cookies', '1');
    session_start();

    /* Vaxtı keçmiş sessiya bağlanır */
    $now = time();
    if (isset($_SESSION['admin'])) {
        $last  = (int)($_SESSION['seen']  ?? $now);
        $start = (int)($_SESSION['start'] ?? $now);
        if ($now - $last > SESSION_IDLE_MAX || $now - $start > SESSION_ABS_MAX) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
    }
    $_SESSION['seen'] = $now;
}

function current_admin(): ?array { boot_session(); return $_SESSION['admin'] ?? null; }
function require_login(): array {
    $a = current_admin();
    if (!$a) redirect('index.php?section=login');
    return $a;
}

/* ---------------- giriş cəhdlərinin məhdudlaşdırılması ---------------- */
const LOGIN_MAX_TRIES = 6;
const LOGIN_WINDOW    = 900;        // 15 dəqiqə

function login_blocked(): int {
    boot_session();
    $now  = time();
    $hits = array_values(array_filter((array)($_SESSION['login_fails'] ?? []),
        static fn($t) => is_int($t) && $t > $now - LOGIN_WINDOW));
    $_SESSION['login_fails'] = $hits;
    if (count($hits) < LOGIN_MAX_TRIES) return 0;
    return max(1, (int)(min($hits) + LOGIN_WINDOW - $now));   // qalan saniyə
}
function login_fail(): void {
    boot_session();
    $_SESSION['login_fails'][] = time();
}

function attempt_login(string $email, string $pass): bool {
    if (login_blocked() > 0) return false;

    $st = pdo()->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
    $st->execute([trim($email)]);
    $u = $st->fetch();

    /* Vaxt fərqinə görə mövcud/mövcud olmayan hesabı ayırd etmək olmasın */
    $hash = $u['pass_hash'] ?? '$2y$12$' . str_repeat('.', 53);
    $ok   = password_verify($pass, $hash);

    if ($u && $ok && (int)($u['active'] ?? 1) === 1) {
        boot_session();
        session_regenerate_id(true);
        unset($_SESSION['login_fails']);
        $_SESSION['start'] = time();
        $_SESSION['seen']  = time();
        $_SESSION['admin'] = [
            'id' => (int)$u['id'], 'email' => $u['email'],
            'name' => $u['name'] ?? '', 'role' => $u['role'] ?? 'editor',
        ];
        try { pdo()->prepare('UPDATE admins SET last_login=? WHERE id=?')->execute([date('Y-m-d H:i'), (int)$u['id']]); } catch (Throwable $e) {}
        return true;
    }
    login_fail();
    return false;
}

/** Yalnız daxil olmuş və rolu «admin» olan istifadəçi üçün true */
function is_admin(): bool {
    $a = current_admin();
    return is_array($a) && ($a['role'] ?? '') === 'admin';
}
function logout(): void {
    boot_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------------- CSRF ---------------- */
function csrf_token(): string {
    boot_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    boot_session();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;

    $have = (string)($_SESSION['csrf'] ?? '');
    $sent = (string)($_POST['_csrf'] ?? '');

    /* Diqqət: boş sətirlərdə hash_equals('','') true qaytarır — sessiyası
       olmayan sorğu yoxlamadan keçərdi. Ona görə hər ikisi dolu olmalıdır. */
    if ($have === '' || $sent === '' || !hash_equals($have, $sent)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        exit('CSRF token səhvdir. Səhifəni yeniləyin.');
    }
}

/* ---------------- flash ---------------- */
function flash(string $msg, string $type = 'ok'): void { boot_session(); $_SESSION['flash'][] = [$type, $msg]; }
function flash_render(): string {
    boot_session(); $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) {
        $bg = $t === 'err' ? '#b91c1c' : '#0f8a4f';
        $out .= '<div class="flash" style="background:' . $bg . '">' . e($m) . '</div>';
    }
    $_SESSION['flash'] = [];
    return $out;
}

/* ---------------- key-value ---------------- */
function kv_get(string $table, string $key, $default = ''): string {
    try {
        $st = pdo()->prepare("SELECT v FROM `$table` WHERE k = ? LIMIT 1");
        $st->execute([$key]);
        $r = $st->fetch();
        return $r ? (string)$r['v'] : (string)$default;
    } catch (Throwable $e) { return (string)$default; }
}
function kv_set(string $table, string $key, string $val): void {
    $db = pdo();
    $st = $db->prepare("SELECT 1 FROM `$table` WHERE k = ? LIMIT 1");
    $st->execute([$key]);
    if ($st->fetch()) $db->prepare("UPDATE `$table` SET v = ? WHERE k = ?")->execute([$val, $key]);
    else              $db->prepare("INSERT INTO `$table` (k, v) VALUES (?, ?)")->execute([$key, $val]);
}

/* ---------------- fayl yükləmə ---------------- */
/** İcazə verilən yüklənmə növləri: uzantı => gözlənilən MIME */
const UPLOAD_TYPES = [
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'webp' => 'image/webp', 'gif'  => 'image/gif',
    'mp4'  => 'video/mp4',  'webm' => 'video/webm', 'ogg' => 'video/ogg',
];
const UPLOAD_MAX_BYTES = 25 * 1024 * 1024;   // 25 MB
/* SVG qəsdən siyahıda yoxdur: içində <script> ola bilər və fayl birbaşa
   açıldıqda saytın mənşəyində icra olunur. */

function admin_move(string $tmp, string $name): ?string {
    if (!is_uploaded_file($tmp)) return null;
    if (filesize($tmp) > UPLOAD_MAX_BYTES) return null;

    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
    $name = preg_replace('/\.{2,}/', '.', (string)$name);          // ".." yolları
    $ext  = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
    if (!isset(UPLOAD_TYPES[$ext])) return null;

    /* Məzmun uzantıya uyğun olmalıdır — .jpg adlı PHP faylı keçməsin */
    $mime = '';
    if (class_exists('finfo')) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$fi->file($tmp);
    }
    $isImage = str_starts_with(UPLOAD_TYPES[$ext], 'image/');
    if ($isImage) {
        $info = @getimagesize($tmp);
        if ($info === false) return null;                          // həqiqi şəkil deyil
        if ($mime !== '' && !str_starts_with($mime, 'image/')) return null;
    } elseif ($mime !== '' && !str_starts_with($mime, 'video/') && $mime !== 'application/octet-stream') {
        return null;
    }

    $dir = dirname(__DIR__) . '/assets/img/admin';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    /* Ad təsadüfi olsun: mövcud faylın üstünə yazılmasın və ad təxmin edilməsin */
    $base = pathinfo((string)$name, PATHINFO_FILENAME);
    $base = mb_substr($base, 0, 60);
    $safe = date('Ymd') . '_' . bin2hex(random_bytes(4)) . '_' . $base . '.' . $ext;

    if (!move_uploaded_file($tmp, "$dir/$safe")) return null;
    @chmod("$dir/$safe", 0644);
    return 'admin/' . $safe;
}

function admin_upload(string $field): ?string {
    if (empty($_FILES[$field]['name'])) return null;
    if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    return admin_move($_FILES[$field]['tmp_name'], (string)$_FILES[$field]['name']);
}

/* ---------------- SMTP (sadə göndərici) ---------------- */
function smtp_send(string $to, string $subject, string $body, string &$err = ''): bool {
    $host = kv_get('settings', 'smtp_host');
    if ($host === '') { $err = 'SMTP-сервер не заполнен'; return false; }
    $port   = (int)(kv_get('settings', 'smtp_port') ?: 587);
    $user   = kv_get('settings', 'smtp_user');
    $pass   = kv_get('settings', 'smtp_pass');
    $secure = kv_get('settings', 'smtp_secure') ?: 'tls';
    $from   = kv_get('settings', 'smtp_from') ?: $user;
    $fname  = kv_get('settings', 'smtp_from_name') ?: 'Caucasus CL';
    $target = $secure === 'ssl' ? "ssl://$host" : $host;

    $fp = @fsockopen($target, $port, $eno, $estr, 15);
    if (!$fp) { $err = "Подключение не удалось: $estr ($eno)"; return false; }
    stream_set_timeout($fp, 15);
    $get = function () use ($fp) { $d = ''; while (($l = fgets($fp, 515)) !== false) { $d .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $d; };
    $cmd = function ($c) use ($fp, $get) { fwrite($fp, $c . "\r\n"); return $get(); };
    $ok  = fn($r, $code) => str_starts_with(trim($r), $code);

    $get();
    $cmd('EHLO caucasuscl.com');
    if ($secure === 'tls') {
        if (!$ok($cmd('STARTTLS'), '220') || !stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $err = 'STARTTLS не удалось'; fclose($fp); return false;
        }
        $cmd('EHLO caucasuscl.com');
    }
    if ($user !== '') {
        $cmd('AUTH LOGIN'); $cmd(base64_encode($user));
        if (!$ok($cmd(base64_encode($pass)), '235')) { $err = 'Авторизация SMTP не прошла'; fclose($fp); return false; }
    }
    $cmd("MAIL FROM:<$from>");
    $cmd("RCPT TO:<$to>");
    if (!$ok($cmd('DATA'), '354')) { $err = 'Сервер не принял DATA'; fclose($fp); return false; }
    $hdr = "From: =?UTF-8?B?" . base64_encode($fname) . "?= <$from>\r\nTo: <$to>\r\n"
         . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nMIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $r = $cmd($hdr . str_replace("\r\n.", "\r\n..", $body) . "\r\n.");
    $cmd('QUIT'); fclose($fp);
    if (!$ok($r, '250')) { $err = 'Письмо не отправлено: ' . trim($r); return false; }
    return true;
}

/* ---------------- ictimai ünvanlar ---------------- */
require_once __DIR__ . '/../includes/compat.php';
require_once __DIR__ . '/../includes/routes.php';

/**
 * Materialın saytdakı ünvanı — paneldən «saytda aç» keçidi üçün.
 * Marşrut qaydaları includes/routes.php-dən gəlir, ona görə ünvan sxemi
 * dəyişsə, keçid də özü düzəlir.
 */
function public_url(string $canonical, string $lang): string {
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host  = $_SERVER['HTTP_HOST'] ?? 'caucasuscl.com';
    $pre   = $lang === 'az' ? '' : '/' . $lang;
    $p     = route_path($canonical, $lang);
    $p     = $p === '/' ? '/' : rtrim($p, '/') . '/';
    return ($https ? 'https' : 'http') . '://' . $host . $pre . $p;
}

/**
 * Üz qabığı şəklinin yolu — yalnız assets/img daxilində şəkil faylı.
 * `../` keçidləri və skript uzantıları buraxılmır.
 */
function news_cover_path(string $p): string {
    $p = str_replace('\\', '/', trim($p));
    $p = preg_replace('~\.\.+/~', '', $p);
    $p = ltrim((string)$p, '/');
    if ($p === '') return '';
    if (!preg_match('~^[A-Za-z0-9._/-]{1,200}$~', $p)) return '';
    $ext = strtolower((string)pathinfo($p, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $p : '';
}

/* ---------------- dillər ---------------- */
const CMS_LANGS = ['az' => 'AZ', 'ru' => 'RU', 'en' => 'EN'];

/** Panelin hazırda redaktə etdiyi dil */
function cur_lang(): string {
    boot_session();
    if (isset($_GET['lang']) && isset(CMS_LANGS[$_GET['lang']])) {
        $_SESSION['cms_lang'] = $_GET['lang'];
    }
    return $_SESSION['cms_lang'] ?? 'az';
}

/** Dilə görə key-value oxumaq */
function kvl_get(string $table, string $key, string $lang, $default = ''): string {
    try {
        $st = pdo()->prepare("SELECT v FROM `$table` WHERE k = ? AND lang = ? LIMIT 1");
        $st->execute([$key, $lang]);
        $r = $st->fetch();
        return $r ? (string)$r['v'] : (string)$default;
    } catch (Throwable $e) { return (string)$default; }
}
function kvl_set(string $table, string $key, string $lang, string $val): void {
    $db = pdo();
    $st = $db->prepare("SELECT 1 FROM `$table` WHERE k = ? AND lang = ? LIMIT 1");
    $st->execute([$key, $lang]);
    if ($st->fetch()) $db->prepare("UPDATE `$table` SET v = ? WHERE k = ? AND lang = ?")->execute([$val, $key, $lang]);
    else              $db->prepare("INSERT INTO `$table` (lang, k, v) VALUES (?,?,?)")->execute([$lang, $key, $val]);
}

/* ---------------- naviqasiya ---------------- */
const SECTIONS = [
    'overview'    => ['Обзор', '▤'],
    'texts'       => ['Тексты сайта', '¶'],
    'pages'       => ['Страницы', '❐'],
    'services'    => ['Услуги', '✦'],
    'news'        => ['Новости', '✎'],
    'blocks'      => ['Цифры и блоки', '◧'],
    'clients'     => ['Клиенты', '⬡'],
    'links'       => ['Полезные ссылки', '⚯'],
    'images'      => ['Изображения', '❏'],
    'contacts'    => ['Контакты и соцсети', '☏'],
    'submissions' => ['Заявки с сайта', '✉'],
    'seo'         => ['SEO', '☌'],
    'smtp'        => ['Почта (SMTP)', '✉'],
    'security'    => ['Безопасность', '⚿'],
    'users'       => ['Пользователи', '☺'],
    'profile'     => ['Мой профиль', '☺'],
];
const GROUPS = [
    'ОСНОВНОЕ'  => ['overview'],
    'КОНТЕНТ'   => ['texts', 'pages', 'services', 'news', 'blocks', 'clients', 'links', 'images', 'contacts', 'submissions'],
    'НАСТРОЙКИ' => ['seo', 'smtp', 'security', 'users', 'profile'],
];
const ADMIN_ONLY = ['users', 'smtp', 'security'];
const LANG_SECTIONS = ['texts', 'pages', 'services', 'news', 'blocks', 'links', 'seo'];

function require_admin(): void {
    require_login();
    if (!is_admin()) { flash('Недостаточно прав (нужна роль Администратор).', 'err'); redirect('index.php?section=overview'); }
}

function unread_count(): int {
    try { return (int)pdo()->query('SELECT COUNT(*) c FROM submissions WHERE is_read=0')->fetch()['c']; }
    catch (Throwable $e) { return 0; }
}

function layout_top(string $active, string $title): void {
    $admin  = current_admin();
    $unread = unread_count();
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' — Caucasus CL admin</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<style>' . admin_css() . '</style></head><body>';
    echo '<input type="checkbox" id="navtoggle" hidden><div class="wrap">';

    echo '<aside class="side"><div class="brand"><img src="../assets/img/Caucasus-1.svg" alt="Caucasus"></div><nav>';
    foreach (GROUPS as $glabel => $keys) {
        echo '<div class="navgroup">' . e($glabel) . '</div>';
        foreach ($keys as $key) {
            if (in_array($key, ADMIN_ONLY, true) && !is_admin()) continue;
            [$label, $ic] = SECTIONS[$key];
            $cls = $key === $active ? ' class="on"' : '';
            $badge = ($key === 'submissions' && $unread) ? '<b class="badge">' . $unread . '</b>' : '';
            echo '<a' . $cls . ' href="index.php?section=' . $key . '"><i>' . $ic . '</i><span>' . e($label) . '</span>' . $badge . '</a>';
        }
    }
    echo '</nav><div class="who">Вы вошли как<br><b>' . e($admin['name'] ?: ($admin['email'] ?? '')) . '</b><br>'
       . '<span class="role">' . (($admin['role'] ?? '') === 'admin' ? 'Администратор' : 'Редактор') . '</span></div></aside>';

    echo '<main><header class="bar">';
    echo '<label for="navtoggle" class="burger" title="Меню"><span></span><span></span><span></span></label>';
    echo '<h1>' . e($title) . '</h1><div class="bar-r">';
    if (in_array($active, LANG_SECTIONS, true)) {
        $qs = $_GET; unset($qs['lang']);
        echo '<span class="langsw" title="Язык контента">';
        foreach (CMS_LANGS as $code => $label) {
            $qs['lang'] = $code;
            $cls = $code === cur_lang() ? ' class="on"' : '';
            echo '<a' . $cls . ' href="index.php?' . e(http_build_query($qs)) . '">' . $label . '</a>';
        }
        echo '</span> ';
    }
    echo '<a class="btn ghost" href="../" target="_blank">Открыть сайт</a> ';
    echo '<a class="btn" href="index.php?section=logout">Выйти</a></div></header><div class="body">';
    echo flash_render();
}
function layout_bottom(): void {
    echo '</div></main><label for="navtoggle" class="scrim"></label></div>';
    echo '<script src="admin.js?v=1"></script>';
    echo '<script src="editor.js?v=1"></script></body></html>';
}

function admin_css(): string {
    return <<<CSS
*{box-sizing:border-box}
body{margin:0;font-family:Montserrat,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f4f7f8;color:#0f1c26}
a{text-decoration:none}
.wrap{display:flex;min-height:100vh}

.side{width:256px;background:#0e2f47;color:#cfe0ea;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;flex:none;z-index:30}
.brand{padding:20px 18px;border-bottom:1px solid #17415f}
.brand img{max-height:40px;max-width:190px;filter:brightness(0) invert(1)}
.side nav{display:flex;flex-direction:column;padding:4px 0 14px;flex:1;overflow:auto}
.navgroup{color:#6f93ac;font-size:11px;letter-spacing:.09em;padding:15px 20px 5px;font-weight:700}
.side nav a{color:#b7cddc;padding:11px 20px;font-size:14px;display:flex;gap:12px;align-items:center;border-left:3px solid transparent}
.side nav a i{width:18px;font-style:normal;opacity:.85;text-align:center}
.side nav a:hover{background:#123a56;color:#fff}
.side nav a.on{background:#0f8a4f;color:#fff;font-weight:700;border-left-color:#f5c518}
.badge{margin-left:auto;background:#f5c518;color:#0e2f47;font-size:11px;font-weight:800;padding:1px 8px;border-radius:20px}
.who{padding:16px 20px;font-size:12px;color:#8fabc0;border-top:1px solid #17415f;line-height:1.5}
.who b{color:#fff}
.who .role{display:inline-block;margin-top:4px;background:#17415f;padding:2px 9px;border-radius:20px;font-size:11px}

main{flex:1;min-width:0}
.bar{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:16px 26px;background:#fff;border-bottom:1px solid #e2e9ec;position:sticky;top:0;z-index:20}
.bar h1{margin:0;font-size:21px;letter-spacing:-.02em;flex:1}
.bar-r{display:flex;gap:8px;flex:none}
.langsw{display:inline-flex;gap:2px;border:1px solid #cfd9df;border-radius:22px;padding:3px;margin-right:6px}
.langsw a{padding:6px 12px;border-radius:20px;font-size:13px;font-weight:700;color:#5b6f7d}
.langsw a.on{background:#0e2f47;color:#fff}
.burger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:6px}
.burger span{width:22px;height:2px;background:#0e2f47;border-radius:2px}
.scrim{display:none}
.body{padding:24px 26px 60px;max-width:1120px}

.flash{color:#fff;padding:12px 16px;border-radius:10px;margin-bottom:14px;font-weight:600;font-size:14px}
.btn{display:inline-block;background:#0f8a4f;color:#fff;border:0;padding:10px 18px;border-radius:24px;font-weight:700;cursor:pointer;font-size:14px;font-family:inherit;line-height:1.3}
.btn:hover{background:#0a6b3d}
.btn.ghost{background:transparent;color:#0e2f47;border:1.5px solid #cfd9df}
.btn.ghost:hover{background:#e8f5ee;border-color:#0f8a4f;color:#0a6b3d}
.btn.sm{padding:6px 12px;font-size:13px;border-radius:8px}
.btn.red{background:#b91c1c}.btn.red:hover{background:#991717}
.btn.navy{background:#0e2f47}.btn.navy:hover{background:#0a2334}

.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:22px}
.card{background:#fff;border:1px solid #e5ecef;border-radius:14px;padding:20px 22px}
.card .n{font-size:34px;font-weight:800;color:#0f8a4f;line-height:1}
.card .l{color:#5b6f7d;font-size:12.5px;text-transform:uppercase;letter-spacing:.04em;margin-top:6px}
.panel{background:#fff;border:1px solid #e5ecef;border-radius:14px;padding:22px;margin-bottom:20px}
.panel h3{margin:0 0 6px;font-size:18px}
.panel h4{margin:22px 0 6px;font-size:15px;color:#0e2f47}

table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:11px 12px;border-bottom:1px solid #eef2f4;font-size:14px;vertical-align:top}
th{color:#5b6f7d;font-size:11.5px;text-transform:uppercase;letter-spacing:.04em}
.tablewrap{overflow-x:auto}

label{display:block;font-weight:600;margin:14px 0 6px;font-size:13.5px}
input[type=text],input[type=email],input[type=password],input[type=number],input[type=date],textarea,select{
  width:100%;padding:11px 13px;border:1px solid #cfd9df;border-radius:9px;font-size:14px;font-family:inherit;background:#fbfcfd}
input:focus,textarea:focus,select:focus{outline:0;border-color:#0f8a4f;background:#fff;box-shadow:0 0 0 3px rgba(15,138,79,.12)}
textarea{min-height:90px;resize:vertical}
.row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
fieldset{border:1px solid #e5ecef;border-radius:10px;padding:12px 16px;margin-top:14px}
legend{color:#0e2f47;font-weight:700;font-size:13px;padding:0 6px}
.muted{color:#5b6f7d;font-size:13px}
.right{text-align:right}
.item{border:1px solid #eef2f4;border-radius:12px;padding:16px 18px;margin-bottom:14px;background:#fbfcfd}

/* --- vizual mətn redaktoru --- */
.rt-wrap{border:1px solid #d7e0e6;border-radius:10px;overflow:hidden;background:#fff}
.rt-bar{display:flex;flex-wrap:wrap;gap:4px;padding:7px 8px;background:#f4f7f8;border-bottom:1px solid #e3eaef}
.rt-btn{border:1px solid transparent;background:none;border-radius:7px;padding:5px 10px;cursor:pointer;
        font:600 13px/1.2 Montserrat,Arial,sans-serif;color:#0f1c26}
.rt-btn:hover{background:#fff;border-color:#d7e0e6}
.rt-btn b,.rt-btn i{font-size:14px}
.rt-area{min-height:320px;max-height:60vh;overflow:auto;padding:18px 20px;font-size:15px;line-height:1.65;outline:none}
.rt-area:focus{box-shadow:inset 0 0 0 2px #cfe3d7}
.rt-area h2{font-size:21px;margin:22px 0 10px;font-weight:800}
.rt-area h3{font-size:17px;margin:18px 0 8px;font-weight:700}
.rt-area h2:first-child,.rt-area h3:first-child,.rt-area p:first-child{margin-top:0}
.rt-area p{margin:0 0 12px}
.rt-area ul,.rt-area ol{margin:0 0 14px;padding-left:24px}
.rt-area li{margin:0 0 6px}
.rt-area li[data-card]{list-style:square;color:#0e6b40}
.rt-area a{color:#0f8a4f;text-decoration:underline}
.rt-toggle{margin-top:10px;border:1px solid #d7e0e6;background:#fff;border-radius:8px;padding:7px 14px;
           cursor:pointer;font:600 13px Montserrat,Arial,sans-serif;color:#51687a}
.rt-toggle:hover{border-color:#0f8a4f;color:#0f8a4f}
.item b.num{color:#0f8a4f}
.dot{display:inline-block;width:11px;height:11px;border-radius:50%}
.pill{display:inline-block;font-size:11px;padding:3px 10px;border-radius:20px;background:#e8f5ee;color:#0a6b3d;font-weight:700}
.grid-media{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px}

.login{min-height:100vh;display:grid;place-items:center;background:linear-gradient(150deg,#0e2f47,#092235 60%,#0f8a4f)}
.login .box{background:#fff;padding:36px 32px;border-radius:18px;width:380px;max-width:92vw;box-shadow:0 24px 70px rgba(0,0,0,.35)}
.login h2{margin:8px 0 2px;font-size:22px}
.login .sub{color:#5b6f7d;margin-bottom:16px;font-size:14px}
.login img{max-height:46px;max-width:220px}

@media(max-width:900px){
  .side{position:fixed;left:0;top:0;transform:translateX(-100%);transition:transform .25s;box-shadow:0 0 40px rgba(0,0,0,.3)}
  #navtoggle:checked ~ .wrap .side{transform:none}
  #navtoggle:checked ~ .wrap .scrim{display:block;position:fixed;inset:0;background:rgba(9,34,53,.45);z-index:25}
  .burger{display:flex}
  .body{padding:18px 16px 50px}
  .bar{padding:12px 16px}
  .bar h1{font-size:17px}
  .bar-r .btn.ghost{display:none}
  .row,.row3{grid-template-columns:1fr}
}
CSS;
}
