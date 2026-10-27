/*
 * Google Analytics 4 — başlatma.
 *
 * Google-un standart kod parçasında bu hissə səhifənin içində (inline)
 * yazılır. Saytın CSP siyasəti inline skriptləri qadağan edir, ona görə
 * eyni kod ayrıca fayla çıxarılıb. Ölçüsü kiçikdir, davranışı eynidir.
 *
 * Ölçmə nömrəsi <script> teqinin data-ga-id atributundan götürülür
 * (admin panelində «SEO» bölməsində dəyişdirilir).
 */
(function () {
  'use strict';

  var self = document.currentScript;
  var id = self && self.getAttribute('data-ga-id');

  /* Ehtiyat: nömrə gtag.js ünvanından oxunur */
  if (!id) {
    var tag = document.querySelector('script[src*="googletagmanager.com/gtag/js"]');
    if (tag) {
      var m = tag.src.match(/[?&]id=([^&]+)/);
      if (m) id = decodeURIComponent(m[1]);
    }
  }
  if (!id) return;

  window.dataLayer = window.dataLayer || [];
  window.gtag = function gtag() { window.dataLayer.push(arguments); };

  window.gtag('js', new Date());
  window.gtag('config', id);
})();
