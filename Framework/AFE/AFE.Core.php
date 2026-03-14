<?php
/**
 * Adlaire Framework Ecosystem (AFE) - Core Module
 * 
 * AFE = Adlaire Framework Ecosystem
 * 
 * @package AFE
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace AFE\Core;

// ============================================================================
// Container - 依存性注入コンテナ
// ============================================================================

class Container {
    private array $bindings = [];
    private array $instances = [];
    private array $aliases = [];

    public function bind(string $abstract, $concrete = null, bool $shared = false): void {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    public function singleton(string $abstract, $concrete = null): void {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, $instance): void {
        $this->instances[$abstract] = $instance;
    }

    public function alias(string $alias, string $abstract): void {
        $this->aliases[$alias] = $abstract;
    }

    public function make(string $abstract, array $parameters = []) {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);
        $object = $this->build($concrete, $parameters);

        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    public function has(string $abstract): bool {
        $abstract = $this->getAlias($abstract);
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    private function getAlias(string $abstract): string {
        return $this->aliases[$abstract] ?? $abstract;
    }

    private function getConcrete(string $abstract) {
        if (!isset($this->bindings[$abstract])) {
            return $abstract;
        }
        return $this->bindings[$abstract]['concrete'];
    }

    private function isShared(string $abstract): bool {
        return isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared'] === true;
    }

    private function build($concrete, array $parameters = []) {
        if ($concrete instanceof \Closure) {
            return $concrete($this, $parameters);
        }

        if (is_string($concrete) && class_exists($concrete)) {
            $reflector = new \ReflectionClass($concrete);
            
            if (!$reflector->isInstantiable()) {
                throw new \Exception("Class {$concrete} is not instantiable");
            }

            $constructor = $reflector->getConstructor();

            if (is_null($constructor)) {
                return new $concrete;
            }

            $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);
            return $reflector->newInstanceArgs($dependencies);
        }

        return $concrete;
    }

    private function resolveDependencies(array $dependencies, array $parameters): array {
        $results = [];

        foreach ($dependencies as $dependency) {
            if (array_key_exists($dependency->name, $parameters)) {
                $results[] = $parameters[$dependency->name];
                continue;
            }

            $type = $dependency->getType();
            
            if ($type && !$type->isBuiltin()) {
                $results[] = $this->make($type->getName());
            } elseif ($dependency->isDefaultValueAvailable()) {
                $results[] = $dependency->getDefaultValue();
            } else {
                throw new \Exception("Unable to resolve dependency [{$dependency->name}]");
            }
        }

        return $results;
    }
}

// ============================================================================
// Router - ルーティング
// ============================================================================

class Router {
    private array $routes = [];
    private array $middlewares = [];
    private array $groupStack = [];
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function get(string $uri, $action): self {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, $action): self {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, $action): self {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function patch(string $uri, $action): self {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function delete(string $uri, $action): self {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function any(string $uri, $action): self {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        foreach ($methods as $method) {
            $this->addRoute($method, $uri, $action);
        }
        return $this;
    }

    public function group(array $attributes, \Closure $callback): void {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function middleware($middleware): self {
        if (empty($this->routes)) {
            $this->middlewares[] = $middleware;
        } else {
            $lastRoute = array_key_last($this->routes);
            $this->routes[$lastRoute]['middleware'][] = $middleware;
        }
        return $this;
    }

    private function addRoute(string $method, string $uri, $action): self {
        $uri = $this->applyGroupPrefix($uri);
        $middleware = $this->getGroupMiddleware();

        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => $middleware,
            'pattern' => $this->compilePattern($uri)
        ];

        return $this;
    }

    private function applyGroupPrefix(string $uri): string {
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $uri = trim($group['prefix'], '/') . '/' . trim($uri, '/');
            }
        }
        return '/' . trim($uri, '/');
    }

    private function getGroupMiddleware(): array {
        $middleware = [];
        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $middleware = array_merge($middleware, (array)$group['middleware']);
            }
        }
        return $middleware;
    }

    private function compilePattern(string $uri): string {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $uri);
        $pattern = preg_replace('/\{(\w+)\?\}/', '(?P<$1>[^/]*)', $pattern);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request): Response {
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $request->setParams($params);

                return $this->runRoute($route, $request);
            }
        }

        return new Response('Not Found', 404);
    }

    private function runRoute(array $route, Request $request): Response {
        $middleware = array_merge($this->middlewares, $route['middleware']);
        
        $pipeline = array_reduce(
            array_reverse($middleware),
            function ($next, $middleware) {
                return function ($request) use ($next, $middleware) {
                    if (is_string($middleware)) {
                        $middleware = $this->container->make($middleware);
                    }
                    return $middleware->handle($request, $next);
                };
            },
            function ($request) use ($route) {
                return $this->callAction($route['action'], $request);
            }
        );

        return $pipeline($request);
    }

    private function callAction($action, Request $request): Response {
        if ($action instanceof \Closure) {
            $result = $action($request);
        } elseif (is_array($action)) {
            [$controller, $method] = $action;
            $controller = $this->container->make($controller);
            $result = $controller->$method($request);
        } elseif (is_string($action) && strpos($action, '@') !== false) {
            [$controller, $method] = explode('@', $action);
            $controller = $this->container->make($controller);
            $result = $controller->$method($request);
        } else {
            throw new \Exception('Invalid route action');
        }

        if ($result instanceof Response) {
            return $result;
        }

        return new Response($result);
    }
}

// ============================================================================
// Request - HTTPリクエスト
// ============================================================================

class Request {
    private array $query;
    private array $post;
    private array $files;
    private array $cookies;
    private array $server;
    private array $headers;
    private array $params = [];
    private ?string $body = null;

    public function __construct() {
        $this->query = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->cookies = $_COOKIE;
        $this->server = $_SERVER;
        $this->headers = $this->parseHeaders();
        $this->body = file_get_contents('php://input');
    }

    private function parseHeaders(): array {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }

    public function method(): string {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return parse_url($uri, PHP_URL_PATH) ?? '/';
    }

    public function query(?string $key = null, $default = null) {
        if (is_null($key)) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function post(?string $key = null, $default = null) {
        if (is_null($key)) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    public function input(?string $key = null, $default = null) {
        $input = array_merge($this->query, $this->post);
        
        if (is_null($key)) {
            return $input;
        }
        return $input[$key] ?? $default;
    }

    public function json(?string $key = null, $default = null) {
        static $json = null;
        
        if (is_null($json)) {
            $json = json_decode($this->body, true) ?? [];
        }

        if (is_null($key)) {
            return $json;
        }
        return $json[$key] ?? $default;
    }

    public function file(string $key): ?array {
        return $this->files[$key] ?? null;
    }

    public function cookie(string $key, $default = null) {
        return $this->cookies[$key] ?? $default;
    }

    public function header(string $key, $default = null) {
        return $this->headers[$key] ?? $default;
    }

    public function ip(): string {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function isJson(): bool {
        return strpos($this->header('Content-Type', ''), 'application/json') !== false;
    }

    public function isAjax(): bool {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function setParams(array $params): void {
        $this->params = $params;
    }

    public function param(string $key, $default = null) {
        return $this->params[$key] ?? $default;
    }
}

// ============================================================================
// Response - HTTPレスポンス
// ============================================================================

class Response {
    private $content;
    private int $statusCode;
    private array $headers = [];

    public function __construct($content = '', int $statusCode = 200, array $headers = []) {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public static function json($data, int $statusCode = 200, array $headers = []): self {
        $headers['Content-Type'] = 'application/json';
        return new self(json_encode($data), $statusCode, $headers);
    }

    public static function html(string $html, int $statusCode = 200, array $headers = []): self {
        $headers['Content-Type'] = 'text/html; charset=UTF-8';
        return new self($html, $statusCode, $headers);
    }

    public static function redirect(string $url, int $statusCode = 302): self {
        return new self('', $statusCode, ['Location' => $url]);
    }

    public function withHeader(string $key, string $value): self {
        $this->headers[$key] = $value;
        return $this;
    }

    public function withCookie(string $name, string $value, int $expires = 0, string $path = '/'): self {
        setcookie($name, $value, $expires, $path);
        return $this;
    }

    public function send(): void {
        http_response_code($this->statusCode);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        if (is_array($this->content) || is_object($this->content)) {
            echo json_encode($this->content);
        } else {
            echo $this->content;
        }
    }

    public function getContent() {
        return $this->content;
    }

    public function getStatusCode(): int {
        return $this->statusCode;
    }
}

// ============================================================================
// Middleware - ミドルウェア基底クラス
// ============================================================================

abstract class Middleware {
    abstract public function handle(Request $request, \Closure $next): Response;
}
