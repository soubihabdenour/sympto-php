<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title ??= 'MedAgent AI';
?>
<!doctype html>
<html lang="<?= h(current_locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<meta name="theme-color" content="#0e7490">
<title><?= h($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: {
    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'], mono: ['JetBrains Mono', 'ui-monospace', 'monospace'] },
    colors: {
      // Clinical teal — calm, medical, distinct from generic SaaS blue
      brand: { 50:'#ecfeff', 100:'#cffafe', 200:'#a5f3fc', 300:'#67e8f9', 400:'#22d3ee', 500:'#06b6d4', 600:'#0891b2', 700:'#0e7490', 800:'#155e75', 900:'#164e63' },
      // Cool slate — clinical, easier on eyes than zinc
      ink:   { 50:'#f8fafc', 100:'#f1f5f9', 200:'#e2e8f0', 300:'#cbd5e1', 400:'#94a3b8', 500:'#64748b', 600:'#475569', 700:'#334155', 800:'#1e293b', 900:'#0f172a' },
      // Medical green for confirmations / safe states
      vital: { 50:'#ecfdf5', 100:'#d1fae5', 500:'#10b981', 600:'#059669', 700:'#047857' },
    },
    boxShadow: {
      card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
      lift: '0 4px 12px -2px rgb(15 23 42 / 0.08), 0 2px 4px -2px rgb(15 23 42 / 0.06)',
    },
  } } };
</script>
<style type="text/tailwindcss">
  @layer base {
    body { font-feature-settings: "cv11", "ss01", "ss03"; }
  }
  @layer components {
    .input { @apply w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-900 placeholder:text-ink-400 transition-colors focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20; }
    .label { @apply block text-xs font-semibold uppercase tracking-wide text-ink-500 mb-1.5; }
    .btn-primary { @apply inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:bg-brand-800 active:translate-y-px disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer; }
    .btn-secondary { @apply inline-flex items-center justify-center gap-2 rounded-lg border border-ink-200 bg-white px-4 py-2 text-sm font-semibold text-ink-700 shadow-sm transition-colors hover:bg-ink-50 hover:border-ink-300 cursor-pointer; }
    .btn-ghost { @apply inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium text-ink-700 transition-colors hover:bg-ink-100 cursor-pointer; }
    .btn-danger-ghost { @apply inline-flex items-center justify-center gap-1 rounded-lg p-1.5 text-sm text-red-600 transition-colors hover:bg-red-50 cursor-pointer; }
    .card { @apply bg-white border border-ink-200 rounded-xl shadow-card; }
    .card-hover { @apply transition-all hover:shadow-lift hover:border-brand-200; }
    .pill { @apply inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold; }
    .section-title { @apply flex items-center gap-2 text-sm font-semibold text-ink-800; }
    .nav-item { @apply flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors; }
    .nav-item-idle { @apply text-ink-600 hover:bg-ink-100 hover:text-ink-900; }
    .nav-item-active { @apply bg-brand-50 text-brand-800 ring-1 ring-brand-100; }
    .kpi { @apply card p-4 flex items-center gap-3; }
    .kpi-icon { @apply w-10 h-10 rounded-lg grid place-items-center; }
    .tab-btn { @apply inline-flex items-center gap-2 py-2.5 px-1 -mb-px border-b-2 text-sm font-medium transition-colors; }
    .tab-idle { @apply border-transparent text-ink-500 hover:text-ink-800 hover:border-ink-200; }
    .tab-active { @apply border-brand-600 text-brand-800; }
  }
  @media print {
    .no-print { display: none !important; }
    body { background: #fff !important; }
  }
</style>
</head>
<body class="min-h-screen bg-ink-50 text-ink-900 font-sans antialiased">
<div class="flex min-h-screen">
  <?php require TEMPLATES_DIR . '/components/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-w-0">
    <?php $disclaimer_variant = 'banner'; require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
    <main class="flex-1"><?= $page_content ?></main>
  </div>
</div>
</body>
</html>
