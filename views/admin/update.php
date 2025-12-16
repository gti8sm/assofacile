<?php

$title = 'Mise à jour';
ob_start();
?>
<div class="max-w-2xl">
    <h1 class="text-2xl font-semibold">Mise à jour</h1>

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
        <div class="text-sm font-semibold">Migrations en attente</div>
        <div class="mt-2 text-sm text-slate-600">
            <?php if (empty($pending)): ?>
                Aucune. La base est à jour.
            <?php else: ?>
                <div class="font-mono text-xs bg-slate-50 border border-slate-200 rounded p-3">
                    <?php foreach ($pending as $m): ?>
                        <div><?= e((string)$m) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="post" class="mt-4">
            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
            <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Appliquer les mises à jour</button>
            <a class="ml-2 border border-slate-300 rounded px-3 py-2 text-sm" href="/dashboard">Retour</a>
        </form>

        <div class="mt-4 text-xs text-slate-500">
            Tant que la base n'est pas à jour, l'application peut être bloquée pour éviter des erreurs (ex: colonne manquante).
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
