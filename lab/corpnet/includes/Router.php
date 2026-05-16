<?php
// =============================================================
//  UrbanUpC — Front Controller Router
//  Usage :
//    $r = new Router();
//    $r->get('/path', 'Controller@method');
//    $r->post('/path', 'Controller@method');
//    $r->dispatch($uri, $method);
// =============================================================

class Router {
    private array $routes = [];

    public function get(string $path, string $handler): void {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, string $handler): void {
        $this->routes['POST'][$path] = $handler;
    }

    /**
     * Résout $uri + $method vers le bon contrôleur.
     * Normalise le path (retire trailing slash sauf racine).
     */
    public function dispatch(string $uri, string $method): void {
        // Normalise
        $path = '/' . trim($uri, '/');
        if ($path === '//') $path = '/';

        $table = $this->routes[$method] ?? [];

        if (isset($table[$path])) {
            [$class, $action] = explode('@', $table[$path]);
            $controllerFile = __DIR__ . '/../controllers/' . $class . '.php';
            if (file_exists($controllerFile)) {
                require_once $controllerFile;
                (new $class())->$action();
                return;
            }
        }

        // Fallback 404
        http_response_code(404);
        include __DIR__ . '/../public/errors/404.php';
    }
}
