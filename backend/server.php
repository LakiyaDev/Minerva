<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in web server (`php artisan serve`).
 *
 * Laravel uses this file when it exists, otherwise it falls back to the
 * framework's own router, which resolves the public path from the current
 * working directory. That breaks when the working directory is replaced — for
 * example while switching branches — and every request dies with
 * "Failed opening required '/index.php'". Resolving the path relative to this
 * file keeps the server stable regardless of the process working directory.
 */
$publicPath = __DIR__.'/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Serve existing static files directly from the public directory.
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
