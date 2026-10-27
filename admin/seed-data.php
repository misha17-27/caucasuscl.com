<?php
/*
 * BİRDƏFƏLİK SKRIPT — _data.json faylındakı məzmunu bazaya yükləyir.
 * İşə salma:  /admin/seed-data.php?token=<install_token>
 * İşi bitəndən sonra bu fayl və _data.json SİLİNMƏLİDİR.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

$cfg = require __DIR__ . '/config.php';
$__tok = (string)($cfg['install_token'] ?? '');
if ($__tok === '' || !hash_equals($__tok, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit("Token yanlışdır.\n");
}

set_error_handler(static function ($no, $str, $file, $line) {
    throw new ErrorException($str, 0, $no, $file, $line);
});
set_exception_handler(static function (Throwable $e) {
    echo "\n=== XƏTA ===\n" . get_class($e) . ': ' . $e->getMessage()
       . "\n" . basename($e->getFile()) . ':' . $e->getLine() . "\n";
    exit(1);
});

$db = require __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';

$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Driver: $driver\n";

$file = __DIR__ . '/_data.json';
if (!is_file($file)) exit("_data.json tapılmadı.\n");
$dump = json_decode((string)file_get_contents($file), true);
if (!is_array($dump) || empty($dump['tables'])) exit("_data.json oxunmadı.\n");
echo "Fayl tarixi: " . ($dump['generated'] ?? '?') . "\n\n";

/* 1. Cədvəllər */
$made = cms_create_tables($db);
echo "Cədvəllər hazırdır: " . count($made) . "\n";

/* 2. Çatışmayan sütunlar (köhnə bazanın üstünə yüklənərsə) */
function seed_columns(PDO $db, string $t): array {
    $cols = [];
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($db->query("PRAGMA table_info(`$t`)") as $r) $cols[] = $r['name'];
    } else {
        foreach ($db->query("SHOW COLUMNS FROM `$t`") as $r) $cols[] = $r['Field'];
    }
    return $cols;
}
$extra = [
    'news'      => ['body' => 'TEXT', 'tkey' => "VARCHAR(160) DEFAULT ''",
                    'meta_title' => "VARCHAR(255) DEFAULT ''", 'meta_desc' => 'TEXT'],
    'services'  => ['tkey' => "VARCHAR(160) DEFAULT ''"],
    'seo_pages' => ['hero_title' => "VARCHAR(255) DEFAULT ''", 'hero_text' => 'TEXT', 'body' => 'TEXT'],
];
foreach ($extra as $t => $defs) {
    $have = seed_columns($db, $t);
    foreach ($defs as $c => $type) {
        if (in_array($c, $have, true)) continue;
        $db->exec("ALTER TABLE `$t` ADD COLUMN `$c` $type");
        echo "   + $t.$c əlavə olundu\n";
    }
}

/* 3. Məlumatların yüklənməsi */
echo "\nMəzmun yüklənir:\n";
if ($driver === 'mysql') $db->exec('SET FOREIGN_KEY_CHECKS=0');

$total = 0;
foreach ($dump['tables'] as $table => $rows) {
    $have = seed_columns($db, $table);
    if (!$have) { echo "   ! $table cədvəli yoxdur — ötürüldü\n"; continue; }

    $db->exec("DELETE FROM `$table`");
    if ($driver === 'sqlite') $db->exec("DELETE FROM sqlite_sequence WHERE name='$table'");
    else                      $db->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");

    $n = 0;
    if ($rows) {
        /* yalnız real mövcud sütunlar */
        $cols = array_values(array_intersect(array_keys($rows[0]), $have));
        $sql  = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES ('
              . implode(',', array_fill(0, count($cols), '?')) . ')';
        $st = $db->prepare($sql);
        $db->beginTransaction();
        foreach ($rows as $r) {
            $vals = [];
            foreach ($cols as $c) $vals[] = $r[$c];
            $st->execute($vals);
            $n++;
        }
        $db->commit();
    }
    $total += $n;
    printf("   %-12s %4d sətir\n", $table, $n);
}
if ($driver === 'mysql') $db->exec('SET FOREIGN_KEY_CHECKS=1');

echo "\nCəmi: $total sətir.\n";

/* 4. Qısa yoxlama */
echo "\nYoxlama:\n";
foreach (['az', 'ru', 'en'] as $l) {
    $q = $db->prepare('SELECT COUNT(*) FROM news WHERE lang=?');       $q->execute([$l]); $nn = $q->fetchColumn();
    $q = $db->prepare('SELECT COUNT(*) FROM services WHERE lang=?');   $q->execute([$l]); $ns = $q->fetchColumn();
    $q = $db->prepare('SELECT COUNT(*) FROM seo_pages WHERE lang=?');  $q->execute([$l]); $np = $q->fetchColumn();
    echo "   $l: xəbər $nn, xidmət $ns, səhifə $np\n";
}
$adm = $db->query('SELECT email, role FROM admins')->fetchAll(PDO::FETCH_ASSOC);
foreach ($adm as $a) echo "   admin: {$a['email']} ({$a['role']})\n";

echo "\nBitdi. İNDİ BU FAYLI VƏ _data.json FAYLINI SİLİN.\n";
