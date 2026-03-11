const ENDPOINT = 'https://collector.anl139.site/collect.php';
const SEND_INTERVAL_MS = 5000;   // send activity batches every 5s
const MOUSE_THROTTLE_MS = 100;   // sample mouse at most every 100ms
const MAX_EVENTS_PER_BATCH = 1000; // basic safety cap

function getSessionId() {
  let sid = sessionStorage.getItem('_collector_sid');
  if (!sid) {
    sid = Math.random().toString(36).substring(2) + Date.now().toString(36);
    sessionStorage.setItem('_collector_sid', sid);
  }
  return sid;
}
function getNetworkInfo() {
  if (!('connection' in navigator)) return {};

   const conn = navigator.connection;
   return {
      effectiveType: conn.effectiveType,
      downlink: conn.downlink,
      rtt: conn.rtt,
      saveData: conn.saveData
    };
  }
const staticData = {
  sessionID,
  userAgent: navigator.userAgent,
  language: navigator.language,
  cookiesEnabled: navigator.cookieEnabled,
  jsEnabled: true,
  screenWidth: window.screen.width,
  screenHeight: window.screen.height,
  windowWidth: window.innerWidth,
  windowHeight: window.innerHeight,
  connectionType: getNetworkInfo(),
  page: window.location.pathname,
  referrer: document.referrer || null,
  entryTime: Date.now()
};

window.addEventListener('load', () => {
  try {
    const p = window.performance || {};
    const timing = p.timing || {};
    // A small, stable perf snapshot
    const perfData = {
      navigationStart: timing.navigationStart || performance.timeOrigin || Date.now(),
      loadEventEnd: timing.loadEventEnd || Date.now(),
      totalLoadTime: (timing.loadEventEnd && timing.navigationStart) ? (timing.loadEventEnd - timing.navigationStart) : null,
      // micro-metrics (if available)
      paintEntries: (performance.getEntriesByType ? performance.getEntriesByType('paint').map(e => ({name: e.name, start: e.startTime})) : [])
    };
    // send static + perf once
    sendData({ type: 'static', staticData, perfData, t: Date.now() });
  } catch (err) {
    // non-fatal
    console.error('perf capture failed', err);
  }
});

let lastMouseTime = 0;
let lastActivity = Date.now();
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
  // cap buffer sizes to avoid ballooning
  if (arr.length > MAX_EVENTS_PER_BATCH) arr.shift();
}

// Throttle helper
function shouldSampleMouse() {
  const now = Date.now();
  if (now - lastMouseTime < MOUSE_THROTTLE_MS) return false;
  lastMouseTime = now;
  return true;
}

function checkIdle() {
  const now = Date.now();
  if (!idleStart && now - lastActivity > 2000) {
    idleStart = lastActivity;
  } else if (idleStart && now - lastActivity <= 2000) {
    // user returned
    activityData.idleTimes.push({ start: idleStart, duration: Date.now() - idleStart });
    idleStart = null;
    if (activityData.idleTimes.length > 50) activityData.idleTimes.shift();
  }
  lastActivity = now;
}

document.addEventListener('mousemove', e => {
  if (!shouldSampleMouse()) return;
  pushEvent(activityData.mouseMoves, { x: e.clientX, y: e.clientY, t: Date.now() });
  checkIdle();
});

document.addEventListener('click', e => {
  pushEvent(activityData.clicks, {
    x: e.clientX, y: e.clientY, button: e.button, t: Date.now()
  });
  checkIdle();
});

window.addEventListener('scroll', () => {
  pushEvent(activityData.scrolls, { x: window.scrollX, y: window.scrollY, t: Date.now() });
  checkIdle();
}, { passive: true });

document.addEventListener('keydown', e => {
  pushEvent(activityData.keys, { key: e.key, type: 'down', t: Date.now() });
  checkIdle();
});
document.addEventListener('keyup', e => {
  pushEvent(activityData.keys, { key: e.key, type: 'up', t: Date.now() });
  checkIdle();
});

// Capture JS runtime errors
window.addEventListener('error', e => {
  pushEvent(activityData.errors, {
    message: e.message,
    source: e.filename,
    lineno: e.lineno,
    colno: e.colno,
    stack: e.error && e.error.stack ? String(e.error.stack).slice(0, 2000) : null,
    t: Date.now()
  });
});

// --------------------------
// sendData: sendBeacon with fetch fallback
// --------------------------
function sendData(payloadObj) {
  try {
    const body = JSON.stringify(payloadObj);
    const blob = new Blob([body], { type: 'application/json' });

    // sendBeacon returns boolean — check it
    const beaconOk = (navigator.sendBeacon && navigator.sendBeacon(ENDPOINT, blob));
    if (beaconOk) return true;

    // fallback to fetch with keepalive (works on unload on modern browsers)
    fetch(ENDPOINT, {
      method: 'POST',
      mode: 'cors',
      credentials: 'include',           // include cookies if you rely on them
      headers: { 'Content-Type': 'application/json' },
      body,
      keepalive: true
    }).catch(err => {
      // still non-fatal for analytics
      console.error('analytics send failed', err);
    });
    return true;
  } catch (err) {
    console.error('sendData exception', err);
    return false;
  }
}

setInterval(() => {
  const hasActivity = activityData.mouseMoves.length ||
                      activityData.clicks.length ||
                      activityData.keys.length ||
                      activityData.scrolls.length ||
                      activityData.errors.length ||
                      activityData.idleTimes.length;

  if (!hasActivity) return;

  const payload = {
    type: 'activity',
    sessionID,
    page: window.location.pathname,
    t: Date.now(),
    activityData: {
      mouseMoves: activityData.mouseMoves.splice(0),
      clicks: activityData.clicks.splice(0),
      scrolls: activityData.scrolls.splice(0),
      keys: activityData.keys.splice(0),
      errors: activityData.errors.splice(0),
      idleTimes: activityData.idleTimes.splice(0)
    }
  };

  sendData(payload);

}, SEND_INTERVAL_MS);

window.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') {
    // quick last-chance send
    const leavingPayload = {
      type: 'activity',
      sessionID,
      page: window.location.pathname,
      t: Date.now(),
      leaving: true,
      activityData: {
        mouseMoves: activityData.mouseMoves.splice(0),
        clicks: activityData.clicks.splice(0),
        scrolls: activityData.scrolls.splice(0),
        keys: activityData.keys.splice(0),
        errors: activityData.errors.splice(0),
        idleTimes: activityData.idleTimes.splice(0)
      }
    };
    // prefer sendBeacon for synchronous background send
    try {
      const blob = new Blob([JSON.stringify(leavingPayload)], { type: 'application/json' });
      if (!(navigator.sendBeacon && navigator.sendBeacon(ENDPOINT, blob))) {
        // fallback (may or may not complete, but fetch keepalive is best-effort)
        fetch(ENDPOINT, {
          method: 'POST',
          mode: 'cors',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(leavingPayload),
          keepalive: true
        }).catch(() => {});
      }
    } catch (err) {
      // swallow; analytics best-effort
    }
  }
});

window.addEventListener('beforeunload', () => {
  try {
    const leavingPayload = {
      type: 'activity',
      sessionID,
      page: window.location.pathname,
      t: Date.now(),
      leaving: true,
      activityData: {
        mouseMoves: activityData.mouseMoves,
        clicks: activityData.clicks,
        scrolls: activityData.scrolls,
        keys: activityData.keys,
        errors: activityData.errors,
        idleTimes: activityData.idleTimes
      }
    };
    const blob = new Blob([JSON.stringify(leavingPayload)], { type: 'application/json' });
    if (!(navigator.sendBeacon && navigator.sendBeacon(ENDPOINT, blob))) {
      navigator.sendBeacon && navigator.sendBeacon(ENDPOINT, blob); // try again, harmless
    }
  } catch (err) {}
});
