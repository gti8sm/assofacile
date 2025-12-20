<?php

$title = 'Modifier foyer';
ob_start();
?>
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Modifier foyer</h1>
        <div class="text-xs text-slate-500">#<?= e((string)$household['id']) ?></div>
    </div>
    <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/households">Retour</a>
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
    <input type="hidden" name="id" value="<?= e((string)$household['id']) ?>">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Nom du foyer</label>
            <input name="name" value="<?= e((string)($household['name'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Adresse</label>
            <textarea name="address" class="w-full border border-slate-300 rounded px-3 py-2" rows="3"><?= e((string)($household['address'] ?? '')) ?></textarea>
        </div>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
</form>

<?php if (App\Support\ModuleSettings::getBool((int)$_SESSION['tenant_id'], 'members', 'memberships_enabled', true)): ?>
    <div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div class="font-semibold">Cotisations (foyer)</div>
            <a class="text-sm underline" href="/memberships/products">Catalogue</a>
        </div>

        <form method="post" action="/memberships/subscriptions/new" class="mt-3 grid grid-cols-1 sm:grid-cols-6 gap-2">
            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
            <input type="hidden" name="household_id" value="<?= e((string)$household['id']) ?>">

            <select name="product_id" class="w-full border border-slate-300 rounded px-3 py-2 sm:col-span-2">
                <option value="">Cotisation…</option>
                <?php foreach (($membershipProducts ?? []) as $p): ?>
                    <option value="<?= e((string)$p['id']) ?>">
                        <?= e((string)$p['label']) ?>
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
                                <?php if ((string)($s['status'] ?? '') === 'pending' && (string)($s['payment_provider'] ?? '') === 'helloasso' && App\Support\ModuleSettings::getBool((int)$_SESSION['tenant_id'], 'members', 'helloasso_enabled', false)): ?>
                                    <form method="post" action="/memberships/helloasso/pay" class="inline">
                                        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                        <input type="hidden" name="subscription_id" value="<?= e((string)$s['id']) ?>">
                                        <button class="border border-slate-300 rounded px-3 py-2 text-sm" type="submit">Payer (HelloAsso)</button>
                                    </form>
                                <?php elseif ((string)($s['status'] ?? '') === 'pending' && in_array((string)($s['payment_provider'] ?? ''), ['check', 'transfer'], true)): ?>
                                    <form method="post" action="/memberships/subscriptions/mark-paid" class="inline">
                                        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                        <input type="hidden" name="subscription_id" value="<?= e((string)$s['id']) ?>">
                                        <button class="border border-slate-300 rounded px-3 py-2 text-sm" type="submit">Marquer payée</button>
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
<?php endif; ?>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <div class="p-4 border-b border-slate-200">
        <div class="flex items-center justify-between">
            <div class="font-semibold">Membres du foyer</div>
            <a class="bg-slate-900 text-white rounded px-3 py-2 text-sm" href="/members/new?type=child&household_id=<?= e((string)$household['id']) ?>&return_to=<?= e('/households/edit?id=' . (string)$household['id']) ?>">Ajouter un enfant</a>
        </div>
        <div class="text-xs text-slate-500">Rattache un membre à ce foyer depuis sa fiche (Adhérents → Modifier → Foyer).</div>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Nom</th>
            <th class="text-left p-3">Rôle</th>
            <th class="text-right p-3">Action</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($members)): ?>
            <tr>
                <td class="p-3 text-slate-500" colspan="3">Aucun membre rattaché.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($members as $m): ?>
                <tr class="border-t border-slate-200">
                    <td class="p-3">
                        <div class="font-medium"><?= e(trim((string)($m['first_name'] ?? '') . ' ' . (string)($m['last_name'] ?? ''))) ?: '—' ?></div>
                        <div class="text-xs text-slate-500">#<?= e((string)$m['id']) ?></div>
                    </td>
                    <td class="p-3">
                        <span class="text-xs bg-slate-100 text-slate-700 px-2 py-1 rounded"><?= e((string)($m['relationship'] ?? 'adult')) ?></span>
                    </td>
                    <td class="p-3 text-right">
                        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/members/edit?id=<?= e((string)$m['id']) ?>">Ouvrir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
