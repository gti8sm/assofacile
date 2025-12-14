<?php

declare(strict_types=1);

namespace Licensing\Http\Controllers;

use Licensing\Database\Db;
use Licensing\Support\Installer;
use Licensing\Support\Session;

final class AuthController
{
    public static function showLogin(): void
    {
        if (!Installer::isLocked()) {
            redirect('/install');
        }

        if (isset($_SESSION['admin_id'])) {
            redirect('/licenses');
        }

        $error = Session::flash('error');
        $success = Session::flash('success');

        require base_path('views/auth/login.php');
    }

    public static function login(): void
    {
        if (!Installer::isLocked()) {
            redirect('/install');
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            Session::flash('error', 'Identifiants invalides.');
            redirect('/login');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, password_hash, is_active FROM admins WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch();

        if (!$admin || (int)$admin['is_active'] !== 1 || !password_verify($password, (string)$admin['password_hash'])) {
            Session::flash('error', 'Identifiants invalides.');
            redirect('/login');
        }

        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];

        redirect('/licenses');
    }

    public static function logout(): void
    {
        session_destroy();
        redirect('/login');
    }
}
