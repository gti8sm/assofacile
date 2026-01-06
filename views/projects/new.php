<?php

$title = 'Nouveau projet';
ob_start();

$vName = (string)($_POST['name'] ?? '');
$vFunding = (string)($_POST['funding_type'] ?? '');
$vStarts = (string)($_POST['starts_on'] ?? '');
$vEnds = (string)($_POST['ends_on'] ?? '');
$vDescription = (string)($_POST['description'] ?? '');
?>
<div class="max-w-2xl">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Nouveau projet</h1>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/projects')) ?>">Retour</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="mt-4 space-y-6 bg-white border border-slate-200 rounded-lg p-4">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">

        <div>
            <label class="block text-sm font-medium mb-1">Nom</label>
            <input name="name" value="<?= e($vName) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Type de financement</label>
            <input name="funding_type" value="<?= e($vFunding) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Subvention, mécénat, adhésions…">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Début</label>
                <input name="starts_on" type="date" value="<?= e($vStarts) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Fin</label>
                <input name="ends_on" type="date" value="<?= e($vEnds) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Description</label>
            <textarea name="description" class="w-full border border-slate-300 rounded px-3 py-2" rows="4"><?= e($vDescription) ?></textarea>
        </div>

        <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Créer</button>
    </form>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
