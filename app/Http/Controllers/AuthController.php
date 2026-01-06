<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Session;

final class AuthController
{
    public static function showLogin(): void
    {
        if (isset($_SESSION['user_id'])) {
            redirect('/dashboard');
        }

        $error = Session::flash('error');
        $success = Session::flash('success');
        require base_path('views/auth/login.php');
    }

    public static function login(): void
    {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            Session::flash('error', 'Identifiants invalides.');
            redirect('/login');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.tenant_id, u.password_hash, u.is_active, u.is_admin, u.role, t.slug AS tenant_slug, t.name AS tenant_name
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, (string)$user['password_hash'])) {
            Session::flash('error', 'Identifiants invalides.');
            redirect('/login');
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['tenant_id'] = (int)$user['tenant_id'];
        $slug = trim((string)($user['tenant_slug'] ?? ''));
        if ($slug === '') {
            $slug = (string)((int)$user['tenant_id']);
        }
        $_SESSION['tenant_slug'] = $slug;
        $_SESSION['tenant_name'] = (string)($user['tenant_name'] ?? '');
        $_SESSION['is_admin'] = (int)($user['is_admin'] ?? 0);
        $_SESSION['role'] = (string)($user['role'] ?? 'member');

        redirect('/dashboard');
    }

    public static function logout(): void
    {
        session_destroy();
        redirect('/login');
    }
}
