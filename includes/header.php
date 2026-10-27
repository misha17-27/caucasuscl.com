<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!defined('PAGE_KEY'))   define('PAGE_KEY', '');
if (!isset($page_title))    $page_title = SITE_NAME . ' — ' . cms_text('site_slogan', SITE_SLOGAN);
if (!isset($page_desc))     $page_desc  = t('page.svc.text');
if (!isset($page_canonical)) $page_canonical = (string)($seo['canonical'] ?? '');
if (!isset($page_robots))    $page_robots    = (string)($seo['robots'] ?? '');
if (!isset($page_image))     $page_image     = '';
if (!isset($page_ld))        $page_ld        = [];
$__canon = canonical_url($page_canonical);
$__og    = og_image($page_image);
?>
<!DOCTYPE html>
<html lang="<?= e(LANGS[LANG]['html']) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?></title>
<meta name="description" content="<?= e($page_desc) ?>">
<link rel="canonical" href="<?= e($__canon) ?>">
<?php if ($page_robots !== '' && $page_robots !== 'index,follow'): ?>
<meta name="robots" content="<?= e($page_robots) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= e($page_title) ?>">
<meta property="og:description" content="<?= e($page_desc) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e($__canon) ?>">
<meta property="og:site_name" content="<?= e(cms_text('site_name', SITE_NAME)) ?>">
<meta property="og:locale" content="<?= e(LANGS[LANG]['locale']) ?>">
<?php foreach (array_keys(LANGS) as $lc): if ($lc === LANG) continue; ?>
<meta property="og:locale:alternate" content="<?= e(LANGS[$lc]['locale']) ?>">
<?php endforeach; ?>
<meta property="og:image" content="<?= e($__og['url']) ?>">
<meta property="og:image:width" content="<?= (int)$__og['w'] ?>">
<meta property="og:image:height" content="<?= (int)$__og['h'] ?>">
<meta property="og:image:alt" content="<?= e($page_title) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= e($__og['url']) ?>">
<meta name="theme-color" content="#092235">
<?php foreach (array_keys(LANGS) as $lc): ?>
<link rel="alternate" hreflang="<?= e($lc) ?>" href="<?= e(abs_url(lang_switch_url($lc))) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e(abs_url(lang_switch_url(LANG_DEFAULT))) ?>">
<link rel="icon" href="<?= e(img('Caucasus-1.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(img('apple-touch-icon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(BASE_URL . '/assets/css/style.css') ?>?v=16">
<?php if (turnstile_on()): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<?= analytics_tags() ?>
<?= json_ld($page_ld) ?>
</head>
<body>
<a href="#main" class="skip"><?= e(t('nav.skip')) ?></a>

<div class="topbar">
  <div class="wrap">
    <div class="tb"><?= icon('pin') ?> <?= e($CONTACT['city']) ?>, <?= e($CONTACT['address']) ?></div>
    <div class="tb"><?= icon('phone') ?> <b><?= e($CONTACT['phone']) ?></b> — <?= e(t('top.support')) ?></div>
    <div class="tb"><?= icon('clock') ?> <?= e($CONTACT['hours_week']) ?> · <?= e($CONTACT['hours_sat']) ?></div>
    <div class="right"><?= lang_selector('dark') ?></div>
  </div>
</div>

<header>
  <div class="wrap">
    <a href="<?= e(url('/')) ?>" class="logo" aria-label="<?= e(SITE_NAME) ?>">
      <img src="<?= e(img('Caucasus-1.svg')) ?>" alt="<?= e(SITE_NAME) ?>" width="198" height="80">
    </a>
    <nav class="main" id="nav" aria-label="<?= e(t('nav.main')) ?>">

      <div class="nav-head">
        <a href="<?= e(url('/')) ?>" class="logo"><img src="<?= e(img('Caucasus-1.svg')) ?>" alt="<?= e(SITE_NAME) ?>" width="109" height="44"></a>
        <?= lang_selector('light') ?>
        <button type="button" class="nav-close" id="navClose" aria-label="<?= e(t('nav.close')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="nav-links">
        <?php foreach ($NAV as $item): ?>
          <a href="<?= e(url($item['u'])) ?>"<?= is_active($item['key']) ? ' class="on" aria-current="page"' : '' ?>><?= e($item['t']) ?></a>
        <?php endforeach; ?>
      </div>

      <div class="nav-foot">
        <a href="tel:<?= e($CONTACT['phone_raw']) ?>" class="btn btn-p"><?= icon('phone') ?> <?= e($CONTACT['phone']) ?></a>
        <div class="nav-soc">
          <a href="<?= e(safe_url($CONTACT['facebook'])) ?>" target="_blank" rel="noopener" aria-label="Facebook"><?= social_icon('facebook') ?></a>
          <a href="<?= e(safe_url($CONTACT['instagram'])) ?>" target="_blank" rel="noopener" aria-label="Instagram"><?= social_icon('instagram') ?></a>
          <a href="<?= e(safe_url($CONTACT['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= social_icon('whatsapp') ?></a>
        </div>
        <span class="nav-slogan"><?= e(cms_text('site_slogan', SITE_SLOGAN)) ?></span>
      </div>

    </nav>
    <div class="hdr-cta"><a href="<?= e(url('/elaqe')) ?>" class="btn btn-p"><?= e(t('top.consult')) ?></a></div>
    <button class="burger" id="burger" aria-label="<?= e(t('nav.open')) ?>" aria-expanded="false" aria-controls="nav"><span></span><span></span><span></span></button>
  </div>
</header>

<main id="main">
