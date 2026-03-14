<?php
/**
 * Adlaire Content Engine (ACE) - Core Module
 *
 * ACE = Adlaire Content Engine
 *
 * コレクション管理・コンテンツCRUD・メタデータ処理・バリデーションを提供する
 * 再利用可能なフレームワークモジュール。Adlaire Platform の CollectionEngine から
 * 汎用化・抽出された独立コンポーネント。
 *
 * @package ACE
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace ACE\Core;

// ============================================================================
// CollectionManager - コレクション／スキーマ管理
// ============================================================================

/**
 * コレクション定義の作成・削除・一覧・スキーマ取得を管理する。
 *
 * コレクションはコンテンツの論理的なグループ（例: posts, pages, products）であり、
 * JSON ファイルにスキーマ定義として保存される。
 */
class CollectionManager
{
    /** @var string データディレクトリのパス */
    private string $dataDir;

    /** @var string スキーマ定義ファイル名 */
    private const SCHEMA_FILE = 'collections.json';

    /** @var string スラッグのバリデーションパターン */
    private const SLUG_PATTERN = '/^[a-zA-Z0-9_\-]+$/';

    /** @var string[] 許可されるフィールド型 */
    private const ALLOWED_TYPES = [
        'string', 'text', 'number', 'boolean',
        'date', 'datetime', 'array', 'image',
    ];

    /**
     * コンストラクタ
     *
     * @param string $dataDir コレクション定義を保存するディレクトリパス
     */
    public function __construct(string $dataDir)
    {
        $this->dataDir = rtrim($dataDir, '/');

        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    /**
     * 新しいコレクションを作成する。
     *
     * @param string $name   コレクション名（スラッグ形式）
     * @param string $label  表示ラベル
     * @param array  $fields フィールド定義の連想配列 [name => [type, required, default]]
     * @return bool 作成成功時 true
     */
    public function create(string $name, string $label, array $fields = []): bool
    {
        if (!preg_match(self::SLUG_PATTERN, $name)) {
            return false;
        }

        $schema = $this->loadSchema();

        if (isset($schema['collections'][$name])) {
            return false;
        }

        /* フィールド型のバリデーション */
        foreach ($fields as $fieldName => $fieldDef) {
            $type = $fieldDef['type'] ?? 'string';
            if (!in_array($type, self::ALLOWED_TYPES, true)) {
                return false;
            }
        }

        $schema['collections'][$name] = [
            'label'      => $label ?: $name,
            'directory'  => $name,
            'format'     => 'markdown',
            'fields'     => $fields,
            'created_at' => date('c'),
        ];

        $this->saveSchema($schema);

        /* コレクションディレクトリを作成 */
        $dir = $this->dataDir . '/' . $name;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return true;
    }

    /**
     * コレクションを削除する（コンテンツファイルは削除しない）。
     *
     * @param string $name コレクション名
     * @return bool 削除成功時 true
     */
    public function delete(string $name): bool
    {
        $schema = $this->loadSchema();

        if (!isset($schema['collections'][$name])) {
            return false;
        }

        unset($schema['collections'][$name]);
        $this->saveSchema($schema);

        return true;
    }

    /**
     * 全コレクションの一覧を取得する。
     *
     * @return array<int, array{name: string, label: string, directory: string, format: string, count: int}>
     */
    public function listCollections(): array
    {
        $schema = $this->loadSchema();
        $collections = $schema['collections'] ?? [];
        $result = [];

        foreach ($collections as $name => $def) {
            $dir = $this->dataDir . '/' . ($def['directory'] ?? $name);
            $count = 0;
            if (is_dir($dir)) {
                $files = glob($dir . '/*.md') ?: [];
                $count = count($files);
            }

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

    /**
     * 指定コレクションのスキーマ定義を取得する。
     *
     * @param string $name コレクション名
     * @return array スキーマ定義（存在しない場合は空配列）
     */
    public function getSchema(string $name): array
    {
        $schema = $this->loadSchema();
        return $schema['collections'][$name] ?? [];
    }

    /**
     * コレクションモードが有効か判定する。
     * 1つ以上のコレクションが定義されていれば true。
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        $schema = $this->loadSchema();
        return !empty($schema['collections']);
    }

    /**
     * スキーマ定義ファイルを読み込む。
     *
     * @return array
     */
    private function loadSchema(): array
    {
        $path = $this->dataDir . '/' . self::SCHEMA_FILE;

        if (!file_exists($path)) {
            return ['collections' => []];
        }

        $content = file_get_contents($path);
        $data = json_decode($content ?: '', true);

        return is_array($data) ? $data : ['collections' => []];
    }

    /**
     * スキーマ定義ファイルを保存する。
     *
     * @param array $schema スキーマデータ
     */
    private function saveSchema(array $schema): void
    {
        $path = $this->dataDir . '/' . self::SCHEMA_FILE;
        file_put_contents(
            $path,
            json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}

// ============================================================================
// ContentManager - コンテンツ CRUD 操作
// ============================================================================

/**
 * Markdown ベースのコンテンツアイテムに対する CRUD 操作を提供する。
 *
 * フロントマター付き Markdown ファイルとしてコンテンツを管理し、
 * 一覧・検索・ソート・ページネーション機能を備える。
 */
class ContentManager
{
    /** @var string コンテンツディレクトリのパス */
    private string $contentDir;

    /** @var string スラッグのバリデーションパターン */
    private const SLUG_PATTERN = '/^[a-zA-Z0-9_\-]+$/';

    /**
     * コンストラクタ
     *
     * @param string $contentDir コンテンツファイルを格納するディレクトリパス
     */
    public function __construct(string $contentDir)
    {
        $this->contentDir = rtrim($contentDir, '/');

        if (!is_dir($this->contentDir)) {
            mkdir($this->contentDir, 0755, true);
        }
    }

    /**
     * 指定コレクション内の単一アイテムを取得する。
     *
     * @param string $collection コレクション名
     * @param string $slug       アイテムスラッグ
     * @return array|null アイテムデータ [slug, meta, body] または null
     */
    public function getItem(string $collection, string $slug): array|null
    {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return null;
        }

        $path = $this->resolvePath($collection, $slug);

        if (!file_exists($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $metaManager = new MetaManager();
        $parsed = $metaManager->extractMeta($raw);

        return [
            'slug' => $slug,
            'meta' => $parsed['meta'],
            'body' => $parsed['body'],
        ];
    }

    /**
     * アイテムを保存する（作成または更新）。
     *
     * @param string $collection コレクション名
     * @param string $slug       アイテムスラッグ
     * @param array  $data       保存データ [meta => array, body => string]
     * @return bool 保存成功時 true
     */
    public function saveItem(string $collection, string $slug, array $data): bool
    {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return false;
        }
        if (!preg_match(self::SLUG_PATTERN, $collection)) {
            return false;
        }

        $dir = $this->contentDir . '/' . $collection;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $meta = $data['meta'] ?? [];
        $body = $data['body'] ?? '';

        $metaManager = new MetaManager();
        $content = $metaManager->buildMeta($meta) . "\n" . $body . "\n";

        $path = $this->resolvePath($collection, $slug);

        return file_put_contents($path, $content) !== false;
    }

    /**
     * アイテムを削除する。
     *
     * @param string $collection コレクション名
     * @param string $slug       アイテムスラッグ
     * @return bool 削除成功時 true
     */
    public function deleteItem(string $collection, string $slug): bool
    {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return false;
        }

        $path = $this->resolvePath($collection, $slug);

        if (!file_exists($path)) {
            return false;
        }

        return unlink($path);
    }

    /**
     * コレクション内のアイテム一覧を取得する。
     *
     * @param string $collection コレクション名
     * @param array  $options    オプション [sort => string, order => asc|desc, limit => int, offset => int, filter => callable]
     * @return array アイテムの配列
     */
    public function listItems(string $collection, array $options = []): array
    {
        $dir = $this->contentDir . '/' . $collection;

        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.md') ?: [];
        $metaManager = new MetaManager();
        $items = [];

        foreach ($files as $file) {
            $slug = basename($file, '.md');
            $raw = file_get_contents($file);
            if ($raw === false) {
                continue;
            }

            $parsed = $metaManager->extractMeta($raw);
            $item = [
                'slug' => $slug,
                'meta' => $parsed['meta'],
                'body' => $parsed['body'],
            ];

            /* フィルタ適用 */
            if (isset($options['filter']) && is_callable($options['filter'])) {
                if (!($options['filter'])($item)) {
                    continue;
                }
            }

            $items[] = $item;
        }

        /* ソート */
        $sortBy = $options['sort'] ?? null;
        $sortOrder = strtolower($options['order'] ?? 'asc');

        if ($sortBy !== null) {
            usort($items, function (array $a, array $b) use ($sortBy, $sortOrder): int {
                $va = $a['meta'][$sortBy] ?? '';
                $vb = $b['meta'][$sortBy] ?? '';

                $cmp = is_numeric($va) && is_numeric($vb)
                    ? ($va <=> $vb)
                    : strcmp((string)$va, (string)$vb);

                return $sortOrder === 'desc' ? -$cmp : $cmp;
            });
        }

        /* ページネーション */
        $offset = (int)($options['offset'] ?? 0);
        $limit = isset($options['limit']) ? (int)$options['limit'] : null;

        if ($offset > 0 || $limit !== null) {
            $items = array_slice($items, $offset, $limit);
        }

        return $items;
    }

    /**
     * コンテンツ全文検索を実行する。
     *
     * @param string   $query       検索クエリ
     * @param string[] $collections 検索対象コレクション名の配列（空の場合は全コレクション）
     * @return array 検索結果の配列
     */
    public function search(string $query, array $collections = []): array
    {
        $query = mb_strtolower(trim($query), 'UTF-8');
        if ($query === '') {
            return [];
        }

        /* 対象コレクションディレクトリを収集 */
        if (empty($collections)) {
            $dirs = glob($this->contentDir . '/*', GLOB_ONLYDIR) ?: [];
            $collections = array_map('basename', $dirs);
        }

        $metaManager = new MetaManager();
        $results = [];

        foreach ($collections as $collection) {
            $dir = $this->contentDir . '/' . $collection;
            if (!is_dir($dir)) {
                continue;
            }

            $files = glob($dir . '/*.md') ?: [];
            foreach ($files as $file) {
                $raw = file_get_contents($file);
                if ($raw === false) {
                    continue;
                }

                $parsed = $metaManager->extractMeta($raw);
                $title = mb_strtolower($parsed['meta']['title'] ?? '', 'UTF-8');
                $body = mb_strtolower($parsed['body'], 'UTF-8');

                $pos = mb_strpos($title, $query, 0, 'UTF-8');
                if ($pos === false) {
                    $pos = mb_strpos($body, $query, 0, 'UTF-8');
                }
                if ($pos === false) {
                    continue;
                }

                /* プレビュー文字列を生成 */
                $start = max(0, $pos - 30);
                $preview = mb_substr($body, $start, 100, 'UTF-8');
                if ($start > 0) {
                    $preview = '...' . $preview;
                }
                if ($start + 100 < mb_strlen($body, 'UTF-8')) {
                    $preview .= '...';
                }

                $results[] = [
                    'collection' => $collection,
                    'slug'       => basename($file, '.md'),
                    'title'      => $parsed['meta']['title'] ?? basename($file, '.md'),
                    'preview'    => $preview,
                ];
            }
        }

        return $results;
    }

    /**
     * コレクションとスラッグからファイルパスを解決する。
     *
     * @param string $collection コレクション名
     * @param string $slug       アイテムスラッグ
     * @return string ファイルパス
     */
    private function resolvePath(string $collection, string $slug): string
    {
        return $this->contentDir . '/' . $collection . '/' . $slug . '.md';
    }
}

// ============================================================================
// MetaManager - メタデータ／フロントマター処理
// ============================================================================

/**
 * YAML フロントマターの解析・生成・マージ・バリデーションを提供する。
 *
 * Markdown ファイルの先頭にある `---` で囲まれた YAML ブロックを
 * メタデータとして扱う。
 */
class MetaManager
{
    /**
     * コンテンツ文字列から YAML フロントマターを抽出する。
     *
     * @param string $content フロントマター付きコンテンツ
     * @return array{meta: array, body: string} メタデータと本文
     */
    public function extractMeta(string $content): array
    {
        $meta = [];
        $body = $content;

        /* フロントマター区切り `---` を検出 */
        if (str_starts_with(trim($content), '---')) {
            $parts = preg_split('/^---\s*$/m', $content, 3);

            if ($parts !== false && count($parts) >= 3) {
                $yamlBlock = trim($parts[1]);
                $body = trim($parts[2]);
                $meta = $this->parseYaml($yamlBlock);
            }
        }

        return [
            'meta' => $meta,
            'body' => $body,
        ];
    }

    /**
     * メタデータ配列から YAML フロントマター文字列を生成する。
     *
     * @param array $meta メタデータの連想配列
     * @return string フロントマター文字列（`---` 区切り付き）
     */
    public function buildMeta(array $meta): string
    {
        $lines = ['---'];

        foreach ($meta as $key => $value) {
            $lines[] = $key . ': ' . $this->encodeYamlValue($value);
        }

        $lines[] = '---';

        return implode("\n", $lines);
    }

    /**
     * ベースメタデータにオーバーライドをマージする。
     *
     * オーバーライド側の値が優先され、ベース側にのみ存在するキーは保持される。
     *
     * @param array $base     ベースメタデータ
     * @param array $override オーバーライドメタデータ
     * @return array マージ結果
     */
    public function mergeMeta(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = $this->mergeMeta($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * メタデータをスキーマ定義に基づきバリデーションする。
     *
     * @param array $meta   バリデーション対象のメタデータ
     * @param array $schema スキーマ定義 [fieldName => [type, required, ...]]
     * @return array バリデーションエラーメッセージの配列（空なら正常）
     */
    public function validateMeta(array $meta, array $schema): array
    {
        $errors = [];

        foreach ($schema as $fieldName => $fieldDef) {
            $type = $fieldDef['type'] ?? 'string';
            $required = !empty($fieldDef['required']);
            $value = $meta[$fieldName] ?? null;

            /* 必須チェック */
            if ($required && ($value === null || $value === '')) {
                $errors[] = "フィールド '{$fieldName}' は必須です";
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            /* 型チェック */
            match ($type) {
                'number' => !is_numeric($value)
                    ? $errors[] = "フィールド '{$fieldName}' は数値である必要があります"
                    : null,
                'boolean' => (!is_bool($value) && !in_array($value, ['0', '1', 'true', 'false'], true))
                    ? $errors[] = "フィールド '{$fieldName}' は真偽値である必要があります"
                    : null,
                'date', 'datetime' => (is_string($value) && strtotime($value) === false)
                    ? $errors[] = "フィールド '{$fieldName}' は有効な日付である必要があります"
                    : null,
                'array' => !is_array($value)
                    ? $errors[] = "フィールド '{$fieldName}' は配列である必要があります"
                    : null,
                default => null,
            };
        }

        return $errors;
    }

    /**
     * 簡易 YAML パーサー（`key: value` 形式に対応）。
     *
     * @param string $yaml YAML 文字列
     * @return array パース結果の連想配列
     */
    private function parseYaml(string $yaml): array
    {
        $result = [];
        $lines = explode("\n", $yaml);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $colonPos));
            $value = trim(substr($line, $colonPos + 1));

            $result[$key] = $this->decodeYamlValue($value);
        }

        return $result;
    }

    /**
     * YAML 値文字列をPHP の型に変換する。
     *
     * @param string $value YAML 値文字列
     * @return mixed 変換後の値
     */
    private function decodeYamlValue(string $value): mixed
    {
        /* 空値 */
        if ($value === '' || $value === '~' || $value === 'null') {
            return null;
        }

        /* 真偽値 */
        if ($value === 'true') return true;
        if ($value === 'false') return false;

        /* クォートされた文字列 */
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $unquoted = substr($value, 1, -1);
            return str_replace(
                ['\\n', '\\r', '\\t', '\\"', '\\\\'],
                ["\n", "\r", "\t", '"', '\\'],
                $unquoted
            );
        }

        /* 配列（[a, b, c] 形式） */
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $inner = substr($value, 1, -1);
            $items = array_map('trim', explode(',', $inner));
            return array_map(fn(string $item) => $this->decodeYamlValue($item), $items);
        }

        /* 数値 */
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }

        return $value;
    }

    /**
     * PHP の値を YAML 文字列にエンコードする。
     *
     * @param mixed $value エンコード対象の値
     * @return string YAML 文字列表現
     */
    private function encodeYamlValue(mixed $value): string
    {
        if ($value === null) return '~';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_int($value) || is_float($value)) return (string)$value;

        if (is_array($value)) {
            $items = array_map(function (mixed $v): string {
                if (is_string($v)) {
                    return '"' . $this->escapeYamlString($v) . '"';
                }
                if (is_array($v)) {
                    return '"' . $this->escapeYamlString(
                        json_encode($v, JSON_UNESCAPED_UNICODE)
                    ) . '"';
                }
                return (string)$v;
            }, $value);
            return '[' . implode(', ', $items) . ']';
        }

        $str = (string)$value;

        /* 特殊文字を含む場合はクォートする */
        if (preg_match('/[:#\[\]{}|>\r\n]/', $str)
            || str_contains($str, '---')
            || trim($str) !== $str
            || $str === '') {
            return '"' . $this->escapeYamlString($str) . '"';
        }

        return $str;
    }

    /**
     * YAML ダブルクォート文字列用のエスケープ処理。
     *
     * @param string $s 対象文字列
     * @return string エスケープ済み文字列
     */
    private function escapeYamlString(string $s): string
    {
        return str_replace(
            ['\\',   '"',   "\n",  "\r",  "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $s
        );
    }
}

// ============================================================================
// ContentValidator - コンテンツバリデーション
// ============================================================================

/**
 * コンテンツデータおよびスラッグのバリデーション機能を提供する。
 *
 * フィールド定義に基づく型チェック、必須チェック、スラッグ形式の検証と
 * サニタイズを行う。
 */
class ContentValidator
{
    /** @var string スラッグのバリデーションパターン */
    private const SLUG_PATTERN = '/^[a-zA-Z0-9_\-]+$/';

    /**
     * データをフィールド定義に基づきバリデーションする。
     *
     * @param array $data   バリデーション対象データ
     * @param array $fields フィールド定義 [fieldName => [type, required, min, max, ...]]
     * @return array エラーメッセージの配列（空なら正常）
     */
    public function validate(array $data, array $fields): array
    {
        $errors = [];

        foreach ($fields as $fieldName => $fieldDef) {
            $type = $fieldDef['type'] ?? 'string';
            $required = !empty($fieldDef['required']);
            $value = $data[$fieldName] ?? null;

            /* 必須チェック */
            if ($required && ($value === null || $value === '')) {
                $errors[] = "フィールド '{$fieldName}' は必須です";
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            /* 型バリデーション */
            match ($type) {
                'string', 'text' => $this->validateString(
                    $fieldName, $value, $fieldDef, $errors
                ),
                'number' => $this->validateNumber(
                    $fieldName, $value, $fieldDef, $errors
                ),
                'boolean' => $this->validateBoolean(
                    $fieldName, $value, $errors
                ),
                'date', 'datetime' => $this->validateDate(
                    $fieldName, $value, $errors
                ),
                'array' => $this->validateArray(
                    $fieldName, $value, $errors
                ),
                'image' => $this->validateImage(
                    $fieldName, $value, $errors
                ),
                default => null,
            };
        }

        return $errors;
    }

    /**
     * スラッグが有効な形式かを検証する。
     *
     * @param string $slug 検証対象のスラッグ
     * @return bool 有効な場合 true
     */
    public function validateSlug(string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        return (bool)preg_match(self::SLUG_PATTERN, $slug);
    }

    /**
     * 入力文字列をスラッグ形式にサニタイズする。
     *
     * @param string $input 変換対象の文字列
     * @return string スラッグ形式の文字列
     */
    public function sanitizeSlug(string $input): string
    {
        /* 小文字に変換 */
        $slug = mb_strtolower($input, 'UTF-8');

        /* 英数字以外をハイフンに変換 */
        $slug = preg_replace('/[^a-z0-9\s\-_]/', '', $slug) ?? '';

        /* 空白をハイフンに変換 */
        $slug = preg_replace('/[\s]+/', '-', $slug) ?? '';

        /* 連続するハイフンを1つに */
        $slug = preg_replace('/-+/', '-', $slug) ?? '';

        /* 先頭と末尾のハイフンを除去 */
        return trim($slug, '-_');
    }

    /**
     * 文字列型フィールドのバリデーション。
     *
     * @param string $name     フィールド名
     * @param mixed  $value    値
     * @param array  $fieldDef フィールド定義
     * @param array  &$errors  エラー配列（参照渡し）
     */
    private function validateString(
        string $name,
        mixed $value,
        array $fieldDef,
        array &$errors
    ): void {
        if (!is_string($value)) {
            $errors[] = "フィールド '{$name}' は文字列である必要があります";
            return;
        }

        $min = $fieldDef['min'] ?? null;
        $max = $fieldDef['max'] ?? null;

        if ($min !== null && mb_strlen($value, 'UTF-8') < (int)$min) {
            $errors[] = "フィールド '{$name}' は{$min}文字以上である必要があります";
        }
        if ($max !== null && mb_strlen($value, 'UTF-8') > (int)$max) {
            $errors[] = "フィールド '{$name}' は{$max}文字以下である必要があります";
        }
    }

    /**
     * 数値型フィールドのバリデーション。
     *
     * @param string $name     フィールド名
     * @param mixed  $value    値
     * @param array  $fieldDef フィールド定義
     * @param array  &$errors  エラー配列（参照渡し）
     */
    private function validateNumber(
        string $name,
        mixed $value,
        array $fieldDef,
        array &$errors
    ): void {
        if (!is_numeric($value)) {
            $errors[] = "フィールド '{$name}' は数値である必要があります";
            return;
        }

        $min = $fieldDef['min'] ?? null;
        $max = $fieldDef['max'] ?? null;

        if ($min !== null && $value < $min) {
            $errors[] = "フィールド '{$name}' は{$min}以上である必要があります";
        }
        if ($max !== null && $value > $max) {
            $errors[] = "フィールド '{$name}' は{$max}以下である必要があります";
        }
    }

    /**
     * 真偽値型フィールドのバリデーション。
     *
     * @param string $name    フィールド名
     * @param mixed  $value   値
     * @param array  &$errors エラー配列（参照渡し）
     */
    private function validateBoolean(string $name, mixed $value, array &$errors): void
    {
        if (!is_bool($value) && !in_array($value, ['0', '1', 'true', 'false', 0, 1], true)) {
            $errors[] = "フィールド '{$name}' は真偽値である必要があります";
        }
    }

    /**
     * 日付型フィールドのバリデーション。
     *
     * @param string $name    フィールド名
     * @param mixed  $value   値
     * @param array  &$errors エラー配列（参照渡し）
     */
    private function validateDate(string $name, mixed $value, array &$errors): void
    {
        if (!is_string($value) || strtotime($value) === false) {
            $errors[] = "フィールド '{$name}' は有効な日付である必要があります";
        }
    }

    /**
     * 配列型フィールドのバリデーション。
     *
     * @param string $name    フィールド名
     * @param mixed  $value   値
     * @param array  &$errors エラー配列（参照渡し）
     */
    private function validateArray(string $name, mixed $value, array &$errors): void
    {
        if (!is_array($value)) {
            $errors[] = "フィールド '{$name}' は配列である必要があります";
        }
    }

    /**
     * 画像型フィールドのバリデーション（URL またはファイルパス形式）。
     *
     * @param string $name    フィールド名
     * @param mixed  $value   値
     * @param array  &$errors エラー配列（参照渡し）
     */
    private function validateImage(string $name, mixed $value, array &$errors): void
    {
        if (!is_string($value)) {
            $errors[] = "フィールド '{$name}' は文字列（URL またはファイルパス）である必要があります";
            return;
        }

        /* URL 形式またはファイルパス形式を許容 */
        if (!filter_var($value, FILTER_VALIDATE_URL)
            && !preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $value)) {
            $errors[] = "フィールド '{$name}' は有効な画像 URL またはファイルパスである必要があります";
        }
    }
}

// ============================================================================
// CollectionService - コレクション管理エンジン統合ファサード
// ============================================================================

/**
 * CollectionEngine のロジックを Framework 化した静的ファサード。
 *
 * コレクション定義管理・アイテム CRUD・スキーマバリデーション・
 * レガシー互換変換を統合する。
 *
 * @since Ver.1.8
 */
class CollectionService {

    private const SCHEMA_FILE   = 'ap-collections.json';
    private const SLUG_PATTERN  = '/^[a-zA-Z0-9_\-]+$/';
    private const ALLOWED_TYPES = ['string', 'text', 'number', 'boolean', 'date', 'datetime', 'array', 'image'];

    /* ── コレクション有効判定 ── */

    public static function isEnabled(): bool {
        $schema = self::loadSchema();
        return !empty($schema['collections']);
    }

    /* ── スキーマ管理 ── */

    public static function loadSchema(): array {
        return \APF\Utilities\JsonStorage::read(self::SCHEMA_FILE, \AIS\Core\AppContext::contentDir());
    }

    public static function saveSchema(array $schema): void {
        \APF\Utilities\JsonStorage::write(self::SCHEMA_FILE, $schema, \AIS\Core\AppContext::contentDir());
    }

    public static function listCollections(): array {
        return \AIS\System\ApiCache::remember('collection_list', 60, function () {
            $schema = self::loadSchema();
            $collections = $schema['collections'] ?? [];
            $result = [];
            foreach ($collections as $name => $def) {
                $dir = \AIS\Core\AppContext::contentDir() . '/' . ($def['directory'] ?? $name);
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

    public static function getCollectionDef(string $name): ?array {
        $schema = self::loadSchema();
        return $schema['collections'][$name] ?? null;
    }

    /* ── コレクション CRUD ── */

    public static function createCollection(string $name, array $def): bool {
        if (!preg_match(self::SLUG_PATTERN, $name)) {
            \AIS\System\DiagnosticsManager::log('engine', 'コレクション作成失敗: 不正なスラッグ', ['name' => $name]);
            return false;
        }
        $schema = self::loadSchema();
        if (isset($schema['collections'][$name])) {
            \AIS\System\DiagnosticsManager::log('engine', 'コレクション作成失敗: 既に存在', ['name' => $name]);
            return false;
        }
        $def['directory'] = $def['directory'] ?? $name;
        if (!preg_match(self::SLUG_PATTERN, $def['directory'])) {
            \AIS\System\DiagnosticsManager::log('engine', 'コレクション作成失敗: 不正なディレクトリ名', ['directory' => $def['directory']]);
            return false;
        }
        $schema['collections'][$name] = $def;
        self::saveSchema($schema);

        $dir = \AIS\Core\AppContext::contentDir() . '/' . $def['directory'];
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                \AIS\System\DiagnosticsManager::logEnvironmentIssue('コレクションディレクトリ作成失敗', ['collection' => $name, 'error' => error_get_last()['message'] ?? '']);
            }
        }
        return true;
    }

    public static function deleteCollection(string $name): bool {
        $schema = self::loadSchema();
        if (!isset($schema['collections'][$name])) return false;
        unset($schema['collections'][$name]);
        self::saveSchema($schema);
        return true;
    }

    /* ── アイテム読み込み ── */

    public static function getItems(string $collection): array {
        $def = self::getCollectionDef($collection);
        if ($def === null) return [];
        $dir = \AIS\Core\AppContext::contentDir() . '/' . ($def['directory'] ?? $collection);
        if (!is_dir($dir)) return [];

        $items = \MarkdownEngine::loadDirectory($dir);
        $allItemCount = count($items);

        $now = time();
        $items = array_filter($items, function(array $item) use ($now): bool {
            if (!empty($item['meta']['draft'])) return false;
            $status = $item['meta']['status'] ?? 'published';
            if ($status === 'draft' || $status === 'archived') return false;
            if ($status === 'scheduled') {
                $publishDate = $item['meta']['publishDate'] ?? '';
                if ($publishDate === '') return false;
                $ts = strtotime($publishDate);
                if ($ts === false || $ts > $now) return false;
            }
            $pd = $item['meta']['publishDate'] ?? '';
            if ($pd !== '' && ($ts = strtotime($pd)) !== false && $ts > $now) return false;
            return true;
        });

        if ($allItemCount !== count($items)) {
            \AIS\System\DiagnosticsManager::log('debug', 'コレクションフィルタリング', ['collection' => $collection, 'total' => $allItemCount, 'public' => count($items), 'excluded' => $allItemCount - count($items)]);
        }

        $sortBy = $def['sortBy'] ?? null;
        $sortOrder = strtolower($def['sortOrder'] ?? 'asc');
        if ($sortBy !== null) {
            uasort($items, function(array $a, array $b) use ($sortBy, $sortOrder): int {
                $va = $a['meta'][$sortBy] ?? '';
                $vb = $b['meta'][$sortBy] ?? '';
                $cmp = is_numeric($va) && is_numeric($vb) ? ($va <=> $vb) : strcmp((string)$va, (string)$vb);
                return $sortOrder === 'desc' ? -$cmp : $cmp;
            });
        }
        return $items;
    }

    public static function getAllItems(string $collection): array {
        $def = self::getCollectionDef($collection);
        if ($def === null) return [];
        $dir = \AIS\Core\AppContext::contentDir() . '/' . ($def['directory'] ?? $collection);
        if (!is_dir($dir)) return [];
        return \MarkdownEngine::loadDirectory($dir);
    }

    public static function getItem(string $collection, string $slug): ?array {
        if (!preg_match(self::SLUG_PATTERN, $slug)) return null;
        $def = self::getCollectionDef($collection);
        if ($def === null) return null;
        $dir = \AIS\Core\AppContext::contentDir() . '/' . ($def['directory'] ?? $collection);
        $path = $dir . '/' . $slug . '.md';
        if (!file_exists($path)) return null;
        $raw = \APF\Utilities\FileSystem::read($path);
        if ($raw === false) return null;
        $parsed = \MarkdownEngine::parseFrontmatter($raw);
        return [
            'slug' => $slug,
            'meta' => $parsed['meta'],
            'body' => $parsed['body'],
            'html' => \MarkdownEngine::toHtml($parsed['body']),
        ];
    }

    /* ── アイテム書き込み ── */

    public static function saveItem(string $collection, string $slug, array $meta, string $body, bool $isNew = false): bool {
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            \AIS\System\DiagnosticsManager::log('engine', 'アイテム保存失敗: 不正なスラッグ', ['collection' => $collection, 'slug' => $slug]);
            return false;
        }
        $def = self::getCollectionDef($collection);
        if ($def === null) return false;
        $dir = \AIS\Core\AppContext::contentDir() . '/' . ($def['directory'] ?? $collection);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                \AIS\System\DiagnosticsManager::logEnvironmentIssue('コレクションアイテムディレクトリ作成失敗', ['collection' => $collection, 'error' => error_get_last()['message'] ?? '']);
            }
        }
        $path = $dir . '/' . $slug . '.md';
        if ($isNew && file_exists($path)) return false;
        $errors = self::validateFields($collection, $meta);
        if (!empty($errors)) {
            \AIS\System\DiagnosticsManager::log('engine', 'アイテム保存失敗: バリデーションエラー', ['collection' => $collection, 'slug' => $slug, 'errors' => $errors]);
            return false;
        }
        $content = self::buildMarkdown($meta, $body);
        return \APF\Utilities\FileSystem::write($path, $content);
    }

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
                    if (!is_numeric($value)) $errors[] = "フィールド '{$name}' は数値である必要があります";
                    break;
                case 'boolean':
                    if (!is_bool($value) && $value !== '0' && $value !== '1' && $value !== 'true' && $value !== 'false')
                        $errors[] = "フィールド '{$name}' は真偽値である必要があります";
                    break;
                case 'date': case 'datetime':
                    if (is_string($value) && strtotime($value) === false)
                        $errors[] = "フィールド '{$name}' は有効な日付である必要があります";
                    break;
                case 'array':
                    if (!is_array($value)) $errors[] = "フィールド '{$name}' は配列である必要があります";
                    break;
            }
        }
        return $errors;
    }

    public static function deleteItem(string $collection, string $slug): bool {
        if (!preg_match(self::SLUG_PATTERN, $slug)) return false;
        $def = self::getCollectionDef($collection);
        if ($def === null) return false;
        $dir = \AIS\Core\AppContext::contentDir() . '/' . ($def['directory'] ?? $collection);
        $path = $dir . '/' . $slug . '.md';
        if (!file_exists($path)) return false;
        $result = unlink($path);
        if (!$result) {
            \AIS\System\DiagnosticsManager::log('engine', 'コレクションアイテム削除失敗', ['collection' => $collection, 'slug' => $slug, 'error' => error_get_last()['message'] ?? '']);
        }
        return $result;
    }

    /* ── レガシー互換 ── */

    public static function loadAllAsPages(): array {
        $pages = [];
        $schema = self::loadSchema();
        foreach (($schema['collections'] ?? []) as $name => $def) {
            $items = self::getItems($name);
            foreach ($items as $slug => $item) {
                $key = ($name === 'pages') ? $slug : $name . '/' . $slug;
                $pages[$key] = $item['html'];
            }
        }
        return $pages;
    }

    public static function migrateFromPagesJson(): array {
        $pages = \APF\Utilities\JsonStorage::read('pages.json', \AIS\Core\AppContext::contentDir());
        if (empty($pages)) return ['migrated' => 0, 'error' => 'pages.json が空または存在しません'];
        $schema = self::loadSchema();
        if (!isset($schema['collections']['pages'])) {
            $schema['collections']['pages'] = [
                'label' => '固定ページ', 'directory' => 'pages', 'format' => 'markdown',
                'fields' => ['title' => ['type' => 'string', 'required' => true], 'order' => ['type' => 'number', 'default' => 0]],
            ];
            self::saveSchema($schema);
        }
        $dir = \AIS\Core\AppContext::contentDir() . '/pages';
        \APF\Utilities\FileSystem::ensureDir($dir);
        $count = 0;
        foreach ($pages as $slug => $html) {
            if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
            $path = $dir . '/' . $slug . '.md';
            if (file_exists($path)) continue;
            $meta = ['title' => ucfirst(str_replace('-', ' ', $slug)), 'order' => $count];
            \APF\Utilities\FileSystem::write($path, self::buildMarkdown($meta, $html));
            $count++;
        }
        return ['migrated' => $count, 'total' => count($pages)];
    }

    /* ── ユーティリティ ── */

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
        if (preg_match('/[:#\[\]{}|>\r\n]/', $str) || str_contains($str, '---') || trim($str) !== $str || $str === '') {
            return '"' . self::escapeYamlString($str) . '"';
        }
        return $str;
    }

    private static function escapeYamlString(string $s): string {
        return str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $s);
    }
}
