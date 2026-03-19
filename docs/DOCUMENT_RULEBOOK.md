# AdlairePlatform ドキュメントルールブック

Ver.2.0 | 最終更新: 2026-03-16

---

## §1 ドキュメントマスター表

| ファイル | 役割 | 分類 |
|---------|------|------|
| `README.md` | プロジェクトを紹介する | 公開 |
| `RELEASE-NOTES.md` | リリースノートを記載する | 公開 |
| `docs/VERSIONING.md` | バージョニング規則を定める | 社内限定 |
| `docs/DOCUMENT_RULEBOOK.md` | ドキュメント管理ルールを定める（本書） | 社内限定 |
| `docs/AdlairePlatform_DetailedDesign.md` | 詳細設計を記載する（**開発の最上位準拠文書**） | 社内限定 |
| `docs/FRAMEWORK_RULEBOOK_v3.0.md` | フレームワーク規約を定める | 社内限定 |
| `docs/Licenses/LICENSE_Ver.2.0.md` | Adlaire License Ver.2.0 を記載する | 公開 |
| `docs/Licenses/RELEASE-NOTES.md` | ライセンス用リリースノートを記載する | 公開 |

上記以外のドキュメントを追加する場合は、本表も併せて更新すること。

---

## §2 メタデータブロック

管理対象ドキュメント（分類が「社内限定」のもの）の冒頭には、以下の形式でメタデータブロックを記載する。

```markdown
# ドキュメントタイトル

Ver.X.Y-Z | 最終更新: YYYY-MM-DD
```

- バージョン番号は `docs/VERSIONING.md` に定める累積ルールに従って付与する
- メタデータは 1 行で完結させる（複数行のフロントマターブロックは使用してはならない）

---

## §3 変更履歴

- 従来の `DOCUMENT_CHANGELOG.md` による変更履歴の一元管理は廃止された
- 変更履歴は **git log** で管理する
- ドキュメント末尾に変更履歴参照セクションを設ける必要はない

---

## §4 準拠文書の優先順位

開発時に参照するドキュメントの優先順位は以下の通りである。

1. `docs/AdlairePlatform_DetailedDesign.md` — 設計仕様の最上位準拠文書
2. `docs/FRAMEWORK_RULEBOOK_v3.0.md` — フレームワーク規約
3. `docs/VERSIONING.md` — バージョニング規則
4. `docs/DOCUMENT_RULEBOOK.md` — ドキュメント管理ルール（本書）

ドキュメント間に矛盾がある場合は、上位の文書が優先される。

---

## §5 禁止事項

以下の行為を禁止する。

1. `docs/Licenses/` 配下のライセンス文書の改変
2. リビジョン番号のリセット（VERSIONING.md 参照）

---

*本ルールブックは Adlaire Group に帰属し、同グループの承認なく変更または再配布することを禁ずる。*
