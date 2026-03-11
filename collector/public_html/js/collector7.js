/**
 * collector-v7.js — Full Analytics Collector (Activity + Perf + Errors + Vitals)
 * Style & structure modeled on collector-v6.js from class.
 *
 * - Session via sessionStorage (getSessionId)
 * - Technographics + network info
 * - Navigation timing, resource summary
 * - Core Web Vitals: LCP, CLS, INP
 * - Activity: mouse, clicks, scrolls, keys, idle detection
 * - Error tracking: window.onerror, resource error capture, unhandledrejection
 * - Deduplication + rate limit for errors
 * - sendBeacon with fetch fallback (keepalive)
 *
 * Configure ENDPOINT to your collector endpoint (collect.php).
 */

(function () {
  'use strict';

  // ---------------------------
  // Configuration
  // ---------------------------
  const ENDPOINT = 'https://collector.anl139.site/collect.php'; // your endpoint
  const SEND_INTERVAL_MS = 5000;
  const MOUSE_THROTTLE_MS = 100;
  const MAX_EVENTS_PER_BATCH = 1000;
  const MAX_ERRORS = 10;

  // ---------------------------
  // Error tracking state
  // ---------------------------
  const reportedErrors = new Set();
  let errorCount = 0;

  // ---------------------------
  // Web Vitals state
  // ---------------------------
  const vitals = { lcp: null, cls: 0, inp: null };

  // ---------------------------
  // Utilities
  // ---------------------------
  function round(n) { return Math.round(n * 100) / 100; }
  function nowMs() { return Date.now(); }

  // ---------------------------
  // Session identity (sessionStorage)
  // ---------------------------
  function getSessionId() {
    let sid = sessionStorage.getItem('_collector_sid');
    if (!sid) {
      sid = `${Math.random().toString(36).substring(2)}${Date.now().toString(36)}`;
      try { sessionStorage.setItem('_collector_sid', sid); } catch (e) {/* storage blocked */}
    }
    return sid;
  }
  const sessionId = getSessionId();

  // ---------------------------
  // Network / Technographics
  // ---------------------------
  function getNetworkInfo() {
    if (!('connection' in navigator)) return {};
    const c = navigator.connection;
    return {
      effectiveType: c.effectiveType,
      downlink: c.downlink,
      rtt: c.rtt,
      saveData: c.saveData
    };
  }

  function getTechnographics() {
    const imagesEnabled = (() => {
      const img = new Image();
      let enabled = true;
      img.onerror = () => { enabled = false; };
      img.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAUEBA==';
      return enabled;
    })();

    const cssEnabled = !!document.styleSheets.length;
    return {
      userAgent: navigator.userAgent,
      language: navigator.language,
      cookiesEnabled: navigator.cookieEnabled,
      jsEnabled: true,
      imagesEnabled,
      cssEnabled,
      screenWidth: window.screen.width,
      screenHeight: window.screen.height,
      windowWidth: window.innerWidth,
      windowHeight: window.innerHeight,
      pixelRatio: window.devicePixelRatio || 1,
      cores: navigator.hardwareConcurrency || 0,
      memory: navigator.deviceMemory || 0,
      network: getNetworkInfo(),
      timezone: (Intl && Intl.DateTimeFormat) ? Intl.DateTimeFormat().resolvedOptions().timeZone : null,
      entryTime: nowMs(),
      sessionId
    };
  }

  // ---------------------------
  // Detect images & CSS
  // ---------------------------
  function detectImagesEnabled(timeout = 2000) {
    return new Promise(resolve => {
      try {
        const img = new Image();
        let done = false;
        const finish = val => { if (!done) { done = true; resolve(val); } };
        img.onload = () => finish(true);
        img.onerror = () => finish(false);
        img.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
        setTimeout(() => finish(false), timeout);
      } catch (e) { resolve(false); }
    });
  }

  function detectCssEnabled() {
    try {
      const el = document.createElement('div');
      el.className = 'collector-css-test';
      el.style.display = 'none';
      document.body.appendChild(el);
      const computed = window.getComputedStyle(el).display;
      document.body.removeChild(el);
      return computed !== 'none';
    } catch (e) {
      return false;
    }
  }

  // ---------------------------
  // Navigation Timing & Resource Summary
  // ---------------------------
  function getNavigationTiming() {
    const entries = performance.getEntriesByType('navigation');
    if (!entries.length) return {};

    const n = entries[0];
    const totalLoadTime = n.loadEventEnd - n.startTime;

    return {
      dnsLookup: n.domainLookupEnd - n.domainLookupStart,
      tcpConnect: n.connectEnd - n.connectStart,
      tlsHandshake: n.secureConnectionStart > 0 ? n.connectEnd - n.secureConnectionStart : 0,
      ttfb: n.responseStart - n.requestStart,
      download: n.responseEnd - n.responseStart,
      domInteractive: n.domInteractive - n.fetchStart,
      domComplete: n.domComplete - n.fetchStart,
      loadEvent: n.loadEventEnd - n.fetchStart,
      fetchTime: n.responseEnd - n.fetchStart,
      transferSize: n.transferSize,
      headerSize: n.transferSize - n.encodedBodySize,
      navigationStart: n.startTime,
      loadEventEnd: n.loadEventEnd,
      totalLoadTime
    };
  }
  function getResourceSummary() {
    try {
      const resources = performance.getEntriesByType('resource') || [];
      const summary = {
        totalResources: resources.length,
        byType: {}
      };
      resources.forEach(r => {
        const type = r.initiatorType || 'other';
        if (!summary.byType[type]) summary.byType[type] = { count: 0, totalSize: 0, totalDuration: 0 };
        summary.byType[type].count += 1;
        summary.byType[type].totalSize += r.transferSize || 0;
        summary.byType[type].totalDuration += Math.round(r.duration || 0);
      });
      return summary;
    } catch (e) { return { totalResources: 0, byType: {} }; }
  }

  // ---------------------------
  // Web Vitals observers
  // ---------------------------
  function initWebVitals() {
    // LCP
    try {
      const obs = new PerformanceObserver(list => {
        const entries = list.getEntries();
        if (entries && entries.length) vitals.lcp = round(entries[entries.length - 1].startTime);
      });
      obs.observe({ type: 'largest-contentful-paint', buffered: true });
    } catch (e) { /* not supported */ }

    // CLS
    try {
      const obs2 = new PerformanceObserver(list => {
        list.getEntries().forEach(entry => {
          if (!entry.hadRecentInput) vitals.cls = round(vitals.cls + entry.value);
        });
      });
      obs2.observe({ type: 'layout-shift', buffered: true });
    } catch (e) { /* not supported */ }

    // INP (approximate using event durations)
    try {
      const obs3 = new PerformanceObserver(list => {
        list.getEntries().forEach(entry => {
          const duration = entry.duration;
          if (duration != null && (vitals.inp === null || duration > vitals.inp)) vitals.inp = round(duration);
        });
      });
      obs3.observe({ type: 'event', buffered: true, durationThreshold: 16 });
    } catch (e) { /* not supported */ }
  }

  function getWebVitals() {
    return { lcp: vitals.lcp, cls: vitals.cls, inp: vitals.inp };
  }

  // ---------------------------
  // Payload delivery (sendBeacon fallback)
  // ---------------------------
  function send(payload) {
    try {
      const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
      if (navigator.sendBeacon) {
        navigator.sendBeacon(ENDPOINT, blob);
      } else {
        fetch(ENDPOINT, { method: 'POST', body: blob, keepalive: true }).catch(() => {});
      }
    } catch (e) { /* swallow */ }
    // debug
    if (window && window.console && window.console.debug) {
      console.debug('[collector-v7] send', payload && payload.type ? payload.type : '(payload)', payload);
    }
  }

  // ---------------------------
  // Error tracking / reportError
  // ---------------------------
  function reportError(errorData) {
    if (errorCount >= MAX_ERRORS) return;
    const key = `${errorData.type || ''}:${errorData.message || ''}:${errorData.source || ''}:${errorData.line || ''}`;
    if (reportedErrors.has(key)) return;
    reportedErrors.add(key);
    errorCount++;

    const payload = {
      type: 'error',
      sessionId,
      timestamp: (new Date()).toISOString(),
      url: window.location.href,
      error: errorData,
      vitals: getWebVitals()
    };
    send(payload);

    // allow test pages to listen to errors
    window.dispatchEvent(new CustomEvent('collector:error', { detail: { error: errorData, count: errorCount } }));
  }

  function initErrorTracking() {
    // JS runtime errors and resource errors (capture)
    window.addEventListener('error', event => {
      if (event instanceof ErrorEvent) {
        reportError({
          type: 'js-error',
          message: event.message,
          source: event.filename,
          line: event.lineno,
          column: event.colno,
          stack: event.error ? (event.error.stack || '') : ''
        });
      } else {
        // resource error
        const target = event.target || {};
        if (target.tagName) {
          reportError({
            type: 'resource-error',
            tagName: target.tagName,
            src: target.currentSrc || target.src || target.href || ''
          });
        }
      }
    }, true);

    // unhandled promise rejections
    window.addEventListener('unhandledrejection', event => {
      const reason = event.reason;
      reportError({
        type: 'promise-rejection',
        message: reason instanceof Error ? reason.message : String(reason),
        stack: reason instanceof Error ? reason.stack : ''
      });
    });

    // expose
    if (window && window.console) console.debug('[collector-v7] error tracking initialized');
  }

  // ---------------------------
  // Activity tracking (mouse, clicks, scrolls, keys, idle)
  // ---------------------------
  let lastMouseTime = 0;
  let lastActivity = nowMs();
  let idleStart = null;

  const activityData = {
    mouseMoves: [],
    clicks: [],
    scrolls: [],
    keys: [],
    errors: [],
    idleTimes: []
  };

  function pushEvent(arr, ev) {
    arr.push(ev);
    if (arr.length > MAX_EVENTS_PER_BATCH) arr.shift();
  }

  function shouldSampleMouse() {
    const n = nowMs();
    if (n - lastMouseTime < MOUSE_THROTTLE_MS) return false;
    lastMouseTime = n;
    return true;
  }

  function checkIdle() {
    const n = nowMs();
    if (!idleStart && (n - lastActivity) > 2000) idleStart = lastActivity;
    if (idleStart && (n - lastActivity) <= 2000) {
      activityData.idleTimes.push({ start: idleStart, duration: nowMs() - idleStart });
      idleStart = null;
      if (activityData.idleTimes.length > 50) activityData.idleTimes.shift();
    }
    lastActivity = n;
  }

  document.addEventListener('mousemove', e => {
    if (!shouldSampleMouse()) return;
    pushEvent(activityData.mouseMoves, { x: e.clientX, y: e.clientY, t: nowMs() });
    checkIdle();
  });

  document.addEventListener('click', e => {
    pushEvent(activityData.clicks, { x: e.clientX, y: e.clientY, button: e.button, t: nowMs() });
    checkIdle();
  });

  window.addEventListener('scroll', () => {
    pushEvent(activityData.scrolls, { x: window.scrollX, y: window.scrollY, t: nowMs() });
    checkIdle();
  }, { passive: true });

  document.addEventListener('keydown', e => { pushEvent(activityData.keys, { key: e.key, type: 'down', t: nowMs() }); checkIdle(); });
  document.addEventListener('keyup', e => { pushEvent(activityData.keys, { key: e.key, type: 'up', t: nowMs() }); checkIdle(); });

  window.addEventListener('error', e => {
    // also capture into activity errors array (non-duplicate)
    try {
      pushEvent(activityData.errors, {
        message: e.message || '',
        source: e.filename || '',
        lineno: e.lineno || 0,
        colno: e.colno || 0,
        stack: e.error && e.error.stack ? String(e.error.stack).slice(0, 2000) : null,
        t: nowMs()
      });
    } catch (ex) { /* ignore */ }
  });

  // Periodic flush of activity data
  setInterval(() => {
    const has = activityData.mouseMoves.length || activityData.clicks.length || activityData.keys.length ||
                activityData.scrolls.length || activityData.errors.length || activityData.idleTimes.length;
    if (!has) return;

    const payload = {
      type: 'activity',
      timestamp: (new Date()).toISOString(),
      sessionId,
      page: window.location.pathname,
      t: nowMs(),
      activityData: {
        mouseMoves: activityData.mouseMoves.splice(0),
        clicks: activityData.clicks.splice(0),
        scrolls: activityData.scrolls.splice(0),
        keys: activityData.keys.splice(0),
        errors: activityData.errors.splice(0),
        idleTimes: activityData.idleTimes.splice(0)
      }
    };

    send(payload);
  }, SEND_INTERVAL_MS);

  // On visibility change / unload: send final data and a full snapshot
  function collectAndSendSnapshot(isExit = false) {
    const snapshot = {
      type: isExit ? 'page-exit' : 'pageview',
      timestamp: (new Date()).toISOString(),
      sessionId,
      url: window.location.href,
      title: document.title,
      referrer: document.referrer || null,
      technographics: getTechnographics(),
      navTiming: getNavigationTiming(),
      resources: getResourceSummary(),
      vitals: getWebVitals(),
      activitySummary: {
        mouseMoves: activityData.mouseMoves.slice(0, 10), // small sample
        clicks: activityData.clicks.slice(0, 10),
        keys: activityData.keys.slice(0, 10),
        idleTimes: activityData.idleTimes.slice(0, 10)
      },
      errorCount,
      isExit
    };

    // detect images/css status before sending static-ish info
    detectImagesEnabled(300).then(imagesEnabled => {
      snapshot.technographics.imagesEnabled = imagesEnabled;
      snapshot.technographics.cssEnabled = detectCssEnabled();
      send(snapshot);
    }).catch(() => {
      snapshot.technographics.imagesEnabled = false;
      snapshot.technographics.cssEnabled = detectCssEnabled();
      send(snapshot);
    });
  }

  // visibilitychange and beforeunload handlers
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') collectAndSendSnapshot(true);
  });

  window.addEventListener('beforeunload', () => {
    try { collectAndSendSnapshot(true); } catch (e) { /* best-effort */ }
  });

  // On page load, initialize vitals & error tracking and send initial snapshot
  window.addEventListener('load', () => {
    try {
      initWebVitals();
      initErrorTracking();
      // small delay to allow paint entries to populate
      setTimeout(() => collectAndSendSnapshot(false), 50);
    } catch (e) { /* ignore */ }
  });

  // Expose a small API for debugging/testing
  window.__collector = {
    getSessionId,
    getTechnographics,
    getNavigationTiming,
    getResourceSummary,
    getWebVitals,
    reportError,
    collectNow: collectAndSendSnapshot,
    getErrorCount: () => errorCount,
    getReportedErrors: () => Array.from(reportedErrors)
  };

  // Mark initialized
  if (window && window.console) console.debug('[collector-v7] initialized (endpoint)', ENDPOINT);

})();
