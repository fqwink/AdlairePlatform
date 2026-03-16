# 実装機能ステータス一覧

Ver.2.1-41 | 最終更新: 2026-03-16

## 凡例

| 記号 | 意味 |
|------|------|
| ✅ | 実装済み |
| ⚠️ | 部分実装 |
| 🔧 | スタブ |
| ❌ | 未実装（インターフェースのみ） |

---

## サーバ — ASS（PHP 8.3+）

ACS クライアント SDK との通信のみを担当。

| 機能 | ステータス |
|------|-----------|
| 認証（ログイン/ログアウト/セッション/CSRF） | ✅ |
| ストレージ（JSON CRUD / ファイル一覧） | ✅ |
| ファイル管理（アップロード/ダウンロード/画像最適化） | ✅ |
| Git 操作（configure/pull/push/log/status/test） | ✅ |
| ヘルスチェック | ✅ |
| 管理画面（ユーザー/セッション/サーバ/Git/ストレージ） | ✅ |

---

## クライアント — ACS（TypeScript）

サーバ実装を隠蔽し統一インターフェースを提供。

| 機能 | ステータス |
|------|-----------|
| HTTP トランスポート（インターセプタ/タイムアウト/リトライ） | ✅ |
| 認証サービス（login/logout/session/verify/csrf） | ✅ |
| ストレージサービス（read/write/delete/exists/list） | ✅ |
| ファイルサービス（upload/download/delete/info） | ✅ |
| SSE 接続（EventSourceService） | ✅ |
| ストレージ watch | 🔧 |

---

## バックエンド — APF（TypeScript）

ルーティング・DI・バリデーション等のプラットフォーム基盤。

| 機能 | ステータス |
|------|-----------|
| Router（全 HTTP メソッド/グループ/ミドルウェア/パスパラメータ） | ✅ |
| DI コンテナ（bind/singleton/make） | ✅ |
| Request / Response | ✅ |
| ミドルウェアパイプライン | ✅ |
| Application（Router + Middleware 統合） | ✅ |
| EventBus | ✅ |
| Validator | ✅ |
| Str / Arr ユーティリティ | ✅ |
| Config | ✅ |
| Security（escape/sanitize/csrf/sha256） | ⚠️ |
| FileSystem / JsonStorage | ✅ |
| DB 接続（Connection/QueryBuilder/Model/Schema） | ❌ |
| キャッシュ（CacheInterface） | ❌ |
| ロガー（LoggerInterface） | ❌ |
| セッション（SessionInterface） | ❌ |

---

## バックエンド — ACE（TypeScript）

コンテンツ管理エンジン。

| 機能 | ステータス |
|------|-----------|
| コレクション管理（create/delete/list/schema） | ✅ |
| コンテンツ CRUD / 検索 / ソート / ページネーション | ✅ |
| メタデータ管理 | ✅ |
| バリデーション | ✅ |
| Webhook（登録/削除/有効切替/配信） | ✅ |
| リビジョン管理 | ✅ |
| REST API（/api/collections, /api/search） | ✅ |
| CollectionServiceInterface（高レベルファサード） | ❌ |

---

## バックエンド — AIS（TypeScript）

インフラサービス。Git/更新/診断は ASS PHP 経由に移行。

| 機能 | ステータス |
|------|-----------|
| AppContext（設定管理・ACS 経由） | ✅ |
| I18n（国際化・ACS 経由） | ✅ |
| ServiceContainer | ✅ |
| EventDispatcher | ✅ |
| DiagnosticsManager | ✅ |
| ApiCache | ✅ |
| GitService（ACS 経由で ASS に委譲） | ✅ |
| UpdateService | 🔧 |

---

## バックエンド — ASG（TypeScript）

静的サイトジェネレータ。

| 機能 | ステータス |
|------|-----------|
| buildAll / buildDiff | ✅ |
| ビルドキャッシュ（ハッシュベース差分） | ✅ |
| テンプレートレンダリング（変数/if/each/パーシャル） | ✅ |
| Markdown パース（frontmatter/HTML 変換） | ✅ |
| テーマ管理 | ✅ |
| HTML/CSS ミニファイ | ✅ |
| HybridResolver（Static-First 配信） | ✅ |
| SiteRouter / サイトマップ生成 | ✅ |
| Deployer（.htaccess / _redirects 生成） | ✅ |
| ZIP 生成 | 🔧 |
| 画像処理（ImageProcessorInterface） | ❌ |
| StaticServiceInterface（高レベルファサード） | ❌ |

---

## バックエンド — AP（TypeScript）

コントローラー層。

| 機能 | ステータス |
|------|-----------|
| BaseController / ActionDispatcher | ✅ |
| AuthController（login/logout） | ✅ |
| DashboardController | ✅ |
| AdminController（フィールド編集/画像/リビジョン/ユーザー/リダイレクト） | ✅ |
| CollectionController（CRUD/マイグレーション） | ✅ |
| GitController（ACS 経由で ASS に委譲） | ✅ |
| WebhookController（add/delete/toggle） | ✅ |
| StaticController | 🔧 |
| UpdateController | 🔧 |
| DiagnosticController | 🔧 |
| ミドルウェア（CSRF/Auth/RateLimit/CORS/Security/Logging） | ✅ |

---

## フロントエンド — AEB（JavaScript）

ブロックエディタ。

| 機能 | ステータス |
|------|-----------|
| Editor（初期化/保存/Undo/Redo） | ✅ |
| ブロック（Paragraph/Heading/List/Quote/Code/Image/Table/Checklist/Delimiter） | ✅ |
| EventBus / StateManager / HistoryManager / BlockRegistry | ✅ |
| DOM / Selection / Keyboard ユーティリティ | ✅ |
| HTML サニタイザ | ✅ |

---

## フロントエンド — ADS（CSS）

デザインシステム。全実装済み。

---

## 統計サマリー

| モジュール | 言語 | 分類 | 完成度 |
|-----------|------|------|--------|
| ASS | PHP | サーバ | 100% |
| ACS | TypeScript | クライアント | 95% |
| APF | TypeScript | バックエンド | 70% |
| ACE | TypeScript | バックエンド | 95% |
| AIS | TypeScript | バックエンド | 85% |
| ASG | TypeScript | バックエンド | 85% |
| AP | TypeScript | バックエンド | 65% |
| AEB | JavaScript | フロントエンド | 100% |
| ADS | CSS | フロントエンド | 100% |
