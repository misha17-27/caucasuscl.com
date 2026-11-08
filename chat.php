<?php
/**
 * Onlayn söhbətdən gələn müraciəti bazaya yazır.
 *
 * Vidcetin əsas kanalı WhatsApp-dır (bax `includes/chat.php`). Bu ünvan yalnız
 * ehtiyat nüsxədir: ziyarətçi WhatsApp-da «göndər» düyməsini basmasa, müraciət
 * tamamilə itərdi. Ona görə ad və nömrə burada da saxlanılır, admin panelinin
 * «Заявки» bölməsində görünür.
 *
 * MƏKTUB GÖNDƏRİLMİR. Əlaqə formasından fərqli olaraq burada Turnstile yoxdur
 * (kapçanı söhbətin ortasına qoymaq gedişatı pozardı), ona görə bu ünvanı
 * poçt göndərməyə bağlamaq spam selinə yol açardı. Baza sətri ucuzdur və
 * paneldən təmizlənə bilər, poçt qutusu yox.
 *
 * Müdafiə qatları:
 *   · yalnız POST
 *   · eyni mənşə yoxlaması (Origin/Referer) + `X-Requested-With` başlığı
 *     — ikisi birlikdə sadə saytlararası göndərişi kəsir
 *   · bot tələsi (`website` sahəsi)
 *   · sessiya üzrə hədd (saatda 5) və IP üzrə hədd (saatda 10)
 *
 * Cavab həmişə JSON-dur; vidcet cavabı gözləmir, ona görə xəta da söhbəti
 * dayandırmır.
 */
declare(strict_types=1);

/* Dil sorğu ünvanından oxunur, bu ünvanda isə prefiks yoxdur (/chat.php).
   Prefiksi süni şəkildə qoyuruq ki, `lang_detect()` adi qaydada işləsin və
   LANG, LANG_PATH, ROUTE_KEY — hamısı uyğun təyin olunsun. Əks halda rus
   ziyarətçinin müraciəti bazaya `az` kimi düşərdi.
   config.php lang.php-ni özü yükləyir, ona görə bu sətir ondan ƏVVƏL olmalıdır. */
$__lang = (string)($_POST['lang'] ?? '');
if (in_array($__lang, ['ru', 'en'], true)) $_SERVER['REQUEST_URI'] = '/' . $__lang . '/';
else                                       $_SERVER['REQUEST_URI'] = '/';

require_once __DIR__ . '/includes/config.php';        // lang.php + content.php-i özü yükləyir
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/form.php';          // form_rate_ok(), FORM_LIMITS

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

/** Qısa cavab və çıxış. */
function chat_out(bool $ok, string $why = '', int $code = 0): void {
    http_response_code($code ?: ($ok ? 200 : 400));
    echo json_encode(['ok' => $ok] + ($why !== '' ? ['why' => $why] : []), JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    chat_out(false, 'method', 405);
}

/* ---- eyni mənşə ----
   Brauzer `X-Requested-With`-i saytlararası sorğuda preflight olmadan
   göndərə bilmir; Origin isə POST-da həmişə qoyulur. */
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === '') chat_out(false, 'xhr');

$host   = (string)parse_url(site_origin(), PHP_URL_HOST);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$ref    = (string)($_SERVER['HTTP_REFERER'] ?? '');
$from   = $origin !== '' ? $origin : $ref;
if ($host === '' || $from === '' || strcasecmp((string)parse_url($from, PHP_URL_HOST), $host) !== 0) {
    chat_out(false, 'origin');
}

/* ---- sahələr ---- */
$clean = static function (string $k, int $max): string {
    $v = (string)($_POST[$k] ?? '');
    $v = (string)preg_replace('/[\x00-\x1F\x7F]/u', ' ', $v);     // sətir keçidi də gedir
    return mb_substr(trim((string)preg_replace('/\s+/u', ' ', $v)), 0, $max);
};

if (trim((string)($_POST['website'] ?? '')) !== '') chat_out(false, 'bot');

$name    = $clean('ad',      FORM_LIMITS['ad']);
$phone   = $clean('telefon', FORM_LIMITS['telefon']);
$service = $clean('movzu',   FORM_LIMITS['movzu']);

if ($name === '' || mb_strlen($name) < 2) chat_out(false, 'name');
if (!preg_match('/^[0-9+()\s.\-]{5,60}$/u', $phone)) chat_out(false, 'phone');
if (strlen((string)preg_replace('/\D+/', '', $phone)) < 7) chat_out(false, 'phone');

/* ---- həddlər ---- */
if (!form_rate_ok()) chat_out(false, 'rate');

/**
 * IP üzrə saatlıq hədd. Sessiya həddi çərəzi atan botu saxlamır, bu isə
 * saxlayır. Sadə JSON fayl kifayətdir — gündə onlarla sətirdən söhbət gedir.
 */
function chat_ip_ok(int $maxPerHour = 10): bool {
    $ip = client_ip();
    if ($ip === '') return true;                        // ünvan yoxdursa hədd qoymuruq
    $file = __DIR__ . '/storage/chat-rate.json';
    $dir  = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $fh = @fopen($file, 'c+');
    if (!$fh) return true;                              // yazıla bilmirsə müraciəti itirmirik
    $ok = true;
    if (flock($fh, LOCK_EX)) {
        $raw  = (string)stream_get_contents($fh);
        $data = json_decode($raw, true);
        if (!is_array($data)) $data = [];
        $now  = time();
        /* köhnə yazılar atılır — fayl böyüməsin */
        foreach ($data as $k => $list) {
            $data[$k] = array_values(array_filter((array)$list,
                static fn($t) => is_int($t) && $t > $now - 3600));
            if (!$data[$k]) unset($data[$k]);
        }
        $key = hash('sha256', $ip);                     // faylda açıq IP saxlanmır
        $mine = (array)($data[$key] ?? []);
        if (count($mine) >= $maxPerHour) {
            $ok = false;
        } else {
            $mine[] = $now;
            $data[$key] = $mine;
        }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string)json_encode($data));
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    return $ok;
}

if (!chat_ip_ok()) chat_out(false, 'rate');

/* ---- yazılır ---- */
$note = t('chat.source');
$saved = function_exists('cms_save_submission') ? cms_save_submission([
    'ad'      => $name,
    'soyad'   => '',
    'telefon' => $phone,
    'email'   => '',
    'movzu'   => $service,
    'mesaj'   => $note,
]) : false;

chat_out($saved, $saved ? '' : 'store');
