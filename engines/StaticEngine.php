<?php
/**
 * StaticEngine - 後方互換シム
 *
 * Ver.1.8: 全ロジックを ASG\Core\StaticService に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — \ASG\Core\StaticService を使用してください
 */
class StaticEngine {

	/** @deprecated */
	public function init(): void {
		\ASG\Core\StaticService::init();
	}

	/** @deprecated */
	public function buildDiff(): array {
		return \ASG\Core\StaticService::buildDiff();
	}

	/** @deprecated */
	public function buildAll(): array {
		return \ASG\Core\StaticService::buildAll();
	}

	/** @deprecated */
	public function clean(): void {
		\ASG\Core\StaticService::clean();
	}

	/** @deprecated */
	public function getStatus(): array {
		return \ASG\Core\StaticService::getStatus();
	}

	/** @deprecated */
	public function copyAssets(): void {
		\ASG\Core\StaticService::copyAssets();
	}

	/** @deprecated */
	public function buildZipFile(): string {
		return \ASG\Core\StaticService::buildZipFile();
	}

	/** @deprecated */
	public function buildDiffZipFile(): string {
		return \ASG\Core\StaticService::buildDiffZipFile();
	}

	/** @deprecated */
	public static function minifyHtml(string $html): string {
		return \ASG\Core\StaticService::minifyHtml($html);
	}

	/** @deprecated */
	public static function minifyCss(string $css): string {
		return \ASG\Core\StaticService::minifyCss($css);
	}
}
