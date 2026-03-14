# Adlaire Framework

**Version 1.0.0** | 将来的に独立Public化予定

## 概要

Adlaire Framework は、**3ファイルのエンジン駆動モデル**で構成された統合フレームワーク群です。

**設計原則**: 各フレームワークは厳密に3ファイル構成で、エンジン駆動アーキテクチャを採用しています。

**注意**: Adlaire Platform 本体 (15エンジン) とは独立したフレームワークです。

### フレームワーク構成

| 略称 | 正式名称 | 言語 | 状態 |
|------|---------|------|------|
| **APF** | Adlaire Platform Foundation | PHP 8.2+ | 実装済み |
| **AEB** | Adlaire Editor & Blocks | JavaScript ES6+ | 実装済み |
| **ADS** | Adlaire Design System | CSS3 | 実装済み |
| **ASG** | Adlaire Static Generator | PHP 8.2+ | 計画中 |
| **ACE** | Adlaire Content Engine | PHP 8.2+ | 計画中 |
| **AIS** | Adlaire Infrastructure Services | PHP 8.2+ | 計画中 |

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

**エンジン構成**: 3フレームワーク × 3エンジン = 9エンジンファイル (~128KB)

---

## APF (Adlaire Platform Foundation) - PHP本体エンジン

### 特徴
- **エンジン駆動**: 3エンジンファイル構成
- **独自設計**: PSR非準拠の軽量実装
- **PHP 8.2+**: モダンPHP機能活用
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

## Adlaire Platform への統合状況

以下のFrameworkコンポーネントは、APエンジンパターン（staticメソッド、名前空間なし、IIFE）に適応済みです。

| Framework 元 | AP 統合先 | 状態 |
|-------------|----------|------|
| ADS.Base.css (CSS変数・リセット) | `dashboard.html` で直接リンク | 統合済み |
| APF.Utilities.php Validator | `engines/Validator.php` として抽出 | 統合済み |
| AEB.Core.js EventBus | `engines/JsEngine/ap-events.js` として実装 | 統合済み |
| AEB.Utils.js 共通処理 | `engines/JsEngine/ap-utils.js` として実装 | 統合済み |

**削除済み**: `BaseEngine.php` — 存在しないFramework名前空間を参照していたため削除。APエンジンは `EngineTrait` を使用。

**未統合（保留）**: APF.Core.php (DI Container/Router), APF.Database.php (ORM), AEB.Blocks.js, ADS.Components.css, ADS.Editor.css — APはフラットファイルCMSのため現時点では不要。将来のPublic独立化時に活用。

---

## 統計

| フレームワーク | エンジン数 | 総サイズ | アーキテクチャ |
|--------------|-----------|---------|--------------|
| **APF** | 3 engines | ~52KB | PHP 8.2+ エンジン駆動 |
| **AEB** | 3 engines | ~41KB | JavaScript ES6+ エンジン駆動 |
| **ADS** | 3 engines | ~35KB | CSS3 エンジン駆動 |
| **合計** | **9 engines** | **~128KB** | 統合エンジン駆動 |

---

## 開発ガイドライン

### エンジン命名規則
- **フレームワーク略称.エンジン名.拡張子** (例: `APF.Core.php`, `AEB.Core.js`, `ADS.Base.css`)
- **APF** = Adlaire Platform Foundation (PHPエンジン本体)
- **AEB** = Adlaire Editor & Blocks (JavaScriptエンジン)
- **ADS** = Adlaire Design System (CSSエンジン)
- PascalCase for frameworks and engine modules

### コードスタイル
- **PHP 8.2+**: 型宣言、名前空間、クロージャ
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

## 将来計画

### Version 2.0.0 - 追加フレームワーク (計画中)

現在の **9エンジン (APF + AEB + ADS)** に加え、以下のフレームワークを追加予定:

#### **ASG** (Adlaire Static Generator) - 静的ジェネレーター
- `ASG.Core.php` - Generator, Builder, Router, FileSystem
- `ASG.Template.php` - TemplateEngine, ThemeEngine, MarkdownEngine
- `ASG.Utilities.php` - Cache, ImageOptimizer, DiffBuilder, Deployer

**抽出元**: Adlaire Platform の StaticEngine, TemplateEngine, ThemeEngine, MarkdownEngine, ImageOptimizer

#### **ACE** (Adlaire Content Engine) - CMS
- `ACE.Core.php` - CollectionEngine, ContentManager, MetaManager
- `ACE.Admin.php` - AdminEngine, UserManager, AuthManager
- `ACE.Api.php` - ApiEngine, WebhookEngine, RestHandler

**抽出元**: Adlaire Platform の CollectionEngine, AdminEngine, ApiEngine, WebhookEngine

#### **AIS** (Adlaire Infrastructure Services) - インフラ
- `AIS.Core.php` - AppContext, ServiceProvider, Container
- `AIS.System.php` - CacheEngine, Logger, DiagnosticEngine
- `AIS.Deployment.php` - UpdateEngine, GitEngine, MailerEngine

**抽出元**: Adlaire Platform の AppContext, CacheEngine, Logger, DiagnosticEngine, UpdateEngine, GitEngine, MailerEngine

**将来の合計**: 18エンジン (~278KB)

**詳細**: [FUTURE_ROADMAP.md](./docs/FUTURE_ROADMAP.md)

**注意**:
- Adlaire Platform 本体のソースコードは一切変更しない
- エンジンのコピーを作成してフレームワーク化
- 実装時期は未定

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
**Version**: 1.0.0
**Status**: Production Ready
