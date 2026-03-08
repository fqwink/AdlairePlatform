# AdlairePlatform 設計書

> 確定日: 2026-03-07
> バージョン規則: `Ver.{メジャー}.{マイナー}-{リビジョン}` (AFE VERSIONING.md準拠)
---

## 1. アーキテクチャ方針

### エンジン駆動モデル

- **エントリーポイント** `index.php` にRouter・Auth・Content・Hookを集約
- **専用エンジン** は `engines/` ディレクトリに単一ファイルで配置（JsEngineのみサブディレクトリ）
- **フック機構** は `$hook` / `editTags()` を内部用として存続、外部プラグインは廃止
- **jQuery廃止** → バニラJS (ES2020+) へ全面移行済み
- **データ層** を設定データ (`data/settings/`) とコンテンツ (`data/content/`) に分割済み

---

## 2. ディレクトリ構成（確定版）

```
AdlairePlatform/
├─ index.php                # Router・Auth・Content・Hook 集約
├─ .htaccess                # CSP・engines/ アクセス禁止・URL rewrite
├─ nginx.conf.example       # Nginx 用設定リファレンス
├─ engines/
│  ├─ ThemeEngine.php       # テーマ検証・読み込みロジック
│  ├─ UpdateEngine.php      # アップデート・バックアップ・ロールバック
│  └─ JsEngine/
│     ├─ editInplace.js     # インプレイス編集（バニラJS）
│     ├─ autosize.js        # テキストエリア自動リサイズ
│     ├─ wysiwyg.js         # WYSIWYGエディタ（依存なし）
│     └─ updater.js         # アップデートUI（AJAX）
├─ themes/
│  ├─ AP-Default/
│  │  ├─ theme.php
│  │  └─ style.css
│  └─ AP-Adlaire/
│     ├─ theme.php
│     └─ style.css
├─ data/
│  ├─ settings/
│  │  ├─ settings.json      # サイト設定
│  │  ├─ auth.json          # 認証情報（bcrypt）
│  │  ├─ update_cache.json  # アップデートキャッシュ
│  │  └─ version.json       # バージョン情報
│  └─ content/
│     └─ pages.json         # ページコンテンツ
├─ backup/                  # 最大5世代（AP_BACKUP_GENERATIONS）
├─ docs/
│  ├─ ARCHITECTURE.md       # 設計概念・ファイル責務
│  ├─ STATIC_GENERATOR.md   # StaticEngine 設計（草稿）
│  ├─ HEADLESS_CMS.md       # ApiEngine 設計（草稿）
│  └─ VERSIONING.md         # バージョン策定規則
└─ Licenses/
   └─ LICENSE_Ver.2.0.md
```

### ディレクトリ増減サマリー

| 変更 | ディレクトリ |
|------|------------|
| ➕ 追加 | `engines/`（ルート） |
| ➕ 追加 | `data/settings/`（data/内） |
| ➕ 追加 | `data/content/`（data/内） |
| ➖ 廃止 | `js/`（ルート） |
| ➖ 廃止 | `plugins/`（ルート） |

---

## 3. 各コンポーネントの責務

### index.php（集約エントリーポイント）

| 機能グループ | 主な関数 |
|------------|---------|
| ルーティング | `getSlug()`, `host()`, 404ハンドリング |
| 認証 | `login()`, `logout()`, `savePassword()`, `is_loggedin()` |
| CSRF | `csrf_token()`, `verify_csrf()` |
| コンテンツ | `content()`, `menu()`, `settings()`, `json_read()`, `json_write()` |
| フック | `editTags()`, `registerCoreHooks()`, `$hook[]` |
| ユーティリティ | `h()`, `data_dir()`, `settings_dir()`, `content_dir()`, `migrate_from_files()` |

### engines/ThemeEngine.php

- テーマ自動検出・存在確認
- `themes/{name}/theme.php` のロード
- テーマ切替ロジック（設定値に基づく）

### engines/UpdateEngine.php

- GitHub Releases API チェック（1時間キャッシュ）
- ZIPダウンロード・展開・適用
- バックアップ作成（meta.json付き、最大5世代）
- ロールバック・バックアップ削除
- 環境チェック（ZipArchive, allow_url_fopen, 権限, ディスク容量）
- バックアップ名の内部バリデーション（defense-in-depth）

### engines/JsEngine/editInplace.js

- `class="editText"` 要素クリックで `<textarea>` に変換
- `autosize` 適用
- `fetch` (vanilla) でフォーカスアウト時に保存
- CSRF トークンをメタタグから取得（未検出時は console.error を出力）

### engines/JsEngine/autosize.js

- テキストエリア高さの自動調整（セルフホスト）
- `DOMContentLoaded` で初期化

### engines/JsEngine/updater.js

- 管理画面のアップデートパネル UI（バニラJS / Fetch API）
- バージョン確認・更新適用・ロールバック・バックアップ削除
- バックアップ一覧をテーブル形式で表示（作成日時・更新前バージョン・サイズ）

---

## 4. エディタ設計（2段階移行）

### Phase 1（Ver.1.1-12 で実装済み）

- **RTE廃止**: `richText` クラスを削除、`rte.php` / `rte.js` を削除
- **統合**: `class="editText"` に一本化し、HTMLタグ直接入力モードとして再定義
- **削除対象**: `admin-richText` フック、`rte.js`（JsEngineに含まない）

### Phase 2（将来・WYSIWYG選定後）

- 外部WYSIWYGライブラリを採用（候補: Quill, TipTap, Editor.js）
- `editInplace.js` を拡張またはWYSIWYGアダプタを追加
- `admin-richText` フックを復活させてWYSIWYGを差し込む設計

---

## 5. フック機構

### 存続フック（内部専用）

| フック名 | 用途 |
|---------|-----|
| `admin-head` | 管理画面 `<head>` 内スクリプト挿入 |
| `admin-richText` | Phase 1では廃止済み、Phase 2でWYSIWYG用に復活予定 |

### 廃止済み

- `loadPlugins()` による外部プラグイン走査
- `plugins/` ディレクトリ全体
- 外部からのフック登録機能

### 現行実装

```php
// 旧: loadPlugins() → 新: registerCoreHooks()
function registerCoreHooks(): void {
    global $hook;
    $hook['admin-head'][] = "\n\t<script src='engines/JsEngine/autosize.js'></script>";
    $hook['admin-head'][] = "\n\t<script src='engines/JsEngine/editInplace.js'></script>";
    $hook['admin-head'][] = "\n\t<script src='engines/JsEngine/updater.js'></script>";
}
```

---

## 6. データ層

### 移行パス

| 旧パス | 新パス |
|-------|-------|
| `data/settings.json` | `data/settings/settings.json` |
| `data/auth.json` | `data/settings/auth.json` |
| `data/update_cache.json` | `data/settings/update_cache.json` |
| `data/pages.json` | `data/content/pages.json` |

### 自動マイグレーション

`migrate_from_files()` 関数が旧パスから新パスへ自動移行する。
起動時に旧ファイルが存在すれば新ディレクトリに移動する。（実装済み）

---

## 7. セキュリティ

### .htaccess 設定（実装済み）

```apache
# engines/ 直接アクセス禁止
<FilesMatch "\.(php)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

# CSP ヘッダー
Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';"
```

### 実装済みセキュリティ

- bcrypt パスワードハッシュ
- CSRF トークン（`random_bytes(32)`）
- XSS エスケープ (`h()` / `htmlspecialchars`)
- HttpOnly + SameSite=Lax Cookie
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: same-origin`
- CSP ヘッダー（`default-src 'self'`）
- `engines/` 直接アクセス禁止（.htaccess）
- `data/`, `backup/`, `files/` ディレクトリ保護
- バックアップ名の defense-in-depth バリデーション

---

## 8. テックスタック

| 要素 | 内容 |
|-----|-----|
| サーバーサイド | PHP 8.2+ (必須) |
| Webサーバー | Apache (mod_rewrite, mod_headers) または Nginx (php-fpm) |
| ストレージ | JSON ファイル |
| フロントエンド | バニラJS (ES2020+) |
| パスワードハッシュ | bcrypt |
| PHP拡張 | json, mbstring, ZipArchive |
| 設定 | `allow_url_fopen = On` (推奨) |
| ファイル権限 | `data/`, `backup/` が書き込み可能であること |
| 最低ディスク容量 | 50 MB |

---

## 9. バージョン計画

| フェーズ | バージョン | ステータス | 主な内容 |
|---------|----------|-----------|---------|
| 初期 | `Ver.1.0-11` | ✅ 完了 | 初期リリース・アップデートエンジン・バックアップ等 |
| P1完了 | `Ver.1.1-12` | ✅ 完了 | PHP 8.2 必須化・jQuery廃止・JsEngine・RTE廃止 |
| P2完了 | `Ver.1.2-13` | ✅ 完了 | エンジン分離・データ層分割・サードパーティ排除 |
| P3完了 | `Ver.1.2-14` | ✅ 完了 | セキュリティ強化（CSP・engines/保護・nginx設定） |
| P4完了 | `Ver.1.2-15` | ✅ 完了 | ドキュメント整備 |


> **バージョン規則**: リビジョンはリセット禁止、常に累積加算
> **正しい例**: `Ver.1.0-11 → Ver.1.1-12 → Ver.1.2-13 → Ver.1.2-14 → Ver.1.2-15 → Ver.1.2-16`

---

## 10. 実装タスクリスト（全24件）

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
| P4-2 | ✅ `docs/STATIC_GENERATOR.md` 草稿 | `docs/STATIC_GENERATOR.md` |
| P4-3 | ✅ `docs/HEADLESS_CMS.md` 草稿 | `docs/HEADLESS_CMS.md` |

### Phase 2 – 将来タスク（時期未定）

| # | タスク |
|---|-------|
| Ph2-1 | WYSIWYGライブラリ選定（Quill / TipTap / Editor.js 等） |
| Ph2-2 | WYSIWYGエディタ実装（`editInplace.js` 拡張 または 専用アダプタ） |
| Ph2-3 | `admin-richText` フック復活・WYSIWYG差し込み |

---

## 11. 機能リスト

### 実装済み（Ver.1.2-16 現在）

#### コンテンツ管理
- ✅ ページ作成・編集・保存（JSON）
- ✅ インプレイス編集（editInplace.js / バニラJS）
- ✅ スラッグベースURL
- ✅ 404ハンドリング
- ✅ サイト設定管理

#### テーマエンジン
- ✅ テーマ自動検出・切替
- ✅ AP-Default テーマ
- ✅ AP-Adlaire テーマ
- ✅ `engines/ThemeEngine.php` 分離（P2-4）

#### 認証・セキュリティ
- ✅ bcrypt 単一パスワード認証
- ✅ HttpOnly + SameSite=Lax Cookie セッション
- ✅ CSRF トークン（`random_bytes(32)`）
- ✅ XSS エスケープ（`h()`）
- ✅ パストラバーサル対策
- ✅ セキュリティヘッダー（X-Content-Type-Options, X-Frame-Options, Referrer-Policy）
- ✅ CSP ヘッダー（`default-src 'self'`）
- ✅ `engines/` 直接アクセス禁止（.htaccess）
- ✅ `data/`, `backup/`, `files/` ディレクトリ保護
- ✅ バックアップ名 defense-in-depth バリデーション（basename + 正規表現）

#### フックシステム
- ✅ `admin-head` フック（autosize.js・editInplace.js・updater.js 挿入）
- ✅ `registerCoreHooks()` による内部フック管理
- ✅ `editTags()` スクリプト挿入

#### アップデートエンジン
- ✅ GitHub Releases APIバージョンチェック（1時間キャッシュ）
- ✅ ZIP ダウンロード・展開・適用
- ✅ バックアップ作成（meta.json付き、最大5世代）
- ✅ ロールバック
- ✅ バックアップ削除
- ✅ 環境チェック（ZipArchive, allow_url_fopen, 権限, ディスク容量）

#### フロントエンド
- ✅ インプレイス編集 UI（バニラJS）
- ✅ autosize セルフホスト（`engines/JsEngine/autosize.js`）
- ✅ クリーンURL（mod_rewrite）
- ✅ updater.js（アップデートUI / Fetch API）
- ✅ PHP 8.2 バージョンチェック（起動時）
- ✅ jQuery廃止済み

#### データ層
- ✅ `data/settings/` 分割（settings.json / auth.json / update_cache.json / version.json）
- ✅ `data/content/` 分割（pages.json）
- ✅ `migrate_from_files()` 旧パス自動移行

### 将来計画（Phase 2以降）

- 🔜 WYSIWYGエディタ（外部ライブラリ採用）
- 🔜 静的サイト生成（`engines/StaticEngine.php`）
- 🔜 ヘッドレスCMS（`engines/ApiEngine.php`）

---

## 12. ライセンス

Adlaire License Ver.2.0（ソース公開型・非オープンソース）
著作権者: Adlaire Group / 最高権限者: Kazuhiko Kurata
詳細: `Licenses/LICENSE_Ver.2.0.md`

主な制限:
- 再配布禁止
- 商用利用には許可が必要
- リバースエンジニアリング禁止

---

