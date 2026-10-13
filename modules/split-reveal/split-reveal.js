/*! Nori: Split Reveal. Words of .split-reveal-np text rise into place one after another. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	function split(el, out) {
		Array.prototype.slice.call(el.childNodes).forEach(function (n) {
			if (n.nodeType === 3) {
				var frag = d.createDocumentFragment();
				n.textContent.split(/(\s+)/).forEach(function (p) {
					if (!p) { return; }
					if (/^\s+$/.test(p)) { frag.appendChild(d.createTextNode(p)); return; }
					var outer = d.createElement('span');
					outer.className = 'nori-sr-w';
					var inner = d.createElement('span');
					inner.textContent = p;
					outer.appendChild(inner);
					frag.appendChild(outer);
					out.push(inner);
				});
				n.parentNode.replaceChild(frag, n);
			} else if (n.nodeType === 1 && !/^(SCRIPT|STYLE|svg|SVG)$/.test(n.nodeName)) {
				split(n, out);
			}
		});
	}

	N.start(function () {
		var els = d.querySelectorAll('.split-reveal-np');
		if (!els.length || N.reducedMotion()) { return; }
		var opts = N.settings('split-reveal', { stagger: 60, duration: 900 });

		function reveal(el) {
			el.classList.add('nori-sr-in');
			// Delays are only for the entrance; drop them once it's done.
			w.setTimeout(function () {
				var ws = el.querySelectorAll('.nori-sr-w > span');
				for (var i = 0; i < ws.length; i++) { ws[i].style.removeProperty('transition-delay'); }
				el.classList.add('nori-sr-done');
			}, Number(opts.duration) + ws_count(el) * Number(opts.stagger) + 50);
		}
		function ws_count(el) { return el.querySelectorAll('.nori-sr-w').length; }

		// Inside a section that Slide-Fade-Blur or Exit-Fade-Up is animating, wait for the section to
		// arrive first, so the two animations follow each other instead of overlapping.
		function whenSettled(el, go) {
			var sfb = el.closest('.slide-fade-blur-np');
			if (sfb && !sfb.classList.contains('is-visible-np')) {
				var mo = new w.MutationObserver(function () {
					if (sfb.classList.contains('is-visible-np')) { mo.disconnect(); w.setTimeout(go, 150); }
				});
				mo.observe(sfb, { attributes: true, attributeFilter: ['class'] });
				return;
			}
			var efu = el.closest('.exit-fade-up-np');
			if (efu) {
				var tries = 0;
				(function check() {
					// Exit-Fade-Up sets an inline opacity while it fades a section in; none means it has arrived.
					var o = efu.style.opacity;
					if (o === '' || Number(o) >= 0.85 || ++tries > 120) { go(); } else { w.requestAnimationFrame(check); }
				})();
				return;
			}
			go();
		}

		var io = 'IntersectionObserver' in w ? new w.IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) { io.unobserve(en.target); whenSettled(en.target, function () { reveal(en.target); }); }
			});
		}, { rootMargin: '0px 0px -10% 0px' }) : null;

		for (var i = 0; i < els.length; i++) {
			var el = els[i], list = [];
			// Video text draws the letters as a still mask over the video, so the words can't rise
			// there: leave that text whole.
			if (el.closest('.video-text-np')) { continue; }
			split(el, list);
			list.forEach(function (sp, j) { sp.style.transitionDelay = (j * Number(opts.stagger)) + 'ms'; });
			el.classList.add('nori-sr');
			if (io) { io.observe(el); } else { reveal(el); }
		}
	});
})(window, document);
