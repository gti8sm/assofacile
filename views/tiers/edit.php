<?php

$title = 'Modifier tiers';
ob_start();

$tierId = (int)($tier['id'] ?? 0);
$memberId = (int)($tier['member_id'] ?? 0);
$isLinkedToMember = $memberId > 0;

$vName = (string)($_POST['name'] ?? (string)($tier['name'] ?? ''));
$vTagAdherent = in_array('adherent', $tags ?? [], true);
$vTagPartenaire = in_array('partenaire', $tags ?? [], true);
$vTagFournisseur = in_array('fournisseur', $tags ?? [], true);

if (isset($_POST['tag_adherent'])) {
    $vTagAdherent = (int)($_POST['tag_adherent'] ?? 0) === 1;
}
if (isset($_POST['tag_partenaire'])) {
    $vTagPartenaire = (int)($_POST['tag_partenaire'] ?? 0) === 1;
}
if (isset($_POST['tag_fournisseur'])) {
    $vTagFournisseur = (int)($_POST['tag_fournisseur'] ?? 0) === 1;
}
?>
<div class="max-w-xl">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Modifier tiers</h1>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/tiers/view?id=' . $tierId)) ?>">Retour</a>
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

    <form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-4" action="<?= e(tenant_path('/tiers/edit')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= e((string)$tierId) ?>">

        <div>
            <label class="block text-sm font-medium mb-1">Nom</label>
            <input name="name" value="<?= e($vName) ?>" class="w-full border border-slate-300 rounded px-3 py-2" <?= $isLinkedToMember ? 'disabled' : '' ?> required>
            <?php if ($isLinkedToMember): ?>
                <div class="mt-1 text-xs text-slate-500">Ce tiers est lié à un adhérent : le nom est synchronisé depuis la fiche adhérent.</div>
            <?php endif; ?>
        </div>

        <div>
            <div class="text-sm font-medium mb-1">Tags</div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="tag_adherent" value="1" <?= $vTagAdherent ? 'checked' : '' ?>>
                Adhérent
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm">
                <input type="checkbox" name="tag_partenaire" value="1" <?= $vTagPartenaire ? 'checked' : '' ?>>
                Partenaire
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm">
                <input type="checkbox" name="tag_fournisseur" value="1" <?= $vTagFournisseur ? 'checked' : '' ?>>
                Fournisseur
            </label>

            <div class="mt-1 text-xs text-slate-500">Astuce : coche plusieurs tags si nécessaire.</div>
        </div>

        <div class="flex items-center gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2" type="submit">Enregistrer</button>
            <a class="border border-slate-300 rounded px-4 py-2" href="<?= e(tenant_path('/tiers/view?id=' . $tierId)) ?>">Annuler</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
