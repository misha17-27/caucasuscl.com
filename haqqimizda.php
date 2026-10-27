<?php
require_once __DIR__ . '/includes/form.php';
define('PAGE_KEY', 'about');
$seo = cms_seo('haqqimizda');
$page_title = ($seo['title'] ?? '') ?: 'Haqqımızda | ' . SITE_NAME;
$page_desc  = ($seo['descr'] ?? '') ?: '“Caucasus Customs and Logistics” MMC 2018-ci ildən gömrük brokeri qismində fəaliyyət göstərir. Şirkət, prinsiplər və komanda haqqında.';
$page_ld = [ld_breadcrumbs([
    t('nav.home') => url('/'),
    t('nav.about') => url('/haqqimizda'),
])];
require __DIR__ . '/includes/header.php';
?>

<section class="phero">
  <div class="phero-bg" style="background-image:url('<?= e(img('2022/07/about2.jpg')) ?>')"></div>
  <div class="wrap">
    <nav class="crumbs" aria-label="<?= e(t('nav.crumbs')) ?>">
      <a href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a><span>/</span><span><?= e(t('nav.about')) ?></span>
    </nav>
    <h1><?= e(cms_page('haqqimizda', 'hero_title', t('page.about.h1'))) ?></h1>
    <p><?= e(cms_text('site_slogan', SITE_SLOGAN)) ?> — <?= e(cms_page('haqqimizda', 'hero_text', t('page.about.text'))) ?></p>
  </div>
</section>

<section class="sec">
  <div class="wrap layout">
    <article class="prose reveal">
      <p class="lead"><?= e(SITE_NAME) ?> — <?= e(t('about.lead')) ?></p>

      <p><?= e(SITE_NAME) ?> <?= e(t('about.p.intro')) ?></p>

      <?php $__body = cms_page('haqqimizda', 'body'); ?>
      <?php if ($__body !== ''): ?>
        <?= cms_body_html($__body) ?>
      <?php else: ?>
      <h2><?= e(t('about.h.princip')) ?></h2>
      <p><?= e(t('about.p.princip')) ?></p>

      <div class="cards2">
        <?php foreach ($WHY_US as $w): ?>
          <div class="mini-card"><b><?= e($w['t']) ?></b><span><?= e($w['d']) ?></span></div>
        <?php endforeach; ?>
      </div>

      <h2><?= e(t('about.h.team')) ?></h2>
      <p><?= e(t('about.p.team')) ?></p>

      <h2><?= e(t('about.h.legal')) ?></h2>
      <p><?= e(SITE_NAME) ?> <?= e(t('about.p.legal')) ?></p>

      <?php endif; ?>

      <h2><?= e(t('about.h.numbers')) ?></h2>
      <ul>
        <?php foreach ($STATS as $st): ?>
          <li><?= icon('check') ?> <span><strong><?= e(number_format((float)$st['n'], 0, ',', ' ') . $st['suffix']) ?></strong> — <?= e(mb_strtolower($st['label'])) ?></span></li>
        <?php endforeach; ?>
      </ul>

      <p style="margin-top:24px">
        <a href="<?= e(url('/xidmetler')) ?>" class="btn btn-p"><?= e(t('btn.knowus')) ?> <?= icon('arrow') ?></a>
      </p>
    </article>

    <?php include __DIR__ . '/includes/side.php'; ?>
  </div>
</section>

<section class="sec process">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow"><?= e(t('eyebrow.howwework')) ?></span>
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

<section class="clients">
  <div class="wrap center" style="margin-bottom:34px;max-width:640px">
    <span class="eyebrow"><?= e(t('eyebrow.ourclients')) ?></span>
    <h2 style="font-size:clamp(22px,3vw,28px);margin-top:12px"><?= e(cms_text('clients_title', t('sec.clients.title'))) ?></h2>
  </div>
  <div class="marquee">
    <div class="marquee-t" id="mq">
      <?php foreach ($CLIENTS as $c): ?>
        <img src="<?= e(img($c)) ?>"<?= img_attrs($c) ?> alt="" loading="lazy">
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/contact-section.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
