/*! Nori: Button Text Roll. Wraps each piece of a .btn-roll-np button (its text and its icon) in a
    clipping box and adds a copy below it, so on hover the piece rolls up and the copy rolls in.
    The copies are hidden from screen readers, so the button's name is read once. Works with any
    icon (the copy is the real icon, not a stand-in shape). */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	var opts = N.settings('button-roll', { direction: 'up' });

	function copyOf(el) {
		var c = el.cloneNode(true);
		if (c.nodeType === 1) {
			c.removeAttribute('id');
			c.querySelectorAll('[id]').forEach(function (x) { x.removeAttribute('id'); });
			c.setAttribute('aria-hidden', 'true');
			c.classList.add('nori-roll__copy');
		}
		return c;
	}

	function wrap(btn, node, i) {
		var box = d.createElement('span');
		box.className = 'nori-roll';
		box.style.setProperty('--nori-roll-i', String(i));
		node.parentNode.insertBefore(box, node);
		box.appendChild(node);
		var copy = copyOf(node);
		if (copy.nodeType !== 1) { // A bare text node: its copy needs an element to carry the attributes.
			var s = d.createElement('span');
			s.appendChild(copy);
			copy = s;
			copy.setAttribute('aria-hidden', 'true');
			copy.className = 'nori-roll__copy';
		}
		box.appendChild(copy);
	}

	function build(btn) {
		if (btn.getAttribute('data-nori-roll')) { return; } // Idempotent.
		btn.setAttribute('data-nori-roll', '1');
		if (opts.direction === 'down') { btn.classList.add('nori-roll--down'); }
		var parts = Array.prototype.filter.call(btn.childNodes, function (n) {
			return n.nodeType === 1 ? !/^(SCRIPT|STYLE|TEMPLATE)$/.test(n.tagName) : n.nodeType === 3 && n.textContent.trim() !== '';
		});
		// A text node gets its own span first, so it can move.
		parts = parts.map(function (n) {
			if (n.nodeType !== 3) { return n; }
			var s = d.createElement('span');
			s.textContent = n.textContent.trim();
			n.parentNode.replaceChild(s, n);
			return s;
		});
		parts.forEach(function (n, i) { wrap(btn, n, i); });
	}

	N.start(function () {
		d.querySelectorAll('.btn-roll-np').forEach(build);
	});
})(window, document);
