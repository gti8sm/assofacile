<?php

$title = 'Adhérents';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Adhérents</h1>
    <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'members', 'write')): ?>
        <a class="bg-slate-900 text-white rounded px-3 py-2 text-sm" href="/members/new">Ajouter</a>
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

<form method="get" class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Recherche</label>
            <input name="q" value="<?= e((string)($q ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Nom, email, téléphone">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Statut</label>
            <select name="status" class="w-full border border-slate-300 rounded px-3 py-2">
                <option value="" <?= ($status ?? '') === '' ? 'selected' : '' ?>>Tous</option>
                <option value="active" <?= ($status ?? '') === 'active' ? 'selected' : '' ?>>Actifs</option>
                <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
            </select>
        </div>
    </div>
    <button class="mt-3 border border-slate-300 rounded px-3 py-2 text-sm" type="submit">Filtrer</button>
</form>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Nom</th>
            <th class="text-left p-3">Contact</th>
            <th class="text-left p-3">Statut</th>
            <th class="text-left p-3">Cotisation</th>
            <th class="text-right p-3">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($members)): ?>
            <tr>
                <td class="p-3 text-slate-500" colspan="5">Aucun adhérent.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($members as $m): ?>
                <tr class="border-t border-slate-200">
                    <td class="p-3">
                        <div class="font-medium">
                            <?= e(trim((string)($m['first_name'] ?? '') . ' ' . (string)($m['last_name'] ?? ''))) ?: '—' ?>
                        </div>
                        <div class="text-xs text-slate-500">#<?= e((string)$m['id']) ?></div>
                    </td>
                    <td class="p-3">
                        <div><?= e((string)($m['email'] ?? '')) ?></div>
                        <div class="text-xs text-slate-500"><?= e((string)($m['phone'] ?? '')) ?></div>
                    </td>
                    <td class="p-3">
                        <?php if ((string)$m['status'] === 'inactive'): ?>
                            <span class="text-xs bg-slate-100 text-slate-700 px-2 py-1 rounded">inactif</span>
                        <?php else: ?>
                            <span class="text-xs bg-emerald-50 text-emerald-700 px-2 py-1 rounded">actif</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <div class="text-xs text-slate-500">Payée jusqu'au</div>
                        <div><?= e((string)($m['membership_paid_until'] ?? '')) ?></div>
                    </td>
                    <td class="p-3 text-right">
                        <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'members', 'write')): ?>
                            <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/members/edit?id=<?= e((string)$m['id']) ?>">Modifier</a>
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
