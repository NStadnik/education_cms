<?php

declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    public static function register(string $appPath): void
    {
        spl_autoload_register(static function (string $class) use ($appPath): void {
            if (!str_starts_with($class, 'App\\')) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, 4));
            $file = $appPath . '/' . $relative . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }
}
