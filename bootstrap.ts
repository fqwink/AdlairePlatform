/**
 * AdlairePlatform — ブートストラップ
 *
 * ApplicationFacade 初期化、サービス登録、イベントリスナー設定。
 *
 * FRAMEWORK_RULEBOOK v3.0 §2.1 準拠:
 * - DI コンテナパターンは廃止
 * - サービス間の依存解決は ApplicationFacade のプロパティ直接参照により行う
 *
 * @since 3.0.0
 * @license Adlaire License Ver.2.0
 */

import {
  ApiCache,
  AppContext,
  DiagnosticsManager,
  EventBus,
  I18n,
  Router,
  WebhookService,
} from "./Framework/mod.ts";

import type { AdlaireClient } from "./Framework/ACS/ACS.d.ts";
import { createClient } from "./Framework/ACS/ACS.Api.ts";

// ============================================================================
// globalThis.__acs 型宣言 — FRAMEWORK_RULEBOOK §3.2 準拠
// ============================================================================

declare global {
  // deno-lint-ignore no-var
  var __acs: AdlaireClient;
}

// ============================================================================
// Application — グローバルファサード
// FRAMEWORK_RULEBOOK v3.0 §2.1: DI コンテナ廃止、プロパティ直接参照
// ============================================================================

export class ApplicationFacade {
  private static instance: ApplicationFacade | null = null;

  readonly router: Router;
  readonly events: EventBus;
  readonly context: AppContext;
  readonly i18n: I18n;
  readonly diagnostics: DiagnosticsManager;
  readonly apiCache: ApiCache;
  readonly webhookService: WebhookService;

  private constructor(basePath: string, client: AdlaireClient) {
    this.router = new Router();
    this.events = new EventBus();
    this.context = new AppContext(basePath);
    this.i18n = new I18n();
    this.diagnostics = new DiagnosticsManager(client);
    this.apiCache = new ApiCache();
    this.webhookService = new WebhookService(client);
  }

  static boot(basePath: string, client: AdlaireClient): ApplicationFacade {
    if (ApplicationFacade.instance) {
      return ApplicationFacade.instance;
    }

    const app = new ApplicationFacade(basePath, client);
    ApplicationFacade.instance = app;
    return app;
  }

  static get(): ApplicationFacade {
    if (!ApplicationFacade.instance) {
      throw new Error("Application not booted. Call ApplicationFacade.boot() first.");
    }
    return ApplicationFacade.instance;
  }

  static isBooted(): boolean {
    return ApplicationFacade.instance !== null;
  }

  /** テスト用リセット */
  static reset(): void {
    ApplicationFacade.instance = null;
  }
}

// ============================================================================
// bootstrap() — 初期化関数
// ============================================================================

export interface BootstrapOptions {
  basePath?: string;
  baseUrl?: string;
  token?: string;
  locale?: string;
}

export async function bootstrap(options: BootstrapOptions = {}): Promise<ApplicationFacade> {
  const basePath = options.basePath ?? Deno.cwd();

  // ACS クライアント生成 — FRAMEWORK_RULEBOOK §3.5「初期化順序」準拠
  // ACS の初期化（globalThis.__acs の公開）は bootstrap 処理の最初に実行する
  const client = createClient({
    baseUrl: options.baseUrl ?? "",
    token: options.token ?? null,
  });
  globalThis.__acs = client;

  const app = ApplicationFacade.boot(basePath, client);

  // 設定ファイル読み込み
  try {
    await app.context.loadFromFile(`${basePath}/data/settings/settings.json`);
  } catch {
    // 設定ファイルが存在しない場合はデフォルト値で続行
  }

  // i18n 初期化 — locale は設定ファイル読み込み後に設定する
  const locale = (options.locale as string) ?? app.context.get("language", "ja");
  app.i18n.setLocale(locale);
  await app.i18n.init(`${basePath}/lang`);

  // ── イベントリスナー登録 ──

  // コンテンツ変更 → Webhook 自動配信
  app.events.listen("content.changed", (data) => {
    const event = String(data.event ?? "page.updated");
    const payload = (data.payload ?? {}) as Record<string, unknown>;
    app.webhookService.dispatch(event as Parameters<typeof app.webhookService.dispatch>[0], payload);
  });

  // ログインイベント → 診断ログ
  app.events.listen("auth.login", (data) => {
    app.diagnostics.log("security", "イベント: ログイン", data);
  });

  // キャッシュ無効化
  app.events.listen("cache.invalidate", () => {
    app.apiCache.invalidateContent();
  });

  return app;
}
