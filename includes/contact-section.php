<?php
/**
 * Təkrarlanan "Bizimlə əlaqə" bloku (yaşıl info + ağ forma).
 * İstifadə: include __DIR__.'/includes/contact-section.php';
 */
?>
<section class="sec contact" id="elaqe">
  <div class="wrap contact-in reveal">

    <div class="contact-info">
      <h2><?= e(t('contact.title')) ?></h2>
      <p><?= e(t('contact.text')) ?></p>

      <div class="ci">
        <div class="ci-ic"><?= icon('phone') ?></div>
        <div><b><?= e(t('contact.phone')) ?></b><a href="tel:<?= e($CONTACT['phone_raw']) ?>"><span><?= e($CONTACT['phone']) ?></span></a></div>
      </div>
      <div class="ci">
        <div class="ci-ic"><?= icon('mail') ?></div>
        <div><b><?= e(t('contact.email')) ?></b><a href="mailto:<?= e($CONTACT['email']) ?>"><span><?= e($CONTACT['email']) ?></span></a></div>
      </div>
      <div class="ci">
        <div class="ci-ic"><?= icon('pin') ?></div>
        <div><b><?= e(t('contact.address')) ?></b><span><?= nl2br(e($CONTACT['address_full'])) ?></span></div>
      </div>
      <div class="ci">
        <div class="ci-ic"><?= icon('clock') ?></div>
        <div><b><?= e(t('contact.hours')) ?></b><span><?= e($CONTACT['hours_week']) ?><br><?= e($CONTACT['hours_sat']) ?></span></div>
      </div>

      <div class="socials">
        <a href="<?= e(safe_url($CONTACT['facebook'])) ?>" target="_blank" rel="noopener" aria-label="Facebook"><?= social_icon('facebook') ?></a>
        <a href="<?= e(safe_url($CONTACT['instagram'])) ?>" target="_blank" rel="noopener" aria-label="Instagram"><?= social_icon('instagram') ?></a>
        <a href="<?= e(safe_url($CONTACT['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= social_icon('whatsapp') ?></a>
      </div>
    </div>

    <form class="contact-form" method="post" action="#elaqe">
      <h3><?= e(t('contact.form')) ?></h3>
      <p><?= e(t('contact.formtext')) ?></p>

      <?php if ($form['status'] === 'ok'): ?>
        <div class="alert alert-ok"><?= e($form['msg']) ?></div>
      <?php elseif ($form['status'] === 'err'): ?>
        <div class="alert alert-err"><?= e($form['msg']) ?></div>
      <?php endif; ?>

      <input type="hidden" name="cl_form" value="1">
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

      <div class="row2">
        <div class="field"><label for="c-ad"><?= e(t('f.name')) ?></label><input id="c-ad" name="ad" required placeholder="<?= e(t('f.ph.name')) ?>" value="<?= old('ad') ?>"></div>
        <div class="field"><label for="c-soyad"><?= e(t('f.surname')) ?></label><input id="c-soyad" name="soyad" placeholder="<?= e(t('f.ph.surname')) ?>" value="<?= old('soyad') ?>"></div>
      </div>
      <div class="row2">
        <div class="field"><label for="c-tel"><?= e(t('f.phone')) ?></label><input id="c-tel" name="telefon" type="tel" placeholder="<?= e(t('f.ph.phone')) ?>" value="<?= old('telefon') ?>"></div>
        <div class="field"><label for="c-mail"><?= e(t('f.email')) ?></label><input id="c-mail" name="email" type="email" placeholder="<?= e(t('f.ph.email')) ?>" value="<?= old('email') ?>"></div>
      </div>
      <div class="field">
        <label for="c-movzu"><?= e(t('f.subject')) ?></label>
        <select id="c-movzu" name="movzu">
          <option value=""><?= e(t('f.select')) ?></option>
          <?php foreach ($SERVICES as $s): ?>
            <option value="<?= e($s['title']) ?>"><?= e($s['title']) ?></option>
          <?php endforeach; ?>
          <option value="<?= e(t('f.other')) ?>"><?= e(t('f.other')) ?></option>
        </select>
      </div>
      <div class="field"><label for="c-mesaj"><?= e(t('f.message')) ?></label><textarea id="c-mesaj" name="mesaj" placeholder="<?= e(t('quote.ph.brief')) ?>"><?= old('mesaj') ?></textarea></div>

      <?= turnstile_widget() ?>

      <button class="btn btn-p btn-full" type="submit"><?= e(t('btn.send')) ?> <?= icon('arrow') ?></button>
      <?= whatsapp_button() ?>
      <small class="form-note"><?= e(t('f.privacy')) ?></small>
    </form>

  </div>
</section>
