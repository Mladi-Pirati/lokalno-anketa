(function () {
  'use strict';

  var stage = document.getElementById('map-stage');
  var host = document.getElementById('map-svg');
  var empty = document.getElementById('map-empty');
  var cfgEl = document.getElementById('picker-config');
  if (!stage || !host || !cfgEl) return;

  var cfg = {};
  try { cfg = JSON.parse(cfgEl.textContent); } catch (e) {}
  var muniBase = cfg.muniBase || '/obcina';
  function muniUrl(slug) { return muniBase + '/' + slug; }

  var title = document.getElementById('map-title');
  var backBtn = document.getElementById('map-back');

  // floating name label shown on hover
  var tip = document.createElement('div');
  tip.className = 'map-tip';
  stage.appendChild(tip);
  function showTip(ev, text) {
    var r = stage.getBoundingClientRect();
    tip.textContent = text;
    tip.style.left = (ev.clientX - r.left) + 'px';
    tip.style.top = (ev.clientY - r.top) + 'px';
    tip.classList.add('show');
  }
  function hideTip() { tip.classList.remove('show'); }

  var SVG_NS = 'http://www.w3.org/2000/svg';
  function el(tag, attrs) {
    var n = document.createElementNS(SVG_NS, tag);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    return n;
  }

  var state = { map: null, regions: {}, groups: {}, svg: null, fullVB: null, activeRegion: null };

  if (stage.getAttribute('data-has-geometry') === '1') {
    if (empty) empty.textContent = 'Zemljevid se nalaga …';
    fetch('/data/slovenia_map.json', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(render)
      .catch(function (error) {
        console.error('Map failed to load', error);
        if (empty) empty.textContent = 'Zemljevida ni bilo mogoče naložiti.';
      });
  } else if (empty) {
    empty.textContent = 'Zemljevid ni na voljo.';
  }

  function growBBox(bb, d) {
    var nums = d.match(/-?\d+(?:\.\d+)?/g);
    if (!nums) return;
    for (var i = 0; i + 1 < nums.length; i += 2) {
      var x = +nums[i], y = +nums[i + 1];
      if (x < bb[0]) bb[0] = x;
      if (y < bb[1]) bb[1] = y;
      if (x > bb[2]) bb[2] = x;
      if (y > bb[3]) bb[3] = y;
    }
  }

  // distinct-but-harmonised palette that reads well on the near-black background
  var PALETTE = [
    '#e0684b', '#e2a33c', '#cbbf49', '#8fb85a', '#4fb08a', '#3fa9b5',
    '#5b8fd6', '#7d78d6', '#a874d0', '#d167ad', '#d85f7e', '#9a8b7a'
  ];

  function render(map) {
    state.map = map;
    state.fullVB = [0, 0, map.width, map.height];
    var root = el('svg', { viewBox: map.viewBox, role: 'img', 'aria-label': 'Zemljevid občin Slovenije' });

    var regionMeta = {};
    var colorBySlug = {};
    (map.regions || []).forEach(function (r, i) {
      regionMeta[r.slug] = { name: r.name, munis: [], bbox: [Infinity, Infinity, -Infinity, -Infinity] };
      colorBySlug[r.slug] = PALETTE[i % PALETTE.length];
    });
    (map.municipalities || []).forEach(function (m) {
      var meta = regionMeta[m.region_slug];
      if (meta) meta.munis.push(m);
    });

    Object.keys(regionMeta).forEach(function (slug) {
      var meta = regionMeta[slug];
      var g = el('g', { class: 'region', 'data-region': slug });
      g.style.setProperty('--rc', colorBySlug[slug]);
      var cx = 0, cy = 0, n = 0;
      meta.munis.forEach(function (m) {
        if (!m.path) return;
        var p = el('path', { d: m.path, class: 'muni', 'data-muni': m.slug });
        var t = el('title', {}); t.textContent = m.name; p.appendChild(t);
        growBBox(meta.bbox, m.path);
        if (m.centroid) { cx += m.centroid[0]; cy += m.centroid[1]; n++; }
        // STEP 2: single click on a municipality → straight to the form
        p.addEventListener('click', function (ev) {
          if (stage.getAttribute('data-mode') !== 'munis') return;
          ev.stopPropagation();
          window.location.href = muniUrl(m.slug);
        });
        // hover: municipality name in municipality mode, region name otherwise
        p.addEventListener('mousemove', function (ev) {
          showTip(ev, stage.getAttribute('data-mode') === 'munis' ? m.name : regionMeta[slug].name);
        });
        p.addEventListener('mouseleave', hideTip);
        g.appendChild(p);
      });
      // STEP 1: single click on a region → zoom in
      g.addEventListener('click', function () {
        if (stage.getAttribute('data-mode') === 'regions') enterRegion(slug);
      });
      meta.cx = cx; meta.cy = cy; meta.n = n;
      state.groups[slug] = g;
      root.appendChild(g);
    });

    var labels = el('g', { class: 'labels' });
    Object.keys(regionMeta).forEach(function (slug) {
      var meta = regionMeta[slug];
      if (!meta.n) return;
      var t = el('text', { class: 'region-label', x: (meta.cx / meta.n).toFixed(0), y: (meta.cy / meta.n).toFixed(0) });
      t.textContent = meta.name;
      labels.appendChild(t);
    });
    root.appendChild(labels);

    state.regions = regionMeta;
    state.svg = root;
    host.innerHTML = '';
    host.appendChild(root);
    host.classList.remove('hidden');
    if (empty) empty.classList.add('hidden');
  }

  function tweenViewBox(root, from, to, ms) {
    var start = null;
    function step(ts) {
      if (start === null) start = ts;
      var t = Math.min(1, (ts - start) / ms);
      var e = t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
      var vb = [
        from[0] + (to[0] - from[0]) * e, from[1] + (to[1] - from[1]) * e,
        from[2] + (to[2] - from[2]) * e, from[3] + (to[3] - from[3]) * e
      ];
      root.setAttribute('viewBox', vb.map(function (v) { return v.toFixed(1); }).join(' '));
      if (t < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  function currentVB(root) { return root.getAttribute('viewBox').split(/\s+/).map(Number); }

  function enterRegion(slug) {
    var meta = state.regions[slug];
    if (!meta || !meta.munis.length) return;
    state.activeRegion = slug;
    stage.setAttribute('data-mode', 'munis');
    Object.keys(state.groups).forEach(function (s) { state.groups[s].classList.toggle('active', s === slug); });

    var bb = meta.bbox;
    var pad = Math.max((bb[2] - bb[0]), (bb[3] - bb[1])) * 0.12 + 6;
    var to = [bb[0] - pad, bb[1] - pad, (bb[2] - bb[0]) + pad * 2, (bb[3] - bb[1]) + pad * 2];
    tweenViewBox(state.svg, currentVB(state.svg), to, 380);

    if (title) title.textContent = '2. korak — izberi občino (' + meta.name + ')';
    if (backBtn) backBtn.classList.remove('hidden');
  }

  function exitRegion() {
    state.activeRegion = null;
    stage.setAttribute('data-mode', 'regions');
    Object.keys(state.groups).forEach(function (s) { state.groups[s].classList.remove('active'); });
    if (state.svg && state.fullVB) tweenViewBox(state.svg, currentVB(state.svg), state.fullVB, 380);
    if (title) title.textContent = '1. korak — izberi regijo';
    if (backBtn) backBtn.classList.add('hidden');
  }
  if (backBtn) backBtn.addEventListener('click', exitRegion);
})();
