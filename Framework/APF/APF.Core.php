<?php
/**
 * Adlaire Platform Foundation (APF) - Core Module
 *
 * APF = Adlaire Platform Foundation
 *
 * @package APF
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace APF\Core;

// ============================================================================
// Container - 依存性注入コンテナ
// ============================================================================

class Container {
    private array $bindings = [];
    private array $instances = [];
    private array $aliases = [];
    private array $resolving = [];
    private array $lazy = [];

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

        /* B1: 遅延ロード対応 */
        if (isset($this->lazy[$abstract])) {
            $instance = ($this->lazy[$abstract])($this);
            $this->instances[$abstract] = $instance;
            unset($this->lazy[$abstract]);
            return $instance;
        }

        /* B3: 循環依存検出 */
        if (isset($this->resolving[$abstract])) {
            throw new ContainerException(
                "Circular dependency detected: " . implode(' -> ', array_keys($this->resolving)) . " -> {$abstract}",
                ['chain' => array_keys($this->resolving)]
            );
        }
        $this->resolving[$abstract] = true;

        try {
            $concrete = $this->getConcrete($abstract);
            $object = $this->build($concrete, $parameters);

            if ($this->isShared($abstract)) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        } finally {
            unset($this->resolving[$abstract]);
        }
    }

    public function has(string $abstract): bool {
        $abstract = $this->getAlias($abstract);
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /** B1: 遅延ロード — 初回アクセス時にのみインスタンス化 */
    public function lazy(string $abstract, \Closure $factory): void {
        $this->lazy[$abstract] = $factory;
    }

    /** B1: 条件付きバインド */
    public function bindIf(string $abstract, $concrete = null, bool $shared = false): void {
        if (!$this->has($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
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
    private array $queryMappings = [];
    private array $postMappings = [];
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

    /**
     * Ver.1.7: クエリパラメータ → URI パスのマッピングを登録
     * 例: mapQuery('login', '/login') → ?login を /login としてルーティング
     * 例: mapQuery('ap_api', '/api/{endpoint}', 'endpoint') → ?ap_api=pages を /api/pages に
     */
    public function mapQuery(string $key, string $path, ?string $pathParam = null): self {
        $this->queryMappings[] = compact('key', 'path', 'pathParam');
        return $this;
    }

    /**
     * Ver.1.7: POST ボディパラメータ → URI パスのマッピングを登録
     * 例: mapPost('ap_action', '/dispatch') → POST ap_action=* を /dispatch にルーティング
     */
    public function mapPost(string $key, string $path, ?string $pathParam = null): self {
        $this->postMappings[] = compact('key', 'path', 'pathParam');
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
        $uri = $this->resolveUri($request);

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

    /**
     * Ver.1.7: クエリ/POSTパラメータを URI パスに解決する
     */
    private function resolveUri(Request $request): string {
        /* クエリパラメータマッピング（?login → /login, ?ap_api=pages → /api/pages） */
        foreach ($this->queryMappings as $m) {
            if ($request->query($m['key']) !== null) {
                return $m['pathParam']
                    ? str_replace('{' . $m['pathParam'] . '}', $request->query($m['key'], ''), $m['path'])
                    : $m['path'];
            }
        }

        /* POST ボディマッピング（ap_action=edit_field → /dispatch） */
        if ($request->method() === 'POST') {
            foreach ($this->postMappings as $m) {
                if ($request->post($m['key']) !== null) {
                    return $m['pathParam']
                        ? str_replace('{' . $m['pathParam'] . '}', $request->post($m['key'], ''), $m['path'])
                        : $m['path'];
                }
            }
        }

        return $request->uri();
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

    /** Ver.1.7: サーバー変数アクセス */
    public function server(?string $key = null, $default = null) {
        if (is_null($key)) return $this->server;
        return $this->server[$key] ?? $default;
    }

    /** Ver.1.7: ベースURL取得（スキーム + ホスト） */
    public function baseUrl(): string {
        $scheme = (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        $host = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', $host);
        return $scheme . '://' . $host;
    }

    /** Ver.1.7: ページスラッグ取得 */
    public function slug(): string {
        return $this->query('page', '');
    }

    /** Ver.1.7: 全ヘッダー取得 */
    public function headers(): array {
        return $this->headers;
    }

    /** Ver.1.7: POST リクエストか */
    public function isPost(): bool {
        return $this->method() === 'POST';
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

// ============================================================================
// Exception Hierarchy - カスタム例外クラス (A1)
// ============================================================================

class FrameworkException extends \RuntimeException {
    protected array $context = [];

    public function __construct(string $message = '', array $context = [], int $code = 0, ?\Throwable $previous = null) {
        $this->context = $context;
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): array {
        return $this->context;
    }
}

class ContainerException extends FrameworkException {}
class NotFoundException extends FrameworkException {}
class RoutingException extends FrameworkException {}
class ValidationException extends FrameworkException {
    private array $errors;

    public function __construct(array $errors, string $message = 'Validation failed', int $code = 422) {
        $this->errors = $errors;
        parent::__construct($message, ['errors' => $errors], $code);
    }

    public function getErrors(): array {
        return $this->errors;
    }
}

class MiddlewareException extends FrameworkException {}

// ============================================================================
// HookManager - フック機構 (D2)
// ============================================================================

class HookManager {
    private array $hooks = [];

    public function register(string $name, callable $callback, int $priority = 10): void {
        $this->hooks[$name][] = ['callback' => $callback, 'priority' => $priority];
    }

    public function run(string $name, mixed ...$args): void {
        if (!isset($this->hooks[$name])) return;

        $sorted = $this->hooks[$name];
        usort($sorted, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($sorted as $hook) {
            try {
                ($hook['callback'])(...$args);
            } catch (\Throwable $e) {
                // Hook failure should not break the flow
            }
        }
    }

    public function filter(string $name, mixed $value, mixed ...$args): mixed {
        if (!isset($this->hooks[$name])) return $value;

        $sorted = $this->hooks[$name];
        usort($sorted, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($sorted as $hook) {
            try {
                $value = ($hook['callback'])($value, ...$args);
            } catch (\Throwable $e) {
                // Filter failure preserves current value
            }
        }

        return $value;
    }

    public function has(string $name): bool {
        return isset($this->hooks[$name]) && count($this->hooks[$name]) > 0;
    }

    public function remove(string $name): void {
        unset($this->hooks[$name]);
    }

    public function clear(): void {
        $this->hooks = [];
    }
}

// ============================================================================
// PluginManager - プラグイン機構 (D3)
// ============================================================================

class PluginManager {
    private Container $container;
    private HookManager $hooks;
    private array $plugins = [];
    private array $loaded = [];

    public function __construct(Container $container, HookManager $hooks) {
        $this->container = $container;
        $this->hooks = $hooks;
    }

    public function register(string $name, array $config): void {
        $this->plugins[$name] = array_merge([
            'name' => $name,
            'version' => '1.0.0',
            'boot' => null,
            'dependencies' => [],
            'enabled' => true,
        ], $config);
    }

    public function boot(): void {
        foreach ($this->plugins as $name => $plugin) {
            if (!$plugin['enabled'] || isset($this->loaded[$name])) continue;
            $this->loadPlugin($name, $plugin);
        }
    }

    private function loadPlugin(string $name, array $plugin): void {
        foreach ($plugin['dependencies'] as $dep) {
            if (!isset($this->loaded[$dep])) {
                if (isset($this->plugins[$dep])) {
                    $this->loadPlugin($dep, $this->plugins[$dep]);
                } else {
                    throw new FrameworkException("Plugin dependency not found: {$dep}", ['plugin' => $name]);
                }
            }
        }

        if (is_callable($plugin['boot'])) {
            ($plugin['boot'])($this->container, $this->hooks);
        }

        $this->loaded[$name] = true;
        $this->hooks->run('plugin:loaded', $name, $plugin);
    }

    public function isLoaded(string $name): bool {
        return isset($this->loaded[$name]);
    }

    public function getAll(): array {
        return $this->plugins;
    }

    public function disable(string $name): void {
        if (isset($this->plugins[$name])) {
            $this->plugins[$name]['enabled'] = false;
        }
    }
}

// ============================================================================
// DebugCollector - デバッグ・プロファイル機能 (C1)
// ============================================================================

class DebugCollector {
    private static bool $enabled = false;
    private static array $events = [];
    private static array $timers = [];
    private static array $queries = [];
    private static float $startTime = 0;

    public static function enable(): void {
        self::$enabled = true;
        self::$startTime = hrtime(true);
    }

    public static function isEnabled(): bool {
        return self::$enabled;
    }

    public static function logEvent(string $category, string $message, array $context = []): void {
        if (!self::$enabled) return;
        self::$events[] = [
            'time' => (hrtime(true) - self::$startTime) / 1_000_000,
            'category' => $category,
            'message' => $message,
            'context' => $context,
            'memory' => memory_get_usage(true),
        ];
    }

    public static function startTimer(string $name): void {
        if (!self::$enabled) return;
        self::$timers[$name] = hrtime(true);
    }

    public static function stopTimer(string $name): float {
        if (!self::$enabled || !isset(self::$timers[$name])) return 0.0;
        $elapsed = (hrtime(true) - self::$timers[$name]) / 1_000_000;
        self::logEvent('timer', "{$name}: {$elapsed}ms", ['elapsed_ms' => $elapsed]);
        unset(self::$timers[$name]);
        return $elapsed;
    }

    public static function logQuery(string $sql, array $bindings = [], float $time = 0): void {
        if (!self::$enabled) return;
        self::$queries[] = ['sql' => $sql, 'bindings' => $bindings, 'time_ms' => $time];
    }

    public static function getReport(): array {
        return [
            'total_time_ms' => (hrtime(true) - self::$startTime) / 1_000_000,
            'memory_peak' => memory_get_peak_usage(true),
            'events' => self::$events,
            'queries' => self::$queries,
            'event_count' => count(self::$events),
            'query_count' => count(self::$queries),
        ];
    }

    public static function reset(): void {
        self::$events = [];
        self::$timers = [];
        self::$queries = [];
        self::$startTime = hrtime(true);
    }
}

// ============================================================================
// ErrorBoundary - エラー境界 (A1)
// ============================================================================

class ErrorBoundary {
    private static array $handlers = [];

    public static function register(string $type, callable $handler): void {
        self::$handlers[$type] = $handler;
    }

    public static function wrap(callable $callback, string $errorType = 'default'): mixed {
        try {
            return $callback();
        } catch (\Throwable $e) {
            if (isset(self::$handlers[$errorType])) {
                return (self::$handlers[$errorType])($e);
            }
            if (isset(self::$handlers['default'])) {
                return (self::$handlers['default'])($e);
            }
            throw $e;
        }
    }

    public static function wrapResponse(callable $callback): Response {
        try {
            return $callback();
        } catch (ValidationException $e) {
            return Response::json(['errors' => $e->getErrors()], 422);
        } catch (NotFoundException $e) {
            return Response::json(['error' => $e->getMessage()], 404);
        } catch (FrameworkException $e) {
            return Response::json(['error' => $e->getMessage(), 'context' => $e->getContext()], 500);
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Internal Server Error'], 500);
        }
    }
}
