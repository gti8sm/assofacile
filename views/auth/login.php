<?php
$title = 'Connexion';
ob_start();
?>
<div class="max-w-md mx-auto bg-white border border-slate-200 rounded-lg p-6">
    <h1 class="text-xl font-semibold mb-4">Connexion</h1>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success ?? null)): ?>
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm border border-emerald-200">
            <?= e((string)$success) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input name="email" type="email" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Mot de passe</label>
            <input name="password" type="password" class="w-full border border-slate-300 rounded px-3 py-2" required>
        </div>
        <button class="w-full bg-slate-900 text-white rounded px-3 py-2" type="submit">Se connecter</button>
    </form>

    <p class="mt-4 text-xs text-slate-500">
        <?php if (!App\Support\Installer::isLocked()): ?>
            Première installation : lance <a class="underline" href="/install">/install</a>.
        <?php else: ?>
            Si c'est une première installation, lance d'abord les migrations SQL (voir README).
        <?php endif; ?>
    </p>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
