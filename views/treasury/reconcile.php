<?php
$title = 'Rapprochement bancaire';
$layoutMaxWidth = 'max-w-7xl';
ob_start();

$q = (string)($_GET['q'] ?? '');
$type = (string)($_GET['type'] ?? '');

$fmtCents = static function (int $cents): string {
    return number_format($cents / 100, 2, ',', ' ') . ' €';
};
?>
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">À rapprocher</h1>
        <div class="text-xs text-slate-500">Transactions non rapprochées</div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/treasury')) ?>">Retour</a>
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

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4 lg:sticky lg:top-3 lg:z-20">
    <div class="hidden lg:block">
        <form method="get" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs text-slate-600">Type</label>
                <select name="type" class="border border-slate-300 rounded px-2 py-2 text-sm">
                    <option value="" <?= ($type === '' ? 'selected' : '') ?>>Tout</option>
                    <option value="expense" <?= ($type === 'expense' ? 'selected' : '') ?>>Dépenses</option>
                    <option value="income" <?= ($type === 'income' ? 'selected' : '') ?>>Recettes</option>
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
            <form method="get" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-600">Type</label>
                    <select name="type" class="w-full border border-slate-300 rounded px-2 py-2 text-sm">
                        <option value="" <?= ($type === '' ? 'selected' : '') ?>>Tout</option>
                        <option value="expense" <?= ($type === 'expense' ? 'selected' : '') ?>>Dépenses</option>
                        <option value="income" <?= ($type === 'income' ? 'selected' : '') ?>>Recettes</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-slate-600">Recherche</label>
                    <input name="q" value="<?= e($q) ?>" class="w-full border border-slate-300 rounded px-2 py-2 text-sm" placeholder="libellé">
                </div>
                <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Appliquer</button>
            </form>
        </details>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 gap-3 lg:hidden">
    <?php if (empty($transactions)): ?>
        <div class="bg-white border border-slate-200 rounded-lg p-4 text-slate-500 text-sm">Aucune transaction à rapprocher.</div>
    <?php endif; ?>

    <?php foreach ($transactions as $t): ?>
        <?php if (!is_array($t)) continue; ?>
        <?php
        $pm = (string)($t['payment_method'] ?? '');
        $catId = (string)($t['category_id'] ?? '');
        $formId = 'reconcile_card_' . (string)($t['id'] ?? '0');
        $sign = ((string)($t['type'] ?? '') === 'income') ? '+' : '-';
        $color = ((string)($t['type'] ?? '') === 'income') ? 'text-emerald-700' : 'text-red-700';
        $isClosed = ((int)($t['is_closed'] ?? 0) === 1);
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
                    </div>
                    <div class="mt-1 font-medium break-words"><?= e((string)($t['label'] ?? '')) ?></div>
                    <div class="mt-1 text-xs text-slate-500">#<?= e((string)($t['id'] ?? '')) ?></div>
                </div>
                <div class="text-right shrink-0">
                    <div class="font-mono text-sm <?= $color ?>"><?= e($sign . ' ' . $fmtCents((int)($t['amount_cents'] ?? 0))) ?></div>
                </div>
            </div>

            <form id="<?= e($formId) ?>" method="post" action="<?= e(tenant_path('/treasury/reconcile/update')) ?>" class="mt-3 space-y-3">
                <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= e((string)($t['id'] ?? 0)) ?>">
                <input type="hidden" name="return_to" value="<?= e((string)($_SERVER['REQUEST_URI'] ?? tenant_path('/treasury/reconcile'))) ?>">

                <div>
                    <label class="block text-xs text-slate-600">Tiers</label>
                    <input name="counterparty" value="<?= e((string)($t['counterparty'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm <?= $isClosed ? 'opacity-60 cursor-not-allowed' : '' ?>" placeholder="Ex: Banque, fournisseur" <?= $isClosed ? 'disabled' : '' ?>>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-slate-600">Moyen</label>
                        <select name="payment_method" class="w-full border border-slate-300 rounded px-3 py-2 text-sm <?= $isClosed ? 'opacity-60 cursor-not-allowed' : '' ?>" <?= $isClosed ? 'disabled' : '' ?>>
                            <option value="" <?= ($pm === '' ? 'selected' : '') ?>>—</option>
                            <option value="cash" <?= ($pm === 'cash' ? 'selected' : '') ?>>Espèces</option>
                            <option value="card" <?= ($pm === 'card' ? 'selected' : '') ?>>Carte</option>
                            <option value="transfer" <?= ($pm === 'transfer' ? 'selected' : '') ?>>Virement</option>
                            <option value="check" <?= ($pm === 'check' ? 'selected' : '') ?>>Chèque</option>
                            <option value="other" <?= ($pm === 'other' ? 'selected' : '') ?>>Autre</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600">Référence</label>
                        <input name="reference" value="<?= e((string)($t['reference'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm <?= $isClosed ? 'opacity-60 cursor-not-allowed' : '' ?>" placeholder="Référence" <?= $isClosed ? 'disabled' : '' ?>>
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-600">Catégorie</label>
                    <select name="category_id" class="w-full border border-slate-300 rounded px-3 py-2 text-sm <?= $isClosed ? 'opacity-60 cursor-not-allowed' : '' ?>" <?= $isClosed ? 'disabled' : '' ?>>
                        <option value="" <?= ($catId === '' ? 'selected' : '') ?>>—</option>
                        <?php foreach (($categories ?? []) as $c): ?>
                            <option value="<?= e((string)$c['id']) ?>" <?= ((string)$c['id'] === $catId ? 'selected' : '') ?>><?= e((string)$c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm <?= $isClosed ? 'opacity-50 cursor-not-allowed' : '' ?>" type="submit" name="is_cleared" value="1" <?= $isClosed ? 'disabled' : '' ?>>Rapprocher</button>
                    <button class="border border-slate-300 rounded px-3 py-2 text-sm <?= $isClosed ? 'opacity-50 cursor-not-allowed' : '' ?>" type="submit" name="is_cleared" value="0" <?= $isClosed ? 'disabled' : '' ?>>Enregistrer</button>
                </div>
            </form>
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
            <th class="text-left p-3">Tiers</th>
            <th class="text-left p-3">Moyen</th>
            <th class="text-left p-3">Référence</th>
            <th class="text-left p-3">Catégorie</th>
            <th class="text-right p-3 whitespace-nowrap">Montant</th>
            <th class="text-right p-3">Action</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($transactions)): ?>
            <tr class="border-t border-slate-100">
                <td class="p-3 text-slate-500" colspan="8">Aucune transaction à rapprocher.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($transactions as $t): ?>
                <?php if (!is_array($t)) continue; ?>
                <?php $isClosed = ((int)($t['is_closed'] ?? 0) === 1); ?>
                <tr class="border-t border-slate-100 align-top odd:bg-white even:bg-slate-50 <?= $isClosed ? 'opacity-70' : 'hover:bg-slate-100' ?>">
                    <td class="p-3 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <span><?= e(date_fr((string)($t['occurred_on'] ?? ''))) ?></span>
                            <?php if ($isClosed): ?>
                                <span class="text-amber-700" title="Clôturée">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="p-3">
                        <div class="font-medium"><?= e((string)($t['label'] ?? '')) ?></div>
                        <div class="text-xs text-slate-500">#<?= e((string)($t['id'] ?? '')) ?></div>
                    </td>
                    <?php
                    $pm = (string)($t['payment_method'] ?? '');
                    $catId = (string)($t['category_id'] ?? '');
                    $formId = 'reconcile_' . (string)($t['id'] ?? '0');
                    ?>
                    <td class="p-3">
                        <form id="<?= e($formId) ?>" method="post" action="<?= e(tenant_path('/treasury/reconcile/update')) ?>" class="hidden">
                            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                            <input type="hidden" name="id" value="<?= e((string)($t['id'] ?? 0)) ?>">
                            <input type="hidden" name="return_to" value="<?= e((string)($_SERVER['REQUEST_URI'] ?? tenant_path('/treasury/reconcile'))) ?>">
                        </form>
                        <input form="<?= e($formId) ?>" name="counterparty" value="<?= e((string)($t['counterparty'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-2 py-2 text-sm <?= $isClosed ? 'opacity-60 cursor-not-allowed' : '' ?>" placeholder="Ex: Banque, fournisseur" <?= $isClosed ? 'disabled' : '' ?>>
                    </td>
                    <td class="p-3">
                        <select form="<?= e($formId) ?>" name="payment_method" class="w-full border border-slate-300 rounded px-2 py-2 text-sm <?= $isClosed ? 'opacity-60 cursor-not-allowed' : '' ?>" <?= $isClosed ? 'disabled' : '' ?>>
                            <option value="" <?= ($pm === '' ? 'selected' : '') ?>>—</option>
                            <option value="cash" <?= ($pm === 'cash' ? 'selected' : '') ?>>Espèces</option>
                            <option value="card" <?= ($pm === 'card' ? 'selected' : '') ?>>Carte</option>
                            <option value="transfer" <?= ($pm === 'transfer' ? 'selected' : '') ?>>Virement</option>
                            <option value="check" <?= ($pm === 'check' ? 'selected' : '') ?>>Chèque</option>
                            <option value="other" <?= ($pm === 'other' ? 'selected' : '') ?>>Autre</option>
                        </select>
                    </td>
                    <td class="p-3">
                        <input form="<?= e($formId) ?>" name="reference" value="<?= e((string)($t['reference'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-2 py-2 text-sm <?= $isClosed ? 'opacity-60 cursor-not-allowed' : '' ?>" placeholder="Référence" <?= $isClosed ? 'disabled' : '' ?>>
                    </td>
                    <td class="p-3">
                        <select form="<?= e($formId) ?>" name="category_id" class="w-full border border-slate-300 rounded px-2 py-2 text-sm <?= $isClosed ? 'opacity-60 cursor-not-allowed' : '' ?>" <?= $isClosed ? 'disabled' : '' ?>>
                            <option value="" <?= ($catId === '' ? 'selected' : '') ?>>—</option>
                            <?php foreach (($categories ?? []) as $c): ?>
                                <option value="<?= e((string)$c['id']) ?>" <?= ((string)$c['id'] === $catId ? 'selected' : '') ?>><?= e((string)$c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="p-3 text-right whitespace-nowrap">
                        <?php
                        $sign = ((string)($t['type'] ?? '') === 'income') ? '+' : '-';
                        $color = ((string)($t['type'] ?? '') === 'income') ? 'text-emerald-700' : 'text-red-700';
                        ?>
                        <span class="font-mono <?= $color ?>"><?= e($sign . ' ' . $fmtCents((int)($t['amount_cents'] ?? 0))) ?></span>
                    </td>
                    <td class="p-3 text-right whitespace-nowrap">
                        <div class="flex flex-col items-end gap-2">
                            <button form="<?= e($formId) ?>" class="bg-slate-900 text-white rounded px-3 py-2 text-sm <?= $isClosed ? 'opacity-50 cursor-not-allowed' : '' ?>" type="submit" name="is_cleared" value="1" <?= $isClosed ? 'disabled' : '' ?>>Rapprocher</button>
                            <button form="<?= e($formId) ?>" class="border border-slate-300 rounded px-3 py-2 text-sm <?= $isClosed ? 'opacity-50 cursor-not-allowed' : '' ?>" type="submit" name="is_cleared" value="0" <?= $isClosed ? 'disabled' : '' ?>>Enregistrer sans rapprocher</button>
                        </div>
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
