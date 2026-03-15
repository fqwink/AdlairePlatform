# AdlairePlatform（略：AP／翻訳：アドレイル・プラットホーム）

> ⚠️ **本リポジトリのソースコードは参照目的で公開されています。**
> **Adlaire License Ver.2.0 に基づき、改変・再配布・商用利用は禁止されています。**
> 詳細は [docs/Licenses/LICENSE_Ver.2.0.md](docs/Licenses/LICENSE_Ver.2.0.md) を参照してください。

APは、デザインテンプレートエンジンを搭載したフラットファイルベースの軽量 CMS フレームワークです。
データベース不要で動作し、各機能を Framework モジュールとして設計することで、段階的なシステム拡張が可能です。

> **現在のバージョン**: Ver.1.9-39（内部品質向上・フレームワーク改良）

---

## 主な特徴

- **フラットファイル JSON ストレージ** — データベース不要
- **テンプレートエンジン** — PHP フリーのテーマシステム（`{{var}}` / `{{#if}}` / `{{#each}}` / `{{> partial}}` / フィルター）
- **WYSIWYG エディタ** — 依存ライブラリなしのブロックベースエディタ
- **静的サイト生成** — 差分ビルド・Static-First Hybrid 配信
- **ヘッドレス CMS API** — REST API・API キー認証・CORS 対応
- **Framework 統合** — 全エンジンを Framework モジュール（APF/ACE/AIS/ASG/AP）に統合

詳細な機能一覧は [docs/features.md](docs/features.md) を参照してください。

---

## Framework モジュール一覧

| モジュール | ファイル | 説明 |
|-----------|---------|------|
| **APF** | `Framework/APF/APF.Core.php` | Container, Router, Request, Response, HookManager |
| | `Framework/APF/APF.Middleware.php` | Middleware パイプライン |
| | `Framework/APF/APF.Database.php` | JSON ファイルストレージ |
| | `Framework/APF/APF.Utilities.php` | Security, Str, Cache, Logger |
| **ACE** | `Framework/ACE/ACE.Core.php` | TemplateEngine, ThemeEngine, CollectionEngine, MarkdownEngine |
| | `Framework/ACE/ACE.Admin.php` | AdminEngine（認証・ダッシュボード） |
| | `Framework/ACE/ACE.Api.php` | ApiEngine, WebhookService, RateLimiter |
| **AIS** | `Framework/AIS/AIS.Core.php` | AppContext, I18n, EventDispatcher |
| | `Framework/AIS/AIS.System.php` | HealthMonitor, DiagnosticsManager, ApiCache |
| | `Framework/AIS/AIS.Deployment.php` | UpdateEngine, GitEngine, ImageOptimizer |
| **ASG** | `Framework/ASG/ASG.Core.php` | StaticEngine |
| | `Framework/ASG/ASG.Template.php` | テンプレート処理 |
| | `Framework/ASG/ASG.Utilities.php` | ユーティリティ |
| **AP** | `Framework/AP/AP.Controllers.php` | Controller 統合モジュール |
| | `Framework/AP/AP.Bridge.php` | グローバルユーティリティ関数 |
| | `Framework/AP/JsEngine/` | フロントエンド JavaScript（17ファイル） |

---

## 動作要件

| 項目 | 要件 |
|------|------|
| PHP | **8.3 以上（必須）** — PHP 8.2 以前は非対応 |
| Web サーバー | Apache（mod_rewrite・mod_headers 有効）または Nginx（php-fpm） |
| PHP 拡張 | `json`, `mbstring`, `ZipArchive`（アップデート機能に必要） |
| データベース | 不要 |

---

## インストール

1. リポジトリをサーバーの公開ディレクトリに配置
2. Apache の `AllowOverride All` または `AllowOverride FileInfo Options` を確認
3. ブラウザでアクセス — 初回起動時に `data/` が自動生成
4. 画面下部の「Login」リンクからログイン（初期パスワード: `admin`）
5. **ログイン後、直ちにパスワードを変更してください**

---

## ドキュメント

| ドキュメント | 内容 |
|---|---|
| [docs/features.md](docs/features.md) | 実装機能一覧 |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | アーキテクチャ・ファイル責務 |
| [docs/AdlairePlatform_Design.md](docs/AdlairePlatform_Design.md) | 設計書・仕様書・バージョン計画 |
| [docs/DETAILED_DESIGN.md](docs/DETAILED_DESIGN.md) | 詳細設計書 |
| [docs/SECURITY_POLICY.md](docs/SECURITY_POLICY.md) | セキュリティ方針 |
| [RELEASE-NOTES.md](RELEASE-NOTES.md) | リリースノート・変更履歴 |
| [CONTRIBUTING.md](CONTRIBUTING.md) | コントリビューション・ガイド |
| [Framework/README.md](Framework/README.md) | Adlaire Framework 概要 |

ドキュメントの整備・更新は [docs/DOC_RULEBOOK.md](docs/DOC_RULEBOOK.md) に従う。

---

## ライセンス

> ⚠️ **本リポジトリのソースコードは参照目的で公開されています。**
> **Adlaire License Ver.2.0 に基づき、改変・再配布・商用利用は禁止されています。**

本ソフトウェアは **Adlaire License Ver.2.0** に基づきます。
ライセンス全文は [docs/Licenses/LICENSE_Ver.2.0.md](docs/Licenses/LICENSE_Ver.2.0.md) を参照してください。

本ソフトウェアのソースコードは参照目的で公開されており、オープンソースではありません（**"Source Available, Not Open Source"**）。

### 許可される行為
- 個人の非商用目的での使用
- 内部業務目的での使用（第三者への提供・再配布を除く）
- 学習・研究目的でのソースコード閲覧
- 権利者が承認したコントリビュート（提供されたコードの著作権は権利者に帰属）

### 禁止される行為
- ソースコード・バイナリの再配布
- 権利者の書面による許諾なしの改変・二次著作物の作成
- 第三者へのサブライセンス
- 権利者との別途契約なしの商用利用
- リバースエンジニアリング・逆コンパイル・逆アセンブル
- 「Adlaire」「Adlaire Group」「AdlairePlatform」「AP」商標の無断使用
- 本ソフトウェアを参考にした競合製品・サービスの開発

---

## Copyright

Copyright (c) 2014 - 2026 Adlaire Group
最高権限者：倉田和宏
All Rights Reserved.
