# DiagnosticEngine 設計書

> 確定日: 2026-03-10
> 対象バージョン: Ver.1.4.x
> 分類: 社内限り

---

## 1. 概要

DiagnosticEngine は AdlairePlatform のリアルタイム診断・テレメトリエンジンである。
本番サーバ上でシステムエラー・カスタムログ・環境情報を集約し、開発元へリアルタイム送信する。

### 設計目標

| # | 目標 | 実現手段 |
|---|------|----------|
| 1 | 障害の早期検知 | PHP エラー/例外/Fatal を自動キャプチャし12カテゴリに分類 |
| 2 | パフォーマンス可視化 | エンジン別実行時間・メモリ使用量をリクエスト単位で計測 |
| 3 | セキュリティ監視 | ログイン失敗・ロックアウト・レート制限・SSRF ブロックを集計 |
| 4 | プライバシー保護 | PII マスク・センシティブキー除外・匿名 UUID 識別 |
| 5 | 運用安定性 | ログローテーション・破損自動復旧・サーキットブレーカー |

### 動作原則

- **デフォルト有効** — エンドユーザーがダッシュボードから無効化可能
- **リアルタイム送信** — 毎リクエストで未送信分を開発元エンドポイントへ POST
- **14日間強制保存** — 送信後もサーバ内にログを保持、14日経過で古い順に自動削除
- **データベース不要** — JSON ファイルのみで完結（プラットフォーム方針準拠）

---

## 2. ファイル構成

```
engines/
├─ DiagnosticEngine.php          # エンジン本体（単一クラス）
├─ AdminEngine/
│  ├─ dashboard.html             # ダッシュボード UI（診断セクション含む）
│  └─ dashboard.css              # 診断 UI 用スタイル
└─ JsEngine/
   └─ diagnostics.js             # 診断ダッシュボード制御スクリプト

data/settings/
├─ diagnostics.json              # 設定ファイル（有効/レベル/インストールID等）
└─ diagnostics_log.json          # ログファイル（errors/custom/daily_summary）
   ├─ diagnostics_log.json.1     # アーカイブ世代1
   ├─ diagnostics_log.json.2     # アーカイブ世代2
   └─ diagnostics_log.json.3     # アーカイブ世代3
```

---

## 3. クラス設計

### 3.1 定数定義

```
┌─────────────────────────────────────────────────────────────┐
│ DiagnosticEngine (static class)                             │
├─────────────────────────────────────────────────────────────┤
│ CONFIG_FILE         = 'diagnostics.json'                    │
│ LOG_FILE            = 'diagnostics_log.json'                │
│ ENDPOINT            = 'https://telemetry.adlaire.com/…'    │
│ INTERVAL            = 0  (リアルタイム)                       │
│ LOG_RETENTION_DAYS  = 14                                    │
│ MAX_BUFFER_ITEMS    = 100                                   │
│ MAX_LOG_SIZE_BYTES  = 524288  (512KB)                       │
│ LOG_ARCHIVE_GENERATIONS = 3                                 │
│ RETRY_MAX           = 3                                     │
│ RETRY_BACKOFF       = [1, 2, 4]  秒                         │
│ RETRYABLE_CODES     = [429, 502, 503]                       │
│ CIRCUIT_BREAKER_THRESHOLD = 5                               │
│ CIRCUIT_BREAKER_DURATION  = 86400  (24h)                    │
│ VALID_LEVELS        = ['basic','extended','debug']          │
│ DEBUG_CATEGORIES    = [12分類]                               │
│ ENGINE_CLASSES      = [13エンジン]                            │
│ SENSITIVE_KEYS      = [12キー]                               │
└─────────────────────────────────────────────────────────────┘
```

### 3.2 静的プロパティ（リクエストスコープ）

| プロパティ | 型 | 用途 |
|---|---|---|
| `$timers` | array | 汎用タイマー（実行中） |
| `$timings` | array | 汎用タイマー（計測結果 ms） |
| `$memoryStart` | int | リクエスト開始時メモリ |
| `$engineTimers` | array | エンジン別タイマー（実行中） |
| `$engineTimings` | array | エンジン別計測結果（累積） |
| `$engineMemoryBefore` | array | エンジン起動前メモリ |
| `$engineCallCounts` | array | エンジン別呼び出し回数 |
| `$capturedTraces` | array | スタックトレース蓄積（上限50件） |

---

## 4. デバッグ診断カテゴリ（12分類）

PHPエラー・例外を自動分類するルールベースのカテゴリシステム。

| カテゴリ | 分類条件（エラー） | 分類条件（例外） |
|---|---|---|
| `syntax` | E_PARSE, E_COMPILE_ERROR/WARNING | ParseError |
| `runtime` | 上記以外のデフォルト | デフォルト |
| `logic` | E_USER_ERROR, assertion, invalid argument | LogicException, DomainException, InvalidArgumentException, LengthException |
| `semantic` | type error, undefined variable/property, must be of type | TypeError |
| `off_by_one` | undefined offset/index/array key, out of range | OutOfRangeException, OutOfBoundsException |
| `race_condition` | lock, deadlock, concurrent, flock | メッセージに lock/deadlock/concurrent |
| `memory` | allowed memory size, out of memory | OverflowException, メッセージに memory |
| `performance` | — (logSlowExecution経由) | — |
| `security` | permission denied, csrf, injection, xss | メッセージに permission/unauthorized/forbidden |
| `environment` | extension, function not found, class not found | メッセージに extension/not supported/class not found |
| `timing` | maximum execution time, timeout | メッセージに timeout/timed out |
| `integration` | curl, http, api, connection refused, webhook | メッセージに curl/connection/webhook/api/http |

### 分類優先順位

`classifyError()` は上から順に評価し、最初にマッチしたカテゴリを返す:

```
syntax → memory → timing → off_by_one → semantic → race_condition
→ security → integration → environment → logic → runtime(default)
```

---

## 5. 収集レベル設計

### 5.1 レベル階層

```
┌────────────────────────────────────────────────┐
│ debug                                          │
│  ┌──────────────────────────────────────────┐  │
│  │ extended                                 │  │
│  │  ┌───────────────────────────────────┐   │  │
│  │  │ basic (デフォルト)                  │   │  │
│  │  │  install_id, versions, engines    │   │  │
│  │  │  error_count, error_summary       │   │  │
│  │  │  debug_category_summary           │   │  │
│  │  │  security_summary                 │   │  │
│  │  └───────────────────────────────────┘   │  │
│  │  + recent_errors (50件, PIIマスク済)      │  │
│  │  + recent_logs (30件)                    │  │
│  │  + performance (memory, disk)            │  │
│  │  + timings, engine_timings               │  │
│  └──────────────────────────────────────────┘  │
│  + full_errors (20件, ファイルパス含む)          │
│  + category_breakdown (12カテゴリ全件集計)       │
│  + category_recent (各カテゴリ最新5件)           │
│  + traced_errors (スタックトレース付き20件)       │
│  + captured_traces (手動キャプチャ)              │
│  + engine_timings (全詳細)                     │
│  + php_config, environment, memory_detail      │
└────────────────────────────────────────────────┘
```

### 5.2 basic 収集フィールド

| フィールド | 型 | 説明 |
|---|---|---|
| `install_id` | string | 匿名 UUID v4 |
| `ap_version` | string | AP_VERSION 定数値 |
| `php_version` | string | PHP_VERSION |
| `os` | string | PHP_OS_FAMILY |
| `sapi` | string | PHP_SAPI |
| `engines` | string[] | 有効エンジンクラス名一覧 |
| `error_count` | int | エラー総数 |
| `error_summary` | object | エラータイプ別件数 `{E_WARNING: 3, ...}` |
| `debug_category_summary` | object | 12カテゴリ別件数 `{syntax: 0, runtime: 2, ...}` |
| `custom_log_count` | int | カスタムログ総数 |
| `security_summary` | object | セキュリティイベント集計 |

### 5.3 extended 追加フィールド

| フィールド | 型 | 説明 |
|---|---|---|
| `recent_errors` | array | 直近50件（PIIマスク済・ファイルパス除外） |
| `recent_logs` | array | 直近30件のカスタムログ |
| `performance.memory_peak` | int | ピークメモリ使用量（バイト） |
| `performance.memory_peak_human` | string | 人間可読形式 |
| `performance.disk_free` | int | ディスク残容量（バイト） |
| `timings` | object | リクエスト計測結果 `{timings_ms, memory_start, memory_peak}` |
| `engine_timings` | object | エンジン別 `{total_ms, calls, methods}` |
| `security` | object | セキュリティサマリー |

### 5.4 debug 追加フィールド

| フィールド | 型 | 説明 |
|---|---|---|
| `full_errors` | array | 直近20件（ファイルパス含む完全版） |
| `category_breakdown` | object | 12カテゴリ別全件集計 |
| `category_recent` | object | 各カテゴリ最新5件 `{syntax: [...], runtime: [...], ...}` |
| `traced_errors` | array | スタックトレース付きエラー直近20件 |
| `captured_traces` | array | `captureTrace()` で蓄積されたトレース |
| `engine_timings` | object | `{detail, engines, summary}` の全階層 |
| `php_config` | object | PHP主要設定7項目 |
| `environment` | object | PHP/OS/拡張/Zend詳細 |
| `memory_detail` | object | `{current, peak, limit, limit_bytes, usage_ratio}` |
| `debug_categories` | string[] | 12カテゴリ定義一覧 |

---

## 6. データフロー

### 6.1 エラーキャプチャフロー

```
PHP Error/Exception/Fatal
        │
        ▼
registerErrorHandler()
        │
        ├─ set_error_handler()        ─→ classifyError()   ─→ 12カテゴリ判定
        ├─ set_exception_handler()    ─→ classifyException()─→ 12カテゴリ判定
        └─ register_shutdown_function()─→ classifyError()   ─→ 12カテゴリ判定
                                         captureRuntimeSnapshot()
        │
        ▼
   logError(entry)
        │
        ├─ rotateIfNeeded()          ─→ 512KB超でローテーション
        ├─ safeJsonRead()            ─→ JSON破損時は .corrupt バックアップ
        ├─ purgeExpiredEntries()      ─→ 14日超エントリ削除
        ├─ recordDailySummary()       ─→ 日別カウンター更新（30日保持）
        └─ json_write()              ─→ diagnostics_log.json に永続化
```

### 6.2 カスタムログフロー

```
エンジン内呼び出し
  │
  ├─ DiagnosticEngine::log(category, message, context)
  ├─ DiagnosticEngine::logDebugEvent(category, message, context)
  ├─ DiagnosticEngine::logSlowExecution(label, elapsed, threshold)
  ├─ DiagnosticEngine::checkMemoryUsage()
  ├─ DiagnosticEngine::logRaceCondition(resource, detail)
  ├─ DiagnosticEngine::logIntegrationError(service, httpCode, detail)
  ├─ DiagnosticEngine::logEnvironmentIssue(issue, context)
  └─ DiagnosticEngine::logTimingIssue(operation, elapsed, limit)
        │
        ▼
   log(category, message, context)
        │
        ├─ isEnabled() チェック
        ├─ rotateIfNeeded()
        ├─ safeJsonRead()
        ├─ sanitizeMessage()         ─→ PII マスク
        ├─ stripSensitiveKeys()      ─→ センシティブキー除外
        ├─ purgeExpiredEntries()
        ├─ recordDailySummary()
        └─ json_write()
```

### 6.3 送信フロー

```
maybeSend()  ←── 毎リクエスト呼び出し
    │
    ├─ isEnabled() → false → 終了
    ├─ purgeExpiredLogs()
    ├─ サーキットブレーカー発動中 → 終了
    ├─ 送信間隔チェック
    │
    ├─ collectWithUnsent(lastSent)
    │   ├─ collect()  ─→ レベルに応じて basic/extended/debug
    │   └─ 未送信エントリ抽出（lastSent 以降の errors/custom）
    │
    ├─ 新規ログなし & 環境データ24h以内送信済 → 終了
    │
    └─ send(data)
        │
        ├─ JSON エンコード
        ├─ cURL POST → ENDPOINT
        │   ├─ 成功(2xx) → last_sent更新・失敗カウントリセット
        │   ├─ 429/502/503 → 指数バックオフリトライ (1s→2s→4s, 最大3回)
        │   └─ その他エラー → 即失敗
        │
        └─ 失敗時: consecutive_failures++
            └─ 5回連続失敗 → サーキットブレーカー発動（24h送信停止）
```

---

## 7. ストレージ設計

### 7.1 diagnostics.json（設定ファイル）

```json
{
    "enabled": true,
    "level": "basic",
    "install_id": "550e8400-e29b-41d4-a716-446655440000",
    "last_sent": "2026-03-10T12:00:00+09:00",
    "last_env_sent": "2026-03-10T00:00:00+09:00",
    "first_run_notice_shown": true,
    "send_interval": 0,
    "consecutive_failures": 0,
    "circuit_breaker_until": 0
}
```

### 7.2 diagnostics_log.json（ログファイル）

```json
{
    "errors": [
        {
            "type": "E_WARNING",
            "debug_category": "integration",
            "message": "curl: connection refused to [IP]",
            "file": "/engines/WebhookEngine.php",
            "line": 142,
            "timestamp": "2026-03-10T10:30:00+09:00",
            "stack_trace": [
                {"file": "/engines/WebhookEngine.php", "line": 142, "function": "send", "class": "WebhookEngine"}
            ]
        }
    ],
    "custom": [
        {
            "category": "engine",
            "message": "CacheEngine 書き込み失敗",
            "timestamp": "2026-03-10T10:35:00+09:00",
            "context": {"endpoint": "/api/pages"}
        }
    ],
    "daily_summary": {
        "2026-03-10": {
            "errors": 5,
            "security": 1,
            "engine": 2,
            "other": 0,
            "debug_syntax": 0,
            "debug_runtime": 3,
            "debug_logic": 0,
            "debug_semantic": 0,
            "debug_off_by_one": 0,
            "debug_race_condition": 0,
            "debug_memory": 0,
            "debug_performance": 0,
            "debug_security": 1,
            "debug_environment": 0,
            "debug_timing": 0,
            "debug_integration": 1
        }
    }
}
```

### 7.3 ログローテーション

```
条件: filesize(diagnostics_log.json) >= 512KB

実行:
  diagnostics_log.json.3  → 削除
  diagnostics_log.json.2  → .3 にリネーム
  diagnostics_log.json.1  → .2 にリネーム
  diagnostics_log.json    → .1 にリネーム
  新規 diagnostics_log.json 作成（daily_summary は旧ファイルから引き継ぎ）
```

### 7.4 破損検知・自動復旧

```
safeJsonRead() 実行時:
  ├─ ファイル不在      → {errors:[], custom:[]} を返却
  ├─ 読み取り失敗      → {errors:[], custom:[]} を返却
  ├─ JSONパース成功    → データを返却
  └─ JSONパース失敗    → ① error_log() で記録
                         ② 破損ファイルを .corrupt.{timestamp} にリネーム
                         ③ {errors:[], custom:[]} を返却（自動復旧）
```

---

## 8. エンジン別実行時間トラッカー

### 計測構造

```
startEngineTimer('TemplateEngine', 'render')
    │
    ▼  hrtime(true) 記録 + memory_get_usage 記録
   ...処理実行...
    │
    ▼
stopEngineTimer('TemplateEngine', 'render')
    │
    ├─ 経過時間(ms) 計算
    ├─ メモリ差分 計算
    └─ 累積記録:
        $engineTimings['TemplateEngine::render'] = {
            total_ms:           累積経過時間,
            calls:              呼び出し回数,
            max_ms:             最大経過時間,
            min_ms:             最小経過時間,
            memory_delta_total: 累積メモリ差分
        }
```

### getEngineTimings() 出力構造

```json
{
    "detail": {
        "TemplateEngine::render": {
            "total_ms": 45.23,
            "calls": 3,
            "avg_ms": 15.08,
            "max_ms": 22.10,
            "min_ms": 8.50,
            "memory_delta": 131072,
            "memory_delta_human": "128 KB"
        }
    },
    "engines": {
        "TemplateEngine": {
            "total_ms": 45.23,
            "calls": 3,
            "methods": { "TemplateEngine::render": { "..." } }
        }
    },
    "summary": {
        "total_engines_tracked": 4,
        "total_calls": 12,
        "engine_call_counts": { "TemplateEngine": 3, "CacheEngine": 5 }
    }
}
```

---

## 9. エラートレンド追跡

### 日別サマリー

- `recordDailySummary()` — `logError()` / `log()` の呼び出し時にカウンター更新
- キー: `YYYY-MM-DD` 形式の日付文字列
- カウンター: `errors`, `security`, `engine`, `other` + `debug_{category}` × 12
- 保持期間: 30日（超過分は自動削除）

### トレンド方向判定

```
getTrends(days=7):
  直近3日平均 vs 前4日平均
    ├─ 直近 > 前期 × 1.5  → 'increasing'
    ├─ 前期 > 直近 × 1.5  → 'decreasing'
    └─ それ以外            → 'stable'
```

### 急増検知（スパイク）

```
detectSpike():
  当日合計 > 過去7日平均 × 3  → true
  → healthCheck() の status を 'warning' に格上げ
```

---

## 10. プライバシー・セキュリティ設計

### 10.1 PII マスク処理（sanitizeMessage）

| 対象 | 正規表現 | 置換後 |
|---|---|---|
| メールアドレス | `/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/` | `[EMAIL]` |
| IPv4 アドレス | `/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/` | `[IP]` |
| Unix ユーザーパス | `#/home/[^/]+/#` | `/home/[USER]/` |
| Windows ユーザーパス | `#C:\\Users\\[^\\]+\\#i` | `C:\Users\[USER]\` |

### 10.2 パスサニタイズ（sanitizePath）

1. `DOCUMENT_ROOT` からの相対パスに変換（可能な場合）
2. ホームディレクトリ部分をマスク（Unix / Windows）

### 10.3 センシティブキー除外（stripSensitiveKeys）

再帰的に以下のキーを含むフィールドを `[REDACTED]` に置換:

```
password, password_hash, token, secret, api_key, apikey,
authorization, cookie, session, csrf, private_key, credentials
```

### 10.4 識別方式

- 匿名 UUID v4（`install_id`）のみで識別
- IP アドレス・ドメイン名は送信しない
- cURL ヘッダに `X-AP-Install-ID` を付与

---

## 11. 送信制御設計

### 11.1 サーキットブレーカー

```
状態遷移:
  CLOSED (通常運用)
    └─ 送信失敗 → consecutive_failures++
        └─ 5回連続失敗 → OPEN (24時間送信停止)
                            └─ 24時間経過 → HALF-OPEN (次回送信試行)
                                ├─ 成功 → CLOSED (カウントリセット)
                                └─ 失敗 → OPEN (再度24時間停止)
```

### 11.2 リトライポリシー

| 試行 | 待機時間 | 対象HTTPコード |
|---|---|---|
| 1回目 | 即時 | — |
| 2回目 | 1秒 | 429, 502, 503 |
| 3回目 | 2秒 | 429, 502, 503 |
| 4回目 | 4秒 | 429, 502, 503 |

- 上記以外のHTTPエラーコードは即座に失敗扱い
- cURL タイムアウト: 接続3秒 / 応答5秒

### 11.3 送信ペイロード

```
POST https://telemetry.adlaire.com/v1/report
Content-Type: application/json
User-Agent: AdlairePlatform/{version}
X-AP-Install-ID: {uuid}

{
    "install_id": "...",
    "ap_version": "...",
    "collect_level": "basic|extended|debug",
    "collected_at": "2026-03-10T12:00:00+09:00",
    ... レベルに応じた収集データ ...,
    "unsent_errors": [...],
    "unsent_logs": [...],
    "total_errors_held": 42,
    "total_logs_held": 15,
    "retention_days": 14
}
```

---

## 12. ヘルスチェック設計

### ステータス判定ロジック

```
status 判定（優先順位）:
  1. errors_24h > 50           → 'critical'
  2. disk_free < 100MB         → 'critical'
  3. errors_24h > 10           → 'warning'
  4. detectSpike() = true      → 'warning'
  5. 上記いずれにも該当しない    → 'ok'
```

### 出力フィールド

| フィールド | 型 | 説明 |
|---|---|---|
| `status` | string | `ok` / `warning` / `critical` |
| `version` | string | AP_VERSION |
| `php` | string | PHP_VERSION |
| `uptime_check` | bool | 常に `true` |
| `diagnostics.errors_24h` | int | 直近24時間のエラー件数 |
| `diagnostics.disk_free_mb` | int\|null | ディスク残容量 (MB) |
| `diagnostics.memory_peak_mb` | float | ピークメモリ (MB) |
| `diagnostics.last_diagnostic_sent` | string | 最終送信日時 |
| `diagnostics.log_file_size_kb` | float | ログファイルサイズ (KB) |

`detailed=true` 時の追加:

| フィールド | 型 | 説明 |
|---|---|---|
| `security` | object | セキュリティサマリー |
| `timings` | object | 計測結果 |
| `diagnostics.error_count` | int | エラー総数 |
| `diagnostics.log_count` | int | カスタムログ総数 |

---

## 13. 管理画面 API 設計

全アクションは POST で `ap_action` パラメータにより振り分け。CSRF トークン必須。

### エンドポイント一覧

| ap_action | 必要権限 | 説明 | レスポンス |
|---|---|---|---|
| `diag_set_enabled` | admin | 有効/無効切替 | `{ok, data: {enabled}}` |
| `diag_set_level` | admin | 収集レベル変更 | `{ok, data: {level}}` |
| `diag_preview` | logged_in | 送信データプレビュー | `{ok, data: {collect結果}}` |
| `diag_send_now` | admin | 手動即時送信 | `{ok, data: {message, sent_at}}` |
| `diag_clear_logs` | admin | ログクリア | `{ok, data: {message}}` |
| `diag_get_logs` | logged_in | 全ログ取得 | `{ok, data: {errors, custom, daily_summary}}` |
| `diag_get_summary` | logged_in | サマリー取得 | `{ok, data: {統計+設定+トレンド}}` |
| `diag_health` | logged_in | ヘルスチェック | `{ok, data: {healthCheck結果}}` |

### エラーレスポンス

```json
HTTP 401: {"ok": false, "error": "未ログイン"}
HTTP 403: {"ok": false, "error": "管理者権限が必要です"}
HTTP 400: {"ok": false, "error": "無効なレベルです: xxx"}
```

---

## 14. ダッシュボード UI 設計

### 14.1 画面構成

```
┌──────────────────────────────────────────────────┐
│ [初回通知バナー]                                    │
│  「本システムは診断データを開発元に送信します...」      │
├──────────────────────────────────────────────────┤
│                                                  │
│  ヘルスステータス: [●OK] / [●WARNING] / [●CRITICAL]│
│                                                  │
│  診断データ送信: [有効 ○ / ● 無効]                   │
│  収集レベル:    [basic ▼]                          │
│                                                  │
│  エラー数: 42    カスタムログ数: 15                   │
│  [サーキットブレーカー警告 (表示時のみ)]              │
│                                                  │
│  ── セキュリティサマリー ──                          │
│  ログイン失敗: 3  ロックアウト: 1                    │
│  レート制限: 0    SSRF ブロック: 0                   │
│                                                  │
│  ── パフォーマンスプロファイラ ──                     │
│  TemplateEngine::render  ████████░░  22.1ms       │
│  CacheEngine::fetch      ████░░░░░░  12.3ms       │
│  ...                                             │
│                                                  │
│  ── エラートレンド (7日間) ──                        │
│  03/04  ██         2                             │
│  03/05  ████       4                             │
│  03/06  ██████     6   ↑ increasing              │
│  03/07  ████████  8                              │
│  03/08  ██████████ 10                            │
│  03/09  ██████     6                             │
│  03/10  ████       4                             │
│                                                  │
│  ── エラー種別 ──                                   │
│  E_WARNING: 25  E_NOTICE: 12  E_ERROR: 5         │
│                                                  │
│  ── 直近のエラー ──                                 │
│  ┌────────┬────────┬───────────────────┐          │
│  │ 日時   │ 種別   │ メッセージ          │          │
│  ├────────┼────────┼───────────────────┤          │
│  │ ...    │ ...    │ ...               │          │
│  └────────┴────────┴───────────────────┘          │
│                                                  │
│  ── 直近のカスタムログ ──                            │
│  ┌────────┬──────────┬──────────────────┐         │
│  │ 日時   │ カテゴリ  │ メッセージ        │         │
│  ├────────┼──────────┼──────────────────┤         │
│  │ ...    │ ...      │ ...              │         │
│  └────────┴──────────┴──────────────────┘         │
│                                                  │
│  [プレビュー] [今すぐ送信] [全ログ表示] [ログクリア]   │
│                                                  │
│  インストールID: 550e8400-e29b-41d4-...           │
├──────────────────────────────────────────────────┤
│ [結果メッセージ表示エリア]                           │
└──────────────────────────────────────────────────┘
```

### 14.2 CSS クラス体系

| クラス | 用途 |
|---|---|
| `.ap-diag-badge` | エラータイプバッジ |
| `.ap-diag-table` | エラー/ログテーブル |
| `.ap-diag-timing-row` | プロファイラ行 |
| `.ap-diag-timing-label` | プロファイララベル |
| `.ap-diag-timing-bar-bg` | プロファイラバー背景 |
| `.ap-diag-timing-bar` | プロファイラバー（塗り） |
| `.ap-diag-timing-value` | プロファイラ値 |
| `.ap-diag-trend-chart` | トレンドチャートコンテナ |
| `.ap-diag-trend-bar-wrap` | トレンドバーラッパー |
| `.ap-diag-trend-bar` | トレンドバー（高さ＝最大値比率） |
| `.ap-diag-trend-date` | トレンド日付ラベル |
| `.ap-diag-trend-count` | トレンド件数表示 |
| `.ap-diag-trend-info` | トレンド方向表示 |

### 14.3 diagnostics.js 関数一覧

| 関数 | 説明 |
|---|---|
| `init()` | 初期化（loadSummary + loadHealth + bindEvents） |
| `loadSummary()` | `diag_get_summary` を fetch し各セクションを描画 |
| `loadHealth()` | `diag_health` を fetch しステータスバッジを更新 |
| `renderSecuritySummary()` | セキュリティサマリーを描画 |
| `renderTimings()` | パフォーマンスプロファイラバーを描画 |
| `renderTrends()` | 7日間トレンドチャート + 方向インジケータを描画 |
| `renderRecentErrors()` | 直近エラーテーブルを描画 |
| `renderRecentLogs()` | 直近カスタムログテーブルを描画 |
| `bindEvents()` | 全ボタン・トグル・セレクタにイベントハンドラをバインド |

---

## 15. 外部エンジン連携

### CacheEngine 連携

```php
// CacheEngine::store() — 書き込み失敗時
$result = file_put_contents($path, $content, LOCK_EX);
if ($result === false && class_exists('DiagnosticEngine')) {
    DiagnosticEngine::log('engine', 'CacheEngine 書き込み失敗', ['endpoint' => $endpoint]);
}
```

### CollectionEngine 連携

```php
// 不正スラッグ・重複・不正ディレクトリ名で失敗時
if (class_exists('DiagnosticEngine')) {
    DiagnosticEngine::log('engine', 'コレクション作成失敗: {理由}', ['name' => $name]);
}
```

### シャットダウン時スナップショット

```php
// register_shutdown_function() 内で自動実行
captureRuntimeSnapshot()
    → log('runtime_snapshot', 'シャットダウン時スナップショット', {
        memory_usage, memory_peak,
        timings, engine_timings, engine_summary,
        traced_count
    })
```

---

## 16. public メソッド一覧

### 設定管理

| メソッド | シグネチャ | 戻り値 |
|---|---|---|
| `loadConfig` | `(): array` | 設定配列（初回自動生成・UUID発行） |
| `saveConfig` | `(array $config): void` | — |
| `isEnabled` | `(): bool` | 有効か |
| `getLevel` | `(): string` | 収集レベル |
| `setEnabled` | `(bool $enabled): void` | — |
| `setLevel` | `(string $level): void` | — |
| `getInstallId` | `(): string` | インストールID |

### パフォーマンス計測

| メソッド | シグネチャ | 戻り値 |
|---|---|---|
| `startTimer` | `(string $label): void` | — |
| `stopTimer` | `(string $label): void` | — |
| `getTimings` | `(): array` | `{timings_ms, memory_start, memory_peak, memory_peak_human}` |

### エンジン別計測

| メソッド | シグネチャ | 戻り値 |
|---|---|---|
| `startEngineTimer` | `(string $engine, string $method = ''): void` | — |
| `stopEngineTimer` | `(string $engine, string $method = ''): ?float` | 経過ms（未開始時null） |
| `getEngineTimings` | `(): array` | `{detail, engines, summary}` |

### スタックトレース

| メソッド | シグネチャ | 戻り値 |
|---|---|---|
| `captureTrace` | `(string $label, int $depth = 15): void` | — |
| `getCapturedTraces` | `(): array` | 蓄積トレース一覧 |

### エラー・ログ

| メソッド | シグネチャ | 戻り値 |
|---|---|---|
| `registerErrorHandler` | `(): void` | — |
| `logError` | `(array $entry): void` | — |
| `log` | `(string $category, string $message, array $context = []): void` | — |
| `logDebugEvent` | `(string $category, string $message, array $context = []): void` | — |
| `logSlowExecution` | `(string $label, float $elapsed, float $threshold = 1000.0): void` | — |
| `checkMemoryUsage` | `(): void` | — |
| `logRaceCondition` | `(string $resource, string $detail = ''): void` | — |
| `logIntegrationError` | `(string $service, int $httpCode = 0, string $detail = ''): void` | — |
| `logEnvironmentIssue` | `(string $issue, array $context = []): void` | — |
| `logTimingIssue` | `(string $operation, float $elapsed, float $limit): void` | — |

### ログ管理

| メソッド | シグネチャ | 戻り値 |
|---|---|---|
| `rotateIfNeeded` | `(): void` | — |
| `purgeExpiredLogs` | `(): void` | — |
| `clearLogs` | `(): void` | — |
| `getAllLogs` | `(): array` | ログ全体 |

### データ収集・送信

| メソッド | シグネチャ | 戻り値 |
|---|---|---|
| `collect` | `(): array` | レベルに応じた診断データ |
| `collectBasic` | `(): array` | Level 1 データ |
| `collectExtended` | `(): array` | Level 2 データ |
| `collectDebug` | `(): array` | Level 3 データ |
| `preview` | `(): array` | 送信データプレビュー |
| `maybeSend` | `(): void` | 自動送信（サーキットブレーカー付） |
| `send` | `(array $data): bool` | cURL 送信（リトライ付） |

### トレンド・ヘルスチェック

| メソッド | シグネチャ | 戻り値 |
|---|---|---|
| `getTrends` | `(int $days = 7): array` | `{days, trend_direction, spike_detected}` |
| `getLogSummary` | `(): array` | ログ統計サマリー |
| `getSecuritySummary` | `(): array` | セキュリティイベント集計 |
| `healthCheck` | `(bool $detailed = false): array` | ヘルスチェック結果 |

### UI・通知

| メソッド | シグネチャ | 戻り値 |
|---|---|---|
| `handle` | `(): void` | POST アクション振り分け |
| `shouldShowNotice` | `(): bool` | 初回通知表示要否 |
| `markNoticeShown` | `(): void` | 通知表示済みマーク |

---

## 17. 制約・制限事項

| 項目 | 制限値 |
|---|---|
| ログファイルサイズ上限 | 512KB（超過でローテーション） |
| アーカイブ世代数 | 3世代 |
| ログ保持期間 | 14日間（強制） |
| 日別サマリー保持期間 | 30日間 |
| スタックトレース蓄積上限 | リクエストあたり50件 |
| スタックトレース深度 | extended: 10フレーム / debug: 20フレーム |
| recent_errors 件数 | 50件（extended） / 20件（debug full） |
| recent_logs 件数 | 30件 |
| category_recent 件数 | 各カテゴリ5件 |
| cURL 接続タイムアウト | 3秒 |
| cURL 応答タイムアウト | 5秒 |
| サーキットブレーカー閾値 | 5回連続失敗 |
| サーキットブレーカー停止期間 | 24時間 |
| 環境データ送信間隔 | 24時間に1回（新規ログなし時） |
