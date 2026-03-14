<?php
/**
 * SecurityTest - Ver.1.6 セキュリティ強化のテスト
 *
 * セッションタイムアウト、Argon2id、Webhook 署名検証、
 * API キープレフィックス拡張のテストを含む。
 */
class SecurityTest extends TestCase {

	protected function setUp(): void {
		$this->resetSession();
		$this->resetPost();
		$this->clearJsonCache();
	}

	/* ═══ Argon2id パスワードハッシュ ═══ */

	public function testSavePasswordUsesArgon2idWhenAvailable(): void {
		$hash = AdminEngine::savePassword('test_password_16');
		$this->assertTrue(password_verify('test_password_16', $hash));
		/* Argon2id 対応環境では $argon2id$ プレフィックス、非対応では $2y$ */
		$validPrefixes = ['$argon2id$', '$2y$'];
		$prefixMatch = false;
		foreach ($validPrefixes as $prefix) {
			if (str_starts_with($hash, $prefix)) {
				$prefixMatch = true;
				break;
			}
		}
		$this->assertTrue($prefixMatch, 'ハッシュは Argon2id または bcrypt 形式であること');
	}

	public function testPasswordNeedsRehashDetectsBcrypt(): void {
		$bcryptHash = password_hash('old_pass', PASSWORD_BCRYPT);
		if (defined('PASSWORD_ARGON2ID')) {
			$this->assertTrue(password_needs_rehash($bcryptHash, PASSWORD_ARGON2ID));
		} else {
			$this->markSkipped('PASSWORD_ARGON2ID not available');
		}
	}

	/* ═══ Webhook HMAC-SHA256 署名検証 ═══ */

	public function testVerifySignatureValidSignature(): void {
		$payload = '{"event":"test","data":{}}';
		$secret = 'my_webhook_secret';
		$signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
		$this->assertTrue(WebhookEngine::verifySignature($payload, $signature, $secret));
	}

	public function testVerifySignatureInvalidSignature(): void {
		$payload = '{"event":"test","data":{}}';
		$secret = 'my_webhook_secret';
		$wrongSig = 'sha256=0000000000000000000000000000000000000000000000000000000000000000';
		$this->assertFalse(WebhookEngine::verifySignature($payload, $wrongSig, $secret));
	}

	public function testVerifySignatureEmptySecret(): void {
		$this->assertFalse(WebhookEngine::verifySignature('payload', 'sha256=abc', ''));
	}

	public function testVerifySignatureEmptySignature(): void {
		$this->assertFalse(WebhookEngine::verifySignature('payload', '', 'secret'));
	}

	public function testVerifySignatureTampered(): void {
		$payload = '{"event":"test","data":{}}';
		$secret = 'secret123';
		$signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
		/* ペイロード改ざん */
		$tampered = '{"event":"test","data":{"hacked":true}}';
		$this->assertFalse(WebhookEngine::verifySignature($tampered, $signature, $secret));
	}

	/* ═══ API キープレフィックス拡張 (12文字) ═══ */

	public function testGenerateApiKeyPrefix12Chars(): void {
		$key = ApiEngine::generateApiKey('test_key_16');
		$this->assertStringStartsWith('ap_', $key);
		$this->assertTrue(strlen($key) >= 48, 'API キーは48文字以上');

		/* 生成されたキーのプレフィックスが12文字形式か確認 */
		$keys = json_read('api_keys.json', settings_dir());
		$lastKey = end($keys);
		$expectedPrefix = substr($key, 0, 12) . '...';
		$this->assertEquals($expectedPrefix, $lastKey['key_prefix']);
	}

	/* ═══ CacheEngine::remember ═══ */

	public function testCacheRememberStoresAndReturns(): void {
		$callCount = 0;
		$result = CacheEngine::remember('test_remember', 60, function () use (&$callCount) {
			$callCount++;
			return ['cached' => true, 'value' => 42];
		});
		$this->assertEquals(['cached' => true, 'value' => 42], $result);
		$this->assertEquals(1, $callCount);

		/* 2回目はキャッシュから取得 */
		$result2 = CacheEngine::remember('test_remember', 60, function () use (&$callCount) {
			$callCount++;
			return ['different' => true];
		});
		$this->assertEquals(['cached' => true, 'value' => 42], $result2);
		$this->assertEquals(1, $callCount, 'callback は1回だけ呼ばれるべき');
	}

	/* ═══ Application イベントシステム ═══ */

	public function testEventDispatcherFiresListeners(): void {
		$fired = false;
		Application::events()->listen('test.event', function ($data) use (&$fired) {
			$fired = true;
		});
		Application::events()->dispatch('test.event', ['key' => 'value']);
		$this->assertTrue($fired, 'イベントリスナーが実行されるべき');
	}

	public function testApplicationIsBooted(): void {
		$this->assertTrue(Application::isBooted());
	}
}
