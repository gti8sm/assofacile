<?php

$title = 'Modifier adhérent';
ob_start();
?>
<?php
$memberId = (int)($member['id'] ?? 0);
$memberName = trim((string)($member['first_name'] ?? '') . ' ' . (string)($member['last_name'] ?? ''));
$memberStatus = (string)($member['status'] ?? '');
$memberSince = (string)($member['member_since'] ?? '');
$paidUntil = (string)($member['membership_paid_until'] ?? '');
$relationship = (string)($member['relationship'] ?? '');
$email = (string)($member['email'] ?? '');
$phone = (string)($member['phone'] ?? '');

$countDocs = (int)($tabCounts['docs'] ?? 0);
$countMemberships = (int)($tabCounts['memberships'] ?? 0);

$kpiLabel = 'Cotisation';
$kpiValue = $paidUntil !== '' ? $paidUntil : '—';
$kpiSub = $memberSince !== '' ? ('Adhérent depuis: ' . $memberSince) : '';
?>

<div class="flex items-start justify-between gap-3">
    <div>
        <div class="text-xs text-slate-500">Adhérent #<?= e((string)$memberId) ?></div>
        <h1 class="text-2xl font-semibold"><?= e($memberName !== '' ? $memberName : 'Fiche adhérent') ?></h1>
        <div class="mt-1 text-sm text-slate-600">
            Statut: <span class="font-medium text-slate-900"><?= e($memberStatus !== '' ? $memberStatus : '—') ?></span>
            <?php if ($relationship !== ''): ?> · Rôle: <span class="font-medium text-slate-900"><?= e($relationship) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/members">Retour</a>
        <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm member-open-tab" data-tab="docs">Documents</button>
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

<div class="mt-6">
    <div class="border-b border-slate-200 flex flex-wrap gap-2">
        <button type="button" class="member-tab px-3 py-2 text-sm rounded-t border border-slate-200 bg-white" data-tab="recap">Récap</button>
        <button type="button" class="member-tab px-3 py-2 text-sm rounded-t border border-slate-200 bg-slate-50" data-tab="infos">Infos</button>
        <?php if (App\Support\ModuleSettings::getBool((int)$_SESSION['tenant_id'], 'members', 'memberships_enabled', true)): ?>
            <button type="button" class="member-tab px-3 py-2 text-sm rounded-t border border-slate-200 bg-slate-50" data-tab="memberships">Cotisations <span class="ml-1 text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded"><?= (int)$countMemberships ?></span></button>
        <?php endif; ?>
        <button type="button" class="member-tab px-3 py-2 text-sm rounded-t border border-slate-200 bg-slate-50" data-tab="docs">Documents <span class="ml-1 text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded"><?= (int)$countDocs ?></span></button>
    </div>

    <div class="mt-4">
        <div class="member-tab-panel" data-panel="recap">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="bg-white border border-slate-200 rounded-lg p-4 lg:col-span-2">
                    <div class="text-sm font-medium">Tableau de bord</div>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="border border-slate-200 rounded p-3">
                            <div class="text-xs text-slate-500">Email</div>
                            <div class="text-sm font-semibold truncate"><?= e($email !== '' ? $email : '—') ?></div>
                        </div>
                        <div class="border border-slate-200 rounded p-3">
                            <div class="text-xs text-slate-500">Téléphone</div>
                            <div class="text-sm font-semibold"><?= e($phone !== '' ? $phone : '—') ?></div>
                        </div>
                        <div class="border border-slate-200 rounded p-3">
                            <div class="text-xs text-slate-500"><?= e($kpiLabel) ?></div>
                            <div class="text-sm font-semibold"><?= e($kpiValue) ?></div>
                            <?php if ($kpiSub !== ''): ?><div class="mt-1 text-xs text-slate-500"><?= e($kpiSub) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm member-open-tab" data-tab="infos">Modifier infos</button>
                        <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm member-open-tab" data-tab="memberships">Voir cotisations</button>
                        <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm member-open-tab" data-tab="docs">Voir documents</button>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <div class="text-sm font-medium">Raccourcis</div>
                    <div class="mt-3 space-y-2">
                        <a class="block border border-slate-300 rounded px-3 py-2 text-sm" href="/members">Liste adhérents</a>
                        <a class="block border border-slate-300 rounded px-3 py-2 text-sm" href="/households">Foyers</a>
                        <?php if (!empty($member['household_id'])): ?>
                            <a class="block border border-slate-300 rounded px-3 py-2 text-sm" href="/households/edit?id=<?= e((string)($member['household_id'] ?? '')) ?>">Ouvrir foyer</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="member-tab-panel hidden" data-panel="infos">
            <form method="post" action="/members/edit" class="bg-white border border-slate-200 rounded-lg p-4 space-y-4">
                <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= e((string)$memberId) ?>">

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
                        <?php if (!empty($household)): ?>
                            <div class="mt-2 text-xs text-slate-600 whitespace-pre-line">
                                <div class="text-slate-500">Adresse du foyer</div>
                                <div><?= e((string)($household['address'] ?? '')) ?: '—' ?></div>
                            </div>
                        <?php endif; ?>
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
                        <div class="w-full border border-slate-200 rounded px-3 py-2 bg-slate-50 text-slate-700">
                            <?= !empty($member['membership_paid_until']) ? e((string)$member['membership_paid_until']) : '—' ?>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">Lecture seule : l'état de cotisation se gère via les cotisations (foyer ou personne).</div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-1">Adresse</label>
                        <textarea name="address" class="w-full border border-slate-300 rounded px-3 py-2" rows="2"><?= e((string)($member['address'] ?? '')) ?></textarea>
                        <label class="mt-2 inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="use_household_address" value="1" class="h-4 w-4" <?= ((int)($member['use_household_address'] ?? 0) === 1) ? 'checked' : '' ?>>
                            <span>Utiliser l'adresse du foyer</span>
                        </label>
                        <?php if (((int)($member['use_household_address'] ?? 0) === 1) && isset($effectiveAddress)): ?>
                            <div class="mt-2 text-xs text-slate-600 whitespace-pre-line">
                                <div class="text-slate-500">Adresse effective</div>
                                <div><?= e((string)$effectiveAddress) ?: '—' ?></div>
                            </div>
                        <?php endif; ?>
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
                                        <input type="hidden" name="member_id" value="<?= e((string)$memberId) ?>">
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
                                                            <input type="hidden" name="member_id" value="<?= e((string)$memberId) ?>">
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

                <div class="flex items-center gap-2">
                    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
                </div>
            </form>
        </div>

        <?php if (App\Support\ModuleSettings::getBool((int)$_SESSION['tenant_id'], 'members', 'memberships_enabled', true)): ?>
            <div class="member-tab-panel hidden" data-panel="memberships">
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold">Cotisations</div>
                        <a class="text-sm underline" href="/memberships/products">Catalogue</a>
                    </div>

                    <?php if (!empty($member['household_id'])): ?>
                        <div class="mt-3 p-3 rounded bg-slate-50 border border-slate-200 text-sm text-slate-700">
                            Ce membre est rattaché à un foyer : la cotisation se gère depuis la fiche foyer.
                            <?php if (!empty($household)): ?>
                                <div class="mt-2">
                                    <a class="underline" href="/households/edit?id=<?= e((string)($household['id'] ?? '')) ?>">Ouvrir le foyer</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="post" action="/memberships/subscriptions/new" class="mt-3 grid grid-cols-1 sm:grid-cols-6 gap-2">
                            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                            <input type="hidden" name="member_id" value="<?= e((string)$memberId) ?>">

                            <select name="product_id" class="w-full border border-slate-300 rounded px-3 py-2 sm:col-span-2">
                                <option value="">Cotisation…</option>
                                <?php foreach (($membershipProducts ?? []) as $p): ?>
                                    <option value="<?= e((string)$p['id']) ?>">
                                        <?= e((string)($p['label'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input name="start_date" type="date" value="<?= e((new DateTimeImmutable('today'))->format('Y-m-d')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">

                            <input name="amount" placeholder="Montant (optionnel)" class="w-full border border-slate-300 rounded px-3 py-2">

                            <select name="payment_method" class="w-full border border-slate-300 rounded px-3 py-2">
                                <option value="">Paiement…</option>
                                <?php if (App\Support\ModuleSettings::getBool((int)$_SESSION['tenant_id'], 'members', 'helloasso_enabled', false)): ?>
                                    <option value="helloasso">HelloAsso</option>
                                <?php endif; ?>
                                <option value="cash">Espèces</option>
                                <option value="check">Chèque</option>
                                <option value="transfer">Virement</option>
                            </select>

                            <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Ajouter</button>
                        </form>
                    <?php endif; ?>

                    <div class="mt-3 bg-white border border-slate-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50">
                            <tr>
                                <th class="text-left p-3">Cotisation</th>
                                <th class="text-left p-3">Période</th>
                                <th class="text-left p-3">Montant</th>
                                <th class="text-left p-3">Statut</th>
                                <th class="text-right p-3">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($membershipSubscriptions)): ?>
                                <tr>
                                    <td class="p-3 text-slate-500" colspan="5">Aucune cotisation enregistrée.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (($membershipSubscriptions ?? []) as $s): ?>
                                    <tr class="border-t border-slate-200">
                                        <td class="p-3">
                                            <div class="font-medium"><?= e((string)($s['product_label'] ?? '—')) ?></div>
                                            <div class="text-xs text-slate-500">#<?= e((string)$s['id']) ?></div>
                                        </td>
                                        <td class="p-3">
                                            <?= e((string)($s['start_date'] ?? '')) ?> → <?= e((string)($s['end_date'] ?? '')) ?>
                                        </td>
                                        <td class="p-3"><?= e(number_format(((int)($s['amount_cents'] ?? 0)) / 100, 2, ',', ' ')) ?> €</td>
                                        <?php
                                        $status = (string)($s['status'] ?? '');
                                        $statusLabel = $status;
                                        if ($status === 'pending') {
                                            $statusLabel = 'En attente';
                                        } elseif ($status === 'paid') {
                                            $statusLabel = 'Payée';
                                        } elseif ($status === 'canceled') {
                                            $statusLabel = 'Annulée';
                                        } elseif ($status === 'expired') {
                                            $statusLabel = 'Expirée';
                                        }
                                        ?>
                                        <td class="p-3"><?= e($statusLabel) ?></td>
                                        <td class="p-3 text-right">
                                            <?php if (!empty($member['household_id'])): ?>
                                                <span class="text-slate-400">—</span>
                                            <?php elseif ((string)($s['status'] ?? '') === 'pending' && (string)($s['payment_provider'] ?? '') === 'helloasso' && App\Support\ModuleSettings::getBool((int)$_SESSION['tenant_id'], 'members', 'helloasso_enabled', false)): ?>
                                                <form method="post" action="/memberships/helloasso/pay" class="inline">
                                                    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                                    <input type="hidden" name="subscription_id" value="<?= e((string)$s['id']) ?>">
                                                    <button class="border border-slate-300 rounded px-3 py-2 text-sm" type="submit">Payer (HelloAsso)</button>
                                                </form>
                                            <?php elseif (empty($member['household_id']) && (string)($s['status'] ?? '') === 'pending' && in_array((string)($s['payment_provider'] ?? ''), ['check', 'transfer'], true)): ?>
                                                <form method="post" action="/memberships/subscriptions/mark-paid" class="inline">
                                                    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                                    <input type="hidden" name="subscription_id" value="<?= e((string)$s['id']) ?>">
                                                    <button class="border border-slate-300 rounded px-3 py-2 text-sm" type="submit">Marquer payée</button>
                                                </form>
                                            <?php elseif ((string)($s['status'] ?? '') === 'paid'): ?>
                                                <a class="border border-slate-300 rounded px-3 py-2 text-sm" target="_blank" rel="noopener" href="/memberships/card?subscription_id=<?= e((string)$s['id']) ?>">Carte</a>
                                            <?php elseif (!empty($_SESSION['is_admin'])
                                                && in_array((string)($s['status'] ?? ''), ['pending', 'canceled', 'expired'], true)
                                                && empty($s['treasury_transaction_id'])
                                                && empty($s['payment_external_id'])): ?>
                                                <form method="post" action="/memberships/subscriptions/delete-test" class="inline" onsubmit="return confirm('Supprimer cette cotisation de test ?');">
                                                    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                                    <input type="hidden" name="subscription_id" value="<?= e((string)$s['id']) ?>">
                                                    <input type="hidden" name="return_to" value="<?= e('/members/edit?id=' . (string)($memberId)) ?>">
                                                    <button class="border border-red-300 text-red-700 rounded px-3 py-2 text-sm" type="submit">Supprimer (test)</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-slate-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="member-tab-panel hidden" data-panel="docs">
            <div class="bg-white border border-slate-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium">Documents</div>
                    <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-open="modal_add_member_doc">Ajouter</button>
                </div>

                <?php if (empty($documents)): ?>
                    <div class="mt-3 text-sm text-slate-500">Aucun document.</div>
                <?php else: ?>
                    <div class="mt-3 space-y-2">
                        <?php foreach (($documents ?? []) as $d): ?>
                            <?php
                            $did = (int)($d['id'] ?? 0);
                            $title = (string)($d['title'] ?? '');
                            $url = (string)($d['url'] ?? '');
                            $notes = (string)($d['notes'] ?? '');
                            $hasFile = !empty($d['local_path']) || !empty($d['gdrive_file_id']);
                            ?>
                            <div class="border border-slate-200 rounded p-3 flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-medium text-sm truncate"><?= e($title !== '' ? $title : 'Document') ?></div>
                                    <?php if ($hasFile && $did > 0): ?>
                                        <a class="text-xs underline text-slate-700" href="/members/documents/download?id=<?= e((string)$did) ?>">Télécharger</a>
                                    <?php endif; ?>
                                    <?php if ($url !== ''): ?>
                                        <a class="ml-2 text-xs underline text-slate-700" href="<?= e($url) ?>" target="_blank" rel="noreferrer"><?= e($url) ?></a>
                                    <?php endif; ?>
                                    <?php if ($notes !== ''): ?>
                                        <div class="mt-1 text-xs text-slate-500 whitespace-pre-line"><?= e($notes) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($did > 0): ?>
                                    <form method="post" action="/members/documents/delete" onsubmit="const r = prompt('Motif de suppression (optionnel) :'); this.querySelector('input[name=delete_reason]').value = (r || '').trim(); return confirm('Déplacer ce document dans la corbeille ?');">
                                        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                        <input type="hidden" name="member_id" value="<?= e((string)$memberId) ?>">
                                        <input type="hidden" name="id" value="<?= e((string)$did) ?>">
                                        <input type="hidden" name="delete_reason" value="">
                                        <button class="border border-red-300 text-red-700 rounded px-2 py-1 text-xs" type="submit">Supprimer</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="modal_add_member_doc" class="hidden fixed inset-0 z-50">
    <div class="member-modal-backdrop absolute inset-0 bg-black/40" data-modal-id="modal_add_member_doc"></div>
    <div class="relative max-w-xl mx-auto mt-24 bg-white border border-slate-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div class="font-semibold">Ajouter un document</div>
            <button type="button" class="border border-slate-300 rounded px-2 py-1 text-sm" data-modal-close="modal_add_member_doc">Fermer</button>
        </div>

        <form class="mt-4 space-y-3" method="post" action="/members/documents/add" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
            <input type="hidden" name="member_id" value="<?= e((string)$memberId) ?>">

            <div>
                <label class="block text-sm font-medium mb-1">Titre</label>
                <input name="title" class="w-full border border-slate-300 rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Fichier (optionnel)</label>
                <input name="file" type="file" class="w-full border border-slate-300 rounded px-3 py-2">
                <div class="mt-1 text-xs text-slate-500">Max 10 Mo. Stockage Google Drive si activé, sinon local.</div>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">URL (optionnel)</label>
                <input name="url" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="https://...">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Notes (optionnel)</label>
                <textarea name="notes" class="w-full border border-slate-300 rounded px-3 py-2" rows="3"></textarea>
            </div>

            <div class="flex items-center gap-2">
                <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Ajouter</button>
                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-close="modal_add_member_doc">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    const tabBtns = Array.from(document.querySelectorAll('.member-tab'));
    const panels = Array.from(document.querySelectorAll('.member-tab-panel'));
    const openTabBtns = Array.from(document.querySelectorAll('.member-open-tab'));

    const setActive = (tab) => {
        if (!tab) tab = 'recap';
        for (const b of tabBtns) {
            const isActive = b.dataset.tab === tab;
            b.className = 'member-tab px-3 py-2 text-sm rounded-t border border-slate-200 ' + (isActive ? 'bg-white' : 'bg-slate-50');
        }
        for (const p of panels) {
            p.classList.toggle('hidden', p.dataset.panel !== tab);
        }
    };

    const getTabFromHash = () => {
        const h = (window.location.hash || '').replace('#', '').trim();
        if (!h) return 'recap';
        return h;
    };

    for (const b of tabBtns) {
        b.addEventListener('click', () => {
            const tab = b.dataset.tab || 'recap';
            window.location.hash = tab;
            setActive(tab);
        });
    }
    for (const b of openTabBtns) {
        b.addEventListener('click', () => {
            const tab = b.dataset.tab || 'recap';
            window.location.hash = tab;
            setActive(tab);
        });
    }
    window.addEventListener('hashchange', () => setActive(getTabFromHash()));
    setActive(getTabFromHash());

    const closeModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
    };
    const openModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        const focusable = el.querySelector('input,select,textarea,button');
        if (focusable) focusable.focus();
    };

    document.addEventListener('click', (e) => {
        const t = e.target;
        if (!(t instanceof HTMLElement)) return;
        const open = t.getAttribute('data-modal-open');
        if (open) {
            openModal(open);
            return;
        }
        const close = t.getAttribute('data-modal-close');
        if (close) {
            closeModal(close);
            return;
        }
        if (t.classList.contains('member-modal-backdrop')) {
            const id = t.getAttribute('data-modal-id');
            if (id) closeModal(id);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const modal = document.getElementById('modal_add_member_doc');
        if (modal && !modal.classList.contains('hidden')) {
            closeModal('modal_add_member_doc');
        }
    });
})();
</script>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
