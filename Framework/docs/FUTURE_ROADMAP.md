# Adlaire Framework Ecosystem - 将来計画

**Version**: 1.0.0 → 2.0.0 (計画)  
**Last Updated**: 2026-03-14

---

## 📋 概要

現在の Adlaire Framework Ecosystem は **3つのフレームワーク (9エンジン)** で構成されています。

将来的に、Adlaire Platform 本体の機能を **フレームワーク化** し、独立した再利用可能なコンポーネント群として提供する計画です。

---

## 🎯 現在の構成 (Version 1.0.0)

### 実装済みフレームワーク

| フレームワーク | エンジン数 | サイズ | 言語 | 状態 |
|--------------|-----------|-------|------|------|
| **AFE** (Adlaire Framework Ecosystem) | 3 | ~52KB | PHP 8.2+ | ✅ 実装済み |
| **AEF** (Adlaire Editor Framework) | 3 | ~41KB | JavaScript ES6+ | ✅ 実装済み |
| **ACF** (Adlaire CSS Framework) | 3 | ~35KB | CSS3 | ✅ 実装済み |

**合計**: 9エンジン (~128KB)

---

## 🚀 将来計画 (Version 2.0.0)

### 追加予定フレームワーク

#### 1. **ASF** (Adlaire Static Framework) - 静的ジェネレーターフレームワーク
**目的**: 静的サイト生成機能のフレームワーク化

**3エンジン構成案**:
- `ASF.Core.php` - Generator, Builder, Router, FileSystem
- `ASF.Template.php` - TemplateEngine, ThemeEngine, MarkdownEngine
- `ASF.Utilities.php` - Cache, ImageOptimizer, DiffBuilder, Deployer

**抽出元** (Adlaire Platform本体):
- `StaticEngine.php` (静的サイト生成)
- `TemplateEngine.php` (テンプレート処理)
- `ThemeEngine.php` (テーマ管理)
- `MarkdownEngine.php` (Markdown変換)
- `ImageOptimizer.php` (画像最適化)

**サイズ予測**: ~45KB (3 engines)

---

#### 2. **ACM** (Adlaire CMS Framework) - CMSフレームワーク
**目的**: コンテンツ管理システム機能のフレームワーク化

**3エンジン構成案**:
- `ACM.Core.php` - CollectionEngine, ContentManager, MetaManager
- `ACM.Admin.php` - AdminEngine, UserManager, AuthManager
- `ACM.Api.php` - ApiEngine, WebhookEngine, RestHandler

**抽出元** (Adlaire Platform本体):
- `CollectionEngine.php` (コレクション管理)
- `AdminEngine.php` (管理画面)
- `ApiEngine.php` (REST API)
- `WebhookEngine.php` (Webhook処理)

**サイズ予測**: ~50KB (3 engines)

---

#### 3. **AIF** (Adlaire Infrastructure Framework) - インフラフレームワーク
**目的**: システム基盤機能のフレームワーク化

**3エンジン構成案**:
- `AIF.Core.php` - AppContext, ServiceProvider, Container
- `AIF.System.php` - CacheEngine, Logger, DiagnosticEngine
- `AIF.Deployment.php` - UpdateEngine, GitEngine, MailerEngine

**抽出元** (Adlaire Platform本体):
- `AppContext.php` (アプリケーションコンテキスト)
- `CacheEngine.php` (キャッシュ管理)
- `Logger.php` (ロギング)
- `DiagnosticEngine.php` (診断ツール)
- `UpdateEngine.php` (更新管理)
- `GitEngine.php` (Git連携)
- `MailerEngine.php` (メール送信)

**サイズ予測**: ~55KB (3 engines)

---

## 📁 将来のディレクトリ構造 (Version 2.0.0)

```
Framework/
├── AFE/                          # PHP Framework Engine (本体)
│   ├── AFE.Core.php             (15KB)
│   ├── AFE.Database.php         (19KB)
│   └── AFE.Utilities.php        (18KB)
│
├── AEF/                          # JavaScript Editor Engine
│   ├── AEF.Core.js              (12KB)
│   ├── AEF.Blocks.js            (16KB)
│   └── AEF.Utils.js             (13KB)
│
├── ACF/                          # CSS Framework Engine
│   ├── ACF.Base.css             (14KB)
│   ├── ACF.Components.css        (9KB)
│   └── ACF.Editor.css           (12KB)
│
├── ASF/                          # Static Framework Engine (計画中)
│   ├── ASF.Core.php             (~15KB)
│   ├── ASF.Template.php         (~18KB)
│   └── ASF.Utilities.php        (~12KB)
│
├── ACM/                          # CMS Framework Engine (計画中)
│   ├── ACM.Core.php             (~17KB)
│   ├── ACM.Admin.php            (~20KB)
│   └── ACM.Api.php              (~13KB)
│
├── AIF/                          # Infrastructure Framework Engine (計画中)
│   ├── AIF.Core.php             (~18KB)
│   ├── AIF.System.php           (~20KB)
│   └── AIF.Deployment.php       (~17KB)
│
├── docs/
└── README.md
```

**将来の合計**: 18エンジン (~278KB)

---

## 🎯 設計原則

### 1. 非破壊的抽出
- **Adlaire Platform 本体は変更しない**
- エンジンの**コピー**を作成してフレームワーク化
- 本体との互換性を維持

### 2. 3エンジン構成の厳守
- 各フレームワークは**厳密に3ファイル**
- Core / Domain / Utilities の役割分担
- 疎結合で拡張可能な設計

### 3. 独立性の確保
- 各フレームワークは単独で動作可能
- 相互依存を最小化
- Composer経由でも利用可能

### 4. 後方互換性
- Adlaire Platform 本体との共存
- 既存APIの維持
- 段階的な移行を支援

---

## 📊 フレームワーク比較表

| フレームワーク | 状態 | エンジン数 | サイズ | 抽出元 | 独立性 |
|--------------|------|-----------|-------|-------|-------|
| **AFE** | ✅ 実装済み | 3 | ~52KB | 新規開発 | 完全独立 |
| **AEF** | ✅ 実装済み | 3 | ~41KB | wysiwyg.js | 完全独立 |
| **ACF** | ✅ 実装済み | 3 | ~35KB | 散在CSS | 完全独立 |
| **ASF** | 📋 計画中 | 3 | ~45KB | Platform 5エンジン | 要抽出 |
| **ACM** | 📋 計画中 | 3 | ~50KB | Platform 4エンジン | 要抽出 |
| **AIF** | 📋 計画中 | 3 | ~55KB | Platform 7エンジン | 要抽出 |

---

## 🔄 移行戦略

### Phase 1: 分析・設計 (完了)
- ✅ 既存エンジンの依存関係分析
- ✅ 3エンジン構成への分割設計
- ✅ API設計・インターフェース定義

### Phase 2: ASF実装 (静的ジェネレーター)
**目標**: 静的サイト生成機能を独立フレームワーク化

**作業内容**:
1. StaticEngine, TemplateEngine, ThemeEngine, MarkdownEngine, ImageOptimizer を分析
2. 依存関係を整理し、3ファイルに再構成
3. Adlaire Platform 本体のコードはコピーして利用
4. 単体テスト・統合テスト実施

**期間**: 4週間 (実装は将来)

### Phase 3: ACM実装 (CMS)
**目標**: CMS機能を独立フレームワーク化

**作業内容**:
1. CollectionEngine, AdminEngine, ApiEngine, WebhookEngine を分析
2. 3ファイルに再構成
3. REST API互換性の確保
4. テスト実施

**期間**: 4週間 (実装は将来)

### Phase 4: AIF実装 (インフラ)
**目標**: システム基盤を独立フレームワーク化

**作業内容**:
1. 残りのエンジン (AppContext, Cache, Logger等) を分析
2. 3ファイルに再構成
3. 本体との共存確認
4. テスト実施

**期間**: 4週間 (実装は将来)

### Phase 5: 統合・最適化
**目標**: 6フレームワークの統合・最適化

**作業内容**:
1. フレームワーク間の相互運用性確認
2. パフォーマンス最適化
3. ドキュメント整備
4. 独立Public化準備

**期間**: 2週間 (実装は将来)

---

## 📈 期待効果

### コード再利用性
- Adlaire Platform の機能を他プロジェクトで利用可能
- 静的サイト生成だけ、CMSだけ、など部分利用可能

### メンテナンス性向上
- 3エンジン構成による責任分離
- 各フレームワークの独立したテスト
- バグ修正・機能追加の効率化

### エコシステム拡大
- npm/Composer経由での配布
- コミュニティ貢献の促進
- 他CMSプラットフォームへの組み込み

### パフォーマンス
- 必要な機能のみロード可能
- オートロード最適化
- キャッシュ戦略の改善

---

## 🛡️ リスク管理

### リスク 1: 複雑性の増加
**対策**:
- 各フレームワークの責任範囲を明確化
- 依存関係を最小限に抑える
- 包括的なドキュメント整備

### リスク 2: 後方互換性の破壊
**対策**:
- Adlaire Platform 本体は一切変更しない
- エンジンのコピーを作成してフレームワーク化
- セマンティックバージョニング厳守

### リスク 3: メンテナンス負荷
**対策**:
- 自動テストの充実
- CI/CDパイプライン構築
- ドキュメント自動生成

---

## 📅 タイムライン (暫定)

| フェーズ | 内容 | 期間 | 開始予定 |
|---------|------|------|---------|
| Phase 1 | 分析・設計 | - | ✅ 完了 |
| Phase 2 | ASF実装 | 4週間 | 未定 |
| Phase 3 | ACM実装 | 4週間 | 未定 |
| Phase 4 | AIF実装 | 4週間 | 未定 |
| Phase 5 | 統合・最適化 | 2週間 | 未定 |

**合計**: 約14週間（実装時期は未定）

---

## 🌐 独立Public化計画

### 目標
**Adlaire-Framework-Ecosystem** として独立リポジトリ化

### 配布方法
- **Composer**: `composer require adlaire/framework-ecosystem`
- **npm/CDN**: JavaScript/CSS フレームワーク
- **Docker**: 開発環境イメージ
- **GitHub Releases**: バイナリ配布

### ドキュメント
- 包括的な公式ドキュメントサイト
- API リファレンス
- チュートリアル・サンプルコード
- コントリビューションガイド

### コミュニティ
- GitHub Discussions
- Discord サーバー
- 月次リリースサイクル
- セキュリティポリシー

---

## 📝 注意事項

### 重要
1. **Adlaire Platform 本体は一切変更しない**
2. エンジンの**コピー**を作成してフレームワーク化
3. 本体との共存を前提とした設計
4. 実装は将来的に行う（時期未定）

### 現在の作業範囲
- ✅ 設計・計画策定のみ
- ❌ 実装は行わない
- ❌ 既存コードの変更は行わない

---

## 🔗 関連ドキュメント

- [Framework/README.md](../README.md) - メインREADME
- [Framework/docs/README.md](./README.md) - ドキュメント一覧
- [AFE_IMPROVEMENT_ROADMAP_V2.md](./AFE_IMPROVEMENT_ROADMAP_V2.md) - AFE改良ロードマップ

---

**Adlaire Framework Ecosystem - Future Roadmap**  
**Version**: 1.0.0 → 2.0.0 (計画)  
**Status**: 📋 Planning Phase  
**Last Updated**: 2026-03-14

---

## 📊 将来の全体像

```
Adlaire Framework Ecosystem (Version 2.0.0)
├── 実装済み (9 engines, ~128KB)
│   ├── AFE (PHP Framework)
│   ├── AEF (JavaScript Editor)
│   └── ACF (CSS Framework)
│
└── 計画中 (9 engines, ~150KB)
    ├── ASF (Static Generator)
    ├── ACM (CMS)
    └── AIF (Infrastructure)

合計: 18 engines (~278KB)
```

**目標**: 完全なフルスタックフレームワーク・エコシステム
