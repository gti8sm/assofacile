<?php
$title = 'Admin - Modules';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Modules</h1>
</div>

<?php if (!empty($flash)): ?>
    <div class="mt-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm border border-emerald-200">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-4">
    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">

    <div class="space-y-3">
        <?php foreach ($modules as $m): ?>
            <?php $key = (string)$m['module_key']; ?>
            <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
                <div>
                    <div class="font-medium"><?= e((string)$m['name']) ?></div>
                    <div class="text-xs text-slate-500"><?= e($key) ?></div>
                </div>
                <input
                    type="checkbox"
                    name="modules[<?= e($key) ?>]"
                    value="1"
                    class="h-5 w-5"
                    <?= !empty($enabledByKey[$key]) ? 'checked' : '' ?>
                >
            </label>
        <?php endforeach; ?>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
</form>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
