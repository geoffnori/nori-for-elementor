/*! Nori: Background Video. Playback rules and video text for Elementor's Background Video element. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	var opts = N.settings('background-video', { phones: true, offscreen: true, shape: 'even' });

	/* ---------------------------------------------------------------- playback */
	function setup(root) {
		var v = root.querySelector('.e-background-video__media');
		if (!v || root.classList.contains('nori-bgv')) { return; }
		root.classList.add('nori-bgv');
		if (opts.shape === 'bottom' || opts.shape === 'side') { root.classList.add('nori-bgv--' + opts.shape); }

		var small = w.matchMedia && w.matchMedia('(max-width: 767px)').matches;
		var saveData = !!(w.navigator.connection && w.navigator.connection.saveData);
		if ((small && !opts.phones) || saveData) {
			// No download at all: drop the source. The section keeps its background color.
			v.pause();
			var s = v.querySelectorAll('source');
			for (var i = 0; i < s.length; i++) { s[i].removeAttribute('src'); }
			v.removeAttribute('src');
			v.load();
			v.addEventListener('play', function () { v.pause(); });
			root.classList.add('nori-bgv-off');
			return;
		}

		var reduce = N.reducedMotion();
		var wants = !reduce;     // Whether the video should be playing when it's on screen.
		var visible = true;
		var covered = false;     // Nori's Lightbox is open over the page.

		// The visitor's own play and pause buttons decide from then on.
		root.addEventListener('click', function (e) {
			if (!e.target || !e.target.closest) { return; }
			if (e.target.closest('.e-background-video__play')) { wants = true; }
			if (e.target.closest('.e-background-video__pause')) { wants = false; }
		}, true);

		// Elementor starts autoplay itself; hold it back while it shouldn't play.
		v.addEventListener('play', function () {
			if (!wants || covered || (opts.offscreen && !visible)) {
				v.pause();
			}
		});
		if (reduce) {
			v.removeAttribute('autoplay');
			v.autoplay = false;
			v.pause();
		}

		if (opts.offscreen && 'IntersectionObserver' in w) {
			new w.IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					visible = en.isIntersecting;
					if (!visible && !v.paused) {
						v.pause();
					} else if (visible && wants && !covered && v.paused) {
						var p = v.play();
						if (p && p.catch) { p.catch(function () {}); }
					}
				});
			}, { rootMargin: '80px 0px' }).observe(root);
		}

		// Nori's Lightbox covers the page: no need to keep decoding a video nobody can see.
		if ('MutationObserver' in w && d.body) {
			new w.MutationObserver(function () {
				var c = d.body.classList.contains('plb-scroll-locked');
				if (c === covered) { return; }
				covered = c;
				if (c) {
					if (!v.paused) { v.pause(); }
				} else if (wants && (!opts.offscreen || visible) && v.paused) {
					var p = v.play();
					if (p && p.catch) { p.catch(function () {}); }
				}
			}).observe(d.body, { attributes: true, attributeFilter: ['class'] });
		}
	}

	/* ---------------------------------------------------------------- video text */
	// Wrap each word of the text in a span, so its place on the page can be measured.
	function words(el, out) {
		var kids = Array.prototype.slice.call(el.childNodes);
		kids.forEach(function (n) {
			if (n.nodeType === 3) {
				var parts = n.textContent.split(/(\s+)/), frag = d.createDocumentFragment();
				parts.forEach(function (p) {
					if (!p) { return; }
					if (/^\s+$/.test(p)) { frag.appendChild(d.createTextNode(p)); return; }
					var sp = d.createElement('span');
					sp.className = 'nori-vt-w';
					sp.textContent = p;
					frag.appendChild(sp);
					out.push(sp);
				});
				n.parentNode.replaceChild(frag, n);
			} else if (n.nodeType === 1 && !/^(SCRIPT|STYLE|SVG|svg)$/.test(n.nodeName)) {
				words(n, out);
			}
		});
	}

	function caseOf(t, tt) {
		if (tt === 'uppercase') { return t.toUpperCase(); }
		if (tt === 'lowercase') { return t.toLowerCase(); }
		if (tt === 'capitalize') { return t.replace(/(^|\s)(\S)/g, function (m, a, b) { return a + b.toUpperCase(); }); }
		return t;
	}

	function videoText(root) {
		var v = root.querySelector('.e-background-video__media');
		var content = root.querySelector('.e-background-video__content');
		// No video (switched off on phones, data saver): the heading stays plain, solid text.
		if (!v || !content || root.classList.contains('nori-bgv-off')) { return; }
		var spans = [];
		var texts = content.querySelectorAll('h1,h2,h3,h4,h5,h6,p');
		for (var i = 0; i < texts.length; i++) { words(texts[i], spans); }
		if (!spans.length) { return; }

		var c = d.createElement('canvas');
		var x = c.getContext('2d');
		if (!x) { return; }
		var timer = null;

		function draw() {
			timer = null;
			var rb = v.getBoundingClientRect();
			if (!rb.width || !rb.height) { return; }
			var s = Math.min(w.devicePixelRatio || 1, 2);
			c.width = Math.round(rb.width * s);
			c.height = Math.round(rb.height * s);
			x.setTransform(s, 0, 0, s, 0, 0);
			x.clearRect(0, 0, rb.width, rb.height);
			x.fillStyle = '#000';
			x.textBaseline = 'alphabetic';
			spans.forEach(function (sp) {
				var r = sp.getBoundingClientRect();
				if (!r.width) { return; }
				var cs = w.getComputedStyle(sp);
				x.font = cs.fontStyle + ' ' + cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;
				if ('letterSpacing' in x) { x.letterSpacing = cs.letterSpacing === 'normal' ? '0px' : cs.letterSpacing; }
				var t = caseOf(sp.textContent, cs.textTransform);
				var m = x.measureText(t);
				var asc = m.fontBoundingBoxAscent || m.actualBoundingBoxAscent || parseFloat(cs.fontSize) * 0.8;
				var desc = m.fontBoundingBoxDescent || m.actualBoundingBoxDescent || parseFloat(cs.fontSize) * 0.2;
				var y = r.top - rb.top + (r.height - (asc + desc)) / 2 + asc;
				// Stretch to the measured box, so a missing letterSpacing in older browsers still lines up.
				var sx = m.width ? r.width / m.width : 1;
				x.save();
				x.translate(r.left - rb.left, 0);
				x.scale(sx > 0.8 && sx < 1.25 ? sx : 1, 1);
				x.fillText(t, 0, y);
				x.restore();
			});
			var url = 'url(' + c.toDataURL('image/png') + ')';
			v.style.webkitMaskImage = url;
			v.style.maskImage = url;
			drawn = true;
			reveal();
		}
		// The letters only go see-through once the video has a picture to show through them, so a
		// slow, failed or blocked video never leaves the heading blank.
		var drawn = false;
		function reveal() {
			if (drawn && v.readyState >= 2 && !v.error) { root.classList.add('nori-vt-ready'); }
		}
		v.addEventListener('loadeddata', reveal);
		v.addEventListener('error', function () { root.classList.remove('nori-vt-ready'); }, true);
		function later() {
			if (!timer) { timer = w.requestAnimationFrame(draw); }
		}
		later();
		if (d.fonts && d.fonts.ready) { d.fonts.ready.then(later); }
		if ('ResizeObserver' in w) { new w.ResizeObserver(later).observe(root); }
		root.addEventListener('nori:fit', later);
		w.addEventListener('resize', later, { passive: true });
	}

	N.start(function () {
		var roots = d.querySelectorAll('.bg-video-np, .video-text-np');
		for (var i = 0; i < roots.length; i++) {
			setup(roots[i]);
			if (roots[i].classList.contains('video-text-np')) { videoText(roots[i]); }
		}
	});
})(window, document);
