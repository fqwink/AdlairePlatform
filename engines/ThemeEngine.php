<?php
/**
 * ThemeEngine - テーマ検証・読み込みロジック
 */
class ThemeEngine {
	private const FALLBACK   = 'AP-Default';
	private const THEMES_DIR = 'themes';

	public static function load(string $themeSelect): void {
		if (!preg_match('/^[a-zA-Z0-9_-]+$/', $themeSelect)) {
			$themeSelect = self::FALLBACK;
		}
		$path = self::THEMES_DIR . '/' . $themeSelect . '/theme.php';
		if (!file_exists($path)) {
			$path = self::THEMES_DIR . '/' . self::FALLBACK . '/theme.php';
		}
		require $path;
	}

	public static function listThemes(): array {
		$dirs = glob(self::THEMES_DIR . '/*', GLOB_ONLYDIR);
		return is_array($dirs) ? array_map('basename', $dirs) : [];
	}
}
