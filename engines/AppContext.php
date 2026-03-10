<?php
/**
 * AppContext - アプリケーション状態の集中管理
 *
 * B-3 fix: グローバル変数 ($c, $d, $host 等) をクラスベースの状態管理に移行。
 * 全エンジンから AppContext::get() / AppContext::set() でアクセス。
 */
class AppContext {

	/** @var array<string, mixed> アプリケーション設定 (旧 $c) */
	private static array $config = [];

	/** @var array<string, mixed> デフォルト値・テンプレートデータ (旧 $d) */
	private static array $defaults = [];

	/** @var string ベースURL (旧 $host) */
	private static string $host = '';

	/** @var string リクエストパス (旧 $rp) */
	private static string $requestPath = '';

	/** @var string ログインステータスHTML (旧 $lstatus) */
	private static string $loginStatus = '';

	/** @var string クレジットHTML (旧 $apcredit) */
	private static string $credit = '';

	/** @var array<string, array<string>> フック登録 (旧 $hook) */
	private static array $hooks = [];

	/* ── 設定 ($c) アクセサ ── */

	public static function config(string $key, mixed $default = null): mixed {
		return self::$config[$key] ?? $default;
	}

	public static function setConfig(string $key, mixed $value): void {
		self::$config[$key] = $value;
	}

	public static function getAllConfig(): array {
		return self::$config;
	}

	public static function setAllConfig(array $config): void {
		self::$config = $config;
	}

	/* ── デフォルト値 ($d) アクセサ ── */

	public static function defaults(string $section, string $key, mixed $default = null): mixed {
		return self::$defaults[$section][$key] ?? $default;
	}

	public static function setDefaults(string $section, string $key, mixed $value): void {
		self::$defaults[$section][$key] = $value;
	}

	public static function getAllDefaults(): array {
		return self::$defaults;
	}

	public static function setAllDefaults(array $defaults): void {
		self::$defaults = $defaults;
	}

	/* ── ホスト / リクエストパス ── */

	public static function host(): string {
		return self::$host;
	}

	public static function setHost(string $host): void {
		self::$host = $host;
	}

	public static function requestPath(): string {
		return self::$requestPath;
	}

	public static function setRequestPath(string $path): void {
		self::$requestPath = $path;
	}

	/* ── ログインステータス / クレジット ── */

	public static function loginStatus(): string {
		return self::$loginStatus;
	}

	public static function setLoginStatus(string $status): void {
		self::$loginStatus = $status;
	}

	public static function credit(): string {
		return self::$credit;
	}

	public static function setCredit(string $credit): void {
		self::$credit = $credit;
	}

	/* ── フック管理 ── */

	public static function addHook(string $name, string $content): void {
		self::$hooks[$name][] = $content;
	}

	public static function getHooks(string $name): array {
		return self::$hooks[$name] ?? [];
	}

	public static function getAllHooks(): array {
		return self::$hooks;
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
	}

	/**
	 * AppContext の状態をグローバル変数に書き戻す（後方互換用）
	 */
	public static function syncToGlobals(
		array &$c, array &$d, string &$host,
		string &$lstatus, string &$apcredit, array &$hook
	): void {
		$c        = self::$config;
		$d        = self::$defaults;
		$host     = self::$host;
		$lstatus  = self::$loginStatus;
		$apcredit = self::$credit;
		$hook     = self::$hooks;
	}
}
