<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Request;

define('BASE_PATH', __DIR__);

require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/Core/Autoloader.php';

Autoloader::register(BASE_PATH . '/app');

$app = new App(BASE_PATH);
$app->boot();
$app->handle(Request::capture())->send();
