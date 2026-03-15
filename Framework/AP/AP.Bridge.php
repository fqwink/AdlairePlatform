<?php
/**
 * AP.Bridge - グローバルユーティリティ関数
 *
 * index.php のページレンダリングで使用されるグローバル関数を提供する。
 * 各関数は Framework クラスに委譲する。
 *
 * @since Ver.1.8
 * @license Adlaire License Ver.2.0
 */

/* ══════════════════════════════════════════════
   JsonCache - 後方互換シム
   APF\Utilities\JsonStorage に委譲
   ══════════════════════════════════════════════ */

class JsonCache {
	public static function get(string $path): ?array {
		return \APF\Utilities\JsonStorage::getCached($path);
	}

	public static function set(string $path, array $data): void {
		\APF\Utilities\JsonStorage::setCached($path, $data);
	}

	public static function clear(): void {
		\APF\Utilities\JsonStorage::clearCache();
	}
}

/* ══════════════════════════════════════════════
   ディレクトリヘルパー → AIS\Core\AppContext
   ══════════════════════════════════════════════ */

function data_dir(): string {
	return \AIS\Core\AppContext::dataDir();
}

function settings_dir(): string {
	return \AIS\Core\AppContext::settingsDir();
}

function content_dir(): string {
	return \AIS\Core\AppContext::contentDir();
}

/* ══════════════════════════════════════════════
   JSON I/O → APF\Utilities\JsonStorage
   ══════════════════════════════════════════════ */

function json_read(string $file, string $dir = ''): array {
	return \APF\Utilities\JsonStorage::read($file, $dir);
}

function json_write(string $file, array $data, string $dir = ''): void {
	\APF\Utilities\JsonStorage::write($file, $data, $dir);
}

/* ══════════════════════════════════════════════
   文字列ユーティリティ → APF\Utilities\Security / Str
   ══════════════════════════════════════════════ */

function h(string $s): string {
	return \APF\Utilities\Security::escape($s);
}

function getSlug(string $p): string {
	return \APF\Utilities\Str::safePath($p);
}

/* ══════════════════════════════════════════════
   ホスト解決 → AIS\Core\AppContext::resolveHost()
   ══════════════════════════════════════════════ */

function host(): void {
	global $host, $rp;
	$resolved = \AIS\Core\AppContext::resolveHost();
	$host = $resolved['host'];
	$rp = $resolved['rp'];
}
