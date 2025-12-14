<?php

$title = 'Installation';
ob_start();
?>
<div class="max-w-2xl mx-auto bg-white border border-slate-200 rounded-lg p-6">
    <h1 class="text-xl font-semibold mb-2">Installation (serveur de licences)</h1>
    <p class="text-sm text-slate-600 mb-4">Configure la base et crée le compte administrateur.</p>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="space-y-6">
        <input type="hidden" name="_csrf" value="<?= e(Licensing\Support\Csrf::token()) ?>">

        <div>
            <h2 class="text-sm font-semibold mb-2">Base de données</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Hôte</label>
                    <input name="db_host" value="<?= e((string)$data['db_host']) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Port</label>
                    <input name="db_port" value="<?= e((string)$data['db_port']) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Base</label>
                    <input name="db_name" value="<?= e((string)$data['db_name']) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Utilisateur</label>
                    <input name="db_user" value="<?= e((string)$data['db_user']) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Mot de passe</label>
                    <input name="db_pass" value="<?= e((string)$data['db_pass']) ?>" type="password" class="w-full border border-slate-300 rounded px-3 py-2">
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold mb-2">Administrateur</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input name="admin_email" type="email" value="<?= e((string)$data['admin_email']) ?>" class="w-full border border-slate-300 rounded px-3 py-2" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Nom complet (optionnel)</label>
                    <input name="admin_name" value="<?= e((string)$data['admin_name']) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Mot de passe</label>
                    <input name="admin_password" type="password" class="w-full border border-slate-300 rounded px-3 py-2" required>
                    <p class="mt-1 text-xs text-slate-500">8 caractères minimum.</p>
                </div>
            </div>
        </div>

        <button class="w-full bg-slate-900 text-white rounded px-3 py-2" type="submit">Installer</button>
    </form>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
