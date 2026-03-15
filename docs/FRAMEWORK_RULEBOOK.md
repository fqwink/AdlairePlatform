# AdlairePlatform フレームワークルールブック

---

**ファイル名**: `docs/FRAMEWORK_RULEBOOK.md`
**バージョン**: Ver.1.0-1
**作成日**: 2026-03-15
**最終更新日**: 2026-03-15
**ステータス**: 🔧 策定中
**分類**: 内部専用
**所有者**: Adlaire Group

---

## 目次

1. [§1 本ルールブックの目的](#1-本ルールブックの目的)
2. [§2 フレームワーク基本方針](#2-フレームワーク基本方針)
3. [§3 アーキテクチャ分類](#3-アーキテクチャ分類)
4. [§4 エンジン駆動モデル規則](#4-エンジン駆動モデル規則)
5. [§5 命名規則](#5-命名規則)
6. [§6 フレームワーク登録簿](#6-フレームワーク登録簿)
7. [§7 ファイル操作制限・削除禁止事項](#7-ファイル操作制限削除禁止事項)
8. [§8 API フレームワーク規則](#8-api-フレームワーク規則)
9. [§9 オートローダー規則](#9-オートローダー規則)
10. [§10 フレームワーク新設・廃止手順](#10-フレームワーク新設廃止手順)

---

## §1 本ルールブックの目的

本ルールブックは、AdlairePlatform におけるフレームワークの設計・開発・運用に関する統一規則を定めます。

すべてのフレームワーク開発者は、本ルールブックに準拠しなければなりません。

---

## §2 フレームワーク基本方針

### §2.1 ヘッドレスフレームワーク

AdlairePlatform のフレームワーク群は**ヘッドレスフレームワーク**として位置づけられます。

| 方針 | 内容 | ステータス |
|------|------|-----------|
| アーキテクチャ | ヘッドレス（フロントエンドとバックエンドの完全分離） | ✅ 確定 |
| 通信方式 | フロントエンド ↔ バックエンド間の通信はすべて API 経由 | ✅ 確定 |
| レンダリング責務 | バックエンドはデータ提供に専念、描画はフロントエンド側で実行 | ✅ 確定 |
| 結合度 | フロントエンドとバックエンドは疎結合 | ✅ 確定 |

### §2.2 エンジン駆動モデル

| 方針 | 内容 | ステータス |
|------|------|-----------|
| 設計モデル | すべてのフレームワークはエンジン駆動モデルを採用する | ✅ 確定 |
| ファイル構成 | 原則 **3 ファイル構成**（Core / Components / Utils） | ✅ 確定 |
| ファイル内設計 | 1 ファイルに複数クラスを格納（単一ファイルモジュール） | ✅ 確定 |
| 依存性 | モジュール間の依存性を最小化、疎結合を維持 | ✅ 確定 |
| 外部依存 | 外部ライブラリへの依存ゼロを原則とする | ✅ 確定 |

### §2.3 言語要件

| 言語 | 最低バージョン | ステータス |
|------|--------------|-----------|
| PHP | 8.3 以上（8.2 以前は非対応） | ✅ 確定 |
| JavaScript | ES6+ (ES2015+) | ✅ 確定 |
| CSS | CSS3 (Custom Properties, Grid, Flexbox) | ✅ 確定 |

---

## §3 アーキテクチャ分類

フレームワークは以下の **3 分類**に区分されます。

### §3.1 分類定義

```
┌─────────────────────────────────────────────────────┐
│                  AdlairePlatform                    │
│                                                     │
│  ┌──────────────┐    ┌──────────┐    ┌───────────┐  │
│  │  フロントエンド │◄──►│  API 層  │◄──►│ バックエンド │  │
│  │  (Frontend)   │    │(API FW)  │    │ (Backend)  │  │
│  └──────────────┘    └──────────┘    └───────────┘  │
│                                                     │
│  ・AEB            ・ACE (Api)       ・APF            │
│  ・ADS                              ・ACE (Core/Admin)│
│                                     ・AIS            │
│                                     ・ASG            │
│                                     ・AP             │
└─────────────────────────────────────────────────────┘
```

### §3.2 分類一覧

| 分類 | 役割 | 対象フレームワーク |
|------|------|------------------|
| **フロントエンド** | UI 描画・ユーザー操作・ブラウザ側処理 | AEB, ADS |
| **バックエンド** | ビジネスロジック・データ処理・サーバー側処理 | APF, ACE, AIS, ASG, AP |
| **API 層（リクエスト通信）** | フロントエンドとバックエンド間の通信仲介 | ACE (Api モジュール) |

### §3.3 分類規則

| 規則 | 内容 | ステータス |
|------|------|-----------|
| 通信経路 | フロントエンド ↔ バックエンドの通信は**必ず API 層を経由**する | ✅ 確定 |
| 直接参照禁止 | フロントエンドからバックエンドのクラス・関数を直接呼び出すことは禁止 | ❌ 禁止 |
| API 統一 | すべてのリクエスト通信は API フレームワーク経由で統一する | ✅ 確定 |
| フロントエンドの独立性 | フロントエンド FW は PHP に依存しない（JS/CSS のみ） | ✅ 確定 |
| バックエンドの独立性 | バックエンド FW はブラウザ API に依存しない | ✅ 確定 |

---

## §4 エンジン駆動モデル規則

### §4.1 ファイル構成原則

すべてのフレームワークは原則 **3 ファイル構成**とします。

| エンジン | 役割 | 必須 |
|---------|------|------|
| **Engine 1** — Core / Base | 基本機能・中核クラス | ✅ 必須 |
| **Engine 2** — Components / Blocks | コンポーネント・機能部品 | ✅ 必須 |
| **Engine 3** — Utils / Specialized | ユーティリティ・補助機能 | ✅ 必須 |

### §4.2 例外規定

| 条件 | 許容内容 | 承認 |
|------|---------|------|
| 構成の拡張（4 ファイル以上） | 明確な分離理由がある場合のみ許容 | Adlaire Group 承認必須 |
| 構成の縮小（1〜2 ファイル） | 単一責務のフレームワークに限り許容 | Adlaire Group 承認必須 |

**現行例外:**

| フレームワーク | 実際の構成 | 例外理由 |
|--------------|-----------|---------|
| APF | 4 ファイル（APF.Middleware.php 追加） | Middleware 層の明確な分離 |
| AP | 1 ファイル（AP.Controllers.php） | コントローラー統合による単一責務 |

### §4.3 エンジン設計原則

| 原則 | 内容 |
|------|------|
| 独立性 | 各エンジンファイルは独立して機能する |
| 疎結合 | エンジン間の依存を最小限にする |
| 単一名前空間 | 1 エンジンファイルにつき 1 名前空間 |
| 自己完結 | 外部ライブラリ不要で動作する |

---

## §5 命名規則

### §5.1 フレームワーク略称

| 規則 | 内容 | ステータス |
|------|------|-----------|
| 文字数 | **3 文字**（固定） | ✅ 確定 |
| 構成 | 正式名称の頭文字に基づく英大文字 | ✅ 確定 |
| 一意性 | 略称は全フレームワーク間で一意でなければならない | ✅ 確定 |
| 予約 | 登録済み略称は他フレームワークで使用禁止 | ❌ 禁止 |

#### 略称の命名方法

正式名称（英語）の単語頭文字を組み合わせて **3 文字**とする。

```
例:
  Adlaire Platform Foundation  → APF
  Adlaire Editor & Blocks      → AEB
  Adlaire Design System         → ADS
  Adlaire Content Engine        → ACE
  Adlaire Infrastructure Services → AIS
  Adlaire Static Generator      → ASG
  Adlaire Platform (Controllers) → AP（2文字例外: §5.1.1 参照）
```

#### §5.1.1 2 文字略称の例外

| 条件 | 内容 |
|------|------|
| 適用 | プラットフォーム本体のコア機能に限定 |
| 現行 | `AP`（Adlaire Platform） のみ |
| 新設 | 原則禁止。3 文字略称を使用すること |

### §5.2 ファイル命名規則

| 規則 | 形式 | 例 |
|------|------|-----|
| エンジンファイル | `{略称}.{エンジン名}.{拡張子}` | `APF.Core.php`, `AEB.Blocks.js` |
| ケース | PascalCase | `APF.Database.php` ✅ / `apf.database.php` ❌ |
| 拡張子 | 言語に応じて `.php` / `.js` / `.css` | — |

### §5.3 名前空間規則（PHP）

| 規則 | 形式 | 例 |
|------|------|-----|
| 構造 | `{略称}\{エンジン名}\` | `APF\Core\`, `ACE\Admin\` |
| クラス参照 | `{略称}\{エンジン名}\{クラス名}` | `APF\Core\Container` |

### §5.4 ディレクトリ命名規則

| 規則 | 形式 | 例 |
|------|------|-----|
| Framework 配下 | `Framework/{略称}/` | `Framework/APF/`, `Framework/AEB/` |
| ケース | 略称はすべて大文字 | `Framework/APF/` ✅ / `Framework/apf/` ❌ |

---

## §6 フレームワーク登録簿

### §6.1 登録済みフレームワーク一覧

| 略称 | 正式名称 | 分類 | 言語 | 状態 | 削除 |
|------|---------|------|------|------|------|
| **AP** | Adlaire Platform (Controllers) | バックエンド | PHP 8.3+ | 実装済み | 🚫 禁止 |
| **APF** | Adlaire Platform Foundation | バックエンド | PHP 8.3+ | 実装済み | 🚫 禁止 |
| **ACE** | Adlaire Content Engine | バックエンド / API 層 | PHP 8.3+ | 実装済み | 🚫 禁止 |
| **AIS** | Adlaire Infrastructure Services | バックエンド | PHP 8.3+ | 実装済み | 🚫 禁止 |
| **ASG** | Adlaire Static Generator | バックエンド | PHP 8.3+ | 実装済み | 🚫 禁止 |
| **AEB** | Adlaire Editor & Blocks | フロントエンド | JS ES6+ | 実装済み | 🚫 禁止 |
| **ADS** | Adlaire Design System | フロントエンド | CSS3 | 実装済み | 🚫 禁止 |

### §6.2 予約済み略称

以下の略称は登録済みまたは将来予約されており、新規フレームワークでの使用を禁止します。

| 略称 | 状態 |
|------|------|
| AP, APF, ACE, AIS, ASG, AEB, ADS | 登録済み — 使用禁止 |

---

## §7 ファイル操作制限・削除禁止事項

### §7.1 削除禁止対象

以下のファイル・ディレクトリは**永続的に削除を禁止**します。

#### フレームワークディレクトリ（削除禁止）

| 対象 | 削除 | 理由 |
|------|------|------|
| `Framework/` ディレクトリ | 🚫 **永続禁止** | フレームワーク格納ルート |
| `Framework/AP/` | 🚫 **永続禁止** | Platform Controllers |
| `Framework/APF/` | 🚫 **永続禁止** | Platform Foundation |
| `Framework/ACE/` | 🚫 **永続禁止** | Content Engine |
| `Framework/AIS/` | 🚫 **永続禁止** | Infrastructure Services |
| `Framework/ASG/` | 🚫 **永続禁止** | Static Generator |
| `Framework/AEB/` | 🚫 **永続禁止** | Editor & Blocks |
| `Framework/ADS/` | 🚫 **永続禁止** | Design System |

#### エンジンファイル（削除禁止）

| ファイル | 削除 |
|---------|------|
| `Framework/AP/AP.Controllers.php` | 🚫 禁止 |
| `Framework/APF/APF.Core.php` | 🚫 禁止 |
| `Framework/APF/APF.Middleware.php` | 🚫 禁止 |
| `Framework/APF/APF.Database.php` | 🚫 禁止 |
| `Framework/APF/APF.Utilities.php` | 🚫 禁止 |
| `Framework/ACE/ACE.Core.php` | 🚫 禁止 |
| `Framework/ACE/ACE.Admin.php` | 🚫 禁止 |
| `Framework/ACE/ACE.Api.php` | 🚫 禁止 |
| `Framework/AIS/AIS.Core.php` | 🚫 禁止 |
| `Framework/AIS/AIS.System.php` | 🚫 禁止 |
| `Framework/AIS/AIS.Deployment.php` | 🚫 禁止 |
| `Framework/ASG/ASG.Core.php` | 🚫 禁止 |
| `Framework/ASG/ASG.Template.php` | 🚫 禁止 |
| `Framework/ASG/ASG.Utilities.php` | 🚫 禁止 |
| `Framework/AEB/AEB.Core.js` | 🚫 禁止 |
| `Framework/AEB/AEB.Blocks.js` | 🚫 禁止 |
| `Framework/AEB/AEB.Utils.js` | 🚫 禁止 |
| `Framework/ADS/ADS.Base.css` | 🚫 禁止 |
| `Framework/ADS/ADS.Components.css` | 🚫 禁止 |
| `Framework/ADS/ADS.Editor.css` | 🚫 禁止 |

#### インフラファイル（削除禁止）

| ファイル | 削除 | 理由 |
|---------|------|------|
| `autoload.php` | 🚫 **永続禁止** | フレームワーク名前空間オートローダー |
| `bootstrap.php` | 🚫 **永続禁止** | アプリケーション初期化 |
| `docs/FRAMEWORK_RULEBOOK.md` | 🚫 **永続禁止** | 本ルールブック |
| `Framework/README.md` | 🚫 **永続禁止** | フレームワーク説明書 |

### §7.2 禁止操作一覧

| 操作 | ステータス | 理由 |
|------|-----------|------|
| 登録済みフレームワークディレクトリの削除 | ❌ 禁止 | フレームワーク破壊防止 |
| 登録済みエンジンファイルの削除 | ❌ 禁止 | エンジン駆動モデルの維持 |
| `autoload.php` の削除 | ❌ 禁止 | 名前空間解決が不能になる |
| `bootstrap.php` の削除 | ❌ 禁止 | アプリケーション起動不能 |
| フレームワーク略称の変更 | ❌ 禁止 | 名前空間・参照の破壊 |
| エンジンファイルの分割（承認なし） | ❌ 禁止 | エンジン駆動モデル違反 |
| 名前空間の無断変更 | ❌ 禁止 | オートローダー・依存の破壊 |
| 外部ライブラリの追加（承認なし） | ❌ 禁止 | 依存ゼロ原則違反 |

### §7.3 許可操作

| 操作 | ステータス | 条件 |
|------|-----------|------|
| エンジンファイル内のクラス追加・変更 | ✅ 許可 | 名前空間を維持すること |
| 新規フレームワークの追加 | ✅ 許可 | §10 の手順に従うこと |
| エンジンファイルへの機能追加 | ✅ 許可 | 既存インターフェースを破壊しないこと |
| フレームワークドキュメントの更新 | ✅ 許可 | DOCUMENT_CHANGELOG に記録すること |

---

## §8 API フレームワーク規則

### §8.1 API 層の役割

API 層は、フロントエンドとバックエンド間の**唯一の通信経路**です。

```
[フロントエンド]                    [バックエンド]
  AEB (JS)  ──── HTTP Request ────►  ACE.Api (ApiRouter)
  ADS (CSS)  ◄── HTTP Response ───  ──► ACE.Core / AIS / ASG / APF
```

### §8.2 API 通信規則

| 規則 | 内容 | ステータス |
|------|------|-----------|
| 通信プロトコル | HTTP/HTTPS | ✅ 確定 |
| データ形式 | JSON（リクエスト・レスポンスともに） | ✅ 確定 |
| API ルーティング | ACE.Api（ApiRouter）が一元管理 | ✅ 確定 |
| 認証 | すべての API リクエストに認証を要求（公開 API を除く） | ✅ 確定 |
| バージョニング | API エンドポイントにバージョンプレフィックスを付与 | ✅ 確定 |

### §8.3 禁止事項

| 禁止事項 | 理由 |
|---------|------|
| フロントエンドから PHP クラスの直接呼び出し | ヘッドレス原則違反 |
| API 層を経由しないデータ取得 | 通信経路の統一性破壊 |
| バックエンドからブラウザ DOM の直接操作 | 責務分離違反 |
| ACE.Api を経由しない独自 API エンドポイントの作成 | API 一元管理違反 |

---

## §9 オートローダー規則

### §9.1 基本方針

| 方針 | 内容 | ステータス |
|------|------|-----------|
| 方式 | 名前空間プレフィックス → 物理ファイルの直接マッピング | ✅ 確定 |
| PSR-4 | 非準拠（1 ファイル = 複数クラスのため） | ✅ 確定 |
| 二重読み込み防止 | `$loaded` 配列で require 済みファイルを追跡 | ✅ 確定 |

### §9.2 マッピング登録規則

| 規則 | 内容 |
|------|------|
| 新規フレームワーク追加時 | `autoload.php` の `$map` に名前空間プレフィックスを追加する |
| フレームワーク廃止時 | `$map` からエントリを削除する（§10 の手順に従う） |
| コメント | 各フレームワークのエントリにはコメントヘッダーを付ける |
| 順序 | フレームワーク略称のアルファベット順（AP を除く） |

### §9.3 現行マッピング

```php
static $map = [
    /* APF - Adlaire Platform Foundation */
    'APF\\Core\\'       => 'Framework/APF/APF.Core.php',
    'APF\\Middleware\\'  => 'Framework/APF/APF.Middleware.php',
    'APF\\Database\\'   => 'Framework/APF/APF.Database.php',
    'APF\\Utilities\\'  => 'Framework/APF/APF.Utilities.php',

    /* ACE - Adlaire Content Engine */
    'ACE\\Core\\'       => 'Framework/ACE/ACE.Core.php',
    'ACE\\Admin\\'      => 'Framework/ACE/ACE.Admin.php',
    'ACE\\Api\\'        => 'Framework/ACE/ACE.Api.php',

    /* AIS - Adlaire Infrastructure Services */
    'AIS\\Core\\'       => 'Framework/AIS/AIS.Core.php',
    'AIS\\System\\'     => 'Framework/AIS/AIS.System.php',
    'AIS\\Deployment\\' => 'Framework/AIS/AIS.Deployment.php',

    /* ASG - Adlaire Static Generator */
    'ASG\\Core\\'       => 'Framework/ASG/ASG.Core.php',
    'ASG\\Template\\'   => 'Framework/ASG/ASG.Template.php',
    'ASG\\Utilities\\'  => 'Framework/ASG/ASG.Utilities.php',

    /* AP - Adlaire Platform Controllers */
    'AP\\Controllers\\' => 'Framework/AP/AP.Controllers.php',
];
```

---

## §10 フレームワーク新設・廃止手順

### §10.1 新設手順

新規フレームワークを追加する場合、以下の手順に従います。

1. **略称の決定** — 正式名称から 3 文字の略称を決定する（§5.1 準拠）
2. **分類の決定** — フロントエンド / バックエンド / API 層のいずれかに分類する（§3 準拠）
3. **Adlaire Group 承認** — 新設の承認を取得する
4. **ディレクトリ作成** — `Framework/{略称}/` を作成する
5. **エンジンファイル作成** — 3 ファイル構成でエンジンを実装する（§4 準拠）
6. **オートローダー登録** — `autoload.php` に名前空間マッピングを追加する（PHP の場合）
7. **登録簿更新** — 本ルールブック §6 の登録簿に追加する
8. **ドキュメント更新** — `Framework/README.md` および関連ドキュメントを更新する
9. **変更履歴記録** — `DOCUMENT_CHANGELOG.md` に記録する

### §10.2 廃止手順

フレームワークを廃止する場合、以下の手順に従います。

> ⚠️ 登録済みフレームワークの廃止には **Adlaire Group の承認が必須**です。

1. **依存関係調査** — 廃止対象に依存するコード・ドキュメントをすべて特定する
2. **Adlaire Group 承認** — 廃止の承認を取得する
3. **廃止予定の告知** — 登録簿の状態を「廃止予定」に変更し、終了日を明記する
4. **依存の移行** — すべての依存コードを移行・代替実装に置き換える
5. **オートローダー削除** — `autoload.php` からマッピングを削除する
6. **ファイル削除** — エンジンファイルおよびディレクトリを削除する
7. **登録簿更新** — 本ルールブック §6 の登録簿を更新する（状態を「廃止」に変更）
8. **ドキュメント更新** — `Framework/README.md` および関連ドキュメントを更新する
9. **変更履歴記録** — `DOCUMENT_CHANGELOG.md` に記録する

---

## 関連ドキュメント

- [Framework/README.md](../Framework/README.md) — フレームワーク技術詳細
- [docs/ARCHITECTURE.md](./ARCHITECTURE.md) — アーキテクチャ設計
- [docs/DOC_RULEBOOK.md](./DOC_RULEBOOK.md) — ドキュメント管理ルールブック
- [docs/VERSIONING.md](./VERSIONING.md) — バージョニング規則
- [docs/DOCUMENT_CHANGELOG.md](./DOCUMENT_CHANGELOG.md) — 変更履歴

---

## 📝 変更履歴

本ドキュメントの変更履歴は、以下のファイルで一元管理されています:

👉 **[docs/DOCUMENT_CHANGELOG.md](./DOCUMENT_CHANGELOG.md)** を参照してください。

---

*本ドキュメントは Adlaire Group の所有であり、承認なく変更・再配布することを禁じます。*

*ライセンス全文: [Adlaire License Ver.2.0](./Licenses/LICENSE_Ver.2.0.md)*
