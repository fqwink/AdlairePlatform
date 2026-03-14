<?php
/**
 * FileSystem - ファイル I/O 抽象化
 *
 * 全エンジンで散在する file_get_contents / file_put_contents を統合。
 * ロギング・エラーハンドリング・ロック管理を一元化。
 */
class FileSystem {

	/**
	 * ファイルを安全に読み込む。
	 * @return string|false 成功時は内容、失敗時は false
	 */
	public static function read(string $path): string|false {
		$content = @file_get_contents($path);
		if ($content === false) {
			Logger::error('ファイル読み込み失敗', ['path' => basename($path)]);
			if (class_exists('DiagnosticEngine')) {
				DiagnosticEngine::log('runtime', 'ファイル読み込み失敗', ['path' => basename($path)]);
			}
		}
		return $content;
	}

	/**
	 * ファイルを安全に書き込む（LOCK_EX 付き）。
	 * @return bool 成功時 true
	 */
	public static function write(string $path, string $content): bool {
		$dir = dirname($path);
		if (!is_dir($dir)) {
			if (!@mkdir($dir, 0755, true)) {
				Logger::error('ディレクトリ作成失敗', ['dir' => basename($dir)]);
				return false;
			}
		}
		$result = @file_put_contents($path, $content, LOCK_EX);
		if ($result === false) {
			Logger::error('ファイル書き込み失敗', ['path' => basename($path)]);
			if (class_exists('DiagnosticEngine')) {
				DiagnosticEngine::log('runtime', 'ファイル書き込み失敗', ['path' => basename($path)]);
			}
			return false;
		}
		return true;
	}

	/**
	 * JSON ファイルを読み込んで配列として返す。
	 * @return array|null 失敗時は null
	 */
	public static function readJson(string $path): ?array {
		$content = self::read($path);
		if ($content === false) return null;
		$data = json_decode($content, true);
		if (!is_array($data)) {
			Logger::warning('JSON パース失敗', ['path' => basename($path)]);
			return null;
		}
		return $data;
	}

	/**
	 * 配列を JSON ファイルとして書き込む。
	 */
	public static function writeJson(string $path, array $data): bool {
		$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		if ($json === false) {
			Logger::error('JSON エンコード失敗', ['path' => basename($path)]);
			return false;
		}
		return self::write($path, $json);
	}

	/**
	 * ファイルが存在するか確認。
	 */
	public static function exists(string $path): bool {
		return file_exists($path);
	}

	/**
	 * ファイルを安全に削除。
	 */
	public static function delete(string $path): bool {
		if (!file_exists($path)) return true;
		$result = @unlink($path);
		if (!$result) {
			Logger::error('ファイル削除失敗', ['path' => basename($path)]);
		}
		return $result;
	}

	/**
	 * ディレクトリが存在しなければ作成。
	 */
	public static function ensureDir(string $dir): bool {
		if (is_dir($dir)) return true;
		$result = @mkdir($dir, 0755, true);
		if (!$result) {
			Logger::error('ディレクトリ作成失敗', ['dir' => basename($dir)]);
		}
		return $result;
	}
}
