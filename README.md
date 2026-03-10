# AdlairePlatform（略：AP／翻訳：アドレイル・プラットホーム）

> ⚠️ **本リポジトリのソースコードは参照目的で公開されています。**
> **Adlaire License Ver.2.0 に基づき、改変・再配布・商用利用は禁止されています。**
> 詳細は [docs/Licenses/LICENSE_Ver.2.0.md](docs/Licenses/LICENSE_Ver.2.0.md) を参照してください。

APは、デザインテンプレートエンジンを搭載したフラットファイルベースの軽量 CMS フレームワークです。
データベース不要で動作し、各機能を小さなエンジン単位として設計することで、段階的なシステム拡張が可能です。

> **現在のバージョン**: Ver.1.4-pre（Ver.1.4系開発中）

---

## 主な特徴

- **フラットファイル JSON ストレージ** — データベース不要
- **テンプレートエンジン** — PHP フリーのテーマシステム（`{{var}}` / `{{#if}}` / `{{#each}}` / `{{> partial}}` / フィルター）
- **WYSIWYG エディタ** — 依存ライブラリなしのブロックベースエディタ
- **静的サイト生成** — 差分ビルド・Static-First Hybrid 配信
- **ヘッドレス CMS API** — REST API・API キー認証・CORS 対応
- **15 エンジン構成** — 各機能が独立したエンジンとして実装

詳細な機能一覧は [docs/features.md](docs/features.md) を参照してください。

---

## エンジン一覧（15 エンジン）

| エンジン | ファイル | 説明 |
|---------|---------|------|
| AdminEngine | `engines/AdminEngine.php` | 認証・CSRF・管理アクション・ダッシュボード |
| TemplateEngine | `engines/TemplateEngine.php` | 軽量テンプレートエンジン |
| ThemeEngine | `engines/ThemeEngine.php` | テーマ検証・読み込み・コンテキスト構築 |
| UpdateEngine | `engines/UpdateEngine.php` | アップデート・バックアップ・ロールバック |
| StaticEngine | `engines/StaticEngine.php` | 静的サイト生成 |
| ApiEngine | `engines/ApiEngine.php` | 公開 REST API |
| CollectionEngine | `engines/CollectionEngine.php` | コレクション管理 |
| MarkdownEngine | `engines/MarkdownEngine.php` | Markdown パーサー |
| GitEngine | `engines/GitEngine.php` | GitHub リポジトリ連携 |
| WebhookEngine | `engines/WebhookEngine.php` | Webhook 管理・送信 |
| CacheEngine | `engines/CacheEngine.php` | API レスポンスキャッシュ |
| ImageOptimizer | `engines/ImageOptimizer.php` | 画像最適化 |
| AppContext | `engines/AppContext.php` | 集中状態管理 |
| Logger | `engines/Logger.php` | 構造化ログ（PSR-3 互換） |
| MailerEngine | `engines/MailerEngine.php` | メール送信抽象化 |

---

## 動作要件

| 項目 | 要件 |
|------|------|
| PHP | **8.2 以上（必須）** |
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

| ファイル | 役割 | ステータス |
|---------|------|-----------|
| [README.md](README.md) | プロジェクト概要・導入案内 | 既存 |
| [CONTRIBUTING.md](CONTRIBUTING.md) | コントリビューションガイド | 既存 |
| [RELEASE-NOTES.md](RELEASE-NOTES.md) | リリースノート | 既存 |
| [docs/AdlairePlatform_Design.md](docs/AdlairePlatform_Design.md) | 基本設計・方針に関するドキュメント | 既存 |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | アーキテクチャに関するドキュメント | 既存 |
| [docs/VERSIONING.md](docs/VERSIONING.md) | バージョニング規則に関するドキュメント | 既存 |
| [docs/features.md](docs/features.md) | 実装・未実装・関数リファレンス・APIリファレンスに関するドキュメント | 既存 |
| [docs/HEADLESS_CMS.md](docs/HEADLESS_CMS.md) | Headless CMS / REST API 仕様 | 既存 |
| [docs/HEADLESS_CMS_ROADMAP.md](docs/HEADLESS_CMS_ROADMAP.md) | Headless CMS ロードマップ | 既存 |
| [docs/STATIC_GENERATOR.md](docs/STATIC_GENERATOR.md) | 静的サイト生成仕様 | 既存 |
| [docs/DIAGNOSTIC_ENGINE.md](docs/DIAGNOSTIC_ENGINE.md) | DiagnosticEngine 仕様 | 既存 |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | 設定リファレンスに関するドキュメント | 未作成 |
| [docs/THEME_DEVELOPMENT.md](docs/THEME_DEVELOPMENT.md) | テーマ開発リファレンスに関するドキュメント | 未作成 |
| [docs/nginx.conf.example](docs/nginx.conf.example) | Nginx 設定サンプル | 既存 |
| [docs/Licenses/LICENSE_Ver.1.0.md](docs/Licenses/LICENSE_Ver.1.0.md) | Adlaire License Ver.1.0 | 既存 |
| [docs/Licenses/LICENSE_Ver.2.0.md](docs/Licenses/LICENSE_Ver.2.0.md) | Adlaire License Ver.2.0 | 既存 |

### ドキュメント運用ルール

ドキュメントの整備・更新は [DOC_RULEBOOK.md](DOC_RULEBOOK.md) に従う。

**ファイル操作制限**

| ファイル | 作成 | 削除 |
|---------|------|------|
| `CHANGELOG.md` | **🚫 永久禁止** | **🚫 永久禁止** |
| `docs/AdlairePlatform_Design.md` | — | **🚫 禁止** |
| `docs/ARCHITECTURE.md` | — | **🚫 禁止** |
| `docs/VERSIONING.md` | — | **🚫 禁止** |

- 作成禁止ファイルを誤って作成した場合は強制削除を義務とする

**ドキュメント更新時のメタデータ**

全ドキュメントのファイル先頭に以下を必ず記載する。

| キー | 形式 | 例 |
|-----|------|----|
| ファイル名 | テキスト | `ARCHITECTURE.md` |
| バージョン | `Ver.{メジャー}.{マイナー}-{ビルド}` | `Ver.1.0-1` |
| 最終更新 | `YYYY-MM-DD` | `2026-03-10` |

全ドキュメントのファイル末尾に以下の変更履歴テーブルを必ず記載する。

```
## 変更履歴

| バージョン | 更新日 | 変更内容 |
|-----------|--------|----------|
| Ver.x.x-x | YYYY-MM-DD | 初版作成 |
```

- 更新のたびに行を追記する（既存行は削除しない）
- 新しい行を先頭に追加する（降順）
- バージョンは `VERSIONING.md` の累積バージョニング規則に従い、ビルド番号リセット禁止

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
