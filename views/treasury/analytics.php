<?php
$title = 'Analytique (axes)';
$layoutMaxWidth = 'max-w-7xl';
ob_start();

$activePeriod = (string)($_GET['period'] ?? 'month');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$btnBase = 'border border-slate-300 rounded px-3 py-2 text-sm';
$btnActive = 'bg-slate-900 text-white border-slate-900';
$btnInactive = 'bg-white text-slate-900 hover:bg-slate-50';
?>

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Analytique (axes)</h1>
        <div class="text-xs text-slate-500">Ventilations par axes / valeurs</div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/treasury')) ?>">Retour trésorerie</a>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/treasury/analytics' . ($activePeriod === 'custom' ? ('?period=custom&from=' . urlencode($from) . '&to=' . urlencode($to)) : ('?period=' . urlencode($activePeriod))))) ?>">Rafraîchir</a>
    </div>
</div>

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
    <div class="flex flex-wrap items-center gap-2">
        <a class="<?= e($btnBase . ' ' . ($activePeriod === 'month' ? $btnActive : $btnInactive)) ?>" href="<?= e(tenant_path('/treasury/analytics?period=month')) ?>">Mois</a>
        <a class="<?= e($btnBase . ' ' . ($activePeriod === 'prev_month' ? $btnActive : $btnInactive)) ?>" href="<?= e(tenant_path('/treasury/analytics?period=prev_month')) ?>">Mois -1</a>
        <a class="<?= e($btnBase . ' ' . ($activePeriod === 'year' ? $btnActive : $btnInactive)) ?>" href="<?= e(tenant_path('/treasury/analytics?period=year')) ?>">Année</a>
    </div>

    <form method="get" class="mt-3 flex flex-wrap items-end gap-2">
        <input type="hidden" name="period" value="custom">
        <div>
            <label class="block text-xs text-slate-600">Du</label>
            <input name="from" type="date" value="<?= e($from) ?>" class="border border-slate-300 rounded px-2 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-600">Au</label>
            <input name="to" type="date" value="<?= e($to) ?>" class="border border-slate-300 rounded px-2 py-2 text-sm">
        </div>
        <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">OK</button>

        <div class="ml-auto text-sm text-slate-700">
            À ventiler : <span class="font-semibold <?= ((int)($unallocatedCount ?? 0)) > 0 ? 'text-amber-700' : 'text-emerald-700' ?>"><?= (int)($unallocatedCount ?? 0) ?></span>
        </div>
    </form>
</div>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
            <tr>
                <th class="text-left p-3">Axe</th>
                <th class="text-left p-3">Valeur</th>
                <th class="text-right p-3">Dépenses</th>
                <th class="text-right p-3">Recettes</th>
                <th class="text-right p-3">Solde</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr class="border-t border-slate-100">
                    <td class="p-3 text-slate-500" colspan="5">Aucune ventilation sur la période.</td>
                </tr>
            <?php endif; ?>

            <?php foreach (($rows ?? []) as $r): ?>
                <?php
                $expense = (int)($r['expense_cents'] ?? 0);
                $income = (int)($r['income_cents'] ?? 0);
                $balance = $income - $expense;
                ?>
                <tr class="border-t border-slate-100 odd:bg-white even:bg-slate-50">
                    <td class="p-3 font-medium"><?= e((string)($r['axis_label'] ?? '')) ?></td>
                    <td class="p-3"><?= e((string)($r['value_label'] ?? '')) ?></td>
                    <td class="p-3 text-right text-red-700 whitespace-nowrap"><?= number_format($expense / 100, 2, ',', ' ') ?> €</td>
                    <td class="p-3 text-right text-emerald-700 whitespace-nowrap"><?= number_format($income / 100, 2, ',', ' ') ?> €</td>
                    <td class="p-3 text-right whitespace-nowrap <?= $balance >= 0 ? 'text-emerald-700' : 'text-red-700' ?>"><?= number_format($balance / 100, 2, ',', ' ') ?> €</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
