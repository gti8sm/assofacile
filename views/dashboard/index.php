<?php
$title = 'Dashboard';
ob_start();

$fmtCents = static function (?int $cents): string {
    if ($cents === null) {
        return '—';
    }
    $euros = $cents / 100;
    return number_format($euros, 2, ',', ' ') . ' €';
};
?>
<h1 class="text-2xl font-semibold">Dashboard</h1>
<p class="mt-2 text-slate-600">Association : <span class="font-medium"><?= e((string)($tenant['name'] ?? '—')) ?></span></p>

<?php if (!empty($quickActions) && is_array($quickActions)): ?>
    <div class="mt-4 flex flex-wrap gap-2">
        <?php foreach ($quickActions as $qa): ?>
            <?php if (is_array($qa) && !empty($qa['href']) && !empty($qa['label'])): ?>
                <a class="bg-slate-900 text-white rounded px-3 py-2 text-sm" href="<?= e(tenant_path((string)$qa['href'])) ?>"><?= e((string)$qa['label']) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($stats)): ?>
    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php if (array_key_exists('members_active', $stats)): ?>
            <div class="bg-white border border-slate-200 rounded-lg p-4">
                <div class="text-xs text-slate-500">Adhérents actifs</div>
                <div class="mt-1 text-2xl font-semibold"><?= e((string)($stats['members_active'] ?? 0)) ?></div>
            </div>
        <?php endif; ?>

        <?php if (array_key_exists('households', $stats) && $stats['households'] !== null): ?>
            <div class="bg-white border border-slate-200 rounded-lg p-4">
                <div class="text-xs text-slate-500">Familles</div>
                <div class="mt-1 text-2xl font-semibold"><?= e((string)($stats['households'] ?? 0)) ?></div>
            </div>
        <?php endif; ?>

        <?php if (array_key_exists('memberships_paid', $stats) && $stats['memberships_paid'] !== null): ?>
            <div class="bg-white border border-slate-200 rounded-lg p-4">
                <div class="text-xs text-slate-500">Cotisations payées</div>
                <div class="mt-1 text-2xl font-semibold"><?= e((string)($stats['memberships_paid'] ?? 0)) ?></div>
                <?php if (array_key_exists('memberships_pending', $stats) && $stats['memberships_pending'] !== null): ?>
                    <div class="mt-1 text-xs text-slate-500">En attente: <?= e((string)($stats['memberships_pending'] ?? 0)) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (array_key_exists('treasury_balance_total_cents', $stats) && $stats['treasury_balance_total_cents'] !== null): ?>
            <div class="bg-white border border-slate-200 rounded-lg p-4">
                <div class="text-xs text-slate-500">Solde trésorerie</div>
                <div class="mt-1 text-2xl font-semibold"><?= e($fmtCents((int)$stats['treasury_balance_total_cents'])) ?></div>
                <?php if (array_key_exists('treasury_balance_month_cents', $stats) && $stats['treasury_balance_month_cents'] !== null): ?>
                    <div class="mt-1 text-xs text-slate-500">Ce mois: <?= e($fmtCents((int)$stats['treasury_balance_month_cents'])) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
    <?php if (App\Support\Modules::isEnabled((int)$_SESSION['tenant_id'], 'treasury')): ?>
        <a href="<?= e(tenant_path('/treasury')) ?>" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-slate-300">
            <div class="font-semibold">Trésorerie</div>
            <div class="text-sm text-slate-600">Dépenses, recettes, justificatifs (MVP)</div>
        </a>
    <?php endif; ?>
    <a href="<?= e(tenant_path('/changelog')) ?>" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-slate-300">
        <div class="font-semibold">Changelog</div>
        <div class="text-sm text-slate-600">Historique des versions et mises à jour</div>
    </a>
</div>

<?php if (!empty($recent) && is_array($recent) && (!empty($recent['members']) || !empty($recent['treasury']) || !empty($recent['memberships']))): ?>
    <div class="mt-8">
        <h2 class="text-lg font-semibold">Activité récente</h2>
        <div class="mt-3 grid grid-cols-1 lg:grid-cols-3 gap-4">
            <?php if (!empty($recent['members']) && is_array($recent['members'])): ?>
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <div class="font-medium">Derniers adhérents</div>
                    <div class="mt-3 space-y-2">
                        <?php foreach ($recent['members'] as $m): ?>
                            <?php if (is_array($m)): ?>
                                <div class="text-sm flex items-center justify-between gap-3">
                                    <a class="hover:text-[color:var(--site-primary)]" href="<?= e(tenant_path('/members/edit?id=' . (string)($m['id'] ?? 0))) ?>">
                                        <?= e(trim((string)($m['first_name'] ?? '') . ' ' . (string)($m['last_name'] ?? ''))) ?>
                                    </a>
                                    <span class="text-xs text-slate-500"><?= e((string)($m['status'] ?? '')) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($recent['treasury']) && is_array($recent['treasury'])): ?>
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <div class="font-medium">Dernières transactions</div>
                    <div class="mt-3 space-y-2">
                        <?php foreach ($recent['treasury'] as $t): ?>
                            <?php if (is_array($t)): ?>
                                <?php
                                $type = (string)($t['type'] ?? '');
                                $amount = (int)($t['amount_cents'] ?? 0);
                                $sign = $type === 'income' ? '+' : '-';
                                ?>
                                <div class="text-sm flex items-center justify-between gap-3">
                                    <a class="hover:text-[color:var(--site-primary)]" href="<?= e(tenant_path('/treasury')) ?>">
                                        <?= e((string)($t['label'] ?? '')) ?>
                                    </a>
                                    <span class="text-xs font-mono <?= $type === 'income' ? 'text-emerald-700' : 'text-rose-700' ?>"><?= e($sign . ' ' . $fmtCents($amount)) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($recent['memberships']) && is_array($recent['memberships'])): ?>
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <div class="font-medium">Dernières cotisations</div>
                    <div class="mt-3 space-y-2">
                        <?php foreach ($recent['memberships'] as $s): ?>
                            <?php if (is_array($s)): ?>
                                <?php
                                $status = (string)($s['status'] ?? '');
                                $amount = (int)($s['amount_cents'] ?? 0);
                                $householdName = trim((string)($s['household_name'] ?? ''));
                                $memberName = trim((string)($s['member_name'] ?? ''));
                                $memberId = (int)($s['member_id'] ?? 0);
                                $householdId = (int)($s['household_id'] ?? 0);
                                ?>
                                <div class="text-sm flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <?php if ($householdId > 0 && $householdName !== ''): ?>
                                            <a class="block truncate hover:text-[color:var(--site-primary)]" href="<?= e(tenant_path('/households/edit?id=' . (string)$householdId)) ?>">
                                                <?= e($householdName) ?>
                                            </a>
                                        <?php elseif ($memberId > 0 && $memberName !== ''): ?>
                                            <a class="block truncate hover:text-[color:var(--site-primary)]" href="<?= e(tenant_path('/members/edit?id=' . (string)$memberId)) ?>">
                                                <?= e($memberName) ?>
                                            </a>
                                        <?php else: ?>
                                            <a class="block truncate hover:text-[color:var(--site-primary)]" href="<?= e(tenant_path('/memberships/products')) ?>">
                                                Cotisation
                                            </a>
                                        <?php endif; ?>
                                        <div class="text-xs text-slate-500 truncate"><?= e($status) ?></div>
                                    </div>
                                    <a class="shrink-0 text-xs font-mono hover:text-[color:var(--site-primary)]" href="<?= e(tenant_path('/memberships/products')) ?>"><?= e($fmtCents($amount)) ?></a>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
