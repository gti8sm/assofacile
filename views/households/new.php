<?php

$title = 'Nouveau foyer';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Nouveau foyer</h1>
    <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/households">Retour</a>
</div>

<?php if (!empty($error)): ?>
    <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-4">
    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Nom du foyer</label>
            <input name="name" value="<?= e((string)($_POST['name'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Ex: Famille DUPONT">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Adresse</label>
            <textarea name="address" class="w-full border border-slate-300 rounded px-3 py-2" rows="3"><?= e((string)($_POST['address'] ?? '')) ?></textarea>
        </div>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Créer</button>
</form>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
