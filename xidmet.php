<?php
require_once __DIR__ . '/includes/form.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9\-]/', '', (string)$_GET['slug']) : '';

if ($slug === '' || !isset($SERVICES[$slug])) {
    /* Köhnə saytda eyni xidmətin başqa slug-ı ola bilər — 404 yerinə 301 */
    if ($slug !== '' && function_exists('cms_find_slug')) {
        $alt = cms_find_slug('services', $slug, LANG);
        if ($alt !== '' && isset($SERVICES[$alt])) {
            header('Location: ' . url('/xidmetler/' . $alt), true, 301);
            exit;
        }
    }
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$s = $SERVICES[$slug];
$active_service = $slug;

define('PAGE_KEY', 'services');
$page_title = $s['meta_title'] ?? ($s['title'] . ' | ' . SITE_NAME);
$page_desc  = $s['meta_desc'] ?? excerpt($s['lead'], 155);
$page_image = (string)($s['img'] ?? '');
$page_ld = [
    [
        '@type'       => 'Service',
        'name'        => (string)$s['title'],
        'description' => excerpt((string)($s['lead'] ?? $s['short'] ?? ''), 300),
        'serviceType' => (string)$s['title'],
        'url'         => abs_url(url('/xidmetler/' . $slug)),
        'provider'    => ['@id' => site_origin() . '/#org'],
        'areaServed'  => ['@type' => 'Country', 'name' => 'Azerbaijan'],
    ],
    ld_breadcrumbs([
        t('nav.home')     => url('/'),
        t('nav.services') => url('/xidmetler'),
        (string)$s['title'] => url('/xidmetler/' . $slug),
    ]),
];
require __DIR__ . '/includes/header.php';

// Qonşu xidmətlər (əvvəlki / növbəti)
$keys = array_keys($SERVICES);
$i    = array_search($slug, $keys, true);
$prev = $i > 0 ? $keys[$i - 1] : null;
$next = $i < count($keys) - 1 ? $keys[$i + 1] : null;
?>

<section class="phero">
  <div class="phero-bg" style="background-image:url('<?= e(img($s['img'])) ?>')"></div>
  <div class="wrap">
    <nav class="crumbs" aria-label="<?= e(t('nav.crumbs')) ?>">
      <a href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a><span>/</span>
      <a href="<?= e(url('/xidmetler')) ?>"><?= e(t('nav.services')) ?></a><span>/</span>
      <span><?= e(excerpt($s['title'], 40)) ?></span>
    </nav>
    <h1><?= e($s['title']) ?></h1>
  </div>
</section>

<section class="sec">
  <div class="wrap layout">
    <article class="prose reveal">
      <img src="<?= e(img($s['img'])) ?>"<?= img_attrs($s['img']) ?> alt="<?= e($s['title']) ?>" fetchpriority="high">
      <p class="lead"><?= e($s['lead']) ?></p>

      <?php /* baza varsa mətn, yoxsa config.php-dəki hazır bölmələr */ ?>
      <?= cms_body_html($s['body'] ?? ($s['sections'] ?? '')) ?>

      <h2><?= e(t('svc.why')) ?></h2>
      <div class="cards2">
        <?php foreach ($WHY_US as $w): ?>
          <div class="mini-card"><b><?= e($w['t']) ?></b><span><?= e($w['d']) ?></span></div>
        <?php endforeach; ?>
      </div>

      <p style="margin-top:26px">
        <a href="<?= e(url('/elaqe')) ?>" class="btn btn-p"><?= e(t('btn.servicereq')) ?> <?= icon('arrow') ?></a>
      </p>

      <?php if ($prev || $next): ?>
        <div style="display:flex;gap:14px;flex-wrap:wrap;justify-content:space-between;margin-top:34px;padding-top:24px;border-top:1px solid var(--line)">
          <?php if ($prev): ?>
            <a class="svc-more" href="<?= e(url('/xidmetler/' . $prev)) ?>"><?= icon('back') ?> <?= e(excerpt($SERVICES[$prev]['title'], 38)) ?></a>
          <?php else: ?><span></span><?php endif; ?>
          <?php if ($next): ?>
            <a class="svc-more" href="<?= e(url('/xidmetler/' . $next)) ?>"><?= e(excerpt($SERVICES[$next]['title'], 38)) ?> <?= icon('arrow') ?></a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </article>

    <?php include __DIR__ . '/includes/side.php'; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/contact-section.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
