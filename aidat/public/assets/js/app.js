/* Aidat Yönetim — uygulama davranışları (Alpine.js üzerine).
   Tema, kenar çubuğu, çekmece/modal, toast, komut paleti, tutar alanı biçimleme, klavye kısayolları, grafik teması. */
(function () {
  'use strict';

  // ---- Tema: <html data-theme> ; tercih localStorage ----
  const root = document.documentElement;
  function applyTheme(pref) {
    let theme = pref;
    if (pref === 'auto' || !pref) {
      theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    root.setAttribute('data-theme', theme);
    root.setAttribute('data-theme-pref', pref || 'auto');
  }
  try { applyTheme(localStorage.getItem('aidat.theme') || 'auto'); } catch (e) { applyTheme('auto'); }
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
      let pref = 'auto';
      try { pref = localStorage.getItem('aidat.theme') || 'auto'; } catch (e) {}
      if (pref === 'auto') applyTheme('auto');
    });
  }
  window.aidatTheme = {
    set: function (pref) { try { localStorage.setItem('aidat.theme', pref); } catch (e) {} applyTheme(pref); },
    get: function () { try { return localStorage.getItem('aidat.theme') || 'auto'; } catch (e) { return 'auto'; } },
    cycle: function () { const order = ['auto', 'light', 'dark']; const cur = this.get(); this.set(order[(order.indexOf(cur) + 1) % order.length]); return this.get(); }
  };

  // ---- Tutar biçimleme (Türkçe: 1.250,50) ----
  function formatMoneyInput(value) {
    if (value === null || value === undefined) return '';
    let s = String(value).trim().replace(/[₺\s]/g, '');
    if (s === '' || s === '-') return s;
    const neg = s.startsWith('-'); s = s.replace(/^-/, '');
    let intPart = s, frac = '';
    if (s.includes(',') || s.includes('.')) {
      const lastComma = s.lastIndexOf(','), lastDot = s.lastIndexOf('.');
      const sep = lastComma > lastDot ? ',' : (lastDot > -1 && (s.length - lastDot - 1) <= 2 && s.indexOf('.') === lastDot ? '.' : (lastComma > -1 ? ',' : null));
      if (sep) { intPart = s.slice(0, s.lastIndexOf(sep)); frac = s.slice(s.lastIndexOf(sep) + 1); }
    }
    intPart = intPart.replace(/[^0-9]/g, '') || '0';
    frac = frac.replace(/[^0-9]/g, '').slice(0, 2);
    const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return (neg ? '-' : '') + grouped + (frac !== '' ? ',' + frac.padEnd(2, '0') : ',00');
  }
  window.formatMoneyInput = formatMoneyInput;
  function parseMoney(value) {
    const f = formatMoneyInput(value);
    if (!f) return 0;
    return Math.round(parseFloat(f.replace(/\./g, '').replace(',', '.')) * 100);
  }
  window.parseMoney = parseMoney;
  window.money = function (kurus, symbol) {
    const neg = kurus < 0; const abs = Math.abs(Math.round(kurus || 0));
    const tl = Math.floor(abs / 100), kr = abs % 100;
    const txt = String(tl).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + String(kr).padStart(2, '0');
    return (neg ? '-' : '') + txt + (symbol === false ? '' : ' ₺');
  };
  document.addEventListener('blur', function (e) {
    const el = e.target;
    if (el && el.matches && el.matches('input[data-money]')) { el.value = el.value.trim() === '' ? '' : formatMoneyInput(el.value); }
  }, true);

  // ---- Toast ----
  window.toast = function (message, tone) {
    const host = document.getElementById('toasts'); if (!host) return;
    const el = document.createElement('div');
    el.className = 'toast ' + (tone || 'info') + ' fade-up';
    const icon = { ok: 'check-circle', bad: 'x-octagon', warn: 'exclamation-triangle', info: 'info-circle' }[tone || 'info'];
    el.innerHTML = '<i class="bi bi-' + icon + '"></i><div>' + message + '</div><button type="button" aria-label="Kapat"><i class="bi bi-x"></i></button>';
    el.querySelector('button').addEventListener('click', function () { el.remove(); });
    host.appendChild(el);
    setTimeout(function () { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(function () { el.remove(); }, 320); }, 5200);
  };

  // ---- Alpine bileşenleri ----
  document.addEventListener('alpine:init', function () {
    Alpine.data('shell', function () {
      return {
        collapsed: (function () { try { return localStorage.getItem('aidat.sidebar') === '1'; } catch (e) { return false; } })(),
        menuOpen: false,
        cmdkOpen: false,
        themePref: window.aidatTheme.get(),
        toggleCollapse: function () { this.collapsed = !this.collapsed; try { localStorage.setItem('aidat.sidebar', this.collapsed ? '1' : '0'); } catch (e) {} },
        cycleTheme: function () { this.themePref = window.aidatTheme.cycle(); },
        themeIcon: function () { return { auto: 'bi-circle-half', light: 'bi-sun', dark: 'bi-moon-stars' }[this.themePref]; },
        themeLabel: function () { return { auto: 'Tema: sistem', light: 'Tema: açık', dark: 'Tema: koyu' }[this.themePref]; },
        init: function () {
          const self = this;
          let seq = '';
          let seqTimer = null;
          window.addEventListener('keydown', function (e) {
            const tag = (e.target && e.target.tagName) || '';
            const typing = /INPUT|TEXTAREA|SELECT/.test(tag) || (e.target && e.target.isContentEditable);
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); self.cmdkOpen = !self.cmdkOpen; return; }
            if (typing) return;
            if (e.key === 'Escape') { self.cmdkOpen = false; self.menuOpen = false; return; }
            if (e.key === '?') { window.dispatchEvent(new CustomEvent('open-shortcuts')); return; }
            // "g d", "g t" gibi sıralı kısayollar
            seq += e.key.toLowerCase();
            clearTimeout(seqTimer); seqTimer = setTimeout(function () { seq = ''; }, 900);
            const map = window.AIDAT_SHORTCUTS || {};
            if (map[seq]) { window.location.href = map[seq]; seq = ''; }
            else if (seq.length > 2) seq = seq.slice(-2);
          });
        }
      };
    });

    Alpine.data('dropdown', function () {
      return { open: false, toggle: function () { this.open = !this.open; }, close: function () { this.open = false; } };
    });

    Alpine.data('confirmForm', function (message) {
      return {
        submit: function (e) { if (!window.confirm(message || 'Bu işlemi onaylıyor musunuz?')) { e.preventDefault(); } }
      };
    });

    Alpine.data('reasonModal', function () {
      return {
        open: false, action: '', title: '', reason: '', label: '',
        ask: function (action, title, label) { this.action = action; this.title = title || 'İşlemi onayla'; this.label = label || 'Gerekçe'; this.reason = ''; this.open = true; this.$nextTick(function () { const el = document.getElementById('reason-input'); if (el) el.focus(); }); },
        submit: function () { if (this.reason.trim().length < 3) { window.toast('Gerekçe en az 3 karakter olmalı.', 'warn'); return; } this.$refs.form.action = this.action; this.$refs.form.submit(); }
      };
    });

    Alpine.data('cmdk', function (searchUrl) {
      return {
        q: '', sel: 0, results: [], loading: false, timer: null,
        pages: (window.AIDAT_PAGES || []),
        filtered: function () {
          const q = this.q.trim().toLowerCase();
          const pages = q === '' ? this.pages.slice(0, 12) : this.pages.filter(function (p) { return (p.t + ' ' + (p.k || '')).toLowerCase().includes(q); }).slice(0, 8);
          return pages.map(function (p) { return { t: p.t, u: p.u, i: p.i || 'arrow-right-short', g: 'Sayfalar', k: p.key || '' }; }).concat(this.results);
        },
        search: function () {
          const self = this; clearTimeout(this.timer); this.sel = 0;
          const q = this.q.trim();
          if (q.length < 2 || !searchUrl) { this.results = []; return; }
          this.timer = setTimeout(function () {
            self.loading = true;
            fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
              .then(function (r) { return r.ok ? r.json() : { sonuclar: [] }; })
              .then(function (d) { self.results = (d.sonuclar || []).map(function (r) { return { t: r.t, u: r.u, i: r.i, g: r.g, k: r.k || '' }; }); })
              .catch(function () { self.results = []; })
              .finally(function () { self.loading = false; });
          }, 180);
        },
        move: function (d) { const n = this.filtered().length; if (!n) return; this.sel = (this.sel + d + n) % n; },
        go: function () { const item = this.filtered()[this.sel]; if (item) window.location.href = item.u; }
      };
    });

    // Tahsilat formu: açık borç eşleştirme önizlemesi
    Alpine.data('allocator', function (charges, initialMode) {
      return {
        mode: initialMode || 'eski',
        amount: '',
        charges: charges || [],
        manual: {},
        init: function () { const self = this; this.charges.forEach(function (c) { self.manual[c.id] = ''; }); },
        amountKurus: function () { return parseMoney(this.amount); },
        preview: function () {
          const total = this.amountKurus(); let rest = total; const out = {};
          if (this.mode === 'avans') return { out: out, rest: total };
          if (this.mode === 'manuel') {
            const self = this;
            this.charges.forEach(function (c) { const v = parseMoney(self.manual[c.id]); if (v > 0) { out[c.id] = Math.min(v, c.open); } });
            const used = Object.values(out).reduce(function (a, b) { return a + b; }, 0);
            return { out: out, rest: total - used };
          }
          for (const c of this.charges) { if (rest <= 0) break; const a = Math.min(rest, c.open); if (a > 0) { out[c.id] = a; rest -= a; } }
          return { out: out, rest: rest };
        },
        allocated: function (id) { const p = this.preview(); return p.out[id] || 0; },
        remaining: function () { return this.preview().rest; },
        manualTotal: function () { return Object.values(this.preview().out).reduce(function (a, b) { return a + b; }, 0); },
        fmt: function (k) { return window.money(k); }
      };
    });

    // Toplu tahakkuk önizleme: dağıtım hesaplama (sunucu ile aynı kural: yuvarlama farkı son satıra)
    Alpine.data('planPreview', function (units) {
      return {
        distribution: 'esit', total: '', unitAmount: '', included: {}, groupAmounts: {}, manual: {},
        units: units || [],
        init: function () { const self = this; this.units.forEach(function (u) { self.included[u.id] = true; self.manual[u.id] = ''; }); },
        activeUnits: function () { const self = this; return this.units.filter(function (u) { return self.included[u.id]; }); },
        weights: function () {
          const self = this;
          return this.activeUnits().map(function (u) {
            if (self.distribution === 'm2') return u.m2 || 0;
            if (self.distribution === 'arsa_payi') return u.land || 0;
            return 1;
          });
        },
        rows: function () {
          const self = this; const act = this.activeUnits(); const out = {};
          if (this.distribution === 'sabit') { act.forEach(function (u) { out[u.id] = parseMoney(self.unitAmount); }); return out; }
          if (this.distribution === 'manuel') { act.forEach(function (u) { out[u.id] = parseMoney(self.manual[u.id]); }); return out; }
          if (this.distribution === 'grup') { act.forEach(function (u) { out[u.id] = parseMoney(self.groupAmounts[u.group || 0] || ''); }); return out; }
          const total = parseMoney(this.total); const w = this.weights(); const sum = w.reduce(function (a, b) { return a + b; }, 0);
          if (sum <= 0) { act.forEach(function (u) { out[u.id] = 0; }); return out; }
          let alloc = 0;
          act.forEach(function (u, i) {
            if (i === act.length - 1) { out[u.id] = total - alloc; }
            else { const s = Math.floor(total * (w[i] / sum)); out[u.id] = s; alloc += s; }
          });
          return out;
        },
        sum: function () { return Object.values(this.rows()).reduce(function (a, b) { return a + b; }, 0); },
        fmt: function (k) { return window.money(k); }
      };
    });
  });

  // ---- Flash → toast ----
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-flash]').forEach(function (el) { window.toast(el.getAttribute('data-flash'), el.getAttribute('data-tone') || 'info'); el.remove(); });
    // Satır bağlantısı: tr[data-href]
    document.querySelectorAll('tr[data-href]').forEach(function (tr) {
      tr.classList.add('is-link');
      tr.addEventListener('click', function (e) { if (e.target.closest('a, button, input, select, label, form')) return; window.location.href = tr.getAttribute('data-href'); });
    });
    // Filtre formları: select değişince gönder
    document.querySelectorAll('form[data-autosubmit] select, form[data-autosubmit] input[type=date], form[data-autosubmit] input[type=month]').forEach(function (el) {
      el.addEventListener('change', function () { el.form.submit(); });
    });
    // Toplu seçim
    document.querySelectorAll('[data-check-all]').forEach(function (master) {
      const scope = master.closest('table') || document;
      master.addEventListener('change', function () { scope.querySelectorAll('input[data-check-item]').forEach(function (c) { c.checked = master.checked; c.dispatchEvent(new Event('change', { bubbles: true })); }); });
    });
    // Yazdır düğmesi
    document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
    // Grafik varsayılanları
    if (window.Chart) {
      const cs = getComputedStyle(root);
      Chart.defaults.font.family = cs.getPropertyValue('--font-sans').trim() || 'IBM Plex Sans';
      Chart.defaults.font.size = 12;
      Chart.defaults.color = cs.getPropertyValue('--ink-3').trim();
      Chart.defaults.borderColor = cs.getPropertyValue('--rule').trim();
      Chart.defaults.plugins.legend.labels.boxWidth = 10;
      Chart.defaults.plugins.legend.labels.boxHeight = 10;
      Chart.defaults.plugins.tooltip.backgroundColor = cs.getPropertyValue('--ink').trim();
      Chart.defaults.plugins.tooltip.titleColor = cs.getPropertyValue('--on-primary').trim();
      Chart.defaults.plugins.tooltip.bodyColor = cs.getPropertyValue('--on-primary').trim();
      Chart.defaults.plugins.tooltip.cornerRadius = 6;
      Chart.defaults.plugins.tooltip.padding = 10;
      Chart.defaults.elements.bar.borderRadius = 3;
      Chart.defaults.elements.line.tension = 0.35;
      Chart.defaults.elements.point.radius = 2.5;
      window.aidatChartColors = {
        ink: cs.getPropertyValue('--ink').trim(), moss: cs.getPropertyValue('--moss').trim(), clay: cs.getPropertyValue('--clay').trim(),
        amber: cs.getPropertyValue('--amber').trim(), slate: cs.getPropertyValue('--slate').trim(), ink4: cs.getPropertyValue('--ink-4').trim(),
        mossBg: cs.getPropertyValue('--moss-bg').trim(), clayBg: cs.getPropertyValue('--clay-bg').trim(), amberBg: cs.getPropertyValue('--amber-bg').trim()
      };
      document.querySelectorAll('canvas[data-chart]').forEach(function (cv) {
        try {
          const cfg = JSON.parse(cv.getAttribute('data-chart'));
          const palette = [window.aidatChartColors.ink, window.aidatChartColors.moss, window.aidatChartColors.clay, window.aidatChartColors.amber, window.aidatChartColors.slate, window.aidatChartColors.ink4];
          (cfg.data.datasets || []).forEach(function (ds, i) {
            if (ds.color) { const c = window.aidatChartColors[ds.color] || ds.color; ds.borderColor = ds.borderColor || c; ds.backgroundColor = ds.backgroundColor || (cfg.type === 'line' ? c + '22' : c); delete ds.color; }
            else if (!ds.backgroundColor) { ds.backgroundColor = cfg.type === 'doughnut' || cfg.type === 'pie' ? palette : palette[i % palette.length]; ds.borderColor = cfg.type === 'doughnut' || cfg.type === 'pie' ? cs.getPropertyValue('--surface').trim() : palette[i % palette.length]; }
          });
          cfg.options = cfg.options || {}; cfg.options.maintainAspectRatio = false; cfg.options.responsive = true;
          if (cfg.money) {
            cfg.options.scales = cfg.options.scales || {};
            if (cfg.type !== 'doughnut' && cfg.type !== 'pie') { cfg.options.scales.y = Object.assign({ ticks: { callback: function (v) { return window.money(v, false); } }, grid: { color: cs.getPropertyValue('--rule-soft').trim() } }, cfg.options.scales.y || {}); cfg.options.scales.x = Object.assign({ grid: { display: false } }, cfg.options.scales.x || {}); }
            cfg.options.plugins = cfg.options.plugins || {};
            cfg.options.plugins.tooltip = Object.assign({ callbacks: { label: function (c) { return (c.dataset.label ? c.dataset.label + ': ' : '') + window.money(c.parsed.y !== undefined && c.parsed.y !== null ? c.parsed.y : c.parsed); } } }, cfg.options.plugins.tooltip || {});
          }
          new Chart(cv.getContext('2d'), cfg);
        } catch (err) { console.warn('chart', err); }
      });
    }
    // QR (makbuz doğrulama)
    if (window.qrcode) {
      document.querySelectorAll('[data-qr]').forEach(function (el) {
        try { const q = qrcode(0, 'M'); q.addData(el.getAttribute('data-qr')); q.make(); el.innerHTML = q.createSvgTag({ cellSize: 3, margin: 0, scalable: true }); } catch (e) {}
      });
    }
  });
})();
