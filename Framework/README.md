# Adlaire Framework

**Version 1.5.0** | 将来的に独立Public化予定

## 概要

Adlaire Framework は、**3ファイルのエンジン駆動モデル**で構成された統合フレームワーク群です。

**設計原則**: 各フレームワークは厳密に3ファイル構成で、エンジン駆動アーキテクチャを採用しています。

**Ver.1.5**: 全 6 フレームワーク（18エンジン）が Adlaire Platform の engines/ に統合済み。

### フレームワーク構成

| 略称 | 正式名称 | 言語 | 状態 |
|------|---------|------|------|
| **AP** | Adlaire Platform Controllers | PHP 8.3+ | 実装済み・統合済み |
| **APF** | Adlaire Platform Foundation | PHP 8.3+ | 実装済み・統合済み |
| **AEB** | Adlaire Editor & Blocks | JavaScript ES6+ | 実装済み・統合済み |
| **ADS** | Adlaire Design System | CSS3 | 実装済み・統合済み |
| **ASG** | Adlaire Static Generator | PHP 8.3+ | 実装済み・統合済み |
| **ACE** | Adlaire Content Engine | PHP 8.3+ | 実装済み・統合済み |
| **AIS** | Adlaire Infrastructure Services | PHP 8.3+ | 実装済み・統合済み |

---

## 設計原則

### 3ファイルのエンジン駆動モデル
各フレームワークは **3つのエンジンファイル** で構成されています:
- **Engine 1** - Core/Base エンジン (基本機能)
- **Engine 2** - Blocks/Components エンジン (コンポーネント)
- **Engine 3** - Utils/Specialized エンジン (ユーティリティ)

### エンジン駆動アーキテクチャ
- 各ファイルが独立したエンジンとして機能
- 疎結合で拡張可能な設計
- モジュール間の依存性を最小化
- 将来的な独立Public化を想定

---

## ディレクトリ構造

```
Framework/
├── AP/                           # Platform Controllers (Ver.2.0)
│   └── AP.Controllers.php       (単一ファイル) - 全 Controller クラス統合
│
├── APF/                          # PHP Platform Foundation
│   ├── APF.Core.php             (15KB) - Container, Router, Request, Response, Middleware
│   ├── APF.Database.php         (19KB) - Connection, QueryBuilder, Model, Schema
│   └── APF.Utilities.php        (18KB) - Validator, Cache, Logger, Session, Security, Str, Arr
│
├── AEB/                          # JavaScript Editor & Blocks
│   ├── AEB.Core.js              (12KB) - Editor, EventBus, BlockRegistry, StateManager, HistoryManager
│   ├── AEB.Blocks.js            (16KB) - 全ブロックタイプ (10種類)
│   └── AEB.Utils.js             (13KB) - sanitizer, dom, selection, keyboard
│
├── ADS/                          # CSS Design System
│   ├── ADS.Base.css             (14KB) - variables, reset, typography, utilities
│   ├── ADS.Components.css        (9KB) - buttons, forms, cards, modals, alerts, badges
│   └── ADS.Editor.css           (12KB) - editor-base, blocks, toolbar
│
└── docs/                         # Documentation
```

**エンジン構成**: 6フレームワーク × 3エンジン = 18エンジンファイル (~278KB)

---

## APF (Adlaire Platform Foundation) - PHP本体エンジン

### 特徴
- **エンジン駆動**: 3エンジンファイル構成
- **独自設計**: PSR非準拠の軽量実装
- **PHP 8.3+**: モダンPHP機能活用
- **依存ゼロ**: 外部ライブラリ不要
- **完全機能**: Container, Router, ORM, Cache, Logger, Validator

### エンジン構成

#### APF.Core.php (15KB)
- `Container` - 依存性注入コンテナ (bind, singleton, make, resolve, lazy, bindIf)
- `Router` - ルーティング (GET/POST/PUT/PATCH/DELETE, group, middleware)
- `Request` - HTTPリクエスト (query, post, json, file, cookie, header)
- `Response` - HTTPレスポンス (json, html, redirect, withHeader, withCookie)
- `Middleware` - ミドルウェア基底クラス
- `HookManager` - フック管理 (register, run, filter, has, remove, clear)
- `PluginManager` - プラグイン管理 (register, boot, loadPlugin, isLoaded, getAll, disable)
- `DebugCollector` - デバッグ情報収集 (enable, logEvent, startTimer, stopTimer, logQuery, getReport)
- `ErrorBoundary` - エラー境界 (register, wrap, wrapResponse)
- 例外クラス: FrameworkException, ContainerException, NotFoundException, RoutingException, ValidationException, MiddlewareException

#### APF.Database.php (19KB)
- `Connection` - データベース接続 (query, insert, update, delete, transaction)
- `QueryBuilder` - クエリビルダー (select, where, join, orderBy, limit, offset)
- `Model` - ORM基底クラス (all, find, create, save, delete, toArray, toJson)
- `Schema` - スキーマビルダー (create, drop, hasTable)
- `Blueprint` - テーブル定義 (id, string, text, integer, timestamps)

#### APF.Utilities.php (18KB)
- `Validator` - バリデーション (required, email, min, max, numeric, url, date, confirmed, regex, between, size, array, json, ip, uuid, slug)
- `Cache` - ファイルキャッシュ (get, set, delete, remember, forever)
- `Logger` - ログシステム (debug, info, warning, error, critical)
- `Session` - セッション管理 (get, set, flash, regenerate)
- `Security` - セキュリティ (hash, verify, csrf, encrypt, decrypt, rateLimit)
- `Str` - 文字列ヘルパー (slug, camel, snake, kebab, random, limit)
- `Arr` - 配列ヘルパー (get, set, only, except, flatten, pluck, where)

### 使用方法

```php
<?php
require 'Framework/APF/APF.Core.php';
require 'Framework/APF/APF.Database.php';
require 'Framework/APF/APF.Utilities.php';

use APF\Core\{Container, Router, Request, Response};
use APF\Database\{Connection, Model};
use APF\Utilities\{Validator, Cache, Logger, Session, Security};

// Container
$container = new Container();
$container->singleton(Connection::class, fn() => new Connection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'mydb',
    'username' => 'root',
    'password' => ''
]));

// Router
$router = new Router($container);

$router->get('/', function(Request $request) {
    return Response::json(['message' => 'Hello APF']);
});

$response = $router->dispatch(new Request());
$response->send();
```

---

## AEB (Adlaire Editor & Blocks) - JavaScriptエンジン

### 特徴
- **エンジン駆動**: イベント駆動3エンジン構成
- **モジュール式**: 拡張可能なブロックシステム
- **型安全**: JSDoc完備
- **高機能**: Undo/Redo, オートセーブ, 履歴管理

### エンジン構成

#### AEB.Core.js (12KB)
- `Editor` - メインコントローラー
- `EventBus` - Pub/Subイベントシステム
- `BlockRegistry` - ブロック型管理
- `StateManager` - リアクティブ状態管理
- `HistoryManager` - Undo/Redo システム

#### AEB.Blocks.js (16KB)
- `BaseBlock` - 抽象基底クラス
- `ParagraphBlock`, `HeadingBlock` (H2/H3), `ListBlock`, `QuoteBlock`, `CodeBlock`, `ImageBlock`, `TableBlock`, `ChecklistBlock`, `DelimiterBlock`

#### AEB.Utils.js (13KB)
- `sanitizer` - HTMLサニタイゼーション (XSS防御)
- `dom` - DOM操作ヘルパー
- `selection` - 選択範囲ユーティリティ
- `keyboard` - キーボードショートカット

### 使用方法

```javascript
import { Editor } from './Framework/AEB/AEB.Core.js';
import * as Blocks from './Framework/AEB/AEB.Blocks.js';

const editor = new Editor({
  holder: document.getElementById('editor'),
  autosave: true
});

editor.blocks.register('paragraph', Blocks.ParagraphBlock);
editor.blocks.register('heading', Blocks.HeadingBlock);

editor.render([
  { type: 'heading', data: { text: 'Title', level: 2 } },
  { type: 'paragraph', data: { text: 'Content...' } }
]);
```

---

## ADS (Adlaire Design System) - CSSエンジン

### 特徴
- **エンジン駆動**: 3CSSエンジン構成
- **ユーティリティファースト**: 柔軟なクラス設計
- **モダンCSS**: CSS変数、Grid、Flexbox
- **レスポンシブ**: モバイルファースト
- **ダークモード**: 自動対応

### エンジン構成

#### ADS.Base.css (14KB)
- CSS変数 (色、スペース、タイポグラフィ): `--ads-primary`, `--ads-bg`, `--ads-text` 等
- モダンCSSリセット
- タイポグラフィスタイル
- ユーティリティクラス (spacing, display, flexbox, text, border, shadow)

#### ADS.Components.css (9KB)
- `.ads-btn`, `.ads-btn-primary`, `.ads-btn-secondary`, `.ads-btn-outline`, `.ads-btn-ghost`
- `.ads-card`, `.ads-form`, `.ads-input`, `.ads-textarea`, `.ads-select`
- `.ads-modal`, `.ads-alert`, `.ads-badge`, `.ads-tooltip`, `.ads-spinner`

#### ADS.Editor.css (12KB)
- `.aeb-editor`, `.aeb-block`, `.aeb-toolbar` 等エディタ特化スタイル

### 使用方法

```html
<link rel="stylesheet" href="./Framework/ADS/ADS.Base.css">
<link rel="stylesheet" href="./Framework/ADS/ADS.Components.css">

<button class="ads-btn ads-btn-primary">Primary Button</button>

<div class="ads-card">
  <div class="ads-card-header">
    <h2 class="ads-card-title">Card Title</h2>
  </div>
  <div class="ads-card-body">
    <p>Card content...</p>
  </div>
</div>
```

---

## Adlaire Platform への統合状況（Ver.1.5）

Ver.1.5 で全 6 フレームワークの全エンジンが AP に統合されました。
engines/ の static ファサードを維持しつつ、内部を Framework モジュールに委譲する **Static Facade パターン** を採用。

### インフラ基盤

| 新規ファイル | 役割 |
|-------------|------|
| `autoload.php` | `spl_autoload_register` で Framework 名前空間を自動ロード（12プレフィックス） |
| `bootstrap.php` | `Application` クラス（DI Container, HookManager, EventDispatcher） |
| `engines/Bridge.php` | index.php から分離したユーティリティ関数集約 |
| `engines/JsEngine/aeb-adapter.js` | AEB ES6 モジュール → グローバルスコープブリッジ |

### PHP エンジン委譲マッピング（全 16 エンジン）

| AP エンジン | Framework 委譲先 | getter メソッド |
|-----------|----------------|----------------|
| AppContext | `AIS\Core\AppContext` | `getInstance()` |
| Logger | `AIS\System\AppLogger` | `getAppLogger()` |
| CacheEngine | `AIS\System\CacheStore` | `getStore()` |
| DiagnosticEngine | `AIS\System\DiagnosticsCollector` | `getCollector()` |
| AdminEngine | `ACE\Admin\AuthManager` | `getAuthManager()` |
| ApiEngine | `ACE\Api\ApiRouter` | `getRouter()` |
| CollectionEngine | `ACE\Core\CollectionManager` | `getManager()` |
| WebhookEngine | `ACE\Api\WebhookManager` | `getWebhookManager()` |
| TemplateEngine | `ASG\Template\TemplateRenderer` | `getRenderer()` |
| StaticEngine | `ASG\Core\Generator` | `getGenerator()` |
| MarkdownEngine | `ASG\Template\MarkdownParser` | `getParser()` |
| ImageOptimizer | `ASG\Utilities\ImageOptimizer` | `getOptimizer()` |
| UpdateEngine | `AIS\Deployment\Updater` | `getUpdater()` |
| GitEngine | `AIS\Deployment\GitSync` | `getGitSync()` |
| MailerEngine | `AIS\Deployment\Mailer` | `getMailer()` |
| Validator | `APF\Utilities\Validator` | `createFrameworkValidator()` |

### フロントエンド統合

| Framework 元 | AP 統合先 | 方式 |
|-------------|----------|------|
| ADS.Base.css | `dashboard.html` + テーマ admin-head フック | CSS リンク |
| ADS.Components.css | `dashboard.html` + テーマ admin-head フック | CSS リンク |
| ADS.Editor.css | `dashboard.html` + テーマ admin-head フック | CSS リンク |
| AEB.Core.js / AEB.Blocks.js / AEB.Utils.js | `aeb-adapter.js` → `window.AEB` | ES6 動的 import |

---

## 統計

| フレームワーク | エンジン数 | 総サイズ | アーキテクチャ |
|--------------|-----------|---------|--------------|
| **APF** | 3 engines | ~52KB | PHP 8.3+ エンジン駆動 |
| **ACE** | 3 engines | ~50KB | PHP 8.3+ エンジン駆動 |
| **AIS** | 3 engines | ~50KB | PHP 8.3+ エンジン駆動 |
| **ASG** | 3 engines | ~40KB | PHP 8.3+ エンジン駆動 |
| **AEB** | 3 engines | ~41KB | JavaScript ES6+ エンジン駆動 |
| **ADS** | 3 engines | ~35KB | CSS3 エンジン駆動 |
| **合計** | **18 engines** | **~268KB** | 統合エンジン駆動 |

---

## 開発ガイドライン

### エンジン命名規則
- **フレームワーク略称.エンジン名.拡張子** (例: `APF.Core.php`, `AEB.Core.js`, `ADS.Base.css`)
- **APF** = Adlaire Platform Foundation (PHPエンジン本体)
- **AEB** = Adlaire Editor & Blocks (JavaScriptエンジン)
- **ADS** = Adlaire Design System (CSSエンジン)
- PascalCase for frameworks and engine modules

### コードスタイル
- **PHP 8.3+**: 型宣言、名前空間、クロージャ
- **JavaScript ES6+**: modules (import/export)
- **CSS3**: custom properties, Grid, Flexbox
- JSDoc comments for public APIs
- BEM-like class naming (`.ads-component-element`)

### 追加開発
**各フレームワークは厳密に3ファイル構成を維持**:

#### APF (PHP本体)
1. **APF.Core.php** - Container, Router, Request, Response, Middleware, HookManager, PluginManager, DebugCollector, ErrorBoundary
2. **APF.Database.php** - Connection, QueryBuilder, Model, Schema
3. **APF.Utilities.php** - Validator, Cache, Logger, Session, Security, Helpers

#### AEB (JavaScript)
1. **AEB.Core.js** - Editor, EventBus, BlockRegistry, StateManager, HistoryManager
2. **AEB.Blocks.js** - 全ブロックタイプ実装
3. **AEB.Utils.js** - sanitizer, dom, selection, keyboard

#### ADS (CSS)
1. **ADS.Base.css** - variables, reset, typography, utilities
2. **ADS.Components.css** - 再利用可能コンポーネント
3. **ADS.Editor.css** - エディタ特化スタイル

---

## 実装済みフレームワーク全体像

全 **18エンジン** が実装・統合済み:

| フレームワーク | エンジン1 | エンジン2 | エンジン3 |
|-------------|----------|----------|----------|
| **APF** | APF.Core.php | APF.Database.php | APF.Utilities.php |
| **ACE** | ACE.Core.php | ACE.Admin.php | ACE.Api.php |
| **AIS** | AIS.Core.php | AIS.System.php | AIS.Deployment.php |
| **ASG** | ASG.Core.php | ASG.Template.php | ASG.Utilities.php |
| **AEB** | AEB.Core.js | AEB.Blocks.js | AEB.Utils.js |
| **ADS** | ADS.Base.css | ADS.Components.css | ADS.Editor.css |

---

### Public独立化 (時期未定)
- **Adlaire-Framework** として独立リポジトリ化
- Composer/npm/CDN 公開
- 包括的ドキュメントサイト
- コミュニティ貢献ガイドライン

---

## 関連ドキュメント

- [docs/README.md](./docs/README.md) - ドキュメント一覧
- [docs/FUTURE_ROADMAP.md](./docs/FUTURE_ROADMAP.md) - 将来計画 (ASG/ACE/AIS)
- [docs/AFE_IMPROVEMENT_ROADMAP_V2.md](./docs/AFE_IMPROVEMENT_ROADMAP_V2.md) - APF改良ロードマップ
- [docs/EDITOR_CSS_FRAMEWORK_DESIGN.md](./docs/EDITOR_CSS_FRAMEWORK_DESIGN.md) - AEB/ADS設計詳細
- [docs/WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md](./docs/WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md) - エディタ改良提案

---

## ライセンス

Adlaire Platformプロジェクトの一部

---

**Last Updated**: 2026-03-14
**Version**: 1.5.0
**Status**: Production Ready — 全モジュール AP 統合済み
