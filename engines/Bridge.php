<?php
/**
 * Bridge - グローバルユーティリティ関数 & 後方互換ブリッジ
 *
 * index.php に直接記述されていたユーティリティ関数・クラスを集約。
 * 将来的に Framework モジュールへの委譲ポイントとなる。
 *
 * @since Ver.1.5.0
 * @license Adlaire License Ver.2.0
 */

/* ══════════════════════════════════════════════
   JsonCache - リクエスト内キャッシュ
   ══════════════════════════════════════════════ */

/**
 * E-1 fix: リクエスト内キャッシュ付き JSON I/O
 * JsonCache クラスで共有キャッシュを管理し、重複I/Oを排除。
 */
class JsonCache {
	/** @var array<string, array> パス → データの読み込みキャッシュ */
	private static array $store = [];

	public static function get(string $path): ?array {
		return self::$store[$path] ?? null;
	}

	public static function set(string $path, array $data): void {
		self::$store[$path] = $data;
	}

	public static function clear(): void {
		self::$store = [];
	}
}

/* ══════════════════════════════════════════════
   ディレクトリヘルパー
   ══════════════════════════════════════════════ */

function data_dir(): string {
	$dir = 'data';
	if (!is_dir($dir)) mkdir($dir, 0755, true);
	return $dir;
}

function settings_dir(): string {
	$dir = 'data/settings';
	if (!is_dir($dir)) mkdir($dir, 0755, true);
	return $dir;
}

function content_dir(): string {
	$dir = 'data/content';
	if (!is_dir($dir)) mkdir($dir, 0755, true);
	return $dir;
}

/* ══════════════════════════════════════════════
   JSON I/O
   ══════════════════════════════════════════════ */

function json_read(string $file, string $dir = ''): array {
	$path = ($dir ?: data_dir()) . '/' . $file;

	/* キャッシュヒット */
	$cached = JsonCache::get($path);
	if ($cached !== null) {
		return $cached;
	}

	if (!file_exists($path)) return [];
	$raw = file_get_contents($path);
	if ($raw === false) return [];
	$decoded = json_decode($raw, true);
	$result = is_array($decoded) ? $decoded : [];

	/* キャッシュに保存 */
	JsonCache::set($path, $result);
	return $result;
}

function json_write(string $file, array $data, string $dir = ''): void {
	$path = ($dir ?: data_dir()) . '/' . $file;

	$result = file_put_contents(
		$path,
		json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
		LOCK_EX
	);
	if ($result === false) {
		Logger::error('JSON書き込み失敗', ['file' => $file]);
		header('HTTP/1.1 500 Internal Server Error');
		exit;
	}

	/* 書き込み成功時にキャッシュも更新 */
	JsonCache::set($path, $data);
}

/* ══════════════════════════════════════════════
   文字列ユーティリティ
   ══════════════════════════════════════════════ */

function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function getSlug(string $p): string {
	$slug = mb_convert_case(str_replace(' ', '-', $p), MB_CASE_LOWER, "UTF-8");
	/* R26 fix: ループでパストラバーサルを再帰的に除去（ ....// → ../ 対策） */
	$slug = str_replace("\0", '', $slug);
	do {
		$prev = $slug;
		$slug = str_replace(['../', '..\\'], '', $slug);
	} while ($slug !== $prev);
	$slug = preg_replace('#/+#', '/', $slug);
	return ltrim($slug, '/');
}

/* ══════════════════════════════════════════════
   ホスト解決・レガシー関数
   ══════════════════════════════════════════════ */

function host(): void {
	global $host, $rp;
	$rp = preg_replace('#/+#', '/', (isset($_GET['page'])) ? urldecode($_GET['page']) : '');
	/* C8 fix: HTTP_HOST ヘッダーインジェクション防止 */
	$rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$rawHost = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', $rawHost);
	$host = $rawHost;
	$uri = preg_replace('#/+#', '/', urldecode($_SERVER['REQUEST_URI']));
	/* BUG#7 fix: $rp が空文字列の場合 strrpos は常に true を返す問題を修正 */
	$host = ($rp !== '' && strrpos($uri, $rp) !== false) ? $host . '/' . substr($uri, 0, strlen($uri) - strlen($rp)) : $host . '/' . $uri;
	$host = explode('?', $host);
	$host = '//' . str_replace('//', '/', $host[0]);
	$strip = array('index.php', '?', '"', '\'', '>', '<', '=', '(', ')', '\\');
	$rp = strip_tags(str_replace($strip, '', $rp));
	$host = strip_tags(str_replace($strip, '', $host));
}

/* ══════════════════════════════════════════════
   データマイグレーション
   ══════════════════════════════════════════════ */

/**
 * Ver.1.7-36: Phase 1（files/ → data/）マイグレーションを削除。
 * Ver.1.7-37: Phase 2（data/*.json → data/settings/ & data/content/）を削除。
 *   Ver.1.4 → Ver.1.5 移行期間は十分経過。新規インストールは
 *   data/settings/ & data/content/ を直接使用。
 *
 * is_loggedin() は Ver.1.4 非推奨 → Ver.1.7 で削除。
 * AdminEngine::isLoggedIn() を使用してください。
 */
