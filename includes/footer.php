</main>

<footer>
  <div class="wrap">
    <div class="f-grid">
      <div>
        <span class="f-logo"><img src="<?= e(img('Caucasus-1.svg')) ?>" alt="<?= e(SITE_NAME) ?>" width="149" height="60"></span>
        <p><?= e(cms_text('site_slogan', SITE_SLOGAN)) ?>. <?= e(t('footer.text')) ?></p>
        <div class="f-soc">
          <a href="<?= e(safe_url($CONTACT['facebook'])) ?>" target="_blank" rel="noopener" aria-label="Facebook"><?= social_icon('facebook') ?></a>
          <a href="<?= e(safe_url($CONTACT['instagram'])) ?>" target="_blank" rel="noopener" aria-label="Instagram"><?= social_icon('instagram') ?></a>
          <a href="<?= e(safe_url($CONTACT['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= social_icon('whatsapp') ?></a>
        </div>
      </div>

      <div>
        <h4><?= e(t('footer.about')) ?></h4>
        <ul>
          <?php foreach ($NAV as $item): ?>
            <li><a href="<?= e(url($item['u'])) ?>"><?= e($item['t']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div>
        <h4><?= e(t('footer.services')) ?></h4>
        <ul>
          <?php foreach ($SERVICES as $slug => $s): ?>
            <li><a href="<?= e(url('/xidmetler/' . $slug)) ?>"><?= e(excerpt($s['title'], 42)) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div>
        <h4><?= e(t('footer.contact')) ?></h4>
        <ul>
          <li><a href="tel:<?= e($CONTACT['phone_raw']) ?>"><?= e($CONTACT['phone']) ?></a></li>
          <li><a href="mailto:<?= e($CONTACT['email']) ?>"><?= e($CONTACT['email']) ?></a></li>
          <li><?= nl2br(e($CONTACT['address_full'])) ?></li>
          <li><?= e($CONTACT['hours_week']) ?><br><?= e($CONTACT['hours_sat']) ?></li>
        </ul>
      </div>
    </div>

    <div class="f-bot">
      <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?> MMC — <?= e(t('footer.rights')) ?></span>
      <span><a href="<?= e(url('/linkler')) ?>"><?= e(t('footer.links')) ?></a></span>
    </div>
  </div>
</footer>

<a class="wa" href="<?= e(safe_url($CONTACT['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= social_icon('whatsapp') ?></a>

<script src="<?= e(BASE_URL . '/assets/js/main.js') ?>?v=5" defer></script>
</body>
</html>
