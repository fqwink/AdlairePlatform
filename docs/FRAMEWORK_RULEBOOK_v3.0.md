# AdlairePlatform フレームワークルールブック v3.0

## 1. 目的

AdlairePlatform におけるフレームワークの設計・開発・運用の統一規則を定める。

---

## 2. アーキテクチャ

### 2.1 全体構成

```
┌──────────────────────────────────────────────────────┐
│                   AdlairePlatform                      │
│                                                        │
│  フロントエンド                        クライアント      │
│  APF, ACE, AIS, ASG, AP, AEB, ADS    ACS              │
│                                       │                │
│                                       ▼                │
│                                     サーバ              │
│                                      ASS               │
└──────────────────────────────────────────────────────┘
```

### 2.2 分類と役割

| 分類 | 役割 | フレームワーク | 言語 |
|------|------|--------------|------|
| フロントエンド | ビジネスロジック・CMS 機能 | APF, ACE, AIS, ASG, AP | TypeScript (Deno 2.x+) |
| フロントエンド | UI 描画・ブラウザ側処理 | AEB | JavaScript (ES6+) |
| フロントエンド | スタイル定義 | ADS | CSS3 |
| クライアント | サーバ通信の抽象化・共有層 | ACS | TypeScript (Deno 2.x+) |
| サーバ | 認証・データストレージ・ファイル管理 | ASS | PHP 8.3+ |

### 2.3 分類間規則

- フロントエンド ↔ サーバの通信は必ず ACS 経由とする
- フロントエンドからサーバのクラス・関数を直接呼び出してはならない
- サーバは他フレームワークに依存しない。ACS の契約のみに準拠する

---

## 3. エンジン駆動モデル

### 3.1 基本原則

エンジン駆動モデルは AdlairePlatform 独自のフレームワーク設計パターンである。

- **1 エンジン 5 ファイル原則** — 1 フレームワーク = 1 エンジン。5 つのモジュラーコンポーネント（Core のみ定義必須）が一体となり動作する
- **自己完結** — 5 ファイル一体で自己完結する。他エンジンの存在を前提としない
- **モジュラーコンポーネント** — 各ファイルはエンジン一体型の構成部品。1 コンポーネント内に複数クラスの配置を許容する。各コンポーネントにはエンジン全体の設計意図に沿う範囲で役割配分は柔軟に調整できる
- **エンジン内協調** — 同一エンジン内のコンポーネントは相互参照を Core のみ定義
- **外部依存ゼロ** — サードパーティライブラリ・外部パッケージへの依存を禁止する。言語標準ライブラリのみ使用可
- **フレームワーク間依存ゼロ** — ACS への相互参照のみ許可し、ACS に相互参照の登録義務化（§4 参照）

### 3.2 構成の例外規定

| 条件 | 許容内容 | 承認 |
|------|---------|------|
| 1〜4 ファイル | 単一責務のフレームワークに限り許容 | Adlaire Group 承認必須 |
| 6〜7 ファイル | 明確な責務分離理由がある場合に許容 | Adlaire Group 承認必須 |
| 8 ファイル以上 | **禁止** — 新規フレームワーク化を検討する | — |

---

## 4. ACS 共有層

### 4.1 共有層の位置づけ

ACS（Adlaire Client Services）エンジン全体が、全フレームワーク共通の共有層を担う。他エンジンが ACS の全コンポーネント（Interface / Class / Core / Api / Utils）を参照することを許可する。

```
  APF ──→ ACS ←── ACE
  AIS ──→ ACS ←── ASG
   AP ──→ ACS ←── AEB
            ↑
           ASS（PHP 側は ACS の契約に準拠）
```

### 4.2 参照規則

- **参照方向は一方向のみ** — 他エンジン → ACS。ACS が他エンジンを参照してはならない
- **相互依存は禁止** — ACS 以外のエンジン間の直接参照は一切禁止
- **相互参照の登録義務** — 他エンジンが ACS を参照する場合、その相互参照を ACS に登録しなければならない

### 4.3 ACS に配置すべきもの

- 通信契約型（リクエスト / レスポンス型、API エンドポイント契約）
- 共通エラー型
- 複数エンジンが共有するインターフェース・抽象クラス
- 複数エンジンが共有するユーティリティ関数・定数
- 複数エンジンが共有するデータクラス・値オブジェクト

### 4.4 ACS に配置してはならないもの

- 特定エンジン内部でのみ使用する型・クラス → 各エンジン自身に定義する
- ビジネスロジック固有の型 → 該当エンジンに定義する

### 4.5 ACS の設計制約

- **破壊的変更の禁止** — ACS の公開インターフェースを破壊的に変更してはならない。変更は後方互換を保つこと
- **依存方向の厳守** — ACS は他エンジンの存在を一切知らない。特定エンジン向けのロジックを ACS に入れてはならない
- **最小公開の原則** — ACS から export するものは、複数エンジンで共有する必要性が明確なもののみとする。「いずれ使うかもしれない」は配置理由にならない

---

## 5. 命名規則

### 5.1 フレームワーク略称

3 文字固定（`AP` のみ 2 文字例外）。正式名称の頭文字に基づく英大文字。全フレームワーク間で一意。

### 5.2 ファイル名

形式: `{略称}.{コンポーネント名}.{拡張子}`（PascalCase）

拡張子: `.php` / `.ts` / `.js` / `.css`

### 5.3 名前空間・エクスポート

- PHP: `{略称}\{コンポーネント名}\{クラス名}`
- TypeScript: named export のみ。デフォルトエクスポート禁止

### 5.4 ディレクトリ

`Framework/{略称}/`（略称は大文字）

---

## 6. API 規則

すべてのサーバ通信は ACS の `AdlaireClient` インターフェース経由とする。サーバ側 API は ASS が一元管理する。

- 通信プロトコル: HTTP/HTTPS
- データ形式: JSON
- 認証: すべての API リクエストに認証を要求（公開 API を除く）

---

## 7. インポート規則

### 7.1 PHP

名前空間プレフィックス → 物理ファイルの直接マッピング。PSR-4 非準拠（1 ファイル = 複数クラスのため）。

### 7.2 TypeScript

- named import のみ。デフォルトエクスポート禁止
- 相対パス参照。拡張子 `.ts` を必ず含める
- re-export は重複クラス統合時に使用。統合元が正
- フレームワーク間の import は ACS からのみ許可（§4 参照）

---

## 8. フレームワーク登録簿

### 8.1 登録済みフレームワーク

| 略称 | 正式名称 | 分類 | 言語 |
|------|---------|------|------|
| APF | Adlaire Platform Foundation | フロントエンド | TypeScript |
| ACE | Adlaire Content Engine | フロントエンド | TypeScript |
| AIS | Adlaire Infrastructure Services | フロントエンド | TypeScript |
| ASG | Adlaire Static Generator | フロントエンド | TypeScript |
| AP | Adlaire Platform (Controllers) | フロントエンド | TypeScript |
| AEB | Adlaire Editor & Blocks | フロントエンド | JavaScript |
| ADS | Adlaire Design System | フロントエンド | CSS |
| ACS | Adlaire Client Services | クライアント | TypeScript |
| ASS | Adlaire Server System | サーバ | PHP |

登録済みフレームワークの削除は禁止。略称の変更は禁止。

### 8.2 エンジンファイル登録簿

| フレームワーク | PHP | TypeScript / JS / CSS |
|--------------|-----|----------------------|
| APF | `APF.Core.php`, `APF.Api.php`, `APF.Utilities.php`, `APF.Interface.php`, `APF.Class.php` | `APF.Core.ts`, `APF.Api.ts`, `APF.Utilities.ts`, `APF.Interface.ts`, `APF.Class.ts` |
| ACE | `ACE.Core.php`, `ACE.Api.php`, `ACE.Utilities.php`, `ACE.Interface.php`, `ACE.Class.php` | `ACE.Core.ts`, `ACE.Api.ts`, `ACE.Utilities.ts`, `ACE.Interface.ts`, `ACE.Class.ts` |
| AIS | `AIS.Core.php`, `AIS.Api.php`, `AIS.Utilities.php`, `AIS.Interface.php`, `AIS.Class.php` | `AIS.Core.ts`, `AIS.Api.ts`, `AIS.Utilities.ts`, `AIS.Interface.ts`, `AIS.Class.ts` |
| ASG | `ASG.Core.php`, `ASG.Api.php`, `ASG.Utilities.php`, `ASG.Interface.php`, `ASG.Class.php` | `ASG.Core.ts`, `ASG.Api.ts`, `ASG.Utilities.ts`, `ASG.Interface.ts`, `ASG.Class.ts` |
| AP | `AP.Core.php`, `AP.Api.php`, `AP.Utilities.php`, `AP.Interface.php`, `AP.Class.php` | `AP.Core.ts`, `AP.Api.ts`, `AP.Utilities.ts`, `AP.Interface.ts`, `AP.Class.ts` |
| AEB | — | `AEB.Core.js`, `AEB.Api.js`, `AEB.Utils.js`, `AEB.Interface.js`, `AEB.Class.js` |
| ADS | — | `ADS.Core.css`, `ADS.Api.css`, `ADS.Utils.css`, `ADS.Interface.css`, `ADS.Class.css` |
| ACS | — | `ACS.Core.ts`, `ACS.Api.ts`, `ACS.Utilities.ts`, `ACS.Interface.ts`, `ACS.Class.ts` |
| ASS | `ASS.Core.php`, `ASS.Api.php`, `ASS.Utilities.php`, `ASS.Interface.php`, `ASS.Class.php` | — |

登録済みエンジンファイルの削除は禁止。

### 8.3 予約済み略称

AP, APF, ACE, AIS, ASG, AEB, ADS, ACS, ASS — 使用禁止。
