/*! Nori: Mobile Menu. .menu-toggle-np opens .mobile-menu-np below the header, on phones (and tablets if set). */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	var cfg = N.settings('mobile-menu', { breakpoint: 'mobile', openLabel: 'Open menu', closeLabel: 'Close menu', menuLabel: 'Menu' });
	var query = w.matchMedia('(max-width: ' + (cfg.breakpoint === 'tablet' ? 1024 : 767) + 'px)');
	var count = 0;

	/** The panel that goes with a button: the nearest .mobile-menu-np around it (normally in the same header). */
	function panelFor(btn) {
		for (var box = btn.parentElement; box; box = box.parentElement) {
			var panel = box.querySelector('.mobile-menu-np');
			if (panel) { return { panel: panel, scope: box }; }
		}
		return null;
	}

	function setup(btn) {
		var found = panelFor(btn);
		if (!found || btn.hasAttribute('aria-controls')) { return; }
		var panel = found.panel;
		// What the panel opens below: the header the button is in.
		var bar = btn.closest('header, [data-elementor-type="header"]') || found.scope;
		var open = false;

		if (!panel.id) { panel.id = 'nori-mobile-menu-' + (++count); }
		if (!panel.hasAttribute('aria-label')) { panel.setAttribute('aria-label', cfg.menuLabel); }
		btn.setAttribute('role', 'button');
		btn.setAttribute('tabindex', '0');
		btn.setAttribute('aria-controls', panel.id);

		function focusables() {
			return Array.prototype.filter.call(panel.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])'), function (el) {
				return el.offsetWidth > 0 || el.offsetHeight > 0;
			});
		}

		// Match the header: a rounded bar (Bento) gets a rounded card floating just below it, lined up with
		// the bar and with its corners and border; a square header (Studio) gets a full-width sheet.
		var shell = panel.parentElement;
		function place() {
			var cs = w.getComputedStyle(shell);
			var floating = (parseFloat(cs.borderTopLeftRadius) || 0) > 0;
			var box = (floating ? shell : bar).getBoundingClientRect();
			panel.classList.toggle('is-floating-np', floating);
			panel.style.setProperty('--nori-menu-top', Math.max(0, Math.round(box.bottom + (floating ? 8 : 0))) + 'px');
			if (floating) {
				panel.style.setProperty('--nori-menu-left', Math.round(box.left) + 'px');
				panel.style.setProperty('--nori-menu-right', Math.round(w.innerWidth - box.right) + 'px');
				panel.style.setProperty('--nori-menu-radius', cs.borderTopLeftRadius);
				panel.style.setProperty('--nori-menu-border', cs.borderTopWidth + ' ' + cs.borderTopStyle + ' ' + cs.borderTopColor);
			}
		}

		function set(state, moveFocus) {
			open = state;
			if (open) { place(); }
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			btn.setAttribute('aria-label', open ? cfg.closeLabel : cfg.openLabel);
			btn.classList.toggle('is-open-np', open);
			panel.classList.toggle('is-open-np', open);
			d.documentElement.classList.toggle('nori-menu-open', open);
			if (N.lenis) { if (open) { N.lenis.stop(); } else { N.lenis.start(); } }
			if (!moveFocus) { return; }
			if (open) {
				var first = focusables()[0];
				if (first) { w.setTimeout(function () { first.focus(); }, 60); }
			} else {
				btn.focus();
			}
		}
		set(false, false);

		btn.addEventListener('click', function (e) {
			e.preventDefault();
			set(!open, false);
		});
		btn.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
				e.preventDefault();
				set(!open, true);
			}
		});
		// A tap outside the menu (and its button) closes it.
		d.addEventListener('click', function (e) {
			if (open && !panel.contains(e.target) && !btn.contains(e.target)) { set(false, false); }
		});
		// Picking a link (even one to a spot on this page) closes the menu.
		panel.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('a[href]')) { set(false, false); }
		});
		d.addEventListener('keydown', function (e) {
			if (!open) { return; }
			if (e.key === 'Escape' || e.key === 'Esc') {
				set(false, true);
				return;
			}
			if (e.key !== 'Tab') { return; }
			// Keep focus on the button and the menu while it's open.
			var items = [btn].concat(focusables());
			var at = items.indexOf(d.activeElement);
			if (e.shiftKey && at <= 0) {
				e.preventDefault();
				items[items.length - 1].focus();
			} else if (!e.shiftKey && (at === items.length - 1 || at === -1)) {
				e.preventDefault();
				items[0].focus();
			}
		});
		// Wider screens show the normal menu: close this one.
		var onChange = function () { if (!query.matches && open) { set(false, false); } };
		if (query.addEventListener) { query.addEventListener('change', onChange); } else if (query.addListener) { query.addListener(onChange); }
		w.addEventListener('resize', function () { if (open) { place(); } }, { passive: true });
	}

	N.start(function () {
		Array.prototype.forEach.call(d.querySelectorAll('.menu-toggle-np'), setup);
	});
})(window, document);
