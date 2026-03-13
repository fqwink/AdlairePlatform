<?php
/**
 * framework/Kernel.php - フレームワークカーネル
 * 
 * アプリケーションのライフサイクル全体を管理する中核クラス。
 * 
 * @package AdlairePlatform\Framework
 * @version 2.0.0-alpha.1
 * @author AdlairePlatform Team
 */

namespace AdlairePlatform\Framework;

class Kernel {
    
    private string $basePath;
    private Container $container;
    private ConfigManager $config;
    private EngineManager $engineManager;
    private EventDispatcher $events;
    private Router $router;
    
    private bool $booted = false;
    private float $bootTime = 0.0;
    
    /**
     * コンストラクタ
     * 
     * @param string $basePath アプリケーションのベースパス
     */
    public function __construct(string $basePath) {
        $this->basePath = rtrim($basePath, '/');
        $this->bootTime = microtime(true);
    }
    
    /**
     * フレームワークを起動
     * 
     * 1. DIコンテナ初期化
     * 2. 設定マネージャー初期化
     * 3. イベントディスパッチャー初期化
     * 4. ルーター初期化
     * 5. エンジンマネージャー初期化
     * 6. サービスプロバイダー登録
     * 7. エンジン自動検出・起動
     * 8. 起動完了イベント発火
     * 
     * @return void
     */
    public function boot(): void {
        if ($this->booted) {
            return;
        }
        
        // 1. DIコンテナ初期化
        $this->container = new Container();
        $this->container->instance('kernel', $this);
        
        // 2. 設定マネージャー初期化
        $this->config = new ConfigManager($this->basePath . '/config');
        $this->container->instance('config', $this->config);
        
        // 3. イベントディスパッチャー初期化
        $this->events = new EventDispatcher();
        $this->container->instance('events', $this->events);
        
        // デバッグモードならイベント履歴記録を有効化
        if ($this->config->get('app.debug', false)) {
            $this->events->enableHistory();
        }
        
        // 4. ルーター初期化
        $this->router = new Router($this->container);
        $this->container->instance('router', $this->router);
        
        // 5. エンジンマネージャー初期化
        $this->engineManager = new EngineManager(
            $this->container,
            $this->config,
            $this->events
        );
        $this->container->instance('engines', $this->engineManager);
        
        // 6. サービスプロバイダー登録
        $this->registerServiceProviders();
        
        // 7. エンジン自動検出・起動
        $enginesPath = $this->basePath . '/engines';
        $pluginsPath = $this->basePath . '/plugins';
        
        try {
            $this->engineManager->discoverEngines($enginesPath);
            $this->engineManager->discoverPlugins($pluginsPath);
            $this->engineManager->bootAll();
        } catch (\Throwable $e) {
            $this->handleBootError($e);
        }
        
        // 8. 起動完了イベント
        $bootDuration = microtime(true) - $this->bootTime;
        $this->events->dispatch('framework.booted', [
            'time' => $bootDuration,
            'engines' => count($this->engineManager->all()),
            'memory' => memory_get_usage(true) / 1024 / 1024 // MB
        ]);
        
        $this->booted = true;
        
        // ログ出力
        if ($this->container->has('logger')) {
            $logger = $this->container->make('logger');
            $logger->info('Framework booted', [
                'duration_ms' => round($bootDuration * 1000, 2),
                'engines' => count($this->engineManager->all())
            ]);
        }
    }
    
    /**
     * HTTPリクエストを処理
     * 
     * @return Response レスポンスオブジェクト
     */
    public function handleRequest(): Response {
        if (!$this->booted) {
            throw new \RuntimeException('Framework not booted. Call boot() first.');
        }
        
        $this->events->dispatch('request.received', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $startTime = microtime(true);
        
        try {
            // ルーター経由でリクエストをディスパッチ
            $response = $this->router->dispatch(
                $_SERVER['REQUEST_METHOD'] ?? 'GET',
                $_SERVER['REQUEST_URI'] ?? '/'
            );
            
            $duration = microtime(true) - $startTime;
            
            $this->events->dispatch('request.handled', [
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'status' => $response->getStatusCode()
            ]);
            
            return $response;
            
        } catch (\Throwable $e) {
            $this->events->dispatch('request.error', [
                'exception' => $e,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->handleException($e);
        }
    }
    
    /**
     * シャットダウン処理
     * 
     * @return void
     */
    public function shutdown(): void {
        if (!$this->booted) {
            return;
        }
        
        $this->events->dispatch('framework.shutting_down');
        
        // 全エンジンをシャットダウン（逆順）
        $this->engineManager->shutdownAll();
        
        $totalTime = microtime(true) - $this->bootTime;
        
        $this->events->dispatch('framework.shutdown', [
            'total_time_ms' => round($totalTime * 1000, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ]);
        
        // ログ出力
        if ($this->container->has('logger')) {
            $logger = $this->container->make('logger');
            $logger->info('Framework shutdown', [
                'total_time_ms' => round($totalTime * 1000, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
            ]);
        }
        
        $this->booted = false;
    }
    
    /**
     * DIコンテナを取得
     * 
     * @return Container
     */
    public function container(): Container {
        return $this->container;
    }
    
    /**
     * 設定マネージャーを取得
     * 
     * @return ConfigManager
     */
    public function config(): ConfigManager {
        return $this->config;
    }
    
    /**
     * イベントディスパッチャーを取得
     * 
     * @return EventDispatcher
     */
    public function events(): EventDispatcher {
        return $this->events;
    }
    
    /**
     * エンジンマネージャーを取得
     * 
     * @return EngineManager
     */
    public function engines(): EngineManager {
        return $this->engineManager;
    }
    
    /**
     * ルーターを取得
     * 
     * @return Router
     */
    public function router(): Router {
        return $this->router;
    }
    
    /**
     * ベースパスを取得
     * 
     * @return string
     */
    public function basePath(): string {
        return $this->basePath;
    }
    
    /**
     * 起動済みか判定
     * 
     * @return bool
     */
    public function isBooted(): bool {
        return $this->booted;
    }
    
    /**
     * サービスプロバイダーを登録
     * 
     * @return void
     */
    private function registerServiceProviders(): void {
        $providers = $this->config->get('services.providers', []);
        
        foreach ($providers as $providerClass) {
            if (!class_exists($providerClass)) {
                if ($this->config->get('app.debug', false)) {
                    error_log("Service provider class not found: {$providerClass}");
                }
                continue;
            }
            
            try {
                $provider = new $providerClass($this->container);
                
                if (method_exists($provider, 'register')) {
                    $provider->register();
                }
                
                if (method_exists($provider, 'boot')) {
                    $provider->boot();
                }
                
            } catch (\Throwable $e) {
                if ($this->config->get('app.debug', false)) {
                    error_log("Failed to register service provider {$providerClass}: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * 起動エラーをハンドリング
     * 
     * @param \Throwable $e
     * @return void
     * @throws \Throwable
     */
    private function handleBootError(\Throwable $e): void {
        error_log("Framework boot error: " . $e->getMessage());
        error_log($e->getTraceAsString());
        
        // デバッグモードなら例外を再スロー
        if ($this->config->get('app.debug', false)) {
            throw $e;
        }
        
        // プロダクションモードならログのみ
        if ($this->container->has('logger')) {
            $logger = $this->container->make('logger');
            $logger->error('Framework boot failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
    
    /**
     * 例外をハンドリング
     * 
     * @param \Throwable $e
     * @return Response
     */
    private function handleException(\Throwable $e): Response {
        // ログ記録
        if ($this->container->has('logger')) {
            $logger = $this->container->make('logger');
            $logger->error('Unhandled exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // デバッグモードならスタックトレース表示
        if ($this->config->get('app.debug', false)) {
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title>';
            $html .= '<style>body{font-family:monospace;margin:40px;} pre{background:#f4f4f4;padding:20px;border-left:4px solid #e74c3c;}</style>';
            $html .= '</head><body>';
            $html .= '<h1>🚨 Exception: ' . htmlspecialchars(get_class($e)) . '</h1>';
            $html .= '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            $html .= '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ' (Line ' . $e->getLine() . ')</p>';
            $html .= '<h2>Stack Trace:</h2><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            $html .= '</body></html>';
            
            return new Response($html, 500);
        }
        
        // プロダクションモードではシンプルなエラーメッセージ
        return new Response('Internal Server Error', 500);
    }
}

/**
 * Response クラス - HTTPレスポンスを表現
 */
class Response {
    
    private string $content;
    private int $statusCode;
    private array $headers = [];
    
    public function __construct(string $content, int $statusCode = 200, array $headers = []) {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }
    
    public function send(): void {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        
        echo $this->content;
    }
    
    public function getContent(): string {
        return $this->content;
    }
    
    public function getStatusCode(): int {
        return $this->statusCode;
    }
    
    public function getHeaders(): array {
        return $this->headers;
    }
}

/**
 * JsonResponse クラス - JSON レスポンス
 */
class JsonResponse extends Response {
    
    public function __construct($data, int $statusCode = 200, array $headers = []) {
        $headers['Content-Type'] = 'application/json; charset=UTF-8';
        $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        parent::__construct($content, $statusCode, $headers);
    }
}
