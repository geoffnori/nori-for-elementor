/*! Nori for Elementor - shared helpers for every module. */
(function (w, d) {
	'use strict';
	var N = (w.NoriFE = w.NoriFE || {});

	/** Per-module settings from WP Admin > Nori > Modules, keyed by module key. */
	N.config = w.NoriFEConfig || N.config || {};

	/** One module's settings merged over its built-in defaults. */
	N.settings = function (key, defaults) {
		var out = {}, k, saved = N.config[key] || {};
		for (k in defaults) { if (Object.prototype.hasOwnProperty.call(defaults, k)) { out[k] = defaults[k]; } }
		for (k in saved) { if (Object.prototype.hasOwnProperty.call(saved, k)) { out[k] = saved[k]; } }
		return out;
	};

	/** True inside the Elementor editor or its preview frame: modules stay idle there. */
	N.isEditor = function () {
		try {
			if (w.elementorFrontend && typeof w.elementorFrontend.isEditMode === 'function' && w.elementorFrontend.isEditMode()) {
				return true;
			}
			var b = d.body;
			if (b && (b.classList.contains('elementor-editor-active') || b.classList.contains('elementor-editor-preview'))) {
				return true;
			}
			if ((w.location.search || '').indexOf('elementor-preview') !== -1) {
				return true;
			}
		} catch (e) {}
		return false;
	};

	N.reducedMotion = function () {
		return !!(w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches);
	};

	N.ready = function (fn) {
		if (d.readyState === 'loading') {
			d.addEventListener('DOMContentLoaded', fn, { once: true });
		} else {
			fn();
		}
	};

	/** Runs fn once per page load, outside the editor. */
	N.start = function (fn) {
		N.ready(function () {
			if (!N.isEditor()) {
				fn();
			}
		});
	};

	// Lets module CSS hide things only when JS is actually running (no-JS visitors still see content).
	d.documentElement.classList.add('nori-js');
})(window, document);
