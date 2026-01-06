<?php

$title = 'Modifier tâche';
ob_start();

$task = is_array($task ?? null) ? $task : [];
$actions = is_array($actions ?? null) ? $actions : [];

$taskId = (int)($task['id'] ?? 0);
$projectId = (int)($task['project_id'] ?? 0);

$vTitle = (string)($_POST['title'] ?? ($task['title'] ?? ''));
$vDue = (string)($_POST['due_on'] ?? ($task['due_on'] ?? ''));
$vStatus = (string)($_POST['status'] ?? ($task['status'] ?? 'todo'));
$vActionId = (string)($_POST['action_id'] ?? ($task['action_id'] ?? ''));
?>
<div class="max-w-2xl">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Modifier tâche</h1>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/projects/view?id=' . $projectId)) ?>">Retour</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(tenant_path('/projects/tasks/edit')) ?>" class="mt-4 space-y-6 bg-white border border-slate-200 rounded-lg p-4">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= e((string)$taskId) ?>">
        <input type="hidden" name="project_id" value="<?= e((string)$projectId) ?>">

        <div>
            <label class="block text-sm font-medium mb-1">Titre</label>
            <input name="title" value="<?= e($vTitle) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Statut</label>
                <select name="status" class="w-full border border-slate-300 rounded px-3 py-2">
                    <option value="todo" <?= ($vStatus === 'todo' ? 'selected' : '') ?>>à faire</option>
                    <option value="doing" <?= ($vStatus === 'doing' ? 'selected' : '') ?>>en cours</option>
                    <option value="done" <?= ($vStatus === 'done' ? 'selected' : '') ?>>fait</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Échéance (optionnel)</label>
                <input name="due_on" type="date" value="<?= e($vDue) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Action (optionnel)</label>
            <select name="action_id" class="w-full border border-slate-300 rounded px-3 py-2">
                <option value="">—</option>
                <?php foreach ($actions as $a): ?>
                    <?php $aid = (string)($a['id'] ?? ''); ?>
                    <option value="<?= e($aid) ?>" <?= ((string)$vActionId === $aid ? 'selected' : '') ?>><?= e((string)($a['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
    </form>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
