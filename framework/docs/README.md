# AFE Framework Documentation

**Adlaire Framework Ecosystem (AFE) - Official Documentation**

---

## 📚 ドキュメント一覧

### 📖 主要ドキュメント

#### 1. [AFE_ENGINE_DRIVEN_MODEL.md](./AFE_ENGINE_DRIVEN_MODEL.md)
**エンジン駆動モデル完全解説**（約12,000文字）

AFEの中核概念である「エンジン駆動モデル」を詳細に解説。

**内容**:
- AFEとは（概要・設計哲学）
- エンジン駆動モデルの概念
- コアアーキテクチャ（3ファイル構成）
- エンジンライフサイクル（Register → Initialize → Boot → Runtime → Shutdown）
- 依存性注入（DI）システム
- イベント駆動アーキテクチャ
- 依存関係解決メカニズム（トポロジカルソート）
- エンジン開発ガイド（最小限～実用的）
- ベストプラクティス
- 将来の展望（独立リポジトリ化計画）

**対象**: AFE初学者～中級者

---

#### 2. [ENGINE_FRAMEWORK_3FILES_PROPOSAL.md](./ENGINE_FRAMEWORK_3FILES_PROPOSAL.md)
**3ファイル構成フレームワーク提案書**（約22KB）

AFEの3ファイル構成設計の全体像と実装詳細。

**内容**:
- エグゼクティブサマリー
- 設計目標（最小性・拡張性・後方互換性）
- ファイル構成（Framework.php, EngineInterface.php, BaseEngine.php）
- コンポーネント詳細
- 使用方法（基本～高度）
- 既存エンジンの移行ガイド
- メリット・デメリット比較
- 期待効果
- 実装スケジュール
- テスト戦略

**対象**: AFE導入検討者、アーキテクト

---

#### 3. [AFE_IMPROVEMENT_PLAN.md](./AFE_IMPROVEMENT_PLAN.md)
**改良計画書**（Ver 2.1.0 - 2.3.0）

AFEの段階的改良計画とロードマップ。

**内容**:
- 現状分析
- 改良項目一覧（カテゴリA-E）
- 優先順位付け（Phase 1-3）
- 詳細設計
  - エラーハンドリング強化
  - バリデーション機構
  - 遅延ロード
  - テストヘルパー
  - ロギング強化
  - キャッシュ最適化
  - デバッグ機能
  - ミドルウェアシステム
- 実装計画（65時間、8日間）
- 投資対効果分析

**対象**: 開発チーム、プロジェクトマネージャー

---

### 📋 参考ドキュメント

#### 4. [ENGINE_DRIVEN_FRAMEWORK_PROPOSAL.md](./ENGINE_DRIVEN_FRAMEWORK_PROPOSAL.md)
**エンジン駆動フレームワーク提案書（初版）**

AFE構想の原点となった提案書。

---

#### 5. [ENGINE_DRIVEN_FRAMEWORK_PROPOSAL_REVISED.md](./ENGINE_DRIVEN_FRAMEWORK_PROPOSAL_REVISED.md)
**エンジン駆動フレームワーク提案書（改訂版）**

プラグインシステムを含む拡張版提案書。

---

#### 6. [ENGINE_FRAMEWORK_CORE_PROPOSAL.md](./ENGINE_FRAMEWORK_CORE_PROPOSAL.md)
**フレームワークコア提案書**

コア機能に絞った簡潔版提案書。

---

#### 7. [ENGINE_FRAMEWORK_PROJECT_SUMMARY.md](./ENGINE_FRAMEWORK_PROJECT_SUMMARY.md)
**プロジェクトサマリー**

AFEプロジェクト全体の概要。

---

#### 8. [ENGINE_DESIGN.md](./ENGINE_DESIGN.md)
**エンジン設計書**

既存エンジン（16種類）の詳細設計。

---

## 🎯 推奨読書順序

### 初めてAFEに触れる方

1. **AFE_ENGINE_DRIVEN_MODEL.md** - エンジン駆動モデルの理解
2. **ENGINE_FRAMEWORK_3FILES_PROPOSAL.md** - 3ファイル構成の詳細
3. **AFE_IMPROVEMENT_PLAN.md** - 改良計画の確認

**所要時間**: 約2時間

---

### AFE導入を検討している方

1. **ENGINE_FRAMEWORK_3FILES_PROPOSAL.md** - 提案書の精読
2. **AFE_ENGINE_DRIVEN_MODEL.md** - 技術詳細の理解
3. **ENGINE_FRAMEWORK_PROJECT_SUMMARY.md** - プロジェクト概要
4. **AFE_IMPROVEMENT_PLAN.md** - 改良ロードマップの確認

**所要時間**: 約3時間

---

### AFE開発に参加する方

すべてのドキュメントを読むことを推奨。

**所要時間**: 約5時間

---

## 📊 ドキュメント統計

| ドキュメント | サイズ | 文字数（推定） | 対象読者 |
|------------|-------|--------------|---------|
| AFE_ENGINE_DRIVEN_MODEL.md | 36KB | 12,000 | 初学者～中級者 |
| ENGINE_FRAMEWORK_3FILES_PROPOSAL.md | 30KB | 10,000 | 導入検討者 |
| AFE_IMPROVEMENT_PLAN.md | 13KB | 4,500 | 開発チーム |
| ENGINE_DRIVEN_FRAMEWORK_PROPOSAL.md | 64KB | 21,000 | 参考資料 |
| ENGINE_DRIVEN_FRAMEWORK_PROPOSAL_REVISED.md | 55KB | 18,000 | 参考資料 |
| ENGINE_FRAMEWORK_CORE_PROPOSAL.md | 13KB | 4,500 | 参考資料 |
| ENGINE_FRAMEWORK_PROJECT_SUMMARY.md | 12KB | 4,000 | 概要理解 |
| ENGINE_DESIGN.md | 68KB | 22,000 | 上級者 |

**合計**: 約291KB、約96,000文字

---

## 🔗 関連リンク

- **GitHubリポジトリ**: https://github.com/fqwink/AdlairePlatform
- **プルリクエスト**: https://github.com/fqwink/AdlairePlatform/pull/34
- **将来の独立リポジトリ**: TBD（時期未定）

---

## 📝 ドキュメント更新履歴

| 日付 | ドキュメント | 変更内容 |
|-----|------------|---------|
| 2026-03-13 | AFE_ENGINE_DRIVEN_MODEL.md | 新規作成（12,000文字） |
| 2026-03-13 | AFE_IMPROVEMENT_PLAN.md | Ver 2.1.0 改良計画作成 |
| 2026-03-13 | ENGINE_FRAMEWORK_3FILES_PROPOSAL.md | AFEブランディング適用 |
| 2026-03-13 | 全ドキュメント | framework/docs/へ集約 |

---

## 🛠️ ドキュメント貢献ガイドライン

### ドキュメント作成・更新時のルール

1. **Markdown形式**: すべてのドキュメントはMarkdown (.md) で作成
2. **日本語**: 主要ドキュメントは日本語で記述
3. **コード例**: 必ずシンタックスハイライト付きで記載
4. **目次**: 長いドキュメント（3,000文字以上）は目次を含める
5. **更新履歴**: 大きな変更は本READMEに記録

### 命名規則

- **大文字スネークケース**: `AFE_FEATURE_NAME.md`
- **明確なプレフィックス**: `AFE_`, `ENGINE_`
- **簡潔な名前**: 30文字以内

---

## 📞 サポート・フィードバック

ドキュメントに関する質問・改善提案は以下へ：

- **GitHub Issues**: [AdlairePlatform/issues](https://github.com/fqwink/AdlairePlatform/issues)
- **Pull Request**: ドキュメント改善のPR歓迎

---

**AFE Framework Documentation**  
**Version**: 2.1.0  
**Last Updated**: 2026-03-13
