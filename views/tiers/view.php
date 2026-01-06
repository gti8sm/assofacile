<?php

$title = 'Tiers';
ob_start();

$fmtCents = static function (int $cents): string {
    return number_format($cents / 100, 2, ',', ' ');
};

$tierName = (string)($tier['name'] ?? '');
$tierId = (int)($tier['id'] ?? 0);
$memberId = (int)($tier['member_id'] ?? 0);

$totalIncome = (int)($stats['total_income_cents'] ?? 0);
$totalExpense = (int)($stats['total_expense_cents'] ?? 0);
$net = (int)($stats['net_cents'] ?? 0);
$ytdIncome = (int)($stats['ytd_income_cents'] ?? 0);
$ytdExpense = (int)($stats['ytd_expense_cents'] ?? 0);
$txCount = (int)($stats['tx_count'] ?? 0);
?>
<div class="max-w-5xl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold"><?= e($tierName) ?></h1>
            <div class="text-xs text-slate-500">Tiers #<?= e((string)$tierId) ?></div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/tiers')) ?>">Retour</a>
            <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'treasury', 'write')): ?>
                <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/tiers/edit?id=' . $tierId)) ?>">Modifier</a>
            <?php endif; ?>
            <?php if ($memberId > 0): ?>
                <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/members/edit?id=' . $memberId)) ?>">Fiche adhérent</a>
            <?php endif; ?>
        </div>
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

    <div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
        <div class="text-sm font-medium">Tags</div>
        <div class="mt-2 flex flex-wrap gap-1">
            <?php if (empty($tags)): ?>
                <span class="text-xs bg-slate-100 text-slate-700 px-2 py-1 rounded">—</span>
            <?php else: ?>
                <?php foreach ($tags as $tag): ?>
                    <span class="text-xs bg-slate-100 text-slate-700 px-2 py-1 rounded"><?= e($tag) ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($member)): ?>
        <div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
            <div class="text-sm font-medium">Adhérent associé</div>
            <div class="mt-2 text-sm">
                <?= e(trim((string)($member['first_name'] ?? '') . ' ' . (string)($member['last_name'] ?? ''))) ?>
            </div>
            <div class="mt-1 text-xs text-slate-500"><?= e((string)($member['email'] ?? '')) ?><?= ($member['phone'] ?? '') !== '' ? ' · ' . e((string)$member['phone']) : '' ?></div>
        </div>
    <?php endif; ?>

    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div class="bg-white border border-slate-200 rounded-lg p-4">
            <div class="text-xs text-slate-500">Année en cours</div>
            <div class="mt-1 text-sm">Recettes : <span class="font-semibold text-emerald-700"><?= e($fmtCents($ytdIncome)) ?> €</span></div>
            <div class="mt-1 text-sm">Dépenses : <span class="font-semibold text-amber-800"><?= e($fmtCents($ytdExpense)) ?> €</span></div>
        </div>
        <div class="bg-white border border-slate-200 rounded-lg p-4">
            <div class="text-xs text-slate-500">Total</div>
            <div class="mt-1 text-sm">Recettes : <span class="font-semibold text-emerald-700"><?= e($fmtCents($totalIncome)) ?> €</span></div>
            <div class="mt-1 text-sm">Dépenses : <span class="font-semibold text-amber-800"><?= e($fmtCents($totalExpense)) ?> €</span></div>
        </div>
        <div class="bg-white border border-slate-200 rounded-lg p-4">
            <div class="text-xs text-slate-500">Solde net</div>
            <div class="mt-1 text-lg font-semibold <?= $net >= 0 ? 'text-emerald-700' : 'text-amber-800' ?>"><?= e($fmtCents($net)) ?> €</div>
            <div class="mt-1 text-xs text-slate-500"><?= e((string)$txCount) ?> transactions</div>
        </div>
    </div>

    <div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div class="p-4 border-b border-slate-200">
            <div class="text-sm font-medium">Dernières transactions</div>
            <div class="text-xs text-slate-500">Liées à ce tiers (25 dernières)</div>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
            <tr>
                <th class="text-left p-3">Date</th>
                <th class="text-left p-3">Libellé</th>
                <th class="text-left p-3">Catégorie</th>
                <th class="text-right p-3">Montant</th>
                <th class="text-right p-3">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($transactions)): ?>
                <tr>
                    <td class="p-3 text-slate-500" colspan="5">Aucune transaction pour ce tiers.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($transactions as $tx): ?>
                    <?php
                    $txId = (int)($tx['id'] ?? 0);
                    $amount = (int)($tx['amount_cents'] ?? 0);
                    $type = (string)($tx['type'] ?? 'expense');
                    $signed = $type === 'income' ? $amount : -$amount;
                    ?>
                    <tr class="border-t border-slate-200">
                        <td class="p-3"><?= e((string)($tx['occurred_on'] ?? '')) ?></td>
                        <td class="p-3">
                            <div class="font-medium"><?= e((string)($tx['label'] ?? '')) ?></div>
                            <div class="text-xs text-slate-500">#<?= e((string)$txId) ?></div>
                        </td>
                        <td class="p-3"><?= e((string)($tx['category_name'] ?? '')) ?></td>
                        <td class="p-3 text-right">
                            <span class="font-semibold <?= $signed >= 0 ? 'text-emerald-700' : 'text-amber-800' ?>"><?= e($fmtCents($signed)) ?> €</span>
                        </td>
                        <td class="p-3 text-right">
                            <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/treasury/edit?id=' . $txId)) ?>">Ouvrir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
