<?php
/**
 * TemplateEngine - 軽量テンプレートエンジン（PHP フリーテーマ用）
 *
 * 構文:
 *   {{variable}}              → htmlspecialchars でエスケープ出力
 *   {{{variable}}}            → 生 HTML 出力
 *   {{#if var}}...{{else}}...{{/if}}  → 条件分岐（!var で否定）
 *   {{#each items}}...{{/each}}       → ループ
 */
class TemplateEngine {

	/**
	 * テンプレート文字列とコンテキスト配列から HTML を生成
	 */
	public static function render(string $template, array $context): string
	{
		$html = self::processEach($template, $context);
		$html = self::processIf($html, $context);
		$html = self::processRawVars($html, $context);
		$html = self::processVars($html, $context);
		return $html;
	}

	/**
	 * {{#each items}}...{{/each}} を処理
	 */
	private static function processEach(string $tpl, array $ctx): string
	{
		return preg_replace_callback(
			'/\{\{#each\s+(\w+)\}\}(.*?)\{\{\/each\}\}/s',
			function (array $m) use ($ctx): string {
				$key  = $m[1];
				$body = $m[2];
				$items = $ctx[$key] ?? [];
				if (!is_array($items)) return '';
				$out = '';
				foreach ($items as $item) {
					if (!is_array($item)) continue;
					$loopCtx = array_merge($ctx, $item);
					$rendered = self::processIf($body, $loopCtx);
					$rendered = self::processRawVars($rendered, $loopCtx);
					$rendered = self::processVars($rendered, $loopCtx);
					$out .= $rendered;
				}
				return $out;
			},
			$tpl
		) ?? $tpl;
	}

	/**
	 * {{#if var}}...{{else}}...{{/if}} を処理（ネスト対応・再帰）
	 */
	private static function processIf(string $tpl, array $ctx): string
	{
		$prev = '';
		while ($prev !== $tpl) {
			$prev = $tpl;
			$tpl = preg_replace_callback(
				'/\{\{#if\s+(!?\w+)\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{\/if\}\}/s',
				function (array $m) use ($ctx): string {
					$key    = $m[1];
					$negate = false;
					if (str_starts_with($key, '!')) {
						$negate = true;
						$key = substr($key, 1);
					}
					$truthy = !empty($ctx[$key]);
					if ($negate) $truthy = !$truthy;
					return $truthy ? $m[2] : ($m[3] ?? '');
				},
				$tpl
			) ?? $tpl;
		}
		return $tpl;
	}

	/**
	 * {{{var}}} → エスケープなしの生出力
	 */
	private static function processRawVars(string $tpl, array $ctx): string
	{
		return preg_replace_callback(
			'/\{\{\{(\w+)\}\}\}/',
			fn(array $m): string => (string)($ctx[$m[1]] ?? ''),
			$tpl
		) ?? $tpl;
	}

	/**
	 * {{var}} → htmlspecialchars でエスケープ
	 */
	private static function processVars(string $tpl, array $ctx): string
	{
		return preg_replace_callback(
			'/\{\{(\w+)\}\}/',
			fn(array $m): string => htmlspecialchars(
				(string)($ctx[$m[1]] ?? ''),
				ENT_QUOTES,
				'UTF-8'
			),
			$tpl
		) ?? $tpl;
	}
}
