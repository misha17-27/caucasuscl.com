<?php
require_once __DIR__ . '/includes/form.php';
define('PAGE_KEY', 'home');
$seo = cms_seo('/');
$page_title = ($seo['title'] ?? '') ?: 'Gömrük brokeri Bakıda | ' . SITE_NAME;
$page_desc  = ($seo['descr'] ?? '') ?: 'Bakıda lisenziyalı gömrük brokeri. Gömrük bəyannamələri, idxal-ixrac sənədləri, sertifikatlar, logistika və yüklərin çatdırılması — 2018-ci ildən.';
$page_ld = [[
    '@type' => 'WebSite',
    '@id'   => site_origin() . '/#website',
    'url'   => site_origin() . '/',
    'name'  => cms_text('site_name', SITE_NAME),
    'inLanguage' => LANG,
    'publisher'  => ['@id' => site_origin() . '/#org'],
]];
require __DIR__ . '/includes/header.php';

$svc_list = $SERVICES;
$svc_keys = array_keys($svc_list);
$last_key = array_pop($svc_keys); // sonuncu xidmət geniş kartda göstərilir

$tick_default = [
    'Təcrübəli və ixtisaslaşdırılmış işçi qrupu',
    'Kommersiya məlumatlarının tam məxfiliyi',
    '18 fərqli sahə üzrə praktiki təcrübə',
    'Vahid əlaqə nöqtəsi — bir menecer, bütün proses',
];
?>

<section class="hero">
  <div class="hero-bg">
    <i class="on" style="background-image:url('<?= e(img('2022/08/slider-1.jpg')) ?>')"></i>
    <i style="background-image:url('<?= e(img('2022/07/slide3-1.jpg')) ?>')"></i>
    <i style="background-image:url('<?= e(img('2022/07/slide3-2.jpg')) ?>')"></i>
  </div>
  <div class="wrap">
    <div>
      <span class="badge"><span class="dot"></span> <?= e(cms_text('hero_badge', t('hero.badge'))) ?></span>
      <h1><?= nl2br(e(cms_text('hero_title', t('hero.title')))) ?> <em><?= e(cms_text('hero_accent', t('hero.accent'))) ?></em>.</h1>
      <p><?= e(cms_text('hero_text', t('hero.text'))) ?></p>
      <div class="hero-actions">
        <a href="<?= e(url('/elaqe')) ?>" class="btn btn-g"><?= e(t('btn.send')) ?> <?= icon('arrow') ?></a>
        <a href="<?= e(url('/xidmetler')) ?>" class="btn btn-o"><?= e(t('btn.ourservices')) ?></a>
      </div>
      <div class="hero-mini">
        <div><?= icon('check') ?> <?= e(t('hero.m1')) ?></div>
        <div><?= icon('shield') ?> <?= e(t('hero.m2')) ?></div>
        <div><?= icon('clock') ?> <?= e(t('hero.m3')) ?></div>
      </div>
    </div>

    <form class="quote" method="post" action="#sorgu" id="sorgu">
      <h3><?= e(t('quote.title')) ?></h3>
      <p><?= e(t('quote.text')) ?></p>

      <?php if ($form['status'] === 'ok'): ?>
        <div class="alert alert-ok"><?= e($form['msg']) ?></div>
      <?php elseif ($form['status'] === 'err'): ?>
        <div class="alert alert-err"><?= e($form['msg']) ?></div>
      <?php endif; ?>

      <input type="hidden" name="cl_form" value="1">
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

      <div class="row2">
        <div class="field"><label for="h-ad"><?= e(t('quote.name')) ?></label><input id="h-ad" name="ad" required placeholder="<?= e(t('f.ph.name')) ?>" value="<?= old('ad') ?>"></div>
        <div class="field"><label for="h-tel"><?= e(t('f.phone')) ?></label><input id="h-tel" name="telefon" type="tel" required placeholder="<?= e(t('f.ph.phone')) ?>" value="<?= old('telefon') ?>"></div>
      </div>
      <div class="field">
        <label for="h-movzu"><?= e(t('quote.type')) ?></label>
        <select id="h-movzu" name="movzu">
          <?php foreach ($SERVICES as $s): ?>
            <option value="<?= e($s['title']) ?>"><?= e($s['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="h-mesaj"><?= e(t('quote.brief')) ?></label><textarea id="h-mesaj" name="mesaj" rows="3" placeholder="<?= e(t('quote.ph.brief')) ?>"><?= old('mesaj') ?></textarea></div>
      <?= turnstile_widget() ?>

      <button class="btn btn-p btn-full" type="submit"><?= e(t('btn.send')) ?></button>
      <?= whatsapp_button() ?>
      <small class="form-note"><?= e(t('f.privacy')) ?></small>
    </form>
  </div>
</section>

<div class="wrap stats">
  <div class="stats-in">
    <?php foreach ($STATS as $st): ?>
      <div class="stat">
        <b data-to="<?= (int)$st['n'] ?>" data-suffix="<?= e($st['suffix']) ?>">0</b>
        <span><?= e($st['label']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<section class="sec" id="xidmetler">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow"><?= e(t('eyebrow.services')) ?></span>
      <h2><?= e(cms_text('svc_title', t('sec.svc.title'))) ?></h2>
      <p><?= e(cms_text('svc_text', t('sec.svc.text'))) ?></p>
    </div>

    <div class="svc-grid">
      <?php foreach ($svc_list as $slug => $s): ?>
        <article class="svc<?= $slug === $last_key ? ' wide' : '' ?> reveal">
          <div class="svc-img">
            <span class="svc-no"><?= e($s['no']) ?></span>
            <img src="<?= e(img($s['img'])) ?>"<?= img_attrs($s['img']) ?> alt="<?= e($s['title']) ?>" loading="lazy">
          </div>
          <div class="svc-body">
            <h3><?= e($s['title']) ?></h3>
            <p><?= e($s['short']) ?></p>
            <a class="svc-more" href="<?= e(url('/xidmetler/' . $slug)) ?>"><?= e(t('btn.more')) ?> <?= icon('arrow') ?></a>
          </div>
        </article>
      <?php endforeach; ?>

      <article class="svc cta reveal">
        <h3><?= e(t('svc.cta.title')) ?></h3>
        <p><?= e(t('svc.cta.text')) ?></p>
        <a href="<?= e(url('/elaqe')) ?>" class="btn btn-g" style="align-self:flex-start"><?= e(t('btn.talk')) ?></a>
      </article>
    </div>
  </div>
</section>

<section class="sec about" id="haqqimizda">
  <div class="wrap about-in">
    <div class="about-media reveal">
      <img src="<?= e(img('2022/07/about2.jpg')) ?>"<?= img_attrs('2022/07/about2.jpg') ?> alt="<?= e(SITE_NAME) ?>" loading="lazy">
      <div class="about-card"><b>2018</b><span><?= e(t('about.start')) ?></span></div>
    </div>
    <div class="reveal">
      <span class="eyebrow"><?= e(t('eyebrow.about')) ?></span>
      <h2><?= e(cms_text('about_title', cms_text('site_slogan', SITE_SLOGAN))) ?></h2>
      <p><?= e(cms_text('about_p1', SITE_NAME . ' ' . t('about.lead'))) ?></p>
      <p><?= e(cms_text('about_p2', t('about.p.princip'))) ?></p>
      <ul class="ticks">
        <?php foreach (lines(cms_text('about_ticks', implode(chr(10), $tick_default))) as $tick): ?>
          <li><?= icon('check') ?> <?= e($tick) ?></li>
        <?php endforeach; ?>
      </ul>
      <a href="<?= e(url('/haqqimizda')) ?>" class="btn btn-p"><?= e(t('btn.readmore')) ?></a>
    </div>
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
        <div class="step reveal">
          <b><?= e($p['n']) ?></b>
          <h3><?= e($p['t']) ?></h3>
          <p><?= e($p['d']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec" id="xeberler">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow"><?= e(t('eyebrow.news')) ?></span>
      <h2><?= e(cms_text('news_title', t('sec.news.title'))) ?></h2>
      <p><?= e(cms_text('news_text', t('sec.news.text'))) ?></p>
    </div>
    <div class="news-grid">
      <?php foreach (array_slice($NEWS, 0, 3, true) as $slug => $n): ?>
        <article class="news reveal">
          <a class="news-img" href="<?= e(url('/xeberler/' . $slug)) ?>">
            <?php $__c = news_cover($n); ?><img src="<?= e(img($__c)) ?>"<?= img_attrs($__c) ?> alt="<?= e($n['title']) ?>" loading="lazy">
          </a>
          <div class="news-body">
            <span class="tag"><?= e(t(news_is_own($n) ? 'news.tag.article' : 'news.tag')) ?></span>
            <h3><a href="<?= e(url('/xeberler/' . $slug)) ?>"><?= e($n['title']) ?></a></h3>
            <p><?= e(excerpt($n['excerpt'], 110)) ?></p>
            <a class="news-more" href="<?= e(url('/xeberler/' . $slug)) ?>"><?= e(t('btn.readmore')) ?> →</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:40px" class="reveal">
      <a href="<?= e(url('/xeberler')) ?>" class="btn btn-d"><?= e(t('btn.allnews')) ?> <?= icon('arrow') ?></a>
    </div>
  </div>
</section>

<section class="clients">
  <div class="wrap center" style="margin-bottom:34px;max-width:640px">
    <span class="eyebrow"><?= e(t('eyebrow.clients')) ?></span>
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
