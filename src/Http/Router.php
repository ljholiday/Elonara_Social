<?php
declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable, regex:string}> */
    private array $routes = [];

    /**
     * Register a GET route
     */
    public function get(string $pattern, callable $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    /**
     * Register a POST route
     */
    public function post(string $pattern, callable $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $pattern, callable $handler): void
    {
        $this->addRoute('DELETE', $pattern, $handler);
    }

    /**
     * Register a route for any method
     */
    public function any(string $pattern, callable $handler): void
    {
        $this->addRoute('*', $pattern, $handler);
    }

    /**
     * Register a route with specific method
     */
    public function addRoute(string $method, string $pattern, callable $handler): void
    {
        $regex = $this->patternToRegex($pattern);

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'regex' => $regex,
        ];
    }

    /**
     * Dispatch request to matching route
     *
     * @return mixed Handler return value, or null if no match
     */
    public function dispatch(Request $request)
    {
        $path = $request->path();
        $method = $request->method();

        foreach ($this->routes as $route) {
            if ($route['method'] !== '*' && $route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                return ($route['handler'])($request, ...$matches);
            }
        }

        return null;
    }

    /**
     * Convert route pattern to regex
     */
    private function patternToRegex(string $pattern): string
    {
        $regex = preg_replace_callback(
            '/\{([^}]+)\}/',
            static function ($matches) {
                $param = $matches[1];
                if (str_ends_with($param, '?')) {
                    return '([^/]*)';
                }
                return '([^/]+)';
            },
            $pattern
        );

        $regex = str_replace('/', '\/', $regex ?? $pattern);

        return '/^' . $regex . '$/';
    }
}
