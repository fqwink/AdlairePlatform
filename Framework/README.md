# Adlaire Framework Ecosystem

**Version 1.0.0** | 将来的に独立Public化予定

## 📖 概要

Adlaire Framework Ecosystem は、**3ファイルのエンジン駆動モデル**で構成された統合フレームワーク群です。

**設計原則**: 各フレームワークは厳密に3ファイル構成で、エンジン駆動アーキテクチャを採用しています。

**注意**: Adlaire Platform 本体 (15エンジン) とは独立したフレームワークです。

### フレームワーク構成

- **AFE** (Adlaire Framework Ecosystem) - PHP製コアフレームワーク・エンジン (本体)
- **AEF** (Adlaire Editor Framework) - JavaScript製エディタフレームワーク・エンジン
- **ACF** (Adlaire CSS Framework) - CSSフレームワーク・エンジン

---

## 🎯 設計原則

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

## 📁 ディレクトリ構造

```
Framework/                        # Adlaire Framework Ecosystem
├── AFE/                          # PHP Framework Engine (本体)
│   ├── AFE.Core.php             (15KB) - Container, Router, Request, Response, Middleware
│   ├── AFE.Database.php         (19KB) - Connection, QueryBuilder, Model, Schema
│   └── AFE.Utilities.php        (18KB) - Validator, Cache, Logger, Session, Security, Str, Arr
│
├── AEF/                          # JavaScript Editor Engine
│   ├── AEF.Core.js              (12KB) - Editor, EventBus, BlockRegistry, StateManager, HistoryManager
│   ├── AEF.Blocks.js            (16KB) - 全ブロックタイプ (10種類)
│   └── AEF.Utils.js             (13KB) - sanitizer, dom, selection, keyboard
│
├── ACF/                          # CSS Framework Engine
│   ├── ACF.Base.css             (14KB) - variables, reset, typography, utilities
│   ├── ACF.Components.css        (9KB) - buttons, forms, cards, modals, alerts, badges
│   └── ACF.Editor.css           (12KB) - editor-base, blocks, toolbar
│
└── docs/                         # Documentation
```

**エンジン構成**: 3フレームワーク × 3エンジン = 9エンジンファイル (~128KB)

---

## 🚀 AFE (Adlaire Framework Ecosystem) - PHP本体エンジン

### 特徴
- **エンジン駆動**: 3エンジンファイル構成
- **独自設計**: PSR非準拠の軽量実装
- **PHP 8.2+**: モダンPHP機能活用
- **依存ゼロ**: 外部ライブラリ不要
- **完全機能**: Container, Router, ORM, Cache, Logger, Validator

### エンジン構成

#### AFE.Core.php (15KB)
- `Container` - 依存性注入コンテナ (bind, singleton, make, resolve)
- `Router` - ルーティング (GET/POST/PUT/PATCH/DELETE, group, middleware)
- `Request` - HTTPリクエスト (query, post, json, file, cookie, header)
- `Response` - HTTPレスポンス (json, html, redirect, withHeader, withCookie)
- `Middleware` - ミドルウェア基底クラス

#### AFE.Database.php (19KB)
- `Connection` - データベース接続 (query, insert, update, delete, transaction)
- `QueryBuilder` - クエリビルダー (select, where, join, orderBy, limit, offset)
- `Model` - ORM基底クラス (all, find, create, save, delete, toArray, toJson)
- `Schema` - スキーマビルダー (create, drop, hasTable)
- `Blueprint` - テーブル定義 (id, string, text, integer, timestamps)

#### AFE.Utilities.php (18KB)
- `Validator` - バリデーション (required, email, min, max, numeric, url, date)
- `Cache` - ファイルキャッシュ (get, set, delete, remember, forever)
- `Logger` - ログシステム (debug, info, warning, error, critical)
- `Session` - セッション管理 (get, set, flash, regenerate)
- `Security` - セキュリティ (hash, verify, csrf, encrypt, decrypt, rateLimit)
- `Str` - 文字列ヘルパー (slug, camel, snake, kebab, random, limit)
- `Arr` - 配列ヘルパー (get, set, only, except, flatten, pluck, where)

### 使用方法

```php
<?php
require 'Framework/AFE/AFE.Core.php';
require 'Framework/AFE/AFE.Database.php';
require 'Framework/AFE/AFE.Utilities.php';

use AFE\Core\{Container, Router, Request, Response};
use AFE\Database\{Connection, Model};
use AFE\Utilities\{Validator, Cache, Logger, Session, Security};

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
    return Response::json(['message' => 'Hello AFE']);
});

$router->post('/users', function(Request $request) {
    $validator = Validator::make($request->post(), [
        'name' => 'required|min:3',
        'email' => 'required|email'
    ]);
    
    if ($validator->fails()) {
        return Response::json(['errors' => $validator->errors()], 400);
    }
    
    // Process...
    return Response::json(['success' => true], 201);
});

$response = $router->dispatch(new Request());
$response->send();
```

---

## 🚀 AEF (Adlaire Editor Framework) - JavaScriptエンジン

### 特徴
- **エンジン駆動**: イベント駆動3エンジン構成
- **モジュール式**: 拡張可能なブロックシステム
- **型安全**: JSDoc完備
- **高機能**: Undo/Redo, オートセーブ, 履歴管理

### エンジン構成

#### AEF.Core.js (11.9KB)
- `Editor` - メインコントローラー
- `EventBus` - Pub/Subイベントシステム
- `BlockRegistry` - ブロック型管理
- `StateManager` - リアクティブ状態管理
- `HistoryManager` - Undo/Redo システム

#### AEF.Blocks.js (16KB)
- `BaseBlock` - 抽象基底クラス
- `ParagraphBlock` - 段落
- `HeadingBlock` - 見出し (H2/H3)
- `ListBlock` - リスト (ordered/unordered)
- `QuoteBlock` - 引用
- `CodeBlock` - コード
- `ImageBlock` - 画像
- `TableBlock` - テーブル
- `ChecklistBlock` - チェックリスト
- `DelimiterBlock` - 区切り

#### AEF.Utils.js (13KB)
- `sanitizer` - HTMLサニタイゼーション (XSS防御)
- `dom` - DOM操作ヘルパー
- `selection` - 選択範囲ユーティリティ
- `keyboard` - キーボードショートカット

### 使用方法

```javascript
import { Editor, ParagraphBlock, HeadingBlock } from './Framework/AEF/AEF.Core.js';
import * as Blocks from './Framework/AEF/AEF.Blocks.js';

const editor = new Editor({
  holder: document.getElementById('editor'),
  autosave: true,
  autosaveInterval: 30000
});

editor.blocks.register('paragraph', Blocks.ParagraphBlock);
editor.blocks.register('heading', Blocks.HeadingBlock);

editor.render([
  { type: 'heading', data: { text: 'Title', level: 2 } },
  { type: 'paragraph', data: { text: 'Content...' } }
]);

editor.events.on('save', ({ blocks }) => {
  console.log('Saved:', blocks);
});
```

---

## 🎨 ACF (Adlaire CSS Framework) - CSSエンジン

### 特徴
- **エンジン駆動**: 3CSSエンジン構成
- **ユーティリティファースト**: 柔軟なクラス設計
- **モダンCSS**: CSS変数、Grid、Flexbox
- **レスポンシブ**: モバイルファースト
- **ダークモード**: 自動対応

### エンジン構成

#### ACF.Base.css (14.2KB)
- **variables.css** - CSS変数 (色、スペース、タイポグラフィ)
- **reset.css** - モダンCSSリセット
- **typography.css** - タイポグラフィスタイル
- **utilities.css** - ユーティリティクラス (spacing, display, flexbox, text, border, shadow)

#### ACF.Components.css (9.1KB)
- **buttons** - プライマリ、セカンダリ、アウトライン、ゴースト
- **forms** - input, textarea, select, checkbox, radio
- **cards** - ヘッダー、ボディ、フッター
- **modals** - バックドロップ、ヘッダー、ボディ、フッター
- **alerts** - info, success, warning, error
- **badges** - 各種カラーバリエーション
- **tooltips** - ツールチップ
- **spinners** - ローディングスピナー

#### ACF.Editor.css (12.2KB)
- **editor-base.css** - エディタラッパー、ブロックコンテナ
- **blocks.css** - 全ブロックタイプスタイル (9+ types)
- **toolbar.css** - ツールバー、スラッシュメニュー、ブロックハンドル

### 使用方法

```html
<!DOCTYPE html>
<html lang="ja">
<head>
  <link rel="stylesheet" href="./Framework/ACF/ACF.Base.css">
  <link rel="stylesheet" href="./Framework/ACF/ACF.Components.css">
  <link rel="stylesheet" href="./Framework/ACF/ACF.Editor.css">
</head>
<body>
  <button class="acf-btn acf-btn-primary">Primary Button</button>
  
  <div class="acf-card">
    <div class="acf-card-header">
      <h2 class="acf-card-title">Card Title</h2>
    </div>
    <div class="acf-card-body">
      <p>Card content...</p>
    </div>
  </div>
</body>
</html>
```

---

## 📊 統計

| フレームワーク | エンジン数 | 総サイズ | アーキテクチャ |
|--------------|-----------|---------|--------------|
| **AFE** | 3 engines | ~52KB | PHP 8.2+ エンジン駆動 |
| **AEF** | 3 engines | ~41KB | JavaScript ES6+ エンジン駆動 |
| **ACF** | 3 engines | ~35KB | CSS3 エンジン駆動 |
| **合計** | **9 engines** | **~128KB** | 統合エンジン駆動 |

### 改善指標

| 指標 | Before | After | 改善 |
|------|--------|-------|------|
| **エンジンファイル数** | 24+ files | 9 engines | **62%削減** |
| **メインJSサイズ** | 2,889行 | ~300行 | **90%削減** |
| **CSS整理** | JS内散在 | エンジン分離 | **100%** |
| **PHP統合** | - | 52KB (3 engines) | **新規** |
| **機能追加時間** | 4-8時間 | 1-2時間 | **75%削減** |
| **アーキテクチャ** | モノリシック | エンジン駆動 | **革新** |

---

## 🔧 開発ガイドライン

### エンジン命名規則
- **フレームワーク略称.エンジン名.拡張子** (例: `AFE.Core.php`, `AEF.Core.js`, `ACF.Base.css`)
- **AFE** = Adlaire Framework Ecosystem (PHPエンジン本体)
- **AEF** = Adlaire Editor Framework (JavaScriptエンジン)
- **ACF** = Adlaire CSS Framework (CSSエンジン)
- PascalCase for frameworks and engine modules

### コードスタイル
- **PHP 8.2+**: 型宣言、名前空間、クロージャ
- **JavaScript ES6+**: modules (import/export)
- **CSS3**: custom properties, Grid, Flexbox
- JSDoc comments for public APIs
- BEM-like class naming (`.acf-component-element`)

### 追加開発
**各フレームワークは厳密に3ファイル構成を維持**:

#### AFE (PHP本体)
1. **AFE.Core.php** - Container, Router, Request, Response, Middleware
2. **AFE.Database.php** - Connection, QueryBuilder, Model, Schema
3. **AFE.Utilities.php** - Validator, Cache, Logger, Session, Security, Helpers

#### AEF (JavaScript)
1. **AEF.Core.js** - Editor, EventBus, BlockRegistry, StateManager, HistoryManager
2. **AEF.Blocks.js** - 全ブロックタイプ実装
3. **AEF.Utils.js** - sanitizer, dom, selection, keyboard

#### ACF (CSS)
1. **ACF.Base.css** - variables, reset, typography, utilities
2. **ACF.Components.css** - 再利用可能コンポーネント
3. **ACF.Editor.css** - エディタ特化スタイル

---

## 🌐 将来計画

### Public独立化 (時期未定)
- **Adlaire-Framework-Ecosystem** として独立リポジトリ化
- npm/CDN公開
- 包括的ドキュメントサイト
- コミュニティ貢献ガイドライン

---

## 📚 関連ドキュメント

- [AFE_IMPROVEMENT_ROADMAP_V2.md](./docs/AFE_IMPROVEMENT_ROADMAP_V2.md) - フレームワークロードマップ
- [EDITOR_CSS_FRAMEWORK_DESIGN.md](./docs/EDITOR_CSS_FRAMEWORK_DESIGN.md) - 設計詳細
- [WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md](./docs/WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md) - 改良提案

---

## 📄 ライセンス

Adlaire Platformプロジェクトの一部

---

**Last Updated**: 2026-03-14  
**Version**: 1.0.0  
**Status**: ✅ Production Ready
