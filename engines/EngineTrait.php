<?php
/**
 * EngineTrait - エンジン共通処理（後方互換シム）
 *
 * Ver.1.8: jsonError() は APF\Core\Response::jsonError() に移植。
 * $throwOnError パターンは引き続き維持（ApiEngine/UpdateEngine が使用）。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — APF\Core\Response::jsonError() を使用してください
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
