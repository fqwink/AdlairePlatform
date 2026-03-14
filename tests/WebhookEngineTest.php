<?php
/**
 * WebhookEngineTest - Webhook 管理・SSRF防止のテスト
 */
class WebhookEngineTest extends TestCase {

	protected function setUp(): void {
		$this->clearJsonCache();
		/* テスト用にクリーンなWebhook設定で開始 */
		$configPath = settings_dir() . '/webhooks.json';
		if (file_exists($configPath)) @unlink($configPath);
	}

	/* ═══ Webhook 追加 ═══ */

	public function testAddWebhookSuccess(): void {
		$result = WebhookEngine::addWebhook('https://example.com/hook', 'Test Hook');
		$this->assertTrue($result);
	}

	public function testAddWebhookInvalidUrl(): void {
		$this->assertFalse(WebhookEngine::addWebhook('not-a-url'));
	}

	public function testAddWebhookFtpSchemeRejected(): void {
		$this->assertFalse(WebhookEngine::addWebhook('ftp://example.com/file'));
	}

	public function testAddWebhookWithEvents(): void {
		WebhookEngine::addWebhook(
			'https://example.com/hook2',
			'Event Hook',
			['item.created', 'item.updated']
		);
		$this->clearJsonCache();
		$list = WebhookEngine::listWebhooks();
		$this->assertNotEmpty($list);
		$last = end($list);
		$this->assertCount(2, $last['events']);
		$this->assertContains('item.created', $last['events']);
	}

	public function testAddWebhookDefaultEvents(): void {
		WebhookEngine::addWebhook('https://example.com/default');
		$this->clearJsonCache();
		$list = WebhookEngine::listWebhooks();
		$last = end($list);
		$this->assertCount(5, $last['events']);
	}

	/* ═══ SSRF 防止 ═══ */

	public function testAddWebhookBlocksLocalhost(): void {
		$this->assertFalse(WebhookEngine::addWebhook('https://localhost/hook'));
	}

	public function testAddWebhookBlocksLoopback(): void {
		$this->assertFalse(WebhookEngine::addWebhook('https://127.0.0.1/hook'));
	}

	public function testAddWebhookBlocksIPv6Loopback(): void {
		$this->assertFalse(WebhookEngine::addWebhook('https://[::1]/hook'));
	}

	public function testAddWebhookBlocksZeroAddress(): void {
		$this->assertFalse(WebhookEngine::addWebhook('https://0.0.0.0/hook'));
	}

	/* ═══ Webhook 一覧 ═══ */

	public function testListWebhooksEmpty(): void {
		$list = WebhookEngine::listWebhooks();
		$this->assertEquals([], $list);
	}

	public function testListWebhooksHidesSecret(): void {
		WebhookEngine::addWebhook('https://example.com/secret', 'Secret', [], 'my_secret');
		$this->clearJsonCache();
		$list = WebhookEngine::listWebhooks();
		$this->assertNotEmpty($list);
		$wh = $list[0];
		$this->assertTrue($wh['has_secret']);
		/* シークレット値自体は含まれない */
		$this->assertFalse(isset($wh['secret']), 'Secret value should not be exposed');
	}

	/* ═══ Webhook 削除 ═══ */

	public function testDeleteWebhook(): void {
		WebhookEngine::addWebhook('https://example.com/del', 'Delete Me');
		$this->clearJsonCache();
		$this->assertTrue(WebhookEngine::deleteWebhook(0));
		$this->clearJsonCache();
		$this->assertEquals([], WebhookEngine::listWebhooks());
	}

	public function testDeleteWebhookInvalidIndex(): void {
		$this->assertFalse(WebhookEngine::deleteWebhook(999));
	}

	/* ═══ Webhook トグル ═══ */

	public function testToggleWebhook(): void {
		WebhookEngine::addWebhook('https://example.com/toggle', 'Toggle');
		$this->clearJsonCache();
		/* 最初は active: true */
		$list = WebhookEngine::listWebhooks();
		$this->assertTrue($list[0]['active']);

		/* トグルで false に */
		$this->clearJsonCache();
		$this->assertTrue(WebhookEngine::toggleWebhook(0));
		$this->clearJsonCache();
		$list = WebhookEngine::listWebhooks();
		$this->assertFalse($list[0]['active']);

		/* もう一度トグルで true に */
		$this->clearJsonCache();
		WebhookEngine::toggleWebhook(0);
		$this->clearJsonCache();
		$list = WebhookEngine::listWebhooks();
		$this->assertTrue($list[0]['active']);
	}

	public function testToggleWebhookInvalidIndex(): void {
		$this->assertFalse(WebhookEngine::toggleWebhook(999));
	}
}
