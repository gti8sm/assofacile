<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ModuleSettings;
use App\Support\Session;

final class AdminSiteSettingsController
{
    private static function requireAdmin(): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['tenant_id'])) {
            redirect('/login');
        }

        if (!isset($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] !== 1) {
            http_response_code(403);
            echo '403';
            exit;
        }
    }

    /** @return string[] */
    private static function knownMenuKeys(): array
    {
        return [
            'members',
            'households',
            'child_groups',
            'memberships',
            'treasury',
            'admin_modules',
            'admin_access',
            'admin_license',
            'admin_update',
            'admin_site_settings',
            'changelog',
            'roadmap',
        ];
    }

    private static function normalizeMenuGroups($decoded, array $known): array
    {
        if (!is_array($decoded)) {
            return [];
        }

        $groups = [];
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
                    if ($k === '' || !in_array($k, $known, true) || in_array($k, $children, true)) {
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

        return $groups;
    }

    public static function index(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        $known = self::knownMenuKeys();

        $menuOrderRaw = ModuleSettings::getRaw($tenantId, 'site', 'menu_order');
        $menuOrder = [];
        if (is_string($menuOrderRaw) && $menuOrderRaw !== '') {
            $decoded = json_decode($menuOrderRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k) {
                    if (is_string($k) && in_array($k, $known, true) && !in_array($k, $menuOrder, true)) {
                        $menuOrder[] = $k;
                    }
                }
            }
        }

        $menuHiddenRaw = ModuleSettings::getRaw($tenantId, 'site', 'menu_hidden');
        $menuHidden = [];
        if (is_string($menuHiddenRaw) && $menuHiddenRaw !== '') {
            $decoded = json_decode($menuHiddenRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k) {
                    if (is_string($k) && in_array($k, $known, true) && !in_array($k, $menuHidden, true)) {
                        $menuHidden[] = $k;
                    }
                }
            }
        }

        $settings = [
            'menu_icons_enabled' => ModuleSettings::getBool($tenantId, 'site', 'menu_icons_enabled', true),
            'menu_order' => $menuOrder,
            'menu_hidden' => $menuHidden,
            'menu_groups_json' => (string)(ModuleSettings::getRaw($tenantId, 'site', 'menu_groups') ?? ''),
            'brand_name' => (string)(ModuleSettings::getString($tenantId, 'site', 'brand_name', 'AssoFacile') ?? 'AssoFacile'),
            'logo_url' => (string)(ModuleSettings::getString($tenantId, 'site', 'logo_url', '') ?? ''),
            'primary_color' => (string)(ModuleSettings::getString($tenantId, 'site', 'primary_color', '#0f172a') ?? '#0f172a'),
            'font_family' => (string)(ModuleSettings::getString($tenantId, 'site', 'font_family', 'system') ?? 'system'),
        ];

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/admin/site_settings.php');
    }

    public static function update(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        $known = self::knownMenuKeys();

        $iconsEnabled = isset($_POST['menu_icons_enabled']);

        $orderCsv = trim((string)($_POST['menu_order_csv'] ?? ''));
        $order = [];
        if ($orderCsv !== '') {
            $parts = preg_split('/\s*,\s*/', $orderCsv);
            if (is_array($parts)) {
                foreach ($parts as $k) {
                    $k = trim((string)$k);
                    if ($k !== '' && in_array($k, $known, true) && !in_array($k, $order, true)) {
                        $order[] = $k;
                    }
                }
            }
        }

        $hidden = [];
        $hiddenPost = $_POST['menu_hidden'] ?? [];
        if (is_array($hiddenPost)) {
            foreach ($hiddenPost as $k => $v) {
                $k = (string)$k;
                if (in_array($k, $known, true) && !in_array($k, $hidden, true)) {
                    $hidden[] = $k;
                }
            }
        }

        $brandName = trim((string)($_POST['brand_name'] ?? ''));
        if ($brandName === '') {
            $brandName = 'AssoFacile';
        }

        $logoUrl = trim((string)($_POST['logo_url'] ?? ''));
        if ($logoUrl !== '' && !preg_match('~^https?://~i', $logoUrl) && !str_starts_with($logoUrl, '/')) {
            Session::flash('error', 'URL du logo invalide (utilise une URL http(s) ou un chemin commençant par /).');
            redirect('/admin/site-settings');
        }

        $primaryColor = trim((string)($_POST['primary_color'] ?? ''));
        if ($primaryColor === '') {
            $primaryColor = '#0f172a';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) {
            Session::flash('error', 'Couleur principale invalide (format attendu: #RRGGBB).');
            redirect('/admin/site-settings');
        }

        $fontFamily = (string)($_POST['font_family'] ?? 'system');
        $allowedFonts = ['system', 'inter', 'poppins', 'georgia'];
        if (!in_array($fontFamily, $allowedFonts, true)) {
            $fontFamily = 'system';
        }

        $menuGroupsJson = trim((string)($_POST['menu_groups_json'] ?? ''));
        if ($menuGroupsJson !== '') {
            $decoded = json_decode($menuGroupsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Session::flash('error', 'Menu principal/sous-menus: JSON invalide.');
                redirect('/admin/site-settings');
            }

            $normalized = self::normalizeMenuGroups($decoded, $known);
            if (empty($normalized)) {
                Session::flash('error', 'Menu principal/sous-menus: structure invalide (attendu: liste de groupes avec label + children).');
                redirect('/admin/site-settings');
            }

            $menuGroupsJson = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        }

        ModuleSettings::setBool($tenantId, 'site', 'menu_icons_enabled', $iconsEnabled);
        ModuleSettings::setRaw($tenantId, 'site', 'menu_order', json_encode($order));
        ModuleSettings::setRaw($tenantId, 'site', 'menu_hidden', json_encode($hidden));
        ModuleSettings::setRaw($tenantId, 'site', 'menu_groups', $menuGroupsJson === '' ? '[]' : $menuGroupsJson);
        ModuleSettings::setString($tenantId, 'site', 'brand_name', $brandName);
        ModuleSettings::setString($tenantId, 'site', 'logo_url', $logoUrl);
        ModuleSettings::setString($tenantId, 'site', 'primary_color', $primaryColor);
        ModuleSettings::setString($tenantId, 'site', 'font_family', $fontFamily);

        Session::flash('success', 'Paramètres enregistrés.');
        redirect('/admin/site-settings');
    }
}
