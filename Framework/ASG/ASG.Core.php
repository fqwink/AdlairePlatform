<?php
/**
 * Adlaire Static Generator (ASG) - Core Module
 *
 * ASG = Adlaire Static Generator
 *
 * 静的サイト生成の中核モジュール。サイト全体のビルド、差分ビルド、
 * URL ルーティング、ファイルシステム操作を提供する。
 * Adlaire Platform のエンジン群から抽出・汎用化した独立フレームワーク。
 *
 * @package ASG
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace ASG\Core;

// ============================================================================
// BuildStatus - ビルド状態列挙型
// ============================================================================

/**
 * ビルドプロセスの状態を表す列挙型
 */
enum BuildStatus: string {
    /** ビルド待機中 */
    case Pending = 'pending';
    /** ビルド実行中 */
    case Building = 'building';
    /** ビルド完了 */
    case Complete = 'complete';
    /** ビルドエラー */
    case Error = 'error';
}

// ============================================================================
// Generator - 静的サイト生成オーケストレーター
// ============================================================================

/**
 * 静的サイト全体のビルドプロセスを統括するオーケストレーター。
 *
 * コンテンツディレクトリからページを読み込み、テンプレートを適用し、
 * 出力ディレクトリに静的 HTML ファイルを生成する。
 * フルリビルドと差分ビルドの両方に対応。
 */
class Generator {
    /** @var array<string, mixed> ジェネレーター設定 */
    private readonly array $config;

    /** @var BuildStatus 現在のビルド状態 */
    private BuildStatus $status = BuildStatus::Pending;

    /** @var array<string, mixed> 最後のビルド結果統計 */
    private array $lastStats = [];

    /** @var float ビルド開始時刻（hrtime） */
    private float $startTime = 0;

    /**
     * ジェネレーターを初期化
     *
     * @param array $config 設定配列
     *   - outputDir: string  生成ファイルの出力先ディレクトリ
     *   - contentDir: string コンテンツ（Markdown等）のソースディレクトリ
     *   - themeDir: string   テーマテンプレートのディレクトリ
     *   - baseUrl: string    サイトのベース URL（省略時: '/'）
     *   - cleanUrls: bool    拡張子なし URL を使用するか（省略時: true）
     */
    public function __construct(array $config) {
        $this->config = array_merge([
            'outputDir'  => './dist',
            'contentDir' => './content',
            'themeDir'   => './themes',
            'baseUrl'    => '/',
            'cleanUrls'  => true,
        ], $config);
    }

    /**
     * サイト全体をフルリビルドする
     *
     * コンテンツディレクトリ内の全ファイルを処理し、静的 HTML を生成する。
     *
     * @return array ビルド統計情報
     *   - totalPages: int    生成されたページ数
     *   - totalFiles: int    生成されたファイル総数
     *   - duration: float    ビルド所要時間（ミリ秒）
     *   - errors: array      発生したエラーの一覧
     *   - status: string     ビルド結果状態
     */
    public function buildAll(): array {
        $this->status = BuildStatus::Building;
        $this->startTime = hrtime(true);
        $errors = [];
        $pagesBuilt = 0;
        $filesGenerated = 0;

        $fs = new StaticFileSystem();
        $outputDir = $this->config['outputDir'];

        /* 出力ディレクトリを準備 */
        $fs->ensureDir($outputDir);

        /* コンテンツファイル一覧を取得 */
        $contentDir = $this->config['contentDir'];
        $contentFiles = $fs->listFiles($contentDir, 'md');

        foreach ($contentFiles as $file) {
            try {
                $slug = pathinfo($file, PATHINFO_FILENAME);
                $content = $fs->read($file);
                if ($content === false) {
                    $errors[] = ['slug' => $slug, 'error' => 'ファイルの読み込みに失敗'];
                    continue;
                }

                /* 出力パスを決定 */
                $outputPath = $this->resolveOutputPath($slug);
                $fullOutputPath = $outputDir . '/' . $outputPath;

                /* 出力ディレクトリを確保 */
                $fs->ensureDir(dirname($fullOutputPath));

                /* コンテンツを書き出し（実際のレンダリングは Builder に委譲） */
                $fs->write($fullOutputPath, $content);
                $pagesBuilt++;
                $filesGenerated++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'slug'  => $slug ?? basename($file),
                    'error' => $e->getMessage(),
                ];
            }
        }

        /* 静的アセットをコピー */
        $assetCount = $this->copyStaticAssets($fs);
        $filesGenerated += $assetCount;

        $duration = (hrtime(true) - $this->startTime) / 1_000_000;
        $this->status = empty($errors) ? BuildStatus::Complete : BuildStatus::Error;

        $this->lastStats = [
            'totalPages'     => $pagesBuilt,
            'totalFiles'     => $filesGenerated,
            'duration'       => round($duration, 2),
            'errors'         => $errors,
            'status'         => $this->status->value,
            'timestamp'      => date('Y-m-d H:i:s'),
        ];

        return $this->lastStats;
    }

    /**
     * 差分ビルドを実行する
     *
     * 前回のビルド状態と比較し、変更されたページのみ再生成する。
     *
     * @param array $previousState 前回のビルド状態（コンテンツハッシュマップ）
     *   キー: slug, 値: コンテンツのハッシュ値
     * @return array ビルド統計情報（buildAll と同じ形式 + changedSlugs）
     */
    public function buildDiff(array $previousState): array {
        $this->status = BuildStatus::Building;
        $this->startTime = hrtime(true);
        $errors = [];
        $pagesBuilt = 0;
        $changedSlugs = [];

        $fs = new StaticFileSystem();
        $outputDir = $this->config['outputDir'];
        $contentDir = $this->config['contentDir'];

        $fs->ensureDir($outputDir);

        $contentFiles = $fs->listFiles($contentDir, 'md');

        foreach ($contentFiles as $file) {
            try {
                $slug = pathinfo($file, PATHINFO_FILENAME);
                $content = $fs->read($file);
                if ($content === false) {
                    continue;
                }

                $currentHash = $fs->getHash($file);
                $previousHash = $previousState[$slug] ?? '';

                /* ハッシュが一致する場合はスキップ */
                if ($currentHash === $previousHash) {
                    continue;
                }

                $outputPath = $this->resolveOutputPath($slug);
                $fullOutputPath = $outputDir . '/' . $outputPath;
                $fs->ensureDir(dirname($fullOutputPath));
                $fs->write($fullOutputPath, $content);

                $changedSlugs[] = $slug;
                $pagesBuilt++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'slug'  => $slug ?? basename($file),
                    'error' => $e->getMessage(),
                ];
            }
        }

        /* 削除されたページを検出・除去 */
        $currentSlugs = array_map(
            fn(string $f) => pathinfo($f, PATHINFO_FILENAME),
            $contentFiles
        );
        $deletedSlugs = array_diff(array_keys($previousState), $currentSlugs);
        foreach ($deletedSlugs as $slug) {
            $outputPath = $this->resolveOutputPath($slug);
            $fullOutputPath = $outputDir . '/' . $outputPath;
            $fs->delete($fullOutputPath);
        }

        $duration = (hrtime(true) - $this->startTime) / 1_000_000;
        $this->status = empty($errors) ? BuildStatus::Complete : BuildStatus::Error;

        $this->lastStats = [
            'totalPages'     => $pagesBuilt,
            'totalFiles'     => $pagesBuilt,
            'duration'       => round($duration, 2),
            'errors'         => $errors,
            'status'         => $this->status->value,
            'changedSlugs'   => $changedSlugs,
            'deletedSlugs'   => array_values($deletedSlugs),
            'timestamp'      => date('Y-m-d H:i:s'),
        ];

        return $this->lastStats;
    }

    /**
     * 生成済みファイルをすべて削除する
     *
     * 出力ディレクトリの中身を再帰的に削除する。
     * ディレクトリ自体は保持される。
     */
    public function clean(): void {
        $outputDir = $this->config['outputDir'];
        if (!is_dir($outputDir)) {
            return;
        }

        $this->removeDirectoryContents($outputDir);
        $this->status = BuildStatus::Pending;
        $this->lastStats = [];
    }

    /**
     * 現在のビルド状態を取得する
     *
     * @return array ビルド状態情報
     *   - status: string       現在の状態
     *   - lastBuild: array     最後のビルド統計
     *   - config: array        現在の設定（パスワード等は除外）
     */
    public function getStatus(): array {
        return [
            'status'    => $this->status->value,
            'lastBuild' => $this->lastStats,
            'config'    => [
                'outputDir'  => $this->config['outputDir'],
                'contentDir' => $this->config['contentDir'],
                'themeDir'   => $this->config['themeDir'],
                'baseUrl'    => $this->config['baseUrl'],
                'cleanUrls'  => $this->config['cleanUrls'],
            ],
        ];
    }

    /**
     * スラッグから出力ファイルパスを解決する
     *
     * @param string $slug ページスラッグ
     * @return string 相対出力パス
     */
    private function resolveOutputPath(string $slug): string {
        if ($slug === 'index') {
            return 'index.html';
        }

        if ($this->config['cleanUrls']) {
            return $slug . '/index.html';
        }

        return $slug . '.html';
    }

    /**
     * テーマの静的アセットを出力ディレクトリにコピーする
     *
     * @param StaticFileSystem $fs ファイルシステムインスタンス
     * @return int コピーされたファイル数
     */
    private function copyStaticAssets(StaticFileSystem $fs): int {
        $assetsDir = $this->config['themeDir'] . '/assets';
        $outputAssetsDir = $this->config['outputDir'] . '/assets';
        $count = 0;

        if (!is_dir($assetsDir)) {
            return 0;
        }

        $fs->ensureDir($outputAssetsDir);
        $assetFiles = $fs->listFiles($assetsDir);

        foreach ($assetFiles as $file) {
            $relativePath = substr($file, strlen($assetsDir));
            $destPath = $outputAssetsDir . $relativePath;
            $fs->ensureDir(dirname($destPath));

            $content = $fs->read($file);
            if ($content !== false) {
                $fs->write($destPath, $content);
                $count++;
            }
        }

        return $count;
    }

    /**
     * ディレクトリの中身を再帰的に削除する
     *
     * @param string $dir 対象ディレクトリパス
     */
    private function removeDirectoryContents(string $dir): void {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectoryContents($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}

// ============================================================================
// Builder - 個別ページビルダー
// ============================================================================

/**
 * 個別のページ HTML を構築するビルダー。
 *
 * テンプレートレンダラーと連携し、コンテンツをテンプレートに流し込んで
 * 完成した HTML ページを生成する。
 * ASG\Template\TemplateRenderer への依存は引数の型ヒントではなく
 * callable ベースで疎結合を実現する。
 */
class Builder {
    /** @var callable テンプレートレンダリング関数 */
    private $renderer;

    /**
     * ビルダーを初期化
     *
     * @param callable $renderer テンプレートレンダリング関数
     *   シグネチャ: fn(string $template, array $context): string
     */
    public function __construct(callable $renderer) {
        $this->renderer = $renderer;
    }

    /**
     * 単一ページの HTML を構築する
     *
     * コンテンツとコンテキストをテンプレートに適用し、完全な HTML を生成する。
     *
     * @param string $slug    ページスラッグ（URL パス識別子）
     * @param string $content ページ本文 HTML
     * @param array  $context テンプレートコンテキスト変数
     *   - title: string       ページタイトル
     *   - template: string    使用するテンプレート文字列
     *   - meta: array         メタデータ（description, keywords 等）
     *   - site: array         サイト全体設定
     * @return string 完成した HTML 文字列
     */
    public function buildPage(string $slug, string $content, array $context): string {
        $template = $context['template'] ?? '{{{content}}}';

        /* ページ固有のコンテキストを構築 */
        $pageContext = array_merge($context, [
            'slug'      => $slug,
            'content'   => $content,
            'permalink' => $this->buildPermalink($slug, $context),
            'buildTime' => date('Y-m-d H:i:s'),
        ]);

        /* メタタグを生成 */
        $pageContext['metaTags'] = $this->buildMetaTags($pageContext);

        return ($this->renderer)($template, $pageContext);
    }

    /**
     * インデックス（一覧）ページの HTML を構築する
     *
     * 複数のページ情報を受け取り、一覧テンプレートを適用して
     * インデックスページの HTML を生成する。
     *
     * @param array $pages   ページ情報の配列（各要素に slug, title, excerpt 等）
     * @param array $context テンプレートコンテキスト変数
     * @return string 完成した HTML 文字列
     */
    public function buildIndex(array $pages, array $context): string {
        $template = $context['template'] ?? '{{{content}}}';

        /* ページ一覧をソート（日付降順） */
        usort($pages, function (array $a, array $b): int {
            $dateA = $a['date'] ?? $a['created'] ?? '';
            $dateB = $b['date'] ?? $b['created'] ?? '';
            return strcmp($dateB, $dateA);
        });

        $indexContext = array_merge($context, [
            'pages'     => $pages,
            'pageCount' => count($pages),
            'isIndex'   => true,
            'buildTime' => date('Y-m-d H:i:s'),
        ]);

        return ($this->renderer)($template, $indexContext);
    }

    /**
     * パーマリンクを構築する
     *
     * @param string $slug    ページスラッグ
     * @param array  $context コンテキスト（baseUrl を含む）
     * @return string 完全な URL パス
     */
    private function buildPermalink(string $slug, array $context): string {
        $baseUrl = rtrim($context['baseUrl'] ?? '/', '/');

        if ($slug === 'index') {
            return $baseUrl . '/';
        }

        return $baseUrl . '/' . $slug . '/';
    }

    /**
     * メタタグ HTML を生成する
     *
     * @param array $context ページコンテキスト
     * @return string メタタグ HTML 文字列
     */
    private function buildMetaTags(array $context): string {
        $tags = [];
        $meta = $context['meta'] ?? [];

        if (isset($meta['description'])) {
            $desc = htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8');
            $tags[] = '<meta name="description" content="' . $desc . '">';
        }

        if (isset($meta['keywords'])) {
            $kw = htmlspecialchars(
                is_array($meta['keywords']) ? implode(', ', $meta['keywords']) : $meta['keywords'],
                ENT_QUOTES,
                'UTF-8'
            );
            $tags[] = '<meta name="keywords" content="' . $kw . '">';
        }

        /* OGP タグ */
        $title = htmlspecialchars($context['title'] ?? '', ENT_QUOTES, 'UTF-8');
        if ($title !== '') {
            $tags[] = '<meta property="og:title" content="' . $title . '">';
        }
        if (isset($meta['description'])) {
            $tags[] = '<meta property="og:description" content="' . $desc . '">';
        }
        if (isset($meta['image'])) {
            $img = htmlspecialchars($meta['image'], ENT_QUOTES, 'UTF-8');
            $tags[] = '<meta property="og:image" content="' . $img . '">';
        }

        return implode("\n    ", $tags);
    }
}

// ============================================================================
// SiteRouter - 静的サイト用 URL/パスリゾルバー
// ============================================================================

/**
 * 静的サイト向けの URL・パス解決とサイトマップ生成を担当する。
 *
 * URL からスラッグへの変換、スラッグから URL への変換、
 * XML サイトマップの生成を提供する。
 */
class SiteRouter {
    /** @var string サイトのベース URL */
    private readonly string $baseUrl;

    /** @var bool クリーン URL を使用するか */
    private readonly bool $cleanUrls;

    /**
     * サイトルーターを初期化
     *
     * @param string $baseUrl   サイトのベース URL（例: 'https://example.com'）
     * @param bool   $cleanUrls 拡張子なし URL を使用するか（デフォルト: true）
     */
    public function __construct(string $baseUrl = '/', bool $cleanUrls = true) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cleanUrls = $cleanUrls;
    }

    /**
     * URL パスからスラッグを解決する
     *
     * @param string $url URL パス（例: '/blog/hello-world/'）
     * @return string 解決されたスラッグ（例: 'hello-world'）
     */
    public function resolveSlug(string $url): string {
        /* クエリ文字列とフラグメントを除去 */
        $path = parse_url($url, PHP_URL_PATH) ?? '/';

        /* ベース URL プレフィックスを除去 */
        $basePath = parse_url($this->baseUrl, PHP_URL_PATH) ?? '';
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        /* 前後のスラッシュを除去 */
        $path = trim($path, '/');

        /* 空パスはインデックス */
        if ($path === '' || $path === 'index.html') {
            return 'index';
        }

        /* .html 拡張子を除去 */
        if (str_ends_with($path, '.html')) {
            $path = substr($path, 0, -5);
        }

        /* /index サフィックスを除去（クリーン URL 形式） */
        if (str_ends_with($path, '/index')) {
            $path = substr($path, 0, -6);
        }

        return $path;
    }

    /**
     * スラッグから URL パスを構築する
     *
     * @param string $slug ページスラッグ
     * @return string 完全な URL パス
     */
    public function buildUrl(string $slug): string {
        if ($slug === 'index' || $slug === '') {
            return $this->baseUrl . '/';
        }

        if ($this->cleanUrls) {
            return $this->baseUrl . '/' . $slug . '/';
        }

        return $this->baseUrl . '/' . $slug . '.html';
    }

    /**
     * XML サイトマップを生成する
     *
     * @param array  $pages   ページ情報の配列
     *   各要素: ['slug' => string, 'lastmod' => string, 'priority' => float, 'changefreq' => string]
     * @param string $baseUrl サイトの完全なベース URL（省略時: コンストラクタの値を使用）
     * @return string XML サイトマップ文字列
     */
    public function generateSitemap(array $pages, string $baseUrl = ''): string {
        $base = $baseUrl !== '' ? rtrim($baseUrl, '/') : $this->baseUrl;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($pages as $page) {
            $slug = $page['slug'] ?? '';
            $loc = $base . $this->buildUrl($slug);

            /* 二重スキーム防止 */
            if (str_starts_with($this->buildUrl($slug), $base)) {
                $loc = $this->buildUrl($slug);
            }

            $lastmod = $page['lastmod'] ?? date('Y-m-d');
            $changefreq = $page['changefreq'] ?? 'weekly';
            $priority = $page['priority'] ?? ($slug === 'index' ? '1.0' : '0.5');

            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
            $xml .= '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $priority . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }
}

// ============================================================================
// StaticFileSystem - 静的出力用ファイル操作
// ============================================================================

/**
 * 静的サイト生成に特化したファイル操作クラス。
 *
 * ファイルの読み書き・削除・ディレクトリ管理・ハッシュ計算など、
 * ビルドプロセスで必要なファイルシステム操作を提供する。
 */
class StaticFileSystem {
    /**
     * ファイルにコンテンツを書き込む
     *
     * 親ディレクトリが存在しない場合は自動的に作成する。
     *
     * @param string $path    書き込み先のファイルパス
     * @param string $content 書き込む内容
     * @return bool 成功した場合 true
     */
    public function write(string $path, string $content): bool {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            $this->ensureDir($dir);
        }

        $result = file_put_contents($path, $content, LOCK_EX);
        return $result !== false;
    }

    /**
     * ファイルの内容を読み込む
     *
     * @param string $path 読み込むファイルパス
     * @return string|false ファイル内容。失敗時は false
     */
    public function read(string $path): string|false {
        if (!file_exists($path)) {
            return false;
        }

        return file_get_contents($path);
    }

    /**
     * ファイルを削除する
     *
     * @param string $path 削除するファイルパス
     * @return bool 成功した場合 true
     */
    public function delete(string $path): bool {
        if (!file_exists($path)) {
            return false;
        }

        return @unlink($path);
    }

    /**
     * ディレクトリが存在することを保証する
     *
     * ディレクトリが存在しない場合は再帰的に作成する。
     *
     * @param string $dir ディレクトリパス
     * @return bool 成功した場合（既に存在する場合も含む）true
     */
    public function ensureDir(string $dir): bool {
        if (is_dir($dir)) {
            return true;
        }

        return mkdir($dir, 0755, true);
    }

    /**
     * ディレクトリ内のファイル一覧を取得する
     *
     * 再帰的にサブディレクトリも走査する。
     *
     * @param string $dir ディレクトリパス
     * @param string $ext ファイル拡張子でフィルタ（空文字で全ファイル）
     * @return array<string> ファイルパスの配列
     */
    public function listFiles(string $dir, string $ext = ''): array {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filePath = $file->getPathname();

            /* 拡張子フィルタ */
            if ($ext !== '') {
                $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if ($fileExt !== strtolower($ext)) {
                    continue;
                }
            }

            /* パス区切りを統一 */
            $files[] = str_replace('\\', '/', $filePath);
        }

        sort($files);
        return $files;
    }

    /**
     * ファイルのコンテンツハッシュを計算する
     *
     * 差分ビルドでの変更検出に使用される SHA-256 ハッシュ。
     *
     * @param string $path ファイルパス
     * @return string SHA-256 ハッシュ値。ファイルが存在しない場合は空文字
     */
    public function getHash(string $path): string {
        if (!file_exists($path)) {
            return '';
        }

        $hash = hash_file('sha256', $path);
        return $hash !== false ? $hash : '';
    }
}

// ============================================================================
// StaticService - 静的サイト生成ファサード
// ============================================================================

/**
 * StaticEngine の全ロジックをフレームワーク化した静的ファサード。
 *
 * インスタンスベースの StaticEngine を内部で管理し、
 * 差分/フルビルド・クリーン・ZIP・ステータスを提供する。
 * すべての旧エンジン参照（CollectionEngine, ThemeEngine, TemplateEngine,
 * FileSystem, DiagnosticEngine, WebhookEngine, json_read/json_write,
 * settings_dir/content_dir）をフレームワーク参照に置換。
 *
 * @since Ver.1.8
 */
class StaticService {

    private const OUTPUT_DIR   = 'static';
    private const BUILD_STATE  = 'static_build.json';
    private const SLUG_PATTERN = '/^[a-zA-Z0-9_\-]+(\/[a-zA-Z0-9_\-]+)*$/';

    private static array  $settings     = [];
    private static array  $pages        = [];
    private static string $themeDir     = '';
    private static array  $buildState   = [];
    private static array  $warnings     = [];
    private static array  $changedFiles = [];
    private static bool   $initialized  = false;

    /* ══════════════════════════════════════════════
       初期化
       ══════════════════════════════════════════════ */

    public static function init(): void {
        self::$warnings     = [];
        self::$changedFiles = [];
        self::$settings = \APF\Utilities\JsonStorage::read(
            'settings.json',
            \AIS\Core\AppContext::settingsDir()
        );

        /* コレクションモードか従来モードかを判定 */
        if (\ACE\Core\CollectionService::isEnabled()) {
            self::$pages = \ACE\Core\CollectionService::loadAllAsPages();
            $legacy = \APF\Utilities\JsonStorage::read(
                'pages.json',
                \AIS\Core\AppContext::contentDir()
            );
            foreach ($legacy as $slug => $content) {
                if (!isset(self::$pages[$slug])) {
                    self::$pages[$slug] = $content;
                }
            }
        } else {
            self::$pages = \APF\Utilities\JsonStorage::read(
                'pages.json',
                \AIS\Core\AppContext::contentDir()
            );
        }

        $theme = self::$settings['themeSelect'] ?? 'AP-Default';
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $theme)) $theme = 'AP-Default';
        self::$themeDir = 'themes/' . $theme;
        if (!is_dir(self::$themeDir)) {
            self::$themeDir = 'themes/AP-Default';
        }
        self::loadBuildState();
        self::$initialized = true;
    }

    /* ══════════════════════════════════════════════
       差分ビルド
       ══════════════════════════════════════════════ */

    public static function buildDiff(): array {
        self::ensureInit();
        $start = hrtime(true);
        $built = 0; $skipped = 0;

        $newSettingsHash = self::computeSettingsHash();
        $allDirty = ($newSettingsHash !== (self::$buildState['settings_hash'] ?? ''));
        $themeChanged = ((self::$settings['themeSelect'] ?? '') !== (self::$buildState['theme'] ?? ''));

        if ($themeChanged || $allDirty) {
            self::copyAssets();
        }
        self::$buildState['settings_hash'] = $newSettingsHash;
        self::$buildState['theme'] = self::$settings['themeSelect'] ?? 'AP-Default';

        /* ビルドフック: before_build */
        self::runHook('before_build', ['type' => 'diff']);

        foreach (self::$pages as $slug => $content) {
            if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
            $contentHash = self::computeContentHash($slug, (string)$content, $newSettingsHash);
            $outputPath  = self::OUTPUT_DIR . '/' . ($slug === 'index' ? '' : $slug . '/') . 'index.html';

            if (!$allDirty
                && isset(self::$buildState['pages'][$slug]['content_hash'])
                && self::$buildState['pages'][$slug]['content_hash'] === $contentHash
                && file_exists($outputPath)) {
                $skipped++;
                continue;
            }

            self::buildPage($slug, (string)$content);
            self::$buildState['pages'][$slug] = [
                'content_hash' => $contentHash,
                'built_at'     => date('c'),
            ];
            $built++;
        }

        $built += self::buildCollectionIndexes();
        $built += self::buildTagPages();

        self::generateSitemap();
        self::generateRobotsTxt();
        self::generate404Page();
        self::generateSearchIndex();
        self::generateRedirects();
        $deleted = self::deleteOrphanedFiles();
        self::$buildState['last_diff_build'] = date('c');
        self::$buildState['changed_files'] = self::$changedFiles;
        self::saveBuildState();

        self::runHook('after_build', ['type' => 'diff', 'built' => $built]);

        \ACE\Api\WebhookService::dispatch('build.completed', [
            'type' => 'diff', 'built' => $built, 'skipped' => $skipped,
        ]);

        $elapsed = (int)((hrtime(true) - $start) / 1_000_000);
        \AIS\System\DiagnosticsManager::log('performance', 'StaticService 差分ビルド完了', [
            'built' => $built, 'skipped' => $skipped,
            'deleted' => $deleted, 'elapsed_ms' => $elapsed,
        ]);
        $result = [
            'ok' => true, 'built' => $built, 'skipped' => $skipped,
            'deleted' => $deleted, 'elapsed_ms' => $elapsed,
        ];
        if (self::$warnings) $result['warnings'] = self::$warnings;
        return $result;
    }

    /* ══════════════════════════════════════════════
       フルビルド
       ══════════════════════════════════════════════ */

    public static function buildAll(): array {
        self::ensureInit();
        $start = hrtime(true);
        $built = 0;

        self::copyAssets();
        $newSettingsHash = self::computeSettingsHash();
        self::$buildState['settings_hash'] = $newSettingsHash;
        self::$buildState['theme'] = self::$settings['themeSelect'] ?? 'AP-Default';
        self::$buildState['pages'] = [];

        self::runHook('before_build', ['type' => 'full']);

        foreach (self::$pages as $slug => $content) {
            if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
            self::buildPage($slug, (string)$content);
            self::$buildState['pages'][$slug] = [
                'content_hash' => self::computeContentHash($slug, (string)$content, $newSettingsHash),
                'built_at'     => date('c'),
            ];
            $built++;
        }

        $built += self::buildCollectionIndexes();
        $built += self::buildTagPages();

        self::generateSitemap();
        self::generateRobotsTxt();
        self::generate404Page();
        self::generateSearchIndex();
        self::generateRedirects();
        $deleted = self::deleteOrphanedFiles();
        self::$buildState['last_full_build'] = date('c');
        self::$buildState['last_diff_build'] = date('c');
        self::$buildState['changed_files'] = self::$changedFiles;
        self::saveBuildState();

        self::runHook('after_build', ['type' => 'full', 'built' => $built]);

        \ACE\Api\WebhookService::dispatch('build.completed', [
            'type' => 'full', 'built' => $built,
        ]);

        $elapsed = (int)((hrtime(true) - $start) / 1_000_000);
        \AIS\System\DiagnosticsManager::log('performance', 'StaticService フルビルド完了', [
            'built' => $built, 'deleted' => $deleted, 'elapsed_ms' => $elapsed,
        ]);
        $result = [
            'ok' => true, 'built' => $built, 'skipped' => 0,
            'deleted' => $deleted, 'elapsed_ms' => $elapsed,
        ];
        if (self::$warnings) $result['warnings'] = self::$warnings;
        return $result;
    }

    /* ══════════════════════════════════════════════
       クリーン
       ══════════════════════════════════════════════ */

    public static function clean(): void {
        self::ensureInit();
        if (is_dir(self::OUTPUT_DIR)) {
            self::removeDir(self::OUTPUT_DIR);
        }
        self::$buildState = ['schema_version' => 1, 'pages' => []];
        self::saveBuildState();
    }

    /* ══════════════════════════════════════════════
       ステータス
       ══════════════════════════════════════════════ */

    public static function getStatus(): array {
        self::ensureInit();
        $pageStatuses = [];
        $settingsHash = self::computeSettingsHash();
        $allDirty = ($settingsHash !== (self::$buildState['settings_hash'] ?? ''));

        foreach (self::$pages as $slug => $content) {
            if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
            $contentHash = self::computeContentHash($slug, (string)$content, $settingsHash);
            $outputPath  = self::OUTPUT_DIR . '/' . ($slug === 'index' ? '' : $slug . '/') . 'index.html';
            $state = 'not_built';
            if (file_exists($outputPath)) {
                if (!$allDirty
                    && isset(self::$buildState['pages'][$slug]['content_hash'])
                    && self::$buildState['pages'][$slug]['content_hash'] === $contentHash) {
                    $state = 'current';
                } else {
                    $state = 'outdated';
                }
            }
            $pageStatuses[] = ['slug' => $slug, 'state' => $state];
        }

        return [
            'ok'              => true,
            'last_full_build' => self::$buildState['last_full_build'] ?? null,
            'last_diff_build' => self::$buildState['last_diff_build'] ?? null,
            'theme'           => self::$buildState['theme'] ?? null,
            'pages'           => $pageStatuses,
            'static_exists'   => is_dir(self::OUTPUT_DIR) && !empty(glob(self::OUTPUT_DIR . '/*/index.html')),
        ];
    }

    /* ══════════════════════════════════════════════
       アセットコピー
       ══════════════════════════════════════════════ */

    public static function copyAssets(): void {
        \AIS\System\DiagnosticsManager::startTimer('StaticService::copyAssets');
        $assetsDir = self::OUTPUT_DIR . '/assets';
        \APF\Utilities\FileSystem::ensureDir($assetsDir);

        /* テーマ CSS（ミニファイ対応） */
        $css = self::$themeDir . '/style.css';
        if (file_exists($css)) {
            $cssContent = \APF\Utilities\FileSystem::read($css);
            if ($cssContent !== false && !empty(self::$settings['minify'] ?? true)) {
                $cssContent = self::minifyCss($cssContent);
            }
            if ($cssContent !== false) {
                \APF\Utilities\FileSystem::write($assetsDir . '/style.css', $cssContent);
            } else {
                copy($css, $assetsDir . '/style.css');
            }
        }

        /* テーマ JS */
        foreach (glob(self::$themeDir . '/*.js') ?: [] as $js) {
            copy($js, $assetsDir . '/' . basename($js));
        }

        /* 公開 API クライアント JS */
        $apiClient = 'Framework/AP/JsEngine/ap-api-client.js';
        if (file_exists($apiClient)) {
            copy($apiClient, $assetsDir . '/ap-api-client.js');
        }

        /* uploads/ 差分コピー */
        self::syncUploads();

        /* 検索 JS */
        $searchJs = 'Framework/AP/JsEngine/ap-search.js';
        if (file_exists($searchJs)) {
            copy($searchJs, $assetsDir . '/ap-search.js');
        }

        /* static/.htaccess 自動生成 */
        self::writeStaticHtaccess();

        /* ビルドフック: after_asset_copy */
        self::runHook('after_asset_copy', ['assets_dir' => $assetsDir]);

        self::$buildState['assets_copied_at'] = date('c');
        \AIS\System\DiagnosticsManager::stopTimer('StaticService::copyAssets');
    }

    /* ══════════════════════════════════════════════
       ZIP ダウンロード
       ══════════════════════════════════════════════ */

    /**
     * 静的ファイル全体の ZIP を temp に作成しパスを返す
     * @throws \RuntimeException
     */
    public static function buildZipFile(): string {
        if (!is_dir(self::OUTPUT_DIR)) {
            throw new \RuntimeException('静的ファイルが生成されていません。先にビルドを実行してください。');
        }
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('ZipArchive 拡張が利用できません。');
        }
        $tmpFile = sys_get_temp_dir() . '/ap_static_' . date('Ymd_His') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP ファイルの作成に失敗しました。');
        }
        self::addDirToZip($zip, self::OUTPUT_DIR, self::OUTPUT_DIR);
        $zip->close();
        return $tmpFile;
    }

    /**
     * 差分ビルドの変更ファイルのみ ZIP を temp に作成しパスを返す
     * @throws \RuntimeException
     */
    public static function buildDiffZipFile(): string {
        self::ensureInit();
        $changedFiles = self::$buildState['changed_files'] ?? [];
        if (empty($changedFiles)) {
            throw new \RuntimeException('変更ファイルがありません。先にビルドを実行してください。');
        }
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('ZipArchive 拡張が利用できません。');
        }
        $tmpFile = sys_get_temp_dir() . '/ap_deploy_diff_' . date('Ymd_His') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP ファイルの作成に失敗しました。');
        }
        $prefix = self::OUTPUT_DIR . '/';
        foreach ($changedFiles as $file) {
            if (file_exists($file) && str_starts_with($file, $prefix)) {
                $rel = substr($file, strlen($prefix));
                $zip->addFile($file, $rel);
            }
        }
        $zip->close();
        return $tmpFile;
    }

    /* ══════════════════════════════════════════════
       内部メソッド — ページビルド
       ══════════════════════════════════════════════ */

    private static function buildPage(string $slug, string $content): void {
        $html = self::renderPageWithCollectionTemplate($slug, $content);
        if ($html === null) {
            $html = self::renderPage($slug, $content);
        }

        if (!empty(self::$settings['minify'] ?? true)) {
            $html = self::minifyHtml($html);
        }

        self::runHook('after_page_render', ['slug' => $slug, 'html' => &$html]);

        if ($slug === 'index') {
            $dir = self::OUTPUT_DIR;
        } else {
            $dir = self::OUTPUT_DIR . '/' . $slug;
        }
        \APF\Utilities\FileSystem::ensureDir($dir);
        $path = $dir . '/index.html';
        \APF\Utilities\FileSystem::write($path, $html);
        self::$changedFiles[] = $path;
    }

    private static function renderPage(string $slug, string $content, array $meta = []): string {
        $tplPath = self::$themeDir . '/theme.html';
        if (!file_exists($tplPath)) {
            $tplPath = 'themes/AP-Default/theme.html';
        }
        $tpl = \APF\Utilities\FileSystem::read($tplPath);
        if ($tpl === false) {
            $msg = "テンプレート読み込みエラー: {$tplPath}";
            self::$warnings[] = $msg;
            return '<!-- StaticService: ' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . ' -->';
        }

        $context = \ASG\Template\ThemeService::buildStaticContext($slug, $content, self::$settings, $meta);
        $html = \ASG\Template\TemplateService::render($tpl, $context, dirname($tplPath));
        return self::rewriteAssetPaths($html);
    }

    private static function rewriteAssetPaths(string $html): string {
        $theme = self::$settings['themeSelect'] ?? 'AP-Default';

        $html = str_replace(
            'href="themes/' . $theme . '/style.css"',
            'href="/assets/style.css"',
            $html
        );
        $html = str_replace(
            "href='themes/" . $theme . "/style.css'",
            "href='/assets/style.css'",
            $html
        );
        $html = preg_replace(
            '#(src=["\'])uploads/#',
            '$1/assets/uploads/',
            $html
        ) ?? $html;

        return $html;
    }

    /* ══════════════════════════════════════════════
       コレクション一覧ページ生成
       ══════════════════════════════════════════════ */

    private static function buildCollectionIndexes(): int {
        if (!\ACE\Core\CollectionService::isEnabled()) return 0;

        $built = 0;
        $collections = \ACE\Core\CollectionService::listCollections();

        foreach ($collections as $col) {
            $name = $col['name'];
            if ($name === 'pages') continue;

            $items = \ACE\Core\CollectionService::getItems($name);
            $perPage = (int)($col['perPage'] ?? 10);
            if ($perPage < 1) $perPage = 10;

            $allItems = array_values(array_map(function($slug, $item) use ($name) {
                $preview = strip_tags($item['html']);
                if (mb_strlen($preview, 'UTF-8') > 200) {
                    $preview = mb_substr($preview, 0, 200, 'UTF-8') . '...';
                }
                return array_merge($item['meta'], [
                    'slug'    => $slug,
                    'preview' => $preview,
                    'link'    => '/' . $name . '/' . $slug . '/',
                    'html'    => $item['html'],
                ]);
            }, array_keys($items), array_values($items)));

            $totalItems = count($allItems);
            $totalPages = max(1, (int)ceil($totalItems / $perPage));

            for ($page = 1; $page <= $totalPages; $page++) {
                $offset = ($page - 1) * $perPage;
                $pageItems = array_slice($allItems, $offset, $perPage);

                $pagination = [
                    'current_page' => $page,
                    'total_pages'  => $totalPages,
                    'per_page'     => $perPage,
                    'total_items'  => $totalItems,
                    'has_prev'     => $page > 1,
                    'has_next'     => $page < $totalPages,
                    'prev_url'     => $page === 2 ? '/' . $name . '/' : '/' . $name . '/page/' . ($page - 1) . '/',
                    'next_url'     => '/' . $name . '/page/' . ($page + 1) . '/',
                ];

                $indexContent = self::renderCollectionIndex($col, $pageItems, $pagination, $allItems);

                if ($page === 1) {
                    $slug = $name;
                    $dir = self::OUTPUT_DIR . '/' . $slug;
                } else {
                    $slug = $name . '/page/' . $page;
                    $dir = self::OUTPUT_DIR . '/' . $slug;
                }
                \APF\Utilities\FileSystem::ensureDir($dir);
                $path = $dir . '/index.html';
                $meta = ['title' => ($col['label'] ?? $name) . ($page > 1 ? ' - ' . $page . 'ページ' : '')];
                $rendered = self::renderPage($slug, $indexContent, $meta);
                if (!empty(self::$settings['minify'] ?? true)) {
                    $rendered = self::minifyHtml($rendered);
                }
                \APF\Utilities\FileSystem::write($path, $rendered);
                self::$changedFiles[] = $path;
                $built++;
            }
        }
        return $built;
    }

    private static function renderCollectionIndex(array $col, array $pageItems, array $pagination = [], array $allItems = []): string {
        $name = $col['name'];
        $template = $col['template'] ?? null;

        $customTpl = self::$themeDir . '/collection-' . $name . '-index.html';
        if ($template) {
            $customTpl = self::$themeDir . '/' . $template . '-index.html';
        }

        $allTags = self::collectTags($allItems ?: $pageItems);

        if (file_exists($customTpl)) {
            $tplContent = \APF\Utilities\FileSystem::read($customTpl);
            if ($tplContent !== false) {
                $ctx = array_merge(
                    \ASG\Template\ThemeService::buildStaticContext($name, '', self::$settings, ['title' => $col['label'] ?? $name]),
                    [
                        'collection_name'  => $name,
                        'collection_label' => $col['label'] ?? $name,
                        'items'            => $pageItems,
                        'item_count'       => count($pageItems),
                        'pagination'       => $pagination,
                        'all_tags'         => $allTags,
                    ]
                );
                return \ASG\Template\TemplateService::render($tplContent, $ctx, self::$themeDir);
            }
        }

        /* デフォルトテンプレート */
        $label = htmlspecialchars($col['label'] ?? $name, ENT_QUOTES, 'UTF-8');
        $html  = "<h1>{$label}</h1>\n<div class=\"ap-collection-index\">\n";

        foreach ($pageItems as $item) {
            $slug    = $item['slug'] ?? '';
            $title   = htmlspecialchars($item['title'] ?? $slug, ENT_QUOTES, 'UTF-8');
            $date    = htmlspecialchars($item['date'] ?? '', ENT_QUOTES, 'UTF-8');
            $preview = htmlspecialchars($item['preview'] ?? '', ENT_QUOTES, 'UTF-8');
            $link    = $item['link'] ?? ('/' . $name . '/' . $slug . '/');

            $html .= "<article class=\"ap-collection-entry\">\n";
            $html .= "  <h2><a href=\"{$link}\">{$title}</a></h2>\n";
            if ($date) $html .= "  <time>{$date}</time>\n";
            $html .= "  <p>{$preview}</p>\n";
            $html .= "</article>\n";
        }

        $html .= "</div>\n";

        if (!empty($pagination) && $pagination['total_pages'] > 1) {
            $html .= "<nav class=\"ap-pagination\">\n";
            if ($pagination['has_prev']) {
                $html .= '  <a href="' . htmlspecialchars($pagination['prev_url'], ENT_QUOTES, 'UTF-8') . '" class="ap-pagination-prev">&laquo; 前</a>' . "\n";
            }
            $html .= '  <span class="ap-pagination-info">' . $pagination['current_page'] . ' / ' . $pagination['total_pages'] . '</span>' . "\n";
            if ($pagination['has_next']) {
                $html .= '  <a href="' . htmlspecialchars($pagination['next_url'], ENT_QUOTES, 'UTF-8') . '" class="ap-pagination-next">次 &raquo;</a>' . "\n";
            }
            $html .= "</nav>\n";
        }

        return $html;
    }

    /**
     * 個別コレクションアイテムをカスタムテンプレートでレンダリング
     */
    private static function renderPageWithCollectionTemplate(string $slug, string $content): ?string {
        if (!str_contains($slug, '/')) return null;
        $parts = explode('/', $slug, 2);
        $colName = $parts[0];
        $itemSlug = $parts[1];

        $def = \ACE\Core\CollectionService::getCollectionDef($colName);
        if ($def === null) return null;

        $template = $def['template'] ?? null;
        $singleTpl = self::$themeDir . '/collection-' . $colName . '-single.html';
        if ($template) {
            $singleTpl = self::$themeDir . '/' . $template . '-single.html';
        }
        if (!file_exists($singleTpl)) return null;

        $tplContent = \APF\Utilities\FileSystem::read($singleTpl);
        if ($tplContent === false) return null;

        $item = \ACE\Core\CollectionService::getItem($colName, $itemSlug);
        $meta = $item ? $item['meta'] : [];

        $prevNext = self::getPrevNextItems($colName, $itemSlug);

        $ctx = array_merge(
            \ASG\Template\ThemeService::buildStaticContext($slug, $content, self::$settings, $meta),
            $meta,
            [
                'collection_name'  => $colName,
                'collection_label' => $def['label'] ?? $colName,
                'item_slug'        => $itemSlug,
                'prev_item'        => $prevNext['prev'],
                'next_item'        => $prevNext['next'],
            ]
        );
        return \ASG\Template\TemplateService::render($tplContent, $ctx, self::$themeDir);
    }

    /**
     * 前後アイテムを取得（日付降順）
     */
    private static function getPrevNextItems(string $colName, string $currentSlug): array {
        $items = \ACE\Core\CollectionService::getItems($colName);
        $slugs = array_keys($items);
        $idx = array_search($currentSlug, $slugs, true);
        $result = ['prev' => null, 'next' => null];
        if ($idx === false) return $result;

        if ($idx > 0) {
            $prevSlug = $slugs[$idx - 1];
            $prevItem = $items[$prevSlug];
            $result['prev'] = [
                'slug'  => $prevSlug,
                'title' => $prevItem['meta']['title'] ?? $prevSlug,
                'url'   => '/' . $colName . '/' . $prevSlug . '/',
                'date'  => $prevItem['meta']['date'] ?? '',
            ];
        }
        if ($idx < count($slugs) - 1) {
            $nextSlug = $slugs[$idx + 1];
            $nextItem = $items[$nextSlug];
            $result['next'] = [
                'slug'  => $nextSlug,
                'title' => $nextItem['meta']['title'] ?? $nextSlug,
                'url'   => '/' . $colName . '/' . $nextSlug . '/',
                'date'  => $nextItem['meta']['date'] ?? '',
            ];
        }
        return $result;
    }

    /* ══════════════════════════════════════════════
       SEO: sitemap.xml / robots.txt
       ══════════════════════════════════════════════ */

    private static function generateSitemap(): void {
        $baseUrl = rtrim(self::$settings['site_url'] ?? '', '/');
        if ($baseUrl === '') {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $proto . '://' . $host;
        }

        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach (self::$pages as $slug => $content) {
            if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
            $loc = $baseUrl . '/' . ($slug === 'index' ? '' : $slug . '/');
            $xml .= "  <url><loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc></url>\n";
        }

        if (\ACE\Core\CollectionService::isEnabled()) {
            $collections = \ACE\Core\CollectionService::listCollections();
            foreach ($collections as $col) {
                if ($col['name'] === 'pages') continue;
                $colName = $col['name'];
                $xml .= "  <url><loc>" . htmlspecialchars($baseUrl . '/' . $colName . '/', ENT_XML1, 'UTF-8') . "</loc></url>\n";

                $items = \ACE\Core\CollectionService::getItems($colName);
                $perPage = (int)($col['perPage'] ?? 10);
                $totalPages = max(1, (int)ceil(count($items) / max(1, $perPage)));
                for ($p = 2; $p <= $totalPages; $p++) {
                    $xml .= "  <url><loc>" . htmlspecialchars($baseUrl . '/' . $colName . '/page/' . $p . '/', ENT_XML1, 'UTF-8') . "</loc></url>\n";
                }

                $tagSlugs = [];
                foreach ($items as $item) {
                    $tags = $item['meta']['tags'] ?? [];
                    if (is_string($tags)) $tags = array_map('trim', explode(',', $tags));
                    foreach ($tags as $tag) {
                        $tag = trim($tag);
                        if ($tag === '') continue;
                        $ts_ = preg_replace('/[^a-z0-9\-_\p{L}\p{N}]/u', '', mb_convert_case(str_replace(' ', '-', $tag), MB_CASE_LOWER, 'UTF-8'));
                        if ($ts_ !== '' && !str_contains($ts_, '..')) $tagSlugs[$ts_] = true;
                    }
                }
                if ($tagSlugs) {
                    $xml .= "  <url><loc>" . htmlspecialchars($baseUrl . '/' . $colName . '/tags/', ENT_XML1, 'UTF-8') . "</loc></url>\n";
                    foreach (array_keys($tagSlugs) as $ts) {
                        $xml .= "  <url><loc>" . htmlspecialchars($baseUrl . '/' . $colName . '/tag/' . $ts . '/', ENT_XML1, 'UTF-8') . "</loc></url>\n";
                    }
                }
            }
        }

        $xml .= "</urlset>\n";

        \APF\Utilities\FileSystem::ensureDir(self::OUTPUT_DIR);
        \APF\Utilities\FileSystem::write(self::OUTPUT_DIR . '/sitemap.xml', $xml);
    }

    private static function generateRobotsTxt(): void {
        $baseUrl = rtrim(self::$settings['site_url'] ?? '', '/');
        $content = "User-agent: *\nAllow: /\n";
        if ($baseUrl !== '') {
            $sitemapUrl = $baseUrl . '/sitemap.xml';
            $content .= "Sitemap: {$sitemapUrl}\n";
        }
        \APF\Utilities\FileSystem::ensureDir(self::OUTPUT_DIR);
        \APF\Utilities\FileSystem::write(self::OUTPUT_DIR . '/robots.txt', $content);
    }

    /* ══════════════════════════════════════════════
       タグページ生成
       ══════════════════════════════════════════════ */

    private static function buildTagPages(): int {
        if (!\ACE\Core\CollectionService::isEnabled()) return 0;

        $built = 0;
        $collections = \ACE\Core\CollectionService::listCollections();

        foreach ($collections as $col) {
            $name = $col['name'];
            if ($name === 'pages') continue;

            $items = \ACE\Core\CollectionService::getItems($name);
            $tagMap = [];
            foreach ($items as $slug => $item) {
                $tags = $item['meta']['tags'] ?? [];
                if (is_string($tags)) $tags = array_map('trim', explode(',', $tags));
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    if ($tag === '') continue;
                    $tagSlug = mb_convert_case(str_replace(' ', '-', $tag), MB_CASE_LOWER, 'UTF-8');
                    $tagSlug = preg_replace('/[^a-z0-9\-_\p{L}\p{N}]/u', '', $tagSlug);
                    if ($tagSlug === '' || str_contains($tagSlug, '..')) continue;
                    if (!isset($tagMap[$tagSlug])) {
                        $tagMap[$tagSlug] = ['name' => $tag, 'items' => []];
                    }
                    $preview = strip_tags($item['html']);
                    if (mb_strlen($preview, 'UTF-8') > 200) {
                        $preview = mb_substr($preview, 0, 200, 'UTF-8') . '...';
                    }
                    $tagMap[$tagSlug]['items'][] = array_merge($item['meta'], [
                        'slug'    => $slug,
                        'preview' => $preview,
                        'link'    => '/' . $name . '/' . $slug . '/',
                    ]);
                }
            }

            if (empty($tagMap)) continue;

            $tagTplPath = self::$themeDir . '/collection-' . $name . '-tag.html';
            $tagTpl = file_exists($tagTplPath) ? \APF\Utilities\FileSystem::read($tagTplPath) : null;

            $allTags = [];
            foreach ($tagMap as $tagSlug => $tagData) {
                $allTags[] = [
                    'name'  => $tagData['name'],
                    'slug'  => $tagSlug,
                    'count' => count($tagData['items']),
                    'url'   => '/' . $name . '/tag/' . $tagSlug . '/',
                ];
            }

            foreach ($tagMap as $tagSlug => $tagData) {
                $pageSlug = $name . '/tag/' . $tagSlug;
                $dir = self::OUTPUT_DIR . '/' . $pageSlug;
                \APF\Utilities\FileSystem::ensureDir($dir);

                if ($tagTpl !== null) {
                    $ctx = array_merge(
                        \ASG\Template\ThemeService::buildStaticContext($pageSlug, '', self::$settings, [
                            'title' => $tagData['name'] . ' - ' . ($col['label'] ?? $name),
                        ]),
                        [
                            'collection_name'  => $name,
                            'collection_label' => $col['label'] ?? $name,
                            'tag_name'         => $tagData['name'],
                            'tag_slug'         => $tagSlug,
                            'tag_items'        => $tagData['items'],
                            'item_count'       => count($tagData['items']),
                            'all_tags'         => $allTags,
                        ]
                    );
                    $html = \ASG\Template\TemplateService::render($tagTpl, $ctx, self::$themeDir);
                } else {
                    $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
                    $html = '<h1>' . $esc($tagData['name']) . '</h1>' . "\n";
                    $html .= '<p class="ap-tag-count">' . count($tagData['items']) . ' 件</p>' . "\n";
                    $html .= '<div class="ap-collection-index">' . "\n";
                    foreach ($tagData['items'] as $ti) {
                        $html .= '<article class="ap-collection-entry">' . "\n";
                        $html .= '  <h2><a href="' . $esc($ti['link']) . '">' . $esc($ti['title'] ?? $ti['slug']) . '</a></h2>' . "\n";
                        if (!empty($ti['date'])) $html .= '  <time>' . $esc($ti['date']) . '</time>' . "\n";
                        $html .= '  <p>' . $esc($ti['preview']) . '</p>' . "\n";
                        $html .= '</article>' . "\n";
                    }
                    $html .= '</div>' . "\n";
                }

                $rendered = self::renderPage($pageSlug, $html, ['title' => $tagData['name']]);
                if (!empty(self::$settings['minify'] ?? true)) {
                    $rendered = self::minifyHtml($rendered);
                }
                \APF\Utilities\FileSystem::write($dir . '/index.html', $rendered);
                self::$changedFiles[] = $dir . '/index.html';
                $built++;
            }

            /* タグ一覧ページ */
            $tagsIndexSlug = $name . '/tags';
            $tagsDir = self::OUTPUT_DIR . '/' . $tagsIndexSlug;
            \APF\Utilities\FileSystem::ensureDir($tagsDir);

            $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
            $tagsHtml = '<h1>' . $esc($col['label'] ?? $name) . ' — タグ一覧</h1>' . "\n";
            $tagsHtml .= '<div class="ap-tags-list">' . "\n";
            usort($allTags, fn($a, $b) => $b['count'] - $a['count']);
            foreach ($allTags as $t) {
                $tagsHtml .= '<a href="' . $esc($t['url']) . '" class="ap-tag-badge">' . $esc($t['name']) . ' <span>(' . $t['count'] . ')</span></a> ' . "\n";
            }
            $tagsHtml .= '</div>' . "\n";

            $rendered = self::renderPage($tagsIndexSlug, $tagsHtml, ['title' => ($col['label'] ?? $name) . ' タグ一覧']);
            if (!empty(self::$settings['minify'] ?? true)) {
                $rendered = self::minifyHtml($rendered);
            }
            \APF\Utilities\FileSystem::write($tagsDir . '/index.html', $rendered);
            self::$changedFiles[] = $tagsDir . '/index.html';
            $built++;
        }
        return $built;
    }

    private static function collectTags(array $items): array {
        $tags = [];
        foreach ($items as $item) {
            $itemTags = $item['tags'] ?? [];
            if (is_string($itemTags)) $itemTags = array_map('trim', explode(',', $itemTags));
            foreach ($itemTags as $tag) {
                $tag = trim($tag);
                if ($tag === '') continue;
                $tagSlug = mb_convert_case(str_replace(' ', '-', $tag), MB_CASE_LOWER, 'UTF-8');
                $tagSlug = preg_replace('/[^a-z0-9\-_\p{L}\p{N}]/u', '', $tagSlug);
                if ($tagSlug === '' || str_contains($tagSlug, '..')) continue;
                if (!isset($tags[$tagSlug])) {
                    $tags[$tagSlug] = ['name' => $tag, 'slug' => $tagSlug, 'count' => 0];
                }
                $tags[$tagSlug]['count']++;
            }
        }
        return array_values($tags);
    }

    /* ══════════════════════════════════════════════
       404 エラーページ
       ══════════════════════════════════════════════ */

    private static function generate404Page(): void {
        $customTpl = self::$themeDir . '/404.html';
        if (file_exists($customTpl)) {
            $tplContent = \APF\Utilities\FileSystem::read($customTpl);
            if ($tplContent !== false) {
                $ctx = \ASG\Template\ThemeService::buildStaticContext('404', '', self::$settings, ['title' => 'ページが見つかりません']);
                $html = \ASG\Template\TemplateService::render($tplContent, $ctx, self::$themeDir);
            } else {
                $html = self::renderPage('404', '<h1>404 — ページが見つかりません</h1><p>お探しのページは存在しないか、移動されました。</p>', ['title' => '404']);
            }
        } else {
            $html = self::renderPage('404', '<h1>404 — ページが見つかりません</h1><p>お探しのページは存在しないか、移動されました。</p><p><a href="/">トップページへ</a></p>', ['title' => '404']);
        }

        if (!empty(self::$settings['minify'] ?? true)) {
            $html = self::minifyHtml($html);
        }
        \APF\Utilities\FileSystem::ensureDir(self::OUTPUT_DIR);
        \APF\Utilities\FileSystem::write(self::OUTPUT_DIR . '/404.html', $html);
        self::$changedFiles[] = self::OUTPUT_DIR . '/404.html';
    }

    /* ══════════════════════════════════════════════
       クライアントサイド検索インデックス
       ══════════════════════════════════════════════ */

    private static function generateSearchIndex(): void {
        $index = [];

        foreach (self::$pages as $slug => $content) {
            if (!preg_match(self::SLUG_PATTERN, $slug)) continue;
            $text = strip_tags($content);
            $text = preg_replace('/\s+/', ' ', trim($text));

            $entry = [
                'slug'  => $slug,
                'title' => $slug,
                'body'  => mb_substr($text, 0, 500, 'UTF-8'),
                'url'   => '/' . ($slug === 'index' ? '' : $slug . '/'),
            ];

            if (str_contains($slug, '/')) {
                $parts = explode('/', $slug, 2);
                $item = \ACE\Core\CollectionService::getItem($parts[0], $parts[1]);
                if ($item) {
                    $status = $item['meta']['status'] ?? 'published';
                    if (!empty($item['meta']['draft']) || $status === 'draft' || $status === 'archived') {
                        continue;
                    }
                    $entry['title'] = $item['meta']['title'] ?? $parts[1];
                    $entry['tags']  = $item['meta']['tags'] ?? [];
                    $entry['date']  = $item['meta']['date'] ?? '';
                }
            }
            $index[] = $entry;
        }

        \APF\Utilities\FileSystem::ensureDir(self::OUTPUT_DIR);
        \APF\Utilities\FileSystem::write(
            self::OUTPUT_DIR . '/search-index.json',
            json_encode($index, JSON_UNESCAPED_UNICODE)
        );
        self::$changedFiles[] = self::OUTPUT_DIR . '/search-index.json';

        $searchJs = 'Framework/AP/JsEngine/ap-search.js';
        if (file_exists($searchJs)) {
            $dst = self::OUTPUT_DIR . '/assets/ap-search.js';
            \APF\Utilities\FileSystem::ensureDir(dirname($dst));
            copy($searchJs, $dst);
        }
    }

    /* ══════════════════════════════════════════════
       HTML / CSS ミニファイ
       ══════════════════════════════════════════════ */

    public static function minifyHtml(string $html): string {
        $protected = [];
        $html = preg_replace_callback(
            '#(<(pre|code|script|style|textarea)\b[^>]*>)(.*?)(</\2>)#si',
            function($m) use (&$protected) {
                $key = "\x00AP_PROTECT_" . count($protected) . "\x00";
                $protected[$key] = $m[0];
                return $key;
            },
            $html
        ) ?? $html;

        $html = preg_replace('/<!--(?!\[if\s).*?-->/s', '', $html) ?? $html;
        $html = preg_replace('/\s{2,}/', ' ', $html) ?? $html;
        $html = preg_replace('/>\s+</', '><', $html) ?? $html;

        foreach ($protected as $key => $val) {
            $html = str_replace($key, $val, $html);
        }
        return trim($html);
    }

    public static function minifyCss(string $css): string {
        $calcProtected = [];
        $css = preg_replace_callback('/calc\([^)]+\)/i', function($m) use (&$calcProtected) {
            $key = "\x00AP_CALC_" . count($calcProtected) . "\x00";
            $calcProtected[$key] = $m[0];
            return $key;
        }, $css) ?? $css;
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;
        $css = preg_replace('/\s*([{};:,>~])\s*/', '$1', $css) ?? $css;
        $css = str_replace(';}', '}', $css);
        foreach ($calcProtected as $key => $val) {
            $css = str_replace($key, $val, $css);
        }
        return trim($css);
    }

    /* ══════════════════════════════════════════════
       リダイレクト管理
       ══════════════════════════════════════════════ */

    private static function generateRedirects(): void {
        $redirects = \APF\Utilities\JsonStorage::read(
            'redirects.json',
            \AIS\Core\AppContext::settingsDir()
        );
        if (empty($redirects)) return;

        $lines = [];
        foreach ($redirects as $r) {
            $from = $r['from'] ?? '';
            $to   = $r['to'] ?? '';
            $code = (int)($r['code'] ?? 301);
            if ($from === '' || $to === '') continue;
            if (!in_array($code, [301, 302], true)) $code = 301;
            $from = preg_replace('/[\r\n\x00-\x1f]/', '', $from);
            $to   = preg_replace('/[\r\n\x00-\x1f]/', '', $to);
            if (!str_starts_with($from, '/')) continue;
            $lines[] = "{$from}  {$to}  {$code}";
        }

        \APF\Utilities\FileSystem::ensureDir(self::OUTPUT_DIR);
        if ($lines) {
            \APF\Utilities\FileSystem::write(self::OUTPUT_DIR . '/_redirects', implode("\n", $lines) . "\n");
            self::$changedFiles[] = self::OUTPUT_DIR . '/_redirects';
        }
    }

    /* ══════════════════════════════════════════════
       ビルドフック
       ══════════════════════════════════════════════ */

    private static function runHook(string $hookName, array $context = []): void {
        $hooks = \APF\Utilities\JsonStorage::read(
            'build_hooks.json',
            \AIS\Core\AppContext::settingsDir()
        );
        if (empty($hooks[$hookName])) return;

        foreach ((array)$hooks[$hookName] as $hookFile) {
            if (!is_string($hookFile)) continue;
            $real = realpath($hookFile);
            if ($real === false) continue;
            $projectRoot = realpath('.') ?: '';
            if (!str_starts_with($real, $projectRoot . '/Framework/') && !str_starts_with($real, $projectRoot . '/data/')) {
                self::$warnings[] = "フック拒否（許可外パス）: {$hookFile}";
                continue;
            }
            if (!str_ends_with($real, '.php')) continue;

            try {
                $apHookContext = $context;
                $apHookContext['settings']   = self::$settings;
                $apHookContext['pages']      = array_keys(self::$pages);
                $apHookContext['output_dir'] = self::OUTPUT_DIR;
                $apHookContext['theme_dir']  = self::$themeDir;
                include $real;
            } catch (\Throwable $e) {
                self::$warnings[] = "フックエラー ({$hookFile}): " . $e->getMessage();
            }
        }
    }

    /* ══════════════════════════════════════════════
       内部メソッド — ハッシュ・差分判定
       ══════════════════════════════════════════════ */

    private static function computeSettingsHash(): string {
        $keys = ['title', 'description', 'keywords', 'copyright', 'themeSelect', 'menu', 'subside'];
        $data = [];
        foreach ($keys as $k) {
            $data[$k] = self::$settings[$k] ?? '';
        }
        return md5(json_encode($data));
    }

    private static function computeContentHash(string $slug, string $content, string $settingsHash): string {
        return md5($slug . $content . $settingsHash);
    }

    /* ══════════════════════════════════════════════
       内部メソッド — ビルド状態
       ══════════════════════════════════════════════ */

    private static function loadBuildState(): void {
        self::$buildState = \APF\Utilities\JsonStorage::read(
            self::BUILD_STATE,
            \AIS\Core\AppContext::settingsDir()
        );
        if (empty(self::$buildState['schema_version'])) {
            self::$buildState = ['schema_version' => 1, 'pages' => []];
        }
    }

    private static function saveBuildState(): void {
        \APF\Utilities\JsonStorage::write(
            self::BUILD_STATE,
            self::$buildState,
            \AIS\Core\AppContext::settingsDir()
        );
    }

    /* ══════════════════════════════════════════════
       内部メソッド — ファイル操作
       ══════════════════════════════════════════════ */

    private static function deleteOrphanedFiles(): int {
        $deleted = 0;
        if (!is_dir(self::OUTPUT_DIR)) return 0;

        $topIndex = self::OUTPUT_DIR . '/index.html';
        if (file_exists($topIndex) && !isset(self::$pages['index'])) {
            @unlink($topIndex);
            $deleted++;
        }

        $collectionNames = [];
        if (\ACE\Core\CollectionService::isEnabled()) {
            foreach (\ACE\Core\CollectionService::listCollections() as $col) {
                $collectionNames[$col['name']] = true;
            }
        }
        $dirs = glob(self::OUTPUT_DIR . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            if ($slug === 'assets') continue;
            if (isset($collectionNames[$slug])) continue;
            if (!isset(self::$pages[$slug])) {
                self::removeDir($dir);
                $deleted++;
            }
        }
        return $deleted;
    }

    private static function syncUploads(): void {
        $src = 'uploads';
        $dst = self::OUTPUT_DIR . '/assets/uploads';
        if (!is_dir($src)) return;
        \APF\Utilities\FileSystem::ensureDir($dst);

        $files = glob($src . '/*') ?: [];
        foreach ($files as $f) {
            if (is_dir($f)) continue;
            $base = basename($f);
            if ($base === '.htaccess') continue;
            $target = $dst . '/' . $base;
            if (!file_exists($target) || filemtime($f) > filemtime($target)) {
                copy($f, $target);
            }
        }
    }

    private static function writeStaticHtaccess(): void {
        $htaccess = self::OUTPUT_DIR . '/.htaccess';
        $content = "Options -Indexes -ExecCGI\n\n"
            . "# PHP 実行禁止\n"
            . "<FilesMatch \"\\.php$\">\n"
            . "    Require all denied\n"
            . "</FilesMatch>\n\n";

        $redirects = \APF\Utilities\JsonStorage::read(
            'redirects.json',
            \AIS\Core\AppContext::settingsDir()
        );
        if (!empty($redirects)) {
            $content .= "# リダイレクト\n";
            foreach ($redirects as $r) {
                $from = $r['from'] ?? '';
                $to   = $r['to'] ?? '';
                $code = (int)($r['code'] ?? 301);
                if ($from === '' || $to === '') continue;
                if (!in_array($code, [301, 302], true)) $code = 301;
                $from = preg_replace('/[\r\n\x00-\x1f]/', '', $from);
                $to   = preg_replace('/[\r\n\x00-\x1f]/', '', $to);
                if (!str_starts_with($from, '/')) continue;
                $content .= "Redirect {$code} {$from} {$to}\n";
            }
            $content .= "\n";
        }

        $content .= "ErrorDocument 404 /404.html\n";

        \APF\Utilities\FileSystem::write($htaccess, $content);
    }

    private static function removeDir(string $dir): void {
        $real = realpath($dir);
        if ($real === false) return;
        $projectRoot = realpath('.');
        if ($projectRoot === false || !str_starts_with($real, $projectRoot)) return;

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($real, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $f) {
            if ($f->isDir()) {
                if (!@rmdir($f->getRealPath())) {
                    \AIS\System\DiagnosticsManager::log('engine', 'StaticService ディレクトリ削除失敗', ['path' => basename($f->getRealPath())]);
                }
            } else {
                if (!@unlink($f->getRealPath())) {
                    \AIS\System\DiagnosticsManager::log('engine', 'StaticService ファイル削除失敗', ['path' => basename($f->getRealPath())]);
                }
            }
        }
        @rmdir($real);
    }

    private static function addDirToZip(\ZipArchive $zip, string $dir, string $base): void {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $item) {
            $rel = substr($item->getPathname(), strlen($base) + 1);
            if ($item->isDir()) {
                $zip->addEmptyDir($rel);
            } else {
                $zip->addFile($item->getPathname(), $rel);
            }
        }
    }

    /** 初期化済みであることを保証 */
    private static function ensureInit(): void {
        if (!self::$initialized) {
            self::init();
        }
    }
}
