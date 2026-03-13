<?php
/**
 * TestHelper - AFE Testing Utilities
 *
 * AFEフレームワークのテストを容易にするヘルパークラス。
 * モックエンジンの生成、イベントアサーション、テスト用DIコンテナなどを提供します。
 *
 * @package Adlaire Framework Ecosystem
 * @version 2.1.0
 * @since   2026-03-13
 */

require_once __DIR__ . '/../EngineInterface.php';
require_once __DIR__ . '/../Framework.php';

class TestHelper
{
    /**
     * モックエンジンを作成
     *
     * @param string $name エンジン名
     * @param array $dependencies 依存エンジンリスト
     * @param int $priority 優先度
     * @return EngineInterface モックエンジン
     */
    public static function createMockEngine(
        string $name,
        array $dependencies = [],
        int $priority = 100
    ): EngineInterface {
        return new class($name, $dependencies, $priority) implements EngineInterface {
            private string $name;
            private array $dependencies;
            private int $priority;
            public bool $initialized = false;
            public bool $booted = false;
            public bool $shutdownCalled = false;
            public array $initConfig = [];
            
            public function __construct(string $name, array $deps, int $priority)
            {
                $this->name = $name;
                $this->dependencies = $deps;
                $this->priority = $priority;
            }
            
            public function getName(): string
            {
                return $this->name;
            }
            
            public function getPriority(): int
            {
                return $this->priority;
            }
            
            public function getDependencies(): array
            {
                return $this->dependencies;
            }
            
            public function initialize(array $config = []): void
            {
                $this->initialized = true;
                $this->initConfig = $config;
            }
            
            public function boot(): void
            {
                $this->booted = true;
            }
            
            public function shutdown(): void
            {
                $this->shutdownCalled = true;
            }
        };
    }

    /**
     * テスト用フレームワークインスタンスを作成
     *
     * @param array $config 設定配列
     * @return Framework テスト用フレームワーク
     */
    public static function createTestFramework(array $config = []): Framework
    {
        $defaultConfig = [
            'debug' => true,
            'test_mode' => true,
            'engines' => [],
        ];
        
        return Framework::getInstance(array_merge($defaultConfig, $config));
    }

    /**
     * イベントトラッカーを作成
     *
     * @param Framework $framework フレームワークインスタンス
     * @param string $event イベント名
     * @return callable イベントハンドラー
     */
    public static function createEventTracker(Framework $framework, string $event): callable
    {
        $tracker = new class {
            public int $callCount = 0;
            public array $lastData = [];
            public array $history = [];
            
            public function handle(array $data): void
            {
                $this->callCount++;
                $this->lastData = $data;
                $this->history[] = $data;
            }
        };
        
        $framework->on($event, [$tracker, 'handle']);
        
        return $tracker;
    }

    /**
     * イベントが発火されたかアサート
     *
     * @param object $tracker イベントトラッカー
     * @param int $expectedCount 期待される呼び出し回数
     * @param string|null $message エラーメッセージ
     * @return void
     * @throws AssertionError アサーション失敗時
     */
    public static function assertEventEmitted(
        object $tracker,
        int $expectedCount = 1,
        ?string $message = null
    ): void {
        $actualCount = $tracker->callCount ?? 0;
        
        if ($actualCount !== $expectedCount) {
            $msg = $message ?? "Expected event to be emitted {$expectedCount} time(s), but was emitted {$actualCount} time(s)";
            throw new AssertionError($msg);
        }
    }

    /**
     * イベントが発火されなかったかアサート
     *
     * @param object $tracker イベントトラッカー
     * @param string|null $message エラーメッセージ
     * @return void
     * @throws AssertionError アサーション失敗時
     */
    public static function assertEventNotEmitted(
        object $tracker,
        ?string $message = null
    ): void {
        self::assertEventEmitted($tracker, 0, $message);
    }

    /**
     * エンジンが初期化されたかアサート
     *
     * @param EngineInterface $engine エンジン
     * @param string|null $message エラーメッセージ
     * @return void
     * @throws AssertionError アサーション失敗時
     */
    public static function assertEngineInitialized(
        EngineInterface $engine,
        ?string $message = null
    ): void {
        if (!$engine->initialized ?? false) {
            $msg = $message ?? "Expected engine '{$engine->getName()}' to be initialized";
            throw new AssertionError($msg);
        }
    }

    /**
     * エンジンが起動されたかアサート
     *
     * @param EngineInterface $engine エンジン
     * @param string|null $message エラーメッセージ
     * @return void
     * @throws AssertionError アサーション失敗時
     */
    public static function assertEngineBooted(
        EngineInterface $engine,
        ?string $message = null
    ): void {
        if (!$engine->booted ?? false) {
            $msg = $message ?? "Expected engine '{$engine->getName()}' to be booted";
            throw new AssertionError($msg);
        }
    }

    /**
     * サービスが登録されているかアサート
     *
     * @param Framework $framework フレームワークインスタンス
     * @param string $serviceId サービスID
     * @param string|null $message エラーメッセージ
     * @return void
     * @throws AssertionError アサーション失敗時
     */
    public static function assertServiceRegistered(
        Framework $framework,
        string $serviceId,
        ?string $message = null
    ): void {
        if (!$framework->has($serviceId)) {
            $msg = $message ?? "Expected service '{$serviceId}' to be registered";
            throw new AssertionError($msg);
        }
    }

    /**
     * モックサービスを登録
     *
     * @param Framework $framework フレームワークインスタンス
     * @param string $serviceId サービスID
     * @param mixed $mockObject モックオブジェクト
     * @return void
     */
    public static function registerMockService(
        Framework $framework,
        string $serviceId,
        $mockObject
    ): void {
        $framework->instance($serviceId, $mockObject);
    }
}
