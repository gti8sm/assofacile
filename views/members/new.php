<?php

$isChildMode = isset($createType) && $createType === 'child';
$title = $isChildMode ? 'Nouvel enfant' : 'Nouvel adhérent';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold"><?= e($title) ?></h1>
    <?php if (!empty($returnTo)): ?>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e($returnTo) ?>">Retour</a>
    <?php else: ?>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/members">Retour</a>
    <?php endif; ?>
</div>

<?php if (!empty($error)): ?>
    <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-4">
    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
    <?php if (!empty($returnTo)): ?>
        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <?php endif; ?>
    <?php if ($isChildMode): ?>
        <input type="hidden" name="relationship" value="child">
        <input type="hidden" name="use_household_address" value="1">
        <?php if (!empty($prefillHouseholdId)): ?>
            <input type="hidden" name="household_id" value="<?= e((string)$prefillHouseholdId) ?>">
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($isChildMode && !empty($availableParents)): ?>
        <div>
            <label class="block text-sm font-medium mb-1">Parent de référence</label>
            <select id="parent_select" class="w-full border border-slate-300 rounded px-3 py-2">
                <?php foreach ($availableParents as $p): ?>
                    <option
                        value="<?= e((string)$p['id']) ?>"
                        data-email="<?= e((string)($p['email'] ?? '')) ?>"
                        data-phone="<?= e((string)($p['phone'] ?? '')) ?>"
                    >
                        <?= e(trim((string)($p['first_name'] ?? '') . ' ' . (string)($p['last_name'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="mt-1 text-xs text-slate-500">Permet de proposer automatiquement ses coordonnées si besoin.</div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium mb-1">Prénom</label>
            <input name="first_name" value="<?= e((string)($_POST['first_name'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Nom</label>
            <input name="last_name" value="<?= e((string)($_POST['last_name'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Date de naissance</label>
            <input name="birth_date" type="date" value="<?= e((string)($_POST['birth_date'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input id="email" name="email" type="email" value="<?= e((string)($_POST['email'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
            <?php if ($isChildMode): ?>
                <div class="mt-1 text-xs text-slate-500">Optionnel (pour un enfant). Tu peux utiliser l'email du parent.</div>
                <button type="button" class="mt-1 text-xs underline" onclick="(function(){var s=document.getElementById('parent_select');if(s){var o=s.options[s.selectedIndex];var v=(o&&o.dataset)?o.dataset.email:'';if(v){document.getElementById('email').value=v;return;}}<?php if (!empty($suggestedParent) && !empty($suggestedParent['email'])): ?>document.getElementById('email').value = <?= json_encode((string)$suggestedParent['email']) ?>;<?php endif; ?>})()">Utiliser l'email du parent</button>
            <?php endif; ?>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Téléphone</label>
            <input id="phone" name="phone" value="<?= e((string)($_POST['phone'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
            <?php if ($isChildMode): ?>
                <div class="mt-1 text-xs text-slate-500">Optionnel (pour un enfant). Tu peux utiliser le téléphone du parent.</div>
                <button type="button" class="mt-1 text-xs underline" onclick="(function(){var s=document.getElementById('parent_select');if(s){var o=s.options[s.selectedIndex];var v=(o&&o.dataset)?o.dataset.phone:'';if(v){document.getElementById('phone').value=v;return;}}<?php if (!empty($suggestedParent) && !empty($suggestedParent['phone'])): ?>document.getElementById('phone').value = <?= json_encode((string)$suggestedParent['phone']) ?>;<?php endif; ?>})()">Utiliser le téléphone du parent</button>
            <?php endif; ?>
        </div>
        <?php if (!$isChildMode): ?>
            <div>
                <label class="block text-sm font-medium mb-1">Adhérent depuis</label>
                <input name="member_since" type="date" value="<?= e((string)($_POST['member_since'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Cotisation payée jusqu'au</label>
                <input name="membership_paid_until" type="date" value="<?= e((string)($_POST['membership_paid_until'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1">Adresse</label>
                <textarea name="address" class="w-full border border-slate-300 rounded px-3 py-2" rows="2"><?= e((string)($_POST['address'] ?? '')) ?></textarea>
            </div>
        <?php endif; ?>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Notes</label>
            <textarea name="notes" class="w-full border border-slate-300 rounded px-3 py-2" rows="3"><?= e((string)($_POST['notes'] ?? '')) ?></textarea>
        </div>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Créer</button>
</form>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
