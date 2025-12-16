<?php

$title = 'Admin - Accès';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Accès par module</h1>
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

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
    <div class="text-sm text-slate-600">
        Les administrateurs ont accès à tout. Si aucun droit n'est configuré pour l'association, les modules activés restent accessibles (mode compatibilité).
    </div>
</div>

<form method="post" class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">

    <div class="overflow-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
            <tr>
                <th class="text-left p-3">Utilisateur</th>
                <?php foreach ($modules as $m): ?>
                    <?php $key = (string)$m['module_key']; ?>
                    <th class="text-left p-3">
                        <div class="font-medium"><?= e((string)$m['name']) ?></div>
                        <div class="text-xs text-slate-500"><?= e($key) ?><?= empty($enabledByKey[$key]) ? ' (désactivé)' : '' ?></div>
                    </th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <?php $uid = (int)$u['id']; ?>
                <tr class="border-t border-slate-200">
                    <td class="p-3 align-top">
                        <div class="font-medium">
                            <?= e((string)($u['full_name'] ?: $u['email'])) ?>
                            <?php if ((int)$u['is_admin'] === 1): ?>
                                <span class="ml-2 text-xs bg-slate-900 text-white px-2 py-0.5 rounded">admin</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-slate-500"><?= e((string)$u['email']) ?></div>
                        <div class="text-xs text-slate-500">role: <?= e((string)$u['role']) ?></div>
                        <?php if ((int)$u['is_active'] !== 1): ?>
                            <div class="text-xs text-red-600">inactif</div>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($modules as $m): ?>
                        <?php
                        $key = (string)$m['module_key'];
                        $p = $perm[$uid][$key] ?? ['read' => false, 'write' => false];
                        ?>
                        <td class="p-3 align-top">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" class="h-4 w-4" name="perm[<?= e((string)$uid) ?>][<?= e($key) ?>][read]" value="1" <?= !empty($p['read']) ? 'checked' : '' ?>>
                                <span>Lecture</span>
                            </label>
                            <label class="mt-1 flex items-center gap-2">
                                <input type="checkbox" class="h-4 w-4" name="perm[<?= e((string)$uid) ?>][<?= e($key) ?>][write]" value="1" <?= !empty($p['write']) ? 'checked' : '' ?>>
                                <span>Écriture</span>
                            </label>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="p-4 border-t border-slate-200">
        <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
        <a class="ml-2 border border-slate-300 rounded px-3 py-2 text-sm" href="/admin/modules">Retour modules</a>
    </div>
</form>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
