/*! Nori: HubSpot Bridge. Sends an Elementor Atomic form's entry to a HubSpot form.
    Put a bridge class (hs-bridge-xxxxx-np) on the Atomic form. On submit:
    1. Form Spam Protection (if on) gets the first say.
    2. The fields the HubSpot form defines are sent to HubSpot's public Forms API, with the
       visitor's HubSpot tracking cookie (hubspotutk), the page URL and the page title.
    3. Only if HubSpot accepts the entry does Elementor run its own actions (email, collect
       submissions) and show its success message. Otherwise the form shows its error message.
    Fields are matched by ID or name: an input whose ID or name is "email", or whose ID ends
    with it ("e-form-email", "contact-email"), fills the HubSpot field "email". */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N || !w.fetch) { return; }

	var cfg = N.settings('hubspot-bridge', { bridges: [], report: '', respectPrivacy: true });
	var bridges = cfg.bridges || [];
	if (!bridges.length) { return; }

	var PASS = 'data-nori-hs-pass';
	var bound = w.WeakSet ? new w.WeakSet() : null;

	/* ------------------------------------------------------------ matching */

	function norm(s) { return String(s || '').toLowerCase().replace(/[^a-z0-9]/g, ''); }

	/** Same rule as HubSpot_Bridges::match_score() in PHP. */
	function score(field, identifier, isName) {
		var f = norm(field), id = String(identifier || '');
		if (!f || !id) { return 0; }
		if (norm(id) === f) { return isName ? 3 : 2; }
		var parts = id.toLowerCase().split(/[^a-z0-9]+/).filter(Boolean), tail = '';
		for (var i = parts.length - 1; i >= 0; i--) {
			tail = parts[i] + tail;
			if (tail === f) { return 1; }
			if (tail.length >= f.length) { break; }
		}
		return 0;
	}

	/** The form's inputs as groups: radios and checkboxes that share a name are one input. */
	function inputsOf(form) {
		var list = [], byName = {};
		var els = form.querySelectorAll('input, textarea, select');
		for (var i = 0; i < els.length; i++) {
			var el = els[i], type = (el.getAttribute('type') || '').toLowerCase();
			if (el.name === 'nori_hp' || el.closest('.nori-hp-field') || ['submit', 'button', 'reset', 'image'].indexOf(type) !== -1) { continue; }
			var name = el.getAttribute('name') || '';
			if ((type === 'radio' || type === 'checkbox') && name) {
				if (byName[name]) { byName[name].els.push(el); continue; }
				byName[name] = { id: el.id, name: name, els: [el] };
				list.push(byName[name]);
				continue;
			}
			list.push({ id: el.id, name: name, els: [el] });
		}
		return list;
	}

	function match(fields, inputs) {
		var order = fields.slice().sort(function (a, b) { return norm(b.n).length - norm(a.n).length; });
		var taken = [], out = {};
		order.forEach(function (f) {
			var best = -1, top = 0;
			inputs.forEach(function (inp, i) {
				if (taken[i]) { return; }
				var s = Math.max(score(f.n, inp.name, true), score(f.n, inp.id, false));
				if (s > top) { top = s; best = i; }
			});
			if (best >= 0) { taken[best] = true; out[f.n] = inputs[best]; }
		});
		return out;
	}

	/** The value HubSpot expects for one input group. Multiple choices are joined with ";". */
	function valueOf(inp, field) {
		var els = inp.els, first = els[0], type = (first.getAttribute('type') || first.tagName).toLowerCase();
		if (type === 'checkbox') {
			if (els.length === 1) {
				if (field.t === 'booleancheckbox') { return first.checked ? 'true' : 'false'; }
				return first.checked ? (first.value && first.value !== 'on' ? first.value : 'true') : '';
			}
			return els.filter(function (e) { return e.checked; }).map(function (e) { return e.value; }).join(';');
		}
		if (type === 'radio') {
			var on = els.filter(function (e) { return e.checked; })[0];
			return on ? on.value : '';
		}
		if (first.tagName === 'SELECT' && first.multiple) {
			return Array.prototype.filter.call(first.options, function (o) { return o.selected; }).map(function (o) { return o.value; }).join(';');
		}
		return String(first.value || '').trim();
	}

	/* ------------------------------------------------------------ submit */

	function cookie(name) {
		var m = d.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
		return m ? decodeURIComponent(m[1]) : '';
	}

	/**
	 * May the visitor's HubSpot tracking cookie go with the entry? With Respect privacy choices on
	 * (the default), not when the browser sends Global Privacy Control, the visitor declined
	 * HubSpot's cookie banner (__hs_opt_out=yes) or used its do-not-track link (__hs_do_not_track),
	 * or a consent plugin using the WP Consent API has no marketing consent. The entry itself is still sent, just not tied to their browsing.
	 */
	function trackingAllowed() {
		if (!cfg.respectPrivacy) { return true; }
		var nav = w.navigator || {};
		if (nav.globalPrivacyControl === true || nav.globalPrivacyControl === '1') { return false; }
		if (cookie('__hs_opt_out') === 'yes' || cookie('__hs_do_not_track')) { return false; }
		if (typeof w.wp_has_consent === 'function') {
			try { return !!w.wp_has_consent('marketing'); } catch (e) { return false; }
		}
		return true;
	}

	function setState(form, state) {
		form.classList.remove('form-state-default', 'form-state-success', 'form-state-error');
		form.classList.add('form-state-' + state);
		var msg = form.querySelector('[data-e-type="e-form-' + state + '-message"]');
		if (msg && msg.focus) { try { msg.focus({ focusVisible: true }); } catch (e) { msg.focus(); } }
	}

	function busy(form, on) {
		form.setAttribute('aria-busy', on ? 'true' : 'false');
		var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
		for (var i = 0; i < buttons.length; i++) { buttons[i].disabled = on; }
	}

	function report(bridge, ok, errors) {
		if (!cfg.report) { return; }
		try {
			var headers = { 'Content-Type': 'application/json' };
			if (cfg.nonce) {
				headers['X-WP-Nonce'] = cfg.nonce;
			}
			w.fetch(cfg.report, {
				method: 'POST',
				headers: headers,
				body: JSON.stringify({ bridge: bridge.id, ok: ok, errors: errors || [] }),
				keepalive: true,
				credentials: 'same-origin'
			}).catch(function () {});
		} catch (e) {}
	}

	/** Let Elementor's own submit run now that HubSpot has the entry. */
	function handOver(form) {
		if (form.hasAttribute('x-data')) {
			form.setAttribute(PASS, '1');
			var ev;
			try { ev = new w.SubmitEvent('submit', { bubbles: true, cancelable: true }); } catch (e) { ev = new w.Event('submit', { bubbles: true, cancelable: true }); }
			form.dispatchEvent(ev);
			form.removeAttribute(PASS);
			return;
		}
		// Not handled by Elementor's form script: show the success state ourselves.
		setState(form, 'success');
		form.reset();
	}

	function send(form, bridge) {
		var inputs = inputsOf(form);
		var found = match(bridge.fields, inputs);
		var fields = [], missing = [];
		bridge.fields.forEach(function (f) {
			var inp = found[f.n];
			if (!inp) {
				// Hidden HubSpot fields are filled as HubSpot's embed does: a preset value, or for tracking
				// fields (utm_source, gclid...) the value in the page address.
				if (f.h) {
					var hv = f.d || param(f.n);
					if (hv) { fields.push(f.o ? { name: f.n, value: hv, objectTypeId: f.o } : { name: f.n, value: hv }); }
					return;
				}
				if (f.r && !f.h) { missing.push(f.n); }
				return;
			}
			var v = valueOf(inp, f);
			if (v === '') {
				if (f.r) { missing.push(f.n); }
				return;
			}
			var row = { name: f.n, value: v };
			if (f.o) { row.objectTypeId = f.o; }
			fields.push(row);
		});

		if (missing.length) {
			if (w.console) { w.console.warn('[Nori HubSpot Bridge] ' + bridge.cls + ': required HubSpot fields are missing or empty: ' + missing.join(', ') + '. Give an input in this form the ID or name ' + missing.join(' / ') + '.'); }
			setState(form, 'error');
			report(bridge, false, missing.map(function (n) { return { errorType: 'REQUIRED_FIELD', message: 'Missing field ' + n }; }));
			return;
		}

		var legal = consentOptions(form, bridge.c);
		if (legal.missing.length) {
			if (w.console) { w.console.warn('[Nori HubSpot Bridge] ' + bridge.cls + ': required consent checkboxes are not ticked or missing: ' + legal.missing.join(', ') + '.'); }
			setState(form, 'error');
			// A checkbox that isn't on the form at all is a setup problem: tell the bridge card.
			if (legal.absent.length) { report(bridge, false, legal.absent.map(function (n) { return { errorType: 'REQUIRED_FIELD', message: 'Missing consent checkbox ' + n }; })); }
			return;
		}

		var context = { pageUri: w.location.href, pageName: d.title };
		var hutk = trackingAllowed() ? cookie('hubspotutk') : '';
		if (hutk) { context.hutk = hutk; }

		busy(form, true);
		post(form, bridge, fields, context, [], legal.options ? { legalConsentOptions: legal.options } : {});
	}

	function param(name) {
		try { return new w.URLSearchParams(w.location.search).get(name) || ''; } catch (e) { return ''; }
	}

	/** The checkbox for a consent: ID or name "consent-<subscription id>", or "consent-to-process". */
	function consentBox(form, key, onlyOne) {
		var boxes = form.querySelectorAll('input[type="checkbox"]'), loose = null;
		for (var i = 0; i < boxes.length; i++) {
			var id = norm(boxes[i].id) + ' ' + norm(boxes[i].name);
			if (id.indexOf(norm(key)) !== -1) { return boxes[i]; }
			if (onlyOne && !loose && /consent/.test(id) && !/process/.test(id)) { loose = boxes[i]; }
		}
		return loose;
	}

	/**
	 * legalConsentOptions for HubSpot's submit API, from the HubSpot form's privacy settings.
	 * Legitimate interest needs nothing from the visitor (the form shows the notice). Consent forms
	 * send what the visitor ticked; a required box left unticked stops the entry.
	 */
	function consentOptions(form, c) {
		var out = { options: null, missing: [], absent: [] };
		if (!c || !c.subs) { return out; }
		if (c.mode === 'li') {
			if (c.subs.length) {
				out.options = { legitimateInterest: { value: true, subscriptionTypeId: Number(c.subs[0].id), legalBasis: c.basis || 'LEAD', text: c.text || '' } };
			}
			return out;
		}
		var proc = true;
		if (!c.implicit) {
			var pb = consentBox(form, 'consent-to-process', false);
			proc = !!(pb && pb.checked);
			if (!proc) { out.missing.push('consent-to-process'); }
			if (!pb) { out.absent.push('consent-to-process'); }
		}
		var comms = c.subs.map(function (sub) {
			var box = consentBox(form, 'consent-' + sub.id, c.subs.length === 1);
			var on = !!(box && box.checked);
			if (sub.required && !on) { out.missing.push('consent-' + sub.id); }
			if (sub.required && !box) { out.absent.push('consent-' + sub.id); }
			return { value: on, subscriptionTypeId: Number(sub.id), text: sub.label || '' };
		});
		out.options = { consent: { consentToProcess: proc, text: c.processing || c.text || '', communications: comms } };
		return out;
	}

	/** Field names HubSpot says its form doesn't have. */
	function unknownFields(errors) {
		var out = [];
		(errors || []).forEach(function (e) {
			if (e.errorType !== 'FIELD_NOT_IN_FORM_DEFINITION') { return; }
			var m = String(e.message || '').match(/'fields\.([^']+)'/) || String(e.message || '').match(/[Ff]ield '([^']+)'/);
			if (m && out.indexOf(m[1]) === -1) { out.push(m[1]); }
		});
		return out;
	}

	/**
	 * Send to HubSpot. If HubSpot says a field isn't on its form, leave that field out and send once more,
	 * so one wrong optional field never loses an entry. WP Admin is told which field to remove.
	 */
	function post(form, bridge, fields, context, dropped, extra) {
		w.fetch(bridge.url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(Object.assign({ submittedAt: Date.now(), fields: fields, context: context }, extra || {}))
		}).then(function (res) {
			return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, body: body }; });
		}).then(function (r) {
			if (r.ok) {
				busy(form, false);
				report(bridge, true, dropped.map(function (n) { return { errorType: 'FIELD_NOT_IN_FORM_DEFINITION', message: 'Left out ' + n + ': the HubSpot form has no field with that name' }; }));
				handOver(form);
				return;
			}
			var errors = (r.body && r.body.errors) || [{ errorType: 'HTTP', message: (r.body && r.body.message) || 'HubSpot rejected the entry.' }];
			var unknown = unknownFields(errors);
			if (unknown.length && !dropped.length) {
				var rest = fields.filter(function (f) { return unknown.indexOf(f.name) === -1; });
				if (rest.length && rest.length < fields.length) {
					if (w.console) { w.console.warn('[Nori HubSpot Bridge] HubSpot\'s form has no field ' + unknown.join(', ') + '; sending without it.'); }
					post(form, bridge, rest, context, unknown, extra);
					return;
				}
			}
			busy(form, false);
			if (w.console) { w.console.warn('[Nori HubSpot Bridge] HubSpot rejected the entry:', errors); }
			setState(form, 'error');
			report(bridge, false, errors);
		}).catch(function (err) {
			busy(form, false);
			setState(form, 'error');
			report(bridge, false, [{ errorType: 'NETWORK', message: String(err && err.message || err) }]);
		});
	}

	function bind(form, bridge) {
		if (bound ? bound.has(form) : form.__noriHs) { return; }
		if (bound) { bound.add(form); } else { form.__noriHs = true; }
		// Capture phase on the form, so HubSpot goes first and Elementor's handler waits for it.
		form.addEventListener('submit', function (e) {
			if (form.hasAttribute(PASS)) { return; } // Our own hand-over to Elementor.
			e.preventDefault();
			e.stopImmediatePropagation();
			if (N.isSpam && N.isSpam(form)) { form.reset(); return; }
			if (form.getAttribute('aria-busy') === 'true') { return; }
			send(form, bridge);
		}, true);
	}

	function scan() {
		bridges.forEach(function (bridge) {
			var nodes = d.querySelectorAll('form.' + bridge.cls + ', .' + bridge.cls + ' form');
			for (var i = 0; i < nodes.length; i++) { bind(nodes[i], bridge); }
		});
	}

	N.start(function () {
		scan();
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
