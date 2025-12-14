<?php

$title = 'Licence';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Licence</h1>
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
    <form method="post" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">

        <div>
            <label class="block text-sm font-medium mb-1">Clé de licence</label>
            <input name="license_key" value="<?= e((string)($license['license_key'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>

        <button class="bg-slate-900 text-white rounded px-3 py-2" type="submit">Enregistrer et vérifier</button>
    </form>

    <div class="mt-6 text-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div class="text-slate-500">Statut</div>
            <div class="font-medium"><?= e((string)($license['status'] ?? 'unknown')) ?></div>

            <div class="text-slate-500">Plan</div>
            <div class="font-medium"><?= e((string)($license['plan_type'] ?? '-')) ?></div>

            <div class="text-slate-500">Valide jusqu'au</div>
            <div class="font-medium"><?= e((string)($license['valid_until'] ?? '-')) ?></div>

            <div class="text-slate-500">Grâce jusqu'au</div>
            <div class="font-medium"><?= e((string)($license['grace_until'] ?? '-')) ?></div>

            <div class="text-slate-500">Dernière vérification</div>
            <div class="font-medium"><?= e((string)($license['last_checked_at'] ?? '-')) ?></div>

            <div class="text-slate-500">Dernière erreur</div>
            <div class="font-medium"><?= e((string)($license['last_error'] ?? '-')) ?></div>

            <div class="text-slate-500">Token valide jusqu'au</div>
            <div class="font-medium"><?= e((string)($license['token_valid_until'] ?? '-')) ?></div>

            <div class="text-slate-500">Token émis le</div>
            <div class="font-medium"><?= e((string)($license['token_issued_at'] ?? '-')) ?></div>
        </div>

        <p class="mt-4 text-xs text-slate-500">
            Les modules payants (ex: Google Drive) sont bloqués si la licence n'est pas active et hors période de grâce.
        </p>
    </div>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
