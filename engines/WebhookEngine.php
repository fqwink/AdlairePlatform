<?php
/**
 * WebhookEngine - Outgoing Webhook 配信エンジン（後方互換シム）
 *
 * Ver.1.8: 全ロジックを ACE\Api\WebhookService に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — ACE\Api\WebhookService を使用してください
 */
class WebhookEngine {
	use EngineTrait;

	/** @var \ACE\Api\WebhookManager|null Ver.1.5 Framework Webhook マネージャ */
	private static ?\ACE\Api\WebhookManager $webhookManager = null;

	/**
	 * Ver.1.5: Framework WebhookManager インスタンスを取得する
	 */
	public static function getWebhookManager(): \ACE\Api\WebhookManager {
		if (self::$webhookManager === null) {
			self::$webhookManager = new \ACE\Api\WebhookManager(settings_dir());
		}
		return self::$webhookManager;
	}

	public static function loadConfig(): array {
		return \ACE\Api\WebhookService::loadConfig();
	}

	public static function saveConfig(array $config): void {
		\ACE\Api\WebhookService::saveConfig($config);
	}

	public static function addWebhook(string $url, string $label = '', array $events = [], string $secret = ''): bool {
		return \ACE\Api\WebhookService::addWebhook($url, $label, $events, $secret);
	}

	public static function deleteWebhook(int $index): bool {
		return \ACE\Api\WebhookService::deleteWebhook($index);
	}

	public static function toggleWebhook(int $index): bool {
		return \ACE\Api\WebhookService::toggleWebhook($index);
	}

	public static function listWebhooks(): array {
		return \ACE\Api\WebhookService::listWebhooks();
	}

	public static function dispatch(string $event, array $payload = []): void {
		\ACE\Api\WebhookService::dispatch($event, $payload);
	}

	public static function verifySignature(string $payload, string $signature, string $secret): bool {
		return \ACE\Api\WebhookService::verifySignature($payload, $signature, $secret);
	}
}
