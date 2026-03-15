<?php
/**
 * TemplateEngine - 後方互換シム
 *
 * Ver.1.8: 全ロジックを ASG\Template\TemplateService に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — \ASG\Template\TemplateService を使用してください
 */
class TemplateEngine {

	/** @deprecated */
	public static function getRenderer(): \ASG\Template\TemplateRenderer {
		static $renderer = null;
		if ($renderer === null) {
			$renderer = new \ASG\Template\TemplateRenderer();
		}
		return $renderer;
	}

	/** @deprecated */
	public static function render(string $template, array $context, string $partialsDir = ''): string {
		return \ASG\Template\TemplateService::render($template, $context, $partialsDir);
	}
}
