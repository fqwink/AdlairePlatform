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

import type {
  AdlaireClient,
  ApiResponse,
  RevisionEntry,
  WebhookConfig,
  WebhookEvent,
} from "../types.ts";

import type {
  ApiEndpointHandler,
  ApiRouterInterface,
  RevisionServiceInterface,
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
    hooks.push({ url, label, events, secret, enabled: true });
    return this.client.storage.write("webhooks.json", hooks, "settings");
  }

  async deleteWebhook(index: number): Promise<boolean> {
    const hooks = await this.listWebhooks();
    if (index < 0 || index >= hooks.length) return false;
    hooks.splice(index, 1);
    return this.client.storage.write("webhooks.json", hooks, "settings");
  }

  async toggleWebhook(index: number): Promise<boolean> {
    const hooks = await this.listWebhooks();
    if (index < 0 || index >= hooks.length) return false;

    const mutable = { ...hooks[index], enabled: !hooks[index].enabled };
    hooks[index] = mutable;
    return this.client.storage.write("webhooks.json", hooks, "settings");
  }

  async dispatch(event: WebhookEvent, data: Record<string, unknown>): Promise<void> {
    const hooks = await this.listWebhooks();
    const targets = hooks.filter((h) => h.enabled && h.events.includes(event));

    const payload = JSON.stringify({ event, data, timestamp: new Date().toISOString() });

    await Promise.allSettled(
      targets.map((hook) =>
        fetch(hook.url, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            ...(hook.secret ? { "X-Webhook-Secret": hook.secret } : {}),
          },
          body: payload,
          signal: AbortSignal.timeout(10000),
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
