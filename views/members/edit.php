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
            <label class="block text-sm font-medium mb-1">Date de naissance</label>
            <input name="birth_date" type="date" value="<?= e((string)($member['birth_date'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Foyer</label>
            <select name="household_id" class="w-full border border-slate-300 rounded px-3 py-2">
                <option value="0">Aucun</option>
                <?php foreach (($households ?? []) as $h): ?>
                    <?php $hid = (int)($h['id'] ?? 0); ?>
                    <option value="<?= e((string)$hid) ?>" <?= ((int)($member['household_id'] ?? 0) === $hid) ? 'selected' : '' ?>>
                        <?= e((string)($h['name'] ?? '')) ?: ('Foyer #' . e((string)$hid)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="mt-1 text-xs text-slate-500">
                <a class="underline" href="/households">Gérer les foyers</a>
            </div>
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
            <label class="block text-sm font-medium mb-1">Rôle (famille)</label>
            <select name="relationship" class="w-full border border-slate-300 rounded px-3 py-2">
                <option value="adult" <?= ((string)($member['relationship'] ?? 'adult') === 'adult') ? 'selected' : '' ?>>Adulte</option>
                <option value="spouse" <?= ((string)($member['relationship'] ?? 'adult') === 'spouse') ? 'selected' : '' ?>>Conjoint(e)</option>
                <option value="child" <?= ((string)($member['relationship'] ?? 'adult') === 'child') ? 'selected' : '' ?>>Enfant</option>
            </select>
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
            <label class="mt-2 inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="use_household_address" value="1" class="h-4 w-4" <?= ((int)($member['use_household_address'] ?? 0) === 1) ? 'checked' : '' ?>>
                <span>Utiliser l'adresse du foyer</span>
            </label>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Notes</label>
            <textarea name="notes" class="w-full border border-slate-300 rounded px-3 py-2" rows="3"><?= e((string)($member['notes'] ?? '')) ?></textarea>
        </div>

        <?php if (!empty($canMedical)): ?>
            <div class="sm:col-span-2 border-t border-slate-200 pt-4">
                <h2 class="text-sm font-semibold text-slate-900">Infos médicales (enfant)</h2>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-1">Allergies</label>
                        <textarea name="medical_allergies" class="w-full border border-slate-300 rounded px-3 py-2" rows="2"><?= e((string)($medical['allergies'] ?? '')) ?></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-1">Notes médicales</label>
                        <textarea name="medical_notes" class="w-full border border-slate-300 rounded px-3 py-2" rows="3"><?= e((string)($medical['medical_notes'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>

            <?php if ((string)($member['relationship'] ?? 'adult') === 'child'): ?>
                <div class="sm:col-span-2 border-t border-slate-200 pt-4">
                    <h2 class="text-sm font-semibold text-slate-900">Personnes habilitées à récupérer</h2>

                    <div class="mt-3 bg-slate-50 border border-slate-200 rounded p-3">
                        <div class="text-xs text-slate-500">Ajout rapide</div>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-4 gap-2">
                            <input form="pickup-add" name="name" placeholder="Nom" class="w-full border border-slate-300 rounded px-3 py-2">
                            <input form="pickup-add" name="phone" placeholder="Téléphone" class="w-full border border-slate-300 rounded px-3 py-2">
                            <input form="pickup-add" name="relation" placeholder="Lien" class="w-full border border-slate-300 rounded px-3 py-2">
                            <button form="pickup-add" class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Ajouter</button>
                        </div>
                        <textarea form="pickup-add" name="notes" placeholder="Notes" class="mt-2 w-full border border-slate-300 rounded px-3 py-2" rows="2"></textarea>
                        <form id="pickup-add" method="post" action="/members/pickups/new" class="hidden">
                            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                            <input type="hidden" name="member_id" value="<?= e((string)$member['id']) ?>">
                        </form>
                    </div>

                    <div class="mt-3 bg-white border border-slate-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50">
                            <tr>
                                <th class="text-left p-3">Nom</th>
                                <th class="text-left p-3">Téléphone</th>
                                <th class="text-left p-3">Lien</th>
                                <th class="text-right p-3">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($pickups)): ?>
                                <tr>
                                    <td class="p-3 text-slate-500" colspan="4">Aucune personne habilitée.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (($pickups ?? []) as $p): ?>
                                    <tr class="border-t border-slate-200">
                                        <td class="p-3">
                                            <div class="font-medium"><?= e((string)($p['name'] ?? '')) ?></div>
                                            <?php if (!empty($p['notes'])): ?>
                                                <div class="text-xs text-slate-500 whitespace-pre-line"><?= e((string)$p['notes']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3"><?= e((string)($p['phone'] ?? '')) ?></td>
                                        <td class="p-3"><?= e((string)($p['relation'] ?? '')) ?></td>
                                        <td class="p-3 text-right">
                                            <form method="post" action="/members/pickups/delete" onsubmit="return confirm('Supprimer ?');">
                                                <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                                <input type="hidden" name="member_id" value="<?= e((string)$member['id']) ?>">
                                                <input type="hidden" name="id" value="<?= e((string)($p['id'] ?? '0')) ?>">
                                                <button class="border border-slate-300 rounded px-3 py-2 text-sm" type="submit">Supprimer</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
</form>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
