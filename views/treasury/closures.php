<?php
$title = 'Clôtures (Trésorerie)';
$layoutMaxWidth = 'max-w-4xl';
ob_start();
?>
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Clôtures (Trésorerie)</h1>
        <div class="text-xs text-slate-500">Verrouille une période pour empêcher toute modification (saisie, suppression, rapprochement, justificatifs).</div>
    </div>
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

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
    <div class="font-semibold">Clôturer une période</div>
    <form method="post" action="<?= e(tenant_path('/treasury/closures/close')) ?>" class="mt-3 grid grid-cols-1 sm:grid-cols-6 gap-2">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">

        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-600">Début</label>
            <input name="start_date" type="date" value="<?= e((string)($defaultStart ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-600">Fin</label>
            <input name="end_date" type="date" value="<?= e((string)($defaultEnd ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-600">Motif (optionnel)</label>
            <input name="reason" value="" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
        </div>

        <div class="sm:col-span-6 flex items-center justify-end">
            <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit" onclick="return confirm('Clôturer cette période ?');">Clôturer</button>
        </div>
    </form>
</div>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <div class="p-4 border-b border-slate-200">
        <div class="font-semibold">Périodes clôturées</div>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Période</th>
            <th class="text-right p-3">Écritures</th>
            <th class="text-left p-3">Motif</th>
            <th class="text-left p-3">Clôturée le</th>
            <th class="text-left p-3">Par</th>
            <th class="text-right p-3">Action</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($closures)): ?>
            <tr>
                <td class="p-3 text-slate-500" colspan="6">Aucune clôture.</td>
            </tr>
        <?php else: ?>
            <?php foreach (($closures ?? []) as $c): ?>
                <tr class="border-t border-slate-200">
                    <td class="p-3">
                        <div class="font-medium"><?= e((string)($c['start_date'] ?? '')) ?> → <?= e((string)($c['end_date'] ?? '')) ?></div>
                        <div class="text-xs text-slate-500">#<?= e((string)($c['id'] ?? '')) ?></div>
                    </td>
                    <td class="p-3 text-right text-slate-700 whitespace-nowrap"><?= (int)($c['tx_count'] ?? 0) ?></td>
                    <td class="p-3"><?= e((string)($c['reason'] ?? '')) ?: '—' ?></td>
                    <td class="p-3"><?= e((string)($c['closed_at'] ?? '')) ?></td>
                    <td class="p-3"><?= e((string)($c['closed_by_name'] ?? '')) ?: ('#' . e((string)($c['closed_by_user_id'] ?? ''))) ?></td>
                    <td class="p-3 text-right whitespace-nowrap">
                        <form method="post" action="<?= e(tenant_path('/treasury/closures/delete')) ?>" class="inline">
                            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                            <input type="hidden" name="id" value="<?= e((string)($c['id'] ?? '0')) ?>">
                            <button class="border border-slate-300 rounded px-3 py-2 text-sm" type="submit" onclick="return confirm('Déclôturer cette période ?');">Déclôturer</button>
                        </form>
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
