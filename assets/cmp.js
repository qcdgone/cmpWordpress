(function () {
  const CFG = window.CMP410GONE || {};
  const COOKIE_NAME = CFG.cookieName || 'cmp410gone_consent';
  const LEGACY_COOKIE_NAME = CFG.legacyCookieName || 'cmp_consent';
  const TTL_DAYS = Number(CFG.ttlDays || 180);

  function log(...args) {
    if (CFG.debug) console.log('[CMP410GONE]', ...args);
  }

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
    log('showBanner');
  }

  function hideAll() {
    $wrap.setAttribute('aria-hidden', 'true');
    $wrap.classList.remove('is-open');
    $banner.style.display = 'none';
    $modal.style.display = 'none';
    log('hideAll');
  }

  function openModal() {
    $wrap.setAttribute('aria-hidden', 'false');
    $wrap.classList.add('is-open');
    $banner.style.display = 'none';
    $modal.style.display = 'block';
    log('openModal');
  }

  function closeModal() {
    if (getConsent() && !CFG.forceShow) hideAll();
    else showBanner();
  }

  function gtagConsentUpdate(consent) {
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

    log('consent update', c);
  }

  function applyConsent(consent) {
    gtagConsentUpdate(consent || CONSENT_DEFAULT);
  }

  function syncUIFromConsent(consent) {
    const c = consent ? normalizeConsent(consent) : CONSENT_DEFAULT;
    $analytics.checked = c.analytics_storage === 'granted';
    $retargeting.checked = c.ad_storage === 'granted';
  }


  function acceptAll() {
    const consent = {
      analytics_storage: 'granted',
      ad_storage: 'granted',
      ad_user_data: 'granted',
      ad_personalization: 'granted'
    };
    setConsent(consent);
    applyConsent(consent);
    hideAll();
  }

  function rejectAll() {
    const consent = { ...CONSENT_DEFAULT };
    setConsent(consent);
    applyConsent(consent);
    hideAll();
  }

  function saveCustom() {
    const consent = {
      analytics_storage: $analytics.checked ? 'granted' : 'denied',
      ad_storage: $retargeting.checked ? 'granted' : 'denied',
      ad_user_data: $retargeting.checked ? 'granted' : 'denied',
      ad_personalization: $retargeting.checked ? 'granted' : 'denied'
    };
    setConsent(consent);
    applyConsent(consent);
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
    applyConsent(existing);
    hideAll();
  }

  log('init ok', { existing, forceShow: force });
})();
