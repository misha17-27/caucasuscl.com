<?php
/*
 * Miqrasiya 3 — orijinal saytın ünvan sxeminə keçid:
 *   1) services / news cədvəllərinə `tkey` (tərcümə açarı = AZ slug)
 *   2) RU xidmətlərin slug-ları orijinal saytdakı kimi dəyişdirilir
 *
 *   /admin/migrate3.php?token=<install_token>
 *
 * Təkrar işə salmaq təhlükəsizdir.
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('Content-Type: text/plain; charset=utf-8');

$__tok = (string)cfg('install_token');
if ($__tok === '' || !hash_equals($__tok, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden. ?token=... əlavə edin');
}
$db = pdo();

function has_column(PDO $db, string $t, string $c): bool {
    try {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($db->query("PRAGMA table_info(`$t`)") as $r) if (strcasecmp($r['name'], $c) === 0) return true;
            return false;
        }
        $s = $db->prepare('SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $s->execute([$t, $c]);
        return (int)$s->fetch()['c'] > 0;
    } catch (Throwable $e) { return false; }
}

echo "1) tkey sütunu\n";
foreach (['services', 'news'] as $t) {
    if (has_column($db, $t, 'tkey')) { echo "   · $t.tkey var\n"; continue; }
    $db->exec("ALTER TABLE `$t` ADD COLUMN tkey VARCHAR(160) DEFAULT ''");
    echo "   + $t.tkey\n";
}

echo "\n2) tkey doldurulur (AZ slug-a bağlanır)\n";
foreach (['services', 'news'] as $t) {
    // AZ sətirlərində tkey = öz slug-ı
    $db->exec("UPDATE `$t` SET tkey = slug WHERE lang = 'az' AND (tkey IS NULL OR tkey = '')");
    // RU/EN: hazırda slug-lar AZ ilə eynidir, ona görə tkey = slug
    $db->exec("UPDATE `$t` SET tkey = slug WHERE (tkey IS NULL OR tkey = '')");
    $n = (int)$db->query("SELECT COUNT(*) c FROM `$t` WHERE tkey <> ''")->fetch()['c'];
    echo "   $t: $n sətir\n";
}

echo "\n3) RU xidmət slug-ları orijinal saytdakı kimi\n";
$ruSlugs = [
    'gomruk-beyannameleri'      => 'gomruk-beyannamelerinin-tertib-olunmasi',
    'idxal-ixrac-senedleri'     => 'idxal-ve-ixrac-zamani-teleb-olunan-zeruri-senedlerin-hazirlanmasi',
    'sertifikatlar-ve-icazeler' => 'dovlet-orqanlarindan-lazimi-sertifikatlarin-ve-icaze-senedlerin-alinmasi',
    'yuklerin-catdirilmasi'     => 'yuklerin-gomruk-anbarindan-tehvil-alinmasi-ve-musterinin-unvanina-catdirilmasi',
    'logistika-xidmetleri'      => 'logistika-xidmeti',
    'konsaltinq-xidmeti'        => 'konsaltinq-xidmeti',
    'konsiqnasiya-xidmetleri'   => 'konsiqnasiya-isleri',
];
$u = $db->prepare("UPDATE services SET slug = ? WHERE lang = 'ru' AND tkey = ?");
$n = 0;
foreach ($ruSlugs as $az => $ru) {
    try { $u->execute([$ru, $az]); $n += $u->rowCount(); } catch (Throwable $e) { echo "   ! $az: " . $e->getMessage() . "\n"; }
}
echo "   dəyişdirildi: $n\n";

echo "\nYekun:\n";
foreach ($db->query("SELECT lang, slug, tkey FROM services ORDER BY tkey, lang") as $r) {
    echo sprintf("   %-3s %-62s <- %s\n", $r['lang'], $r['slug'], $r['tkey']);
}
echo "\nHAZIRDIR.\n";
