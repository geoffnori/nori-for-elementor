/*! Nori: Accessibility Enhancements. Labels icon-only social and logo links, hides decorative SVGs. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	var SOCIAL = {
		'instagram.com': 'Instagram', 'linkedin.com': 'LinkedIn', 'facebook.com': 'Facebook',
		'twitter.com': 'X (Twitter)', 'x.com': 'X (Twitter)', 'youtube.com': 'YouTube',
		'tiktok.com': 'TikTok', 'pinterest.com': 'Pinterest', 'threads.net': 'Threads'
	};

	function host(url) {
		try { return new URL(url, w.location.href).hostname.replace(/^www\./, '').toLowerCase(); } catch (e) { return ''; }
	}

	function hasName(el) {
		return el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.getAttribute('title') || el.textContent.trim();
	}

	var opts = N.settings('accessibility', { autoLabels: true });

	N.start(function () {
		var links = opts.autoLabels ? d.querySelectorAll('a[href]') : [];

		var siteHost = host(w.location.href);
		for (var i = 0; i < links.length; i++) {
			var a = links[i];
			if (hasName(a)) { continue; }
			var h = host(a.href);
			if (SOCIAL[h]) {
				a.setAttribute('aria-label', SOCIAL[h]);
			} else if (h === siteHost && (a.pathname === '/' || a.pathname === '') && a.closest('header, footer')) {
				a.setAttribute('aria-label', 'Home');
			}
		}

		var svgs = d.querySelectorAll('a svg, button svg');
		for (var j = 0; j < svgs.length; j++) {
			var svg = svgs[j];
			if (!svg.hasAttribute('aria-hidden') && !svg.getAttribute('aria-label') && !svg.querySelector('title')) {
				svg.setAttribute('aria-hidden', 'true');
				svg.setAttribute('focusable', 'false');
			}
		}
	});
})(window, document);
