/*! Nori: Marquee. Clones the content of .custom-marquee-np for a gap-free loop.
    Clones get aria-hidden and lose their ids, so screen readers and crawlers see each item once. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	var opts = N.settings('marquee', { direction: 'left', pauseOnHover: true, pauseButton: true });

	function build(marquee) {
		if (marquee.getAttribute('data-nori-marquee') === 'ready') { return; } // Idempotent.

		// Carry the gap set in Elementor over to the loop before zeroing the container's own gap.
		var gap = w.getComputedStyle(marquee).columnGap;
		if (gap && gap !== 'normal' && !marquee.style.getPropertyValue('--marquee-gap')) {
			marquee.style.setProperty('--marquee-gap', gap);
		}

		var track = d.createElement('div');
		track.className = 'nori-marquee-track';
		while (marquee.firstChild) {
			track.appendChild(marquee.firstChild);
		}
		marquee.appendChild(track);

		for (var i = 0; i < 2; i++) {
			var clone = track.cloneNode(true);
			clone.setAttribute('aria-hidden', 'true');
			clone.removeAttribute('id');
			var withIds = clone.querySelectorAll('[id]');
			for (var j = 0; j < withIds.length; j++) { withIds[j].removeAttribute('id'); }
			var focusables = clone.querySelectorAll('a, button, input, select, textarea, [tabindex]');
			for (var k = 0; k < focusables.length; k++) { focusables[k].setAttribute('tabindex', '-1'); }
			marquee.appendChild(clone);
		}

		var speed = marquee.getAttribute('data-marquee-speed');
		if (speed && /^\d+(\.\d+)?$/.test(speed)) {
			marquee.style.setProperty('--marquee-speed', speed + 's');
		}

		if (!marquee.hasAttribute('data-marquee-direction') && opts.direction === 'right') {
			marquee.setAttribute('data-marquee-direction', 'right');
		}
		if (!opts.pauseOnHover) { marquee.classList.add('no-hover-pause-np'); }

		if (opts.pauseButton && !N.reducedMotion()) {
			var toggle = d.createElement('button');
			toggle.type = 'button';
			toggle.className = 'nori-marquee-toggle';
			toggle.setAttribute('aria-label', 'Pause scrolling');
			toggle.textContent = '❚❚';
			toggle.addEventListener('click', function () {
				var paused = marquee.classList.toggle('is-paused-np');
				toggle.setAttribute('aria-label', paused ? 'Play scrolling' : 'Pause scrolling');
				toggle.textContent = paused ? '▶' : '❚❚';
			});
			marquee.appendChild(toggle);
		}

		marquee.setAttribute('data-nori-marquee', 'ready');
		marquee.classList.add('nori-marquee-ready');
	}

	N.start(function () {
		var list = d.querySelectorAll('.custom-marquee-np');
		for (var i = 0; i < list.length; i++) { build(list[i]); }
	});
})(window, document);
