<?php
/**
 * EngineTrait - エンジン共通処理
 *
 * JSON エラーレスポンスを統合。
 * 使用: use EngineTrait; （static メソッドとして提供）
 *
 * Ver.1.7-37: $throwOnError フラグ追加。true 時は exit の代わりに例外を投げる。
 */
trait EngineTrait {

	/**
	 * true のとき jsonError は exit せず例外を投げる。
	 * @since Ver.1.7-37
	 */
	protected static bool $throwOnError = false;

	/**
	 * エラー JSON レスポンスを送信して終了。
	 * $throwOnError が true の場合は RuntimeException を投げる。
	 */
	protected static function jsonError(string $msg, int $status = 400): never {
		if (static::$throwOnError) {
			throw new \RuntimeException($msg, $status);
		}
		http_response_code($status);
		echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
		exit;
	}
}
