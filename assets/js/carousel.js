/*! Nori for Elementor: carousel engine shared by the Faded Carousel and Video Carousel modules.
    Turns a flexbox into an endless, centered carousel. The flexbox's own children are the slides.
    Copies made for the loop are hidden from screen readers, lose their ids and can't be tabbed to. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N || N.Carousel) { return; }

	var CLONE_SETS = 2;

	function isFocusable(el) { return el.matches && el.matches('a[href], button, input, select, textarea, video[controls], [tabindex]'); }

	function strip(clone) {
		clone.classList.add('nori-carousel-clone');
		clone.setAttribute('aria-hidden', 'true');
		clone.removeAttribute('id');
		clone.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
		if (isFocusable(clone)) { clone.setAttribute('tabindex', '-1'); }
		clone.querySelectorAll('a[href], button, input, select, textarea, video, [tabindex]').forEach(function (el) { el.setAttribute('tabindex', '-1'); });
		return clone;
	}

	/**
	 * @param {HTMLElement} root The flexbox with the class.
	 * @param {Object} o
	 *   when()        true while the carousel should run (the mobile-only variant turns it off on desktop)
	 *   active()      how many slides are fully shown at the current width (1, 3 or 5)
	 *   autoplay      seconds between slides, 0 for none
	 *   pauseButton   show a pause/play button while autoplay runs
	 *   minUnit       repeat the slides until one loop has at least this many (short lists loop smoothly)
	 *   centerOnClick clicking a faded slide brings it to the center
	 *   onBuild(api), onActive(api), onTeardown(api)
	 */
	function Carousel(root, o) {
		this.root = root;
		this.o = o;
		this.built = false;
		this.paused = false;   // By the pause button: stays until pressed again.
		this.hover = false;    // Hover, keyboard focus or a touch: autoplay waits.
		this.focus = false;
		this.touch = false;
		this.timer = 0;
		this.resume = 0;
		this.settle = 0;
		this.index = 0;
		this.originals = Array.prototype.slice.call(root.children);
		this.check();
		var self = this;
		var width = root.clientWidth;
		function relayout() { self.check(); if (self.built) { self.measure(); self.center(self.index, true); } }
		w.addEventListener('resize', relayout);
		// Carousels in tabs, accordions and popups start hidden: line them up once they get a size.
		if (w.ResizeObserver) {
			new w.ResizeObserver(function () {
				if (root.clientWidth !== width) { width = root.clientWidth; relayout(); }
			}).observe(root);
		}
		d.addEventListener('visibilitychange', function () { if (d.hidden) { self.stop(); } else { self.play(); } });
	}

	Carousel.prototype.check = function () {
		var want = this.originals.length > 1 && this.o.when();
		if (want && !this.built) { this.build(); }
		if (!want && this.built) { this.teardown(); }
	};

	Carousel.prototype.build = function () {
		var root = this.root, self = this, originals = this.originals;
		var repeat = Math.max(1, Math.ceil((this.o.minUnit || 1) / originals.length));
		var unit = [], i, r;
		for (r = 0; r < repeat; r++) { for (i = 0; i < originals.length; i++) { unit.push(originals[i]); } }
		this.unit = unit.length;

		// The middle set is the originals plus, for short lists, copies that fill out one loop.
		for (r = 1; r < repeat; r++) {
			for (i = 0; i < originals.length; i++) { root.appendChild(strip(originals[i].cloneNode(true))); }
		}
		var before = d.createDocumentFragment(), after = d.createDocumentFragment();
		for (var s = 0; s < CLONE_SETS; s++) {
			for (i = 0; i < unit.length; i++) {
				before.appendChild(strip(unit[i].cloneNode(true)));
				after.appendChild(strip(unit[i].cloneNode(true)));
			}
		}
		root.insertBefore(before, root.firstChild);
		root.appendChild(after);
		this.slides = Array.prototype.slice.call(root.children);
		this.base = CLONE_SETS * this.unit;

		root.classList.add('nori-carousel-ready');
		root.setAttribute('data-nori-carousel', 'ready');
		if (!root.hasAttribute('role')) { root.setAttribute('role', 'region'); this.addedRole = true; }
		if (!root.hasAttribute('aria-roledescription')) { root.setAttribute('aria-roledescription', 'carousel'); }

		if (!this.bound) { this.bind(); this.bound = true; }
		this.built = true;
		this.measure();
		if (this.o.onBuild) { this.o.onBuild(this); }
		this.index = this.base + (this.o.active() > 1 ? Math.floor(this.o.active() / 2) : 0);
		this.center(this.index, true);
		// Fonts and images change widths after first paint: line up again once they settle.
		w.requestAnimationFrame(function () { self.center(self.index, true); });
		setTimeout(function () { self.center(self.index, true); }, 80);
		w.addEventListener('load', function () { if (self.built) { self.center(self.index, true); } }, { once: true });
		this.addButton();
		this.play();
	};

	Carousel.prototype.teardown = function () {
		this.stop();
		var root = this.root;
		root.querySelectorAll(':scope > .nori-carousel-clone').forEach(function (c) { c.remove(); });
		this.originals.forEach(function (s) { s.classList.remove('is-active-np'); });
		root.classList.remove('nori-carousel-ready', 'is-dragging-np', 'no-transition-np');
		root.removeAttribute('data-nori-carousel');
		if (this.addedRole) { root.removeAttribute('role'); this.addedRole = false; }
		root.removeAttribute('aria-roledescription');
		root.scrollLeft = 0;
		if (this.button) { this.button.remove(); this.button = null; }
		if (this.o.onTeardown) { this.o.onTeardown(this); }
		this.built = false;
	};

	/** Carries the flexbox gap from Elementor over to the slide-width math. */
	Carousel.prototype.measure = function () {
		var gap = w.getComputedStyle(this.root).columnGap;
		this.root.style.setProperty('--nori-carousel-gap', gap && gap !== 'normal' ? gap : '0px');
		this.root.style.setProperty('--nori-carousel-count', String(this.o.active()));
	};

	/** Scroll position that centers slide i. */
	Carousel.prototype.pos = function (i) {
		var s = this.slides[i];
		if (!s) { return 0; }
		var rootBox = this.root.getBoundingClientRect(), box = s.getBoundingClientRect();
		return this.root.scrollLeft + (box.left - rootBox.left) - (this.root.clientWidth - box.width) / 2;
	};

	Carousel.prototype.nearest = function () {
		var rootBox = this.root.getBoundingClientRect(), mid = rootBox.left + rootBox.width / 2, best = 0, diff = Infinity;
		for (var i = 0; i < this.slides.length; i++) {
			var b = this.slides[i].getBoundingClientRect(), dd = Math.abs(b.left + b.width / 2 - mid);
			if (dd < diff) { diff = dd; best = i; }
		}
		return best;
	};

	Carousel.prototype.mark = function () {
		if (!this.root.clientWidth) { return; } // Hidden (a closed tab or popup): nothing to measure yet.
		var c = this.nearest(), half = Math.floor(this.o.active() / 2);
		this.index = c;
		for (var i = 0; i < this.slides.length; i++) {
			this.slides[i].classList.toggle('is-active-np', i >= c - half && i <= c + half);
		}
		if (this.o.onActive) { this.o.onActive(this); }
	};

	Carousel.prototype.frozen = function (fn) {
		var root = this.root;
		root.classList.add('no-transition-np');
		fn();
		void root.offsetWidth;
		w.requestAnimationFrame(function () { root.classList.remove('no-transition-np'); });
	};

	Carousel.prototype.center = function (i, instant) {
		var self = this;
		this.index = i;
		if (instant) {
			this.frozen(function () { self.root.scrollLeft = self.pos(i); self.mark(); });
		} else {
			this.root.scrollTo({ left: this.pos(i), behavior: N.reducedMotion() ? 'auto' : 'smooth' });
		}
	};

	Carousel.prototype.go = function (step) { this.center(this.index + step, false); };

	/** After scrolling stops, jump back into the middle set by exactly one loop: no visible change. */
	Carousel.prototype.normalize = function () {
		if (!this.built || this.dragging || !this.root.clientWidth) { return; }
		var c = this.nearest(), self = this;
		if (c >= this.base && c < this.base + this.unit) { return; }
		var steps = c >= this.base + this.unit ? -Math.floor((c - this.base) / this.unit) : Math.ceil((this.base - c) / this.unit);
		var target = c + steps * this.unit;
		var shift = this.pos(target) - this.pos(c);
		this.frozen(function () { self.root.scrollLeft += shift; self.mark(); });
	};

	Carousel.prototype.play = function () {
		this.stop();
		var secs = Number(this.o.autoplay) || 0, self = this;
		if (!this.built || !secs || this.paused || this.hover || this.focus || this.touch || this.dragging || d.hidden || N.reducedMotion()) { return; }
		this.timer = setInterval(function () { self.go(1); }, secs * 1000);
	};

	Carousel.prototype.stop = function () { clearInterval(this.timer); this.timer = 0; };

	/** Hover, focus and touch each hold autoplay; it restarts once none of them is left. */
	Carousel.prototype.hold = function (why, on) {
		this[why] = on;
		if (on) { this.stop(); } else { this.play(); }
	};

	Carousel.prototype.addButton = function () {
		if (!this.o.pauseButton || !Number(this.o.autoplay) || N.reducedMotion() || this.button) { return; }
		var self = this, b = d.createElement('button');
		b.type = 'button';
		b.className = 'nori-carousel-toggle';
		b.setAttribute('aria-label', 'Pause carousel');
		b.textContent = '❚❚';
		b.addEventListener('click', function () {
			self.paused = !self.paused;
			b.setAttribute('aria-label', self.paused ? 'Play carousel' : 'Pause carousel');
			b.textContent = self.paused ? '▶' : '❚❚';
			if (self.paused) { self.stop(); } else { self.play(); }
		});
		this.root.parentNode.insertBefore(b, this.root.nextSibling);
		this.button = b;
	};

	Carousel.prototype.bind = function () {
		var self = this, root = this.root, ticking = false;

		root.addEventListener('scroll', function () {
			if (!self.built) { return; }
			if (!ticking) { ticking = true; w.requestAnimationFrame(function () { ticking = false; self.mark(); }); }
			clearTimeout(self.settle);
			self.settle = setTimeout(function () { self.normalize(); }, 140);
		}, { passive: true });
		if ('onscrollend' in w) {
			root.addEventListener('scrollend', function () { clearTimeout(self.settle); self.normalize(); });
		}

		// Hover and keyboard focus pause autoplay; touch pauses it for a moment.
		root.addEventListener('mouseenter', function () { self.hold('hover', true); });
		root.addEventListener('mouseleave', function () { self.hold('hover', false); });
		root.addEventListener('focusin', function (e) {
			self.hold('focus', true);
			var slide = e.target.closest ? e.target.closest('.nori-carousel-ready > *') : null;
			var i = slide ? self.slides.indexOf(slide) : -1;
			if (i !== -1 && !slide.classList.contains('is-active-np')) { self.center(i, false); }
		});
		root.addEventListener('focusout', function (e) { if (!root.contains(e.relatedTarget)) { self.hold('focus', false); } });
		root.addEventListener('touchstart', function () { clearTimeout(self.resume); self.hold('touch', true); }, { passive: true });
		root.addEventListener('touchend', function () {
			clearTimeout(self.resume);
			self.resume = setTimeout(function () { self.hold('touch', false); }, 3500);
		}, { passive: true });
		root.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { e.preventDefault(); self.go(1); }
			if (e.key === 'ArrowLeft') { e.preventDefault(); self.go(-1); }
		});

		// Click and drag with a mouse. A real drag never counts as a click on a link or a video.
		var down = false, startX = 0, startLeft = 0, moved = false;
		root.addEventListener('mousedown', function (e) {
			if (!self.built || e.button !== 0 || (e.target.closest && e.target.closest('button:not([data-nori-drag]), input, select, textarea, video[controls]'))) { return; }
			down = true; moved = false; startX = e.clientX; startLeft = root.scrollLeft;
			self.dragging = true;
			e.preventDefault();
		});
		w.addEventListener('mousemove', function (e) {
			if (!down) { return; }
			var walk = e.clientX - startX;
			if (!moved && Math.abs(walk) > 6) { moved = true; root.classList.add('is-dragging-np'); }
			if (moved) { root.scrollLeft = startLeft - walk * 1.2; }
		});
		w.addEventListener('mouseup', function () {
			if (!down) { return; }
			down = false;
			self.dragging = false;
			root.classList.remove('is-dragging-np');
			if (moved) { self.center(self.nearest(), false); }
			self.play();
		});
		root.addEventListener('click', function (e) {
			if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; return; }
			if (!self.o.centerOnClick) { return; }
			var slide = e.target.closest ? e.target.closest('.nori-carousel-ready > *') : null;
			var i = slide ? self.slides.indexOf(slide) : -1;
			if (i !== -1 && !slide.classList.contains('is-active-np')) { e.preventDefault(); e.stopPropagation(); self.center(i, false); }
		}, true);
		root.addEventListener('dragstart', function (e) { e.preventDefault(); });
	};

	N.Carousel = Carousel;
})(window, document);
