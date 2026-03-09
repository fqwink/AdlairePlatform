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
		self::$partialDepth = 0;

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
				if ($content === false) {
					self::$partialDepth--;
					error_log("TemplateEngine: パーシャルの読み込みに失敗しました: {$path}");
					return '';
				}
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
	 * ネストされた {{#each}} にも対応（バランスドマッチング）
	 */
	private static function processEach(string $tpl, array $ctx): string
	{
		$offset = 0;
		while (preg_match('/\{\{#each\s+(\w+)\}\}/s', $tpl, $m, PREG_OFFSET_CAPTURE, $offset)) {
			$tagStart = $m[0][1];
			$tagEnd   = $tagStart + strlen($m[0][0]);
			$key      = $m[1][0];

			$closeEnd = self::findClosingTag($tpl, $tagEnd, '#each\s+\w+', '/each');
			if ($closeEnd === null) {
				$offset = $tagEnd;
				continue;
			}
			/* C10 fix: strrpos の代わりに閉じタグ長から直接計算 */
			$closeTagLen = strlen('{{/each}}');
			$closeStart = $closeEnd - $closeTagLen;
			$body = substr($tpl, $tagEnd, $closeStart - $tagEnd);

			$items = $ctx[$key] ?? [];
			if (!is_array($items)) {
				$replacement = '';
			} else {
				$items = array_values($items);
				$count = count($items);
				$out = '';
				/* C9 fix: セキュリティキー（admin, csrf_token 等）の上書き防止 */
				$protectedKeys = ['admin', 'csrf_token', 'admin_scripts'];
				foreach ($items as $i => $item) {
					$loopCtx = $ctx; /* 親コンテキストをベースに */
					$loopCtx['@index'] = $i;
					$loopCtx['@first'] = ($i === 0);
					$loopCtx['@last']  = ($i === $count - 1);
					if (is_array($item)) {
						/* アイテムのキーをマージするが、保護キーは上書きしない */
						foreach ($item as $ik => $iv) {
							if (!in_array($ik, $protectedKeys, true)) {
								$loopCtx[$ik] = $iv;
							}
						}
					} else {
						/* M13 fix: スカラー値は {{this}} でアクセス可能に */
						$loopCtx['this'] = $item;
					}
					$rendered = self::processEach($body, $loopCtx);
					$rendered = self::processIf($rendered, $loopCtx);
					$rendered = self::processRawVars($rendered, $loopCtx);
					$rendered = self::processVars($rendered, $loopCtx);
					$out .= $rendered;
				}
				$replacement = $out;
			}

			$tpl    = substr($tpl, 0, $tagStart) . $replacement . substr($tpl, $closeEnd);
			$offset = $tagStart + strlen($replacement);
		}
		return $tpl;
	}

	/**
	 * {{#if var}}...{{else}}...{{/if}} を処理
	 * ネスト対応（バランスドマッチングで正しい閉じタグを検出）
	 */
	private static function processIf(string $tpl, array $ctx): string
	{
		$offset = 0;
		while (preg_match('/\{\{#if\s+(!?[\w@]+)\}\}/s', $tpl, $m, PREG_OFFSET_CAPTURE, $offset)) {
			$tagStart = $m[0][1];
			$tagEnd   = $tagStart + strlen($m[0][0]);
			$key      = $m[1][0];

			$closeEnd = self::findClosingTag($tpl, $tagEnd, '#if\s+!?[\w@]+', '/if');
			if ($closeEnd === null) {
				$offset = $tagEnd;
				continue;
			}
			/* C10 fix: strrpos の代わりに閉じタグ長から直接計算 */
			$closeTagLen = strlen('{{/if}}');
			$closeStart = $closeEnd - $closeTagLen;
			$innerContent = substr($tpl, $tagEnd, $closeStart - $tagEnd);

			/* 同じネストレベルの {{else}} を検索 */
			$elsePos = self::findElseTag($innerContent);

			$negate = false;
			if (str_starts_with($key, '!')) {
				$negate = true;
				$key = substr($key, 1);
			}
			$truthy = !empty($ctx[$key]);
			if ($negate) $truthy = !$truthy;

			if ($elsePos !== null) {
				$trueBody  = substr($innerContent, 0, $elsePos);
				$falseBody = substr($innerContent, $elsePos + 8); /* 8 = strlen('{{else}}') */
				$replacement = $truthy ? $trueBody : $falseBody;
			} else {
				$replacement = $truthy ? $innerContent : '';
			}

			$tpl    = substr($tpl, 0, $tagStart) . $replacement . substr($tpl, $closeEnd);
			$offset = $tagStart;
		}
		return $tpl;
	}

	/**
	 * バランスドマッチングで対応する閉じタグの終了位置を返す
	 *
	 * @return int|null 閉じタグの終了位置（見つからない場合は null）
	 */
	private static function findClosingTag(string $tpl, int $startPos, string $openSuffix, string $closeSuffix): ?int
	{
		$depth = 1;
		$pos   = $startPos;
		$pattern = '/\{\{(' . $openSuffix . '|' . preg_quote($closeSuffix, '/') . ')\}\}/s';

		while ($depth > 0 && preg_match($pattern, $tpl, $cm, PREG_OFFSET_CAPTURE, $pos)) {
			$matchedTag = $cm[1][0];
			$matchEnd   = $cm[0][1] + strlen($cm[0][0]);

			if (preg_match('/^' . $openSuffix . '$/', $matchedTag)) {
				$depth++;
			} else {
				$depth--;
			}
			$pos = $matchEnd;
			if ($depth === 0) {
				return $matchEnd;
			}
		}
		return null;
	}

	/**
	 * 同じネストレベル（depth=0）の {{else}} 位置を検索
	 */
	private static function findElseTag(string $content): ?int
	{
		$depth  = 0;
		$pos    = 0;

		while (preg_match('/\{\{(#if\s+!?[\w@]+|else|\/if)\}\}/s', $content, $m, PREG_OFFSET_CAPTURE, $pos)) {
			$tag = $m[1][0];
			$tagPos = $m[0][1];
			$pos = $tagPos + strlen($m[0][0]);

			if (str_starts_with($tag, '#if')) {
				$depth++;
			} elseif ($tag === '/if') {
				$depth--;
			} elseif ($tag === 'else' && $depth === 0) {
				return $tagPos;
			}
		}
		return null;
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
