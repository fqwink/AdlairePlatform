# AdlairePlatform ドキュメントルールブック

---

## ドキュメント一覧

| ファイル | 削除 | 役割 |
|---------|------|------|
| `README.md` | 可 | プロジェクト概要・導入案内 |
| `CONTRIBUTING.md` | 可 | コントリビューションガイド |
| `RELEASE-NOTES.md` | 可 | リリースノート |
| `docs/AdlairePlatform_Design.md` | **🚫 禁止** | 基本設計・方針に関するドキュメント |
| `docs/ARCHITECTURE.md` | **🚫 禁止** | アーキテクチャに関するドキュメント |
| `docs/VERSIONING.md` | **🚫 禁止** | バージョニング規則に関するドキュメント |
| `docs/features.md` | 可 | 実装・未実装・関数リファレンス・APIリファレンスに関するドキュメント |
| `docs/HEADLESS_CMS.md` | 可 | Headless CMS / REST API 仕様 |
| `docs/HEADLESS_CMS_ROADMAP.md` | 可 | Headless CMS ロードマップ |
| `docs/STATIC_GENERATOR.md` | 可 | 静的サイト生成仕様 |
| `docs/DIAGNOSTIC_ENGINE.md` | 可 | DiagnosticEngine 仕様 |
| `docs/CONFIGURATION.md` | 可 | 設定リファレンスに関するドキュメント |
| `docs/THEME_DEVELOPMENT.md` | 可 | テーマ開発リファレンスに関するドキュメント |
| `docs/nginx.conf.example` | 可 | Nginx 設定サンプル |
| `docs/Licenses/LICENSE_Ver.1.0.md` | 可 | Adlaire License Ver.1.0 |
| `docs/Licenses/LICENSE_Ver.2.0.md` | 可 | Adlaire License Ver.2.0 |

---

## ドキュメント更新時のバージョン管理ルール

### 更新日記載

全ドキュメントのファイル先頭に以下のメタデータブロックを必ず記載する。

```
ファイル名: {ファイル名}
バージョン: Ver.{メジャー}.{マイナー}-{ビルド}
最終更新: YYYY-MM-DD
```

- `最終更新` はファイルを変更したその日付を記載する
- 日付フォーマットは `YYYY-MM-DD` に統一する

---

### 変更履歴

全ドキュメントのファイル末尾に以下の変更履歴テーブルを必ず記載する。

```
## 変更履歴

| バージョン | 更新日 | 変更内容 |
|-----------|--------|----------|
| Ver.x.x-x | YYYY-MM-DD | 初版作成 |
```

- 更新のたびに行を追記する（既存行は削除しない）
- 新しい行を先頭に追加する（降順）
- `変更内容` は簡潔に1行で記載する

---

### バージョン番号の扱い

- ドキュメントのバージョンは `VERSIONING.md` の累積バージョニング規則に従う
- ビルド番号はドキュメント単位で管理し、リセット禁止

---

## ファイル操作制限リスト

| ファイル | 作成 | 削除 |
|---------|------|------|
| `CHANGELOG.md` | **🚫 永久禁止** | **🚫 永久禁止** |
| `docs/AdlairePlatform_Design.md` | — | **🚫 禁止** |
| `docs/ARCHITECTURE.md` | — | **🚫 禁止** |
| `docs/VERSIONING.md` | — | **🚫 禁止** |

- 作成禁止ファイルを誤って作成した場合は強制削除を義務とする
