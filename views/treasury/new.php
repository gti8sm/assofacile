<?php
$title = 'Nouvelle transaction';
ob_start();
?>
<div class="max-w-xl">
    <h1 class="text-2xl font-semibold">Nouvelle transaction</h1>

    <?php if (!empty($error)): ?>
        <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="mt-4 space-y-4 bg-white border border-slate-200 rounded-lg p-4">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
        <div>
            <label class="block text-sm font-medium mb-1">Type</label>
            <select name="type" class="w-full border border-slate-300 rounded px-3 py-2">
                <option value="expense">Dépense</option>
                <option value="income">Recette</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Libellé</label>
            <input name="label" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Montant (€)</label>
            <input name="amount" inputmode="decimal" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="12,50" required>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Date</label>
            <input name="occurred_on" type="date" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>
        <div class="flex gap-2">
            <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
            <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/treasury">Annuler</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
