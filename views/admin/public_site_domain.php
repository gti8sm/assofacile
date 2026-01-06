<?php

$title = 'Admin - Domaine';
ob_start();

$domain = (string)($tenantRow['public_domain'] ?? '');
$status = (string)($tenantRow['public_domain_status'] ?? 'pending');
$token = (string)($tenantRow['public_domain_verification_token'] ?? '');
$verifiedAt = (string)($tenantRow['public_domain_verified_at'] ?? '');

$expectedTxt = $token !== '' ? ('assofacile-verify=' . $token) : '';
?>
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Domaine du site public</h1>
        <div class="text-xs text-slate-500"><?= e((string)($tenantRow['name'] ?? '')) ?></div>
    </div>
    <div class="flex items-center gap-2">
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/admin/public-site/pages">Retour</a>
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

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-4">
    <div class="text-sm text-slate-700">
        <div class="font-medium">Statut</div>
        <div class="mt-1">
            <?php if ($status === 'verified'): ?>
                <span class="text-emerald-700">Vérifié</span>
            <?php else: ?>
                <span class="text-slate-600">En attente</span>
            <?php endif; ?>
            <?php if ($verifiedAt !== ''): ?>
                <span class="text-xs text-slate-500">(<?= e($verifiedAt) ?>)</span>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" action="/admin/public-site/domain/save" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
        <div>
            <label class="block text-sm font-medium mb-1">Domaine (sans www)</label>
            <input name="public_domain" value="<?= e($domain) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="monasso.fr">
            <div class="mt-1 text-xs text-slate-500">Le site sera accessible sur ce domaine après vérification.</div>
        </div>
        <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
    </form>

    <?php if ($domain !== '' && $token !== ''): ?>
        <div class="border-t border-slate-200 pt-4 space-y-2">
            <div class="font-medium">Vérification DNS (TXT)</div>
            <div class="text-sm text-slate-700">Ajoute un enregistrement TXT sur <span class="font-mono"><?= e($domain) ?></span> avec la valeur :</div>
            <div class="p-2 bg-slate-50 border border-slate-200 rounded font-mono text-xs break-all"><?= e($expectedTxt) ?></div>

            <form method="post" action="/admin/public-site/domain/verify">
                <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                <button class="border border-slate-300 rounded px-3 py-2 text-sm" type="submit">Vérifier maintenant</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
