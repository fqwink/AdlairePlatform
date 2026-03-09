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
		if (!preg_match(self::SLUG_PATTERN, $name)) return false;
		$schema = self::loadSchema();
		if (isset($schema['collections'][$name])) return false;

		$def['directory'] = $def['directory'] ?? $name;
		$schema['collections'][$name] = $def;
		self::saveSchema($schema);

		/* ディレクトリ作成 */
		$dir = content_dir() . '/' . $def['directory'];
		if (!is_dir($dir)) mkdir($dir, 0755, true);
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
	 * @return array<string, array{meta: array, body: string, html: string}>
	 */
	public static function getItems(string $collection): array {
		$def = self::getCollectionDef($collection);
		if ($def === null) return [];

		$dir = content_dir() . '/' . ($def['directory'] ?? $collection);
		if (!is_dir($dir)) return [];

		$items = MarkdownEngine::loadDirectory($dir);

		/* ドラフト除外（公開 API 用） */
		$items = array_filter($items, function(array $item): bool {
			return empty($item['meta']['draft']);
		});

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

		$raw = file_get_contents($path);
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
		if (!preg_match(self::SLUG_PATTERN, $slug)) return false;
		$def = self::getCollectionDef($collection);
		if ($def === null) return false;

		$dir = content_dir() . '/' . ($def['directory'] ?? $collection);
		if (!is_dir($dir)) mkdir($dir, 0755, true);

		$path = $dir . '/' . $slug . '.md';

		/* スラッグ一意性チェック: 新規作成時に既存ファイルがあれば拒否 */
		if ($isNew && file_exists($path)) return false;

		/* スキーマバリデーション */
		$errors = self::validateFields($collection, $meta);
		if (!empty($errors)) return false;

		$content = self::buildMarkdown($meta, $body);
		return file_put_contents($path, $content, LOCK_EX) !== false;
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
		return unlink($path);
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
		if (!is_dir($dir)) mkdir($dir, 0755, true);

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
			file_put_contents($path, $content, LOCK_EX);
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
				if (is_string($v)) return '"' . addslashes($v) . '"';
				return (string)$v;
			}, $value);
			return '[' . implode(', ', $items) . ']';
		}
		/* 特殊文字を含む文字列はクォート */
		if (preg_match('/[:#\[\]{}|>]/', (string)$value) || trim((string)$value) !== (string)$value) {
			return '"' . addslashes((string)$value) . '"';
		}
		return (string)$value;
	}

	/* ══════════════════════════════════════════════
	   POST アクションハンドラ
	   ══════════════════════════════════════════════ */

	/**
	 * ap_action=collection_* のリクエストを処理。
	 * 管理画面からのコレクション操作。
	 */
	public static function handle(): void {
		$action = $_POST['ap_action'] ?? '';
		$valid = [
			'collection_create', 'collection_delete',
			'collection_item_save', 'collection_item_delete',
			'collection_migrate',
		];
		if (!in_array($action, $valid, true)) return;

		if (!AdminEngine::isLoggedIn()) {
			http_response_code(401);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['error' => '未ログイン']);
			exit;
		}
		AdminEngine::verifyCsrf();
		header('Content-Type: application/json; charset=UTF-8');

		match ($action) {
			'collection_create'      => self::handleCreate(),
			'collection_delete'      => self::handleDelete(),
			'collection_item_save'   => self::handleItemSave(),
			'collection_item_delete' => self::handleItemDelete(),
			'collection_migrate'     => self::handleMigrate(),
		};
	}

	private static function handleCreate(): never {
		$name = trim($_POST['name'] ?? '');
		$label = trim($_POST['label'] ?? '') ?: $name;
		if (!preg_match(self::SLUG_PATTERN, $name)) {
			self::jsonError('不正なコレクション名です（英数字・ハイフン・アンダースコアのみ）');
		}
		$ok = self::createCollection($name, [
			'label'     => $label,
			'directory' => $name,
			'format'    => 'markdown',
			'fields'    => [
				'title' => ['type' => 'string', 'required' => true],
			],
		]);
		if (!$ok) self::jsonError('コレクションの作成に失敗しました（既に存在する可能性があります）');

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("コレクション作成: {$name}");
		}
		self::jsonOk(['name' => $name, 'label' => $label]);
	}

	private static function handleDelete(): never {
		$name = trim($_POST['name'] ?? '');
		if (!self::deleteCollection($name)) {
			self::jsonError('コレクションの削除に失敗しました');
		}
		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("コレクション削除: {$name}");
		}
		self::jsonOk(['deleted' => $name]);
	}

	private static function handleItemSave(): never {
		$collection = trim($_POST['collection'] ?? '');
		$slug = trim($_POST['slug'] ?? '');
		$title = trim($_POST['title'] ?? '');
		$body = $_POST['body'] ?? '';
		$isNew = !empty($_POST['is_new']);

		if ($collection === '' || $slug === '') {
			self::jsonError('コレクション名とスラッグは必須です');
		}

		$meta = ['title' => $title ?: $slug];
		/* 追加メタデータ（JSON 形式で渡される場合） */
		if (!empty($_POST['meta'])) {
			$extra = json_decode($_POST['meta'], true);
			if (is_array($extra)) {
				$meta = array_merge($meta, $extra);
			}
		}

		/* バリデーション（エラー詳細を返す） */
		$validationErrors = self::validateFields($collection, $meta);
		if (!empty($validationErrors)) {
			self::jsonError('バリデーションエラー: ' . implode(', ', $validationErrors));
		}

		if (!self::saveItem($collection, $slug, $meta, $body, $isNew)) {
			$msg = $isNew ? 'アイテムの保存に失敗しました（同名のスラッグが既に存在する可能性があります）' : 'アイテムの保存に失敗しました';
			self::jsonError($msg);
		}

		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("コレクションアイテム保存: {$collection}/{$slug}");
		}
		self::jsonOk(['collection' => $collection, 'slug' => $slug]);
	}

	private static function handleItemDelete(): never {
		$collection = trim($_POST['collection'] ?? '');
		$slug = trim($_POST['slug'] ?? '');
		if (!self::deleteItem($collection, $slug)) {
			self::jsonError('アイテムの削除に失敗しました');
		}
		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("コレクションアイテム削除: {$collection}/{$slug}");
		}
		self::jsonOk(['deleted' => "{$collection}/{$slug}"]);
	}

	private static function handleMigrate(): never {
		$result = self::migrateFromPagesJson();
		if (class_exists('AdminEngine') && method_exists('AdminEngine', 'logActivity')) {
			AdminEngine::logActivity("pages.json → コレクション移行: {$result['migrated']}件");
		}
		self::jsonOk($result);
	}

	private static function jsonOk(mixed $data): never {
		echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
		exit;
	}

	private static function jsonError(string $msg, int $status = 400): never {
		http_response_code($status);
		echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
		exit;
	}
}
