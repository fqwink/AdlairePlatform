# AdlairePlatform — 基本設計・設計方針

<!-- ⚠️ 削除禁止: 本ドキュメントはプロジェクトの正式な設計書です -->

> **ドキュメントバージョン**: Ver.0.5-1  
> **ステータス**: 🔧 開発中（Ver.1.4-pre）  
> **作成日**: 2026-03-06  
> **最終更新**: 2026-03-10（5文書構成整理）  
> **所有者**: Adlaire Group  
> **バージョニング規則**: [AFE/VERSIONING.md](https://github.com/fqwink/AdlaireGroup-Documents-Repository/blob/main/AFE/VERSIONING.md)

> **関連ドキュメント**:  
> - アーキテクチャ: [ARCHITECTURE.md](ARCHITECTURE.md)  
> - 機能仕様: [SPECIFICATION.md](SPECIFICATION.md)  
> - セキュリティ: [SECURITY_POLICY.md](SECURITY_POLICY.md)  
> - エンジン技術設計: [ENGINE_DESIGN.md](ENGINE_DESIGN.md)  
> - 実装機能一覧: [features.md](features.md)

---

## 目次

1. [概要](#1-概要)
2. [技術スタック](#2-技術スタック)
3. [動作要件](#3-動作要件)
4. [バージョン計画](#4-バージョン計画)
5. [実装タスクリスト（全24件）](#5-実装タスクリスト全24件)
6. [変更履歴](#6-変更履歴)

---

## 1. 概要

**AdlairePlatform（AP）** は、Adlaire Group が開発・保守・所有する、  
**データベース不要のフラットファイルベース軽量 CMS フレームワーク**です。

JSON ファイルをデータストレージとして使用し、テーマエンジン・インプレイス編集・  
WYSIWYGエディタ・アップデートエンジンを備えることで、小規模 Web サイトの迅速かつ安全な構築・管理を実現します。

### プロジェクト方針

| 方針 | 内容 |
|------|------|
| **軽量性** | データベース不要。単一エントリーポイントを基本とする |
| **安全性** | 多層セキュリティ（認証・CSRF・XSS・CSP・パストラバーサル対策）を標準装備 |
| **拡張性** | テーマ・エンジンによる段階的な機能拡張を設計原則とする |
| **依存最小化** | 外部ライブラリへの依存を最小限に抑え、バニラ技術を優先する |

### 設計思想

AdlairePlatform は以下の設計思想に基づいて開発されています:

#### 1. エンジン駆動アーキテクチャ

- 各機能を独立したエンジンとして実装（AdminEngine, StaticEngine, ApiEngine 等）
- 単一エントリーポイント（`index.php`）からエンジンを動的にロード
- エンジン間の疎結合を保ち、保守性と拡張性を両立

#### 2. フラットファイルベースストレージ

- データベース不要で JSON ファイルを使用
- 小規模サイトに最適化された軽量設計
- ファイルベースのバックアップとバージョン管理

#### 3. PHP フリーテーマ

- テーマから PHP コードを排除（Ver.1.3-28）
- TemplateEngine による宣言的テンプレート（`{{var}}` 構文）
- XSS リスクの低減とテーマの保守性向上

#### 4. 外部依存最小化

- jQuery 廃止（Ver.1.1-12）、バニラ JavaScript への移行完了
- CDN 依存を排除し、セルフホストを優先
- autosize ライブラリのみ採用（セルフホスト）

#### 5. セキュリティファースト

- 多層防御: 認証・CSRF・XSS・パストラバーサル・レート制限
- CSP ヘッダーによるコンテンツインジェクション防止
- ディレクトリ保護と engines/ 直接アクセス禁止

### ロードマップ（概要）

```
Ver.1.2系（終了）                 Ver.1.3系（終了）                        Ver.1.4系（Ver.1.4-pre）
──────────────────────────     ───────────────────────────────     ────────────────────────────────
フラットファイル CMS（確定）  →    継続                              →    継続
動的ページ配信（確定）       →    StaticEngine 実装（✅ Ver.1.3-28） →    ─
jQuery 全廃（完了）          →    ─                                 →    ─
PHP 8.2+ 必須化（完了）      →    ─                                 →    ─
TemplateEngine 導入（完了）  →    ─                                 →    フィルター・ドット記法拡張
WYSIWYGエディタ（完了）      →    ─                                 →    ─
─                            →    AdminEngine（✅ Ver.1.3-27）       →    ─
─                            →    ApiEngine（✅ Ver.1.3-28）         →    ─
─                            →    CollectionEngine（✅ Ver.1.3-28）  →    ─
─                            →    MarkdownEngine（✅ Ver.1.3-28）    →    ─
─                            →    GitEngine（✅ Ver.1.3-28）         →    ─
─                            →    WebhookEngine（✅ Ver.1.3-28）     →    ─
─                            →    CacheEngine（✅ Ver.1.3-28）       →    ─
─                            →    ImageOptimizer（✅ Ver.1.3-28）    →    ─
─                            →    ─                                 →    AppContext 実装（✅ Ver.1.4-pre）
─                            →    ─                                 →    Logger 実装（✅ Ver.1.4-pre）
─                            →    ─                                 →    MailerEngine 実装（✅ Ver.1.4-pre）
```

> **注**: Ver.1.2系は Ver.1.2-26 をもって終了。Ver.1.3系（Ver.1.3-29）で 12 エンジンの実装を完了。  
> Ver.1.4系で AppContext・Logger・MailerEngine を追加し **15 エンジン体制**へ。

---

## 2. 技術スタック

### 2.1 確定技術スタック

| 項目 | 採用技術 | バージョン要件 | 備考 |
|------|----------|----------------|------|
| **サーバーサイド言語** | PHP | **8.2 以降必須** | 8.2 未満は非サポート・廃止 |
| **Web サーバー** | Apache / Nginx | 任意（要件参照） | Apache: mod_rewrite / mod_headers 必須、Nginx: php-fpm 連携・server block 設定必須 |
| **フロントエンドライブラリ** | autosize | 最新安定版 | テキストエリア自動拡張専用（セルフホスト） |
| **スクリプト言語** | JavaScript（バニラ） | ES5+ | jQuery は廃止済み |
| **スタイルシート** | CSS | CSS3 | フレームワーク非依存 |
| **データストレージ** | JSON ファイル | — | DB 不要（フラットファイル） |
| **認証ハッシュ** | bcrypt | PASSWORD_BCRYPT | PHP 標準実装 |

### 2.2 廃止・削除項目

| 廃止項目 | 理由 | 代替 |
|----------|------|------|
| **jQuery** | 外部依存の削減、バニラ JS で代替可能 | バニラ JavaScript（ES5+） |
| **PHP 8.2 未満** | EOL/セキュリティリスク、新機能活用のため | PHP 8.2 以降 |
| **MD5 パスワードハッシュ** | 既に廃止済み（自動移行実装済み） | bcrypt |
| **plugins/ システム** | 内部コアフックへの統合により廃止 | `registerCoreHooks()` |
| **rte.php / rte.js** | 独自 WYSIWYG 実装により廃止 | `engines/JsEngine/wysiwyg.js` |
| **js/ ディレクトリ** | `engines/JsEngine/` へ移行 | `engines/JsEngine/` |

### 2.3 外部依存ライブラリ方針

```
【原則】外部 CDN への依存を最小化し、自己ホスト（セルフホスト）を優先する

廃止済み:
  - jQuery 3.7.1（CDN 経由）→ 削除済み

維持:
  - autosize（テキストエリア自動拡張・セルフホスト）

方針:
  - 新規ライブラリの追加は原則禁止
  - 追加が必要な場合は Adlaire Group の承認を要する
  - CDN 利用は禁止。ローカルまたは engines/JsEngine/ での提供を必須とする
```

---

## 3. 動作要件

### 3.1 サーバー要件

| 項目 | 要件 | 詳細 |
|------|------|------|
| PHP | **8.2 以上（必須）** | 8.2 未満では動作保証なし |
| PHP 拡張 | `json` | JSON 読み書き（標準バンドル） |
| PHP 拡張 | `mbstring` | マルチバイト文字列処理 |
| PHP 拡張 | `ZipArchive` | アップデートエンジン（推奨） |
| PHP 拡張 | `finfo` | 画像 MIME 型検証 |
| PHP 設定 | `allow_url_fopen = On` | アップデートチェック（推奨） |
| Web サーバー | **Apache** または **Nginx** | Apache: `mod_rewrite`・`mod_headers` 有効必須 / Nginx: `php-fpm` 連携・server block 設定必須 |
| ファイル権限 | `data/` ディレクトリ | Web サーバーユーザーによる書き込み可 |
| ファイル権限 | `backup/` ディレクトリ | Web サーバーユーザーによる書き込み可 |
| ファイル権限 | `uploads/` ディレクトリ | Web サーバーユーザーによる書き込み可 |
| ディスク容量 | 最低 50MB 以上 | バックアップ世代管理を考慮 |

#### Apache 固有要件

| モジュール | 用途 |
|-----------|------|
| `mod_rewrite` | クリーン URL（拡張子なし URL）の実現 |
| `mod_headers` | セキュリティヘッダーの付与 |

`.htaccess` によりディレクトリ単位の設定が自動適用される。

#### Nginx 固有要件

| 要件 | 詳細 |
|------|------|
| `php-fpm` | PHP 処理のための FastCGI プロセスマネージャー |
| server block 設定 | URL リライト・セキュリティヘッダー・ディレクトリ保護を `nginx.conf` または `conf.d/*.conf` に記述 |
| `.htaccess` 非対応 | Nginx は `.htaccess` を読み込まないため、同等の設定を server block に手動記述すること |

### 3.2 クライアント要件

| 項目 | 要件 |
|------|------|
| ブラウザ | 最新の Chrome / Firefox / Edge / Safari |
| JavaScript | 有効（管理機能に必須） |
| Cookie | 有効（セッション管理に必須） |

### 3.3 非サポート環境

- PHP 8.1 以前（廃止）
- IIS など Apache・Nginx 以外の Web サーバー
- `mod_rewrite` が無効な Apache 環境
- `php-fpm` が利用できない Nginx 環境

---

## 4. バージョン計画

| フェーズ | バージョン | ステータス | 主な内容 |
|---------|----------|-----------|---------|
| 初期 | `Ver.1.0-11` | ✅ 完了 | 初期リリース・アップデートエンジン・バックアップ等 |
| P1完了 | `Ver.1.1-12` | ✅ 完了 | PHP 8.2 必須化・jQuery廃止・JsEngine・RTE廃止 |
| P2完了 | `Ver.1.2-13` | ✅ 完了 | エンジン分離・データ層分割・サードパーティ排除 |
| P3完了 | `Ver.1.2-14` | ✅ 完了 | セキュリティ強化（CSP・engines/保護・nginx設定） |
| P4完了 | `Ver.1.2-15` | ✅ 完了 | ドキュメント整備 |
| バグ修正 | `Ver.1.2-16` | ✅ 完了 | defense-in-depth・バグ修正 |
| WYSIWYG | `Ver.1.2-20 〜 25` | ✅ 完了 | WYSIWYG エディタ独自実装（ブロックベース） |
| テンプレート | `Ver.1.2-26` | ✅ 完了 | TemplateEngine 導入・テーマ PHP フリー化・最終バグ修正 |
| **Ver.1.2系終了** | `Ver.1.2-26` | **🔒 終了** | **Ver.1.2系最終リビジョン** |
| AdminEngine | `Ver.1.3-27` | ✅ 完了 | 管理エンジン導入・ダッシュボード化 |
| StaticEngine | `Ver.1.3-28` | ✅ 完了 | 静的サイト生成・theme.php 廃止 |
| ヘッドレス CMS | `Ver.1.3-28` | ✅ 完了 | ApiEngine・CollectionEngine・MarkdownEngine・GitEngine・WebhookEngine・CacheEngine・ImageOptimizer |
| **Ver.1.3系終了** | `Ver.1.3-29` | **🔒 終了** | **Ver.1.3系最終リビジョン** |
| Ver.1.4-pre | Ver.1.4-pre | ✅ 完了 | AppContext・Logger・MailerEngine・JsonCache・TemplateEngine拡張 |

### Ver.1.3系（🔒 終了）

| フェーズ | 主な内容 | ステータス |
|---------|---------|-----------|
| AdminEngine・ダッシュボード | 管理ツールのエンジン駆動モデル化・ダッシュボード UI 導入 | ✅ 完了（Ver.1.3-27） |
| StaticEngine | 静的サイト生成エンジン・theme.php 廃止 | ✅ 完了（Ver.1.3-28） |
| ApiEngine | ヘッドレス CMS REST API 実装 | ✅ 完了（Ver.1.3-28） |
| CollectionEngine | Markdown ベースのコレクション管理 | ✅ 完了（Ver.1.3-28） |
| MarkdownEngine | フロントマター付き Markdown パーサー | ✅ 完了（Ver.1.3-28） |
| GitEngine | GitHub リポジトリ連携 | ✅ 完了（Ver.1.3-28） |
| WebhookEngine | Webhook 管理・送信 | ✅ 完了（Ver.1.3-28） |
| CacheEngine | API レスポンスキャッシュ | ✅ 完了（Ver.1.3-28） |
| ImageOptimizer | 画像最適化 | ✅ 完了（Ver.1.3-28） |

> **バージョン規則**: リビジョンはリセット禁止、常に累積加算  
> **Ver.1.2系実績**: `Ver.1.0-11 → Ver.1.1-12 → Ver.1.2-13 → ... → Ver.1.2-26（終了）`  
> **Ver.1.3系実績**: `Ver.1.3-27 → Ver.1.3-28 → Ver.1.3-29（終了）`

---

## 5. 実装タスクリスト（全24件）

### P1 – 即実装（→ Ver.1.1-12）✅ 完了

| # | タスク | 対象ファイル |
|---|-------|------------|
| P1-1 | ✅ PHP 8.2 バージョンチェック追加 | `index.php` |
| P1-2 | ✅ jQuery CDN タグ削除 | `themes/AP-Default/theme.php`, `themes/AP-Adlaire/theme.php` |
| P1-3 | ✅ `editInplace.js` 作成（バニラJS全面リライト） | `engines/JsEngine/editInplace.js` |
| P1-4 | ✅ `autosize.js` セルフホスト配置 | `engines/JsEngine/autosize.js` |
| P1-5 | ✅ `rte.php` 削除・`richText` クラス廃止 | `js/rte.php` → 削除, `index.php` |

### P2 – エンジン構築・データ層・サードパーティ排除（→ Ver.1.2-13）✅ 完了

| # | タスク | 対象ファイル |
|---|-------|------------|
| P2-1 | ✅ `loadPlugins()` → `registerCoreHooks()` 改修 | `index.php` |
| P2-2 | ✅ `plugins/` ディレクトリ廃止 | `plugins/` |
| P2-3 | ✅ `engines/` ディレクトリ作成 | `engines/` |
| P2-4 | ✅ `ThemeEngine.php` 作成 | `engines/ThemeEngine.php` |
| P2-5 | ✅ `UpdateEngine.php` 作成 | `engines/UpdateEngine.php` |
| P2-6 | ✅ `JsEngine/` 作成・移行 | `engines/JsEngine/` |
| P2-7 | ✅ `data/settings/` 作成・JSONファイル移行 | `data/settings/` |
| P2-8 | ✅ `data/content/` 作成・`pages.json` 移行 | `data/content/` |
| P2-9 | ✅ `index.php` パス更新（`settings_dir()`, `content_dir()`） | `index.php` |
| P2-10 | ✅ `migrate_from_files()` 自動移行ロジック追加 | `index.php` |
| P2-11 | ✅ `index.php` に `require` 追加・`AP_VERSION` 更新 | `index.php` |
| P2-12 | ✅ `registerCoreHooks()` JSパス更新 | `index.php` |
| P2-13 | ✅ `js/` ディレクトリ廃止 | `js/` → 削除 |
| P2-14 | ✅ `admin-richText` フック削除 | `index.php` |

### P3 – セキュリティ強化（→ Ver.1.2-14）✅ 完了

| # | タスク | 対象ファイル |
|---|-------|------------|
| P3-1 | ✅ `engines/*.php` 直接アクセス禁止 | `.htaccess` |
| P3-2 | ✅ CSP ヘッダー追加 | `.htaccess`, `nginx.conf.example` |
| P3-3 | ✅ `nginx.conf.example` 新規作成 | `nginx.conf.example` |

### P4 – ドキュメント整備（→ Ver.1.2-15）✅ 完了

| # | タスク | 対象ファイル |
|---|-------|------------|
| P4-1 | ✅ `docs/ARCHITECTURE.md` 作成 | `docs/ARCHITECTURE.md` |
| P4-2 | ✅ `docs/ENGINE_DESIGN.md` 草稿（旧 STATIC_GENERATOR.md / HEADLESS_CMS.md 統合） | `docs/ENGINE_DESIGN.md` |

### Phase 2 – 将来タスク（時期未定）

> ⚠️ **Ph2-1〜3 はステータス変更**: WYSIWYG は外部ライブラリを採用せず、`engines/JsEngine/wysiwyg.js` として  
> 依存なしの独自実装で完了済み（Ver.1.2-20）。以下は参考として残す。

| # | タスク | ステータス |
|---|-------|-----------|
| Ph2-1 | ~~WYSIWYGライブラリ選定（Quill / TipTap / Editor.js 等）~~ | ✅ 完了（独自実装を採用） |
| Ph2-2 | ~~WYSIWYGエディタ実装~~ | ✅ 完了（`engines/JsEngine/wysiwyg.js`） |
| Ph2-3 | ~~`admin-richText` フック復活~~ | ✅ 完了（`editRich` クラスとして統合） |
| Ph3 | ~~Editor.js スタイル ブロックベースエディタ~~ | ✅ 完了（Ver.1.2-22） |

---

## 6. 変更履歴

| バージョン | 日付 | 変更内容 | 担当 |
|------------|------|----------|------|
| Ver.0.5-1 | 2026-03-10 | 5文書構成整理。詳細仕様・セキュリティ・エンジン技術設計を分離。設計思想を追加 | Adlaire Group |
| Ver.0.4-1 | 2026-03-10 | SPEC.md を統合。設計書・仕様書を単一ドキュメントに集約。ディレクトリ構成・ライセンス・機能リストなど他ドキュメントと重複するセクションを削除 | Adlaire Group |
| Ver.0.3-12 | 2026-03-10 | Ver.1.4-pre 更新。AppContext・Logger・MailerEngine の 3 エンジン追加（15 エンジン体制）。TemplateEngine にドット記法・フィルター構文を追加。contact エンドポイントを MailerEngine::sendContact() に変更。ロードマップ・ディレクトリ構成を更新 | Adlaire Group |

---

*本ドキュメントは AdlairePlatform の公式設計方針書です。*  
*内容は Adlaire Group の承認なく変更・転載することを禁じます。*
