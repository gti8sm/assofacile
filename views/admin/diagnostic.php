<?php
$title = 'Admin - Diagnostic serveur';
ob_start();

$mask = static function (string $v): string {
    $v = trim($v);
    if ($v === '') {
        return '';
    }
    $len = mb_strlen($v);
    if ($len <= 8) {
        return str_repeat('*', $len);
    }
    return mb_substr($v, 0, 4) . str_repeat('*', max(0, $len - 8)) . mb_substr($v, -4);
};

$present = static function (string $v): string {
    return trim($v) !== '' ? 'OK' : 'Manquante';
};
?>

<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Diagnostic serveur</h1>
    <div class="flex items-center gap-2">
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/admin/license')) ?>">Licence</a>
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/admin/modules')) ?>">Modules</a>
    </div>
</div>

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
    <div class="text-sm font-semibold">Google Drive (variables serveur)</div>
    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
        <div class="text-slate-500">GOOGLE_CLIENT_ID</div>
        <div class="font-mono">
            <span class="inline-block px-2 py-1 rounded text-xs <?= trim($google['client_id'] ?? '') !== '' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                <?= e($present((string)($google['client_id'] ?? ''))) ?>
            </span>
            <?php if (trim((string)($google['client_id'] ?? '')) !== ''): ?>
                <span class="ml-2 text-xs text-slate-500"><?= e($mask((string)$google['client_id'])) ?></span>
            <?php endif; ?>
        </div>

        <div class="text-slate-500">GOOGLE_CLIENT_SECRET</div>
        <div class="font-mono">
            <span class="inline-block px-2 py-1 rounded text-xs <?= trim($google['client_secret'] ?? '') !== '' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                <?= e($present((string)($google['client_secret'] ?? ''))) ?>
            </span>
            <?php if (trim((string)($google['client_secret'] ?? '')) !== ''): ?>
                <span class="ml-2 text-xs text-slate-500"><?= e($mask((string)$google['client_secret'])) ?></span>
            <?php endif; ?>
        </div>

        <div class="text-slate-500">GOOGLE_REDIRECT_URI</div>
        <div class="font-mono">
            <span class="inline-block px-2 py-1 rounded text-xs <?= trim($google['redirect_uri'] ?? '') !== '' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                <?= e($present((string)($google['redirect_uri'] ?? ''))) ?>
            </span>
            <?php if (trim((string)($google['redirect_uri'] ?? '')) !== ''): ?>
                <span class="ml-2 text-xs text-slate-500"><?= e((string)$google['redirect_uri']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-4">
        <a class="border border-slate-300 rounded px-3 py-2 text-sm inline-block" href="<?= e(tenant_path('/admin/diagnostic/env-template')) ?>">Télécharger .env.template</a>
        <div class="mt-2 text-xs text-slate-500">
            Ce fichier est un template à copier sur le serveur. L'application n'écrit pas de .env depuis le web.
        </div>
    </div>
</div>

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
    <div class="text-sm font-semibold">État Drive (tenant)</div>
    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
        <div class="text-slate-500">Librairies Google (Composer)</div>
        <div>
            <span class="inline-block px-2 py-1 rounded text-xs <?= !empty($driveAvailable) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                <?= !empty($driveAvailable) ? 'OK' : 'Manquantes' ?>
            </span>
        </div>

        <div class="text-slate-500">Drive configuré (GOOGLE_* + libs)</div>
        <div>
            <span class="inline-block px-2 py-1 rounded text-xs <?= !empty($driveConfigured) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                <?= !empty($driveConfigured) ? 'OK' : 'Incomplet' ?>
            </span>
        </div>

        <div class="text-slate-500">Drive connecté (tenant)</div>
        <div>
            <span class="inline-block px-2 py-1 rounded text-xs <?= !empty($driveConnected) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-50 text-slate-700 border border-slate-200' ?>">
                <?= !empty($driveConnected) ? 'Oui' : 'Non' ?>
            </span>
        </div>

        <div class="text-slate-500">Folder ID</div>
        <div class="font-mono text-xs text-slate-700">
            <?= e((string)($driveFolderId ?? '')) ?>
        </div>
    </div>
</div>

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
    <div class="text-sm font-semibold">Licence (tenant)</div>
    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
        <div class="text-slate-500">Statut</div>
        <div class="font-medium"><?= e((string)($license['status'] ?? 'unknown')) ?></div>

        <div class="text-slate-500">Plan</div>
        <div class="font-medium"><?= e((string)($license['plan_type'] ?? '-')) ?></div>

        <div class="text-slate-500">Valide jusqu'au</div>
        <div class="font-medium"><?= e((string)($license['valid_until'] ?? '-')) ?></div>

        <div class="text-slate-500">Grâce jusqu'au</div>
        <div class="font-medium"><?= e((string)($license['grace_until'] ?? '-')) ?></div>
    </div>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
