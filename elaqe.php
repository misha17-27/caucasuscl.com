<?php
require_once __DIR__ . '/includes/form.php';
define('PAGE_KEY', 'contact');
$seo = cms_seo('elaqe');
$page_title = ($seo['title'] ?? '') ?: 'Əlaqə | ' . SITE_NAME;
$page_desc  = ($seo['descr'] ?? '') ?: 'Bakı, Heydər Əliyev Prospekti 95a, Qafqaz Biznes Mərkəzi. Telefon: (+994) 70 548 01 10. Gömrük brokeri ilə əlaqə.';
$page_ld = [ld_breadcrumbs([
    t('nav.home') => url('/'),
    t('nav.contact') => url('/elaqe'),
])];
require __DIR__ . '/includes/header.php';
?>

<section class="phero">
  <div class="phero-bg" style="background-image:url('<?= e(img('2022/07/slide3-2.jpg')) ?>')"></div>
  <div class="wrap">
    <nav class="crumbs" aria-label="<?= e(t('nav.crumbs')) ?>">
      <a href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a><span>/</span><span><?= e(t('nav.contact')) ?></span>
    </nav>
    <h1><?= e(cms_page('elaqe', 'hero_title', t('page.contact.h1'))) ?></h1>
    <p><?= e(cms_page('elaqe', 'hero_text', t('page.contact.text'))) ?></p>
  </div>
</section>

<?php include __DIR__ . '/includes/contact-section.php'; ?>

<section class="sec-sm" style="padding-top:0">
  <div class="wrap">
    <div class="map reveal">
      <iframe src="<?= e(safe_url($CONTACT['map'])) ?>" loading="lazy" title="<?= e(t('contact.map')) ?>" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
    </div>
  </div>
</section>

<section class="sec-sm" style="background:#fff;border-top:1px solid var(--line)">
  <div class="wrap">
    <div class="sec-head center reveal">
      <span class="eyebrow"><?= e(t('eyebrow.faq')) ?></span>
      <h2 style="font-size:clamp(22px,3vw,32px)"><?= e(t('faq.title')) ?></h2>
    </div>
    <div class="cards2" style="max-width:900px;margin:0 auto">
      <div class="mini-card"><b><?= e(t('faq.q1')) ?></b><span><?= e(t('faq.a1')) ?></span></div>
      <div class="mini-card"><b><?= e(t('faq.q2')) ?></b><span><?= e(t('faq.a2')) ?></span></div>
      <div class="mini-card"><b><?= e(t('faq.q3')) ?></b><span><?= e(t('faq.a3')) ?></span></div>
      <div class="mini-card"><b><?= e(t('faq.q4')) ?></b><span><?= e(t('faq.a4')) ?></span></div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
