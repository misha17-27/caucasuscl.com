/* Caucasus CL — onlayn söhbət: xidmət → ad → telefon → WhatsApp.
   Üslub main.js ilə eynidir (var, closure, ES5) — köhnə brauzerlər də işləsin. */
(function () {
  'use strict';

  var root = document.querySelector('[data-chat]');
  if (!root) return;

  var panel   = document.getElementById('chatPanel');
  var log     = document.getElementById('chatLog');
  var form    = document.getElementById('chatForm');
  var input   = document.getElementById('chatInput');
  var openBtn = document.getElementById('chatOpen');
  var closeBtn   = document.getElementById('chatClose');
  var restartBtn = document.getElementById('chatRestart');
  if (!panel || !log || !form || !input || !openBtn) return;

  function attr(name, fallback) {
    try { return JSON.parse(root.getAttribute(name) || fallback); }
    catch (e) { return JSON.parse(fallback); }
  }
  var S        = attr('data-chat-strings', '{}');
  var SERVICES = attr('data-chat-services', '[]');
  var PHONE    = (root.getAttribute('data-chat-phone') || '').replace(/\D+/g, '');
  var ENDPOINT = root.getAttribute('data-chat-endpoint') || '';
  var LANG     = root.getAttribute('data-chat-lang') || 'az';

  var STORE = 'ccl_chat';
  function blank() { return { step: 'service', data: {}, log: [], sent: false, lang: LANG }; }
  var state = blank();

  /* ---- vəziyyətin saxlanması ----
     sessionStorage bloklana bilər (gizli rejim, saytlararası məhdudiyyət) —
     hər müraciət try/catch içindədir, uğursuzluq söhbəti dayandırmır. */
  function save() {
    try { sessionStorage.setItem(STORE, JSON.stringify(state)); } catch (e) {}
  }
  function load() {
    try {
      var raw = sessionStorage.getItem(STORE);
      if (!raw) return false;
      var s = JSON.parse(raw);
      if (!s || typeof s !== 'object' || !s.step || !(s.log instanceof Array)) return false;
      /* Dil dəyişibsə köhnə söhbəti geri qurmuruq: rus səhifəsində azərbaycanca
         yazışma görünərdi. Söhbət sıfırdan, cari dildə başlayır. */
      if (s.lang !== LANG) return false;
      state = { step: s.step, data: s.data || {}, log: s.log, sent: !!s.sent, lang: LANG };
      return true;
    } catch (e) { return false; }
  }

  /* ---- mesajlar ---- */
  function scroll() { log.scrollTop = log.scrollHeight; }

  function bubble(role, text) {
    var d = document.createElement('div');
    d.className = 'chat-msg chat-' + (role === 'me' ? 'me' : 'bot');
    d.textContent = text;
    log.appendChild(d);
    scroll();
    return d;
  }

  function say(text, remember) {
    bubble('bot', text);
    if (remember !== false) { state.log.push(['bot', text]); save(); }
  }
  function me(text, remember) {
    bubble('me', text);
    if (remember !== false) { state.log.push(['me', text]); save(); }
  }

  /** Sürətli cavab düymələri — yalnız cari sualda görünür, seçimdən sonra silinir. */
  function choices(items) {
    var box = document.createElement('div');
    box.className = 'chat-opts';
    items.forEach(function (it) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'chat-opt';
      b.textContent = it.s;
      b.addEventListener('click', function () {
        box.remove();
        pick(it.f);
      });
      box.appendChild(b);
    });
    log.appendChild(box);
    scroll();
  }

  function clearOpts() {
    var o = log.querySelector('.chat-opts');
    if (o) o.remove();
  }

  /* ---- addımlar ---- */
  function askService() {
    state.step = 'service';
    input.placeholder = S.phMsg || '';
    input.setAttribute('inputmode', 'text');
    save();
    var items = SERVICES.map(function (x) { return { s: x.s, f: x.f }; });
    items.push({ s: S.other, f: '' });        // boş `f` = «başqa sual»
    choices(items);
  }

  function askName() {
    state.step = 'name';
    input.placeholder = S.phName || '';
    input.setAttribute('inputmode', 'text');
    save();
    say(S.qName);
    focus();
  }

  function askPhone() {
    state.step = 'phone';
    input.placeholder = S.phPhone || '';
    input.setAttribute('inputmode', 'tel');
    save();
    say(S.qPhone);
    focus();
  }

  /** Xidmət seçildi (boş dəyər — sərbəst mətn soruşulur). */
  function pick(full) {
    if (!full) {
      state.step = 'other';
      save();
      me(S.other);
      say(S.qOther);
      focus();
      return;
    }
    state.data.service = full;
    me(full);
    askName();
  }

  /* ---- telefon ---- */
  function phoneOk(v) {
    if (!/^[0-9+()\s.\-]{5,60}$/.test(v)) return false;
    var digits = v.replace(/\D+/g, '');
    return digits.length >= 7 && digits.length <= 15;
  }

  /* ---- yekun ---- */
  function waUrl() {
    var d = state.data;
    var lines = [];
    if (S.waIntro) lines.push(S.waIntro);
    if (d.service) lines.push((S.lService || 'Service') + ': ' + d.service);
    if (d.name)    lines.push((S.lName    || 'Name')    + ': ' + d.name);
    if (d.phone)   lines.push((S.lPhone   || 'Phone')   + ': ' + d.phone);
    var text = lines.join('\n');
    return 'https://wa.me/' + PHONE + (text ? '?text=' + encodeURIComponent(text) : '');
  }

  function openWa() {
    var url = waUrl();
    var w = window.open(url, '_blank', 'noopener');
    if (!w) window.location.href = url;          // popup bağlıdırsa
  }

  /** Sorğunu serverə də yazırıq: ziyarətçi WhatsApp-da «göndər»i basmasa,
      müraciət tamamilə itərdi. Cavab gözlənilmir — söhbət ondan asılı deyil. */
  function store() {
    if (state.sent || !ENDPOINT || !window.fetch) return;
    state.sent = true;
    save();
    var body = new URLSearchParams();
    body.set('movzu',   state.data.service || '');
    body.set('ad',      state.data.name    || '');
    body.set('telefon', state.data.phone   || '');
    body.set('lang',    LANG);
    body.set('website', '');                      // bot tələsi
    try {
      fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'chat' },
        body: body.toString(),
        credentials: 'same-origin'
      })['catch'](function () {});
    } catch (e) {}
  }

  function finish(fromGesture) {
    state.step = 'done';
    save();
    say(S.done);
    waBlock();
    store();
    if (fromGesture) openWa();                    // istifadəçi hərəkəti — popup keçir
    input.placeholder = S.phMsg || '';
    input.value = '';
    input.disabled = true;
  }

  function waBlock() {
    var box = document.createElement('div');
    box.className = 'chat-opts chat-final';
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'chat-wa';
    b.textContent = S.btnWa || 'WhatsApp';
    b.addEventListener('click', openWa);
    box.appendChild(b);
    var note = document.createElement('span');
    note.className = 'chat-note';
    note.textContent = S.fallback || '';
    box.appendChild(note);
    log.appendChild(box);
    scroll();
  }

  /* ---- mətn girişi ---- */
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (form.elements.website && form.elements.website.value) return;   // bot
    var v = input.value.replace(/\s+/g, ' ').trim();
    if (!v) return;

    /* Yazılan hər şey əvvəlcə söhbətdə görünür, sonra yoxlanılır — əks halda
       xəta cavabı nəyə aid olduğu bilinmir (iki eyni xəta yan-yana düşür). */
    input.value = '';
    me(v);

    if (state.step === 'service') {
      clearOpts();                       // düymələrə baxmadan yazdı — sərbəst mətn
      state.data.service = v;
      askName();
      return;
    }
    if (state.step === 'other') {
      if (v.length < 3) { say(S.errText); return; }
      state.data.service = v;
      askName();
      return;
    }
    if (state.step === 'name') {
      if (v.length < 2) { say(S.errName); return; }
      state.data.name = v;
      askPhone();
      return;
    }
    if (state.step === 'phone') {
      if (!phoneOk(v)) { say(S.errPhone); return; }
      state.data.phone = v;
      finish(true);
    }
  });

  /* ---- açıq/bağlı ---- */
  function focus() {
    if (window.innerWidth > 700 && !input.disabled) { try { input.focus(); } catch (e) {} }
  }

  function setOpen(open) {
    panel.hidden = !open;
    root.classList.toggle('open', open);
    openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (!open) return;
    if (!state.log.length) start();
    scroll();
    focus();
  }

  function start() {
    say(S.hello);
    say(S.qService);
    askService();
  }

  /** Saxlanmış söhbəti geri qurur — ziyarətçi səhifə dəyişsə də itmir. */
  function restore() {
    state.log.forEach(function (m) { bubble(m[0], m[1]); });
    if (state.step === 'service')    askService();
    else if (state.step === 'done')  { waBlock(); input.disabled = true; }
    else {
      input.placeholder = (state.step === 'phone' ? S.phPhone : S.phName) || '';
      input.setAttribute('inputmode', state.step === 'phone' ? 'tel' : 'text');
    }
    scroll();
  }

  function reset() {
    state = blank();
    log.innerHTML = '';
    input.disabled = false;
    input.value = '';
    save();
    start();
    focus();
  }

  openBtn.addEventListener('click', function () { setOpen(panel.hidden); });
  if (closeBtn)   closeBtn.addEventListener('click', function () { setOpen(false); });
  if (restartBtn) restartBtn.addEventListener('click', reset);

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && !panel.hidden) setOpen(false);
  });

  if (load() && state.log.length) restore();
})();
