/*! Nori: Lightbox. Image, MP4, YouTube and Vimeo overlay with scroll lock, ESC to close and focus return. */
(function() {
  'use strict';

  function isEditor() {
    return !!(window.NoriFE && window.NoriFE.isEditor());
  }

  const TRIGGER_SEL = '.open-lightbox-np, [data-lightbox-video], [data-lightbox-src]';

  let overlay = null;
  let lastFocus = null;
  let container = null;
  let mediaWrapper = null;

  function createLightboxDOM() {
    if (document.getElementById('universal-lightbox-overlay')) return;

    overlay = document.createElement('div');
    overlay.id = 'universal-lightbox-overlay';
    overlay.className = 'plb-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Media Lightbox');

    overlay.innerHTML = `
      <div class="plb-container" id="universal-lightbox-container">
        <button type="button" class="plb-close-btn" id="universal-lightbox-close" aria-label="Close Lightbox">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
        <div class="plb-media-wrapper" id="universal-lightbox-media"></div>
      </div>
    `;

    document.body.appendChild(overlay);

    container = overlay.querySelector('#universal-lightbox-container');
    mediaWrapper = overlay.querySelector('#universal-lightbox-media');

    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) {
        closeLightbox();
      }
    });

    const closeBtn = overlay.querySelector('#universal-lightbox-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        closeLightbox();
      });
    }

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && overlay && overlay.classList.contains('is-active')) {
        closeLightbox();
      }
    });
  }

  // Only http(s) URLs may reach an iframe, video or img src (blocks javascript:, data:, etc.).
  function safeUrl(u) {
    if (!u || typeof u !== 'string') return null;
    try {
      const parsed = new URL(u.trim(), window.location.href);
      return (parsed.protocol === 'https:' || parsed.protocol === 'http:') ? parsed.href : null;
    } catch (err) {
      return null;
    }
  }

  function openLightbox(mediaConfig) {
    if (!overlay) createLightboxDOM();
    if (!mediaConfig) return;
    const src = safeUrl(mediaConfig.src);
    if (!src) return;
    mediaConfig = Object.assign({}, mediaConfig, { src: src, poster: safeUrl(mediaConfig.poster) });

    mediaWrapper.innerHTML = '';
    const cfg = (window.NoriFE && window.NoriFE.config && window.NoriFE.config.lightbox) || {};
    const autoplay = cfg.autoplay !== false;

    if (mediaConfig.type === 'video') {
      container.classList.add('is-fit-content');
      const videoEl = document.createElement('video');
      videoEl.src = mediaConfig.src;
      if (mediaConfig.poster) videoEl.poster = mediaConfig.poster;
      videoEl.controls = true;
      videoEl.autoplay = autoplay;
      videoEl.playsInline = true;
      videoEl.setAttribute('webkit-playsinline', 'true');
      if (mediaConfig.currentTime && mediaConfig.currentTime > 0) {
        videoEl.currentTime = mediaConfig.currentTime;
      }
      mediaWrapper.appendChild(videoEl);

      const playPromise = autoplay ? videoEl.play() : undefined;
      if (playPromise !== undefined) {
        playPromise.catch(function() {
          videoEl.muted = true;
          videoEl.play();
        });
      }
    } else if (mediaConfig.type === 'iframe') {
      container.classList.remove('is-fit-content');
      const iframeEl = document.createElement('iframe');
      iframeEl.src = autoplay ? mediaConfig.src : mediaConfig.src.replace(/([?&])autoplay=1/, '$1autoplay=0');
      iframeEl.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen';
      iframeEl.allowFullscreen = true;
      iframeEl.setAttribute('allowfullscreen', 'true');
      mediaWrapper.appendChild(iframeEl);
    } else if (mediaConfig.type === 'image') {
      container.classList.add('is-fit-content');
      const imgEl = document.createElement('img');
      imgEl.src = mediaConfig.src;
      imgEl.alt = mediaConfig.alt || 'Lightbox Image';
      mediaWrapper.appendChild(imgEl);
    }

    lastFocus = document.activeElement;
    document.body.classList.add('plb-scroll-locked');
    overlay.classList.add('is-active');
    if (window.NoriFE && window.NoriFE.lenis) { window.NoriFE.lenis.stop(); }
    const closeBtn = overlay.querySelector('#universal-lightbox-close');
    if (closeBtn) { closeBtn.focus(); }
  }

  function closeLightbox() {
    if (!overlay || !overlay.classList.contains('is-active')) return;

    overlay.classList.remove('is-active');
    document.body.classList.remove('plb-scroll-locked');
    if (window.NoriFE && window.NoriFE.lenis) { window.NoriFE.lenis.start(); }
    if (lastFocus && typeof lastFocus.focus === 'function') { lastFocus.focus(); }

    setTimeout(function() {
      const vid = mediaWrapper.querySelector('video');
      if (vid) {
        vid.pause();
        vid.removeAttribute('src');
        vid.load();
      }
      const iframe = mediaWrapper.querySelector('iframe');
      if (iframe) {
        iframe.src = 'about:blank';
      }
      mediaWrapper.innerHTML = '';
    }, 320);
  }

  function parseVideoEmbedUrl(url) {
    if (!url || typeof url !== 'string') return null;

    const ytMatch = url.match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=|shorts\/))([\w-]{11})/i);
    if (ytMatch) {
      const videoId = ytMatch[1];
      const timeMatch = url.match(/[?&](?:t|start)=(\d+)/);
      const startParam = timeMatch ? `&start=${timeMatch[1]}` : '';
      return `https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1${startParam}`;
    }

    if (url.includes('vimeo.com')) {
      // Player URLs are rebuilt from the id and hash only, never passed through.
      let player = null;
      try { player = new URL(url.trim(), window.location.href); } catch (err) { player = null; }
      if (player && player.protocol === 'https:' && player.hostname === 'player.vimeo.com') {
        const idMatch = player.pathname.match(/^\/video\/(\d+)\/?$/);
        if (!idMatch) return null;
        const h = player.searchParams.get('h');
        return `https://player.vimeo.com/video/${idMatch[1]}?autoplay=1` + (h && /^[a-zA-Z0-9]+$/.test(h) ? `&h=${h}` : '');
      }
      if (url.includes('player.vimeo.com')) return null;

      const vimeoMatch = url.match(/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)(?:\/([a-zA-Z0-9]+))?/);
      if (vimeoMatch && vimeoMatch[3]) {
        const videoId = vimeoMatch[3];
        const hash = vimeoMatch[4];
        let embedUrl = `https://player.vimeo.com/video/${videoId}?autoplay=1`;
        if (hash) {
          embedUrl += `&h=${hash}`;
        }
        const hParamMatch = url.match(/[?&]h=([a-zA-Z0-9]+)/);
        if (!hash && hParamMatch) {
          embedUrl += `&h=${hParamMatch[1]}`;
        }
        return embedUrl;
      }
    }

    return null;
  }

  function extractMedia(trigger, clickedEl) {
    const dataVideo = trigger.getAttribute('data-lightbox-video') || trigger.dataset.lightboxVideo;
    if (dataVideo) {
      const embed = parseVideoEmbedUrl(dataVideo);
      if (embed) return { type: 'iframe', src: embed };
      return { type: 'video', src: dataVideo };
    }

    const dataSrc = trigger.getAttribute('data-lightbox-src') || trigger.dataset.lightboxSrc;
    if (dataSrc) {
      if (dataSrc.match(/\.(mp4|webm|ogv|mov)(\?.*)?$/i)) {
        return { type: 'video', src: dataSrc };
      }
      const embed = parseVideoEmbedUrl(dataSrc);
      if (embed) return { type: 'iframe', src: embed };
      if (dataSrc.match(/\.(jpg|jpeg|png|webp|gif|svg)(\?.*)?$/i)) {
        return { type: 'image', src: dataSrc };
      }
      return { type: 'video', src: dataSrc };
    }

    const inlineVideo = trigger.querySelector('video') || (trigger.tagName === 'VIDEO' ? trigger : null);
    if (inlineVideo) {
      let src = inlineVideo.currentSrc || inlineVideo.src;
      if (!src) {
        const sourceEl = inlineVideo.querySelector('source');
        if (sourceEl) src = sourceEl.getAttribute('src');
      }
      if (src) {
        if (!inlineVideo.paused) inlineVideo.pause();
        return {
          type: 'video',
          src: src,
          poster: inlineVideo.poster || null,
          currentTime: inlineVideo.currentTime || 0
        };
      }
    }

    const inlineIframe = trigger.querySelector('iframe');
    if (inlineIframe && inlineIframe.src) {
      const embed = parseVideoEmbedUrl(inlineIframe.src) || inlineIframe.src;
      return { type: 'iframe', src: embed };
    }

    let anchor = (clickedEl && clickedEl.closest('a[href]')) || (trigger.matches('a[href]') ? trigger : trigger.querySelector('a[href]'));
    let href = anchor ? anchor.getAttribute('href') : trigger.getAttribute('href');

    if (href && href !== '#' && safeUrl(href)) {
      const embed = parseVideoEmbedUrl(href);
      if (embed) return { type: 'iframe', src: embed };
      if (href.match(/\.(mp4|webm|ogv|mov)(\?.*)?$/i)) {
        return { type: 'video', src: href };
      }
      if (href.match(/\.(jpg|jpeg|png|webp|gif|svg)(\?.*)?$/i)) {
        return { type: 'image', src: href };
      }
    }

    const img = trigger.querySelector('img');
    if (img && img.src) {
      return { type: 'image', src: img.currentSrc || img.src, alt: img.alt };
    }

    return null;
  }

  function handleDocumentClick(e) {
    if (isEditor()) return;

    const trigger = e.target.closest(TRIGGER_SEL);
    if (!trigger) return;

    if (e.target.tagName === 'VIDEO' && e.target.hasAttribute('controls')) {
      return;
    }

    const mediaConfig = extractMedia(trigger, e.target);
    if (mediaConfig && safeUrl(mediaConfig.src)) {
      e.preventDefault();
      e.stopPropagation();
      openLightbox(mediaConfig);
    }
  }

  function init() {
    createLightboxDOM();
    document.addEventListener('click', handleDocumentClick, { capture: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.UniversalLightbox = {
    open: openLightbox,
    close: closeLightbox
  };
})();
