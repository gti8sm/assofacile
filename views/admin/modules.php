<?php
$title = 'Admin - Modules';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Modules</h1>
    <div class="flex items-center gap-2">
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/admin/diagnostic')) ?>">Diagnostic serveur</a>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/admin/site-settings')) ?>">Paramétrages généraux</a>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="mt-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm border border-emerald-200">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error = App\Support\Session::flash('error'))): ?>
    <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-4">
    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">

    <div class="space-y-3">
        <?php foreach ($modules as $m): ?>
            <?php $key = (string)$m['module_key']; ?>
            <div class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
                <div>
                    <div class="font-medium"><?= e((string)$m['name']) ?></div>
                    <div class="text-xs text-slate-500"><?= e($key) ?></div>
                </div>
                <div class="flex items-center gap-3">
                    <?php if (!empty($enabledByKey[$key])): ?>
                        <?php if ($key === 'public_site'): ?>
                            <a class="text-sm underline" href="<?= e(tenant_path('/admin/public-site/pages')) ?>">Paramètres</a>
                        <?php else: ?>
                            <a class="text-sm underline" href="<?= e(tenant_path('/admin/modules/settings?module=' . urlencode($key))) ?>">Paramètres</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <input
                        type="checkbox"
                        name="modules[<?= e($key) ?>]"
                        value="1"
                        class="h-5 w-5"
                        <?= !empty($enabledByKey[$key]) ? 'checked' : '' ?>
                    >
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
</form>

<?php if (!empty($enabledByKey['drive'])): ?>
    <div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
        <div class="font-semibold">Google Drive</div>
        <?php if (empty($driveConfigured)): ?>
            <p class="mt-1 text-sm text-slate-600">Non configuré côté serveur (variables GOOGLE_* manquantes ou Composer non installé).</p>
        <?php else: ?>
            <?php if (!empty($driveConnected)): ?>
                <p class="mt-1 text-sm text-emerald-700">Connecté</p>

                <?php
                $driveFolderId = App\Support\GoogleDrive::getDriveFolderId((int)($_SESSION['tenant_id'] ?? 0));
                ?>

                <form method="post" action="<?= e(tenant_path('/drive/folder')) ?>" class="mt-3">
                    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                    <label class="block text-sm font-medium mb-1">Folder ID (dossier cible)</label>
                    <input
                        name="drive_folder_id"
                        value="<?= e((string)($driveFolderId ?? '')) ?>"
                        class="w-full border border-slate-300 rounded px-3 py-2"
                        placeholder="Ex: 1AbCDefGhIJkLmNoPqRsTuVwXyZ..."
                        autocomplete="off"
                    >
                    <div class="mt-2 text-xs text-slate-500">
                        Laisser vide pour utiliser le dossier par défaut.
                    </div>
                    <button class="mt-3 bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer le dossier</button>
                </form>

                <form method="post" action="<?= e(tenant_path('/drive/disconnect')) ?>" class="mt-3">
                    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                    <button class="border border-slate-300 rounded px-3 py-2 text-sm" type="submit">Déconnecter</button>
                </form>
            <?php else: ?>
                <p class="mt-1 text-sm text-slate-600">Non connecté</p>
                <a class="mt-3 inline-block bg-slate-900 text-white rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/drive/connect')) ?>">Connecter Google Drive</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
