<?php

$title = 'Carte d\'adhérent';

$fmtCents = static function (?int $cents): string {
    if ($cents === null) {
        return '—';
    }
    $euros = $cents / 100;
    return number_format($euros, 2, ',', ' ') . ' €';
};

$memberName = trim((string)($subscription['member_name'] ?? ''));
$householdName = trim((string)($subscription['household_name'] ?? ''));
$holderLabel = $householdName !== '' ? $householdName : $memberName;
if ($holderLabel === '') {
    $holderLabel = '—';
}

$productLabel = trim((string)($subscription['product_label'] ?? ''));
if ($productLabel === '') {
    $productLabel = 'Cotisation';
}

$period = trim((string)($subscription['start_date'] ?? ''));
$end = trim((string)($subscription['end_date'] ?? ''));
if ($end !== '') {
    $period .= ' → ' . $end;
}

$cardNumber = (int)($subscription['id'] ?? 0);
$brandName = (string)($site['brand_name'] ?? 'AssoFacile');
$logoUrl = (string)($site['logo_url'] ?? '');
$primaryColor = (string)($site['primary_color'] ?? '#0f172a');

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --site-primary: <?= e($primaryColor) ?>; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
<div class="no-print max-w-2xl mx-auto px-4 py-4 flex items-center justify-between">
    <a class="text-sm underline" href="/dashboard">Retour</a>
    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" onclick="window.print()">Imprimer</button>
</div>

<div class="max-w-2xl mx-auto px-4 pb-10">
    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= e($logoUrl) ?>" alt="Logo" class="h-10 w-10 rounded object-contain border border-slate-200">
                <?php endif; ?>
                <div class="min-w-0">
                    <div class="text-xs text-slate-500">Carte d'adhérent</div>
                    <div class="text-lg font-semibold truncate" style="color: var(--site-primary)"><?= e($brandName) ?></div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs text-slate-500">N°</div>
                <div class="font-mono text-sm">#<?= e((string)$cardNumber) ?></div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <div class="text-xs text-slate-500">Titulaire</div>
                <div class="text-base font-semibold break-words"><?= e($holderLabel) ?></div>
                <?php if ($householdName !== '' && $memberName !== ''): ?>
                    <div class="text-xs text-slate-500 mt-1">Membre: <?= e($memberName) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div class="text-xs text-slate-500">Cotisation</div>
                <div class="text-base font-semibold break-words"><?= e($productLabel) ?></div>
                <div class="text-xs text-slate-500 mt-1">Montant: <?= e($fmtCents((int)($subscription['amount_cents'] ?? 0))) ?></div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <div class="text-xs text-slate-500">Période</div>
                <div class="text-sm"><?= e($period !== '' ? $period : '—') ?></div>
            </div>
            <div>
                <div class="text-xs text-slate-500">Statut</div>
                <div class="text-sm">Payée</div>
            </div>
        </div>

        <div class="mt-6 rounded-lg p-4" style="background: color-mix(in srgb, var(--site-primary) 10%, white)">
            <div class="text-xs text-slate-600">Présenter cette carte en cas de contrôle.</div>
        </div>
    </div>

    <div class="no-print mt-4 text-xs text-slate-500">
        Astuce: tu peux enregistrer en PDF depuis la boîte de dialogue d'impression.
    </div>
</div>
</body>
</html>
