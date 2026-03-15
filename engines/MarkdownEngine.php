<?php
/**
 * MarkdownEngine - 後方互換シム
 *
 * Ver.1.8: 全ロジックを ASG\Template\MarkdownService に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — \ASG\Template\MarkdownService を使用してください
 */
class MarkdownEngine {

	/** @deprecated */
	public static function getParser(): \ASG\Template\MarkdownParser {
		static $parser = null;
		if ($parser === null) {
			$parser = new \ASG\Template\MarkdownParser();
		}
		return $parser;
	}

	/** @deprecated */
	public static function parseFrontmatter(string $content): array {
		return \ASG\Template\MarkdownService::parseFrontmatter($content);
	}

	/** @deprecated */
	public static function toHtml(string $markdown, ?string $baseDir = null, bool $addHeadingIds = false): string {
		return \ASG\Template\MarkdownService::toHtml($markdown, $baseDir, $addHeadingIds);
	}

	/** @deprecated */
	public static function loadDirectory(string $dir): array {
		return \ASG\Template\MarkdownService::loadDirectory($dir);
	}
}
