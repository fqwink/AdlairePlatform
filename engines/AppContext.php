<?php
/**
 * AppContext - アプリケーション状態の集中管理
 *
 * B-3 fix: グローバル変数 ($c, $d, $host 等) をクラスベースの状態管理に移行。
 * 全エンジンから AppContext::config() / AppContext::defaults() 等でアクセス。
 *
 * Ver.1.5: AIS\Core\AppContext インスタンスに内部委譲。
 *          既存の static API は完全に維持。
 */
class AppContext {

	/** @var array<string, mixed> アプリケーション設定 (旧 $c) */
	private static array $config = [];

	/** @var array<string, mixed> デフォルト値・テンプレートデータ (旧 $d) */
	private static array $defaults = [];

	/** @var string ベースURL (旧 $host) */
	private static string $host = '';

	/** @var string ログインステータスHTML (旧 $lstatus) */
	private static string $loginStatus = '';

	/** @var string クレジットHTML (旧 $apcredit) */
	private static string $credit = '';

	/** @var array<string, array<string>> フック登録 (旧 $hook) */
	private static array $hooks = [];

	/** @var \AIS\Core\AppContext|null Framework インスタンス */
	private static ?\AIS\Core\AppContext $instance = null;

	/* ── 設定 ($c) アクセサ ── */

	public static function config(string $key, mixed $default = null): mixed {
		return self::$config[$key] ?? $default;
	}

	/* ── デフォルト値 ($d) アクセサ ── */

	public static function defaults(string $section, string $key, mixed $default = null): mixed {
		return self::$defaults[$section][$key] ?? $default;
	}

	/* ── ホスト ── */

	public static function host(): string {
		return self::$host;
	}

	/* ── ログインステータス / クレジット ── */

	public static function loginStatus(): string {
		return self::$loginStatus;
	}

	public static function credit(): string {
		return self::$credit;
	}

	/* ── フック管理 ── */

	public static function addHook(string $name, string $content): void {
		self::$hooks[$name][] = $content;
	}

	public static function getHooks(string $name): array {
		return self::$hooks[$name] ?? [];
	}

	/**
	 * グローバル変数との同期（後方互換用）
	 * index.php の初期化完了後に呼び出し、旧コードとの互換性を維持する。
	 */
	public static function syncFromGlobals(
		array &$c, array &$d, string &$host,
		string &$lstatus, string &$apcredit, ?array &$hook = null
	): void {
		self::$config      = $c;
		self::$defaults    = $d;
		self::$host        = $host;
		self::$loginStatus = $lstatus;
		self::$credit      = $apcredit;
		self::$hooks       = $hook ?? [];

		/* Ver.1.5: Framework AppContext にも同期 */
		self::$instance = new \AIS\Core\AppContext([
			'config'   => $c,
			'defaults' => $d,
			'host'     => $host,
			'version'  => defined('AP_VERSION') ? AP_VERSION : 'unknown',
		]);
	}

	/**
	 * Ver.1.5: Framework AppContext インスタンスを取得する
	 *
	 * Framework モジュールとの連携や、ドット記法アクセスが必要な場合に使用。
	 */
	public static function getInstance(): \AIS\Core\AppContext {
		if (self::$instance === null) {
			self::$instance = new \AIS\Core\AppContext([
				'config'   => self::$config,
				'defaults' => self::$defaults,
				'host'     => self::$host,
				'version'  => defined('AP_VERSION') ? AP_VERSION : 'unknown',
			]);
		}
		return self::$instance;
	}
}
