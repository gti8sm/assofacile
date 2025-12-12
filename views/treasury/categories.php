<?php
$title = 'Catégories - Trésorerie';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Catégories</h1>
    <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/treasury">Retour</a>
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

<form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 flex gap-2">
    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
    <input name="name" class="flex-1 border border-slate-300 rounded px-3 py-2" placeholder="Ex: Achats" required>
    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Ajouter</button>
</form>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Nom</th>
            <th class="text-left p-3">Créée le</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <tr class="border-t border-slate-100">
                <td class="p-3"><?= e((string)$c['name']) ?></td>
                <td class="p-3 text-slate-600"><?= e((string)$c['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($categories)): ?>
            <tr class="border-t border-slate-100">
                <td class="p-3 text-slate-500" colspan="2">Aucune catégorie.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
