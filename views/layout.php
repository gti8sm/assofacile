<?php
/** @var string $title */
/** @var string $content */

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
<nav class="bg-white border-b border-slate-200">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="/dashboard" class="font-semibold">AssoFacile</a>
        <div class="flex items-center gap-3">
            <?php if (isset($_SESSION['tenant_id'], $_SESSION['user_id']) && App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'members', 'read')): ?>
                <a href="/members" class="text-sm text-slate-700 hover:text-slate-900">Adhérents</a>
                <a href="/households" class="text-sm text-slate-700 hover:text-slate-900">Familles</a>
                <a href="/child-groups" class="text-sm text-slate-700 hover:text-slate-900">Groupes enfants</a>
                <?php if (App\Support\ModuleSettings::getBool((int)$_SESSION['tenant_id'], 'members', 'memberships_enabled', true)): ?>
                    <a href="/memberships/products" class="text-sm text-slate-700 hover:text-slate-900">Cotisations</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['tenant_id'], $_SESSION['user_id']) && App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'treasury', 'read')): ?>
                <a href="/treasury" class="text-sm text-slate-700 hover:text-slate-900">Trésorerie</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1): ?>
                <a href="/admin/modules" class="text-sm text-slate-700 hover:text-slate-900">Admin</a>
                <a href="/admin/access" class="text-sm text-slate-700 hover:text-slate-900">Accès</a>
                <a href="/admin/license" class="text-sm text-slate-700 hover:text-slate-900">Licence</a>
            <?php endif; ?>
            <a href="/changelog" class="text-sm text-slate-700 hover:text-slate-900">Changelog</a>
            <a href="/roadmap" class="text-sm text-slate-700 hover:text-slate-900">Roadmap</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="post" action="/logout">
                    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                    <button class="text-sm text-slate-700 hover:text-slate-900" type="submit">Déconnexion</button>
                </form>
            <?php else: ?>
                <a href="/login" class="text-sm text-slate-700 hover:text-slate-900">Connexion</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="max-w-5xl mx-auto px-4 py-6">
    <?= $content ?>
</main>
</body>
</html>
