# Adlaire Platform Foundation - Documentation

**3ファイルのエンジン駆動モデル - 統合ドキュメント**

**Version**: 1.0.0  
**Status**: ✅ Production Ready (9 Engines) + 📋 Planning (9 Engines)  
**Last Updated**: 2026-03-14

---

## 📚 目次

1. [概要](#-概要)
2. [ドキュメント構成](#-ドキュメント構成)
3. [推奨読書順序](#-推奨読書順序)
4. [統計情報](#-統計情報)
5. [貢献ガイドライン](#-貢献ガイドライン)

---

## 📖 概要

Adlaire Platform Foundation は **3ファイルのエンジン駆動モデル** で構成された統合フレームワーク群です。

### 現在の構成 (Version 1.0.0)

| フレームワーク | エンジン数 | サイズ | 状態 |
|--------------|-----------|-------|------|
| **APF** (PHP Framework) | 3 | ~52KB | ✅ 実装済み |
| **AEB** (JavaScript Editor) | 3 | ~41KB | ✅ 実装済み |
| **ADS** (CSS Framework) | 3 | ~35KB | ✅ 実装済み |
| **合計** | **9 engines** | **~128KB** | **完成** |

### 将来計画 (Version 2.0.0)

| フレームワーク | エンジン数 | サイズ | 状態 |
|--------------|-----------|-------|------|
| **ASG** (Static Generator) | 3 | ~45KB | 📋 設計完了 |
| **ACE** (CMS Framework) | 3 | ~50KB | 📋 設計完了 |
| **AIS** (Infrastructure) | 3 | ~55KB | 📋 設計完了 |
| **追加合計** | **9 engines** | **~150KB** | **計画中** |

**将来の総合計**: 18エンジン (~278KB)

---

## 📁 ドキュメント構成

### 🌟 主要ドキュメント (必読)

#### 1. **[README.md](./README.md)** (本ドキュメント)
**統合インデックス - 全ドキュメントへの入口**

#### 2. **[FUTURE_ROADMAP.md](./FUTURE_ROADMAP.md)** ⭐ 最重要
**Version 2.0.0 将来計画書** (7KB)

将来追加予定の3フレームワーク (ASF/ACM/AIF) の完全設計。

**内容**:
- **ASG** (Adlaire Static Generator) - 静的ジェネレーター
  - 3エンジン構成: Core, Template, Utilities
  - 抽出元: StaticEngine, TemplateEngine, ThemeEngine, MarkdownEngine, ImageOptimizer
- **ACE** (Adlaire Content Engine) - CMS
  - 3エンジン構成: Core, Admin, Api
  - 抽出元: CollectionEngine, AdminEngine, ApiEngine, WebhookEngine
- **AIS** (Adlaire Infrastructure Services) - インフラ
  - 3エンジン構成: Core, System, Deployment
  - 抽出元: AppContext, CacheEngine, Logger, DiagnosticEngine, UpdateEngine, GitEngine, MailerEngine
- 非破壊的抽出戦略
- タイムライン (14週間、実施時期未定)
- リスク管理・期待効果

**対象**: プロジェクト全体像を把握したい全ての方

**重要**: Adlaire Platform 本体には一切触れず、コピーを作成してフレームワーク化

---

#### 3. **[EDITOR_CSS_FRAMEWORK_DESIGN.md](./EDITOR_CSS_FRAMEWORK_DESIGN.md)**
**AEB AEF & ACF 完全設計書** (31KB)

JavaScript エディタフレームワーク (AEB) と CSS フレームワーク (ADS) の技術詳細。

**内容**:
- **AEB (JavaScript Editor Engine)** - 3エンジン構成
  - AEB.Core.js (12KB) - Editor, EventBus, BlockRegistry, StateManager, HistoryManager
  - AEB.Blocks.js (16KB) - 10種類のブロック実装
  - AEB.Utils.js (13KB) - sanitizer, dom, selection, keyboard
  - イベント駆動アーキテクチャ
  - ブロック拡張方法
  - API リファレンス
- **ADS (CSS Framework Engine)** - 3エンジン構成
  - ADS.Base.css (14KB) - variables, reset, typography, utilities
  - ADS.Components.css (9KB) - buttons, forms, cards, modals, alerts, badges
  - ADS.Editor.css (12KB) - editor-base, blocks, toolbar
  - ユーティリティファースト設計
  - カスタマイズ方法
  - レスポンシブ設計
- 使用方法・サンプルコード
- パフォーマンス最適化
- 移行ガイド

**対象**: AEB/ADS 開発者・利用者

---

#### 4. **[WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md](./WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md)**
**WYSIWYG エディタ改良提案書** (42KB)

既存エディタ (wysiwyg.js) から AEF への移行計画。

**内容**:
- 現状分析 (2,889行のモノリシック構造)
- 問題点の詳細
  - CSS散在 (JS内200行 + テンプレート977行)
  - テストの欠如
  - 拡張困難
- AEF 移行による改善効果
  - コードサイズ 90% 削減 (2,889行 → ~300行)
  - 機能追加時間 75% 削減 (4-8時間 → 1-2時間)
  - CSS 100% 分離
- 段階的移行戦略 (4フェーズ)
- リスク管理
- テスト計画

**対象**: 既存プロジェクト移行担当者、アーキテクト

---

### 📘 技術詳細ドキュメント

#### 5. **[AFE_ENGINE_DRIVEN_MODEL.md](./AFE_ENGINE_DRIVEN_MODEL.md)**
**エンジン駆動モデル完全解説** (37KB)

APF (PHP本体) のコアアーキテクチャ詳細。

**内容**:
- エンジン駆動モデルの概念
- 3ファイル構成の設計原則
- エンジンライフサイクル
  - Register → Initialize → Boot → Runtime → Shutdown
- 依存性注入 (DI) システム
- イベント駆動アーキテクチャ
- 依存関係解決 (トポロジカルソート)
- エンジン開発ガイド
- ベストプラクティス

**対象**: APF (PHP) 開発者、アーキテクト

---

#### 6. **[ENGINE_FRAMEWORK_3FILES_PROPOSAL.md](./ENGINE_FRAMEWORK_3FILES_PROPOSAL.md)**
**3ファイル構成提案書** (31KB)

3エンジン構成の理論的基盤と実装詳細。

**内容**:
- 設計目標 (最小性・拡張性・後方互換性)
- 3ファイル構成の利点
  - 認知負荷の軽減
  - 依存関係の明確化
  - テスト容易性
- ファイル役割分担
  - Core: 基本機能・制御フロー
  - Domain: ドメインロジック・コンポーネント
  - Utilities: ヘルパー・ユーティリティ
- 実装パターン
- 移行ガイド

**対象**: フレームワーク設計者、アーキテクト

---

#### 7. **[ENGINE_DESIGN.md](./ENGINE_DESIGN.md)**
**Adlaire Platform エンジン設計書** (67KB)

Adlaire Platform 本体 (15エンジン) の詳細設計。

**内容**:
- 15エンジンの完全仕様
  - AdminEngine, ApiEngine, AppContext, CacheEngine, CollectionEngine
  - DiagnosticEngine, GitEngine, ImageOptimizer, Logger, MailerEngine
  - MarkdownEngine, StaticEngine, TemplateEngine, ThemeEngine, UpdateEngine, WebhookEngine
- 依存関係グラフ
- エンジン間連携
- 拡張ポイント

**対象**: Adlaire Platform 本体の理解が必要な上級者

**注意**: このドキュメントは参考資料（Platformの本体設計）

---

### 🗺️ ロードマップ・改良計画

#### 8. **[AFE_IMPROVEMENT_ROADMAP_V2.md](./AFE_IMPROVEMENT_ROADMAP_V2.md)**
**AFE 改良ロードマップ Ver.2** (49KB)

APF (PHP本体) の将来改良計画 (Ver 2.2.0 - 2.5.0)。

**内容**:
- Phase 2 (Ver 2.2.0): 実用的強化
  - 構造化ロギング
  - 依存グラフキャッシング
  - イベントトレーシング
  - ミドルウェアパイプライン
- Phase 3 (Ver 2.3.0): JavaScript 統合
- Phase 4 (Ver 2.4.0): 高度な最適化
  - APCuキャッシング
  - プリロード機能
- Phase 5 (Ver 2.5.0): エンタープライズ対応
  - セキュリティ強化
  - 国際化 (i18n)
- 実装計画 (121時間、11週間)
- ROI分析 (194%、投資回収期間4ヶ月)

**対象**: AFE 開発チーム、プロジェクトマネージャー

---

#### 9. **[AFE_IMPROVEMENT_PLAN.md](./AFE_IMPROVEMENT_PLAN.md)**
**AFE 改良計画 Ver.1** (14KB)

APF の初期改良計画 (Ver 2.1.0、実装済み)。

**内容**:
- エラーハンドリング強化
- バリデーション機構
- 遅延ロード
- テストヘルパー

**対象**: 参考資料（実装済み）

---

### 📦 アーカイブ (参考資料)

以下のドキュメントは `archive/` ディレクトリに移動されました。

- **ENGINE_DRIVEN_FRAMEWORK_PROPOSAL.md** (64KB) - エンジン駆動提案書 (初版)
- **ENGINE_DRIVEN_FRAMEWORK_PROPOSAL_REVISED.md** (55KB) - 提案書改訂版
- **ENGINE_FRAMEWORK_CORE_PROPOSAL.md** (14KB) - コア提案書
- **ENGINE_FRAMEWORK_PROJECT_SUMMARY.md** (12KB) - プロジェクトサマリー
- **AFE_ADVANCED_IMPROVEMENTS_v1_archived.md** (21KB) - 高度な改良案 (v1)

これらは歴史的資料として保管されています。

---

## 🎯 推奨読書順序

### 📌 レベル1: 初心者 - 全体像の把握

**目的**: Adlaire Platform Foundation の全体像を理解する

**推奨ドキュメント**:
1. **[../README.md](../README.md)** (親ディレクトリ) - メインREADME (15分)
2. **[FUTURE_ROADMAP.md](./FUTURE_ROADMAP.md)** ⭐ - 将来計画 (30分)
3. **[EDITOR_CSS_FRAMEWORK_DESIGN.md](./EDITOR_CSS_FRAMEWORK_DESIGN.md)** - AEB/ADS設計 (40分)

**所要時間**: 約1.5時間

**得られる知識**:
- 9エンジン構成の理解
- 将来の18エンジン構想
- AEB/ADSの使用方法

---

### 📌 レベル2: 開発者 - 実装詳細の理解

**目的**: 各フレームワークの開発・利用方法を習得

#### パターンA: AEB/ADS (JavaScript/CSS) 開発者

**推奨ドキュメント**:
1. **[EDITOR_CSS_FRAMEWORK_DESIGN.md](./EDITOR_CSS_FRAMEWORK_DESIGN.md)** - 設計詳細 (1時間)
2. **[WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md](./WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md)** - 移行計画 (1時間)
3. **[../AEF/](../AEF/)** および **[../ACF/](../ACF/)** - ソースコード確認 (30分)

**所要時間**: 約2.5時間

**得られる知識**:
- AEB/ADS API完全理解
- カスタムブロックの作成方法
- CSS カスタマイズ方法

---

#### パターンB: APF (PHP) 開発者

**推奨ドキュメント**:
1. **[AFE_ENGINE_DRIVEN_MODEL.md](./AFE_ENGINE_DRIVEN_MODEL.md)** - エンジン駆動モデル (1.5時間)
2. **[ENGINE_FRAMEWORK_3FILES_PROPOSAL.md](./ENGINE_FRAMEWORK_3FILES_PROPOSAL.md)** - 3ファイル構成 (1時間)
3. **[AFE_IMPROVEMENT_ROADMAP_V2.md](./AFE_IMPROVEMENT_ROADMAP_V2.md)** - 改良ロードマップ (1時間)
4. **[../AFE/](../AFE/)** - ソースコード確認 (30分)

**所要時間**: 約4時間

**得られる知識**:
- エンジン駆動アーキテクチャ
- 依存性注入システム
- AFE API完全理解

---

### 📌 レベル3: アーキテクト - 将来計画の策定

**目的**: フレームワーク拡張・移行戦略の立案

**推奨ドキュメント**:
1. **[FUTURE_ROADMAP.md](./FUTURE_ROADMAP.md)** ⭐ - 将来計画詳細 (1時間)
2. **[ENGINE_DESIGN.md](./ENGINE_DESIGN.md)** - Platform本体設計 (2時間)
3. **[ENGINE_FRAMEWORK_3FILES_PROPOSAL.md](./ENGINE_FRAMEWORK_3FILES_PROPOSAL.md)** - 理論的基盤 (1時間)
4. **[WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md](./WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md)** - 移行戦略 (1時間)

**所要時間**: 約5時間

**得られる知識**:
- ASF/ACM/AIF の設計方針
- Platformからの抽出戦略
- 後方互換性の維持方法
- リスク管理

---

### 📌 レベル4: コントリビューター - 完全理解

**目的**: すべてのドキュメントを理解し、貢献可能なレベルへ

**推奨順序**:
1. レベル1 の全ドキュメント (1.5時間)
2. レベル2 の全ドキュメント (パターンA + B、6.5時間)
3. レベル3 の全ドキュメント (5時間)
4. アーカイブドキュメント確認 (2時間)

**所要時間**: 約15時間

**得られる知識**:
- Ecosystem 全体の完全理解
- コントリビューション可能
- 新規フレームワーク設計能力

---

## 📊 統計情報

### ドキュメント統計

| カテゴリ | ドキュメント数 | 総サイズ | 文字数（推定） |
|---------|--------------|---------|---------------|
| **主要** | 4 | 87KB | 29,000 |
| **技術詳細** | 3 | 135KB | 45,000 |
| **ロードマップ** | 2 | 63KB | 21,000 |
| **アーカイブ** | 5 | 166KB | 55,000 |
| **合計** | **14** | **451KB** | **150,000** |

### フレームワーク統計

| 状態 | フレームワーク数 | エンジン数 | サイズ |
|------|----------------|-----------|-------|
| ✅ 実装済み | 3 (AFE, AEF, ACF) | 9 | ~128KB |
| 📋 計画中 | 3 (ASF, ACM, AIF) | 9 | ~150KB |
| **合計** | **6** | **18** | **~278KB** |

### 改善指標

| 指標 | Before | After | 改善率 |
|------|--------|-------|--------|
| エンジンファイル数 | 24+ files | 9 engines | **62%削減** |
| メインJSサイズ | 2,889行 | ~300行 | **90%削減** |
| CSS整理 | JS内散在 | エンジン分離 | **100%** |
| 機能追加時間 | 4-8時間 | 1-2時間 | **75%削減** |

---

## 🛠️ 貢献ガイドライン

### ドキュメント作成ルール

1. **Markdown形式**: すべて `.md` 形式
2. **日本語**: 主要ドキュメントは日本語（コード例は英語可）
3. **コード例**: シンタックスハイライト必須
4. **目次**: 3,000文字以上のドキュメントには目次必須
5. **更新履歴**: 大きな変更は本READMEに記録

### 命名規則

- **大文字スネークケース**: `FRAMEWORK_FEATURE_NAME.md`
- **プレフィックス**:
  - `AFE_` - PHP Framework 関連
  - `AEF_` - JavaScript Editor 関連
  - `ACF_` - CSS Framework 関連
  - `ASF_` - Static Generator 関連（将来）
  - `ACM_` - CMS Framework 関連（将来）
  - `AIF_` - Infrastructure 関連（将来）
  - `ENGINE_` - エンジン駆動モデル一般
- **簡潔**: 30文字以内

### ドキュメント構成

良いドキュメントの構成:
```markdown
# タイトル

**概要を1-2行で**

**Version**: X.X.X  
**Last Updated**: YYYY-MM-DD

---

## 📖 概要

...

## 🎯 目的

...

## 📋 詳細

...

## 💡 使用例

...

## 📚 関連ドキュメント

...
```

---

## 🔗 関連リンク

- **GitHubリポジトリ**: https://github.com/fqwink/AdlairePlatform
- **メインREADME**: [Framework/README.md](../README.md)
- **将来の独立Public化**: 時期未定

---

## 📝 更新履歴

| 日付 | 変更内容 |
|-----|---------|
| 2026-03-14 | **ドキュメント全体を再整備** - 統合インデックス作成、古い提案書をarchive化 |
| 2026-03-14 | **FUTURE_ROADMAP.md 作成** - ASF/ACM/AIF 将来計画 (18エンジン構想) |
| 2026-03-14 | **3ファイルのエンジン駆動モデル実装完了** - AFE/AEB/ADS (9エンジン) |
| 2026-03-13 | EDITOR_CSS_FRAMEWORK_DESIGN.md 作成 |
| 2026-03-13 | WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md 作成 |
| 2026-03-13 | AFE_ENGINE_DRIVEN_MODEL.md 作成 |
| 2026-03-13 | 全ドキュメント Framework/docs/ へ集約 |

---

## 📞 サポート

ドキュメントに関する質問・改善提案:

- **GitHub Issues**: [AdlairePlatform/issues](https://github.com/fqwink/AdlairePlatform/issues)
- **Pull Request**: ドキュメント改善PR歓迎

---

## 🚀 次のステップ

### 現在の状態 (Version 1.0.0)
- ✅ **APF** (PHP本体) - 3エンジン実装完了
- ✅ **AEB** (JavaScript) - 3エンジン実装完了
- ✅ **ADS** (CSS) - 3エンジン実装完了
- ✅ **ドキュメント** - 全体再整備完了

### 将来計画 (Version 2.0.0)
1. **ASG** (Static Generator) - 設計完了、実装未定
2. **ACE** (CMS Framework) - 設計完了、実装未定
3. **AIS** (Infrastructure) - 設計完了、実装未定
4. 独立Public化 (時期未定)
5. Composer/npm/CDN公開
6. 包括的ドキュメントサイト
7. コミュニティ貢献ガイドライン

詳細は **[FUTURE_ROADMAP.md](./FUTURE_ROADMAP.md)** ⭐ を参照。

---

**Adlaire Platform Foundation Documentation**  
**Version**: 1.0.0 (Planning: 2.0.0)  
**Status**: ✅ Production Ready (9 Engines) + 📋 Planning (9 Engines)  
**Total**: 14 Documents, 451KB, ~150,000 characters  
**Last Updated**: 2026-03-14
