/*! Nori: Exit-Fade-Up. Scroll-linked fade for .exit-fade-up-np elements. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	N.start(function () {
		var els = d.querySelectorAll('.exit-fade-up-np');
		if (!els.length || N.reducedMotion()) { return; }

		var opts = N.settings('exit-fade-up', { exitZone: 20, enterDistance: 200, exitShift: 30, enterShift: 40 });
		var ticking = false;

		function update() {
			var vh = w.innerHeight;
			var exitZone = vh * Number(opts.exitZone) / 100;       // Top part of the viewport.
			var enterStart = vh;                                   // Starts entering at the bottom edge...
			var enterEnd = vh - Number(opts.enterDistance);        // ...fully in this far above it.

			for (var i = 0; i < els.length; i++) {
				var el = els[i];
				var r = el.getBoundingClientRect();
				var p;
				if (r.bottom < exitZone) {
					p = Math.max(0, Math.min(1, (exitZone - r.bottom) / exitZone));
					el.style.opacity = String(1 - p);
					el.style.transform = 'translateY(' + (-Number(opts.exitShift) * p) + 'px)';
				} else if (r.top > enterEnd) {
					p = Math.max(0, Math.min(1, (enterStart - r.top) / (enterStart - enterEnd)));
					el.style.opacity = String(p);
					el.style.transform = 'translateY(' + (Number(opts.enterShift) * (1 - p)) + 'px)';
				} else {
					el.style.removeProperty('opacity');
					el.style.removeProperty('transform');
				}
			}
			ticking = false;
		}

		function schedule() {
			if (!ticking) {
				ticking = true;
				w.requestAnimationFrame(update);
			}
		}

		w.addEventListener('scroll', schedule, { passive: true });
		w.addEventListener('resize', schedule, { passive: true });
		update();
	});
})(window, document);
