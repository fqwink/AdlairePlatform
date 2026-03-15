<?php
/**
 * ImageOptimizer - 後方互換シム
 *
 * Ver.1.8: 全ロジックを ASG\Utilities\ImageService に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — \ASG\Utilities\ImageService を使用してください
 */
class ImageOptimizer {

	/** @deprecated */
	public static function optimize(string $path): bool {
		return \ASG\Utilities\ImageService::optimize($path);
	}
}
