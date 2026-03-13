<?php
/**
 * AFE Exception Classes
 *
 * AFEフレームワークで使用する例外クラス群。
 * 各コンポーネント固有の例外を定義します。
 *
 * @package Adlaire Framework Ecosystem
 * @version 2.1.0
 * @since   2026-03-13
 */

require_once __DIR__ . '/FrameworkException.php';

/**
 * EngineException - エンジン関連の例外
 */
class EngineException extends FrameworkException
{
}

/**
 * EngineNotFoundException - エンジンが見つからない
 */
class EngineNotFoundException extends EngineException
{
    public function __construct(string $engineName, ?Throwable $previous = null)
    {
        parent::__construct(
            "Engine not found: {$engineName}",
            ['engine_name' => $engineName],
            404,
            $previous
        );
    }
}

/**
 * CircularDependencyException - 循環依存エラー
 */
class CircularDependencyException extends EngineException
{
    public function __construct(array $cycle, ?Throwable $previous = null)
    {
        $cycleString = implode(' → ', $cycle);
        parent::__construct(
            "Circular dependency detected: {$cycleString}",
            ['dependency_cycle' => $cycle],
            500,
            $previous
        );
    }
}

/**
 * ContainerException - DIコンテナ関連の例外
 */
class ContainerException extends FrameworkException
{
}

/**
 * ServiceNotFoundException - サービスが見つからない
 */
class ServiceNotFoundException extends ContainerException
{
    public function __construct(string $serviceId, ?Throwable $previous = null)
    {
        parent::__construct(
            "Service not found: {$serviceId}",
            ['service_id' => $serviceId],
            404,
            $previous
        );
    }
}

/**
 * InvalidConfigException - 設定エラー
 */
class InvalidConfigException extends ContainerException
{
    public function __construct(
        string $key,
        string $expectedType,
        string $actualType,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            "Invalid config type for '{$key}': expected {$expectedType}, got {$actualType}",
            [
                'config_key' => $key,
                'expected_type' => $expectedType,
                'actual_type' => $actualType,
            ],
            400,
            $previous
        );
    }
}

/**
 * EventException - イベント関連の例外
 */
class EventException extends FrameworkException
{
}

/**
 * InvalidEngineException - 無効なエンジン
 */
class InvalidEngineException extends EngineException
{
    public function __construct(
        string $reason,
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            "Invalid engine: {$reason}",
            $context,
            400,
            $previous
        );
    }
}
