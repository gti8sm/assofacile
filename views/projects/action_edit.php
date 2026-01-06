<?php

$title = 'Modifier action';
ob_start();

$action = is_array($action ?? null) ? $action : [];
$actionId = (int)($action['id'] ?? 0);
$projectId = (int)($action['project_id'] ?? 0);

$vName = (string)($_POST['name'] ?? ($action['name'] ?? ''));
$vRule = (string)($_POST['schedule_rule'] ?? ($action['schedule_rule'] ?? ''));
?>
<div class="max-w-2xl">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Modifier action</h1>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/projects/view?id=' . $projectId)) ?>">Retour</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(tenant_path('/projects/actions/edit')) ?>" class="mt-4 space-y-6 bg-white border border-slate-200 rounded-lg p-4">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= e((string)$actionId) ?>">
        <input type="hidden" name="project_id" value="<?= e((string)$projectId) ?>">

        <div>
            <label class="block text-sm font-medium mb-1">Nom</label>
            <input name="name" value="<?= e($vName) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Règle (optionnel)</label>
            <input name="schedule_rule" value="<?= e($vRule) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Ex: weekly:tuesday">
        </div>

        <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
    </form>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
