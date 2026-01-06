<?php
/** @var string $title */
/** @var string $content */

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<?php
$tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
$siteBrandName = 'AssoFacile';
$siteLogoUrl = '';
$sitePrimaryColor = '#0f172a';
$siteFontFamily = 'system';
if ($tenantId > 0) {
    try {
        $siteBrandName = App\Support\ModuleSettings::getString($tenantId, 'site', 'brand_name', 'AssoFacile') ?? 'AssoFacile';
        $siteLogoUrl = App\Support\ModuleSettings::getString($tenantId, 'site', 'logo_url', '') ?? '';
        $sitePrimaryColor = App\Support\ModuleSettings::getString($tenantId, 'site', 'primary_color', '#0f172a') ?? '#0f172a';
        $siteFontFamily = App\Support\ModuleSettings::getString($tenantId, 'site', 'font_family', 'system') ?? 'system';
    } catch (\Throwable $e) {
        // Layout must never crash (e.g. login page) if DB/config is not available.
    }
}

$fontCss = 'ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';
$fontLink = '';
if ($siteFontFamily === 'inter') {
    $fontCss = 'Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';
    $fontLink = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap';
} elseif ($siteFontFamily === 'poppins') {
    $fontCss = 'Poppins, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';
    $fontLink = 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap';
} elseif ($siteFontFamily === 'georgia') {
    $fontCss = 'Georgia, ui-serif, serif';
}
?>
<?php if ($fontLink !== ''): ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?= e($fontLink) ?>" rel="stylesheet">
<?php endif; ?>
<style>
    :root { --site-primary: <?= e((string)$sitePrimaryColor) ?>; --site-font: <?= e($fontCss) ?>; }
    body { font-family: var(--site-font); }
</style>
<body class="bg-slate-50 text-slate-900">
<nav class="bg-white border-b border-slate-200">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="<?= e(tenant_path('/dashboard')) ?>" class="font-semibold inline-flex items-center gap-2">
            <?php if (is_string($siteLogoUrl) && $siteLogoUrl !== ''): ?>
                <img src="<?= e($siteLogoUrl) ?>" alt="" class="h-7 w-auto max-w-[44px] object-contain" />
            <?php endif; ?>
            <span class="leading-tight">
                <span class="block"><?= e((string)$siteBrandName) ?></span>
                <span class="block text-[11px] font-normal text-slate-500">by Assofacile</span>
            </span>
        </a>
        <div class="flex items-center gap-3">
            <?php
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            $isAdmin = isset($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1;

            $siteIconsEnabled = true;
            $menuOrderRaw = null;
            try {
                $siteIconsEnabled = $tenantId > 0 ? App\Support\ModuleSettings::getBool($tenantId, 'site', 'menu_icons_enabled', true) : true;
                $menuOrderRaw = $tenantId > 0 ? App\Support\ModuleSettings::getRaw($tenantId, 'site', 'menu_order') : null;
            } catch (\Throwable $e) {
                $siteIconsEnabled = true;
                $menuOrderRaw = null;
            }
            $menuOrder = [];
            if (is_string($menuOrderRaw) && $menuOrderRaw !== '') {
                $decoded = json_decode($menuOrderRaw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $k) {
                        if (is_string($k) && !in_array($k, $menuOrder, true)) {
                            $menuOrder[] = $k;
                        }
                    }
                }
            }

            $menuHiddenRaw = null;
            try {
                $menuHiddenRaw = $tenantId > 0 ? App\Support\ModuleSettings::getRaw($tenantId, 'site', 'menu_hidden') : null;
            } catch (\Throwable $e) {
                $menuHiddenRaw = null;
            }
            $menuHidden = [];
            if (is_string($menuHiddenRaw) && $menuHiddenRaw !== '') {
                $decoded = json_decode($menuHiddenRaw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $k) {
                        if (is_string($k) && !in_array($k, $menuHidden, true)) {
                            $menuHidden[] = $k;
                        }
                    }
                }
            }

            $items = [
                [
                    'key' => 'members',
                    'label' => 'Adhérents',
                    'href' => tenant_path('/members'),
                    'icon' => 'users',
                    'visible' => static fn () => $tenantId > 0 && $userId > 0 && App\Support\Access::can($tenantId, $userId, 'members', 'read'),
                ],
                [
                    'key' => 'households',
                    'label' => 'Familles',
                    'href' => tenant_path('/households'),
                    'icon' => 'home',
                    'visible' => static fn () => $tenantId > 0 && $userId > 0 && App\Support\Access::can($tenantId, $userId, 'members', 'read'),
                ],
                [
                    'key' => 'child_groups',
                    'label' => 'Groupes enfants',
                    'href' => tenant_path('/child-groups'),
                    'icon' => 'baby',
                    'visible' => static fn () => $tenantId > 0 && $userId > 0 && App\Support\Access::can($tenantId, $userId, 'members', 'read'),
                ],
                [
                    'key' => 'memberships',
                    'label' => 'Cotisations',
                    'href' => tenant_path('/memberships/products'),
                    'icon' => 'badge-euro',
                    'visible' => static fn () => $tenantId > 0 && $userId > 0
                        && App\Support\Access::can($tenantId, $userId, 'members', 'read')
                        && App\Support\ModuleSettings::getBool($tenantId, 'members', 'memberships_enabled', true),
                ],
                [
                    'key' => 'treasury',
                    'label' => 'Trésorerie',
                    'href' => tenant_path('/treasury'),
                    'icon' => 'wallet',
                    'visible' => static fn () => $tenantId > 0 && $userId > 0 && App\Support\Access::can($tenantId, $userId, 'treasury', 'read'),
                ],
                [
                    'key' => 'tiers',
                    'label' => 'Tiers',
                    'href' => tenant_path('/tiers'),
                    'icon' => 'contact',
                    'visible' => static fn () => $tenantId > 0 && $userId > 0 && App\Support\Access::can($tenantId, $userId, 'treasury', 'read'),
                ],
                [
                    'key' => 'projects',
                    'label' => 'Projets',
                    'href' => tenant_path('/projects'),
                    'icon' => 'folder',
                    'visible' => static fn () => $tenantId > 0 && $userId > 0 && App\Support\Access::can($tenantId, $userId, 'projects', 'read'),
                ],
                [
                    'key' => 'admin_modules',
                    'label' => 'Admin',
                    'href' => tenant_path('/admin/modules'),
                    'icon' => 'settings',
                    'visible' => static fn () => $isAdmin,
                ],
                [
                    'key' => 'admin_access',
                    'label' => 'Accès',
                    'href' => tenant_path('/admin/access'),
                    'icon' => 'key',
                    'visible' => static fn () => $isAdmin,
                ],
                [
                    'key' => 'admin_license',
                    'label' => 'Licence',
                    'href' => tenant_path('/admin/license'),
                    'icon' => 'shield',
                    'visible' => static fn () => $isAdmin,
                ],
                [
                    'key' => 'admin_update',
                    'label' => 'Mise à jour',
                    'href' => tenant_path('/admin/update'),
                    'icon' => 'refresh-cw',
                    'visible' => static fn () => $isAdmin,
                ],
                [
                    'key' => 'admin_site_settings',
                    'label' => 'Site',
                    'href' => tenant_path('/admin/site-settings'),
                    'icon' => 'sliders',
                    'visible' => static fn () => $isAdmin,
                ],
                [
                    'key' => 'changelog',
                    'label' => 'Changelog',
                    'href' => tenant_path('/changelog'),
                    'icon' => 'list',
                    'visible' => static fn () => true,
                ],
                [
                    'key' => 'roadmap',
                    'label' => 'Roadmap',
                    'href' => tenant_path('/roadmap'),
                    'icon' => 'map',
                    'visible' => static fn () => true,
                ],
            ];

            $byKey = [];
            foreach ($items as $it) {
                $byKey[(string)$it['key']] = $it;
            }

            $sorted = [];
            foreach ($menuOrder as $k) {
                if (isset($byKey[$k])) {
                    $sorted[] = $byKey[$k];
                    unset($byKey[$k]);
                }
            }
            foreach ($items as $it) {
                $k = (string)$it['key'];
                if (isset($byKey[$k])) {
                    $sorted[] = $byKey[$k];
                }
            }

            $iconSvg = static function (string $name): string {
                $common = 'width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
                if ($name === 'users') {
                    return '<svg ' . $common . '><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>';
                }
                if ($name === 'home') {
                    return '<svg ' . $common . '><path d="M3 9l9-7 9 7"></path><path d="M9 22V12h6v10"></path></svg>';
                }
                if ($name === 'baby') {
                    return '<svg ' . $common . '><path d="M9 12h.01"></path><path d="M15 12h.01"></path><path d="M10 16s1.5 1 2 1 2-1 2-1"></path><path d="M19 10c0 5-3.6 9-7 9s-7-4-7-9a7 7 0 0 1 14 0Z"></path><path d="M8 9c1.5-2 6.5-2 8 0"></path></svg>';
                }
                if ($name === 'badge-euro') {
                    return '<svg ' . $common . '><path d="M20 12H8"></path><path d="M20 7H8"></path><path d="M14 17a6 6 0 1 1 0-10"></path></svg>';
                }
                if ($name === 'wallet') {
                    return '<svg ' . $common . '><path d="M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"></path><path d="M3 7h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H3"></path><path d="M17 11h.01"></path></svg>';
                }
                if ($name === 'contact') {
                    return '<svg ' . $common . '><path d="M21 20a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h12"></path><path d="M16 2l6 6"></path><path d="M14 8h8"></path><path d="M7 10h.01"></path><path d="M11 10h.01"></path><path d="M7 14h.01"></path><path d="M11 14h.01"></path><path d="M7 18h.01"></path><path d="M11 18h.01"></path></svg>';
                }
                if ($name === 'settings') {
                    return '<svg ' . $common . '><path d="M12 1v2"></path><path d="M12 21v2"></path><path d="M4.22 4.22l1.42 1.42"></path><path d="M18.36 18.36l1.42 1.42"></path><path d="M1 12h2"></path><path d="M21 12h2"></path><path d="M4.22 19.78l1.42-1.42"></path><path d="M18.36 5.64l1.42-1.42"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                }
                if ($name === 'key') {
                    return '<svg ' . $common . '><path d="M21 2l-2 2m-7.6 7.6a5 5 0 1 1-7.1-7.1 5 5 0 0 1 7.1 7.1Z"></path><path d="M15.5 8.5L19 5l2 2-3.5 3.5"></path><path d="M12 12l-4 4H5v-3l4-4"></path></svg>';
                }
                if ($name === 'shield') {
                    return '<svg ' . $common . '><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path></svg>';
                }
                if ($name === 'refresh-cw') {
                    return '<svg ' . $common . '><path d="M21 12a9 9 0 0 1-9 9 9 9 0 0 1-6.36-2.64"></path><path d="M3 12a9 9 0 0 1 9-9 9 9 0 0 1 6.36 2.64"></path><path d="M21 3v6h-6"></path><path d="M3 21v-6h6"></path></svg>';
                }
                if ($name === 'sliders') {
                    return '<svg ' . $common . '><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>';
                }
                if ($name === 'list') {
                    return '<svg ' . $common . '><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>';
                }
                if ($name === 'map') {
                    return '<svg ' . $common . '><path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3Z"></path><path d="M9 3v15"></path><path d="M15 6v15"></path></svg>';
                }
                return '';
            };

            $renderLink = static function (array $it) use ($menuHidden, $siteIconsEnabled, $iconSvg): string {
                $key = (string)($it['key'] ?? '');
                if ($key === '' || in_array($key, $menuHidden, true)) {
                    return '';
                }
                $visibleFn = $it['visible'] ?? null;
                if (!is_callable($visibleFn) || !$visibleFn()) {
                    return '';
                }

                $label = (string)($it['label'] ?? '');
                $href = (string)($it['href'] ?? '#');
                $iconName = (string)($it['icon'] ?? '');
                $svg = ($siteIconsEnabled && $iconName !== '') ? $iconSvg($iconName) : '';
                $iconHtml = $svg !== '' ? ('<span class="inline-flex items-center text-slate-500">' . $svg . '</span>') : '';
                return '<a href="' . e($href) . '" class="text-sm text-slate-700 hover:text-slate-900 inline-flex items-center gap-1.5">' . $iconHtml . e($label) . '</a>';
            };

            $groups = [];
            $menuGroupsRaw = $tenantId > 0 ? App\Support\ModuleSettings::getRaw($tenantId, 'site', 'menu_groups') : null;
            if (is_string($menuGroupsRaw) && $menuGroupsRaw !== '') {
                $decoded = json_decode($menuGroupsRaw, true);
                if (is_array($decoded)) {
                    $knownKeys = array_keys($byKey);
                    foreach ($decoded as $g) {
                        if (!is_array($g)) {
                            continue;
                        }
                        $label = trim((string)($g['label'] ?? ''));
                        if ($label === '') {
                            continue;
                        }

                        $children = [];
                        $childrenIn = $g['children'] ?? [];
                        if (is_array($childrenIn)) {
                            foreach ($childrenIn as $k) {
                                if (!is_string($k)) {
                                    continue;
                                }
                                $k = trim($k);
                                if ($k === '' || !in_array($k, $knownKeys, true) || in_array($k, $children, true)) {
                                    continue;
                                }
                                $children[] = $k;
                            }
                        }

                        if (empty($children)) {
                            continue;
                        }

                        $icon = trim((string)($g['icon'] ?? ''));
                        $group = [
                            'label' => $label,
                            'children' => $children,
                        ];
                        if ($icon !== '') {
                            $group['icon'] = $icon;
                        }
                        $groups[] = $group;
                    }
                }
            }
            if (empty($groups)) {
                $groups = [
                    [
                        'label' => 'Adhérents',
                        'icon' => 'users',
                        'children' => ['members', 'households', 'child_groups', 'memberships'],
                    ],
                    [
                        'label' => 'Trésorerie',
                        'icon' => 'wallet',
                        'children' => ['treasury', 'tiers'],
                    ],
                    [
                        'label' => 'Admin',
                        'icon' => 'settings',
                        'children' => ['admin_modules', 'admin_site_settings', 'admin_access', 'admin_license', 'admin_update'],
                    ],
                    [
                        'label' => 'Infos',
                        'icon' => 'list',
                        'children' => ['changelog', 'roadmap'],
                    ],
                ];
            }

            foreach ($groups as $g) {
                $childrenHtml = '';
                foreach ((array)($g['children'] ?? []) as $childKey) {
                    foreach ($sorted as $it) {
                        if ((string)($it['key'] ?? '') !== (string)$childKey) {
                            continue;
                        }
                        $link = $renderLink($it);
                        if ($link !== '') {
                            $childrenHtml .= '<div class="py-1">' . $link . '</div>';
                        }
                    }
                }

                if ($childrenHtml === '') {
                    continue;
                }

                $groupIconName = (string)($g['icon'] ?? '');
                $groupSvg = ($siteIconsEnabled && $groupIconName !== '') ? $iconSvg($groupIconName) : '';
                $groupIconHtml = $groupSvg !== '' ? ('<span class="inline-flex items-center text-slate-500">' . $groupSvg . '</span>') : '';
                ?>
                <details class="relative">
                    <summary class="list-none cursor-pointer select-none text-sm text-slate-700 hover:text-slate-900 inline-flex items-center gap-1.5">
                        <?= $groupIconHtml ?>
                        <span><?= e((string)$g['label']) ?></span>
                        <span class="text-slate-400">▾</span>
                    </summary>
                    <div class="absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-lg shadow-sm p-2 z-50">
                        <?= $childrenHtml ?>
                    </div>
                </details>
                <?php
            }
            ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="post" action="/logout">
                    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                    <button class="text-sm text-slate-700 hover:text-slate-900" type="submit">Déconnexion</button>
                </form>
            <?php else: ?>
                <a href="/login" class="text-sm text-slate-700 hover:text-slate-900">Connexion</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php
$layoutMaxWidth = isset($layoutMaxWidth) && is_string($layoutMaxWidth) && $layoutMaxWidth !== '' ? $layoutMaxWidth : 'max-w-5xl';
?>
<main class="<?= e($layoutMaxWidth) ?> mx-auto px-4 py-6">
    <?= $content ?>
</main>
</body>
</html>
