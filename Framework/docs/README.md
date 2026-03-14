# Adlaire Framework Ecosystem Documentation

**3ファイルのエンジン駆動モデル - 公式ドキュメント**

**Version**: 1.0.0  
**Last Updated**: 2026-03-14

---

## 📚 概要

Adlaire Framework Ecosystem は **3ファイルのエンジン駆動モデル** で構成された統合フレームワーク群です。

### フレームワーク構成

- **AFE** (Adlaire Framework Ecosystem) - PHP製コアフレームワーク・エンジン (本体)
- **AEF** (Adlaire Editor Framework) - JavaScript製エディタフレームワーク・エンジン
- **ACF** (Adlaire CSS Framework) - CSSフレームワーク・エンジン

**合計**: 9エンジンファイル (~128KB)

---

## 📖 主要ドキュメント

### 0. [FUTURE_ROADMAP.md](./FUTURE_ROADMAP.md) ⭐ **将来計画**
**Version 2.0.0 - 追加フレームワーク計画**

静的ジェネレーター・CMS・インフラのフレームワーク化計画。

**内容**:
- **ASF** (Adlaire Static Framework) - 静的ジェネレーター (3 engines, ~45KB)
- **ACM** (Adlaire CMS Framework) - CMS (3 engines, ~50KB)
- **AIF** (Adlaire Infrastructure Framework) - インフラ (3 engines, ~55KB)
- 将来の合計: 18エンジン (~278KB)
- Adlaire Platform からの非破壊的抽出戦略
- 3エンジン構成への再設計
- タイムライン・リスク管理

**対象**: プロジェクト全体の将来を理解したい方

**注意**: 実装時期は未定、Adlaire Platform 本体は一切変更しない

---

### 1. [EDITOR_CSS_FRAMEWORK_DESIGN.md](./EDITOR_CSS_FRAMEWORK_DESIGN.md)
**AEF & ACF 設計書**

JavaScript エディタフレームワーク (AEF) と CSS フレームワーク (ACF) の設計詳細。

**内容**:
- AEF アーキテクチャ (3エンジン構成)
  - AEF.Core.js - Editor, EventBus, BlockRegistry, StateManager, HistoryManager
  - AEF.Blocks.js - 10種類のブロック実装
  - AEF.Utils.js - sanitizer, dom, selection, keyboard
- ACF アーキテクチャ (3エンジン構成)
  - ACF.Base.css - variables, reset, typography, utilities
  - ACF.Components.css - buttons, forms, cards, modals, alerts
  - ACF.Editor.css - editor-base, blocks, toolbar
- 使用方法とサンプルコード
- パフォーマンス最適化

**対象**: AEF/ACF 開発者

---

### 2. [WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md](./WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md)
**WYSIWYG エディタ改良提案書**

既存エディタからAEFへの移行計画と改善提案。

**内容**:
- 現状分析 (2,889行のモノリシック構造)
- AEF 移行による改善効果
  - コードサイズ 90% 削減
  - 機能追加時間 75% 削減
  - CSS 完全分離
- 段階的移行戦略
- テスト計画

**対象**: 既存プロジェクト移行担当者

---

### 3. [AFE_IMPROVEMENT_ROADMAP_V2.md](./AFE_IMPROVEMENT_ROADMAP_V2.md)
**AFE 改良ロードマップ Ver.2**

AFE (PHP本体エンジン) の将来計画。

**内容**:
- Phase 2 (Ver 2.2.0): 実用的強化
- Phase 3 (Ver 2.3.0): JavaScript統合
- Phase 4 (Ver 2.4.0): 高度な最適化
- Phase 5 (Ver 2.5.0): エンタープライズ対応
- ROI分析

**対象**: AFE 開発チーム

---

## 📁 ドキュメント一覧

### エンジン駆動モデル関連

| ドキュメント | サイズ | 説明 |
|------------|-------|------|
| **AFE_ENGINE_DRIVEN_MODEL.md** | 36KB | エンジン駆動モデル完全解説 |
| **ENGINE_FRAMEWORK_3FILES_PROPOSAL.md** | 30KB | 3ファイル構成提案書 |
| **ENGINE_DESIGN.md** | 68KB | エンジン設計書 (Adlaire Platform本体) |
| **ENGINE_DRIVEN_FRAMEWORK_PROPOSAL.md** | 64KB | エンジン駆動フレームワーク提案書 (初版) |
| **ENGINE_DRIVEN_FRAMEWORK_PROPOSAL_REVISED.md** | 55KB | 提案書改訂版 |
| **ENGINE_FRAMEWORK_CORE_PROPOSAL.md** | 13KB | コア提案書 |
| **ENGINE_FRAMEWORK_PROJECT_SUMMARY.md** | 12KB | プロジェクトサマリー |

### フレームワーク関連

| ドキュメント | サイズ | 説明 |
|------------|-------|------|
| **EDITOR_CSS_FRAMEWORK_DESIGN.md** | 31KB | AEF & ACF 設計書 |
| **WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md** | 42KB | エディタ改良提案書 |
| **AFE_IMPROVEMENT_PLAN.md** | 13KB | AFE改良計画 Ver.1 |
| **AFE_IMPROVEMENT_ROADMAP_V2.md** | 49KB | AFE改良ロードマップ Ver.2 ⭐ |

**合計**: 約412KB、約137,000文字

---

## 🎯 推奨読書順序

### 初めて Adlaire Framework Ecosystem に触れる方

1. **Framework/README.md** (親ディレクトリ) - 全体概要の理解
2. **FUTURE_ROADMAP.md** ⭐ - 将来計画の確認
3. **EDITOR_CSS_FRAMEWORK_DESIGN.md** - AEF/ACF の詳細
4. **AFE_ENGINE_DRIVEN_MODEL.md** - エンジン駆動モデルの理解

**所要時間**: 約3時間

---

### AFE (PHP本体エンジン) 開発者

1. **AFE_ENGINE_DRIVEN_MODEL.md** - エンジン駆動アーキテクチャ
2. **ENGINE_FRAMEWORK_3FILES_PROPOSAL.md** - 3ファイル構成詳細
3. **AFE_IMPROVEMENT_ROADMAP_V2.md** ⭐ - 最新ロードマップ

**所要時間**: 約3時間

---

### AEF/ACF (JavaScript/CSS) 開発者

1. **EDITOR_CSS_FRAMEWORK_DESIGN.md** - 設計詳細
2. **WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md** - 改良提案
3. **Framework/README.md** - 使用方法

**所要時間**: 約2時間

---

## 📊 統計

### エンジン構成

| フレームワーク | エンジン数 | サイズ | 言語 |
|--------------|-----------|-------|------|
| **AFE** | 3 engines | ~52KB | PHP 8.2+ |
| **AEF** | 3 engines | ~41KB | JavaScript ES6+ |
| **ACF** | 3 engines | ~35KB | CSS3 |
| **合計** | **9 engines** | **~128KB** | - |

### 改善指標

| 指標 | Before | After | 改善 |
|------|--------|-------|------|
| エンジンファイル数 | 24+ files | 9 engines | **62%削減** |
| メインJSサイズ | 2,889行 | ~300行 | **90%削減** |
| CSS整理 | JS内散在 | エンジン分離 | **100%** |
| 機能追加時間 | 4-8時間 | 1-2時間 | **75%削減** |

---

## 🔗 関連リンク

- **GitHubリポジトリ**: https://github.com/fqwink/AdlairePlatform
- **メインREADME**: [Framework/README.md](../README.md)
- **将来の独立Public化**: 時期未定

---

## 📝 ドキュメント更新履歴

| 日付 | 変更内容 |
|-----|---------|
| 2026-03-14 | **3ファイルのエンジン駆動モデル実装完了** - AFE/AEF/ACF統合 (9 engines) |
| 2026-03-13 | EDITOR_CSS_FRAMEWORK_DESIGN.md 作成 |
| 2026-03-13 | WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md 作成 |
| 2026-03-13 | AFE_ENGINE_DRIVEN_MODEL.md 作成 |
| 2026-03-13 | 全ドキュメント Framework/docs/ へ集約 |

---

## 🛠️ ドキュメント貢献ガイドライン

### ドキュメント作成ルール

1. **Markdown形式**: すべて `.md` 形式
2. **日本語**: 主要ドキュメントは日本語
3. **コード例**: シンタックスハイライト必須
4. **目次**: 3,000文字以上のドキュメントには必須
5. **更新履歴**: 大きな変更は本READMEに記録

### 命名規則

- **大文字スネークケース**: `FRAMEWORK_FEATURE.md`
- **プレフィックス**: `AFE_`, `AEF_`, `ACF_`, `ENGINE_`
- **簡潔**: 30文字以内

---

## 📞 サポート

ドキュメントに関する質問・改善提案:

- **GitHub Issues**: [AdlairePlatform/issues](https://github.com/fqwink/AdlairePlatform/issues)
- **Pull Request**: ドキュメント改善PR歓迎

---

## 🚀 次のステップ

### 現在の状態
- ✅ **AFE** (PHP本体) - 3エンジン実装完了
- ✅ **AEF** (JavaScript) - 3エンジン実装完了
- ✅ **ACF** (CSS) - 3エンジン実装完了

### 将来計画
1. 独立Public化 (時期未定)
2. npm/CDN公開
3. 包括的ドキュメントサイト
4. コミュニティ貢献ガイドライン

詳細は [AFE_IMPROVEMENT_ROADMAP_V2.md](./AFE_IMPROVEMENT_ROADMAP_V2.md) を参照。

---

**Adlaire Framework Ecosystem Documentation**  
**Version**: 1.0.0  
**Status**: ✅ Production Ready (9 Engines)  
**Last Updated**: 2026-03-14
