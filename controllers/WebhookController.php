<?php
/**
 * WebhookController - Outgoing Webhook 管理
 *
 * WebhookEngine の handle() private ハンドラを Controller メソッドとして提供。
 *
 * @since Ver.1.7-36
 */
namespace AP\Controllers;

use APF\Core\{Request, Response};

class WebhookController extends BaseController {

	/** Webhook 追加 */
	public function add(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$url    = trim($request->post('url', ''));
		$label  = trim($request->post('label', ''));
		$events = json_decode($request->post('events', '[]'), true) ?: [];
		$secret = trim($request->post('secret', ''));

		if ($url === '') {
			return $this->error('URLは必須です');
		}
		if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
			return $this->error('有効な HTTP(S) URL を指定してください');
		}

		if (!\ACE\Api\WebhookService::addWebhook($url, $label, $events, $secret)) {
			return $this->error('Webhook追加に失敗しました', 500);
		}
		\ACE\Admin\AdminManager::logActivity('Webhook追加: ' . $label);
		return $this->ok();
	}

	/** Webhook 削除 */
	public function delete(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$index = (int)$request->post('index', -1);
		if (!\ACE\Api\WebhookService::deleteWebhook($index)) {
			return $this->error('Webhook削除に失敗しました');
		}
		\ACE\Admin\AdminManager::logActivity("Webhook削除: #{$index}");
		return $this->ok();
	}

	/** Webhook 有効/無効 切替 */
	public function toggle(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$index = (int)$request->post('index', -1);
		if (!\ACE\Api\WebhookService::toggleWebhook($index)) {
			return $this->error('Webhook切替に失敗しました');
		}
		return $this->ok();
	}

	/** テスト送信 */
	public function test(Request $request): Response {
		if ($err = $this->requireRole('admin')) return $err;

		$index = (int)$request->post('index', -1);
		$webhooks = \ACE\Api\WebhookService::listWebhooks();
		if ($index < 0 || $index >= count($webhooks)) {
			return $this->error('無効なインデックス');
		}
		\ACE\Api\WebhookService::dispatch('webhook.test', ['test' => true, 'timestamp' => date('c')]);
		return $this->ok();
	}
}
