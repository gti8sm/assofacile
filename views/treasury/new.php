<?php
$title = 'Nouvelle transaction';
ob_start();
$defaultDate = date('Y-m-d');

$vType = (string)($_POST['type'] ?? ($prefill['type'] ?? 'expense'));
$vLabel = (string)($_POST['label'] ?? ($prefill['label'] ?? ''));
$vAmount = (string)($_POST['amount'] ?? ($prefill['amount'] ?? ''));
$vOccurredOn = (string)($_POST['occurred_on'] ?? ($prefill['occurred_on'] ?? $defaultDate));
$vCategoryId = (string)($_POST['category_id'] ?? ($prefill['category_id'] ?? ''));
$vPaymentMethod = (string)($_POST['payment_method'] ?? ($prefill['payment_method'] ?? ''));
$vTierId = (string)($_POST['tier_id'] ?? '');
$vCounterparty = (string)($_POST['counterparty'] ?? ($prefill['counterparty'] ?? ''));
$vReference = (string)($_POST['reference'] ?? ($prefill['reference'] ?? ''));

$budgetsEnabled = !empty($budgetsEnabled);
$budgets = is_array($budgets ?? null) ? $budgets : [];
?>
<div class="max-w-2xl">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Nouvelle transaction</h1>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/treasury')) ?>">Retour</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="mt-4 space-y-6 bg-white border border-slate-200 rounded-lg p-4">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
        <input type="hidden" name="tier_id" id="tier_id" value="<?= e($vTierId) ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Type</label>
                <select name="type" class="w-full border border-slate-300 rounded px-3 py-2">
                    <option value="expense" <?= ($vType === 'expense' ? 'selected' : '') ?>>Dépense</option>
                    <option value="income" <?= ($vType === 'income' ? 'selected' : '') ?>>Recette</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Date</label>
                <input name="occurred_on" type="date" value="<?= e($vOccurredOn) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Libellé</label>
            <input name="label" value="<?= e($vLabel) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Montant (€)</label>
                <input name="amount" value="<?= e($vAmount) ?>" inputmode="decimal" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="12,50" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Moyen de paiement</label>
                <select name="payment_method" class="w-full border border-slate-300 rounded px-3 py-2">
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
            <select name="category_id" class="w-full border border-slate-300 rounded px-3 py-2">
                <option value="">—</option>
                <?php foreach (($categories ?? []) as $c): ?>
                    <option value="<?= e((string)$c['id']) ?>" <?= ((string)$c['id'] === $vCategoryId ? 'selected' : '') ?>><?= e((string)$c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <details class="border border-slate-200 rounded-lg p-3">
            <summary class="cursor-pointer select-none text-sm font-medium">Options (tiers, référence, justificatifs)</summary>
            <div class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Tiers (optionnel)</label>
                    <div class="relative">
                        <input name="counterparty" id="tier_input" value="<?= e($vCounterparty) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Ex: Supermarché, Mairie, ..." autocomplete="off">
                        <div id="tier_suggestions" class="hidden absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded shadow-sm overflow-hidden"></div>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">Sélectionne un tiers existant (autocomplete) ou saisis librement : un tiers sera créé automatiquement.</div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Référence (optionnel)</label>
                    <input name="reference" value="<?= e($vReference) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Ex: chèque n°, ref virement">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Justificatifs (optionnel)</label>
                    <input type="file" name="attachments[]" multiple accept="image/jpeg,image/png,application/pdf" class="w-full">
                </div>
            </div>
        </details>

        <?php if ($budgetsEnabled): ?>
            <details class="border border-slate-200 rounded-lg p-3">
                <summary class="cursor-pointer select-none text-sm font-medium">Budgets (enveloppes)</summary>
                <div class="mt-2 text-xs text-slate-500">Saisie en % ou en € (si les deux sont remplis, le % est prioritaire).</div>

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <a class="text-xs text-slate-600 hover:text-slate-900 underline" href="<?= e(tenant_path('/treasury/budgets')) ?>">Gérer les budgets</a>
                </div>

                <div class="mt-3 space-y-2">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                            <div class="sm:col-span-6">
                                <label class="block text-xs text-slate-600">Budget</label>
                                <select name="budget_id[]" class="w-full border border-slate-300 rounded px-2 py-2 text-sm">
                                    <option value="">—</option>
                                    <?php foreach ($budgets as $b): ?>
                                        <?php $bid = (int)($b['id'] ?? 0); if ($bid <= 0) continue; ?>
                                        <option value="<?= $bid ?>"><?= e((string)($b['name'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-xs text-slate-600">%</label>
                                <input name="budget_percent[]" inputmode="decimal" class="w-full border border-slate-300 rounded px-2 py-2 text-sm" placeholder="Ex: 50">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-xs text-slate-600">Montant (€)</label>
                                <input name="budget_amount[]" inputmode="decimal" class="w-full border border-slate-300 rounded px-2 py-2 text-sm" placeholder="Ex: 12,50">
                            </div>
                        </div>
                    <?php endfor; ?>

                    <div class="text-xs text-slate-500">
                        Astuce : si tu saisis un % sur une ligne, il est prioritaire sur le montant.
                    </div>
                </div>
            </details>
        <?php endif; ?>

        <div class="flex items-center gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2" type="submit">Enregistrer</button>
            <a class="border border-slate-300 rounded px-4 py-2" href="<?= e(tenant_path('/treasury')) ?>">Annuler</a>
        </div>
    </form>
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
