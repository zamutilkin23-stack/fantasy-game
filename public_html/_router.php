<?php
// URL routing
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = rtrim($path, '/');

$routes = [
    '/wmw'   => '/wmw.php',
    '/mwm'   => '/mwm.php',
    '/mwmw'  => '/mwmw.php',
    '/start' => '/start.php',
    '/game'  => '/game.php',
];

if (isset($routes[$path])) {
    $_SERVER['SCRIPT_NAME'] = $routes[$path];
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . $routes[$path];
    require __DIR__ . $routes[$path];
    return true;
}

return false;