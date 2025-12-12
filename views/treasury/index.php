<?php
$title = 'Trésorerie';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Trésorerie</h1>
    <div class="flex items-center gap-2">
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/treasury/categories">Catégories</a>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/treasury/export.csv">Export CSV</a>
        <a class="bg-slate-900 text-white rounded px-3 py-2 text-sm" href="/treasury/new">Nouvelle transaction</a>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="mt-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm border border-emerald-200">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Date</th>
            <th class="text-left p-3">Libellé</th>
            <th class="text-left p-3">Catégorie</th>
            <th class="text-left p-3">Type</th>
            <th class="text-right p-3">Montant</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <tr class="border-t border-slate-100">
                <td class="p-3"><?= e((string)$t['occurred_on']) ?></td>
                <td class="p-3"><?= e((string)$t['label']) ?></td>
                <td class="p-3 text-slate-600"><?= e((string)($t['category_name'] ?? '')) ?></td>
                <td class="p-3"><?= e((string)$t['type']) ?></td>
                <td class="p-3 text-right"><?= number_format(((int)$t['amount_cents']) / 100, 2, ',', ' ') ?> €</td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($transactions)): ?>
            <tr class="border-t border-slate-100">
                <td class="p-3 text-slate-500" colspan="5">Aucune transaction.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
