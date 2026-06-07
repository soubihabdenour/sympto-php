/*  MedAgent AI — Push notifications client (OneSignal)
 *
 *  The OneSignal Web SDK runs on its own service worker
 *  (OneSignalSDKWorker.js) and is configured via window.OneSignal.
 *
 *  This module:
 *    1. Loads the SDK only when a OneSignal App ID is configured on the page
 *       (look for <meta name="onesignal-app-id" content="…">).
 *    2. Initializes with the soft-prompt slidedown (so we never raise the
 *       native permission dialog unprompted — required by Chrome's quality
 *       guidelines).
 *    3. When the user opts in, sends their OneSignal player ID to the PHP
 *       backend at /api/push/subscribe so it can be linked to their doctor
 *       account in the push_subscriptions table.
 *    4. On logout/unsubscribe (window event "medagent:logout"), unlinks
 *       the player ID server-side.
 */
(function () {
  'use strict';

  var meta = document.querySelector('meta[name="onesignal-app-id"]');
  var APP_ID = meta && meta.content ? meta.content.trim() : '';
  if (!APP_ID) return;   // OneSignal not configured -> do nothing.

  if (location.protocol !== 'https:' &&
      location.hostname !== 'localhost' &&
      location.hostname !== '127.0.0.1') {
    return;
  }

  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var CSRF = csrfMeta ? csrfMeta.content : '';

  function postJson(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'fetch',
        'X-CSRF-Token': CSRF,
      },
      body: JSON.stringify(body || {}),
    });
  }

  // Lazy-load the OneSignal SDK from their CDN.
  // (Pinned to v16, which is the long-lived web push SDK.)
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  var s = document.createElement('script');
  s.src = 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js';
  s.async = true;
  s.defer = true;
  document.head.appendChild(s);

  // Try to surface a hint that we want a subscription from the user.
  // The slidedown prompt is shown by OneSignal itself; everything else here
  // just keeps our DB in sync with the player ID OneSignal mints.
  window.OneSignalDeferred.push(async function (OneSignal) {
    try {
      await OneSignal.init({
        appId: APP_ID,
        // The promptOptions soft-prompt asks the user before we ever raise the
        // browser-native permission UI. This is a Chrome-required best practice.
        promptOptions: {
          slidedown: {
            prompts: [{
              type: 'push',
              autoPrompt: true,
              delay: { pageViews: 2, timeDelay: 20 },
              text: {
                actionMessage:
                  'Get notified when reports complete and when admins broadcast urgent advisories.',
                acceptButton: 'Allow',
                cancelButton: 'Not now',
              },
            }],
          },
        },
        // We use our own root /sw.js for the app shell, so let OneSignal
        // use its own worker files at the root.
        serviceWorkerPath: 'OneSignalSDKWorker.js',
        serviceWorkerParam: { scope: '/' },
        allowLocalhostAsSecureOrigin: true,
        notifyButton: { enable: false },
      });

      // Subscription change -> server sync
      OneSignal.User.PushSubscription.addEventListener('change', function (ev) {
        var sub = ev && ev.current ? ev.current : null;
        var optedIn = sub && sub.optedIn;
        var playerId = sub && sub.id;
        if (optedIn && playerId) syncSubscribed(playerId);
        else if (sub && sub.id) syncUnsubscribed(sub.id);
      });

      // First-load reconciliation: if the user already has a player ID, push it.
      var ps = OneSignal.User.PushSubscription;
      if (ps && ps.id && ps.optedIn) syncSubscribed(ps.id);
    } catch (err) {
      if (window.console) console.warn('[OneSignal] init failed:', err);
    }
  });

  function syncSubscribed(playerId) {
    postJson('/api/push/subscribe', { player_id: playerId, platform: detectPlatform() })
      .catch(function () { /* best-effort */ });
  }
  function syncUnsubscribed(playerId) {
    postJson('/api/push/unsubscribe', { player_id: playerId })
      .catch(function () { /* best-effort */ });
  }

  // Hook for the rest of the app: clear server-side on logout
  window.addEventListener('medagent:logout', function () {
    window.OneSignalDeferred.push(async function (OneSignal) {
      try {
        var ps = OneSignal.User && OneSignal.User.PushSubscription;
        if (ps && ps.id) syncUnsubscribed(ps.id);
        if (OneSignal.User && OneSignal.User.removeExternalId) {
          await OneSignal.User.removeExternalId();
        }
      } catch (_) {}
    });
  });

  function detectPlatform() {
    var ua = navigator.userAgent || '';
    if (/iPad|iPhone|iPod/.test(ua)) return 'ios';
    if (/Android/.test(ua)) return 'android';
    if (/Mac OS X/.test(ua)) return 'macos';
    if (/Windows/.test(ua)) return 'windows';
    return 'web';
  }
})();
