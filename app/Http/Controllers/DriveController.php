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
            redirect('/admin/modules');
        }

        $url = GoogleDrive::getAuthUrl((int)$_SESSION['tenant_id']);
        if (!$url) {
            Session::flash('error', 'Impossible de démarrer OAuth.');
            redirect('/admin/modules');
        }

        $_SESSION['_oauth_drive'] = [
            'started_at' => time(),
        ];

        redirect($url);
    }

    public static function callback(): void
    {
        self::guard();

        $code = (string)($_GET['code'] ?? '');
        if ($code === '') {
            Session::flash('error', 'Code OAuth manquant.');
            redirect('/admin/modules');
        }

        $ok = GoogleDrive::exchangeCode((int)$_SESSION['tenant_id'], $code);
        if (!$ok) {
            Session::flash('error', 'Connexion Google Drive échouée.');
            redirect('/admin/modules');
        }

        Session::flash('success', 'Google Drive connecté.');
        redirect('/admin/modules');
    }

    public static function disconnect(): void
    {
        self::guard();

        GoogleDrive::disconnect((int)$_SESSION['tenant_id']);
        Session::flash('success', 'Google Drive déconnecté.');
        redirect('/admin/modules');
    }
}
