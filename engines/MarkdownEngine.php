<?php
/**
 * MarkdownEngine - Markdown → HTML 変換エンジン
 *
 * PHP のみで Markdown を HTML に変換するゼロ依存パーサー。
 * GFM (GitHub Flavored Markdown) の主要機能に対応。
 * フロントマター (YAML 形式) のパースにも対応。
 */
class MarkdownEngine {

	/* ══════════════════════════════════════════════
	   フロントマター解析
	   ══════════════════════════════════════════════ */

	/**
	 * フロントマター付き Markdown を分離。
	 * @return array{meta: array, body: string}
	 */
	public static function parseFrontmatter(string $content): array {
		$content = ltrim($content);
		if (!str_starts_with($content, '---')) {
			return ['meta' => [], 'body' => $content];
		}
		$end = strpos($content, "\n---", 3);
		if ($end === false) {
			return ['meta' => [], 'body' => $content];
		}
		$yamlBlock = substr($content, 3, $end - 3);
		$body = ltrim(substr($content, $end + 4));
		$meta = self::parseSimpleYaml($yamlBlock);
		return ['meta' => $meta, 'body' => $body];
	}

	/**
	 * 簡易 YAML パーサー（フロントマター用）
	 * ネスト構造は1階層まで。配列は [item1, item2] 形式。
	 */
	private static function parseSimpleYaml(string $yaml): array {
		$result = [];
		$lines = explode("\n", $yaml);
		foreach ($lines as $line) {
			$line = rtrim($line);
			if ($line === '' || $line[0] === '#') continue;
			$pos = strpos($line, ':');
			if ($pos === false) continue;
			$key = trim(substr($line, 0, $pos));
			$val = trim(substr($line, $pos + 1));
			if ($key === '') continue;
			/* R8 fix: 重複キーは最初の値を優先（後からのステータス上書き防止） */
			if (!array_key_exists($key, $result)) {
				$result[$key] = self::parseYamlValue($val);
			}
		}
		return $result;
	}

	private static function parseYamlValue(string $val): mixed {
		if ($val === '' || $val === '~' || $val === 'null') return null;
		if ($val === 'true') return true;
		if ($val === 'false') return false;
		if (is_numeric($val)) return str_contains($val, '.') ? (float)$val : (int)$val;

		/* [item1, item2] 形式の配列 */
		if (str_starts_with($val, '[') && str_ends_with($val, ']')) {
			$inner = substr($val, 1, -1);
			if (trim($inner) === '') return [];
			return array_map(function(string $v): mixed {
				$v = trim($v);
				/* クォート除去 */
				if ((str_starts_with($v, '"') && str_ends_with($v, '"'))
					|| (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
					return substr($v, 1, -1);
				}
				return self::parseYamlValue($v);
			}, explode(',', $inner));
		}

		/* クォート付き文字列 */
		if ((str_starts_with($val, '"') && str_ends_with($val, '"'))
			|| (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
			return substr($val, 1, -1);
		}
		return $val;
	}

	/* ══════════════════════════════════════════════
	   Markdown → HTML 変換
	   ══════════════════════════════════════════════ */

	/**
	 * Markdown テキストを HTML に変換。
	 * @param string      $markdown    Markdown テキスト
	 * @param string|null $baseDir     画像相対パス解決用のベースディレクトリ
	 * @param bool        $addHeadingIds 見出しに ID 属性を付与するか（TOC 用）
	 */
	public static function toHtml(string $markdown, ?string $baseDir = null, bool $addHeadingIds = false): string {
		$markdown = str_replace("\r\n", "\n", $markdown);
		$markdown = str_replace("\r", "\n", $markdown);

		/* 画像相対パス解決 */
		if ($baseDir !== null) {
			$markdown = preg_replace_callback(
				'/!\[([^\]]*)\]\((?!https?:\/\/|\/|data:)([^)\s]+)((?:\s+"[^"]*")?)\)/',
				function(array $m) use ($baseDir): string {
					$resolved = rtrim($baseDir, '/') . '/' . $m[2];
					return '![' . $m[1] . '](' . $resolved . $m[3] . ')';
				},
				$markdown
			) ?? $markdown;
		}

		/* コードブロック（``` ）を先に退避 */
		$codeBlocks = [];
		$markdown = preg_replace_callback(
			'/^```(\w*)\n(.*?)\n```$/ms',
			function(array $m) use (&$codeBlocks): string {
				$lang = $m[1] ? ' class="language-' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '"' : '';
				$code = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
				$placeholder = "\x00CODE" . count($codeBlocks) . "\x00";
				$codeBlocks[$placeholder] = "<pre><code{$lang}>{$code}</code></pre>";
				return $placeholder;
			},
			$markdown
		) ?? $markdown;

		/* インラインコード（` ）を退避 */
		$inlineCodes = [];
		$markdown = preg_replace_callback(
			'/`([^`\n]+)`/',
			function(array $m) use (&$inlineCodes): string {
				$placeholder = "\x00INLINE" . count($inlineCodes) . "\x00";
				$inlineCodes[$placeholder] = '<code>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
				return $placeholder;
			},
			$markdown
		) ?? $markdown;

		$lines = explode("\n", $markdown);
		$html = [];
		$inList = false;
		$listType = '';
		$inBlockquote = false;
		$inTable = false;
		$tableAlign = [];
		$paragraph = [];

		$flushParagraph = function() use (&$paragraph, &$html): void {
			if ($paragraph) {
				$text = implode("\n", $paragraph);
				$html[] = '<p>' . self::inlineFormat($text) . '</p>';
				$paragraph = [];
			}
		};
		$closeList = function() use (&$inList, &$listType, &$html): void {
			if ($inList) {
				$html[] = $listType === 'ol' ? '</ol>' : '</ul>';
				$inList = false;
			}
		};
		$closeBlockquote = function() use (&$inBlockquote, &$html): void {
			if ($inBlockquote) {
				$html[] = '</blockquote>';
				$inBlockquote = false;
			}
		};
		$closeTable = function() use (&$inTable, &$html): void {
			if ($inTable) {
				$html[] = '</tbody></table>';
				$inTable = false;
			}
		};

		for ($i = 0; $i < count($lines); $i++) {
			$line = $lines[$i];
			$trimmed = rtrim($line);

			/* コードブロックプレースホルダー */
			if (str_starts_with($trimmed, "\x00CODE")) {
				$flushParagraph();
				$closeList();
				$closeBlockquote();
				$closeTable();
				$html[] = $trimmed;
				continue;
			}

			/* 空行 */
			if ($trimmed === '') {
				$flushParagraph();
				$closeList();
				$closeBlockquote();
				$closeTable();
				continue;
			}

			/* 水平線 */
			if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trimmed)) {
				$flushParagraph();
				$closeList();
				$closeBlockquote();
				$closeTable();
				$html[] = '<hr>';
				continue;
			}

			/* 見出し */
			if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
				$flushParagraph();
				$closeList();
				$closeBlockquote();
				$closeTable();
				$level = strlen($m[1]);
				$rawText = rtrim($m[2], ' #');
				$text = self::inlineFormat($rawText);
				if ($addHeadingIds) {
					$id = self::slugify($rawText);
					$html[] = "<h{$level} id=\"{$id}\">{$text}</h{$level}>";
				} else {
					$html[] = "<h{$level}>{$text}</h{$level}>";
				}
				continue;
			}

			/* ブロック引用 */
			if (str_starts_with($trimmed, '> ') || $trimmed === '>') {
				$flushParagraph();
				$closeList();
				$closeTable();
				if (!$inBlockquote) {
					$html[] = '<blockquote>';
					$inBlockquote = true;
				}
				$content = ltrim(substr($trimmed, 1));
				$html[] = '<p>' . self::inlineFormat($content) . '</p>';
				continue;
			}
			if ($inBlockquote && $trimmed !== '') {
				$html[] = '<p>' . self::inlineFormat($trimmed) . '</p>';
				continue;
			}

			/* テーブル */
			if (str_contains($trimmed, '|')) {
				$cells = self::parseTableRow($trimmed);
				if ($cells !== null) {
					/* セパレータ行チェック */
					if (!$inTable && isset($lines[$i + 1])) {
						$nextCells = self::parseTableRow(rtrim($lines[$i + 1]));
						if ($nextCells !== null && self::isTableSeparator($nextCells)) {
							$flushParagraph();
							$closeList();
							$closeBlockquote();
							$tableAlign = self::parseTableAlign($nextCells);
							$html[] = '<table><thead><tr>';
							foreach ($cells as $j => $cell) {
								$align = $tableAlign[$j] ?? '';
								$style = $align ? " style=\"text-align:{$align}\"" : '';
								$html[] = "<th{$style}>" . self::inlineFormat(trim($cell)) . '</th>';
							}
							$html[] = '</tr></thead><tbody>';
							$inTable = true;
							$i++; /* セパレータ行をスキップ */
							continue;
						}
					}
					if ($inTable) {
						$html[] = '<tr>';
						foreach ($cells as $j => $cell) {
							$align = $tableAlign[$j] ?? '';
							$style = $align ? " style=\"text-align:{$align}\"" : '';
							$html[] = "<td{$style}>" . self::inlineFormat(trim($cell)) . '</td>';
						}
						$html[] = '</tr>';
						continue;
					}
				}
			}

			/* 順序なしリスト（タスクリスト対応） */
			if (preg_match('/^[\-\*\+]\s+(.+)$/', $trimmed, $m)) {
				$flushParagraph();
				$closeBlockquote();
				$closeTable();
				if (!$inList || $listType !== 'ul') {
					$closeList();
					$html[] = '<ul>';
					$inList = true;
					$listType = 'ul';
				}
				$itemText = $m[1];
				/* GFM タスクリスト: - [ ] / - [x] */
				if (preg_match('/^\[([ xX])\]\s*(.*)$/', $itemText, $tm)) {
					$checked = (strtolower($tm[1]) === 'x') ? ' checked' : '';
					$html[] = '<li class="task-list-item"><input type="checkbox" disabled' . $checked . '> ' . self::inlineFormat($tm[2]) . '</li>';
				} else {
					$html[] = '<li>' . self::inlineFormat($itemText) . '</li>';
				}
				continue;
			}

			/* 順序付きリスト */
			if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
				$flushParagraph();
				$closeBlockquote();
				$closeTable();
				if (!$inList || $listType !== 'ol') {
					$closeList();
					$html[] = '<ol>';
					$inList = true;
					$listType = 'ol';
				}
				$html[] = '<li>' . self::inlineFormat($m[1]) . '</li>';
				continue;
			}

			/* 通常テキスト（段落蓄積） */
			$closeList();
			$closeBlockquote();
			$closeTable();
			$paragraph[] = $trimmed;
		}

		$flushParagraph();
		$closeList();
		$closeBlockquote();
		$closeTable();

		$result = implode("\n", $html);

		/* コードブロック・インラインコードを復元 */
		$result = strtr($result, $codeBlocks);
		$result = strtr($result, $inlineCodes);

		return $result;
	}

	/* ══════════════════════════════════════════════
	   インライン書式
	   ══════════════════════════════════════════════ */

	private static function inlineFormat(string $text): string {
		$esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

		/* 画像・リンクを先に抽出しプレースホルダーに退避（エスケープ前に処理） */
		$inlineRefs = [];

		/* 画像 */
		$text = preg_replace_callback(
			'/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/',
			function(array $m) use (&$inlineRefs, $esc): string {
				$url = self::sanitizeUrl($m[2]);
				if ($url === null) return $esc($m[0]);
				$alt   = $esc($m[1]);
				$title = isset($m[3]) && $m[3] !== '' ? ' title="' . $esc($m[3]) . '"' : '';
				$key = "\x00IMG" . count($inlineRefs) . "\x00";
				$inlineRefs[$key] = '<img src="' . $esc($url) . '" alt="' . $alt . '"' . $title . '>';
				return $key;
			},
			$text
		) ?? $text;

		/* リンク */
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/',
			function(array $m) use (&$inlineRefs, $esc): string {
				$url = self::sanitizeUrl($m[2]);
				if ($url === null) return $esc($m[0]);
				$linkText = $m[1]; /* リンクテキストは後でエスケープされる */
				$title = isset($m[3]) && $m[3] !== '' ? ' title="' . $esc($m[3]) . '"' : '';
				$key = "\x00LINK" . count($inlineRefs) . "\x00";
				$inlineRefs[$key] = '<a href="' . $esc($url) . '"' . $title . '>' . $esc($linkText) . '</a>';
				return $key;
			},
			$text
		) ?? $text;

		/* 残りのテキストを HTML エスケープ（XSS 防止） */
		$text = $esc($text);

		/* 太字 + 斜体 */
		$text = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $text) ?? $text;
		$text = preg_replace('/___(.+?)___/', '<strong><em>$1</em></strong>', $text) ?? $text;

		/* 太字 */
		$text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text) ?? $text;
		$text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text) ?? $text;

		/* 斜体 */
		$text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text) ?? $text;
		$text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text) ?? $text;

		/* 打ち消し線 (GFM) */
		$text = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text) ?? $text;

		/* 改行（行末スペース2つ） */
		$text = preg_replace('/  $/', '<br>', $text) ?? $text;

		/* 画像・リンクプレースホルダーを復元 */
		$text = strtr($text, $inlineRefs);

		return $text;
	}

	/**
	 * URL をサニタイズ。安全なスキームのみ許可。
	 * @return string|null サニタイズ済み URL。危険な場合は null
	 */
	private static function sanitizeUrl(string $url): ?string {
		$url = trim($url);
		/* R4 fix: 制御文字・空白を除去（スキームチェックバイパス防止） */
		$url = preg_replace('/[\x00-\x1f\x7f\s]/', '', $url);
		/* 許可リスト方式: 安全なスキーム / 相対パスのみ許可 */
		if (preg_match('#^https?://#i', $url) || preg_match('#^(mailto:|/|#|\?)#', $url)) {
			return $url;
		}
		/* 相対パス（スキームなし・コロンなし） */
		if (!str_contains($url, ':')) {
			return $url;
		}
		/* それ以外（javascript:, data:, vbscript: 等）はブロック */
		return null;
	}

	/* ══════════════════════════════════════════════
	   テーブルヘルパー
	   ══════════════════════════════════════════════ */

	private static function parseTableRow(string $line): ?array {
		$line = trim($line);
		if (!str_contains($line, '|')) return null;
		if (str_starts_with($line, '|')) $line = substr($line, 1);
		if (str_ends_with($line, '|')) $line = substr($line, 0, -1);
		return explode('|', $line);
	}

	private static function isTableSeparator(array $cells): bool {
		foreach ($cells as $cell) {
			if (!preg_match('/^\s*:?-+:?\s*$/', trim($cell))) return false;
		}
		return true;
	}

	private static function parseTableAlign(array $cells): array {
		$align = [];
		foreach ($cells as $cell) {
			$cell = trim($cell);
			$left = str_starts_with($cell, ':');
			$right = str_ends_with($cell, ':');
			if ($left && $right) $align[] = 'center';
			elseif ($right) $align[] = 'right';
			elseif ($left) $align[] = 'left';
			else $align[] = '';
		}
		return $align;
	}

	/* ══════════════════════════════════════════════
	   目次（TOC）生成
	   ══════════════════════════════════════════════ */

	/**
	 * Markdown から見出しを抽出して目次配列を返す。
	 * @return array{level: int, text: string, id: string}[]
	 */
	public static function generateToc(string $markdown): array {
		$headings = [];
		if (preg_match_all('/^(#{1,6})\s+(.+)$/m', $markdown, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$level = strlen($match[1]);
				$text  = rtrim($match[2], ' #');
				$id    = self::slugify($text);
				$headings[] = ['level' => $level, 'text' => $text, 'id' => $id];
			}
		}
		return $headings;
	}

	/**
	 * 目次配列を HTML リストとして描画。
	 */
	public static function renderToc(array $toc): string {
		if (empty($toc)) return '';
		$html = '<nav class="ap-toc"><ul>';
		foreach ($toc as $item) {
			$indent = str_repeat('  ', $item['level'] - 1);
			$html .= $indent . '<li><a href="#' . htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') . '">'
				. htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') . '</a></li>';
		}
		$html .= '</ul></nav>';
		return $html;
	}

	/**
	 * テキストを URL セーフなスラッグに変換。
	 */
	private static function slugify(string $text): string {
		$slug = mb_strtolower(strip_tags($text), 'UTF-8');
		$slug = preg_replace('/[^a-z0-9\s\-_]/', '', $slug) ?? '';
		$slug = preg_replace('/[\s]+/', '-', trim($slug)) ?? '';
		$slug = preg_replace('/-+/', '-', $slug) ?? '';
		return $slug ?: 'heading';
	}

	/* ══════════════════════════════════════════════
	   コレクション読み込みヘルパー
	   ══════════════════════════════════════════════ */

	/**
	 * ディレクトリ内の全 .md ファイルを読み込み。
	 * @return array<string, array{meta: array, body: string, html: string}>
	 */
	public static function loadDirectory(string $dir): array {
		$items = [];
		$files = glob($dir . '/*.md') ?: [];
		foreach ($files as $file) {
			$slug = basename($file, '.md');
			$raw = file_get_contents($file);
			if ($raw === false) continue;
			$parsed = self::parseFrontmatter($raw);
			$items[$slug] = [
				'meta' => $parsed['meta'],
				'body' => $parsed['body'],
				'html' => self::toHtml($parsed['body']),
			];
		}
		return $items;
	}
}
