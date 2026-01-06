<?php
$title = 'Trésorerie';
$layoutMaxWidth = 'max-w-7xl';
ob_start();
?>
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Trésorerie</h1>
        <div class="text-xs text-slate-500">Saisie, justificatifs, pointage et rapprochement</div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/treasury/reconcile')) ?>">À rapprocher<?= isset($unreconciledCount) ? ' (' . (int)$unreconciledCount . ')' : '' ?></a>
        <?php if (isset($unallocatedCount)): ?>
            <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/treasury/analytics')) ?>">Analytique<?= (int)$unallocatedCount > 0 ? ' (' . (int)$unallocatedCount . ')' : '' ?></a>
        <?php endif; ?>
        <details class="relative z-50">
            <summary class="list-none border border-slate-300 rounded px-3 py-2 text-sm cursor-pointer select-none bg-white hover:bg-slate-50">
                Actions
            </summary>
            <div class="absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-lg shadow-lg overflow-hidden z-50">
                <a class="block px-4 py-2 text-sm hover:bg-slate-50" href="<?= e(tenant_path('/treasury/categories')) ?>">Catégories</a>
                <?php if (!empty($budgetsEnabled)): ?>
                    <a class="block px-4 py-2 text-sm hover:bg-slate-50" href="<?= e(tenant_path('/treasury/budgets')) ?>">Budgets</a>
                <?php endif; ?>
                <a class="block px-4 py-2 text-sm hover:bg-slate-50" href="<?= e(tenant_path('/treasury/export.csv')) ?>">Export CSV</a>
                <a class="block px-4 py-2 text-sm hover:bg-slate-50" href="<?= e(tenant_path('/treasury/export.zip')) ?>">Export ZIP (cabinet)</a>
                <?php if (!empty($_SESSION['is_admin'])): ?>
                    <a class="block px-4 py-2 text-sm hover:bg-slate-50" href="<?= e(tenant_path('/treasury/closures')) ?>">Clôtures</a>
                <?php endif; ?>
            </div>
        </details>
        <a class="bg-slate-900 text-white rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/treasury/new')) ?>">Nouvelle transaction</a>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="mt-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm border border-emerald-200">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['is_admin']) && !empty($closureSuggestions) && is_array($closureSuggestions)): ?>
    <div class="mt-4 p-4 rounded-lg bg-amber-50 border border-amber-200">
        <div class="text-sm font-medium text-amber-900">Proposition de clôture</div>
        <div class="mt-1 text-xs text-amber-800">Périodes révolues non encore clôturées. Tu peux pré-remplir la clôture en un clic.</div>
        <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($closureSuggestions as $s): ?>
                <?php
                if (!is_array($s)) {
                    continue;
                }
                $label = (string)($s['label'] ?? 'Clôturer');
                $start = (string)($s['start'] ?? '');
                $end = (string)($s['end'] ?? '');
                if ($start === '' || $end === '') {
                    continue;
                }
                $href = tenant_path('/treasury/closures?start_date=' . urlencode($start) . '&end_date=' . urlencode($end));
                ?>
                <a class="border border-amber-300 bg-white rounded px-3 py-2 text-sm hover:bg-amber-100" href="<?= e($href) ?>">
                    <?= e($label) ?> (<?= e($start) ?> → <?= e($end) ?>)
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php
$activePeriod = (string)($_GET['period'] ?? 'month');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$q = (string)($_GET['q'] ?? '');
$type = (string)($_GET['type'] ?? '');
$categoryId = (string)($_GET['category_id'] ?? '');
$cleared = (string)($_GET['cleared'] ?? '');
$btnBase = 'border border-slate-300 rounded px-3 py-2 text-sm';
$btnActive = 'bg-slate-900 text-white border-slate-900';
$btnInactive = 'bg-white text-slate-900 hover:bg-slate-50';
?>

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4 lg:sticky lg:top-3 lg:z-20">
    <div class="hidden lg:flex flex-col gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <a class="<?= e($btnBase . ' ' . ($activePeriod === 'month' ? $btnActive : $btnInactive)) ?>" href="<?= e(tenant_path('/treasury?period=month')) ?>">Mois</a>
            <a class="<?= e($btnBase . ' ' . ($activePeriod === 'prev_month' ? $btnActive : $btnInactive)) ?>" href="<?= e(tenant_path('/treasury?period=prev_month')) ?>">Mois -1</a>
            <a class="<?= e($btnBase . ' ' . ($activePeriod === 'year' ? $btnActive : $btnInactive)) ?>" href="<?= e(tenant_path('/treasury?period=year')) ?>">Année</a>
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
                <label class="block text-xs text-slate-600">Pointage</label>
                <select name="cleared" class="border border-slate-300 rounded px-2 py-2 text-sm">
                    <option value="" <?= ($cleared === '' ? 'selected' : '') ?>>Tout</option>
                    <option value="0" <?= ($cleared === '0' ? 'selected' : '') ?>>Non rapprochées</option>
                    <option value="1" <?= ($cleared === '1' ? 'selected' : '') ?>>Rapprochées</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-600">Recherche</label>
                <input name="q" value="<?= e($q) ?>" class="border border-slate-300 rounded px-2 py-2 text-sm" placeholder="libellé">
            </div>
            <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">OK</button>
        </form>
    </div>

    <div class="lg:hidden">
        <details class="group">
            <summary class="cursor-pointer select-none flex items-center justify-between">
                <div class="text-sm font-medium">Filtres</div>
                <div class="text-xs text-slate-500 group-open:hidden">Afficher</div>
                <div class="text-xs text-slate-500 hidden group-open:block">Masquer</div>
            </summary>

            <div class="mt-3 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <a class="<?= e($btnBase . ' ' . ($activePeriod === 'month' ? $btnActive : $btnInactive)) ?>" href="<?= e(tenant_path('/treasury?period=month')) ?>">Mois</a>
                    <a class="<?= e($btnBase . ' ' . ($activePeriod === 'prev_month' ? $btnActive : $btnInactive)) ?>" href="<?= e(tenant_path('/treasury?period=prev_month')) ?>">Mois -1</a>
                    <a class="<?= e($btnBase . ' ' . ($activePeriod === 'year' ? $btnActive : $btnInactive)) ?>" href="<?= e(tenant_path('/treasury?period=year')) ?>">Année</a>
                </div>

                <form method="get" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input type="hidden" name="period" value="custom">
                    <div>
                        <label class="block text-xs text-slate-600">Du</label>
                        <input name="from" type="date" value="<?= e($from) ?>" class="w-full border border-slate-300 rounded px-2 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600">Au</label>
                        <input name="to" type="date" value="<?= e($to) ?>" class="w-full border border-slate-300 rounded px-2 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600">Type</label>
                        <select name="type" class="w-full border border-slate-300 rounded px-2 py-2 text-sm">
                            <option value="" <?= ($type === '' ? 'selected' : '') ?>>Tout</option>
                            <option value="expense" <?= ($type === 'expense' ? 'selected' : '') ?>>Dépenses</option>
                            <option value="income" <?= ($type === 'income' ? 'selected' : '') ?>>Recettes</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600">Pointage</label>
                        <select name="cleared" class="w-full border border-slate-300 rounded px-2 py-2 text-sm">
                            <option value="" <?= ($cleared === '' ? 'selected' : '') ?>>Tout</option>
                            <option value="0" <?= ($cleared === '0' ? 'selected' : '') ?>>Non rapprochées</option>
                            <option value="1" <?= ($cleared === '1' ? 'selected' : '') ?>>Rapprochées</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-slate-600">Catégorie</label>
                        <select name="category_id" class="w-full border border-slate-300 rounded px-2 py-2 text-sm">
                            <option value="" <?= ($categoryId === '' ? 'selected' : '') ?>>Toutes</option>
                            <?php foreach (($categories ?? []) as $c): ?>
                                <option value="<?= e((string)$c['id']) ?>" <?= ((string)$c['id'] === $categoryId ? 'selected' : '') ?>><?= e((string)$c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-slate-600">Recherche</label>
                        <input name="q" value="<?= e($q) ?>" class="w-full border border-slate-300 rounded px-2 py-2 text-sm" placeholder="libellé">
                    </div>
                    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Appliquer</button>
                </form>
            </div>
        </details>
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

<div class="mt-4 grid grid-cols-1 gap-3 lg:hidden">
    <?php if (empty($transactions)): ?>
        <div class="bg-white border border-slate-200 rounded-lg p-4 text-slate-500 text-sm">Aucune transaction.</div>
    <?php endif; ?>

    <?php foreach ($transactions as $t): ?>
        <?php
        $isIncome = ((string)($t['type'] ?? '') === 'income');
        $amount = (int)($t['amount_cents'] ?? 0);
        $amtLabel = number_format($amount / 100, 2, ',', ' ') . ' €';
        $isCleared = ((int)($t['is_cleared'] ?? 0) === 1);
        $isClosed = ((int)($t['is_closed'] ?? 0) === 1);
        $isAllocated = ((int)($t['is_allocated'] ?? 0) === 1);
        ?>
        <div class="bg-white border border-slate-200 rounded-lg p-4 <?= $isClosed ? 'opacity-70' : '' ?>">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs text-slate-500 flex items-center gap-2">
                        <span><?= e(date_fr((string)($t['occurred_on'] ?? ''))) ?></span>
                        <?php if ($isClosed): ?>
                            <span class="inline-flex items-center gap-1 text-amber-700" title="Clôturée">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                                <span class="text-xs">Clôturée</span>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($unallocatedCount) && $isAllocated): ?>
                            <span class="inline-flex items-center gap-1 text-emerald-700" title="Ventilée">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 6 9 17l-5-5"/>
                                </svg>
                                <span class="text-xs">Ventilée</span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-1 font-medium break-words"><?= e((string)($t['label'] ?? '')) ?></div>
                    <?php if ((string)($t['category_name'] ?? '') !== ''): ?>
                        <div class="mt-1 text-xs text-slate-600"><?= e((string)($t['category_name'] ?? '')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="text-right shrink-0">
                    <div class="font-mono text-sm <?= $isIncome ? 'text-emerald-700' : 'text-red-700' ?>">
                        <?= e(($isIncome ? '+' : '-') . ' ' . $amtLabel) ?>
                    </div>
                    <div class="mt-1 text-xs <?= $isCleared ? 'text-emerald-700' : 'text-slate-500' ?>">
                        <?= $isCleared ? 'Rapprochée' : 'Non rapprochée' ?>
                    </div>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2">
                <?php if ($isClosed): ?>
                    <span class="border border-slate-200 rounded px-3 py-2 text-sm text-center text-slate-400 cursor-not-allowed" title="Période clôturée">Modifier</span>
                <?php else: ?>
                    <a class="border border-slate-300 rounded px-3 py-2 text-sm text-center" href="<?= e(tenant_path('/treasury/edit?id=' . (string)$t['id'])) ?>">Modifier</a>
                <?php endif; ?>
                <a class="border border-slate-300 rounded px-3 py-2 text-sm text-center" href="<?= e(tenant_path('/treasury/new?duplicate_id=' . (string)$t['id'])) ?>">Dupliquer</a>
                <a class="border border-slate-300 rounded px-3 py-2 text-sm text-center col-span-2" href="<?= e(tenant_path('/treasury/attachments?transaction_id=' . (string)$t['id'])) ?>">Justificatifs</a>
                <form method="post" action="<?= e(tenant_path('/treasury/toggle-cleared')) ?>" class="col-span-2">
                    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                    <input type="hidden" name="id" value="<?= e((string)$t['id']) ?>">
                    <input type="hidden" name="return_to" value="<?= e((string)($_SERVER['REQUEST_URI'] ?? tenant_path('/treasury'))) ?>">
                    <button class="w-full border border-slate-300 rounded px-3 py-2 text-sm <?= $isClosed ? 'opacity-50 cursor-not-allowed' : '' ?>" type="submit" <?= $isClosed ? 'disabled' : '' ?>>
                        <?= $isCleared ? 'Dépointer' : 'Pointer' ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden hidden lg:block">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 sticky top-0 z-10">
        <tr>
            <th class="text-left p-3 whitespace-nowrap">Date</th>
            <th class="text-left p-3">Libellé</th>
            <th class="text-left p-3">Catégorie</th>
            <th class="text-right p-3">Dépenses</th>
            <th class="text-right p-3">Recettes</th>
            <th class="text-center p-3">R</th>
            <th class="text-right p-3">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <?php $isClosed = ((int)($t['is_closed'] ?? 0) === 1); ?>
            <?php $isAllocated = ((int)($t['is_allocated'] ?? 0) === 1); ?>
            <tr class="border-t border-slate-100 odd:bg-white even:bg-slate-50 <?= $isClosed ? 'opacity-70' : 'hover:bg-slate-100' ?>">
                <td class="p-3 whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        <span><?= e(date_fr((string)$t['occurred_on'])) ?></span>
                        <?php if ($isClosed): ?>
                            <span class="text-amber-700" title="Clôturée">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($unallocatedCount) && $isAllocated): ?>
                            <span class="text-emerald-700" title="Ventilée">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 6 9 17l-5-5"/>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="p-3"><?= e((string)$t['label']) ?></td>
                <td class="p-3 text-slate-600"><?= e((string)($t['category_name'] ?? '')) ?></td>
                <td class="p-3 text-right text-red-700 whitespace-nowrap">
                    <?php if ((string)$t['type'] === 'expense'): ?>
                        <?= number_format(((int)$t['amount_cents']) / 100, 2, ',', ' ') ?> €
                    <?php endif; ?>
                </td>
                <td class="p-3 text-right text-emerald-700 whitespace-nowrap">
                    <?php if ((string)$t['type'] === 'income'): ?>
                        <?= number_format(((int)$t['amount_cents']) / 100, 2, ',', ' ') ?> €
                    <?php endif; ?>
                </td>
                <td class="p-3 text-center">
                    <?php if ((int)($t['is_cleared'] ?? 0) === 1): ?>
                        <span class="text-emerald-700 font-semibold">✓</span>
                    <?php else: ?>
                        <span class="text-slate-400">—</span>
                    <?php endif; ?>
                </td>
                <td class="p-3 text-right">
                    <div class="inline-flex items-center gap-2">
                        <?php if ($isClosed): ?>
                            <span class="border border-slate-200 rounded p-2 text-xs text-slate-400 cursor-not-allowed" title="Période clôturée">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 20h9"/>
                                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                                </svg>
                            </span>
                        <?php else: ?>
                            <a class="border border-slate-300 rounded p-2 text-xs hover:bg-slate-50" href="<?= e(tenant_path('/treasury/edit?id=' . (string)$t['id'])) ?>" title="Modifier">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 20h9"/>
                                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                                </svg>
                            </a>
                        <?php endif; ?>

                        <a class="border border-slate-300 rounded p-2 text-xs hover:bg-slate-50" href="<?= e(tenant_path('/treasury/attachments?transaction_id=' . (string)$t['id'])) ?>" title="Justificatifs">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.82-2.82l8.49-8.48"/>
                            </svg>
                        </a>

                        <a class="border border-slate-300 rounded p-2 text-xs hover:bg-slate-50" href="<?= e(tenant_path('/treasury/new?duplicate_id=' . (string)$t['id'])) ?>" title="Dupliquer">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                        </a>

                        <form method="post" action="<?= e(tenant_path('/treasury/toggle-cleared')) ?>" class="inline">
                            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                            <input type="hidden" name="id" value="<?= e((string)$t['id']) ?>">
                            <input type="hidden" name="return_to" value="<?= e((string)($_SERVER['REQUEST_URI'] ?? tenant_path('/treasury'))) ?>">
                            <button class="border border-slate-300 rounded p-2 text-xs hover:bg-slate-50 <?= $isClosed ? 'opacity-50 cursor-not-allowed' : '' ?>" type="submit" title="<?= ((int)($t['is_cleared'] ?? 0) === 1) ? 'Dépointée' : 'Pointée' ?>" <?= $isClosed ? 'disabled' : '' ?>>
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
                <td class="p-3 text-slate-500" colspan="7">Aucune transaction.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
