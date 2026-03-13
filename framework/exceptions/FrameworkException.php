<?php
/**
 * FrameworkException - AFE Base Exception
 *
 * すべてのAFE例外の基底クラス。
 * コンテキスト情報を保持し、デバッグを容易にします。
 *
 * @package Adlaire Framework Ecosystem
 * @version 2.1.0
 * @since   2026-03-13
 */

abstract class FrameworkException extends Exception
{
    /**
     * @var array エラーコンテキスト情報
     */
    protected array $context = [];

    /**
     * コンストラクタ
     *
     * @param string $message エラーメッセージ
     * @param array $context コンテキスト情報（デバッグ用）
     * @param int $code エラーコード
     * @param Throwable|null $previous 前の例外
     */
    public function __construct(
        string $message,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * コンテキスト情報を取得
     *
     * @return array コンテキスト配列
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 詳細なエラー情報を取得
     *
     * @return array エラー情報配列
     */
    public function getDetails(): array
    {
        return [
            'exception' => get_class($this),
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
            'trace' => $this->getTraceAsString(),
        ];
    }

    /**
     * エラー情報をJSON形式で取得
     *
     * @return string JSON文字列
     */
    public function toJson(): string
    {
        return json_encode($this->getDetails(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
