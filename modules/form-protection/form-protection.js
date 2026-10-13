/*! Nori: Form Spam Protection. Stops form spam without a CAPTCHA.
    1. An invisible honeypot field: people never see it, bots fill it in.
    2. A too-fast-to-be-human check: a form sent within the minimum time (1.2s by default)
       with no typing or clicking is dropped.
    3. A signed pass: after the page loads, the browser fetches a pass from this site and sends it
       with the entry (a hidden field, plus a short-lived cookie for Atomic forms). The server
       rejects entries without a valid, unused pass, or sent too soon after it was issued, so bots
       that post straight to WordPress are stopped (see includes/class-form-guard.php).
    What the browser stops is reported (just the reason) for the log in WP Admin. In test mode
    nothing is blocked, only logged. Works on Elementor Pro forms and Atomic forms. Other Nori
    modules (the HubSpot Bridge) ask NoriFE.isSpam(form) before sending anything. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N) { return; }

	var cfg = N.settings('form-protection', { minTime: 1200, logOnly: false, ajax: '' });
	var FIELD = 'nori_hp', TOKEN = 'nori_ft', HS_PASS = 'data-nori-hs-pass';
	var SELECTOR = 'form.elementor-form, form[data-element_type="e-form"]';
	var MIN_TIME = Number(cfg.minTime) || 0;
	var LOG_ONLY = !!cfg.logOnly;
	var REFRESH = 50 * 60 * 1000; // The server accepts a pass for 2 hours; get a fresh one well before.
	var state = w.WeakMap ? new w.WeakMap() : null;

	function info(form) {
		return state ? state.get(form) : form.__noriHp;
	}

	/* ------------------------------------------------------------ the pass */

	var pass = { value: '', got: 0, used: false, waiting: null };

	function post(action, extra) {
		var body = new w.FormData();
		body.append('action', action);
		Object.keys(extra || {}).forEach(function (k) { body.append(k, extra[k]); });
		return w.fetch(cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin', cache: 'no-store' });
	}

	function fetchPass() {
		if (pass.waiting) { return pass.waiting; }
		if (!cfg.ajax || !w.fetch) { return Promise.resolve(); }
		pass.waiting = post('nori_fe_form_token').then(function (r) { return r.json(); }).then(function (j) {
			if (j && j.success && j.data && j.data.token) {
				pass.value = j.data.token; pass.got = Date.now(); pass.used = false;
			}
		}).catch(function () {}).then(function () { pass.waiting = null; });
		return pass.waiting;
	}

	function passReady() {
		return pass.value && !pass.used && Date.now() - pass.got < REFRESH && Date.now() - pass.got >= MIN_TIME;
	}

	/** Get a usable pass (fresh, and at least the minimum time old), then call done. */
	function whenReady(done) {
		var start = Date.now();
		(function step() {
			if (passReady() || Date.now() - start > 15000) { done(); return; }
			if (!pass.value || pass.used || Date.now() - pass.got >= REFRESH) {
				fetchPass().then(function () { w.setTimeout(step, 30); });
				return;
			}
			w.setTimeout(step, Math.max(30, MIN_TIME - (Date.now() - pass.got) + 30));
		})();
	}

	function attachPass(form) {
		var s = info(form);
		if (s) { s.token.value = pass.value; }
		// Atomic forms send only their own fields, so the pass also travels as a cookie (10 minutes, this site only).
		d.cookie = TOKEN + '=' + encodeURIComponent(pass.value) + '; path=/; max-age=600; SameSite=Strict' + (w.location.protocol === 'https:' ? '; Secure' : '');
		pass.used = true;
		// The entry has been sent: get the next pass ready for another one.
		w.setTimeout(fetchPass, 2500);
	}

	/* ------------------------------------------------------------ checks */

	function report(why) {
		if (!cfg.ajax) { return; }
		try {
			var body = new w.FormData();
			body.append('action', 'nori_fe_spam_report');
			body.append('why', why);
			if (w.navigator.sendBeacon) { w.navigator.sendBeacon(cfg.ajax, body); } else { post('nori_fe_spam_report', { why: why }); }
		} catch (e) {}
	}

	function verdict(form) {
		var s = info(form);
		if (!s) { return ''; }
		if (s.input.value.trim() !== '') { return 'honeypot'; }
		if (MIN_TIME > 0 && Date.now() - s.loaded < MIN_TIME && !s.interacted) { return 'too_fast'; }
		return '';
	}

	/** True when this submission looks like a bot. Forms Nori hasn't protected are never spam. In test mode, never. */
	N.isSpam = function (form) {
		return !LOG_ONLY && verdict(form) !== '';
	};

	/** Is a HubSpot Bridge class on this form (and the bridge on)? */
	function bridged(form) {
		var hs = N.settings('hubspot-bridge', { bridges: [] });
		return !!(hs.bridges && hs.bridges.length) && /(^|\s)hs-bridge-[a-z0-9]{5}-np(\s|$)/.test(form.className);
	}

	function protect(form) {
		if (info(form)) { return; }

		var wrap = d.createElement('div');
		wrap.className = 'nori-hp-field';
		wrap.setAttribute('aria-hidden', 'true');
		var input = d.createElement('input');
		input.type = 'text';
		input.name = FIELD;
		input.tabIndex = -1;
		input.autocomplete = 'off';
		wrap.appendChild(input);
		var token = d.createElement('input');
		token.type = 'hidden';
		token.name = TOKEN;
		wrap.appendChild(token);
		form.appendChild(wrap);

		var s = { input: input, token: token, loaded: Date.now(), interacted: false };
		if (state) { state.set(form, s); } else { form.__noriHp = s; }
		form.setAttribute('data-nori-hp', '1');

		function mark() { s.interacted = true; }
		form.addEventListener('focusin', mark, { once: true });
		form.addEventListener('keydown', mark, { once: true });
		form.addEventListener('pointerdown', mark, { once: true });

		// Capture phase on the form itself, so this runs before Elementor's own submit handler.
		form.addEventListener('submit', function (e) {
			var why = verdict(form);
			if (why) {
				report(why);
				if (!LOG_ONLY) {
					e.preventDefault();
					e.stopImmediatePropagation();
					form.reset();
					return;
				}
			}
			// A HubSpot Bridge form goes to HubSpot first and hands over to Elementor afterwards: the pass is
			// only needed then (the bridge stops this first submit itself).
			var handOver = form.hasAttribute(HS_PASS);
			if (!handOver && bridged(form)) { return; }
			if (!passReady()) {
				// Still getting the pass (or it was just used): hold the entry a moment, then send it.
				e.preventDefault();
				e.stopImmediatePropagation();
				if (s.holding) { return; }
				s.holding = true;
				// Keep the bridge's hand-over mark, so the entry isn't sent to HubSpot twice.
				whenReady(function () {
					s.holding = false;
					if (handOver) { form.setAttribute(HS_PASS, '1'); }
					var ev;
					try { ev = new w.SubmitEvent('submit', { bubbles: true, cancelable: true }); } catch (x) { ev = new w.Event('submit', { bubbles: true, cancelable: true }); }
					if (handOver || !form.requestSubmit) { form.dispatchEvent(ev); } else { form.requestSubmit(); }
					if (handOver) { form.removeAttribute(HS_PASS); }
				});
				return;
			}
			attachPass(form);
		}, true);
	}

	function scan() {
		var forms = d.querySelectorAll(SELECTOR), found = false;
		for (var i = 0; i < forms.length; i++) { protect(forms[i]); found = true; }
		if (found && !pass.value && !pass.waiting) { fetchPass(); }
	}

	N.start(function () {
		scan();
		w.setInterval(function () { if (pass.value && Date.now() - pass.got > REFRESH) { fetchPass(); } }, 5 * 60 * 1000);
		// Forms inside popups appear later; rescan at most once per animation frame.
		if (w.MutationObserver) {
			var queued = false;
			new w.MutationObserver(function () {
				if (queued) { return; }
				queued = true;
				w.requestAnimationFrame(function () { queued = false; scan(); });
			}).observe(d.body, { childList: true, subtree: true });
		}
	});
})(window, document);
