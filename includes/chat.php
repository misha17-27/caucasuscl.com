<?php
/**
 * Onlayn söhbət vidceti.
 *
 * Gedişat: xidmət → ad → telefon → WhatsApp. Söhbəti `assets/js/chat.js`
 * aparır, bu fayl yalnız qabığı və məlumatı verir.
 *
 * Niyə `data-` atributları, niyə sətiriçi skript yox: .htaccess-dəki CSP
 * `script-src`-də 'unsafe-inline' SAXLAMIR. Sətiriçi <script> ümumiyyətlə
 * işləməzdi. Eyni sxem `whatsapp_button()`-da da işlənir.
 *
 * Nömrə yalnız rəqəmlərdən ibarət olmalıdır — wa.me başqa formatı qəbul etmir.
 */

$__waPhone = preg_replace('/\D+/', '', (string)($CONTACT['phone_raw'] ?? ''));
if ($__waPhone === '') return;                    // nömrə yoxdursa vidcet mənasızdır

/* Düymə üçün qısa ad, WhatsApp mesajı üçün tam ad: başlıqların bəzisi
   40 simvoldan uzundur və düymədə sətri pozur. */
$__chatSvc = [];
foreach ($SERVICES as $__slug => $__s) {
    $__title = (string)$__s['title'];
    $__chatSvc[] = ['s' => excerpt($__title, 34), 'f' => $__title];
}

$__chatStr = [
    'hello'    => t('chat.hello'),
    'qService' => t('chat.q.service'),
    'other'    => t('chat.other'),
    'qOther'   => t('chat.q.other'),
    'qName'    => t('chat.q.name'),
    'qPhone'   => t('chat.q.phone'),
    'phMsg'    => t('chat.ph.msg'),
    'phName'   => t('chat.ph.name'),
    'phPhone'  => t('chat.ph.phone'),
    'errText'  => t('chat.err.text'),
    'errName'  => t('chat.err.name'),
    'errPhone' => t('chat.err.phone'),
    'done'     => t('chat.done'),
    'btnWa'    => t('chat.btn.wa'),
    'fallback' => t('chat.fallback'),
    'waIntro'  => t('wa.intro'),
    'lService' => t('chat.f.service'),
    'lName'    => t('f.name'),
    'lPhone'   => t('f.phone'),
];
?>
<div class="chat" id="chat"
     data-chat
     data-chat-phone="<?= e($__waPhone) ?>"
     data-chat-lang="<?= e(LANG) ?>"
     data-chat-endpoint="<?= e(BASE_URL . '/chat.php') ?>"
     data-chat-services="<?= e((string)json_encode($__chatSvc, JSON_UNESCAPED_UNICODE)) ?>"
     data-chat-strings="<?= e((string)json_encode($__chatStr, JSON_UNESCAPED_UNICODE)) ?>">

  <div class="chat-panel" id="chatPanel" role="dialog" aria-modal="false"
       aria-label="<?= e(t('chat.open')) ?>" hidden>

    <div class="chat-head">
      <span class="chat-ava"><img src="<?= e(img('favicon.svg')) ?>" alt="" width="40" height="40" loading="lazy" decoding="async"></span>
      <span class="chat-id">
        <b><?= e(SITE_SHORT) ?></b>
        <span><?= e(t('chat.sub')) ?></span>
      </span>
      <button type="button" class="chat-ic" id="chatRestart" title="<?= e(t('chat.restart')) ?>" aria-label="<?= e(t('chat.restart')) ?>"><?= icon('restart') ?></button>
      <button type="button" class="chat-ic" id="chatClose" title="<?= e(t('chat.close')) ?>" aria-label="<?= e(t('chat.close')) ?>"><?= icon('close') ?></button>
    </div>

    <div class="chat-log" id="chatLog" role="log" aria-live="polite"></div>

    <form class="chat-foot" id="chatForm" autocomplete="off">
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <input type="text" id="chatInput" name="msg" maxlength="200"
             placeholder="<?= e(t('chat.ph.msg')) ?>" aria-label="<?= e(t('chat.ph.msg')) ?>">
      <button type="submit" class="chat-send" aria-label="<?= e(t('btn.send')) ?>"><?= icon('send') ?></button>
    </form>

  </div>

  <button type="button" class="chat-btn" id="chatOpen" aria-label="<?= e(t('chat.open')) ?>" aria-expanded="false" aria-controls="chatPanel">
    <?= icon('chat', 'chat-ico-open') ?>
    <?= icon('close', 'chat-ico-close') ?>
  </button>

</div>
