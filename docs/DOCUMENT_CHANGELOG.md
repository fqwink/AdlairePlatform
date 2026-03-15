# AdlairePlatform ドキュメント変更履歴

---

**ファイル名**: `docs/DOCUMENT_CHANGELOG.md`  
**バージョン**: Ver.1.2-3  
**作成日**: 2026-03-10  
**最終更新**: 2026-03-10  
**ステータス**: 🔧 運用開始  
**分類**: 内部専用  
**所有者**: Adlaire Group  
**目的**: AdlairePlatform 全ドキュメントの変更履歴を一元管理

---

## 📋 概要

本ファイルは、AdlairePlatform プロジェクトのすべてのドキュメントの変更履歴を一元管理します。

各ドキュメントファイルには変更履歴テーブルを記載せず、本ファイルへの参照リンクのみを記載します。

---

## 📝 管理対象ドキュメント

| ファイル | 役割 | 削除 |
|---------|------|------|
| `docs/AdlairePlatform_Design.md` | 基本設計・設計方針 | 🚫 禁止 |
| `docs/ARCHITECTURE.md` | アーキテクチャ設計 | 🚫 禁止 |
| `docs/DETAILED_DESIGN.md` | 詳細設計書（実装レベルの技術仕様） | 🚫 禁止 |
| `docs/SECURITY_POLICY.md` | セキュリティ方針（機密） | 🚫 禁止 |
| `docs/VERSIONING.md` | バージョニング規則 | 🚫 禁止 |
| `docs/DOC_RULEBOOK.md` | ドキュメント管理ルールブック | 🚫 禁止 |
| `docs/FRAMEWORK_RULEBOOK.md` | フレームワーク管理ルールブック | 🚫 禁止 |

---

## 📖 変更履歴の記載ルール

1. **新しい変更履歴はテーブルの最上部に追加**
2. **日付は `YYYY-MM-DD` 形式で統一**
3. **変更内容は簡潔に1行で記述** (必要に応じて複数項目を列挙)
4. **変更者は GitHub アカウント名を記載**
5. **バージョン番号は `VERSIONING.md` の累積ルールに準拠**
6. **バージョン番号は各ドキュメントのメタデータブロックと一致させる**

---

## 📄 AdlairePlatform_Design.md

| バージョン | 更新日 | 変更内容 | 変更者 |
|-----------|--------|----------|--------|
| Ver.0.6-3 | 2026-03-15 | Ver.2.0 移行: PHP 8.3+ 必須化、バージョン計画にVer.2.0追加、PHP対応仕様を8.3+に更新 | Claude |
| Ver.0.5-1 | 2026-03-10 | 5文書構成整理、設計方針・設計思想を統合、詳細仕様を SPECIFICATION.md に分離、約80%削減、相互参照リンク追加 | fqwink |
| Ver.0.4-1 | 2026-03-10 | SPEC.md 統合、Ver.1.4-pre エンジン追加（AppContext・Logger・MailerEngine） | fqwink |
| Ver.0.3-1 | 2026-03-09 | Ver.1.3 系完了記録、バージョン計画更新 | fqwink |
| Ver.0.2-1 | 2026-03-08 | バージョン履歴整理、実装タスクリスト更新 | fqwink |
| Ver.0.1-1 | 2026-03-06 | 初版作成 | fqwink |

---

## 📄 ARCHITECTURE.md

| バージョン | 更新日 | 変更内容 | 変更者 |
|-----------|--------|----------|--------|
| Ver.1.5-2 | 2026-03-15 | Ver.2.0 移行: ディレクトリ構成に Framework/AP/ 追加、controllers/ 廃止を反映 | Claude |
| Ver.1.4-1 | 2026-03-10 | 5文書構成整理、設計方針を AdlairePlatform_Design.md に移動、セキュリティ節を SECURITY_POLICY.md に移動、相互参照リンク追加、目次番号を1-7に統一 | fqwink |
| Ver.1.3-1 | 2026-03-09 | Ver.1.3 系エンジン15個完成、AppContext・Logger・MailerEngine 追加、ディレクトリ構造更新 | fqwink |
| Ver.1.2-1 | 2026-03-08 | エンジン分離後のアーキテクチャ記載、リクエストフロー図追加 | fqwink |

---

## 📄 SPECIFICATION.md（削除済み）

| バージョン | 更新日 | 変更内容 | 変更者 |
|-----------|--------|----------|--------|
| — | 2026-03-14 | **削除** — features.md および DETAILED_DESIGN.md と内容が重複のため | Claude |
| Ver.1.0-1 | 2026-03-10 | 新規作成。AdlairePlatform_Design.md のセクション5-13（機能仕様）を統合 | fqwink |

---

## 📄 SECURITY_POLICY.md

| バージョン | 更新日 | 変更内容 | 変更者 |
|-----------|--------|----------|--------|
| Ver.1.0-1 | 2026-03-10 | 新規作成。AdlairePlatform_Design.md と ARCHITECTURE.md のセキュリティ関連セクションを統合 | fqwink |

---

## 📄 ENGINE_DESIGN.md（削除済み）

| バージョン | 更新日 | 変更内容 | 変更者 |
|-----------|--------|----------|--------|
| — | 2026-03-14 | **削除** — SOURCE_CODE_ANALYSIS_JP.md に内容が統合済みのため | Claude |
| Ver.1.0-3 | 2026-03-12 | AdminEngine, TemplateEngine, ThemeEngine, UpdateEngine, WebhookEngine, CacheEngine, ImageOptimizer, AppContext, Logger, MailerEngine の設計書を追加（ソースコード解析に基づく）、統合エンジン数を7→17に拡大 | fqwink |
| Ver.1.0-2 | 2026-03-10 | ENGINE_DESIGN_INTEGRATED.md からリネーム、タイトルを「エンジン技術設計書リファレンス」に変更、相互参照リンク追加 | fqwink |
| Ver.1.0-1 | 2026-03-08 | ENGINE_DESIGN_INTEGRATED.md として初版作成、StaticEngine・ApiEngine・DiagnosticEngine・Git連携エンジン群の設計書を統合 | fqwink |

---

## 📄 VERSIONING.md

| バージョン | 更新日 | 変更内容 | 変更者 |
|-----------|--------|----------|--------|
| Ver.0.1-1 | 2026-02-28 | 初版作成。累積バージョニング規則策定、リセット禁止ルール明記 | fqwink |

---

## 📄 DOC_RULEBOOK.md

| バージョン | 更新日 | 変更内容 | 変更者 |
|-----------|--------|----------|--------|
| Ver.1.3-2 | 2026-03-15 | §1 マスター表に FRAMEWORK_RULEBOOK.md を追加 | Claude |
| Ver.1.2-1 | 2026-03-10 | §2.5 ステータス管理ルールを新設、§2.1 メタデータブロックを更新（所有者フィールド削除、ステータス詳細化） | fqwink |
| Ver.1.1-1 | 2026-03-10 | DOCUMENT_CHANGELOG.md 一元管理方式を導入、各ドキュメントの変更履歴テーブル記載禁止ルール化、マスター表に DOCUMENT_CHANGELOG.md 追加 | fqwink |
| Ver.1.0-2 | 2026-03-10 | SPECIFICATION.md・SECURITY_POLICY.md 追加、ENGINE_DESIGN.md リネーム反映、ファイル操作制限リスト更新 | fqwink |
| Ver.1.0-1 | 2026-03-08 | 初版作成。ドキュメント管理ルール策定、バージョン管理ルール記載 | fqwink |

---

## 📄 FRAMEWORK_RULEBOOK.md

| バージョン | 更新日 | 変更内容 | 変更者 |
|-----------|--------|----------|--------|
| Ver.1.0-1 | 2026-03-15 | 初版作成。ヘッドレスFW方針、3分類アーキテクチャ、エンジン駆動モデル規則、命名規則、削除禁止事項、API通信規則、オートローダー規則、新設・廃止手順を策定 | Claude |

---

## 🔄 本ファイルの変更履歴

| バージョン | 更新日 | 変更内容 | 変更者 |
|-----------|--------|----------|--------|
| Ver.1.2-3 | 2026-03-15 | FRAMEWORK_RULEBOOK.md 新設に伴い管理対象追加、変更履歴セクション追加 | Claude |
| Ver.1.1-2 | 2026-03-15 | Ver.2.0 ドキュメント再整備の変更履歴を追加 | Claude |
| Ver.1.0-1 | 2026-03-10 | 初版作成。全ドキュメントの変更履歴を一元管理開始 | fqwink |

---

*本ドキュメントは Adlaire Group の所有であり、承認なく変更・再配布することを禁じます。*

*ライセンス全文: [Adlaire License Ver.2.0](../Licenses/LICENSE_Ver.2.0.md)*
