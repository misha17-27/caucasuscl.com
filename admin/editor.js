/*
 * Vizual mətn redaktoru.
 *
 * Saytın məzmunu bazada HTML kimi YOX, sadə mətn formatında saxlanılır
 * (## başlıq, - punkt, **qalın** …) — belə olanda çıxışda hər parça escape
 * olunur və paneldən XSS keçmir. Bu redaktor yalnız görüntü qatıdır:
 * yüklənəndə mətni HTML-ə, saxlayanda yenidən mətnə çevirir.
 *
 * Ayrıca fayldadır ki, CSP-də script-src üçün 'unsafe-inline' lazım olmasın.
 */
(function () {
  'use strict';

  /* ---------- mətn -> HTML ---------- */

  function esc(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function inlineToHtml(s) {
    var out = esc(s);
    out = out.replace(/\[([^\]\[]{1,200})\]\(([^)\s]{1,300})\)/g, function (m, t, u) {
      return '<a href="' + u.replace(/"/g, '&quot;') + '">' + t + '</a>';
    });
    out = out.replace(/\*\*(\S(?:[^*]*\S)?)\*\*/g, '<strong>$1</strong>');
    out = out.replace(/(^|[^*])\*(\S(?:[^*]*\S)?)\*(?!\*)/g, '$1<em>$2</em>');
    return out;
  }

  function mdToHtml(text) {
    var lines = String(text || '').split(/\r\n|\r|\n/);
    var html = [], list = null;                 // list = 'ul' | 'ol' | null

    function closeList() { if (list) { html.push('</' + list + '>'); list = null; } }
    function openList(k) { if (list !== k) { closeList(); html.push('<' + k + '>'); list = k; } }

    lines.forEach(function (raw) {
      var line = raw.trim();
      if (!line) { closeList(); return; }

      if (line.indexOf('### ') === 0) { closeList(); html.push('<h3>' + inlineToHtml(line.slice(4)) + '</h3>'); return; }
      if (line.indexOf('## ')  === 0) { closeList(); html.push('<h2>' + inlineToHtml(line.slice(3)) + '</h2>'); return; }

      /* kart sətri redaktorda adi punkt kimi göstərilir, formatı qorunur */
      if (line.indexOf('* ') === 0 && line.indexOf('|') !== -1) {
        openList('ul');
        html.push('<li data-card="1">' + inlineToHtml(line.slice(2)) + '</li>');
        return;
      }
      if (line.indexOf('- ') === 0) { openList('ul'); html.push('<li>' + inlineToHtml(line.slice(2)) + '</li>'); return; }
      if (line.indexOf('+ ') === 0) { openList('ol'); html.push('<li>' + inlineToHtml(line.slice(2)) + '</li>'); return; }

      closeList();
      html.push('<p>' + inlineToHtml(line) + '</p>');
    });
    closeList();
    return html.join('');
  }

  /* ---------- HTML -> mətn ---------- */

  function inlineToMd(node) {
    var out = '';
    node.childNodes.forEach(function (n) {
      if (n.nodeType === 3) { out += n.nodeValue.replace(/\s+/g, ' '); return; }
      if (n.nodeType !== 1) return;
      var tag = n.nodeName.toLowerCase();
      var inner = inlineToMd(n);
      if (tag === 'br') { out += ' '; return; }
      if (tag === 'b' || tag === 'strong') { out += inner.trim() ? '**' + inner.trim() + '**' : ''; return; }
      if (tag === 'i' || tag === 'em')     { out += inner.trim() ? '*'  + inner.trim() + '*'  : ''; return; }
      if (tag === 'a') {
        var href = (n.getAttribute('href') || '').trim();
        out += href ? '[' + inner.trim() + '](' + href + ')' : inner;
        return;
      }
      out += inner;
    });
    return out;
  }

  function htmlToMd(root) {
    var lines = [];

    function walk(el) {
      Array.prototype.forEach.call(el.childNodes, function (n) {
        if (n.nodeType === 3) {
          var t = n.nodeValue.replace(/\s+/g, ' ').trim();
          if (t) lines.push(t);
          return;
        }
        if (n.nodeType !== 1) return;
        var tag = n.nodeName.toLowerCase();

        if (tag === 'h1' || tag === 'h2') { lines.push('## '  + inlineToMd(n).trim()); return; }
        if (tag === 'h3' || tag === 'h4' || tag === 'h5') { lines.push('### ' + inlineToMd(n).trim()); return; }

        if (tag === 'ul' || tag === 'ol') {
          var mark = tag === 'ol' ? '+ ' : '- ';
          Array.prototype.forEach.call(n.children, function (li) {
            if (li.nodeName.toLowerCase() !== 'li') return;
            var txt = inlineToMd(li).trim();
            if (!txt) return;
            /* "Başlıq | təsvir" quruluşu kart kimi saxlanılır */
            lines.push(li.getAttribute('data-card') && txt.indexOf('|') !== -1 ? '* ' + txt : mark + txt);
          });
          lines.push('');
          return;
        }

        if (tag === 'p' || tag === 'div') {
          /* içində blok varsa — dərinə gedirik */
          if (n.querySelector('p,div,ul,ol,h1,h2,h3,h4,h5')) { walk(n); return; }
          var t2 = inlineToMd(n).trim();
          if (t2) lines.push(t2);
          return;
        }
        if (tag === 'br') return;

        var t3 = inlineToMd(n).trim();
        if (t3) lines.push(t3);
      });
    }

    walk(root);

    /* artıq boş sətirləri yığışdırırıq */
    var out = [];
    lines.forEach(function (l) {
      if (l === '' && (out.length === 0 || out[out.length - 1] === '')) return;
      out.push(l);
    });
    while (out.length && out[out.length - 1] === '') out.pop();
    return out.join('\n');
  }

  /* ---------- redaktorun qurulması ---------- */

  var TOOLS = [
    ['bold',                 '<b>B</b>',  'Полужирный'],
    ['italic',               '<i>I</i>',  'Курсив'],
    ['formatBlock:h2',       'H2',        'Заголовок раздела'],
    ['formatBlock:h3',       'H3',        'Подзаголовок'],
    ['formatBlock:p',        '¶',         'Обычный абзац'],
    ['insertUnorderedList',  '• список',  'Маркированный список'],
    ['insertOrderedList',    '1. список', 'Нумерованный список'],
    ['createLink',           'ссылка',    'Вставить ссылку'],
    ['unlink',               'убрать',    'Убрать ссылку'],
    ['removeFormat',         'очистить',  'Снять форматирование']
  ];

  function build(area) {
    if (area.dataset.richReady) return;
    area.dataset.richReady = '1';

    var wrap = document.createElement('div');
    wrap.className = 'rt-wrap';

    var bar = document.createElement('div');
    bar.className = 'rt-bar';

    var edit = document.createElement('div');
    edit.className = 'rt-area';
    edit.contentEditable = 'true';
    edit.innerHTML = mdToHtml(area.value);

    function sync() { area.value = htmlToMd(edit); }

    TOOLS.forEach(function (item) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'rt-btn';
      b.innerHTML = item[1];
      b.title = item[2];
      b.addEventListener('mousedown', function (ev) {
        ev.preventDefault();
        edit.focus();
        var parts = item[0].split(':');
        if (parts[0] === 'createLink') {
          var url = window.prompt('Адрес ссылки:', 'https://');
          if (url) document.execCommand('createLink', false, url);
        } else if (parts[0] === 'formatBlock') {
          document.execCommand('formatBlock', false, parts[1]);
        } else {
          document.execCommand(parts[0], false, null);
        }
        sync();
      });
      bar.appendChild(b);
    });

    edit.addEventListener('input', sync);
    edit.addEventListener('blur', sync);

    /* yapışdırmada formatı atırıq — Word-dən gələn zibil düşməsin */
    edit.addEventListener('paste', function (ev) {
      ev.preventDefault();
      var txt = (ev.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, txt);
    });

    var form = area.closest('form');
    if (form) form.addEventListener('submit', sync);

    area.style.display = 'none';
    area.parentNode.insertBefore(wrap, area);
    wrap.appendChild(bar);
    wrap.appendChild(edit);
    wrap.appendChild(area);

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'rt-toggle';
    toggle.textContent = 'Показать исходный текст';
    toggle.addEventListener('click', function () {
      var source = area.style.display !== 'none';
      if (source) edit.innerHTML = mdToHtml(area.value);   // mətndən geri
      else        sync();                                   // redaktordan mətnə
      area.style.display = source ? 'none' : 'block';
      edit.style.display = source ? 'block' : 'none';
      bar.style.display  = source ? 'flex' : 'none';
      toggle.textContent = source ? 'Показать исходный текст' : 'Вернуться к редактору';
    });
    wrap.parentNode.insertBefore(toggle, wrap.nextSibling);
  }

  document.querySelectorAll('textarea[data-rich]').forEach(build);

  /* Sonradan əlavə olunan sahələr və yoxlama üçün açıq saxlanılır */
  window.CclEditor = { mdToHtml: mdToHtml, htmlToMd: htmlToMd, build: build };
})();
