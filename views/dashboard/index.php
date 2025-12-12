<?php
$title = 'Dashboard';
ob_start();
?>
<h1 class="text-2xl font-semibold">Dashboard</h1>
<p class="mt-2 text-slate-600">Association : <span class="font-medium"><?= e((string)($tenant['name'] ?? '—')) ?></span></p>

<div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
    <?php if (App\Support\Modules::isEnabled((int)$_SESSION['tenant_id'], 'treasury')): ?>
        <a href="/treasury" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-slate-300">
            <div class="font-semibold">Trésorerie</div>
            <div class="text-sm text-slate-600">Dépenses, recettes, justificatifs (MVP)</div>
        </a>
    <?php endif; ?>
    <a href="/changelog" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-slate-300">
        <div class="font-semibold">Changelog</div>
        <div class="text-sm text-slate-600">Historique des versions et mises à jour</div>
    </a>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
