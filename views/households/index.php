<?php

$title = 'Familles';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Familles</h1>
    <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'members', 'write')): ?>
        <a class="bg-slate-900 text-white rounded px-3 py-2 text-sm" href="/households/new">Créer un foyer</a>
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
            <th class="text-left p-3">Foyer</th>
            <th class="text-left p-3">Adresse</th>
            <th class="text-left p-3">Membres</th>
            <th class="text-right p-3">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($households)): ?>
            <tr>
                <td class="p-3 text-slate-500" colspan="4">Aucun foyer.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($households as $h): ?>
                <tr class="border-t border-slate-200">
                    <td class="p-3">
                        <div class="font-medium"><?= e((string)($h['name'] ?? '')) ?: ('Foyer #' . e((string)$h['id'])) ?></div>
                        <div class="text-xs text-slate-500">#<?= e((string)$h['id']) ?></div>
                    </td>
                    <td class="p-3">
                        <div class="whitespace-pre-line"><?= e((string)($h['address'] ?? '')) ?></div>
                    </td>
                    <td class="p-3"><?= e((string)($h['members_count'] ?? '0')) ?></td>
                    <td class="p-3 text-right">
                        <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'members', 'write')): ?>
                            <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/households/edit?id=<?= e((string)$h['id']) ?>">Ouvrir</a>
                        <?php else: ?>
                            <span class="text-xs text-slate-500">—</span>
                        <?php endif; ?>
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
