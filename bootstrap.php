<?php
/**
 * AdlairePlatform - アプリケーション ブートストラップ
 *
 * DI コンテナの初期化、Framework サービスの登録、
 * イベントシステムの起動を行う。
 *
 * autoload.php の読み込み後に require する。
 *
 * @since Ver.1.5.0
 * @license Adlaire License Ver.2.0
 */

use APF\Core\Container;
use APF\Core\HookManager;
use AIS\Core\EventDispatcher;

/**
 * Application - アプリケーションファサード
 *
 * DI コンテナ・フック・イベントへのグローバルアクセスを提供する。
 * engines/ から Framework サービスを利用する際の統一エントリポイント。
 */
class Application {

    private static ?Container $container = null;
    private static ?HookManager $hooks = null;
    private static ?EventDispatcher $events = null;
    private static bool $booted = false;

    /**
     * アプリケーションを初期化する
     *
     * index.php の冒頭で一度だけ呼び出す。
     */
    public static function boot(): void {
        if (self::$booted) {
            return;
        }

        self::$container = new Container();
        self::$hooks     = new HookManager();
        self::$events    = new EventDispatcher();

        /* コア サービスをコンテナに登録 */
        self::$container->instance(Container::class, self::$container);
        self::$container->instance(HookManager::class, self::$hooks);
        self::$container->instance(EventDispatcher::class, self::$events);

        self::$booted = true;
    }

    /**
     * DI コンテナを取得する
     */
    public static function container(): Container {
        if (!self::$booted) {
            self::boot();
        }
        return self::$container;
    }

    /**
     * フックマネージャを取得する
     */
    public static function hooks(): HookManager {
        if (!self::$booted) {
            self::boot();
        }
        return self::$hooks;
    }

    /**
     * イベントディスパッチャを取得する
     */
    public static function events(): EventDispatcher {
        if (!self::$booted) {
            self::boot();
        }
        return self::$events;
    }

    /**
     * コンテナからサービスを解決する（ショートカット）
     */
    public static function make(string $abstract, array $parameters = []): mixed {
        return self::container()->make($abstract, $parameters);
    }

    /**
     * アプリケーションが初期化済みか確認する
     */
    public static function isBooted(): bool {
        return self::$booted;
    }

    /**
     * テスト用: アプリケーション状態をリセットする
     */
    public static function reset(): void {
        self::$container = null;
        self::$hooks     = null;
        self::$events    = null;
        self::$booted    = false;
    }
}

/* ブートストラップ読み込み時に自動初期化 */
Application::boot();

/**
 * Ver.1.7: Router / Request を DI コンテナにシングルトン登録
 *
 * Router は routes.php でルート定義後、index.php で dispatch() する。
 * Request は全 Controller / Middleware で共有する。
 */
$container = Application::container();

$container->singleton(\APF\Core\Router::class, fn() => new \APF\Core\Router($container));
$container->singleton(\APF\Core\Request::class, fn() => new \APF\Core\Request());

/**
 * Ver.1.6: コアサービスを DI コンテナに遅延登録
 *
 * エンジンが必要とする Framework サービスを Application::make() で取得可能にする。
 * 遅延ロードにより、使用されないサービスのインスタンス化コストを回避。
 */

/* セッション管理 */
$container->lazy(\APF\Utilities\Session::class, fn() => new \APF\Utilities\Session());

/* HealthMonitor — ダッシュボードの診断ウィジェットで使用 */
$container->lazy(\AIS\System\HealthMonitor::class, fn() => new \AIS\System\HealthMonitor());

/* RateLimiter — API レート制限で使用 */
$container->lazy(\ACE\Api\RateLimiter::class, fn() => new \ACE\Api\RateLimiter(
    function_exists('settings_dir') ? settings_dir() : 'data/settings'
));

/**
 * Ver.1.6: コアイベントリスナー登録
 *
 * エンジンの WebhookEngine::dispatch() をイベントシステムに接続。
 * コンテンツ変更時にイベントを発火 → Webhook 配信を自動トリガー。
 */
$events = Application::events();

/* コンテンツ変更イベント → Webhook 自動配信 */
$events->listen('content.changed', function (array $data) {
    if (class_exists('WebhookEngine')) {
        $event = $data['event'] ?? 'page.updated';
        WebhookEngine::dispatch($event, $data['payload'] ?? []);
    }
});

/* ログインイベント → 診断ログ */
$events->listen('auth.login', function (array $data) {
    if (class_exists('DiagnosticEngine')) {
        DiagnosticEngine::log('security', 'イベント: ログイン', $data);
    }
});

/* キャッシュ無効化イベント */
$events->listen('cache.invalidate', function (array $data) {
    if (class_exists('CacheEngine')) {
        CacheEngine::invalidateContent();
    }
});
