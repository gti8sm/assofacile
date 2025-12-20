<?php

$title = 'Nouvelle cotisation';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Nouvelle cotisation</h1>
    <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/memberships/products">Retour</a>
</div>

<?php if (!empty($error)): ?>
    <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-4">
    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Libellé</label>
            <input name="label" value="<?= e((string)($_POST['label'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Ex: Adulte / Enfant / Famille / Soutien">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Cible</label>
            <select name="applies_to" class="w-full border border-slate-300 rounded px-3 py-2">
                <option value="person" <?= ((string)($_POST['applies_to'] ?? 'person') === 'person') ? 'selected' : '' ?>>Personne</option>
                <option value="household" <?= ((string)($_POST['applies_to'] ?? 'person') === 'household') ? 'selected' : '' ?>>Foyer</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Durée (mois)</label>
            <input name="period_months" type="number" min="1" max="60" value="<?= e((string)($_POST['period_months'] ?? '12')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Montant par défaut (optionnel)</label>
            <input name="amount_default" value="<?= e((string)($_POST['amount_default'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Ex: 30 ou 30,00">
            <div class="mt-1 text-xs text-slate-500">Si vide: montant libre.</div>
        </div>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Créer</button>
</form>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
