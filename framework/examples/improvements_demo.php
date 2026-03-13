<?php
/**
 * AFE Framework Ver 2.1.0 Improvement Examples
 *
 * AFEフレームワークの改良機能のデモンストレーション
 *
 * @package Adlaire Framework Ecosystem
 * @version 2.1.0
 * @since   2026-03-13
 */

require_once __DIR__ . '/../framework/Framework.php';
require_once __DIR__ . '/../framework/BaseEngine.php';
require_once __DIR__ . '/../framework/testing/TestHelper.php';

// ==================== 例1: エラーハンドリング ====================

echo "=== Example 1: Error Handling ===\n\n";

try {
    $framework = Framework::getInstance(['debug' => true]);
    
    // カスタムエラーハンドラーを登録
    $framework->onError(ServiceNotFoundException::class, function($e, $fw) {
        echo "Custom Handler: Service '{$e->getContext()['service_id']}' not found!\n";
        echo "Suggestion: Did you forget to register it?\n\n";
    });
    
    // 存在しないサービスを取得（エラー発生）
    $framework->get('non_existent_service');
    
} catch (Throwable $e) {
    echo "Caught: " . $e->getMessage() . "\n\n";
}

// ==================== 例2: バリデーション ====================

echo "=== Example 2: Validation ===\n\n";

try {
    // 無効な設定（debugがbooleanでなくstring）
    $framework = Framework::getInstance(['debug' => 'yes']);  // エラー
} catch (InvalidConfigException $e) {
    echo "Config Validation Error:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n\n";
}

// ==================== 例3: 遅延ロード ====================

echo "=== Example 3: Lazy Loading ===\n\n";

$framework = Framework::getInstance(['debug' => false]);

// 通常のサービス登録
$framework->singleton('heavy_service', function($fw) {
    echo "  [Loading heavy service...]\n";
    return new class {
        public function process() {
            return "Heavy processing complete";
        }
    };
});

// 遅延ロードサービス登録（使用するまで初期化しない）
$framework->lazy('lazy_service', function($fw) {
    echo "  [Lazy service now loading...]\n";
    return new class {
        public function getData() {
            return "Lazy data";
        }
    };
});

echo "Services registered (lazy service not yet loaded)\n";

// 遅延ロードサービスを実際に使用
echo "Accessing lazy service...\n";
$lazy = $framework->get('lazy_service');
echo "  Result: " . $lazy->getData() . "\n\n";

// ==================== 例4: 条件付きエンジン登録 ====================

echo "=== Example 4: Conditional Engine Registration ===\n\n";

$framework = Framework::getInstance(['debug' => false]);

// 開発環境のみで有効なエンジン
$devEngine = TestHelper::createMockEngine('dev_tools', [], 10);
$framework->registerIf($devEngine, function($fw) {
    return $fw->config('debug', false) === true;
});

echo "Dev engine registered: " . ($framework->engine('dev_tools') ? 'Yes' : 'No') . "\n";

// 本番環境のエンジン
$prodEngine = TestHelper::createMockEngine('analytics', [], 20);
$framework->registerIf($prodEngine, function($fw) {
    return $fw->config('debug', false) === false;
});

echo "Production engine registered: " . ($framework->engine('analytics') ? 'Yes' : 'No') . "\n\n";

// ==================== 例5: テストヘルパー ====================

echo "=== Example 5: Testing Helpers ===\n\n";

// テスト用フレームワーク作成
$testFramework = TestHelper::createTestFramework();

// モックエンジン作成
$mockEngine = TestHelper::createMockEngine('test_engine', ['logger'], 50);
$testFramework->register($mockEngine);

// イベントトラッカー作成
$tracker = TestHelper::createEventTracker($testFramework, 'test.event');

// エンジンを起動
$testFramework->boot();

// イベント発火
$testFramework->emit('test.event', ['test' => 'data']);
$testFramework->emit('test.event', ['test' => 'data2']);

// アサーション
try {
    TestHelper::assertEngineInitialized($mockEngine);
    echo "✓ Engine initialized\n";
    
    TestHelper::assertEngineBooted($mockEngine);
    echo "✓ Engine booted\n";
    
    TestHelper::assertEventEmitted($tracker, 2);
    echo "✓ Event emitted 2 times\n";
    
    TestHelper::assertServiceRegistered($testFramework, 'test_engine');
    echo "✓ Service registered\n";
    
    echo "\nAll assertions passed!\n\n";
} catch (AssertionError $e) {
    echo "✗ Assertion failed: " . $e->getMessage() . "\n\n";
}

// ==================== 例6: 循環依存検出 ====================

echo "=== Example 6: Circular Dependency Detection ===\n\n";

$framework = Framework::getInstance(['debug' => false]);

// 循環依存するエンジン
$engineA = TestHelper::createMockEngine('engine_a', ['engine_b'], 100);
$engineB = TestHelper::createMockEngine('engine_b', ['engine_a'], 100);

$framework->register($engineA);
$framework->register($engineB);

try {
    $framework->boot();  // 循環依存エラー
} catch (CircularDependencyException $e) {
    echo "Circular Dependency Detected:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  Cycle: " . json_encode($e->getContext()['dependency_cycle']) . "\n\n";
}

echo "=== All Examples Complete ===\n";
