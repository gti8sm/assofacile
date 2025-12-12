<?php
$title = 'Justificatifs - Trésorerie';
ob_start();
?>
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Justificatifs</h1>
        <p class="mt-1 text-sm text-slate-600">
            <?= e((string)$transaction['occurred_on']) ?> — <?= e((string)$transaction['label']) ?> (<?= e((string)$transaction['type']) ?>)
        </p>
    </div>
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

<form method="post" enctype="multipart/form-data" class="mt-4 bg-white border border-slate-200 rounded-lg p-4 space-y-3">
    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
    <input type="hidden" name="transaction_id" value="<?= e((string)$transaction['id']) ?>">

    <div>
        <label class="block text-sm font-medium mb-1">Ajouter des fichiers (jpg/png/pdf, 10 Mo max)</label>
        <input type="file" name="attachments[]" multiple accept="image/jpeg,image/png,application/pdf" class="w-full">
        <?php if (App\Support\Modules::isEnabled((int)$_SESSION['tenant_id'], 'drive') && App\Support\GoogleDrive::isConfigured() && App\Support\GoogleDrive::isAvailable()): ?>
            <?php if (App\Support\GoogleDrive::isConnected((int)$_SESSION['tenant_id'])): ?>
                <label class="mt-2 flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="store_driver" value="gdrive">
                    Stocker sur Google Drive
                </label>
            <?php else: ?>
                <p class="mt-1 text-xs text-slate-500">Google Drive activé mais non connecté (Admin → Connecter).</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Uploader</button>
</form>

<div class="mt-4 bg-white border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
        <tr>
            <th class="text-left p-3">Fichier</th>
            <th class="text-left p-3">Type</th>
            <th class="text-right p-3">Taille</th>
            <th class="text-right p-3">Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($attachments as $a): ?>
            <tr class="border-t border-slate-100">
                <td class="p-3"><?= e((string)$a['original_name']) ?></td>
                <td class="p-3 text-slate-600"><?= e((string)$a['mime_type']) ?></td>
                <td class="p-3 text-right text-slate-600"><?= number_format(((int)$a['size_bytes']) / 1024, 0, ',', ' ') ?> Ko</td>
                <td class="p-3 text-right">
                    <a class="border border-slate-300 rounded px-2 py-1 text-xs" href="/treasury/attachment/download?id=<?= e((string)$a['id']) ?>">Télécharger</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($attachments)): ?>
            <tr class="border-t border-slate-100">
                <td class="p-3 text-slate-500" colspan="4">Aucun justificatif.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
