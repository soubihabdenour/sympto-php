<?php
/**
 * Drop-in PWA <head> markup.
 *
 * Include from layouts BEFORE </head>:
 *     <?php require TEMPLATES_DIR . '/components/pwa_head.php'; ?>
 *
 * It owns: manifest link, apple meta tags, iOS splash links, theme color,
 * PWA stylesheet, and the SW-registering script. It also exposes localized
 * strings as data-* attributes for the JS to pick up.
 *
 * It deliberately does NOT emit <meta name="theme-color"> if one is already
 * present in the calling layout — but it always emits its own per-color-scheme
 * variants to make the status bar look right in dark mode too.
 */
$onesignal_app_id = env('ONESIGNAL_APP_ID', '') ?? '';
$pwa_locale = current_locale();
?>
<link rel="manifest" href="/manifest.webmanifest">
<meta name="application-name" content="MedAgent AI">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<!--
  status-bar-style:
    "default" tells iOS to render its own status bar (clock / battery) ABOVE
    the web view. The web view starts below it, so the app header never
    overlaps the notch. (Switching to "black-translucent" floats the web
    view UNDER the status bar and requires perfect safe-area padding
    everywhere — fragile, especially on installs cached before the safe-area
    CSS shipped.)
-->
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="MedAgent AI">
<meta name="msapplication-config" content="/browserconfig.xml">
<meta name="msapplication-TileColor" content="#0e7490">
<meta name="format-detection" content="telephone=no">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#0e7490" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#155e75" media="(prefers-color-scheme: dark)">

<link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16.png">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="120x120" href="/icons/apple-touch-icon-iphone.png">
<link rel="apple-touch-icon" sizes="152x152" href="/icons/apple-touch-icon-ipad-old.png">
<link rel="apple-touch-icon" sizes="167x167" href="/icons/apple-touch-icon-ipad.png">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
<link rel="mask-icon" href="/favicon.svg" color="#0e7490">

<?php
// iOS splash screens — Apple requires precise media-queries per device pixel size.
$apple_splash = [
  ['1290x2796', '430px', '932px', '3'],
  ['1179x2556', '393px', '852px', '3'],
  ['1170x2532', '390px', '844px', '3'],
  ['1125x2436', '375px', '812px', '3'],
  ['1080x1920', '360px', '640px', '3'],
  ['1668x2388', '834px', '1194px', '2'],
  ['2048x2732', '1024px', '1366px', '2'],
];
foreach ($apple_splash as [$dim, $w, $h, $dpr]):
?>
<link rel="apple-touch-startup-image"
      media="(device-width: <?= $w ?>) and (device-height: <?= $h ?>) and (-webkit-device-pixel-ratio: <?= $dpr ?>) and (orientation: portrait)"
      href="/icons/apple-splash-<?= $dim ?>.png">
<?php endforeach; ?>

<link rel="stylesheet" href="/assets/pwa.css">

<?php if ($onesignal_app_id !== ''): ?>
<meta name="onesignal-app-id" content="<?= h($onesignal_app_id) ?>">
<?php endif; ?>

<span id="pwa-i18n" hidden
      data-installTitle="<?= h(t('Pwa.installTitle')) ?>"
      data-installSub="<?= h(t('Pwa.installSub')) ?>"
      data-installAction="<?= h(t('Pwa.installAction')) ?>"
      data-installDismiss="<?= h(t('Pwa.installDismiss')) ?>"
      data-iosTitle="<?= h(t('Pwa.iosTitle')) ?>"
      data-iosBody="<?= h(t('Pwa.iosBody')) ?>"
      data-iosBody2="<?= h(t('Pwa.iosBody2')) ?>"
      data-iosDismiss="<?= h(t('Pwa.iosDismiss')) ?>"
      data-netOffline="<?= h(t('Pwa.netOffline')) ?>"
      data-netOnline="<?= h(t('Pwa.netOnline')) ?>"
      data-updateAvailable="<?= h(t('Pwa.updateAvailable')) ?>"
      data-updateReload="<?= h(t('Pwa.updateReload')) ?>"></span>

<script src="/assets/pwa.js" defer></script>
<?php if ($onesignal_app_id !== '' && current_doctor()): ?>
<script src="/assets/notifications.js" defer></script>
<?php endif; ?>
