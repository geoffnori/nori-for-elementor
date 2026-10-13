/* Nori for Elementor: the admin app.
 *
 * Source repository: https://github.com/geoffnori/nori-wordpress-plugin
 * Handwritten vanilla JavaScript, shipped as written: no build step, bundler or minification.
 *
 * One screen with a sidebar. Everything it shows comes from NoriApp.state (built by
 * includes/admin/class-admin-app.php), and every change waits in one save bar until Save, which
 * sends one batch to the REST route nori/v1/admin/save. A change only counts while it differs
 * from what's saved, so putting a control back the way it was clears it.
 */
(function (w, d) {
	'use strict';

	var BOOT = w.NoriApp || {};
	var i18n = (w.wp && w.wp.i18n) || { __: function (s) { return s; }, _n: function (a, b, n) { return n === 1 ? a : b; }, sprintf: function (f) { var a = [].slice.call(arguments, 1), i = 0; return String(f).replace(/%(\d+\$)?[sd]/g, function (m, p) { return p ? a[parseInt(p, 10) - 1] : a[i++]; }); } };
	var __ = i18n.__, _n = i18n._n, sprintf = i18n.sprintf;
	var S = BOOT.state;
	var root = d.getElementById('nori-app');
	if (!root || !S) { return; }

	/* ================= helpers ================= */
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function $(id) { return d.getElementById(id); }
	var ICONS = {
		home: '<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
		templates: '<path d="M4 4h16v16H4zM4 9h16M10 9v11"/>',
		parts: '<path d="M3 4h18v4H3zM3 16h18v4H3zM7 12h10"/>',
		colors: '<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor"/>',
		type: '<path d="M4 7V5h11v2M9.5 5v14M7 19h5M14 13v-1.5h7V13M17.5 11.5V19M16 19h3"/>',
		variables: '<path d="M8 4c-2 0-3 1-3 3v2c0 1.2-.8 2-2 3 1.2 1 2 1.8 2 3v2c0 2 1 3 3 3M16 4c2 0 3 1 3 3v2c0 1.2.8 2 2 3-1.2 1-2 1.8-2 3v2c0 2-1 3-3 3"/>',
		classes: '<path d="M10 3 8 21M16 3l-2 18M4 8.5h17M3 15.5h17"/>',
		scrolling: '<path d="M12 3a5 5 0 0 1 5 5v8a5 5 0 0 1-10 0V8a5 5 0 0 1 5-5zM12 7v3"/>',
		'scroll-animation': '<path d="M12 21V9M7 14l5-5 5 5M5 4h14"/>',
		buttons: '<path d="M4 4l15 6.5-6.2 2.2L10.5 19z"/><path d="M13 13l5 5"/>',
		media: '<rect x="3" y="5" width="18" height="14"/><path d="M10 9.5v5l4.5-2.5z"/>',
		carousels: '<path d="M7 6h10v12H7zM3 8.5v7M21 8.5v7"/>',
		video: '<rect x="3" y="5" width="18" height="14"/><path d="M6.5 9h6M9.5 9v6.5"/><path d="M14.5 11v4.5l3.5-2.25z"/>',
		forms: '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M8.8 12.2l2.2 2.2 4.3-4.6"/>',
		site: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3.2 3.4 3.2 14.6 0 18M12 3c-3.2 3.4-3.2 14.6 0 18"/>',
		tools: '<path d="M7 4v13M3 13l4 4 4-4M17 20V7M13 11l4-4 4 4"/>',
		reset: '<path d="M4 12a8 8 0 1 0 2.4-5.7"/><path d="M4 4v4h4"/>',
		changelog: '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/>',
		chev: '<path d="M6 9l6 6 6-6"/>',
		cup: '<path d="M4 8h12v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z"/><path d="M16 10h2a2.5 2.5 0 0 1 0 5h-2.2"/><path d="M10 15.2c-1.6-1-2.4-1.8-2.4-2.8a1.2 1.2 0 0 1 2.4-.3 1.2 1.2 0 0 1 2.4.3c0 1-.8 1.8-2.4 2.8z" fill="currentColor" stroke="none"/>',
		ext: '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		check: '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16.5 9"/>',
		info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
		warn: '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.5"/>',
		bulb: '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z"/>',
		search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/>'
	};
	function icon(k) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + (ICONS[k] || '') + '</svg>'; }

	/* colors */
	function hex(v) { v = String(v || '').trim().toUpperCase(); return /^#[0-9A-F]{6}$/.test(v) ? v : ''; }
	function rgb(h) { h = hex(h) || '#000000'; return [parseInt(h.slice(1, 3), 16), parseInt(h.slice(3, 5), 16), parseInt(h.slice(5, 7), 16)]; }
	function mix(a, b, t) { var x = rgb(a), y = rgb(b), o = '#'; for (var i = 0; i < 3; i++) { var c = Math.round(x[i] + (y[i] - x[i]) * t); o += (c < 16 ? '0' : '') + c.toString(16).toUpperCase(); } return o; }
	function lum(h) { return rgb(h).map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }).reduce(function (s, v, i) { return s + v * [0.2126, 0.7152, 0.0722][i]; }, 0); }
	function contrast(a, b) { var l1 = lum(a), l2 = lum(b); return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05); }
	function readable(bg, ink) { if (contrast(bg, ink) >= 4.5) { return hex(ink); } return contrast(bg, '#FFFFFF') >= contrast(bg, '#111111') ? '#FFFFFF' : '#111111'; }
	// Same as Design::tones() and Design::derive() in PHP.
	function withTones(c) {
		var t = 0.32, m = mix(c.text, c.background, t);
		while (t > 0 && Math.min(contrast(m, c.background), contrast(m, c.surface)) < 4.5) { t = Math.max(0, t - 0.04); m = mix(c.text, c.background, t); }
		var dk = lum(c.background) < 0.18;
		c.line = mix(c.background, c.text, dk ? 0.14 : 0.12);
		c.muted = m;
		c.band = dk ? mix(c.background, c.text, 0.07) : (contrast(c.primary || c.text, c.background) >= 7 ? hex(c.primary || c.text) : hex(c.text));
		c['on-band'] = dk ? hex(c.text) : hex(c.background);
		return c;
	}
	function derive(accent, ink) {
		accent = hex(accent) || '#D6FF00';
		ink = hex(ink) || mix('#111827', accent, 0.12);
		var paper = mix('#F5F5F5', accent, 0.04);
		return {
			light: withTones({ primary: ink, secondary: mix(ink, '#FFFFFF', 0.32), text: ink, accent: accent, 'on-accent': readable(accent, ink), background: '#FFFFFF', surface: mix('#FFFFFF', accent, 0.06) }),
			dark: withTones({ primary: paper, secondary: mix(paper, '#121212', 0.35), text: paper, accent: accent, 'on-accent': readable(accent, ink), background: mix('#121212', accent, 0.03), surface: mix('#1C1C1C', accent, 0.05) })
		};
	}
	// "Suggest": flip the lightness, keep the hue (mid tones move a little further).
	function suggest(h) {
		var c = rgb(h).map(function (v) { return v / 255; }), r = c[0], g = c[1], b = c[2];
		var mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2, s = 0, hh = 0, dd = mx - mn;
		if (dd) { s = l > 0.5 ? dd / (2 - mx - mn) : dd / (mx + mn); hh = mx === r ? (g - b) / dd + (g < b ? 6 : 0) : mx === g ? (b - r) / dd + 2 : (r - g) / dd + 4; hh /= 6; }
		l = 1 - l; if (l > 0.35 && l < 0.65) { l += 0.12; } l = Math.min(0.92, Math.max(0.1, l));
		function f(n) { var k = (n + hh * 12) % 12, a = s * Math.min(l, 1 - l); var v = l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1)); return ('0' + Math.round(v * 255).toString(16)).slice(-2); }
		return ('#' + f(0) + f(8) + f(4)).toUpperCase();
	}

	/* ================= lookups ================= */
	var IDX = {};
	function index() {
		IDX.cls = {}; S.classes.items.forEach(function (c) { IDX.cls[c.key] = c; });
		IDX.vars = {}; S.variables.items.forEach(function (v) { IDX.vars[v.key] = v; });
		IDX.cgroup = {}; S.classes.groups.forEach(function (g) { IDX.cgroup[g.key] = g; });
		IDX.mine = {}; S.colors.mine.forEach(function (u) { IDX.mine[u.id] = u; });
	}
	function cls(k) { return IDX.cls[k]; }
	function vr(k) { return IDX.vars[k]; }
	function mod(k) { return S.modules.items[k]; }
	function cat(k) { return S.modules.categories.filter(function (c) { return c.key === k; })[0]; }
	var CKEYS = S.design.colorKeys, DKEYS = CKEYS.map(function (k) { return S.design.darkKeys[k]; });

	/* ================= state =================
	   BASE is what's saved. CUR holds what the screen shows now. */
	var BASE = {}, CUR = {};
	function val(k) { return Object.prototype.hasOwnProperty.call(CUR, k) ? CUR[k] : BASE[k]; }
	function base(k) { return BASE[k]; }
	function changed(k) { return Object.prototype.hasOwnProperty.call(CUR, k) && CUR[k] !== BASE[k]; }
	function setv(k, v) { if (v === BASE[k]) { delete CUR[k]; } else { CUR[k] = v; } bar(); }
	function dirty() { return Object.keys(CUR).filter(changed); }
	function load(state) {
		S = state; index(); BASE = {}; CUR = {};
		Object.keys(S.modules.items).forEach(function (k) {
			var m = S.modules.items[k];
			BASE['mod:' + k] = !!m.on;
			BASE['delcls:' + k] = false;
			m.fields.forEach(function (f) { var v = m.values[f.key]; BASE['set:' + k + ':' + f.key] = f.type === 'color' ? (hex(v) ? String(v).toLowerCase() : String(v)) : f.type === 'select' ? String(v) : f.type === 'toggle' ? !!v : +v; });
		});
		S.variables.items.forEach(function (v) { BASE['var:' + v.key] = v.type === 'color' ? (hex(v.value) || v.value) : String(v.value); BASE['vact:' + v.key] = ''; });
		S.classes.items.forEach(function (c) { BASE['cact:' + c.key] = ''; });
		S.colors.mine.forEach(function (u) { BASE['ucol:' + u.id] = u.to; });
		BASE['ts:base'] = +S.design.type.base; BASE['ts:ratio'] = String(S.design.type.ratio); BASE['ts:weight'] = String(S.design.type.weight);
		BASE.brand = S.design.preset || (S.design.brand.preset === 'custom' ? 'custom' : '');
		BASE['brand:accent'] = S.design.brand.accent; BASE['brand:ink'] = S.design.brand.ink;
		BASE['dm:base'] = S.design.base;
	}
	function modOn(k) { return !!val('mod:' + k); }
	function siteLook() { var on = modOn('dark-mode'), dk = val('dm:base') === 'dark'; return on ? (dk ? 'darkswitch' : 'switch') : (dk ? 'dark' : 'light'); }
	function darkOf(k) { return val('set:dark-mode:' + S.design.darkKeys[k]); }
	function inEl(st) { return st === 'default' || st === 'edited'; }
	/** A color value to a hex: var(accent) follows the Nori color, var(e-gv-…) the user's own. */
	function colorOf(v) {
		v = String(v || '');
		var m = v.match(/^var\(([a-z0-9-]+)\)$/);
		if (m) { if (IDX.vars[m[1]]) { return val('var:' + m[1]); } var u = IDX.mine[m[1]]; return u ? u.value : '#000000'; }
		return hex(v) || '#000000';
	}
	/** The current values of Nori's colors, as CSS custom properties for the previews. */
	function brandVars() {
		var r = root.style;
		[['bp', 'primary'], ['ba', 'accent'], ['bo', 'on-accent'], ['bb', 'background'], ['bs', 'surface'], ['bl', 'line'], ['bm', 'muted'], ['bt', 'text']].forEach(function (x) { r.setProperty('--' + x[0], val('var:' + x[1])); });
	}

	/* ================= what a save sends ================= */
	function payload() {
		var p = {}, ks = dirty();
		var has = function (pre) { return ks.some(function (k) { return k.indexOf(pre) === 0; }); };
		if (changed('dm:base')) { p.base = val('dm:base'); }
		if (changed('brand') || changed('brand:accent') || changed('brand:ink')) { p.brand = { preset: val('brand'), accent: val('brand:accent'), ink: val('brand:ink') }; }
		if (has('ts:')) { p.type = { base: val('ts:base'), ratio: val('ts:ratio'), weight: val('ts:weight') }; }
		var V = { add: [], reset: [], 'delete': [], values: {} }, C = { add: [], reset: [], 'delete': [] };
		ks.forEach(function (k) {
			var x = k.split(':'), v = val(k);
			if (x[0] === 'vact' && v) { V[v].push(x[1]); }
			if (x[0] === 'cact' && v) { C[v].push(x[1]); }
			if (x[0] === 'var') { V.values[x[1]] = v; }
		});
		if (V.add.length || V.reset.length || V['delete'].length || Object.keys(V.values).length) { p.variables = V; }
		if (C.add.length || C.reset.length || C['delete'].length) { p.classes = C; }
		var M = { enabled: {}, settings: {}, deleteClasses: [] }, any = false;
		ks.forEach(function (k) {
			var x = k.split(':');
			if (x[0] === 'mod') { M.enabled[x[1]] = val(k); any = true; }
			if (x[0] === 'delcls' && val(k)) { M.deleteClasses.push(x[1]); any = true; }
			if (x[0] === 'set' && !M.settings[x[1]]) {
				var all = {}; mod(x[1]).fields.forEach(function (f) { all[f.key] = val('set:' + x[1] + ':' + f.key); });
				M.settings[x[1]] = all; any = true;
			}
		});
		if (has('ucol:')) {
			M.darkColors = {};
			S.colors.mine.forEach(function (u) { var v = val('ucol:' + u.id); M.darkColors[u.id] = hex(v) ? { to: 'custom', custom: v } : { to: v }; });
			any = true;
		}
		if (any) { p.modules = M; }
		return p;
	}

	/* ================= navigation ================= */
	var CAT_ICON = { scrolling: 'scrolling', 'scroll-animation': 'scroll-animation', buttons: 'buttons', media: 'media', carousels: 'carousels', video: 'video', forms: 'forms', site: 'site' };
	function catView(k) { return 'm-' + k; }
	function nav() {
		var cats = S.modules.categories.filter(function (c) { return c.key !== 'forms' && c.key !== 'site'; });
		var tail = S.modules.categories.filter(function (c) { return c.key === 'forms' || c.key === 'site'; });
		var mk = function (c) { return { k: catView(c.key), l: c.key === 'forms' ? __('Forms & HubSpot', 'nori-for-elementor') : c.label, i: CAT_ICON[c.key] || 'buttons', cat: c.key }; };
		return [
			{ h: __('Start', 'nori-for-elementor'), items: [{ k: 'home', l: __('Home', 'nori-for-elementor'), i: 'home' }] },
			{ h: __('Build', 'nori-for-elementor'), items: [{ k: 'templates', l: __('Page templates', 'nori-for-elementor'), i: 'templates' }, { k: 'site-parts', l: __('Header & footer', 'nori-for-elementor'), i: 'parts' }] },
			{ h: __('Design system', 'nori-for-elementor'), items: [
				{ k: 'colors', l: __('Colors & dark mode', 'nori-for-elementor'), i: 'colors', p: ['var:primary', 'var:secondary', 'var:text', 'var:accent', 'var:on-accent', 'var:background', 'var:surface', 'var:line', 'var:muted', 'set:dark-mode:', 'mod:dark-mode', 'ucol:', 'brand', 'dm:'] },
				{ k: 'type', l: __('Type scale', 'nori-for-elementor'), i: 'type', c: function () { return S.classes.items.filter(function (c) { return c.group === 'type'; }).length; }, p: ['ts:'] },
				{ k: 'variables', l: __('Variables', 'nori-for-elementor'), i: 'variables', c: function () { return S.variables.items.length; }, p: ['vact:', 'var:border', 'var:size'] },
				{ k: 'classes', l: __('Classes', 'nori-for-elementor'), i: 'classes', c: function () { return S.classes.items.filter(function (c) { return !c.module; }).length; }, p: ['cact:'] }
			] },
			{ h: __('Motion & effects', 'nori-for-elementor'), items: cats.map(mk) },
			{ h: __('Forms & site', 'nori-for-elementor'), items: tail.map(mk) },
			{ h: __('Tools', 'nori-for-elementor'), items: [{ k: 'tools', l: __('Import & export', 'nori-for-elementor'), i: 'tools' }, { k: 'reset', l: __('Full reset', 'nori-for-elementor'), i: 'reset' }, { k: 'changelog', l: __('What\'s new', 'nori-for-elementor'), i: 'changelog' }] }
		];
	}
	function navItem(k) { var r = null; nav().forEach(function (g) { g.items.forEach(function (it) { if (it.k === k) { r = it; } }); }); return r; }
	function views() { var out = []; nav().forEach(function (g) { g.items.forEach(function (it) { out.push(it.k); }); }); return out; }
	function viewDirty(it) {
		var ks = dirty();
		if (it.cat) {
			var ms = cat(it.cat).modules;
			return ks.some(function (k) { var p = k.split(':'); return (p[0] === 'mod' || p[0] === 'set' || p[0] === 'delcls') && ms.indexOf(p[1]) > -1 && !(p[1] === 'dark-mode' && p[0] !== 'delcls'); });
		}
		return !!it.p && ks.some(function (k) { return it.p.some(function (p) { return k.indexOf(p) === 0; }); });
	}
	function renderSide() {
		var h = '';
		nav().forEach(function (g) {
			h += '<h4>' + esc(g.h) + '</h4>';
			g.items.forEach(function (it) {
				var c = it.c ? it.c() : (it.cat ? cat(it.cat).modules.filter(modOn).length + '/' + cat(it.cat).modules.length : '');
				h += '<a href="#' + it.k + '" data-go="' + it.k + '"' + (view === it.k ? ' aria-current="page"' : '') + '>' + icon(it.i) + '<span class="lb">' + esc(it.l) + '</span>' + (viewDirty(it) ? '<span class="pd" title="' + esc(__('Unsaved changes here', 'nori-for-elementor')) + '"></span>' : '') + '<span class="cnt">' + esc(c) + '</span></a>';
			});
		});
		h += '<h4>Nori</h4><a class="ext" href="' + esc(S.site.siteUrl) + '" target="_blank" rel="noopener">' + icon('ext') + '<span class="lb">noriforelement.com</span><span class="screen-reader-text"> ' + esc(__('(opens in a new tab)', 'nori-for-elementor')) + '</span></a>';
		h += '<a class="ext" href="' + esc(S.site.support) + '" target="_blank" rel="noopener" title="' + esc(__('Nori is free. If it saves you time, you can support it on Ko-fi.', 'nori-for-elementor')) + '">' + icon('cup') + '<span class="lb">' + esc(__('Support Nori', 'nori-for-elementor')) + '</span><span class="cnt">Ko-fi</span></a>';
		$('nori-side').innerHTML = h;
	}

	/* ================= save bar ================= */
	function summary() {
		var n = { add: 0, reset: 0, del: 0, on: 0, off: 0, set: 0, col: 0, dark: 0, type: 0, delc: 0, pre: 0, look: 0 };
		dirty().forEach(function (k) {
			var p = k.split(':'), v = val(k);
			if (p[0] === 'cact' || p[0] === 'vact') { if (v === 'add') { n.add++; } else if (v === 'reset') { n.reset++; } else if (v === 'delete') { n.del++; } }
			else if (p[0] === 'mod') { if (v) { n.on++; } else { n.off++; } }
			else if (p[0] === 'set') { if (p[1] === 'dark-mode' && /^dark[A-Z]/.test(p[2])) { n.dark++; } else { n.set++; } }
			else if (p[0] === 'var') { n.col++; }
			else if (p[0] === 'ucol') { n.dark++; }
			else if (p[0] === 'ts') { n.type = 1; }
			else if (p[0] === 'brand') { n.pre = 1; }
			else if (p[0] === 'dm') { n.look = 1; }
			else if (p[0] === 'delcls' && v) { n.delc++; }
		});
		var o = [], alt = val('dm:base') === 'dark';
		if (n.add) { o.push(sprintf(__('%d to add', 'nori-for-elementor'), n.add)); }
		if (n.reset) { o.push(sprintf(__('%d to reset', 'nori-for-elementor'), n.reset)); }
		if (n.del) { o.push(sprintf(__('%d to delete', 'nori-for-elementor'), n.del)); }
		if (n.on) { o.push(sprintf(__('%d to switch on', 'nori-for-elementor'), n.on)); }
		if (n.off) { o.push(sprintf(__('%d to switch off', 'nori-for-elementor'), n.off)); }
		if (n.set) { o.push(sprintf(_n('%d setting changed', '%d settings changed', n.set, 'nori-for-elementor'), n.set)); }
		if (n.col) { o.push(sprintf(_n('%d color or value changed', '%d colors or values changed', n.col, 'nori-for-elementor'), n.col)); }
		if (n.dark) { o.push(alt ? sprintf(_n('%d light version changed', '%d light versions changed', n.dark, 'nori-for-elementor'), n.dark) : sprintf(_n('%d dark color changed', '%d dark colors changed', n.dark, 'nori-for-elementor'), n.dark)); }
		if (n.type) { o.push(__('type scale changed', 'nori-for-elementor')); }
		if (n.delc) { o.push(__('module classes to delete', 'nori-for-elementor')); }
		if (n.pre) { o.push(__('color preset changed', 'nori-for-elementor')); }
		if (n.look) { o.push(__('site type changed', 'nori-for-elementor')); }
		return o;
	}
	function bar() {
		var s = summary(), n = dirty().length;
		$('nori-pending').textContent = n ? sprintf(__('Unsaved: %s', 'nori-for-elementor'), s.join(', ')) : __('No unsaved changes', 'nori-for-elementor');
		$('nori-pending').title = n ? s.join(', ') : '';
		$('nori-pending').classList.toggle('has', !!n);
		$('nori-save').disabled = !n || busy; $('nori-discard').disabled = !n || busy;
		$('nori-save').textContent = busy ? __('Saving…', 'nori-for-elementor') : n ? sprintf(_n('Save %d change', 'Save %d changes', n, 'nori-for-elementor'), n) : __('Save changes', 'nori-for-elementor');
		renderSide(); markChanged(); brandVars();
	}
	function markChanged() {
		root.querySelectorAll('[data-k]').forEach(function (el) {
			var k = el.getAttribute('data-k'), c = changed(k);
			if (el.classList.contains('sw')) { el.classList.toggle('changed', c); }
			else if (el.tagName === 'INPUT' || el.tagName === 'SELECT') { el.classList.toggle('changed-in', c); }
			var f = el.closest('.field'); if (f) { var out = f.querySelector('output'); if (out) { out.classList.toggle('changed', changed(f.getAttribute('data-fk') || k)); } }
		});
	}
	var toastTimer;
	function toast(t, kind) {
		var el = $('nori-toast');
		el.className = 'toast' + (kind === 'error' ? ' is-error' : '');
		el.innerHTML = icon(kind === 'error' ? 'warn' : 'check') + '<span>' + esc(t) + '</span>';
		el.hidden = false; clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { el.hidden = true; }, kind === 'error' ? 9000 : Math.min(9000, 2600 + t.length * 25));
	}
	function deletions() { return dirty().filter(function (k) { return ((k.indexOf('cact:') === 0 || k.indexOf('vact:') === 0) && val(k) === 'delete') || (k.indexOf('delcls:') === 0 && val(k)); }); }
	var busy = false;
	function askSave() {
		var dl = deletions();
		if (!dl.length) { return commit(); }
		var pages = 0, items = [];
		dl.forEach(function (k) {
			var p = k.split(':');
			if (p[0] === 'cact') { pages += cls(p[1]).usage; items.push('.' + cls(p[1]).label); }
			else if (p[0] === 'vact') { items.push(vr(p[1]).label + ' ' + __('(variable)', 'nori-for-elementor')); }
			else { mod(p[1]).classes.forEach(function (c) { if (inEl(c.status)) { pages += c.usage; items.push('.' + c.key + '-np'); } }); }
		});
		var c = $('nori-confirm');
		c.innerHTML = '<b>' + esc(sprintf(_n('Delete %d item from Elementor?', 'Delete %d items from Elementor?', items.length, 'nori-for-elementor'), items.length)) + '</b><ul>' + items.slice(0, 8).map(function (i) { return '<li><code>' + esc(i) + '</code></li>'; }).join('') + (items.length > 8 ? '<li class="note">' + esc(sprintf(__('and %d more', 'nori-for-elementor'), items.length - 8)) + '</li>' : '') + '</ul><p>' + esc(pages ? sprintf(_n('They will be removed from every element that uses them (on %d page). This can\'t be undone.', 'They will be removed from every element that uses them (on %d pages). This can\'t be undone.', pages, 'nori-for-elementor'), pages) : __('No page uses them, so nothing on your site changes. You can add them again later.', 'nori-for-elementor')) + (dl.some(function (k) { return k.indexOf('vact:') === 0; }) ? ' ' + esc(__('Nori classes that use these variables keep the value as a plain number and show as edited.', 'nori-for-elementor')) : '') + '</p><div class="row"><button type="button" class="btn sm" data-confirm="no">' + esc(__('Back', 'nori-for-elementor')) + '</button><button type="button" class="btn sm primary" data-confirm="yes">' + esc(__('Delete and save', 'nori-for-elementor')) + '</button></div>';
		c.hidden = false; c.querySelector('[data-confirm="yes"]').focus();
	}
	function api(path, body) {
		return w.fetch(BOOT.rest + path, { method: body ? 'POST' : 'GET', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': BOOT.nonce }, body: body ? JSON.stringify(body) : undefined })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) { throw new Error(j && j.message ? j.message : 'HTTP ' + r.status); } return j; }); });
	}
	function commit() {
		var p = payload(), n = dirty().length;
		busy = true; bar();
		return api('save', p).then(function (r) {
			busy = false;
			load(r.state); bar(); render(true);
			if (r.errors && r.errors.length) { toast(r.errors.concat(r.messages || []).join(' '), 'error'); }
			else { toast((r.messages && r.messages.length ? r.messages.join(' ') : sprintf(_n('Saved %d change.', 'Saved %d changes.', n, 'nori-for-elementor'), n))); }
		}).catch(function (e) {
			busy = false; bar();
			toast(sprintf(__('Nothing was saved: %s', 'nori-for-elementor'), e.message), 'error');
		});
	}
	function discard() { var n = dirty().length; CUR = {}; $('nori-confirm').hidden = true; bar(); render(true); toast(sprintf(_n('Discarded %d change', 'Discarded %d changes', n, 'nori-for-elementor'), n)); }

	/* ================= shared view bits ================= */
	var view = 'home', OPEN = {}, filt = { group: 'all', q: '', status: 'all' }, vtab = null, FOLD = false;
	function head(crumb, title, desc, right) { return '<div class="head"><div><div class="crumb">' + esc(crumb) + '</div><h1>' + esc(title) + '</h1>' + (desc ? '<p>' + desc + '</p>' : '') + '</div>' + (right || '') + '</div>'; }
	function pill(st) {
		return {
			'default': '<span class="pill live">' + esc(__('In Elementor', 'nori-for-elementor')) + '</span>',
			edited: '<span class="pill edited">' + esc(__('Edited in Elementor', 'nori-for-elementor')) + '</span>',
			missing: '<span class="pill">' + esc(__('Not in Elementor', 'nori-for-elementor')) + '</span>',
			deleted: '<span class="pill">' + esc(__('Deleted in Elementor', 'nori-for-elementor')) + '</span>',
			needs_pro: '<span class="pill pro">' + esc(__('Needs Elementor Pro', 'nori-for-elementor')) + '</span>'
		}[st] || '';
	}
	function callout(kind, ic, html) { return '<div class="callout ' + kind + '">' + icon(ic) + '<div class="grow">' + html + '</div></div>'; }
	function gap() { return '<div style="height:12px"></div>'; }
	function kb(b) { return (b / 1024).toFixed(1) + ' KB'; }

	/* ================= Home ================= */
	function vHome() {
		var nc = S.classes.items.filter(function (c) { return !c.module; });
		var inN = nc.filter(function (c) { return inEl(c.status); }).length, ed = nc.filter(function (c) { return c.status === 'edited'; });
		var vIn = S.variables.items.filter(function (v) { return inEl(v.status); }).length;
		var on = Object.keys(S.modules.items).filter(function (k) { return S.modules.items[k].on; });
		var h = head(__('Nori for Elementor', 'nori-for-elementor'), __('Home', 'nori-for-elementor'), esc(__('Your design system at a glance, with a shortcut into each part.', 'nori-for-elementor')));
		S.site.errors.forEach(function (e) { h += callout('warn', 'warn', '<span>' + esc(e) + '</span>') + gap(); });
		if (!S.site.atomic) { h += callout('warn', 'warn', '<span>' + esc(__('Elementor\'s Atomic (version 4) editor isn\'t active on this site. Turn it on in Elementor\'s settings to use Nori classes and variables. Modules work either way.', 'nori-for-elementor')) + '</span>') + gap(); }
		var vMiss = S.variables.items.filter(function (v) { return v.status === 'missing'; }), cMiss = nc.filter(function (c) { return c.status === 'missing'; });
		if (S.site.atomic && !inN && !vIn && (vMiss.length || cMiss.length)) {
			var queued = vMiss.every(function (v) { return val('vact:' + v.key) === 'add'; }) && cMiss.every(function (c) { return val('cact:' + c.key) === 'add'; });
			h += '<section class="card strong hero" id="nori-starter"><div class="card-b"><div class="grow"><h2>' + esc(__('Start with the whole design system', 'nori-for-elementor')) + '</h2><p>' + esc(sprintf(__('Add all %1$d starter variables and %2$d classes to Elementor in one go: colors, type, spacing, layout and the rest. Modules are separate: switching one on adds its own classes.', 'nori-for-elementor'), vMiss.length, cMiss.length)) + '</p></div>'
				+ (queued ? '<span class="pendlab">' + esc(__('Ready to add. Save to finish.', 'nori-for-elementor')) + '<button type="button" class="link" data-starter="undo">' + esc(__('Undo', 'nori-for-elementor')) + '</button></span>' : '<button type="button" class="btn primary" data-starter="add">' + esc(__('Add the starter design system', 'nori-for-elementor')) + '</button>') + '</div></section>' + gap();
		}
		h += '<div class="stats">'
			+ '<button type="button" data-go="classes"><b>' + inN + '<small>/' + nc.length + '</small></b><span>' + esc(__('Classes in Elementor', 'nori-for-elementor')) + '</span><em>' + esc(ed.length ? sprintf(__('%d edited', 'nori-for-elementor'), ed.length) : __('None edited', 'nori-for-elementor')) + '</em></button>'
			+ '<button type="button" data-go="variables"><b>' + vIn + '<small>/' + S.variables.items.length + '</small></b><span>' + esc(__('Starter variables', 'nori-for-elementor')) + '</span><em>' + esc(S.site.pro ? __('Spacing included', 'nori-for-elementor') : __('Spacing needs Elementor Pro', 'nori-for-elementor')) + '</em></button>'
			+ '<button type="button" data-go="' + catView(S.modules.categories[0].key) + '"><b>' + on.length + '<small>/' + Object.keys(S.modules.items).length + '</small></b><span>' + esc(__('Modules on', 'nori-for-elementor')) + '</span><em>' + esc(on.length ? on.slice(0, 3).map(function (k) { return mod(k).label.replace(/^Button:\s*/, ''); }).join(', ') + (on.length > 3 ? '…' : '') : __('None yet', 'nori-for-elementor')) + '</em></button>'
			+ '<button type="button" data-scroll="nori-pages"><b>' + S.home.pages.length + '</b><span>' + esc(__('Pages using Nori', 'nori-for-elementor')) + '</span><em>' + esc(__('Pages, posts and templates', 'nori-for-elementor')) + '</em></button></div>';
		if (S.home.spam) { h += callout('volt', 'forms', '<span>' + (S.home.spam.blocked ? sprintf(esc(_n('Form Spam Protection blocked %s entry in the last 7 days.', 'Form Spam Protection blocked %s entries in the last 7 days.', S.home.spam.blocked, 'nori-for-elementor')), '<b>' + S.home.spam.blocked + '</b>') : esc(__('Form Spam Protection is on. Nothing blocked in the last 7 days.', 'nori-for-elementor'))) + ' <button type="button" class="link" data-go="m-forms" data-open="form-protection">' + esc(__('See what was blocked', 'nori-for-elementor')) + '</button></span>') + gap(); }
		h += '<div class="home"><div class="stack">';
		h += '<section class="card strong"><div class="card-h"><h2>' + esc(__('Your design system', 'nori-for-elementor')) + '</h2><span class="cnt">' + esc(__('Everything Nori adds to Elementor', 'nori-for-elementor')) + '</span></div><div class="ds">';
		// colors
		var look = siteLook(), pre = S.design.presets[val('brand')];
		h += '<section><h3>' + icon('colors') + esc(__('Colors & dark mode', 'nori-for-elementor')) + '<span class="cnt">' + esc(!S.variables.items.some(function (v) { return CKEYS.indexOf(v.key) > -1 && inEl(v.status); }) ? __('Not added yet', 'nori-for-elementor') : pre ? pre.label : val('brand') === 'custom' ? __('Your brand', 'nori-for-elementor') : __('Custom', 'nori-for-elementor')) + '</span></h3><div><div class="pal-l"><span>' + esc(look === 'dark' || look === 'darkswitch' ? __('Dark (your design)', 'nori-for-elementor') : __('Light', 'nori-for-elementor')) + '</span><span>' + esc(sprintf(__('%d variables', 'nori-for-elementor'), CKEYS.length)) + '</span></div><div class="palette">' + CKEYS.map(function (k) { return '<i style="background:' + esc(val('var:' + k)) + '" title="' + esc(k) + '-np"></i>'; }).join('') + '</div>';
		if (look === 'switch' || look === 'darkswitch') { h += '<div class="palette">' + CKEYS.map(function (k) { return '<i style="background:' + esc(darkOf(k)) + '"></i>'; }).join('') + '</div>'; }
		h += '<div class="pal-l" style="margin-top:3px"><span>' + esc(look === 'switch' ? __('Dark (switch)', 'nori-for-elementor') : look === 'darkswitch' ? __('Light (switch)', 'nori-for-elementor') : '') + '</span><span>' + (look === 'switch' ? '<span class="ok">' + esc(__('Dark mode on', 'nori-for-elementor')) + '</span>' : look === 'darkswitch' ? '<span class="ok">' + esc(__('Light switch on', 'nori-for-elementor')) + '</span>' : look === 'dark' ? esc(__('Dark site', 'nori-for-elementor')) : esc(__('Dark mode off', 'nori-for-elementor'))) + '</span></div></div><div class="acts"><button type="button" class="btn sm go" data-go="colors">' + esc(__('Edit colors', 'nori-for-elementor')) + '</button>' + (modOn('dark-mode') ? '<button type="button" class="btn sm go alt" data-go="colors" data-scroll="nori-colors-table">' + esc(look === 'darkswitch' ? __('Light versions', 'nori-for-elementor') : __('Dark mode colors', 'nori-for-elementor')) + '</button>' : '') + '</div></section>';
		// type
		var b = val('ts:base'), r = +val('ts:ratio'), wt = val('ts:weight');
		h += '<section><h3>' + icon('type') + esc(__('Type scale', 'nori-for-elementor')) + '<span class="cnt">' + esc(sprintf(__('%d classes', 'nori-for-elementor'), S.design.steps.length)) + '</span></h3><div class="ladder">' + [['H1', 6], ['H3', 4], ['Lead', 1], ['Body', 0], ['Small', -1]].map(function (s) { var px = Math.max(12, Math.floor(b * Math.pow(r, s[1]) + 0.5)); return '<span style="font-size:' + Math.min(px, 24) + 'px;font-weight:' + (s[1] > 1 ? wt : 400) + '"><span class="cnt" style="display:inline-block;width:46px">' + s[0] + '</span>' + esc(__('Nori means seaweed in Japanese', 'nori-for-elementor')) + ' <span class="cnt">' + px + 'px</span></span>'; }).join('') + '</div><p class="note">' + esc(sprintf(__('%1$spx body · ratio %2$s · headings %3$s', 'nori-for-elementor'), b, r, wt)) + '</p><div class="acts"><button type="button" class="btn sm go" data-go="type">' + esc(__('Type scale', 'nori-for-elementor')) + '</button></div></section>';
		// variables
		h += '<section><h3>' + icon('variables') + esc(__('Variables', 'nori-for-elementor')) + '<span class="cnt">' + esc(sprintf(__('%1$d of %2$d', 'nori-for-elementor'), vIn, S.variables.items.length)) + '</span></h3><div class="bars">' + S.variables.groups.map(function (g) { var all = S.variables.items.filter(function (v) { return v.group === g.key; }); var n = all.filter(function (v) { return inEl(v.status); }).length, pro = all.some(function (v) { return v.status === 'needs_pro'; }); return '<div><span>' + esc(g.label) + '</span><i><b style="width:' + (all.length ? n / all.length * 100 : 0) + '%"></b></i><em>' + (pro && !n ? 'Pro' : n + '/' + all.length) + '</em></div>'; }).join('') + '</div>' + (S.site.pro ? '' : '<p class="note">' + esc(__('Spacing variables need Elementor Pro. The classes use plain numbers until then.', 'nori-for-elementor')) + '</p>') + '<div class="acts"><button type="button" class="btn sm go" data-go="variables">' + esc(__('Variables', 'nori-for-elementor')) + '</button></div></section>';
		// classes
		var missing = nc.filter(function (c) { return c.status === 'missing'; }).length;
		h += '<section><h3>' + icon('classes') + esc(__('Classes', 'nori-for-elementor')) + '<span class="cnt">' + esc(sprintf(__('%1$d of %2$d', 'nori-for-elementor'), inN, nc.length)) + '</span></h3><div class="bars">' + S.classes.groups.filter(function (g) { return nc.some(function (c) { return c.group === g.key; }); }).map(function (g) { var all = nc.filter(function (c) { return c.group === g.key; }); var n = all.filter(function (c) { return inEl(c.status); }).length, e = all.filter(function (c) { return c.status === 'edited'; }).length; return '<div><span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + esc(g.label) + '</span><i><b style="width:' + (n / all.length * 100) + '%"></b><b class="e" style="width:' + (e / all.length * 100) + '%"></b></i><em>' + n + '/' + all.length + '</em></div>'; }).join('') + '</div><p class="note"><span class="swatch" style="width:10px;height:10px;background:var(--amber);border:0"></span> ' + esc(__('edited in Elementor (updates leave them alone)', 'nori-for-elementor')) + '</p><div class="acts"><button type="button" class="btn sm go" data-go="classes">' + esc(__('Classes', 'nori-for-elementor')) + '</button>' + (missing && S.site.atomic ? '<button type="button" class="btn sm go alt" data-addmissing>' + esc(sprintf(__('Add %d missing', 'nori-for-elementor'), missing)) + '</button>' : '') + '</div></section>';
		// effects
		h += '<section><h3>' + icon('buttons') + esc(__('Motion & effects', 'nori-for-elementor')) + '<span class="cnt">' + esc(sprintf(__('%1$d of %2$d on', 'nori-for-elementor'), on.length, Object.keys(S.modules.items).length)) + '</span></h3><div style="display:grid;gap:7px">' + S.modules.categories.map(function (c) { return '<div style="display:grid;gap:3px"><button type="button" class="link" style="justify-self:start" data-go="' + catView(c.key) + '">' + esc(c.label) + ' →</button><div class="modchips">' + c.modules.map(function (k) { return '<span class="' + (modOn(k) ? 'on' : '') + '">' + esc(mod(k).label.replace(/^Button:\s*/, '')) + '</span>'; }).join('') + '</div></div>'; }).join('') + '</div></section>';
		// build
		h += '<section><h3>' + icon('templates') + esc(__('Build', 'nori-for-elementor')) + '</h3><p class="note">' + esc(__('Full page templates, each one button away from a draft page, and one header and footer for the whole site.', 'nori-for-elementor')) + '</p><div class="acts"><button type="button" class="btn sm go" data-go="templates">' + esc(__('Page templates', 'nori-for-elementor')) + '</button><button type="button" class="btn sm go alt" data-go="site-parts">' + esc(__('Header & footer', 'nori-for-elementor')) + '</button></div></section>';
		h += '</div></section>';
		var edv = S.variables.items.filter(function (v) { return v.status === 'edited'; });
		h += '<section class="card"><div class="card-h"><h2>' + esc(__('Edited in Elementor', 'nori-for-elementor')) + '</h2><span class="cnt">' + esc(__('Updates keep your edits', 'nori-for-elementor')) + '</span></div><div class="card-b chips">' + (ed.length || edv.length ? ed.slice(0, 12).map(function (c) { return '<button type="button" class="chip code" data-jump-cls="' + esc(c.key) + '">.' + esc(c.label) + '</button>'; }).join('') + edv.slice(0, 12).map(function (v) { return '<button type="button" class="chip code" data-jump-var="' + esc(v.key) + '">' + esc(v.label) + '</button>'; }).join('') : '<span class="note">' + esc(__('Nothing yet: everything is at its default.', 'nori-for-elementor')) + '</span>') + '</div></section>';
		h += '</div><div class="stack">';
		h += '<section class="card"><div class="card-h"><h2>' + esc(__('Site health', 'nori-for-elementor')) + '</h2></div><div class="card-b"><ul class="list">' + S.home.health.map(function (x) { return '<li style="flex-wrap:wrap"><span><span class="dotst ' + esc(x.status) + '"></span>' + esc(x.label) + '</span><span class="' + (x.status === 'ok' ? '' : 'note') + '">' + esc(x.value) + '</span>' + (x.status !== 'ok' ? '<span class="note" style="flex-basis:100%;text-align:left">' + esc(x.fix) + '</span>' : '') + '</li>'; }).join('') + '</ul></div></section>';
		h += '<section class="card" id="nori-pages"><div class="card-h"><h2>' + esc(__('Pages using Nori classes', 'nori-for-elementor')) + '</h2></div><div class="card-b">' + (S.home.pages.length ? '<ul class="list">' + S.home.pages.slice(0, 12).map(function (p) { return '<li><a class="link" style="text-decoration:none" href="' + esc(p.edit) + '">' + esc(p.title) + '</a><span class="cnt">' + esc(p.type) + ' · ' + esc(sprintf(_n('%d class', '%d classes', p.count, 'nori-for-elementor'), p.count)) + '</span></li>'; }).join('') + '</ul>' + (S.home.pages.length > 12 ? '<p class="note">' + esc(sprintf(__('And %d more.', 'nori-for-elementor'), S.home.pages.length - 12)) + '</p>' : '') : '<p class="note">' + esc(__('None yet. Add a Nori class to an element in the Elementor editor and save the page.', 'nori-for-elementor')) + '</p>') + '</div></section>';
		var fp = S.home.footprint, owners = {};
		fp.rows.forEach(function (r) { (owners[r.owner] = owners[r.owner] || []).push(r); });
		h += '<section class="card"><div class="card-h"><h2>' + esc(__('Front-end footprint', 'nori-for-elementor')) + '</h2><span class="cnt"><b>' + kb(fp.gzip) + '</b> ' + esc(sprintf(_n('compressed · %d file', 'compressed · %d files', fp.rows.length, 'nori-for-elementor'), fp.rows.length)) + '</span></div>' + (fp.rows.length ? '<div class="tablewrap" style="border:0"><table class="mini foot"><thead><tr><th>' + esc(__('Module', 'nori-for-elementor')) + '</th><th>' + esc(__('Files', 'nori-for-elementor')) + '</th><th style="text-align:right">' + esc(__('Size', 'nori-for-elementor')) + '</th></tr></thead><tbody>' + Object.keys(owners).map(function (o) { var s = 0; owners[o].forEach(function (f) { s += f.gzip; }); return '<tr><td>' + esc(o.replace(/^Button:\s*/, '')) + '</td><td><span class="cnt">' + owners[o].map(function (f) { return esc(f.type + ' ' + f.file.split('/').pop()); }).join(' · ') + '</span></td><td style="text-align:right" class="cnt">' + kb(s) + '</td></tr>'; }).join('') + '</tbody></table></div><div class="card-b" style="padding-top:8px"><p class="note">' + esc(__('Sizes are gzip-compressed, as most hosts send them. Scripts load deferred in the footer; only the tiny shared helper loads in the head. Switched-off modules add nothing.', 'nori-for-elementor')) + '</p></div>' : '<div class="card-b"><p class="note">' + esc(__('No module is on, so Nori adds nothing to your pages. Classes and variables are output by Elementor itself.', 'nori-for-elementor')) + '</p></div>') + '</section>';
		h += '</div></div>';
		return h;
	}

	/* ================= fields (module settings) ================= */
	function fmt(v, f) { var st = f.step && f.step < 1 ? String(f.step).split('.')[1].length : 0; return (st ? (+v).toFixed(st) : v) + (f.unit || ''); }
	function colorOptions(v, withNote) {
		var o = '<optgroup label="' + esc(__('Nori colors', 'nori-for-elementor')) + '">' + CKEYS.map(function (c) { var x = vr(c); return '<option value="var(' + c + ')"' + (v === 'var(' + c + ')' ? ' selected' : '') + '>' + esc((x ? x.label : c + '-np') + ' · ' + val('var:' + c)) + (withNote && x && !inEl(x.status) ? ' ' + esc(__('(not in Elementor yet)', 'nori-for-elementor')) : '') + '</option>'; }).join('') + '</optgroup>';
		var mine = S.colors.mine.filter(function (u) { return !u.module; });
		if (mine.length) { o += '<optgroup label="' + esc(__('Your Elementor colors', 'nori-for-elementor')) + '">' + mine.map(function (u) { return '<option value="var(' + esc(u.id) + ')"' + (v === 'var(' + u.id + ')' ? ' selected' : '') + '>' + esc(u.label + ' · ' + u.value) + '</option>'; }).join('') + '</optgroup>'; }
		return o;
	}
	function field(mk, f) {
		var m = mod(mk), k = 'set:' + mk + ':' + f.key, v = val(k), id = 'f-' + mk + '-' + f.key, h = '<div class="field" data-fk="' + esc(k) + '">';
		if (f.type === 'range') {
			h += '<label for="' + esc(id) + '">' + esc(f.label) + ' <output id="o-' + esc(mk + '-' + f.key) + '">' + esc(fmt(v, f)) + '</output></label><input id="' + esc(id) + '" type="range" min="' + f.min + '" max="' + f.max + '" step="' + (f.step || 1) + '" value="' + v + '" data-k="' + esc(k) + '" data-num data-unit="' + esc(f.unit || '') + '" data-step="' + (f.step || 1) + '" data-out="o-' + esc(mk + '-' + f.key) + '">';
			if (f.presets) { h += '<div class="pre">' + Object.keys(f.presets).map(function (p) { return '<button type="button" class="chip" data-setk="' + esc(k) + '" data-setv="' + f.presets[p] + '" aria-pressed="' + (+v === +f.presets[p]) + '">' + esc(p) + ' <span>' + f.presets[p] + esc(f.unit || '') + '</span></button>'; }).join('') + '</div>'; }
		} else if (f.type === 'toggle') {
			h += '<span class="lab">' + esc(f.label) + '<button type="button" class="sw" role="switch" data-k="' + esc(k) + '" aria-checked="' + (!!v) + '" aria-label="' + esc(f.label) + '"></button></span>';
		} else if (f.type === 'select') {
			h += '<label for="' + esc(id) + '">' + esc(f.label) + '</label><select id="' + esc(id) + '" data-k="' + esc(k) + '">' + Object.keys(f.options).map(function (o) { return '<option value="' + esc(o) + '"' + (o === v ? ' selected' : '') + '>' + esc(f.options[o]) + '</option>'; }).join('') + '</select>';
		} else if (f.type === 'color' && f.plain) {
			h += '<label for="' + esc(id) + '">' + esc(f.label) + '</label><div class="colrow"><input id="' + esc(id) + '" type="color" value="' + esc(String(v).toLowerCase()) + '" data-k="' + esc(k) + '"><code>' + esc(String(v).toUpperCase()) + '</code></div>';
		} else if (f.type === 'color') {
			var custom = !/^var\(/.test(v);
			h += '<label for="' + esc(id) + '">' + esc(f.label) + '</label><div class="colrow"><span class="swatch" style="background:' + esc(colorOf(v)) + '"></span><select id="' + esc(id) + '" data-k="' + esc(k) + '" data-colsel>' + colorOptions(v, true) + '<option value="' + esc(custom ? v : (hex(f.default) || '#D6FF00')) + '"' + (custom ? ' selected' : '') + '>' + esc(__('Custom color…', 'nori-for-elementor')) + '</option></select>' + (custom ? '<input type="color" value="' + esc(String(v).toLowerCase()) + '" data-k="' + esc(k) + '" aria-label="' + esc(sprintf(__('%s: custom color', 'nori-for-elementor'), f.label)) + '">' : '') + '</div>';
		}
		if (f.help) { h += '<span class="help">' + esc(f.help) + '</span>'; }
		if (f.variable && (f.type !== 'color' || !/^var\(/.test(v))) {
			var vs = m.varState[f.key] || 'missing', vlabel = f.variable + '-np';
			h += '<span class="vh">' + esc(vs === 'needs_pro' ? sprintf(__('With Elementor Pro, also editable in Elementor as %s.', 'nori-for-elementor'), vlabel) : inEl(vs) ? sprintf(__('Also editable in Elementor: Variables › %s', 'nori-for-elementor'), vlabel) + (m.tuned[f.key] != null ? ' · ' + sprintf(__('Fine-tuned in Elementor: %s', 'nori-for-elementor'), m.tuned[f.key]) : '') : sprintf(__('Becomes the Elementor variable %s when the module is on.', 'nori-for-elementor'), vlabel)) + '</span>';
		} else if (f.link && f.type === 'color') {
			h += '<span class="vh">' + esc(sprintf(__('Follows %s unless you pick another color.', 'nori-for-elementor'), f.link + '-np')) + '</span>';
		}
		return h + '</div>';
	}
	function swHtml(k) { return '<button type="button" class="sw" role="switch" data-k="mod:' + esc(k) + '" aria-checked="' + modOn(k) + '" aria-label="' + esc(mod(k).label) + '"></button>'; }
	function roleTarget(r) { return String(r || '').split('.')[0].replace(/^Put this on /, '').replace(/^Optional\. /, '').replace(/\s*\(.*\)\s*$/, '').replace(/^./, function (c) { return c.toLowerCase(); }); }
	function howBox(m) {
		if (!m.classes.length) { return '<div class="how"><h4>' + icon('site') + esc(__('Works across the whole site', 'nori-for-elementor')) + '</h4><p class="note" style="color:var(--text-2)">' + esc(__('Switch it on and save. There is nothing to add in Elementor.', 'nori-for-elementor')) + '</p></div>'; }
		var names = m.classes.map(function (c) { return '<code>' + esc(c.key) + '-np</code>'; }).join(', ');
		return '<div class="how"><h4>' + icon('bulb') + esc(__('Switching this on doesn\'t change your pages', 'nori-for-elementor')) + '</h4><ol>'
			+ '<li>' + sprintf(esc(__('Switch it on and press Save changes. Nori adds %s to Elementor\'s class library.', 'nori-for-elementor')), names) + '</li>'
			+ '<li>' + esc(__('Open the page or Theme Builder template in the Elementor editor.', 'nori-for-elementor')) + '</li>'
			+ '<li>' + sprintf(esc(__('Select the element (%1$s) and type %2$s in Classes, on the Style tab.', 'nori-for-elementor')), esc(roleTarget(m.classes[0].role)), '<code>' + esc(m.classes[0].key) + '-np</code>') + '</li>'
			+ '<li>' + esc(__('Update the page. Only elements with the class get the effect.', 'nori-for-elementor')) + '</li></ol></div>';
	}

	/* ================= Colors & dark mode ================= */
	function vColors() {
		var on = modOn('dark-mode'), look = siteLook(), dark1 = look === 'darkswitch', dsite = look === 'dark' || dark1;
		var altName = dark1 ? __('Light', 'nori-for-elementor') : __('Dark', 'nori-for-elementor'), mainName = dark1 ? __('Dark', 'nori-for-elementor') : (dsite ? __('Your colors', 'nori-for-elementor') : __('Light', 'nori-for-elementor'));
		var h = head(__('Design system', 'nori-for-elementor'), __('Colors & dark mode', 'nori-for-elementor'), esc(__('Your Nori colors are Elementor variables. Tell Nori what kind of site this is, and it shows only the colors you need.', 'nori-for-elementor')));
		var LOOKS = [
			['light', __('Light site', 'nori-for-elementor'), __('One set of light colors. No switch.', 'nori-for-elementor'), '#FFFFFF', '#111827'],
			['switch', __('Light site with a dark switch', 'nori-for-elementor'), __('Visitors can flip to dark with any element that has theme-toggle-np. Turns on the Dark Mode module.', 'nori-for-elementor'), 'linear-gradient(90deg,#FFFFFF 50%,#121212 50%)', '#7DD3FC'],
			['dark', __('Dark site', 'nori-for-elementor'), __('I live in the night. The darkness is my home. Your colors are dark, with no switch.', 'nori-for-elementor'), '#121212', '#F5F5F5'],
			['darkswitch', __('Dark site with a light switch', 'nori-for-elementor'), __('Your design stays dark. Visitors who prefer light can flip to a light version.', 'nori-for-elementor'), 'linear-gradient(90deg,#121212 50%,#FFFFFF 50%)', '#7DD3FC']
		];
		h += '<div class="stack"><section class="card strong"><div class="card-h"><h2>' + esc(__('What kind of site is this?', 'nori-for-elementor')) + '</h2></div><div class="card-b"><div class="looks" role="radiogroup" aria-label="' + esc(__('What kind of site is this?', 'nori-for-elementor')) + '">' + LOOKS.map(function (x) { return '<button type="button" class="preset look" role="radio" data-look="' + x[0] + '" aria-checked="' + (look === x[0]) + '" aria-pressed="' + (look === x[0]) + '"><span class="lk" style="background:' + x[3] + '"><i style="background:' + x[4] + '"></i></span><b>' + esc(x[1]) + '</b><span class="note">' + esc(x[2]) + '</span></button>'; }).join('') + '</div>'
			+ (look === 'dark' ? '<p class="note" style="margin-top:10px">' + esc(__('Presets now fill your colors with their dark palettes. Buttons, text and the class previews all use them. Changed your mind later? Pick a light option and Nori brings the light versions back.', 'nori-for-elementor')) + '</p>' : dark1 ? '<p class="note" style="margin-top:10px">' + esc(__('Your Elementor colors are your dark design. Each one gets a light version below, used only after a visitor switches. With "Match the visitor\'s system setting", people whose device is set to light see the light version from the start.', 'nori-for-elementor')) + '</p>' : '') + '</div></section>';
		var cur = val('brand');
		h += '<section class="card"><div class="card-h"><h2>' + esc(__('Start from a preset', 'nori-for-elementor')) + '</h2><p>' + esc(on ? (dark1 ? __('Sets all your colors and their light versions at once. You can fine-tune any of them below before you save.', 'nori-for-elementor') : __('Sets all your colors and their dark versions at once. You can fine-tune any of them below before you save.', 'nori-for-elementor')) : dsite ? __('Sets all your colors with the preset\'s dark palette. You can fine-tune any of them below before you save.', 'nori-for-elementor') : __('Sets all your colors at once. You can fine-tune any of them below before you save.', 'nori-for-elementor')) + '</p></div><div class="card-b" style="display:grid;gap:14px"><div class="presets">';
		Object.keys(S.design.presets).forEach(function (k) {
			var p = S.design.presets[k], P = dsite ? p.dark : p.light, Q = dark1 ? p.light : p.dark;
			h += '<button type="button" class="preset" data-preset="' + esc(k) + '" aria-pressed="' + (cur === k) + '"><span class="sw4"' + (on ? '' : ' style="grid-template-columns:repeat(4,1fr)"') + '><i style="background:' + P.accent + '"></i><i style="background:' + P.primary + '"></i><i style="background:' + P.background + '"></i><i style="background:' + P.surface + '"></i>' + (on ? '<i style="background:' + Q.background + ';--da:' + Q.accent + '"></i>' : '') + '</span><b>' + esc(p.label) + (S.design.preset === k ? ' <span class="cnt">' + esc(__('(current)', 'nori-for-elementor')) + '</span>' : '') + '</b></button>';
		});
		h += '<button type="button" class="preset" data-preset="custom" aria-pressed="' + (cur === 'custom') + '"><span class="sw4" style="grid-template-columns:1fr"><i style="background:linear-gradient(90deg,#f87171,#facc15,#4ade80,#38bdf8,#a78bfa)"></i></span><b>' + esc(__('From your brand', 'nori-for-elementor')) + '</b></button></div>';
		if (cur === 'custom') { h += '<div class="field" style="border:0;padding:0"><span class="lab">' + esc(__('From your brand color', 'nori-for-elementor')) + '</span><div class="colrow"><input type="color" id="nori-brand-a" value="' + esc(val('brand:accent').toLowerCase()) + '" aria-label="' + esc(__('Brand color', 'nori-for-elementor')) + '"><span class="note">' + esc(__('Brand color', 'nori-for-elementor')) + '</span><input type="color" id="nori-brand-i" value="' + esc(val('brand:ink').toLowerCase()) + '" aria-label="' + esc(__('Dark (ink) color', 'nori-for-elementor')) + '"><span class="note">' + esc(__('Dark (ink) color', 'nori-for-elementor')) + '</span><button type="button" class="btn sm primary" data-brand-go>' + esc(__('Generate colors', 'nori-for-elementor')) + '</button></div><span class="help">' + esc(__('Nori fills all your colors below from these two, so text stays readable on every background and button.', 'nori-for-elementor')) + '</span></div>'; }
		h += '<div class="duo"' + (on ? '' : ' style="grid-template-columns:minmax(0,1fr)"') + '>' + dprev(false, dark1 ? __('Dark (your design)', 'nori-for-elementor') : dsite ? __('Your site (dark)', 'nori-for-elementor') : on ? __('Light (your design)', 'nori-for-elementor') : __('Light', 'nori-for-elementor')) + (on ? dprev(true, dark1 ? __('Light (when visitors switch)', 'nori-for-elementor') : __('Dark (when visitors switch)', 'nori-for-elementor')) : '') + '</div></div></section>';
		h += '<section class="card" id="nori-colors-table"><div class="card-h"><h2>' + esc(on ? sprintf(__('Nori colors, %1$s and %2$s', 'nori-for-elementor'), mainName.toLowerCase(), altName.toLowerCase()) : __('Nori colors', 'nori-for-elementor')) + '</h2><p>' + esc(on ? sprintf(__('%1$s values are the Elementor variables (also editable in Elementor under Variables). %2$s values are used only after a visitor switches; the Elementor editor always shows your own design.', 'nori-for-elementor'), mainName, altName) : __('These are the Elementor variables, also editable in Elementor under Variables.', 'nori-for-elementor')) + '</p></div><div class="tablewrap" style="border:0"><table class="ctab"><thead><tr><th>' + esc(__('Variable', 'nori-for-elementor')) + '</th><th class="' + (dsite ? 'dk' : 'lt') + '">' + esc(mainName + (on ? ' ' + __('(your design)', 'nori-for-elementor') : '')) + '</th>' + (on ? '<th class="arrow hide-s"></th><th class="' + (dark1 ? 'lt' : 'dk') + '">' + esc(altName) + '</th>' : '') + '<th class="hide-s">' + esc(__('Used for', 'nori-for-elementor')) + '</th></tr></thead><tbody>';
		CKEYS.forEach(function (k) {
			var v = vr(k), dk = 'set:dark-mode:' + S.design.darkKeys[k];
			if (!v) { return; }
			h += '<tr><td class="nm"><code>' + esc(v.label) + '</code>' + (v.status === 'edited' ? ' <span class="pill edited">' + esc(__('Edited', 'nori-for-elementor')) + '</span>' : !inEl(v.status) ? ' <span class="pill">' + esc(__('Added when you save', 'nori-for-elementor')) + '</span>' : '') + '</td><td class="' + (dsite ? 'dk' : 'lt') + (changed('var:' + k) ? ' chg' : '') + '"><span class="vin"><input type="color" data-k="var:' + k + '" value="' + esc(val('var:' + k).toLowerCase()) + '" aria-label="' + esc(v.label) + '"><code>' + esc(val('var:' + k)) + '</code></span></td>' + (on ? '<td class="arrow hide-s">→</td><td class="' + (dark1 ? 'lt' : 'dk') + (changed(dk) ? ' chg' : '') + '"><span class="vin"><input type="color" data-k="' + dk + '" value="' + esc(String(val(dk)).toLowerCase()) + '" aria-label="' + esc(v.label + ' (' + altName.toLowerCase() + ')') + '"><code>' + esc(String(val(dk)).toUpperCase()) + '</code></span></td>' : '') + '<td class="hide-s"><span class="note">' + esc(v.description) + '</span></td></tr>';
		});
		h += '</tbody></table></div></section>';
		if (!on) { return h + '</div>'; }
		var mine = S.colors.mine.filter(function (u) { return !u.module; }), mods = S.colors.mine.filter(function (u) { return u.module; });
		h += '<section class="card"><div class="card-h"><h2>' + esc(__('Your own Elementor colors after switching', 'nori-for-elementor')) + '</h2>' + (mine.length ? '<button type="button" class="btn sm" data-suggest-all>' + esc(__('Suggest for the rest', 'nori-for-elementor')) + '</button>' : '') + '<p>' + esc(dark1 ? __('Every color variable you add in Elementor shows up here. Each stays the same until you give it a light version: a custom color, or one of your other colors. Colors typed straight into a widget (not a variable) can\'t change.', 'nori-for-elementor') : __('Every color variable you add in Elementor shows up here. Each stays the same until you give it a dark version: a custom color, or one of your other colors. Colors typed straight into a widget (not a variable) can\'t change.', 'nori-for-elementor')) + '</p></div>';
		h += mine.length ? '<div class="tablewrap" style="border:0"><table class="ctab"><thead><tr><th>' + esc(__('Your color', 'nori-for-elementor')) + '</th><th class="lt">' + esc(mainName) + '</th><th class="arrow hide-s"></th><th>' + esc(altName) + '</th></tr></thead><tbody>' + mine.map(ucolRow).join('') + '</tbody></table></div>' : '<div class="card-b"><p class="note">' + esc(__('You haven\'t added color variables of your own in Elementor yet. Ones you add show up here, ready for a second version.', 'nori-for-elementor')) + '</p></div>';
		if (mods.length) { h += '<details class="fold" id="nori-modfold" style="margin:12px 16px 16px"' + (FOLD ? ' open' : '') + '><summary>' + esc(sprintf(__('Custom module colors (%d)', 'nori-for-elementor'), mods.length)) + '</summary><div class="tablewrap" style="border:0"><table class="ctab"><tbody>' + mods.map(ucolRow).join('') + '</tbody></table></div></details>'; }
		h += '</section>';
		var dm = mod('dark-mode');
		h += '<section class="card"><div class="card-h"><h2>' + esc(__('How the switch behaves', 'nori-for-elementor')) + '</h2></div><div class="mod-grid"><div>' + dm.fields.filter(function (f) { return !f.plain; }).map(function (f) { return field('dark-mode', f); }).join('') + '</div><div>' + howBox(dm) + '<div class="kbd-cls">' + dm.classes.map(function (c) { return '<div><code>' + esc(c.key) + '-np</code><span>' + esc(c.role) + '</span></div>'; }).join('') + '</div></div></div></section>';
		return h + '</div>';
	}
	function dprev(alt, label) {
		var g = function (k) { return alt ? darkOf(k) : val('var:' + k); };
		return '<div class="dprev" style="background:' + g('background') + ';color:' + g('text') + ';border-color:' + g('line') + '"><span class="cnt" style="color:' + g('muted') + '">' + esc(label) + '</span><h3 style="color:' + g('primary') + '">' + esc(__('A headline in your primary color', 'nori-for-elementor')) + '</h3><p style="color:' + g('muted') + '">' + esc(__('Muted copy on the page background.', 'nori-for-elementor')) + '</p><div class="surf" style="background:' + g('surface') + ';border:1px solid ' + g('line') + '"><span class="b" style="background:' + g('accent') + ';border-color:' + g('accent') + ';color:' + g('on-accent') + '">btn-primary-np</span><span class="b" style="background:' + g('primary') + ';border-color:' + g('primary') + ';color:' + g('background') + '">btn-secondary-np</span><span class="b" style="background:transparent;border-color:' + g('primary') + ';color:' + g('primary') + '">btn-outline-np</span></div></div>';
	}
	function ucolRow(u) {
		var k = 'ucol:' + u.id, v = val(k), custom = !!hex(v), alt = val('dm:base') === 'dark';
		var opts = '<option value="same"' + (v === 'same' ? ' selected' : '') + '>' + esc(alt ? __('Same in light', 'nori-for-elementor') : __('Same in dark', 'nori-for-elementor')) + '</option><optgroup label="' + esc(alt ? __('Nori colors (their light values)', 'nori-for-elementor') : __('Nori colors (their dark values)', 'nori-for-elementor')) + '">' + CKEYS.map(function (c) { return '<option value="var(' + c + ')"' + (v === 'var(' + c + ')' ? ' selected' : '') + '>' + esc(c + '-np · ' + String(darkOf(c)).toUpperCase()) + '</option>'; }).join('') + '</optgroup><optgroup label="' + esc(__('Your colors', 'nori-for-elementor')) + '">' + S.colors.mine.filter(function (x) { return x.id !== u.id && !x.module; }).map(function (x) { return '<option value="var(' + esc(x.id) + ')"' + (v === 'var(' + x.id + ')' ? ' selected' : '') + '>' + esc(x.label + ' · ' + x.value) + '</option>'; }).join('') + '</optgroup><option value="custom"' + (custom ? ' selected' : '') + '>' + esc(__('Custom color…', 'nori-for-elementor')) + '</option>';
		var m = v.match(/^var\(([a-z0-9-]+)\)$/), dv = v === 'same' ? u.value : custom ? v : m && CKEYS.indexOf(m[1]) > -1 ? darkOf(m[1]) : colorOf(v);
		return '<tr><td class="nm"><code>' + esc(u.label) + '</code></td><td class="lt"><span class="vin"><span class="swatch" style="background:' + esc(u.value) + '"></span><code>' + esc(u.value) + '</code></span></td><td class="arrow hide-s">→</td><td class="' + (changed(k) ? 'chg' : '') + '"><div class="ucol"><span class="swatch" style="background:' + esc(dv) + '"></span><select data-k="' + esc(k) + '" data-ucol="' + esc(u.id) + '" aria-label="' + esc(sprintf(__('Second version of %s', 'nori-for-elementor'), u.label)) + '">' + opts + '</select>' + (custom ? '<input type="color" data-ucustom="' + esc(u.id) + '" value="' + esc(v.toLowerCase()) + '" aria-label="' + esc(__('Custom color', 'nori-for-elementor')) + '">' : '') + '<button type="button" class="link" data-suggest="' + esc(u.id) + '">' + esc(__('Suggest', 'nori-for-elementor')) + '</button></div></td></tr>';
	}

	/* ================= Type scale ================= */
	function tpx(b, r, s) { return Math.max(12, Math.floor(b * Math.pow(r, s) + 0.5)); }
	function vType() {
		var b = val('ts:base'), r = val('ts:ratio'), wt = val('ts:weight');
		var h = head(__('Design system', 'nori-for-elementor'), __('Type scale', 'nori-for-elementor'), esc(sprintf(__('%d type classes from one body size and one ratio. Headings shrink on tablet and mobile on a gentler ratio; body text and smaller keep one size. The font family stays in Elementor\'s Site Settings > Typography.', 'nori-for-elementor'), S.design.steps.length)));
		var sizes = []; for (var i = 14; i <= 20; i++) { sizes.push(i); }
		h += '<div class="type-wrap"><section class="card"><div class="card-b ts-ctl">'
			+ '<div class="field" data-fk="ts:base"><label for="nori-tb">' + esc(__('Body size', 'nori-for-elementor')) + '</label><select id="nori-tb" data-k="ts:base" data-num>' + sizes.map(function (x) { return '<option value="' + x + '"' + (x === b ? ' selected' : '') + '>' + x + 'px</option>'; }).join('') + '</select></div>'
			+ '<div class="field" data-fk="ts:ratio"><label for="nori-tr">' + esc(__('Scale', 'nori-for-elementor')) + '</label><select id="nori-tr" data-k="ts:ratio">' + Object.keys(S.design.ratios).map(function (x) { return '<option value="' + esc(x) + '"' + (x === r ? ' selected' : '') + '>' + esc(S.design.ratios[x]) + '</option>'; }).join('') + '</select></div>'
			+ '<div class="field" data-fk="ts:weight"><label for="nori-tw">' + esc(__('Heading weight', 'nori-for-elementor')) + '</label><select id="nori-tw" data-k="ts:weight">' + S.design.weights.map(function (x) { return '<option value="' + esc(x) + '"' + (String(x) === wt ? ' selected' : '') + '>' + esc(x) + '</option>'; }).join('') + '</select></div>'
			+ '</div><div class="card-b" style="border-top:1px solid var(--line);padding-block:10px"><p class="note">' + esc(__('Saving updates the type classes in Elementor. A type class you edited in Elementor keeps your edits; use Reset on it under Classes to put it on the new scale.', 'nori-for-elementor')) + '</p></div></section><section class="card" id="nori-trows"></section></div>';
		return h;
	}
	var SAMPLE = function (k) {
		return { 'type-display': __('Display', 'nori-for-elementor'), 'type-subtitle': __('A subtitle that sets up the section', 'nori-for-elementor'), 'type-lead': __('A lead paragraph opens the page.', 'nori-for-elementor'), 'type-body': __('Body copy reads comfortably at this size.', 'nori-for-elementor'), 'type-small': __('Small print for notes and details.', 'nori-for-elementor'), 'type-caption': __('Caption under an image', 'nori-for-elementor'), 'type-overline': __('Overline label', 'nori-for-elementor'), 'type-label': __('Form label', 'nori-for-elementor') }[k] || sprintf(__('Heading %s', 'nori-for-elementor'), k.slice(6).toUpperCase());
	};
	function drawType() {
		var el = $('nori-trows'); if (!el) { return; }
		var b = val('ts:base'), r = +val('ts:ratio'), wt = val('ts:weight');
		el.innerHTML = '<div class="trow" style="background:var(--sunken)"><span class="sec-h">' + esc(__('Class', 'nori-for-elementor')) + '</span><span class="sec-h">' + esc(__('Desktop / tablet / mobile', 'nori-for-elementor')) + '</span><span class="sec-h">' + esc(__('Sample', 'nori-for-elementor')) + '</span></div>' + S.design.steps.map(function (s) {
			var key = s[0], step = s[1], px = tpx(b, r, step), pt = step > 0 ? tpx(b, 1 + (r - 1) * 0.8, step) : px, pm = step > 0 ? tpx(b, 1 + (r - 1) * 0.6, step) : px;
			var w8 = s[3] === 'h' ? wt : s[3], c = cls(key), ed = c && c.status === 'edited';
			return '<div class="trow"><code>' + esc(key) + '-np' + (ed ? ' <span class="pill edited">' + esc(__('Edited', 'nori-for-elementor')) + '</span>' : '') + '</code><span class="sz">' + px + ' / ' + pt + ' / ' + pm + 'px</span><span class="sm" style="font-size:' + Math.min(px, 64) + 'px;line-height:' + s[2] + ';font-weight:' + esc(w8) + (s[4] === 'uppercase' ? ';text-transform:uppercase;letter-spacing:.12em' : '') + '">' + esc(SAMPLE(key)) + '</span></div>';
		}).join('');
	}

	/* ================= Variables ================= */
	function actLabel(a) { return { add: __('Will be added', 'nori-for-elementor'), reset: __('Will be reset', 'nori-for-elementor'), 'delete': __('Will be deleted', 'nori-for-elementor') }[a] || ''; }
	function cbx(k, st) {
		if (st === 'needs_pro') { return '<input type="checkbox" class="cb" disabled title="' + esc(__('Needs Elementor Pro', 'nori-for-elementor')) + '" aria-label="' + esc(__('Needs Elementor Pro', 'nori-for-elementor')) + '">'; }
		if (inEl(st)) { return '<input type="checkbox" class="cb" checked disabled title="' + esc(__('Already in Elementor', 'nori-for-elementor')) + '" aria-label="' + esc(__('Already in Elementor', 'nori-for-elementor')) + '">'; }
		return '<input type="checkbox" class="cb" data-addk="' + esc(k) + '"' + (val(k) === 'add' ? ' checked' : '') + ' title="' + esc(__('Add to Elementor when you save', 'nori-for-elementor')) + '" aria-label="' + esc(__('Add to Elementor when you save', 'nori-for-elementor')) + '">';
	}
	function rowActs(k, st, big) {
		var a = val(k), c = big ? 'btn sm' : 'link', h = '';
		if (st === 'needs_pro') { return ''; }
		if (!inEl(st)) { return big ? '<button type="button" class="btn sm' + (a === 'add' ? ' primary' : '') + '" data-act="' + esc(k) + '" data-v="add">' + esc(a === 'add' ? __('Will be added · undo', 'nori-for-elementor') : __('Add to Elementor', 'nori-for-elementor')) + '</button>' : ''; }
		if (st === 'edited') { h += '<button type="button" class="' + c + '" data-act="' + esc(k) + '" data-v="reset">' + esc(a === 'reset' ? __('Keep edits', 'nori-for-elementor') : __('Reset', 'nori-for-elementor')) + '</button>'; }
		h += '<button type="button" class="' + c + ' danger' + (a === 'delete' && big ? ' on' : '') + '" data-act="' + esc(k) + '" data-v="delete">' + esc(a === 'delete' ? __('Keep', 'nori-for-elementor') : __('Delete', 'nori-for-elementor')) + '</button>';
		return h;
	}
	function catHead(title, desc, items, noun) {
		var miss = items.filter(function (i) { return i.st === 'missing' || i.st === 'deleted'; }), ed = items.filter(function (i) { return i.st === 'edited'; }), inN = items.filter(function (i) { return inEl(i.st); });
		var keys = function (arr) { return esc(JSON.stringify(arr.map(function (i) { return i.a; }))); };
		return '<div class="cathead"><div><h2>' + esc(title) + '</h2>' + (desc ? '<p>' + esc(desc) + '</p>' : '') + '<span class="meta">' + esc(sprintf(__('%1$d of %2$d in Elementor', 'nori-for-elementor'), inN.length, items.length) + (ed.length ? ' · ' + sprintf(__('%d edited', 'nori-for-elementor'), ed.length) : '')) + '</span></div><div class="bulk">'
			+ '<button type="button" class="btn xs" data-bulk="add" data-keys="' + keys(miss) + '"' + (miss.length && S.site.atomic ? '' : ' disabled') + ' title="' + esc(noun === 'class' ? __('Select every class that isn\'t in Elementor yet', 'nori-for-elementor') : __('Select every variable that isn\'t in Elementor yet', 'nori-for-elementor')) + '">' + esc(__('Enable all', 'nori-for-elementor')) + '</button>'
			+ '<button type="button" class="btn xs" data-bulk="clear" data-keys="' + keys(miss) + '"' + (miss.length ? '' : ' disabled') + ' title="' + esc(__('Clear the selection', 'nori-for-elementor')) + '">' + esc(__('Disable all', 'nori-for-elementor')) + '</button>'
			+ '<button type="button" class="btn xs" data-bulk="reset" data-keys="' + keys(ed) + '"' + (ed.length ? '' : ' disabled') + ' title="' + esc(noun === 'class' ? __('Put every edited class back to its default look', 'nori-for-elementor') : __('Put every edited variable back to its default value', 'nori-for-elementor')) + '">' + esc(__('Reset all', 'nori-for-elementor')) + '</button>'
			+ '<button type="button" class="btn xs danger" data-bulk="delete" data-keys="' + keys(inN) + '"' + (inN.length ? '' : ' disabled') + ' title="' + esc(noun === 'class' ? __('Delete every class here from Elementor', 'nori-for-elementor') : __('Delete every variable here from Elementor', 'nori-for-elementor')) + '">' + esc(__('Delete all', 'nori-for-elementor')) + '</button></div></div>';
	}
	function vVariables() {
		if (!vtab || !S.variables.groups.some(function (g) { return g.key === vtab; })) { vtab = S.variables.groups[0].key; }
		var g = S.variables.groups.filter(function (x) { return x.key === vtab; })[0], list = S.variables.items.filter(function (v) { return v.group === vtab; });
		var h = head(__('Design system', 'nori-for-elementor'), __('Variables', 'nori-for-elementor'), esc(__('Elementor variables the classes are built on. Classes connect to them on desktop, so changing one updates every class that uses it.', 'nori-for-elementor')));
		h += '<div class="toolbar"><div class="chips" role="group" aria-label="' + esc(__('Variable groups', 'nori-for-elementor')) + '">' + S.variables.groups.map(function (x) { return '<button type="button" class="chip" data-vtab="' + esc(x.key) + '" aria-pressed="' + (x.key === vtab) + '">' + esc(x.label) + '<span>' + S.variables.items.filter(function (v) { return v.group === x.key; }).length + '</span></button>'; }).join('') + '</div></div>';
		if (list.some(function (v) { return v.status === 'needs_pro'; })) { h += callout('info', 'info', '<span>' + esc(__('Size variables are an Elementor Pro feature, so they can\'t be added on this site. The classes that would use them keep plain numbers instead, which you can still edit in Elementor.', 'nori-for-elementor')) + '</span>') + gap(); }
		if (list.some(function (v) { return CKEYS.indexOf(v.key) > -1; })) { h += callout('volt', 'colors', '<span>' + esc(__('Want to change these with their dark versions side by side?', 'nori-for-elementor')) + ' <button type="button" class="link" data-go="colors">' + esc(__('Open Colors & dark mode', 'nori-for-elementor')) + '</button></span>') + gap(); }
		h += catHead(g.label, g.description, list.map(function (v) { return { k: v.key, st: v.status, a: 'vact:' + v.key }; }), 'variable');
		h += '<div class="tablewrap"><table><thead><tr><th class="cbx"></th><th>' + esc(__('Variable', 'nori-for-elementor')) + '</th><th>' + esc(__('Value', 'nori-for-elementor')) + '</th><th class="hide-s">' + esc(__('Used by', 'nori-for-elementor')) + '</th><th>' + esc(__('Status', 'nori-for-elementor')) + '</th><th class="hide-s"></th><th></th></tr></thead><tbody>';
		list.forEach(function (v) {
			var st = v.status, a = val('vact:' + v.key), open = OPEN['v:' + v.key], cur = val('var:' + v.key);
			h += '<tr class="it' + (open ? ' open' : '') + (a ? ' pend' : '') + '" data-row="v:' + esc(v.key) + '"><td class="cbx">' + cbx('vact:' + v.key, st) + '</td><td class="name"><code>' + esc(v.label) + '</code></td><td class="mono">' + (v.type === 'color' ? '<span class="swatch" style="background:' + esc(cur) + '"></span> ' : '') + esc(cur) + (st === 'edited' ? ' <span class="note">' + esc(sprintf(__('default %s', 'nori-for-elementor'), v['default'])) + '</span>' : '') + '</td><td class="use hide-s">' + esc(sprintf(_n('%d class', '%d classes', v.usedBy.length, 'nori-for-elementor'), v.usedBy.length)) + '</td><td class="st">' + (a ? '<span class="pendlab">' + esc(actLabel(a)) + '<button type="button" class="link" data-undo="vact:' + esc(v.key) + '">' + esc(__('Undo', 'nori-for-elementor')) + '</button></span>' : pill(st)) + '</td><td class="acts hide-s">' + rowActs('vact:' + v.key, st) + '</td><td class="chev">' + icon('chev') + '</td></tr>';
			if (open) {
				h += '<tr class="detail"><td colspan="7"><div class="dt"><div><h3>' + esc(__('Value', 'nori-for-elementor')) + '</h3>' + (v.type === 'color'
					? '<span class="vin"><input type="color" data-k="var:' + esc(v.key) + '" value="' + esc(cur.toLowerCase()) + '" aria-label="' + esc(v.label) + '"><code>' + esc(cur) + '</code></span>' + (CKEYS.indexOf(v.key) > -1 && modOn('dark-mode') ? '<p class="note">' + esc(val('dm:base') === 'dark' ? __('Light version:', 'nori-for-elementor') : __('In dark mode:', 'nori-for-elementor')) + ' <span class="swatch" style="width:12px;height:12px;background:' + esc(darkOf(v.key)) + '"></span> <code>' + esc(String(darkOf(v.key)).toUpperCase()) + '</code> · <button type="button" class="link" data-go="colors" data-scroll="nori-colors-table">' + esc(__('Change', 'nori-for-elementor')) + '</button></p>' : '')
					: '<input class="input" data-k="var:' + esc(v.key) + '" value="' + esc(cur) + '" aria-label="' + esc(v.label) + '" style="max-width:160px"' + (st === 'needs_pro' ? ' disabled' : '') + '>') + '<p class="note">' + esc(v.description) + '</p>' + (st === 'edited' ? '<p class="note">' + esc(sprintf(__('Now %1$s · default %2$s. Changed in Elementor.', 'nori-for-elementor'), cur, v['default'])) + '</p>' : '') + '</div>'
					+ '<div><h3>' + esc(sprintf(_n('Used by %d class', 'Used by %d classes', v.usedBy.length, 'nori-for-elementor'), v.usedBy.length)) + '</h3><div class="chips">' + (v.usedBy.length ? v.usedBy.slice(0, 24).map(function (ck) { var c = cls(ck); return c ? '<button type="button" class="chip code" data-jump-cls="' + esc(ck) + '">.' + esc(c.label) + '</button>' : ''; }).join('') : '<span class="note">' + esc(__('No classes use it yet.', 'nori-for-elementor')) + '</span>') + '</div></div>'
					+ '<div><h3>' + esc(__('Status', 'nori-for-elementor')) + '</h3>' + pill(st) + (st === 'needs_pro' ? '<p class="note">' + esc(__('Without Pro, classes use the number directly.', 'nori-for-elementor')) + '</p>' : '') + '<div class="chips">' + rowActs('vact:' + v.key, st, true) + '</div></div></div></td></tr>';
			}
		});
		h += '</tbody></table></div><p class="foot-note">' + esc(__('If you delete a variable, the classes that use it keep its value as a plain number and show as edited; Reset reconnects them once the variable is back.', 'nori-for-elementor')) + '</p>';
		return h;
	}

	/* ================= Classes ================= */
	function vClasses() {
		if (!S.site.atomic) { return head(__('Design system', 'nori-for-elementor'), __('Classes', 'nori-for-elementor'), '') + callout('warn', 'warn', '<span>' + esc(__('Elementor\'s Atomic (version 4) editor isn\'t active on this site. Turn it on in Elementor\'s settings to use Nori classes. Modules work either way.', 'nori-for-elementor')) + '</span>'); }
		var list = S.classes.items.filter(function (c) { return !c.module; });
		var groups = S.classes.groups.filter(function (g) { return list.some(function (c) { return c.group === g.key; }); });
		var h = head(__('Design system', 'nori-for-elementor'), __('Classes', 'nori-for-elementor'), esc(__('Elementor global classes Nori adds and keeps track of. Click a row to see its values and the variables it uses.', 'nori-for-elementor')));
		h += '<div class="toolbar"><div class="chips" role="group" aria-label="' + esc(__('Group', 'nori-for-elementor')) + '"><button type="button" class="chip" data-group="all" aria-pressed="' + (filt.group === 'all') + '">' + esc(__('All', 'nori-for-elementor')) + '<span>' + list.length + '</span></button>';
		groups.forEach(function (g) { h += '<button type="button" class="chip" data-group="' + esc(g.key) + '" aria-pressed="' + (filt.group === g.key) + '">' + esc(g.label) + '<span>' + list.filter(function (c) { return c.group === g.key; }).length + '</span></button>'; });
		h += '</div><div class="grp"><select class="filter" id="nori-stf" aria-label="' + esc(__('Status', 'nori-for-elementor')) + '"><option value="all">' + esc(__('Any status', 'nori-for-elementor')) + '</option><option value="default">' + esc(__('In Elementor', 'nori-for-elementor')) + '</option><option value="edited">' + esc(__('Edited', 'nori-for-elementor')) + '</option><option value="missing">' + esc(__('Not in Elementor', 'nori-for-elementor')) + '</option></select><input class="filter" id="nori-cf" type="search" placeholder="' + esc(__('Filter classes', 'nori-for-elementor')) + '" value="' + esc(filt.q) + '" aria-label="' + esc(__('Filter classes', 'nori-for-elementor')) + '"></div></div>';
		var q = filt.q.toLowerCase(), shown = [], rows = '';
		groups.forEach(function (g) {
			if (filt.group !== 'all' && filt.group !== g.key) { return; }
			var items = list.filter(function (c) { return c.group === g.key && (!q || (c.label + ' ' + c.description + ' ' + c.spec).toLowerCase().indexOf(q) > -1) && (filt.status === 'all' || filt.status === c.status); });
			if (!items.length) { return; }
			shown = shown.concat(items);
			if (filt.group === 'all') { rows += '<tr class="group-row"><td colspan="7">' + esc(g.label) + ' · ' + items.length + '</td></tr>'; }
			items.forEach(function (c) { rows += classRow(c); });
		});
		var gObj = IDX.cgroup[filt.group];
		if (filt.group === 'buttons') { h += callout('volt', 'buttons', '<span><b>' + esc(__('Hover and press effects', 'nori-for-elementor')) + '</b> ' + esc(__('(Ripple Fill, Border Glow, Sliding Underline, Gradient Shift, Text Roll) are modules you add next to a look class.', 'nori-for-elementor')) + ' <button type="button" class="link" data-go="m-buttons">' + esc(__('Button effects', 'nori-for-elementor')) + '</button></span>') + gap(); }
		if (filt.group === 'type') { h += callout('volt', 'type', '<span>' + esc(__('These sizes come from your type scale.', 'nori-for-elementor')) + ' <button type="button" class="link" data-go="type">' + esc(__('Change the scale', 'nori-for-elementor')) + '</button></span>') + gap(); }
		h += catHead(gObj ? gObj.label : __('All classes', 'nori-for-elementor'), gObj ? gObj.description : __('Bulk buttons act on the classes shown.', 'nori-for-elementor'), shown.map(function (c) { return { k: c.key, st: c.status, a: 'cact:' + c.key }; }), 'class');
		h += '<div class="tablewrap"><table><thead><tr><th class="cbx"></th><th>' + esc(__('Class', 'nori-for-elementor')) + '</th><th class="hide-s">' + esc(__('Sets', 'nori-for-elementor')) + '</th><th class="hide-s">' + esc(__('Used', 'nori-for-elementor')) + '</th><th>' + esc(__('Status', 'nori-for-elementor')) + '</th><th class="hide-s"></th><th></th></tr></thead><tbody>' + (rows || '<tr><td colspan="7" class="note">' + esc(__('No classes match.', 'nori-for-elementor')) + '</td></tr>') + '</tbody></table></div>';
		h += '<p class="foot-note">' + esc(__('Adding a class never overwrites one already in Elementor, so your edits in the editor are kept. Reset puts a class back to its default look; its name and the elements that use it stay the same.', 'nori-for-elementor')) + '</p>';
		return h;
	}
	function classRow(c) {
		var st = c.status, k = 'cact:' + c.key, a = val(k), open = OPEN['c:' + c.key];
		var h = '<tr class="it' + (open ? ' open' : '') + (a ? ' pend' : '') + '" data-row="c:' + esc(c.key) + '"><td class="cbx">' + cbx(k, st) + '</td><td class="name"><code>.' + esc(c.label) + '</code></td><td class="spec hide-s">' + esc(c.spec) + '</td><td class="use hide-s">' + (!inEl(st) ? '<span class="note">–</span>' : (c.usage ? esc(sprintf(_n('On %d page', 'On %d pages', c.usage, 'nori-for-elementor'), c.usage)) : '<span class="note">' + esc(__('Not used yet', 'nori-for-elementor')) + '</span>')) + '</td><td class="st">' + (a ? '<span class="pendlab">' + esc(actLabel(a)) + '<button type="button" class="link" data-undo="' + esc(k) + '">' + esc(__('Undo', 'nori-for-elementor')) + '</button></span>' : pill(st)) + '</td><td class="acts hide-s">' + rowActs(k, st) + '</td><td class="chev">' + icon('chev') + '</td></tr>';
		if (open) { h += '<tr class="detail"><td colspan="7">' + classDetail(c) + '</td></tr>'; }
		return h;
	}
	function classDetail(c) {
		var st = c.status, k = 'cact:' + c.key;
		var cols = ['desktop', 'tablet', 'mobile', 'desktop:hover', 'desktop:active'].filter(function (b) { return c.styles[b]; });
		var props = {}; cols.forEach(function (b) { Object.keys(c.styles[b]).forEach(function (p) { props[p] = 1; }); });
		var t = '<table class="bp"><thead><tr><th>' + esc(__('Property', 'nori-for-elementor')) + '</th>' + cols.map(function (b) { return '<th>' + esc(b.replace('desktop:', '')) + '</th>'; }).join('') + '</tr></thead><tbody>' + Object.keys(props).map(function (p) { return '<tr><td>' + esc(p) + '</td>' + cols.map(function (b) { var v = (c.styles[b] || {})[p]; return '<td>' + (v == null ? '<span class="dash">–</span>' : esc(String(v).replace(/var\(([a-z0-9-]+)\)/g, '$1-np'))) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table>';
		var chips = c.vars.map(function (v) {
			var x = vr(v), s = x ? x.status : 'missing', klass = s === 'needs_pro' ? 'st-pro' : inEl(s) ? '' : 'st-add';
			var tip = s === 'needs_pro' ? __('Needs Elementor Pro: the class uses a plain number instead', 'nori-for-elementor') : inEl(s) ? __('Connected: the class follows this variable on desktop', 'nori-for-elementor') : __('Not in Elementor yet: added together with this class', 'nori-for-elementor');
			return '<button type="button" class="chip code ' + klass + '"' + (x ? ' data-jump-var="' + esc(v) + '"' : '') + ' title="' + esc(tip) + '">' + (s === 'needs_pro' ? '⌁ ' : inEl(s) ? '● ' : '+ ') + esc(x ? x.label : v + '-np') + '</button>';
		}).join('');
		return '<div class="dt"><div><h3>' + esc(__('Preview', 'nori-for-elementor')) + '</h3><div class="prev">' + preview(c) + '</div><p class="note">' + esc(c.description) + '</p></div><div><h3>' + esc(__('Values per breakpoint', 'nori-for-elementor')) + '</h3><div style="overflow-x:auto">' + t + '</div>' + (st === 'edited' ? '<p class="note">' + esc(__('These are Nori\'s defaults. You changed the class in Elementor; updates leave it alone, and Reset puts these back.', 'nori-for-elementor')) + '</p>' : '') + '</div><div><h3>' + esc(__('Variables it uses', 'nori-for-elementor')) + '</h3><div class="chips">' + (chips || '<span class="note">' + esc(__('None: plain values only.', 'nori-for-elementor')) + '</span>') + '</div>' + (chips ? '<p class="note">' + esc(__('● connected · + added with the class · ⌁ needs Pro', 'nori-for-elementor')) + '</p>' : '') + '<h3>' + esc(__('Use it', 'nori-for-elementor')) + '</h3><p class="note">' + sprintf(esc(__('In the Elementor editor, select an element and type %s in Classes (Style tab).', 'nori-for-elementor')), '<code>' + esc(c.label) + '</code>') + (c.usage ? ' ' + esc(sprintf(_n('Used on %d page now.', 'Used on %d pages now.', c.usage, 'nori-for-elementor'), c.usage)) : '') + '</p><div class="chips">' + rowActs(k, st, true) + '</div></div></div>';
	}
	function preview(c) {
		var k = c.key, D = c.styles.desktop || {};
		if (/^btn-/.test(k)) { return '<span class="pv-btn' + (k === 'btn-secondary' ? ' dark' : k === 'btn-outline' ? ' outline' : k === 'btn-text' ? ' text' : '') + '"' + (k === 'btn-pill' ? ' style="border-radius:999px"' : k === 'btn-sharp' ? ' style="border-radius:0"' : k === 'btn-sm' ? ' style="padding:7px 14px;font-size:13px"' : k === 'btn-lg' ? ' style="padding:16px 30px;font-size:17px"' : '') + '>' + esc(__('Book a visit →', 'nori-for-elementor')) + '</span>'; }
		if (/^gap-/.test(k)) { var g = String(D.gap || '14px'); var mm = g.match(/^var\(([a-z0-9-]+)\)$/); if (mm && vr(mm[1])) { g = vr(mm[1]).value; } if (!/^\d/.test(g)) { g = '14px'; } return '<div class="pv-gap" style="gap:' + esc(g) + '"><i></i><i></i><i></i></div>'; }
		if (/^type-/.test(k)) { return '<div class="pv-type" style="font-size:min(' + esc(D['font-size'] || '16px') + ',44px);font-weight:' + esc(D['font-weight'] || 700) + (D['text-transform'] ? ';text-transform:' + esc(D['text-transform']) : '') + '">' + esc(__('Nori means seaweed in Japanese', 'nori-for-elementor')) + '</div>'; }
		if (/^fx-/.test(k)) { return '<div class="pv-box" style="padding:14px;box-shadow:0 10px 24px rgba(0,0,0,.12);transform:translateY(-4px)"><i></i></div>'; }
		if (/padding|section-|container/.test(k)) { return '<div class="pv-box" style="padding:18px"><i></i></div>'; }
		if (/text-|caps|sr-only/.test(k)) { return '<div style="width:100%;color:var(--bp);text-align:' + (k === 'text-right' ? 'right' : k === 'text-center' ? 'center' : 'left') + ';font-weight:600' + (k === 'all-caps' ? ';text-transform:uppercase;letter-spacing:.08em' : '') + '">' + (k === 'sr-only' ? '<span class="note">' + esc(__('Hidden on screen, read by screen readers', 'nori-for-elementor')) + '</span>' : esc(__('Nori means seaweed in Japanese', 'nori-for-elementor'))) + '</div>'; }
		return '<div class="pv-box" style="padding:12px;border-radius:' + (/radius|card|pill/.test(k) ? '24px' : '0') + (k === 'border-bottom' ? ';border:0;border-bottom:2px solid var(--bp)' : k === 'border-top' ? ';border:0;border-top:2px solid var(--bp)' : '') + '"><i></i></div>';
	}

	/* ================= keep pending changes across a fresh state ================= */
	function reload(state) {
		var keep = CUR;
		load(state);
		Object.keys(keep).forEach(function (k) { if (Object.prototype.hasOwnProperty.call(BASE, k)) { setv(k, keep[k]); } });
		bar(); rerender();
	}

	/* ================= Page templates ================= */
	function pageAdds(p) {
		var groups = {}, vars = {}, mods = [], already = 0;
		p.classes.forEach(function (ck) {
			var c = cls(ck); if (!c) { return; }
			if (c.module) { var m = mod(c.module); if (m && !modOn(c.module) && mods.indexOf(m.label) < 0) { mods.push(m.label); } return; }
			if (!inEl(c.status) && val('cact:' + ck) !== 'add') { var g = IDX.cgroup[c.group]; groups[g ? g.label : c.group] = (groups[g ? g.label : c.group] || 0) + 1; } else { already++; }
			c.vars.forEach(function (v) { var x = vr(v); if (x && x.status === 'missing') { vars[v] = 1; } });
		});
		p.vars.forEach(function (v) { var x = vr(v); if (x && x.status === 'missing') { vars[v] = 1; } });
		var rows = Object.keys(groups).map(function (g) { return '<div><span>' + esc(g) + '</span><b>+' + esc(sprintf(_n('%d class', '%d classes', groups[g], 'nori-for-elementor'), groups[g])) + '</b></div>'; });
		var nv = Object.keys(vars).length; if (nv) { rows.push('<div><span>' + esc(__('Variables', 'nori-for-elementor')) + '</span><b>+' + nv + '</b></div>'); }
		var total = p.classes.filter(function (ck) { var c = cls(ck); return c && !c.module; }).length;
		var h = '<div class="uses"><span class="sec-h">' + esc(__('Adds to Elementor', 'nori-for-elementor')) + '</span>' + (rows.length ? rows.join('') + '<span class="note">' + esc(sprintf(__('%1$d of the %2$d classes it uses are already there', 'nori-for-elementor'), already, total)) + '</span>' : '<span class="none">✓ ' + esc(sprintf(__('Nothing new: all %d classes it uses are already there', 'nori-for-elementor'), total)) + '</span>');
		if (mods.length) { h += '<span class="note">' + esc(sprintf(__('Uses %s, which is off: switch it on for that part to work.', 'nori-for-elementor'), mods.join(', '))) + '</span>'; }
		return h + '</div>';
	}
	/* The look the page templates and the header and footer come in. The screen remembers the last one picked. */
	var TSTYLE = null, PSWITCH = false;
	try { TSTYLE = w.localStorage.getItem('nori-tstyle'); } catch (e) {}
	function styleOf() { var T = S.templates; if (!TSTYLE || !T.styles.some(function (x) { return x.key === TSTYLE; })) { TSTYLE = T.style; } return TSTYLE; }
	function stylePicker(attr, cur, note) {
		return '<div class="stylepick" role="radiogroup" aria-label="' + esc(__('Style', 'nori-for-elementor')) + '">' + S.templates.styles.map(function (st) {
			var n = S.templates.pages.filter(function (p) { return p.style === st.key; }).length;
			return '<button type="button" role="radio" aria-checked="' + (st.key === cur) + '" ' + attr + '="' + esc(st.key) + '"><b>' + esc(st.label) + (st.key === S.templates.parts.style && S.templates.parts.items.length ? ' <span class="pill live">' + esc(__('Your header & footer', 'nori-for-elementor')) + '</span>' : '') + '</b><span>' + esc(st.description) + '</span>' + (note ? '<em>' + esc(sprintf(_n('%d page template', '%d page templates', n, 'nori-for-elementor'), n)) + '</em>' : '') + '</button>';
		}).join('') + '</div>';
	}
	function vTemplates() {
		var h = head(__('Build', 'nori-for-elementor'), __('Page templates', 'nori-for-elementor'), esc(__('Pick a style and a page, and Nori makes a draft of it with sample text, ready to edit in Elementor. Everything on it is a normal Elementor element, styled by your Nori classes, colors and type scale.', 'nori-for-elementor')));
		h += callout('info', 'info', '<span>' + esc(__('Making a page also switches on Smooth Scroll (Snappy, 0.5s) and Exit-Fade-Up, so its sections glide and fade as visitors scroll. If Slide-Fade-Blur is already on, pages use it instead of Exit-Fade-Up. Billboard and Lens pages also switch on the Video & big type modules they use. Change any of them under Motion & effects.', 'nori-for-elementor')) + '</span>') + gap();
		if (!S.site.atomic) { return h + callout('warn', 'warn', '<span>' + esc(__('Templates are built from Elementor 4\'s Atomic elements. Turn on the Atomic editor in Elementor to use them.', 'nori-for-elementor')) + '</span>'); }
		var st = styleOf(), P = S.templates.parts;
		h += stylePicker('data-tstyle', st, true) + gap();
		if (!P.items.length) { h += callout('volt', 'parts', '<span>' + esc(__('Add the site header and footer too, so your pages have them.', 'nori-for-elementor')) + ' <button type="button" class="link" data-go="site-parts">' + esc(__('Header & footer', 'nori-for-elementor')) + '</button></span>') + gap(); }
		else if (P.style !== st) { h += callout('info', 'info', '<span>' + esc(sprintf(__('Your header and footer are in the %s style. Pages in another style work fine with them, or switch the header and footer to match.', 'nori-for-elementor'), (S.templates.styles.filter(function (x) { return x.key === P.style; })[0] || {}).label || '')) + ' <button type="button" class="link" data-go="site-parts">' + esc(__('Header & footer', 'nori-for-elementor')) + '</button></span>') + gap(); }
		h += '<div class="tgrid">' + S.templates.pages.filter(function (p) { return p.style === st; }).map(function (p) {
			return '<article class="tc" id="nori-page-' + esc(p.key) + '"><div class="shot">' + (p.shot ? '<img src="' + esc(p.shot) + '" alt="' + esc(sprintf(__('Screenshot of the %s page template', 'nori-for-elementor'), p.label)) + '" loading="lazy" decoding="async">' : '') + '</div><div class="tb"><h3>' + esc(p.label) + '</h3><p>' + esc(p.description) + '</p>' + pageAdds(p) + '</div><div class="tf"><button type="button" class="btn primary" data-mkpage="' + esc(p.key) + '">' + esc(__('Create draft page', 'nori-for-elementor')) + '<span class="screen-reader-text"> ' + esc(p.label) + '</span></button></div></article>';
		}).join('') + '</div>';
		return h;
	}
	function vSiteParts() {
		var P = S.templates.parts, pro = P.mode === 'theme-builder', both = P.items.length >= 2, st = P.items.length ? P.style : styleOf();
		var cur = S.templates.styles.filter(function (x) { return x.key === st; })[0] || S.templates.styles[0];
		var h = head(__('Build', 'nori-for-elementor'), __('Header & footer', 'nori-for-elementor'), esc(__('One header and one footer for the whole site. Edit them once and every page changes.', 'nori-for-elementor')));
		h += stylePicker('data-pstyle', PSWITCH || st, false) + gap();
		h += '<section class="card strong site-card" id="nori-site-parts">' + (cur.partsShot ? '<img src="' + esc(cur.partsShot) + '" alt="' + esc(sprintf(__('Screenshot of the %s header and footer', 'nori-for-elementor'), cur.label)) + '">' : '<div></div>') + '<div class="card-b" style="display:grid;gap:10px;align-content:start"><p style="color:var(--text-2)">' + esc(pro ? __('One header and one footer for the whole site, made in Elementor Pro\'s Theme Builder. Edit them once and every page changes.', 'nori-for-elementor') : __('One header and one footer for the whole site, saved as Elementor templates. Edit them once and every page changes.', 'nori-for-elementor')) + '</p>';
		if (P.items.length) { h += '<ul class="parts">' + P.items.map(function (x) { return '<li><b>' + esc(x.title) + '</b>' + (x.live ? '<span class="pill live">' + esc(__('On every page', 'nori-for-elementor')) + '</span>' : '<span class="pill">' + esc(__('Not shown yet', 'nori-for-elementor')) + '</span>') + '<a class="btn sm" href="' + esc(x.edit) + '">' + esc(__('Edit in Elementor', 'nori-for-elementor')) + '</a></li>'; }).join('') + '</ul>'; }
		if (P.note) { h += '<p class="note">' + esc(P.note) + '</p>'; }
		if (modOn('smart-header')) { h += '<p class="note">' + sprintf(esc(__('Smart Header is on: add %s to the header\'s outer container to make it hide and show on scroll.', 'nori-for-elementor')), '<code>main-header-np</code>') + '</p>'; }
		if (PSWITCH && PSWITCH !== P.style && P.items.length) {
			var to = S.templates.styles.filter(function (x) { return x.key === PSWITCH; })[0];
			h += '<div class="callout warn">' + icon('warn') + '<span class="grow"><b>' + esc(sprintf(__('Make the header and footer again in the %s style?', 'nori-for-elementor'), to.label)) + '</b> ' + esc(__('Changes you made to them in Elementor are lost. The old ones go to the trash, so you can still get them back.', 'nori-for-elementor')) + '<span style="display:flex;gap:8px;margin-top:8px"><button type="button" class="btn sm primary" data-mkparts="' + esc(PSWITCH) + '" data-replace="1">' + esc(sprintf(__('Switch to %s', 'nori-for-elementor'), to.label)) + '</button><button type="button" class="btn sm" data-pstyle="' + esc(P.style) + '">' + esc(__('Keep them', 'nori-for-elementor')) + '</button></span></span></div>';
		} else {
			h += both ? '<p class="note ok">' + esc(__('Done. Edit them in Elementor, or pick the other style above to switch.', 'nori-for-elementor')) + '</p>' : '<p><button type="button" class="btn primary" data-mkparts="' + esc(st) + '">' + esc(P.items.length ? __('Create the missing one', 'nori-for-elementor') : sprintf(__('Create header & footer in %s', 'nori-for-elementor'), cur.label)) + '</button></p>';
		}
		return h + '</div></section>';
	}

	/* ================= Modules ================= */
	function S_(m, k) { return val('set:' + m + ':' + k); }
	function modAdds(m) {
		if (m.key === 'hubspot-bridge') { return __('Adds one class per bridge · you put it on your Atomic form', 'nori-for-elementor'); }
		return m.classes.length ? sprintf(_n('Adds %d class to your Elementor library · you apply it in the editor', 'Adds %d classes to your Elementor library · you apply them in the editor', m.classes.length, 'nori-for-elementor'), m.classes.length) : __('Site-wide · nothing to add in Elementor', 'nori-for-elementor');
	}
	function modPend(k, pick) {
		if (changed('mod:' + k)) { return '<span class="pill pend">' + esc(val('mod:' + k) ? __('Switching on', 'nori-for-elementor') : __('Switching off', 'nori-for-elementor')) + '</span>'; }
		return modOn(k) ? '<span class="pill live">' + esc(pick ? __('Selected', 'nori-for-elementor') : __('On', 'nori-for-elementor')) + '</span>' : '<span class="pill">' + esc(pick ? __('Not selected', 'nori-for-elementor') : __('Off', 'nori-for-elementor')) + '</span>';
	}
	function defaultOf(m, f) { var v = m.defaults[f.key]; return f.type === 'color' ? (hex(v) ? String(v).toLowerCase() : String(v)) : f.type === 'select' ? String(v) : f.type === 'toggle' ? !!v : +v; }
	function vModules(ck) {
		var c = cat(ck), ms = c.modules.map(mod), on = ms.filter(function (m) { return modOn(m.key); }).length;
		var h = head(ck === 'forms' || ck === 'site' ? __('Forms & site', 'nori-for-elementor') : __('Motion & effects', 'nori-for-elementor'), ck === 'forms' ? __('Forms & HubSpot', 'nori-for-elementor') : c.label, esc(c.description));
		var anyCls = ms.some(function (m) { return m.classes.some(function (x) { return inEl(x.status); }); });
		h += '<div class="cat-top"><span class="cnt">' + esc(sprintf(__('%1$d of %2$d on', 'nori-for-elementor'), on, ms.length)) + '</span><div class="bulk">'
			+ (c.pick ? '' : '<button type="button" class="btn xs" data-mbulk="on" data-cat="' + esc(ck) + '" title="' + esc(__('Switch on every module in this category', 'nori-for-elementor')) + '">' + esc(__('Enable all', 'nori-for-elementor')) + '</button>')
			+ '<button type="button" class="btn xs" data-mbulk="off" data-cat="' + esc(ck) + '" title="' + esc(__('Switch off every module in this category (their classes stay in Elementor)', 'nori-for-elementor')) + '">' + esc(__('Disable all', 'nori-for-elementor')) + '</button>'
			+ '<button type="button" class="btn xs" data-mbulk="reset" data-cat="' + esc(ck) + '" title="' + esc(__('Put every setting in this category back to its default', 'nori-for-elementor')) + '">' + esc(__('Reset all', 'nori-for-elementor')) + '</button>'
			+ '<button type="button" class="btn xs danger" data-mbulk="delete" data-cat="' + esc(ck) + '"' + (anyCls ? '' : ' disabled') + ' title="' + esc(__('Delete this category\'s module classes from Elementor and every element', 'nori-for-elementor')) + '">' + esc(__('Delete all', 'nori-for-elementor')) + '</button></div></div>';
		if (!S.site.atomic) { h += callout('warn', 'warn', '<span>' + esc(__('Elementor\'s Atomic (version 4) editor isn\'t active, so module classes can\'t be added yet. Site-wide modules work either way.', 'nori-for-elementor')) + '</span>') + gap(); }
		if (ms.some(function (m) { return m.classes.length; })) { h += callout('info', 'info', '<span><b>' + esc(__('Switching a module on adds its classes to your Elementor class library. It doesn\'t put them on anything.', 'nori-for-elementor')) + '</b> ' + esc(__('Nothing on your pages changes until you add a class to an element in the Elementor editor. Each module below says which class goes where.', 'nori-for-elementor')) + '</span>') + gap(); }
		var rivals = ms.filter(function (m) { return (c.pickModules || []).indexOf(m.key) > -1; });
		if (c.pick) {
			var cur = rivals.filter(function (m) { return modOn(m.key); })[0];
			h += '<fieldset class="pick" style="margin:0 0 12px"><legend class="screen-reader-text">' + esc(__('Pick one', 'nori-for-elementor')) + '</legend><b>' + esc(__('Pick one', 'nori-for-elementor')) + '</b>' + [{ key: 'none', label: __('None', 'nori-for-elementor') }].concat(rivals).map(function (m) { return '<label><input type="radio" name="nori-pick-' + esc(ck) + '" data-pick="' + esc(ck) + '" value="' + esc(m.key) + '"' + ((cur ? cur.key : 'none') === m.key ? ' checked' : '') + '>' + esc(m.label) + '</label>'; }).join('') + '<span class="note">' + esc(__('Both move elements as you scroll, so only one can be on.', 'nori-for-elementor')) + '</span></fieldset>';
		}
		return h + '<div class="mods">' + ms.map(function (m) { return modCard(m, rivals.indexOf(m) > -1); }).join('') + '</div>';
	}
	function modCard(m, pick) {
		var open = OPEN['m:' + m.key], k = m.key;
		var h = '<article class="mod' + (open ? ' open' : '') + '" id="nori-mod-' + esc(k) + '"><div class="mod-h" data-mtoggle="' + esc(k) + '"><div class="thumb" aria-hidden="true">' + stageFor(k, false) + '</div><div class="mod-t"><h3>' + esc(m.label) + ' ' + modPend(k, pick) + '</h3><p>' + esc(m.summary) + '</p><span class="adds">' + esc(modAdds(m)) + '</span></div><div class="mod-r">' + (pick ? '' : swHtml(k)) + '<button type="button" class="chevb" aria-expanded="' + !!open + '" aria-label="' + esc(sprintf(open ? __('Hide details for %s', 'nori-for-elementor') : __('Show details for %s', 'nori-for-elementor'), m.label)) + '" data-mtoggle="' + esc(k) + '">' + icon('chev') + '</button></div></div>';
		if (!open) { return h + '</article>'; }
		var sets = m.fields.filter(function (f) { return !f.plain; });
		var rightEmpty = !sets.length && !m.classes.length && k !== 'dark-mode';
		var ex = m.explain.length ? '<div><div class="sec-h" style="margin-bottom:8px">' + esc(__('How it works', 'nori-for-elementor')) + '</div><ol class="ex">' + m.explain.map(function (e) { return '<li><span><b>' + esc(e.title) + '.</b> ' + esc(e.text) + '</span></li>'; }).join('') + '</ol></div>' : '';
		h += '<div class="mod-b">' + (k === 'hubspot-bridge' ? hubTutorial() : '') + '<div class="mod-grid"><div><div class="stage" id="nori-stage-' + esc(k) + '">' + stageFor(k, true) + '</div><p class="stage-cap">' + esc(stageCap(k)) + '</p>' + (k === 'hubspot-bridge' ? '' : howBox(m)) + (m.how && k !== 'hubspot-bridge' ? '<p class="note">' + esc(m.how) + '</p>' : '');
		if (!modOn(k) && m.classes.some(function (c) { return inEl(c.status); })) { h += callout('warn', 'warn', '<span>' + esc(__('Off, but its classes are still in Elementor and stay on your elements. They do nothing until you switch the module back on.', 'nori-for-elementor')) + '</span>'); }
		if (!rightEmpty) { h += ex; }
		h += pairsBox(m);
		h += '</div><div>' + (rightEmpty ? ex : '');
		if (k === 'font-preconnect') { h += callout('warn', 'warn', '<span class="grow"><b>' + esc(__('Third-party fonts slow down your pages.', 'nori-for-elementor')) + '</b> ' + esc(__('Loading fonts from Google or Adobe adds a render-blocking stylesheet from another server: text can\'t show until it arrives, which hurts load time and Core Web Vitals. Preconnect shortens that wait but doesn\'t remove it. For the fastest pages, turn on Elementor > Settings > Performance > Load Google Fonts locally (Nori then skips Google here). Only switch on the providers you actually use: an unused preconnect wastes a connection.', 'nori-for-elementor')) + '</span>'); }
		if (sets.length) { h += '<div><div class="set-h"><span class="sec-h">' + esc(__('Settings', 'nori-for-elementor')) + '</span><button type="button" class="link" data-mreset="' + esc(k) + '">' + esc(__('Reset to defaults', 'nori-for-elementor')) + '</button></div>' + sets.map(function (f) { return field(k, f); }).join('') + '</div>'; }
		if (k === 'dark-mode') { h += '<div class="callout volt">' + icon('colors') + '<div class="grow"><b>' + esc(val('dm:base') === 'dark' ? __('Light versions', 'nori-for-elementor') : __('Dark colors', 'nori-for-elementor')) + '</b><div class="palette">' + CKEYS.map(function (c) { return '<i style="background:' + esc(darkOf(c)) + '"></i>'; }).join('') + '</div><span>' + esc(__('Nori\'s colors and your own colors\' second versions are set on Colors & dark mode.', 'nori-for-elementor')) + ' <button type="button" class="link" data-go="colors" data-scroll="nori-colors-table">' + esc(__('Edit them', 'nori-for-elementor')) + '</button></span></div></div>'; }
		if (m.classes.length) {
			var del = val('delcls:' + k), inAny = m.classes.some(function (c) { return inEl(c.status); });
			h += '<div><div class="sec-h" style="margin-bottom:4px">' + esc(_n('Class it adds to Elementor', 'Classes it adds to Elementor', m.classes.length, 'nori-for-elementor')) + '</div>' + m.classes.map(function (c) {
				var p = !inEl(c.status) ? (modOn(k) ? '<span class="pill">' + esc(__('Added when you save', 'nori-for-elementor')) + '</span>' : '<span class="pill">' + esc(__('Not in Elementor', 'nori-for-elementor')) + '</span>') : (modOn(k) ? (c.status === 'edited' ? '<span class="pill edited">' + esc(__('In Elementor · edited', 'nori-for-elementor')) + '</span>' : '<span class="pill live">' + esc(__('In Elementor', 'nori-for-elementor')) + '</span>') : '<span class="pill edited">' + esc(__('Kept · effect off', 'nori-for-elementor')) + '</span>');
				return '<div class="clsrow"><code>.' + esc(c.key) + '-np</code><span class="meta">' + p + '<span class="cnt">' + (inEl(c.status) ? esc(c.usage ? sprintf(_n('On %d page', 'On %d pages', c.usage, 'nori-for-elementor'), c.usage) : __('Not used yet', 'nori-for-elementor')) : '') + '</span></span><p>' + esc(c.role) + '</p></div>';
			}).join('') + (inAny ? '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:6px"><button type="button" class="btn sm danger' + (del ? ' on' : '') + '" data-flipk="delcls:' + esc(k) + '">' + esc(del ? __('Keep classes', 'nori-for-elementor') : _n('Delete class everywhere', 'Delete classes everywhere', m.classes.length, 'nori-for-elementor')) + '</button>' + (del ? '<span class="pendlab">' + esc(__('On save: deleted from Elementor and removed from every element on every page.', 'nori-for-elementor')) + '</span>' : '') + '</div>' : '') + '</div>';
		}
		h += '</div></div>';
		if (k === 'form-protection') { h += spamPanel(); }
		if (k === 'hubspot-bridge') { h += hubPanel(); }
		return h + '</div></article>';
	}
	// How this module behaves next to others (library/modules.json "pairs"), strongest first.
	var PAIR_KINDS = ['pick-one', 'steps-aside', 'takes-turns', 'teams-up', 'fine'];
	function pairLabel(kind) {
		return ({
			'pick-one': __('Pick one', 'nori-for-elementor'),
			'steps-aside': __('Steps aside', 'nori-for-elementor'),
			'takes-turns': __('Takes turns', 'nori-for-elementor'),
			'teams-up': __('Teams up', 'nori-for-elementor'),
			'fine': __('Works together', 'nori-for-elementor')
		})[kind] || kind;
	}
	function catOf(k) { return S.modules.categories.filter(function (c) { return c.modules.indexOf(k) > -1; })[0]; }
	function pairsBox(m) {
		var ps = (m.pairs || []).filter(function (p) { return mod(p.with); }).slice().sort(function (a, b) { return PAIR_KINDS.indexOf(a.kind) - PAIR_KINDS.indexOf(b.kind); });
		if (!ps.length) { return ''; }
		var help = S.modules.pairKinds || {};
		return '<div class="pairs"><div class="sec-h" style="margin-bottom:8px">' + esc(__('With other modules', 'nori-for-elementor')) + '</div><ul>' + ps.map(function (p) {
			var o = mod(p.with), c = catOf(p.with), both = modOn(m.key) && modOn(p.with);
			return '<li><span class="pk pk-' + esc(p.kind) + '" title="' + esc(help[p.kind] || '') + '">' + esc(pairLabel(p.kind)) + '</span><span><b>' + (c ? '<button type="button" class="link" data-go="' + catView(c.key) + '" data-open="' + esc(p.with) + '">' + esc(o.label) + '</button>' : esc(o.label)) + '</b>' + (both ? ' <span class="pill live">' + esc(__('Both on', 'nori-for-elementor')) + '</span>' : '') + ' ' + esc(p.text) + '</span></li>';
		}).join('') + '</ul></div>';
	}
	function stageCap(k) {
		return ({
			'faded-carousel': __('.faded-carousel-np on a flexbox. Autoplays; hover to pause. Use .faded-carousel-mobile-np instead to keep your desktop layout and only carousel on tablet and mobile.', 'nori-for-elementor'),
			'video-carousel': __('.video-carousel-np on a flexbox of videos. Try the arrows; only the center video plays.', 'nori-for-elementor'),
			'exit-fade-up': __('Elements fade in from below as they arrive and drift up and out as they leave the top.', 'nori-for-elementor'),
			'slide-fade-blur': __('Children slide up, fade in and sharpen one after another.', 'nori-for-elementor'),
			'smart-header': __('The header hides on scroll down and comes back on scroll up.', 'nori-for-elementor'),
			'mobile-menu': __('On a phone, the menu button opens the menu below the header.', 'nori-for-elementor'),
			'font-preconnect': __('What Nori adds to your page <head>, and what it changes when a font loads.', 'nori-for-elementor'),
			'form-protection': __('Try sending the form as a person and as a bot.', 'nori-for-elementor'),
			'hubspot-bridge': __('A simulated Atomic form and the entry the bridge sends to HubSpot.', 'nori-for-elementor'),
			'background-video': __('The tint sits between the video and your text. Play and pause stay in reach.', 'nori-for-elementor'),
			'fit-text': __('The same word fills a wide and a narrow container.', 'nori-for-elementor'),
			'split-reveal': __('Plays on a loop here; on your site each heading rises once, when it scrolls into view.', 'nori-for-elementor'),
			'scroll-expand': __('Plays on a loop here; on your site it follows the scroll.', 'nori-for-elementor'),
			'button-roll': __('Plays on a loop here; on your site it rolls once per hover or keyboard focus.', 'nori-for-elementor')
		})[k] || __('The preview follows your settings and your colors.', 'nori-for-elementor');
	}

	/* ---------- previews ---------- */
	var FCVIEW = 'desktop';
	function fadedStage(big) {
		if (!big) { return car('faded', 'faded-carousel', false); }
		var V = [['desktop', __('Desktop', 'nori-for-elementor')], ['tablet', __('Tablet', 'nori-for-elementor')], ['mobile', __('Mobile', 'nori-for-elementor')], ['mobileonly', __('Desktop with faded-carousel-mobile-np', 'nori-for-elementor')]];
		var chips = '<div class="fcview" role="group" aria-label="' + esc(__('Preview size', 'nori-for-elementor')) + '">' + V.map(function (v) { return '<button type="button" class="chip" data-fcview="' + v[0] + '" aria-pressed="' + (FCVIEW === v[0]) + '">' + esc(v[1]) + '</button>'; }).join('') + '</div>', body;
		if (FCVIEW === 'mobileonly') { body = '<div class="fc-static">' + SLIDES().slice(0, 3).map(function (n) { return '<div>' + esc(n) + '</div>'; }).join('') + '</div><p class="note" style="text-align:center">' + sprintf(esc(__('With %s the flexbox stays exactly as you built it on desktop. Tablet and mobile get the carousel.', 'nori-for-elementor')), '<code>faded-carousel-mobile-np</code>') + '</p>'; }
		else if (FCVIEW === 'desktop') { body = car('faded', 'faded-carousel', true); }
		else { body = '<div class="frame" style="width:100%;max-width:' + (FCVIEW === 'tablet' ? 340 : 220) + 'px">' + car('faded', 'faded-carousel', true, (FCVIEW === 'tablet' ? S_('faded-carousel', 'tabletWidth') : S_('faded-carousel', 'mobileWidth')) / 100) + '</div>'; }
		return '<div style="display:grid;gap:12px;width:100%;justify-items:center">' + chips + body + '</div>';
	}
	function SLIDES() { return [__('Onboarding', 'nori-for-elementor'), __('Care team', 'nori-for-elementor'), __('Reports', 'nori-for-elementor'), __('Reminders', 'nori-for-elementor'), __('Support', 'nori-for-elementor')]; }
	function car(kind, m, big, wf) {
		var vid = kind === 'video', n = vid || wf ? 1 : +S_('faded-carousel', 'desktopCount');
		var st = vid ? '--fade:' + S_(m, 'fade') + ';--vr:' + Math.round(S_(m, 'radius') * (big ? 0.5 : 0.35)) + 'px;--tint:' + S_(m, 'tint') + ';--ps:' + Math.round(S_(m, 'playSize') * (big ? 0.5 : 0.35)) + 'px;--pc:' + colorOf(S_(m, 'playColor')) + ';--pf:' + colorOf(S_(m, 'playFill')) + ';--as:' + Math.round(S_(m, 'arrowSize') * (big ? 0.6 : 0.45)) + 'px;--ab:' + colorOf(S_(m, 'arrowBg')) + ';--ai:' + colorOf(S_(m, 'arrowColor')) + ';--abh:' + colorOf(S_(m, 'arrowHoverBg')) + ';--aih:' + colorOf(S_(m, 'arrowHoverColor')) : '--fade:' + S_(m, 'fade');
		var ratio = { '16-9': '16/9', '4-3': '4/3', '1-1': '1/1', '9-16': '9/16' }[S_('video-carousel', 'ratio')] || '16/9';
		var pc = val('var:primary'), g = [0, 0.25, 0.45, 0.15, 0.35].map(function (t) { return mix(pc, '#000000', t); });
		var sl = SLIDES().map(function (nm, i) { return vid ? '<div class="sl" style="--g:' + g[i] + ';aspect-ratio:' + ratio + '"><small>' + esc(sprintf(__('video %d', 'nori-for-elementor'), i + 1)) + '</small><span class="play ' + (S_(m, 'playStyle') === 'filled' ? 'filled' : S_(m, 'playStyle') === 'icon' ? 'icon' : '') + '"></span></div>' : '<div class="sl"><small>' + esc(sprintf(__('slide %d', 'nori-for-elementor'), i + 1)) + '</small><b>' + esc(nm) + '</b><i></i><i></i></div>'; }).join('');
		var chevL = '<svg viewBox="0 0 8 14" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M7 1L1 7L7 13"/></svg>', chevR = '<svg viewBox="0 0 8 14" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M1 1L7 7L1 13"/></svg>';
		var as = S_('video-carousel', 'arrowStyle'), ac = as === 'square' ? ' sq' : as === 'minimal' ? ' min' : '';
		var arrows = vid && S_(m, 'arrows') ? '<button type="button" class="arw a-prev' + ac + '" data-car="-1" aria-label="' + esc(__('Previous video', 'nori-for-elementor')) + '" tabindex="' + (big ? 0 : -1) + '">' + chevL + '</button><button type="button" class="arw a-next' + ac + '" data-car="1" aria-label="' + esc(__('Next video', 'nori-for-elementor')) + '" tabindex="' + (big ? 0 : -1) + '">' + chevR + '</button>' : '';
		var auto = vid ? '1' : (S_(m, 'autoplay') ? '1' : '0');
		return '<div lang="en" class="car ' + kind + '"' + (wf ? ' data-w="' + wf + '"' : '') + ' data-n="' + n + '" data-auto="' + auto + '" data-int="' + (vid ? 3200 : Math.max(1600, S_(m, 'interval') * 600)) + '" style="' + st + '"><div class="track">' + sl + '</div>' + arrows + '</div>' + (!vid && S_(m, 'pauseButton') && big ? '<button type="button" class="car-pause">' + esc(__('Pause carousel', 'nori-for-elementor')) + '</button>' : '');
	}
	function stageFor(k, big) {
		var B = esc(__('Book a visit', 'nori-for-elementor'));
		switch (k) {
		case 'smooth-scroll': return '<div class="m-scroll"><div><i></i><i></i><i></i><i></i><i></i><i></i></div></div>';
		case 'smart-header': return '<div class="m-hdr"><b style="animation-duration:' + (S_(k, 'speed') * 9) + 'ms"></b><i></i></div>';
		case 'mobile-menu': return '<div class="m-mm"><b></b><span><i></i><i></i><i></i><i></i></span></div>';
		case 'marquee': return '<div class="m-marq"><div style="animation-duration:' + (S_(k, 'speed') / 5) + 's;animation-direction:' + (S_(k, 'direction') === 'right' ? 'reverse' : 'normal') + '"><span>NORI</span><span>MARQUEE</span><span>LOOP</span><span>NORI</span><span>MARQUEE</span><span>LOOP</span></div></div>';
		case 'slide-fade-blur': return '<div class="m-bars">' + [0, 1, 2].map(function (i) { return '<i style="animation-delay:' + (i * S_(k, 'stagger') * 2) + 'ms"></i>'; }).join('') + '</div>';
		case 'exit-fade-up': return '<div class="m-efu">' + (big ? '<small class="t">' + esc(__('leaves the top', 'nori-for-elementor')) + '</small><small class="b">' + esc(__('arrives from below', 'nori-for-elementor')) + '</small>' : '') + '<i></i><i></i><i></i><i></i></div>';
		case 'background-video': return '<div class="m-bgv' + (S_(k, 'shape') === 'bottom' ? ' bottom' : S_(k, 'shape') === 'side' ? ' side' : '') + '" style="--t:' + colorOf(S_(k, 'tint')) + ';--s:' + S_(k, 'strength') + '"><i></i><span>' + esc(__('Your headline', 'nori-for-elementor')) + '</span><b>' + esc(__('Pause', 'nori-for-elementor')) + '</b></div>';
		case 'fit-text': return '<div class="m-fit"><div style="width:100%"><b style="font-size:41px">NORI</b></div><div style="width:62%"><b style="font-size:25px">NORI</b></div></div>';
		case 'split-reveal': return '<div class="m-split">' + [__('Words', 'nori-for-elementor'), __('rise', 'nori-for-elementor'), __('into', 'nori-for-elementor'), __('place', 'nori-for-elementor')].map(function (w, i) { return '<span><i style="animation-delay:' + (i * S_(k, 'stagger') * 2) + 'ms;animation-duration:' + (S_(k, 'duration') * 3) + 'ms">' + esc(w) + '</i></span>'; }).join(' ') + '</div>';
		case 'scroll-expand': return '<div class="m-expand" style="--in:' + Math.round(S_(k, 'inset') / 2) + 'px;--r:' + Math.round(S_(k, 'radius') / 2) + 'px"><i></i></div>';
		case 'lightbox': return '<div class="m-lb" style="background:rgba(18,18,18,' + S_(k, 'backdrop') + ')"><i></i><b>×</b></div>';
		case 'faded-carousel': return fadedStage(big);
		case 'video-carousel': return car('video', k, big);
		case 'button-ripple': return '<span class="m-btn rip" style="--rip:' + colorOf(S_(k, 'color')) + ';color:' + colorOf(S_(k, 'text')) + ';--ripd:' + (S_(k, 'speed') * 3.4) + 'ms"><span>' + B + '</span></span>';
		case 'button-glow': return '<span class="m-btn glow" style="--gl:' + colorOf(S_(k, 'color')) + '"><span>' + B + '</span></span>';
		case 'button-underline': return '<span class="m-btn ul" style="--ulh:' + S_(k, 'thickness') + 'px"><span>' + esc(__('Learn more →', 'nori-for-elementor')) + '</span></span>';
		case 'button-gradient': return '<span class="m-btn gr" style="background-image:linear-gradient(90deg,' + [1, 2, 3, 4, 5, 4, 3, 2, 1].map(function (i) { return colorOf(S_(k, 'color' + i)); }).join(',') + ');color:' + colorOf(S_(k, 'text')) + '"><span>' + B + '</span></span>';
		case 'button-roll': return '<span class="m-btn rl solid' + (S_(k, 'direction') === 'down' ? ' down' : '') + '" style="--rld:' + (S_(k, 'speed') * 6.5) + 'ms"><span class="rl-in"><span class="rl-col"><i>' + B + ' →</i><i>' + B + ' →</i></span></span></span>';
		case 'dark-mode': return '<div class="m-dark"><i style="background:' + val('var:background') + ';color:' + val('var:primary') + '">Aa</i><i style="background:' + darkOf('background') + ';color:' + darkOf('accent') + '">Aa</i></div>';
		case 'accessibility': return '<span class="m-focus" style="outline-color:' + colorOf(S_(k, 'ring')) + ';outline-width:' + S_(k, 'width') + 'px;box-shadow:0 0 0 ' + (S_(k, 'width') * 2) + 'px ' + colorOf(S_(k, 'halo')) + '">' + esc(__('Focus', 'nori-for-elementor')) + '</span>';
		case 'form-protection': return big ? spamPreview() : '<div class="m-form"><i></i><i></i><b></b></div>';
		case 'hubspot-bridge': return big ? hubPreview() : '<div class="m-form"><i></i><i></i><b style="background:#FF7A59;border-color:#FF7A59"></b></div>';
		case 'font-preconnect': return big ? fontPreview() : '<div class="m-fp" style="width:180px"><div style="grid-template-columns:minmax(0,1fr)"><span><i class="c" style="width:45%"></i><i class="d" style="width:40%"></i></span></div><div style="grid-template-columns:minmax(0,1fr)"><span><i class="c" style="width:12%"></i><i class="d" style="width:40%"></i></span></div></div>';
		}
		return '';
	}
	function fontPreview() {
		var gg = S_('font-preconnect', 'google') && !S.modules.localFonts, t = S_('font-preconnect', 'typekit'), lines = [];
		if (gg) { lines.push('<link rel="preconnect" href="https://fonts.googleapis.com">', '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'); }
		if (t) { lines.push('<link rel="preconnect" href="https://use.typekit.net" crossorigin>', '<link rel="preconnect" href="https://p.typekit.net" crossorigin>'); }
		return '<div style="width:100%;max-width:440px;display:grid;gap:12px"><pre style="margin:0;padding:10px;background:var(--surface);border:1px solid var(--line-2);font:11px/1.6 var(--mono);white-space:pre-wrap;word-break:break-all">' + esc(lines.length ? lines.join('\n') : __('(nothing)', 'nori-for-elementor')) + '</pre>' + (S_('font-preconnect', 'google') && S.modules.localFonts ? '<p class="note">' + esc(__('Elementor loads Google Fonts from your own site, so Nori skips Google here.', 'nori-for-elementor')) + '</p>' : '') + '<div class="m-fp" style="width:100%"><div>' + esc(__('without', 'nori-for-elementor')) + '<span><i class="c" style="width:42%"></i><i class="d" style="width:38%"></i></span></div><div>' + esc(__('with', 'nori-for-elementor')) + '<span><i class="c" style="width:' + (lines.length ? 10 : 42) + '%"></i><i class="d" style="width:38%"></i></span></div><p class="note" style="font-family:var(--font)">' + esc(__('Grey: connecting to the font server. Green: downloading the font. Text waits for both.', 'nori-for-elementor')) + '</p></div></div>';
	}

	/* ---------- Form Spam Protection ---------- */
	function spamPanel() {
		var L = S.forms.spam, max = Math.max.apply(null, L.reasons.map(function (r) { return r[1]; }).concat([1]));
		var h = '<section style="border-top:1px solid var(--line);padding:16px;display:grid;gap:12px" id="nori-spam-log"><div class="set-h"><span class="sec-h">' + esc(__('Blocked in the last 7 days', 'nori-for-elementor')) + '</span>' + (L.recent.length ? '<button type="button" class="link" data-spamclear>' + esc(__('Clear log', 'nori-for-elementor')) + '</button>' : '') + '</div>';
		h += '<div style="display:flex;gap:12px;align-items:baseline;flex-wrap:wrap"><span class="big">' + L.blocked + '</span><span>' + esc(_n('entry blocked', 'entries blocked', L.blocked, 'nori-for-elementor')) + '</span>' + (L.flagged ? '<span class="pill edited">' + esc(sprintf(_n('%d let through in test mode', '%d let through in test mode', L.flagged, 'nori-for-elementor'), L.flagged)) + '</span>' : '') + '</div>';
		if (L.reasons.length) { h += '<div class="reasons">' + L.reasons.map(function (r) { return '<div><span>' + esc(r[0]) + '</span><i><b style="width:' + (r[1] / max * 100) + '%"></b></i><em>' + r[1] + '</em></div>'; }).join('') + '</div>'; }
		if (L.recent.length) { h += '<details class="fold"><summary>' + esc(__('Latest blocked entries', 'nori-for-elementor')) + '</summary><div class="tablewrap" style="border:0"><table class="mini"><thead><tr><th>' + esc(__('When', 'nori-for-elementor')) + '</th><th>' + esc(__('Why', 'nori-for-elementor')) + '</th><th>' + esc(__('Page', 'nori-for-elementor')) + '</th><th class="hide-s">' + esc(__('Caught', 'nori-for-elementor')) + '</th></tr></thead><tbody>' + L.recent.map(function (r) { return '<tr><td>' + esc(r.when) + '</td><td>' + esc(r.why) + (r.test ? ' <span class="pill">' + esc(__('test mode', 'nori-for-elementor')) + '</span>' : '') + '</td><td class="mono">' + esc(r.page) + '</td><td class="hide-s">' + esc(r.caught) + '</td></tr>'; }).join('') + '</tbody></table></div><p class="note" style="padding:8px 12px">' + esc(__('Only the time, the reason and the page are kept, never what was typed or who sent it. The log keeps the last 50 entries and 30 days of counts.', 'nori-for-elementor')) + '</p></details>'; }
		else if (modOn('form-protection')) { h += '<p class="note">' + esc(__('Nothing blocked yet. Blocked entries show up here.', 'nori-for-elementor')) + '</p>'; }
		return h + '</section>';
	}
	function spamPreview() { return '<div style="display:grid;gap:8px;width:100%;max-width:300px"><div class="m-form" style="width:100%"><i></i><i></i><b></b></div><div class="chips"><button type="button" class="chip" data-spamtry="person">' + esc(__('Send as a person', 'nori-for-elementor')) + '</button><button type="button" class="chip" data-spamtry="bot">' + esc(__('Send as a bot', 'nori-for-elementor')) + '</button><button type="button" class="chip" data-spamtry="fast">' + esc(__('Send 0.3s after load', 'nori-for-elementor')) + '</button></div><p class="note" id="nori-spamres" aria-live="polite">' + esc(__('Pick one to see what happens (simulated).', 'nori-for-elementor')) + '</p></div>'; }

	/* ---------- HubSpot Bridge ---------- */
	var HS = { edit: null, del: null, draft: [], draftName: '', create: null, msg: {}, busy: '' };
	function hubTutorial() {
		var steps = [
			[__('Copy the embed code from HubSpot', 'nori-for-elementor'), __('In HubSpot, open your form and choose Share › Embed code. The classic embed, the new one, or the form\'s editor URL all work.', 'nori-for-elementor')],
			[__('Create a bridge here', 'nori-for-elementor'), __('Paste the code under Create a bridge below and press Read form. Nori finds the form\'s fields and which ones are required.', 'nori-for-elementor')],
			[__('Build the form in Elementor', 'nori-for-elementor'), __('Make an Atomic form any way you like. Give each input the ID of its HubSpot field (Settings › ID), like email or firstname.', 'nori-for-elementor')],
			[__('Add the bridge\'s class', 'nori-for-elementor'), __('Select the form itself (not a field) and add its class, like hs-bridge-a1b2c-np, in Classes on the Style tab. Update the page.', 'nori-for-elementor')],
			[__('Check and test', 'nori-for-elementor'), __('Step 3 on the bridge checks every page that uses the class and lists missing fields. Send a test entry from the live page; the result shows up there.', 'nori-for-elementor')]
		];
		return '<div class="tut"><h3>' + icon('bulb') + esc(__('How to connect a form to HubSpot', 'nori-for-elementor')) + '</h3><p>' + esc(__('Switching the module on doesn\'t connect anything by itself. Each form needs a bridge, and the bridge\'s class on the form. Five steps:', 'nori-for-elementor')) + '</p><ol class="tut-steps">' + steps.map(function (s, i) { return '<li><b><span>' + (i + 1) + '</span>' + esc(s[0]) + '</b><span>' + esc(s[1]) + '</span></li>'; }).join('') + '</ol></div>';
	}
	function hubPanel() {
		var H = S.forms.hubspot, h = '<section style="border-top:1px solid var(--line);padding:16px;display:grid;gap:14px" id="nori-hs">';
		if (!H.atomicForms) { h += callout('warn', 'warn', '<span>' + esc(__('The Atomic form comes with Elementor Pro (with its Atomic form feature on). You can set up bridges now; they work as soon as the form is available.', 'nori-for-elementor')) + '</span>'); }
		if (!modOn('hubspot-bridge') && H.bridges.length) { h += callout('warn', 'warn', '<span>' + esc(__('The module is off, so your bridges don\'t send anything yet. Switch it on and press Save changes.', 'nori-for-elementor')) + '</span>'); }
		h += '<div class="set-h"><span class="sec-h">' + esc(sprintf(__('Your bridges (%d)', 'nori-for-elementor'), H.bridges.length)) + '</span><span class="note">' + esc(__('Bridges save on their own; they don\'t wait for Save changes.', 'nori-for-elementor')) + '</span></div>';
		if (!H.bridges.length) { h += '<p class="note">' + esc(__('No bridges yet. Create one below from a HubSpot embed code.', 'nori-for-elementor')) + '</p>'; }
		H.bridges.forEach(function (b) { h += bridgeCard(b); });
		return h + createBridge() + '</section>';
	}
	function hsMsg(key) { var m = HS.msg[key]; return m ? m.map(function (x) { return callout(x[1] ? 'warn' : 'volt', x[1] ? 'warn' : 'info', '<span>' + esc(x[0]) + '</span>'); }).join('') : ''; }
	function bridgeCard(b) {
		var editing = HS.edit === b.id, need = { required: ['edited', __('Required', 'nori-for-elementor')], optional: ['', __('Optional', 'nori-for-elementor')], auto: ['', __('Hidden, sent automatically', 'nori-for-elementor')], url: ['', __('Hidden, from the page address', 'nori-for-elementor')] };
		var h = '<div class="bridge" id="nori-hs-' + esc(b.id) + '"><div class="bridge-h"><div><b>' + esc(b.name || __('HubSpot form', 'nori-for-elementor')) + '</b><small>' + esc(sprintf(__('Portal %1$s · Form %2$s · %3$s', 'nori-for-elementor'), b.portal, b.form, String(b.region).toUpperCase())) + '</small></div>' + (S.site.atomic ? (b.inEl ? '<span class="pill live">' + esc(__('Class in Elementor', 'nori-for-elementor')) + '</span>' : '<span class="pill edited">' + esc(__('Class missing', 'nori-for-elementor')) + '</span>') : '') + '</div>';
		h += '<div class="bstep"><h4><span>1</span>' + esc(__('Add this class to your Atomic form', 'nori-for-elementor')) + '</h4><div class="copyrow"><code>.' + esc(b['class']) + '</code><button type="button" class="btn sm" data-copy="' + esc(b['class']) + '">' + esc(__('Copy', 'nori-for-elementor')) + '</button>' + (S.site.atomic && !b.inEl ? '<button type="button" class="btn sm" data-hs="fixclass" data-id="' + esc(b.id) + '">' + esc(__('Add the class to Elementor again', 'nori-for-elementor')) + '</button>' : '') + '</div><p class="note">' + esc(__('Select the form (the Atomic form container, not a field) and add the class in Classes, on the Style tab. Each bridge has its own class, so different forms never mix up.', 'nori-for-elementor')) + '</p></div>';
		if (editing) {
			h += '<div class="bstep"><h4><span>2</span>' + esc(__('Edit fields', 'nori-for-elementor')) + '</h4>' + hsMsg(b.id) + fieldEditor('edit') + '</div>';
		} else {
			h += '<div class="bstep"><h4><span>2</span>' + esc(sprintf(__('Build your Atomic form with matching fields (%1$d from HubSpot, %2$d required)', 'nori-for-elementor'), b.fields.length, b.required)) + '</h4>' + callout('warn', 'warn', '<span><b>' + esc(__('The fields must match, or the bridge won\'t work.', 'nori-for-elementor')) + '</b> ' + esc(__('For every HubSpot field below, add an input to your Atomic form of the kind shown, select it, and in its Settings set ID to the HubSpot field name exactly (for example email). Every required field needs its input. If one is missing, HubSpot rejects the entry and the form shows its error message.', 'nori-for-elementor')) + (b.blocked ? '<br><b>' + esc(b.blocked) + '</b>' : '') + '</span>');
			h += '<div class="tablewrap"><table class="mini"><thead><tr><th>' + esc(__('Input ID (Settings › ID)', 'nori-for-elementor')) + '</th><th>' + esc(__('HubSpot label', 'nori-for-elementor')) + '</th><th class="hide-s">' + esc(__('Atomic field to use', 'nori-for-elementor')) + '</th><th>' + esc(__('Needed', 'nori-for-elementor')) + '</th></tr></thead><tbody>' + b.rows.map(function (f) { var n = need[f.need] || need.optional; return '<tr><td><code>' + esc(f.name) + '</code></td><td>' + esc(f.label) + '</td><td class="hide-s">' + esc(f.atomic) + '</td><td><span class="pill ' + n[0] + '">' + esc(n[1]) + '</span></td></tr>'; }).join('') + '</tbody></table></div>';
			if (b.hidden) { h += '<p class="note">' + esc(b.hidden) + '</p>'; }
			if (b.notice) { h += '<div class="note">' + esc(__('Privacy info is sent automatically. If your site needs it, show this notice near your Send button.', 'nori-for-elementor')) + ' <details><summary>' + esc(__('Show the notice', 'nori-for-elementor')) + '</summary><blockquote>' + esc(b.notice) + '</blockquote></details></div>'; }
			h += '<p class="note">' + esc(__('Optional fields can be left out. Inputs whose ID HubSpot doesn\'t know are not sent to HubSpot (Elementor still gets them). Elementor\'s own default IDs also work when they end with the field name, like e-form-email for email, but setting the exact ID is clearest.', 'nori-for-elementor')) + '</p></div>';
		}
		h += '<div class="bstep"><h4><span>3</span>' + esc(__('Check', 'nori-for-elementor')) + '</h4>';
		if (!b.inEl) { h += '<p class="note">' + esc(__('Once the class is in Elementor, Nori checks every page that uses it.', 'nori-for-elementor')) + '</p>'; }
		else if (!b.pages.length) { h += '<p class="note">' + esc(__('Not on any form yet. After you add the class to a form and save the page, Nori checks its fields here.', 'nori-for-elementor')) + '</p>'; }
		else { h += b.pages.map(function (p) { return '<div class="chk ' + (p.ok ? 'good' : 'bad') + '"><i>' + (p.ok ? '✓' : '!') + '</i><span><a class="link" href="' + esc(p.edit) + '">' + esc(p.title) + '</a> ' + esc(p.text) + '</span></div>'; }).join(''); }
		h += b.report.map(function (r) { return '<div class="chk ' + (r[0] === true ? 'good' : r[0] === false ? 'bad' : '') + '"><i>' + (r[0] === true ? '✓' : r[0] === false ? '!' : '·') + '</i><span>' + esc(r[1]) + '</span></div>'; }).join('') + '</div>';
		if (!editing) { h += hsMsg(b.id) ? '<div class="bstep">' + hsMsg(b.id) + '</div>' : ''; }
		if (HS.del === b.id) { h += '<div class="bacts" style="background:var(--red-soft)"><span style="flex-basis:100%;font-size:13px"><b>' + esc(__('Delete this bridge?', 'nori-for-elementor')) + '</b> ' + esc(__('Its class is removed from Elementor and from the form that uses it, and the form stops sending to HubSpot.', 'nori-for-elementor')) + '</span><button type="button" class="btn sm" data-hs="delno">' + esc(__('Keep it', 'nori-for-elementor')) + '</button><button type="button" class="btn sm danger" data-hs="delete" data-id="' + esc(b.id) + '">' + esc(__('Delete bridge', 'nori-for-elementor')) + '</button></div>'; }
		else if (!editing) { h += '<div class="bacts"><button type="button" class="btn sm" data-hs="refresh" data-id="' + esc(b.id) + '"' + (HS.busy === 'refresh' + b.id ? ' disabled' : '') + '>' + esc(HS.busy === 'refresh' + b.id ? __('Working…', 'nori-for-elementor') : __('Read fields from HubSpot again', 'nori-for-elementor')) + '</button><button type="button" class="btn sm" data-hs="edit" data-id="' + esc(b.id) + '">' + esc(__('Edit fields', 'nori-for-elementor')) + '</button><button type="button" class="btn sm danger" data-hs="delask" data-id="' + esc(b.id) + '">' + esc(__('Delete bridge', 'nori-for-elementor')) + '</button></div>'; }
		return h + '</div>';
	}
	var SUGGEST = ['firstname', 'lastname', 'email', 'phone', 'company', 'jobtitle', 'message', 'website', 'mobilephone', 'city', 'country'];
	function prettyName(n) {
		var known = { firstname: 'First name', lastname: 'Last name', email: 'Email', phone: 'Phone number', company: 'Company name', jobtitle: 'Job title', message: 'Message', website: 'Website URL', mobilephone: 'Mobile phone', city: 'City', state: 'State', country: 'Country', zip: 'Postal code', address: 'Street address' };
		return known[n] || String(n).replace(/__c$/, '').replace(/[_-]+/g, ' ').replace(/^./, function (c) { return c.toUpperCase(); });
	}
	function fieldEditor(mode) {
		var have = HS.draft.map(function (f) { return String(f.name).toLowerCase(); }), sug = SUGGEST.filter(function (s) { return have.indexOf(s) < 0; });
		return '<div style="display:grid;gap:10px"><label style="display:grid;gap:4px;font-weight:600;max-width:320px">' + esc(__('Name (just for you)', 'nori-for-elementor')) + '<input class="input" data-hsname value="' + esc(HS.draftName) + '"></label><div class="tablewrap"><table class="mini fed"><thead><tr><th>' + esc(__('HubSpot field name', 'nori-for-elementor')) + '</th><th>' + esc(__('Label', 'nori-for-elementor')) + '</th><th>' + esc(__('Required', 'nori-for-elementor')) + '</th><th></th></tr></thead><tbody>' + HS.draft.map(function (f, i) { return '<tr><td><input type="text" data-hsf="' + i + '" data-c="name" value="' + esc(f.name) + '" placeholder="email" spellcheck="false" aria-label="' + esc(__('HubSpot field name', 'nori-for-elementor')) + '"></td><td><input type="text" data-hsf="' + i + '" data-c="label" value="' + esc(f.label) + '" aria-label="' + esc(__('Label', 'nori-for-elementor')) + '"></td><td><input type="checkbox" class="cb" data-hsf="' + i + '" data-c="required"' + (f.required ? ' checked' : '') + ' aria-label="' + esc(__('Required', 'nori-for-elementor')) + '"></td><td><button type="button" class="link danger" data-hsrm="' + i + '">' + esc(__('Remove', 'nori-for-elementor')) + '</button></td></tr>'; }).join('') + '</tbody></table></div>'
			+ '<div class="chips">' + (sug.length ? '<span class="note" style="align-self:center">' + esc(__('Also on your HubSpot form? Add:', 'nori-for-elementor')) + '</span>' + sug.map(function (s) { return '<button type="button" class="chip" data-hsadd="' + s + '">+ ' + s + '</button>'; }).join('') : '') + '<button type="button" class="btn sm" data-hsadd="">' + esc(__('Add a field', 'nori-for-elementor')) + '</button></div>'
			+ '<div style="display:flex;gap:8px">' + (mode === 'edit' ? '<button type="button" class="btn sm primary" data-hs="save"' + (HS.busy === 'save' ? ' disabled' : '') + '>' + esc(HS.busy === 'save' ? __('Working…', 'nori-for-elementor') : __('Save fields', 'nori-for-elementor')) + '</button><button type="button" class="btn sm" data-hs="cancel">' + esc(__('Cancel', 'nori-for-elementor')) + '</button>' : '<button type="button" class="btn sm primary" data-hs="create"' + (HS.busy === 'create' ? ' disabled' : '') + '>' + esc(HS.busy === 'create' ? __('Working…', 'nori-for-elementor') : __('Create bridge', 'nori-for-elementor')) + '</button><button type="button" class="btn sm" data-hs="restart">' + esc(__('Start over', 'nori-for-elementor')) + '</button>') + '</div></div>';
	}
	function createBridge() {
		var c = HS.create, has = S.forms.hubspot.bridges.length;
		var h = '<div class="card" id="nori-hs-new"><div class="card-h"><h2>' + esc(has ? __('Create another bridge', 'nori-for-elementor') : __('Create a bridge', 'nori-for-elementor')) + '</h2></div><div class="card-b" style="display:grid;gap:10px">';
		if (!c || !c.ids) {
			h += '<label style="display:grid;gap:4px;font-weight:600" for="nori-hs-embed">' + esc(__('HubSpot embed code', 'nori-for-elementor')) + '</label><textarea class="code" id="nori-hs-embed" style="min-height:96px" spellcheck="false" placeholder="' + esc('<script src="https://js.hsforms.net/forms/embed/12345678.js" defer></script>\n<div class="hs-form-frame" data-region="na1" data-form-id="…" data-portal-id="12345678"></div>') + '">' + esc(c ? c.code : '') + '</textarea><p class="note">' + esc(__('In HubSpot, open the form and choose Share, then Embed code. Either embed style works, and so does the form\'s editor URL. Only the public portal and form ids are used.', 'nori-for-elementor')) + '</p><div><button type="button" class="btn sm primary" data-hs="read"' + (HS.busy === 'read' ? ' disabled' : '') + '>' + esc(HS.busy === 'read' ? __('Reading the form from HubSpot…', 'nori-for-elementor') : __('Read form', 'nori-for-elementor')) + '</button></div>' + hsMsg('new');
		} else {
			h += hsMsg('new') + fieldEditor('create') + callout('info', 'info', '<span>' + esc(__('Next, your Atomic form needs an input for each of these fields, with its ID (Settings › ID) set to the HubSpot field name. Required fields must all be there, or HubSpot rejects the entries.', 'nori-for-elementor')) + '</span>');
		}
		return h + '</div></div>';
	}
	function hubPreview() {
		var b = S.forms.hubspot.bridges[0], fields = b ? b.fields.filter(function (f) { return !f.hidden; }).slice(0, 3) : [{ name: 'firstname', label: 'First name' }, { name: 'email', label: 'Email' }];
		return '<div style="display:grid;gap:8px;width:100%;max-width:380px"><div style="display:grid;gap:6px;background:var(--bb);border:1px solid var(--bl);padding:10px">' + fields.map(function (f) { return '<input class="input" data-hsdemo="' + esc(f.name) + '" value="' + esc(f.name === 'email' ? 'ada@example.com' : f.name === 'firstname' ? 'Ada' : '') + '" placeholder="' + esc(f.label) + '" aria-label="' + esc(f.label + ' (' + f.name + ')') + '">'; }).join('') + '<button type="button" class="btn sm primary" data-hstry>' + esc(__('Send test (simulated)', 'nori-for-elementor')) + '</button></div><pre id="nori-hsout" style="margin:0;padding:8px;background:#111;color:#D6FF00;font:11px/1.5 var(--mono);min-height:60px;white-space:pre-wrap">' + esc(__('// The entry sent to HubSpot shows here. Nothing is sent.', 'nori-for-elementor')) + '</pre></div>';
	}
	/* Reading a HubSpot form in the browser: same rules as HubSpot_Bridges in PHP. */
	function hsPlain(s) { /* DOMParser documents are inert: no scripts, no image loads. */ var doc = new DOMParser().parseFromString(String(s == null ? '' : s), 'text/html'); return ((doc.body && doc.body.textContent) || '').trim(); }
	var V4TYPES = { email: 'email', singlelinetext: 'text', multilinetext: 'textarea', number: 'number', phone: 'phonenumber', dropdownselect: 'select', radio: 'radio', multiplecheckboxes: 'checkbox', singlecheckbox: 'booleancheckbox', datepicker: 'date', date: 'date', file: 'file', hidden: 'hidden' };
	function readConsent(c) {
		var subs = (c.communicationConsentCheckboxes || []).filter(function (b) { return b && b.communicationTypeId != null; }).map(function (b) { return { id: b.communicationTypeId, label: hsPlain(b.label), required: !!b.required }; });
		if (!subs.length) { subs = (c.legitimateInterestSubscriptionTypes || []).map(function (id) { return { id: id, label: '', required: false }; }); }
		var li = !!c.isLegitimateInterest;
		return { mode: li ? 'li' : 'consent', basis: String(c.legitimateInterestLegalBasis || '').indexOf('CLIENT') !== -1 ? 'CUSTOMER' : 'LEAD', implicit: String(c.processingConsentType || '').toUpperCase() === 'IMPLICIT', subs: subs, text: hsPlain(li ? c.privacyPolicyText : c.communicationConsentText), processing: hsPlain(c.processingConsentText || c.processingConsentCheckboxLabel || '') };
	}
	function readDefinition(data) {
		var fields = [], seen = {}, consent = null;
		var opts = function (o) { return (Array.isArray(o) ? o : []).filter(function (x) { return x && x.value != null; }).map(function (x) { return { value: String(x.value), label: hsPlain(x.label != null ? x.label : x.value) }; }); };
		(function walk(node) {
			if (!node || typeof node !== 'object') { return; }
			var ref = typeof node.propertyReference === 'string' && node.propertyReference.match(/^(\d+-\d+)\/([A-Za-z0-9_.\-]{1,100})$/);
			if (ref && !seen[ref[2]]) {
				seen[ref[2]] = true;
				var t4 = V4TYPES[String(node.type || '').toLowerCase()] || 'text';
				fields.push({ name: ref[2], label: hsPlain(node.label || ref[2]) || ref[2], required: !!node.required && !node.conditionallyHidden, hidden: t4 === 'hidden', type: t4, object: ref[1], 'default': Array.isArray(node.values) ? node.values.join(';') : (node.defaultValue != null ? String(node.defaultValue) : ''), options: opts(node.options) });
			}
			if (!consent && String(node.type || '').toLowerCase() === 'dataprivacy') { consent = readConsent(node); }
			var isField = typeof node.name === 'string' && ('fieldType' in node || 'required' in node || 'objectTypeId' in node);
			if (isField && /^[A-Za-z0-9_.\-]{1,100}$/.test(node.name) && !seen[node.name]) {
				seen[node.name] = true;
				var type = String(node.fieldType || node.type || 'text').toLowerCase();
				var def = node.defaultValue != null ? node.defaultValue : (Array.isArray(node.defaultValues) ? node.defaultValues.join(';') : '');
				fields.push({ name: node.name, label: hsPlain(node.label || node.name) || node.name, required: !!node.required, hidden: !!node.hidden || type === 'hidden', type: type, object: typeof node.objectTypeId === 'string' ? node.objectTypeId : '', 'default': typeof def === 'string' || typeof def === 'number' ? String(def) : '', options: opts(node.options) });
			}
			Object.keys(node).forEach(function (k) { if (node[k] && typeof node[k] === 'object') { walk(node[k]); } });
		})(data);
		var name = (data && data.form && data.form.name) || (data && data.name) || '';
		return { name: typeof name === 'string' ? name : '', fields: fields, consent: consent };
	}
	function readOne(url) {
		return w.fetch(url, { credentials: 'omit' })
			.then(function (r) { if (!r.ok) { throw new Error(sprintf(__('HubSpot answered your browser with status %d.', 'nori-for-elementor'), r.status)); } return r.json(); })
			.then(function (data) { var def = readDefinition(data); if (!def.fields.length) { throw new Error(__('HubSpot sent the form to your browser, but it had no fields.', 'nori-for-elementor')); } return def; },
				function (err) { throw new Error(err && err.message && err.message.indexOf('HubSpot') === 0 ? err.message : __('Your browser couldn\'t reach HubSpot either (an ad or privacy blocker can stop it; try again with it paused for this page).', 'nori-for-elementor')); });
	}
	function browserRead(urls) {
		var last = null;
		return [].concat(urls || []).reduce(function (chain, url) { return chain.then(function (found) { return found || readOne(url).catch(function (e) { last = e; return null; }); }); }, Promise.resolve(null))
			.then(function (found) { if (!found) { throw last || new Error(__('HubSpot sent the form to your browser, but it had no fields.', 'nori-for-elementor')); } return found; });
	}
	function parseEmbed(code) {
		var UUID = '([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})', portal = '', form = '', region = '', m;
		code = String(code || '');
		if ((m = code.match(/portal[_-]?id["']?\s*[:=]\s*["']?(\d{3,12})/i))) { portal = m[1]; } else if ((m = code.match(/\/forms\/embed\/(\d{3,12})\.js/))) { portal = m[1]; } else if ((m = code.match(/hubspot\.com\/forms\/(\d{3,12})\//))) { portal = m[1]; }
		if ((m = code.match(new RegExp('form[_-]?id["\']?\\s*[:=]\\s*["\']?' + UUID, 'i')))) { form = m[1].toLowerCase(); } else if ((m = code.match(new RegExp('hubspot\\.com/forms/\\d{3,12}/(?:editor/)?' + UUID, 'i')))) { form = m[1].toLowerCase(); }
		if ((m = code.match(/region["']?\s*[:=]\s*["']([a-z]{2}\d{1,2})["']/i))) { region = m[1].toLowerCase(); } else if ((m = code.match(/(?:js|forms|api|app)-([a-z]{2}\d{1,2})\.(?:hsforms|hubspot)/i))) { region = m[1].toLowerCase(); }
		return portal && form ? { portal: portal, form: form, region: region || 'na1' } : null;
	}
	// HubSpot's newer forms don't publish their fields; its submit API names the required ones when an entry
	// leaves them out. One entry with only a made-up field is always rejected, so nothing is saved in HubSpot.
	function probeRequired(ids) {
		var host = ids.region === 'na1' ? 'https://api.hsforms.com' : 'https://api-' + ids.region + '.hsforms.com';
		return w.fetch(host + '/submissions/v3/integration/submit/' + ids.portal + '/' + ids.form, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'omit', body: JSON.stringify({ fields: [{ name: 'nori_field_check', value: '1' }], context: { pageUri: w.location.origin + '/', pageName: 'Nori field check' } }) })
			.then(function (r) {
				return r.json().catch(function () { return {}; }).then(function (body) {
					if (r.ok) { return { required: [] }; }
					var errors = (body && body.errors) || [], required = [];
					errors.forEach(function (e) { if (e.errorType !== 'REQUIRED_FIELD') { return; } var mm = String(e.message || '').match(/Required field '([^']+)'/) || String(e.message || '').match(/'fields\.([^']+)'/); if (mm && required.indexOf(mm[1]) === -1) { required.push(mm[1]); } });
					if (!errors.some(function (e) { return e.errorType === 'REQUIRED_FIELD' || e.errorType === 'FIELD_NOT_IN_FORM_DEFINITION'; })) { throw new Error(sprintf(__('HubSpot didn\'t say which fields are required (status %1$s. %2$s)', 'nori-for-elementor'), r.status, (body && body.message) || '')); }
					return { required: required };
				});
			});
	}
	function hsCall(op, data) {
		var body = new w.FormData(), H = S.forms.hubspot;
		body.append('action', 'nori_fe_hs_' + op); body.append('nonce', H.nonce);
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		return w.fetch(H.ajax, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j || !j.success) { throw new Error((j && j.data && j.data.message) || 'error'); } return j.data; });
	}
	function hsFields() { return HS.draft.filter(function (f) { return String(f.name).trim(); }).map(function (f) { return { name: String(f.name).trim(), label: String(f.label || '').trim() || String(f.name).trim(), required: !!f.required, hidden: !!f.hidden, 'default': f['default'] || '', type: f.type || 'text', object: f.object || '', options: f.options || [] }; }); }
	function hsAfter(id) { return api('state').then(function (st) { reload(st); if (id) { var el = $('nori-hs-' + id); if (el) { el.scrollIntoView({ block: 'start' }); } } }); }
	function hsDraftFrom(fields, name) { HS.draft = (fields.length ? fields : [{ name: 'email', label: 'Email', required: true }]).map(function (f) { return Object.assign({}, f); }); HS.draftName = name || ''; }
	function hsAction(op, id) {
		var fail = function (key) { return function (e) { HS.busy = ''; HS.msg[key] = [[sprintf(__('Something went wrong: %s', 'nori-for-elementor'), e.message), 1]]; rerender(); }; };
		var b = S.forms.hubspot.bridges.filter(function (x) { return x.id === id; })[0];
		if (op === 'read') {
			var code = $('nori-hs-embed').value, ids = parseEmbed(code);
			HS.create = { code: code }; HS.busy = 'read'; HS.msg['new'] = null; rerender();
			hsCall('read', ids || { embed: code.replace(/[<>]/g, ' ') }).then(function (data) {
				var done = function (fields, name, notes, consent) { HS.busy = ''; HS.create = { code: code, ids: data.ids, consent: consent || null }; hsDraftFrom(fields, name); HS.msg['new'] = [[sprintf(__('Found portal %1$s, form %2$s (%3$s).', 'nori-for-elementor'), data.ids.portal, data.ids.form, String(data.ids.region).toUpperCase()), 0]].concat(notes); rerender(); };
				if (data.fields.length) { return done(data.fields, data.name, [[sprintf(__('Nori read %d fields from HubSpot. Check them, then create the bridge.', 'nori-for-elementor'), data.fields.length), 0]], data.consent); }
				return browserRead(data.definitions || data.definition).then(function (def) { done(def.fields, def.name || data.name, [[sprintf(__('Nori read %d fields from HubSpot. Check them, then create the bridge.', 'nori-for-elementor'), def.fields.length), 0]], def.consent); })
					.catch(function (err) {
						return probeRequired(data.ids).then(function (p) { done(p.required.map(function (n) { return { name: n, label: prettyName(n), required: true }; }), data.name, [[p.required.length ? sprintf(__('This HubSpot form doesn\'t publish its fields, so Nori asked HubSpot which ones are required: %s. Nothing was saved in HubSpot. Add the optional fields your form also has, with their HubSpot internal names.', 'nori-for-elementor'), p.required.join(', ')) : __('This HubSpot form doesn\'t publish its fields, and HubSpot says none are required. Add the fields your form has, with their HubSpot internal names. Nothing was saved in HubSpot.', 'nori-for-elementor'), 0]]); })
							.catch(function (err2) { done([], '', [[sprintf(__('Nori couldn\'t read the fields from HubSpot. %s Type the HubSpot field names below (their internal names, like email or firstname) and tick the ones HubSpot requires.', 'nori-for-elementor'), [data.fetchError, err.message, err2.message].filter(Boolean).join(' ')), 1]]); });
					});
			}).catch(function (e) { HS.busy = ''; HS.msg['new'] = [[e.message, 1]]; rerender(); });
			return;
		}
		if (op === 'create') {
			var c = HS.create; HS.busy = 'create'; rerender();
			hsCall('create', { portal: c.ids.portal, form: c.ids.form, region: c.ids.region, name: HS.draftName, fields: JSON.stringify(hsFields()), consent: c.consent ? JSON.stringify(c.consent) : '' })
				.then(function (r) { HS.busy = ''; HS.create = null; HS.msg['new'] = null; return hsAfter(r.id).then(function () { toast(__('Bridge created. Its class is in Elementor.', 'nori-for-elementor')); }); }).catch(fail('new'));
			return;
		}
		if (op === 'restart') { HS.create = null; HS.msg['new'] = null; rerender(); return; }
		if (op === 'edit') { HS.edit = id; HS.msg[id] = null; hsDraftFrom(b.fields, b.name); rerender(); return; }
		if (op === 'cancel') { HS.msg[HS.edit] = null; HS.edit = null; rerender(); return; }
		if (op === 'save') {
			HS.busy = 'save'; rerender();
			hsCall('update', { id: HS.edit, name: HS.draftName, fields: JSON.stringify(hsFields()), consent: HS.consent ? JSON.stringify(HS.consent) : '' })
				.then(function () { var eid = HS.edit; HS.busy = ''; HS.edit = null; return hsAfter(eid).then(function () { toast(__('Fields saved.', 'nori-for-elementor')); }); }).catch(fail(HS.edit));
			return;
		}
		if (op === 'delask') { HS.del = id; rerender(); return; }
		if (op === 'delno') { HS.del = null; rerender(); return; }
		if (op === 'delete') { hsCall('delete', { id: id }).then(function () { HS.del = null; return hsAfter('').then(function () { toast(__('Bridge deleted.', 'nori-for-elementor')); }); }).catch(fail(id)); return; }
		if (op === 'fixclass') { hsCall('fixclass', { id: id }).then(function () { return hsAfter(id); }).catch(fail(id)); return; }
		if (op === 'refresh') {
			HS.busy = 'refresh' + id; rerender();
			hsCall('refresh', { id: id }).then(function (r) {
				if (r.updated) { HS.busy = ''; return hsAfter(id).then(function () { toast(__('Fields read from HubSpot again.', 'nori-for-elementor')); }); }
				var open = function (fields, name, note, consent) { HS.busy = ''; HS.edit = id; HS.consent = consent || null; hsDraftFrom(fields, name); HS.msg[id] = note; rerender(); };
				return browserRead(r.definitions || r.definition).then(function (def) { open(def.fields, def.name || b.name, null, def.consent); })
					.catch(function (err) {
						return probeRequired({ portal: b.portal, form: b.form, region: b.region }).then(function (p) {
							var merged = b.fields.map(function (f) { var c2 = Object.assign({}, f); c2.required = p.required.indexOf(f.name) !== -1; return c2; });
							p.required.forEach(function (n) { if (!merged.some(function (f) { return f.name === n; })) { merged.push({ name: n, label: prettyName(n), required: true }); } });
							open(merged, b.name, [[p.required.length ? sprintf(__('This HubSpot form doesn\'t publish its fields, so Nori asked HubSpot which ones are required: %s. Nothing was saved in HubSpot. Add the optional fields your form also has, with their HubSpot internal names.', 'nori-for-elementor'), p.required.join(', ')) : __('This HubSpot form doesn\'t publish its fields, and HubSpot says none are required. Add the fields your form has, with their HubSpot internal names. Nothing was saved in HubSpot.', 'nori-for-elementor'), 0]]);
						}).catch(function (err2) { open(b.fields, b.name, [[sprintf(__('Nori couldn\'t read the fields from HubSpot. %s Type the HubSpot field names below (their internal names, like email or firstname) and tick the ones HubSpot requires.', 'nori-for-elementor'), [r.fetchError, err.message, err2.message].filter(Boolean).join(' ')), 1]]); });
					});
			}).catch(fail(id));
		}
	}

	/* ================= Import & export ================= */
	var exMode = 'json';
	function vTools() {
		var T = S.tools, h = head(__('Tools', 'nori-for-elementor'), __('Import & export', 'nori-for-elementor'), esc(__('Move your whole Nori setup to another site, or keep it as a backup.', 'nori-for-elementor')) + ' <button type="button" class="link" data-go="reset">' + esc(__('Start over with a full reset', 'nori-for-elementor')) + '</button>');
		if (T.import) {
			var I = T['import'];
			h += '<section class="card strong" id="nori-import-preview" style="margin-bottom:18px"><div class="card-h"><div><div class="crumb">' + esc(__('Import preview', 'nori-for-elementor')) + '</div><h2>' + esc(I.title) + '</h2></div><p>' + esc(I.sub) + '</p></div><div class="card-b">';
			if (!I.changes) { h += '<p class="note">' + esc(__('This site already matches the export. There is nothing to import.', 'nori-for-elementor')) + '</p>'; }
			else {
				h += '<ul class="list">' + I.rows.map(function (r) { return '<li style="align-items:flex-start"><span style="font-weight:600;white-space:nowrap">' + esc(r.label) + ' <span class="cnt">' + r.count + '</span></span><span class="chips" style="justify-content:flex-end">' + r.tokens.map(function (t) { return '<span class="chip static code">' + esc(t) + '</span>'; }).join('') + (r.count > r.tokens.length ? '<span class="note">+' + (r.count - r.tokens.length) + '</span>' : '') + '</span></li>'; }).join('');
				if (I.conflicts.length) { h += '<li style="align-items:flex-start;flex-wrap:wrap"><span style="font-weight:600">' + esc(__('Edited on both sites', 'nori-for-elementor')) + ' <span class="cnt">' + I.conflicts.length + '</span><br><span class="chips" style="margin-top:4px">' + I.conflicts.map(function (t) { return '<span class="chip static code">' + esc(t) + '</span>'; }).join('') + '</span></span><span style="display:grid;gap:4px;text-align:left"><label><input type="radio" name="nori-conf" value="keep" checked> ' + esc(__('Keep this site\'s version', 'nori-for-elementor')) + '</label><label><input type="radio" name="nori-conf" value="import"> ' + esc(__('Use the imported version', 'nori-for-elementor')) + '</label></span></li>'; }
				h += '</ul>';
			}
			h += I.warnings.map(function (x) { return callout('warn', 'warn', '<span>' + esc(x) + '</span>'); }).join('') + '<div style="display:flex;gap:8px;margin-top:12px">' + (I.changes ? '<button type="button" class="btn primary" data-im="apply">' + esc(__('Import now', 'nori-for-elementor')) + '</button>' : '') + '<button type="button" class="btn" data-im="cancel">' + esc(__('Cancel', 'nori-for-elementor')) + '</button></div></div></section>';
		}
		h += '<div class="home"><section class="card strong"><div class="card-h"><h2>' + esc(__('Export Nori Theme JSON', 'nori-for-elementor')) + '</h2><p>' + esc(__('Your whole Nori setup: modules and their settings, variables, classes including your edits, the type scale, colors and dark colors, and HubSpot bridges. Import it on another site, or keep it as a backup.', 'nori-for-elementor')) + '</p></div><div class="card-b" style="display:grid;gap:10px"><div class="seg" role="group" aria-label="' + esc(__('Export format', 'nori-for-elementor')) + '"><button type="button" data-exmode="json" aria-pressed="' + (exMode === 'json') + '">' + esc(__('For import (JSON)', 'nori-for-elementor')) + '</button><button type="button" data-exmode="text" aria-pressed="' + (exMode === 'text') + '">' + esc(__('Readable summary', 'nori-for-elementor')) + '</button></div><textarea class="code" readonly id="nori-extext" aria-label="' + esc(__('Export', 'nori-for-elementor')) + '">' + esc(exMode === 'json' ? T.json : T.text) + '</textarea><div class="chips"><button type="button" class="btn primary" data-download>' + esc(__('Export Nori Theme JSON', 'nori-for-elementor')) + '</button><button type="button" class="btn" data-copyex>' + esc(__('Copy', 'nori-for-elementor')) + '</button></div>' + (dirty().length ? '<p class="note">' + esc(sprintf(_n('The export is what\'s saved. Your %d unsaved change isn\'t in it yet.', 'The export is what\'s saved. Your %d unsaved changes aren\'t in it yet.', dirty().length, 'nori-for-elementor'), dirty().length)) + '</p>' : '') + '</div></section>';
		h += '<section class="card"><div class="card-h"><h2>' + esc(__('Import Nori Theme JSON', 'nori-for-elementor')) + '</h2><p>' + esc(__('Bring a Nori setup from another site, or restore a backup. You\'ll see exactly what will change before anything is applied.', 'nori-for-elementor')) + '</p></div><div class="card-b" style="display:grid;gap:10px"><label class="btn" style="justify-self:start">' + esc(__('Choose a Nori Theme JSON file', 'nori-for-elementor')) + '<input type="file" accept=".json,application/json" id="nori-imfile" hidden></label><textarea class="code" id="nori-imtext" style="min-height:120px" placeholder=\'{ "format": "nori-for-elementor", … }\' aria-label="' + esc(__('Or paste the export here', 'nori-for-elementor')) + '"></textarea><div><button type="button" class="btn primary" data-im="preview" disabled id="nori-imgo">' + esc(__('Preview import', 'nori-for-elementor')) + '</button></div><p class="note">' + esc(__('Only Nori\'s own classes, variables and settings are changed. Your pages and other classes stay as they are.', 'nori-for-elementor')) + '</p><div id="nori-imerr"></div></div></section></div>';
		return h;
	}

	/* ================= Full reset ================= */
	var RS = { token: '', file: '', at: '', saved: false, typed: '', busy: false };
	function resetReady() { return !!RS.token && RS.saved && RS.typed === 'RESET' && !RS.busy; }
	function vReset() {
		var R = S.tools.reset, pages = (S.home.pages || []).length, list = [];
		if (R.classes) { list.push(sprintf(_n('%d Nori class deleted from Elementor', '%d Nori classes deleted from Elementor', R.classes, 'nori-for-elementor'), R.classes)); }
		if (R.variables) { list.push(sprintf(_n('%d Nori variable deleted from Elementor', '%d Nori variables deleted from Elementor', R.variables, 'nori-for-elementor'), R.variables)); }
		if (R.modules) { list.push(sprintf(_n('%d module switched off, its settings back to the defaults', '%d modules switched off, their settings back to the defaults', R.modules, 'nori-for-elementor'), R.modules)); }
		if (R.bridges) { list.push(sprintf(_n('%d HubSpot bridge deleted', '%d HubSpot bridges deleted', R.bridges, 'nori-for-elementor'), R.bridges)); }
		if (R.parts) { list.push(__('Nori\'s site header and footer moved to the trash', 'nori-for-elementor')); }
		if (R.spam) { list.push(__('The spam log cleared', 'nori-for-elementor')); }
		list.push(__('Colors, dark colors, type scale and template style back to the factory settings', 'nori-for-elementor'));
		var h = head(__('Tools', 'nori-for-elementor'), __('Full reset', 'nori-for-elementor'), esc(__('Put Nori back the way a fresh install starts. You\'ll export a backup first, so you can bring everything back with Import Nori Theme JSON.', 'nori-for-elementor')));
		h += '<div class="home" style="grid-template-columns:1fr">';
		h += '<section class="card strong"><div class="card-h"><h2>' + esc(__('What a full reset does', 'nori-for-elementor')) + '</h2></div><div class="card-b"><ul class="list">' + list.map(function (x) { return '<li><span>' + esc(x) + '</span></li>'; }).join('') + '</ul>';
		if (pages) { h += callout('warn', 'warn', '<span><b>' + esc(sprintf(_n('%d page or template uses Nori classes.', '%d pages or templates use Nori classes.', pages, 'nori-for-elementor'), pages)) + '</b> ' + esc(__('Elementor removes the deleted classes from every element on them, so those elements lose that styling. Your own classes or elements that use Nori\'s variables lose those values too.', 'nori-for-elementor')) + '</span>'); }
		h += '<p class="note" style="margin-top:10px">' + esc(__('Not touched: pages you made from templates, your own classes and variables, and your Elementor settings.', 'nori-for-elementor')) + '</p></div></section>';
		// Step 1
		h += '<section class="card"><div class="card-h"><h2>' + esc(__('1. Export Nori Theme JSON', 'nori-for-elementor')) + '</h2><p>' + esc(__('Required. Download a backup of your current setup. To undo the reset, open Import & export and use Import Nori Theme JSON with this file.', 'nori-for-elementor')) + '</p></div><div class="card-b" style="display:grid;gap:10px;justify-items:start">';
		h += '<button type="button" class="btn' + (RS.token ? '' : ' primary') + '" data-reset="backup">' + icon('tools') + esc(RS.token ? __('Export again', 'nori-for-elementor') : __('Export Nori Theme JSON', 'nori-for-elementor')) + '</button>';
		if (RS.token) { h += callout('volt', 'check', '<span>' + esc(sprintf(__('Downloaded %1$s at %2$s. It\'s good for a reset in the next 30 minutes.', 'nori-for-elementor'), RS.file, RS.at)) + '</span>'); }
		h += '</div></section>';
		// Step 2
		h += '<section class="card"><div class="card-h"><h2>' + esc(__('2. Confirm', 'nori-for-elementor')) + '</h2></div><div class="card-b" style="display:grid;gap:12px;justify-items:start">';
		h += '<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" id="nori-rs-saved"' + (RS.saved ? ' checked' : '') + (RS.token ? '' : ' disabled') + '> ' + esc(__('I\'ve saved the backup file where I can find it.', 'nori-for-elementor')) + '</label>';
		h += '<label style="display:grid;gap:6px"><span>' + esc(__('Type RESET to confirm', 'nori-for-elementor')) + '</span><input type="text" class="input" id="nori-rs-typed" autocomplete="off" spellcheck="false" value="' + esc(RS.typed) + '"' + (RS.token ? '' : ' disabled') + ' style="max-width:220px"></label>';
		if (dirty().length) { h += '<p class="note">' + esc(sprintf(_n('Your %d unsaved change will be discarded.', 'Your %d unsaved changes will be discarded.', dirty().length, 'nori-for-elementor'), dirty().length)) + '</p>'; }
		h += '<button type="button" class="btn danger on" data-reset="run" id="nori-rs-go"' + (resetReady() ? '' : ' disabled') + '>' + icon('reset') + esc(RS.busy ? __('Resetting…', 'nori-for-elementor') : __('Reset everything', 'nori-for-elementor')) + '</button>';
		h += '</div></section></div>';
		return h;
	}
	function resetAction(kind, el) {
		if (kind === 'backup') {
			el.disabled = true;
			api('reset/backup', {}).then(function (r) {
				var blob = new w.Blob([r.json], { type: 'application/json' }), u = w.URL.createObjectURL(blob), a = d.createElement('a');
				a.href = u; a.download = r.filename; d.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { w.URL.revokeObjectURL(u); }, 1000);
				var now = new Date();
				RS.token = r.token; RS.file = r.filename; RS.at = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); RS.saved = false;
				rerender(); toast(sprintf(__('Backup downloaded: %s', 'nori-for-elementor'), r.filename));
			}).catch(function (e) { el.disabled = false; toast(e.message, 'error'); });
		}
		if (kind === 'run' && resetReady()) {
			RS.busy = true; rerender();
			api('reset/run', { token: RS.token, confirm: RS.typed }).then(function (r) {
				RS.busy = false;
				if (r.state) { RS = { token: '', file: '', at: '', saved: false, typed: '', busy: false }; CUR = {}; load(r.state); bar(); go('home'); }
				else { rerender(); }
				toast(r.message, r.type === 'error' ? 'error' : '');
			}).catch(function (e) { RS.busy = false; rerender(); toast(e.message, 'error'); });
		}
	}

	function vChangelog() {
		var h = head(__('Tools', 'nori-for-elementor'), __('What\'s new', 'nori-for-elementor'), '');
		var tagOf = { New: ['new', __('New', 'nori-for-elementor')], Improved: ['imp', __('Improved', 'nori-for-elementor')], Fixed: ['fix', __('Fixed', 'nori-for-elementor')], 'Initial release': ['new', __('Initial release', 'nori-for-elementor')] };
		h += '<section class="card">' + S.home.changelog.map(function (r) {
			return '<div class="rel"><div><h3>v' + esc(r.version) + '</h3>' + (r.version === S.site.version ? '<span class="pill live">' + esc(__('Installed', 'nori-for-elementor')) + '</span>' : '') + '</div><ul>' + r.entries.map(function (e) { var m = e.match(/^(New|Improved|Fixed|Initial release):?\s*(.*)$/), t = m ? tagOf[m[1]] : null; return '<li>' + (t ? '<span class="tag ' + t[0] + '">' + esc(t[1]) + '</span>' : '') + esc(m ? m[2] : e) + '</li>'; }).join('') + '</ul></div>';
		}).join('') + '</section>';
		return h;
	}

	/* ================= render + routing ================= */
	function render(keep) {
		var wk = $('nori-work'), top = wk.scrollTop, h;
		if (view === 'home') { h = vHome(); }
		else if (view === 'templates') { h = vTemplates(); }
		else if (view === 'site-parts') { h = vSiteParts(); }
		else if (view === 'colors') { h = vColors(); }
		else if (view === 'type') { h = vType(); }
		else if (view === 'variables') { h = vVariables(); }
		else if (view === 'classes') { h = vClasses(); }
		else if (view.indexOf('m-') === 0) { h = vModules(view.slice(2)); }
		else if (view === 'tools') { h = vTools(); }
		else if (view === 'reset') { h = vReset(); }
		else { h = vChangelog(); }
		wk.innerHTML = '<div class="nori-wrap">' + h + '</div>';
		renderSide(); drawType(); markChanged(); brandVars(); initCars();
		var stf = $('nori-stf'); if (stf) { stf.value = filt.status; }
		if (keep) { wk.scrollTop = top; }
	}
	function go(v, anchor) {
		if (views().indexOf(v) < 0) { v = 'home'; }
		view = v; render(); $('nori-work').scrollTop = 0;
		$('nori-side').classList.remove('open'); $('nori-menu').setAttribute('aria-expanded', 'false');
		try { w.history.replaceState(null, '', '#' + v); } catch (e) { /* ignore */ }
		if (anchor) { var a = $(anchor) || $('nori-mod-' + anchor); if (a) { a.scrollIntoView({ block: 'start' }); } }
	}
	function rerender() { render(true); }
	function applyPreset(pk) {
		if (pk === 'custom') { setv('brand', 'custom'); rerender(); return; }
		var p = S.design.presets[pk], ds = val('dm:base') === 'dark';
		setv('brand', pk);
		CKEYS.forEach(function (k) { setv('var:' + k, hex(ds ? p.dark[k] : p.light[k])); setv('set:dark-mode:' + S.design.darkKeys[k], hex(ds ? p.light[k] : p.dark[k]).toLowerCase()); });
		rerender();
		toast(sprintf(__('%s applied. Save to keep it, or Discard.', 'nori-for-elementor'), p.label));
	}
	function setLook(lk) {
		var from = siteLook();
		var V = CKEYS.map(function (k) { return val('var:' + k); }), A = DKEYS.map(function (k) { return val('set:dark-mode:' + k); });
		var fromDark = from === 'dark' || from === 'darkswitch', LS = fromDark ? A : V, DS = fromDark ? V : A, toDark = lk === 'dark' || lk === 'darkswitch';
		setv('mod:dark-mode', lk === 'switch' || lk === 'darkswitch');
		setv('dm:base', toDark ? 'dark' : 'light');
		CKEYS.forEach(function (k, i) { setv('var:' + k, hex(toDark ? DS[i] : LS[i])); setv('set:dark-mode:' + DKEYS[i], String(toDark ? LS[i] : DS[i]).toLowerCase()); });
		rerender();
	}
	function normColor(k, v) { return k.indexOf('var:') === 0 ? String(v).toUpperCase() : String(v).toLowerCase(); }

	/* ================= events ================= */
	root.addEventListener('click', function (e) {
		var t = e.target, x;
		if ((x = t.closest('[data-confirm]'))) { $('nori-confirm').hidden = true; if (x.getAttribute('data-confirm') === 'yes') { commit(); } else { $('nori-save').focus(); } return; }
		if ((x = t.closest('[data-go]'))) { e.preventDefault(); var op = x.getAttribute('data-open'); if (op) { OPEN['m:' + op] = 1; } go(x.getAttribute('data-go'), x.getAttribute('data-scroll') || op); return; }
		if ((x = t.closest('[data-scroll]'))) { var a = $(x.getAttribute('data-scroll')); if (a) { a.scrollIntoView({ block: 'start', behavior: 'smooth' }); } return; }
		if ((x = t.closest('.sw[data-k]'))) {
			var k = x.getAttribute('data-k'), nv = !val(k);
			if (k.indexOf('mod:') === 0) { var mk = k.slice(4), pc = S.modules.categories.filter(function (c) { return c.pick && (c.pickModules || []).indexOf(mk) > -1; })[0]; if (nv && pc) { pc.pickModules.forEach(function (o) { if (o !== mk) { setv('mod:' + o, false); } }); } }
			setv(k, nv); rerender(); return;
		}
		// modules
		if ((x = t.closest('[data-mtoggle]')) && !t.closest('.sw')) { var mt = x.getAttribute('data-mtoggle'); OPEN['m:' + mt] = !OPEN['m:' + mt]; rerender(); return; }
		if ((x = t.closest('[data-flipk]'))) { var fk = x.getAttribute('data-flipk'); setv(fk, !val(fk)); rerender(); return; }
		if ((x = t.closest('[data-mreset]'))) { var mm = mod(x.getAttribute('data-mreset')); mm.fields.forEach(function (f) { if (!f.plain) { setv('set:' + mm.key + ':' + f.key, defaultOf(mm, f)); } }); rerender(); toast(sprintf(__('%s: settings back to their defaults (not saved yet).', 'nori-for-elementor'), mm.label)); return; }
		if ((x = t.closest('[data-mbulk]'))) {
			var how = x.getAttribute('data-mbulk'), cc = cat(x.getAttribute('data-cat'));
			cc.modules.forEach(function (k2) {
				var m2 = mod(k2);
				if (how === 'on') { setv('mod:' + k2, true); } else if (how === 'off') { setv('mod:' + k2, false); }
				else if (how === 'reset') { m2.fields.forEach(function (f) { if (!f.plain) { setv('set:' + k2 + ':' + f.key, defaultOf(m2, f)); } }); }
				else if (how === 'delete' && m2.classes.some(function (c) { return inEl(c.status); })) { setv('delcls:' + k2, true); }
			});
			rerender(); return;
		}
		if ((x = t.closest('[data-fcview]'))) { FCVIEW = x.getAttribute('data-fcview'); livePreview('set:faded-carousel:x'); return; }
		if ((x = t.closest('[data-car]'))) { var cr = x.closest('.car'); if (cr && cr._step) { cr._step(+x.getAttribute('data-car')); } return; }
		if ((x = t.closest('.car-pause'))) { var cp = x.previousElementSibling; cp._paused = !cp._paused; x.textContent = cp._paused ? __('Play carousel', 'nori-for-elementor') : __('Pause carousel', 'nori-for-elementor'); return; }
		// templates
		if ((x = t.closest('[data-mkpage]'))) {
			x.disabled = true; x.textContent = __('Working…', 'nori-for-elementor');
			api('template', { page: x.getAttribute('data-mkpage') }).then(function (r) { reload(r.state); toastLinks(r); }).catch(function (er) { toast(er.message, 'error'); rerender(); });
			return;
		}
		if ((x = t.closest('[data-mkparts]'))) { x.disabled = true; api('site-parts', { style: x.getAttribute('data-mkparts') || '', replace: x.getAttribute('data-replace') === '1' }).then(function (r) { PSWITCH = false; reload(r.state); toastLinks(r); }).catch(function (er) { toast(er.message, 'error'); rerender(); }); return; }
		if ((x = t.closest('[data-tstyle]'))) { TSTYLE = x.getAttribute('data-tstyle'); try { w.localStorage.setItem('nori-tstyle', TSTYLE); } catch (e) {} rerender(); return; }
		if ((x = t.closest('[data-pstyle]'))) { var ps = x.getAttribute('data-pstyle'), PP = S.templates.parts; if (PP.items.length) { PSWITCH = ps === PP.style ? false : ps; } else { TSTYLE = ps; PSWITCH = false; } rerender(); return; }
		// forms
		if ((x = t.closest('[data-spamtry]'))) {
			var tr = x.getAttribute('data-spamtry'), res = $('nori-spamres'), min = S_('form-protection', 'minTime'), logOnly = S_('form-protection', 'logOnly');
			res.innerHTML = tr === 'person' ? '<span class="ok">✓ ' + esc(__('Sent.', 'nori-for-elementor')) + '</span> ' + esc(__('It passed the hidden field, the signed pass and the timing check.', 'nori-for-elementor'))
				: tr === 'bot' ? '<b style="color:var(--red)">' + esc(__('Blocked:', 'nori-for-elementor')) + '</b> ' + esc(__('filled in the hidden field.', 'nori-for-elementor')) + (logOnly ? ' ' + esc(__('(Test mode: let through and logged.)', 'nori-for-elementor')) : '')
				: (min > 300 ? '<b style="color:var(--red)">' + esc(__('Blocked:', 'nori-for-elementor')) + '</b> ' + esc(sprintf(__('sent too fast (%dms minimum).', 'nori-for-elementor'), min)) : '<span class="ok">✓ ' + esc(__('Sent.', 'nori-for-elementor')) + '</span> ' + esc(__('The timing check is set below 300ms.', 'nori-for-elementor')));
			return;
		}
		if ((x = t.closest('[data-spamclear]'))) {
			var body = new w.FormData(); body.append('action', 'nori_fe_spam_clear'); body.append('nonce', S.forms.spam.nonce);
			w.fetch(S.forms.spam.ajax, { method: 'POST', body: body, credentials: 'same-origin' }).then(function () { return api('state'); }).then(function (st) { reload(st); toast(__('Log cleared.', 'nori-for-elementor')); });
			return;
		}
		if ((x = t.closest('[data-copy]'))) { copyText(x.getAttribute('data-copy'), x); return; }
		if ((x = t.closest('[data-hs]'))) { hsAction(x.getAttribute('data-hs'), x.getAttribute('data-id')); return; }
		if ((x = t.closest('[data-hsadd]'))) { var nm = x.getAttribute('data-hsadd'); HS.draft.push({ name: nm, label: nm ? prettyName(nm) : '', required: false }); rerender(); return; }
		if ((x = t.closest('[data-hsrm]'))) { HS.draft.splice(+x.getAttribute('data-hsrm'), 1); rerender(); return; }
		if ((x = t.closest('[data-hstry]'))) { var out = { fields: [].map.call(root.querySelectorAll('[data-hsdemo]'), function (i) { return { name: i.getAttribute('data-hsdemo'), value: i.value }; }), context: { pageUri: w.location.origin + '/contact/', pageName: 'Contact' } }; $('nori-hsout').textContent = JSON.stringify(out, null, 1); return; }
		// tools
		if ((x = t.closest('[data-exmode]'))) { exMode = x.getAttribute('data-exmode'); rerender(); return; }
		if ((x = t.closest('[data-copyex]'))) { copyText($('nori-extext').value, x); return; }
		if ((x = t.closest('[data-download]'))) { var blob = new w.Blob([S.tools.json], { type: 'application/json' }), u = w.URL.createObjectURL(blob), a2 = d.createElement('a'); a2.href = u; a2.download = S.tools.filename; d.body.appendChild(a2); a2.click(); a2.remove(); setTimeout(function () { w.URL.revokeObjectURL(u); }, 1000); return; }
		if ((x = t.closest('[data-reset]'))) { resetAction(x.getAttribute('data-reset'), x); return; }
		if ((x = t.closest('[data-im]'))) {
			var im = x.getAttribute('data-im');
			if (im === 'preview') { api('import', { json: $('nori-imtext').value }).then(function (r) { if (r.type === 'error') { $('nori-imerr').innerHTML = callout('warn', 'warn', '<span>' + esc(r.message) + '</span>'); return; } reload(r.state); $('nori-work').scrollTop = 0; }); }
			if (im === 'apply') { var conf = root.querySelector('input[name="nori-conf"]:checked'); x.disabled = true; api('import/apply', { conflicts: conf ? conf.value : 'keep' }).then(function (r) { CUR = {}; load(r.state); bar(); rerender(); toast(r.message, r.type === 'error' ? 'error' : ''); }); }
			if (im === 'cancel') { api('import/cancel', {}).then(function (r) { reload(r.state); toast(r.message); }); }
			return;
		}
		if ((x = t.closest('[data-setk]'))) { setv(x.getAttribute('data-setk'), +x.getAttribute('data-setv')); rerender(); return; }
		if ((x = t.closest('tr.it')) && !t.closest('input,button,a,select')) { var r = x.getAttribute('data-row'); OPEN[r] = !OPEN[r]; rerender(); return; }
		if ((x = t.closest('[data-act]'))) { var ak = x.getAttribute('data-act'), av = x.getAttribute('data-v'); setv(ak, val(ak) === av ? '' : av); rerender(); return; }
		if ((x = t.closest('[data-undo]'))) { setv(x.getAttribute('data-undo'), ''); rerender(); return; }
		if ((x = t.closest('[data-bulk]'))) { var keys = JSON.parse(x.getAttribute('data-keys')), b = x.getAttribute('data-bulk'); keys.forEach(function (k3) { setv(k3, b === 'clear' ? '' : b); }); rerender(); return; }
		if ((x = t.closest('[data-starter]'))) { var add = x.getAttribute('data-starter') === 'add'; S.variables.items.forEach(function (v) { if (v.status === 'missing') { setv('vact:' + v.key, add ? 'add' : ''); } }); S.classes.items.forEach(function (c) { if (!c.module && c.status === 'missing') { setv('cact:' + c.key, add ? 'add' : ''); } }); rerender(); return; }
		if ((x = t.closest('[data-addmissing]'))) { S.classes.items.forEach(function (c) { if (!c.module && c.status === 'missing') { setv('cact:' + c.key, 'add'); } }); filt.status = 'missing'; filt.group = 'all'; go('classes'); return; }
		if ((x = t.closest('[data-jump-var]'))) { var jv = x.getAttribute('data-jump-var'), v0 = vr(jv); if (!v0) { return; } vtab = v0.group; OPEN['v:' + jv] = 1; go('variables'); var row = root.querySelector('[data-row="v:' + jv + '"]'); if (row) { row.scrollIntoView({ block: 'center' }); } return; }
		if ((x = t.closest('[data-jump-cls]'))) { var jc = x.getAttribute('data-jump-cls'); filt.group = 'all'; filt.q = ''; filt.status = 'all'; OPEN['c:' + jc] = 1; go('classes'); var row2 = root.querySelector('[data-row="c:' + jc + '"]'); if (row2) { row2.scrollIntoView({ block: 'center' }); } return; }
		if ((x = t.closest('[data-group]'))) { filt.group = x.getAttribute('data-group'); rerender(); return; }
		if ((x = t.closest('[data-vtab]'))) { vtab = x.getAttribute('data-vtab'); rerender(); return; }
		if ((x = t.closest('[data-look]'))) { setLook(x.getAttribute('data-look')); return; }
		if ((x = t.closest('[data-preset]'))) { applyPreset(x.getAttribute('data-preset')); return; }
		if ((x = t.closest('[data-brand-go]'))) {
			var A = $('nori-brand-a').value, I = $('nori-brand-i').value, pal = derive(A, I), ds = val('dm:base') === 'dark';
			setv('brand', 'custom'); setv('brand:accent', hex(A)); setv('brand:ink', hex(I));
			CKEYS.forEach(function (k) { setv('var:' + k, hex(ds ? pal.dark[k] : pal.light[k])); setv('set:dark-mode:' + S.design.darkKeys[k], hex(ds ? pal.light[k] : pal.dark[k]).toLowerCase()); });
			rerender(); var tb = $('nori-colors-table'); if (tb) { tb.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
			toast(__('Generated your colors from your brand color. They\'re in the table below (not saved yet).', 'nori-for-elementor')); return;
		}
		if ((x = t.closest('[data-suggest]'))) { var u = IDX.mine[x.getAttribute('data-suggest')]; if (u && u.value) { setv('ucol:' + u.id, suggest(u.value)); rerender(); } return; }
		if ((x = t.closest('[data-suggest-all]'))) { S.colors.mine.forEach(function (u2) { if (!u2.module && u2.value && val('ucol:' + u2.id) === 'same') { setv('ucol:' + u2.id, suggest(u2.value)); } }); rerender(); return; }
		if ((x = t.closest('#nori-results button'))) { pick(x); return; }
		if (!t.closest('.search')) { $('nori-results').hidden = true; }
		if (!t.closest('#nori-confirm') && !t.closest('#nori-save')) { $('nori-confirm').hidden = true; }
	});
	root.addEventListener('toggle', function (e) { if (e.target.id === 'nori-modfold') { FOLD = e.target.open; } }, true);
	root.addEventListener('input', function (e) {
		var t = e.target;
		if (t.id === 'nori-cf') { filt.q = t.value; var pos = t.selectionStart; rerender(); var f = $('nori-cf'); f.focus(); f.setSelectionRange(pos, pos); return; }
		if (t.id === 'nori-q') { search(t.value); return; }
		if (t.id === 'nori-imtext') { $('nori-imgo').disabled = !t.value.trim(); return; }
		if (t.id === 'nori-rs-typed') { RS.typed = t.value.trim().toUpperCase(); $('nori-rs-go').disabled = !resetReady(); return; }
		if (t.hasAttribute('data-hsname')) { HS.draftName = t.value; return; }
		if (t.hasAttribute('data-hsf')) { var hi = +t.getAttribute('data-hsf'), hc = t.getAttribute('data-c'); HS.draft[hi][hc] = hc === 'required' ? t.checked : t.value; return; }
		if (t.id === 'nori-hs-embed') { HS.create = { code: t.value }; return; }
		if (t.hasAttribute('data-ucustom')) { setv('ucol:' + t.getAttribute('data-ucustom'), t.value.toUpperCase()); var sw = t.parentNode.querySelector('.swatch'); if (sw) { sw.style.background = t.value; } return; }
		if (t.hasAttribute('data-k') && t.tagName === 'INPUT') {
			var k = t.getAttribute('data-k'), v = t.hasAttribute('data-num') ? +t.value : t.value;
			if (t.type === 'color') { v = normColor(k, v); }
			if (t.type === 'range') { var st = +t.getAttribute('data-step'); if (st < 1) { v = +(+v).toFixed(String(st).split('.')[1].length); } }
			setv(k, v);
			var o = t.getAttribute('data-out') && $(t.getAttribute('data-out')); if (o) { var stp = +t.getAttribute('data-step'); o.textContent = (stp < 1 ? (+v).toFixed(String(stp).split('.')[1].length) : v) + (t.getAttribute('data-unit') || ''); }
			var code = t.parentNode.querySelector('code'); if (code && t.type === 'color') { code.textContent = String(v).toUpperCase(); }
			var cell = t.closest('td.lt,td.dk'); if (cell) { cell.classList.toggle('chg', changed(k)); }
			var row = t.closest('.colrow'); if (row && row.querySelector('.swatch')) { row.querySelector('.swatch').style.background = v; }
			livePreview(k);
			if (view === 'colors') { var duo = root.querySelector('.duo'); if (duo) { var on = modOn('dark-mode'), look = siteLook(); duo.innerHTML = dprev(false, look === 'darkswitch' ? __('Dark (your design)', 'nori-for-elementor') : look === 'dark' ? __('Your site (dark)', 'nori-for-elementor') : on ? __('Light (your design)', 'nori-for-elementor') : __('Light', 'nori-for-elementor')) + (on ? dprev(true, look === 'darkswitch' ? __('Light (when visitors switch)', 'nori-for-elementor') : __('Dark (when visitors switch)', 'nori-for-elementor')) : ''); } }
		}
	});
	root.addEventListener('change', function (e) {
		var t = e.target;
		if (t.id === 'nori-stf') { filt.status = t.value; rerender(); return; }
		if (t.id === 'nori-rs-saved') { RS.saved = t.checked; $('nori-rs-go').disabled = !resetReady(); return; }
		if (t.id === 'nori-imfile' && t.files[0]) { var fr = new w.FileReader(); fr.onload = function () { $('nori-imtext').value = fr.result; $('nori-imgo').disabled = false; }; fr.readAsText(t.files[0]); return; }
		if (t.hasAttribute('data-pick')) { var pc2 = cat(t.getAttribute('data-pick')); (pc2.pickModules || pc2.modules).forEach(function (m) { setv('mod:' + m, m === t.value); }); rerender(); return; }
		if (t.hasAttribute('data-hsf') && t.type === 'checkbox') { HS.draft[+t.getAttribute('data-hsf')].required = t.checked; return; }
		if (t.hasAttribute('data-addk')) { setv(t.getAttribute('data-addk'), t.checked ? 'add' : ''); rerender(); return; }
		if (t.hasAttribute('data-ucol')) { var u = IDX.mine[t.getAttribute('data-ucol')]; setv('ucol:' + u.id, t.value === 'custom' ? suggest(u.value || '#808080') : t.value); rerender(); return; }
		if (t.tagName === 'SELECT' && t.hasAttribute('data-k')) { var k = t.getAttribute('data-k'); setv(k, t.hasAttribute('data-num') ? +t.value : t.value); if (t.hasAttribute('data-colsel')) { rerender(); } else { livePreview(k); } if (/^ts:/.test(k)) { drawType(); } return; }
		if (t.type === 'color' && t.hasAttribute('data-k') && view === 'colors') { rerender(); }
	});
	d.addEventListener('keydown', function (e) {
		if (e.key === '/' && ['INPUT', 'SELECT', 'TEXTAREA'].indexOf(d.activeElement.tagName) < 0 && !d.activeElement.isContentEditable) { e.preventDefault(); $('nori-q').focus(); }
		if (e.key === 'Escape') { $('nori-results').hidden = true; $('nori-confirm').hidden = true; }
	});
	w.addEventListener('beforeunload', function (e) { if (dirty().length && !busy) { e.preventDefault(); e.returnValue = ''; } });
	w.addEventListener('hashchange', function () { var h = w.location.hash.replace('#', ''); if (views().indexOf(h) > -1 && h !== view) { go(h); } });


	function livePreview(k) {
		var p = k.split(':');
		if (p[0] !== 'set') { return; }
		var st = $('nori-stage-' + p[1]); if (st) { st.innerHTML = stageFor(p[1], true); }
		var th = root.querySelector('#nori-mod-' + p[1] + ' .thumb'); if (th) { th.innerHTML = stageFor(p[1], false); }
		initCars();
	}
	function toastLinks(r) {
		toast(r.message, r.type === 'error' ? 'error' : '');
		var links = r.links || {}, keys = Object.keys(links);
		if (keys.length) { var el = $('nori-toast'); el.classList.add('has-links'); el.querySelector('span').insertAdjacentHTML('beforeend', ' ' + keys.map(function (l) { return '<a href="' + esc(links[l]) + '">' + esc(l) + '</a>'; }).join(' · ')); clearTimeout(toastTimer); toastTimer = setTimeout(function () { el.hidden = true; el.classList.remove('has-links'); }, 15000); }
	}
	function copyText(txt, btn) {
		var done = function () { var o = btn.textContent; btn.textContent = __('Copied', 'nori-for-elementor'); setTimeout(function () { btn.textContent = o; }, 1400); };
		try { w.navigator.clipboard.writeText(txt).then(done, function () { toast(__('Select the text and copy it.', 'nori-for-elementor')); }); } catch (er) { toast(__('Select the text and copy it.', 'nori-for-elementor')); }
	}
	/* Carousels: three copies of the slides; the active group sits exactly in the middle, and after each
	   step the track jumps back by one copy without animating, so it loops forever. */
	function initCars() { root.querySelectorAll('.car:not([data-init])').forEach(setupCar); }
	function setupCar(el) {
		el.setAttribute('data-init', '1');
		var track = el.querySelector('.track'), orig = [].slice.call(track.children), N = orig.length;
		orig.forEach(function (sl) { var c = sl.cloneNode(true); c.setAttribute('aria-hidden', 'true'); track.appendChild(c); });
		orig.forEach(function (sl) { var c = sl.cloneNode(true); c.setAttribute('aria-hidden', 'true'); track.insertBefore(c, track.firstChild); });
		var n = +el.getAttribute('data-n') || 1, vid = el.classList.contains('video'), idx = N, g = vid ? 10 : 8;
		function layout(anim) {
			var W = el.clientWidth; if (!W) { return; }
			var wf = +el.getAttribute('data-w') || 0, sw = vid ? W * 0.6 : wf ? W * wf : (W - (n - 1) * g) / (n + 0.5), slides = track.children;
			for (var i = 0; i < slides.length; i++) { slides[i].style.width = sw + 'px'; slides[i].style.marginRight = g + 'px'; slides[i].classList.toggle('on', i >= idx && i < idx + n); }
			track.style.transition = anim ? 'transform .6s cubic-bezier(.16,1,.3,1)' : 'none';
			track.style.transform = 'translateX(' + ((W - (n * sw + (n - 1) * g)) / 2 - idx * (sw + g)) + 'px)';
		}
		el._step = function (dd) { idx += dd; layout(true); setTimeout(function () { if (idx >= 2 * N) { idx -= N; layout(false); } else if (idx < N) { idx += N; layout(false); } }, 620); };
		layout(false);
		if (w.ResizeObserver) { new w.ResizeObserver(function () { layout(false); }).observe(el); }
		if (el.getAttribute('data-auto') === '1' && !(w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
			var iv = setInterval(function () { if (!el.isConnected) { clearInterval(iv); return; } if (el._paused || el.matches(':hover') || el.contains(d.activeElement)) { return; } el._step(1); }, +el.getAttribute('data-int') || 2400);
		}
	}

	/* ================= search ================= */
	var hits = [];
	function search(q) {
		q = q.trim().toLowerCase(); var box = $('nori-results');
		if (!q) { box.hidden = true; return; }
		hits = [];
		[[__('Colors & dark mode', 'nori-for-elementor'), 'colors', 'dark mode light colors palette brand presets'], [__('Page templates', 'nori-for-elementor'), 'templates', 'pages draft'], [__('Header & footer', 'nori-for-elementor'), 'site-parts', 'theme builder'], [__('Type scale', 'nori-for-elementor'), 'type', 'font size ratio heading'], [__('Import & export', 'nori-for-elementor'), 'tools', 'backup json export import nori theme'], [__('Full reset', 'nori-for-elementor'), 'reset', 'reset defaults factory delete everything start over'], [__('What\'s new', 'nori-for-elementor'), 'changelog', 'changelog version']].forEach(function (p) { if ((p[0] + ' ' + p[2]).toLowerCase().indexOf(q) > -1) { hits.push({ t: __('Screen', 'nori-for-elementor'), l: p[0], s: '', go: function () { go(p[1]); } }); } });
		S.modules.categories.forEach(function (c) { c.modules.forEach(function (k) { var m = mod(k); if ((m.label + ' ' + m.summary + ' ' + m.classes.map(function (x) { return x.key; }).join(' ')).toLowerCase().indexOf(q) > -1) { hits.push({ t: __('Module', 'nori-for-elementor'), l: m.label, s: m.summary, go: function () { OPEN['m:' + k] = 1; go(catView(c.key)); } }); } }); });
		S.classes.items.forEach(function (c) { if (!c.module && (c.label + ' ' + c.description).toLowerCase().indexOf(q) > -1) { hits.push({ t: __('Class', 'nori-for-elementor'), l: '.' + c.label, s: c.spec, go: function () { filt.group = 'all'; filt.q = ''; filt.status = 'all'; OPEN['c:' + c.key] = 1; go('classes'); var r = root.querySelector('[data-row="c:' + c.key + '"]'); if (r) { r.scrollIntoView({ block: 'center' }); } } }); } });
		S.variables.items.forEach(function (v) { if ((v.label + ' ' + v.description).toLowerCase().indexOf(q) > -1) { hits.push({ t: __('Variable', 'nori-for-elementor'), l: v.label, s: val('var:' + v.key), go: function () { vtab = v.group; OPEN['v:' + v.key] = 1; go('variables'); } }); } });
		hits = hits.slice(0, 12);
		box.innerHTML = hits.length ? hits.map(function (h, i) { return '<button type="button" role="option" data-i="' + i + '"><small>' + esc(h.t) + '</small><span><strong>' + esc(h.l) + '</strong><em>' + esc(h.s) + '</em></span></button>'; }).join('') : '<p class="note" style="padding:12px">' + esc(sprintf(__('Nothing matches "%s".', 'nori-for-elementor'), q)) + '</p>';
		box.hidden = false;
	}
	function pick(btn) { var h = hits[+btn.getAttribute('data-i')]; $('nori-results').hidden = true; $('nori-q').value = ''; h.go(); }

	/* ================= start ================= */
	root.innerHTML = '<div class="app">'
		+ '<header class="top"><button type="button" class="btn sm menu-btn" id="nori-menu" aria-expanded="false" aria-controls="nori-side">' + esc(__('Menu', 'nori-for-elementor')) + '</button>'
		+ '<a class="brand" href="#home" data-go="home"><span class="logo" aria-hidden="true"></span><b>Nori</b><span class="ver" title="' + esc(__('What\'s new', 'nori-for-elementor')) + '">v' + esc(S.site.version) + '</span></a>'
		+ '<div class="search">' + icon('search') + '<input id="nori-q" type="search" placeholder="' + esc(__('Search classes, variables, modules, settings', 'nori-for-elementor')) + '" aria-label="' + esc(__('Search Nori', 'nori-for-elementor')) + '" autocomplete="off"><kbd>/</kbd><div class="results" id="nori-results" hidden role="listbox" aria-label="' + esc(__('Search results', 'nori-for-elementor')) + '"></div></div>'
		+ '<div class="r"><span class="pending" id="nori-pending" aria-live="polite"></span><button type="button" class="btn sm" id="nori-discard" disabled>' + esc(__('Discard', 'nori-for-elementor')) + '</button><button type="button" class="btn sm primary" id="nori-save" disabled>' + esc(__('Save changes', 'nori-for-elementor')) + '</button></div>'
		+ '<div class="confirm" id="nori-confirm" hidden role="dialog" aria-label="' + esc(__('Confirm save', 'nori-for-elementor')) + '"></div></header>'
		+ '<div class="body"><nav class="side" id="nori-side" aria-label="' + esc(__('Nori sections', 'nori-for-elementor')) + '"></nav><main class="work" id="nori-work" tabindex="-1"></main></div></div>'
		+ '<div class="toast" id="nori-toast" hidden role="status"></div>';
	root.style.setProperty('--mark', 'url("' + BOOT.mark + '")');
	$('nori-save').addEventListener('click', askSave);
	$('nori-discard').addEventListener('click', discard);
	$('nori-menu').addEventListener('click', function () { var o = $('nori-side').classList.toggle('open'); this.setAttribute('aria-expanded', String(o)); });
	$('nori-q').addEventListener('keydown', function (e) { if (e.key === 'Enter' && hits[0]) { e.preventDefault(); pick($('nori-results').querySelector('button')); } });
	load(S);
	var start = (w.location.hash || '').replace('#', '');
	view = views().indexOf(start) > -1 ? start : 'home';
	bar(); render();
})(window, document);
