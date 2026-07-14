<?php

declare(strict_types=1);

use App\Core\Router;

session_start();

define('BASE_PATH', __DIR__);

define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$config = require BASE_PATH . '/config/app.php';

$router = new Router($config);

return [
    'config' => $config,
    'router' => $router,
];
