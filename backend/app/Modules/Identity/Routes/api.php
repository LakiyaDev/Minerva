<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Api\V1\Admin\AdminPublisherPermissionController;
use App\Modules\Identity\Http\Controllers\Api\V1\Admin\AdminRoleController;
use App\Modules\Identity\Http\Controllers\Api\V1\Admin\AdminStaffInvitationController;
use App\Modules\Identity\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Modules\Identity\Http\Controllers\Api\V1\Admin\AdminUserPermissionController;
use App\Modules\Identity\Http\Controllers\Api\V1\Admin\AdminUserRoleController;
use App\Modules\Identity\Http\Controllers\Api\V1\Auth\GoogleCallbackController;
use App\Modules\Identity\Http\Controllers\Api\V1\Auth\GoogleRedirectController;
use App\Modules\Identity\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Modules\Identity\Http\Controllers\Api\V1\Auth\StaffForgotPasswordController;
use App\Modules\Identity\Http\Controllers\Api\V1\Auth\StaffInvitationAcceptController;
use App\Modules\Identity\Http\Controllers\Api\V1\Auth\StaffLoginController;
use App\Modules\Identity\Http\Controllers\Api\V1\Auth\StaffMfaSetupController;
use App\Modules\Identity\Http\Controllers\Api\V1\Auth\StaffMfaVerifyController;
use App\Modules\Identity\Http\Controllers\Api\V1\Auth\StaffResetPasswordController;
use App\Modules\Identity\Http\Controllers\Api\V1\MeController;
use App\Modules\Identity\Http\Controllers\Api\V1\MeSessionsController;
use App\Modules\Identity\Http\Controllers\Api\V1\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity & Access routes (mounted under /api/v1)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function (): void {
    // Readers — Google OpenID Connect.
    Route::get('google/redirect', GoogleRedirectController::class)
        ->middleware('throttle:identity-oauth');
    // The OAuth callback is a top-level navigation from Google, not from the
    // SPA. It therefore needs the web session stack explicitly so the session
    // cookie is available before the SPA resumes cookie-authenticated calls.
    Route::get('google/callback', GoogleCallbackController::class)
        ->middleware(['web', 'throttle:identity-oauth']);

    // Staff — password + TOTP.
    Route::post('staff/login', StaffLoginController::class)
        ->middleware('throttle:identity-staff-login');
    Route::post('staff/mfa/verify', StaffMfaVerifyController::class)
        ->middleware('throttle:identity-mfa');
    Route::post('staff/mfa/setup', [StaffMfaSetupController::class, 'store'])
        ->middleware('throttle:identity-mfa');
    Route::post('staff/mfa/setup/confirm', [StaffMfaSetupController::class, 'confirm'])
        ->middleware('throttle:identity-mfa');

    // Staff onboarding and recovery.
    Route::post('staff/invitations/accept', StaffInvitationAcceptController::class)
        ->middleware('throttle:identity-invitation');
    Route::post('staff/forgot-password', StaffForgotPasswordController::class)
        ->middleware('throttle:identity-password');
    Route::post('staff/reset-password', StaffResetPasswordController::class)
        ->middleware('throttle:identity-password');

    Route::post('logout', LogoutController::class)
        ->middleware('auth:sanctum');
});

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('me', [MeController::class, 'show']);
    Route::patch('me', [MeController::class, 'update']);
    Route::delete('me/sessions', MeSessionsController::class)
        ->middleware('throttle:identity-sessions');

    Route::post('me/two-factor/setup', [TwoFactorController::class, 'setup']);
    Route::post('me/two-factor/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('me/two-factor/disable', [TwoFactorController::class, 'disable'])
        ->middleware('throttle:identity-mfa');
});

Route::prefix('admin')->middleware(['auth:sanctum', 'active', 'staff.mfa'])->group(function (): void {
    Route::get('users', [AdminUserController::class, 'index'])
        ->middleware('permission:user.manage');
    Route::get('users/{user}', [AdminUserController::class, 'show'])
        ->middleware('permission:user.manage');
    Route::patch('users/{user}', [AdminUserController::class, 'update'])
        ->middleware('permission:user.manage');

    Route::post('users/{user}/roles', [AdminUserRoleController::class, 'store'])
        ->middleware('permission:user.manage');
    Route::delete('users/{user}/roles', [AdminUserRoleController::class, 'destroy'])
        ->middleware('permission:user.manage');

    Route::post('users/{user}/permissions', [AdminUserPermissionController::class, 'store'])
        ->middleware('permission:user.manage');
    Route::delete('users/{user}/permissions', [AdminUserPermissionController::class, 'destroy'])
        ->middleware('permission:user.manage');

    Route::post('users/{user}/publisher-permission', [AdminPublisherPermissionController::class, 'store'])
        ->middleware('permission:publisher.manage');
    Route::patch('users/{user}/publisher-permission', [AdminPublisherPermissionController::class, 'update'])
        ->middleware('permission:publisher.manage');
    Route::delete('users/{user}/publisher-permission', [AdminPublisherPermissionController::class, 'destroy'])
        ->middleware('permission:publisher.manage');

    Route::get('staff-invitations', [AdminStaffInvitationController::class, 'index'])
        ->middleware('permission:user.manage');
    Route::post('staff-invitations', [AdminStaffInvitationController::class, 'store'])
        ->middleware('permission:user.manage');

    Route::get('roles', AdminRoleController::class)
        ->middleware('permission:user.manage');
});
