<?php

$title = 'Modifier groupe enfants';
ob_start();
?>
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Modifier groupe enfants</h1>
        <div class="text-xs text-slate-500">#<?= e((string)$group['id']) ?></div>
    </div>
    <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/child-groups">Retour</a>
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
    <input type="hidden" name="id" value="<?= e((string)$group['id']) ?>">

    <div>
        <label class="block text-sm font-medium mb-1">Nom</label>
        <input name="name" value="<?= e((string)($group['name'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <div class="font-semibold">Enfants</div>
            <div class="mt-2 space-y-2 max-h-72 overflow-auto border border-slate-200 rounded p-2">
                <?php if (empty($children)): ?>
                    <div class="text-sm text-slate-500">Aucun enfant.</div>
                <?php else: ?>
                    <?php foreach ($children as $c): ?>
                        <?php $cid = (int)$c['id']; ?>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" class="h-4 w-4" name="children[<?= e((string)$cid) ?>]" value="1" <?= !empty($childIds[$cid]) ? 'checked' : '' ?>>
                            <span><?= e(trim((string)($c['first_name'] ?? '') . ' ' . (string)($c['last_name'] ?? ''))) ?: ('#' . e((string)$cid)) ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="font-semibold">Encadrants (staff)</div>
            <div class="mt-2 space-y-2 max-h-72 overflow-auto border border-slate-200 rounded p-2">
                <?php if (empty($users)): ?>
                    <div class="text-sm text-slate-500">Aucun utilisateur.</div>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <?php $uid = (int)$u['id']; ?>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" class="h-4 w-4" name="staff[<?= e((string)$uid) ?>]" value="1" <?= !empty($staffIds[$uid]) ? 'checked' : '' ?>>
                            <span>
                                <?= e((string)($u['email'] ?? '')) ?>
                                <?php if (!empty($u['full_name'])): ?>
                                    <span class="text-xs text-slate-500">(<?= e((string)$u['full_name']) ?>)</span>
                                <?php endif; ?>
                                <?php if (!empty($u['is_admin'])): ?>
                                    <span class="text-xs text-slate-500">admin</span>
                                <?php elseif (!empty($u['role'])): ?>
                                    <span class="text-xs text-slate-500"><?= e((string)$u['role']) ?></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
</form>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
