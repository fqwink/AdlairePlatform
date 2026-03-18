/**
 * Adlaire Infrastructure Services (AIS) — Utilities Module
 *
 * 診断マネージャ、API キャッシュ、Git サービス、アップデートサービスを提供する。
 * PHP AIS.Utilities.php からの移植。
 *
 * @package AIS
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { AdlaireClient } from "../ACS/ACS.d.ts";

import type {
  ApiCacheInterface,
  BackupEntry,
  DiagEvent,
  DiagnosticsConfig,
  DiagnosticsManagerInterface,
  DiagnosticsReport,
  EnvironmentCheck,
  GitLogEntry,
  GitResult,
  GitServiceConfig,
  GitServiceInterface,
  GitStatus,
  HealthCheckResult,
  UpdateApplyResult,
  UpdateInfo,
  UpdateServiceInterface,
} from "./AIS.Interface.ts";

// ============================================================================
// DiagnosticsManager — 診断マネージャ
// ============================================================================

export class DiagnosticsManager implements DiagnosticsManagerInterface {
  private enabled = false;
  private level: "basic" | "extended" | "debug" = "basic";
  private events: DiagEvent[] = [];
  private timings: Record<string, number> = {};
  private readonly maxEvents = 10000;

  constructor(private readonly client: AdlaireClient) {}

  log(channel: string, message: string, context?: Record<string, unknown>): void {
    if (!this.enabled) return;

    if (this.events.length >= this.maxEvents) {
      // Drop oldest 10% to avoid constant shifting
      this.events = this.events.slice(Math.floor(this.maxEvents * 0.1));
    }

    this.events.push({
      channel,
      level: "info",
      message,
      context: context ?? {},
      timestamp: new Date().toISOString(),
    });
  }

  logEnvironmentIssue(message: string, context?: Record<string, unknown>): void {
    this.events.push({
      channel: "environment",
      level: "warning",
      message,
      context: context ?? {},
      timestamp: new Date().toISOString(),
    });
  }

  logSlowExecution(label: string, elapsed: number, threshold: number): void {
    if (elapsed > threshold) {
      this.log("performance", `Slow execution: ${label}`, { elapsed, threshold });
    }
    this.timings[label] = elapsed;
  }

  setEnabled(enabled: boolean): void {
    this.enabled = enabled;
  }

  setLevel(level: "basic" | "extended" | "debug"): void {
    this.level = level;
  }

  async healthCheck(_detailed?: boolean): Promise<HealthCheckResult> {
    // ASS PHP サーバのヘルスチェックに委譲
    const resp = await this.client.http.get<HealthCheckResult>("/health");
    if (resp.ok && resp.data) {
      return resp.data;
    }

    // ASS 接続失敗時のフォールバック
    return {
      status: "degraded",
      version: "Ver.2.3-44",
      runtime: "deno (ASS unreachable)",
      time: new Date().toISOString(),
      checks: {
        server: { status: "error", message: "ASS server unreachable" },
      },
    };
  }

  collectWithUnsent(lastSent: string): Promise<DiagnosticsReport> {
    const cutoff = new Date(lastSent);
    const filtered = this.events.filter((e) => new Date(e.timestamp) > cutoff);

    const byChannel: Record<string, number> = {};
    const byLevel: Record<string, number> = {};
    for (const e of filtered) {
      byChannel[e.channel] = (byChannel[e.channel] ?? 0) + 1;
      byLevel[e.level] = (byLevel[e.level] ?? 0) + 1;
    }

    return Promise.resolve({
      events: filtered,
      summary: { total: filtered.length, byChannel, byLevel },
      collectedAt: new Date().toISOString(),
    });
  }

  async send(data: DiagnosticsReport): Promise<boolean> {
    const result = await this.client.http.post("/api/diagnostics", data);
    return result.ok;
  }

  async loadConfig(): Promise<DiagnosticsConfig> {
    const config = await this.client.storage.read<DiagnosticsConfig>(
      "diagnostic.json",
      "settings",
    );
    return config ?? {
      enabled: false,
      level: "basic",
      autoSend: false,
      retentionDays: 30,
    };
  }

  async saveConfig(config: DiagnosticsConfig): Promise<void> {
    await this.client.storage.write("diagnostic.json", config, "settings");
    this.enabled = config.enabled;
    this.level = config.level;
  }

  clearLogs(): Promise<void> {
    this.events = [];
    return Promise.resolve();
  }

  async purgeExpiredLogs(): Promise<void> {
    const config = await this.loadConfig();
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - config.retentionDays);
    const cutoffStr = cutoff.toISOString();
    this.events = this.events.filter((e) => e.timestamp >= cutoffStr);
  }

  getTimings(): Record<string, number> {
    return { ...this.timings };
  }

  getEngineTimings(): Record<string, number> {
    return { ...this.timings };
  }
}

// ============================================================================
// ApiCache — API レスポンスキャッシュ
// ============================================================================

export class ApiCache implements ApiCacheInterface {
  private cache = new Map<string, { value: unknown; expiresAt: number }>();
  private readonly maxSize: number;

  constructor(maxSize: number = 500) {
    this.maxSize = maxSize;
  }

  async remember<T>(key: string, ttl: number, callback: () => Promise<T>): Promise<T> {
    const cached = this.cache.get(key);
    if (cached && cached.expiresAt > Date.now()) {
      return cached.value as T;
    }

    const value = await callback();
    // Evict expired entries when approaching max size
    if (this.cache.size >= this.maxSize) {
      this.evictExpired();
    }
    // If still at max, remove oldest entry
    if (this.cache.size >= this.maxSize) {
      const firstKey = this.cache.keys().next().value;
      if (firstKey !== undefined) this.cache.delete(firstKey);
    }
    this.cache.set(key, { value, expiresAt: Date.now() + ttl * 1000 });
    return value;
  }

  invalidateContent(): void {
    for (const key of this.cache.keys()) {
      if (key.startsWith("content:") || key.startsWith("collection:")) {
        this.cache.delete(key);
      }
    }
  }

  private evictExpired(): void {
    const now = Date.now();
    for (const [key, entry] of this.cache) {
      if (entry.expiresAt <= now) {
        this.cache.delete(key);
      }
    }
  }
}

// ============================================================================
// GitService — Git 操作サービス
// ============================================================================

/**
 * GitService — ASS PHP サーバ経由で Git 操作を行う。
 *
 * Deno.Command による直接実行を廃止し、ACS 経由で ASS の Git API を呼び出す。
 */
export class GitService implements GitServiceInterface {
  constructor(private readonly client: AdlaireClient) {}

  async loadConfig(): Promise<GitServiceConfig> {
    return (await this.client.storage.read<GitServiceConfig>(
      "git.json",
      "settings",
    )) ?? { repoUrl: "", branch: "main" };
  }

  async saveConfig(config: GitServiceConfig): Promise<void> {
    await this.client.storage.write("git.json", config, "settings");
  }

  async testConnection(): Promise<GitResult> {
    const resp = await this.client.http.get<{ reachable: boolean; error?: string }>("/api/git/test");
    if (!resp.ok || !resp.data) {
      return { success: false, output: "", error: resp.error ?? "Connection test failed" };
    }
    return {
      success: resp.data.reachable,
      output: resp.data.reachable ? "Connection successful" : "",
      error: resp.data.error,
    };
  }

  async pull(): Promise<GitResult> {
    const resp = await this.client.http.post<{ success: boolean; message: string }>("/api/git/pull", {});
    if (!resp.ok || !resp.data) {
      return { success: false, output: "", error: resp.error ?? "Pull failed" };
    }
    return { success: resp.data.success, output: resp.data.message, error: resp.data.success ? undefined : resp.data.message };
  }

  async push(_message?: string): Promise<GitResult> {
    const resp = await this.client.http.post<{ success: boolean; message: string }>("/api/git/push", {});
    if (!resp.ok || !resp.data) {
      return { success: false, output: "", error: resp.error ?? "Push failed" };
    }
    return { success: resp.data.success, output: resp.data.message, error: resp.data.success ? undefined : resp.data.message };
  }

  async log(limit: number = 20): Promise<GitLogEntry[]> {
    const resp = await this.client.http.get<{ commits: GitLogEntry[] }>(`/api/git/log?limit=${limit}`);
    if (!resp.ok || !resp.data) return [];
    return resp.data.commits;
  }

  async status(): Promise<GitStatus> {
    const resp = await this.client.http.get<{ clean: boolean; changes: { file: string; status: string }[] }>("/api/git/status");
    if (!resp.ok || !resp.data) {
      return { branch: "main", clean: true, modified: [], untracked: [], ahead: 0, behind: 0 };
    }
    const modified = resp.data.changes.filter((c) => c.status !== "??").map((c) => c.file);
    const untracked = resp.data.changes.filter((c) => c.status === "??").map((c) => c.file);
    return { branch: "main", clean: resp.data.clean, modified, untracked, ahead: 0, behind: 0 };
  }

  async createPreviewBranch(_name: string): Promise<GitResult> {
    return { success: false, output: "", error: "Preview branches are managed by ASS server" };
  }
}

// ============================================================================
// UpdateService — アップデートサービス
// ============================================================================

export class UpdateService implements UpdateServiceInterface {
  constructor(private readonly client: AdlaireClient) {}

  checkUpdate(): Promise<UpdateInfo> {
    return Promise.resolve({
      available: false,
      currentVersion: "Ver.2.3-44",
      latestVersion: "Ver.2.3-44",
    });
  }

  async checkEnvironment(): Promise<EnvironmentCheck> {
    // ASS PHP サーバの環境情報に委譲
    const resp = await this.client.http.get<EnvironmentCheck>("/health");
    if (resp.ok && resp.data) {
      return {
        runtimeVersion: (resp.data as unknown as Record<string, string>).runtime ?? "unknown",
        requiredVersion: "Ver.2.3-44",
        writable: true,
        diskSpace: 0,
        issues: [],
      };
    }
    return {
      runtimeVersion: "unknown",
      requiredVersion: "Ver.2.3-44",
      writable: true,
      diskSpace: 0,
      issues: ["ASS server unreachable"],
    };
  }

  executeApplyUpdate(): Promise<UpdateApplyResult> {
    return Promise.resolve({
      success: false,
      fromVersion: "Ver.2.3-44",
      toVersion: "Ver.2.3-44",
      error: "No update available",
    });
  }

  async executeRollback(name: string): Promise<{ success: boolean; error?: string }> {
    const exists = await this.client.storage.exists(name, "backups");
    if (!exists) return { success: false, error: "Backup not found" };
    return { success: true };
  }

  async executeDeleteBackup(name: string): Promise<{ success: boolean; error?: string }> {
    const result = await this.client.storage.delete(name, "backups");
    return { success: result };
  }

  async listBackups(): Promise<BackupEntry[]> {
    const files = await this.client.storage.list("backups", ".zip");
    return files.map((f) => ({
      name: f,
      createdAt: "",
      size: 0,
      version: "",
    }));
  }
}
