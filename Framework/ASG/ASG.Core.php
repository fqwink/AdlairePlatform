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
