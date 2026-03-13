<?php
/**
 * engines/BaseEngine.php - エンジン基底クラス
 * 
 * 全エンジンが継承すべき抽象基底クラス。
 * EngineInterface を実装し、共通機能を提供する。
 * 
 * @package AdlairePlatform\Engines
 * @version 2.0.0-alpha.1
 */

use AdlairePlatform\Framework\Interfaces\EngineInterface;
use AdlairePlatform\Framework\Container;
use AdlairePlatform\Framework\EventDispatcher;
use AdlairePlatform\Framework\ConfigManager;

abstract class BaseEngine implements EngineInterface {
    
    /** @var Container DIコンテナ */
    protected Container $container;
    
    /** @var EventDispatcher イベントディスパッチャー */
    protected EventDispatcher $events;
    
    /** @var ConfigManager 設定マネージャー */
    protected ConfigManager $config;
    
    /** @var array<string, mixed> このエンジン固有の設定 */
    protected array $engineConfig = [];
    
    /** @var bool 起動済みフラグ */
    private bool $booted = false;
    
    /**
     * コンストラクタ（依存性注入）
     * 
     * フレームワークのDIコンテナから自動的に注入される。
     * サブクラスでオーバーライドする場合は parent::__construct() を呼ぶこと。
     * 
     * @param Container $container DIコンテナ
     * @param EventDispatcher $events イベントディスパッチャー
     * @param ConfigManager $config 設定マネージャー
     */
    public function __construct(
        Container $container,
        EventDispatcher $events,
        ConfigManager $config
    ) {
        $this->container = $container;
        $this->events = $events;
        $this->config = $config;
        
        // エンジン固有の設定を読み込み
        // config/engines.php で定義された設定を取得
        $this->engineConfig = $config->get('engines.' . $this->getName(), []);
    }
    
    /**
     * {@inheritdoc}
     * 
     * デフォルト実装: クラス名から Engine を除去して小文字化
     * 例: AdminEngine → 'admin', ApiEngine → 'api'
     * 
     * 独自のエンジン名が必要な場合はオーバーライドすること。
     */
    public function getName(): string {
        $className = (new \ReflectionClass($this))->getShortName();
        $name = str_replace('Engine', '', $className);
        return strtolower($name);
    }
    
    /**
     * {@inheritdoc}
     * 
     * デフォルトバージョン: '1.0.0'
     * サブクラスでオーバーライド推奨。
     */
    public function getVersion(): string {
        return '1.0.0';
    }
    
    /**
     * {@inheritdoc}
     * 
     * デフォルト: 依存なし（空配列）
     * 依存エンジンがある場合はオーバーライドすること。
     */
    public function getDependencies(): array {
        return [];
    }
    
    /**
     * {@inheritdoc}
     * 
     * デフォルト実装: 何もしない
     * サブクラスで必要に応じてオーバーライド。
     */
    public function register(): void {
        // デフォルト実装なし
    }
    
    /**
     * {@inheritdoc}
     * 
     * デフォルト実装: 何もしない
     * サブクラスで必要に応じてオーバーライド。
     */
    public function boot(): void {
        $this->booted = true;
    }
    
    /**
     * {@inheritdoc}
     * 
     * デフォルト実装: 何もしない
     * サブクラスで必要に応じてオーバーライド。
     */
    public function shutdown(): void {
        // デフォルト実装なし
    }
    
    /**
     * 起動済みか判定
     * 
     * @return bool 起動済みならtrue
     */
    public function isBooted(): bool {
        return $this->booted;
    }
    
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  イベントシステム（簡易API）
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    
    /**
     * イベントを発火
     * 
     * エンジン名をプレフィックスとして自動付与される。
     * 例: $this->emit('content.updated') → '{engine}.content.updated'
     * 
     * @param string $event イベント名
     * @param mixed $payload イベントペイロード
     * @return void
     */
    protected function emit(string $event, $payload = null): void {
        $fullEvent = $this->getName() . '.' . $event;
        $this->events->dispatch($fullEvent, $payload);
    }
    
    /**
     * イベントリスナーを登録
     * 
     * @param string $event イベント名（フルネーム）
     * @param callable $listener リスナー関数
     * @param int $priority 優先度（高い方が先に実行）
     * @return void
     */
    protected function on(string $event, callable $listener, int $priority = 0): void {
        $this->events->listen($event, $listener, $priority);
    }
    
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  DIコンテナ（簡易API）
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    
    /**
     * DIコンテナからサービスを解決
     * 
     * @param string $abstract サービス名またはクラス名
     * @return mixed 解決されたサービス
     */
    protected function resolve(string $abstract): mixed {
        return $this->container->make($abstract);
    }
    
    /**
     * DIコンテナにサービスを登録
     * 
     * @param string $abstract サービス名
     * @param mixed $concrete 実体（クロージャ、クラス名、インスタンス）
     * @param bool $singleton シングルトンとして登録するか
     * @return void
     */
    protected function bind(string $abstract, $concrete = null, bool $singleton = false): void {
        $this->container->bind($abstract, $concrete, $singleton);
    }
    
    /**
     * DIコンテナにシングルトンとして登録
     * 
     * @param string $abstract サービス名
     * @param mixed $concrete 実体
     * @return void
     */
    protected function singleton(string $abstract, $concrete = null): void {
        $this->container->singleton($abstract, $concrete);
    }
    
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  設定管理（簡易API）
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    
    /**
     * エンジン固有の設定を取得
     * 
     * config/engines.php で定義された設定値を取得。
     * 例: $this->getConfig('cache_enabled', true)
     * 
     * @param string $key 設定キー
     * @param mixed $default デフォルト値
     * @return mixed 設定値
     */
    protected function getConfig(string $key, $default = null): mixed {
        return $this->engineConfig[$key] ?? $default;
    }
    
    /**
     * グローバル設定を取得
     * 
     * config/ 配下の全設定ファイルにアクセス可能。
     * 例: $this->getGlobalConfig('app.debug', false)
     * 
     * @param string $key ドット記法のキー（例: 'app.timezone'）
     * @param mixed $default デフォルト値
     * @return mixed 設定値
     */
    protected function getGlobalConfig(string $key, $default = null): mixed {
        return $this->config->get($key, $default);
    }
    
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  ロギング（簡易API）
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    
    /**
     * ログを出力（Loggerエンジンへ委譲）
     * 
     * Loggerエンジンが利用可能な場合、自動的にそちらへ委譲される。
     * エンジン名は自動的にコンテキストに追加される。
     * 
     * @param string $level ログレベル（debug, info, warning, error）
     * @param string $message メッセージ
     * @param array $context コンテキスト情報
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void {
        // エンジン名を自動追加
        $context['engine'] = $this->getName();
        
        // Loggerエンジンが利用可能なら委譲
        if ($this->container->has('logger')) {
            try {
                $logger = $this->container->make('logger');
                
                if (method_exists($logger, $level)) {
                    $logger->{$level}($message, $context);
                    return;
                }
            } catch (\Throwable $e) {
                // Logger利用失敗時はerror_logにフォールバック
                error_log("Logger failed: {$e->getMessage()}");
            }
        }
        
        // フォールバック: error_log
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        error_log("[{$level}] [{$this->getName()}] {$message} {$contextStr}");
    }
    
    /**
     * デバッグログ
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function debug(string $message, array $context = []): void {
        $this->log('debug', $message, $context);
    }
    
    /**
     * 情報ログ
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function info(string $message, array $context = []): void {
        $this->log('info', $message, $context);
    }
    
    /**
     * 警告ログ
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function warning(string $message, array $context = []): void {
        $this->log('warning', $message, $context);
    }
    
    /**
     * エラーログ
     * 
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function error(string $message, array $context = []): void {
        $this->log('error', $message, $context);
    }
    
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  ユーティリティ
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    
    /**
     * エンジン情報を配列で取得
     * 
     * @return array{name: string, version: string, dependencies: array, booted: bool}
     */
    public function toArray(): array {
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'dependencies' => $this->getDependencies(),
            'booted' => $this->booted
        ];
    }
    
    /**
     * エンジン情報をJSON文字列で取得
     * 
     * @return string
     */
    public function toJson(): string {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    /**
     * エンジン説明を文字列で取得
     * 
     * @return string
     */
    public function __toString(): string {
        return sprintf(
            '%s (v%s) [%s]',
            $this->getName(),
            $this->getVersion(),
            $this->booted ? 'booted' : 'not booted'
        );
    }
}
