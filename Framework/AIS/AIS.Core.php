<?php
/**
 * Adlaire Infrastructure Services (AIS) - Core Module
 *
 * AIS = Adlaire Infrastructure Services
 *
 * アプリケーション基盤サービスを提供するコアモジュール。
 * AppContext（状態管理）、ServiceProvider（サービス登録）、
 * ServiceContainer（DIコンテナ）、EventDispatcher（イベントシステム）を含む。
 *
 * @package AIS
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace AIS\Core;

// ============================================================================
// AppContext - アプリケーション状態管理
// ============================================================================

/**
 * アプリケーションの設定・状態を一元管理するコンテキストクラス。
 *
 * 設定値のドット記法アクセス、ファイルからの読み込み・保存に対応。
 * Adlaire Platform の AppContext を汎用化・再利用可能にしたもの。
 */
class AppContext {

    /** @var array<string, mixed> 設定データストア */
    private array $data = [];

    /**
     * コンストラクタ
     *
     * @param array<string, mixed> $config 初期設定値
     */
    public function __construct(array $config = []) {
        $this->data = $config;
    }

    /**
     * 設定値を取得する
     *
     * ドット記法（例: 'database.host'）によるネスト値のアクセスをサポート。
     *
     * @param string $key     設定キー（ドット記法対応）
     * @param mixed  $default キーが存在しない場合のデフォルト値
     * @return mixed 設定値またはデフォルト値
     */
    public function get(string $key, mixed $default = null): mixed {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        $segments = explode('.', $key);
        $current = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * 設定値を設定する
     *
     * ドット記法によるネスト値の設定をサポート。
     *
     * @param string $key   設定キー（ドット記法対応）
     * @param mixed  $value 設定する値
     */
    public function set(string $key, mixed $value): void {
        $segments = explode('.', $key);

        if (count($segments) === 1) {
            $this->data[$key] = $value;
            return;
        }

        $current = &$this->data;
        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * 指定キーが存在するか確認する
     *
     * @param string $key 設定キー（ドット記法対応）
     * @return bool 存在する場合 true
     */
    public function has(string $key): bool {
        if (array_key_exists($key, $this->data)) {
            return true;
        }

        $segments = explode('.', $key);
        $current = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }
            $current = $current[$segment];
        }

        return true;
    }

    /**
     * 全設定値を配列として取得する
     *
     * @return array<string, mixed> 全設定データ
     */
    public function all(): array {
        return $this->data;
    }

    /**
     * JSONファイルから設定を読み込む
     *
     * 既存の設定値はマージされる（ファイル側が優先）。
     *
     * @param string $path JSONファイルのパス
     * @throws \RuntimeException ファイルが読み込めない場合
     * @throws \JsonException JSONのパースに失敗した場合
     */
    public function loadFromFile(string $path): void {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("設定ファイルが読み込めません: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("設定ファイルの読み込みに失敗しました: {$path}");
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException("設定ファイルのフォーマットが不正です: {$path}");
        }

        $this->data = array_replace_recursive($this->data, $decoded);
    }

    /**
     * 現在の設定値をJSONファイルに保存する
     *
     * @param string $path 保存先ファイルのパス
     * @throws \RuntimeException 書き込みに失敗した場合
     */
    public function saveToFile(string $path): void {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException("ディレクトリの作成に失敗しました: {$dir}");
        }

        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException("設定ファイルの書き込みに失敗しました: {$path}");
        }
    }
}

// ============================================================================
// ServiceProvider - サービスプロバイダー基底クラス
// ============================================================================

/**
 * サービスの登録・初期化を行うプロバイダーの基底クラス。
 *
 * 継承して register() / boot() をオーバーライドすることで、
 * アプリケーションに必要なサービスを ServiceContainer に登録する。
 */
abstract class ServiceProvider {

    /** @var AppContext アプリケーションコンテキスト */
    protected AppContext $app;

    /**
     * コンストラクタ
     *
     * @param AppContext $app アプリケーションコンテキスト
     */
    public function __construct(AppContext $app) {
        $this->app = $app;
    }

    /**
     * サービスをコンテナに登録する
     *
     * コンテナへのバインディング定義を行う。
     * この段階では他のサービスへの依存解決は行わないこと。
     */
    public function register(): void {
        // サブクラスでオーバーライド
    }

    /**
     * サービスの初期化処理を行う
     *
     * 全プロバイダーの register() 完了後に呼ばれる。
     * 他のサービスへの依存解決が可能。
     */
    public function boot(): void {
        // サブクラスでオーバーライド
    }

    /**
     * このプロバイダーが提供するサービス名の一覧を返す
     *
     * 遅延ロード時にコンテナが判断材料として使用する。
     *
     * @return array<string> サービス名の配列
     */
    public function provides(): array {
        return [];
    }
}

// ============================================================================
// ServiceContainer - 軽量DIコンテナ
// ============================================================================

/**
 * 軽量な依存性注入（DI）コンテナ。
 *
 * APF の Container をシンプルにした設計。
 * サービスのバインド・シングルトン登録・解決、
 * ServiceProvider の統合管理機能を持つ。
 */
class ServiceContainer {

    /** @var array<string, callable> ファクトリーバインディング */
    private array $bindings = [];

    /** @var array<string, callable> シングルトンファクトリー */
    private array $singletons = [];

    /** @var array<string, mixed> 解決済みシングルトンインスタンス */
    private array $instances = [];

    /** @var array<string, bool> 現在解決中のサービス（循環依存検出用） */
    private array $resolving = [];

    /** @var ServiceProvider[] 登録済みサービスプロバイダー */
    private array $providers = [];

    /** @var bool boot済みフラグ */
    private bool $booted = false;

    /**
     * サービスファクトリーをバインドする
     *
     * 呼び出しごとに新しいインスタンスを生成する。
     *
     * @param string   $name    サービス名
     * @param callable $factory ファクトリー関数 (ServiceContainer $c): mixed
     */
    public function bind(string $name, callable $factory): void {
        $this->bindings[$name] = $factory;
        unset($this->singletons[$name], $this->instances[$name]);
    }

    /**
     * シングルトンとしてサービスファクトリーを登録する
     *
     * 初回のみファクトリーを実行し、以降は同一インスタンスを返す。
     *
     * @param string   $name    サービス名
     * @param callable $factory ファクトリー関数 (ServiceContainer $c): mixed
     */
    public function singleton(string $name, callable $factory): void {
        $this->singletons[$name] = $factory;
        unset($this->bindings[$name], $this->instances[$name]);
    }

    /**
     * サービスを解決（生成・取得）する
     *
     * シングルトン → バインディング の順で検索し、インスタンスを返す。
     * 循環依存を検出した場合は例外をスローする。
     *
     * @param string $name サービス名
     * @return mixed サービスインスタンス
     * @throws \RuntimeException サービスが未登録、または循環依存の場合
     */
    public function make(string $name): mixed {
        /* 解決済みシングルトンを返す */
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        /* 循環依存検出 */
        if (isset($this->resolving[$name])) {
            $chain = implode(' -> ', array_keys($this->resolving));
            throw new \RuntimeException(
                "循環依存を検出しました: {$chain} -> {$name}"
            );
        }

        $this->resolving[$name] = true;

        try {
            /* シングルトンファクトリー */
            if (isset($this->singletons[$name])) {
                $instance = ($this->singletons[$name])($this);
                $this->instances[$name] = $instance;
                unset($this->singletons[$name]);
                return $instance;
            }

            /* 通常バインディング */
            if (isset($this->bindings[$name])) {
                return ($this->bindings[$name])($this);
            }

            throw new \RuntimeException("サービスが登録されていません: {$name}");
        } finally {
            unset($this->resolving[$name]);
        }
    }

    /**
     * 指定サービスが登録されているか確認する
     *
     * @param string $name サービス名
     * @return bool 登録済みの場合 true
     */
    public function has(string $name): bool {
        return isset($this->bindings[$name])
            || isset($this->singletons[$name])
            || isset($this->instances[$name]);
    }

    /**
     * サービスプロバイダーを登録する
     *
     * プロバイダーの register() を即時実行し、
     * boot() は boot() メソッド呼び出し時にまとめて実行する。
     *
     * @param ServiceProvider $provider サービスプロバイダー
     */
    public function register(ServiceProvider $provider): void {
        $this->providers[] = $provider;
        $provider->register();

        /* 既に boot 済みなら即座に boot する */
        if ($this->booted) {
            $provider->boot();
        }
    }

    /**
     * 登録済みの全プロバイダーを初期化する
     *
     * 各プロバイダーの boot() を呼び出す。
     * 二重実行は防止される。
     */
    public function boot(): void {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }
}

// ============================================================================
// EventDispatcher - アプリケーションイベントシステム
// ============================================================================

/**
 * 優先度付きイベントディスパッチャー。
 *
 * イベント名に対して複数のリスナーを優先度付きで登録し、
 * dispatch() で全リスナーを優先度順に実行する。
 * APF の HookManager を汎用イベントシステムとして再設計。
 */
class EventDispatcher {

    /**
     * @var array<string, array<array{listener: callable, priority: int}>>
     * イベントごとのリスナー定義
     */
    private array $listeners = [];

    /** @var array<string, bool> ソート済みフラグ */
    private array $sorted = [];

    /**
     * イベントリスナーを登録する
     *
     * 同一イベントに複数リスナーを登録可能。
     * priority が小さいほど先に実行される。
     *
     * @param string   $event    イベント名
     * @param callable $listener リスナー関数 (array $data): mixed
     * @param int      $priority 優先度（小さいほど先に実行、デフォルト 0）
     */
    public function listen(string $event, callable $listener, int $priority = 0): void {
        $this->listeners[$event][] = [
            'listener' => $listener,
            'priority' => $priority,
        ];
        unset($this->sorted[$event]);
    }

    /**
     * イベントをディスパッチし、全リスナーを優先度順に実行する
     *
     * 各リスナーの戻り値を配列として収集して返す。
     * リスナー内で例外が発生した場合は、そのリスナーをスキップし
     * 残りのリスナーの実行を継続する。
     *
     * @param string              $event イベント名
     * @param array<string,mixed> $data  イベントデータ
     * @return array<mixed> 各リスナーの戻り値の配列
     */
    public function dispatch(string $event, array $data = []): array {
        if (!isset($this->listeners[$event])) {
            return [];
        }

        $this->sortListeners($event);

        $results = [];
        foreach ($this->listeners[$event] as $entry) {
            try {
                $results[] = ($entry['listener'])($data);
            } catch (\Throwable) {
                /* リスナーの例外は握りつぶし、残りのリスナー実行を継続 */
            }
        }

        return $results;
    }

    /**
     * 指定イベントにリスナーが登録されているか確認する
     *
     * @param string $event イベント名
     * @return bool リスナーが存在する場合 true
     */
    public function hasListeners(string $event): bool {
        return !empty($this->listeners[$event]);
    }

    /**
     * 指定イベントから特定のリスナーを削除する
     *
     * callable の同一性で照合し、一致するリスナーを全て除去する。
     *
     * @param string   $event    イベント名
     * @param callable $listener 削除対象のリスナー
     */
    public function removeListener(string $event, callable $listener): void {
        if (!isset($this->listeners[$event])) {
            return;
        }

        $this->listeners[$event] = array_values(
            array_filter(
                $this->listeners[$event],
                fn(array $entry): bool => $entry['listener'] !== $listener
            )
        );

        if (empty($this->listeners[$event])) {
            unset($this->listeners[$event], $this->sorted[$event]);
        } else {
            unset($this->sorted[$event]);
        }
    }

    /**
     * リスナーを優先度順にソートする（キャッシュ付き）
     *
     * @param string $event イベント名
     */
    private function sortListeners(string $event): void {
        if (isset($this->sorted[$event])) {
            return;
        }

        usort(
            $this->listeners[$event],
            fn(array $a, array $b): int => $a['priority'] <=> $b['priority']
        );

        $this->sorted[$event] = true;
    }
}
