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
