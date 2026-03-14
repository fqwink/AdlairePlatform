# Adlaire Editor Framework (AEF) & Adlaire CSS Framework (ACF)

**モダンでモジュラーなWYSIWYGエディタ & CSSフレームワーク**

---

## 📦 プロジェクト構成

```
framework/
├── editor/ (AEF - Adlaire Editor Framework)
│   ├── core/          # コアシステム
│   ├── blocks/        # ブロックタイプ
│   ├── tools/         # ツール（ツールバー、コマンド等）
│   ├── utils/         # ユーティリティ
│   ├── plugins/       # プラグインシステム
│   └── types/         # TypeScript型定義
├── css/ (ACF - Adlaire CSS Framework)
│   ├── base/          # 基礎スタイル
│   ├── components/    # コンポーネント
│   ├── layout/        # レイアウト
│   ├── editor/        # エディタ専用スタイル
│   └── themes/        # テーマ
└── docs/              # ドキュメント
```

---

## 🎯 AEF (Adlaire Editor Framework)

### 特徴

- ✅ **ブロックベース**: Editor.jsスタイルのブロックエディタ
- ✅ **モジュラー設計**: 各ブロック・ツールが独立
- ✅ **プラグインシステム**: カスタムブロック・ツールの追加が容易
- ✅ **TypeScript対応**: 型定義ファイル付属
- ✅ **軽量**: 外部依存なし、Vanilla JavaScript
- ✅ **アクセシビリティ**: ARIA対応

### 実装済みブロック（予定）

- [ ] Paragraph（段落）
- [ ] Heading（見出し h2-h6）
- [ ] List（リスト ul/ol）
- [ ] Code（コードブロック）
- [ ] Quote（引用）
- [ ] Table（テーブル）
- [ ] Image（画像）
- [ ] Checklist（チェックリスト）
- [ ] Delimiter（区切り線）

### 使用例

```javascript
import { AEFEditor } from './framework/editor/core/Editor.js';

const editor = new AEFEditor('#editor', {
    autosave: true,
    autosaveInterval: 30000,
    placeholder: '/ を入力してコマンド...',
    onSave: async (data) => {
        // 保存処理
        await fetch('/api/save', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
});

// データ取得
const data = editor.getData();

// データ設定
editor.setData({
    blocks: [
        { type: 'paragraph', data: { text: 'Hello World!' } }
    ]
});
```

---

## 🎨 ACF (Adlaire CSS Framework)

### 特徴

- ✅ **CSS変数ベース**: カスタマイズ容易
- ✅ **ダークテーマデフォルト**: ライトテーマも対応
- ✅ **レスポンシブ**: モバイルファースト
- ✅ **軽量**: 最小限のスタイル
- ✅ **ユーティリティクラス**: Tailwind CSS風

### CSS変数

```css
:root {
    --acf-primary: #0ad;
    --acf-bg-primary: #1a1a1a;
    --acf-text-primary: #eee;
    --acf-space-md: 1rem;
    --acf-radius-md: 6px;
    /* ... */
}
```

### 使用例

```html
<!-- ボタン -->
<button class="acf-btn acf-btn-primary">
    保存
</button>

<!-- カード -->
<div class="acf-card acf-shadow acf-p-3">
    <h3 class="acf-text-lg">タイトル</h3>
    <p class="acf-text-secondary">説明文</p>
</div>

<!-- グリッド -->
<div class="acf-grid acf-gap-3">
    <div class="acf-col-6">左</div>
    <div class="acf-col-6">右</div>
</div>
```

---

## 📊 ステータス

### 実装状況

| コンポーネント | ステータス | 進捗 |
|------------|----------|------|
| **AEF Core** | 🔜 準備中 | 0% |
| **AEF Blocks** | 🔜 準備中 | 0% |
| **AEF Tools** | 🔜 準備中 | 0% |
| **ACF Base** | 🔜 準備中 | 0% |
| **ACF Components** | 🔜 準備中 | 0% |
| **ACF Editor** | 🔜 準備中 | 0% |

### 実装計画

**Phase 1** (4週間, 48h) - 基盤構築
- AEF コア実装
- ACF 基盤実装
- リリース予定: 2026-04-11

**Phase 2** (3週間, 36h) - 機能拡張
- AEF ブロック拡張
- ACF コンポーネント
- リリース予定: 2026-05-02

**Phase 3** (2週間, 24h) - 統合・テスト
- 統合
- テスト・ドキュメント
- リリース予定: 2026-05-16

---

## 📚 ドキュメント

- [設計書](./docs/EDITOR_CSS_FRAMEWORK_DESIGN.md) - 詳細設計とAPI仕様
- [改良提案書](./docs/WYSIWYG_EDITOR_IMPROVEMENT_PROPOSAL.md) - 背景と課題分析

---

## 🛠️ 開発

### 必要環境

- Node.js 18+ (開発用)
- 外部依存なし（実行時）

### セットアップ

```bash
# リポジトリクローン
git clone https://github.com/fqwink/AdlairePlatform.git
cd AdlairePlatform

# フレームワークディレクトリへ移動
cd framework
```

### ビルド（準備中）

```bash
npm run build
```

### テスト（準備中）

```bash
npm test
```

---

## 📝 ライセンス

Adlaire License Ver.2.0

---

## 🤝 コントリビューション

プルリクエスト歓迎！

1. このリポジトリをフォーク
2. フィーチャーブランチ作成 (`git checkout -b feature/AmazingFeature`)
3. コミット (`git commit -m 'Add some AmazingFeature'`)
4. プッシュ (`git push origin feature/AmazingFeature`)
5. プルリクエスト作成

---

**作成日**: 2026年3月14日  
**バージョン**: 0.1.0 (開発中)  
**ステータス**: 設計完了、実装開始準備中
