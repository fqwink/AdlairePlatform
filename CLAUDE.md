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

| 接頭辞 | 名称 | 言語 | 用途 |
|--------|------|------|------|
| **AFE** | Adlaire Foundation Engine | TypeScript | Router、Request、Response、EventBus、Middleware 基盤 |
| **ACS** | Adlaire Client Services | TypeScript | サーバ通信の一元化（`globalThis.__acs`） |
| **ACE** | Adlaire Content Engine | TypeScript | コレクション・コンテンツ管理 |
| **AIS** | Adlaire Infrastructure Services | TypeScript | AppContext、i18n、診断、キャッシュ |
| **ASG** | Adlaire Static Generator | TypeScript | テンプレート・Markdown・静的サイトビルド |
| **AP** | Adlaire Platform Controllers | TypeScript | 認証・管理・API コントローラ |
| **AEB** | Adlaire Editor & Blocks | TypeScript | ブロックエディタ・WYSIWYG |
| **ADS** | Adlaire Design System | CSS | スタイル定義 |
| **ASS** | Adlaire Server System | PHP | サーバサイドサービス |

### 絶対に守るべきルール

1. **1 エンジン最大 5 ファイル**: Core（必須）+ 最大 4 コンポーネント。サブディレクトリ内ファイルは上限に含めない
2. **外部依存ゼロ**: npm パッケージ・サードパーティライブラリの使用禁止。Deno 標準ライブラリのみ
3. **フレームワーク間 import 禁止**: 他エンジンの実装を直接 `import` してはならない
4. **DI コンテナ禁止**: `ApplicationFacade` のプロパティ直接参照を使用
5. **共有型ファイル禁止**: 各エンジンが独自に型を定義する。ACS 型のみ `import type` で `ACS.d.ts` から参照可
6. **ACS 経由通信**: サーバ通信はすべて `globalThis.__acs` 経由。直接 `fetch()` 禁止
7. **Core 限定参照**: `globalThis.__acs` を参照できるのは各エンジンの Core のみ
8. **Node.js 禁止**: Node.js ランタイムおよび npm の使用禁止

### ACS グローバルシングルトン

```typescript
// 許可: 型のみの参照
import type { AuthModule, StorageModule } from "../ACS/ACS.d.ts";

// 禁止: 実装の import
import { AuthService } from "../ACS/ACS.Core.ts";
```

ACS は `globalThis.__acs` として `auth`・`storage`・`files`・`http` の 4 名前空間を公開する。初期化は bootstrap で最初に実行される。

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
- エンジン外部に公開する関数は例外をスローせず結果型で返却
- ACS 通信エラーのリトライは ACS 自身が行う。呼び出し側の独自リトライ禁止

### API エラーレスポンス形式

```json
{ "ok": false, "error": "エラーメッセージ", "errors": {} }
```

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
