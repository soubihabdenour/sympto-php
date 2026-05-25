<?php
// Inputs: $title, $page_content (already-rendered HTML)
$title ??= 'MedAgent AI';
?>
<!doctype html>
<html lang="<?= h(current_locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title><?= h($title) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: { colors: {
    brand: { 50:'#eff6ff', 100:'#dbeafe', 200:'#bfdbfe', 300:'#93c5fd', 500:'#3b82f6', 600:'#2563eb', 700:'#1d4ed8' },
    ink: { 50:'#fafafa', 100:'#f4f4f5', 200:'#e4e4e7', 300:'#d4d4d8', 400:'#a1a1aa', 500:'#71717a', 600:'#52525b', 700:'#3f3f46', 800:'#27272a', 900:'#18181b' },
  } } } };
</script>
<style type="text/tailwindcss">
  @layer components {
    .input { @apply w-full rounded-md border border-ink-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500; }
    .label { @apply block text-xs font-medium text-ink-600 mb-1; }
    .btn-primary { @apply inline-flex items-center justify-center gap-1.5 rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 cursor-pointer; }
    .btn-secondary { @apply inline-flex items-center justify-center gap-1.5 rounded-md border border-ink-200 bg-white px-3 py-2 text-sm font-medium hover:bg-ink-50 cursor-pointer; }
    .btn-ghost { @apply inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-sm hover:bg-ink-100 cursor-pointer; }
    .card { @apply bg-white border border-ink-100 rounded-lg shadow-sm; }
    .pill { @apply inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium; }
    .section-title { @apply text-sm font-semibold text-ink-700; }
  }
  @media print { .no-print { display: none !important; } }
</style>
</head>
<body class="min-h-screen bg-ink-50 text-ink-900 font-sans antialiased">
<div class="flex min-h-screen">
  <?php require TEMPLATES_DIR . '/components/sidebar.php'; ?>
  <div class="flex-1 flex flex-col">
    <?php $disclaimer_variant = 'banner'; require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
    <main class="flex-1"><?= $page_content ?></main>
  </div>
</div>
</body>
</html>
