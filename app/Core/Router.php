<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionFunction;
use ReflectionMethod;

class Router
{
    private array $routes = [];
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function add(string $method, string $path, callable $handler): void
    {
        // Tiny route registry: method + path + callable.
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            // Convert /tasks/{id} into a regex with named capture groups.
            $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (!preg_match($pattern, $path, $matches)) {
                continue;
            }

            $params = array_filter(
                $matches,
                static fn ($key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            // Different routes use different closure signatures, so call them safely.
            $handler = $route['handler'];
            $reflection = is_array($handler)
                ? new ReflectionMethod($handler[0], $handler[1])
                : new ReflectionFunction($handler);

            $parameterCount = $reflection->getNumberOfParameters();

            if ($parameterCount === 0) {
                call_user_func($handler);
            } elseif ($parameterCount === 1) {
                call_user_func($handler, $params);
            } else {
                call_user_func($handler, $params, $this->config);
            }
            return;
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Route not found']);
    }
}
