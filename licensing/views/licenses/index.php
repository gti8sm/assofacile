<?php

$title = 'Licences';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Licences</h1>
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

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
    <h2 class="text-sm font-semibold mb-2">Créer une licence</h2>
    <form method="post" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <input type="hidden" name="_csrf" value="<?= e(Licensing\Support\Csrf::token()) ?>">

        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">License key</label>
            <input name="license_key" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Association (optionnel)</label>
            <input name="tenant_name" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Type</label>
            <select name="plan_type" class="w-full border border-slate-300 rounded px-3 py-2" required>
                <option value="annual">Annual</option>
                <option value="lifetime">Lifetime</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Valide jusqu'au (annual)</label>
            <input name="valid_until" type="date" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div class="sm:col-span-2">
            <button class="bg-slate-900 text-white rounded px-3 py-2" type="submit">Créer</button>
        </div>
    </form>
</div>

<div class="mt-6 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Key</th>
            <th class="text-left p-3">Asso</th>
            <th class="text-left p-3">Plan</th>
            <th class="text-left p-3">Valid until</th>
            <th class="text-left p-3">Status</th>
            <th class="text-left p-3">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($licenses as $l): ?>
            <tr class="border-t border-slate-200">
                <td class="p-3 font-mono text-xs"><?= e((string)$l['license_key']) ?></td>
                <td class="p-3"><?= e((string)($l['tenant_name'] ?? '')) ?></td>
                <td class="p-3"><?= e((string)$l['plan_type']) ?></td>
                <td class="p-3"><?= e((string)($l['valid_until'] ?? '')) ?></td>
                <td class="p-3"><?= ((int)$l['is_revoked'] === 1) ? 'revoked' : 'ok' ?></td>
                <td class="p-3">
                    <form method="post" action="/licenses/revoke" class="inline">
                        <input type="hidden" name="_csrf" value="<?= e(Licensing\Support\Csrf::token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                        <button class="text-red-700" type="submit">Révoquer</button>
                    </form>
                    <form method="post" action="/licenses/renew" class="inline ml-3">
                        <input type="hidden" name="_csrf" value="<?= e(Licensing\Support\Csrf::token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                        <input type="date" name="valid_until" class="border border-slate-300 rounded px-2 py-1" required>
                        <button class="ml-2 text-slate-900" type="submit">Renouveler</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="mt-6 text-xs text-slate-600">
    <div class="font-semibold">Clé publique à copier dans AssoFacile</div>
    <div class="mt-2 font-mono break-all bg-slate-50 border border-slate-200 rounded p-3"><?= e((string)(Licensing\Support\Env::get('LICENSE_PUBLIC_KEY_B64', '') ?? '')) ?></div>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
