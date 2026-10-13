/*! Nori: Faded Carousel. An endless carousel with the center slide(s) in full and faded slides
    peeking in on both sides. Autoplay pauses on hover, keyboard focus and touch; drag or swipe to move.
    .faded-carousel-np runs on every screen size, .faded-carousel-mobile-np only up to 1024px wide. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N || !N.Carousel) { return; }

	var opts = N.settings('faded-carousel', { desktopCount: '3', autoplay: true, interval: 4, pauseButton: false });
	var small = w.matchMedia('(max-width: 1024px)'); // Elementor's tablet breakpoint.

	function make(root, mobileOnly) {
		if (root.getAttribute('data-nori-fc')) { return; } // Idempotent.
		root.setAttribute('data-nori-fc', '1');
		var c = new N.Carousel(root, {
			when: function () { return !mobileOnly || small.matches; },
			active: function () { return small.matches ? 1 : Math.max(1, parseInt(opts.desktopCount, 10) || 3); },
			autoplay: opts.autoplay ? opts.interval : 0,
			pauseButton: !!opts.pauseButton,
			minUnit: 4,
			centerOnClick: false
		});
	}

	N.start(function () {
		d.querySelectorAll('.faded-carousel-np').forEach(function (el) { make(el, false); });
		d.querySelectorAll('.faded-carousel-mobile-np').forEach(function (el) { make(el, !el.classList.contains('faded-carousel-np')); });
	});
})(window, document);
