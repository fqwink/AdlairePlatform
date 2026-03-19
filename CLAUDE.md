# CLAUDE.md

## プロジェクト概要

AdlairePlatform（AP）は、Deno ベースのフラットファイル CMS フレームワークである。データベース不要で JSON ストレージを使用し、静的サイト生成・テンプレートエンジン・ブロックエディタ・ヘッドレス REST API を提供する。

## 技術スタック

- **バックエンド**: Deno 2.x+、TypeScript（strict モード）
- **サーバ**: PHP 8.4+（ASS — 共有レンタルサーバ向け）
- **フロントエンド**: JavaScript（vanilla）、CSS
- **データ**: JSON フラットファイル（`data/` 配下）
- **外部依存**: ゼロ（言語標準ライブラリのみ）

## 開発コマンド

```bash
deno task dev          # 開発サーバ（watch モード）
deno task start        # 本番サーバ起動
deno task check        # 型チェック（サーバサイド）
deno task check:browser # 型チェック（ブラウザ向け）
deno task lint         # リント
deno task fmt          # フォーマット
deno task test         # テスト実行
```

## 環境変数

| 変数 | デフォルト | 用途 |
|------|-----------|------|
| `AP_PORT` | `8080` | サーバポート |
| `AP_BASE_URL` | `""` | API ベース URL |
| `AP_TOKEN` | — | API 認証トークン |
| `AP_LOCALE` | `"ja"` | デフォルトロケール |

## アーキテクチャ — エンジン駆動モデル

各フレームワークは「エンジン」として自己完結する。以下の厳格なルールに従うこと。

### エンジン一覧

| 接頭辞 | 名称 | 言語 | 用途 | ファイル数 |
|--------|------|------|------|-----------|
| **AFE** | Adlaire Foundation Engine | TypeScript | Router、Request、Response、EventBus、MiddlewarePipeline 等の基盤 | 5 |
| **ACS** | Adlaire Client Services | TypeScript | サーバ通信の一元化（`globalThis.__acs`）（§3 参照） | 5 |
| **ACE** | Adlaire Content Engine | TypeScript | コレクション・コンテンツ・メタデータ管理、管理画面 UI | 5 + クライアントモジュール |
| **AIS** | Adlaire Infrastructure Services | TypeScript | AppContext、i18n、API キャッシュ、診断、リクエストロギング | 5 |
| **ASG** | Adlaire Static Generator | TypeScript | Markdown パース・テンプレートレンダリング・静的サイトビルド | 5 |
| **ASS** | Adlaire Server System | PHP | 認証・セキュリティ・ストレージ・ファイル操作・Git 等のサーバサイドサービス | 5 |
| **ADS** | Adlaire Design System | CSS | ベーススタイル・コンポーネントスタイル・エディタスタイル・管理画面スタイル | 3 + アセット |
| **AEB** | Adlaire Editor & Blocks | TypeScript | ブロックエディタ・WYSIWYG | 3 + クライアントモジュール |

### 絶対に守るべきルール

1. **1 エンジン 5 ファイル原則**: Core（必須）+ 最大 4 コンポーネント。サブディレクトリ内ファイルは上限に含めない。例外は Adlaire Group の承認を要する（FRAMEWORK_RULEBOOK §2.2）
2. **自己完結**: 各エンジンは他のエンジンの存在を前提とした設計を禁止する
3. **エンジン内協調（Core 経由）**: 同一エンジン内のコンポーネント間で参照が必要な場合は、必ず Core を介す。Core 以外のコンポーネント同士の直接参照は禁止
4. **外部依存ゼロ**: サードパーティライブラリおよび外部パッケージへの依存は禁止。言語標準ライブラリのみ
5. **フレームワーク間依存ゼロ**: 他エンジンの実装を直接 `import` してはならない（ACS を含む）。サーバ通信は `globalThis.__acs` 経由
6. **DI コンテナ禁止**: `ApplicationFacade` のプロパティ直接参照を使用
7. **共有型ファイル禁止**: 各エンジンが独自に型を定義する。ACS 型のみ `import type` で `ACS.d.ts` から参照可
8. **ACS 経由通信**: サーバ通信はすべて `globalThis.__acs` 経由。直接 `fetch()` 禁止
9. **Core 限定参照**: `globalThis.__acs` を参照できるのは各エンジンの Core のみ。Core 以外が ACS 機能を必要とする場合は Core を介して間接的に利用
10. **Node.js 禁止**: Node.js ランタイムおよび npm の使用禁止

### ACS グローバルシングルトン

```typescript
// 許可: 型のみの参照
import type { AuthModule, StorageModule } from "../ACS/ACS.d.ts";

// 禁止: 実装の import
import { AuthService } from "../ACS/ACS.Core.ts";
```

ACS は `globalThis.__acs` として `auth`・`storage`・`files`・`http` の 4 名前空間を公開する。初期化は bootstrap で最初に実行される。

#### モジュールキャッシュ方式（FRAMEWORK_RULEBOOK §3.5）

- **トップレベル生成**: ACS インスタンスはエントリモジュールのトップレベルで生成。遅延初期化禁止
- **凍結**: `globalThis.__acs` に代入するオブジェクトは `Object.freeze()` で凍結
- **再代入禁止**: `globalThis.__acs` への代入は ACS エントリモジュール評価時の 1 回のみ
- **動的 import 禁止**: ACS エントリモジュールを `import()` 式で読み込むことは禁止

## ファイル構成

```
AdlairePlatform/
├── main.ts                  # HTTP サーバエントリポイント
├── bootstrap.ts             # ApplicationFacade 初期化
├── routes.ts                # ルート・ミドルウェア登録
├── deno.json                # Deno 設定・タスク定義
├── Framework/
│   ├── mod.ts               # バレルエクスポート
│   ├── browser.deno.json    # ブラウザ向けコンパイラ設定
│   ├── {PREFIX}/            # 各エンジンディレクトリ
│   │   ├── {PREFIX}.Core.ts # Core（必須）
│   │   ├── {PREFIX}.Interface.ts
│   │   ├── {PREFIX}.Class.ts
│   │   ├── {PREFIX}.Api.ts
│   │   ├── {PREFIX}.Utilities.ts
│   │   ├── {PREFIX}.d.ts    # 公開型定義（ACS のみ）
│   │   └── ClientEngine/    # クライアントモジュール（任意）
├── data/                    # JSON ストレージ
│   ├── content/             # ページ・コレクション・リビジョン
│   └── settings/            # アプリ設定・認証・API キー
├── themes/                  # テーマテンプレート
├── lang/                    # i18n（ja.json, en.json）
└── docs/                    # 内部ドキュメント
```

### 命名規則

- **コンポーネントファイル**: `{PREFIX}.{ComponentName}.{ext}`（例: `ACS.Core.ts`、`ASS.Api.php`）
- **エンジンディレクトリ**: 接頭辞と同一の大文字（例: `AFE/`、`ACS/`）
- **サブディレクトリ**: PascalCase（例: `ClientEngine/`、`AdminEngine/`）
- **クライアントモジュール**: kebab-case（例: `acs-api-client.ts`、`aeb-wysiwyg.ts`）

## コーディング規約

### フォーマット

- インデント: 2 スペース
- 行幅: 100 文字
- クォート: ダブルクォート
- セミコロン: 必須
- `deno fmt` で自動フォーマット

### リント

- `deno lint` で recommended ルールセットを適用
- `no-explicit-any` は除外
- `Framework/ADS/` はリント・フォーマット対象外

### TypeScript

- `strict: true`、`noImplicitAny: true` 必須
- ブラウザ向けコード（`ClientEngine/`、`AEB`）は `browser.deno.json` に従う
- PHP は `declare(strict_types=1)` 必須

## エラーハンドリング

- エラークラスは各エンジンの Class コンポーネントに定義
- 他エンジンのエラークラスの直接参照禁止
- **結果型の必須化**: エンジン外部に公開する関数は例外をスローせず、成功と失敗を表す結果型で返却
- **エンジン内例外の公開境界捕捉**: エンジン内部での例外スローは許容するが、公開境界で必ず捕捉すること
- ACS 通信エラーのリトライは ACS 自身が行う。呼び出し側の独自リトライ禁止

### API エラーレスポンス形式

```json
{ "ok": false, "error": "エラーメッセージ", "errors": {} }
```

#### HTTP ステータスコード

| ステータス | 用途 |
|-----------|------|
| `400` | リクエストパラメータの不正、バリデーションエラー |
| `401` | 認証が必要、またはトークンが無効 |
| `403` | 認証済みだがアクセス権限がない |
| `404` | リソースが存在しない |
| `500` | サーバ内部エラー |

## バージョニング

`Ver.{Major}.{Minor}-{Revision}` 形式の累積バージョニングを採用。リビジョン番号は絶対にリセットしない。

- **Major**: 後方互換性のない破壊的変更
- **Minor**: 後方互換性を保った機能追加・改良
- **Revision**: 累積（微調整、軽微なバグ修正、ドキュメント更新、リファクタリング）

現在のバージョン: **Ver.2.3-44**

## ドキュメント優先順位

矛盾がある場合は上位が優先される。

1. `docs/AdlairePlatform_DetailedDesign.md` — 詳細設計（最上位準拠文書）
2. `docs/FRAMEWORK_RULEBOOK_v3.0.md` — フレームワーク規約
3. `docs/VERSIONING.md` — バージョニング規則
4. `docs/DOCUMENT_RULEBOOK.md` — ドキュメント管理ルール

## 初期化順序

1. ACS 初期化（`globalThis.__acs` 公開）
2. `ApplicationFacade` インスタンス生成
3. 設定読み込み（`globalThis.__acs.storage` 経由）
4. i18n 初期化
5. イベントリスナー登録
