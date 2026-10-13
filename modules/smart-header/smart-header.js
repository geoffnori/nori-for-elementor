/*! Nori: Smart Header. Hides .main-header-np on scroll down, shows it on scroll up or keyboard focus. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	N.start(function () {
		var headers = d.querySelectorAll('.main-header-np');
		if (!headers.length) { return; }

		var opts = N.settings('smart-header', { topZone: 80, threshold: 10 });
		var TOP_ZONE = Number(opts.topZone);    // Always visible near the top of the page.
		var THRESHOLD = Number(opts.threshold); // Ignore tiny scroll jitter.
		var lastY = w.pageYOffset || d.documentElement.scrollTop;
		var ticking = false;

		function setHidden(hidden) {
			for (var i = 0; i < headers.length; i++) {
				headers[i].classList.toggle('header-hidden-np', hidden);
			}
		}

		function update() {
			var y = w.pageYOffset || d.documentElement.scrollTop;
			if (y <= TOP_ZONE) {
				setHidden(false);
			} else if (Math.abs(y - lastY) >= THRESHOLD) {
				// Keep it visible while something inside it has keyboard focus.
				var focusInside = false;
				for (var i = 0; i < headers.length; i++) {
					if (headers[i].contains(d.activeElement)) { focusInside = true; }
				}
				setHidden(y > lastY && !focusInside);
			} else {
				ticking = false;
				return;
			}
			lastY = y;
			ticking = false;
		}

		w.addEventListener('scroll', function () {
			if (!ticking) {
				ticking = true;
				w.requestAnimationFrame(update);
			}
		}, { passive: true });

		for (var i = 0; i < headers.length; i++) {
			headers[i].addEventListener('focusin', function () { setHidden(false); });
		}
	});
})(window, document);
