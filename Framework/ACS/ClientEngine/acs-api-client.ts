/// <reference lib="dom" />
/// <reference path="./browser-types.d.ts" />

/**
 * ap-api-client.ts — AdlairePlatform 公開 API クライアント
 *
 * 静的サイト（static/）から Fetch API で公開エンドポイントを呼び出す。
 * 依存ライブラリなし / バニラ TS
 *
 * API:
 *   window.AP.api(action, params) → Promise<Object>
 *   window.AP.origin              → API オリジン（自動検出）
 *
 * フォーム自動バインド:
 *   <form class="ap-contact"> を検出し、submit を Fetch に変換。
 *   送信完了時に CustomEvent 'ap:done' を dispatch。
 */
(function (): void {
  "use strict";

  interface APIClientAP {
    origin: string;
    api: (action: string, params?: Record<string, string>) => Promise<APResponse>;
  }

  const script: HTMLScriptElement | null = document.currentScript as HTMLScriptElement | null;
  let origin: string = "";
  if (script && script.src) {
    origin = script.src.replace(/\/engines\/.*$|\/static\/.*$|\/assets\/.*$/, "");
  }

  /**
   * 公開 API 呼び出し
   * @param action - ap_api パラメータ値（pages, page, settings, search, contact）
   * @param params - 追加パラメータ
   * @returns JSON レスポンス
   */
  function api(action: string, params?: Record<string, string>): Promise<APResponse> {
    let url: string = origin + "/?ap_api=" + encodeURIComponent(action);
    const isPost: boolean = action === "contact";
    const opts: RequestInit = { method: isPost ? "POST" : "GET" };

    if (isPost) {
      opts.headers = { "Content-Type": "application/x-www-form-urlencoded" };
      opts.body = _toUrlParams(params || {});
    } else if (params) {
      const keys: string[] = Object.keys(params);
      for (let i: number = 0; i < keys.length; i++) {
        url += "&" + encodeURIComponent(keys[i]) + "=" + encodeURIComponent(params[keys[i]]);
      }
    }

    return fetch(url, opts).then(function (r: Response): Promise<APResponse> {
      if (!r.ok) throw new Error("HTTP " + r.status);
      return r.json();
    });
  }

  /* ── ユーティリティ ── */

  function _toUrlParams(obj: Record<string, string>): string {
    const parts: string[] = [];
    const keys: string[] = Object.keys(obj);
    for (let i: number = 0; i < keys.length; i++) {
      parts.push(encodeURIComponent(keys[i]) + "=" + encodeURIComponent(obj[keys[i]]));
    }
    return parts.join("&");
  }

  /* ── フォーム自動バインド ── */

  document.addEventListener("DOMContentLoaded", function (): void {
    const forms: NodeListOf<HTMLFormElement> = document.querySelectorAll<HTMLFormElement>(
      "form.ap-contact",
    );
    for (let i: number = 0; i < forms.length; i++) {
      (function (form: HTMLFormElement): void {
        form.addEventListener("submit", function (e: Event): void {
          e.preventDefault();
          const btn: HTMLButtonElement | null = form.querySelector<HTMLButtonElement>(
            '[type="submit"]',
          );
          if (btn) {
            btn.disabled = true;
            btn.dataset.origText = btn.textContent ?? "";
            btn.textContent = "送信中...";
          }

          const fd: FormData = new FormData(form);
          const params: Record<string, string> = {};
          fd.forEach(function (v: FormDataEntryValue, k: string): void {
            params[k] = v as string;
          });

          api("contact", params).then(function (res: APResponse): void {
            form.dispatchEvent(new CustomEvent("ap:done", { detail: res }));
            if (btn) {
              btn.disabled = false;
              btn.textContent = btn.dataset.origText ?? "";
            }
          }).catch(function (): void {
            form.dispatchEvent(
              new CustomEvent("ap:done", {
                detail: { ok: false, error: "通信エラーが発生しました" },
              }),
            );
            if (btn) {
              btn.disabled = false;
              btn.textContent = btn.dataset.origText ?? "";
            }
          });
        });
      })(forms[i]);
    }
  });

  /* ── グローバル公開 ── */

  (window as unknown as { AP: APIClientAP }).AP = {
    origin: origin,
    api: api,
  };
})();
