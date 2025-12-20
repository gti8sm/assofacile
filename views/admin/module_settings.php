<?php

$title = 'Admin - Paramètres module';
ob_start();
?>
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Paramètres</h1>
        <div class="text-xs text-slate-500"><?= e($moduleName) ?> (<?= e($moduleKey) ?>)</div>
    </div>
    <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/admin/modules">Retour modules</a>
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
    <input type="hidden" name="module" value="<?= e($moduleKey) ?>">

    <?php if ($moduleKey === 'members'): ?>
        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Activer les cotisations</div>
                <div class="text-xs text-slate-500">Affiche/masque le catalogue et la gestion des cotisations.</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="memberships_enabled" value="1" <?= !empty($settings['memberships_enabled']) ? 'checked' : '' ?>>
        </label>

        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Créer une écriture Trésorerie automatiquement</div>
                <div class="text-xs text-slate-500">Nécessite le module Trésorerie activé + droit "écriture".</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="memberships_create_treasury_income" value="1" <?= !empty($settings['memberships_create_treasury_income']) ? 'checked' : '' ?>>
        </label>

        <div class="mt-2 font-semibold">HelloAsso (paiement en ligne)</div>

        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Activer HelloAsso</div>
                <div class="text-xs text-slate-500">Permet de payer une cotisation via HelloAsso Checkout (API).</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="helloasso_enabled" value="1" <?= !empty($settings['helloasso_enabled']) ? 'checked' : '' ?>>
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Environnement</label>
                <select name="helloasso_environment" class="w-full border border-slate-300 rounded px-3 py-2">
                    <option value="prod" <?= (($settings['helloasso_environment'] ?? 'prod') === 'prod') ? 'selected' : '' ?>>Production</option>
                    <option value="sandbox" <?= (($settings['helloasso_environment'] ?? 'prod') === 'sandbox') ? 'selected' : '' ?>>Sandbox</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Organization slug</label>
                <input name="helloasso_organization_slug" value="<?= e((string)($settings['helloasso_organization_slug'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Client ID</label>
                <input name="helloasso_client_id" value="<?= e((string)($settings['helloasso_client_id'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" autocomplete="off">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Client Secret</label>
                <input type="password" name="helloasso_client_secret" value="<?= e((string)($settings['helloasso_client_secret'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" autocomplete="new-password">
            </div>
        </div>
    <?php endif; ?>

    <?php if ($moduleKey === 'treasury'): ?>
        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Activer l'analytique</div>
                <div class="text-xs text-slate-500">Prépare l'activation de la ventilation analytique.</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="analytics_enabled" value="1" <?= !empty($settings['analytics_enabled']) ? 'checked' : '' ?>>
        </label>

        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Activer la ventilation par budgets</div>
                <div class="text-xs text-slate-500">Prépare l'activation des budgets.</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="budget_allocation_enabled" value="1" <?= !empty($settings['budget_allocation_enabled']) ? 'checked' : '' ?>>
        </label>

        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Activer la ventilation par projets</div>
                <div class="text-xs text-slate-500">Prépare l'activation des projets.</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="project_allocation_enabled" value="1" <?= !empty($settings['project_allocation_enabled']) ? 'checked' : '' ?>>
        </label>
    <?php endif; ?>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
</form>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
