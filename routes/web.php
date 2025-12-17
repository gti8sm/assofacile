<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\TreasuryCategoriesController;
use App\Http\Controllers\TreasuryAttachmentsController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\AdminModulesController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\AdminLicenseController;
use App\Http\Controllers\AdminUpdateController;
use App\Http\Controllers\AdminAccessController;
use App\Http\Controllers\MembersController;
use App\Http\Controllers\HouseholdsController;

$router->get('/', [DashboardController::class, 'index']);

$router->get('/install', [InstallController::class, 'show']);
$router->post('/install', [InstallController::class, 'submit']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/admin/modules', [AdminModulesController::class, 'index']);
$router->post('/admin/modules', [AdminModulesController::class, 'update']);

$router->get('/admin/access', [AdminAccessController::class, 'index']);
$router->post('/admin/access', [AdminAccessController::class, 'update']);

$router->get('/admin/update', [AdminUpdateController::class, 'index']);
$router->post('/admin/update', [AdminUpdateController::class, 'run']);

$router->get('/admin/license', [AdminLicenseController::class, 'index']);
$router->post('/admin/license', [AdminLicenseController::class, 'update']);

$router->get('/treasury', [TreasuryController::class, 'index']);
$router->get('/treasury/new', [TreasuryController::class, 'create']);
$router->post('/treasury/new', [TreasuryController::class, 'store']);
$router->post('/treasury/toggle-cleared', [TreasuryController::class, 'toggleCleared']);

$router->get('/treasury/categories', [TreasuryCategoriesController::class, 'index']);
$router->post('/treasury/categories', [TreasuryCategoriesController::class, 'store']);

$router->get('/treasury/export.csv', [TreasuryController::class, 'exportCsv']);

$router->get('/treasury/attachments', [TreasuryAttachmentsController::class, 'index']);
$router->post('/treasury/attachments', [TreasuryAttachmentsController::class, 'store']);
$router->get('/treasury/attachment/download', [TreasuryAttachmentsController::class, 'download']);

$router->get('/drive/connect', [DriveController::class, 'connect']);
$router->get('/drive/callback', [DriveController::class, 'callback']);
$router->post('/drive/disconnect', [DriveController::class, 'disconnect']);

$router->get('/members', [MembersController::class, 'index']);
$router->get('/members/new', [MembersController::class, 'create']);
$router->post('/members/new', [MembersController::class, 'store']);
$router->get('/members/edit', [MembersController::class, 'edit']);
$router->post('/members/edit', [MembersController::class, 'update']);

$router->get('/households', [HouseholdsController::class, 'index']);
$router->get('/households/new', [HouseholdsController::class, 'create']);
$router->post('/households/new', [HouseholdsController::class, 'store']);
$router->get('/households/edit', [HouseholdsController::class, 'edit']);
$router->post('/households/edit', [HouseholdsController::class, 'update']);

$router->get('/changelog', [ChangelogController::class, 'index']);
