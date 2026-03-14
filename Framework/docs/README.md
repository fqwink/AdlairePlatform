# Adlaire Platform Foundation - Documentation

**3ファイルのエンジン駆動モデル - 統合ドキュメント**

**Version**: 2.0.0
**Status**: ✅ Production Ready (18 Engines)
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

### フレームワーク構成 (Version 2.0.0)

| フレームワーク | エンジン数 | サイズ | 状態 |
|--------------|-----------|-------|------|
| **APF** (PHP Framework) | 3 | ~52KB | ✅ 実装済み |
| **AEB** (JavaScript Editor) | 3 | ~41KB | ✅ 実装済み |
| **ADS** (CSS Framework) | 3 | ~35KB | ✅ 実装済み |
| **ASG** (Static Generator) | 3 | ~103KB | ✅ 実装済み |
| **ACE** (CMS Framework) | 3 | ~85KB | ✅ 実装済み |
| **AIS** (Infrastructure) | 3 | ~93KB | ✅ 実装済み |
| **合計** | **18 engines** | **~409KB** | **完成** |

---

## 📁 ドキュメント構成

### 🌟 主要ドキュメント (必読)

#### 1. **[README.md](./README.md)** (本ドキュメント)
**統合インデックス - 全ドキュメントへの入口**

#### 2. **[AFE_ENGINE_DRIVEN_MODEL.md](./AFE_ENGINE_DRIVEN_MODEL.md)**
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

#### 3. **[WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md](./WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md)**
**WYSIWYG エディタ改良提案書** (42KB)

既存エディタ (wysiwyg.js) から AEB への移行計画。

**内容**:
- 現状分析 (2,889行のモノリシック構造)
- AEB 移行による改善効果
- 段階的移行戦略 (4フェーズ)
- リスク管理・テスト計画

**対象**: 既存プロジェクト移行担当者、アーキテクト

---

#### 4. **[AFE_IMPROVEMENT_ROADMAP_V2.md](./AFE_IMPROVEMENT_ROADMAP_V2.md)**
**APF 改良ロードマップ Ver.2** (49KB)

APF (PHP本体) の将来改良計画 (Ver 2.2.0 - 2.5.0)。

**内容**:
- Phase 2 (Ver 2.2.0): 実用的強化
- Phase 3 (Ver 2.3.0): JavaScript 統合
- Phase 4 (Ver 2.4.0): 高度な最適化
- Phase 5 (Ver 2.5.0): エンタープライズ対応
- 実装計画 (121時間、11週間)
- ROI分析 (194%、投資回収期間4ヶ月)

**対象**: APF 開発チーム、プロジェクトマネージャー

---

## 🎯 推奨読書順序

### 📌 レベル1: 初心者 - 全体像の把握

**推奨ドキュメント**:
1. **[../README.md](../README.md)** (親ディレクトリ) - メインREADME (15分)
2. 本ドキュメント (README.md) - 概要把握 (15分)

**所要時間**: 約30分

---

### 📌 レベル2: 開発者 - 実装詳細の理解

**推奨ドキュメント**:
1. **[AFE_ENGINE_DRIVEN_MODEL.md](./AFE_ENGINE_DRIVEN_MODEL.md)** - エンジン駆動モデル (1.5時間)
2. **[AFE_IMPROVEMENT_ROADMAP_V2.md](./AFE_IMPROVEMENT_ROADMAP_V2.md)** - 改良ロードマップ (1時間)
3. 各フレームワークのソースコード確認 (1時間)

**所要時間**: 約3.5時間

---

### 📌 レベル3: アーキテクト - 移行戦略の策定

**推奨ドキュメント**:
1. **[WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md](./WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md)** - 移行戦略 (1時間)
2. **[AFE_ENGINE_DRIVEN_MODEL.md](./AFE_ENGINE_DRIVEN_MODEL.md)** - アーキテクチャ深掘り (1.5時間)
3. Platform本体の [SOURCE_CODE_ANALYSIS_JP.md](../../SOURCE_CODE_ANALYSIS_JP.md) - ソースコード解析 (2時間)

**所要時間**: 約4.5時間

---

## 📊 統計情報

### ドキュメント統計

| カテゴリ | ドキュメント数 | 総サイズ |
|---------|--------------|---------|
| **主要** | 1 (README) | 16KB |
| **技術詳細** | 2 (ENGINE_DRIVEN_MODEL, WYSIWYG) | 79KB |
| **ロードマップ** | 1 (ROADMAP_V2) | 49KB |
| **合計** | **4** | **~144KB** |

### フレームワーク統計

| 状態 | フレームワーク数 | エンジン数 | サイズ |
|------|----------------|-----------|-------|
| ✅ 実装済み | 6 (APF, AEB, ADS, ASG, ACE, AIS) | 18 | ~409KB |

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
  - `APF_` - PHP Framework 関連
  - `AEB_` - JavaScript Editor 関連
  - `ADS_` - CSS Framework 関連
  - `ASG_` - Static Generator 関連
  - `ACE_` - CMS Framework 関連
  - `AIS_` - Infrastructure 関連
  - `ENGINE_` - エンジン駆動モデル一般
- **簡潔**: 30文字以内

---

## 🔗 関連リンク

- **GitHubリポジトリ**: https://github.com/fqwink/AdlairePlatform
- **メインREADME**: [Framework/README.md](../README.md)
- **ソースコード解析**: [SOURCE_CODE_ANALYSIS_JP.md](../../SOURCE_CODE_ANALYSIS_JP.md)

---

## 📝 更新履歴

| 日付 | 変更内容 |
|-----|---------|
| 2026-03-14 | **Version 2.0.0** - ASG/ACE/AIS実装完了、全18エンジン体制。実装済み提案書・計画書・アーカイブを削除 |
| 2026-03-14 | **ドキュメント全体を再整備** - 統合インデックス作成、古い提案書をarchive化 |
| 2026-03-14 | **3ファイルのエンジン駆動モデル実装完了** - APF/AEB/ADS (9エンジン) |
| 2026-03-13 | WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md 作成 |
| 2026-03-13 | AFE_ENGINE_DRIVEN_MODEL.md 作成 |
| 2026-03-13 | 全ドキュメント Framework/docs/ へ集約 |

---

**Adlaire Platform Foundation Documentation**
**Version**: 2.0.0
**Status**: ✅ Production Ready (18 Engines)
**Total**: 4 Documents, ~144KB
**Last Updated**: 2026-03-14
