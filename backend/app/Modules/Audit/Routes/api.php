<?php

declare(strict_types=1);

use App\Modules\Audit\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Audit routes (mounted under /api/v1)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware(['auth:sanctum', 'active', 'staff.mfa'])->group(function (): void {
    Route::get('audit-logs', [AdminAuditLogController::class, 'index'])
        ->middleware('permission:audit.view');
});
