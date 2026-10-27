<?php
require_once __DIR__ . '/includes/form.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9\-]/', '', (string)$_GET['slug']) : '';

if ($slug === '' || !isset($NEWS[$slug])) {
    /* Başqa dildəki slug ilə gəliblərsə — 404 yerinə 301 */
    if ($slug !== '' && function_exists('cms_find_slug')) {
        $alt = cms_find_slug('news', $slug, LANG);
        if ($alt !== '' && isset($NEWS[$alt])) {
            header('Location: ' . url('/xeberler/' . $alt), true, 301);
            exit;
        }
    }
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$n = $NEWS[$slug];

define('PAGE_KEY', 'news');
$page_title = trim((string)($n['meta_title'] ?? '')) !== '' ? $n['meta_title'] : $n['title'] . ' | ' . SITE_NAME;
$page_desc  = trim((string)($n['meta_desc'] ?? '')) !== '' ? $n['meta_desc'] : excerpt($n['excerpt'], 155);
/* Öz üz qabığı varsa paylaşımda o göstərilir; yoxdursa brendli 1200x630
   kart — bütün xəbərlər üçün ümumi şəkil paylaşımda zəif görünür. */
$__cover = trim((string)($n['image'] ?? ''));
if ($__cover !== '' && is_file(__DIR__ . '/assets/img/' . $__cover)) $page_image = $__cover;
$__isQA = trim((string)($n['question'] ?? '')) !== '';
$__body = trim((string)($n['body'] ?? ''));
$page_ld = [
    [
        '@type'            => 'NewsArticle',
        'headline'         => mb_substr((string)$n['title'], 0, 110),
        'description'      => excerpt((string)$n['excerpt'], 300),
        'datePublished'    => (string)$n['date'],
        'dateModified'     => (string)$n['date'],
        'inLanguage'       => LANG,
        'image'            => [og_image($__cover)['url']],
        'author'           => ['@id' => site_origin() . '/#org'],
        'publisher'        => ['@id' => site_origin() . '/#org'],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => abs_url(url('/xeberler/' . $slug))],
    ],
    ld_breadcrumbs([
        t('nav.home') => url('/'),
        t('nav.news') => url('/xeberler'),
        excerpt((string)$n['title'], 60) => url('/xeberler/' . $slug),
    ]),
];
/* Videolu xəbərlər üçün VideoObject.
   Belə səhifələrdə mətn azdır (cavab videodadır) — bu nişan sayəsində
   səhifə heç olmasa Google-un video nəticələrində görünə bilir.
   uploadDate kimi xəbərin dərc tarixi götürülür (video onunla birlikdə verilib). */
if (!empty($n['video'])) {
    $vid = (string)$n['video'];
    $page_ld[] = [
        '@type'        => 'VideoObject',
        'name'         => mb_substr((string)$n['title'], 0, 110),
        'description'  => excerpt((string)($n['question'] ?: $n['excerpt']), 300),
        'thumbnailUrl' => ['https://i.ytimg.com/vi/' . rawurlencode($vid) . '/hqdefault.jpg'],
        'uploadDate'   => (string)$n['date'],
        'embedUrl'     => 'https://www.youtube.com/embed/' . rawurlencode($vid),
        'inLanguage'   => LANG,
        'publisher'    => ['@id' => site_origin() . '/#org'],
    ];
}

/* Sual-cavab formatındakı xəbərlər üçün FAQ nişanı */
if ($__isQA && $__body !== '') {
    $page_ld[] = [
        '@type' => 'FAQPage',
        'mainEntity' => [[
            '@type' => 'Question',
            'name'  => mb_substr((string)$n['question'], 0, 250),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => excerpt($__body, 900)],
        ]],
    ];
}
require __DIR__ . '/includes/header.php';

$others = array_filter($NEWS, static fn($k) => $k !== $slug, ARRAY_FILTER_USE_KEY);
$others = array_slice($others, 0, 3, true);
?>

<section class="phero">
  <div class="phero-bg" style="background-image:url('<?= e(img(news_cover($n))) ?>')"></div>
  <div class="wrap">
    <nav class="crumbs" aria-label="<?= e(t('nav.crumbs')) ?>">
      <a href="<?= e(url('/')) ?>"><?= e(t('nav.home')) ?></a><span>/</span>
      <a href="<?= e(url('/xeberler')) ?>"><?= e(t('nav.news')) ?></a><span>/</span>
      <span><?= e(excerpt($n['title'], 40)) ?></span>
    </nav>
    <h1><?= e($n['title']) ?></h1>
    <p><?= e(az_date($n['date'])) ?> · <?= e(t(news_is_own($n) ? 'news.source.article' : 'news.source')) ?></p>
  </div>
</section>

<section class="sec">
  <div class="wrap layout">
    <article class="prose reveal">
      <?php if (trim((string)$n['question']) !== ''): ?>
        <div class="qbox">
          <b><?= e(t('news.question')) ?></b>
          <p><?= e($n['question']) ?></p>
        </div>
      <?php endif; ?>

      <?php if (!empty($n['video'])): ?><p><?= e(t('news.video')) ?></p><?php endif; ?>

      <?php if (!empty($n['video'])): ?>
        <div class="video">
          <iframe src="https://www.youtube.com/embed/<?= e($n['video']) ?>"
                  title="<?= e($n['title']) ?>"
                  loading="lazy" allowfullscreen
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
        </div>
      <?php endif; ?>

      <?php $__nb = trim((string)($n['body'] ?? '')); ?>
      <?php if ($__nb !== ''): ?>
        <?= cms_body_html($__nb, false) ?>
      <?php elseif (trim((string)$n['excerpt']) !== ''): ?>
        <p><?= e($n['excerpt']) ?></p>
      <?php endif; ?>

      <h2><?= e(t('news.left')) ?></h2>
      <p><?= e(t('news.lefttext')) ?></p>
      <p style="margin-top:22px">
        <a href="<?= e(url('/elaqe')) ?>" class="btn btn-p"><?= e(t('btn.expert')) ?> <?= icon('arrow') ?></a>
        <a href="<?= e(url('/xeberler')) ?>" class="btn btn-d" style="margin-left:8px"><?= icon('back') ?> <?= e(t('btn.allnews')) ?></a>
      </p>
    </article>

    <?php include __DIR__ . '/includes/side.php'; ?>
  </div>
</section>

<?php if ($others): ?>
<section class="sec-sm" style="background:#fff;border-top:1px solid var(--line)">
  <div class="wrap">
    <div class="sec-head reveal" style="margin-bottom:32px">
      <span class="eyebrow"><?= e(t('eyebrow.othernews')) ?></span>
      <h2 style="font-size:clamp(22px,3vw,30px)"><?= e(t('sec.othernews')) ?></h2>
    </div>
    <div class="news-grid">
      <?php foreach ($others as $oslug => $o): ?>
        <article class="news reveal">
          <a class="news-img" href="<?= e(url('/xeberler/' . $oslug)) ?>">
            <?php $__oc = news_cover($o); ?><img src="<?= e(img($__oc)) ?>"<?= img_attrs($__oc) ?> alt="<?= e($o['title']) ?>" loading="lazy">
          </a>
          <div class="news-body">
            <span class="tag"><?= e(t(news_is_own($o) ? 'news.tag.article' : 'news.tag')) ?></span>
            <h3><a href="<?= e(url('/xeberler/' . $oslug)) ?>"><?= e($o['title']) ?></a></h3>
            <span class="news-date"><?= e(az_date($o['date'])) ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/contact-section.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
