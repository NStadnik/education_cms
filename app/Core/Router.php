<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes[$request->method] ?? [] as $route) {
            $params = $this->match($route['path'], $request->path);
            if ($params === null) {
                continue;
            }

            [$class, $method] = $route['handler'];
            $controller = new $class();
            return $controller->$method($request, $params);
        }

        return new Response('Not found', 404);
    }

    private function add(string $method, string $path, array $handler): void
    {
        $this->routes[$method][] = ['path' => '/' . trim($path, '/'), 'handler' => $handler];
    }

    private function match(string $route, string $path): ?array
    {
        $routeParts = explode('/', trim($route, '/'));
        $pathParts = explode('/', trim($path, '/'));

        if ($route === '/') {
            return $path === '/' ? [] : null;
        }

        $params = [];
        $i = 0;
        foreach ($routeParts as $part) {
            if (preg_match('/^\{(.+)\}$/', $part, $m)) {
                $name = $m[1];
                if ($name === 'path') {
                    $params[$name] = implode('/', array_slice($pathParts, $i));
                    return $params;
                }
                if (!isset($pathParts[$i])) {
                    return null;
                }
                $params[$name] = $pathParts[$i];
            } elseif (($pathParts[$i] ?? null) !== $part) {
                return null;
            }
            $i++;
        }

        return $i === count($pathParts) ? $params : null;
    }
}
