<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/Core/Autoloader.php';

App\Core\Autoloader::register(BASE_PATH . '/app');

$app = new App(BASE_PATH);
$app->boot();
$app->handle(Request::capture())->send();
