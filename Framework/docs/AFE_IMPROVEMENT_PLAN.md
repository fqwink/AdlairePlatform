# APF Framework 改良計画書

**Adlaire Platform Foundation Ver.2.1.0 改良提案**

**日付**: 2026年3月13日  
**ステータス**: 改良計画  
**目的**: AFEフレームワークの機能強化とパフォーマンス最適化

---

## 📋 目次

1. [現状分析](#現状分析)
2. [改良項目一覧](#改良項目一覧)
3. [優先順位付け](#優先順位付け)
4. [詳細設計](#詳細設計)
5. [実装計画](#実装計画)

---

## 現状分析

### 現在のAFE構成

```
✅ Framework.php      (~500行) - 統合フレームワーク本体
✅ EngineInterface.php (~70行)  - エンジンインターフェース
✅ BaseEngine.php     (~250行) - 基本エンジン抽象クラス
```

### 強み

- ✅ 3ファイル構成で学習コストが低い
- ✅ 外部依存ゼロ（Pure PHP）
- ✅ DIコンテナ・イベント駆動・依存解決を統合
- ✅ 明確なエンジンライフサイクル

### 改善の余地がある領域

1. **エラーハンドリング**: 例外処理が不十分
2. **パフォーマンス**: キャッシュ機構が未実装
3. **デバッグ**: ロギング・トレース機能が限定的
4. **検証**: バリデーション機構が不足
5. **拡張性**: ミドルウェア・フック機構が未実装
6. **テスト**: テストヘルパーが未提供

---

## 改良項目一覧

### カテゴリA: 安定性・信頼性

#### A1. エラーハンドリング強化
- [ ] カスタム例外クラスの追加
- [ ] エラー境界（Error Boundary）実装
- [ ] グレースフルデグラデーション
- [ ] エラーリカバリー機構

#### A2. バリデーション機構
- [ ] 設定値のバリデーション
- [ ] エンジン登録時のチェック
- [ ] サービス型チェック

#### A3. ロギング強化
- [ ] 構造化ログ対応
- [ ] ログレベル管理
- [ ] コンテキスト情報の自動追加

### カテゴリB: パフォーマンス

#### B1. 遅延ロード（Lazy Loading）
- [ ] サービスの遅延初期化
- [ ] エンジンの条件付きロード
- [ ] イベントリスナーの遅延登録

#### B2. キャッシュ最適化
- [ ] 依存グラフのキャッシュ
- [ ] 設定値のキャッシュ
- [ ] リフレクション結果のキャッシュ

#### B3. メモリ管理
- [ ] 循環参照の検出・解消
- [ ] 不要オブジェクトの早期解放

### カテゴリC: 開発体験

#### C1. デバッグ機能
- [ ] デバッグモード
- [ ] イベントトレース
- [ ] 依存グラフの可視化
- [ ] パフォーマンスプロファイラ

#### C2. CLI ツール
- [ ] エンジン生成コマンド
- [ ] 依存関係チェックコマンド
- [ ] キャッシュクリアコマンド

#### C3. テストヘルパー
- [ ] モックエンジン生成
- [ ] イベントアサーション
- [ ] テスト用DIコンテナ

### カテゴリD: 拡張性

#### D1. ミドルウェアシステム
- [ ] リクエスト/レスポンスミドルウェア
- [ ] エンジンライフサイクルミドルウェア
- [ ] イベントミドルウェア

#### D2. フック機構
- [ ] ビフォア/アフターフック
- [ ] エンジン登録フック
- [ ] 設定変更フック

#### D3. プラグインAPI
- [ ] プラグイン検出
- [ ] プラグイン自動ロード
- [ ] プラグイン依存管理

### カテゴリE: ドキュメント

#### E1. PHPDoc 完全化
- [ ] 全メソッドに型アノテーション
- [ ] 使用例の追加
- [ ] @throws アノテーション

#### E2. APIドキュメント自動生成
- [ ] PHPDocumentor設定
- [ ] オンラインドキュメント公開

---

## 優先順位付け

### 🔴 最高優先（Ver 2.1.0 必須）

| ID | 項目 | 理由 | 工数 |
|----|-----|------|-----|
| A1 | エラーハンドリング強化 | 本番環境での安定性確保 | 4h |
| A2 | バリデーション機構 | 誤使用の防止 | 3h |
| B1 | 遅延ロード | パフォーマンス改善 | 4h |
| C3 | テストヘルパー | 開発効率向上 | 3h |

**合計**: 14時間

### 🟡 高優先（Ver 2.2.0）

| ID | 項目 | 理由 | 工数 |
|----|-----|------|-----|
| A3 | ロギング強化 | トラブルシューティング改善 | 3h |
| B2 | キャッシュ最適化 | パフォーマンス向上 | 4h |
| C1 | デバッグ機能 | 開発体験向上 | 5h |
| D1 | ミドルウェアシステム | 拡張性向上 | 6h |

**合計**: 18時間

### 🟢 中優先（Ver 2.3.0以降）

| ID | 項目 | 工数 |
|----|-----|-----|
| B3 | メモリ管理 | 3h |
| C2 | CLI ツール | 8h |
| D2 | フック機構 | 4h |
| D3 | プラグインAPI | 8h |
| E1 | PHPDoc 完全化 | 6h |
| E2 | APIドキュメント | 4h |

**合計**: 33時間

---

## 詳細設計

### A1. エラーハンドリング強化

#### 設計方針
1. カスタム例外階層の構築
2. エラーコンテキストの保持
3. エラーリカバリー戦略の実装

#### 実装内容

```php
// exceptions/FrameworkException.php
abstract class FrameworkException extends Exception {
    protected array $context = [];
    
    public function __construct(string $message, array $context = [], int $code = 0, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }
    
    public function getContext(): array {
        return $this->context;
    }
}

class EngineException extends FrameworkException {}
class ContainerException extends FrameworkException {}
class CircularDependencyException extends EngineException {}
class ServiceNotFoundException extends ContainerException {}
class EngineNotFoundException extends EngineException {}

// Framework.phpに追加
class Framework {
    private array $errorHandlers = [];
    
    public function onError(string $exceptionClass, callable $handler): void {
        $this->errorHandlers[$exceptionClass] = $handler;
    }
    
    private function handleException(Throwable $e): void {
        $class = get_class($e);
        
        if (isset($this->errorHandlers[$class])) {
            $this->errorHandlers[$class]($e, $this);
            return;
        }
        
        // デフォルトハンドラ
        if ($e instanceof FrameworkException) {
            $this->logError($e);
        }
        
        throw $e;
    }
    
    private function logError(FrameworkException $e): void {
        error_log(sprintf(
            "[AFE Error] %s: %s (Context: %s)",
            get_class($e),
            $e->getMessage(),
            json_encode($e->getContext())
        ));
    }
}
```

### A2. バリデーション機構

#### 設計方針
1. 設定スキーマの定義
2. ランタイムバリデーション
3. 明確なエラーメッセージ

#### 実装内容

```php
// Framework.phpに追加
class Framework {
    private array $configSchema = [
        'debug' => 'bool',
        'engines' => 'array',
        'cache_enabled' => 'bool',
    ];
    
    private function validateConfig(array $config): void {
        foreach ($config as $key => $value) {
            if (isset($this->configSchema[$key])) {
                $expectedType = $this->configSchema[$key];
                $actualType = gettype($value);
                
                if ($actualType !== $expectedType) {
                    throw new ContainerException(
                        "Invalid config type for '{$key}'",
                        ['expected' => $expectedType, 'actual' => $actualType]
                    );
                }
            }
        }
    }
    
    private function validateEngine(EngineInterface $engine): void {
        $name = $engine->getName();
        
        // エンジン名のバリデーション
        if (empty($name) || !preg_match('/^[a-z_]+$/', $name)) {
            throw new EngineException(
                "Invalid engine name: must be lowercase with underscores",
                ['name' => $name]
            );
        }
        
        // 優先度のバリデーション
        $priority = $engine->getPriority();
        if ($priority < 0 || $priority > 1000) {
            throw new EngineException(
                "Invalid priority: must be between 0 and 1000",
                ['engine' => $name, 'priority' => $priority]
            );
        }
    }
}
```

### B1. 遅延ロード（Lazy Loading）

#### 設計方針
1. プロキシパターンの活用
2. 必要になるまで初期化を遅延
3. 透過的なアクセス

#### 実装内容

```php
// Framework.phpに追加
class Framework {
    private array $lazyServices = [];
    
    public function lazy(string $id, callable $factory): void {
        $this->lazyServices[$id] = $factory;
    }
    
    public function get(string $id) {
        // 通常のサービス
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }
        
        // 遅延ロードサービス
        if (isset($this->lazyServices[$id])) {
            $this->services[$id] = $this->lazyServices[$id]($this);
            unset($this->lazyServices[$id]);
            return $this->services[$id];
        }
        
        // ファクトリーから生成
        if (isset($this->factories[$id])) {
            $instance = $this->factories[$id]($this);
            
            if ($this->singletons[$id] ?? false) {
                $this->services[$id] = $instance;
            }
            
            return $instance;
        }
        
        throw new ServiceNotFoundException(
            "Service not found: {$id}",
            ['service_id' => $id]
        );
    }
}

// エンジンの条件付きロード
class Framework {
    public function registerIf(EngineInterface $engine, callable $condition): self {
        if ($condition($this)) {
            $this->register($engine);
        }
        return $this;
    }
}

// 使用例
$framework->registerIf(new HeavyEngine($framework), function($fw) {
    return $fw->config('feature.heavy_engine_enabled', false);
});
```

### C3. テストヘルパー

#### 設計方針
1. モックエンジンの簡易生成
2. イベントアサーション
3. テスト用DIコンテナ

#### 実装内容

```php
// testing/TestHelper.php
class TestHelper {
    public static function createMockEngine(
        string $name,
        array $dependencies = [],
        int $priority = 100
    ): EngineInterface {
        return new class($name, $dependencies, $priority) implements EngineInterface {
            private string $name;
            private array $dependencies;
            private int $priority;
            public bool $initialized = false;
            public bool $booted = false;
            
            public function __construct(string $name, array $deps, int $priority) {
                $this->name = $name;
                $this->dependencies = $deps;
                $this->priority = $priority;
            }
            
            public function getName(): string { return $this->name; }
            public function getPriority(): int { return $this->priority; }
            public function getDependencies(): array { return $this->dependencies; }
            public function initialize(array $config = []): void { $this->initialized = true; }
            public function boot(): void { $this->booted = true; }
            public function shutdown(): void {}
        };
    }
    
    public static function assertEventEmitted(Framework $fw, string $event): void {
        // イベント履歴トラッキング実装
    }
    
    public static function createTestFramework(): Framework {
        return Framework::getInstance(['debug' => true, 'test_mode' => true]);
    }
}
```

---

## 実装計画

### Phase 1: Ver 2.1.0（最高優先項目）

**期間**: 2週間  
**工数**: 14時間

#### Week 1
- [ ] Day 1-2: カスタム例外クラス実装（4h）
- [ ] Day 3: バリデーション機構実装（3h）

#### Week 2
- [ ] Day 1-2: 遅延ロード実装（4h）
- [ ] Day 3: テストヘルパー実装（3h）
- [ ] Day 4: 統合テスト・ドキュメント更新

### Phase 2: Ver 2.2.0（高優先項目）

**期間**: 3週間  
**工数**: 18時間

#### Week 1
- [ ] ロギング強化（3h）
- [ ] キャッシュ最適化（4h）

#### Week 2-3
- [ ] デバッグ機能（5h）
- [ ] ミドルウェアシステム（6h）

### Phase 3: Ver 2.3.0以降（中優先項目）

**期間**: 5週間  
**工数**: 33時間

- [ ] メモリ管理（3h）
- [ ] CLI ツール（8h）
- [ ] フック機構（4h）
- [ ] プラグインAPI（8h）
- [ ] PHPDoc完全化（6h）
- [ ] APIドキュメント（4h）

---

## まとめ

### 改良の焦点

1. **安定性**: エラーハンドリング・バリデーションで本番環境に対応
2. **パフォーマンス**: 遅延ロード・キャッシュで高速化
3. **開発体験**: テストヘルパー・デバッグ機能で効率化
4. **拡張性**: ミドルウェア・フック・プラグインで柔軟性向上

### 投資対効果

| Phase | 工数 | 主な改善 | ROI |
|-------|-----|---------|-----|
| Ver 2.1.0 | 14h | 安定性+50%, テスト効率+100% | 高 |
| Ver 2.2.0 | 18h | パフォーマンス+30%, DX+50% | 高 |
| Ver 2.3.0 | 33h | 拡張性+100%, 保守性+50% | 中 |

**総工数**: 65時間（約8日間）

---

**次のアクション**: Phase 1（Ver 2.1.0）の実装開始

