<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

http_response_code(404);
if (!isset($form)) $form = ['status' => '', 'msg' => '', 'old' => []];
if (!function_exists('old')) {
    function old(string $key): string { return ''; }
}
if (!defined('PAGE_KEY')) define('PAGE_KEY', '');
$page_title = t('e404.meta') . ' | ' . SITE_NAME;
$page_desc  = t('e404.desc');
$page_robots = 'noindex,follow';       // tapılmayan səhifə indeksləşməsin
require __DIR__ . '/includes/header.php';
?>

<div class="wrap e404">
  <b>404</b>
  <h1><?= e(t('e404.title')) ?></h1>
  <p><?= e(t('e404.text')) ?></p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
    <a href="<?= e(url('/')) ?>" class="btn btn-p"><?= e(t('nav.home')) ?></a>
    <a href="<?= e(url('/xidmetler')) ?>" class="btn btn-d"><?= e(t('nav.services')) ?></a>
    <a href="<?= e(url('/elaqe')) ?>" class="btn btn-d"><?= e(t('nav.contact')) ?></a>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
