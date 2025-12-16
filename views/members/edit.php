<?php

$title = 'Modifier adhérent';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Modifier adhérent</h1>
    <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/members">Retour</a>
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

<form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-4">
    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
    <input type="hidden" name="id" value="<?= e((string)$member['id']) ?>">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium mb-1">Prénom</label>
            <input name="first_name" value="<?= e((string)($member['first_name'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Nom</label>
            <input name="last_name" value="<?= e((string)($member['last_name'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input name="email" type="email" value="<?= e((string)($member['email'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Téléphone</label>
            <input name="phone" value="<?= e((string)($member['phone'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Statut</label>
            <select name="status" class="w-full border border-slate-300 rounded px-3 py-2">
                <option value="active" <?= ((string)$member['status'] === 'active') ? 'selected' : '' ?>>Actif</option>
                <option value="inactive" <?= ((string)$member['status'] === 'inactive') ? 'selected' : '' ?>>Inactif</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Adhérent depuis</label>
            <input name="member_since" type="date" value="<?= e((string)($member['member_since'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Cotisation payée jusqu'au</label>
            <input name="membership_paid_until" type="date" value="<?= e((string)($member['membership_paid_until'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Adresse</label>
            <textarea name="address" class="w-full border border-slate-300 rounded px-3 py-2" rows="2"><?= e((string)($member['address'] ?? '')) ?></textarea>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Notes</label>
            <textarea name="notes" class="w-full border border-slate-300 rounded px-3 py-2" rows="3"><?= e((string)($member['notes'] ?? '')) ?></textarea>
        </div>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
</form>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
