<?php
/** @var string $tenantName */
/** @var string $requestedPath */

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page introuvable</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
<header class="bg-white border-b border-slate-200">
    <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="font-semibold"><?= e($tenantName !== '' ? $tenantName : 'Site public') ?></div>
        <a class="text-sm text-slate-700 hover:text-slate-900" href="/login">Connexion</a>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-12">
    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <h1 class="text-2xl font-semibold">Page introuvable</h1>
        <p class="mt-2 text-slate-600">La page demandée n'existe pas ou n'est pas publiée.</p>
        <div class="mt-4 text-xs text-slate-500 font-mono"><?= e($requestedPath) ?></div>
        <?php
        $debugKey = App\Support\Env::get('PUBLIC_SITE_DEBUG_KEY');
        $debugEnabled = is_string($debugKey) && $debugKey !== ''
            && isset($_GET['debug'])
            && hash_equals($debugKey, (string)($_GET['debug'] ?? ''));
        $isAdmin = isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1;
        ?>
        <?php if (($isAdmin || $debugEnabled) && !empty($debugReason ?? null)): ?>
            <div class="mt-3 text-xs text-slate-500 font-mono">debug: <?= e((string)$debugReason) ?></div>
        <?php endif; ?>
        <a class="mt-5 inline-block border border-slate-300 rounded px-3 py-2 text-sm" href="/">Retour</a>
    </div>
</main>
</body>
</html>
