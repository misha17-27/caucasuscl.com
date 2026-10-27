/* Caucasus CL — interfeys skriptləri */
(function () {
  'use strict';

  /* ---- Mobil menyu ---- */
  var burger = document.getElementById('burger');
  var nav = document.getElementById('nav');

  var navClose = document.getElementById('navClose');

  if (burger && nav) {
    var setNav = function (open) {
      nav.classList.toggle('open', open);
      burger.classList.toggle('on', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.classList.toggle('nav-open', open);
      if (open) nav.scrollTop = 0;
    };

    burger.addEventListener('click', function () {
      setNav(!nav.classList.contains('open'));
    });
    if (navClose) navClose.addEventListener('click', function () { setNav(false); });

    nav.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () { setNav(false); });
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && nav.classList.contains('open')) setNav(false);
    });
    // Ekran genişlənəndə menyu açıq qalmasın
    window.addEventListener('resize', function () {
      if (window.innerWidth > 900 && nav.classList.contains('open')) setNav(false);
    });
  }

  /* ---- Dil seçici (açılan) ---- */
  var langsels = [].slice.call(document.querySelectorAll('[data-langsel]'));
  function closeLangs(except) {
    langsels.forEach(function (d) {
      if (d === except) return;
      d.classList.remove('open');
      var b = d.querySelector('.langsel-btn');
      if (b) b.setAttribute('aria-expanded', 'false');
    });
  }
  langsels.forEach(function (d) {
    var btn = d.querySelector('.langsel-btn');
    if (!btn) return;
    btn.addEventListener('click', function (ev) {
      ev.stopPropagation();
      var open = !d.classList.contains('open');
      closeLangs(d);
      d.classList.toggle('open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });
  document.addEventListener('click', function () { closeLangs(null); });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') closeLangs(null);
  });

  /* ---- Hero slayder ---- */
  var slides = [].slice.call(document.querySelectorAll('.hero-bg i'));
  if (slides.length > 1) {
    var si = 0;
    setInterval(function () {
      slides[si].classList.remove('on');
      si = (si + 1) % slides.length;
      slides[si].classList.add('on');
    }, 6000);
  }

  /* ---- Skrol animasiyaları ---- */
  var reveals = [].slice.call(document.querySelectorAll('.reveal'));
  if (!('IntersectionObserver' in window)) {
    reveals.forEach(function (el) { el.classList.add('in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
    reveals.forEach(function (el, i) {
      el.style.transitionDelay = (i % 3) * 70 + 'ms';
      io.observe(el);
    });
  }

  /* ---- Rəqəm sayğacları ---- */
  var counters = [].slice.call(document.querySelectorAll('[data-to]'));
  function runCounter(el) {
    var to = parseInt(el.getAttribute('data-to'), 10) || 0;
    var suffix = el.getAttribute('data-suffix') || '';
    var start = performance.now();
    (function tick(now) {
      var p = Math.min((now - start) / 1600, 1);
      var v = Math.floor((1 - Math.pow(1 - p, 3)) * to);
      el.textContent = v.toLocaleString('az-AZ') + (p === 1 ? suffix : '');
      if (p < 1) requestAnimationFrame(tick);
    })(start);
  }
  if (counters.length) {
    if (!('IntersectionObserver' in window)) {
      counters.forEach(runCounter);
    } else {
      var co = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { co.unobserve(en.target); runCounter(en.target); }
        });
      }, { threshold: 0.4 });
      counters.forEach(function (el) { co.observe(el); });
    }
  }

  /* ---- Loqo lenti (fasiləsiz döngü üçün dublikat) ---- */
  var mq = document.getElementById('mq');
  if (mq) mq.innerHTML += mq.innerHTML;

  /* ---- «WhatsApp ilə göndər»: formanı mesaj mətninə çevirir ---- */
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-wa-send]') : null;
    if (!btn) return;
    ev.preventDefault();

    var form  = btn.closest('form');
    var phone = (btn.getAttribute('data-wa-phone') || '').replace(/\D+/g, '');
    if (!phone) return;

    var L = {};
    try { L = JSON.parse(btn.getAttribute('data-wa-labels') || '{}'); } catch (e) { L = {}; }

    function val(name) {
      if (!form) return '';
      var el = form.elements[name];
      return el && el.value ? String(el.value).trim() : '';
    }

    var lines = [];
    if (L.intro) lines.push(L.intro);

    var ad = [val('ad'), val('soyad')].filter(Boolean).join(' ');
    if (ad)              lines.push((L.name    || 'Ad') + ': ' + ad);
    if (val('telefon'))  lines.push((L.phone   || 'Tel') + ': ' + val('telefon'));
    if (val('email'))    lines.push((L.email   || 'Email') + ': ' + val('email'));
    if (val('movzu'))    lines.push((L.subject || 'Mövzu') + ': ' + val('movzu'));
    if (val('mesaj'))    lines.push((L.message || 'Mesaj') + ': ' + val('mesaj'));

    var text = lines.join('\n');
    var url  = 'https://wa.me/' + phone + (text ? '?text=' + encodeURIComponent(text) : '');

    var w = window.open(url, '_blank', 'noopener');
    if (!w) window.location.href = url;   // popup bağlıdırsa
  });
})();
