/**
 * Adlaire Content Engine (ACE) — Utilities Module
 *
 * Webhook ディスパッチ、リビジョン管理、API ルーターを提供する。
 * PHP ACE.Utilities.php からの移植。
 *
 * @package ACE
 * @version 2.0.0
 * @license Adlaire License Ver.2.0
 */

import type { AdlaireClient, ApiResponse } from "../ACS/ACS.d.ts";

import type {
  ApiEndpointHandler,
  ApiRouterInterface,
  RevisionEntry,
  RevisionServiceInterface,
  WebhookConfig,
  WebhookEvent,
  WebhookServiceInterface,
} from "./ACE.Interface.ts";

// ============================================================================
// WebhookService — Webhook ディスパッチ
// ============================================================================

export class WebhookService implements WebhookServiceInterface {
  constructor(private readonly client: AdlaireClient) {}

  async listWebhooks(): Promise<WebhookConfig[]> {
    return (await this.client.storage.read<WebhookConfig[]>(
      "webhooks.json",
      "settings",
    )) ?? [];
  }

  async addWebhook(
    url: string,
    label: string,
    events: WebhookEvent[],
    secret?: string,
  ): Promise<boolean> {
    const hooks = await this.listWebhooks();
    const updated = [...hooks, { url, label, events, secret, enabled: true }];
    return this.client.storage.write("webhooks.json", updated, "settings");
  }

  async deleteWebhook(index: number): Promise<boolean> {
    const hooks = await this.listWebhooks();
    if (index < 0 || index >= hooks.length) return false;
    const updated = hooks.filter((_, i) => i !== index);
    return this.client.storage.write("webhooks.json", updated, "settings");
  }

  async toggleWebhook(index: number): Promise<boolean> {
    const hooks = await this.listWebhooks();
    if (index < 0 || index >= hooks.length) return false;

    const updated = hooks.map((h, i) => i === index ? { ...h, enabled: !h.enabled } : h);
    return this.client.storage.write("webhooks.json", updated, "settings");
  }

  /**
   * Webhook ディスパッチ
   *
   * FRAMEWORK_RULEBOOK §3.5「直接 fetch 禁止」準拠:
   * ACS の http モジュール経由で外部 URL に POST する。
   * HttpTransport は絶対 URL をそのまま通過させるため、
   * 外部 Webhook エンドポイントへの送信が可能。
   */
  async dispatch(event: WebhookEvent, data: Record<string, unknown>): Promise<void> {
    const hooks = await this.listWebhooks();
    const targets = hooks.filter((h) => h.enabled && h.events.includes(event));

    const payload = { event, data, timestamp: new Date().toISOString() };

    await Promise.allSettled(
      targets.map((hook) =>
        this.client.http.post(hook.url, {
          ...payload,
          ...(hook.secret ? { _webhookSecret: hook.secret } : {}),
        })
      ),
    );
  }
}

// ============================================================================
// RevisionService — リビジョン管理
// ============================================================================

export class RevisionService implements RevisionServiceInterface {
  constructor(private readonly client: AdlaireClient) {}

  async list(slug: string): Promise<RevisionEntry[]> {
    const dir = `revisions/${slug}`;
    const files = await this.client.storage.list(dir, ".json");

    const entries: RevisionEntry[] = [];
    for (const file of files) {
      const data = await this.client.storage.read<RevisionEntry>(file, dir);
      if (data) entries.push(data);
    }

    return entries.sort((a, b) => b.timestamp.localeCompare(a.timestamp));
  }

  async get(slug: string, file: string): Promise<string | null> {
    const data = await this.client.storage.read<{ content: string }>(
      file,
      `revisions/${slug}`,
    );
    return data?.content ?? null;
  }

  async restore(slug: string, file: string): Promise<boolean> {
    const content = await this.get(slug, file);
    if (content === null) return false;

    return this.client.storage.write(`${slug}.md`, content, "content");
  }

  async pin(slug: string, file: string): Promise<boolean> {
    const dir = `revisions/${slug}`;
    const data = await this.client.storage.read<Record<string, unknown>>(file, dir);
    if (!data) return false;

    data.pinned = !data.pinned;
    return this.client.storage.write(file, data, dir);
  }

  async search(slug: string, query: string): Promise<RevisionEntry[]> {
    const all = await this.list(slug);
    const lower = query.toLowerCase();
    return all.filter((entry) =>
      entry.file.toLowerCase().includes(lower) ||
      entry.user.toLowerCase().includes(lower)
    );
  }
}

// ============================================================================
// ApiRouter — エンドポイントディスパッチャ
// ============================================================================

export class ApiRouter implements ApiRouterInterface {
  private endpoints = new Map<string, { handler: ApiEndpointHandler; requiresAuth: boolean }>();

  registerEndpoint(
    name: string,
    handler: ApiEndpointHandler,
    requiresAuth: boolean = true,
  ): void {
    this.endpoints.set(name, { handler, requiresAuth });
  }

  async dispatch(
    endpoint: string,
    params: Record<string, unknown>,
    requestBody?: string,
  ): Promise<ApiResponse> {
    const ep = this.endpoints.get(endpoint);
    if (!ep) {
      return { ok: false, error: `Unknown endpoint: ${endpoint}` };
    }

    try {
      return await ep.handler(params, requestBody);
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : "Internal error";
      return { ok: false, error: message };
    }
  }

  listEndpoints(): string[] {
    return [...this.endpoints.keys()];
  }
}
