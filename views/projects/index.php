<?php

$title = 'Projets';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Projets</h1>
    <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
        <a class="bg-slate-900 text-white rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/projects/new')) ?>">Nouveau</a>
    <?php endif; ?>
</div>

<?php if (!empty($flash)): ?>
    <div class="mt-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm border border-emerald-200">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Nom</th>
            <th class="text-left p-3">Période</th>
            <th class="text-left p-3">Statut</th>
            <th class="text-right p-3">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($projects)): ?>
            <tr>
                <td class="p-3 text-slate-500" colspan="4">Aucun projet.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($projects as $p): ?>
                <?php
                $id = (int)$p['id'];
                $starts = (string)($p['starts_on'] ?? '');
                $ends = (string)($p['ends_on'] ?? '');
                $status = (string)($p['status'] ?? '');
                $period = trim(($starts !== '' ? $starts : '') . (($ends !== '' ? ' → ' . $ends : '')));
                ?>
                <tr class="border-t border-slate-200">
                    <td class="p-3">
                        <div class="font-medium"><?= e((string)$p['name']) ?></div>
                        <div class="text-xs text-slate-500">#<?= e((string)$id) ?></div>
                    </td>
                    <td class="p-3 text-slate-700"><?= e($period !== '' ? $period : '—') ?></td>
                    <td class="p-3">
                        <span class="text-xs bg-slate-100 text-slate-700 px-2 py-1 rounded"><?= e($status !== '' ? $status : '—') ?></span>
                    </td>
                    <td class="p-3 text-right">
                        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/projects/view?id=' . $id)) ?>">Ouvrir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
