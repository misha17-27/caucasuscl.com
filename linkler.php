<?php
require_once __DIR__ . '/includes/form.php';
define('PAGE_KEY', 'links');
$seo = cms_seo('linkler');
$page_title = ($seo['title'] ?? '') ?: 'Faydalı linklər | ' . SITE_NAME;
$page_desc  = ($seo['descr'] ?? '') ?: 'İdxal-ixrac və gömrük rəsmiləşdirilməsi üçün lazım olan dövlət orqanlarının rəsmi saytları.';
$page_ld = [ld_breadcrumbs([
    t('nav.home') => url('/'),
    t('nav.links') => url('/linkler'),
])];
require __DIR__ . '/includes/header.php';
?>

<section class="phero">
  <div class="phero-bg" style="background-image:url('<?= e(img('2022/07/slide3-3.jpg')) ?>')"></div>
  <div class="wrap">
    <nav class="crumbs" aria-label="<?= e(t('nav.crumbs')) ?>">
      <a href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a><span>/</span><span><?= e(t('nav.links')) ?></span>
    </nav>
    <h1><?= e(cms_page('linkler', 'hero_title', t('page.links.h1'))) ?></h1>
    <p><?= e(cms_page('linkler', 'hero_text', t('page.links.text'))) ?></p>
  </div>
</section>

<section class="sec">
  <div class="wrap layout">
    <div>
      <div class="links-grid">
        <?php foreach ($USEFUL_LINKS as $l): ?>
          <a class="link-card reveal" href="<?= e(safe_url($l['u'])) ?>" target="_blank" rel="noopener">
            <span class="ic"><?= icon('ext') ?></span>
            <div>
              <h3><?= e($l['t']) ?></h3>
              <p><?= e($l['d']) ?></p>
              <span><?= e(parse_url($l['u'], PHP_URL_HOST)) ?> →</span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="prose reveal" style="margin-top:30px">
        <h2><?= e(t('page.links.help')) ?></h2>
        <p><?= e(t('page.links.helptext')) ?></p>
        <p style="margin-top:20px"><a href="<?= e(url('/elaqe')) ?>" class="btn btn-p"><?= e(t('btn.writeus')) ?> <?= icon('arrow') ?></a></p>
      </div>
    </div>

    <?php include __DIR__ . '/includes/side.php'; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/contact-section.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
