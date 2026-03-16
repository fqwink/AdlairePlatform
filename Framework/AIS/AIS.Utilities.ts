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

import type {
  AdlaireClient,
  BackupEntry,
  DiagEvent,
  DiagnosticsReport,
  GitLogEntry,
  GitResult,
  GitStatus,
  HealthCheckResult,
  UpdateInfo,
} from "../types.ts";

import type {
  ApiCacheInterface,
  DiagnosticsConfig,
  DiagnosticsManagerInterface,
  EnvironmentCheck,
  GitServiceConfig,
  GitServiceInterface,
  UpdateApplyResult,
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

  constructor(private readonly client: AdlaireClient) {}

  log(channel: string, message: string, context?: Record<string, unknown>): void {
    if (!this.enabled) return;

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

  async healthCheck(detailed?: boolean): Promise<HealthCheckResult> {
    const checks: Record<
      string,
      { status: "ok" | "warning" | "error"; message: string; value?: unknown }
    > = {};

    // ストレージチェック
    const storageOk = await this.client.storage.exists("site.json", "settings");
    checks["storage"] = {
      status: storageOk ? "ok" : "warning",
      message: storageOk ? "Storage accessible" : "Settings not found",
    };

    // メモリ使用量（Deno）
    if (detailed) {
      const mem = Deno.memoryUsage();
      checks["memory"] = {
        status: mem.heapUsed < 512 * 1024 * 1024 ? "ok" : "warning",
        message: `Heap: ${Math.round(mem.heapUsed / 1024 / 1024)}MB`,
        value: mem.heapUsed,
      };
    }

    const hasError = Object.values(checks).some((c) => c.status === "error");
    const hasWarning = Object.values(checks).some((c) => c.status === "warning");

    return {
      status: hasError ? "error" : hasWarning ? "degraded" : "ok",
      version: "2.0.0",
      php: `deno/${Deno.version.deno}`,
      time: new Date().toISOString(),
      checks,
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

  async remember<T>(key: string, ttl: number, callback: () => Promise<T>): Promise<T> {
    const cached = this.cache.get(key);
    if (cached && cached.expiresAt > Date.now()) {
      return cached.value as T;
    }

    const value = await callback();
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
}

// ============================================================================
// GitService — Git 操作サービス
// ============================================================================

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
    const config = await this.loadConfig();
    if (!config.repoUrl) {
      return { success: false, output: "", error: "No repository URL configured" };
    }
    // テスト接続は Deno.Command で git ls-remote を実行
    try {
      const cmd = new Deno.Command("git", {
        args: ["ls-remote", "--heads", config.repoUrl],
        stdout: "piped",
        stderr: "piped",
      });
      const result = await cmd.output();
      return {
        success: result.success,
        output: new TextDecoder().decode(result.stdout),
        error: result.success ? undefined : new TextDecoder().decode(result.stderr),
      };
    } catch (error: unknown) {
      return {
        success: false,
        output: "",
        error: error instanceof Error ? error.message : "Git command failed",
      };
    }
  }

  pull(): Promise<GitResult> {
    return this.runGit(["pull", "origin"]);
  }

  async push(message?: string): Promise<GitResult> {
    if (message) {
      const addResult = await this.runGit(["add", "-A"]);
      if (!addResult.success) return addResult;

      const commitResult = await this.runGit(["commit", "-m", message]);
      if (!commitResult.success) return commitResult;
    }
    return this.runGit(["push", "origin"]);
  }

  async log(limit: number = 20): Promise<GitLogEntry[]> {
    const result = await this.runGit([
      "log",
      `--max-count=${limit}`,
      "--format=%H|%s|%an|%aI",
    ]);

    if (!result.success) return [];

    return result.output
      .trim()
      .split("\n")
      .filter(Boolean)
      .map((line) => {
        const [hash, message, author, date] = line.split("|");
        return { hash, message, author, date };
      });
  }

  async status(): Promise<GitStatus> {
    const result = await this.runGit(["status", "--porcelain"]);
    const branchResult = await this.runGit(["branch", "--show-current"]);

    const lines = result.output.trim().split("\n").filter(Boolean);
    const modified = lines.filter((l) => l.startsWith(" M") || l.startsWith("M")).map((l) =>
      l.slice(3)
    );
    const untracked = lines.filter((l) => l.startsWith("??")).map((l) => l.slice(3));

    return {
      branch: branchResult.output.trim() || "main",
      clean: lines.length === 0,
      modified,
      untracked,
      ahead: 0,
      behind: 0,
    };
  }

  createPreviewBranch(name: string): Promise<GitResult> {
    return this.runGit(["checkout", "-b", name]);
  }

  private async runGit(args: string[]): Promise<GitResult> {
    try {
      const cmd = new Deno.Command("git", {
        args,
        stdout: "piped",
        stderr: "piped",
      });
      const result = await cmd.output();
      return {
        success: result.success,
        output: new TextDecoder().decode(result.stdout),
        error: result.success ? undefined : new TextDecoder().decode(result.stderr),
      };
    } catch (error: unknown) {
      return {
        success: false,
        output: "",
        error: error instanceof Error ? error.message : "Git command failed",
      };
    }
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
      currentVersion: "2.0.0",
      latestVersion: "2.0.0",
    });
  }

  checkEnvironment(): Promise<EnvironmentCheck> {
    return Promise.resolve({
      phpVersion: `deno/${Deno.version.deno}`,
      requiredVersion: "2.0.0",
      writable: true,
      diskSpace: 0,
      issues: [],
    });
  }

  executeApplyUpdate(): Promise<UpdateApplyResult> {
    return Promise.resolve({
      success: false,
      fromVersion: "2.0.0",
      toVersion: "2.0.0",
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
