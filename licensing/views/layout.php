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
        <a href="/licenses" class="font-semibold">AssoFacile Licences</a>
        <div class="flex items-center gap-3">
            <?php if (isset($_SESSION['admin_id'])): ?>
                <form method="post" action="/logout">
                    <input type="hidden" name="_csrf" value="<?= e(Licensing\Support\Csrf::token()) ?>">
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
