# AdlairePlatform — 基本設計・設計方針

<!-- ⚠️ 削除禁止: 本ドキュメントは基本設計書・基本方針に関する最上位ドキュメントです -->

> **ドキュメントバージョン**: Ver.0.5-2
> **ステータス**: 🔧 開発中（Ver.1.8）
> **作成日**: 2026-03-06
> **最終更新**: 2026-03-10（ドキュメント役割再定義・4ドキュメント体制）
> **所有者**: Adlaire Group
> **バージョニング規則**: [AFE/VERSIONING.md](https://github.com/fqwink/AdlaireGroup-Documents-Repository/blob/main/AFE/VERSIONING.md)

> **関連ドキュメント**:
> - エンジン駆動モデルアーキテクチャ基本設計書 → [ARCHITECTURE.md](ARCHITECTURE.md)
> - 詳細設計書（実装レベルの技術仕様） → [DETAILED_DESIGN.md](DETAILED_DESIGN.md)
> - 機能一覧・関数リファレンス → [features.md](features.md)
> - 本ドキュメントは **基本設計書・基本方針に関するすべて** を定めています。

---

## 目次

1. [概要](#1-概要)
2. [技術スタック・ライブラリ方針](#2-技術スタックライブラリ方針)
3. [動作要件](#3-動作要件)
4. [設計方針](#4-設計方針)
   - 4.1 [コンテンツ管理](#41-コンテンツ管理)
   - 4.2 [テーマエンジン](#42-テーマエンジン)
   - 4.3 [認証・セキュリティ](#43-認証セキュリティ)
   - 4.4 [フロントエンド](#44-フロントエンド)
   - 4.5 [エディタ設計（3段階実装）](#45-エディタ設計3段階実装)
   - 4.6 [フック機構](#46-フック機構)
   - 4.7 [データ層](#47-データ層)
5. [PHP 8.3+ 対応仕様](#5-php-83-対応仕様)
6. [セキュリティ方針](#6-セキュリティ方針)
7. [バージョン計画](#7-バージョン計画)
   - [保留事項・次期バージョン検討項目](#保留事項次期バージョン検討項目)
8. [実装タスクリスト（全24件）](#8-実装タスクリスト全24件)
9. [変更履歴](#9-変更履歴)

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
- ディレクトリ保護と Framework/ 直接アクセス禁止

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

Ver.1.5系〜Ver.1.8系（Framework 統合）
──────────────────────────────────────────────────────────────────────────
Ver.1.5  Framework/ ディレクトリ導入・engines/ → Framework/AP/ 移行（✅ 完了）
Ver.1.6  Controller 層導入・Router/Middleware 実装（✅ 完了）
Ver.1.7  エンジンフレームワーク化・モジュール統合（✅ 完了）
Ver.1.8  PHP 8.3+ 移行・Controller 単一ファイル化・tests/ 廃止（🔧 開発中）
```

> **注**: Ver.1.2系は Ver.1.2-26 をもって終了。Ver.1.3系（Ver.1.3-29）で 12 エンジンの実装を完了。
> Ver.1.4系で AppContext・Logger・MailerEngine を追加し **15 エンジン体制**へ。
> Ver.1.5〜1.7 で Framework 統合を完了し、Ver.1.8 で PHP 8.3+ 移行を実施中。

---

## 2. 技術スタック・ライブラリ方針

### 2.1 確定技術スタック

| 項目 | 採用技術 | バージョン要件 | 備考 |
|------|----------|----------------|------|
| **サーバーサイド言語** | PHP | **8.3 以降必須** | 8.2 以前は非サポート（Ver.1.8〜） |
| **Web サーバー** | Apache / Nginx | 任意（要件参照） | Apache: mod_rewrite / mod_headers 必須、Nginx: php-fpm 連携必須 |
| **フロントエンドライブラリ** | autosize | 最新安定版 | テキストエリア自動拡張専用（セルフホスト） |
| **スクリプト言語** | JavaScript（バニラ） | ES5+ | jQuery は廃止済み |
| **スタイルシート** | CSS | CSS3 | フレームワーク非依存 |
| **データストレージ** | JSON ファイル | — | DB 不要（フラットファイル） |
| **認証ハッシュ** | bcrypt | PASSWORD_BCRYPT | PHP 標準実装 |

### 2.2 外部依存ライブラリ方針

```
【原則】外部 CDN への依存を最小化し、自己ホスト（セルフホスト）を優先する

維持:
  - autosize（テキストエリア自動拡張・セルフホスト）

方針:
  - 新規ライブラリの追加は原則禁止
  - 追加が必要な場合は Adlaire Group の承認を要する
  - CDN 利用は禁止。ローカルまたは Framework/AP/JsEngine/ での提供を必須とする
```

---

## 3. 動作要件

### 3.1 サーバー要件

| 項目 | 要件 | 詳細 |
|------|------|------|
| PHP | **8.3 以上（必須）** | 8.2 以前は非サポート（Ver.1.8〜） |
| PHP 拡張 | `json` | JSON 読み書き（標準バンドル） |
| PHP 拡張 | `mbstring` | マルチバイト文字列処理 |
| PHP 拡張 | `ZipArchive` | アップデートエンジン（推奨） |
| PHP 拡張 | `finfo` | 画像 MIME 型検証 |
| PHP 設定 | `allow_url_fopen = On` | アップデートチェック（推奨） |
| Web サーバー | **Apache** または **Nginx** | Apache: `mod_rewrite`・`mod_headers` 有効必須 / Nginx: `php-fpm` 連携必須 |
| ファイル権限 | `data/` / `backup/` / `uploads/` | Web サーバーユーザーによる書き込み可 |
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
| server block 設定 | URL リライト・セキュリティヘッダー・ディレクトリ保護を手動記述 |
| `.htaccess` 非対応 | Nginx は `.htaccess` を読み込まないため、`nginx.conf.example` を参照 |

### 3.2 クライアント要件

| 項目 | 要件 |
|------|------|
| ブラウザ | 最新の Chrome / Firefox / Edge / Safari |
| JavaScript | 有効（管理機能に必須） |
| Cookie | 有効（セッション管理に必須） |

### 3.3 非サポート環境

- PHP 8.2 以前
- IIS など Apache・Nginx 以外の Web サーバー
- `mod_rewrite` が無効な Apache 環境
- `php-fpm` が利用できない Nginx 環境

---

## 4. 設計方針

> 本セクションは各機能の**設計判断の根拠**を記録します。
> 実装の詳細は [ARCHITECTURE.md](ARCHITECTURE.md)、機能一覧は [features.md](features.md) を参照してください。

### 4.1 コンテンツ管理

**設計判断**:
- **フラットファイル JSON** を採用し、データベースへの依存を排除。小規模サイトではファイル I/O で十分なパフォーマンスが得られる
- **スラッグベース URL** でページを識別。`mb_convert_case` による UTF-8 対応の小文字化を行う
- **インプレイス編集** を採用し、管理画面と公開画面を分離しない。ログイン中のみ `editText` / `editRich` クラスで編集 UI を付与
- **設定管理** はダッシュボード（`?admin`）に集約。テーマ内の `settings.html` パーシャルは Ver.1.3-27 で廃止

> 実装詳細: [ARCHITECTURE.md §5 データ層](ARCHITECTURE.md)、[features.md §1 コンテンツ管理](features.md)

### 4.2 テーマエンジン

**設計判断**:
- **PHP フリーのテンプレートエンジン**（`theme.html` 方式）を採用。`theme.php` は Ver.1.3-28 で廃止
- 独自テンプレート構文（`{{var}}` / `{{#if}}` / `{{#each}}` / `{{> partial}}`）を実装し、Handlebars ライクな構文で PHP の知識なしにテーマを作成可能にする
- **フィルター構文**（`{{var|upper}}`）とドット記法（`{{obj.prop}}`）を Ver.1.4-pre で追加
- テーマ名のバリデーション（`[a-zA-Z0-9_-]`）でパストラバーサルを防止
- デフォルトテーマ `AP-Default` へのフォールバックで存在しないテーマ指定時も安全に動作

> 構文仕様: [features.md §2 テーマエンジン](features.md)、エンジン実装: [ARCHITECTURE.md §3.3-3.4](ARCHITECTURE.md)

### 4.3 認証・セキュリティ

**設計判断**:
- **シングルパスワード認証** を採用。マルチユーザー管理の複雑性を排除し、小規模サイトに適した軽量設計
- **bcrypt** によるパスワードハッシュ化。MD5 からの自動移行を実装済み
- **CSRF トークン** は `random_bytes(32)` で生成し、`hash_equals()` で定数時間比較
- **レート制限**（5回失敗で15分ロックアウト）は IP ベースで JSON ファイルに記録
- **HTTP セキュリティヘッダー**（CSP / X-Content-Type-Options / X-Frame-Options / Referrer-Policy）を `.htaccess` / Nginx server block で付与

> 実装詳細: [ARCHITECTURE.md §6 セキュリティ](ARCHITECTURE.md)、[features.md §4 認証・セキュリティ](features.md)

### 4.4 フロントエンド

**設計判断**:
- **jQuery を廃止し、バニラ JavaScript（ES5+）** で全 DOM 操作・イベント処理を実装。外部依存の削減、バニラ JS の成熟（ES5+ でネイティブ実装可能）、jQuery（~90KB）除去による軽量化が理由
- **Fetch API** で HTTP 通信を統一。XMLHttpRequest は使用しない
- **autosize** のみ外部ライブラリとして維持（セルフホスト）。テキストエリア自動拡張の独自実装コストに対するトレードオフ
- **クリーン URL** は Apache `mod_rewrite` / Nginx `try_files` で実現

> JS モジュール一覧: [ARCHITECTURE.md §3.14 JsEngine](ARCHITECTURE.md)、[features.md §5 フロントエンド](features.md)

### 4.5 エディタ設計（3段階実装）

エディタは 3 フェーズに分けて段階的に実装した。

#### Phase 1（Ver.1.1-12 で実装済み）

- `class="editText"` に一本化し、HTMLタグ直接入力モードとして再定義

#### Phase 2（Ver.1.2-20 で実装済み）

- 外部WYSIWYGライブラリは採用せず、`Framework/AP/JsEngine/wysiwyg.js` として独自実装
- `class="editRich"` 要素クリックで WYSIWYG エディタを起動
- 基本インラインツール（B/I/U）、ブロックタイプ（H2/H3/P/UL/OL）、画像挿入（D&D/クリップボード/ボタン）、自動保存（30秒）

#### Phase 3（Ver.1.2-22 で実装済み）

- Editor.js スタイルのブロックベースアーキテクチャに全面改修
- 各ブロックが独立した contenteditable を持つ設計
- 新ブロックタイプ: blockquote / code / delimiter / table / image / checklist
- インラインツール拡張: S / Code / Marker / Link
- フローティングインラインツールバー・スラッシュコマンドメニュー・ブロックハンドル・ドラッグ並べ替え
- ARIA アクセシビリティ対応

> 機能詳細: [features.md §3 WYSIWYGエディタ](features.md)

### 4.6 フック機構

**設計判断**:
- 外部プラグインによるフック登録は廃止。`loadPlugins()` / `plugins/` ディレクトリを削除
- **内部コアフックのみ** に限定。`admin-head` フックで JsEngine スクリプトを登録
- `AdminEngine::registerHooks()` → `AdminEngine::getAdminScripts()` のパターンでフック内容を取得

> 実装詳細: [ARCHITECTURE.md §7 フック機構](ARCHITECTURE.md)

### 4.7 データ層

**設計判断**:
- `data/settings/`（設定系）と `data/content/`（コンテンツ系）に分離して管理
- 初回アクセス時にディレクトリを自動生成し、インストールの手間を最小化
- レガシーデータからの自動マイグレーションを 2 段階で実装:

```
Phase 1: files/{key} → data/settings/{key}.json / data/content/pages.json
Phase 2: data/{file}.json → data/settings/{file}.json / data/content/pages.json
```

Phase 2 は起動時に毎回チェックするが、移行済みの場合は `file_exists` で早期 skip される。

> 現行データ構造: [ARCHITECTURE.md §5 データ層](ARCHITECTURE.md)

---

## 5. PHP 8.3+ 対応仕様

### 5.1 サポートバージョン

| バージョン | EOL（セキュリティ） | ステータス |
|------------|---------------------|----------|
| PHP 8.2 | 2026-12-31 | **非サポート**（Ver.1.8〜） |
| **PHP 8.3** | **2027-12-31** | **最低サポートバージョン** |
| PHP 8.4 | 2028-12-31 | 推奨バージョン |

### 5.2 バージョンチェック

アプリケーション起動時に PHP バージョンを検証する:

```php
if (PHP_VERSION_ID < 80300) {
    http_response_code(500);
    exit('AdlairePlatform requires PHP 8.3 or later. Current version: ' . PHP_VERSION);
}
```

---

## 6. セキュリティ方針

### 6.1 セキュリティ設計原則

1. **デフォルト拒否の原則**: 不要なアクセスはすべてデフォルトで拒否する
2. **最小権限の原則**: 処理に必要な最低限の権限のみを使用する
3. **多層防御**: 単一の防御に依存せず、複数の層でセキュリティを確保する
4. **入力の検証**: すべてのユーザー入力を検証・サニタイズする
5. **出力のエスケープ**: すべての出力箇所でエスケープ処理を行う

> 脅威対策マトリクス（実装仕様）は [DETAILED_DESIGN.md §4 セキュリティ実装仕様](DETAILED_DESIGN.md) を参照してください。
> アーキテクチャレベルのセキュリティ設計は [ARCHITECTURE.md §6 セキュリティ設計方針](ARCHITECTURE.md) を参照してください。

---

## 7. バージョン計画

| フェーズ | バージョン | ステータス | 主な内容 |
|---------|----------|-----------|---------|
| 初期 | `Ver.1.0-11` | ✅ 完了 | 初期リリース・アップデートエンジン・バックアップ等 |
| P1完了 | `Ver.1.1-12` | ✅ 完了 | PHP 8.2 必須化・jQuery廃止・JsEngine・RTE廃止 |
| P2完了 | `Ver.1.2-13` | ✅ 完了 | エンジン分離・データ層分割・サードパーティ排除 |
| P3完了 | `Ver.1.2-14` | ✅ 完了 | セキュリティ強化（CSP・Framework/保護・nginx設定） |
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
| Ver.1.5〜1.8 | Ver.1.5〜1.8 | ✅ 完了 | Framework統合・Controller層・Router/Middleware・エンジンフレームワーク化 |
| **Ver.1.8** | **Ver.1.8** | **🔧 開発中** | **PHP 8.3+ 移行・Controller単一ファイル化・tests/廃止** |

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

### 保留事項・次期バージョン検討項目

#### 次期バージョン検討予定

| # | 項目 | 概要 | 参照 |
|---|------|------|------|
| F-1 | WorkflowEngine | プレビュー + レビュー承認ワークフロー | `docs/HEADLESS_CMS_ROADMAP.md` Phase 3 |
| F-2 | マルチ環境 | Git ブランチ = 環境（dev / staging / production） | `docs/HEADLESS_CMS_ROADMAP.md` |
| F-3 | GraphQL 対応 | REST に加えて GraphQL エンドポイント | `docs/HEADLESS_CMS_ROADMAP.md` |
| F-4 | GitHub OAuth | 外部認証連携 | `docs/HEADLESS_CMS_ROADMAP.md` |
| F-5 | OpenAPI 自動生成 | Swagger 形式の API ドキュメント | `docs/HEADLESS_CMS_ROADMAP.md` |
| F-6 | Astro / Next.js テンプレート | スターターテンプレートの提供 | `docs/HEADLESS_CMS_ROADMAP.md` |
| F-7 | WYSIWYG ↔ Markdown 変換 | 逆変換の精度と実装コスト | `docs/HEADLESS_CMS_ROADMAP.md` |

#### 暫定実装（⚠️）

| # | 項目 | 現状 | 参照 |
|---|------|------|------|
| P-1 | メール送信 | PHP `mail()` 暫定使用。SMTP 対応は未実装 | `docs/HEADLESS_CMS.md` |
| P-2 | 日本語検索 | `mb_stripos` ベース。形態素解析は非実装 | `docs/HEADLESS_CMS.md` |
| P-3 | 管理 API 認証方式 | セッション Cookie 暫定。API キー / Bearer トークンは検討中 | `docs/HEADLESS_CMS.md` |

#### 未定（❓）

| # | 項目 | 参照 |
|---|------|------|
| U-1 | API バージョニング戦略 | `docs/HEADLESS_CMS.md` |
| U-2 | 複数テーマ CSS パス（サブディレクトリ対応） | `docs/STATIC_GENERATOR.md` |
| U-3 | メニュー active クラス動的付与 | `docs/STATIC_GENERATOR.md` |

#### 将来予定（🔜）

| # | 項目 | 概要 | 参照 |
|---|------|------|------|
| L-1 | メディアアップロード API | 外部フロントエンドからの画像アップロード | `docs/HEADLESS_CMS.md` |
| L-2 | `api_origin` 管理画面設定 | Static-Only モード選択時の設定 UI | `docs/HEADLESS_CMS.md` |

#### 未実装・未検討

| # | モジュール | 略称 | 説明 |
|---|----------|------|------|
| X-1 | Core modules | CM | コアモジュール |
| X-2 | SubCore modules | SCM | サブコアモジュール |
| X-3 | Adlaire account authentication system | A3S | アドレイルアカウント認証システム |

---

## 8. 実装タスクリスト（全24件）

### P1 – 即実装（→ Ver.1.1-12）✅ 完了

| # | タスク | 対象ファイル |
|---|-------|------------|
| P1-1 | ✅ PHP 8.2 バージョンチェック追加 | `index.php` |
| P1-2 | ✅ jQuery CDN タグ削除 | `themes/AP-Default/theme.php`, `themes/AP-Adlaire/theme.php` |
| P1-3 | ✅ `editInplace.js` 作成（バニラJS全面リライト） | `Framework/AP/JsEngine/editInplace.js` |
| P1-4 | ✅ `autosize.js` セルフホスト配置 | `Framework/AP/JsEngine/autosize.js` |
| P1-5 | ✅ `rte.php` 削除・`richText` クラス廃止 | `js/rte.php` → 削除, `index.php` |

### P2 – エンジン構築・データ層・サードパーティ排除（→ Ver.1.2-13）✅ 完了

| # | タスク | 対象ファイル |
|---|-------|------------|
| P2-1 | ✅ `loadPlugins()` → `registerCoreHooks()` 改修 | `index.php` |
| P2-2 | ✅ `plugins/` ディレクトリ廃止 | `plugins/` |
| P2-3 | ✅ `Framework/` ディレクトリ作成 | `Framework/` |
| P2-4 | ✅ `ThemeEngine.php` 作成 | `Framework/AP/ThemeEngine.php` |
| P2-5 | ✅ `UpdateEngine.php` 作成 | `Framework/AP/UpdateEngine.php` |
| P2-6 | ✅ `JsEngine/` 作成・移行 | `Framework/AP/JsEngine/` |
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
| P3-1 | ✅ `Framework/*.php` 直接アクセス禁止 | `.htaccess` |
| P3-2 | ✅ CSP ヘッダー追加 | `.htaccess`, `nginx.conf.example` |
| P3-3 | ✅ `nginx.conf.example` 新規作成 | `nginx.conf.example` |

### P4 – ドキュメント整備（→ Ver.1.2-15）✅ 完了

| # | タスク | 対象ファイル |
|---|-------|------------|
| P4-1 | ✅ `docs/ARCHITECTURE.md` 作成 | `docs/ARCHITECTURE.md` |
| P4-2 | ✅ `docs/ENGINE_DESIGN.md` 草稿（旧 STATIC_GENERATOR.md / HEADLESS_CMS.md 統合） | `docs/ENGINE_DESIGN.md` |

### Phase 2 – 将来タスク（時期未定）

> ⚠️ **Ph2-1〜3 はステータス変更**: WYSIWYG は外部ライブラリを採用せず、`Framework/AP/JsEngine/wysiwyg.js` として
> 依存なしの独自実装で完了済み（Ver.1.2-20）。以下は参考として残す。

| # | タスク | ステータス |
|---|-------|-----------|
| Ph2-1 | ~~WYSIWYGライブラリ選定（Quill / TipTap / Editor.js 等）~~ | ✅ 完了（独自実装を採用） |
| Ph2-2 | ~~WYSIWYGエディタ実装~~ | ✅ 完了（`Framework/AP/JsEngine/wysiwyg.js`） |
| Ph2-3 | ~~`admin-richText` フック復活~~ | ✅ 完了（`editRich` クラスとして統合） |
| Ph3 | ~~Editor.js スタイル ブロックベースエディタ~~ | ✅ 完了（Ver.1.2-22） |

---

## 9. 変更履歴

本ドキュメントの変更履歴は、以下のファイルで一元管理されています:

👉 **[docs/DOCUMENT_CHANGELOG.md](./DOCUMENT_CHANGELOG.md#-adlaireplatform_designmd)** を参照してください。

---

*本ドキュメントは AdlairePlatform の公式設計方針書です。*  
*内容は Adlaire Group の承認なく変更・転載することを禁じます。*
