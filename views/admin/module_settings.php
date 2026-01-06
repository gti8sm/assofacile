<?php

$title = 'Admin - Paramètres module';
ob_start();
?>
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Paramètres</h1>
        <div class="text-xs text-slate-500"><?= e($moduleName) ?> (<?= e($moduleKey) ?>)</div>
    </div>
    <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/admin/modules')) ?>">Retour modules</a>
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
    <input type="hidden" name="module" value="<?= e($moduleKey) ?>">

    <?php if ($moduleKey === 'members'): ?>
        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Activer les cotisations</div>
                <div class="text-xs text-slate-500">Affiche/masque le catalogue et la gestion des cotisations.</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="memberships_enabled" value="1" <?= !empty($settings['memberships_enabled']) ? 'checked' : '' ?>>
        </label>

        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Créer une écriture Trésorerie automatiquement</div>
                <div class="text-xs text-slate-500">Nécessite le module Trésorerie activé + droit "écriture".</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="memberships_create_treasury_income" value="1" <?= !empty($settings['memberships_create_treasury_income']) ? 'checked' : '' ?>>
        </label>

        <div class="mt-2 font-semibold">HelloAsso (paiement en ligne)</div>

        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Activer HelloAsso</div>
                <div class="text-xs text-slate-500">Permet de payer une cotisation via HelloAsso Checkout (API).</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="helloasso_enabled" value="1" <?= !empty($settings['helloasso_enabled']) ? 'checked' : '' ?>>
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Environnement</label>
                <select name="helloasso_environment" class="w-full border border-slate-300 rounded px-3 py-2">
                    <option value="prod" <?= (($settings['helloasso_environment'] ?? 'prod') === 'prod') ? 'selected' : '' ?>>Production</option>
                    <option value="sandbox" <?= (($settings['helloasso_environment'] ?? 'prod') === 'sandbox') ? 'selected' : '' ?>>Sandbox</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Organization slug</label>
                <input name="helloasso_organization_slug" value="<?= e((string)($settings['helloasso_organization_slug'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Client ID</label>
                <input name="helloasso_client_id" value="<?= e((string)($settings['helloasso_client_id'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" autocomplete="off">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Client Secret</label>
                <input type="password" name="helloasso_client_secret" value="<?= e((string)($settings['helloasso_client_secret'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" autocomplete="new-password">
            </div>
        </div>
    <?php endif; ?>

    <?php if ($moduleKey === 'treasury'): ?>
        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Activer l'analytique</div>
                <div class="text-xs text-slate-500">Prépare l'activation de la ventilation analytique.</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="analytics_enabled" value="1" <?= !empty($settings['analytics_enabled']) ? 'checked' : '' ?>>
        </label>

        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Activer la ventilation par budgets</div>
                <div class="text-xs text-slate-500">Prépare l'activation des budgets.</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="budget_allocation_enabled" value="1" <?= !empty($settings['budget_allocation_enabled']) ? 'checked' : '' ?>>
        </label>

        <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
            <div>
                <div class="font-medium">Activer la ventilation par projets</div>
                <div class="text-xs text-slate-500">Prépare l'activation des projets.</div>
            </div>
            <input type="checkbox" class="h-5 w-5" name="project_allocation_enabled" value="1" <?= !empty($settings['project_allocation_enabled']) ? 'checked' : '' ?>>
        </label>

        <div class="p-3 border border-slate-200 rounded">
            <div class="font-medium">Exercice</div>
            <div class="mt-1 text-xs text-slate-500">Utilisé pour les propositions de clôture (exercice précédent).</div>

            <div class="mt-3 max-w-xs">
                <label class="block text-sm font-medium mb-1">Mois de début d'exercice</label>
                <select name="fiscal_year_start_month" class="w-full border border-slate-300 rounded px-3 py-2">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= ((int)($settings['fiscal_year_start_month'] ?? 1) === $m) ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($moduleKey === 'drive'): ?>
        <div class="p-3 border border-slate-200 rounded">
            <div class="font-medium">Google Drive</div>
            <?php if (empty($settings['drive_configured'])): ?>
                <div class="mt-1 text-sm text-slate-600">Non configuré côté serveur (variables GOOGLE_* manquantes ou Composer non installé).</div>
            <?php else: ?>
                <?php if (empty($settings['drive_connected'])): ?>
                    <div class="mt-1 text-sm text-slate-600">Non connecté (connexion à faire dans Admin > Modules).</div>
                <?php else: ?>
                    <div class="mt-1 text-sm text-emerald-700">Connecté</div>

                    <div class="mt-3">
                        <label class="block text-sm font-medium mb-1">Folder ID (dossier cible)</label>
                        <input
                            name="drive_folder_id"
                            value="<?= e((string)($settings['drive_folder_id'] ?? '')) ?>"
                            class="w-full border border-slate-300 rounded px-3 py-2"
                            placeholder="Ex: 1AbCDefGhIJkLmNoPqRsTuVwXyZ..."
                            autocomplete="off"
                        >
                        <div class="mt-2 text-xs text-slate-500">
                            Laisser vide pour utiliser le dossier par défaut.
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="p-3 border border-slate-200 rounded">
            <div class="font-medium">Aide au paramétrage</div>
            <div class="mt-2 text-sm text-slate-700 space-y-2">
                <div>
                    <div class="font-medium">1) Configuration serveur (une fois)</div>
                    <div class="text-slate-600">
                        Le module Drive nécessite les variables <code class="text-xs">GOOGLE_CLIENT_ID</code>, <code class="text-xs">GOOGLE_CLIENT_SECRET</code> et <code class="text-xs">GOOGLE_REDIRECT_URI</code>.
                        Si l'écran indique « non configuré », il faut ajouter ces variables côté serveur.
                    </div>
                </div>
                <div>
                    <div class="font-medium">2) Connexion du compte Drive (par tenant)</div>
                    <div class="text-slate-600">
                        Va sur <span class="font-mono text-xs">Admin &gt; Modules</span> puis clique <span class="font-mono text-xs">Connecter Google Drive</span>.
                        Une fois connecté, tu peux revenir ici.
                    </div>
                </div>
                <div>
                    <div class="font-medium">3) Choisir le dossier de stockage (Folder ID)</div>
                    <div class="text-slate-600">
                        Crée (ou choisis) un dossier dans Google Drive, puis ouvre-le.
                        Dans l'URL du navigateur, l'ID est la partie après <span class="font-mono text-xs">/folders/</span>.
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        Exemple : <span class="font-mono text-xs">https://drive.google.com/drive/folders/1AbCDefGhIJkLmNoPqRsTuVwXyZ</span>
                        → Folder ID = <span class="font-mono text-xs">1AbCDefGhIJkLmNoPqRsTuVwXyZ</span>
                    </div>
                </div>
                <div class="text-slate-600">
                    Astuce : laisse le champ vide si tu veux stocker « par défaut » (sans imposer de dossier parent).
                </div>
            </div>
        </div>
    <?php endif; ?>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
</form>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
