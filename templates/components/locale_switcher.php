<label class="flex items-center gap-2 text-xs text-ink-600">
    <span class="sr-only"><?= h(t('Locale.label')) ?></span>
    <select onchange="setLocale(this.value)" class="bg-transparent border-0 text-xs text-ink-700 focus:outline-none focus:ring-0 cursor-pointer">
        <?php foreach (SUPPORTED_LOCALES as $l): ?>
            <option value="<?= h($l) ?>" <?= $l === current_locale() ? 'selected' : '' ?>>
                <?= h(t("Locale.$l")) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>
<script>
function setLocale(loc) {
    const fd = new FormData();
    fd.append('locale', loc);
    fd.append('_csrf', document.querySelector('meta[name="csrf-token"]')?.content || '');
    fetch('/api/locale', { method: 'POST', body: fd })
        .then(() => location.reload());
}
</script>
