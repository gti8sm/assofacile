<?php

$title = 'Modifier foyer';
ob_start();
?>
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Modifier foyer</h1>
        <div class="text-xs text-slate-500">#<?= e((string)$household['id']) ?></div>
    </div>
    <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/households">Retour</a>
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

<form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-4">
    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
    <input type="hidden" name="id" value="<?= e((string)$household['id']) ?>">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Nom du foyer</label>
            <input name="name" value="<?= e((string)($household['name'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Adresse</label>
            <textarea name="address" class="w-full border border-slate-300 rounded px-3 py-2" rows="3"><?= e((string)($household['address'] ?? '')) ?></textarea>
        </div>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
</form>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <div class="p-4 border-b border-slate-200">
        <div class="font-semibold">Membres du foyer</div>
        <div class="text-xs text-slate-500">Rattache un membre à ce foyer depuis sa fiche (Adhérents → Modifier → Foyer).</div>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Nom</th>
            <th class="text-left p-3">Rôle</th>
            <th class="text-right p-3">Action</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($members)): ?>
            <tr>
                <td class="p-3 text-slate-500" colspan="3">Aucun membre rattaché.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($members as $m): ?>
                <tr class="border-t border-slate-200">
                    <td class="p-3">
                        <div class="font-medium"><?= e(trim((string)($m['first_name'] ?? '') . ' ' . (string)($m['last_name'] ?? ''))) ?: '—' ?></div>
                        <div class="text-xs text-slate-500">#<?= e((string)$m['id']) ?></div>
                    </td>
                    <td class="p-3">
                        <span class="text-xs bg-slate-100 text-slate-700 px-2 py-1 rounded"><?= e((string)($m['relationship'] ?? 'adult')) ?></span>
                    </td>
                    <td class="p-3 text-right">
                        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/members/edit?id=<?= e((string)$m['id']) ?>">Ouvrir</a>
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
