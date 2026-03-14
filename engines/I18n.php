<?php
/**
 * I18n - 多言語化ヘルパー
 *
 * 対応言語: ja（デフォルト）, en
 * 翻訳ファイル: lang/ja.json, lang/en.json
 */
class I18n {

	private static string $locale = 'ja';
	private static array $translations = [];
	private static array $fallback = [];
	private static bool $loaded = false;

	/**
	 * ロケールを設定し、翻訳ファイルを読み込む
	 */
	public static function init(): void {
		/* 優先順位: GET > SESSION > settings.json > 'ja' */
		$locale = $_GET['lang'] ?? $_SESSION['ap_locale'] ?? null;
		if ($locale === null) {
			$settings = json_read('settings.json', settings_dir());
			$locale = $settings['admin_locale'] ?? 'ja';
		}
		$locale = in_array($locale, ['ja', 'en'], true) ? $locale : 'ja';

		/* セッションに保存 */
		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION['ap_locale'] = $locale;
		}

		self::$locale = $locale;
		self::load();
	}

	/**
	 * 翻訳ファイルを読み込み
	 */
	private static function load(): void {
		$basePath = dirname(__DIR__) . '/lang/';

		/* フォールバック（日本語）を常に読み込み */
		$jaPath = $basePath . 'ja.json';
		if (file_exists($jaPath)) {
			$data = json_decode(file_get_contents($jaPath), true);
			self::$fallback = is_array($data) ? $data : [];
		}

		/* 現在のロケール */
		if (self::$locale === 'ja') {
			self::$translations = self::$fallback;
		} else {
			$path = $basePath . self::$locale . '.json';
			if (file_exists($path)) {
				$data = json_decode(file_get_contents($path), true);
				self::$translations = is_array($data) ? $data : [];
			}
		}

		self::$loaded = true;
	}

	/**
	 * 現在のロケールを取得
	 */
	public static function getLocale(): string {
		return self::$locale;
	}

	/**
	 * 翻訳を取得
	 *
	 * @param string $key    ドット記法キー（例: "login.submit"）
	 * @param array  $params プレースホルダ（例: ['count' => 5]）
	 * @return string
	 */
	public static function t(string $key, array $params = []): string {
		if (!self::$loaded) self::load();

		$text = self::$translations[$key]
			?? self::$fallback[$key]
			?? $key;

		/* プレースホルダ置換: {variable} → 値 */
		if (!empty($params)) {
			foreach ($params as $k => $v) {
				$text = str_replace('{' . $k . '}', (string)$v, $text);
			}
		}

		return $text;
	}

	/**
	 * 全翻訳データをフラットな配列で返す（JS注入用）
	 */
	public static function all(): array {
		if (!self::$loaded) self::load();
		return array_merge(self::$fallback, self::$translations);
	}

	/**
	 * 全翻訳データをネスト配列で返す（テンプレートエンジン用）
	 *
	 * "login.submit" → ['login' => ['submit' => '...']]
	 */
	public static function allNested(): array {
		$flat = self::all();
		$nested = [];
		foreach ($flat as $key => $value) {
			$parts = explode('.', $key);
			$ref = &$nested;
			foreach ($parts as $i => $part) {
				if ($i === count($parts) - 1) {
					$ref[$part] = $value;
				} else {
					if (!isset($ref[$part]) || !is_array($ref[$part])) {
						$ref[$part] = [];
					}
					$ref = &$ref[$part];
				}
			}
			unset($ref);
		}
		return $nested;
	}

	/**
	 * HTML lang 属性用の値を返す
	 */
	public static function htmlLang(): string {
		return self::$locale;
	}
}
