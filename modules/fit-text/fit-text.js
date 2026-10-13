/*! Nori: Fit Text. Sizes .fit-text-np text so it fills its container's width on one line. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	N.start(function () {
		var els = d.querySelectorAll('.fit-text-np');
		if (!els.length) { return; }
		var opts = N.settings('fit-text', { min: 32, max: 400 });

		function fit(el) {
			el.style.removeProperty('font-size');
			el.style.whiteSpace = 'nowrap';
			var cs = w.getComputedStyle(el);
			var avail = el.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
			var range = d.createRange();
			range.selectNodeContents(el);
			var used = range.getBoundingClientRect().width;
			if (!avail || !used) { return; }
			var base = parseFloat(cs.fontSize);
			var size = base * (avail / used) * 0.995;
			if (size < Number(opts.min)) {
				// Too long for one line at a readable size (a long name on a phone): stack the words
				// instead, sized so the longest word fills the width.
				el.style.whiteSpace = 'normal';
				var widest = longestWord(el, cs);
				if (widest) { size = base * (avail / widest) * 0.97; }
			}
			size = Math.max(Number(opts.min), Math.min(Number(opts.max), size));
			el.style.fontSize = size.toFixed(2) + 'px';
			el.classList.add('nori-fit-ready');
			el.dispatchEvent(new w.CustomEvent('nori:fit', { bubbles: true }));
		}
		var ctx = d.createElement('canvas').getContext('2d');
		function longestWord(el, cs) {
			if (!ctx) { return 0; }
			ctx.font = cs.fontStyle + ' ' + cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;
			var ls = parseFloat(cs.letterSpacing) || 0, max = 0;
			var t = el.textContent || '';
			if (cs.textTransform === 'uppercase') { t = t.toUpperCase(); }
			t.split(/\s+/).forEach(function (wd) {
				if (wd) { max = Math.max(max, ctx.measureText(wd).width + ls * wd.length); }
			});
			return max;
		}
		function all() {
			for (var i = 0; i < els.length; i++) { fit(els[i]); }
		}
		all();
		if (d.fonts && d.fonts.ready) { d.fonts.ready.then(all); }
		var t = null;
		if ('ResizeObserver' in w) {
			var ro = new w.ResizeObserver(function () {
				if (!t) { t = w.requestAnimationFrame(function () { t = null; all(); }); }
			});
			for (var i = 0; i < els.length; i++) { if (els[i].parentElement) { ro.observe(els[i].parentElement); } }
		} else {
			w.addEventListener('resize', all, { passive: true });
		}
	});
})(window, document);
