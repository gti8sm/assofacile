<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\TreasuryCategoriesController;
use App\Http\Controllers\TreasuryAttachmentsController;
use App\Http\Controllers\TreasuryBudgetsController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\AdminModulesController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\AdminLicenseController;
use App\Http\Controllers\AdminDiagnosticController;
use App\Http\Controllers\AdminUpdateController;
use App\Http\Controllers\AdminAccessController;
use App\Http\Controllers\AdminModuleSettingsController;
use App\Http\Controllers\AdminSiteSettingsController;
use App\Http\Controllers\AdminPublicSitePagesController;
use App\Http\Controllers\MembersController;
use App\Http\Controllers\HouseholdsController;
use App\Http\Controllers\MemberPickupsController;
use App\Http\Controllers\ChildGroupsController;
use App\Http\Controllers\MembershipProductsController;
use App\Http\Controllers\MembershipSubscriptionsController;
use App\Http\Controllers\HelloAssoController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\AdminPublicSiteDomainController;
use App\Http\Controllers\TiersController;
use App\Http\Controllers\ProjectsController;

$router->get('/', [DashboardController::class, 'index']);

$router->get('/install', [InstallController::class, 'show']);
$router->post('/install', [InstallController::class, 'submit']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/s/{tenant}', [PublicSiteController::class, 'tenantHome']);
$router->get('/s/{tenant}/{page}', [PublicSiteController::class, 'tenantPage']);

$router->get('/admin/modules', [AdminModulesController::class, 'index']);
$router->post('/admin/modules', [AdminModulesController::class, 'update']);

$router->get('/admin/modules/settings', [AdminModuleSettingsController::class, 'index']);
$router->post('/admin/modules/settings', [AdminModuleSettingsController::class, 'update']);

$router->get('/admin/access', [AdminAccessController::class, 'index']);
$router->post('/admin/access', [AdminAccessController::class, 'update']);

$router->get('/admin/site-settings', [AdminSiteSettingsController::class, 'index']);
$router->post('/admin/site-settings', [AdminSiteSettingsController::class, 'update']);

$router->get('/admin/public-site/pages', [AdminPublicSitePagesController::class, 'index']);
$router->get('/admin/public-site/pages/new', [AdminPublicSitePagesController::class, 'create']);
$router->get('/admin/public-site/pages/edit', [AdminPublicSitePagesController::class, 'edit']);
$router->post('/admin/public-site/pages/save', [AdminPublicSitePagesController::class, 'save']);
$router->post('/admin/public-site/pages/publish', [AdminPublicSitePagesController::class, 'publish']);

$router->get('/admin/public-site/domain', [AdminPublicSiteDomainController::class, 'index']);
$router->post('/admin/public-site/domain/save', [AdminPublicSiteDomainController::class, 'save']);
$router->post('/admin/public-site/domain/verify', [AdminPublicSiteDomainController::class, 'verify']);

$router->get('/admin/update', [AdminUpdateController::class, 'index']);
$router->post('/admin/update', [AdminUpdateController::class, 'run']);
$router->get('/admin/update/backup', [AdminUpdateController::class, 'backup']);

$router->get('/admin/license', [AdminLicenseController::class, 'index']);
$router->post('/admin/license', [AdminLicenseController::class, 'update']);

$router->get('/admin/diagnostic', [AdminDiagnosticController::class, 'index']);
$router->get('/admin/diagnostic/env-template', [AdminDiagnosticController::class, 'envTemplate']);

$router->get('/treasury', [TreasuryController::class, 'index']);
$router->get('/treasury/reconcile', [TreasuryController::class, 'reconcile']);
$router->get('/treasury/new', [TreasuryController::class, 'create']);
$router->post('/treasury/new', [TreasuryController::class, 'store']);
$router->get('/treasury/edit', [TreasuryController::class, 'edit']);
$router->post('/treasury/edit', [TreasuryController::class, 'update']);
$router->get('/treasury/tiers/search', [TreasuryController::class, 'tiersSearch']);
$router->get('/treasury/analytics', [TreasuryController::class, 'analytics']);
$router->post('/treasury/reconcile/update', [TreasuryController::class, 'updateReconcile']);
$router->post('/treasury/toggle-cleared', [TreasuryController::class, 'toggleCleared']);
$router->post('/treasury/delete', [TreasuryController::class, 'delete']);
$router->get('/treasury/closures', [TreasuryController::class, 'closures']);
$router->post('/treasury/closures/close', [TreasuryController::class, 'closePeriod']);
$router->post('/treasury/closures/delete', [TreasuryController::class, 'deleteClosure']);

$router->get('/treasury/categories', [TreasuryCategoriesController::class, 'index']);
$router->post('/treasury/categories', [TreasuryCategoriesController::class, 'store']);
$router->post('/treasury/categories/update', [TreasuryCategoriesController::class, 'update']);

$router->get('/treasury/budgets', [TreasuryBudgetsController::class, 'index']);
$router->post('/treasury/budgets', [TreasuryBudgetsController::class, 'store']);
$router->post('/treasury/budgets/link-project', [TreasuryBudgetsController::class, 'linkProject']);
$router->post('/treasury/budgets/unlink-project', [TreasuryBudgetsController::class, 'unlinkProject']);
$router->post('/treasury/budgets/archive', [TreasuryBudgetsController::class, 'archive']);
$router->post('/treasury/budgets/unarchive', [TreasuryBudgetsController::class, 'unarchive']);
$router->post('/treasury/budgets/transfer', [TreasuryBudgetsController::class, 'transfer']);
$router->post('/treasury/budgets/delete', [TreasuryBudgetsController::class, 'delete']);

$router->get('/treasury/export.csv', [TreasuryController::class, 'exportCsv']);
$router->get('/treasury/export.zip', [TreasuryController::class, 'exportZip']);

$router->get('/tiers', [TiersController::class, 'index']);
$router->get('/tiers/new', [TiersController::class, 'create']);
$router->post('/tiers/new', [TiersController::class, 'store']);
$router->get('/tiers/view', [TiersController::class, 'view']);
$router->get('/tiers/edit', [TiersController::class, 'edit']);
$router->post('/tiers/edit', [TiersController::class, 'update']);
$router->post('/tiers/sync-members', [TiersController::class, 'syncMembers']);

$router->post('/tiers/documents/add', [TiersController::class, 'addDocument']);
$router->post('/tiers/documents/delete', [TiersController::class, 'deleteDocument']);
$router->get('/tiers/documents/download', [TiersController::class, 'downloadDocument']);

$router->get('/projects', [ProjectsController::class, 'index']);
$router->get('/projects/new', [ProjectsController::class, 'create']);
$router->post('/projects/new', [ProjectsController::class, 'store']);
$router->get('/projects/view', [ProjectsController::class, 'view']);
$router->get('/projects/edit', [ProjectsController::class, 'edit']);
$router->post('/projects/edit', [ProjectsController::class, 'update']);
$router->get('/projects/actions/edit', [ProjectsController::class, 'editAction']);
$router->post('/projects/actions/edit', [ProjectsController::class, 'updateAction']);
$router->get('/projects/tasks/edit', [ProjectsController::class, 'editTask']);
$router->post('/projects/tasks/edit', [ProjectsController::class, 'updateTask']);
$router->get('/projects/tiers/search', [ProjectsController::class, 'tiersSearch']);
$router->post('/projects/actions/add', [ProjectsController::class, 'addAction']);
$router->post('/projects/tasks/add', [ProjectsController::class, 'addTask']);
$router->post('/projects/participants/add', [ProjectsController::class, 'addParticipant']);
$router->post('/projects/links/add', [ProjectsController::class, 'addLink']);
$router->post('/projects/links/delete', [ProjectsController::class, 'deleteLink']);
$router->post('/projects/documents/add', [ProjectsController::class, 'addDocument']);
$router->post('/projects/documents/delete', [ProjectsController::class, 'deleteDocument']);
$router->get('/projects/documents/download', [ProjectsController::class, 'downloadDocument']);
$router->post('/projects/duplicate', [ProjectsController::class, 'duplicate']);

$router->get('/treasury/attachments', [TreasuryAttachmentsController::class, 'index']);
$router->post('/treasury/attachments', [TreasuryAttachmentsController::class, 'store']);
$router->get('/treasury/attachment/download', [TreasuryAttachmentsController::class, 'download']);

$router->get('/drive/connect', [DriveController::class, 'connect']);
$router->get('/drive/callback', [DriveController::class, 'callback']);
$router->post('/drive/disconnect', [DriveController::class, 'disconnect']);

$router->post('/drive/folder', [DriveController::class, 'saveFolder']);

$router->get('/members', [MembersController::class, 'index']);
$router->get('/members/new', [MembersController::class, 'create']);
$router->post('/members/new', [MembersController::class, 'store']);
$router->get('/members/edit', [MembersController::class, 'edit']);
$router->post('/members/edit', [MembersController::class, 'update']);

$router->post('/members/documents/add', [MembersController::class, 'addDocument']);
$router->post('/members/documents/delete', [MembersController::class, 'deleteDocument']);
$router->get('/members/documents/download', [MembersController::class, 'downloadDocument']);

$router->post('/members/pickups/new', [MemberPickupsController::class, 'store']);
$router->post('/members/pickups/delete', [MemberPickupsController::class, 'delete']);

$router->get('/households', [HouseholdsController::class, 'index']);
$router->get('/households/new', [HouseholdsController::class, 'create']);
$router->post('/households/new', [HouseholdsController::class, 'store']);
$router->get('/households/edit', [HouseholdsController::class, 'edit']);
$router->post('/households/edit', [HouseholdsController::class, 'update']);

$router->get('/child-groups', [ChildGroupsController::class, 'index']);
$router->get('/child-groups/new', [ChildGroupsController::class, 'create']);
$router->post('/child-groups/new', [ChildGroupsController::class, 'store']);
$router->get('/child-groups/edit', [ChildGroupsController::class, 'edit']);
$router->post('/child-groups/edit', [ChildGroupsController::class, 'update']);

$router->get('/memberships/products', [MembershipProductsController::class, 'index']);
$router->get('/memberships/products/new', [MembershipProductsController::class, 'create']);
$router->post('/memberships/products/new', [MembershipProductsController::class, 'store']);

$router->get('/memberships/card', [MembershipSubscriptionsController::class, 'card']);

$router->post('/memberships/subscriptions/new', [MembershipSubscriptionsController::class, 'store']);
$router->post('/memberships/subscriptions/mark-paid', [MembershipSubscriptionsController::class, 'markPaid']);
$router->post('/memberships/subscriptions/delete-test', [MembershipSubscriptionsController::class, 'deleteTest']);

$router->post('/memberships/helloasso/pay', [HelloAssoController::class, 'payMembership']);
$router->get('/memberships/helloasso/return', [HelloAssoController::class, 'return']);
$router->post('/webhooks/helloasso', [HelloAssoController::class, 'webhook']);

$router->get('/changelog', [ChangelogController::class, 'index']);
$router->get('/roadmap', [RoadmapController::class, 'index']);
