<?php
/**
 * BaseEngine - Adlaire Framework Ecosystem (AFE) エンジンの基底抽象クラス
 *
 * すべてのエンジンはこのクラスを継承することで、
 * 共通機能とヘルパーメソッドを利用できます。
 *
 * @package Adlaire Framework Ecosystem
 * @version 2.1.0
 * @since   2026-03-13
 */

require_once __DIR__ . '/EngineInterface.php';

abstract class BaseEngine implements EngineInterface
{
    /**
     * @var Framework フレームワークインスタンス
     */
    protected Framework $framework;

    /**
     * @var array エンジンの設定
     */
    protected array $config = [];

    /**
     * @var bool 初期化済みフラグ
     */
    private bool $initialized = false;

    /**
     * @var bool 起動済みフラグ
     */
    private bool $booted = false;

    /**
     * @var int エンジンの優先度
     */
    protected int $priority = 100;

    /**
     * @var array<string> 依存エンジンのリスト
     */
    protected array $dependencies = [];

    /**
     * コンストラクタ
     *
     * @param Framework $framework フレームワークインスタンス
     */
    public function __construct(Framework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(array $config = []): void
    {
        if ($this->initialized) {
            return;
        }

        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->onInitialize();
        $this->initialized = true;

        $this->emit('engine.initialized', ['engine' => $this->getName()]);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->onBoot();
        $this->booted = true;

        $this->emit('engine.booted', ['engine' => $this->getName()]);
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown(): void
    {
        $this->onShutdown();
        $this->emit('engine.shutdown', ['engine' => $this->getName()]);
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * 初期化時に呼ばれるフック（オーバーライド可能）
     *
     * @return void
     */
    protected function onInitialize(): void
    {
        // サブクラスでオーバーライド
    }

    /**
     * 起動時に呼ばれるフック（オーバーライド可能）
     *
     * @return void
     */
    protected function onBoot(): void
    {
        // サブクラスでオーバーライド
    }

    /**
     * シャットダウン時に呼ばれるフック（オーバーライド可能）
     *
     * @return void
     */
    protected function onShutdown(): void
    {
        // サブクラスでオーバーライド
    }

    /**
     * デフォルト設定を取得（オーバーライド可能）
     *
     * @return array デフォルト設定配列
     */
    protected function getDefaultConfig(): array
    {
        return [];
    }

    // ==================== ヘルパーメソッド ====================

    /**
     * 設定値を取得
     *
     * @param string $key 設定キー（ドット記法対応）
     * @param mixed $default デフォルト値
     * @return mixed 設定値
     */
    protected function config(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * イベントを発火
     *
     * @param string $event イベント名
     * @param array $data イベントデータ
     * @return void
     */
    protected function emit(string $event, array $data = []): void
    {
        $this->framework->emit($event, $data);
    }

    /**
     * イベントリスナーを登録
     *
     * @param string $event イベント名
     * @param callable $listener リスナー関数
     * @param int $priority 優先度（小さいほど先に実行）
     * @return void
     */
    protected function on(string $event, callable $listener, int $priority = 100): void
    {
        $this->framework->on($event, $listener, $priority);
    }

    /**
     * コンテナからサービスを取得
     *
     * @param string $id サービスID
     * @return mixed サービスインスタンス
     */
    protected function get(string $id)
    {
        return $this->framework->get($id);
    }

    /**
     * エンジンを取得
     *
     * @param string $name エンジン名
     * @return EngineInterface|null エンジンインスタンス
     */
    protected function engine(string $name): ?EngineInterface
    {
        return $this->framework->engine($name);
    }

    /**
     * ログを記録
     *
     * @param string $message メッセージ
     * @param string $level ログレベル（debug, info, warning, error）
     * @param array $context コンテキストデータ
     * @return void
     */
    protected function log(string $message, string $level = 'info', array $context = []): void
    {
        $logger = $this->engine('logger');
        if ($logger && method_exists($logger, 'log')) {
            $logger->log($level, $message, $context);
        }
    }

    /**
     * キャッシュから値を取得
     *
     * @param string $key キャッシュキー
     * @param mixed $default デフォルト値
     * @return mixed キャッシュ値
     */
    protected function cache(string $key, $default = null)
    {
        $cache = $this->engine('cache');
        if ($cache && method_exists($cache, 'get')) {
            return $cache->get($key, $default);
        }
        return $default;
    }

    /**
     * 初期化済みかチェック
     *
     * @return bool 初期化済みの場合true
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * 起動済みかチェック
     *
     * @return bool 起動済みの場合true
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }
}
