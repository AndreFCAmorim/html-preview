<?php
declare(strict_types=1);
require __DIR__ . '/inc.php';

session_start();
$token = viewer_token();
session_write_close();

header('Cache-Control: no-store, max-age=0');
header('X-Frame-Options: SAMEORIGIN');
header('X-Robots-Tag: noindex, nofollow');

$examples = list_examples();
$current  = (string) ($_GET['p'] ?? '');
if (!isset($examples[$current])) {
    $current = (string) (array_key_first($examples) ?? '');
}

$device = (string) ($_GET['d'] ?? '');
if (!in_array($device, ['desktop', 'tablet', 'mobile'], true)) {
    $device = '';
}

$viewerData = [
    'token'    => $token,
    'current'  => $current,
    'device'   => $device,
    'examples' => array_values(array_map(fn($x) => ['id' => $x['id'], 'title' => $x['title']], $examples)),
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= e($examples[$current]['title'] ?? 'Presentation') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --chrome: #000000;
    --chrome-raised: #1A1A1A;
    --stage: #000000;
    --line: #2A2A2A;
    --text: #E8ECF2;
    --muted: #9A9A9A;
    --accent: #8FB0FF;
    --header-h: 56px;
    --ease: cubic-bezier(.65, 0, .35, 1);
    color-scheme: dark;
  }
  * { box-sizing: border-box; }
  html, body {
    height: 100%; margin: 0; overflow: hidden;
    background: var(--stage); color: var(--text);
    font: 500 14px/1.4 Figtree, system-ui, -apple-system, "Segoe UI", sans-serif;
    -webkit-user-select: none; user-select: none; -webkit-touch-callout: none;
  }
  body { display: flex; flex-direction: column; padding-bottom: env(safe-area-inset-bottom, 0px); }

  /* Header */
  .bar {
    position: relative; z-index: 3; flex: none;
    height: calc(var(--header-h) + env(safe-area-inset-top, 0px));
    padding: env(safe-area-inset-top, 0px) 16px 0;
    display: flex; align-items: center; gap: 12px;
    background: var(--chrome); border-bottom: 1px solid var(--line);
    transition: transform .35s var(--ease), opacity .35s var(--ease);
  }
  .logo { display: block; height: 24px; width: auto; flex: none; }
  .switch { position: relative; min-width: 0; flex: 0 1 420px; }
  .switch select {
    appearance: none; -webkit-appearance: none; width: 100%;
    font: inherit; font-size: 15px; font-weight: 600; color: var(--text);
    background: var(--chrome-raised); border: 1px solid var(--line); border-radius: 8px;
    padding: 8px 36px 8px 12px; cursor: pointer;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .switch::after {
    content: ""; position: absolute; right: 14px; top: 50%; width: 7px; height: 7px;
    border-right: 2px solid var(--muted); border-bottom: 2px solid var(--muted);
    transform: translateY(-70%) rotate(45deg); pointer-events: none;
  }
  .switch .solo { font-size: 15px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
  .spacer { flex: 1; }
  .count { color: var(--muted); font-variant-numeric: tabular-nums; white-space: nowrap; }
  .count b { color: var(--text); font-weight: 600; }
  .btn {
    width: 36px; height: 36px; flex: none; display: grid; place-items: center;
    background: transparent; color: var(--text); border: 1px solid var(--line); border-radius: 8px;
    cursor: pointer; transition: background .15s;
  }
  .btn:hover:not(:disabled) { background: var(--chrome-raised); }
  .btn:disabled { opacity: .35; cursor: default; }
  .btn svg { width: 16px; height: 16px; }
  :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

  .progress { position: absolute; left: 0; bottom: -1px; height: 2px; width: 100%; background: transparent; }
  .progress i { display: block; height: 100%; width: 0; background: var(--accent); transition: width .5s var(--ease); }

  /* Stage */
  .stage { position: relative; flex: 1; min-height: 0; overflow: hidden; }
  .device { position: absolute; inset: 0; transition: opacity .18s var(--ease); }
  .device.switching { opacity: 0; }
  .device iframe {
    position: absolute; inset: 0; width: 100%; height: 100%; border: 0; background: #fff;
    pointer-events: none;            /* nothing inside can be clicked, hovered or selected */
    opacity: 1; transition: opacity .3s var(--ease);
  }
  .device iframe.loading { opacity: 0; }

  /* Tablet and phone previews: real device viewport, scaled down to fit */
  body[data-device="tablet"] .device,
  body[data-device="mobile"] .device {
    inset: auto; left: 50%; top: 50%; transform-origin: center center;
    border-radius: var(--radius); overflow: hidden; background: #fff;
    box-shadow: 0 0 0 var(--bezel) #000000, 0 0 0 calc(var(--bezel) + 1px) #333333, 0 30px 80px rgba(0,0,0,.45);
  }
  body[data-device="tablet"] .device { --radius: 22px; --bezel: 14px; }
  body[data-device="mobile"] .device { --radius: 40px; --bezel: 11px; }

  .devices { display: flex; gap: 2px; padding: 2px; border: 1px solid var(--line); border-radius: 9px; flex: none; }
  .devices .dev {
    width: 34px; height: 30px; display: grid; place-items: center; cursor: pointer;
    background: transparent; color: var(--muted); border: 0; border-radius: 6px; transition: background .15s, color .15s;
  }
  .devices .dev:hover { color: var(--text); }
  .devices .dev[aria-pressed="true"] { background: var(--chrome-raised); color: var(--text); }
  .devices .dev svg { width: 17px; height: 17px; }
  .shield { position: absolute; inset: 0; z-index: 2; cursor: default; touch-action: none; }

  .empty { margin: auto; max-width: 36ch; text-align: center; color: var(--muted); font-size: 16px; line-height: 1.6; }
  .empty code { color: var(--text); }

  /* Fullscreen: header tucks away, comes back when the pointer nears the top */
  body.fs .bar { position: absolute; left: 0; right: 0; top: 0; transform: translateY(-100%); opacity: 0; }
  body.fs.show-bar .bar { transform: none; opacity: 1; }

  @media (max-width: 560px) {
    .count .word { display: none; }
    .bar { gap: 8px; padding-left: 10px; padding-right: 10px; }
    #fsBtn { display: none; }
    .devices .dev { width: 30px; }
  }
  @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
  @media print { html { display: none !important; } }
</style>
</head>
<body>
<?php if (!$examples): ?>
  <p class="empty">Add <code>.html</code> files to the <code>slides</code> folder to show them here.</p>
<?php else: ?>
  <header class="bar">
    <img class="logo" src="assets/logo.webp" alt="AFCA">
    <div class="switch">
      <?php if (count($examples) > 1): ?>
        <select id="picker" aria-label="Choose example">
          <?php foreach ($examples as $x): ?>
            <option value="<?= e($x['id']) ?>"<?= $x['id'] === $current ? ' selected' : '' ?>><?= e($x['title']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <span class="solo"><?= e($examples[$current]['title']) ?></span>
      <?php endif; ?>
    </div>
    <div class="spacer"></div>
    <div class="devices" role="group" aria-label="Preview size">
      <button class="dev" data-device="desktop" aria-label="Desktop view" title="Desktop (D)">
        <svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="1.5" y="2.5" width="15" height="10" rx="1.5"/><path d="M6 15.5h6M9 12.5v3"/></svg>
      </button>
      <button class="dev" data-device="tablet" aria-label="Tablet view" title="Tablet (T)">
        <svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="1.5" width="12" height="15" rx="1.8"/><path d="M8 13.8h2"/></svg>
      </button>
      <button class="dev" data-device="mobile" aria-label="Phone view" title="Phone (M)">
        <svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="1.5" width="8" height="15" rx="1.8"/><path d="M8.2 13.8h1.6"/></svg>
      </button>
    </div>
    <span class="count" aria-live="polite"><span class="word">Page </span><b id="pageNo">1</b> of <span id="pageCount">1</span></span>
    <button class="btn" id="prevBtn" aria-label="Previous page" disabled>
      <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10l4-4 4 4"/></svg>
    </button>
    <button class="btn" id="nextBtn" aria-label="Next page">
      <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
    </button>
    <button class="btn" id="fsBtn" aria-label="Full screen">
      <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 6V2h4M14 6V2h-4M2 10v4h4M14 10v4h-4"/></svg>
    </button>
    <div class="progress"><i id="progress"></i></div>
  </header>

  <main class="stage" id="stage">
    <div class="device" id="device">
      <iframe id="frame" class="loading" tabindex="-1" title="Presentation" aria-hidden="true"></iframe>
    </div>
    <div class="shield" id="shield"></div>
  </main>

  <script>
  (function () {
    const DATA = <?= json_encode($viewerData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const frame   = document.getElementById('frame');
    const shield  = document.getElementById('shield');
    const picker  = document.getElementById('picker');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const fsBtn   = document.getElementById('fsBtn');
    const pageNo  = document.getElementById('pageNo');
    const pageCt  = document.getElementById('pageCount');
    const bar     = document.getElementById('progress');
    const reduce  = matchMedia('(prefers-reduced-motion: reduce)').matches;

    let current = DATA.current, page = 0, pages = 1, anim = null, observer = null;

    /* ---------- Loading an example ---------- */
    function srcFor(id) {
      return 'view.php?id=' + encodeURIComponent(id) + '&t=' + encodeURIComponent(DATA.token);
    }
    function load(id) {
      current = id;
      const ex = DATA.examples.find(x => x.id === id);
      if (ex) document.title = ex.title;
      syncUrl();
      frame.classList.add('loading');
      setTimeout(() => { frame.src = srcFor(id); }, reduce ? 0 : 200);
    }
    // The frame first loads an encrypted loader; once it has decrypted and written
    // the real page it calls __viewerReady, and that's when the page is shown.
    window.__viewerReady = () => {
      page = 0;
      try {
        const w = frame.contentWindow, d = frame.contentDocument;
        w.scrollTo(0, 0);
        if (observer) observer.disconnect();
        observer = new w.ResizeObserver(() => measure(true));   // images/fonts finishing late
        observer.observe(d.body || d.documentElement);
        w.addEventListener('load', () => measure(true));
      } catch (_) {}
      measure(true);
      frame.classList.remove('loading');
    };

    /* ---------- Paging ----------
       Pages ("stops") follow the page's own structure:
       - [data-slide] elements, if the HTML has any, are the pages.
       - Pinned sections (a sticky child inside a taller parent) are stepped through
         evenly, about one step per screen, so scroll-driven animations play.
       - Elsewhere each step moves up to one screen, landing on the start of the
         furthest section that fits, so nothing is skipped or cut mid-section. */
    let stops = [0];

    function computeStops(w, d) {
      const vh = w.innerHeight, sy = w.scrollY;
      const se = d.scrollingElement || d.documentElement;
      const max = Math.max(0, se.scrollHeight - vh);
      const top = el => el.getBoundingClientRect().top + sy;
      const fit = v => Math.max(0, Math.min(max, Math.round(v)));
      const tidy = arr => {
        const out = [];
        arr.sort((x, y) => x - y).forEach(v => { if (!out.length || v - out[out.length - 1] > 4) out.push(v); });
        if (out.length > 1 && max - out[out.length - 1] <= 4) out[out.length - 1] = max;
        return out;
      };

      const marked = d.querySelectorAll('[data-slide]');
      if (marked.length) return tidy([0, ...[...marked].map(el => fit(top(el)))]);

      const style = el => w.getComputedStyle(el);
      const pins = [];
      d.querySelectorAll('body *').forEach(el => {
        if (style(el).position !== 'sticky' || el.offsetHeight < vh * 0.6 || !el.parentElement) return;
        const par = el.parentElement, a = fit(top(par)), b = fit(top(par) + par.offsetHeight - vh);
        if (b - a > vh * 0.3) pins.push({ a, b, el });
      });
      const pinStops = p => {
        const n = Math.max(1, Math.round((p.b - p.a) / vh));
        return Array.from({ length: n + 1 }, (_, i) => Math.round(p.a + (p.b - p.a) * i / n));
      };

      const anchors = pins.map(p => p.a);
      d.querySelectorAll('body > *, main > *, section, header, footer, article').forEach(el => {
        const r = el.getBoundingClientRect(), pos = style(el).position;
        if (r.height < 40 || r.width < w.innerWidth * 0.5) return;
        if (pos === 'fixed' || pos === 'sticky' || pos === 'absolute') return;
        if (pins.some(p => p.el.contains(el))) return;
        const t = fit(r.top + sy);
        if (pins.some(p => t > p.a + 4 && t < p.b)) return;
        anchors.push(t);
      });

      const out = [0];
      let pos = 0, guard = 0;
      while (pos < max - 4 && guard++ < 500) {
        const lo = pos + vh * 0.25, hi = pos + vh * 1.06; // a few % of overshoot is usually just padding
        const pin = pins.find(p => pos >= p.a - 2 && pos < p.b - 2);
        let next;
        if (pin) {
          next = pinStops(pin).find(s => s > pos + 2);
        } else {
          const fits = anchors.filter(t => t > lo && t <= hi);
          next = fits.length ? Math.max(...fits) : pos + vh;
          const ahead = pins.filter(p => p.a > pos + 2 && p.a < next).sort((x, y) => x.a - y.a)[0];
          if (ahead) next = ahead.a <= lo ? pinStops(ahead).find(s => s > lo) : ahead.a;
        }
        next = fit(next === undefined ? max : next);
        if (next <= pos) break;
        out.push(next); pos = next;
      }
      return tidy(out);
    }

    function measure(snap) {
      let w;
      try { w = frame.contentWindow; stops = computeStops(w, frame.contentDocument); } catch (_) { return; }
      pages = stops.length;
      if (page > pages - 1) page = pages - 1;
      if (snap && !anim) w.scrollTo(0, stops[page]);
      ui();
    }

    function go(p) {
      let w;
      try { w = frame.contentWindow; } catch (_) { return; }
      p = Math.max(0, Math.min(pages - 1, p));
      if (p === page && !anim) return;
      page = p; ui();

      const from = w.scrollY, to = stops[p], dur = reduce ? 0 : 650;
      if (anim) cancelAnimationFrame(anim.raf);
      if (!dur) { w.scrollTo(0, to); anim = null; return; }
      const start = performance.now();
      const ease = t => t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
      anim = {};
      (function step(now) {
        const t = Math.min(1, (now - start) / dur);
        w.scrollTo(0, from + (to - from) * ease(t));
        if (t < 1) anim.raf = requestAnimationFrame(step); else anim = null;
      })(start);
    }
    const next = () => go(page + 1), prev = () => go(page - 1);

    function ui() {
      pageNo.textContent = page + 1;
      pageCt.textContent = pages;
      prevBtn.disabled = page === 0;
      nextBtn.disabled = page >= pages - 1;
      bar.style.width = (pages > 1 ? (page / (pages - 1)) * 100 : 100) + '%';
    }

    /* ---------- Input: wheel, touch, keys ---------- */
    // Locked while the page animation runs. Afterwards, trackpad inertia (a decaying tail of
    // events) is ignored; a new gesture is a pause between events or a rising delta.
    let lastAbs = 0, lastTime = 0;
    shield.addEventListener('wheel', e => {
      e.preventDefault();
      const abs = Math.abs(e.deltaY);
      const fresh = e.timeStamp - lastTime > 120 || abs > lastAbs;
      lastAbs = abs; lastTime = e.timeStamp;
      if (anim || !fresh || abs < 6) return;
      e.deltaY > 0 ? next() : prev();
    }, { passive: false });

    let touchY = null;
    shield.addEventListener('touchstart', e => { touchY = e.touches[0].clientY; }, { passive: true });
    shield.addEventListener('touchmove',  e => e.preventDefault(), { passive: false });
    shield.addEventListener('touchend', e => {
      if (touchY === null) return;
      const dy = touchY - e.changedTouches[0].clientY; touchY = null;
      if (Math.abs(dy) > 40) dy > 0 ? next() : prev();
    });

    function onKey(e) {
      const k = e.key, mod = e.ctrlKey || e.metaKey;
      // Block view-source, save, print, select-all, copy, devtools shortcuts
      if (k === 'F12' || (mod && /^[uspac]$/i.test(k)) || (mod && e.shiftKey && /^[ijc]$/i.test(k)) || (e.metaKey && e.altKey && /^[iju]$/i.test(k))) {
        e.preventDefault(); return;
      }
      if (e.target === picker) return;            // let the dropdown use its own arrow keys
      if (['ArrowDown', 'ArrowRight', 'PageDown', ' ', 'Enter'].includes(k)) { e.preventDefault(); next(); }
      else if (['ArrowUp', 'ArrowLeft', 'PageUp'].includes(k)) { e.preventDefault(); prev(); }
      else if (k === 'Home') { e.preventDefault(); go(0); }
      else if (k === 'End')  { e.preventDefault(); go(pages - 1); }
      else if (k === 'f' || k === 'F') { toggleFs(); }
      else if (!mod && /^[dtm]$/i.test(k)) { setDevice({ d: 'desktop', t: 'tablet', m: 'mobile' }[k.toLowerCase()]); }
    }
    document.addEventListener('keydown', onKey, true);
    window.__viewerKey = onKey;

    ['contextmenu', 'selectstart', 'dragstart', 'copy', 'cut'].forEach(t =>
      document.addEventListener(t, e => { if (e.target !== picker) e.preventDefault(); }, true));

    /* ---------- Header controls ---------- */
    prevBtn.addEventListener('click', prev);
    nextBtn.addEventListener('click', next);
    if (picker) picker.addEventListener('change', () => { load(picker.value); picker.blur(); });

    function toggleFs() {
      if (document.fullscreenElement) document.exitFullscreen();
      else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen();
    }
    fsBtn.addEventListener('click', toggleFs);
    document.addEventListener('fullscreenchange', () => {
      document.body.classList.toggle('fs', !!document.fullscreenElement);
      setTimeout(() => { const r = scrollRatio(); layout(); refit(r); }, 350);
    });
    document.addEventListener('mousemove', e => {
      document.body.classList.toggle('show-bar', e.clientY < 70);
    });

    /* ---------- Responsive preview ---------- */
    const DEVICES = { desktop: null, tablet: { w: 820, h: 1180 }, mobile: { w: 390, h: 844 } };
    const stageEl = document.getElementById('stage');
    const box = document.getElementById('device');
    const devBtns = document.querySelectorAll('.devices .dev');
    let device = DATA.device || (() => { try { return localStorage.getItem('viewer-device'); } catch (_) { return null; } })();
    if (!(device in DEVICES)) device = 'desktop';

    function syncUrl() {
      const q = '?p=' + encodeURIComponent(current) + (device !== 'desktop' ? '&d=' + device : '');
      try { history.replaceState(null, '', q); } catch (_) {}
    }

    function layout() {
      document.body.dataset.device = device;
      devBtns.forEach(b => b.setAttribute('aria-pressed', String(b.dataset.device === device)));
      const size = DEVICES[device];
      if (!size) { box.style.width = box.style.height = box.style.transform = ''; return; }
      const pad = 36;
      const s = Math.min(1, (stageEl.clientWidth - pad * 2) / size.w, (stageEl.clientHeight - pad * 2) / size.h);
      box.style.width = size.w + 'px';
      box.style.height = size.h + 'px';
      box.style.transform = 'translate(-50%, -50%) scale(' + Math.max(0.1, s).toFixed(4) + ')';
    }

    // Keep the viewer on roughly the same content when the layout changes size.
    function scrollRatio() {
      try {
        const w = frame.contentWindow, d = frame.contentDocument;
        const max = (d.scrollingElement || d.documentElement).scrollHeight - w.innerHeight;
        return max > 0 ? w.scrollY / max : 0;
      } catch (_) { return 0; }
    }
    function refit(ratio) {
      try {
        const w = frame.contentWindow;
        stops = computeStops(w, frame.contentDocument);
        pages = stops.length;
        const target = ratio * stops[pages - 1];
        page = stops.reduce((best, v, i) => Math.abs(v - target) < Math.abs(stops[best] - target) ? i : best, 0);
        w.scrollTo(0, stops[page]);
        ui();
      } catch (_) {}
    }

    function setDevice(name) {
      if (!(name in DEVICES) || name === device) return;
      const ratio = scrollRatio();
      device = name;
      try { localStorage.setItem('viewer-device', name); } catch (_) {}
      syncUrl();
      box.classList.add('switching');
      setTimeout(() => {
        if (anim) { cancelAnimationFrame(anim.raf); anim = null; }
        layout();
        requestAnimationFrame(() => requestAnimationFrame(() => {
          refit(ratio);
          setTimeout(() => { refit(ratio); box.classList.remove('switching'); }, 120);
        }));
      }, reduce ? 0 : 180);
    }
    devBtns.forEach(b => b.addEventListener('click', () => { setDevice(b.dataset.device); b.blur(); }));

    let resizeTimer;
    addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => { const r = scrollRatio(); layout(); refit(r); }, 120);
    });
    layout();

    load(current);
  })();
  </script>
<?php endif; ?>
</body>
</html>
