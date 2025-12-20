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

            $settings['helloasso_enabled'] = ModuleSettings::getBool($tenantId, 'members', 'helloasso_enabled', false);
            $settings['helloasso_environment'] = ModuleSettings::getString($tenantId, 'members', 'helloasso_environment', 'prod');
            if (!in_array($settings['helloasso_environment'], ['prod', 'sandbox'], true)) {
                $settings['helloasso_environment'] = 'prod';
            }
            $settings['helloasso_organization_slug'] = ModuleSettings::getString($tenantId, 'members', 'helloasso_organization_slug', '');
            $settings['helloasso_client_id'] = ModuleSettings::getString($tenantId, 'members', 'helloasso_client_id', '');
            $settings['helloasso_client_secret'] = ModuleSettings::getString($tenantId, 'members', 'helloasso_client_secret', '');
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

                ModuleSettings::setBool($tenantId, 'members', 'helloasso_enabled', isset($_POST['helloasso_enabled']));
                $env = (string)($_POST['helloasso_environment'] ?? 'prod');
                if (!in_array($env, ['prod', 'sandbox'], true)) {
                    $env = 'prod';
                }
                ModuleSettings::setRaw($tenantId, 'members', 'helloasso_environment', json_encode($env));
                ModuleSettings::setRaw($tenantId, 'members', 'helloasso_organization_slug', json_encode(trim((string)($_POST['helloasso_organization_slug'] ?? ''))));
                ModuleSettings::setRaw($tenantId, 'members', 'helloasso_client_id', json_encode(trim((string)($_POST['helloasso_client_id'] ?? ''))));
                ModuleSettings::setRaw($tenantId, 'members', 'helloasso_client_secret', json_encode(trim((string)($_POST['helloasso_client_secret'] ?? ''))));
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
