# AdlairePlatform — セキュリティ方針

<!-- ⚠️ 削除禁止: 本ドキュメントはセキュリティ方針の正式文書です -->
<!-- 機密レベル: 社内限定 -->

> **ドキュメントバージョン**: Ver.1.0-1  
> **ステータス**: 🔧 開発中（Ver.1.8）
> **作成日**: 2026-03-10  
> **最終更新**: 2026-03-10（新規作成）  
> **所有者**: Adlaire Group  
> **バージョニング規則**: [AFE/VERSIONING.md](https://github.com/fqwink/AdlaireGroup-Documents-Repository/blob/main/AFE/VERSIONING.md)

> **関連ドキュメント**:
> - 設計方針: [AdlairePlatform_Design.md](AdlairePlatform_Design.md)
> - アーキテクチャ: [ARCHITECTURE.md](ARCHITECTURE.md)
> - 詳細設計: [DETAILED_DESIGN.md](DETAILED_DESIGN.md)
> - 機能一覧: [features.md](features.md)

---

## 目次

1. [セキュリティ設計原則](#1-セキュリティ設計原則)
2. [認証・セッション管理](#2-認証セッション管理)
3. [CSRF 対策](#3-csrf-対策)
4. [XSS 対策](#4-xss-対策)
5. [パストラバーサル対策](#5-パストラバーサル対策)
6. [HTTP セキュリティヘッダー](#6-http-セキュリティヘッダー)
7. [ディレクトリ保護](#7-ディレクトリ保護)
8. [脆弱性対策マトリクス](#8-脆弱性対策マトリクス)
9. [API セキュリティ](#9-api-セキュリティ)
10. [実装詳細](#10-実装詳細)
11. [変更履歴](#11-変更履歴)

---

## 1. セキュリティ設計原則

AdlairePlatform のセキュリティ設計は以下の 5 原則に基づきます:

| 原則 | 内容 |
|------|------|
| **1. デフォルト拒否の原則** | 不要なアクセスはすべてデフォルトで拒否する |
| **2. 最小権限の原則** | 処理に必要な最低限の権限のみを使用する |
| **3. 多層防御** | 単一の防御に依存せず、複数の層でセキュリティを確保する |
| **4. 入力の検証** | すべてのユーザー入力を検証・サニタイズする |
| **5. 出力のエスケープ** | すべての出力箇所でエスケープ処理を行う |

---

## 2. 認証・セッション管理

### 2.1 認証仕様

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

### 2.2 レート制限実装

```php
// check_login_rate($ip) により実装
// 5回失敗で15分（900秒）のロックアウト
// data/settings/login_attempts.json に記録
```

### 2.3 セッション固定対策

```php
// AdminEngine::login() 内で実装
session_regenerate_id(true);  // ログイン成功時にセッション ID を再生成
```

---

## 3. CSRF 対策

### 3.1 トークン生成

- 32 バイトのランダムトークンをセッションに保持する（`random_bytes(32)`）
- トークンは16進数文字列に変換して保存

### 3.2 検証方式

- 全 POST リクエストで `verify_csrf()` による検証を行う
- 検証方法: `empty()` ガード + `hash_equals()` による定数時間比較

### 3.3 トークン送信方法

- フォーム hidden フィールド（`csrf`）
- HTTP ヘッダー（`X-CSRF-TOKEN`）

### 3.4 実装例

```php
// AdminEngine::csrfToken() でトークン生成
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
return $_SESSION['csrf'];

// AdminEngine::verifyCsrf() で検証
function verifyCsrf() {
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION['csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf'], $token);
}
```

---

## 4. XSS 対策

### 4.1 出力エスケープ

- 全出力箇所で `h()` 関数（`htmlspecialchars(ENT_QUOTES, 'UTF-8')`）を使用する
- コンテンツの生出力は原則禁止とし、信頼されたコンテンツにのみ例外を設ける

### 4.2 テンプレートエンジンでのエスケープ

- `{{variable}}` 構文: 自動エスケープ
- `{{{variable}}}` 構文: 生 HTML 出力（信頼されたコンテンツのみ）

### 4.3 Content Security Policy

CSP ヘッダーにより外部スクリプトの実行を制限:

```
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';
```

---

## 5. パストラバーサル対策

### 5.1 検証対象

- テーマ名
- フィールド名
- バックアップ名
- コレクション名
- Git パス

### 5.2 検証パターン

| 対象 | 正規表現 | 説明 |
|------|---------|------|
| テーマ名 | `^[a-zA-Z0-9_\-]+$` | 英数字・アンダースコア・ハイフンのみ |
| フィールド名 | `^[a-zA-Z0-9_\-]+$` | 英数字・アンダースコア・ハイフンのみ |
| バックアップ名 | `^[0-9_]+$` | 数字とアンダースコアのみ |
| コレクションスラッグ | `^[a-zA-Z0-9][a-zA-Z0-9_-]*$` | 先頭は英数字、以降は英数字・アンダースコア・ハイフン |

### 5.3 検証失敗時の処理

検証失敗時は HTTP 400 Bad Request を返す。

---

## 6. HTTP セキュリティヘッダー

### 6.1 標準ヘッダー

| ヘッダー | 値 | 目的 |
|----------|----|------|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'` | XSS・コンテンツインジェクション防止 |
| `X-Content-Type-Options` | `nosniff` | MIME スニッフィング防止 |
| `X-Frame-Options` | `SAMEORIGIN` | クリックジャッキング対策 |
| `Referrer-Policy` | `same-origin` | リファラー情報の漏洩防止 |

### 6.2 Apache 設定（.htaccess）

```apache
<IfModule mod_headers.c>
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "same-origin"
</IfModule>
```

### 6.3 Nginx 設定（server block）

```nginx
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "same-origin" always;
```

---

## 7. ディレクトリ保護

### 7.1 保護対象ディレクトリ

| 対象ディレクトリ | 保護内容 |
|------------------|----------|
| `data/` | 外部アクセス完全遮断 |
| `backup/` | 外部アクセス完全遮断 |
| `files/` | 外部アクセス完全遮断（レガシー互換） |
| `Framework/*.php` | 直接アクセス禁止（`RedirectMatch 403`） |
| `uploads/` | PHP 実行禁止（`Options -ExecCGI`） |

### 7.2 Apache 設定（.htaccess）

```apache
# data/ ディレクトリ保護
<IfModule mod_authz_core.c>
    <Directory "/path/to/data">
        Require all denied
    </Directory>
</IfModule>

# Framework/ 直接アクセス禁止
RedirectMatch 403 ^.*/Framework/.*\.php$

# uploads/ 内 PHP 実行禁止
<Directory "/path/to/uploads">
    php_flag engine off
    Options -ExecCGI
    AddHandler cgi-script .php .php3 .php4 .php5 .phtml .pl .py .jsp .asp .sh .cgi
</Directory>
```

### 7.3 Nginx 設定（server block）

```nginx
# data/ ディレクトリ保護
location ~ ^/data/ {
    deny all;
    return 403;
}

# backup/ ディレクトリ保護
location ~ ^/backup/ {
    deny all;
    return 403;
}

# Framework/ 直接アクセス禁止
location ~ /Framework/.*\.php$ {
    deny all;
    return 403;
}

# uploads/ 内 PHP 実行禁止
location ~ ^/uploads/.*\.php$ {
    deny all;
    return 403;
}
```

---

## 8. 脆弱性対策マトリクス

| 脅威 | 対策 | 実装場所 |
|------|------|---------|
| XSS | `h()` 関数による出力エスケープ | `index.php` / `AdminEngine` 全出力箇所 |
| CSRF | 32バイトトークン + `empty()` + `hash_equals()` | `AdminEngine::verifyCsrf()` |
| セッションハイジャック | `session_regenerate_id(true)` | `AdminEngine::login()` |
| パストラバーサル | 正規表現バリデーション | フィールド名・テーマ名・バックアップ名 |
| クリックジャッキング | `X-Frame-Options: SAMEORIGIN` | `.htaccess` / server block |
| MIME スニッフィング | `X-Content-Type-Options: nosniff` | `.htaccess` / server block |
| ディレクトリ列挙 | `Options -Indexes` | `.htaccess` / `autoindex off` |
| データ漏洩 | 保護ディレクトリへのアクセス拒否 | `.htaccess` / server block |
| ブルートフォース | 5回失敗で15分ロックアウト（IP ベース） | `check_login_rate()` |
| パスワード平文保存 | bcrypt ハッシュ化 | `savePassword()` |
| コンテンツインジェクション | CSP ヘッダー（`default-src 'self'`） | `.htaccess` / server block |
| 画像アップロード悪用 | MIME 検証・PHP 実行禁止・ランダムファイル名 | `upload_image()` |
| SSRF | プライベート IP ブロック・DNS リバインディング防止 | `WebhookEngine` |
| JSON-LD XSS | `JSON_UNESCAPED_SLASHES` 除去 | `ThemeEngine` |
| メールヘッダインジェクション | Subject の改行除去 | `ApiEngine` / `MailerEngine` |
| CORS キャッシュポイズニング | `Vary: Origin` ヘッダー | `ApiEngine` |
| オープンリダイレクト | リダイレクト先 URL 検証 | `AdminEngine` |
| API 認証 | Bearer トークン + bcrypt | `ApiEngine` |

---

## 9. API セキュリティ

### 9.1 認証方式

- **公開エンドポイント**: 認証不要
- **管理エンドポイント**: API キー認証（Bearer トークン + bcrypt）

### 9.2 CORS 設定

- 設定可能なオリジン許可
- `Vary: Origin` ヘッダー付き（キャッシュポイズニング防止）

### 9.3 レート制限

- IP ベースレート制限（`contact`: 5回/15分）
- `data/settings/login_attempts.json` に記録

### 9.4 入力検証

| パラメータ | 検証 |
|-----------|------|
| slug | `^[a-zA-Z0-9_-]+$` |
| 検索語 | 100文字制限 |
| email | `filter_var($email, FILTER_VALIDATE_EMAIL)` |

### 9.5 ハニーポット

フォームにハニーポットフィールドを設置してボット検出:

```html
<input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
```

サーバー側で `$_POST['website']` が空でない場合はボットと判定。

---

## 10. 実装詳細

### 10.1 パスワードハッシュ化

```php
// bcrypt ハッシュ化（AdminEngine::savePassword()）
$hash = password_hash($password, PASSWORD_BCRYPT);

// 検証（AdminEngine::login()）
if (password_verify($password, $hash)) {
    $_SESSION['l'] = true;
    session_regenerate_id(true);
}
```

### 10.2 画像アップロード検証

```php
// AdminEngine::handleUploadImage()
// 1. MIME 検証
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $tmpPath);
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowed)) {
    die('Invalid file type');
}

// 2. サイズ制限（2MB）
if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
    die('File too large');
}

// 3. ランダムファイル名
$filename = bin2hex(random_bytes(12)) . '.' . $ext;
```

### 10.3 SSRF 防止（WebhookEngine）

```php
// WebhookEngine::isPrivateHost()
// プライベート IP ブロック
$ip = gethostbyname($host);
if ($ip === $host) {
    return true;  // DNS 解決失敗 → ブロック
}

$private_ranges = [
    '10.0.0.0/8',
    '172.16.0.0/12',
    '192.168.0.0/16',
    '127.0.0.0/8',
    '169.254.0.0/16',
    'fd00::/8',
    'fe80::/10',
    '::1/128',
];

// IP が プライベート範囲に含まれるかチェック
```

### 10.4 メールヘッダインジェクション対策

```php
// MailerEngine::sanitizeHeader()
function sanitizeHeader($value) {
    return str_replace(["\r", "\n", "\0"], '', $value);
}

// Subject の改行除去
$subject = sanitizeHeader($subject);
```

---

## 11. 変更履歴

本ドキュメントの変更履歴は、以下のファイルで一元管理されています:

👉 **[docs/DOCUMENT_CHANGELOG.md](./DOCUMENT_CHANGELOG.md#-security_policymd)** を参照してください。

---

*本ドキュメントは AdlairePlatform の公式セキュリティ方針です。*  
*内容は Adlaire Group の承認なく変更・転載することを禁じます。*  
*機密レベル: 社内限定*
