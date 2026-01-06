<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\GoogleDrive;
use App\Support\Access;
use App\Support\Session;

final class DriveController
{
    private static function guard(): void
    {
        Access::require('drive', 'write');
    }

    public static function connect(): void
    {
        self::guard();

        if (!GoogleDrive::isConfigured()) {
            Session::flash('error', 'Google Drive non configuré côté serveur.');
            redirect(tenant_path('/admin/modules'));
        }

        $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            redirect('/login');
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['_oauth_drive'] = [
            'state' => $state,
            'tenant_id' => $tenantId,
            'started_at' => time(),
        ];

        $url = GoogleDrive::getAuthUrl($tenantId, $state);
        if (!$url) {
            Session::flash('error', 'Impossible de démarrer OAuth.');
            redirect(tenant_path('/admin/modules'));
        }

        redirect($url);
    }

    public static function callback(): void
    {
        self::guard();

        $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            redirect('/login');
        }

        $state = (string)($_GET['state'] ?? '');
        $oauth = $_SESSION['_oauth_drive'] ?? null;
        if (!is_array($oauth)) {
            Session::flash('error', 'Session OAuth manquante.');
            redirect(tenant_path('/admin/modules'));
        }

        $expectedState = (string)($oauth['state'] ?? '');
        $startedAt = (int)($oauth['started_at'] ?? 0);
        $expectedTenantId = (int)($oauth['tenant_id'] ?? 0);
        unset($_SESSION['_oauth_drive']);

        if ($expectedTenantId !== $tenantId) {
            Session::flash('error', 'OAuth invalide (tenant).');
            redirect(tenant_path('/admin/modules'));
        }

        if ($expectedState === '' || $state === '' || !hash_equals($expectedState, $state)) {
            Session::flash('error', 'OAuth invalide (state).');
            redirect(tenant_path('/admin/modules'));
        }

        if ($startedAt <= 0 || (time() - $startedAt) > 600) {
            Session::flash('error', 'OAuth expiré, merci de recommencer.');
            redirect(tenant_path('/admin/modules'));
        }

        $code = (string)($_GET['code'] ?? '');
        if ($code === '') {
            Session::flash('error', 'Code OAuth manquant.');
            redirect(tenant_path('/admin/modules'));
        }

        $ok = GoogleDrive::exchangeCode($tenantId, $code);
        if (!$ok) {
            Session::flash('error', 'Connexion Google Drive échouée.');
            redirect(tenant_path('/admin/modules'));
        }

        Session::flash('success', 'Google Drive connecté.');
        redirect(tenant_path('/admin/modules'));
    }

    public static function disconnect(): void
    {
        self::guard();

        GoogleDrive::disconnect((int)$_SESSION['tenant_id']);
        Session::flash('success', 'Google Drive déconnecté.');
        redirect(tenant_path('/admin/modules'));
    }

    public static function saveFolder(): void
    {
        self::guard();

        $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            redirect('/login');
        }

        if (!GoogleDrive::isConfigured() || !GoogleDrive::isAvailable() || !GoogleDrive::isConnected($tenantId)) {
            Session::flash('error', 'Google Drive non connecté.');
            redirect(tenant_path('/admin/modules'));
        }

        $folderId = trim((string)($_POST['drive_folder_id'] ?? ''));
        if ($folderId === '') {
            GoogleDrive::setDriveFolderId($tenantId, null);
            Session::flash('success', 'Dossier Drive supprimé (utilisation par défaut).');
            redirect(tenant_path('/admin/modules'));
        }

        try {
            $meta = GoogleDrive::getFolderMeta($tenantId, $folderId);
            if (!$meta || empty($meta['isFolder'])) {
                Session::flash('error', 'Folder ID invalide ou non accessible.');
                redirect(tenant_path('/admin/modules'));
            }

            GoogleDrive::setDriveFolderId($tenantId, (string)$meta['id']);
            Session::flash('success', 'Dossier Drive enregistré : ' . (string)$meta['name']);
            redirect(tenant_path('/admin/modules'));
        } catch (\Throwable $e) {
            $msg = trim((string)$e->getMessage());
            if ($msg === '') {
                $msg = 'Impossible de valider le dossier Drive.';
            }
            Session::flash('error', $msg);
            redirect(tenant_path('/admin/modules'));
        }
    }
}
