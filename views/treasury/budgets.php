<?php
$title = 'Budgets - Trésorerie';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Budgets</h1>
    <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/treasury')) ?>">Retour</a>
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
    <input name="name" class="flex-1 border border-slate-300 rounded px-3 py-2" placeholder="Ex: Communication" required>
    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Ajouter</button>
</form>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Nom</th>
            <th class="text-left p-3">Projet</th>
            <th class="text-right p-3">Écritures</th>
            <th class="text-left p-3">Statut</th>
            <th class="text-left p-3">Créé le</th>
            <th class="text-right p-3">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach (($budgets ?? []) as $b): ?>
            <?php
            $id = (int)($b['id'] ?? 0);
            $isActive = ((int)($b['is_active'] ?? 1) === 1);
            $usage = (int)($usageCountById[$id] ?? 0);
            $linked = is_array($budgetProjectsByBudgetId[$id] ?? null) ? $budgetProjectsByBudgetId[$id] : [];
            ?>
            <tr class="border-t border-slate-100">
                <td class="p-3 font-medium"><?= e((string)($b['name'] ?? '')) ?></td>
                <td class="p-3">
                    <div class="space-y-2">
                        <?php if (!empty($linked)): ?>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($linked as $lp): ?>
                                    <?php
                                    $lpId = (int)($lp['id'] ?? 0);
                                    $lpName = (string)($lp['name'] ?? '');
                                    ?>
                                    <span class="inline-flex items-center gap-2 text-xs px-2 py-1 rounded bg-slate-50 border border-slate-200">
                                        <span><?= e($lpName !== '' ? $lpName : ('#' . (string)$lpId)) ?></span>
                                        <form method="post" action="<?= e(tenant_path('/treasury/budgets/unlink-project')) ?>">
                                            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                            <input type="hidden" name="budget_id" value="<?= $id ?>">
                                            <input type="hidden" name="project_id" value="<?= $lpId ?>">
                                            <button class="text-slate-500 hover:text-slate-900" type="submit">×</button>
                                        </form>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?= e(tenant_path('/treasury/budgets/link-project')) ?>" class="flex items-center gap-2">
                            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                            <input type="hidden" name="budget_id" value="<?= $id ?>">
                            <select name="project_id" class="border border-slate-300 rounded px-2 py-1 text-xs" required>
                                <option value="">Ajouter un projet…</option>
                                <?php foreach (($projects ?? []) as $p): ?>
                                    <?php $pid = (int)($p['id'] ?? 0); ?>
                                    <?php if ($pid <= 0) { continue; } ?>
                                    <option value="<?= $pid ?>"><?= e((string)($p['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="border border-slate-300 rounded px-2 py-1 text-xs" type="submit">OK</button>
                        </form>
                    </div>
                </td>
                <td class="p-3 text-right text-slate-700"><?= $usage ?></td>
                <td class="p-3">
                    <span class="text-xs px-2 py-1 rounded <?= $isActive ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-50 text-slate-700 border border-slate-200' ?>">
                        <?= $isActive ? 'Actif' : 'Archivé' ?>
                    </span>
                </td>
                <td class="p-3 text-slate-600"><?= e((string)($b['created_at'] ?? '')) ?></td>
                <td class="p-3">
                    <div class="flex justify-end gap-2 flex-wrap">
                        <?php if ($isActive): ?>
                            <form method="post" action="<?= e(tenant_path('/treasury/budgets/archive')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button class="border border-slate-300 rounded px-2 py-1 text-xs" type="submit">Archiver</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(tenant_path('/treasury/budgets/unarchive')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button class="border border-slate-300 rounded px-2 py-1 text-xs" type="submit">Réactiver</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($usage > 0): ?>
                            <form method="post" action="<?= e(tenant_path('/treasury/budgets/transfer')) ?>" class="flex items-center gap-2">
                                <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                <input type="hidden" name="from_budget_id" value="<?= $id ?>">
                                <select name="to_budget_id" class="border border-slate-300 rounded px-2 py-1 text-xs" required>
                                    <option value="">Transférer vers…</option>
                                    <?php foreach (($budgets ?? []) as $b2): ?>
                                        <?php
                                        $id2 = (int)($b2['id'] ?? 0);
                                        if ($id2 <= 0 || $id2 === $id) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?= $id2 ?>"><?= e((string)($b2['name'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="border border-slate-300 rounded px-2 py-1 text-xs" type="submit" onclick="return confirm('Transférer toutes les allocations vers ce budget puis archiver le budget source ?');">OK</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(tenant_path('/treasury/budgets/delete')) ?>" onsubmit="return confirm('Supprimer ce budget ?');">
                                <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button class="border border-red-300 text-red-700 rounded px-2 py-1 text-xs" type="submit">Supprimer</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($budgets)): ?>
            <tr class="border-t border-slate-100">
                <td class="p-3 text-slate-500" colspan="6">Aucun budget.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
