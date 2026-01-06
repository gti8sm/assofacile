<?php

$title = 'Tiers';
ob_start();

$q = (string)($_GET['q'] ?? '');
$tag = (string)($_GET['tag'] ?? '');
?>
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Tiers</h1>
        <div class="text-xs text-slate-500">Adhérents, partenaires, fournisseurs</div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'treasury', 'write')): ?>
            <a class="bg-slate-900 text-white rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/tiers/new')) ?>">Nouveau tiers</a>
        <?php endif; ?>
        <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'members', 'write')): ?>
            <form method="post" action="<?= e(tenant_path('/tiers/sync-members')) ?>" onsubmit="return confirm('Synchroniser tous les adhérents en tiers ?');">
                <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                <button class="border border-slate-300 rounded px-3 py-2 text-sm bg-white hover:bg-slate-50" type="submit">Synchroniser adhérents → tiers</button>
            </form>
        <?php endif; ?>
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

<form method="get" class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Recherche</label>
            <input name="q" value="<?= e($q) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Nom du tiers">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Type</label>
            <select name="tag" class="w-full border border-slate-300 rounded px-3 py-2">
                <option value="" <?= $tag === '' ? 'selected' : '' ?>>Tous</option>
                <option value="adherent" <?= $tag === 'adherent' ? 'selected' : '' ?>>Adhérents</option>
                <option value="partenaire" <?= $tag === 'partenaire' ? 'selected' : '' ?>>Partenaires</option>
                <option value="fournisseur" <?= $tag === 'fournisseur' ? 'selected' : '' ?>>Fournisseurs</option>
            </select>
        </div>
    </div>
    <button class="mt-3 border border-slate-300 rounded px-3 py-2 text-sm" type="submit">Filtrer</button>
</form>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Nom</th>
            <th class="text-left p-3">Type</th>
            <th class="text-left p-3">Lien</th>
            <th class="text-right p-3">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($tiers)): ?>
            <tr>
                <td class="p-3 text-slate-500" colspan="4">Aucun tiers.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($tiers as $t): ?>
                <?php
                $tid = (int)($t['id'] ?? 0);
                $tagsRaw = (string)($t['tags'] ?? '');
                $tags = $tagsRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $tagsRaw)))) : [];
                $isAdherent = in_array('adherent', $tags, true);
                $isPartenaire = in_array('partenaire', $tags, true);
                $isFournisseur = in_array('fournisseur', $tags, true);

                $badges = [];
                if ($isAdherent) $badges[] = ['adherent', 'bg-emerald-50 text-emerald-700'];
                if ($isPartenaire) $badges[] = ['partenaire', 'bg-slate-100 text-slate-700'];
                if ($isFournisseur) $badges[] = ['fournisseur', 'bg-amber-50 text-amber-800'];
                ?>
                <tr class="border-t border-slate-200">
                    <td class="p-3">
                        <div class="font-medium"><?= e((string)($t['name'] ?? '')) ?></div>
                        <div class="text-xs text-slate-500">#<?= e((string)$tid) ?></div>
                    </td>
                    <td class="p-3">
                        <div class="flex flex-wrap gap-1">
                            <?php if (empty($badges)): ?>
                                <span class="text-xs bg-slate-100 text-slate-700 px-2 py-1 rounded">—</span>
                            <?php else: ?>
                                <?php foreach ($badges as $b): ?>
                                    <span class="text-xs px-2 py-1 rounded <?= e($b[1]) ?>"><?= e($b[0]) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="p-3">
                        <?php if (!empty($t['member_id'])): ?>
                            <a class="text-sm underline text-slate-700 hover:text-slate-900" href="<?= e(tenant_path('/members/edit?id=' . (int)$t['member_id'])) ?>">Fiche adhérent</a>
                        <?php else: ?>
                            <span class="text-xs text-slate-500">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-right">
                        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/tiers/view?id=' . $tid)) ?>">Ouvrir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
