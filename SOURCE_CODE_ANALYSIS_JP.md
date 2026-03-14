# AdlairePlatform ソースコード解析レポート

**作成日**: 2026-03-13  
**バージョン**: Ver.1.4-pre  
**解析対象リポジトリ**: https://github.com/fqwink/AdlairePlatform

---

## 📚 関連ドキュメント

### Adlaire Framework Ecosystem (AFE) ドキュメント

AFEフレームワークに関する詳細ドキュメントは **[Framework/docs/](./Framework/docs/)** に集約されています。

**主要ドキュメント**:
- [AFE_ENGINE_DRIVEN_MODEL.md](./Framework/docs/AFE_ENGINE_DRIVEN_MODEL.md) - エンジン駆動モデル完全解説
- [AFE_IMPROVEMENT_ROADMAP_V2.md](./Framework/docs/AFE_IMPROVEMENT_ROADMAP_V2.md) - APF改良ロードマップ（Ver 2.2.0 - 2.5.0）

詳細は [Framework/docs/README.md](./Framework/docs/README.md) を参照してください。

---

## 📋 目次

1. [プロジェクト概要](#1-プロジェクト概要)
2. [アーキテクチャ](#2-アーキテクチャ)
3. [ディレクトリ構造](#3-ディレクトリ構造)
4. [コアシステム解析](#4-コアシステム解析)
5. [エンジンシステム詳細](#5-エンジンシステム詳細)
6. [セキュリティ実装](#6-セキュリティ実装)
7. [データフロー](#7-データフロー)
8. [フロントエンド実装](#8-フロントエンド実装)
9. [パフォーマンス最適化](#9-パフォーマンス最適化)
10. [今後の拡張性](#10-今後の拡張性)

---

## 1. プロジェクト概要

### 1.1 基本情報

**AdlairePlatform（AP）** は、デザインテンプレートエンジンを搭載したフラットファイルベースの軽量CMSフレームワークです。

| 項目 | 詳細 |
|------|------|
| **言語** | PHP 8.2+ |
| **データベース** | 不要（JSON フラットファイル） |
| **ライセンス** | Adlaire License Ver.2.0（Source Available, Not Open Source） |
| **総コード行数** | 約9,531行（エンジンのみ） + 387行（index.php） |
| **エンジン数** | 16エンジン |
| **対応サーバー** | Apache（mod_rewrite必須）、Nginx（php-fpm） |

### 1.2 特徴

1. **データベース不要** - JSONフラットファイルで全データを管理
2. **PHPフリーテーマ** - テンプレートエンジンによる安全なテーマシステム
3. **エンジン分離アーキテクチャ** - 各機能が独立したエンジンとして実装
4. **静的サイト生成** - 差分ビルド対応のStatic-First配信
5. **ヘッドレスCMS** - REST API・CORS対応
6. **独自WYSIWYGエディタ** - 依存ライブラリなしのブロックベースエディタ

---

## 2. アーキテクチャ

### 2.1 システム構成図

```
┌─────────────────────────────────────────────────────────┐
│                     index.php                           │
│  - エントリーポイント                                      │
│  - 全エンジンの初期化とルーティング                          │
│  - グローバル変数管理（AppContext連携）                     │
└─────────────────────────────────────────────────────────┘
                           │
           ┌───────────────┼───────────────┐
           ▼               ▼               ▼
    ┌──────────┐   ┌──────────┐   ┌──────────┐
    │  Admin   │   │ Template │   │  Theme   │
    │  Engine  │   │  Engine  │   │  Engine  │
    └──────────┘   └──────────┘   └──────────┘
           │
    ┌──────┴──────┬──────┬──────┬──────┬──────┐
    ▼             ▼      ▼      ▼      ▼      ▼
┌────────┐  ┌────────┐ ┌────┐ ┌────┐ ┌────┐ ┌─────┐
│ Static │  │  Api   │ │Git │ │Web │ │Col │ │Cache│
│ Engine │  │ Engine │ │Eng │ │hook│ │lect│ │Eng  │
└────────┘  └────────┘ └────┘ └────┘ └────┘ └─────┘
```

### 2.2 レイヤー構造

```
┌──────────────────────────────────────┐
│    Presentation Layer                │
│  - テーマHTML/CSS                     │
│  - WYSIWYGエディタ                   │
│  - ダッシュボードUI                   │
└──────────────────────────────────────┘
                ↓↑
┌──────────────────────────────────────┐
│    Application Layer                 │
│  - 16エンジン                         │
│  - ルーティング                       │
│  - 認証・認可                         │
└──────────────────────────────────────┘
                ↓↑
┌──────────────────────────────────────┐
│    Infrastructure Layer              │
│  - AppContext（状態管理）            │
│  - Logger（ログ記録）                │
│  - JsonCache（I/Oキャッシュ）         │
│  - MailerEngine（メール送信）         │
└──────────────────────────────────────┘
                ↓↑
┌──────────────────────────────────────┐
│    Data Layer                        │
│  - data/settings/*.json              │
│  - data/content/*.json               │
│  - data/content/[collections]/       │
└──────────────────────────────────────┘
```

### 2.3 リクエストフロー

```
HTTP Request
    ↓
.htaccess (mod_rewrite)
    ├→ static/配下に静的ファイル存在？ → [YES] → 静的配信（Static-First）
    └→ [NO] → index.php
              ↓
        セキュリティヘッダー設定
              ↓
        各エンジンのhandle()実行
        - AdminEngine::handle()    (ap_action処理)
        - ApiEngine::handle()      (?ap_api処理)
        - CollectionEngine::handle()
        - GitEngine::handle()
        - WebhookEngine::handle()
        - StaticEngine::handle()
        - UpdateEngine::handle()
              ↓
        ダッシュボード判定 (?admin)
              ↓
        ページコンテンツ読み込み
              ↓
        ThemeEngine::load()
              ↓
        TemplateEngine::render()
              ↓
        HTML出力
```

---

## 3. ディレクトリ構造

### 3.1 プロジェクトツリー

```
/home/user/webapp/
├── index.php                    # メインエントリーポイント（387行）
├── .htaccess                    # Apache設定（セキュリティ・リライト・Static-First）
├── README.md                    # プロジェクト概要
├── CONTRIBUTING.md              # コントリビュートガイドライン
├── RELEASE-NOTES.md             # リリースノート
│
├── data/                        # データストレージ（.htaccessで保護）
│   ├── settings/                # システム設定
│   │   ├── settings.json        # サイト設定（title, menu, description, etc.）
│   │   ├── auth.json            # 認証情報（bcryptハッシュ）
│   │   ├── users.json           # マルチユーザー管理
│   │   ├── version.json         # バージョン履歴
│   │   ├── api_keys.json        # API認証キー
│   │   ├── login_attempts.json  # ログイン試行記録（レート制限）
│   │   └── update_cache.json    # GitHub API キャッシュ
│   └── content/                 # コンテンツ
│       ├── pages.json           # ページコンテンツ（レガシー）
│       ├── ap-collections.json  # コレクションスキーマ定義
│       └── [collections]/       # コレクションディレクトリ（Markdownファイル）
│
├── engines/                     # エンジンシステム
│   ├── AdminEngine.php          # 管理機能（995行）
│   ├── ApiEngine.php            # REST API（1,257行）
│   ├── AppContext.php           # 集中状態管理（147行）
│   ├── CacheEngine.php          # キャッシュ管理（175行）
│   ├── CollectionEngine.php     # コレクション管理（625行）
│   ├── DiagnosticEngine.php     # 診断エンジン（1,885行）
│   ├── GitEngine.php            # Git連携（730行）
│   ├── ImageOptimizer.php       # 画像最適化（212行）
│   ├── Logger.php               # 構造化ログ（182行）
│   ├── MailerEngine.php         # メール送信（155行）
│   ├── MarkdownEngine.php       # Markdownパーサー（529行）
│   ├── StaticEngine.php         # 静的サイト生成（1,300行）
│   ├── TemplateEngine.php       # テンプレートエンジン（371行）
│   ├── ThemeEngine.php          # テーマ管理（236行）
│   ├── UpdateEngine.php         # アップデート管理（452行）
│   ├── WebhookEngine.php        # Webhook管理（280行）
│   ├── AdminEngine/             # AdminEngine関連アセット
│   └── JsEngine/                # フロントエンドスクリプト
│       ├── editInplace.js       # インプレイス編集
│       ├── wysiwyg.js           # WYSIWYGエディタ
│       ├── dashboard.js         # ダッシュボード
│       ├── updater.js           # アップデート UI
│       ├── static_builder.js    # 静的ビルド UI
│       ├── collection_manager.js # コレクション管理UI
│       ├── git_manager.js       # Git管理UI
│       ├── webhook_manager.js   # Webhook管理UI
│       ├── api_keys.js          # APIキー管理UI
│       ├── diagnostics.js       # 診断UI
│       ├── ap-api-client.js     # API クライアント
│       ├── ap-search.js         # 検索機能
│       └── autosize.js          # テキストエリア自動拡張
│
├── themes/                      # テーマディレクトリ
│   ├── AP-Default/              # デフォルトテーマ
│   │   ├── theme.html           # テンプレート
│   │   └── style.css            # スタイルシート
│   └── AP-Adlaire/              # Adlaireテーマ
│       ├── theme.html
│       └── style.css
│
├── uploads/                     # アップロードファイル（画像等）
├── static/                      # 静的サイト出力先（生成時に作成）
├── backup/                      # バックアップディレクトリ（.htaccessで保護）
│
└── docs/                        # ドキュメント
    ├── features.md              # 機能一覧（570行）
    └── Licenses/
        └── LICENSE_Ver.2.0.md   # ライセンス
```

### 3.2 コード規模

| カテゴリ | ファイル数 | 総行数（概算） |
|---------|-----------|--------------|
| コアシステム（index.php） | 1 | 387 |
| エンジン（PHP） | 16 | 9,531 |
| フロントエンド（JS） | 13 | 約5,000 |
| テーマ | 2 | 約500 |
| ドキュメント | 複数 | 約2,000 |
| **合計** | **32+** | **約17,418行** |

---

## 4. コアシステム解析

### 4.1 index.php の役割

`index.php` は全システムのエントリーポイントであり、以下の責務を持ちます：

#### 初期化フロー

```php
// 1. PHP バージョンチェック（PHP 8.2以上必須）
if (PHP_VERSION_ID < 80200) {
    exit('AdlairePlatform requires PHP 8.2 or later.');
}

// 2. 定数定義
define('AP_VERSION', '1.4.0');
define('AP_UPDATE_URL', 'https://api.github.com/repos/...');
define('AP_BACKUP_GENERATIONS', 5);
define('AP_REVISION_LIMIT', 30);

// 3. 全エンジンの読み込み（16エンジン）
require 'engines/AppContext.php';
require 'engines/Logger.php';
// ... 14個のエンジン ...
require 'engines/DiagnosticEngine.php';

// 4. セキュアセッション設定
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS'])) {
    ini_set('session.cookie_secure', 1);
}
session_start();

// 5. ログシステム初期化
Logger::init();

// 6. 診断エンジンのエラーハンドラ登録
DiagnosticEngine::registerErrorHandler();
DiagnosticEngine::startTimer('request_total');

// 7. セキュリティヘッダー送信
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
```

#### エンジンディスパッチ

```php
// 8. データマイグレーション
migrate_from_files();

// 9. ホスト情報解析
host();

// 10. 各エンジンのhandle()を順次実行
DiagnosticEngine::startTimer('AdminEngine');
AdminEngine::handle();       // edit_field, upload_image等
DiagnosticEngine::stopTimer('AdminEngine');

ApiEngine::handle();         // ?ap_api= REST API
CollectionEngine::handle();  // collection_create等
GitEngine::handle();         // git_pull, git_push等
WebhookEngine::handle();     // webhook管理
StaticEngine::handle();      // 静的サイト生成
handle_update_action();      // update, backup, rollback等
DiagnosticEngine::handle();  // diag_set_enabled等
```

#### デフォルトコンテンツ設定

```php
// 11. デフォルト設定の初期化
$c['password'] = 'admin';
$c['loggedin'] = false;
$c['page'] = 'home';
$d['page']['home'] = "<h3>Your website is now powered by Adlaire Platform.</h3>...";
$d['default']['content'] = 'Click to edit!';
$c['themeSelect'] = 'AP-Default';
$c['menu'] = "Home<br />Example";
$c['title'] = 'Website title';
// ... その他のデフォルト値 ...

// 12. JSONファイルからデータ読み込み
$_settings = json_read('settings.json', settings_dir());
$_auth     = json_read('auth.json', settings_dir());
$_pages    = json_read('pages.json', content_dir());

// 13. コレクションモード有効時のページマージ
if (class_exists('CollectionEngine') && CollectionEngine::isEnabled()) {
    $_collectionPages = CollectionEngine::loadAllAsPages();
    foreach ($_collectionPages as $_cpSlug => $_cpHtml) {
        if (!isset($_pages[$_cpSlug])) {
            $_pages[$_cpSlug] = $_cpHtml;
        }
    }
}
```

#### 認証・ルーティング処理

```php
// 14. 設定のマージ・認証処理
foreach($c as $key => $val){
    if(isset($_settings[$key]))
        $c[$key] = $_settings[$key];
    
    switch($key){
        case 'password':
            // MD5検出 → bcrypt移行
            if(strlen($_auth['password_hash']) === 32 && ctype_xdigit($_auth['password_hash'])){
                $c[$key] = AdminEngine::savePassword('admin');
                Logger::warning('MD5パスワード検出');
            }
            // デフォルトパスワード警告
            if (password_verify('admin', $c[$key])) {
                DiagnosticEngine::log('security', 'デフォルトパスワード使用中');
            }
            break;
        
        case 'loggedin':
            // ログアウト処理
            if(isset($_POST['logout'])){
                AdminEngine::verifyCsrf();
                session_destroy();
                header('Location: ./');
                exit;
            }
            // ログイン画面表示
            if(isset($_GET['login'])){
                echo AdminEngine::renderLogin($msg);
                exit;
            }
            break;
        
        case 'page':
            // ページコンテンツ取得
            $c[$key] = getSlug($c[$key]);
            $c['content'] = $_pages[$c[$key]] ?? null;
            if($c['content'] === null){
                header('HTTP/1.1 404 Not Found');
                $c['content'] = (is_loggedin()) ? 
                    $d['new_page']['admin'] : $d['new_page']['visitor'];
            }
            break;
    }
}

// 15. グローバル変数をAppContextに同期
AppContext::syncFromGlobals($c, $d, $host, $lstatus, $apcredit, $hook);

// 16. ダッシュボードルーティング
if (isset($_GET['admin'])) {
    if (!AdminEngine::isLoggedIn()) {
        header('Location: ./?login');
        exit;
    }
    echo AdminEngine::renderDashboard();
    exit;
}

// 17. 管理画面フック登録
AdminEngine::registerHooks();

// 18. 診断データ送信
DiagnosticEngine::stopTimer('request_total');
DiagnosticEngine::maybeSend();

// 19. テーマ読み込み＆レンダリング
ThemeEngine::load($c['themeSelect']);
```

### 4.2 共有ユーティリティ関数

#### ディレクトリ管理

```php
function data_dir(): string {
    $dir = 'data';
    if(!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function settings_dir(): string {
    $dir = 'data/settings';
    if(!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function content_dir(): string {
    $dir = 'data/content';
    if(!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}
```

#### JSON I/O とキャッシュ

```php
class JsonCache {
    private static array $store = [];
    
    public static function get(string $path): ?array {
        return self::$store[$path] ?? null;
    }
    
    public static function set(string $path, array $data): void {
        self::$store[$path] = $data;
    }
}

function json_read(string $file, string $dir = ''): array {
    $path = ($dir ?: data_dir()).'/'.$file;
    
    // キャッシュヒット
    $cached = JsonCache::get($path);
    if ($cached !== null) {
        return $cached;
    }
    
    // ファイル読み込み
    if(!file_exists($path)) return [];
    $raw = file_get_contents($path);
    $decoded = json_decode($raw, true);
    $result = is_array($decoded) ? $decoded : [];
    
    // キャッシュ保存
    JsonCache::set($path, $result);
    return $result;
}

function json_write(string $file, array $data, string $dir = ''): void {
    $path = ($dir ?: data_dir()).'/'.$file;
    
    file_put_contents(
        $path,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
    
    // キャッシュ更新
    JsonCache::set($path, $data);
}
```

#### セキュリティ関連

```php
function getSlug(string $p): string {
    $slug = mb_convert_case(str_replace(' ', '-', $p), MB_CASE_LOWER, "UTF-8");
    
    // NULL バイト除去
    $slug = str_replace("\0", '', $slug);
    
    // パストラバーサル除去（ループで再帰的に）
    do {
        $prev = $slug;
        $slug = str_replace(['../', '..\\'], '', $slug);
    } while ($slug !== $prev);
    
    // スラッシュ正規化
    $slug = preg_replace('#/+#', '/', $slug);
    return ltrim($slug, '/');
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
```

#### ホスト解析

```php
function host(){
    global $host, $rp;
    $rp = preg_replace('#/+#', '/', (isset($_GET['page'])) ? urldecode($_GET['page']) : '');
    
    // HTTP_HOST ヘッダーインジェクション防止
    $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $rawHost = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', $rawHost);
    $host = $rawHost;
    
    $uri = preg_replace('#/+#', '/', urldecode($_SERVER['REQUEST_URI']));
    $host = (strrpos($uri, $rp) !== false) ? 
        $host.'/'.substr($uri, 0, strlen($uri) - strlen($rp)) : 
        $host.'/'.$uri;
    
    $host = explode('?', $host);
    $host = '//'.str_replace('//', '/', $host[0]);
    
    // サニタイゼーション
    $strip = array('index.php','?','"','\'','>','<','=','(',')','\\');
    $rp = strip_tags(str_replace($strip, '', $rp));
    $host = strip_tags(str_replace($strip, '', $host));
}
```

### 4.3 データマイグレーション

```php
function migrate_from_files(): void {
    // Phase 1: files/ → data/ への旧マイグレーション
    if(!file_exists(data_dir().'/settings.json') && !file_exists(settings_dir().'/settings.json')){
        $settings_keys = ['title','description','keywords','copyright','themeSelect','menu','subside'];
        $settings = [];
        foreach($settings_keys as $key){
            $v = file_exists('files/'.$key) ? file_get_contents('files/'.$key) : false;
            if($v !== false) $settings[$key] = $v;
        }
        if($settings) json_write('settings.json', $settings, settings_dir());
        
        $pw = file_exists('files/password') ? file_get_contents('files/password') : false;
        if($pw) json_write('auth.json', ['password_hash' => trim($pw)], settings_dir());
        
        // ページ移行
        $pages = [];
        foreach(glob('files/*') ?: [] as $f){
            $slug = basename($f);
            if(!in_array($slug, $skip, true)){
                $v = file_get_contents($f);
                if($v !== false) $pages[$slug] = $v;
            }
        }
        if($pages) json_write('pages.json', $pages, content_dir());
    }
    
    // Phase 2: data/*.json → data/settings/ & data/content/ への移行
    $s_dir = settings_dir();
    $c_dir = content_dir();
    foreach(['settings.json','auth.json','update_cache.json','login_attempts.json','version.json'] as $f){
        $old = data_dir().'/'.$f;
        $new = $s_dir.'/'.$f;
        if(file_exists($old) && !file_exists($new)){
            @rename($old, $new);
        }
    }
}
```

---

## 5. エンジンシステム詳細

### 5.1 AdminEngine（管理エンジン）

**ファイル**: `engines/AdminEngine.php` (995行)  
**責務**: 認証・CSRF・管理アクション・ダッシュボード

#### 主要メソッド

```php
class AdminEngine {
    // POSTアクションハンドラ
    public static function handle(): void {
        $action = $_POST['ap_action'] ?? '';
        
        $valid = [
            'edit_field', 'upload_image', 'delete_page',
            'list_revisions', 'get_revision', 'restore_revision',
            'pin_revision', 'search_revisions',
            'user_add', 'user_delete',
            'redirect_add', 'redirect_delete',
        ];
        
        if (!in_array($action, $valid, true)) return;
        
        // 認証チェック
        if (!isset($_SESSION['l']) || $_SESSION['l'] !== true) {
            http_response_code(401);
            echo json_encode(['error' => '未ログイン']);
            exit;
        }
        self::verifyCsrf();
        
        match ($action) {
            'edit_field' => self::handleEditField(),
            'upload_image' => self::handleUploadImage(),
            // ...
        };
    }
    
    // 認証
    public static function isLoggedIn(): bool {
        return isset($_SESSION['l']) && $_SESSION['l'] === true;
    }
    
    public static function login(string $storedHash): string {
        // レート制限チェック
        self::checkLoginRate();
        
        if(!isset($_POST['pass'])) return 'パスワードを入力してください。';
        
        $pass = $_POST['pass'];
        
        // bcrypt検証
        if(password_verify($pass, $storedHash)){
            $_SESSION['l'] = true;
            $_SESSION['ap_username'] = $_POST['username'] ?? 'admin';
            $_SESSION['ap_role'] = self::getUserRole($_SESSION['ap_username']);
            
            // セッション固定攻撃対策
            session_regenerate_id(true);
            
            Logger::info('ログイン成功', ['username' => $_SESSION['ap_username']]);
            header('Location: ./');
            exit;
        }
        
        // 失敗回数記録
        self::recordLoginFailure();
        Logger::warning('ログイン失敗');
        return 'パスワードが正しくありません。';
    }
    
    // CSRFトークン
    public static function csrfToken(): string {
        if(empty($_SESSION['csrf'])){
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }
    
    public static function verifyCsrf(): void {
        $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $stored = $_SESSION['csrf'] ?? '';
        
        if(empty($stored) || !hash_equals($stored, $token)){
            Logger::warning('CSRF検証失敗');
            http_response_code(403);
            exit('CSRF token validation failed.');
        }
    }
    
    // マルチユーザー管理
    public static function listUsers(): array { /* ... */ }
    public static function addUser(string $username, string $password, string $role): bool { /* ... */ }
    public static function deleteUser(string $username): bool { /* ... */ }
    
    // リビジョン管理
    public static function saveRevision(string $fieldname, string $content, string $username): void { /* ... */ }
    public static function listRevisions(string $fieldname): array { /* ... */ }
    public static function getRevision(string $id): ?array { /* ... */ }
    public static function restoreRevision(string $id): bool { /* ... */ }
    
    // ダッシュボード
    public static function renderDashboard(): string { /* ... */ }
    public static function buildDashboardContext(): array { /* ... */ }
}
```

#### レート制限実装

```php
private static function checkLoginRate(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attempts = json_read('login_attempts.json', settings_dir());
    
    if(isset($attempts[$ip])){
        $data = $attempts[$ip];
        $lockout_until = $data['lockout_until'] ?? 0;
        
        // ロックアウト中
        if($lockout_until > time()){
            $remaining = $lockout_until - time();
            http_response_code(429);
            exit("ログイン試行回数が上限に達しました。{$remaining}秒後に再試行してください。");
        }
        
        // カウントリセット
        if(($data['last_attempt'] ?? 0) < time() - 900){
            unset($attempts[$ip]);
            json_write('login_attempts.json', $attempts, settings_dir());
        }
    }
}

private static function recordLoginFailure(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attempts = json_read('login_attempts.json', settings_dir());
    
    $count = ($attempts[$ip]['count'] ?? 0) + 1;
    $attempts[$ip] = [
        'count' => $count,
        'last_attempt' => time(),
        'lockout_until' => ($count >= 5) ? time() + 900 : 0
    ];
    
    json_write('login_attempts.json', $attempts, settings_dir());
}
```

### 5.2 TemplateEngine（テンプレートエンジン）

**ファイル**: `engines/TemplateEngine.php` (371行)  
**責務**: PHPフリーテンプレートのレンダリング

#### サポート構文

| 構文 | 説明 | 例 |
|------|------|-----|
| `{{variable}}` | エスケープ出力 | `{{title}}` |
| `{{{variable}}}` | 生HTML出力 | `{{{content}}}` |
| `{{user.name}}` | ネストアクセス | `{{post.author.name}}` |
| `{{var\|upper}}` | フィルター | `{{name\|upper\|trim}}` |
| `{{#if var}}...{{/if}}` | 条件分岐 | `{{#if loggedin}}...{{/if}}` |
| `{{#each items}}...{{/each}}` | ループ | `{{#each posts}}{{title}}{{/each}}` |
| `{{> partial}}` | 部分読込 | `{{> header}}` |

#### レンダリングフロー

```php
class TemplateEngine {
    public static function render(string $template, array $context, string $partialsDir = ''): string {
        if ($partialsDir !== '') {
            self::$partialsDir = $partialsDir;
        }
        self::$partialDepth = 0;
        
        // 処理順序が重要
        $html = self::processPartials($template, $context);
        $html = self::processEach($html, $context);
        $html = self::processIf($html, $context);
        $html = self::processRawVars($html, $context);
        $html = self::processVars($html, $context);
        self::warnUnprocessed($html);
        return $html;
    }
    
    // 部分テンプレート（循環参照防止: 最大深度10）
    private static function processPartials(string $tpl, array $ctx): string {
        return preg_replace_callback(
            '/\{\{>\s*(\w+)\s*\}\}/',
            function (array $m) use ($ctx): string {
                $name = $m[1];
                if (self::$partialDepth >= self::PARTIAL_MAX_DEPTH) {
                    Logger::warning("パーシャルのネスト超過: {$name}");
                    return '';
                }
                $path = self::$partialsDir . '/' . $name . '.html';
                if (!file_exists($path)) {
                    Logger::warning("パーシャル未検出: {$path}");
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
    
    // ループ（ネスト対応）
    private static function processEach(string $tpl, array $ctx): string {
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
            
            $inner = substr($tpl, $tagEnd, $closeEnd - $tagEnd);
            $items = $ctx[$key] ?? [];
            $out = '';
            
            if (is_array($items)) {
                $count = count($items);
                foreach ($items as $idx => $item) {
                    $loopCtx = array_merge($ctx, is_array($item) ? $item : []);
                    $loopCtx['@index'] = $idx;
                    $loopCtx['@first'] = ($idx === 0);
                    $loopCtx['@last']  = ($idx === $count - 1);
                    
                    // 再帰的に処理
                    $rendered = self::processEach($inner, $loopCtx);
                    $rendered = self::processIf($rendered, $loopCtx);
                    $rendered = self::processRawVars($rendered, $loopCtx);
                    $rendered = self::processVars($rendered, $loopCtx);
                    $out .= $rendered;
                }
            }
            
            $tpl = substr($tpl, 0, $tagStart) . $out . substr($tpl, $closeEnd + 9);
            $offset = $tagStart + strlen($out);
        }
        return $tpl;
    }
    
    // 条件分岐
    private static function processIf(string $tpl, array $ctx): string {
        return preg_replace_callback(
            '/\{\{#if\s+(!?)(\w+(?:\.\w+)*)\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{\/if\}\}/s',
            function (array $m) use ($ctx): string {
                $negate = ($m[1] === '!');
                $keyPath = $m[2];
                $ifBlock = $m[3];
                $elseBlock = $m[4] ?? '';
                
                $value = self::getNestedValue($ctx, $keyPath);
                $condition = !empty($value);
                if ($negate) $condition = !$condition;
                
                return $condition ? $ifBlock : $elseBlock;
            },
            $tpl
        ) ?? $tpl;
    }
    
    // フィルター処理
    private static function applyFilters(string $value, string $filters): string {
        $filterList = explode('|', $filters);
        foreach ($filterList as $filter) {
            $filter = trim($filter);
            if ($filter === '') continue;
            
            // パラメータ付きフィルター（例: truncate:50）
            if (strpos($filter, ':') !== false) {
                [$name, $arg] = explode(':', $filter, 2);
                $name = trim($name);
                $arg = trim($arg);
                
                match ($name) {
                    'truncate' => $value = mb_substr($value, 0, (int)$arg),
                    'default'  => $value = ($value === '') ? $arg : $value,
                    default    => null
                };
            } else {
                // パラメータなしフィルター
                $value = match ($filter) {
                    'upper'      => mb_strtoupper($value, 'UTF-8'),
                    'lower'      => mb_strtolower($value, 'UTF-8'),
                    'capitalize' => mb_convert_case($value, MB_CASE_TITLE, 'UTF-8'),
                    'trim'       => trim($value),
                    'nl2br'      => nl2br($value, false),
                    'length'     => (string)mb_strlen($value, 'UTF-8'),
                    default      => $value
                };
            }
        }
        return $value;
    }
}
```

### 5.3 ApiEngine（REST API エンジン）

**ファイル**: `engines/ApiEngine.php` (1,257行)  
**責務**: ヘッドレスCMS / 公開・管理REST API

#### エンドポイント一覧

**公開エンドポイント（認証不要）**

| エンドポイント | メソッド | 説明 |
|--------------|---------|------|
| `?ap_api=pages` | GET | 全ページ一覧 |
| `?ap_api=page&slug=xxx` | GET | 単一ページ取得 |
| `?ap_api=settings` | GET | 公開設定取得 |
| `?ap_api=search&q=xxx` | GET | 全文検索 |
| `?ap_api=contact` | POST | お問い合わせ送信 |
| `?ap_api=collections` | GET | コレクション定義一覧 |
| `?ap_api=collection&name=xxx` | GET | コレクション全アイテム |
| `?ap_api=item&collection=xxx&slug=yyy` | GET | 単一アイテム取得 |

**管理エンドポイント（APIキー認証）**

| エンドポイント | メソッド | 説明 |
|--------------|---------|------|
| `?ap_api=item_upsert` | POST | アイテム作成・更新 |
| `?ap_api=item_delete` | POST | アイテム削除 |
| `?ap_api=page_upsert` | POST | ページ作成・更新 |
| `?ap_api=page_delete` | POST | ページ削除 |
| `?ap_api=media_list` | GET | メディア一覧 |
| `?ap_api=media_upload` | POST | メディアアップロード |
| `?ap_api=media_delete` | POST | メディア削除 |
| `?ap_api=preview` | GET | 下書きプレビュー |
| `?ap_api=export` | GET | コレクションエクスポート |
| `?ap_api=import` | POST | コレクションインポート |

#### 認証方式

```php
class ApiEngine {
    // APIキー認証
    private static function authenticateApiKey(): bool {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            return false;
        }
        
        $providedKey = $m[1];
        $keys = json_read(self::API_KEYS_FILE, settings_dir());
        
        foreach ($keys as $keyData) {
            if (!($keyData['active'] ?? false)) continue;
            
            // bcrypt検証
            if (password_verify($providedKey, $keyData['key_hash'])) {
                self::$authenticatedViaApiKey = true;
                Logger::info('API認証成功', ['key_name' => $keyData['name']]);
                return true;
            }
        }
        
        Logger::warning('API認証失敗');
        return false;
    }
    
    // レート制限（公開API）
    private static function checkApiRate(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $cacheFile = data_dir() . '/api_rate_' . md5($ip) . '.json';
        
        $data = [];
        if (file_exists($cacheFile)) {
            $raw = file_get_contents($cacheFile);
            $data = json_decode($raw, true) ?: [];
        }
        
        $now = time();
        $window_start = $data['window_start'] ?? $now;
        $count = $data['count'] ?? 0;
        
        // ウィンドウリセット
        if ($now - $window_start >= self::API_RATE_WINDOW) {
            $window_start = $now;
            $count = 0;
        }
        
        $count++;
        
        if ($count > self::API_RATE_MAX) {
            http_response_code(429);
            echo json_encode(['error' => 'レート制限超過']);
            exit;
        }
        
        // 記録
        file_put_contents($cacheFile, json_encode([
            'window_start' => $window_start,
            'count' => $count
        ]));
    }
    
    // CORS設定
    private static function getCorsOrigin(): string {
        $settings = json_read('settings.json', settings_dir());
        $allowed = $settings['api_cors_origin'] ?? '*';
        
        if ($allowed === '*') return '*';
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedList = array_map('trim', explode(',', $allowed));
        
        return in_array($origin, $allowedList, true) ? $origin : 'null';
    }
}
```

#### お問い合わせAPI（ハニーポット＋レート制限）

```php
private static function handleContact(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        self::error('POST required', 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    
    // ハニーポット（ボット対策）
    if (!empty($data['website'])) {
        Logger::info('ハニーポット検出', ['ip' => $_SERVER['REMOTE_ADDR']]);
        echo json_encode(['success' => true]); // ボットに成功と見せかける
        exit;
    }
    
    // レート制限
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attempts = json_read('contact_attempts.json', data_dir());
    
    if (isset($attempts[$ip])) {
        $lockout = $attempts[$ip]['lockout_until'] ?? 0;
        if ($lockout > time()) {
            self::error('送信回数制限', 429);
        }
    }
    
    // バリデーション
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $message = trim($data['message'] ?? '');
    
    if (mb_strlen($name) > self::NAME_MAX_LEN) {
        self::error('名前が長すぎます');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        self::error('無効なメールアドレス');
    }
    if (mb_strlen($message) > self::MSG_MAX_LEN) {
        self::error('メッセージが長すぎます');
    }
    
    // メール送信
    $settings = json_read('settings.json', settings_dir());
    $to = $settings['contact_email'] ?? '';
    
    if ($to) {
        MailerEngine::sendContact($to, $name, $email, $message);
    }
    
    // 失敗回数記録
    $count = ($attempts[$ip]['count'] ?? 0) + 1;
    $attempts[$ip] = [
        'count' => $count,
        'last' => time(),
        'lockout_until' => ($count >= self::CONTACT_MAX_ATTEMPTS) ? 
            time() + self::CONTACT_LOCKOUT_SEC : 0
    ];
    json_write('contact_attempts.json', $attempts, data_dir());
    
    echo json_encode(['success' => true]);
    exit;
}
```

### 5.4 StaticEngine（静的サイト生成）

**ファイル**: `engines/StaticEngine.php` (1,300行)  
**責務**: 静的HTML生成・差分ビルド・アセット管理

#### ビルドタイプ

| タイプ | 説明 |
|--------|------|
| **差分ビルド** | 変更されたページのみ再生成（ハッシュ比較） |
| **フルビルド** | 全ページ強制再生成 |
| **クリーン** | `static/` ディレクトリ完全削除 |
| **ZIP生成** | 静的ファイル一式をZIP圧縮してダウンロード |

#### 差分ビルドフロー

```php
class StaticEngine {
    public function buildDiff(): array {
        $this->loadBuildState();
        $changed = [];
        $skipped = [];
        
        // 1. 設定ハッシュ比較
        $settingsHash = $this->hashSettings();
        $settingsChanged = ($settingsHash !== ($this->buildState['settings_hash'] ?? ''));
        
        // 2. 各ページのハッシュ比較
        foreach ($this->pages as $slug => $content) {
            $contentHash = md5($content);
            $stored = $this->buildState['pages'][$slug]['content_hash'] ?? '';
            
            if ($settingsChanged || $contentHash !== $stored) {
                $this->generatePage($slug, $content);
                $changed[] = $slug;
                
                // ハッシュ記録
                $this->buildState['pages'][$slug] = [
                    'content_hash' => $contentHash,
                    'generated_at' => date('Y-m-d H:i:s')
                ];
            } else {
                $skipped[] = $slug;
            }
        }
        
        // 3. 削除ページの検出
        $existing = array_keys($this->buildState['pages'] ?? []);
        $current = array_keys($this->pages);
        $deleted = array_diff($existing, $current);
        
        foreach ($deleted as $slug) {
            $this->deleteStaticPage($slug);
            unset($this->buildState['pages'][$slug]);
        }
        
        // 4. アセットコピー（差分）
        $this->copyAssets();
        
        // 5. sitemap.xml / robots.txt 生成
        $this->generateSitemap();
        $this->generateRobotsTxt();
        
        // 6. 検索インデックス生成
        $this->generateSearchIndex();
        
        // 7. ビルド状態保存
        $this->buildState['settings_hash'] = $settingsHash;
        $this->buildState['last_build'] = date('Y-m-d H:i:s');
        $this->saveBuildState();
        
        return [
            'success' => true,
            'changed' => $changed,
            'skipped' => $skipped,
            'deleted' => $deleted,
            'warnings' => $this->warnings
        ];
    }
    
    // ページ生成
    private function generatePage(string $slug, string $content): void {
        // コンテキスト構築
        $ctx = ThemeEngine::buildStaticContext(
            $slug,
            $content,
            $this->settings,
            $this->pages
        );
        
        // テンプレートレンダリング
        $theme = $this->settings['themeSelect'] ?? 'AP-Default';
        $themePath = "themes/{$theme}/theme.html";
        
        if (!file_exists($themePath)) {
            $this->warnings[] = "テーマ未検出: {$theme}";
            return;
        }
        
        $template = file_get_contents($themePath);
        $html = TemplateEngine::render($template, $ctx, dirname($themePath));
        
        // ミニファイ
        $html = $this->minifyHtml($html);
        
        // 出力
        $outDir = self::OUTPUT_DIR . '/' . ($slug === 'home' ? '' : $slug);
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }
        file_put_contents($outDir . '/index.html', $html);
    }
    
    // アセット差分コピー
    private function copyAssets(): void {
        $theme = $this->settings['themeSelect'] ?? 'AP-Default';
        $themeDir = "themes/{$theme}";
        
        // CSS
        if (file_exists("{$themeDir}/style.css")) {
            $css = file_get_contents("{$themeDir}/style.css");
            $css = $this->minifyCss($css);
            file_put_contents(self::OUTPUT_DIR . '/style.css', $css);
        }
        
        // uploads/ ディレクトリ（画像等）
        if (is_dir('uploads')) {
            $this->copyDirRecursive('uploads', self::OUTPUT_DIR . '/uploads');
        }
    }
    
    // sitemap.xml 生成
    private function generateSitemap(): void {
        $baseUrl = $this->settings['site_url'] ?? 'https://example.com';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($this->pages as $slug => $content) {
            $url = $baseUrl . ($slug === 'home' ? '' : '/' . $slug);
            $lastmod = date('Y-m-d');
            
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($url, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "  </url>\n";
        }
        
        $xml .= '</urlset>';
        file_put_contents(self::OUTPUT_DIR . '/sitemap.xml', $xml);
    }
    
    // 検索インデックス生成（ドラフト除外）
    private function generateSearchIndex(): void {
        $index = [];
        
        foreach ($this->pages as $slug => $content) {
            // ドラフト判定（frontmatterにdraft: trueがあれば除外）
            if (preg_match('/^---\n.*?draft:\s*true/s', $content)) {
                continue;
            }
            
            // HTMLタグ除去
            $text = strip_tags($content);
            $preview = mb_substr($text, 0, 200);
            
            $index[] = [
                'slug' => $slug,
                'title' => $this->extractTitle($content),
                'preview' => $preview,
                'content' => $text
            ];
        }
        
        file_put_contents(
            self::OUTPUT_DIR . '/search-index.json',
            json_encode($index, JSON_UNESCAPED_UNICODE)
        );
    }
}
```

### 5.5 CollectionEngine（コレクション管理）

**ファイル**: `engines/CollectionEngine.php` (625行)  
**責務**: スキーマベースのコンテンツ管理

#### ディレクトリ構造

```
data/content/
├── ap-collections.json   ← コレクション定義
├── pages/                ← コレクション: 固定ページ
│   ├── index.md
│   └── about.md
└── posts/                ← コレクション: ブログ記事
    ├── hello-world.md
    └── my-second-post.md
```

#### スキーマ定義例

```json
{
  "collections": [
    {
      "name": "pages",
      "label": "ページ",
      "directory": "pages",
      "fields": [
        {"name": "title", "type": "string", "required": true},
        {"name": "description", "type": "text"},
        {"name": "published", "type": "boolean", "default": true}
      ]
    },
    {
      "name": "posts",
      "label": "ブログ記事",
      "directory": "posts",
      "fields": [
        {"name": "title", "type": "string", "required": true},
        {"name": "date", "type": "datetime"},
        {"name": "tags", "type": "array"},
        {"name": "thumbnail", "type": "image"},
        {"name": "draft", "type": "boolean", "default": false}
      ]
    }
  ]
}
```

#### Markdown ファイル構造

```markdown
---
title: "Hello World"
date: "2026-03-13T10:00:00+09:00"
tags: ["introduction", "welcome"]
draft: false
---

# Hello World

これは最初の投稿です。

## セクション1

本文内容...
```

#### コレクションエンジンメソッド

```php
class CollectionEngine {
    // コレクション有効判定
    public static function isEnabled(): bool {
        $schema = self::loadSchema();
        return !empty($schema['collections']);
    }
    
    // 全コレクションのページ化
    public static function loadAllAsPages(): array {
        $schema = self::loadSchema();
        $pages = [];
        
        foreach ($schema['collections'] ?? [] as $collection) {
            $dir = $collection['directory'] ?? '';
            if (!$dir) continue;
            
            $collectionDir = content_dir() . '/' . $dir;
            if (!is_dir($collectionDir)) continue;
            
            foreach (glob($collectionDir . '/*.md') ?: [] as $file) {
                $slug = basename($file, '.md');
                $raw = file_get_contents($file);
                
                // Markdownパース
                $parsed = MarkdownEngine::parse($raw);
                
                // ドラフト除外
                if ($parsed['meta']['draft'] ?? false) {
                    continue;
                }
                
                // HTML変換
                $html = MarkdownEngine::toHtml($parsed['body']);
                
                // スラッグを"{collection}/{slug}"形式に
                $pageSlug = $dir . '/' . $slug;
                $pages[$pageSlug] = $html;
            }
        }
        
        return $pages;
    }
    
    // アイテム保存
    public static function saveItem(string $collection, string $slug, array $data, string $body): bool {
        $schema = self::getCollection($collection);
        if (!$schema) return false;
        
        // バリデーション
        foreach ($schema['fields'] as $field) {
            if (($field['required'] ?? false) && empty($data[$field['name']])) {
                return false;
            }
        }
        
        // Frontmatter生成
        $frontmatter = "---\n";
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $frontmatter .= "{$key}: [" . implode(', ', array_map(function($v){
                    return '"' . addslashes($v) . '"';
                }, $value)) . "]\n";
            } elseif (is_bool($value)) {
                $frontmatter .= "{$key}: " . ($value ? 'true' : 'false') . "\n";
            } else {
                $frontmatter .= "{$key}: \"" . addslashes($value) . "\"\n";
            }
        }
        $frontmatter .= "---\n\n";
        
        // ファイル保存
        $dir = content_dir() . '/' . $schema['directory'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $filePath = $dir . '/' . $slug . '.md';
        file_put_contents($filePath, $frontmatter . $body);
        
        Logger::info('コレクションアイテム保存', [
            'collection' => $collection,
            'slug' => $slug
        ]);
        
        return true;
    }
    
    // アイテム削除
    public static function deleteItem(string $collection, string $slug): bool {
        $schema = self::getCollection($collection);
        if (!$schema) return false;
        
        $filePath = content_dir() . '/' . $schema['directory'] . '/' . $slug . '.md';
        
        if (file_exists($filePath)) {
            unlink($filePath);
            Logger::info('コレクションアイテム削除', [
                'collection' => $collection,
                'slug' => $slug
            ]);
            return true;
        }
        
        return false;
    }
}
```

---

## 6. セキュリティ実装

### 6.1 セキュリティ対策一覧

| カテゴリ | 対策 | 実装場所 |
|---------|------|---------|
| **認証** | bcrypt ハッシュ化 | `AdminEngine::savePassword()` |
| | レート制限（5回/15分） | `AdminEngine::checkLoginRate()` |
| | セッション固定攻撃対策 | `AdminEngine::login()` |
| | MD5→bcrypt自動移行 | `index.php` |
| **CSRF** | トークン生成（random_bytes(32)） | `AdminEngine::csrfToken()` |
| | hash_equals()検証 | `AdminEngine::verifyCsrf()` |
| **XSS** | htmlspecialchars全出力 | `h()` 関数 |
| | WYSIWYGサニタイザー | `wysiwyg.js` |
| | テンプレートエンジンエスケープ | `TemplateEngine` |
| **パストラバーサル** | スラッグ正規化（ループ除去） | `getSlug()` |
| | テーマ名バリデーション | `ThemeEngine::load()` |
| | バックアップ名バリデーション | `UpdateEngine` |
| **セッション** | HttpOnly / SameSite=Lax | `index.php` |
| | Secure（HTTPS時） | `index.php` |
| **HTTPヘッダー** | X-Content-Type-Options | `.htaccess` |
| | X-Frame-Options | `.htaccess` |
| | Referrer-Policy | `.htaccess` |
| | CSP | `.htaccess` |
| | Permissions-Policy | `.htaccess` |
| **ファイルアクセス** | data/ → 403 | `.htaccess` |
| | backup/ → 403 | `.htaccess` |
| | engines/*.php → 403 | `.htaccess` |
| **画像アップロード** | MIME検証（finfo） | `AdminEngine::handleUploadImage()` |
| | 2MB制限 | `AdminEngine::handleUploadImage()` |
| | ランダムファイル名 | `AdminEngine::handleUploadImage()` |
| **アップデート** | GitHub URLのみ許可 | `UpdateEngine::apply_update()` |
| | ZipArchive検証 | `UpdateEngine` |
| **API** | レート制限（60req/min） | `ApiEngine::checkApiRate()` |
| | APIキー bcrypt認証 | `ApiEngine::authenticateApiKey()` |
| | CORS Origin検証 | `ApiEngine::getCorsOrigin()` |
| | ハニーポット（お問い合わせ） | `ApiEngine::handleContact()` |
| **Webhook** | SSRF防止（プライベートIPブロック） | `WebhookEngine` |
| | DNS Rebinding対策 | `WebhookEngine` |
| **ログ** | 構造化ログ（JSON） | `Logger` |
| | ファイルローテーション | `Logger::rotate()` |
| | ログディレクトリ保護 | `.htaccess` |

### 6.2 .htaccess セキュリティ設定

```apache
# ディレクトリアクセス拒否
RedirectMatch 403 ^.*/files/
RedirectMatch 403 ^.*/data/
RedirectMatch 403 ^.*/backup/
RedirectMatch 403 ^.*/engines/.*\.php$
ErrorDocument 403 &nbsp;

# セキュリティヘッダー
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "same-origin"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>

# URL リライト
RewriteEngine on
Options -Indexes

# Static-First配信
RewriteCond static/index.html -f
RewriteCond %{QUERY_STRING} !(?:^|&)(ap_action|ap_api|admin|login)
RewriteRule ^$ static/index.html [L]

RewriteCond static/%1/index.html -f
RewriteCond %{QUERY_STRING} !(?:^|&)(ap_action|ap_api|admin|login)
RewriteRule ^([a-zA-Z0-9_-]+)/?$ static/$1/index.html [L]

# 動的PHPへのフォールバック
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([^\.]+)$ index.php?page=$1 [QSA,L]
```

### 6.3 bcrypt 実装

```php
class AdminEngine {
    // パスワード保存（bcrypt）
    public static function savePassword(string $p): string {
        $hash = password_hash($p, PASSWORD_BCRYPT);
        $auth = ['password_hash' => $hash];
        json_write('auth.json', $auth, settings_dir());
        Logger::info('パスワード変更');
        return $hash;
    }
    
    // ログイン認証
    public static function login(string $storedHash): string {
        self::checkLoginRate();
        
        if(!isset($_POST['pass'])) return 'パスワードを入力してください。';
        
        $pass = $_POST['pass'];
        
        // bcrypt検証
        if(password_verify($pass, $storedHash)){
            $_SESSION['l'] = true;
            $_SESSION['ap_username'] = $_POST['username'] ?? 'admin';
            session_regenerate_id(true);
            
            // 失敗カウントクリア
            self::clearLoginFailures();
            
            Logger::info('ログイン成功');
            header('Location: ./');
            exit;
        }
        
        // 失敗記録
        self::recordLoginFailure();
        Logger::warning('ログイン失敗', ['ip' => $_SERVER['REMOTE_ADDR']]);
        return 'パスワードが正しくありません。';
    }
}
```

---

## 7. データフロー

### 7.1 ページ表示フロー

```
[ブラウザ] GET /about
    ↓
[Apache] .htaccess リライト → index.php?page=about
    ↓
[PHP] index.php
    ├→ セキュリティヘッダー送信
    ├→ セッション開始
    ├→ Logger初期化
    ├→ 各エンジンhandle()（このリクエストでは何も実行されない）
    ├→ host() でページスラッグ解析 → 'about'
    ├→ json_read('pages.json') → キャッシュヒット or ファイル読み込み
    ├→ コレクションモード有効？
    │   └→ [YES] CollectionEngine::loadAllAsPages() でMarkdown→HTML変換
    ├→ $c['page'] = 'about'
    ├→ $c['content'] = $_pages['about']
    ├→ AppContext::syncFromGlobals($c, $d, ...)
    ├→ ThemeEngine::load('AP-Default')
    │   ├→ themes/AP-Default/theme.html 読み込み
    │   └→ TemplateEngine::render($template, $context)
    │       ├→ {{> header}} → partials/header.html 挿入
    │       ├→ {{#if loggedin}} → 条件分岐
    │       ├→ {{#each menu_items}} → ループ
    │       ├→ {{{content}}} → ページコンテンツ挿入（生HTML）
    │       └→ {{title}} → エスケープ出力
    └→ HTML出力
```

### 7.2 コンテンツ編集フロー

```
[ブラウザ] クリックイベント → editInplace.js
    ↓
[JS] contenteditable有効化
    ↓
[ユーザー] テキスト編集
    ↓
[JS] blur or Ctrl+Enter → Fetch API
    POST /
    {
        ap_action: 'edit_field',
        fieldname: 'content',
        content: '編集内容',
        csrf: 'token...'
    }
    ↓
[PHP] index.php → AdminEngine::handle()
    ├→ ap_action = 'edit_field' 検出
    ├→ isLoggedIn() チェック → OK
    ├→ verifyCsrf() チェック → OK
    ├→ handleEditField()
    │   ├→ $_POST['fieldname'] = 'content'
    │   ├→ $_POST['content'] バリデーション
    │   ├→ リビジョン保存（最大30件）
    │   │   └→ data/settings/revisions/{fieldname}/{timestamp}.json
    │   ├→ json_read('pages.json', content_dir())
    │   ├→ $pages[$slug] = $content
    │   ├→ json_write('pages.json', $pages, content_dir())
    │   │   └→ JsonCache更新
    │   ├→ CacheEngine::clear() （API キャッシュクリア）
    │   └→ WebhookEngine::sendAsync('content_updated', ...)
    └→ JSON レスポンス {success: true}
    ↓
[ブラウザ] 保存完了表示
```

### 7.3 静的サイト生成フロー

```
[ブラウザ] ダッシュボードで「差分ビルド」クリック
    ↓
[JS] static_builder.js → Fetch API
    POST /
    {
        ap_action: 'generate_static_diff',
        csrf: 'token...'
    }
    ↓
[PHP] index.php → StaticEngine::handle()
    ├→ ap_action = 'generate_static_diff' 検出
    ├→ isLoggedIn() チェック → OK
    ├→ verifyCsrf() チェック → OK
    ├→ $engine = new StaticEngine()
    ├→ $engine->init()
    │   ├→ json_read('settings.json')
    │   ├→ json_read('pages.json')
    │   ├→ CollectionEngine::isEnabled() → Markdown読み込み
    │   └→ json_read('static_build.json', data_dir())
    └→ $engine->buildDiff()
        ├→ 設定ハッシュ比較
        ├→ 各ページのハッシュ比較
        │   ├→ [変更あり]
        │   │   ├→ ThemeEngine::buildStaticContext()
        │   │   ├→ TemplateEngine::render()
        │   │   ├→ minifyHtml()
        │   │   └→ static/{slug}/index.html 出力
        │   └→ [変更なし] スキップ
        ├→ 削除ページ検出 → HTMLファイル削除
        ├→ copyAssets()
        │   ├→ themes/AP-Default/style.css → static/style.css
        │   └→ uploads/ → static/uploads/
        ├→ generateSitemap() → static/sitemap.xml
        ├→ generateRobotsTxt() → static/robots.txt
        ├→ generateSearchIndex() → static/search-index.json
        ├→ saveBuildState()
        │   └→ data/static_build.json 更新
        └→ WebhookEngine::sendAsync('build_complete', ...)
    ↓
[PHP] JSON レスポンス
    {
        success: true,
        changed: ['about', 'contact'],
        skipped: ['home'],
        deleted: [],
        warnings: []
    }
    ↓
[ブラウザ] ビルド結果表示
```

### 7.4 API リクエストフロー

```
[外部クライアント] GET https://example.com/?ap_api=collections
    ↓
[Apache] index.php へルーティング
    ↓
[PHP] index.php → ApiEngine::handle()
    ├→ $_GET['ap_api'] = 'collections' 検出
    ├→ CORS ヘッダー送信
    │   ├→ Access-Control-Allow-Origin: https://client.com
    │   └→ Vary: Origin
    ├→ 公開エンドポイント判定 → OK
    ├→ checkApiRate()
    │   ├→ IP: 203.0.113.50
    │   ├→ data/api_rate_cf23df2207d99a74fbe169e3eba035e6.json 読み込み
    │   ├→ カウント: 45/60
    │   └→ [OK] カウント+1して記録
    ├→ CacheEngine::serve('collections', $_GET)
    │   ├→ キャッシュファイル: data/cache/api_collections_xxxx.json
    │   ├→ [HIT] キャッシュから返却
    │   └→ [MISS] 続行
    └→ handleCollections()
        ├→ CollectionEngine::loadSchema()
        ├→ JSON整形
        ├→ CacheEngine::save()
        └→ echo json_encode($response)
    ↓
[外部クライアント] JSON レスポンス受信
```

---

## 8. フロントエンド実装

### 8.1 WYSIWYGエディタ（wysiwyg.js）

**行数**: 約1,500行  
**依存**: なし（純粋なES2020+ JavaScript）

#### アーキテクチャ

```javascript
class APEditor {
    constructor(element) {
        this.element = element;
        this.blocks = [];
        this.currentBlockId = null;
        this.originalContent = element.innerHTML;
        
        this.init();
    }
    
    init() {
        // 既存HTMLをブロックにパース
        this.parseHtmlToBlocks(this.originalContent);
        
        // UI構築
        this.buildToolbar();
        this.buildEditor();
        this.attachEventListeners();
        
        // 自動保存（30秒間隔）
        this.autoSaveInterval = setInterval(() => this.save(), 30000);
    }
    
    // HTMLパース → ブロック配列
    parseHtmlToBlocks(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const body = doc.body;
        
        for (const node of body.childNodes) {
            if (node.nodeType === Node.ELEMENT_NODE) {
                const block = this.htmlNodeToBlock(node);
                if (block) this.blocks.push(block);
            } else if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                this.blocks.push({
                    id: this.generateId(),
                    type: 'paragraph',
                    data: { text: node.textContent.trim() }
                });
            }
        }
        
        if (this.blocks.length === 0) {
            this.blocks.push({
                id: this.generateId(),
                type: 'paragraph',
                data: { text: '' }
            });
        }
    }
    
    // ブロック → HTMLノード変換
    htmlNodeToBlock(node) {
        const tagName = node.tagName.toLowerCase();
        const id = this.generateId();
        
        switch (tagName) {
            case 'p':
                return { id, type: 'paragraph', data: { text: node.innerHTML } };
            case 'h2':
                return { id, type: 'heading', data: { level: 2, text: node.innerHTML } };
            case 'h3':
                return { id, type: 'heading', data: { level: 3, text: node.innerHTML } };
            case 'blockquote':
                return { id, type: 'blockquote', data: { text: node.innerHTML } };
            case 'pre':
                const code = node.querySelector('code');
                return { id, type: 'code', data: { code: code ? code.textContent : node.textContent } };
            case 'ul':
            case 'ol':
                const items = Array.from(node.querySelectorAll('li')).map(li => li.innerHTML);
                return { id, type: 'list', data: { style: tagName, items } };
            case 'hr':
                return { id, type: 'delimiter', data: {} };
            case 'table':
                return { id, type: 'table', data: this.parseTable(node) };
            case 'figure':
                const img = node.querySelector('img');
                const caption = node.querySelector('figcaption');
                if (img) {
                    return {
                        id,
                        type: 'image',
                        data: {
                            url: img.src,
                            alt: img.alt || '',
                            caption: caption ? caption.textContent : '',
                            width: img.style.width || '100%'
                        }
                    };
                }
                break;
        }
        
        return null;
    }
    
    // ブロックレンダリング
    renderBlock(block) {
        const wrapper = document.createElement('div');
        wrapper.className = 'ap-block';
        wrapper.dataset.blockId = block.id;
        
        // ハンドル
        const handle = document.createElement('div');
        handle.className = 'ap-block-handle';
        handle.textContent = '⠿';
        handle.draggable = true;
        wrapper.appendChild(handle);
        
        // コンテンツ
        const content = document.createElement('div');
        content.className = 'ap-block-content';
        
        switch (block.type) {
            case 'paragraph':
                content.innerHTML = `<p contenteditable="true" role="textbox" aria-label="段落">${block.data.text}</p>`;
                break;
            case 'heading':
                const level = block.data.level || 2;
                content.innerHTML = `<h${level} contenteditable="true" role="textbox" aria-label="見出し${level}">${block.data.text}</h${level}>`;
                break;
            case 'list':
                const tag = block.data.style === 'ol' ? 'ol' : 'ul';
                const items = block.data.items.map(item => `<li contenteditable="true">${item}</li>`).join('');
                content.innerHTML = `<${tag}>${items}</${tag}>`;
                break;
            case 'code':
                content.innerHTML = `<pre><code contenteditable="true" spellcheck="false">${this.escapeHtml(block.data.code)}</code></pre>`;
                break;
            case 'image':
                content.innerHTML = this.renderImageBlock(block);
                break;
            case 'table':
                content.innerHTML = this.renderTableBlock(block);
                break;
            // ... 他のブロックタイプ
        }
        
        wrapper.appendChild(content);
        return wrapper;
    }
    
    // スラッシュコマンド
    handleSlashCommand(block, text) {
        const commands = [
            { trigger: '/paragraph', type: 'paragraph', label: '¶ 段落' },
            { trigger: '/h2', type: 'heading', label: 'H2 見出し2' },
            { trigger: '/h3', type: 'heading', label: 'H3 見出し3' },
            { trigger: '/ul', type: 'list', label: '•≡ 箇条書き' },
            { trigger: '/ol', type: 'list', label: '1≡ 番号リスト' },
            { trigger: '/quote', type: 'blockquote', label: '❝ 引用' },
            { trigger: '/code', type: 'code', label: '{} コード' },
            { trigger: '/hr', type: 'delimiter', label: '— 区切り線' },
            { trigger: '/table', type: 'table', label: '📊 テーブル' },
            { trigger: '/image', type: 'image', label: '🖼 画像' },
            { trigger: '/checklist', type: 'checklist', label: '☑ チェックリスト' }
        ];
        
        const filtered = commands.filter(cmd => 
            cmd.trigger.startsWith(text.toLowerCase())
        );
        
        if (filtered.length > 0) {
            this.showSlashMenu(block, filtered);
        } else {
            this.hideSlashMenu();
        }
    }
    
    // 保存
    async save() {
        const html = this.blocksToHtml();
        const sanitized = this.sanitize(html);
        
        const formData = new FormData();
        formData.append('ap_action', 'edit_field');
        formData.append('fieldname', this.element.dataset.fieldname);
        formData.append('content', sanitized);
        formData.append('csrf', window.apCsrfToken);
        
        try {
            const response = await fetch('./', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showStatus('保存しました', 'success');
            } else {
                this.showStatus('保存失敗: ' + result.error, 'error');
            }
        } catch (error) {
            this.showStatus('保存失敗: ' + error.message, 'error');
        }
    }
    
    // サニタイザー（ホワイトリスト方式）
    sanitize(html) {
        const allowedTags = [
            'b', 'i', 'u', 's', 'strong', 'em', 'mark', 'code',
            'h2', 'h3', 'p', 'br', 'blockquote', 'pre', 'hr',
            'ul', 'ol', 'li', 'a', 'img',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'figure', 'figcaption'
        ];
        
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        this.sanitizeNode(doc.body, allowedTags);
        
        return doc.body.innerHTML;
    }
    
    sanitizeNode(node, allowedTags) {
        for (let i = node.childNodes.length - 1; i >= 0; i--) {
            const child = node.childNodes[i];
            
            if (child.nodeType === Node.ELEMENT_NODE) {
                const tagName = child.tagName.toLowerCase();
                
                if (!allowedTags.includes(tagName)) {
                    // 不正タグは削除（子要素は保持）
                    while (child.firstChild) {
                        node.insertBefore(child.firstChild, child);
                    }
                    node.removeChild(child);
                } else {
                    // 許可された属性のみ保持
                    this.sanitizeAttributes(child);
                    // 再帰的に処理
                    this.sanitizeNode(child, allowedTags);
                }
            }
        }
    }
    
    sanitizeAttributes(element) {
        const allowedAttrs = {
            'a': ['href', 'title'],
            'img': ['src', 'alt', 'width', 'height'],
            'table': ['class'],
            'td': ['colspan', 'rowspan'],
            'th': ['colspan', 'rowspan']
        };
        
        const tag = element.tagName.toLowerCase();
        const allowed = allowedAttrs[tag] || [];
        
        // 全属性を取得（Array.from で静的コピー）
        const attrs = Array.from(element.attributes);
        
        for (const attr of attrs) {
            if (!allowed.includes(attr.name)) {
                element.removeAttribute(attr.name);
            }
        }
    }
}
```

### 8.2 ダッシュボード（dashboard.js）

```javascript
class APDashboard {
    constructor() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        this.init();
    }
    
    init() {
        // テーマ切替
        document.querySelector('#theme-select')?.addEventListener('change', (e) => {
            this.changeTheme(e.target.value);
        });
        
        // タブ切替
        document.querySelectorAll('.ap-tab').forEach(tab => {
            tab.addEventListener('click', () => this.switchTab(tab.dataset.tab));
        });
        
        // モジュールUI初期化
        this.initUpdater();
        this.initStaticBuilder();
        this.initCollectionManager();
        this.initGitManager();
        this.initWebhookManager();
        this.initApiKeys();
        this.initDiagnostics();
    }
    
    async changeTheme(themeName) {
        const formData = new FormData();
        formData.append('ap_action', 'edit_field');
        formData.append('fieldname', 'themeSelect');
        formData.append('content', themeName);
        formData.append('csrf', this.csrfToken);
        
        try {
            const response = await fetch('./', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload(); // テーマ反映のためリロード
            }
        } catch (error) {
            console.error('テーマ変更失敗:', error);
        }
    }
    
    switchTab(tabName) {
        // 全タブ非表示
        document.querySelectorAll('.ap-tab-content').forEach(content => {
            content.style.display = 'none';
        });
        
        // 選択タブ表示
        document.querySelector(`#tab-${tabName}`).style.display = 'block';
        
        // タブボタン状態更新
        document.querySelectorAll('.ap-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === tabName);
        });
    }
    
    // ... 各モジュールの初期化メソッド
}

// ページ読み込み時に初期化
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.ap-dashboard')) {
        new APDashboard();
    }
});
```

---

## 9. パフォーマンス最適化

### 9.1 実装済み最適化

| カテゴリ | 最適化手法 | 実装場所 | 効果 |
|---------|-----------|---------|------|
| **I/O** | JSONキャッシュ（リクエスト内） | `JsonCache` クラス | 重複読み込み排除 |
| | ファイルロック（LOCK_EX） | `json_write()` | 同時書き込み保護 |
| **HTTP** | Static-First配信 | `.htaccess` | 静的ファイル直接配信 |
| | API レスポンスキャッシュ | `CacheEngine` | API負荷軽減 |
| | CORS プリフライトキャッシュ | `ApiEngine` | 86400秒キャッシュ |
| **HTML** | HTMLミニファイ | `StaticEngine` | ファイルサイズ削減 |
| | CSSミニファイ | `StaticEngine` | ファイルサイズ削減 |
| **ビルド** | 差分ビルド（ハッシュ比較） | `StaticEngine` | 変更ページのみ生成 |
| | アセット差分コピー | `StaticEngine` | 不要コピー削除 |
| **ログ** | ファイルローテーション | `Logger` | ディスク使用量管理 |
| | 古いログ自動削除 | `Logger::cleanup()` | 30日以上削除 |
| **診断** | タイマー計測 | `DiagnosticEngine` | ボトルネック特定 |

### 9.2 JsonCache の効果

```php
// Before（最適化前）
$_settings = json_read('settings.json', settings_dir()); // ファイルI/O
// ... 処理 ...
$_settings = json_read('settings.json', settings_dir()); // 再度ファイルI/O

// After（最適化後）
$_settings = json_read('settings.json', settings_dir()); // ファイルI/O
// ... 処理 ...
$_settings = json_read('settings.json', settings_dir()); // キャッシュヒット（I/Oなし）
```

**効果**: 同一リクエスト内での重複読み込みが完全に排除され、I/O回数が大幅に削減。

### 9.3 Static-First配信の効果

```apache
# 静的ファイル存在チェック → Apache直接配信（PHPを経由しない）
RewriteCond static/%1/index.html -f
RewriteCond %{QUERY_STRING} !(?:^|&)(ap_action|ap_api|admin|login)
RewriteRule ^([a-zA-Z0-9_-]+)/?$ static/$1/index.html [L]
```

**効果**: 
- PHPインタープリター起動なし
- データベースアクセスなし（そもそもDBなし）
- テンプレートレンダリングなし
- **レスポンス時間**: 数百ms → 数ms（約100倍高速化）

### 9.4 差分ビルドの効果

```php
// 設定ハッシュ
$settingsHash = md5(json_encode($this->settings));

// ページハッシュ
foreach ($this->pages as $slug => $content) {
    $contentHash = md5($content);
    $stored = $this->buildState['pages'][$slug]['content_hash'] ?? '';
    
    if ($settingsChanged || $contentHash !== $stored) {
        $this->generatePage($slug, $content); // 変更ページのみ生成
    } else {
        $skipped[] = $slug; // スキップ
    }
}
```

**効果**:
- 100ページのサイトで1ページのみ変更 → 99ページはスキップ
- **ビルド時間**: 数十秒 → 1秒以下（約30倍高速化）

---

## 10. 今後の拡張性

### 10.1 エンジンの追加

現在16エンジンが実装済みですが、新しいエンジンの追加は容易です：

```php
// 例: SearchEngine.php を追加する場合

// 1. engines/SearchEngine.php を作成
class SearchEngine {
    public static function handle(): void {
        $action = $_GET['ap_search'] ?? null;
        if ($action === null) return;
        
        // 全文検索処理
        // ...
    }
    
    public static function index(string $content): void {
        // 検索インデックス作成
        // ...
    }
}

// 2. index.php に追加
require 'engines/SearchEngine.php';

// 3. ディスパッチに追加
SearchEngine::handle();
```

### 10.2 プラグインシステムの実装案

```php
// engines/PluginEngine.php（未実装）

class PluginEngine {
    private static array $plugins = [];
    
    public static function register(string $name, callable $handler): void {
        self::$plugins[$name] = $handler;
    }
    
    public static function execute(string $name, array $args = []): mixed {
        if (!isset(self::$plugins[$name])) {
            return null;
        }
        
        return call_user_func(self::$plugins[$name], ...$args);
    }
    
    public static function loadFromDirectory(string $dir): void {
        foreach (glob($dir . '/*.php') as $file) {
            require_once $file;
        }
    }
}

// プラグイン例: plugins/SyntaxHighlight.php
PluginEngine::register('syntax_highlight', function(string $code, string $lang) {
    // シンタックスハイライト処理
    return highlighted_html;
});

// 使用例
$highlighted = PluginEngine::execute('syntax_highlight', [$code, 'php']);
```

### 10.3 マルチサイト対応案

```php
// 環境変数でサイトIDを判定
$siteId = $_ENV['AP_SITE_ID'] ?? 'default';

// データディレクトリをサイトごとに分離
function data_dir(): string {
    global $siteId;
    $dir = "data/{$siteId}";
    if(!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

// 設定も分離
$_settings = json_read('settings.json', settings_dir());
```

### 10.4 データベース対応案

現在はフラットファイル（JSON）ですが、大規模サイト向けにデータベース対応も可能：

```php
// engines/DatabaseEngine.php（未実装）

class DatabaseEngine {
    private static ?PDO $pdo = null;
    
    public static function connect(): void {
        $dsn = $_ENV['DB_DSN'] ?? 'sqlite:' . data_dir() . '/ap.db';
        $user = $_ENV['DB_USER'] ?? null;
        $pass = $_ENV['DB_PASS'] ?? null;
        
        self::$pdo = new PDO($dsn, $user, $pass);
    }
    
    public static function query(string $sql, array $params = []): array {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// json_read() の代替
function db_read(string $table, array $where = []): array {
    if (class_exists('DatabaseEngine') && DatabaseEngine::isEnabled()) {
        return DatabaseEngine::select($table, $where);
    }
    
    // フォールバック: JSON
    return json_read($table . '.json');
}
```

### 10.5 キャッシュ戦略の拡張

```php
// engines/CacheEngine.php を拡張

class CacheEngine {
    // 既存: ファイルベースキャッシュ
    
    // 追加: Redis対応
    public static function useRedis(string $host, int $port): void {
        self::$redis = new Redis();
        self::$redis->connect($host, $port);
    }
    
    public static function get(string $key): ?string {
        if (self::$redis) {
            return self::$redis->get($key);
        }
        
        // フォールバック: ファイル
        return self::getFromFile($key);
    }
    
    // 追加: Memcached対応
    public static function useMemcached(array $servers): void {
        self::$memcached = new Memcached();
        self::$memcached->addServers($servers);
    }
}
```

---

## 結論

AdlairePlatform は、**エンジン分離アーキテクチャ**により高い拡張性と保守性を実現した、現代的なフラットファイルCMSです。

### 強み

1. **データベース不要** - シンプルなホスティング環境で動作
2. **セキュアな設計** - 多層防御によるセキュリティ対策
3. **パフォーマンス** - Static-First配信とキャッシュ戦略
4. **開発者体験** - エンジン単位での機能追加が容易
5. **ヘッドレス対応** - REST APIによる柔軟な連携

### 技術スタック

- **バックエンド**: PHP 8.2+
- **フロントエンド**: Vanilla JavaScript（ES2020+）
- **データストレージ**: JSON フラットファイル
- **テンプレート**: 独自テンプレートエンジン（Mustacheライク）
- **認証**: bcrypt + セッション
- **API**: REST + CORS対応

### コード品質

- **総行数**: 約17,000行
- **エンジン構成**: 16エンジン
- **セキュリティ対策**: 30項目以上
- **ドキュメント**: 詳細なREADME・機能一覧

### 今後の可能性

- プラグインシステム
- マルチサイト対応
- データベース対応（オプション）
- Redis/Memcachedキャッシュ
- ElasticsearchやAlgoliaとの連携

---

**このレポートは、2026年3月13日時点のソースコードを元に作成されました。**
