<?php

$title = 'Cotisations - Catalogue';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Cotisations</h1>
    <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'members', 'write')): ?>
        <a class="bg-slate-900 text-white rounded px-3 py-2 text-sm" href="/memberships/products/new">Nouvelle cotisation</a>
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
            <th class="text-left p-3">Libellé</th>
            <th class="text-left p-3">Cible</th>
            <th class="text-left p-3">Montant</th>
            <th class="text-left p-3">Durée</th>
            <th class="text-left p-3">Actif</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($products)): ?>
            <tr>
                <td class="p-3 text-slate-500" colspan="5">Aucune cotisation.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($products as $p): ?>
                <tr class="border-t border-slate-200">
                    <td class="p-3">
                        <div class="font-medium"><?= e((string)$p['label']) ?></div>
                        <div class="text-xs text-slate-500">#<?= e((string)$p['id']) ?></div>
                    </td>
                    <td class="p-3">
                        <span class="text-xs bg-slate-100 text-slate-700 px-2 py-1 rounded"><?= e((string)$p['applies_to']) ?></span>
                    </td>
                    <td class="p-3">
                        <?php if ($p['amount_default_cents'] === null): ?>
                            <span class="text-xs text-slate-500">montant libre</span>
                        <?php else: ?>
                            <?= e(number_format(((int)$p['amount_default_cents']) / 100, 2, ',', ' ')) ?> €
                        <?php endif; ?>
                    </td>
                    <td class="p-3"><?= e((string)$p['period_months']) ?> mois</td>
                    <td class="p-3"><?= ((int)$p['is_active'] === 1) ? 'oui' : 'non' ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
