(function () {
  const CFG = window.CMP410GONE || {};
  const COOKIE_NAME = CFG.cookieName || 'cmp410gone_consent';
  const LEGACY_COOKIE_NAME = CFG.legacyCookieName || 'cmp_consent';
  const TTL_DAYS = Number(CFG.ttlDays || 180);

  function log(...args) {
    if (CFG.debug) console.log('[CMP410GONE]', ...args);
  }
  const OVERLAY_MODE = CFG.overlayMode || 'inline';
  const OVERLAY_ENABLED = true;
  const $wrap = document.getElementById('cmp410');

  if (!$wrap) {
    log('wrap not found');
    return;
  }

  const $banner = $wrap.querySelector('.cmp410-banner');
  const $modal = $wrap.querySelector('.cmp410-modal');

  const $analytics = document.getElementById('cmp410-analytics');
  const $retargeting = document.getElementById('cmp410-retargeting');

  const CONSENT_DEFAULT = {
    analytics_storage: 'denied',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied'
  };
  const TRACKING_MODE = CFG.trackingMode || 'hybrid';
  const GTM_ID = (CFG.gtmId || '').trim();
  const GA4_ID = (CFG.ga4Id || '').trim();
  let gtmLoaded = false;
  let gtagLoaded = false;

  function setCookie(name, value, days) {
    const d = new Date();
    d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
    const expires = 'expires=' + d.toUTCString();
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${name}=${encodeURIComponent(value)}; ${expires}; path=/; SameSite=Lax${secure}`;
  }

  function getCookie(name) {
    const n = name + '=';
    const parts = document.cookie.split(';');
    for (let i = 0; i < parts.length; i++) {
      let c = parts[i].trim();
      if (c.indexOf(n) === 0) return decodeURIComponent(c.substring(n.length, c.length));
    }
    return null;
  }

  function safeParse(json) {
    try { return JSON.parse(json); } catch (_) { return null; }
  }

  function normalizeConsent(obj) {
    if (!obj || typeof obj !== 'object') return { ...CONSENT_DEFAULT };

    const out = { ...CONSENT_DEFAULT };
    ['analytics_storage', 'ad_storage', 'ad_user_data', 'ad_personalization'].forEach((key) => {
      if (obj[key] === 'granted' || obj[key] === 'denied') {
        out[key] = obj[key];
      }
    });

    if (typeof obj.analytics === 'boolean') {
      out.analytics_storage = obj.analytics ? 'granted' : 'denied';
    }

    if (typeof obj.retargeting === 'boolean') {
      const value = obj.retargeting ? 'granted' : 'denied';
      out.ad_storage = value;
      out.ad_user_data = value;
      out.ad_personalization = value;
    }

    return out;
  }

  function getConsent() {
    const raw = getCookie(COOKIE_NAME) || getCookie(LEGACY_COOKIE_NAME);
    if (!raw) return null;
    const obj = safeParse(raw);
    if (!obj || typeof obj !== 'object') return null;
    return {
      v: obj.v || 2,
      ts: obj.ts || Date.now(),
      ...normalizeConsent(obj)
    };
  }

  function setConsent(consent) {
    const payload = JSON.stringify({
      v: 2,
      ts: Date.now(),
      ...normalizeConsent(consent)
    });
    setCookie(COOKIE_NAME, payload, TTL_DAYS);
  }

  function showBanner() {
    $wrap.setAttribute('aria-hidden', 'false');
    $wrap.classList.add('is-open');
    $banner.style.display = 'flex';
    $modal.style.display = 'none';
    applyOverlay(true);
    log('showBanner');
  }

  function hideAll() {
    $wrap.setAttribute('aria-hidden', 'true');
    $wrap.classList.remove('is-open');
    $banner.style.display = 'none';
    $modal.style.display = 'none';
    applyOverlay(false);
    log('hideAll');
  }

  function openModal() {
    $wrap.setAttribute('aria-hidden', 'false');
    $wrap.classList.add('is-open');
    $banner.style.display = 'none';
    $modal.style.display = 'block';
    applyOverlay(true);
    log('openModal');
  }

  function closeModal() {
    if (getConsent() && !CFG.forceShow) hideAll();
    else showBanner();
  }

  function applyOverlay(active) {
    if (!OVERLAY_ENABLED) {
      $wrap.style.setProperty('--cmp410-overlay-bg', 'transparent');
      $wrap.style.setProperty('--cmp410-overlay-pe', 'none');
      $wrap.style.setProperty('--cmp410-overlay-display', 'none');
      return;
    }
    if (OVERLAY_MODE !== 'js') {
      return;
    }
    if (active) {
      $wrap.style.setProperty('--cmp410-overlay-bg', 'rgba(0,0,0,.35)');
      $wrap.style.setProperty('--cmp410-overlay-pe', 'auto');
      $wrap.style.setProperty('--cmp410-overlay-display', 'block');
    } else {
      $wrap.style.setProperty('--cmp410-overlay-bg', 'transparent');
      $wrap.style.setProperty('--cmp410-overlay-pe', 'none');
      $wrap.style.setProperty('--cmp410-overlay-display', 'none');
    }
  }

  function gtagConsentUpdate(consent, previous) {
    const c = normalizeConsent(consent);
    window.dataLayer = window.dataLayer || [];

    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', c);
    } else {
      log('gtag not available (still ok), pushing event only');
    }

    window.dataLayer.push({
      event: 'cmp410gone_consent_update',
      cmp410gone_analytics: c.analytics_storage === 'granted',
      cmp410gone_retargeting: c.ad_storage === 'granted'
    });

    if (c.analytics_storage === 'granted') {
      window.dataLayer.push({
        event: 'ga4_after_consent'
      });
    }

    maybeLoadGtag(c);
    maybeLoadGtm(c);

    if (c.analytics_storage === 'granted' && (!previous || previous.analytics_storage !== 'granted')) {
      if (typeof window.gtag === 'function') {
        window.gtag('event', 'page_view');
      } else {
        window.dataLayer.push({ event: 'page_view' });
      }
    }
    log('consent update', c);
  }

  function maybeLoadGtag(consent) {
    if (gtagLoaded || !GA4_ID) return;
    if (TRACKING_MODE !== 'all_inclusive') return;
    if (!consent || consent.analytics_storage !== 'granted') return;
    gtagLoaded = true;

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(['js', new Date()]);
    window.dataLayer.push(['config', GA4_ID]);

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(GA4_ID)}`;
    document.head.appendChild(script);
  }

  function maybeLoadGtm(consent) {
    if (gtmLoaded || !GTM_ID) return;
    if (TRACKING_MODE === 'gtm') return;
    if (TRACKING_MODE === 'all_inclusive' && (!consent || consent.analytics_storage !== 'granted')) return;
    gtmLoaded = true;

    window.dataLayer = window.dataLayer || [];
    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(GTM_ID)}`;
    document.head.appendChild(script);
  }

  function applyConsent(consent, previous) {
    gtagConsentUpdate(consent || CONSENT_DEFAULT, previous);
  }

  function syncUIFromConsent(consent) {
    const c = consent ? normalizeConsent(consent) : CONSENT_DEFAULT;
    $analytics.checked = c.analytics_storage === 'granted';
    $retargeting.checked = c.ad_storage === 'granted';
  }


  function acceptAll() {
    const previous = getConsent();
    const consent = {
      analytics_storage: 'granted',
      ad_storage: 'granted',
      ad_user_data: 'granted',
      ad_personalization: 'granted'
    };
    setConsent(consent);
    applyConsent(consent, previous);
    hideAll();
  }

  function rejectAll() {
    const previous = getConsent();
    const consent = { ...CONSENT_DEFAULT };
    setConsent(consent);
    applyConsent(consent, previous);
    hideAll();
  }

  function saveCustom() {
    const previous = getConsent();
    const consent = {
      analytics_storage: $analytics.checked ? 'granted' : 'denied',
      ad_storage: $retargeting.checked ? 'granted' : 'denied',
      ad_user_data: $retargeting.checked ? 'granted' : 'denied',
      ad_personalization: $retargeting.checked ? 'granted' : 'denied'
    };
    setConsent(consent);
    applyConsent(consent, previous);
    hideAll();
  }

  window.CMP410GONE_open = function () {
    const c = getConsent();
    syncUIFromConsent(c);
    openModal();
  };

  $wrap.addEventListener('click', function (e) {
    const btn = e.target && e.target.closest('[data-cmp410]');
    if (!btn) return;

    const action = btn.getAttribute('data-cmp410');
    switch (action) {
      case 'accept': acceptAll(); break;
      case 'reject': rejectAll(); break;
      case 'customize':
        syncUIFromConsent(getConsent());
        openModal();
        break;
      case 'save': saveCustom(); break;
      case 'close': closeModal(); break;
    }
  });

  // INIT (important with WP Rocket)
  const existing = getConsent();
  const force = !!CFG.forceShow;

  if (force) {
    log('forceShow enabled -> showBanner');
    showBanner();
  } else if (!existing) {
    showBanner();
  } else {
    applyConsent(existing, existing);
    hideAll();
  }

  log('init ok', { existing, forceShow: force });
})();
