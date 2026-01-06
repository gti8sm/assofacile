<?php
$title = 'Modifier transaction';
ob_start();

$fmtCents = static function (int $cents): string {
    return number_format($cents / 100, 2, ',', ' ');
};

$isCleared = ((int)($t['is_cleared'] ?? 0) === 1);
$isClosed = ((int)($t['is_closed'] ?? 0) === 1);
$isReadOnly = $isCleared || $isClosed;

$vType = (string)($_POST['type'] ?? (string)($t['type'] ?? 'expense'));
$vLabel = (string)($_POST['label'] ?? (string)($t['label'] ?? ''));
$vAmount = (string)($_POST['amount'] ?? $fmtCents((int)($t['amount_cents'] ?? 0)));
$vOccurredOn = (string)($_POST['occurred_on'] ?? (string)($t['occurred_on'] ?? date('Y-m-d')));
$vCategoryId = (string)($_POST['category_id'] ?? (string)($t['category_id'] ?? ''));
$vPaymentMethod = (string)($_POST['payment_method'] ?? (string)($t['payment_method'] ?? ''));
$vTierId = (string)($_POST['tier_id'] ?? (string)($t['tier_id'] ?? ''));
$vCounterparty = (string)($_POST['counterparty'] ?? (string)($t['tier_name'] ?? (string)($t['counterparty'] ?? '')));
$vReference = (string)($_POST['reference'] ?? (string)($t['reference'] ?? ''));

$analyticsEnabled = !empty($analyticsEnabled);
$allocations = is_array($allocations ?? null) ? $allocations : [];
$allocatedCents = (int)($allocatedCents ?? 0);
$txAmountCents = (int)($t['amount_cents'] ?? 0);

$budgetsEnabled = !empty($budgetsEnabled);
$budgets = is_array($budgets ?? null) ? $budgets : [];
$budgetAllocations = is_array($budgetAllocations ?? null) ? $budgetAllocations : [];
$budgetAllocatedCents = (int)($budgetAllocatedCents ?? 0);
?>

<div class="max-w-2xl">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Modifier transaction</h1>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/treasury')) ?>">Retour</a>
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

    <?php if ($isCleared): ?>
        <div class="mt-4 p-3 rounded bg-slate-50 text-slate-700 text-sm border border-slate-200">
            Transaction rapprochée : type / montant / date sont verrouillés.
        </div>
    <?php endif; ?>

    <?php if ($isClosed): ?>
        <div class="mt-4 p-3 rounded bg-amber-50 text-amber-800 text-sm border border-amber-200">
            Période clôturée : modification interdite.
        </div>
    <?php endif; ?>

    <form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-6" action="<?= e(tenant_path('/treasury/edit')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= e((string)($t['id'] ?? '')) ?>">
        <input type="hidden" name="return_to" value="<?= e((string)($_SERVER['REQUEST_URI'] ?? tenant_path('/treasury/edit?id=' . (string)($t['id'] ?? '')))) ?>">
        <input type="hidden" name="tier_id" id="tier_id" value="<?= e($vTierId) ?>">

        <div class="flex items-center justify-end gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2 <?= $isReadOnly ? 'opacity-50 cursor-not-allowed' : '' ?>" type="submit" <?= $isReadOnly ? 'disabled' : '' ?>>Enregistrer</button>
            <a class="border border-slate-300 rounded px-4 py-2" href="<?= e(tenant_path('/treasury')) ?>">Annuler</a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Type</label>
                <select name="type" class="w-full border border-slate-300 rounded px-3 py-2" <?= $isReadOnly ? 'disabled' : '' ?>>
                    <option value="expense" <?= ($vType === 'expense' ? 'selected' : '') ?>>Dépense</option>
                    <option value="income" <?= ($vType === 'income' ? 'selected' : '') ?>>Recette</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Date</label>
                <input name="occurred_on" type="date" value="<?= e($vOccurredOn) ?>" class="w-full border border-slate-300 rounded px-3 py-2" <?= $isReadOnly ? 'disabled' : '' ?> required>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Libellé</label>
            <input name="label" value="<?= e($vLabel) ?>" class="w-full border border-slate-300 rounded px-3 py-2" <?= $isReadOnly ? 'disabled' : '' ?> required>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Montant (€)</label>
                <input name="amount" value="<?= e($vAmount) ?>" inputmode="decimal" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="12,50" <?= $isReadOnly ? 'disabled' : '' ?> required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Moyen de paiement</label>
                <select name="payment_method" class="w-full border border-slate-300 rounded px-3 py-2" <?= $isReadOnly ? 'disabled' : '' ?>>
                    <option value="" <?= ($vPaymentMethod === '' ? 'selected' : '') ?>>—</option>
                    <option value="cash" <?= ($vPaymentMethod === 'cash' ? 'selected' : '') ?>>Espèces</option>
                    <option value="card" <?= ($vPaymentMethod === 'card' ? 'selected' : '') ?>>Carte</option>
                    <option value="transfer" <?= ($vPaymentMethod === 'transfer' ? 'selected' : '') ?>>Virement</option>
                    <option value="check" <?= ($vPaymentMethod === 'check' ? 'selected' : '') ?>>Chèque</option>
                    <option value="other" <?= ($vPaymentMethod === 'other' ? 'selected' : '') ?>>Autre</option>
                </select>
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label class="block text-sm font-medium mb-1">Catégorie</label>
                <a class="text-xs text-slate-600 hover:text-slate-900" href="<?= e(tenant_path('/treasury/categories')) ?>">Gérer</a>
            </div>
            <select name="category_id" class="w-full border border-slate-300 rounded px-3 py-2" <?= $isReadOnly ? 'disabled' : '' ?>>
                <option value="">—</option>
                <?php foreach (($categories ?? []) as $c): ?>
                    <option value="<?= e((string)$c['id']) ?>" <?= ((string)$c['id'] === $vCategoryId ? 'selected' : '') ?>><?= e((string)$c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <details class="border border-slate-200 rounded-lg p-3">
            <summary class="cursor-pointer select-none text-sm font-medium">Options (tiers, référence)</summary>
            <div class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Tiers</label>
                    <div class="relative">
                        <input name="counterparty" id="tier_input" value="<?= e($vCounterparty) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Ex: Supermarché, Mairie, ..." autocomplete="off" <?= $isReadOnly ? 'disabled' : '' ?>>
                        <div id="tier_suggestions" class="hidden absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded shadow-sm overflow-hidden"></div>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">Sélectionne un tiers existant (autocomplete) ou saisis librement : un tiers sera créé automatiquement.</div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Référence</label>
                    <input name="reference" value="<?= e($vReference) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Ex: chèque n°, ref virement" <?= $isReadOnly ? 'disabled' : '' ?>>
                </div>
            </div>
        </details>

        <?php if ($analyticsEnabled): ?>
            <details class="border border-slate-200 rounded-lg p-3">
                <summary class="cursor-pointer select-none text-sm font-medium">Analytique (axes) — Ventilé : <span class="font-semibold <?= $allocatedCents === $txAmountCents ? 'text-emerald-700' : 'text-amber-700' ?>"><?= e($fmtCents($allocatedCents)) ?> €</span> / <?= e($fmtCents($txAmountCents)) ?> €</summary>
                <div class="mt-2 text-xs text-slate-500">Multi-axes. Saisie en % ou en € (si les deux sont remplis, le % est prioritaire).</div>

                <div class="mt-3 space-y-2">
                    <?php
                    $rows = $allocations;
                    $extra = 3;
                    for ($i = 0; $i < $extra; $i++) {
                        $rows[] = ['axis_label' => '', 'value_label' => '', 'amount_cents' => 0];
                    }
                    ?>

                    <?php foreach ($rows as $a): ?>
                        <?php
                        $axisLabel = (string)($a['axis_label'] ?? '');
                        $valueLabel = (string)($a['value_label'] ?? '');
                        $amount = (int)($a['amount_cents'] ?? 0);
                        $amountEur = $amount > 0 ? number_format($amount / 100, 2, ',', '') : '';
                        ?>
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                            <div class="sm:col-span-4">
                                <label class="block text-xs text-slate-600">Axe</label>
                                <input name="alloc_axis[]" value="<?= e($axisLabel) ?>" class="w-full border border-slate-300 rounded px-2 py-2 text-sm" placeholder="Ex: Projet, Financeur, Pôle" <?= $isReadOnly ? 'disabled' : '' ?>>
                            </div>
                            <div class="sm:col-span-4">
                                <label class="block text-xs text-slate-600">Valeur</label>
                                <input name="alloc_value[]" value="<?= e($valueLabel) ?>" class="w-full border border-slate-300 rounded px-2 py-2 text-sm" placeholder="Ex: Atelier cuisine, CAF" <?= $isReadOnly ? 'disabled' : '' ?>>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-slate-600">%</label>
                                <input name="alloc_percent[]" inputmode="decimal" class="w-full border border-slate-300 rounded px-2 py-2 text-sm" placeholder="Ex: 50" <?= $isReadOnly ? 'disabled' : '' ?>>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-slate-600">Montant (€)</label>
                                <input name="alloc_amount[]" value="<?= e($amountEur) ?>" inputmode="decimal" class="w-full border border-slate-300 rounded px-2 py-2 text-sm" placeholder="Ex: 12,50" <?= $isReadOnly ? 'disabled' : '' ?>>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="text-xs text-slate-500">
                        Astuce : tu peux saisir uniquement l'axe/valeur + un % (pour ventiler automatiquement) ou un montant.
                    </div>
                </div>
            </details>
        <?php endif; ?>

        <?php if ($budgetsEnabled): ?>
            <details class="border border-slate-200 rounded-lg p-3">
                <summary class="cursor-pointer select-none text-sm font-medium">Budgets (enveloppes) — Ventilé : <span class="font-semibold <?= $budgetAllocatedCents === $txAmountCents ? 'text-emerald-700' : 'text-amber-700' ?>"><?= e($fmtCents($budgetAllocatedCents)) ?> €</span> / <?= e($fmtCents($txAmountCents)) ?> €</summary>
                <div class="mt-2 text-xs text-slate-500">Saisie en % ou en € (si les deux sont remplis, le % est prioritaire).</div>

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <a class="text-xs text-slate-600 hover:text-slate-900 underline" href="<?= e(tenant_path('/treasury/budgets')) ?>">Gérer les budgets</a>
                </div>

                <div class="mt-3 space-y-2">
                    <?php
                    $rows = $budgetAllocations;
                    $extra = 3;
                    for ($i = 0; $i < $extra; $i++) {
                        $rows[] = ['budget_id' => 0, 'budget_name' => '', 'amount_cents' => 0];
                    }
                    ?>

                    <?php foreach ($rows as $a): ?>
                        <?php
                        $budgetId = (int)($a['budget_id'] ?? 0);
                        $amount = (int)($a['amount_cents'] ?? 0);
                        $amountEur = $amount > 0 ? number_format($amount / 100, 2, ',', '') : '';
                        ?>
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                            <div class="sm:col-span-6">
                                <label class="block text-xs text-slate-600">Budget</label>
                                <select name="budget_id[]" class="w-full border border-slate-300 rounded px-2 py-2 text-sm" <?= $isReadOnly ? 'disabled' : '' ?>>
                                    <option value="">—</option>
                                    <?php foreach ($budgets as $b): ?>
                                        <?php
                                        $bid = (int)($b['id'] ?? 0);
                                        if ($bid <= 0) {
                                            continue;
                                        }
                                        $bActive = ((int)($b['is_active'] ?? 1) === 1);
                                        if (!$bActive && $bid !== $budgetId) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?= $bid ?>" <?= $bid === $budgetId ? 'selected' : '' ?>><?= e((string)($b['name'] ?? '')) ?><?= !$bActive ? ' (archivé)' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-xs text-slate-600">%</label>
                                <input name="budget_percent[]" inputmode="decimal" class="w-full border border-slate-300 rounded px-2 py-2 text-sm" placeholder="Ex: 50" <?= $isReadOnly ? 'disabled' : '' ?>>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-xs text-slate-600">Montant (€)</label>
                                <input name="budget_amount[]" value="<?= e($amountEur) ?>" inputmode="decimal" class="w-full border border-slate-300 rounded px-2 py-2 text-sm" placeholder="Ex: 12,50" <?= $isReadOnly ? 'disabled' : '' ?>>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="text-xs text-slate-500">
                        Astuce : si tu saisis un % sur une ligne, il est prioritaire sur le montant.
                    </div>
                </div>
            </details>
        <?php endif; ?>

        <div class="flex items-center gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2 <?= $isReadOnly ? 'opacity-50 cursor-not-allowed' : '' ?>" type="submit" <?= $isReadOnly ? 'disabled' : '' ?>>Enregistrer</button>
            <a class="border border-slate-300 rounded px-4 py-2" href="<?= e(tenant_path('/treasury')) ?>">Annuler</a>
        </div>
    </form>

    <?php if (!empty($canSoftDelete) && !$isReadOnly): ?>
        <form method="post" action="<?= e(tenant_path('/treasury/delete')) ?>" class="mt-3 bg-white border border-red-200 rounded-lg p-4" onsubmit="return confirm('Supprimer cette transaction ?');">
            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
            <input type="hidden" name="id" value="<?= e((string)($t['id'] ?? '')) ?>">
            <input type="hidden" name="return_to" value="<?= e((string)($_SERVER['REQUEST_URI'] ?? tenant_path('/treasury/edit?id=' . (string)($t['id'] ?? '')))) ?>">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-medium text-red-700">Suppression</div>
                    <div class="mt-1 text-xs text-slate-500">Interdit si transaction rapprochée, liée à une cotisation, ou avec justificatifs.</div>
                </div>
                <button class="border border-red-300 text-red-700 rounded px-4 py-2" type="submit">Supprimer</button>
            </div>
            <div class="mt-3">
                <label class="block text-xs text-slate-600">Raison (optionnel)</label>
                <input name="reason" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" placeholder="Ex: doublon, erreur de saisie">
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
(() => {
    const input = document.getElementById('tier_input');
    const hiddenId = document.getElementById('tier_id');
    const box = document.getElementById('tier_suggestions');
    if (!input || !hiddenId || !box) return;

    let lastQuery = '';
    let abortCtrl = null;

    const close = () => {
        box.classList.add('hidden');
        box.innerHTML = '';
    };

    const setTier = (id, name) => {
        hiddenId.value = String(id || '');
        if (typeof name === 'string') {
            input.value = name;
        }
        close();
    };

    const render = (items) => {
        if (!Array.isArray(items) || items.length === 0) {
            close();
            return;
        }
        box.innerHTML = '';
        for (const it of items) {
            const id = Number(it.id || 0);
            const name = String(it.name || '').trim();
            if (!id || !name) continue;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-left px-3 py-2 text-sm hover:bg-slate-50';
            btn.textContent = name;
            btn.addEventListener('click', () => setTier(id, name));
            box.appendChild(btn);
        }
        if (box.childNodes.length === 0) {
            close();
            return;
        }
        box.classList.remove('hidden');
    };

    input.addEventListener('input', async () => {
        const q = input.value.trim();
        hiddenId.value = '';
        if (q.length < 2) {
            close();
            return;
        }
        if (q === lastQuery) return;
        lastQuery = q;

        try {
            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();
            const res = await fetch('<?= e(tenant_path('/treasury/tiers/search')) ?>?q=' + encodeURIComponent(q), { signal: abortCtrl.signal });
            if (!res.ok) {
                close();
                return;
            }
            const data = await res.json();
            render((data && data.items) ? data.items : []);
        } catch (e) {
            close();
        }
    });

    input.addEventListener('blur', () => {
        setTimeout(() => close(), 150);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });
})();
</script>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
