<?php
require_once __DIR__ . '/includes/form.php';
define('PAGE_KEY', 'news');
$seo = cms_seo('xeberler');
$page_title = ($seo['title'] ?? '') ?: 'Xəbərlər və gömrük sual-cavab | ' . SITE_NAME;
$page_desc  = ($seo['descr'] ?? '') ?: 'Dövlət Gömrük Komitəsinin rəsmi izahları əsasında ən çox verilən gömrük sualları və cavabları.';
$page_ld = [ld_breadcrumbs([
    t('nav.home') => url('/'),
    t('nav.news') => url('/xeberler'),
])];
require __DIR__ . '/includes/header.php';
?>

<section class="phero">
  <div class="phero-bg" style="background-image:url('<?= e(img('2022/07/slide3-1.jpg')) ?>')"></div>
  <div class="wrap">
    <nav class="crumbs" aria-label="<?= e(t('nav.crumbs')) ?>">
      <a href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a><span>/</span><span><?= e(t('nav.news')) ?></span>
    </nav>
    <h1><?= e(cms_page('xeberler', 'hero_title', t('page.news.h1'))) ?></h1>
    <p><?= e(cms_page('xeberler', 'hero_text', t('page.news.text'))) ?></p>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="news-grid">
      <?php foreach ($NEWS as $slug => $n): ?>
        <article class="news reveal">
          <a class="news-img" href="<?= e(url('/xeberler/' . $slug)) ?>">
            <?php $__c = news_cover($n); ?><img src="<?= e(img($__c)) ?>"<?= img_attrs($__c) ?> alt="<?= e($n['title']) ?>" loading="lazy">
          </a>
          <div class="news-body">
            <span class="tag"><?= e(t(news_is_own($n) ? 'news.tag.article' : 'news.tag')) ?></span>
            <h3><a href="<?= e(url('/xeberler/' . $slug)) ?>"><?= e($n['title']) ?></a></h3>
            <p><?= e(excerpt($n['excerpt'], 118)) ?></p>
            <span class="news-date"><?= e(az_date($n['date'])) ?></span>
            <a class="news-more" href="<?= e(url('/xeberler/' . $slug)) ?>"><?= e(t('btn.readmore')) ?> →</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/contact-section.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
