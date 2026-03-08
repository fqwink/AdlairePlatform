# AdlairePlatform — 仕様書 (SPEC)

> **ドキュメントバージョン**: Ver.0.2-4
> **ステータス**: ✅ 確定
> **作成日**: 2026-03-06
> **最終更新**: 2026-03-08（Ver.1.2-21 対応）
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
   - 5.3 [認証・セキュリティ](#53-認証セキュリティ)
   - 5.4 [フロントエンド](#54-フロントエンド)
   - 5.5 [WYSIWYGエディタ](#55-wysiwygエディタ)
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
WYSIWYGエディタ・アップデートエンジンを備えることで、小規模 Web サイトの迅速かつ安全な構築・管理を実現します。

### プロジェクト方針

| 方針 | 内容 |
|------|------|
| **軽量性** | データベース不要。単一エントリーポイントを基本とする |
| **安全性** | 多層セキュリティ（認証・CSRF・XSS・CSP・パストラバーサル対策）を標準装備 |
| **拡張性** | テーマ・エンジンによる段階的な機能拡張を設計原則とする |
| **依存最小化** | 外部ライブラリへの依存を最小限に抑え、バニラ技術を優先する |

### ロードマップ（概要）

```
現行 (v1.x)                    計画 (v2.x 以降)
──────────────────────────     ───────────────────────────────
フラットファイル CMS       →    アーキテクチャ再設計（未検討）
動的ページ配信             →    静的生成ジェネレーター機能追加（未検討）
jQuery 依存（廃止済み）    →    バニラ JS に全面移行（完了）
PHP 5.3+ 対応（廃止済み）  →    PHP 8.2 以降専用（完了）
```

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

## 4. アーキテクチャ設計

### 4.1 現行アーキテクチャ（v1.x）

現行は**シングルエントリーポイント + エンジン分離方式**を採用しています。
`index.php` にルーティング・認証・コンテンツが集約され、専用エンジンを `require` します。

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
    ├── PHP バージョンチェック
    ├── require engines/ThemeEngine.php
    ├── require engines/UpdateEngine.php
    ├── ルーティング（$_GET['page'] によるスラッグ解決）
    ├── upload_image() ─ 画像アップロード処理
    ├── handle_update_action() ─ ap_action POST 処理
    ├── edit() ─ フィールド保存処理
    ├── テーマ読み込み（ThemeEngine::load()）
    └── registerCoreHooks() ─ JsEngine スクリプト登録
         │
         ▼
    [JSON データ層]
    ├── data/settings/settings.json   （サイト設定）
    ├── data/settings/auth.json       （認証情報）
    ├── data/settings/version.json    （バージョン履歴）
    ├── data/settings/update_cache.json （APIキャッシュ）
    ├── data/settings/login_attempts.json （ログイン試行）
    └── data/content/pages.json       （ページコンテンツ）
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
[index.php] ← 同上
```

> ⚠️ **Nginx 利用時の注意**: `.htaccess` は Apache 専用のため Nginx では機能しない。
> `nginx.conf.example` を参照して server block に同等の設定を記述すること。

### 4.2 アーキテクチャ変更計画（未検討）

> 🔴 **ステータス: 未検討段階**
>
> アーキテクチャの再設計は計画中ですが、具体的な方式・構成は現時点で未決定です。

---

## 5. 機能仕様

### 5.1 コンテンツ管理

#### 5.1.1 データストレージ

| ファイル | 用途 | 自動生成 |
|----------|------|----------|
| `data/settings/settings.json` | サイト設定（タイトル・説明・テーマ等） | ✅ |
| `data/content/pages.json` | 全ページコンテンツ（スラッグをキーとする） | ✅ |
| `data/settings/auth.json` | 認証情報（bcrypt ハッシュ） | ✅ |
| `data/settings/update_cache.json` | アップデート確認キャッシュ（TTL: 1時間） | ✅ |
| `data/settings/version.json` | アップデート履歴 | ✅ |
| `data/settings/login_attempts.json` | ログイン試行記録（レート制限） | ✅ |

#### 5.1.2 ページ管理

- URL スラッグはページ識別子として使用する
- スラッグ生成: スペース→ハイフン変換、`mb_convert_case` による小文字化（UTF-8対応）
- 存在しないスラッグへのアクセス: HTTP 404 を返す
  - ログイン中: 新規ページ作成画面を表示
  - 非ログイン: 404 コンテンツを表示
- ページコンテンツは `pages.json` にスラッグをキーとして格納する

#### 5.1.3 インプレイス編集

- ログイン中にのみ編集可能な `<span>` タグをコンテンツに付与する
- `class="editText"` によってバニラ JS がバインドする（jQuery 依存なし）
- リッチテキスト対象要素には `class="editRich"` を追加する（WYSIWYGエディタ起動）
- 編集内容は `fetch` API により非同期保存する
- 保存 API エンドポイント: `POST /`（フィールド名を POST パラメータとして送信）

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

#### 5.1.5 画像アップロード

- エンドポイント: `POST /`（`ap_action=upload_image`）
- 認証必須・CSRF 検証必須
- 許可形式: JPEG / PNG / GIF / WebP（`finfo` による MIME 検証）
- 最大サイズ: 2MB
- 保存先: `uploads/` ディレクトリ（PHP 実行不可・ランダムファイル名）

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
- デフォルトテーマ: `AP-Default`（存在しないテーマが指定された場合のフォールバック）
- エンジン: `engines/ThemeEngine.php`（`ThemeEngine::load()` / `ThemeEngine::listThemes()`）

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
| `settings()` | 管理パネル出力（ログイン中のみ） |
| `is_loggedin(): bool` | ログイン状態の確認 |

---

### 5.3 認証・セキュリティ

#### 5.3.1 認証仕様

| 項目 | 仕様 |
|------|------|
| 認証方式 | シングルパスワード認証 |
| ハッシュアルゴリズム | `PASSWORD_BCRYPT`（`password_hash` / `password_verify`） |
| 認証情報格納 | `data/settings/auth.json`（`password_hash` キー） |
| セッション管理 | `$_SESSION['l'] = true` |
| セッション固定対策 | ログイン成功時に `session_regenerate_id(true)` を実行 |
| クッキー設定 | `HttpOnly: 1`、`SameSite: Lax` |
| レート制限 | 5回失敗で15分ロックアウト（IP ベース・`login_attempts.json`） |
| レガシー対応 | MD5 ハッシュ（32文字 hex）検出時に自動警告・移行を促す |

#### 5.3.2 CSRF 対策

- 32 バイトのランダムトークンをセッションに保持する（`random_bytes(32)`）
- 全 POST リクエストで `verify_csrf()` による検証を行う
- 検証方法: `empty()` ガード + `hash_equals()` による定数時間比較
- トークン送信: フォーム hidden フィールド（`csrf`）または HTTP ヘッダー（`X-CSRF-TOKEN`）

#### 5.3.3 XSS 対策

- 全出力箇所で `h()` 関数（`htmlspecialchars(ENT_QUOTES, 'UTF-8')`）を使用する
- コンテンツの生出力は原則禁止とし、信頼されたコンテンツにのみ例外を設ける

#### 5.3.4 パストラバーサル対策

- テーマ名、フィールド名、バックアップ名は正規表現 `^[a-zA-Z0-9_\-]+$` で検証する
- バックアップ名は `^[0-9_]+$`（数字とアンダースコアのみ）でさらに厳格に検証する
- 検証失敗時: HTTP 400 Bad Request を返す

#### 5.3.5 HTTP セキュリティヘッダー（.htaccess / Nginx）

| ヘッダー | 値 | 目的 |
|----------|----|------|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'` | XSS・コンテンツインジェクション防止 |
| `X-Content-Type-Options` | `nosniff` | MIME スニッフィング防止 |
| `X-Frame-Options` | `SAMEORIGIN` | クリックジャッキング対策 |
| `Referrer-Policy` | `same-origin` | リファラー情報の漏洩防止 |

**Apache（.htaccess）:**
```apache
<IfModule mod_headers.c>
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "same-origin"
</IfModule>
```

**Nginx（server block）:**
```nginx
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "same-origin" always;
```

#### 5.3.6 ディレクトリ保護（.htaccess / Nginx）

| 対象ディレクトリ | 保護内容 |
|------------------|----------|
| `data/` | 外部アクセス完全遮断 |
| `backup/` | 外部アクセス完全遮断 |
| `files/` | 外部アクセス完全遮断（レガシー互換） |
| `engines/*.php` | 直接アクセス禁止（`RedirectMatch 403`） |
| `uploads/` | PHP 実行禁止（`Options -ExecCGI`） |
| すべてのディレクトリ | ディレクトリ一覧表示無効 |

---

### 5.4 フロントエンド

#### 5.4.1 基本方針

- **jQuery は使用しない（廃止済み）**
- DOM 操作・イベント処理はすべてバニラ JavaScript（ES5+）で実装する
- HTTP 通信は `fetch` API を使用する
- テキストエリアの自動拡張は `autosize` ライブラリを使用する（セルフホスト）

#### 5.4.2 インプレイス編集（バニラ JS 仕様）

```
【イベントバインド】
  document.querySelectorAll('.editText') でバインド
  → click イベントで編集モードへ移行（textarea 変換）

【保存処理】
  fetch('/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ fieldname: value, csrf: token })
  })

【autosize 適用】
  apAutosize(textarea)  // engines/JsEngine/autosize.js
```

#### 5.4.3 AJAX 保存仕様

- 保存成功時: 画面遷移なし、UIフィードバック（視覚的インジケーター）
- 保存失敗時: エラーメッセージをインライン表示
- CSRF トークンは POST ボディまたは `X-CSRF-TOKEN` ヘッダーで送信する

#### 5.4.4 URL 設計（クリーン URL）

**Apache（mod_rewrite / .htaccess）:**
```apache
RewriteEngine on
Options -Indexes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond $1#%{REQUEST_URI} ([^#]*)#(.*)$
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

---

### 5.5 WYSIWYGエディタ

`engines/JsEngine/wysiwyg.js` による依存ライブラリなしの独自実装。

#### 5.5.1 起動条件

- `class="editRich"` を持つ `<span>` 要素をクリックすると WYSIWYG モードで起動する
- `editInplace.js` とは独立したモジュールとして動作する

#### 5.5.2 ツールバー機能

| ボタン | 機能 |
|--------|------|
| B / I / U | 太字・斜体・下線 |
| 🔗 | リンク挿入 |
| H2 / H3 | 見出し変換 |
| • / 1. | 箇条書き・番号リスト |
| ❝ | 引用ブロック（blockquote） |
| {} | コードブロック（pre） |
| — | 区切り線（hr） |
| ⊞ | テーブル挿入（8×8 グリッドピッカー） |
| ↩ / ↪ | Undo / Redo |
| 🖼 | 画像挿入 |

#### 5.5.3 画像機能

- ドラッグ＆ドロップ・クリップボード貼付・ボタン選択の3通りで挿入
- 許可形式: JPEG / PNG / GIF / WebP
- 挿入後に4コーナーハンドルでアスペクト比を維持したリサイズが可能
- リサイズ枠の下部に alt 属性入力欄を表示・即時反映

#### 5.5.4 テーブル機能

- 8×8 グリッドピッカーで挿入
- テーブル内カーソル時に上部へ行/列操作バーを表示（追加・削除）

#### 5.5.5 フローティングツールバー

- テキスト選択時に選択範囲上部へ B/I/U/🔗/✕ ボタンを浮かせる

#### 5.5.6 スラッシュコマンドメニュー（Ph3-F）

- 空行で `/` を入力するとブロックタイプ選択メニューが表示される
- `/コ` のようにタイプすることでインクリメンタル絞り込みが可能
- ArrowDown/Up で選択移動・Enter で確定・Escape で閉じる

#### 5.5.7 ブロックハンドル（Ph3-G）

- ブロック要素にホバーすると左端へ `⠿` ハンドルを表示する
- ハンドルをクリックするとブロックタイプ変換ポップアップを表示する

#### 5.5.8 ドラッグ並べ替え（Ph3-H）

- `⠿` ハンドルをドラッグしてブロックの順序を変更できる
- ドロップ位置にシアン色のインジケータラインが表示される

#### 5.5.9 自動保存

- 30秒間隔での定期自動保存
- Ctrl+Enter または blur（フォーカス外れ）で即時保存

#### 5.5.10 HTML サニタイザー

- 保存前にホワイトリスト方式で不正タグを除去する
- 許可タグ: `P / H1 / H2 / H3 / H4 / H5 / H6 / BR / STRONG / B / EM / I / U / STRIKE / S / A / UL / OL / LI / BLOCKQUOTE / PRE / CODE / HR / TABLE / TBODY / THEAD / TFOOT / TR / TD / TH / IMG`

---

### 5.6 アップデートエンジン

`engines/UpdateEngine.php` による実装。

#### 5.6.1 API エンドポイント一覧

| `ap_action` 値 | 処理内容 | 認証必須 |
|----------------|----------|----------|
| `check` | 最新バージョンを GitHub API で確認（TTL: 1時間キャッシュ） | ✅ |
| `check_env` | 環境確認（ZipArchive・allow_url_fopen・書込権限・ディスク容量） | ✅ |
| `apply` | アップデート適用（ZIP DL → 展開 → バックアップ → 上書き） | ✅ |
| `list_backups` | バックアップ一覧取得 | ✅ |
| `rollback` | 指定バックアップへのロールバック | ✅ |
| `delete_backup` | バックアップの削除 | ✅ |

#### 5.6.2 バックアップ仕様

- 保存先: `backup/YYYYMMDD_His/`
- 世代管理: 最大 **5 世代**（`AP_BACKUP_GENERATIONS` 定数で変更可能）
- 保護対象: `data/`・`backup/`・`.git/` ディレクトリはバックアップ・アップデート時に除外
- メタデータ: 各バックアップに `meta.json`（更新前バージョン・作成日時・ファイル数・サイズ）を記録

---

## 6. jQuery 廃止 / バニラ JS 移行仕様

### 6.1 廃止背景

| 理由 | 詳細 |
|------|------|
| 外部依存の削減 | CDN 依存によるパフォーマンス・セキュリティリスクの排除 |
| バニラ JS の成熟 | ES5+ により jQuery 相当の機能がネイティブ実装可能 |
| 軽量化 | jQuery（約 90KB minified）の除去によるロード時間短縮 |
| ライセンス整合性 | 外部ライブラリへの依存をプロジェクト方針に沿って最小化 |

### 6.2 移行完了ファイル

| ファイル | 移行内容 |
|----------|----------|
| `engines/JsEngine/editInplace.js` | jQuery を全廃・バニラJS (ES2020+) で完全リライト |
| `engines/JsEngine/wysiwyg.js` | 依存ライブラリなしの独自 WYSIWYG 実装 |
| `engines/JsEngine/updater.js` | 依存ライブラリなしの Fetch API 実装 |
| `themes/AP-Default/theme.php` | jQuery CDN 読み込みタグを削除済み |
| `themes/AP-Adlaire/theme.php` | jQuery CDN 読み込みタグを削除済み |

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

### 7.2 バージョンチェック

アプリケーション起動時に PHP バージョンを検証する:

```php
if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('AdlairePlatform requires PHP 8.2 or later. Current version: ' . PHP_VERSION);
}
```

---

## 8. 静的生成ジェネレーター機能（計画）

> ✅ **ステータス: 設計確定（実装未着手）**
>
> StaticEngine の設計は `docs/STATIC_GENERATOR.md`（Ver.0.2-1）にて承認済みです。
> 詳細は `docs/STATIC_GENERATOR.md` を参照してください。

---

## 9. ヘッドレス CMS 機能（計画）

> ✅ **ステータス: 設計確定（実装未着手）**
>
> ApiEngine の設計は `docs/HEADLESS_CMS.md`（Ver.0.3-1）にて承認済みです。

### 9.1 概要

`engines/ApiEngine.php` により、公開 REST API エンドポイントを提供します。

- **主用途**: StaticEngine が生成した静的 HTML から Fetch API で呼び出す動的機能（フォーム・検索等）
- **将来用途**: 外部フロントエンド（Next.js / Nuxt / SvelteKit）からのヘッドレス CMS 利用

### 9.2 公開エンドポイント（認証不要）

| メソッド | `ap_api` 値 | 説明 |
|---------|------------|------|
| POST | `contact` | お問い合わせフォーム送信（PHP `mail()` 使用） |
| GET | `search` | ページ全文検索（`mb_stripos` ベース） |
| GET | `page` | 単一ページコンテンツの JSON 取得 |
| GET | `pages` | 全ページ一覧（slug + プレビュー） |
| GET | `settings` | 公開サイト設定（title, description, keywords）のみ |

### 9.3 セキュリティ

- IP ベースレート制限（`contact`: 5回/15分）
- ハニーポットフィールドによるボット検出
- パラメータバリデーション（slug: `^[a-zA-Z0-9_-]+$`、検索語: 100文字制限）
- `settings` エンドポイントは `auth.json` 等の機密情報を含めない

詳細は `docs/HEADLESS_CMS.md` を参照してください。

---

## 10. ディレクトリ・ファイル構成

### 10.1 現行構成（v1.x）

```
AdlairePlatform/
├── index.php                     # アプリケーション本体（エントリーポイント）
├── .htaccess                     # Apache リライト・セキュリティ設定（Apache 専用）
├── nginx.conf.example            # Nginx 設定リファレンス
│
├── engines/
│   ├── ThemeEngine.php           # テーマ検証・読み込みロジック
│   ├── UpdateEngine.php          # アップデート・バックアップ・ロールバック
│   └── JsEngine/
│       ├── autosize.js           # テキストエリア自動リサイズ
│       ├── editInplace.js        # インプレイス編集（バニラJS）
│       ├── wysiwyg.js            # WYSIWYGエディタ（依存なし）
│       └── updater.js            # アップデートUI（AJAX）
│
├── themes/
│   ├── AP-Default/
│   │   ├── theme.php             # レイアウトテンプレート
│   │   └── style.css             # スタイルシート
│   └── AP-Adlaire/
│       ├── theme.php
│       └── style.css
│
├── data/                         # JSON データストレージ（自動生成）
│   ├── settings/
│   │   ├── settings.json
│   │   ├── auth.json
│   │   ├── update_cache.json
│   │   ├── login_attempts.json
│   │   └── version.json
│   └── content/
│       └── pages.json
│
├── uploads/                      # 画像アップロード先（PHP実行不可）
│   └── .htaccess                 # PHP禁止・Options -Indexes
│
├── backup/                       # バックアップ（自動生成）
│   └── YYYYMMDD_HHmmss/
│       └── meta.json
│
├── docs/                         # ドキュメント
│   ├── ARCHITECTURE.md
│   ├── AdlairePlatform_Design.md
│   ├── SPEC.md                   # 本仕様書
│   ├── features.md               # 実装機能一覧
│   ├── VERSIONING.md
│   ├── STATIC_GENERATOR.md       # StaticEngine 草稿
│   ├── HEADLESS_CMS.md           # ApiEngine 草稿
│   └── Licenses/
│       ├── LICENSE_Ver.1.0.md    # 旧ライセンス（アーカイブ）
│       └── LICENSE_Ver.2.0.md    # 現行ライセンス
│
├── CHANGES.md
├── RELEASE-NOTES.md
└── README.md
```

> 📝 **Nginx 利用時**: `.htaccess` は配置されているが Nginx では機能しない。
> `nginx.conf.example` に記載の Nginx server block 設定を別途記述すること。

### 10.2 保護対象ディレクトリ

| ディレクトリ | 保護方法 | 理由 |
|-------------|----------|------|
| `data/` | RedirectMatch 403 | 認証情報・コンテンツデータを保護 |
| `backup/` | RedirectMatch 403 | バックアップファイルを保護 |
| `files/` | RedirectMatch 403 | レガシー互換 |
| `engines/*.php` | RedirectMatch 403 | PHP ファイルへの直接アクセスを禁止 |
| `uploads/` | PHP 実行禁止 | アップロード済みファイルから PHP 実行を防止 |

---

## 11. データ仕様

### 11.1 data/settings/settings.json

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

### 11.2 data/content/pages.json

```json
{
  "index": "<p>トップページのコンテンツ</p>",
  "about": "<p>概要ページのコンテンツ</p>",
  "contact": "<p>お問い合わせページのコンテンツ</p>"
}
```

### 11.3 data/settings/auth.json

```json
{
  "password_hash": "$2y$10$..."
}
```

> ⚠️ `password_hash` の値は必ず bcrypt ハッシュ（`$2y$` で始まる文字列）でなければならない。
> MD5 ハッシュ（32文字の16進数）が検出された場合は警告を表示し、パスワードリセットを促す。

### 11.4 data/settings/version.json

```json
{
  "version": "1.2.21",
  "updated_at": "2026-03-08",
  "history": [
    {
      "version": "1.2.21",
      "applied_at": "2026-03-08 12:00:00",
      "backup": "20260308_120000"
    }
  ]
}
```

---

## 12. セキュリティ方針

### 12.1 セキュリティ設計原則

1. **デフォルト拒否の原則**: 不要なアクセスはすべてデフォルトで拒否する
2. **最小権限の原則**: 処理に必要な最低限の権限のみを使用する
3. **多層防御**: 単一の防御に依存せず、複数の層でセキュリティを確保する
4. **入力の検証**: すべてのユーザー入力を検証・サニタイズする
5. **出力のエスケープ**: すべての出力箇所でエスケープ処理を行う

### 12.2 脆弱性対策マトリクス

| 脅威 | 対策 | 実装場所 |
|------|------|---------|
| XSS | `h()` 関数による出力エスケープ | `index.php` 全出力箇所 |
| CSRF | 32バイトトークン + `empty()` + `hash_equals()` | `verify_csrf()` |
| セッションハイジャック | `session_regenerate_id(true)` | `login()` |
| パストラバーサル | 正規表現バリデーション | フィールド名・テーマ名・バックアップ名 |
| クリックジャッキング | `X-Frame-Options: SAMEORIGIN` | `.htaccess` / server block |
| MIME スニッフィング | `X-Content-Type-Options: nosniff` | `.htaccess` / server block |
| ディレクトリ列挙 | `Options -Indexes` | `.htaccess` / `autoindex off` |
| データ漏洩 | 保護ディレクトリへのアクセス拒否 | `.htaccess` / server block |
| ブルートフォース | 5回失敗で15分ロックアウト（IP ベース） | `check_login_rate()` |
| パスワード平文保存 | bcrypt ハッシュ化 | `savePassword()` |
| コンテンツインジェクション | CSP ヘッダー（`default-src 'self'`） | `.htaccess` / server block |
| 画像アップロード悪用 | MIME 検証・PHP 実行禁止・ランダムファイル名 | `upload_image()` |

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

詳細は `docs/Licenses/LICENSE_Ver.2.0.md` を参照してください。

---

## 14. 変更履歴

| バージョン | 日付 | 変更内容 | 担当 |
|------------|------|----------|------|
| Ver.0.2-4 | 2026-03-08 | セクション8・9 のステータスを「未検討段階」から「設計確定（実装未着手）」に更新。セクション9 に ApiEngine の概要・公開エンドポイント・セキュリティを追記。HEADLESS_CMS.md Ver.0.3-1 / STATIC_GENERATOR.md Ver.0.2-1 との整合性を確保 | Adlaire Group |
| Ver.0.2-3 | 2026-03-08 | Ver.1.2-21 対応。プラグインシステム廃止・エンジン分離・データ層分割・WYSIWYG・画像アップロード・レート制限・CSP を追加。旧 js/ / plugins/ / rte.php 参照を削除。セキュリティ課題を実装済みに更新 | Adlaire Group |
| Ver.0.1-2 | 2026-03-06 | ヘッドレス CMS 機能（計画）セクションを新規追加（セクション 9）。pitcms を参考として記録。目次・セクション番号を更新 | Adlaire Group |
| Ver.0.1-1 | 2026-03-06 | 初版確定。技術スタック策定（PHP 8.2 必須化・jQuery 廃止・バニラ JS 採用）、Apache / Nginx 両対応、アーキテクチャ変更・静的生成ジェネレーター計画の記録 | Adlaire Group |

---

*本ドキュメントは AdlairePlatform の公式仕様書です。*
*内容は Adlaire Group の承認なく変更・転載することを禁じます。*
