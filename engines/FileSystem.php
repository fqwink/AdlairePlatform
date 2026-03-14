<?php
/**
 * FileSystem - ファイル I/O 抽象化（後方互換シム）
 *
 * Ver.1.8: 全ロジックを APF\Utilities\FileSystem に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — APF\Utilities\FileSystem を使用してください
 */
class FileSystem {

	public static function read(string $path): string|false {
		$content = \APF\Utilities\FileSystem::read($path);
		if ($content === false && class_exists('Logger', false)) {
			Logger::error('ファイル読み込み失敗', ['path' => basename($path)]);
		}
		return $content;
	}

	public static function write(string $path, string $content): bool {
		$result = \APF\Utilities\FileSystem::write($path, $content);
		if (!$result && class_exists('Logger', false)) {
			Logger::error('ファイル書き込み失敗', ['path' => basename($path)]);
		}
		return $result;
	}

	public static function readJson(string $path): ?array {
		return \APF\Utilities\FileSystem::readJson($path);
	}

	public static function writeJson(string $path, array $data): bool {
		return \APF\Utilities\FileSystem::writeJson($path, $data);
	}

	public static function exists(string $path): bool {
		return \APF\Utilities\FileSystem::exists($path);
	}

	public static function delete(string $path): bool {
		return \APF\Utilities\FileSystem::delete($path);
	}

	public static function ensureDir(string $dir): bool {
		return \APF\Utilities\FileSystem::ensureDir($dir);
	}
}
