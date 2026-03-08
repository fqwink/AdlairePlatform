<?php
/**
 * TemplateEngine - 軽量テンプレートエンジン（PHP フリーテーマ用）
 *
 * 構文:
 *   {{variable}}              → htmlspecialchars でエスケープ出力
 *   {{{variable}}}            → 生 HTML 出力
 *   {{#if var}}...{{else}}...{{/if}}  → 条件分岐（!var で否定）
 *   {{#each items}}...{{/each}}       → ループ（@index, @first, @last 使用可）
 *   {{> partial}}             → 部分テンプレートの読み込み
 */
class TemplateEngine {

	/** パーシャル検索ディレクトリ（テーマディレクトリ） */
	private static string $partialsDir = '';

	/**
	 * テンプレート文字列とコンテキスト配列から HTML を生成
	 *
	 * @param string $template   テンプレート文字列
	 * @param array  $context    コンテキスト変数
	 * @param string $partialsDir パーシャル検索ディレクトリ（省略時は前回値を維持）
	 */
	public static function render(string $template, array $context, string $partialsDir = ''): string
	{
		if ($partialsDir !== '') {
			self::$partialsDir = $partialsDir;
		}

		$html = self::processPartials($template, $context);
		$html = self::processEach($html, $context);
		$html = self::processIf($html, $context);
		$html = self::processRawVars($html, $context);
		$html = self::processVars($html, $context);
		self::warnUnprocessed($html);
		return $html;
	}

	/**
	 * {{> partial}} → 部分テンプレートの読み込みと再帰レンダリング
	 * 循環参照防止: 最大深度 10
	 */
	private static int $partialDepth = 0;
	private const PARTIAL_MAX_DEPTH = 10;

	private static function processPartials(string $tpl, array $ctx): string
	{
		return preg_replace_callback(
			'/\{\{>\s*(\w+)\s*\}\}/',
			function (array $m) use ($ctx): string {
				$name = $m[1];
				if (self::$partialDepth >= self::PARTIAL_MAX_DEPTH) {
					error_log("TemplateEngine: パーシャルのネストが深すぎます（最大" . self::PARTIAL_MAX_DEPTH . "）: {$name}");
					return '';
				}
				$path = self::$partialsDir . '/' . $name . '.html';
				if (!file_exists($path)) {
					error_log("TemplateEngine: パーシャルが見つかりません: {$path}");
					return '';
				}
				self::$partialDepth++;
				$content = file_get_contents($path);
				$rendered = self::processPartials($content, $ctx);
				self::$partialDepth--;
				return $rendered;
			},
			$tpl
		) ?? $tpl;
	}

	/**
	 * {{#each items}}...{{/each}} を処理
	 * ループ内で @index, @first, @last が使用可能
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
				$items = array_values($items);
				$count = count($items);
				$out = '';
				foreach ($items as $i => $item) {
					if (!is_array($item)) continue;
					$loopCtx = array_merge($ctx, $item, [
						'@index' => $i,
						'@first' => ($i === 0),
						'@last'  => ($i === $count - 1),
					]);
					$rendered = self::processEach($body, $loopCtx);
					$rendered = self::processIf($rendered, $loopCtx);
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
				'/\{\{#if\s+(!?[\w@]+)\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{\/if\}\}/s',
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
			'/\{\{\{([\w@]+)\}\}\}/',
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
			'/\{\{([\w@]+)\}\}/',
			fn(array $m): string => htmlspecialchars(
				(string)($ctx[$m[1]] ?? ''),
				ENT_QUOTES,
				'UTF-8'
			),
			$tpl
		) ?? $tpl;
	}

	/**
	 * 未処理のテンプレート構文を検出して警告ログを出力
	 */
	private static function warnUnprocessed(string $html): void
	{
		if (preg_match_all('/\{\{[#\/!>]?\s*[\w@]+[^}]*\}\}/', $html, $matches)) {
			foreach ($matches[0] as $tag) {
				error_log("TemplateEngine: 未処理のテンプレートタグ: {$tag}");
			}
		}
	}
}
