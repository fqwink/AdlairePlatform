/**
 * Adlaire Infrastructure Services (AIS) — Interface Definitions
 *
 * アプリケーションコンテキスト、i18n、診断、デプロイメント、
 * Git 連携、アップデート管理のインターフェースを定義する。
 *
 * @package AIS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

// ============================================================================
// App Context
// ============================================================================

export interface AppContextInterface {
  get<T = unknown>(key: string, defaultValue?: T): T;
  set(key: string, value: unknown): void;
  has(key: string): boolean;
  all(): Record<string, unknown>;
  loadFromFile(path: string): Promise<void>;
  saveToFile(path: string): Promise<void>;
  validate(schema: Record<string, string>): string[];
}

export interface AppContextPaths {
  dataDir(): string;
  settingsDir(): string;
  contentDir(): string;
}

export interface HostInfo {
  readonly host: string;
  readonly rp: string;
}

// ============================================================================
// i18n
// ============================================================================

export interface I18nInterface {
  init(basePath?: string): Promise<void>;
  getLocale(): LocaleId;
  t(key: string, params?: Record<string, string>): string;
  all(): Record<string, string>;
  allNested(): TranslationDict;
  htmlLang(): string;
}

// ============================================================================
// Service Provider / Container
// ============================================================================

export interface ServiceProviderInterface {
  register(): void;
  boot(): void;
  provides(): string[];
}

export interface ServiceContainerInterface {
  bind(name: string, factory: (...args: unknown[]) => unknown): void;
  singleton(name: string, factory: (...args: unknown[]) => unknown): void;
  make<T = unknown>(name: string): T;
  has(name: string): boolean;
  register(provider: ServiceProviderInterface): void;
  boot(): void;
}

// ============================================================================
// Diagnostics
// ============================================================================

export interface DiagnosticsManagerInterface {
  log(channel: string, message: string, context?: Record<string, unknown>): void;
  logEnvironmentIssue(message: string, context?: Record<string, unknown>): void;
  logSlowExecution(label: string, elapsed: number, threshold: number): void;
  setEnabled(enabled: boolean): void;
  setLevel(level: "basic" | "extended" | "debug"): void;
  healthCheck(detailed?: boolean): Promise<HealthCheckResult>;
  collectWithUnsent(lastSent: string): Promise<DiagnosticsReport>;
  send(data: DiagnosticsReport): Promise<boolean>;
  loadConfig(): Promise<DiagnosticsConfig>;
  saveConfig(config: DiagnosticsConfig): Promise<void>;
  clearLogs(): Promise<void>;
  purgeExpiredLogs(): Promise<void>;
  getTimings(): Record<string, number>;
  getEngineTimings(): Record<string, number>;
}

export interface DiagnosticsConfig {
  readonly enabled: boolean;
  readonly level: "basic" | "extended" | "debug";
  readonly autoSend: boolean;
  readonly retentionDays: number;
}

// ============================================================================
// API Cache
// ============================================================================

export interface ApiCacheInterface {
  remember<T>(key: string, ttl: number, callback: () => Promise<T>): Promise<T>;
  invalidateContent(): void;
}

// ============================================================================
// Git Service
// ============================================================================

export interface GitServiceInterface {
  loadConfig(): Promise<GitServiceConfig>;
  saveConfig(config: GitServiceConfig): Promise<void>;
  testConnection(): Promise<GitResult>;
  pull(): Promise<GitResult>;
  push(message?: string): Promise<GitResult>;
  log(limit?: number): Promise<GitLogEntry[]>;
  status(): Promise<GitStatus>;
  createPreviewBranch(name: string): Promise<GitResult>;
}

export interface GitServiceConfig {
  readonly repoUrl: string;
  readonly branch: string;
  readonly username?: string;
  readonly token?: string;
  readonly autoSync?: boolean;
}

// ============================================================================
// Update Service
// ============================================================================

export interface UpdateServiceInterface {
  checkUpdate(): Promise<UpdateInfo>;
  checkEnvironment(): Promise<EnvironmentCheck>;
  executeApplyUpdate(): Promise<UpdateApplyResult>;
  executeRollback(name: string): Promise<{ success: boolean; error?: string }>;
  executeDeleteBackup(name: string): Promise<{ success: boolean; error?: string }>;
  listBackups(): Promise<BackupEntry[]>;
}

export interface EnvironmentCheck {
  readonly runtimeVersion: string;
  readonly requiredVersion: string;
  readonly writable: boolean;
  readonly diskSpace: number;
  readonly issues: string[];
}

export interface UpdateApplyResult {
  readonly success: boolean;
  readonly fromVersion: string;
  readonly toVersion: string;
  readonly backupName?: string;
  readonly error?: string;
}

// ============================================================================
// Updater (Low-level)
// ============================================================================

export interface UpdaterInterface {
  checkForUpdate(currentVersion: string): Promise<UpdateInfo | null>;
  applyUpdate(zipUrl: string, targetVersion: string): Promise<boolean>;
  rollback(backupName: string): Promise<boolean>;
  getLastError(): string;
}

// ============================================================================
// AIS-specific Types
// ============================================================================

/**
 * サイト設定
 */
export interface SiteSettings {
  readonly title: string;
  readonly description: string;
  readonly url: string;
  readonly language: string;
  readonly theme: string;
  readonly timezone?: string;
  readonly perPage?: number;
  readonly cleanUrls?: boolean;
  readonly minifyHtml?: boolean;
  readonly [key: string]: unknown;
}

/**
 * 診断レポート
 */
export interface DiagnosticsReport {
  readonly events: DiagEvent[];
  readonly summary: {
    readonly total: number;
    readonly byChannel: Record<string, number>;
    readonly byLevel: Record<string, number>;
  };
  readonly collectedAt: string;
}

/**
 * 診断イベント
 */
export interface DiagEvent {
  readonly channel: string;
  readonly level: DiagLevel;
  readonly message: string;
  readonly context: Record<string, unknown>;
  readonly timestamp: string;
}

/**
 * 診断レベル
 */
export type DiagLevel = "debug" | "info" | "warning" | "error" | "critical";

/**
 * ヘルスチェック結果
 */
export interface HealthCheckResult {
  readonly status: "ok" | "degraded" | "error";
  readonly version: string;
  readonly runtime: string;
  readonly time: string;
  readonly checks?: Record<
    string,
    {
      readonly status: "ok" | "warning" | "error";
      readonly message: string;
      readonly value?: unknown;
    }
  >;
}

/**
 * Git 操作結果
 */
export interface GitResult {
  readonly success: boolean;
  readonly output: string;
  readonly error?: string;
}

/**
 * Git ステータス
 */
export interface GitStatus {
  readonly branch: string;
  readonly clean: boolean;
  readonly modified: string[];
  readonly untracked: string[];
  readonly ahead: number;
  readonly behind: number;
}

/**
 * Git コミットログ
 */
export interface GitLogEntry {
  readonly hash: string;
  readonly message: string;
  readonly author: string;
  readonly date: string;
}

/**
 * アップデート情報
 */
export interface UpdateInfo {
  readonly available: boolean;
  readonly currentVersion: string;
  readonly latestVersion: string;
  readonly releaseNotes?: string;
  readonly downloadUrl?: string;
}

/**
 * バックアップエントリ
 */
export interface BackupEntry {
  readonly name: string;
  readonly createdAt: string;
  readonly size: number;
  readonly version: string;
}

/**
 * ロケール識別子
 */
export type LocaleId = "ja" | "en" | string;

/**
 * 翻訳辞書
 */
export interface TranslationDict {
  [key: string]: string | TranslationDict;
}
