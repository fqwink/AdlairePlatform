# AdlairePlatform フレームワークルールブック v3.0

## 1. 目的

AdlairePlatform におけるフレームワークの設計・開発・運用の統一規則を定める。

---

## 3. エンジン駆動モデル

### 3.1 基本原則

エンジン駆動モデルは AdlairePlatform 独自のフレームワーク設計パターンである。

- **1 エンジン 5 ファイル原則** — 1 フレームワーク = 1 エンジン。5 つのモジュラーコンポーネント（Core のみ定義必須）が一体となり動作する
- **自己完結** — 5 ファイル一体で自己完結する。他エンジンの存在を前提としない
- **モジュラーコンポーネント** — 各ファイルはエンジン一体型の構成部品。1 コンポーネント内に複数クラスの配置を許容する。各コンポーネントにはエンジン全体の設計意図に沿う範囲で役割配分は柔軟に調整できる
- **エンジン内協調** — 同一エンジン内のコンポーネントは相互参照を Core のみ定義
- **外部依存ゼロ** — サードパーティライブラリ・外部パッケージへの依存を禁止する。言語標準ライブラリのみ使用可
- **フレームワーク間依存ゼロ** — フレームワーク間の直接 `import` を一切禁止する（ACS 含む）。サーバ通信が必要な場合は `globalThis.__acs` 経由でアクセスする（§4 参照）
- **ACS グローバルシングルトン** — ACS は `globalThis.__acs` にシングルトンとして公開される。各フレームワークは ACS を `import` せず `globalThis.__acs` を通じて機能を利用する。型参照は `ACS/ACS.d.ts` からの `import type` のみ許可する
- **共有型ファイル禁止** — `Framework/types.ts` 等の共有型ファイルを持たない。各フレームワークは必要な型を自己定義するか、ACS が公開する `ACS.d.ts` を `import type` で参照する

### 3.2 構成の例外規定

| 条件 | 許容内容 | 承認 |
|------|---------|------|
| 1〜4 ファイル | 単一責務のフレームワークに限り許容 | Adlaire Group 承認必須 |
| 6〜7 ファイル | 明確な責務分離理由がある場合に許容 | Adlaire Group 承認必須 |
| 8 ファイル以上 | **禁止** — 新規フレームワーク化を検討する | — |

---

## 4. ACS グローバルシングルトン仕様

### 4.1 概要

ACS（Adlaire Client Services）はサーバ（ASS）との HTTP API 通信を担うエンジンである。他フレームワークは ACS を直接 `import` せず、`globalThis.__acs` を通じて機能を利用する。

### 4.2 公開インターフェース

ACS は bootstrap 時に以下の名前空間で `globalThis` に公開される。

```typescript
globalThis.__acs = {
  auth,     // 認証（login / logout / session / verify / csrf）
  storage,  // ストレージ（read / write / delete / exists / list）
  files,    // ファイル操作（upload / download / delete / info）
  http,     // HTTP トランスポート（get / post / put / delete）
};
```

### 4.3 型定義

- ACS は `Framework/ACS/ACS.d.ts` に公開型定義を配置する
- 各フレームワークは `import type` のみで `ACS.d.ts` を参照できる
- 実装の `import`（値の import）は禁止

```typescript
// 許可: 型のみ参照
import type { AuthModule, StorageModule } from "../ACS/ACS.d.ts";

// 禁止: 実装の import
import { AuthService } from "../ACS/ACS.Core.ts";
```

### 4.4 利用ルール

| ルール | 内容 |
|--------|------|
| **import 禁止** | 各フレームワークは ACS の実装ファイルを `import` してはならない |
| **globalThis 経由** | サーバ通信は必ず `globalThis.__acs` 経由で行う |
| **型参照のみ許可** | `ACS.d.ts` からの `import type` のみ許可 |
| **直接 fetch 禁止** | 各フレームワークが `fetch()` を直接呼ぶことを禁止する |
| **初期化順序** | ACS の初期化（`globalThis.__acs` の公開）は bootstrap で最初に行う |
