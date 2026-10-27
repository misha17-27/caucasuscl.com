<?php
/**
 * Daxili səhifələrin yan paneli: xidmət siyahısı + əlaqə kartı.
 * $active_service — hazırda açıq olan xidmətin slug-ı (varsa).
 */
$active_service = $active_service ?? '';
?>
<aside class="side">

  <div class="side-box">
    <h3><?= e(t('side.services')) ?></h3>
    <ul class="side-nav">
      <?php foreach ($SERVICES as $slug => $s): ?>
        <li>
          <a href="<?= e(url('/xidmetler/' . $slug)) ?>"<?= $active_service === $slug ? ' class="on"' : '' ?>>
            <i><?= e($s['no']) ?></i><span><?= e($s['title']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="side-box side-cta">
    <h3><?= e(t('side.question')) ?></h3>
    <p><?= e(t('side.text')) ?></p>
    <div class="ci">
      <div class="ci-ic"><?= icon('phone') ?></div>
      <div><b><?= e(t('contact.phone')) ?></b><a href="tel:<?= e($CONTACT['phone_raw']) ?>"><span><?= e($CONTACT['phone']) ?></span></a></div>
    </div>
    <div class="ci">
      <div class="ci-ic"><?= icon('mail') ?></div>
      <div><b><?= e(t('contact.email')) ?></b><a href="mailto:<?= e($CONTACT['email']) ?>"><span><?= e($CONTACT['email']) ?></span></a></div>
    </div>
    <a href="<?= e(url('/elaqe')) ?>" class="btn btn-g btn-full"><?= e(t('btn.send')) ?></a>
  </div>

</aside>
