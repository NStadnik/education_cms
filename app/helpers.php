<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_path(string $path = ''): string
{
    return BASE_PATH . ($path ? '/' . ltrim($path, '/') : '');
}

function url(string $path = ''): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $path = '/' . ltrim($path, '/');
    return ($base === '/' ? '' : $base) . $path;
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 302);
    exit;
}

function selected($left, $right): string
{
    return (string) $left === (string) $right ? 'selected' : '';
}

function checked($value): string
{
    return $value ? 'checked' : '';
}

function excerpt(string $value, int $length = 160): string
{
    $value = trim(strip_tags($value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length);
    }
    return substr($value, 0, $length);
}
