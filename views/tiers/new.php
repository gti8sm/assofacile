<?php

$title = 'Nouveau tiers';
ob_start();

$vName = (string)($_POST['name'] ?? '');
$vTagPartenaire = (int)($_POST['tag_partenaire'] ?? 0) === 1;
$vTagFournisseur = (int)($_POST['tag_fournisseur'] ?? 0) === 1;
?>
<div class="max-w-xl">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Nouveau tiers</h1>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/tiers')) ?>">Retour</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-4" action="<?= e(tenant_path('/tiers/new')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">

        <div>
            <label class="block text-sm font-medium mb-1">Nom</label>
            <input name="name" value="<?= e($vName) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>

        <div>
            <div class="text-sm font-medium mb-1">Tags</div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="tag_partenaire" value="1" <?= $vTagPartenaire ? 'checked' : '' ?>>
                Partenaire
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm">
                <input type="checkbox" name="tag_fournisseur" value="1" <?= $vTagFournisseur ? 'checked' : '' ?>>
                Fournisseur
            </label>
            <div class="mt-1 text-xs text-slate-500">Astuce : tu peux cocher les deux si besoin.</div>
        </div>

        <div class="flex items-center gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2" type="submit">Créer</button>
            <a class="border border-slate-300 rounded px-4 py-2" href="<?= e(tenant_path('/tiers')) ?>">Annuler</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
