<?php
/**
 * CollectionEngine - コレクション（スキーマ）管理エンジン
 *
 * pitcms.net の pitcms.jsonc に相当するコレクション定義機能。
 * content/ ディレクトリ配下の Markdown ファイルをコレクション単位で管理。
 *
 * ディレクトリ構造:
 *   data/content/
 *   ├── ap-collections.json   ← コレクション定義
 *   ├── pages.json            ← レガシー（後方互換）
 *   ├── pages/                ← コレクション: 固定ページ
 *   │   ├── index.md
 *   │   └── about.md
 *   └── posts/                ← コレクション: ブログ記事
 *       └── hello-world.md
 */
class CollectionEngine {
	use EngineTrait;

	/** @var \ACE\Core\CollectionManager|null Ver.1.5 Framework コレクションマネージャ */
	private static ?\ACE\Core\CollectionManager $manager = null;

	/**
	 * Ver.1.5: Framework CollectionManager インスタンスを取得する
	 */
	public static function getManager(): \ACE\Core\CollectionManager {
		if (self::$manager === null) {
			self::$manager = new \ACE\Core\CollectionManager(content_dir());
		}
		return self::$manager;
	}

	private const SCHEMA_FILE    = 'ap-collections.json';
	private const SLUG_PATTERN   = '/^[a-zA-Z0-9_\-]+$/';
	private const ALLOWED_TYPES  = ['string', 'text', 'number', 'boolean', 'date', 'datetime', 'array', 'image'];

	/* ══════════════════════════════════════════════
	   コレクション有効判定
	   ══════════════════════════════════════════════ */

	/**
	 * コレクションモードが有効か判定。
	 * ap-collections.json が存在し、1 つ以上のコレクションが定義されていれば true。
	 */
	public static function isEnabled(): bool {
		$schema = self::loadSchema();
		return !empty($schema['collections']);
	}

	/* ══════════════════════════════════════════════
	   スキーマ管理
	   ══════════════════════════════════════════════ */

	/** コレクション定義を読み込み */
	public static function loadSchema(): array {
		return json_read(self::SCHEMA_FILE, content_dir());
	}

	/** コレクション定義を保存 */
	public static function saveSchema(array $schema): void {
		json_write(self::SCHEMA_FILE, $schema, content_dir());
	}

	/** コレクション定義一覧を取得 */
	public static function listCollections(): array {
		/* Ver.1.6: CacheEngine::remember で N+1 glob() を解消（TTL: 60秒） */
		return CacheEngine::remember('collection_list', 60, function () {
			$schema = self::loadSchema();
			$collections = $schema['collections'] ?? [];
			$result = [];
			foreach ($collections as $name => $def) {
				$dir = content_dir() . '/' . ($def['directory'] ?? $name);
				$count = is_dir($dir) ? count(glob($dir . '/*.md') ?: []) : 0;
				$result[] = [
					'name'      => $name,
					'label'     => $def['label'] ?? $name,
					'directory' => $def['directory'] ?? $name,
					'format'    => $def['format'] ?? 'markdown',
					'count'     => $count,
				];
			}
			return $result;
		});
	}

	/** 特定コレクションの定義を取得 */
	public static function getCollectionDef(string $name): ?array {
		$schema = self::loadSchema();
		return $schema['collections'][$name] ?? null;
	}

	/* ══════════════════════════════════════════════
	   コレクション CRUD
	   ══════════════════════════════════════════════ */

	/**
	 * コレクションを作成。
	 * @param string $name コレクション名（スラッグ）
	 * @param array  $def  定義 {label, fields, sortBy?, sortOrder?}
	 */
	public static function createCollection(string $name, array $def): bool {
		if (!preg_match(self::SLUG_PATTERN, $name)) {
			if (class_exists('DiagnosticEngine')) {
				DiagnosticEngine::log('engine', 'コレクション作成失敗: 不正なスラッグ', ['name' => $name]);
			}
			return false;
		}
		$schema = self::loadSchema();
		if (isset($schema['collections'][$name])) {
			if (class_exists('DiagnosticEngine')) {
				DiagnosticEngine::log('engine', 'コレクション作成失敗: 既に存在', ['name' => $name]);
			}
			return false;
		}

		$def['directory'] = $def['directory'] ?? $name;
		/* R14 fix: directory フィールドもスラグパターンで検証（パストラバーサル防止） */
		if (!preg_match(self::SLUG_PATTERN, $def['directory'])) {
			if (class_exists('DiagnosticEngine')) {
				DiagnosticEngine::log('engine', 'コレクション作成失敗: 不正なディレクトリ名', ['directory' => $def['directory']]);
			}
			return false;
		}
		$schema['collections'][$name] = $def;
		self::saveSchema($schema);

		/* ディレクトリ作成 */
		$dir = content_dir() . '/' . $def['directory'];
		if (!is_dir($dir)) {
			if (!@mkdir($dir, 0755, true) && class_exists('DiagnosticEngine')) {
				DiagnosticEngine::logEnvironmentIssue('コレクションディレクトリ作成失敗', ['collection' => $name, 'error' => error_get_last()['message'] ?? '']);
			}
		}
		return true;
	}

	/** コレクションを削除（ファイルは削除しない） */
	public static function deleteCollection(string $name): bool {
		$schema = self::loadSchema();
		if (!isset($schema['collections'][$name])) return false;
		unset($schema['collections'][$name]);
		self::saveSchema($schema);
		return true;
	}

	/* ══════════════════════════════════════════════
	   アイテム読み込み
	   ══════════════════════════════════════════════ */

	/**
	 * コレクション内の全アイテムを取得。
	 * status フィルタ: draft/scheduled/archived を公開 API から除外。
	 * publishDate が未来日のアイテムも除外。
	 * @return array<string, array{meta: array, body: string, html: string}>
	 */
	public static function getItems(string $collection): array {
		$def = self::getCollectionDef($collection);
		if ($def === null) return [];

		$dir = content_dir() . '/' . ($def['directory'] ?? $collection);
		if (!is_dir($dir)) return [];

		$items = MarkdownEngine::loadDirectory($dir);
		$allItemCount = count($items);

		/* ドラフト除外 + ステータス + 予約公開フィルタ（公開 API 用） */
		$now = time();
		$items = array_filter($items, function(array $item) use ($now): bool {
			if (!empty($item['meta']['draft'])) return false;
			$status = $item['meta']['status'] ?? 'published';
			if ($status === 'draft' || $status === 'archived') return false;
			if ($status === 'scheduled') {
				$publishDate = $item['meta']['publishDate'] ?? '';
				/* publishDate 未設定または不正な場合、scheduled アイテムは非公開 */
				if ($publishDate === '') return false;
				$ts = strtotime($publishDate);
				if ($ts === false || $ts > $now) return false;
			}
			/* published でも publishDate が未来日の場合は除外 */
			$pd = $item['meta']['publishDate'] ?? '';
			if ($pd !== '' && ($ts = strtotime($pd)) !== false && $ts > $now) return false;
			return true;
		});

		/* BUG#8 fix: 診断用に loadDirectory を再呼出しせず、フィルタ前の件数を使用 */
		if (class_exists('DiagnosticEngine') && $allItemCount !== count($items)) {
			DiagnosticEngine::log('debug', 'コレクションフィルタリング', ['collection' => $collection, 'total' => $allItemCount, 'public' => count($items), 'excluded' => $allItemCount - count($items)]);
		}

		/* ソート */
		$sortBy = $def['sortBy'] ?? null;
		$sortOrder = strtolower($def['sortOrder'] ?? 'asc');
		if ($sortBy !== null) {
			uasort($items, function(array $a, array $b) use ($sortBy, $sortOrder): int {
				$va = $a['meta'][$sortBy] ?? '';
				$vb = $b['meta'][$sortBy] ?? '';
				$cmp = is_numeric($va) && is_numeric($vb)
					? ($va <=> $vb)
					: strcmp((string)$va, (string)$vb);
				return $sortOrder === 'desc' ? -$cmp : $cmp;
			});
		}

		return $items;
	}

	/**
	 * コレクション内の全アイテム（ドラフト含む）。管理画面用。
	 */
	public static function getAllItems(string $collection): array {
		$def = self::getCollectionDef($collection);
		if ($def === null) return [];

		$dir = content_dir() . '/' . ($def['directory'] ?? $collection);
		if (!is_dir($dir)) return [];

		return MarkdownEngine::loadDirectory($dir);
	}

	/** 単一アイテムを取得 */
	public static function getItem(string $collection, string $slug): ?array {
		if (!preg_match(self::SLUG_PATTERN, $slug)) return null;
		$def = self::getCollectionDef($collection);
		if ($def === null) return null;

		$dir = content_dir() . '/' . ($def['directory'] ?? $collection);
		$path = $dir . '/' . $slug . '.md';
		if (!file_exists($path)) return null;

		$raw = FileSystem::read($path);
		if ($raw === false) return null;

		$parsed = MarkdownEngine::parseFrontmatter($raw);
		return [
			'slug' => $slug,
			'meta' => $parsed['meta'],
			'body' => $parsed['body'],
			'html' => MarkdownEngine::toHtml($parsed['body']),
		];
	}

	/* ══════════════════════════════════════════════
	   アイテム書き込み
	   ══════════════════════════════════════════════ */

	/**
	 * アイテムを作成・更新。
	 * @param string $collection コレクション名
	 * @param string $slug       アイテムスラッグ
	 * @param array  $meta       フロントマターデータ
	 * @param string $body       Markdown 本文
	 * @param bool   $isNew      新規作成モード（true: 既存ファイルがあればエラー）
	 */
	public static function saveItem(string $collection, string $slug, array $meta, string $body, bool $isNew = false): bool {
		if (!preg_match(self::SLUG_PATTERN, $slug)) {
			if (class_exists('DiagnosticEngine')) {
				DiagnosticEngine::log('engine', 'アイテム保存失敗: 不正なスラッグ', ['collection' => $collection, 'slug' => $slug]);
			}
			return false;
		}
		$def = self::getCollectionDef($collection);
		if ($def === null) return false;

		$dir = content_dir() . '/' . ($def['directory'] ?? $collection);
		if (!is_dir($dir)) {
			if (!@mkdir($dir, 0755, true) && class_exists('DiagnosticEngine')) {
				DiagnosticEngine::logEnvironmentIssue('コレクションアイテムディレクトリ作成失敗', ['collection' => $collection, 'error' => error_get_last()['message'] ?? '']);
			}
		}

		$path = $dir . '/' . $slug . '.md';

		/* スラッグ一意性チェック: 新規作成時に既存ファイルがあれば拒否 */
		if ($isNew && file_exists($path)) return false;

		/* スキーマバリデーション */
		$errors = self::validateFields($collection, $meta);
		if (!empty($errors)) {
			if (class_exists('DiagnosticEngine')) {
				DiagnosticEngine::log('engine', 'アイテム保存失敗: バリデーションエラー', ['collection' => $collection, 'slug' => $slug, 'errors' => $errors]);
			}
			return false;
		}

		$content = self::buildMarkdown($meta, $body);
		return FileSystem::write($path, $content);
	}

	/**
	 * スキーマ定義に基づきフィールド型をバリデーション。
	 * @return string[] エラーメッセージ配列（空なら OK）
	 */
	public static function validateFields(string $collection, array $meta): array {
		$def = self::getCollectionDef($collection);
		if ($def === null) return [];
		$fields = $def['fields'] ?? [];
		if (empty($fields)) return [];

		$errors = [];
		foreach ($fields as $name => $fieldDef) {
			$type = $fieldDef['type'] ?? 'string';
			$required = !empty($fieldDef['required']);
			$value = $meta[$name] ?? null;

			if ($required && ($value === null || $value === '')) {
				$errors[] = "フィールド '{$name}' は必須です";
				continue;
			}
			if ($value === null || $value === '') continue;

			switch ($type) {
				case 'number':
					if (!is_numeric($value)) {
						$errors[] = "フィールド '{$name}' は数値である必要があります";
					}
					break;
				case 'boolean':
					if (!is_bool($value) && $value !== '0' && $value !== '1' && $value !== 'true' && $value !== 'false') {
						$errors[] = "フィールド '{$name}' は真偽値である必要があります";
					}
					break;
				case 'date':
				case 'datetime':
					if (is_string($value) && strtotime($value) === false) {
						$errors[] = "フィールド '{$name}' は有効な日付である必要があります";
					}
					break;
				case 'array':
					if (!is_array($value)) {
						$errors[] = "フィールド '{$name}' は配列である必要があります";
					}
					break;
			}
		}
		return $errors;
	}

	/** アイテムを削除 */
	public static function deleteItem(string $collection, string $slug): bool {
		if (!preg_match(self::SLUG_PATTERN, $slug)) return false;
		$def = self::getCollectionDef($collection);
		if ($def === null) return false;

		$dir = content_dir() . '/' . ($def['directory'] ?? $collection);
		$path = $dir . '/' . $slug . '.md';
		if (!file_exists($path)) return false;
		$result = unlink($path);
		if (!$result && class_exists('DiagnosticEngine')) {
			DiagnosticEngine::log('engine', 'コレクションアイテム削除失敗', ['collection' => $collection, 'slug' => $slug, 'error' => error_get_last()['message'] ?? '']);
		}
		return $result;
	}

	/* ══════════════════════════════════════════════
	   レガシー互換: pages.json → コレクション変換
	   ══════════════════════════════════════════════ */

	/**
	 * 全コレクションのアイテムを pages 形式の連想配列として返す。
	 * StaticEngine の後方互換用。slug → HTML コンテンツ。
	 */
	public static function loadAllAsPages(): array {
		$pages = [];
		$schema = self::loadSchema();
		foreach (($schema['collections'] ?? []) as $name => $def) {
			$items = self::getItems($name);
			foreach ($items as $slug => $item) {
				/* コレクション名が pages の場合はスラッグそのまま、
				   それ以外は collection/slug 形式 */
				$key = ($name === 'pages') ? $slug : $name . '/' . $slug;
				$pages[$key] = $item['html'];
			}
		}
		return $pages;
	}

	/**
	 * pages.json から Markdown コレクションへ移行。
	 * 移行後も pages.json は残す（フォールバック用）。
	 */
	public static function migrateFromPagesJson(): array {
		$pages = json_read('pages.json', content_dir());
		if (empty($pages)) return ['migrated' => 0, 'error' => 'pages.json が空または存在しません'];

		/* pages コレクションを自動作成 */
		$schema = self::loadSchema();
		if (!isset($schema['collections']['pages'])) {
			$schema['collections']['pages'] = [
				'label'     => '固定ページ',
				'directory' => 'pages',
				'format'    => 'markdown',
				'fields'    => [
					'title' => ['type' => 'string', 'required' => true],
					'order' => ['type' => 'number', 'default' => 0],
				],
			];
			self::saveSchema($schema);
		}

		$dir = content_dir() . '/pages';
		FileSystem::ensureDir($dir);

		$count = 0;
		foreach ($pages as $slug => $html) {
			if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
			$path = $dir . '/' . $slug . '.md';
			if (file_exists($path)) continue; /* 既存ファイルは上書きしない */

			$meta = [
				'title' => ucfirst(str_replace('-', ' ', $slug)),
				'order' => $count,
			];
			/* HTML をそのまま本文として保存（Markdown 変換はしない） */
			$content = self::buildMarkdown($meta, $html);
			FileSystem::write($path, $content);
			$count++;
		}

		return ['migrated' => $count, 'total' => count($pages)];
	}

	/* ══════════════════════════════════════════════
	   ユーティリティ
	   ══════════════════════════════════════════════ */

	/** メタデータ + 本文から Markdown ファイルの内容を生成 */
	private static function buildMarkdown(array $meta, string $body): string {
		$lines = ["---"];
		foreach ($meta as $key => $value) {
			$lines[] = $key . ': ' . self::yamlEncode($value);
		}
		$lines[] = "---";
		$lines[] = "";
		$lines[] = $body;
		return implode("\n", $lines) . "\n";
	}

	private static function yamlEncode(mixed $value): string {
		if ($value === null) return '~';
		if (is_bool($value)) return $value ? 'true' : 'false';
		if (is_int($value) || is_float($value)) return (string)$value;
		if (is_array($value)) {
			$items = array_map(function(mixed $v): string {
				if (is_string($v)) return '"' . self::escapeYamlString($v) . '"';
				if (is_array($v)) return '"' . self::escapeYamlString(json_encode($v, JSON_UNESCAPED_UNICODE)) . '"';
				return (string)$v;
			}, $value);
			return '[' . implode(', ', $items) . ']';
		}
		$str = (string)$value;
		/* 特殊文字・改行・YAML区切り文字を含む文字列はクォート */
		if (preg_match('/[:#\[\]{}|>\r\n]/', $str)
			|| str_contains($str, '---')
			|| trim($str) !== $str
			|| $str === '') {
			return '"' . self::escapeYamlString($str) . '"';
		}
		return $str;
	}

	/** YAML ダブルクォート文字列用エスケープ */
	private static function escapeYamlString(string $s): string {
		return str_replace(
			['\\',   '"',   "\n",  "\r",  "\t"],
			['\\\\', '\\"', '\\n', '\\r', '\\t'],
			$s
		);
	}

}
