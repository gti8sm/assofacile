<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\ModuleSettings;
use App\Support\Modules;
use App\Support\Session;

final class AdminModuleSettingsController
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

    public static function index(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        $moduleKey = (string)($_GET['module'] ?? '');

        if (!in_array($moduleKey, ['members', 'treasury'], true)) {
            Session::flash('error', 'Module invalide.');
            redirect('/admin/modules');
        }

        if (!Modules::isEnabled($tenantId, $moduleKey)) {
            Session::flash('error', 'Module désactivé.');
            redirect('/admin/modules');
        }

        $settings = [];
        if ($moduleKey === 'members') {
            $settings['memberships_enabled'] = ModuleSettings::getBool($tenantId, 'members', 'memberships_enabled', true);
            $settings['memberships_create_treasury_income'] = ModuleSettings::getBool($tenantId, 'members', 'memberships_create_treasury_income', false);
        }
        if ($moduleKey === 'treasury') {
            $settings['analytics_enabled'] = ModuleSettings::getBool($tenantId, 'treasury', 'analytics_enabled', false);
            $settings['budget_allocation_enabled'] = ModuleSettings::getBool($tenantId, 'treasury', 'budget_allocation_enabled', false);
            $settings['project_allocation_enabled'] = ModuleSettings::getBool($tenantId, 'treasury', 'project_allocation_enabled', false);
        }

        $moduleName = $moduleKey === 'members' ? 'Adhérents' : 'Trésorerie';
        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/admin/module_settings.php');
    }

    public static function update(): void
    {
        self::requireAdmin();

        $tenantId = (int)$_SESSION['tenant_id'];
        $moduleKey = (string)($_POST['module'] ?? '');

        if (!in_array($moduleKey, ['members', 'treasury'], true)) {
            Session::flash('error', 'Module invalide.');
            redirect('/admin/modules');
        }

        if (!Modules::isEnabled($tenantId, $moduleKey)) {
            Session::flash('error', 'Module désactivé.');
            redirect('/admin/modules');
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            if ($moduleKey === 'members') {
                ModuleSettings::setBool($tenantId, 'members', 'memberships_enabled', isset($_POST['memberships_enabled']));
                ModuleSettings::setBool($tenantId, 'members', 'memberships_create_treasury_income', isset($_POST['memberships_create_treasury_income']));
            }

            if ($moduleKey === 'treasury') {
                ModuleSettings::setBool($tenantId, 'treasury', 'analytics_enabled', isset($_POST['analytics_enabled']));
                ModuleSettings::setBool($tenantId, 'treasury', 'budget_allocation_enabled', isset($_POST['budget_allocation_enabled']));
                ModuleSettings::setBool($tenantId, 'treasury', 'project_allocation_enabled', isset($_POST['project_allocation_enabled']));
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Session::flash('success', 'Paramètres enregistrés.');
        redirect('/admin/modules/settings?module=' . $moduleKey);
    }
}
