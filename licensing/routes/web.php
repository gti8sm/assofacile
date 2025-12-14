<?php

declare(strict_types=1);

use Licensing\Http\Controllers\AuthController;
use Licensing\Http\Controllers\InstallController;
use Licensing\Http\Controllers\LicensesController;
use Licensing\Http\Controllers\ApiLicensesController;

$router->get('/', [LicensesController::class, 'index']);

$router->get('/install', [InstallController::class, 'show']);
$router->post('/install', [InstallController::class, 'submit']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/licenses', [LicensesController::class, 'index']);
$router->post('/licenses', [LicensesController::class, 'store']);
$router->post('/licenses/renew', [LicensesController::class, 'renew']);
$router->post('/licenses/revoke', [LicensesController::class, 'revoke']);

$router->post('/api/v1/licenses/validate', [ApiLicensesController::class, 'validate']);
