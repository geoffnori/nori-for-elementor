/*! Nori: Dark Mode. Any element with .theme-toggle-np switches between light and dark. The choice is
    remembered on this device (if that setting is on); until a visitor picks, the page follows their
    system setting (if the default is "Match the visitor's system"). */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	var opts = N.settings('dark-mode', { default: 'system', remember: true });
	var root = d.documentElement;
	var KEY = 'nori-theme';

	function current() { return root.getAttribute('data-nori-theme') === 'dark' ? 'dark' : 'light'; }
	function stored() { try { var s = w.localStorage.getItem(KEY); return s === 'dark' || s === 'light' ? s : null; } catch (e) { return null; } }

	function sync() {
		var dark = current() === 'dark';
		d.querySelectorAll('.theme-toggle-np').forEach(function (t) {
			t.setAttribute('aria-pressed', dark ? 'true' : 'false');
		});
	}

	function set(mode, remember) {
		root.setAttribute('data-nori-theme', mode);
		if (remember && opts.remember) { try { w.localStorage.setItem(KEY, mode); } catch (e) {} }
		sync();
		try { w.dispatchEvent(new w.CustomEvent('nori:theme', { detail: { theme: mode } })); } catch (e) {}
	}

	function prepare(t) {
		if (t.getAttribute('data-nori-toggle')) { return; }
		t.setAttribute('data-nori-toggle', '1');
		var tag = t.tagName;
		if (tag !== 'BUTTON' && tag !== 'A') {
			t.setAttribute('role', 'button');
			if (!t.hasAttribute('tabindex')) { t.setAttribute('tabindex', '0'); }
		}
		// An icon-only toggle still needs a name for screen readers.
		if (!t.getAttribute('aria-label') && !(t.textContent || '').trim()) { t.setAttribute('aria-label', 'Dark mode'); }
	}

	function toggle(e, t) {
		if (t.tagName === 'A') { e.preventDefault(); }
		set(current() === 'dark' ? 'light' : 'dark', true);
	}

	N.start(function () {
		if (!root.hasAttribute('data-nori-theme')) { root.setAttribute('data-nori-theme', 'light'); }
		d.querySelectorAll('.theme-toggle-np').forEach(prepare);
		sync();
		d.addEventListener('click', function (e) {
			var t = e.target.closest ? e.target.closest('.theme-toggle-np') : null;
			if (t) { toggle(e, t); }
		});
		d.addEventListener('keydown', function (e) {
			var t = e.target.closest ? e.target.closest('.theme-toggle-np[role="button"]') : null;
			if (t && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); toggle(e, t); }
		});
		// Toggles added later (a popup, an off-canvas menu) get the same keyboard support and name.
		if (w.MutationObserver) {
			new w.MutationObserver(function (list) {
				list.forEach(function (m) {
					m.addedNodes.forEach(function (n) {
						if (n.nodeType !== 1) { return; }
						if (n.matches('.theme-toggle-np')) { prepare(n); }
						n.querySelectorAll('.theme-toggle-np').forEach(prepare);
					});
				});
				sync();
			}).observe(d.body || root, { childList: true, subtree: true });
		}
		// Until the visitor picks, follow the system setting live.
		if (opts.default === 'system' && w.matchMedia) {
			var mq = w.matchMedia('(prefers-color-scheme: dark)');
			var follow = function () { if (!(opts.remember && stored())) { set(mq.matches ? 'dark' : 'light', false); } };
			if (mq.addEventListener) { mq.addEventListener('change', follow); } else if (mq.addListener) { mq.addListener(follow); }
		}
	});
})(window, document);
