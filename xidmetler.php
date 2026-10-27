<?php
require_once __DIR__ . '/includes/form.php';
define('PAGE_KEY', 'services');
$seo = cms_seo('xidmetler');
$page_title = ($seo['title'] ?? '') ?: 'Gömrük və logistika xidmətləri Bakıda | ' . SITE_NAME;
$page_desc  = ($seo['descr'] ?? '') ?: 'Bakıda gömrük və logistika xidmətləri: bəyannamələrin tərtibi, idxal-ixrac sənədləri, sertifikatlar, yüklərin çatdırılması, logistika, konsaltinq və konsiqnasiya.';
/* Xidmətlərin maşın üçün oxunaqlı siyahısı.
   Google-un AI cavabları şirkətin nə təklif etdiyini məhz belə bloklardan
   götürür — səhifənin mətnini təkrar-təkrar oxumaqdansa. */
$__items = [];
$__i = 1;
foreach ($SERVICES as $__slug => $__s) {
    $__items[] = [
        '@type'    => 'ListItem',
        'position' => $__i++,
        'name'     => (string)$__s['title'],
        'url'      => abs_url(url('/xidmetler/' . $__slug)),
    ];
}

$page_ld = [
    ld_breadcrumbs([
        t('nav.home') => url('/'),
        t('nav.services') => url('/xidmetler'),
    ]),
    [
        '@type'           => 'ItemList',
        'name'            => $page_title,
        'numberOfItems'   => count($__items),
        'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
        'itemListElement' => $__items,
    ],
];
require __DIR__ . '/includes/header.php';
?>

<section class="phero">
  <div class="phero-bg" style="background-image:url('<?= e(img('2022/08/slider-1.jpg')) ?>')"></div>
  <div class="wrap">
    <nav class="crumbs" aria-label="<?= e(t('nav.crumbs')) ?>">
      <a href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a><span>/</span><span><?= e(t('nav.services')) ?></span>
    </nav>
    <h1><?= e(cms_page('xidmetler', 'hero_title', t('page.svc.h1'))) ?></h1>
    <p><?= e(cms_page('xidmetler', 'hero_text', t('page.svc.text'))) ?></p>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow"><?= e(t('eyebrow.seven')) ?></span>
      <h2><?= e(t('page.svc.sub')) ?></h2>
      <p><?= e(t('page.svc.subtext')) ?></p>
    </div>

    <div class="svc-grid">
      <?php foreach ($SERVICES as $slug => $s): ?>
        <article class="svc reveal">
          <a class="svc-img" href="<?= e(url('/xidmetler/' . $slug)) ?>">
            <span class="svc-no"><?= e($s['no']) ?></span>
            <img src="<?= e(img($s['img'])) ?>"<?= img_attrs($s['img']) ?> alt="<?= e($s['title']) ?>" loading="lazy">
          </a>
          <div class="svc-body">
            <h3><a href="<?= e(url('/xidmetler/' . $slug)) ?>"><?= e($s['title']) ?></a></h3>
            <p><?= e($s['short']) ?></p>
            <a class="svc-more" href="<?= e(url('/xidmetler/' . $slug)) ?>"><?= e(t('btn.more')) ?> <?= icon('arrow') ?></a>
          </div>
        </article>
      <?php endforeach; ?>

      <article class="svc cta reveal">
        <h3><?= e(t('svc.pkg.title')) ?></h3>
        <p><?= e(t('svc.pkg.text')) ?></p>
        <a href="<?= e(url('/elaqe')) ?>" class="btn btn-g" style="align-self:flex-start"><?= e(t('btn.getoffer')) ?></a>
      </article>
    </div>
  </div>
</section>

<section class="sec process">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow"><?= e(t('eyebrow.principle')) ?></span>
      <h2><?= e(cms_text('process_title', t('sec.process.title'))) ?></h2>
      <p><?= e(cms_text('process_text', t('sec.process.text'))) ?></p>
    </div>
    <div class="steps">
      <?php foreach ($PROCESS as $p): ?>
        <div class="step reveal"><b><?= e($p['n']) ?></b><h3><?= e($p['t']) ?></h3><p><?= e($p['d']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/contact-section.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
