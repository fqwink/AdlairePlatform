<?php
/**
 * I18n - 多言語化ヘルパー（後方互換シム）
 *
 * Ver.1.8: 全ロジックを AIS\Core\I18n に移植。
 * このファイルは後方互換のためのシムとして残存。
 * Stage 8 で削除予定。
 *
 * @deprecated Ver.1.8 — AIS\Core\I18n を使用してください
 */
class I18n {

	public static function init(): void {
		\AIS\Core\I18n::init();
	}

	public static function getLocale(): string {
		return \AIS\Core\I18n::getLocale();
	}

	public static function t(string $key, array $params = []): string {
		return \AIS\Core\I18n::t($key, $params);
	}

	public static function all(): array {
		return \AIS\Core\I18n::all();
	}

	public static function allNested(): array {
		return \AIS\Core\I18n::allNested();
	}

	public static function htmlLang(): string {
		return \AIS\Core\I18n::htmlLang();
	}
}
