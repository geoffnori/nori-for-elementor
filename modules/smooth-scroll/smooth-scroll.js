/*! Nori: Smooth Scroll. Momentum scrolling via Lenis; off for reduced motion and in the editor. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	N.start(function () {
		if (N.reducedMotion() || typeof w.Lenis === 'undefined') { return; }
		var opts = N.settings('smooth-scroll', { duration: 0.8, wheelMultiplier: 1, syncTouch: false, anchorOffset: 0 });
		var lenis = new w.Lenis({
			duration: Number(opts.duration) || 0.8,
			easing: function (t) { return Math.min(1, 1.001 - Math.pow(2, -10 * t)); },
			orientation: 'vertical',
			gestureOrientation: 'vertical',
			smoothWheel: true,
			wheelMultiplier: Number(opts.wheelMultiplier) || 1,
			syncTouch: !!opts.syncTouch,
			touchMultiplier: 1.5
		});

		function raf(time) {
			lenis.raf(time);
			w.requestAnimationFrame(raf);
		}
		w.requestAnimationFrame(raf);

		// In-page anchors go through Lenis so they glide instead of jumping.
		d.addEventListener('click', function (e) {
			var link = e.target.closest && e.target.closest('a[href^="#"]');
			if (!link || link.getAttribute('href').length < 2) { return; }
			var target = d.getElementById(decodeURIComponent(link.getAttribute('href').slice(1)));
			if (!target) { return; }
			e.preventDefault();
			lenis.scrollTo(target, { offset: -(Number(opts.anchorOffset) || 0) });
		});

		N.lenis = lenis;
		w.siteLenis = lenis; // Backwards compatible with the old snippet.
	});
})(window, document);
