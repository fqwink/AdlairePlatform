<?php
/**
 * EngineTrait - エンジン共通処理
 *
 * 7+ エンジンで重複している認証・JSON レスポンス処理を統合。
 * 使用: use EngineTrait; （static メソッドとして提供）
 *
 * Ver.1.7-37: $throwOnError フラグ追加。true 時は exit の代わりに例外を投げる。
 * Controller のラッパーメソッドから使用し、エンジンの既存 handle() との後方互換を維持。
 */
trait EngineTrait {

	/**
	 * true のとき jsonError/jsonOk は exit せず例外を投げる。
	 * @since Ver.1.7-37
	 */
	protected static bool $throwOnError = false;

	/**
	 * ログイン必須チェック + CSRF 検証 + JSON Content-Type 設定。
	 * 管理系 POST ハンドラの冒頭で呼び出す。
	 */
	protected static function requireLogin(): void {
		if (!AdminEngine::isLoggedIn()) {
			http_response_code(401);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['error' => '未ログイン'], JSON_UNESCAPED_UNICODE);
			exit;
		}
		AdminEngine::verifyCsrf();
		header('Content-Type: application/json; charset=UTF-8');
	}

	/**
	 * 成功 JSON レスポンスを送信して終了。
	 */
	protected static function jsonOk(mixed $data): never {
		echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
		exit;
	}

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
