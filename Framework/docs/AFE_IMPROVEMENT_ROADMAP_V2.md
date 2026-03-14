# AFE Framework 改良ロードマップ Ver.2

**Adlaire Framework Ecosystem (AFE) Ver.2.2.0 - 2.5.0**

**日付**: 2026年3月13日  
**ステータス**: 実践的改良提案  
**目的**: AFEを実用的で高性能なフレームワークへ進化（AI/JIT不要）

---

## 📋 目次

1. [エグゼクティブサマリー](#エグゼクティブサマリー)
2. [改良方針の転換](#改良方針の転換)
3. [Phase 2: Ver 2.2.0 - 実用的強化](#phase-2-ver-220---実用的強化)
4. [Phase 3: Ver 2.3.0 - JavaScript フレームワーク化](#phase-3-ver-230---javascript-フレームワーク化)
5. [Phase 4: Ver 2.4.0 - 高度な最適化](#phase-4-ver-240---高度な最適化)
6. [Phase 5: Ver 2.5.0 - エンタープライズ対応](#phase-5-ver-250---エンタープライズ対応)
7. [実装計画とROI](#実装計画とroi)

---

## エグゼクティブサマリー

### 改良の焦点

AFE Ver 2.1.0で基礎が確立されました。次のフェーズでは、**実用的で保守しやすいフレームワーク**を目指し、以下の4つの柱で改良を進めます：

1. **🚀 実践的パフォーマンス最適化** - PHP標準機能で5倍高速化
2. **🔒 堅牢性とセキュリティ** - 本番環境完全対応
3. **🧩 JavaScript フレームワーク化** - フロントエンド統一アーキテクチャ
4. **📊 可観測性（Observability）** - デバッグとモニタリング

### 目標指標

| 指標 | 現状 (v2.1.0) | 目標 (v2.5.0) | 改善率 | 実現手法 |
|-----|-------------|-------------|-------|---------|
| 起動時間 | 80ms | 15ms | **81%削減** | OPcache + 依存グラフキャッシング |
| メモリ使用量 | 7MB | 4MB | **43%削減** | 遅延ロード + メモリ管理 |
| リクエスト処理 | 100 req/s | 500 req/s | **5倍** | ミドルウェア + キャッシング |
| コードカバレッジ | 0% | 85%+ | **∞** | PHPUnit + テストヘルパー |
| デバッグ時間 | 4h | 1h | **75%削減** | 構造化ロギング + トレーシング |
| JS保守性 | 低 | 高 | **大幅改善** | フレームワーク化 + モジュール化 |

---

## 改良方針の転換

### なぜAI/JIT最適化を除外するのか

**AI統合を除外する理由:**
- ✅ 複雑性の回避 - シンプルさがAFEの強み
- ✅ 依存関係の最小化 - 外部ライブラリ不要
- ✅ 予測可能性 - 決定的な動作が重要
- ✅ 実装コストとメンテナンスコスト - ROIが低い

**JIT最適化を除外する理由:**
- ✅ PHP 8.x の標準JITで十分 - 追加実装不要
- ✅ OPcache で90%の効果を達成可能
- ✅ 複雑性vsリターンの比率が悪い
- ✅ 標準的なキャッシング手法で目標達成可能

### 代わりに注力する領域

**1. JavaScript フレームワーク化 (新規)**
- 現状: 約4,910行の従来型JavaScript（13ファイル）
- 課題: グローバル変数、重複コード、保守性低下
- 目標: AFE-JSとして統一フレームワーク化

**2. 実用的PHP最適化**
- OPcache活用
- 依存グラフキャッシング
- 遅延ロード（既存機能の強化）

**3. 開発体験の向上**
- 構造化ロギング
- イベントトレーシング
- テストヘルパー（既存を拡張）

---

## Phase 2: Ver 2.2.0 - 実用的強化

**期間**: 3週間（29時間）  
**リリース目標**: 2026年4月4日

### P2-1. 構造化ロギングシステム

**目的**: プロダクショングレードのロギング

**実装内容**:
```php
// framework/logging/StructuredLogger.php
class StructuredLogger {
    private array $handlers = [];
    private array $processors = [];
    private array $context = [];
    private string $requestId;
    
    public function __construct() {
        $this->requestId = uniqid('req_', true);
    }
    
    public function log(string $level, string $message, array $context = []): void {
        $record = [
            'timestamp' => microtime(true),
            'datetime' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => array_merge($this->context, $context),
            'request_id' => $this->requestId,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];
        
        // Processors
        foreach ($this->processors as $processor) {
            $record = $processor($record);
        }
        
        // Handlers
        foreach ($this->handlers as $handler) {
            $handler->handle($record);
        }
    }
    
    public function debug(string $message, array $context = []): void {
        $this->log('debug', $message, $context);
    }
    
    public function info(string $message, array $context = []): void {
        $this->log('info', $message, $context);
    }
    
    public function warning(string $message, array $context = []): void {
        $this->log('warning', $message, $context);
    }
    
    public function error(string $message, array $context = []): void {
        $this->log('error', $message, $context);
    }
    
    public function withContext(array $context): self {
        $clone = clone $this;
        $clone->context = array_merge($this->context, $context);
        return $clone;
    }
}

// ログハンドラー
interface LogHandlerInterface {
    public function handle(array $record): void;
}

class JsonFileHandler implements LogHandlerInterface {
    private string $path;
    private $handle;
    
    public function __construct(string $path) {
        $this->path = $path;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    public function handle(array $record): void {
        $line = json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}

class ConsoleHandler implements LogHandlerInterface {
    private string $minLevel;
    
    public function __construct(string $minLevel = 'debug') {
        $this->minLevel = $minLevel;
    }
    
    public function handle(array $record): void {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        if (($levels[$record['level']] ?? 0) < ($levels[$this->minLevel] ?? 0)) {
            return;
        }
        
        $color = match($record['level']) {
            'error' => "\033[31m",    // Red
            'warning' => "\033[33m",  // Yellow
            'info' => "\033[32m",     // Green
            'debug' => "\033[36m",    // Cyan
            default => "\033[0m"
        };
        
        $reset = "\033[0m";
        
        fprintf(
            STDERR,
            "%s[%s] %s%s: %s (%.2fMB)%s\n",
            $color,
            $record['datetime'],
            strtoupper($record['level']),
            $reset,
            $record['message'],
            $record['memory_mb'],
            PHP_EOL
        );
    }
}
```

**使用例**:
```php
$logger = new StructuredLogger();
$logger->addHandler(new JsonFileHandler('/var/log/afe/app.json'));
$logger->addHandler(new ConsoleHandler('info'));

// リクエストIDプロセッサー
$logger->addProcessor(function($record) {
    $record['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $record['url'] = $_SERVER['REQUEST_URI'] ?? '';
    return $record;
});

$logger->info('User logged in', ['user_id' => 123]);

// コンテキスト付きロギング
$userLogger = $logger->withContext(['user_id' => 123, 'session_id' => session_id()]);
$userLogger->debug('Profile page accessed');
$userLogger->error('Failed to update profile', ['error' => $e->getMessage()]);
```

**メリット**:
- ✅ JSON形式での構造化ログ（解析しやすい）
- ✅ リクエストID追跡（分散トレーシング）
- ✅ カラー出力でコンソール可読性向上
- ✅ デバッグ時間 **60%削減**

**工数**: 6時間

---

### P2-2. 依存グラフキャッシング

**目的**: 起動時間を50%削減

**実装内容**:
```php
// framework/caching/DependencyCache.php
class DependencyCache {
    private string $cacheFile;
    private ?array $cache = null;
    private bool $enabled = true;
    
    public function __construct(string $cacheDir = '/tmp/afe_cache') {
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $this->cacheFile = $cacheDir . '/dependency_graph.php';
    }
    
    public function get(string $key): mixed {
        if ($this->cache === null) {
            $this->load();
        }
        return $this->cache[$key] ?? null;
    }
    
    public function set(string $key, mixed $value): void {
        if ($this->cache === null) {
            $this->load();
        }
        $this->cache[$key] = $value;
    }
    
    public function save(): void {
        if (!$this->enabled || $this->cache === null) {
            return;
        }
        
        $export = var_export($this->cache, true);
        $content = "<?php\n// AFE Dependency Cache\n// Generated: " . date('Y-m-d H:i:s') . "\nreturn {$export};\n";
        
        $tempFile = $this->cacheFile . '.tmp';
        file_put_contents($tempFile, $content);
        rename($tempFile, $this->cacheFile);
        
        // OPcacheでコンパイル
        if (function_exists('opcache_compile_file')) {
            opcache_compile_file($this->cacheFile);
        }
    }
    
    private function load(): void {
        if (!$this->enabled || !file_exists($this->cacheFile)) {
            $this->cache = [];
            return;
        }
        
        $this->cache = include $this->cacheFile;
        
        // キャッシュの有効性チェック（フレームワークファイルの更新時刻）
        if (!$this->isValid()) {
            $this->cache = [];
        }
    }
    
    private function isValid(): bool {
        if (!isset($this->cache['_metadata']['created_at'])) {
            return false;
        }
        
        $cacheTime = $this->cache['_metadata']['created_at'];
        
        // フレームワークコアファイルの更新時刻をチェック
        $frameworkFiles = [
            __DIR__ . '/../Framework.php',
            __DIR__ . '/../BaseEngine.php',
            __DIR__ . '/../EngineInterface.php',
        ];
        
        foreach ($frameworkFiles as $file) {
            if (file_exists($file) && filemtime($file) > $cacheTime) {
                return false;
            }
        }
        
        return true;
    }
    
    public function clear(): void {
        $this->cache = [];
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }
    
    public function warmUp(array $engines): void {
        $this->cache['_metadata'] = [
            'created_at' => time(),
            'engine_count' => count($engines),
        ];
        
        // 依存グラフを事前計算
        $graph = $this->buildDependencyGraph($engines);
        $this->set('dependency_graph', $graph);
        
        // 起動順序を事前計算
        $order = $this->topologicalSort($graph);
        $this->set('boot_order', $order);
        
        $this->save();
    }
    
    private function buildDependencyGraph(array $engines): array {
        $graph = [];
        foreach ($engines as $name => $engine) {
            $deps = $engine['dependencies'] ?? [];
            $graph[$name] = $deps;
        }
        return $graph;
    }
    
    private function topologicalSort(array $graph): array {
        // 既存のトポロジカルソートロジックを使用
        // （Frameworkクラスから移植）
        $inDegree = [];
        foreach (array_keys($graph) as $node) {
            $inDegree[$node] = 0;
        }
        
        foreach ($graph as $deps) {
            foreach ($deps as $dep) {
                if (isset($inDegree[$dep])) {
                    $inDegree[$dep]++;
                }
            }
        }
        
        $queue = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
            }
        }
        
        $result = [];
        while (!empty($queue)) {
            $node = array_shift($queue);
            $result[] = $node;
            
            foreach ($graph as $name => $deps) {
                if (in_array($node, $deps)) {
                    $inDegree[$name]--;
                    if ($inDegree[$name] === 0) {
                        $queue[] = $name;
                    }
                }
            }
        }
        
        return $result;
    }
}
```

**Framework統合**:
```php
class Framework {
    private ?DependencyCache $depCache = null;
    
    public function enableDependencyCache(string $cacheDir = '/tmp/afe_cache'): void {
        $this->depCache = new DependencyCache($cacheDir);
    }
    
    private function resolveDependencies(): array {
        // キャッシュチェック
        if ($this->depCache && ($cached = $this->depCache->get('boot_order'))) {
            return $cached;
        }
        
        // キャッシュミス：計算
        $order = $this->calculateDependencies();
        
        // キャッシュ保存
        if ($this->depCache) {
            $this->depCache->set('boot_order', $order);
            $this->depCache->save();
        }
        
        return $order;
    }
}
```

**メリット**:
- ✅ 起動時間 **50%削減** (80ms → 40ms)
- ✅ CPU使用率 **30%削減**
- ✅ OPcache活用で超高速
- ✅ 自動無効化（ファイル更新検出）

**工数**: 4時間

---

### P2-3. イベントトレーシング

**目的**: デバッグを革命的に改善

**実装内容**:
```php
// framework/tracing/EventTracer.php
class EventTracer {
    private array $traces = [];
    private bool $enabled = false;
    private int $maxTraces = 1000;
    private float $startTime;
    
    public function __construct() {
        $this->startTime = microtime(true);
    }
    
    public function enable(): void {
        $this->enabled = true;
    }
    
    public function disable(): void {
        $this->enabled = false;
    }
    
    public function trace(string $event, array $data = []): void {
        if (!$this->enabled) {
            return;
        }
        
        if (count($this->traces) >= $this->maxTraces) {
            array_shift($this->traces); // FIFO
        }
        
        $now = microtime(true);
        
        $this->traces[] = [
            'timestamp' => $now,
            'relative_ms' => round(($now - $this->startTime) * 1000, 2),
            'event' => $event,
            'data' => $data,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];
    }
    
    public function getTraces(): array {
        return $this->traces;
    }
    
    public function getTimeline(): array {
        return array_map(fn($t) => [
            'event' => $t['event'],
            'time_ms' => $t['relative_ms'],
            'memory_mb' => $t['memory_mb'],
            'data' => $t['data'],
        ], $this->traces);
    }
    
    public function getStats(): array {
        $eventCounts = [];
        $totalDuration = 0;
        
        foreach ($this->traces as $trace) {
            $event = $trace['event'];
            $eventCounts[$event] = ($eventCounts[$event] ?? 0) + 1;
        }
        
        if (!empty($this->traces)) {
            $totalDuration = end($this->traces)['relative_ms'];
        }
        
        return [
            'total_events' => count($this->traces),
            'unique_events' => count($eventCounts),
            'total_duration_ms' => $totalDuration,
            'event_counts' => $eventCounts,
            'peak_memory_mb' => max(array_column($this->traces, 'memory_mb')),
        ];
    }
    
    public function exportJson(): string {
        return json_encode([
            'traces' => $this->getTimeline(),
            'stats' => $this->getStats(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    public function exportHtml(): string {
        $stats = $this->getStats();
        $timeline = $this->getTimeline();
        
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AFE Event Timeline</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; padding: 20px; background: #1a1a1a; color: #e0e0e0; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { margin-bottom: 20px; color: #0cf; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: #2a2a2a; padding: 15px; border-radius: 8px; border-left: 4px solid #0cf; }
        .stat-label { font-size: 12px; color: #888; margin-bottom: 5px; }
        .stat-value { font-size: 24px; font-weight: bold; color: #0cf; }
        .timeline { background: #2a2a2a; border-radius: 8px; padding: 20px; }
        .event { padding: 12px; margin: 8px 0; background: #333; border-left: 4px solid #0cf; border-radius: 4px; transition: all 0.2s; }
        .event:hover { background: #3a3a3a; transform: translateX(5px); }
        .event-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .event-name { font-weight: bold; color: #0cf; }
        .event-time { font-size: 12px; color: #888; }
        .event-memory { font-size: 12px; color: #f90; }
        .event-data { font-size: 12px; color: #aaa; margin-top: 5px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 AFE Event Timeline</h1>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-label">Total Events</div>
                <div class="stat-value">{$stats['total_events']}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Unique Events</div>
                <div class="stat-value">{$stats['unique_events']}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Duration</div>
                <div class="stat-value">{$stats['total_duration_ms']} ms</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Peak Memory</div>
                <div class="stat-value">{$stats['peak_memory_mb']} MB</div>
            </div>
        </div>
        
        <div class="timeline">
HTML;
        
        foreach ($timeline as $item) {
            $eventName = htmlspecialchars($item['event']);
            $timeMs = number_format($item['time_ms'], 2);
            $memoryMb = number_format($item['memory_mb'], 2);
            $dataJson = !empty($item['data']) ? htmlspecialchars(json_encode($item['data'], JSON_UNESCAPED_UNICODE)) : '';
            
            $html .= <<<EVENT
            <div class="event">
                <div class="event-header">
                    <span class="event-name">{$eventName}</span>
                    <span class="event-time">+{$timeMs}ms</span>
                </div>
                <div class="event-memory">Memory: {$memoryMb} MB</div>
EVENT;
            
            if ($dataJson) {
                $html .= "<div class=\"event-data\">{$dataJson}</div>";
            }
            
            $html .= "</div>\n";
        }
        
        $html .= <<<HTML
        </div>
    </div>
</body>
</html>
HTML;
        
        return $html;
    }
}
```

**Framework統合**:
```php
class Framework {
    private ?EventTracer $tracer = null;
    
    public function enableTracing(): void {
        $this->tracer = new EventTracer();
        $this->tracer->enable();
    }
    
    public function emit(string $event, array $data = []): void {
        // トレース記録
        if ($this->tracer) {
            $this->tracer->trace($event, $data);
        }
        
        // 既存のイベント処理
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $item) {
            $listener = $item['listener'];
            $listener($data, $this);
        }
    }
}
```

**使用例**:
```php
// デバッグモードで有効化
if ($framework->config('debug')) {
    $framework->enableTracing();
}

// アプリケーション実行...

// トレース確認
$tracer = $framework->getTracer();
if ($tracer) {
    // JSON出力
    file_put_contents('/tmp/trace.json', $tracer->exportJson());
    
    // HTML出力
    file_put_contents('/tmp/trace.html', $tracer->exportHtml());
    
    // 統計表示
    print_r($tracer->getStats());
}
```

**メリット**:
- ✅ イベントフローの完全可視化
- ✅ パフォーマンスボトルネックの特定
- ✅ デバッグ時間 **70%削減**
- ✅ 美しいHTML出力（ダークテーマ）

**工数**: 5時間

---

### P2-4. ミドルウェアパイプライン

**目的**: リクエスト処理の柔軟性向上

**実装内容**:
```php
// framework/middleware/MiddlewareInterface.php
interface MiddlewareInterface {
    public function process($payload, callable $next);
}

// framework/middleware/Pipeline.php
class Pipeline {
    private array $stages = [];
    
    public function pipe(MiddlewareInterface $middleware): self {
        $this->stages[] = $middleware;
        return $this;
    }
    
    public function process($payload) {
        $pipeline = array_reduce(
            array_reverse($this->stages),
            fn($next, $stage) => fn($payload) => $stage->process($payload, $next),
            fn($payload) => $payload
        );
        
        return $pipeline($payload);
    }
    
    public function then(callable $destination) {
        return $this->process($destination);
    }
}

// 具体的なミドルウェア例
class LoggingMiddleware implements MiddlewareInterface {
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    public function process($payload, callable $next) {
        $this->logger->debug('Before middleware', ['payload' => $payload]);
        
        $startTime = microtime(true);
        $result = $next($payload);
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->debug('After middleware', [
            'result' => $result,
            'duration_ms' => $duration
        ]);
        
        return $result;
    }
}

class ValidationMiddleware implements MiddlewareInterface {
    private array $rules;
    
    public function __construct(array $rules) {
        $this->rules = $rules;
    }
    
    public function process($payload, callable $next) {
        foreach ($this->rules as $field => $rule) {
            if (!isset($payload[$field])) {
                throw new ValidationException("Missing field: {$field}");
            }
            
            if ($rule === 'required' && empty($payload[$field])) {
                throw new ValidationException("Field is required: {$field}");
            }
        }
        
        return $next($payload);
    }
}

class CachingMiddleware implements MiddlewareInterface {
    private $cache;
    private int $ttl;
    
    public function __construct($cache, int $ttl = 3600) {
        $this->cache = $cache;
        $this->ttl = $ttl;
    }
    
    public function process($payload, callable $next) {
        $key = $this->getCacheKey($payload);
        
        if ($cached = $this->cache->get($key)) {
            return $cached;
        }
        
        $result = $next($payload);
        $this->cache->set($key, $result, $this->ttl);
        
        return $result;
    }
    
    private function getCacheKey($payload): string {
        return 'cache_' . md5(json_encode($payload));
    }
}

class RateLimitMiddleware implements MiddlewareInterface {
    private int $maxRequests;
    private int $windowSeconds;
    private array $requests = [];
    
    public function __construct(int $maxRequests = 60, int $windowSeconds = 60) {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }
    
    public function process($payload, callable $next) {
        $key = $this->getClientKey();
        $now = time();
        
        // 古いリクエストを削除
        $this->requests[$key] = array_filter(
            $this->requests[$key] ?? [],
            fn($timestamp) => $timestamp > ($now - $this->windowSeconds)
        );
        
        // レート制限チェック
        if (count($this->requests[$key]) >= $this->maxRequests) {
            throw new RateLimitException('Rate limit exceeded');
        }
        
        // リクエスト記録
        $this->requests[$key][] = $now;
        
        return $next($payload);
    }
    
    private function getClientKey(): string {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
```

**使用例**:
```php
$pipeline = new Pipeline();
$pipeline
    ->pipe(new LoggingMiddleware($logger))
    ->pipe(new ValidationMiddleware(['user_id' => 'required']))
    ->pipe(new RateLimitMiddleware(100, 60))
    ->pipe(new CachingMiddleware($cache));

try {
    $result = $pipeline->process([
        'user_id' => 123,
        'action' => 'get_profile'
    ]);
    
    echo json_encode($result);
} catch (ValidationException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (RateLimitException $e) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
}
```

**メリット**:
- ✅ 柔軟なリクエスト処理
- ✅ クロスカッティング関心事の分離
- ✅ テスト容易性の向上
- ✅ コードの再利用性

**工数**: 6時間

---

**Phase 2 合計**: 29時間（3週間）

**Phase 2 の期待効果**:
- 起動時間: 80ms → 40ms (**50%削減**)
- デバッグ時間: **70%削減**
- 本番環境準備度: 70% → 90%
- ROI: **450%**

---

## Phase 3: Ver 2.3.0 - JavaScript フレームワーク化

**期間**: 4週間（48時間）  
**リリース目標**: 2026年5月2日

### 現状分析

**既存JavaScript構成**:
- 総行数: ~4,910行
- ファイル数: 13ファイル
- 主要ファイル:
  - `wysiwyg.js` - WYSIWYGエディタ（約1,500行）
  - `dashboard.js` - ダッシュボード機能
  - `editInplace.js` - インライン編集
  - `collection_manager.js`, `git_manager.js`, `webhook_manager.js` 等

**課題**:
1. グローバル変数の乱用
2. コード重複（CSRF処理、Fetch処理等）
3. モジュール化不足
4. テストが困難
5. 保守性の低下

### 目標: AFE-JS フレームワーク

**設計原則**:
- ✅ **Vanilla JavaScript** - 外部依存なし（React/Vue不要）
- ✅ **モジュール設計** - ES6 modules
- ✅ **イベント駆動** - PHPフレームワークと同じパターン
- ✅ **再利用性** - 共通機能のコンポーネント化
- ✅ **型安全性** - JSDocで型ヒント

### P3-1. AFE-JS コアフレームワーク

**実装内容**:

```javascript
// framework/js/AFE.js
/**
 * Adlaire Framework Ecosystem - JavaScript Core
 * @version 2.3.0
 */
class AFE {
    constructor() {
        /** @type {Object.<string, Array<Function>>} */
        this.listeners = {};
        
        /** @type {Object.<string, any>} */
        this.services = {};
        
        /** @type {Object.<string, any>} */
        this.config = {};
        
        /** @type {string|null} */
        this.csrfToken = null;
        
        this._init();
    }
    
    _init() {
        // CSRF トークン取得
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            this.csrfToken = meta.getAttribute('content');
        }
        
        // デフォルト設定
        this.config = {
            apiEndpoint: 'index.php',
            debug: false,
        };
    }
    
    /**
     * サービスを登録
     * @param {string} name
     * @param {any} service
     */
    register(name, service) {
        this.services[name] = service;
        this.emit('service:registered', { name, service });
    }
    
    /**
     * サービスを取得
     * @param {string} name
     * @returns {any}
     */
    get(name) {
        if (!this.services[name]) {
            throw new Error(`Service not found: ${name}`);
        }
        return this.services[name];
    }
    
    /**
     * 設定値を取得・設定
     * @param {string} key
     * @param {any} value
     * @returns {any}
     */
    config(key, value) {
        if (value !== undefined) {
            this.config[key] = value;
            return value;
        }
        return this.config[key];
    }
    
    /**
     * イベントリスナーを登録
     * @param {string} event
     * @param {Function} callback
     */
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }
    
    /**
     * イベントを発火
     * @param {string} event
     * @param {any} data
     */
    emit(event, data = {}) {
        if (this.config.debug) {
            console.log(`[AFE Event] ${event}`, data);
        }
        
        if (!this.listeners[event]) {
            return;
        }
        
        this.listeners[event].forEach(callback => {
            try {
                callback(data, this);
            } catch (error) {
                console.error(`[AFE] Error in event listener for "${event}":`, error);
            }
        });
    }
    
    /**
     * HTTP リクエスト（Fetch API ラッパー）
     * @param {string} url
     * @param {Object} options
     * @returns {Promise<any>}
     */
    async request(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
        };
        
        // CSRF トークンを自動付与
        if (this.csrfToken && options.method !== 'GET') {
            defaultOptions.headers['X-CSRF-TOKEN'] = this.csrfToken;
        }
        
        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {}),
            },
        };
        
        try {
            this.emit('request:start', { url, options: mergedOptions });
            
            const response = await fetch(url, mergedOptions);
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || `HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            this.emit('request:success', { url, data });
            
            return data;
        } catch (error) {
            this.emit('request:error', { url, error });
            throw error;
        }
    }
    
    /**
     * POST リクエスト
     * @param {string} url
     * @param {Object} data
     * @returns {Promise<any>}
     */
    async post(url, data) {
        const body = new URLSearchParams(data);
        return this.request(url, {
            method: 'POST',
            body: body.toString(),
        });
    }
    
    /**
     * DOM要素を安全に取得
     * @param {string} selector
     * @returns {Element|null}
     */
    $(selector) {
        return document.querySelector(selector);
    }
    
    /**
     * DOM要素を複数取得
     * @param {string} selector
     * @returns {NodeList}
     */
    $$(selector) {
        return document.querySelectorAll(selector);
    }
    
    /**
     * DOMContentLoaded後に実行
     * @param {Function} callback
     */
    ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }
}

// グローバルインスタンス
window.afe = new AFE();

export default AFE;
```

### P3-2. 共通コンポーネント

**HTTPサービス**:
```javascript
// framework/js/services/HttpService.js
class HttpService {
    constructor(afe) {
        this.afe = afe;
    }
    
    /**
     * フィールド保存（共通処理）
     * @param {string} fieldname
     * @param {string} content
     * @returns {Promise<any>}
     */
    async saveField(fieldname, content) {
        return this.afe.post(this.afe.config.apiEndpoint, {
            ap_action: 'edit_field',
            fieldname,
            content,
            csrf: this.afe.csrfToken,
        });
    }
    
    /**
     * ページ削除
     * @param {string} slug
     * @returns {Promise<any>}
     */
    async deletePage(slug) {
        return this.afe.post(this.afe.config.apiEndpoint, {
            ap_action: 'delete_page',
            slug,
            csrf: this.afe.csrfToken,
        });
    }
}

export default HttpService;
```

**UIヘルパー**:
```javascript
// framework/js/services/UIService.js
class UIService {
    /**
     * フラッシュメッセージ表示
     * @param {Element} element
     * @param {boolean} success
     */
    flash(element, success = true) {
        if (!element) return;
        
        const className = success ? 'afe-flash-success' : 'afe-flash-error';
        element.classList.add(className);
        
        setTimeout(() => {
            element.classList.remove(className);
        }, 1500);
    }
    
    /**
     * 確認ダイアログ
     * @param {string} message
     * @returns {boolean}
     */
    confirm(message) {
        return window.confirm(message);
    }
    
    /**
     * ローディング表示
     * @param {boolean} show
     */
    loading(show = true) {
        let loader = document.getElementById('afe-loader');
        
        if (show && !loader) {
            loader = document.createElement('div');
            loader.id = 'afe-loader';
            loader.innerHTML = '<div class="afe-spinner"></div>';
            document.body.appendChild(loader);
        } else if (!show && loader) {
            loader.remove();
        }
    }
}

export default UIService;
```

### P3-3. モジュール化リファクタリング例

**Before (dashboard.js)**:
```javascript
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        initPageDelete();
    });
    
    function getCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta) {
            console.error('CSRF トークンが見つかりません');
            return null;
        }
        return meta.getAttribute('content');
    }
    
    function initPageDelete() {
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.ap-page-delete');
            if (!btn) return;
            // ... 100行以上のコード
        });
    }
})();
```

**After (dashboard-afe.js)**:
```javascript
// engines/JsEngine/modules/Dashboard.js
import AFE from '../../../framework/js/AFE.js';

class Dashboard {
    constructor(afe) {
        this.afe = afe;
        this.http = afe.get('http');
        this.ui = afe.get('ui');
    }
    
    init() {
        this.bindEvents();
    }
    
    bindEvents() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.ap-page-delete');
            if (btn) {
                this.handleDelete(e, btn);
            }
        });
    }
    
    async handleDelete(e, btn) {
        e.preventDefault();
        e.stopPropagation();
        
        const slug = btn.dataset.slug;
        if (!slug) return;
        
        if (!this.ui.confirm(`ページ「${slug}」を削除しますか？`)) {
            return;
        }
        
        btn.disabled = true;
        btn.textContent = '削除中...';
        
        try {
            await this.http.deletePage(slug);
            
            const item = btn.closest('.ap-dash-page-item');
            if (item) {
                item.remove();
            }
            
            this.afe.emit('page:deleted', { slug });
        } catch (error) {
            console.error('削除エラー:', error);
            btn.disabled = false;
            btn.textContent = '×';
        }
    }
}

export default Dashboard;
```

**エントリーポイント (main.js)**:
```javascript
// engines/JsEngine/main.js
import AFE from '../../framework/js/AFE.js';
import HttpService from '../../framework/js/services/HttpService.js';
import UIService from '../../framework/js/services/UIService.js';
import Dashboard from './modules/Dashboard.js';
import EditInPlace from './modules/EditInPlace.js';

// サービス登録
afe.register('http', new HttpService(afe));
afe.register('ui', new UIService());

// デバッグモード
if (afe.$('meta[name="debug"]')?.content === '1') {
    afe.config('debug', true);
}

// モジュール初期化
afe.ready(() => {
    const dashboard = new Dashboard(afe);
    dashboard.init();
    
    const editInPlace = new EditInPlace(afe);
    editInPlace.init();
    
    afe.emit('app:ready');
});
```

### P3-4. テスタブルなコード

**テストヘルパー**:
```javascript
// framework/js/testing/TestHelper.js
class AFETestHelper {
    constructor() {
        this.afe = new AFE();
        this.mockResponses = new Map();
    }
    
    /**
     * HTTP レスポンスをモック
     * @param {string} url
     * @param {any} response
     */
    mockResponse(url, response) {
        this.mockResponses.set(url, response);
        
        // Fetch APIをモック
        global.fetch = jest.fn((url) => 
            Promise.resolve({
                ok: true,
                json: () => Promise.resolve(this.mockResponses.get(url)),
            })
        );
    }
    
    /**
     * イベントが発火されたか確認
     * @param {string} event
     * @returns {Promise<any>}
     */
    waitForEvent(event) {
        return new Promise((resolve) => {
            this.afe.on(event, (data) => {
                resolve(data);
            });
        });
    }
}

export default AFETestHelper;
```

**テスト例**:
```javascript
// tests/Dashboard.test.js
import Dashboard from '../modules/Dashboard';
import AFETestHelper from '../../../framework/js/testing/TestHelper';

describe('Dashboard', () => {
    let helper;
    let dashboard;
    
    beforeEach(() => {
        helper = new AFETestHelper();
        dashboard = new Dashboard(helper.afe);
    });
    
    test('should delete page successfully', async () => {
        helper.mockResponse('index.php', { ok: true });
        
        const eventPromise = helper.waitForEvent('page:deleted');
        
        // テスト実行
        await dashboard.handleDelete(mockEvent, mockButton);
        
        const eventData = await eventPromise;
        expect(eventData.slug).toBe('test-page');
    });
});
```

### P3-5. 移行計画

**Week 1-2: フレームワーク実装**
- AFE.js コア実装
- HttpService, UIService 実装
- ビルドシステム構築（Rollup/Vite）

**Week 3: 既存コードリファクタリング**
- dashboard.js → Dashboard モジュール
- editInplace.js → EditInPlace モジュール
- 共通処理の抽出

**Week 4: テストと最適化**
- ユニットテスト作成
- E2Eテスト作成
- パフォーマンス最適化

---

**Phase 3 合計**: 48時間（4週間）

**Phase 3 の期待効果**:
- コード重複: **60%削減**
- テストカバレッジ: 0% → **80%**
- 保守性: **大幅改善**
- バグ発生率: **50%削減**
- ROI: **600%**

---

## Phase 4: Ver 2.4.0 - 高度な最適化

**期間**: 2週間（24時間）  
**リリース目標**: 2026年5月16日

### P4-1. APCuキャッシング

**実装内容**:
```php
// framework/caching/APCuCache.php
class APCuCache {
    private string $prefix;
    
    public function __construct(string $prefix = 'afe_') {
        if (!extension_loaded('apcu')) {
            throw new \RuntimeException('APCu extension not loaded');
        }
        $this->prefix = $prefix;
    }
    
    public function get(string $key): mixed {
        $value = apcu_fetch($this->prefix . $key, $success);
        return $success ? $value : null;
    }
    
    public function set(string $key, mixed $value, int $ttl = 3600): bool {
        return apcu_store($this->prefix . $key, $value, $ttl);
    }
    
    public function delete(string $key): bool {
        return apcu_delete($this->prefix . $key);
    }
    
    public function clear(): bool {
        return apcu_clear_cache();
    }
    
    public function has(string $key): bool {
        return apcu_exists($this->prefix . $key);
    }
}
```

**期待効果**:
- メモリ内キャッシュで超高速
- 起動時間さらに30%削減

**工数**: 4時間

---

### P4-2. プリロード機能 (PHP 7.4+)

**実装内容**:
```php
// preload.php
<?php
// PHP OPcache Preload Script for AFE

$files = [
    __DIR__ . '/framework/Framework.php',
    __DIR__ . '/framework/EngineInterface.php',
    __DIR__ . '/framework/BaseEngine.php',
    __DIR__ . '/framework/exceptions/FrameworkException.php',
    __DIR__ . '/framework/logging/StructuredLogger.php',
    __DIR__ . '/framework/caching/DependencyCache.php',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        opcache_compile_file($file);
    }
}
```

**php.ini設定**:
```ini
opcache.enable=1
opcache.preload=/path/to/preload.php
opcache.preload_user=www-data
```

**期待効果**:
- 起動時間さらに20%削減
- CPU使用率15%削減

**工数**: 3時間

---

### P4-3. 非同期処理サポート

**実装内容**:
```php
// framework/async/AsyncQueue.php
class AsyncQueue {
    private string $queueFile;
    
    public function push(string $task, array $data = []): void {
        $job = [
            'id' => uniqid('job_', true),
            'task' => $task,
            'data' => $data,
            'created_at' => time(),
        ];
        
        $queue = $this->load();
        $queue[] = $job;
        $this->save($queue);
        
        // バックグラウンド処理をトリガー
        $this->trigger();
    }
    
    public function process(): void {
        $queue = $this->load();
        
        foreach ($queue as $i => $job) {
            try {
                $this->execute($job);
                unset($queue[$i]);
            } catch (\Exception $e) {
                // エラーログ
                error_log("Job failed: " . $e->getMessage());
            }
        }
        
        $this->save(array_values($queue));
    }
    
    private function execute(array $job): void {
        // タスクハンドラーを実行
        $handler = $this->handlers[$job['task']] ?? null;
        if ($handler) {
            $handler($job['data']);
        }
    }
}
```

**期待効果**:
- 重い処理のバックグラウンド実行
- ユーザー体験の向上

**工数**: 8時間

---

### P4-4. パフォーマンスプロファイラー

**実装内容**:
```php
// framework/profiling/Profiler.php
class Profiler {
    private array $marks = [];
    private float $startTime;
    private int $startMemory;
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }
    
    public function mark(string $name): void {
        $this->marks[] = [
            'name' => $name,
            'time' => microtime(true),
            'memory' => memory_get_usage(true),
        ];
    }
    
    public function getReport(): array {
        $report = [];
        $prevTime = $this->startTime;
        $prevMemory = $this->startMemory;
        
        foreach ($this->marks as $mark) {
            $report[] = [
                'name' => $mark['name'],
                'duration_ms' => round(($mark['time'] - $prevTime) * 1000, 2),
                'memory_delta_mb' => round(($mark['memory'] - $prevMemory) / 1024 / 1024, 2),
                'total_time_ms' => round(($mark['time'] - $this->startTime) * 1000, 2),
                'total_memory_mb' => round($mark['memory'] / 1024 / 1024, 2),
            ];
            
            $prevTime = $mark['time'];
            $prevMemory = $mark['memory'];
        }
        
        return $report;
    }
    
    public function exportHtml(): string {
        // HTML レポート生成
        // （省略）
    }
}
```

**工数**: 5時間

---

**Phase 4 合計**: 24時間（2週間）

**Phase 4 の期待効果**:
- 起動時間: 40ms → 20ms (**50%削減**)
- メモリ使用量: 7MB → 4MB (**43%削減**)
- リクエスト処理: 100 → 400 req/s (**4倍**)
- ROI: **500%**

---

## Phase 5: Ver 2.5.0 - エンタープライズ対応

**期間**: 2週間（20時間）  
**リリース目標**: 2026年5月30日

### P5-1. セキュリティ強化

**実装内容**:
- CSRFトークンの強化
- SQLインジェクション対策（プリペアドステートメント強制）
- XSS対策（HTML Purifierライト版）
- レート制限（ミドルウェア）
- セキュリティヘッダー自動付与

**工数**: 8時間

---

### P5-2. 国際化 (i18n)

**実装内容**:
```php
// framework/i18n/Translator.php
class Translator {
    private array $translations = [];
    private string $locale = 'ja';
    
    public function load(string $locale, array $translations): void {
        $this->translations[$locale] = $translations;
    }
    
    public function setLocale(string $locale): void {
        $this->locale = $locale;
    }
    
    public function trans(string $key, array $params = []): string {
        $translation = $this->translations[$this->locale][$key] ?? $key;
        
        foreach ($params as $placeholder => $value) {
            $translation = str_replace(":{$placeholder}", $value, $translation);
        }
        
        return $translation;
    }
}
```

**工数**: 6時間

---

### P5-3. CLIツール

**実装内容**:
```php
// bin/afe
#!/usr/bin/env php
<?php

require __DIR__ . '/../framework/Framework.php';

$command = $argv[1] ?? 'help';

match($command) {
    'cache:clear' => clearCache(),
    'cache:warmup' => warmupCache(),
    'engine:list' => listEngines(),
    'engine:create' => createEngine($argv[2] ?? null),
    'test' => runTests(),
    'help' => showHelp(),
    default => echo "Unknown command: {$command}\n"
};

function clearCache() {
    echo "Clearing cache...\n";
    // 実装
    echo "Done!\n";
}

function createEngine(?string $name) {
    if (!$name) {
        die("Usage: afe engine:create <EngineName>\n");
    }
    
    echo "Creating engine: {$name}...\n";
    // スケルトン生成
    echo "Done!\n";
}
```

**工数**: 6時間

---

**Phase 5 合計**: 20時間（2週間）

**Phase 5 の期待効果**:
- セキュリティ: **エンタープライズグレード**
- 国際化対応: **完全**
- 開発体験: **大幅改善**
- ROI: **400%**

---

## 実装計画とROI

### 全体スケジュール

| Phase | Ver | 期間 | 工数 | リリース日 |
|-------|-----|------|------|----------|
| Phase 2 | 2.2.0 | 3週間 | 29h | 2026-04-04 |
| Phase 3 | 2.3.0 | 4週間 | 48h | 2026-05-02 |
| Phase 4 | 2.4.0 | 2週間 | 24h | 2026-05-16 |
| Phase 5 | 2.5.0 | 2週間 | 20h | 2026-05-30 |
| **合計** | **-** | **11週間** | **121h** | **-** |

### ROI分析

**投資**:
- 総工数: 121時間（約15営業日）
- 人件費: 121h × ¥5,000/h = ¥605,000

**リターン**（年間）:
- デバッグ時間削減: 4h → 1h（週あたり3h削減 × 52週 × ¥5,000 = ¥780,000）
- 起動時間削減によるサーバーコスト削減: **40%削減** = ¥200,000/年
- バグ発生率低下によるメンテナンスコスト削減: **50%削減** = ¥300,000/年
- 開発速度向上: **30%** = ¥500,000/年

**総リターン**: ¥1,780,000/年

**ROI**: (¥1,780,000 - ¥605,000) / ¥605,000 = **194%**

**投資回収期間**: 約4ヶ月

---

## 最終目標指標達成状況

| 指標 | 現状 (v2.1.0) | 目標 (v2.5.0) | 達成予測 | 手法 |
|-----|-------------|-------------|---------|------|
| 起動時間 | 80ms | 15ms | **20ms** | 依存グラフキャッシング + OPcache + プリロード |
| メモリ使用量 | 7MB | 4MB | **4MB** | 遅延ロード + APCu |
| リクエスト処理 | 100 req/s | 500 req/s | **400 req/s** | ミドルウェア + キャッシング |
| コードカバレッジ | 0% | 85%+ | **85%** | PHPUnit + Jest |
| デバッグ時間 | 4h | 1h | **1h** | 構造化ロギング + トレーシング |
| JS保守性 | 低 | 高 | **高** | AFE-JS フレームワーク化 |
| セキュリティ | 中 | 高 | **高** | CSRF + XSS + レート制限 |

---

## 次のステップ

1. **Phase 2 (Ver 2.2.0) 開始** - 2026年3月14日
2. リソース配分確認
3. 開発環境セットアップ
4. 実装開始

---

**ドキュメント作成**: 2026年3月13日  
**最終更新**: 2026年3月13日  
**ステータス**: 承認待ち
