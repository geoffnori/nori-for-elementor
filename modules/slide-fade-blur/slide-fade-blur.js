/*! Nori: Slide-Fade-Blur. One-shot staggered entrance when a .slide-fade-blur-np container enters the viewport. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	var opts = N.settings('slide-fade-blur', { duration: 650, stagger: 80, triggerOffset: 180, replay: false });
	var STEP = Number(opts.stagger);       // ms between children.
	var DURATION = Number(opts.duration);  // ms, matches --nori-sfb-duration.

	function children(parent) {
		var marked = parent.querySelectorAll('.cascade-item-np');
		if (marked.length) { return Array.prototype.slice.call(marked); }
		// Every direct child: Atomic widgets are bare tags, classic ones are .elementor-element wrappers.
		return Array.prototype.filter.call(parent.children, function (el) {
			return !el.classList.contains('cascade-ignore-np');
		});
	}

	function reveal(parent) {
		var items = children(parent);
		parent.classList.add('is-animating-np');
		items.forEach(function (el, i) { el.style.transitionDelay = (i * STEP) + 'ms'; });
		w.requestAnimationFrame(function () { parent.classList.add('is-visible-np'); });
		// Remove the delay and transition afterwards so hover effects are never slowed down.
		w.setTimeout(function () {
			items.forEach(function (el) { el.style.removeProperty('transition-delay'); });
			parent.classList.remove('is-animating-np');
		}, items.length * STEP + DURATION + 50);
	}

	N.start(function () {
		var parents = d.querySelectorAll('.slide-fade-blur-np');
		if (!parents.length) { return; }

		if (N.reducedMotion() || !('IntersectionObserver' in w)) {
			for (var i = 0; i < parents.length; i++) { parents[i].classList.add('is-visible-np'); }
			return;
		}

		var io = new w.IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					if (!opts.replay) { io.unobserve(entry.target); }
					if (!entry.target.classList.contains('is-visible-np')) { reveal(entry.target); }
				} else if (opts.replay && entry.boundingClientRect.top > 0) {
					// Scrolled back above it: hide again so it replays next time.
					entry.target.classList.remove('is-visible-np');
				}
			});
		}, { threshold: opts.replay ? 0 : 0.15, rootMargin: '0px 0px -' + Number(opts.triggerOffset) + 'px 0px' });

		for (var j = 0; j < parents.length; j++) { io.observe(parents[j]); }
	});
})(window, document);
