<?php
/**
 * ApiEngine - ヘッドレスCMS / 公開・管理 REST API エンジン（後方互換シム）
 *
 * Ver.1.8: 全ロジックを ACE\Api\ApiService に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — ACE\Api\ApiService を使用してください
 */
class ApiEngine {

	/** @var \ACE\Api\ApiRouter|null Ver.1.5 Framework API ルーター */
	private static ?\ACE\Api\ApiRouter $router = null;

	/**
	 * Ver.1.5: Framework ApiRouter インスタンスを取得する
	 */
	public static function getRouter(): \ACE\Api\ApiRouter {
		if (self::$router === null) {
			self::$router = new \ACE\Api\ApiRouter(AdminEngine::getAuthManager());
		}
		return self::$router;
	}

	/* ── エントリーポイント ── */

	public static function handle(): void {
		\ACE\Api\ApiService::handle();
	}

	/* ── API キー管理 ── */

	public static function generateApiKey(string $label = ''): string {
		return \ACE\Api\ApiService::generateApiKey($label);
	}

	public static function listApiKeys(): array {
		return \ACE\Api\ApiService::listApiKeys();
	}

	public static function deleteApiKey(int $index): bool {
		return \ACE\Api\ApiService::deleteApiKey($index);
	}
}
