/*! Nori: Video Carousel. An endless carousel of videos: one in the center, faded ones at the sides.
    Works with self-hosted videos and with YouTube or Vimeo (Elementor's YouTube widget or an embed).
    Adds arrows and a play button, pauses a video when it leaves the center, and never mistakes a
    drag for a click on play. YouTube and Vimeo load only when someone presses play: until then the
    slide shows the video's thumbnail, so nothing is loaded from them and no cookies are set. */
(function (w, d) {
	'use strict';
	var N = w.NoriFE;
	if (!N || !N.Carousel) { return; }

	var opts = N.settings('video-carousel', { arrows: true, ratio: '16-9', arrowStyle: 'circle', playStyle: 'ring' });
	var RATIOS = { '16-9': '16 / 9', '4-3': '4 / 3', '1-1': '1 / 1', '9-16': '9 / 16' };
	var PLAY = '<svg class="nori-vc-icon-play" viewBox="0 0 24 24" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>' +
		'<svg class="nori-vc-icon-pause" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>';
	var YT = /(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:(?:watch)?\?(?:.*&)?vi?=|(?:embed|v|vi|shorts|live)\/))([\w-]{11})/;
	var VIMEO = /vimeo\.com\/(?:video\/|channels\/[\w-]+\/|groups\/[\w-]+\/videos\/)?(\d+)(?:\/([\da-f]+))?/;

	function arrow(cls, label, path) {
		var b = d.createElement('button');
		b.type = 'button';
		b.className = 'nori-vc-arrow ' + cls;
		b.setAttribute('aria-label', label);
		b.innerHTML = '<svg viewBox="0 0 8 14" aria-hidden="true"><path d="' + path + '"/></svg>';
		return b;
	}

	function seconds(v) { var n = parseInt(v, 10); return n > 0 ? n : 0; }

	/** YouTube or Vimeo in a slide: Elementor's YouTube widget, an embed iframe, or a link. */
	function findEmbed(slide) {
		var el = slide.querySelector('[data-e-type="e-youtube"], [data-nori-youtube]');
		if (el) {
			var s = {};
			try { s = JSON.parse(el.getAttribute('data-settings') || '{}') || {}; } catch (e) {}
			var m = String(s.source || el.getAttribute('data-nori-youtube') || '').match(YT) || [null, el.getAttribute('data-nori-youtube')];
			if (m[1] && /^[\w-]{11}$/.test(m[1])) {
				return { host: el, kind: 'youtube', id: m[1], start: seconds(s.start || el.getAttribute('data-start')), end: seconds(s.end), mute: !!s.mute, controls: s.controls !== false, loop: !!s.loop };
			}
		}
		var f = slide.querySelector('iframe[src*="youtu"], iframe[src*="vimeo.com"], iframe[data-src*="youtu"], iframe[data-src*="vimeo.com"]');
		var a = f ? null : slide.querySelector('a[href*="youtu"], a[href*="vimeo.com"]');
		var url = f ? (f.getAttribute('src') || f.getAttribute('data-src')) : (a ? a.getAttribute('href') : '');
		if (!url) { return null; }
		var yt = url.match(YT), vm = url.match(VIMEO);
		var start = seconds((url.match(/[?&#](?:start|t)=(\d+)/) || [])[1]);
		if (yt) { return { host: f || a, kind: 'youtube', id: yt[1], start: start, end: 0, mute: /[?&]mute=1/.test(url), controls: !/[?&]controls=0/.test(url), loop: false }; }
		if (vm) { return { host: f || a, kind: 'vimeo', id: vm[1], hash: vm[2] || (url.match(/[?&]h=([\da-f]+)/) || [])[1] || '', start: start, mute: /[?&]muted=1/.test(url) }; }
		return null;
	}

	function poster(slide, host, info) {
		var custom = slide.getAttribute('data-nori-poster') || host.getAttribute('data-nori-poster');
		var img = d.createElement('img');
		img.className = 'nori-vc-poster';
		img.alt = '';
		img.loading = 'lazy';
		img.decoding = 'async';
		if (custom) { img.src = custom; return img; }
		if (info.kind !== 'youtube') { return null; }
		img.src = 'https://i.ytimg.com/vi/' + info.id + '/maxresdefault.jpg';
		// Not every video has a large thumbnail: YouTube then answers with a 120px placeholder.
		function fallback() { if (img.src.indexOf('hqdefault') === -1) { img.src = 'https://i.ytimg.com/vi/' + info.id + '/hqdefault.jpg'; } }
		img.addEventListener('error', fallback);
		img.addEventListener('load', function () { if (img.naturalWidth && img.naturalWidth <= 120) { fallback(); } });
		return img;
	}

	/** Loads the YouTube or Vimeo player on first play; after that, play and pause go through postMessage. */
	/**
	 * Before any copies are made: swap each YouTube or Vimeo player for a light placeholder with its
	 * thumbnail, so copies never load a player and Elementor's own YouTube player never starts.
	 */
	function prepare(slide) {
		if (slide.querySelector('video')) { return; }
		var info = findEmbed(slide);
		if (!info) { return; }
		var box = d.createElement('div');
		box.className = 'nori-vc-embed';
		var img = poster(slide, info.host, info);
		if (img) { box.appendChild(img); }
		var host = info.host;
		delete info.host;
		box.setAttribute('data-nori-embed', JSON.stringify(info));
		if (host.hasAttribute('data-e-type')) { host.setAttribute('data-nori-e-type', host.getAttribute('data-e-type')); host.removeAttribute('data-e-type'); }
		if (host.tagName === 'IFRAME' || host.tagName === 'A') {
			host.parentNode.replaceChild(box, host);
		} else {
			host.innerHTML = '';
			host.appendChild(box);
		}
	}

	function embedPlayer(slide, box, info) {
		var frame = null, playing = false;
		function send(msg) { if (frame && frame.contentWindow) { frame.contentWindow.postMessage(msg, '*'); } }
		return {
			play: function () {
				playing = true;
				if (!frame) {
					frame = d.createElement('iframe');
					var q;
					if (info.kind === 'youtube') {
						q = 'autoplay=1&enablejsapi=1&playsinline=1&rel=0&modestbranding=1' + (info.start ? '&start=' + info.start : '') + (info.end ? '&end=' + info.end : '') +
							(info.mute ? '&mute=1' : '') + (info.controls ? '' : '&controls=0') + (info.loop ? '&loop=1&playlist=' + info.id : '') + '&origin=' + encodeURIComponent(w.location.origin);
						frame.src = 'https://www.youtube-nocookie.com/embed/' + info.id + '?' + q;
					} else {
						q = 'autoplay=1&dnt=1&playsinline=1' + (info.hash ? '&h=' + info.hash : '') + (info.mute ? '&muted=1' : '') + (info.start ? '#t=' + info.start + 's' : '');
						frame.src = 'https://player.vimeo.com/video/' + info.id + '?' + q;
					}
					frame.title = slide.getAttribute('aria-label') || 'Video';
					frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
					frame.setAttribute('allowfullscreen', '');
					frame.className = 'nori-vc-frame';
					if (info.kind === 'youtube') {
						// Ask YouTube for state updates, so a pause from its own controls is known here too.
						frame.addEventListener('load', function () { send('{"event":"listening","id":"nori"}'); });
						w.addEventListener('message', function (e) {
							if (!frame || e.source !== frame.contentWindow) { return; }
							var data = e.data;
							try { data = typeof data === 'string' ? JSON.parse(data) : data; } catch (x) { return; }
							var st = data && data.info && data.info.playerState;
							if (st === 1 || st === 3) { playing = true; } else if (st === 2 || st === 0) { playing = false; }
						});
					}
					box.appendChild(frame);
				} else if (info.kind === 'youtube') {
					send('{"event":"command","func":"playVideo","args":""}');
				} else {
					send('{"method":"play"}');
				}
			},
			pause: function () {
				if (!playing) { return; }
				playing = false;
				send(info.kind === 'youtube' ? '{"event":"command","func":"pauseVideo","args":""}' : '{"method":"pause"}');
			},
			playing: function () { return playing; }
		};
	}

	function videoPlayer(v) {
		v.playsInline = true;
		v.setAttribute('playsinline', '');
		v.removeAttribute('controls');
		v.removeAttribute('autoplay');
		if (!v.getAttribute('src')) {
			var src = v.querySelector('source');
			if (src && src.src) { v.src = src.src; }
		}
		if (v.readyState === 0 && v.preload !== 'none') { v.load(); }
		return {
			play: function () { v.play().catch(function () {}); },
			pause: function () { if (!v.paused) { v.pause(); } },
			playing: function () { return !v.paused; }
		};
	}

	function pauseOthers(api, keep) {
		api.slides.forEach(function (s, i) {
			if (i !== keep && s.noriPlayer && s.noriPlayer.playing()) {
				s.noriPlayer.pause();
				s.classList.remove('is-playing-np');
			}
		});
	}

	function enhance(api, slide) {
		if (slide.getAttribute('data-nori-vc')) { return; }
		slide.setAttribute('data-nori-vc', '1');
		var v = slide.querySelector('video'), box = v ? null : slide.querySelector('.nori-vc-embed[data-nori-embed]'), info = null;
		if (box) { try { info = JSON.parse(box.getAttribute('data-nori-embed')); } catch (e) {} }
		if (!v && !info) { return; }
		var player = v ? videoPlayer(v) : embedPlayer(slide, box, info);
		slide.noriPlayer = player;
		if (info) { slide.classList.add('is-embed-np'); }

		var btn = d.createElement('button');
		btn.type = 'button';
		btn.className = 'nori-vc-play';
		btn.setAttribute('aria-label', 'Play video');
		btn.setAttribute('data-nori-drag', ''); // Covers the whole video, so a drag can start on it.
		btn.innerHTML = '<span class="nori-vc-play__ring">' + PLAY + '</span>';
		if (slide.classList.contains('nori-carousel-clone')) { btn.setAttribute('tabindex', '-1'); }
		slide.appendChild(btn);

		btn.addEventListener('click', function (e) {
			e.preventDefault();
			if (!slide.classList.contains('is-active-np')) { return; }
			if (player.playing()) { player.pause(); slide.classList.remove('is-playing-np'); return; }
			pauseOthers(api, api.slides.indexOf(slide));
			player.play();
			if (info) {
				// The player's own controls take over; the play button comes back if the slide leaves the center.
				slide.classList.add('is-playing-np');
				btn.setAttribute('aria-label', 'Pause video');
			}
		});
		if (!v) { return; }
		v.addEventListener('play', function () { slide.classList.add('is-playing-np'); btn.setAttribute('aria-label', 'Pause video'); v.setAttribute('controls', ''); });
		v.addEventListener('pause', function () { slide.classList.remove('is-playing-np'); btn.setAttribute('aria-label', 'Play video'); });
		v.addEventListener('ended', function () { slide.classList.remove('is-playing-np'); v.removeAttribute('controls'); });
		slide.addEventListener('mouseleave', function () { if (v.paused) { v.removeAttribute('controls'); } });
	}

	function make(root) {
		if (root.getAttribute('data-nori-vc-root')) { return; }
		root.setAttribute('data-nori-vc-root', '1');
		if (RATIOS[opts.ratio]) { root.style.setProperty('--nori-vc-ratio', RATIOS[opts.ratio]); }
		Array.prototype.forEach.call(root.children, prepare);

		new N.Carousel(root, {
			when: function () { return true; },
			active: function () { return 1; },
			autoplay: 0,
			minUnit: 4, // Two videos loop as A B A B, so moving between them never lands on a loop jump.
			centerOnClick: true,
			onBuild: function (api) {
				api.slides.forEach(function (s) { enhance(api, s); });
				var wrap = d.createElement('div');
				wrap.className = 'nori-vc-wrap nori-vc--arrows-' + (opts.arrowStyle || 'circle') + ' nori-vc--play-' + (opts.playStyle || 'ring');
				root.parentNode.insertBefore(wrap, root);
				wrap.appendChild(root);
				if (!opts.arrows) { return; }
				var prev = arrow('nori-vc-prev', 'Previous video', 'M7 1L1 7L7 13');
				var next = arrow('nori-vc-next', 'Next video', 'M1 1L7 7L1 13');
				prev.addEventListener('click', function () { api.go(-1); });
				next.addEventListener('click', function () { api.go(1); });
				wrap.appendChild(prev);
				wrap.appendChild(next);
			},
			onActive: function (api) { pauseOthers(api, api.index); }
		});
	}

	N.start(function () {
		d.querySelectorAll('.video-carousel-np').forEach(make);
	});
})(window, document);
