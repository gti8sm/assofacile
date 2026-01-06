<?php

$title = 'Admin - Site';
ob_start();

$labels = [
    'members' => 'Adhérents',
    'households' => 'Familles',
    'child_groups' => 'Groupes enfants',
    'memberships' => 'Cotisations',
    'treasury' => 'Trésorerie',
    'admin_modules' => 'Admin',
    'admin_access' => 'Accès',
    'admin_license' => 'Licence',
    'admin_update' => 'Mise à jour',
    'admin_site_settings' => 'Site',
    'changelog' => 'Changelog',
    'roadmap' => 'Roadmap',
];

$menuOrderCsv = '';
if (!empty($settings['menu_order']) && is_array($settings['menu_order'])) {
    $menuOrderCsv = implode(', ', $settings['menu_order']);
}
?>
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Paramétrages généraux</h1>
        <div class="text-xs text-slate-500">Menu & apparence</div>
    </div>
    <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/dashboard">Retour</a>
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

    <div class="p-3 border border-slate-200 rounded">
        <div class="font-medium">Apparence</div>
        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Nom du site</label>
                <input name="brand_name" value="<?= e((string)($settings['brand_name'] ?? 'AssoFacile')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="AssoFacile">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Logo (URL)</label>
                <input name="logo_url" value="<?= e((string)($settings['logo_url'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="https://... ou /uploads/logo.png">
                <div class="mt-1 text-xs text-slate-500">Pour l’instant: URL externe ou chemin absolu (upload viendra ensuite).</div>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Couleur principale</label>
                <div class="flex items-center gap-2">
                    <input type="color" value="<?= e((string)($settings['primary_color'] ?? '#0f172a')) ?>" class="h-10 w-12 border border-slate-300 rounded" oninput="document.getElementById('primary_color').value = this.value">
                    <input id="primary_color" name="primary_color" value="<?= e((string)($settings['primary_color'] ?? '#0f172a')) ?>" class="flex-1 border border-slate-300 rounded px-3 py-2 font-mono" placeholder="#0f172a" maxlength="7">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Police</label>
                <select name="font_family" class="w-full border border-slate-300 rounded px-3 py-2">
                    <?php $ff = (string)($settings['font_family'] ?? 'system'); ?>
                    <option value="system" <?= $ff === 'system' ? 'selected' : '' ?>>Système</option>
                    <option value="inter" <?= $ff === 'inter' ? 'selected' : '' ?>>Inter</option>
                    <option value="poppins" <?= $ff === 'poppins' ? 'selected' : '' ?>>Poppins</option>
                    <option value="georgia" <?= $ff === 'georgia' ? 'selected' : '' ?>>Georgia</option>
                </select>
                <div class="mt-1 text-xs text-slate-500">Inter/Poppins sont chargées via Google Fonts.</div>
            </div>
        </div>
    </div>

    <label class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded">
        <div>
            <div class="font-medium">Afficher les icônes dans le menu</div>
            <div class="text-xs text-slate-500">Affiche une icône à gauche de chaque entrée (si disponible).</div>
        </div>
        <input type="checkbox" class="h-5 w-5" name="menu_icons_enabled" value="1" <?= !empty($settings['menu_icons_enabled']) ? 'checked' : '' ?>>
    </label>

    <div class="p-3 border border-slate-200 rounded">
        <div class="font-medium">Ordre du menu</div>
        <div class="text-xs text-slate-500">Renseigne les clés séparées par des virgules. Les entrées non listées apparaîtront à la fin.</div>
        <input name="menu_order_csv" value="<?= e($menuOrderCsv) ?>" class="mt-2 w-full border border-slate-300 rounded px-3 py-2" placeholder="members, households, child_groups, memberships, treasury, admin_modules, admin_access, admin_license, admin_update, changelog, roadmap">
        <div class="mt-2 text-xs text-slate-500">
            Clés disponibles:
            <span class="font-mono">members, households, child_groups, memberships, treasury, admin_modules, admin_access, admin_license, admin_update, admin_site_settings, changelog, roadmap</span>
        </div>
    </div>

    <div class="p-3 border border-slate-200 rounded">
        <div class="font-medium">Entrées masquées</div>
        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
            <?php foreach ($labels as $k => $label): ?>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="menu_hidden[<?= e($k) ?>]" value="1" <?= (!empty($settings['menu_hidden']) && is_array($settings['menu_hidden']) && in_array($k, $settings['menu_hidden'], true)) ? 'checked' : '' ?>>
                    Masquer <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="p-3 border border-slate-200 rounded">
        <div class="font-medium">Menus principaux + sous-menus (avancé)</div>
        <div class="text-xs text-slate-500">JSON: liste de groupes, chaque groupe avec <span class="font-mono">label</span>, optionnel <span class="font-mono">icon</span>, et <span class="font-mono">children</span> (liste de clés).</div>
        <textarea name="menu_groups_json" rows="8" class="mt-2 w-full border border-slate-300 rounded px-3 py-2 font-mono text-xs" placeholder='[
  {"label": "Adhérents", "icon": "users", "children": ["members", "households", "child_groups", "memberships"]},
  {"label": "Trésorerie", "icon": "wallet", "children": ["treasury"]},
  {"label": "Admin", "icon": "settings", "children": ["admin_modules", "admin_site_settings", "admin_access", "admin_license", "admin_update"]},
  {"label": "Infos", "icon": "list", "children": ["changelog", "roadmap"]}
]'><?= e((string)($settings['menu_groups_json'] ?? '')) ?></textarea>
        <div class="mt-2 text-xs text-slate-500">Clés disponibles: <span class="font-mono">members, households, child_groups, memberships, treasury, admin_modules, admin_access, admin_license, admin_update, admin_site_settings, changelog, roadmap</span></div>
    </div>

    <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Enregistrer</button>
</form>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
