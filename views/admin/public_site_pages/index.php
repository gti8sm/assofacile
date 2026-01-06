<?php

$title = 'Admin - Site public';
ob_start();

$tenantSlug = (string)($tenantRow['slug'] ?? '');
$tenantKey = $tenantSlug !== '' ? $tenantSlug : (string)($tenantRow['id'] ?? '');
?>
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Site public</h1>
        <div class="text-xs text-slate-500"><?= e((string)($tenantRow['name'] ?? '')) ?></div>
    </div>
    <div class="flex items-center gap-2">
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/s/<?= e($tenantKey) ?>?preview=1" target="_blank">Voir le site</a>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/admin/public-site/domain">Domaine</a>
        <a class="bg-slate-900 text-white rounded px-3 py-2 text-sm" href="/admin/public-site/pages/new">Nouvelle page</a>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/admin/modules">Retour</a>
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

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Page</th>
            <th class="text-left p-3">Slug</th>
            <th class="text-left p-3">Statut</th>
            <th class="text-right p-3">Action</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr>
                <td class="p-3 text-slate-500" colspan="4">Aucune page.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($rows as $p): ?>
                <?php
                $isHome = !empty($p['is_home']);
                $isPublished = !empty($p['is_published']);
                $slug = (string)($p['slug'] ?? '');
                $publicUrl = $isHome ? ('/s/' . $tenantKey) : ('/s/' . $tenantKey . '/' . $slug);
                ?>
                <tr class="border-t border-slate-200">
                    <td class="p-3">
                        <div class="font-medium"><?= e((string)($p['title'] ?? '')) ?></div>
                        <?php if ($isHome): ?>
                            <div class="text-xs text-slate-500">Accueil</div>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 font-mono text-xs"><?= e($slug) ?></td>
                    <td class="p-3">
                        <?php if ($isPublished): ?>
                            <span class="text-emerald-700">Publiée</span>
                        <?php else: ?>
                            <span class="text-slate-600">Brouillon</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-right">
                        <a class="text-sm underline" href="/admin/public-site/pages/edit?id=<?= e((string)$p['id']) ?>">Éditer</a>
                        <?php if ($isPublished): ?>
                            <a class="ml-3 text-sm underline" href="<?= e($publicUrl) ?>" target="_blank">Voir</a>
                        <?php endif; ?>
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
