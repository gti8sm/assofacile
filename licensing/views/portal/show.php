<?php

$title = 'Espace Association';
ob_start();
?>
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Espace Association</h1>
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
    <div class="text-sm text-slate-600">Licence</div>
    <div class="mt-1 font-mono text-xs break-all"><?= e((string)$license['license_key']) ?></div>

    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
        <div class="text-slate-500">Plan</div>
        <div class="font-medium"><?= e((string)($license['plan_type'] ?? '-')) ?> / <?= e((string)($license['plan_tier'] ?? 'core')) ?></div>

        <div class="text-slate-500">Valid until</div>
        <div class="font-medium"><?= e((string)($license['valid_until'] ?? '-')) ?></div>
    </div>

    <?php if (!empty($stripeLink) && is_array($stripeLink)): ?>
        <div class="mt-4 text-xs text-slate-600">
            <div class="font-semibold">Stripe</div>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div class="text-slate-500">Customer</div>
                <div class="font-mono break-all"><?= e((string)($stripeLink['stripe_customer_id'] ?? '')) ?></div>

                <div class="text-slate-500">Subscription</div>
                <div class="font-mono break-all"><?= e((string)($stripeLink['stripe_subscription_id'] ?? '')) ?></div>

                <div class="text-slate-500">Payment intent</div>
                <div class="font-mono break-all"><?= e((string)($stripeLink['stripe_payment_intent_id'] ?? '')) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="border border-slate-200 rounded p-3">
            <div class="font-semibold">Premium (annual)</div>
            <div class="text-sm text-slate-600 mt-1">Abonnement annuel.</div>
            <form method="post" action="/portal/checkout" class="mt-3">
                <input type="hidden" name="_csrf" value="<?= e(Licensing\Support\Csrf::token()) ?>">
                <input type="hidden" name="token" value="<?= e((string)($_GET['token'] ?? '')) ?>">
                <input type="hidden" name="plan" value="premium_annual">
                <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Choisir</button>
            </form>
        </div>
        <div class="border border-slate-200 rounded p-3">
            <div class="font-semibold">Premium (lifetime)</div>
            <div class="text-sm text-slate-600 mt-1">Paiement unique.</div>
            <form method="post" action="/portal/checkout" class="mt-3">
                <input type="hidden" name="_csrf" value="<?= e(Licensing\Support\Csrf::token()) ?>">
                <input type="hidden" name="token" value="<?= e((string)($_GET['token'] ?? '')) ?>">
                <input type="hidden" name="plan" value="premium_lifetime">
                <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Choisir</button>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
