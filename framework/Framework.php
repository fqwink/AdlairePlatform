<?php
/**
 * Framework - Adlaire Framework Ecosystem (AFE) Core
 *
 * AFEは統合エンジン駆動フレームワークです。
 * Kernel, Container, EngineManager, EventDispatcherの機能を
 * 1つのクラスに統合した軽量フレームワーク。
 *
 * @package Adlaire Framework Ecosystem
 * @version 2.0.0
 * @since   2026-03-13
 * @link    https://github.com/fqwink/AdlairePlatform (current)
 * @link    TBD - 将来的に独立リポジトリ化予定
 */

require_once __DIR__ . '/EngineInterface.php';

class Framework
{
    // ==================== プロパティ ====================

    /**
     * @var array<string, mixed> サービスコンテナ（DIコンテナ）
     */
    private array $services = [];

    /**
     * @var array<string, callable> サービスファクトリー
     */
    private array $factories = [];

    /**
     * @var array<string, bool> シングルトンフラグ
     */
    private array $singletons = [];

    /**
     * @var array<string, EngineInterface> 登録済みエンジン
     */
    private array $engines = [];

    /**
     * @var array<string> 起動済みエンジン名
     */
    private array $bootedEngines = [];

    /**
     * @var array<string, array<array{listener: callable, priority: int}>> イベントリスナー
     */
    private array $listeners = [];

    /**
     * @var array グローバル設定
     */
    private array $config = [];

    /**
     * @var bool フレームワーク起動済みフラグ
     */
    private bool $booted = false;

    /**
     * @var self|null シングルトンインスタンス
     */
    private static ?self $instance = null;

    // ==================== Kernel機能 ====================

    /**
     * コンストラクタ（プライベート：シングルトン）
     *
     * @param array $config 初期設定
     */
    private function __construct(array $config = [])
    {
        $this->config = $config;
        $this->registerCoreServices();
    }

    /**
     * フレームワークインスタンスを取得（シングルトン）
     *
     * @param array $config 初期設定（初回のみ有効）
     * @return self
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * コア機能をサービスコンテナに登録
     *
     * @return void
     */
    private function registerCoreServices(): void
    {
        // フレームワーク自身を登録
        $this->singleton('framework', fn() => $this);
        
        // 設定マネージャーを登録
        $this->singleton('config', fn() => $this->config);
    }

    /**
     * フレームワークを起動
     *
     * すべてのエンジンを初期化・起動します。
     *
     * @return self
     */
    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        $this->emit('framework.booting');

        // 依存関係を解決してエンジンをソート
        $sortedEngines = $this->resolveDependencies();

        // 各エンジンを初期化
        foreach ($sortedEngines as $name) {
            $engine = $this->engines[$name];
            if (!$engine->isInitialized()) {
                $config = $this->config['engines'][$name] ?? [];
                $engine->initialize($config);
            }
        }

        // 各エンジンを起動
        foreach ($sortedEngines as $name) {
            $engine = $this->engines[$name];
            if (!$engine->isBooted()) {
                $engine->boot();
                $this->bootedEngines[] = $name;
            }
        }

        $this->booted = true;
        $this->emit('framework.booted');

        return $this;
    }

    /**
     * フレームワークをシャットダウン
     *
     * すべてのエンジンをシャットダウンします。
     *
     * @return void
     */
    public function shutdown(): void
    {
        $this->emit('framework.shutdown');

        // 起動の逆順でシャットダウン
        foreach (array_reverse($this->bootedEngines) as $name) {
            if (isset($this->engines[$name])) {
                $this->engines[$name]->shutdown();
            }
        }
    }

    /**
     * 設定値を取得
     *
     * @param string $key 設定キー（ドット記法対応）
     * @param mixed $default デフォルト値
     * @return mixed 設定値
     */
    public function config(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 設定値を設定
     *
     * @param string $key 設定キー（ドット記法対応）
     * @param mixed $value 設定値
     * @return void
     */
    public function setConfig(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    // ==================== Container機能（DIコンテナ） ====================

    /**
     * サービスを登録（通常）
     *
     * @param string $id サービスID
     * @param callable $factory ファクトリー関数
     * @return void
     */
    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        $this->singletons[$id] = false;
    }

    /**
     * サービスを登録（シングルトン）
     *
     * @param string $id サービスID
     * @param callable $factory ファクトリー関数
     * @return void
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        $this->singletons[$id] = true;
    }

    /**
     * インスタンスを直接登録
     *
     * @param string $id サービスID
     * @param mixed $instance インスタンス
     * @return void
     */
    public function instance(string $id, $instance): void
    {
        $this->services[$id] = $instance;
        $this->singletons[$id] = true;
    }

    /**
     * サービスを取得
     *
     * @param string $id サービスID
     * @return mixed サービスインスタンス
     * @throws RuntimeException サービスが見つからない場合
     */
    public function get(string $id)
    {
        // 既にインスタンス化されている場合
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        // ファクトリーが登録されている場合
        if (isset($this->factories[$id])) {
            $instance = $this->factories[$id]($this);

            // シングルトンの場合はキャッシュ
            if ($this->singletons[$id] ?? false) {
                $this->services[$id] = $instance;
            }

            return $instance;
        }

        throw new RuntimeException("Service not found: {$id}");
    }

    /**
     * サービスが登録されているかチェック
     *
     * @param string $id サービスID
     * @return bool 登録されている場合true
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]) || isset($this->factories[$id]);
    }

    // ==================== EngineManager機能 ====================

    /**
     * エンジンを登録
     *
     * @param EngineInterface $engine エンジンインスタンス
     * @return self
     */
    public function register(EngineInterface $engine): self
    {
        $name = $engine->getName();
        
        if (isset($this->engines[$name])) {
            throw new RuntimeException("Engine already registered: {$name}");
        }

        $this->engines[$name] = $engine;
        $this->instance($name, $engine);

        $this->emit('engine.registered', ['engine' => $name]);

        return $this;
    }

    /**
     * エンジンを取得
     *
     * @param string $name エンジン名
     * @return EngineInterface|null エンジンインスタンス
     */
    public function engine(string $name): ?EngineInterface
    {
        return $this->engines[$name] ?? null;
    }

    /**
     * すべてのエンジンを取得
     *
     * @return array<string, EngineInterface> エンジンの配列
     */
    public function engines(): array
    {
        return $this->engines;
    }

    /**
     * 依存関係を解決してエンジンをソート
     *
     * トポロジカルソート（Kahn's Algorithm）を使用
     *
     * @return array<string> ソートされたエンジン名の配列
     * @throws RuntimeException 循環依存が検出された場合
     */
    private function resolveDependencies(): array
    {
        // 優先度でソート
        $engines = $this->engines;
        uasort($engines, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

        // 依存グラフを構築
        $graph = [];
        $inDegree = [];

        foreach ($engines as $name => $engine) {
            $graph[$name] = $engine->getDependencies();
            $inDegree[$name] = 0;
        }

        // 各ノードの入次数を計算
        foreach ($graph as $deps) {
            foreach ($deps as $dep) {
                if (isset($inDegree[$dep])) {
                    $inDegree[$dep]++;
                }
            }
        }

        // トポロジカルソート
        $queue = [];
        $result = [];

        // 入次数0のノードをキューに追加
        foreach ($inDegree as $name => $degree) {
            if ($degree === 0) {
                $queue[] = $name;
            }
        }

        while (!empty($queue)) {
            $current = array_shift($queue);
            $result[] = $current;

            // 隣接ノードの入次数を減らす
            foreach ($graph[$current] as $dep) {
                if (isset($inDegree[$dep])) {
                    $inDegree[$dep]--;
                    if ($inDegree[$dep] === 0) {
                        $queue[] = $dep;
                    }
                }
            }
        }

        // 循環依存チェック
        if (count($result) !== count($engines)) {
            throw new RuntimeException('Circular dependency detected in engines');
        }

        return $result;
    }

    // ==================== EventDispatcher機能 ====================

    /**
     * イベントリスナーを登録
     *
     * @param string $event イベント名
     * @param callable $listener リスナー関数
     * @param int $priority 優先度（小さいほど先に実行）
     * @return void
     */
    public function on(string $event, callable $listener, int $priority = 100): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = [
            'listener' => $listener,
            'priority' => $priority,
        ];

        // 優先度でソート
        usort($this->listeners[$event], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * イベントを発火
     *
     * @param string $event イベント名
     * @param array $data イベントデータ
     * @return void
     */
    public function emit(string $event, array $data = []): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $item) {
            $listener = $item['listener'];
            $listener($data, $this);
        }
    }

    /**
     * イベントリスナーを削除
     *
     * @param string $event イベント名
     * @return void
     */
    public function off(string $event): void
    {
        unset($this->listeners[$event]);
    }

    // ==================== ユーティリティ ====================

    /**
     * フレームワークが起動済みかチェック
     *
     * @return bool 起動済みの場合true
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * 登録済みエンジン数を取得
     *
     * @return int エンジン数
     */
    public function engineCount(): int
    {
        return count($this->engines);
    }

    /**
     * デストラクタ（自動シャットダウン）
     */
    public function __destruct()
    {
        if ($this->booted) {
            $this->shutdown();
        }
    }
}
