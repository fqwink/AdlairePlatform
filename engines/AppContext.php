<?php
/**
 * AppContext - アプリケーション状態の集中管理
 *
 * B-3 fix: グローバル変数 ($c, $d, $host 等) をクラスベースの状態管理に移行。
 * 全エンジンから AppContext::config() / AppContext::defaults() 等でアクセス。
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
	}
}
