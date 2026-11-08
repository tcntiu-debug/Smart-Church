<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| The public/ folder itself is not a page
|--------------------------------------------------------------------------
|
| On the live hosts the document root sits one level above this folder, so
| https://host/public answers with the folder and this file runs as its directory
| index - Laravel would then see the path "/" and render the login page. Shared
| Resource -> Public used to be linked as /public and lives at /resources/public
| now (routes/web.php), so the folder's own URL is forwarded to that page.
|
| A request for the application itself - "/" on cPanel, which the document root's
| .htaccess forwards into this folder - is not the folder's URL and runs normally.
|
| See also the comment block in routes/web.php.
|
*/

$publicFolder = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$requestPath = rtrim(str_replace('\\', '/', strtok($_SERVER['REQUEST_URI'] ?? '/', '?')), '/');

if ($publicFolder !== '' && $requestPath === $publicFolder) {
    header('Location: ' . rtrim(dirname($publicFolder), '/') . '/resources/public', true, 302);
    header('Content-Type: text/plain; charset=UTF-8');

    echo "Shared Resource -> Public has moved to the /resources/public page.\n";

    return;
}

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
