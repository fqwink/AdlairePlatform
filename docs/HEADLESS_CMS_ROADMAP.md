# ヘッドレスCMS ロードマップ — pitcms.net モデル採用計画

> **ドキュメントバージョン**: Ver.1.3-final
> **ステータス**: 🔒 Ver.1.3系終了（Ver.1.3-29 をもって開発終了）
> **作成日**: 2026-03-08
> **最終更新**: 2026-03-09
> **所有者**: Adlaire Group
> **分類**: 社内限り
>
> pitcms.net の仕組みを参考に、AdlairePlatform を
> **ヘッドレスCMS機能搭載の静的サイトジェネレーターCMS** へ進化させるための調査・設計ドキュメント。

---

## 目次

1. [pitcms.net 調査結果](#1-pitcmsnet-調査結果)
2. [AdlairePlatform 現状との比較](#2-adlaireplatform-現状との比較)
3. [目指す姿 — Git ベースヘッドレス CMS + SSG](#3-目指す姿--git-ベースヘッドレス-cms--ssg)
4. [アーキテクチャ設計](#4-アーキテクチャ設計)
5. [フェーズ別実装ロードマップ](#5-フェーズ別実装ロードマップ)
6. [技術的検討事項](#6-技術的検討事項)
7. [未解決事項](#7-未解決事項)
8. [参考資料](#8-参考資料)

---

## 1. pitcms.net 調査結果

### 1.1 概要

**pitcms** は2026年1月に登場した日本製ヘッドレスCMS。「はがしやすい（ベンダーロックインしない）」をコンセプトとする Git ベースの CMS。

| 項目 | 内容 |
|------|------|
| 正式名称 | pitcms |
| コンセプト | 「はがしやすい日本製ヘッドレスCMS」 |
| 技術スタック | SvelteKit（管理画面）、GitHub API（データストア） |
| データストア | GitHub リポジトリ（Markdown ファイル） |
| 価格 | 無料プラン（3コレクション/3メンバー）、Pro ¥980/月（無制限） |

### 1.2 コア機能

#### Git ベースコンテンツ管理
- コンテンツはすべて GitHub リポジトリに Markdown ファイルとして保存
- 記事の投稿・編集 ＝ Git コミット
- プルリクエストベースの記事レビューが可能
- 「いつ誰が何を変えたか」の完全な履歴
- pitcms をやめてもデータはユーザーの GitHub に残る（ベンダーロックインなし）

#### コレクション（スキーマ定義）
- `pitcms.jsonc` ファイルでコレクション（コンテンツ型）を定義
- リポジトリルートに配置する設定ファイルでスキーマを管理

#### 編集セッション
- 管理画面から「編集セッション」を開始
- 編集するとプレビュー用ブランチが Git 上に自動作成される
- 保存後にプレビュー URL が発行される
- 「変更を反映」で main ブランチにマージ → 本番デプロイ

#### お問い合わせフォーム
- HTML 標準フォーム送信で設置可能
- 送信内容は GitHub Issue として保存
- 管理画面から確認・返信まで一元管理

#### プレビュー環境
- ホスティングサービスと連携した編集プレビュー
- 対応プラットフォーム: Cloudflare Pages, Vercel, Netlify, Cloudflare Workers
- ワンクリックセットアップ

#### レビューワークフロー
- コンテンツ公開前にレビューを依頼可能
- チーム承認フローを簡単に構築
- Git のプルリクエストの仕組みを活用

#### マルチ環境
- 開発・ステージング・本番の複数環境を簡単にセットアップ
- Git ブランチ戦略と連動

### 1.3 技術的特徴

| 特徴 | 詳細 |
|------|------|
| データベース不要 | Git リポジトリがデータストア |
| 静的サイト生成 | ビルド時にコンテンツを埋め込み → 表示速度が高速 |
| フレームワーク互換性 | Astro Content Collections との強い互換性を確認 |
| パフォーマンス | Lighthouse 98/100（モバイル）の実績報告 |
| ローカル編集対応 | ローカルで記事編集 → push → pitcms 管理画面に反映 |

### 1.4 ワークフロー概要

```
[管理画面で編集セッション開始]
    ↓
[コレクション内の記事を編集]
    ↓
[保存] → Git プレビューブランチ自動作成
    ↓
[プレビュー URL 発行] → Cloudflare Pages 等でプレビュー確認
    ↓
[レビュー依頼] → チームメンバーが承認
    ↓
[変更を反映] → main にマージ → 本番サイト自動デプロイ
```

---

## 2. AdlairePlatform 現状との比較

### 2.1 機能対照表

| 機能 | pitcms | AP 現状（Ver.1.3-29） | ギャップ |
|------|--------|----------------------|---------|
| **データストア** | GitHub リポジトリ (Markdown) | ローカル JSON ファイル (`pages.json`) | Git 連携が未実装 |
| **コンテンツ形式** | Markdown | HTML（WYSIWYG 出力） | Markdown 対応が必要 |
| **スキーマ定義** | `pitcms.jsonc` コレクション | なし（単一 pages.json） | コレクション機能が必要 |
| **バージョン管理** | Git 履歴（完全） | リビジョン JSON（30件制限） | Git ベースへの移行 |
| **プレビュー** | ホスティング連携プレビュー | 動的 PHP 表示 | ブランチベースプレビュー |
| **レビュー** | PR ベース承認フロー | なし | ワークフロー機能が必要 |
| **マルチ環境** | ブランチ = 環境 | なし | 環境分離の仕組みが必要 |
| **静的生成** | 外部 SSG（Astro 等） | StaticEngine（PHP 内蔵） | ✅ 実装済み |
| **公開 API** | GitHub API 経由 | ApiEngine（5エンドポイント） | ✅ 基盤あり |
| **フォーム** | GitHub Issue 保存 | PHP `mail()` 送信 | GitHub Issue 連携が未実装 |
| **認証** | GitHub OAuth（推定） | 単一パスワード + セッション | マルチユーザー対応が必要 |
| **管理画面** | SvelteKit SPA | PHP 動的レンダリング | 段階的に改善 |
| **ホスティング** | SaaS（pitcms.net） | セルフホスト | セルフホスト維持（強み） |

### 2.2 AdlairePlatform の強み（維持すべき点）

1. **セルフホスト**: サーバーを自分で管理できる。SaaS 依存なし
2. **ゼロ依存**: PHP のみで動作。Node.js / npm 不要
3. **StaticEngine**: 差分ビルド付き静的生成が実装済み
4. **ApiEngine**: 公開 REST API の基盤が実装済み
5. **WYSIWYG エディタ**: バニラ JS のリッチエディタが完成
6. **テンプレートエンジン**: Mustache 風テンプレートが動作中
7. **軽量性**: 単一 PHP ファイル + エンジンファイル数個

---

## 3. 目指す姿 — Git ベースヘッドレス CMS + SSG

### 3.1 ビジョン

> pitcms と同じ Git ベースの仕組みを採用しつつ、
> AdlairePlatform のセルフホスト・ゼロ依存の強みを維持した
> ヘッドレス CMS + 静的サイトジェネレーター。

### 3.2 設計原則

| 原則 | 説明 |
|------|------|
| **Git-First** | コンテンツの正本は Git リポジトリ。JSON / DB はキャッシュ |
| **セルフホスト維持** | SaaS ではなく、ユーザーのサーバーで動作 |
| **PHP のみ（コア）** | コア機能は PHP のみで完結。Node.js はオプショナル |
| **段階的移行** | 既存機能を壊さず、段階的に Git ベースへ移行 |
| **はがしやすさ** | pitcms 同様、CMS を外してもコンテンツ（Git リポジトリ）は残る |

### 3.3 動作モード

```
【モード 1: レガシー（現状維持）】
  ローカル JSON → StaticEngine → 静的 HTML
  ※ Git 連携なし。現在の動作をそのまま維持

【モード 2: Git 連携（新規）】
  GitHub リポジトリ ←→ AP 管理画面
      ↓ 同期
  ローカル content/ ディレクトリ（Markdown/JSON）
      ↓ ビルド
  StaticEngine → 静的 HTML
      ↓ デプロイ
  同一サーバー or 外部ホスティング

【モード 3: ヘッドレス API（将来）】
  GitHub リポジトリ ←→ AP 管理画面
      ↓ API
  外部フロントエンド（Astro / Next.js / Nuxt）が API 経由でコンテンツ取得
```

---

## 4. アーキテクチャ設計

### 4.1 新規エンジン構成

```
engines/
├── AdminEngine.php          # 既存: 管理画面・認証
├── ApiEngine.php            # 既存: 公開 REST API → 拡張
├── StaticEngine.php         # 既存: 静的生成 → 拡張
├── TemplateEngine.php       # 既存: テンプレート描画
├── ThemeEngine.php          # 既存: テーマ管理
├── UpdateEngine.php         # 既存: 自動更新
│
├── GitEngine.php            # 🆕 Git/GitHub 連携エンジン
├── CollectionEngine.php     # 🆕 コレクション（スキーマ）管理
├── MarkdownEngine.php       # 🆕 Markdown パーサー
└── WorkflowEngine.php       # 🆕 レビュー・承認ワークフロー
```

### 4.2 GitEngine — Git/GitHub 連携

pitcms の核心機能。コンテンツを GitHub リポジトリと双方向同期する。

```php
class GitEngine {
    /** GitHub リポジトリとの接続設定を管理 */
    public static function configure(string $repo, string $token): void

    /** リポジトリからコンテンツを pull（同期） */
    public static function pull(): array

    /** ローカル変更を commit + push */
    public static function push(string $message): bool

    /** プレビュー用ブランチを作成 */
    public static function createPreviewBranch(string $name): string

    /** プレビューブランチを main にマージ */
    public static function mergeToMain(string $branch): bool

    /** コミット履歴を取得 */
    public static function log(int $limit = 20): array

    /** 差分を取得 */
    public static function diff(string $from, string $to): string
}
```

**実装方針**:
- PHP の `exec()` で `git` コマンドを直接実行（サーバーに Git がインストールされている前提）
- Git がない環境では GitHub REST API（v3）を HTTP クライアントで直接呼び出す
- Personal Access Token (PAT) で認証
- `data/settings/git_config.json` に接続情報を保存

```json
// data/settings/git_config.json
{
  "enabled": true,
  "provider": "github",
  "repository": "user/my-site-content",
  "branch": "main",
  "token": "ghp_xxxxxxxxxxxx",
  "content_dir": "content",
  "auto_sync": false,
  "last_sync": "2026-03-08T12:00:00+09:00"
}
```

### 4.3 CollectionEngine — コレクション管理

pitcms の `pitcms.jsonc` に相当するスキーマ定義機能。

```
content/
├── ap-collections.json      # コレクション定義（pitcms.jsonc 相当）
├── posts/                   # コレクション: ブログ記事
│   ├── hello-world.md
│   └── second-post.md
├── pages/                   # コレクション: 固定ページ
│   ├── about.md
│   └── contact.md
└── news/                    # コレクション: お知らせ
    └── 2026-03-launch.md
```

```json
// content/ap-collections.json
{
  "collections": {
    "posts": {
      "label": "ブログ記事",
      "directory": "posts",
      "format": "markdown",
      "fields": {
        "title": { "type": "string", "required": true },
        "date": { "type": "date", "required": true },
        "tags": { "type": "array", "items": "string" },
        "draft": { "type": "boolean", "default": false },
        "thumbnail": { "type": "image" }
      },
      "sortBy": "date",
      "sortOrder": "desc"
    },
    "pages": {
      "label": "固定ページ",
      "directory": "pages",
      "format": "markdown",
      "fields": {
        "title": { "type": "string", "required": true },
        "order": { "type": "number", "default": 0 }
      }
    }
  }
}
```

### 4.4 MarkdownEngine — Markdown 処理

pitcms が Markdown ベースであるため、Markdown パーサーが必要。

**実装方針**:
- PHP のみで Markdown → HTML 変換（外部ライブラリ不使用）
- フロントマター（YAML 形式）のパース
- GFM（GitHub Flavored Markdown）互換を目指す

```php
class MarkdownEngine {
    /** Markdown テキストを HTML に変換 */
    public static function toHtml(string $markdown): string

    /** フロントマターを抽出 */
    public static function parseFrontmatter(string $content): array
    // 返値: ['meta' => [...], 'body' => '本文...']

    /** コレクション内の全 .md ファイルを読み込み */
    public static function loadCollection(string $dir): array
}
```

フロントマター例:
```markdown
---
title: はじめてのブログ記事
date: 2026-03-08
tags: [announcement, launch]
draft: false
---

# はじめまして

ブログを始めました。
```

### 4.5 WorkflowEngine — レビュー・承認

pitcms のレビュー機能に相当。Git ブランチ + PR の仕組みを活用。

```php
class WorkflowEngine {
    /** 編集セッションを開始（プレビューブランチ作成） */
    public static function startSession(string $editor): string

    /** 変更をコミット（プレビューブランチ上） */
    public static function saveChanges(string $session, array $changes): bool

    /** レビューを依頼（PR 作成相当） */
    public static function requestReview(string $session): bool

    /** レビューを承認 */
    public static function approve(string $session, string $reviewer): bool

    /** 変更を反映（main にマージ） */
    public static function publish(string $session): bool

    /** セッション一覧 */
    public static function listSessions(): array
}
```

### 4.6 ApiEngine の拡張

既存の ApiEngine に管理 API とコレクション API を追加。

```
【既存エンドポイント（認証不要）】
  ?ap_api=pages       → 全ページ一覧
  ?ap_api=page        → 単一ページ
  ?ap_api=settings    → 公開設定
  ?ap_api=search      → 全文検索
  ?ap_api=contact     → お問い合わせ

【新規エンドポイント — コレクション API（認証不要）】
  ?ap_api=collections           → コレクション定義一覧
  ?ap_api=collection&name=posts → 特定コレクションの全アイテム
  ?ap_api=item&collection=posts&slug=hello-world → 単一アイテム

【新規エンドポイント — 管理 API（要認証）】
  ?ap_api=item_upsert   → アイテム作成・更新（POST）
  ?ap_api=item_delete    → アイテム削除（DELETE）
  ?ap_api=git_sync       → Git 同期実行（POST）
  ?ap_api=git_status     → Git 状態取得（GET）
  ?ap_api=workflow_start → 編集セッション開始（POST）
  ?ap_api=workflow_publish → 変更を反映（POST）
```

### 4.7 お問い合わせフォーム — GitHub Issue 連携

pitcms ではフォーム送信が GitHub Issue として保存される。この機能を追加。

```php
// ApiEngine の contact エンドポイントを拡張
private static function handleContact(): void {
    // 既存: PHP mail() でメール送信

    // 新規: GitHub Issue としても保存（Git 連携有効時）
    if (GitEngine::isEnabled()) {
        GitEngine::createIssue(
            title: "お問い合わせ: {$name}",
            body: "**名前**: {$name}\n**メール**: {$email}\n\n{$message}",
            labels: ['contact']
        );
    }
}
```

---

## 5. フェーズ別実装ロードマップ

### Phase 0: 基盤整備 ✅ 完了

> 既存機能の安定化。セキュリティ監査 Round 1-6 で完了。

| タスク | 内容 | 対応する Plan 提案 |
|--------|------|-------------------|
| セキュリティ修正 | XSS 脆弱性、data: URL バイパス | A1, A2, A3 |
| 編集体験改善 | `_apChanging` バグ修正、保存フィードバック | B1, B2, B3 |
| ダッシュボード改善 | テーマハンドラ重複排除、ページ削除、ログ | C1, C2, C3, C4 |

### Phase 1: Markdown 対応 + コレクション基盤 ✅ 実装済み

> CollectionEngine + MarkdownEngine として Ver.1.3-28 で実装完了。

| タスク | 詳細 |
|--------|------|
| MarkdownEngine 実装 | PHP 製 Markdown → HTML パーサー。フロントマター対応 |
| コレクション定義 | `ap-collections.json` のスキーマ設計・読み込み |
| `content/` ディレクトリ | `pages.json` 一本から `content/{collection}/*.md` への移行パス |
| 後方互換 | `pages.json` も引き続き動作。マイグレーション自動実行 |
| コレクション API | ApiEngine に `collections`, `collection`, `item` エンドポイント追加 |

**マイグレーション戦略**:
```
pages.json（現行）
    ↓ 自動マイグレーション（opt-in）
content/pages/index.md
content/pages/about.md
content/pages/contact.md
```

### Phase 2: Git 連携 ✅ 実装済み

> GitEngine として Ver.1.3-28 で実装完了。

| タスク | 詳細 |
|--------|------|
| GitEngine 実装 | `git` コマンド実行 or GitHub API 呼び出し |
| GitHub PAT 設定 UI | ダッシュボードに接続設定パネル追加 |
| Push / Pull 機能 | 管理画面からワンクリック同期 |
| コミット履歴表示 | リビジョン画面を Git log で置換 |
| 自動同期（オプション） | Webhook 受信 or ポーリングで自動 pull |

### Phase 3: プレビュー + レビューワークフロー 🔜 次期バージョン以降

> pitcms のプレビュー環境・レビュー承認フローは次期バージョンで検討。

| タスク | 詳細 |
|--------|------|
| WorkflowEngine 実装 | 編集セッション → プレビューブランチ → レビュー → マージ |
| プレビューブランチ | 編集時に Git ブランチを自動作成 |
| ホスティング連携 | Cloudflare Pages / Vercel / Netlify の Deploy Preview と連動 |
| レビュー UI | ダッシュボードにレビュー依頼・承認のインターフェース |

### Phase 4: ヘッドレス API 強化 ✅ 実装済み

> 外部フロントエンド（Astro, Next.js 等）からの利用を本格サポート。

| タスク | 詳細 | ステータス |
|--------|------|-----------|
| 管理 API 認証 | API キー + Bearer トークン認証 | ✅ 実装済み |
| GraphQL 対応（検討） | REST に加えて GraphQL エンドポイント（オプション） | 🔜 将来 |
| Webhook 送信 | WebhookEngine: HMAC-SHA256 署名付き通知 | ✅ 実装済み |
| メディア API | media_list / media_upload / media_delete | ✅ 実装済み |
| コンテンツスケジューリング | status + publishDate による予約公開 | ✅ 実装済み |
| プレビューモード | `?ap_api=preview` ドラフトプレビュー | ✅ 実装済み |
| インポート/エクスポート | JSON/CSV バルク操作 | ✅ 実装済み |
| 画像最適化 | GD リサイズ + サムネイル + WebP | ✅ 実装済み |
| API キャッシュ | ファイルベース + ETag + 自動無効化 | ✅ 実装済み |
| コレクションテンプレート | テーマ別一覧・個別テンプレート | ✅ 実装済み |
| API ドキュメント自動生成 | OpenAPI / Swagger 形式 | 🔜 将来 |

### Phase 5: マルチ環境 + マルチユーザー（部分実装） ✅/🔜

> マルチユーザー・ロールベースアクセス・監査ログは Ver.1.3-28 で実装済み。マルチ環境・GitHub OAuth は次期バージョン以降。

| タスク | 詳細 | ステータス |
|--------|------|-----------|
| マルチ環境 | Git ブランチ = 環境（dev / staging / production） | 🔜 将来 |
| マルチユーザー | users.json による独自ユーザー管理 | ✅ 実装済み |
| ロールベースアクセス | admin / editor / viewer ロール | ✅ 実装済み |
| 監査ログ | ユーザー名付きアクティビティログ | ✅ 実装済み |
| GitHub OAuth | 外部認証連携 | 🔜 将来 |

---

## 6. 技術的検討事項

### 6.1 Markdown パーサーの選定

| 選択肢 | 利点 | 欠点 |
|--------|------|------|
| 自前実装（PHP） | ゼロ依存維持 | 開発コスト高。GFM 完全互換は困難 |
| Parsedown（PHP ライブラリ） | 軽量・高速・GFM 対応 | 外部依存（単一ファイル、Composer 不要で導入可） |
| GitHub API で変換 | 完全な GFM 互換 | API 呼び出しが必要。オフライン不可 |

**推奨**: 最小限の自前実装から開始し、必要に応じて Parsedown を `engines/vendor/` に同梱する。

### 6.2 Git 実行環境

| 方式 | 前提条件 | 対応環境 |
|------|---------|---------|
| `exec('git ...')` | サーバーに Git インストール済み | VPS、専用サーバー |
| GitHub REST API | HTTP 通信のみ | 共用サーバーを含むすべて |
| Git + GitHub API ハイブリッド | Git があれば `exec`、なければ API | 最大互換 |

**推奨**: ハイブリッド方式。`exec('which git')` で Git の有無を検出し、自動的に切り替え。

### 6.3 既存 WYSIWYG との共存

- 現行の `wysiwyg.js` は HTML 出力
- Markdown 対応後も WYSIWYG モードは維持（非技術ユーザー向け）
- 管理画面で「Markdown モード / WYSIWYG モード」を切り替え可能にする
- WYSIWYG で編集した HTML を Markdown に変換する逆変換は Phase 4 以降

### 6.4 pages.json からの移行パス

```
Phase 0-1:
  pages.json（既存）→ そのまま動作

Phase 1 opt-in:
  管理画面「Markdown 移行」ボタン → 自動変換
  pages.json の各エントリを content/pages/{slug}.md に変換
  フロントマター自動生成
  変換後も pages.json は読み取り専用で残す（フォールバック）

Phase 2 以降:
  Git 連携有効時は content/ ディレクトリが正本
  pages.json はキャッシュとして自動生成（パフォーマンス用）
```

### 6.5 StaticEngine との連携

現行 StaticEngine は `pages.json` から読み取り。Phase 1 以降は `content/` ディレクトリからも読み取れるように拡張。

```php
// StaticEngine::init() の拡張イメージ
private function init(): void {
    $this->settings = json_read('settings.json', settings_dir());

    // コレクションモードか従来モードかを判定
    if (CollectionEngine::isEnabled()) {
        $this->pages = CollectionEngine::loadAllAsPages();
    } else {
        $this->pages = json_read('pages.json', content_dir());
    }
    // ...
}
```

---

## 7. Ver.1.3 系 実装済み新機能

> **Ver.1.3 系ステータス**: 🔒 **Ver.1.3-29 をもって Ver.1.3系は終了しました。** 以降の機能追加は次期バージョンにて実施予定。

以下の機能が Ver.1.3 系で実装された:

| 機能 | エンジン | 概要 |
|------|---------|------|
| コンテンツスケジューリング | CollectionEngine | status (draft/published/scheduled/archived) + publishDate による予約公開 |
| メディア管理 API | ApiEngine | `media_list`, `media_upload`, `media_delete` エンドポイント |
| Outgoing Webhook | WebhookEngine (新規) | コンテンツ変更時に外部サービスへ HMAC-SHA256 署名付き通知 |
| コレクションテンプレート | StaticEngine | `collection-{name}-index.html`, `collection-{name}-single.html` |
| プレビューモード | ApiEngine | `?ap_api=preview` でドラフト含むアイテムをプレビュー |
| インポート/エクスポート | ApiEngine | JSON/CSV でのバルクインポート・エクスポート |
| 画像最適化 | ImageOptimizer (新規) | GD によるリサイズ(1920px)、サムネイル(400px)、WebP 変換 |
| API レスポンスキャッシュ | CacheEngine (新規) | ファイルベースキャッシュ + ETag/Last-Modified + 自動無効化 |
| マルチユーザー基盤 | AdminEngine | users.json によるユーザー管理、admin/editor/viewer ロール |
| OGP / メタタグ自動生成 | ThemeEngine | Open Graph / Twitter Card / JSON-LD / canonical 自動生成 |
| コレクションページネーション | StaticEngine | perPage 設定による一覧ページ分割 |
| タグ・カテゴリページ | StaticEngine | フロントマター tags からタグ別・タグ一覧ページ自動生成 |
| 404 カスタムエラーページ | StaticEngine | テーマ対応 404.html 静的生成 |
| 前後記事ナビゲーション | StaticEngine | コレクションアイテムの prev_item / next_item コンテキスト |
| クライアントサイド検索 | StaticEngine + ap-search.js | search-index.json 生成 + 軽量検索 JS |
| HTML/CSS ミニファイ | StaticEngine | PHP 正規表現ベースの軽量圧縮 |
| リダイレクト管理 | AdminEngine + StaticEngine | .htaccess + _redirects 自動生成、ダッシュボード UI |
| ビルドフック | StaticEngine | before_build / after_page_render / after_build / after_asset_copy |
| インクリメンタルデプロイ | StaticEngine | 変更ファイルのみ ZIP ダウンロード |

## 8. 未解決事項（次期バージョンへの引き継ぎ）

| 事項 | ステータス | 内容 |
|------|-----------|------|
| WorkflowEngine | 🔜 次期 | プレビュー + レビュー承認ワークフロー（Phase 3） |
| マルチ環境 | 🔜 次期 | Git ブランチ = 環境（dev / staging / production） |
| WYSIWYG → Markdown 変換 | 🔜 次期 | 逆変換の精度と実装コスト |
| GraphQL 対応 | 🔜 次期 | REST に加えて GraphQL エンドポイント |
| GitHub OAuth | 🔜 次期 | GitHub OAuth による外部認証 |
| Astro / Next.js テンプレート | 🔜 次期 | スターターテンプレートの提供 |
| OpenAPI ドキュメント自動生成 | 🔜 次期 | Swagger 形式の API ドキュメント |

---

## 9. 参考資料

### pitcms.net
- 公式サイト: https://pitcms.net/
- コンセプト: 「はがしやすい日本製ヘッドレスCMS」
- 技術記事: https://yuheijotaki.com/blog/2026020901_pitcms/
- 解説記事: https://it-araiguma.com/pitcms-git-based-headless-cms-guide/

### AdlairePlatform 既存ドキュメント
- `docs/HEADLESS_CMS.md` — ApiEngine 設計書（Ver.0.3-1）
- `docs/STATIC_GENERATOR.md` — StaticEngine 仕様書（Ver.0.3-2）
- `docs/ARCHITECTURE.md` — 全体設計書
- `docs/SPEC.md` — 仕様書

### 一般参考
- ヘッドレス CMS アーキテクチャ: Git ベース vs API ベースの比較
  https://www.red-gate.com/simple-talk/development/web/headless-cms-content-management-systems-contrasting-git-based-and-api-based/

---

## 変更履歴

| バージョン | 日付 | 変更内容 |
|------------|------|---------|
| Ver.0.1-1 | 2026-03-08 | 初版。pitcms.net 調査結果、AP 現状比較、アーキテクチャ設計、フェーズ別ロードマップ |
| Ver.0.2-1 | 2026-03-09 | 9機能実装: スケジューリング、メディアAPI、Webhook、テンプレート、プレビュー、インポート/エクスポート、画像最適化、キャッシュ、マルチユーザー |
| Ver.0.3-1 | 2026-03-09 | SSG 10機能実装: OGP、ページネーション、タグページ、404、前後ナビ、検索インデックス、ミニファイ、リダイレクト、ビルドフック、デプロイ |
| Ver.1.3-draft | 2026-03-09 | Ver.1.3 系としてバージョン体系を整理。デバッグ・バグ修正フェーズへ移行 |
| Ver.1.3-final | 2026-03-09 | Ver.1.3系終了。全機能実装・セキュリティ監査完了。次期バージョンへの引き継ぎ事項を「未解決事項」に整理 |

---

*Adlaire License Ver.2.0 — 社内限り*
