<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{0:string,1:string,2:callable|array}> */
    private array $routes = [];

    public function get(string $p, callable|array $h): void  { $this->add('GET', $p, $h); }
    public function post(string $p, callable|array $h): void { $this->add('POST', $p, $h); }

    private function add(string $m, string $p, callable|array $h): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $p) . '$#';
        $this->routes[] = [$m, $regex, $h];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = request_path();
        if ($method === 'HEAD') $method = 'GET';

        foreach ($this->routes as [$m, $regex, $h]) {
            if ($m !== $method || !preg_match($regex, $path, $mt)) continue;
            $params = array_map('urldecode', array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY));

            // Every state-changing request needs a valid CSRF token (the Razorpay webhook is signature-verified instead).
            if ($method === 'POST' && $path !== '/webhook/razorpay') csrf_require();

            if (is_array($h)) {
                [$class, $fn] = $h;
                (new $class())->$fn($params);
            } else {
                $h($params);
            }
            return;
        }
        not_found();
    }
}
