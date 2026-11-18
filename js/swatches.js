/* Shirt color swatches for the order page and the Send mockup page.
   Colors are close screen approximations of the mill colors, not exact matches. */
(function () {
  var EXACT = {
    'white': '#ffffff', 'black': '#111111', 'jet black': '#0b0b0b', 'vintage black': '#2b2b2b', 'oxblood black': '#2a1416',
    'navy': '#1f2a44', 'true navy': '#1b2440', 'team navy': '#1c2541', 'heather navy': '#3a4560', 'vintage navy': '#3b4660', 'marine': '#2b3e5c',
    'royal': '#2350a8', 'true royal': '#2457b3', 'heather royal': '#4e6fb5', 'heather true royal': '#4f6fb8', 'royal purple': '#5a3a99',
    'red': '#c8102e', 'heather red': '#c4505a', 'canvas red': '#c3283a', 'vintage red': '#b2433f', 'cardinal': '#8c1d2c', 'poppy': '#e34b3c',
    'maroon': '#5c1a26', 'athletic maroon': '#5c1f2b', 'heather athletic maroon': '#6e3a44', 'sangria': '#7a2347', 'heather sangria': '#8a4a63',
    'ash': '#d9d9d6', 'athletic heather': '#b7b8b6', 'solid athletic grey': '#a9aaa8', 'medium grey': '#8d8d8b', 'dark heather grey': '#5a5b5c',
    'charcoal': '#4a4b4d', 'coal grey': '#3e3f41', 'graphite heather': '#55575a', 'black heather': '#3a3a3c', 'asphalt': '#4b4c4f', 'dark grey': '#4d4e50',
    'dark grey heather': '#55565a', 'deep heather': '#7d7f80', 'grey triblend': '#8f9092', 'silver': '#c4c6c8', 'storm': '#6b7075', 'slate': '#68737d',
    'natural': '#efe6d2', 'ivory': '#f3ecd8', 'creme': '#f2e7cf', 'soft cream': '#f1e6cf', 'vintage white': '#f2ede1', 'oatmeal heather': '#d8cfbd',
    'sand': '#d7c6a4', 'light sand': '#e3d6b8', 'sand dune': '#cdb28d', 'tan': '#c6a77e', 'toast': '#a26b3e', 'heather dust': '#d6c3ad', 'dust': '#d8c6b1',
    'gold': '#f2b01e', 'yellow': '#f6d529', 'lemon yellow': '#f7e45a', 'maize yellow': '#f5c84c', 'mustard': '#d4a12f', 'heather mustard': '#cfa64a',
    'orange': '#f26b1d', 'tennessee orange': '#f08a24', 'burnt orange': '#c75b21', 'autumn': '#b8652a', 'rust': '#a5482b', 'sunset': '#f08452', 's. orange': '#ff6a13',
    'kelly': '#1f8a3d', 'athletic kelly': '#1d8a43', 'clover green': '#2f9a48', 'dark green': '#1e4630', 'forest': '#1f3d2b', 'hunter green': '#25402f', 'evergreen': '#2c4a3a',
    'pine': '#2f5a3e', 'heather forest': '#3f5a49', 'olive': '#5b5d34', 'dark olive': '#4a4a2c', 'olive drab green': '#4f5332', 'olive drab green heather': '#66694d',
    'heather olive': '#6b6b4c', 'army': '#5a5d3c', 'military green': '#5b5f3e', 'thyme': '#7a8466', 'sage': '#a3ae92', 'leaf': '#4c9a52', 'lime': '#8bc34a',
    'synthetic green': '#3fbf5f', 'laurel green': '#a9b58a', 'jade green': '#2f9d82', 'jadeite': '#a5cfc2', 'mint': '#bfe6cf', 'true celadon': '#b8d8c0', 's. green': '#9bd400',
    'teal': '#11787a', 'deep teal': '#1d5c63', 'heather deep teal': '#3f6f74', 'aqua': '#3fb7c9', 'bright aqua': '#21b6c8', 'turquoise': '#2ab3bd', 'tidal wave': '#2c8fa8',
    'heather blue lagoon': '#4f8fa0', 'atlantic': '#2f5f80', 'ocean blue': '#2a6fa8', 'neptune blue': '#2f6fa3', 'sapphire': '#0f6ab4', 'aquatic blue': '#2b8dd1',
    'carolina blue': '#7bafd4', 'columbia blue': '#9cc3e4', 'light blue': '#a9c9e8', 'baby blue': '#b9d4ec', 'cool blue': '#8fb6d9', 'dusty blue': '#8aa4bb',
    'steel blue': '#5f7f9b', 'stonewashed blue': '#7f99b0', 'tundra blue': '#6b8aa6', 'blue storm': '#6e8396', 'electric blue': '#2f6fe0', 'neon blue': '#3c6df0',
    'vintage denim': '#4b6582', 'lavender blue': '#8f9bd0', 'purple': '#4b2e83', 'team purple': '#46307f', 'heather purple': '#6d5a96', 'purple storm': '#5d4f84',
    'violet': '#7a4fb0', 'light violet': '#b8a3d8', 'lavender': '#b6a3d6', 'dark lavender': '#7c6aa8', 'lavender dust': '#c3b5cf', 'lilac': '#c9a9d8', 'orchid': '#c27fbf',
    'mauve': '#b57f8e', 'berry': '#8d2d5a', 'fuchsia': '#d2307e', 'charity pink': '#f287ad', 'candy pink': '#f4a7c0', 'pink': '#f3a6bf', 'soft pink': '#f6c6d1',
    'cherry blossom': '#f5c2cf', 'flush pink': '#f0a0b8', 'pale blush': '#f2d3cf', 'pink gravel': '#d9b3ab', 'coral': '#f47c6b', 'peach': '#f6b48f',
    'heathered dusty peach': '#e8b39d', 'zinnia': '#e84b5a', 'neon pink': '#ff5fa2', 'neon green': '#7cff4f', 'neon orange': '#ff7a1a', 'neon yellow': '#eaff3c',
    'brown': '#5a3b26', 'dark chocolate brown': '#3e2618', 'heather dark chocolate brown': '#5b4134', 'woodland brown': '#4e3a29', 'coyote brown': '#80684b',
    'duck brown': '#7a5b3a', 'chestnut': '#7b4a2e', 'cocoa': '#5e3c2b', 'pebble brown': '#8d7660', 'vintage brown': '#6d5141', 'clay': '#b9765a',
    'grey acid fleece': '#9a9b9d', 'heather true royal ': '#4f6fb8'
  };
  var WORDS = [['navy', '#1f2a44'], ['black', '#1a1a1a'], ['white', '#ffffff'], ['royal', '#2457b3'], ['red', '#c8102e'], ['maroon', '#5c1a26'], ['grey', '#8d8d8b'],
    ['gray', '#8d8d8b'], ['charcoal', '#4a4b4d'], ['green', '#2f7a45'], ['olive', '#5b5d34'], ['blue', '#3b78c2'], ['purple', '#5a3a99'], ['pink', '#f3a6bf'],
    ['orange', '#f26b1d'], ['yellow', '#f6d529'], ['gold', '#f2b01e'], ['brown', '#5a3b26'], ['tan', '#c6a77e'], ['sand', '#d7c6a4'], ['teal', '#11787a']];
  function hex(name) {
    var n = String(name || '').toLowerCase().trim();
    if (EXACT[n]) return EXACT[n];
    for (var i = 0; i < WORDS.length; i++) if (n.indexOf(WORDS[i][0]) >= 0) return WORDS[i][1];
    return '#cccccc';
  }
  function light(h) { var c = parseInt(h.slice(1), 16), r = c >> 16, g = (c >> 8) & 255, b = c & 255; return (r * 299 + g * 587 + b * 114) / 1000 > 200; }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  var CSS = '.sw-btn{display:flex;align-items:center;gap:10px;width:100%;font:inherit;font-size:16px;padding:9px 11px;border:2px solid #1c1f17;background:#fff;color:#1c1f17;cursor:pointer;text-align:left}'
    + '.sw-btn .cv{margin-left:auto;font-size:12px}.sw-dot{display:inline-block;width:22px;height:22px;border-radius:50%;border:1px solid rgba(0,0,0,.25);flex:none}'
    + '.sw-dot.lt{border-color:#9a9a9a}.sw-pop{display:none;grid-template-columns:repeat(auto-fill,minmax(38px,1fr));gap:8px;padding:12px;border:2px solid #1c1f17;border-top:0;background:#fff;max-height:260px;overflow:auto}'
    + '.sw-pop.open{display:grid}.sw-pop button{width:34px;height:34px;border-radius:50%;border:1px solid rgba(0,0,0,.25);cursor:pointer;padding:0}'
    + '.sw-pop button.lt{border-color:#9a9a9a}.sw-pop button[aria-checked=true]{outline:3px solid #F26B1D;outline-offset:2px}.sw-note{grid-column:1/-1;font-size:12px;color:#5b604f}'
    + '.sw-wrap{position:relative}';
  function addCss() { if (document.getElementById('sw-css')) return; var s = document.createElement('style'); s.id = 'sw-css'; s.textContent = CSS; document.head.appendChild(s); }

  /* picker(container, colors[], current, onPick(name), opts{name: input name for forms}) */
  function picker(box, colors, current, onPick, opts) {
    addCss(); opts = opts || {};
    var cur = colors.indexOf(current) >= 0 ? current : colors[0];
    box.classList.add('sw-wrap');
    box.innerHTML = '<button type="button" class="sw-btn" aria-expanded="false"><span class="sw-dot"></span><span class="nm"></span><span class="cv">&#9662;</span></button>'
      + '<div class="sw-pop" role="radiogroup" aria-label="Shirt color">' + colors.map(function (c) {
        var h = hex(c); return '<button type="button" role="radio" title="' + esc(c) + '" aria-label="' + esc(c) + '" data-c="' + esc(c) + '" style="background:' + h + '"' + (light(h) ? ' class="lt"' : '') + '></button>';
      }).join('') + '<div class="sw-note">Colors on screen are close, not exact.</div></div>'
      + (opts.name ? '<input type="hidden" name="' + esc(opts.name) + '">' : '');
    var btn = box.querySelector('.sw-btn'), pop = box.querySelector('.sw-pop'), hid = box.querySelector('input[type=hidden]');
    function show(c) {
      cur = c; var h = hex(c), dot = btn.querySelector('.sw-dot');
      dot.style.background = h; dot.className = 'sw-dot' + (light(h) ? ' lt' : ''); btn.querySelector('.nm').textContent = c;
      pop.querySelectorAll('button').forEach(function (b) { b.setAttribute('aria-checked', b.getAttribute('data-c') === c ? 'true' : 'false'); });
      if (hid) hid.value = c;
    }
    btn.addEventListener('click', function () { var o = !pop.classList.contains('open'); pop.classList.toggle('open', o); btn.setAttribute('aria-expanded', o); });
    pop.addEventListener('click', function (e) {
      var c = e.target.getAttribute && e.target.getAttribute('data-c'); if (!c) return;
      show(c); pop.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); if (onPick) onPick(c);
    });
    show(cur);
    return { set: show, get: function () { return cur; } };
  }
  window.SISwatch = { hex: hex, picker: picker };
})();
