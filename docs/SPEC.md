# AdlairePlatform — 仕様書 (SPEC)

> **ドキュメントバージョン**: Ver.0.1-2
> **ステータス**: ✅ 確定（初版）
> **作成日**: 2026-03-06
> **所有者**: Adlaire Group
> **バージョニング規則**: [AFE/VERSIONING.md](https://github.com/fqwink/AdlaireGroup-Documents-Repository/blob/main/AFE/VERSIONING.md)

---

## 目次

1. [概要](#1-概要)
2. [技術スタック](#2-技術スタック)
3. [動作要件](#3-動作要件)
4. [アーキテクチャ設計](#4-アーキテクチャ設計)
5. [機能仕様](#5-機能仕様)
   - 5.1 [コンテンツ管理](#51-コンテンツ管理)
   - 5.2 [テーマエンジン](#52-テーマエンジン)
   - 5.3 [プラグインシステム](#53-プラグインシステム)
   - 5.4 [認証・セキュリティ](#54-認証セキュリティ)
   - 5.5 [フロントエンド](#55-フロントエンド)
   - 5.6 [アップデートエンジン](#56-アップデートエンジン)
6. [jQuery 廃止 / バニラ JS 移行仕様](#6-jquery-廃止--バニラ-js-移行仕様)
7. [PHP 8.2 対応仕様](#7-php-82-対応仕様)
8. [静的生成ジェネレーター機能（計画）](#8-静的生成ジェネレーター機能計画)
9. [ヘッドレス CMS 機能（計画）](#9-ヘッドレス-cms-機能計画)
10. [ディレクトリ・ファイル構成](#10-ディレクトリファイル構成)
11. [データ仕様](#11-データ仕様)
12. [セキュリティ方針](#12-セキュリティ方針)
13. [ライセンス](#13-ライセンス)
14. [変更履歴](#14-変更履歴)

---

## 1. 概要

**AdlairePlatform（AP）** は、Adlaire Group が開発・保守・所有する、  
**データベース不要のフラットファイルベース軽量 CMS フレームワーク**です。

JSON ファイルをデータストレージとして使用し、テーマエンジン・インプレイス編集・  
プラグインシステムを備えることで、小規模 Web サイトの迅速かつ安全な構築・管理を実現します。

### プロジェクト方針

| 方針 | 内容 |
|------|------|
| **軽量性** | データベース不要。単一エントリーポイントを基本とする |
| **安全性** | 多層セキュリティ（認証・CSRF・XSS・パストラバーサル対策）を標準装備 |
| **拡張性** | テーマ・プラグインによる段階的な機能拡張を設計原則とする |
| **依存最小化** | 外部ライブラリへの依存を最小限に抑え、バニラ技術を優先する |

### ロードマップ（概要）

```
現行 (v1.x)                 計画 (v2.x 以降)
────────────────────        ────────────────────────────────
フラットファイル CMS    →    アーキテクチャ再設計（未検討）
動的ページ配信         →    静的生成ジェネレーター機能追加（未検討）
jQuery 依存           →    バニラ JS / autosize 移行（本仕様で策定）
PHP 5.3+ 対応         →    PHP 8.2 以降専用（本仕様で策定）
```

> ⚠️ **注記**: 「アーキテクチャ再設計」および「静的生成ジェネレーター」は  
> **現時点では未検討段階**です。本仕様書では方向性の記録に留め、  
> 詳細仕様は別途ドキュメントとして策定予定です。

---

## 2. 技術スタック

### 2.1 確定技術スタック

| 項目 | 採用技術 | バージョン要件 | 備考 |
|------|----------|----------------|------|
| **サーバーサイド言語** | PHP | **8.2 以降必須** | 8.2 未満は非サポート・廃止 |
| **Web サーバー** | Apache / Nginx | 任意（要件参照） | Apache: mod_rewrite / mod_headers 必須、Nginx: php-fpm 連携・server block 設定必須 |
| **フロントエンドライブラリ** | autosize | 最新安定版 | テキストエリア自動拡張専用 |
| **スクリプト言語** | JavaScript（バニラ） | ES2020 以上推奨 | jQuery は廃止 |
| **スタイルシート** | CSS | CSS3 | フレームワーク非依存 |
| **その他言語** | Hack | — | 必要箇所に限定使用 |
| **データストレージ** | JSON ファイル | — | DB 不要（フラットファイル） |
| **認証ハッシュ** | bcrypt | PASSWORD_BCRYPT | PHP 標準実装 |

### 2.2 廃止・削除項目

| 廃止項目 | 理由 | 代替 |
|----------|------|------|
| **jQuery** | 外部依存の削減、バニラ JS で代替可能 | バニラ JavaScript（ES2020+） |
| **PHP 8.2 未満** | EOL/セキュリティリスク、新機能活用のため | PHP 8.2 以降 |
| **MD5 パスワードハッシュ** | 既に廃止済み（自動移行実装済み） | bcrypt |

### 2.3 外部依存ライブラリ方針

```
【原則】外部 CDN への依存を最小化し、自己ホスト（セルフホスト）を優先する

現行（廃止予定）:
  - jQuery 3.7.1（CDN 経由）→ 削除

維持:
  - autosize（テキストエリア自動拡張）

方針:
  - 新規ライブラリの追加は原則禁止
  - 追加が必要な場合は Adlaire Group の承認を要する
  - CDN 利用は禁止。ローカルまたはプラグイン経由での提供を必須とする
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
| PHP 設定 | `allow_url_fopen = On` | アップデートチェック（推奨） |
| Web サーバー | **Apache** または **Nginx** | Apache: `mod_rewrite`・`mod_headers` 有効必須 / Nginx: `php-fpm` 連携・server block 設定必須 |
| ファイル権限 | `data/` ディレクトリ | Web サーバーユーザーによる書き込み可 |
| ファイル権限 | `backup/` ディレクトリ | Web サーバーユーザーによる書き込み可 |
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

## 4. アーキテクチャ設計

### 4.1 現行アーキテクチャ（v1.x）

現行は**シングルエントリーポイント方式**を採用しています。  
`index.php` にルーティング・認証・API・レンダリングが集約されています。

**Apache 環境:**
```
[クライアント]
    │ HTTP リクエスト
    ▼
[.htaccess]
    │ mod_rewrite → index.php?page=<slug>
    ▼
[index.php]  ←── 認証・セッション管理
    │
    ├── ルーティング（$_GET['page'] によるスラッグ解決）
    ├── API 処理（POST: ap_action / edit）
    ├── テーマ読み込み（themes/<name>/theme.php）
    └── プラグインロード（plugins/<name>/index.php）
         │
         ▼
    [JSON データ層]
    ├── data/settings.json  （サイト設定）
    ├── data/pages.json     （ページコンテンツ）
    ├── data/auth.json      （認証情報）
    └── data/update_cache.json （アップデートキャッシュ）
```

**Nginx 環境:**
```
[クライアント]
    │ HTTP リクエスト
    ▼
[nginx server block]
    │ try_files → index.php?page=<slug>（.htaccess は無効）
    ▼
[php-fpm]
    │ FastCGI → index.php
    ▼
[index.php]  ←── 認証・セッション管理
    │
    ├── ルーティング（$_GET['page'] によるスラッグ解決）
    ├── API 処理（POST: ap_action / edit）
    ├── テーマ読み込み（themes/<name>/theme.php）
    └── プラグインロード（plugins/<name>/index.php）
         │
         ▼
    [JSON データ層]
    ├── data/settings.json  （サイト設定）
    ├── data/pages.json     （ページコンテンツ）
    ├── data/auth.json      （認証情報）
    └── data/update_cache.json （アップデートキャッシュ）
```

> ⚠️ **Nginx 利用時の注意**: `.htaccess` は Apache 専用のため Nginx では機能しない。
> URL リライト・セキュリティヘッダー・ディレクトリ保護はすべて server block に記述する必要がある。
> 詳細はセクション 5.4.5・5.4.6・5.5.4 を参照。

### 4.2 アーキテクチャ変更計画（未検討）

> 🔴 **ステータス: 未検討段階**
>
> アーキテクチャの再設計は計画中ですが、具体的な方式・構成は現時点で未決定です。  
> 以下は変更の**方向性（候補）**のみを記録したものであり、  
> 確定仕様ではありません。

**検討が想定される方向性（未確定）:**

- 単一 `index.php` からの機能分離・モジュール化
- ルーター・コントローラーの分離
- 静的生成ジェネレーター機能との統合を考慮した設計
- `data/` ディレクトリ構造の見直し（ページ数増加への対応）

**本仕様書の扱い:**
アーキテクチャ変更仕様は、検討開始後に **`docs/ARCHITECTURE.md`** として  
別途策定・管理するものとします。

---

## 5. 機能仕様

### 5.1 コンテンツ管理

#### 5.1.1 データストレージ

| ファイル | 用途 | 自動生成 |
|----------|------|----------|
| `data/settings.json` | サイト設定（タイトル・説明・テーマ等） | ✅ |
| `data/pages.json` | 全ページコンテンツ（スラッグをキーとする） | ✅ |
| `data/auth.json` | 認証情報（bcrypt ハッシュ） | ✅ |
| `data/update_cache.json` | アップデート確認キャッシュ（TTL: 1時間） | ✅ |

#### 5.1.2 ページ管理

- URL スラッグはページ識別子として使用する
- スラッグ生成: スペース→ハイフン変換、`mb_convert_case` による小文字化（UTF-8対応）
- 存在しないスラッグへのアクセス: HTTP 404 を返す
  - ログイン中: 新規ページ作成画面を表示
  - 非ログイン: 404 コンテンツを表示
- ページコンテンツは `pages.json` にスラッグをキーとして格納する

#### 5.1.3 インプレイス編集

- ログイン中にのみ編集可能な `<span>` タグをコンテンツに付与する
- `class="editText"` によってバニラ JS がバインドする（jQuery 依存を廃止）
- リッチテキスト対象要素には `class="richText"` を追加する
- 編集内容は AJAX（`fetch` API）により非同期保存する
- 保存 API エンドポイント: `POST /` （`ap_action=edit`）

#### 5.1.4 設定管理

管理パネルから以下の設定を編集可能とする:

| 設定キー | 内容 | 型 |
|----------|------|----|
| `title` | サイトタイトル | string |
| `description` | サイト説明文 | string |
| `keywords` | メタキーワード | string |
| `copyright` | 著作権表記 | string |
| `menu` | メニュー項目（改行区切り） | string |
| `themeSelect` | 使用テーマ名 | string |

---

### 5.2 テーマエンジン

#### 5.2.1 テーマ構造

```
themes/
└── <テーマ名>/
    ├── theme.php    （必須: HTML レイアウト）
    └── style.css    （必須: スタイルシート）
```

#### 5.2.2 テーマ仕様

- テーマ名の許可文字: `[a-zA-Z0-9_-]`（パストラバーサル防止）
- テーマの自動検出: `themes/` ディレクトリを走査し、有効なテーマを一覧表示
- テーマ切替: 管理パネルからリアルタイムに切替可能
- デフォルトテーマ: `AP-Default`

#### 5.2.3 同梱テーマ

| テーマ名 | 用途 |
|----------|------|
| `AP-Default` | 標準・汎用テーマ |
| `AP-Adlaire` | Adlaire デザインテーマ |

#### 5.2.4 テーマ内利用可能関数

| 関数 | 説明 |
|------|------|
| `content(string $id, string $content)` | コンテンツ出力（ログイン中は編集可能タグ付与） |
| `menu()` | ナビゲーションメニュー出力 |
| `h(string $s): string` | XSS エスケープ出力 |
| `editTags()` | 管理用スクリプト・スタイルの出力 |

---

### 5.3 プラグインシステム

#### 5.3.1 プラグイン構造

```
plugins/
└── <プラグイン名>/
    └── index.php    （必須: プラグイン本体）
```

#### 5.3.2 フック一覧

| フックキー | 発火タイミング | 用途 |
|------------|---------------|------|
| `admin-head` | 管理画面 `<head>` 内 | CSS / JS の追加 |
| `admin-richText` | リッチテキストエディタ生成時 | エディタの差し込み |

#### 5.3.3 プラグイン開発ガイドライン

- `index.php` 内でグローバル変数 `$hook` に文字列を追加することでフックに登録する
- プラグインは jQuery に依存してはならない（バニラ JS または独自ライブラリを使用）
- プラグイン名の許可文字: `[a-zA-Z0-9_-]`

---

### 5.4 認証・セキュリティ

#### 5.4.1 認証仕様

| 項目 | 仕様 |
|------|------|
| 認証方式 | シングルパスワード認証 |
| ハッシュアルゴリズム | `PASSWORD_BCRYPT`（`password_hash` / `password_verify`） |
| 認証情報格納 | `data/auth.json`（`password_hash` キー） |
| セッション管理 | `$_SESSION['l'] = true` |
| セッション固定対策 | ログイン成功時に `session_regenerate_id(true)` を実行 |
| クッキー設定 | `HttpOnly: 1`、`SameSite: Lax` |
| レガシー対応 | MD5 ハッシュ（32文字 hex）検出時に自動警告・移行を促す |

#### 5.4.2 CSRF 対策

- 32 バイトのランダムトークンをセッションに保持する（`random_bytes(32)`）
- 全 POST リクエストで `verify_csrf()` による検証を行う
- 検証方法: `hash_equals()` による定数時間比較
- トークン送信: フォーム hidden フィールド（`csrf`）または HTTP ヘッダー（`X-CSRF-TOKEN`）

#### 5.4.3 XSS 対策

- 全出力箇所で `h()` 関数（`htmlspecialchars(ENT_QUOTES, 'UTF-8')`）を使用する
- コンテンツの生出力は原則禁止とし、信頼されたコンテンツにのみ例外を設ける

#### 5.4.4 パストラバーサル対策

- テーマ名、フィールド名、バックアップ名は正規表現 `^[a-zA-Z0-9_\-]+$` で検証する
- 検証失敗時: HTTP 400 Bad Request を返す

#### 5.4.5 HTTP セキュリティヘッダー（.htaccess / Nginx）

| ヘッダー | 値 | 目的 |
|----------|----|------|
| `X-Content-Type-Options` | `nosniff` | MIME スニッフィング防止 |
| `X-Frame-Options` | `SAMEORIGIN` | クリックジャッキング対策 |
| `Referrer-Policy` | `same-origin` | リファラー情報の漏洩防止 |

**Apache（.htaccess）:**
```apache
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "same-origin"
</IfModule>
```

**Nginx（server block）:**
```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "same-origin" always;
```

#### 5.4.6 ディレクトリ保護（.htaccess / Nginx）

| 対象ディレクトリ | 保護内容 |
|------------------|----------|
| `data/` | 外部アクセス完全遮断 |
| `backup/` | 外部アクセス完全遮断 |
| `files/` | 外部アクセス完全遮断 |
| すべてのディレクトリ | ディレクトリ一覧表示無効 |

**Apache（.htaccess）:**
```apache
RedirectMatch 403 ^.*/data/
RedirectMatch 403 ^.*/backup/
RedirectMatch 403 ^.*/files/
Options -Indexes
```

**Nginx（server block）:**
```nginx
# ディレクトリ一覧無効 + 保護ディレクトリへのアクセス拒否
autoindex off;

location ~* ^/(data|backup|files)/ {
    deny all;
    return 403;
}
```

---

### 5.5 フロントエンド

#### 5.5.1 基本方針

- **jQuery は使用しない（廃止）**
- DOM 操作・イベント処理はすべてバニラ JavaScript（ES2020+）で実装する
- HTTP 通信は `fetch` API を使用する
- テキストエリアの自動拡張は `autosize` ライブラリを使用する

#### 5.5.2 インプレイス編集（バニラ JS 仕様）

```
【イベントバインド】
  document.querySelectorAll('.editText') でバインド
  → click イベントで編集モードへ移行

【保存処理】
  fetch('/index.php', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': '<token>' },
    body: new FormData()  // フィールド名・コンテンツを含む
  })

【autosize 適用】
  autosize(document.querySelectorAll('textarea'))
```

#### 5.5.3 AJAX 保存仕様

- 保存成功時: 画面遷移なし、UIフィードバック（視覚的インジケーター）
- 保存失敗時: エラーメッセージをインライン表示
- CSRF トークンは `X-CSRF-TOKEN` ヘッダーで送信する

#### 5.5.4 URL 設計（クリーン URL）

拡張子なし URL（`/about`・`/contact` 等）を実現する設定は Web サーバーによって異なる。

**Apache（mod_rewrite / .htaccess）:**
```apache
RewriteEngine on
Options -Indexes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond $1#%{REQUEST_URI} ([^#]*)#(.*)$
RewriteRule ^([^\.]+)$ %2?page=$1 [QSA,L]
```

**Nginx（server block）:**
```nginx
location / {
    try_files $uri $uri/ @php_rewrite;
}

location @php_rewrite {
    rewrite ^/([^.]+)$ /index.php?page=$1 last;
}

location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

```
共通動作:
  /about         → index.php?page=about
  /contact       → index.php?page=contact
  /              → index.php（トップページ）
```

---

### 5.6 アップデートエンジン

#### 5.6.1 概要

GitHub Releases API と連携し、管理画面からワンクリックでアップデートを適用できる。

#### 5.6.2 API エンドポイント一覧

| `ap_action` 値 | 処理内容 | 認証必須 |
|----------------|----------|----------|
| `check` | 最新バージョンを GitHub API で確認（TTL: 1時間キャッシュ） | ✅ |
| `check_env` | 環境確認（ZipArchive・allow_url_fopen・書込権限・ディスク容量） | ✅ |
| `apply` | アップデート適用（ZIP DL → 展開 → バックアップ → 上書き） | ✅ |
| `list_backups` | バックアップ一覧取得 | ✅ |
| `rollback` | 指定バックアップへのロールバック | ✅ |
| `delete_backup` | バックアップの削除 | ✅ |

#### 5.6.3 バックアップ仕様

- 保存先: `backup/YYYYMMDD_His/`
- 世代管理: 最大 **5 世代**（超過した場合は古い世代から自動削除）
- 保護対象: `data/`・`backup/` ディレクトリはアップデート時に上書きしない

---

## 6. jQuery 廃止 / バニラ JS 移行仕様

### 6.1 廃止背景

| 理由 | 詳細 |
|------|------|
| 外部依存の削減 | CDN 依存によるパフォーマンス・セキュリティリスクの排除 |
| バニラ JS の成熟 | ES2020+ により jQuery 相当の機能がネイティブ実装可能 |
| 軽量化 | jQuery（約 90KB minified）の除去によるロード時間短縮 |
| ライセンス整合性 | 外部ライブラリへの依存をプロジェクト方針に沿って最小化 |

### 6.2 移行対象ファイル

| ファイル | 移行内容 |
|----------|----------|
| `js/editInplace.php` | jQuery セレクタ → `querySelector/querySelectorAll` |
| | `$.ajax()` → `fetch` API |
| | `$(document).ready()` → `DOMContentLoaded` イベント |
| | jQuery イベント → `addEventListener` |
| | jQuery DOM 操作 → 標準 DOM API |
| `js/rte.php` | jQuery 依存コードをバニラ JS に置換 |
| `themes/*/theme.php` | jQuery CDN 読み込みタグを削除 |

### 6.3 置換対応表

| jQuery 記法 | バニラ JS 代替 |
|-------------|----------------|
| `$(selector)` | `document.querySelector(selector)` |
| `$(selector).each()` | `document.querySelectorAll().forEach()` |
| `$(selector).on(event, fn)` | `element.addEventListener(event, fn)` |
| `$(selector).val()` | `element.value` |
| `$(selector).text(str)` | `element.textContent = str` |
| `$(selector).html(str)` | `element.innerHTML = str` |
| `$(selector).addClass(c)` | `element.classList.add(c)` |
| `$(selector).removeClass(c)` | `element.classList.remove(c)` |
| `$.ajax({ ... })` | `fetch(url, { method, headers, body })` |
| `$(document).ready(fn)` | `document.addEventListener('DOMContentLoaded', fn)` |

### 6.4 autosize 統合仕様

```javascript
// 初期化（DOMContentLoaded 後）
document.addEventListener('DOMContentLoaded', function() {
  autosize(document.querySelectorAll('textarea'));
});

// 動的追加要素への適用
autosize(newTextarea);

// リサイズ更新
autosize.update(textarea);
```

---

## 7. PHP 8.2 対応仕様

### 7.1 廃止背景

| バージョン | EOL（セキュリティ） | 廃止理由 |
|------------|---------------------|----------|
| PHP 5.3 〜 7.x | 終了済み | 重大なセキュリティリスク |
| PHP 8.0 | 2023-11-26 終了 | EOL、サポート対象外 |
| PHP 8.1 | 2024-11-25 終了 | EOL、サポート対象外 |
| **PHP 8.2** | **2026-12-31** | **最低サポートバージョン** |
| PHP 8.3 | 2027-12-31 | 推奨バージョン |
| PHP 8.4 | 2028-12-31 | 最新安定版 |

### 7.2 PHP 8.2+ 対応コーディング規約

#### 7.2.1 型宣言

```php
// ✅ 推奨: 引数・戻り値に型宣言を必須とする
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function getSlug(string $p): string {
    return mb_convert_case(str_replace(' ', '-', $p), MB_CASE_LOWER, 'UTF-8');
}

// ✅ PHP 8.2: readonly プロパティの活用
// ✅ PHP 8.2: DNF 型（交差型・ユニオン型の複合）の使用可
```

#### 7.2.2 廃止機能の排除

| 廃止項目 | 代替 |
|----------|------|
| 動的プロパティ（PHP 8.2 で廃止） | `#[AllowDynamicProperties]` または明示的宣言 |
| `${var}` 文字列内変数展開 | `{$var}` または文字列結合 |
| `utf8_encode()` / `utf8_decode()` | `mb_convert_encoding()` |
| `create_function()` | 無名関数（クロージャ） |

#### 7.2.3 エラーハンドリング

```php
// ✅ PHP 8.0+ の match 式を活用
// ✅ Nullsafe 演算子（?->）を活用
// ✅ str_contains(), str_starts_with(), str_ends_with() を使用
```

### 7.3 バージョンチェック

アプリケーション起動時に PHP バージョンを検証する:

```php
if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('AdlairePlatform requires PHP 8.2 or later. Current version: ' . PHP_VERSION);
}
```

---

## 8. 静的生成ジェネレーター機能（計画）

> 🔴 **ステータス: 未検討段階**
>
> 静的生成ジェネレーター機能は **計画中** ですが、  
> 現時点では具体的な仕様・設計は未決定です。  
> 本セクションは**方向性の記録**のみを目的としています。

### 8.1 機能概要（想定）

現在の動的 PHP 配信に加えて、コンテンツを静的 HTML ファイルとして  
事前生成・出力する機能。

```
【現行】
  リクエスト → PHP 処理 → 動的 HTML 生成 → レスポンス

【計画（静的生成）】
  生成コマンド実行 → 全ページを静的 HTML として出力
  静的ファイルへのリクエスト → HTML を直接配信（PHP 不要）
```

### 8.2 想定されるユースケース

- 高トラフィック対応（PHP 処理なしで配信）
- CDN / 静的ホスティングへのデプロイ
- セキュリティ向上（サーバーサイドコードの公開面を排除）
- バックアップとしての静的スナップショット生成

### 8.3 アーキテクチャとの関係

静的生成機能の追加は、現行の単一 `index.php` 集約型アーキテクチャの  
**再設計を前提とする可能性が高い**。  
そのため、アーキテクチャ変更の検討と並行して仕様策定を行う。

### 8.4 今後のアクション

| アクション | 担当 | ステータス |
|------------|------|------------|
| アーキテクチャ変更方針の検討 | Adlaire Group | 未着手 |
| 静的生成方式の選定（PHPネイティブ / 別プロセス等） | Adlaire Group | 未着手 |
| 動的・静的ハイブリッド方式の検討 | Adlaire Group | 未着手 |
| `docs/STATIC_GENERATOR.md` 仕様書の策定 | Adlaire Group | 未着手 |

---


---

## 9. ヘッドレス CMS 機能（計画）

> 🔴 **ステータス: 未検討段階**
>
> ヘッドレス CMS 機能は**計画中**ですが、現時点では具体的な仕様・設計は未決定です。
> 本セクションは**方向性と参考情報の記録**のみを目的としています。

### 9.1 ヘッドレス CMS とは

**ヘッドレス CMS** とは、コンテンツ管理（バックエンド）とフロントエンド表示を
完全に分離した CMS アーキテクチャです。

```
【従来型 CMS（現行の AdlairePlatform）】
  管理画面 → コンテンツ管理 → PHP テンプレート → HTML 配信
  ※フロントエンド（Head）と一体化している

【ヘッドレス CMS（計画）】
  管理画面 → コンテンツ管理 → API（JSON 配信）→ 任意のフロントエンド
  ※フロントエンド（Head）を持たない = "Headless"
```

| 比較項目 | 従来型（現行） | ヘッドレス（計画） |
|---------|--------------|-----------------|
| フロントエンド | PHP テーマ固定 | 任意（Next.js / Astro / バニラ HTML 等） |
| コンテンツ配信 | PHP によるサーバーレンダリング | JSON API 経由 |
| マルチチャネル対応 | ❌（Web のみ） | ✅（Web / アプリ / 外部サービス） |
| フロント柔軟性 | 低（テーマ依存） | 高（完全分離） |
| データ所有権 | ✅ JSON ファイルに保存 | ✅ 同左（維持） |

### 9.2 参考：pitcms の設計思想

参考 URL: [https://pitcms.net](https://pitcms.net)

pitcms は「はがしやすい日本製ヘッドレス CMS」として、以下の設計思想を持ちます。
AdlairePlatform のヘッドレス CMS 機能を検討する際の参考とします。

| pitcms の特徴 | AdlairePlatform との関係 |
|-------------|------------------------|
| **コンテンツを GitHub に保存**（Markdown / JSON） | AP は既に JSON フラットファイルベース → **思想的に親和性が高い** |
| **ベンダーロックインなし**（データはユーザーのもの） | AP のフラットファイル設計は既にこの思想を体現している |
| **Git ベース**（API ではなくファイル管理） | AP は DB 不要の設計 → Git 管理との統合も自然 |
| **コレクション定義**（設定ファイルでスキーマ管理） | AP のコンテンツ構造拡張の参考となる |
| **プレビュー機能** | 静的生成ジェネレーターと連携したプレビューの参考 |
| **レビュー機能**（承認フロー） | 将来の多人数編集機能として参考 |
| **複数環境管理**（開発 / ステージング / 本番） | AP の将来機能として参考 |
| **フォーム機能**（HTML 標準フォーム） | AP の問い合わせ機能との統合の参考 |

> 📌 **pitcms と AP の差別化方針（未検討）**:
> pitcms は Git / GitHub への依存が前提だが、
> AP はセルフホスト・DB 不要を原則とし、GitHub 非依存での実現を検討する方向性が考えられる。

### 9.3 機能概要（想定）

> ⚠️ 以下はすべて**未確定の方向性**です。

#### 9.3.1 コンテンツ API

AdlairePlatform が保持する JSON データを外部フロントエンドに提供する
REST API エンドポイントの実装。

```
【想定 API エンドポイント（未確定）】

GET  /api/pages              → 全ページ一覧（JSON）
GET  /api/pages/{slug}       → 個別ページコンテンツ（JSON）
GET  /api/settings           → サイト設定情報（JSON）
GET  /api/collections        → コレクション一覧（JSON）
GET  /api/collections/{name} → コレクションコンテンツ一覧（JSON）

※ 認証方法（API キー / Bearer トークン等）は未検討
※ レスポンス形式（フィールド構造）は未検討
```

#### 9.3.2 コンテンツスキーマ定義

pitcms の `pitcms.jsonc` を参考に、AdlairePlatform 独自の
コンテンツスキーマ定義ファイルの実装を検討。

```
【想定フィールドタイプ（未確定）】
text       テキスト
textarea   複数行テキスト
richtext   リッチテキスト（HTML）
number     数値
boolean    真偽値
date       日付・日時
image      画像
select     選択肢（単一）
checkbox   選択肢（複数）
```

#### 9.3.3 コレクション管理

複数のコンテンツタイプ（ブログ記事・お知らせ・メニュー等）を
JSON ファイルのコレクションとして管理する機能。

```
data/
└── collections/
    ├── posts/
    │   ├── 20260101_article.json
    │   └── 20260201_article.json
    ├── news/
    │   └── 20260301_news.json
    └── ...
```

#### 9.3.4 静的生成との統合

ヘッドレス CMS として API 提供するデータを、
静的生成ジェネレーター（セクション 8）と組み合わせることで
**静的 HTML として事前生成・配信**するフローの実現を検討。

```
【想定フロー（未確定）】
  管理画面でコンテンツ編集
       ↓
  JSON データ更新（data/ ディレクトリ）
       ↓
  静的生成トリガー（手動 or 自動）
       ↓
  全ページを静的 HTML として出力
       ↓
  CDN / 静的ホスティングへデプロイ
```

### 9.4 設計上の制約・考慮事項（未検討）

| 考慮事項 | 内容 |
|---------|------|
| **認証・認可** | API キー管理、レートリミット、CORS 設定 |
| **GitHub 非依存** | pitcms と異なり、外部サービス依存なしで実現する方針 |
| **フラットファイル継続** | DB を導入せず、JSON ファイルベースを維持する方針 |
| **後方互換性** | 既存の動的 CMS 機能を壊さず、ヘッドレスモードを追加機能として提供 |
| **アーキテクチャとの整合** | セクション 4.2 のアーキテクチャ変更と並行して検討 |

### 9.5 今後のアクション

| アクション | 担当 | ステータス |
|-----------|------|----------|
| ヘッドレス CMS 機能の要件定義 | Adlaire Group | 未着手 |
| API 設計（エンドポイント・認証方式・レスポンス形式） | Adlaire Group | 未着手 |
| コンテンツスキーマ定義仕様の策定 | Adlaire Group | 未着手 |
| 静的生成ジェネレーターとの統合設計 | Adlaire Group | 未着手 |
| `docs/HEADLESS_CMS.md` 仕様書の策定 | Adlaire Group | 未着手 |

## 10. ディレクトリ・ファイル構成

### 10.1 現行構成（v1.x）

```
AdlairePlatform/
├── index.php               # アプリケーション本体（エントリーポイント）
├── .htaccess               # Apache リライト・セキュリティ設定（Apache 専用）
│
├── js/
│   ├── editInplace.php     # インプレイス編集用 JS（PHP フック対応）
│   └── rte.php             # リッチテキストエディタ差し込みフック
│
├── themes/
│   ├── AP-Default/
│   │   ├── theme.php       # レイアウトテンプレート
│   │   └── style.css       # スタイルシート
│   └── AP-Adlaire/
│       ├── theme.php
│       └── style.css
│
├── plugins/                # プラグイン配置ディレクトリ（自動生成）
│   └── <plugin-name>/
│       └── index.php
│
├── data/                   # JSON データストレージ（自動生成）
│   ├── settings.json
│   ├── pages.json
│   ├── auth.json
│   └── update_cache.json
│
├── backup/                 # バックアップ（自動生成）
│   └── YYYYMMDD_His/
│
├── docs/                   # ドキュメント
│   ├── SPEC.md             # 本仕様書
│   ├── features.md         # 実装機能一覧
│   └── Licenses/
│
└── Licenses/
    ├── Adlaire-License-v1.0
    └── Adlaire-License-v2.0.md
```

> 📝 **Nginx 利用時**: `.htaccess` は配置されているが Nginx では機能しない。
> セクション 5.4.5・5.4.6・5.5.4 に記載の Nginx server block 設定を
> `/etc/nginx/conf.d/adlaireplatform.conf` 等に別途記述すること。

### 10.2 保護対象ディレクトリ

以下のディレクトリは `.htaccess` により外部からのアクセスを遮断する:

| ディレクトリ | 保護方法 | 理由 |
|-------------|----------|------|
| `data/` | RedirectMatch 403 | 認証情報・コンテンツデータを保護 |
| `backup/` | RedirectMatch 403 | バックアップファイルを保護 |
| `files/` | RedirectMatch 403 | アップロードファイルを保護 |

---

## 11. データ仕様

### 11.1 settings.json

```json
{
  "title": "サイトタイトル",
  "description": "サイト説明文",
  "keywords": "キーワード1, キーワード2",
  "copyright": "© 2026 Adlaire Group",
  "menu": "ページ名1\nページ名2",
  "themeSelect": "AP-Default"
}
```

### 11.2 pages.json

```json
{
  "index": "<p>トップページのコンテンツ</p>",
  "about": "<p>概要ページのコンテンツ</p>",
  "contact": "<p>お問い合わせページのコンテンツ</p>"
}
```

### 11.3 auth.json

```json
{
  "password_hash": "$2y$10$..."
}
```

> ⚠️ `password_hash` の値は必ず bcrypt ハッシュ（`$2y$` で始まる文字列）でなければならない。  
> MD5 ハッシュ（32文字の16進数）が検出された場合は警告を表示し、パスワードリセットを促す。

---

## 12. セキュリティ方針

### 12.1 セキュリティ設計原則

1. **デフォルト拒否の原則**: 不要なアクセスはすべてデフォルトで拒否する
2. **最小権限の原則**: 処理に必要な最低限の権限のみを使用する
3. **多層防御**: 単一の防御に依存せず、複数の層でセキュリティを確保する
4. **入力の検証**: すべてのユーザー入力を検証・サニタイズする
5. **出力のエスケープ**: すべての出力箇所でエスケープ処理を行う

### 12.2 脆弱性対策マトリクス

| 脅威 | 対策 | Apache 実装場所 | Nginx 実装場所 |
|------|------|----------------|----------------|
| XSS | `h()` 関数による出力エスケープ | `index.php` 全出力箇所 | 同左 |
| CSRF | 32バイトトークンによる検証 | `verify_csrf()` | 同左 |
| セッションハイジャック | `session_regenerate_id(true)` | `login()` | 同左 |
| パストラバーサル | 正規表現バリデーション | フィールド名・テーマ名・バックアップ名 | 同左 |
| クリックジャッキング | `X-Frame-Options: SAMEORIGIN` | `.htaccess` | server block `add_header` |
| MIME スニッフィング | `X-Content-Type-Options: nosniff` | `.htaccess` | server block `add_header` |
| ディレクトリ列挙 | `autoindex off` / `Options -Indexes` | `.htaccess` | server block `autoindex off` |
| データ漏洩 | 保護ディレクトリへのアクセス拒否 | `.htaccess` RedirectMatch 403 | server block `location deny all` |
| ブルートフォース | — | 未実装（今後の課題） | 未実装（今後の課題） |
| パスワード平文保存 | bcrypt ハッシュ化 | `savePassword()` | 同左 |

### 12.3 今後のセキュリティ課題

| 課題 | 優先度 | ステータス |
|------|--------|------------|
| ログイン試行回数制限（レートリミット） | 中 | 未実装 |
| Content Security Policy (CSP) ヘッダー追加 | 高 | 未実装 |
| `Permissions-Policy` ヘッダー追加 | 低 | 未実装 |
| 2 要素認証（2FA） | 低 | 未検討 |

---

## 13. ライセンス

**Adlaire License Ver.2.0**

本プロジェクトは Adlaire Group が所有する独自ライセンスの下で公開されています。

| 行為 | 可否 |
|------|------|
| コードの参照・閲覧 | ✅ 許可 |
| 個人の非商用利用 | ✅ 許可 |
| 内部業務利用 | ✅ 許可 |
| 学習・研究目的の使用 | ✅ 許可 |
| 再配布 | ❌ 禁止 |
| 改変・フォーク | ❌ 禁止 |
| 商用利用 | ❌ 禁止 |
| 競合製品の開発 | ❌ 禁止 |
| 商標の無断使用 | ❌ 禁止 |

詳細は `Licenses/Adlaire-License-v2.0.md` を参照してください。

---

## 14. 変更履歴

| バージョン | 日付 | 変更内容 | 担当 |
|------------|------|----------|------|
| Ver.0.1-2 | 2026-03-06 | ヘッドレス CMS 機能（計画）セクションを新規追加（セクション 9）。pitcms を参考として記録。目次・セクション番号を更新 | Adlaire Group |
| Ver.0.1-1 | 2026-03-06 | 初版確定。技術スタック策定（PHP 8.2 必須化・jQuery 廃止・バニラ JS 採用）、Apache / Nginx 両対応、アーキテクチャ変更・静的生成ジェネレーター計画の記録 | Adlaire Group |

---

*本ドキュメントは AdlairePlatform の公式仕様書です。*  
*内容は Adlaire Group の承認なく変更・転載することを禁じます。*
