/*! Nori: Scroll Expand. A framed .scroll-expand-np block opens to the full width as it scrolls up. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	N.start(function () {
		var els = d.querySelectorAll('.scroll-expand-np');
		if (!els.length || N.reducedMotion()) { return; }
		var opts = N.settings('scroll-expand', { start: 55, distance: 45 });
		var ticking = false;

		function update() {
			ticking = false;
			var vh = w.innerHeight;
			var start = vh * Number(opts.start) / 100;          // Starts opening when its top is here...
			var span = Math.max(1, vh * Number(opts.distance) / 100); // ...fully open this much later.
			for (var i = 0; i < els.length; i++) {
				var r = els[i].getBoundingClientRect();
				var p = Math.max(0, Math.min(1, (start - r.top) / span));
				els[i].style.setProperty('--nori-se-p', p.toFixed(3));
			}
		}
		function schedule() {
			if (!ticking) { ticking = true; w.requestAnimationFrame(update); }
		}
		w.addEventListener('scroll', schedule, { passive: true });
		w.addEventListener('resize', schedule, { passive: true });
		update();
	});
})(window, document);
