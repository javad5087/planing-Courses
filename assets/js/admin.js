(function () {
  'use strict';

  var FA = '۰۱۲۳۴۵۶۷۸۹';
  var MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

  function fa(s) {
    return String(s).replace(/[0-9]/g, function (d) { return FA[d]; });
  }
  function en(s) {
    return String(s).replace(/[۰-۹]/g, function (d) { return FA.indexOf(d); })
      .replace(/[٠-٩]/g, function (d) { return d.charCodeAt(0) - 1632; });
  }

  /* ---------- تبدیل تاریخ ---------- */
  function g2j(gy, gm, gd) {
    var gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    var gy2 = gm > 2 ? gy + 1 : gy;
    var days = 355666 + 365 * gy + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) +
      Math.floor((gy2 + 399) / 400) + gd + gdm[gm - 1];
    var jy = -1595 + 33 * Math.floor(days / 12053);
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) { jy += Math.floor((days - 1) / 365); days = (days - 1) % 365; }
    var jm, jd;
    if (days < 186) { jm = 1 + Math.floor(days / 31); jd = 1 + (days % 31); }
    else { jm = 7 + Math.floor((days - 186) / 30); jd = 1 + ((days - 186) % 30); }
    return [jy, jm, jd];
  }
  function j2g(jy, jm, jd) {
    jy += 1595;
    var days = -355668 + 365 * jy + Math.floor(jy / 33) * 8 + Math.floor(((jy % 33) + 3) / 4) + jd +
      (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
    var gy = 400 * Math.floor(days / 146097);
    days %= 146097;
    if (days > 36524) {
      days--;
      gy += 100 * Math.floor(days / 36524);
      days %= 36524;
      if (days >= 365) days++;
    }
    gy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) { gy += Math.floor((days - 1) / 365); days = (days - 1) % 365; }
    var gd = days + 1;
    var leap = ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0) ? 29 : 28;
    var sal = [0, 31, leap, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    var gm;
    for (gm = 0; gm < 13 && gd > sal[gm]; gm++) gd -= sal[gm];
    return [gy, gm, gd];
  }
  function jMonthLen(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    var g = j2g(jy, 12, 30);
    var j = g2j(g[0], g[1], g[2]);
    return (j[0] === jy && j[1] === 12 && j[2] === 30) ? 30 : 29;
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }

  /* ---------- انتخابگر تاریخ شمسی ---------- */
  function buildJdate(el) {
    var name = el.getAttribute('data-name');
    var val = (el.getAttribute('data-value') || '').split('-').map(Number);
    if (val.length !== 3 || !val[0]) {
      var t = new Date();
      val = [t.getFullYear(), t.getMonth() + 1, t.getDate()];
    }
    var j = g2j(val[0], val[1], val[2]);

    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = name;

    var sd = document.createElement('select');
    var sm = document.createElement('select');
    var sy = document.createElement('select');
    sd.className = 'jd-d'; sm.className = 'jd-m'; sy.className = 'jd-y';
    sd.setAttribute('aria-label', 'روز'); sm.setAttribute('aria-label', 'ماه'); sy.setAttribute('aria-label', 'سال');

    var nowJ = g2j(new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate());
    for (var y = nowJ[0] - 3; y <= nowJ[0] + 3; y++) {
      var oy = document.createElement('option');
      oy.value = y; oy.textContent = fa(y);
      sy.appendChild(oy);
    }
    if (j[0] < nowJ[0] - 3 || j[0] > nowJ[0] + 3) {
      var ox = document.createElement('option');
      ox.value = j[0]; ox.textContent = fa(j[0]);
      sy.appendChild(ox);
    }
    MONTHS.forEach(function (m, i) {
      var om = document.createElement('option');
      om.value = i + 1; om.textContent = m;
      sm.appendChild(om);
    });

    function fillDays(selected) {
      var len = jMonthLen(parseInt(sy.value, 10), parseInt(sm.value, 10));
      sd.innerHTML = '';
      for (var d = 1; d <= len; d++) {
        var od = document.createElement('option');
        od.value = d; od.textContent = fa(d);
        sd.appendChild(od);
      }
      sd.value = Math.min(selected, len);
    }
    function sync() {
      var g = j2g(parseInt(sy.value, 10), parseInt(sm.value, 10), parseInt(sd.value, 10));
      hidden.value = g[0] + '-' + pad(g[1]) + '-' + pad(g[2]);
    }

    sy.value = j[0]; sm.value = j[1];
    fillDays(j[2]);
    sync();
    sy.addEventListener('change', function () { fillDays(parseInt(sd.value, 10)); sync(); });
    sm.addEventListener('change', function () { fillDays(parseInt(sd.value, 10)); sync(); });
    sd.addEventListener('change', sync);

    el.appendChild(sd); el.appendChild(sm); el.appendChild(sy); el.appendChild(hidden);
  }

  /* ---------- آکاردئون (کادرهای دروس) ---------- */
  function initAccordions() {
    var store = {};
    try { store = JSON.parse(localStorage.getItem('darsyar_boxes') || '{}'); } catch (e) { store = {}; }

    document.querySelectorAll('.postbox[data-box]').forEach(function (box) {
      var id = box.getAttribute('data-box');
      var head = box.querySelector('.postbox-head');
      var btn = box.querySelector('.toggle');
      var open = id in store ? store[id] : box.hasAttribute('data-open');
      setState(box, btn, open);
      head.addEventListener('click', function (ev) {
        if (ev.target.closest('a, form, input, select, textarea')) return;
        var now = !box.classList.contains('open');
        setState(box, btn, now);
        store[id] = now;
        try { localStorage.setItem('darsyar_boxes', JSON.stringify(store)); } catch (e) { /* ignore */ }
      });
    });

    function setState(box, btn, open) {
      box.classList.toggle('open', open);
      if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    var all = document.querySelectorAll('[data-box-all]');
    all.forEach(function (b) {
      b.addEventListener('click', function () {
        var open = b.getAttribute('data-box-all') === 'open';
        document.querySelectorAll('.postbox[data-box]').forEach(function (box) {
          setState(box, box.querySelector('.toggle'), open);
          store[box.getAttribute('data-box')] = open;
        });
        try { localStorage.setItem('darsyar_boxes', JSON.stringify(store)); } catch (e) { /* ignore */ }
      });
    });
  }

  /* ---------- اعلان‌ها، منو و ... ---------- */
  document.addEventListener('click', function (ev) {
    var d = ev.target.closest('.notice-dismiss');
    if (d) d.parentNode.remove();
    var c = ev.target.closest('[data-confirm]');
    if (c && !window.confirm(c.getAttribute('data-confirm'))) ev.preventDefault();
  });

  // پیش‌گیری از ارسال مضاعف فرم‌ها
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    var btn = ev.submitter;
    if (f.hasAttribute('data-confirm-submit') && !window.confirm(f.getAttribute('data-confirm-submit'))) {
      ev.preventDefault();
      return;
    }
    if (btn && btn.type === 'submit') {
      setTimeout(function () { btn.disabled = true; }, 0);
    }
  });

  var toggle = document.getElementById('menu-toggle');
  if (toggle) {
    toggle.addEventListener('click', function () { document.body.classList.toggle('menu-open'); });
  }

  // نمایش ارقام فارسی هنگام تایپ در فیلدهای عددی
  document.addEventListener('input', function (ev) {
    var t = ev.target;
    if (t.classList && t.classList.contains('fa-num')) {
      var pos = t.selectionStart;
      var v = en(t.value).replace(/[^0-9]/g, '');
      t.value = fa(v);
      try { t.setSelectionRange(pos, pos); } catch (e) { /* ignore */ }
    }
  });

  // انتخاب همهٔ ردیف‌ها
  document.querySelectorAll('[data-check-all]').forEach(function (master) {
    master.addEventListener('change', function () {
      document.querySelectorAll(master.getAttribute('data-check-all')).forEach(function (cb) { cb.checked = master.checked; });
    });
  });

  document.querySelectorAll('.jdate').forEach(buildJdate);
  initAccordions();
})();
