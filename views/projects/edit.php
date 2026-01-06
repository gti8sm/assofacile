<?php

$title = 'Modifier projet';
ob_start();

$project = is_array($project ?? null) ? $project : [];
$projectId = (int)($project['id'] ?? 0);

$vName = (string)($_POST['name'] ?? ($project['name'] ?? ''));
$vFunding = (string)($_POST['funding_type'] ?? ($project['funding_type'] ?? ''));
$vStarts = (string)($_POST['starts_on'] ?? ($project['starts_on'] ?? ''));
$vEnds = (string)($_POST['ends_on'] ?? ($project['ends_on'] ?? ''));
$vStatus = (string)($_POST['status'] ?? ($project['status'] ?? 'active'));
$vDescription = (string)($_POST['description'] ?? ($project['description'] ?? ''));
?>
<div class="max-w-2xl">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Modifier projet</h1>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/projects/view?id=' . $projectId)) ?>">Retour</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(tenant_path('/projects/edit')) ?>" class="mt-4 space-y-6 bg-white border border-slate-200 rounded-lg p-4">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= e((string)$projectId) ?>">

        <div>
            <label class="block text-sm font-medium mb-1">Nom</label>
            <input name="name" value="<?= e($vName) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Statut</label>
            <select name="status" class="w-full border border-slate-300 rounded px-3 py-2">
                <option value="active" <?= ($vStatus === 'active' ? 'selected' : '') ?>>actif</option>
                <option value="completed" <?= ($vStatus === 'completed' ? 'selected' : '') ?>>terminé</option>
                <option value="archived" <?= ($vStatus === 'archived' ? 'selected' : '') ?>>archivé</option>
            </select>
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
            <textarea name="description" class="w-full border border-slate-300 rounded px-3 py-2" rows="5"><?= e($vDescription) ?></textarea>
        </div>

        <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
    </form>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
