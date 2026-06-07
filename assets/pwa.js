/*  MedAgent AI — PWA bootstrap
 *
 *  Responsibilities:
 *    1. Register /sw.js (root-scoped).
 *    2. Show an install banner when beforeinstallprompt fires (Chromium).
 *    3. Show an iOS-specific "Add to Home Screen" hint (Safari).
 *    4. Toast network status changes.
 *    5. Detect waiting-worker updates and surface a "Reload to update" button.
 *
 *  Strings come from data-* attributes on a hidden i18n element rendered by
 *  templates/components/pwa_head.php, so this script stays language-agnostic.
 */
(function () {
  'use strict';

  if (location.protocol !== 'https:' &&
      location.hostname !== 'localhost' &&
      location.hostname !== '127.0.0.1') {
    // Service workers require a secure context. Bail silently on plain HTTP.
    return;
  }

  // -------------- i18n shim --------------
  var i18nEl = document.getElementById('pwa-i18n');
  function t(key, fallback) {
    if (i18nEl && i18nEl.dataset && i18nEl.dataset[key]) return i18nEl.dataset[key];
    return fallback;
  }

  // -------------- Service worker registration --------------
  var swRegistration = null;
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then(function (reg) {
          swRegistration = reg;
          watchForUpdate(reg);
        })
        .catch(function (err) {
          // Soft-fail: PWA features simply won't be available.
          if (window.console) console.warn('[PWA] SW registration failed:', err);
        });

      // When the active worker changes (after we trigger skipWaiting),
      // do a single full reload so the new shell takes over.
      var reloading = false;
      navigator.serviceWorker.addEventListener('controllerchange', function () {
        if (reloading) return;
        reloading = true;
        location.reload();
      });
    });
  }

  function watchForUpdate(reg) {
    function notify(worker) {
      if (!worker) return;
      if (worker.state === 'installed' && navigator.serviceWorker.controller) {
        showUpdateBanner(worker);
      }
    }
    if (reg.waiting) notify(reg.waiting);
    reg.addEventListener('updatefound', function () {
      var worker = reg.installing;
      if (!worker) return;
      worker.addEventListener('statechange', function () { notify(worker); });
    });
  }

  // -------------- Install prompt (Android / desktop Chrome / Edge) --------------
  var deferredPrompt = null;
  var installBanner = null;
  var DISMISS_KEY = 'pwa.install.dismissed.until';

  function isDismissedRecently() {
    try {
      var until = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
      return until && Date.now() < until;
    } catch (_) { return false; }
  }
  function dismissForDays(days) {
    try {
      localStorage.setItem(DISMISS_KEY, String(Date.now() + days * 86400000));
    } catch (_) {}
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    if (isDismissedRecently()) return;
    if (isStandalone()) return;
    ensureInstallBanner();
  });

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches ||
           window.matchMedia('(display-mode: minimal-ui)').matches ||
           // iOS Safari (non-standard)
           window.navigator.standalone === true;
  }

  function ensureInstallBanner() {
    if (installBanner) { installBanner.classList.add('is-open'); return; }
    installBanner = document.createElement('div');
    installBanner.id = 'pwa-install-banner';
    installBanner.setAttribute('role', 'dialog');
    installBanner.setAttribute('aria-label', t('installTitle', 'Install MedAgent AI'));
    installBanner.innerHTML =
      '<div class="pwa-logo">' +
        '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M2 12 H6 L8 6 L11 18 L14 4 L16 12 H22"/></svg>' +
      '</div>' +
      '<div class="pwa-text">' +
        '<div class="pwa-title"></div>' +
        '<div class="pwa-sub"></div>' +
      '</div>' +
      '<div class="pwa-actions">' +
        '<button type="button" class="pwa-btn ghost" data-action="dismiss"></button>' +
        '<button type="button" class="pwa-btn primary" data-action="install"></button>' +
      '</div>';
    installBanner.querySelector('.pwa-title').textContent = t('installTitle', 'Install MedAgent AI');
    installBanner.querySelector('.pwa-sub').textContent = t('installSub', 'Faster access, works offline');
    installBanner.querySelector('[data-action="dismiss"]').textContent = t('installDismiss', 'Not now');
    installBanner.querySelector('[data-action="install"]').textContent = t('installAction', 'Install');
    document.body.appendChild(installBanner);
    installBanner.classList.add('is-open');

    installBanner.querySelector('[data-action="dismiss"]').addEventListener('click', function () {
      installBanner.classList.remove('is-open');
      dismissForDays(14);
    });
    installBanner.querySelector('[data-action="install"]').addEventListener('click', function () {
      if (!deferredPrompt) { installBanner.classList.remove('is-open'); return; }
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function (choice) {
        installBanner.classList.remove('is-open');
        if (choice && choice.outcome !== 'accepted') dismissForDays(7);
        deferredPrompt = null;
      });
    });
  }

  window.addEventListener('appinstalled', function () {
    if (installBanner) installBanner.classList.remove('is-open');
    dismissForDays(365);
  });

  // -------------- iOS Safari hint --------------
  function showIOSHint() {
    if (document.getElementById('pwa-ios-hint')) return;
    var ua = window.navigator.userAgent || '';
    var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    var isSafari = /^((?!chrome|android|crios|fxios).)*safari/i.test(ua);
    if (!isIOS || !isSafari) return;
    if (isStandalone()) return;
    if (isDismissedRecently()) return;
    var box = document.createElement('div');
    box.id = 'pwa-ios-hint';
    box.innerHTML =
      '<button type="button" class="close" aria-label="' + (t('iosDismiss', 'Close')) + '">&times;</button>' +
      '<b>' + (t('iosTitle', 'Install MedAgent AI')) + '</b><br>' +
      (t('iosBody', 'Tap the Share button')) + ' ' +
      '<span class="share-icon">' +
        '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M12 3v12"/><path d="M8 7l4-4 4 4"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>' +
      '</span>' +
      ' ' + (t('iosBody2', 'then "Add to Home Screen".'));
    document.body.appendChild(box);
    box.classList.add('is-open');
    box.querySelector('.close').addEventListener('click', function () {
      box.classList.remove('is-open');
      dismissForDays(14);
    });
  }
  window.addEventListener('load', function () { setTimeout(showIOSHint, 2500); });

  // -------------- Network toast --------------
  var netToast = null;
  function ensureNetToast() {
    if (netToast) return netToast;
    netToast = document.createElement('div');
    netToast.id = 'pwa-net-toast';
    document.body.appendChild(netToast);
    return netToast;
  }
  function showNetToast(message, online) {
    var el = ensureNetToast();
    el.textContent = message;
    el.classList.toggle('is-online', !!online);
    el.classList.add('is-open');
    clearTimeout(showNetToast._t);
    showNetToast._t = setTimeout(function () { el.classList.remove('is-open'); }, 3000);
  }
  window.addEventListener('offline', function () { showNetToast(t('netOffline', 'You are offline'), false); });
  window.addEventListener('online',  function () { showNetToast(t('netOnline',  'Back online'),    true); });

  // -------------- Update banner --------------
  function showUpdateBanner(worker) {
    if (document.getElementById('pwa-update-banner')) return;
    var bar = document.createElement('div');
    bar.id = 'pwa-update-banner';
    bar.innerHTML =
      '<span>' + (t('updateAvailable', 'New version available')) + '</span>' +
      '<button type="button">' + (t('updateReload', 'Reload')) + '</button>';
    document.body.appendChild(bar);
    bar.classList.add('is-open');
    bar.querySelector('button').addEventListener('click', function () {
      bar.classList.remove('is-open');
      try { worker.postMessage({ type: 'SKIP_WAITING' }); } catch (_) {}
      // controllerchange handler above will reload.
      setTimeout(function () { location.reload(); }, 800);
    });
  }
})();
