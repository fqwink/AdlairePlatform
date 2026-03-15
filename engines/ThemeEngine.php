<?php
/**
 * ThemeEngine - 後方互換シム
 *
 * Ver.1.8: 全ロジックを ASG\Template\ThemeService に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — \ASG\Template\ThemeService を使用してください
 */
class ThemeEngine {

	/** @deprecated */
	public static function load(string $themeSelect): void {
		\ASG\Template\ThemeService::load($themeSelect);
	}

	/** @deprecated */
	public static function listThemes(): array {
		return \ASG\Template\ThemeService::listThemes();
	}

	/** @deprecated */
	public static function buildContext(): array {
		return \ASG\Template\ThemeService::buildContext();
	}

	/** @deprecated */
	public static function buildStaticContext(
		string $slug, string $content, array $settings, array $meta = []
	): array {
		return \ASG\Template\ThemeService::buildStaticContext($slug, $content, $settings, $meta);
	}

	/** @deprecated */
	public static function parseMenu(string $menuStr, string $currentPage): array {
		return \ASG\Template\ThemeService::parseMenu($menuStr, $currentPage);
	}
}
