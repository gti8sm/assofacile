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

<?php
$activePeriod = (string)($_GET['period'] ?? 'month');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$q = (string)($_GET['q'] ?? '');
$type = (string)($_GET['type'] ?? '');
$categoryId = (string)($_GET['category_id'] ?? '');
$btnBase = 'border border-slate-300 rounded px-3 py-2 text-sm';
$btnActive = 'bg-slate-900 text-white border-slate-900';
$btnInactive = 'bg-white text-slate-900 hover:bg-slate-50';
?>

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <a class="<?= e($btnBase . ' ' . ($activePeriod === 'month' ? $btnActive : $btnInactive)) ?>" href="/treasury?period=month">Mois</a>
            <a class="<?= e($btnBase . ' ' . ($activePeriod === 'prev_month' ? $btnActive : $btnInactive)) ?>" href="/treasury?period=prev_month">Mois -1</a>
            <a class="<?= e($btnBase . ' ' . ($activePeriod === 'year' ? $btnActive : $btnInactive)) ?>" href="/treasury?period=year">Année</a>
        </div>

        <form method="get" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="period" value="custom">
            <div>
                <label class="block text-xs text-slate-600">Du</label>
                <input name="from" type="date" value="<?= e($from) ?>" class="border border-slate-300 rounded px-2 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-600">Au</label>
                <input name="to" type="date" value="<?= e($to) ?>" class="border border-slate-300 rounded px-2 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-600">Type</label>
                <select name="type" class="border border-slate-300 rounded px-2 py-2 text-sm">
                    <option value="" <?= ($type === '' ? 'selected' : '') ?>>Tout</option>
                    <option value="expense" <?= ($type === 'expense' ? 'selected' : '') ?>>Dépenses</option>
                    <option value="income" <?= ($type === 'income' ? 'selected' : '') ?>>Recettes</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600">Catégorie</label>
                <select name="category_id" class="border border-slate-300 rounded px-2 py-2 text-sm">
                    <option value="" <?= ($categoryId === '' ? 'selected' : '') ?>>Toutes</option>
                    <?php foreach (($categories ?? []) as $c): ?>
                        <option value="<?= e((string)$c['id']) ?>" <?= ((string)$c['id'] === $categoryId ? 'selected' : '') ?>><?= e((string)$c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600">Recherche</label>
                <input name="q" value="<?= e($q) ?>" class="border border-slate-300 rounded px-2 py-2 text-sm" placeholder="libellé">
            </div>
            <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">OK</button>
        </form>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <div class="text-xs text-slate-500">Dépenses (100 dernières)</div>
        <div class="mt-1 text-lg font-semibold text-red-700"><?= number_format(((int)($totalExpenseCents ?? 0)) / 100, 2, ',', ' ') ?> €</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <div class="text-xs text-slate-500">Recettes (100 dernières)</div>
        <div class="mt-1 text-lg font-semibold text-emerald-700"><?= number_format(((int)($totalIncomeCents ?? 0)) / 100, 2, ',', ' ') ?> €</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <div class="text-xs text-slate-500">Solde (100 dernières)</div>
        <div class="mt-1 text-lg font-semibold text-slate-900"><?= number_format(((int)($balanceCents ?? 0)) / 100, 2, ',', ' ') ?> €</div>
    </div>
</div>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Date</th>
            <th class="text-left p-3">Libellé</th>
            <th class="text-left p-3">Catégorie</th>
            <th class="text-right p-3">Dépenses</th>
            <th class="text-right p-3">Recettes</th>
            <th class="text-right p-3">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <tr class="border-t border-slate-100">
                <td class="p-3"><?= e(date_fr((string)$t['occurred_on'])) ?></td>
                <td class="p-3"><?= e((string)$t['label']) ?></td>
                <td class="p-3 text-slate-600"><?= e((string)($t['category_name'] ?? '')) ?></td>
                <td class="p-3 text-right text-red-700">
                    <?php if ((string)$t['type'] === 'expense'): ?>
                        <?= number_format(((int)$t['amount_cents']) / 100, 2, ',', ' ') ?> €
                    <?php endif; ?>
                </td>
                <td class="p-3 text-right text-emerald-700">
                    <?php if ((string)$t['type'] === 'income'): ?>
                        <?= number_format(((int)$t['amount_cents']) / 100, 2, ',', ' ') ?> €
                    <?php endif; ?>
                </td>
                <td class="p-3 text-right">
                    <div class="inline-flex items-center gap-2">
                        <a class="border border-slate-300 rounded p-2 text-xs hover:bg-slate-50" href="/treasury/attachments?transaction_id=<?= e((string)$t['id']) ?>" title="Justificatifs">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.82-2.82l8.49-8.48"/>
                            </svg>
                        </a>

                        <a class="border border-slate-300 rounded p-2 text-xs hover:bg-slate-50" href="/treasury/new?duplicate_id=<?= e((string)$t['id']) ?>" title="Dupliquer">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                        </a>

                        <form method="post" action="/treasury/toggle-cleared" class="inline">
                            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                            <input type="hidden" name="id" value="<?= e((string)$t['id']) ?>">
                            <input type="hidden" name="return_to" value="<?= e((string)($_SERVER['REQUEST_URI'] ?? '/treasury')) ?>">
                            <button class="border border-slate-300 rounded p-2 text-xs hover:bg-slate-50" type="submit" title="<?= ((int)($t['is_cleared'] ?? 0) === 1) ? 'Dépointée' : 'Pointée' ?>">
                                <?php if ((int)($t['is_cleared'] ?? 0) === 1): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-700">
                                        <path d="M20 6 9 17l-5-5"/>
                                    </svg>
                                <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-500">
                                        <circle cx="12" cy="12" r="10"/>
                                    </svg>
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($transactions)): ?>
            <tr class="border-t border-slate-100">
                <td class="p-3 text-slate-500" colspan="6">Aucune transaction.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
