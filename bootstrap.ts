/**
 * AdlairePlatform — ブートストラップ
 *
 * DI コンテナ初期化、サービス登録、イベントリスナー設定。
 * PHP bootstrap.php からの移植。
 *
 * @since 2.0.0
 * @license Adlaire License Ver.2.0
 */

import {
  ApiCache,
  AppContext,
  Container,
  DiagnosticsManager,
  EventBus,
  I18n,
  Router,
  WebhookService,
} from "./Framework/mod.ts";

import { createClient } from "./Framework/ACS/ACS.Api.ts";

// ============================================================================
// Application — グローバルファサード
// ============================================================================

export class ApplicationFacade {
  private static instance: ApplicationFacade | null = null;

  readonly container: Container;
  readonly router: Router;
  readonly events: EventBus;
  readonly context: AppContext;
  readonly i18n: I18n;

  private constructor(basePath: string) {
    this.container = new Container();
    this.router = new Router();
    this.events = new EventBus();
    this.context = new AppContext(basePath);
    this.i18n = new I18n();

    // コアサービスをコンテナに登録
    this.container.singleton("router", () => this.router);
    this.container.singleton("events", () => this.events);
    this.container.singleton("context", () => this.context);
    this.container.singleton("i18n", () => this.i18n);
  }

  static boot(basePath: string): ApplicationFacade {
    if (ApplicationFacade.instance) {
      return ApplicationFacade.instance;
    }

    const app = new ApplicationFacade(basePath);
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
  const app = ApplicationFacade.boot(basePath);

  // ACS クライアント生成
  const client = createClient({
    baseUrl: options.baseUrl ?? "",
    token: options.token ?? null,
  });
  app.container.singleton("client", () => client);

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

  // 診断マネージャ
  const diagnostics = new DiagnosticsManager(client);
  app.container.singleton("diagnostics", () => diagnostics);

  // API キャッシュ
  const apiCache = new ApiCache();
  app.container.singleton("apiCache", () => apiCache);

  // Webhook サービス
  const webhookService = new WebhookService(client);
  app.container.singleton("webhookService", () => webhookService);

  // ── イベントリスナー登録 ──

  // コンテンツ変更 → Webhook 自動配信
  app.events.listen("content.changed", (data) => {
    const event = String(data.event ?? "page.updated");
    const payload = (data.payload ?? {}) as Record<string, unknown>;
    webhookService.dispatch(event as Parameters<typeof webhookService.dispatch>[0], payload);
  });

  // ログインイベント → 診断ログ
  app.events.listen("auth.login", (data) => {
    diagnostics.log("security", "イベント: ログイン", data);
  });

  // キャッシュ無効化
  app.events.listen("cache.invalidate", () => {
    apiCache.invalidateContent();
  });

  return app;
}
