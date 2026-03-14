<?php
/**
 * Adlaire Static Generator (ASG) - Template Module
 *
 * ASG = Adlaire Static Generator
 *
 * テンプレートレンダリング、テーマ管理、Markdown パースを提供する。
 * Adlaire Platform の TemplateEngine / MarkdownEngine から
 * 抽出・汎用化した独立フレームワーク。
 *
 * @package ASG
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace ASG\Template;

// ============================================================================
// TemplateRenderer - Handlebars ライクテンプレートエンジン
// ============================================================================

/**
 * Handlebars ライクな構文をサポートする軽量テンプレートエンジン。
 *
 * 対応構文:
 *   {{variable}}              → htmlspecialchars でエスケープ出力
 *   {{{variable}}}            → 生 HTML 出力（エスケープなし）
 *   {{user.name}}             → ドット記法によるネストプロパティアクセス
 *   {{var|filter}}            → フィルター適用
 *   {{#if var}}...{{else}}...{{/if}}  → 条件分岐（!var で否定対応）
 *   {{#each items}}...{{/each}}       → ループ（@index, @first, @last 使用可）
 *   {{> partial}}             → パーシャル（部分テンプレート）の展開
 */
class TemplateRenderer {
    /** @var array<string, callable> 登録済みカスタムヘルパー */
    private array $helpers = [];

    /** @var array<string, string> 登録済みパーシャルテンプレート */
    private array $partials = [];

    /** @var int パーシャル展開の最大ネスト深度 */
    private const PARTIAL_MAX_DEPTH = 10;

    /** @var int 現在のパーシャルネスト深度 */
    private int $partialDepth = 0;

    /**
     * テンプレートをレンダリングする
     *
     * テンプレート文字列にコンテキスト変数を適用し、完成した HTML を返す。
     * 処理順序: パーシャル → each → if → 生変数 → エスケープ変数
     *
     * @param string $template テンプレート文字列
     * @param array  $context  コンテキスト変数の配列
     * @return string レンダリング済み HTML
     */
    public function render(string $template, array $context): string {
        $this->partialDepth = 0;

        $html = $this->processPartials($template, $context);
        $html = $this->processEach($html, $context);
        $html = $this->processIf($html, $context);
        $html = $this->processRawVars($html, $context);
        $html = $this->processVars($html, $context);

        return $html;
    }

    /**
     * カスタムヘルパー関数を登録する
     *
     * ヘルパーはフィルターとして使用可能: {{var|helperName}}
     *
     * @param string   $name ヘルパー名
     * @param callable $fn   ヘルパー関数 fn(string $value, string $arg): string
     */
    public function registerHelper(string $name, callable $fn): void {
        $this->helpers[$name] = $fn;
    }

    /**
     * パーシャル（部分テンプレート）を登録する
     *
     * 登録されたパーシャルは {{> name}} 構文で展開される。
     *
     * @param string $name     パーシャル名
     * @param string $template パーシャルのテンプレート文字列
     */
    public function registerPartial(string $name, string $template): void {
        $this->partials[$name] = $template;
    }

    // ========================================================================
    // パーシャル処理
    // ========================================================================

    /**
     * {{> partial}} を展開する
     *
     * 登録済みパーシャルを再帰的に展開する。
     * 循環参照防止のため最大深度を制限する。
     *
     * @param string $tpl テンプレート文字列
     * @param array  $ctx コンテキスト
     * @return string 展開後のテンプレート
     */
    private function processPartials(string $tpl, array $ctx): string {
        return preg_replace_callback(
            '/\{\{>\s*(\w+)\s*\}\}/',
            function (array $m) use ($ctx): string {
                $name = $m[1];

                if ($this->partialDepth >= self::PARTIAL_MAX_DEPTH) {
                    return '';
                }

                if (!isset($this->partials[$name])) {
                    return '';
                }

                $this->partialDepth++;
                $content = $this->processPartials($this->partials[$name], $ctx);
                $this->partialDepth--;

                return $content;
            },
            $tpl
        ) ?? $tpl;
    }

    // ========================================================================
    // {{#each}} ループ処理
    // ========================================================================

    /**
     * {{#each items}}...{{/each}} を処理する
     *
     * ループ内では以下の特殊変数が使用可能:
     *   @index  - 現在のインデックス（0始まり）
     *   @first  - 最初の要素の場合 true
     *   @last   - 最後の要素の場合 true
     *   this    - スカラー値の要素自体
     *
     * ネストされた {{#each}} にもバランスドマッチングで対応。
     *
     * @param string $tpl テンプレート文字列
     * @param array  $ctx コンテキスト
     * @return string 処理後のテンプレート
     */
    private function processEach(string $tpl, array $ctx): string {
        $offset = 0;

        while (preg_match('/\{\{#each\s+(\w+)\}\}/s', $tpl, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $tagStart = $m[0][1];
            $tagEnd   = $tagStart + strlen($m[0][0]);
            $key      = $m[1][0];

            /* バランスドマッチングで閉じタグを検出 */
            $closeEnd = $this->findClosingTag($tpl, $tagEnd, '#each\s+\w+', '/each');
            if ($closeEnd === null) {
                $offset = $tagEnd;
                continue;
            }

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

                foreach ($items as $i => $item) {
                    $loopCtx = $ctx;
                    $loopCtx['@index'] = $i;
                    $loopCtx['@first'] = ($i === 0);
                    $loopCtx['@last']  = ($i === $count - 1);

                    if (is_array($item)) {
                        foreach ($item as $ik => $iv) {
                            $loopCtx[$ik] = $iv;
                        }
                    } else {
                        $loopCtx['this'] = $item;
                    }

                    $rendered = $this->processEach($body, $loopCtx);
                    $rendered = $this->processIf($rendered, $loopCtx);
                    $rendered = $this->processRawVars($rendered, $loopCtx);
                    $rendered = $this->processVars($rendered, $loopCtx);
                    $out .= $rendered;
                }
                $replacement = $out;
            }

            $tpl    = substr($tpl, 0, $tagStart) . $replacement . substr($tpl, $closeEnd);
            $offset = $tagStart + strlen($replacement);
        }

        return $tpl;
    }

    // ========================================================================
    // {{#if}} 条件分岐処理
    // ========================================================================

    /**
     * {{#if var}}...{{else}}...{{/if}} を処理する
     *
     * 条件式の先頭に ! を付けることで否定条件に対応。
     * ネストされた {{#if}} にもバランスドマッチングで対応。
     *
     * @param string $tpl テンプレート文字列
     * @param array  $ctx コンテキスト
     * @return string 処理後のテンプレート
     */
    private function processIf(string $tpl, array $ctx): string {
        $offset = 0;

        while (preg_match('/\{\{#if\s+(!?[\w@][\w@.]*)\}\}/s', $tpl, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $tagStart = $m[0][1];
            $tagEnd   = $tagStart + strlen($m[0][0]);
            $key      = $m[1][0];

            $closeEnd = $this->findClosingTag($tpl, $tagEnd, '#if\s+!?[\w@][\w@.]*', '/if');
            if ($closeEnd === null) {
                $offset = $tagEnd;
                continue;
            }

            $closeTagLen = strlen('{{/if}}');
            $closeStart = $closeEnd - $closeTagLen;
            $innerContent = substr($tpl, $tagEnd, $closeStart - $tagEnd);

            /* 同じネストレベルの {{else}} を検索 */
            $elsePos = $this->findElseTag($innerContent);

            /* 否定演算子の処理 */
            $negate = false;
            if (str_starts_with($key, '!')) {
                $negate = true;
                $key = substr($key, 1);
            }

            $truthy = !empty($this->resolveValue($key, $ctx));
            if ($negate) {
                $truthy = !$truthy;
            }

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

    // ========================================================================
    // 変数置換処理
    // ========================================================================

    /**
     * {{{var}}} → エスケープなしの生 HTML 出力を処理する
     *
     * @param string $tpl テンプレート文字列
     * @param array  $ctx コンテキスト
     * @return string 処理後のテンプレート
     */
    private function processRawVars(string $tpl, array $ctx): string {
        return preg_replace_callback(
            '/\{\{\{([\w@][\w@.]*)\}\}\}/',
            fn(array $m): string => (string)($this->resolveValue($m[1], $ctx) ?? ''),
            $tpl
        ) ?? $tpl;
    }

    /**
     * {{var}} / {{var|filter}} → htmlspecialchars でエスケープ出力を処理する
     *
     * フィルターはパイプ (|) で連結可能: {{var|upper|truncate:50}}
     *
     * @param string $tpl テンプレート文字列
     * @param array  $ctx コンテキスト
     * @return string 処理後のテンプレート
     */
    private function processVars(string $tpl, array $ctx): string {
        return preg_replace_callback(
            '/\{\{([\w@][\w@.]*(?:\|[\w:]+)*)\}\}/',
            function (array $m) use ($ctx): string {
                $expr = $m[1];

                /* フィルターの分離 */
                $parts = explode('|', $expr);
                $key = array_shift($parts);
                $value = (string)($this->resolveValue($key, $ctx) ?? '');

                /* フィルター適用 */
                foreach ($parts as $filter) {
                    $value = $this->applyFilter($value, $filter);
                }

                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            },
            $tpl
        ) ?? $tpl;
    }

    // ========================================================================
    // ネストプロパティ解決
    // ========================================================================

    /**
     * ドット記法でコンテキスト内の値を解決する
     *
     * 例: "user.name" → $ctx['user']['name']
     *
     * @param string $key ドット区切りのキー
     * @param array  $ctx コンテキスト
     * @return mixed 解決された値。見つからない場合は null
     */
    private function resolveValue(string $key, array $ctx): mixed {
        /* 単純キー（ドットなし）: 高速パス */
        if (!str_contains($key, '.')) {
            return $ctx[$key] ?? null;
        }

        /* ドット記法: ネストされた配列を辿る */
        $segments = explode('.', $key);
        $current = $ctx;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }
        return $current;
    }

    // ========================================================================
    // フィルター処理
    // ========================================================================

    /**
     * フィルターを適用する
     *
     * 組み込みフィルター:
     *   upper       - 大文字に変換
     *   lower       - 小文字に変換
     *   truncate:N  - N 文字で切り詰め + "..."
     *   default:val - 空値の場合にデフォルト値を使用
     *   nl2br       - 改行を <br> に変換
     *   date:format - 日付フォーマット
     *   escape      - HTML エスケープ（二重エスケープ防止付き）
     *
     * 登録済みカスタムヘルパーも使用可能。
     *
     * @param string $value  フィルタ対象の値
     * @param string $filter フィルター指定（名前:引数 形式）
     * @return string フィルター適用後の値
     */
    private function applyFilter(string $value, string $filter): string {
        /* フィルター名と引数を分離 */
        $colonPos = strpos($filter, ':');
        if ($colonPos !== false) {
            $name = substr($filter, 0, $colonPos);
            $arg  = substr($filter, $colonPos + 1);
        } else {
            $name = $filter;
            $arg  = '';
        }

        /* カスタムヘルパーを優先検索 */
        if (isset($this->helpers[$name])) {
            return ($this->helpers[$name])($value, $arg);
        }

        /* 組み込みフィルター */
        return match ($name) {
            'upper'    => mb_strtoupper($value, 'UTF-8'),
            'lower'    => mb_strtolower($value, 'UTF-8'),
            'truncate' => $this->filterTruncate($value, (int)($arg ?: 100)),
            'default'  => ($value === '') ? $arg : $value,
            'nl2br'    => nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')),
            'date'     => $this->filterDate($value, $arg ?: 'Y-m-d'),
            'escape'   => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            default    => $value,
        };
    }

    /**
     * truncate フィルター: 指定文字数で切り詰め
     *
     * @param string $value  対象文字列
     * @param int    $length 最大文字数
     * @return string 切り詰め後の文字列
     */
    private function filterTruncate(string $value, int $length): string {
        if (mb_strlen($value, 'UTF-8') <= $length) {
            return $value;
        }
        return mb_substr($value, 0, $length, 'UTF-8') . '...';
    }

    /**
     * date フィルター: 日付文字列をフォーマットする
     *
     * @param string $value  日付文字列
     * @param string $format PHP の date() フォーマット
     * @return string フォーマット済み日付。パース失敗時は元の値
     */
    private function filterDate(string $value, string $format): string {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }
        return date($format, $timestamp);
    }

    // ========================================================================
    // タグ検索ヘルパー
    // ========================================================================

    /**
     * バランスドマッチングで対応する閉じタグの終了位置を返す
     *
     * @param string $tpl        テンプレート文字列
     * @param int    $startPos   検索開始位置
     * @param string $openSuffix 開きタグの正規表現サフィックス
     * @param string $closeSuffix 閉じタグのサフィックス
     * @return int|null 閉じタグの終了位置。見つからない場合は null
     */
    private function findClosingTag(string $tpl, int $startPos, string $openSuffix, string $closeSuffix): ?int {
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
     * 同じネストレベル（depth=0）の {{else}} 位置を検索する
     *
     * @param string $content 検索対象の文字列
     * @return int|null {{else}} の開始位置。見つからない場合は null
     */
    private function findElseTag(string $content): ?int {
        $depth = 0;
        $pos   = 0;

        while (preg_match('/\{\{(#if\s+!?[\w@][\w@.]*|else|\/if)\}\}/s', $content, $m, PREG_OFFSET_CAPTURE, $pos)) {
            $tag    = $m[1][0];
            $tagPos = $m[0][1];
            $pos    = $tagPos + strlen($m[0][0]);

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
}

// ============================================================================
// ThemeManager - テーマ読み込み・管理
// ============================================================================

/**
 * テーマの読み込み、一覧取得、テンプレートコンテキスト構築を担当する。
 *
 * テーマディレクトリの構成:
 *   themes/
 *     theme-name/
 *       theme.json     - テーマ設定ファイル
 *       template.html  - メインテンプレート
 *       assets/        - CSS, JS, 画像等の静的アセット
 *       partials/      - パーシャルテンプレート
 */
class ThemeManager {
    /** @var string テーマディレクトリのルートパス */
    private readonly string $themesDir;

    /** @var array<string, array> 読み込み済みテーマ設定のキャッシュ */
    private array $loadedThemes = [];

    /**
     * テーママネージャーを初期化
     *
     * @param string $themesDir テーマディレクトリのルートパス
     */
    public function __construct(string $themesDir) {
        $this->themesDir = rtrim($themesDir, '/\\');
    }

    /**
     * テーマ設定を読み込む
     *
     * テーマディレクトリ内の theme.json を読み込み、設定配列を返す。
     * 読み込み済みのテーマはキャッシュから返す。
     *
     * @param string $themeName テーマ名（ディレクトリ名）
     * @return array テーマ設定配列
     *   - name: string         テーマ名
     *   - version: string      バージョン
     *   - author: string       作者名
     *   - description: string  説明
     *   - options: array       テーマ固有のオプション
     * @throws \RuntimeException テーマが見つからない場合
     */
    public function load(string $themeName): array {
        /* キャッシュチェック */
        if (isset($this->loadedThemes[$themeName])) {
            return $this->loadedThemes[$themeName];
        }

        $themeDir = $this->themesDir . '/' . $themeName;
        if (!is_dir($themeDir)) {
            throw new \RuntimeException("テーマが見つかりません: {$themeName}");
        }

        /* theme.json を読み込み */
        $configFile = $themeDir . '/theme.json';
        $config = [];

        if (file_exists($configFile)) {
            $json = file_get_contents($configFile);
            if ($json !== false) {
                $parsed = json_decode($json, true);
                if (is_array($parsed)) {
                    $config = $parsed;
                }
            }
        }

        /* デフォルト値をマージ */
        $config = array_merge([
            'name'        => $themeName,
            'version'     => '1.0.0',
            'author'      => '',
            'description' => '',
            'options'     => [],
        ], $config);

        $config['dir'] = $themeDir;
        $this->loadedThemes[$themeName] = $config;

        return $config;
    }

    /**
     * テーマの HTML テンプレートを取得する
     *
     * テーマディレクトリ内の template.html を読み込んで返す。
     *
     * @param string $themeName テーマ名
     * @return string テンプレート HTML 文字列
     * @throws \RuntimeException テンプレートファイルが見つからない場合
     */
    public function getTemplate(string $themeName): string {
        $themeDir = $this->themesDir . '/' . $themeName;
        $templateFile = $themeDir . '/template.html';

        if (!file_exists($templateFile)) {
            throw new \RuntimeException("テンプレートファイルが見つかりません: {$templateFile}");
        }

        $content = file_get_contents($templateFile);
        if ($content === false) {
            throw new \RuntimeException("テンプレートファイルの読み込みに失敗: {$templateFile}");
        }

        return $content;
    }

    /**
     * 利用可能なテーマの一覧を取得する
     *
     * テーマディレクトリ内のサブディレクトリをスキャンし、
     * 有効なテーマの情報一覧を返す。
     *
     * @return array<array> テーマ情報の配列
     *   各要素: ['name' => string, 'version' => string, 'description' => string, 'hasTemplate' => bool]
     */
    public function listThemes(): array {
        $themes = [];

        if (!is_dir($this->themesDir)) {
            return [];
        }

        $dirs = scandir($this->themesDir);
        if ($dirs === false) {
            return [];
        }

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $themeDir = $this->themesDir . '/' . $dir;
            if (!is_dir($themeDir)) {
                continue;
            }

            try {
                $config = $this->load($dir);
                $themes[] = [
                    'name'        => $config['name'],
                    'version'     => $config['version'],
                    'description' => $config['description'],
                    'author'      => $config['author'],
                    'hasTemplate' => file_exists($themeDir . '/template.html'),
                ];
            } catch (\Throwable) {
                /* 読み込めないテーマはスキップ */
                $themes[] = [
                    'name'        => $dir,
                    'version'     => 'unknown',
                    'description' => '',
                    'author'      => '',
                    'hasTemplate' => false,
                ];
            }
        }

        return $themes;
    }

    /**
     * テンプレートコンテキストを構築する
     *
     * サイト設定とページデータを統合し、テンプレートに渡す
     * 完全なコンテキスト配列を構築する。
     *
     * @param array $config   サイト・テーマ設定
     * @param array $pageData ページ固有のデータ（title, content 等）
     * @return array 統合されたコンテキスト配列
     */
    public function buildContext(array $config, array $pageData): array {
        /* サイト全体の変数 */
        $context = [
            'site' => [
                'title'       => $config['site_title'] ?? $config['title'] ?? '',
                'description' => $config['site_description'] ?? $config['description'] ?? '',
                'url'         => $config['base_url'] ?? $config['baseUrl'] ?? '/',
                'language'    => $config['language'] ?? 'ja',
                'author'      => $config['author'] ?? '',
            ],
            'theme' => [
                'name'    => $config['theme'] ?? 'default',
                'options' => $config['theme_options'] ?? [],
            ],
            'buildTime' => date('Y-m-d H:i:s'),
            'year'      => date('Y'),
        ];

        /* ページ固有の変数をマージ */
        foreach ($pageData as $key => $value) {
            $context[$key] = $value;
        }

        /* ナビゲーション */
        if (isset($config['navigation'])) {
            $context['navigation'] = $config['navigation'];
        }

        /* ソーシャルリンク */
        if (isset($config['social'])) {
            $context['social'] = $config['social'];
        }

        return $context;
    }
}

// ============================================================================
// MarkdownParser - Markdown → HTML コンバーター
// ============================================================================

/**
 * ゼロ依存の Markdown → HTML パーサー。
 *
 * GFM (GitHub Flavored Markdown) の主要機能に対応:
 *   見出し、太字、斜体、リンク、画像、コードブロック、
 *   インラインコード、リスト（順序付き・順序なし）、
 *   ブロック引用、テーブル、水平線、打ち消し線、タスクリスト
 *
 * YAML フロントマターのパースにも対応。
 */
class MarkdownParser {
    /**
     * Markdown テキストを HTML に変換する
     *
     * @param string $markdown Markdown テキスト
     * @return string 変換された HTML
     */
    public function parse(string $markdown): string {
        $markdown = str_replace("\r\n", "\n", $markdown);
        $markdown = str_replace("\r", "\n", $markdown);

        /* コードブロック（``` ）を先に退避 */
        $codeBlocks = [];
        $markdown = preg_replace_callback(
            '/^```(\w*)\n(.*?)\n```$/ms',
            function (array $m) use (&$codeBlocks): string {
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
            function (array $m) use (&$inlineCodes): string {
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

        $flushParagraph = function () use (&$paragraph, &$html): void {
            if ($paragraph) {
                $text = implode("\n", $paragraph);
                $html[] = '<p>' . $this->inlineFormat($text) . '</p>';
                $paragraph = [];
            }
        };
        $closeList = function () use (&$inList, &$listType, &$html): void {
            if ($inList) {
                $html[] = $listType === 'ol' ? '</ol>' : '</ul>';
                $inList = false;
            }
        };
        $closeBlockquote = function () use (&$inBlockquote, &$html): void {
            if ($inBlockquote) {
                $html[] = '</blockquote>';
                $inBlockquote = false;
            }
        };
        $closeTable = function () use (&$inTable, &$html): void {
            if ($inTable) {
                $html[] = '</tbody></table>';
                $inTable = false;
            }
        };

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $trimmed = rtrim($line, " \t\r");

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
                $text = $this->inlineFormat($rawText);
                $id = $this->slugify($rawText);
                $html[] = "<h{$level} id=\"{$id}\">{$text}</h{$level}>";
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
                $html[] = '<p>' . $this->inlineFormat($content) . '</p>';
                continue;
            }
            if ($inBlockquote && $trimmed !== '') {
                $html[] = '<p>' . $this->inlineFormat($trimmed) . '</p>';
                continue;
            }

            /* テーブル */
            if (str_contains($trimmed, '|')) {
                $cells = $this->parseTableRow($trimmed);
                if ($cells !== null) {
                    if (!$inTable && isset($lines[$i + 1])) {
                        $nextCells = $this->parseTableRow(rtrim($lines[$i + 1]));
                        if ($nextCells !== null && $this->isTableSeparator($nextCells)) {
                            $flushParagraph();
                            $closeList();
                            $closeBlockquote();
                            $tableAlign = $this->parseTableAlign($nextCells);
                            $html[] = '<table><thead><tr>';
                            foreach ($cells as $j => $cell) {
                                $align = $tableAlign[$j] ?? '';
                                $style = $align ? " style=\"text-align:{$align}\"" : '';
                                $html[] = "<th{$style}>" . $this->inlineFormat(trim($cell)) . '</th>';
                            }
                            $html[] = '</tr></thead><tbody>';
                            $inTable = true;
                            $i++;
                            continue;
                        }
                    }
                    if ($inTable) {
                        $html[] = '<tr>';
                        foreach ($cells as $j => $cell) {
                            $align = $tableAlign[$j] ?? '';
                            $style = $align ? " style=\"text-align:{$align}\"" : '';
                            $html[] = "<td{$style}>" . $this->inlineFormat(trim($cell)) . '</td>';
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
                if (preg_match('/^\[([ xX])\]\s*(.*)$/', $itemText, $tm)) {
                    $checked = (strtolower($tm[1]) === 'x') ? ' checked' : '';
                    $html[] = '<li class="task-list-item"><input type="checkbox" disabled' . $checked . '> ' . $this->inlineFormat($tm[2]) . '</li>';
                } else {
                    $html[] = '<li>' . $this->inlineFormat($itemText) . '</li>';
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
                $html[] = '<li>' . $this->inlineFormat($m[1]) . '</li>';
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

        /* プレースホルダーを復元 */
        $result = strtr($result, $codeBlocks);
        $result = strtr($result, $inlineCodes);

        return $result;
    }

    /**
     * YAML フロントマター付き Markdown を分離する
     *
     * フロントマターは --- で囲まれた YAML ブロック。
     *
     * @param string $content フロントマター付き Markdown テキスト
     * @return array ['meta' => array, 'body' => string]
     */
    public function parseFrontmatter(string $content): array {
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
        $meta = $this->parseSimpleYaml($yamlBlock);

        return ['meta' => $meta, 'body' => $body];
    }

    // ========================================================================
    // 簡易 YAML パーサー
    // ========================================================================

    /**
     * 簡易 YAML パーサー（フロントマター用）
     *
     * 1 階層のキー・値ペアに対応。配列は [item1, item2] 形式をサポート。
     *
     * @param string $yaml YAML テキスト
     * @return array パース結果
     */
    private function parseSimpleYaml(string $yaml): array {
        $result = [];
        $lines = explode("\n", $yaml);

        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            if ($key === '') {
                continue;
            }

            /* 重複キーは最初の値を優先 */
            if (!array_key_exists($key, $result)) {
                $result[$key] = $this->parseYamlValue($val);
            }
        }

        return $result;
    }

    /**
     * YAML 値をパースする
     *
     * @param string $val 値文字列
     * @return mixed パースされた値
     */
    private function parseYamlValue(string $val): mixed {
        if ($val === '' || $val === '~' || $val === 'null') {
            return null;
        }
        if ($val === 'true') {
            return true;
        }
        if ($val === 'false') {
            return false;
        }
        if (is_numeric($val)) {
            return str_contains($val, '.') ? (float)$val : (int)$val;
        }

        /* [item1, item2] 形式の配列 */
        if (str_starts_with($val, '[') && str_ends_with($val, ']')) {
            $inner = substr($val, 1, -1);
            if (trim($inner) === '') {
                return [];
            }
            return array_map(function (string $v): mixed {
                $v = trim($v);
                if ((str_starts_with($v, '"') && str_ends_with($v, '"'))
                    || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                    return substr($v, 1, -1);
                }
                return $this->parseYamlValue($v);
            }, explode(',', $inner));
        }

        /* クォート付き文字列 */
        if ((str_starts_with($val, '"') && str_ends_with($val, '"'))
            || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            return substr($val, 1, -1);
        }

        return $val;
    }

    // ========================================================================
    // インライン書式
    // ========================================================================

    /**
     * インライン書式（太字、斜体、リンク、画像等）を適用する
     *
     * @param string $text 対象テキスト
     * @return string 書式適用後の HTML
     */
    private function inlineFormat(string $text): string {
        $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        /* 画像・リンクを先にプレースホルダーに退避 */
        $inlineRefs = [];

        /* 画像 */
        $text = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/',
            function (array $m) use (&$inlineRefs, $esc): string {
                $url = $this->sanitizeUrl($m[2]);
                if ($url === null) {
                    return $esc($m[0]);
                }
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
            function (array $m) use (&$inlineRefs, $esc): string {
                $url = $this->sanitizeUrl($m[2]);
                if ($url === null) {
                    return $esc($m[0]);
                }
                $linkText = $m[1];
                $title = isset($m[3]) && $m[3] !== '' ? ' title="' . $esc($m[3]) . '"' : '';
                $key = "\x00LINK" . count($inlineRefs) . "\x00";
                $inlineRefs[$key] = '<a href="' . $esc($url) . '"' . $title . '>' . $esc($linkText) . '</a>';
                return $key;
            },
            $text
        ) ?? $text;

        /* 残りのテキストを HTML エスケープ */
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

        /* 打ち消し線 */
        $text = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text) ?? $text;

        /* 改行（行末スペース2つ） */
        $text = preg_replace('/  $/', '<br>', $text) ?? $text;

        /* プレースホルダーを復元 */
        $text = strtr($text, $inlineRefs);

        return $text;
    }

    /**
     * URL をサニタイズする
     *
     * 安全なスキーム（http, https, mailto）と相対パスのみを許可し、
     * javascript:, data: 等の危険なスキームをブロックする。
     *
     * @param string $url 検証対象の URL
     * @return string|null サニタイズ済み URL。危険な場合は null
     */
    private function sanitizeUrl(string $url): ?string {
        $url = trim($url);
        $url = preg_replace('/[\x00-\x1f\x7f\s]/', '', $url);

        /* 安全なスキーム / 相対パスのみ許可 */
        if (preg_match('#^https?://#i', $url) || preg_match('/^(mailto:|\/|\#|\?)/', $url)) {
            return $url;
        }

        /* 相対パス（スキームなし・コロンなし） */
        if (!str_contains($url, ':')) {
            return $url;
        }

        /* それ以外はブロック */
        return null;
    }

    // ========================================================================
    // テーブルヘルパー
    // ========================================================================

    /**
     * テーブル行をパースしてセル配列を返す
     *
     * @param string $line テーブル行文字列
     * @return array|null セル配列。テーブル行でない場合は null
     */
    private function parseTableRow(string $line): ?array {
        $line = trim($line);
        if (!str_contains($line, '|')) {
            return null;
        }
        if (str_starts_with($line, '|')) {
            $line = substr($line, 1);
        }
        if (str_ends_with($line, '|')) {
            $line = substr($line, 0, -1);
        }
        return explode('|', $line);
    }

    /**
     * セル配列がテーブルセパレータ行かどうかを判定する
     *
     * @param array $cells セル配列
     * @return bool セパレータ行の場合 true
     */
    private function isTableSeparator(array $cells): bool {
        foreach ($cells as $cell) {
            if (!preg_match('/^\s*:?-+:?\s*$/', trim($cell))) {
                return false;
            }
        }
        return true;
    }

    /**
     * テーブルセパレータ行からアライメント情報を抽出する
     *
     * @param array $cells セパレータ行のセル配列
     * @return array<string> アライメント配列（'left', 'center', 'right', ''）
     */
    private function parseTableAlign(array $cells): array {
        $align = [];
        foreach ($cells as $cell) {
            $cell = trim($cell);
            $left  = str_starts_with($cell, ':');
            $right = str_ends_with($cell, ':');
            if ($left && $right) {
                $align[] = 'center';
            } elseif ($right) {
                $align[] = 'right';
            } elseif ($left) {
                $align[] = 'left';
            } else {
                $align[] = '';
            }
        }
        return $align;
    }

    /**
     * テキストを URL セーフなスラッグに変換する
     *
     * @param string $text 対象テキスト
     * @return string スラッグ文字列
     */
    private function slugify(string $text): string {
        $slug = mb_strtolower(strip_tags($text), 'UTF-8');
        $slug = preg_replace('/[^a-z0-9\s\-_]/', '', $slug) ?? '';
        $slug = preg_replace('/[\s]+/', '-', trim($slug)) ?? '';
        $slug = preg_replace('/-+/', '-', $slug) ?? '';
        return $slug ?: 'heading';
    }
}
