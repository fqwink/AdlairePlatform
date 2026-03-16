# AdlairePlatform ドキュメントルールブック

Ver.2.0 | 最終更新: 2026-03-16

---

## §1 ドキュメントマスター表

| ファイル | 役割 | 分類 |
|---------|------|------|
| `README.md` | プロジェクト紹介 | 公開 |
| `RELEASE-NOTES.md` | リリースノート | 公開 |
| `docs/VERSIONING.md` | バージョニング規則 | 社内限定 |
| `docs/DOC_RULEBOOK.md` | ドキュメント管理ルール（本書） | 社内限定 |
| `docs/AdlairePlatform_DetailedDesign.md` | 詳細設計（**開発の最上位準拠文書**） | 社内限定 |
| `docs/FRAMEWORK_RULEBOOK_v2.0.md` | フレームワーク規約 | 社内限定 |
| `docs/Licenses/LICENSE_Ver.2.0.md` | Adlaire License Ver.2.0 | 公開 |
| `docs/Licenses/RELEASE-NOTES.md` | ライセンス用リリースノート | 公開 |

上記以外のドキュメントファイルを追加する場合は、本表を更新すること。

---

## §2 メタデータブロック

管理対象ドキュメント（分類「社内限定」）の冒頭に記載する。

```markdown
# ドキュメントタイトル

Ver.X.Y-Z | 最終更新: YYYY-MM-DD
```

- バージョン番号は `docs/VERSIONING.md` の累積ルールに準拠
- 1行で完結させる（複数行のフロントマターブロックは使用しない）

---

## §3 変更履歴

- **DOCUMENT_CHANGELOG.md による一元管理は廃止**
- 変更履歴は **git log** で管理する
- ドキュメント末尾の変更履歴参照セクションは不要

---

## §4 準拠文書の優先順位

開発時に参照するドキュメントの優先順位:

1. `docs/AdlairePlatform_DetailedDesign.md` — 設計仕様の最上位準拠文書
2. `docs/FRAMEWORK_RULEBOOK_v2.0.md` — フレームワーク規約
3. `docs/VERSIONING.md` — バージョニング規則
4. `docs/DOC_RULEBOOK.md` — ドキュメント管理ルール（本書）

ドキュメント間で矛盾がある場合は、上位の文書が優先する。

---

## §5 禁止事項

1. マスター表に記載のないドキュメントを `docs/` に無断追加すること
2. `docs/Licenses/` 配下のライセンス文書を改変すること
3. バージョン番号のリビジョンをリセットすること（VERSIONING.md 参照）

---

*本ルールブックは Adlaire Group の所有であり、承認なく変更・再配布することを禁じます。*
