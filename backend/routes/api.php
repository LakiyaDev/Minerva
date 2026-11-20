<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (mounted at /api)
|--------------------------------------------------------------------------
|
| Each module owns its route file. Modules are registered here under the
| public API version prefix. Do not add business logic to this file.
|
*/

Route::prefix('v1')->group(function (): void {
    require app_path('Modules/Identity/Routes/api.php');
    require app_path('Modules/Articles/Routes/api.php');
    require app_path('Modules/Comments/Routes/api.php');
    require app_path('Modules/Voting/Routes/api.php');
});
