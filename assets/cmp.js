(function () {
  const CFG = window.CMP410 || {};
  const COOKIE_NAME = CFG.cookieName || 'cmp_consent';
  const TTL_DAYS = Number(CFG.ttlDays || 180);

  function log(...args) {
    if (CFG.debug) console.log('[CMP410]', ...args);
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

  function getConsent() {
    const raw = getCookie(COOKIE_NAME);
    if (!raw) return null;
    const obj = safeParse(raw);
    if (!obj || typeof obj !== 'object') return null;
    return {
      v: 1,
      essential: true,
      analytics: !!obj.analytics,
      retargeting: !!obj.retargeting,
      ts: obj.ts || Date.now()
    };
  }

  function setConsent(consent) {
    const payload = JSON.stringify({
      v: 1,
      essential: true,
      analytics: !!consent.analytics,
      retargeting: !!consent.retargeting,
      ts: Date.now()
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

  function gtagConsentUpdate(analyticsOn, retargetingOn) {
    window.dataLayer = window.dataLayer || [];

    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', {
        analytics_storage: analyticsOn ? 'granted' : 'denied',
        ad_storage: retargetingOn ? 'granted' : 'denied',
        ad_user_data: retargetingOn ? 'granted' : 'denied',
        ad_personalization: retargetingOn ? 'granted' : 'denied'
      });
    } else {
      log('gtag not available (still ok), pushing event only');
    }

    window.dataLayer.push({
      event: 'cmp_consent_update',
      cmp_analytics: !!analyticsOn,
      cmp_retargeting: !!retargetingOn
    });

    log('consent update', { analyticsOn, retargetingOn });
  }

  // Meta Pixel
  function loadMetaPixel(pixelId) {
    if (!pixelId) return;
    if (window.fbq) return;

    !(function(f,b,e,v,n,t,s){
      if(f.fbq) return; n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq) f._fbq=n; n.push=n; n.loaded=!0; n.version='2.0';
      n.queue=[]; t=b.createElement(e); t.async=!0;
      t.src=v; s=b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t,s);
    })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

    window.fbq('init', pixelId);
    window.fbq('track', 'PageView');
    log('Meta loaded', pixelId);
  }

  // LinkedIn Insight
  function loadLinkedIn(partnerId) {
    if (!partnerId) return;

    window._linkedin_partner_id = String(partnerId);
    window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
    if (!window._linkedin_data_partner_ids.includes(String(partnerId))) {
      window._linkedin_data_partner_ids.push(String(partnerId));
    }

    (function(d){
      if (d.getElementById('linkedin-insight')) return;
      const s = d.createElement('script');
      s.type = 'text/javascript';
      s.async = true;
      s.id = 'linkedin-insight';
      s.src = 'https://snap.licdn.com/li.lms-analytics/insight.min.js';
      const x = d.getElementsByTagName('script')[0];
      x.parentNode.insertBefore(s, x);
    })(document);

    log('LinkedIn loaded', partnerId);
  }

  function applyRetargetingLoadIfNeeded(consent) {
    if (!consent || !consent.retargeting) return;
    loadMetaPixel(CFG.metaPixelId);
    loadLinkedIn(CFG.linkedinPartnerId);
  }

  function applyConsent(consent) {
    gtagConsentUpdate(!!consent.analytics, !!consent.retargeting);
    applyRetargetingLoadIfNeeded(consent);
  }

  function syncUIFromConsent(consent) {
  if (!consent) {
    // Defaults UI: checked
    $analytics.checked = true;
    $retargeting.checked = true;
    return;
  }
  $analytics.checked = !!consent.analytics;
  $retargeting.checked = !!consent.retargeting;
}


  function acceptAll() {
    const consent = { essential: true, analytics: true, retargeting: true };
    setConsent(consent);
    applyConsent(consent);
    hideAll();
  }

  function rejectAll() {
    const consent = { essential: true, analytics: false, retargeting: false };
    setConsent(consent);
    applyConsent(consent);
    hideAll();
  }

  function saveCustom() {
    const consent = { essential: true, analytics: !!$analytics.checked, retargeting: !!$retargeting.checked };
    setConsent(consent);
    applyConsent(consent);
    hideAll();
  }

  window.CMP410_open = function () {
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

  log('init ok', { existing, forceShow: force, metaPixelId: CFG.metaPixelId, linkedinPartnerId: CFG.linkedinPartnerId });
})();
