/// <reference lib="dom" />
/// <reference path="../../ACS/ClientEngine/browser-types.d.ts" />
/**
 * static_builder.ts — ダッシュボード上の静的書き出し管理 UI
 *
 * ボタン操作:
 *   [差分ビルド]  → ap_action=generate_static_diff
 *   [フルビルド]  → ap_action=generate_static_full
 *   [クリーン]    → ap_action=clean_static
 *   [ZIP DL]      → ap_action=build_zip
 *   [ステータス]  → ap_action=static_status
 *
 * 依存: ap-utils.js (AP.postAction, AP.escHtml, AP.getCsrf)
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    const statusEl = document.getElementById("ap-static-status");
    const resultEl = document.getElementById("ap-static-result");
    const pagesEl = document.getElementById("ap-static-pages");
    const modeEl = document.getElementById("ap-static-mode");

    if (!statusEl || !resultEl) return;

    const esc = AP.escHtml;

    const allButtons: HTMLButtonElement[] = [
      "ap-static-diff",
      "ap-static-full",
      "ap-static-clean",
      "ap-static-zip",
    ]
      .map(function (id) {
        return document.getElementById(id) as HTMLButtonElement | null;
      })
      .filter(Boolean) as HTMLButtonElement[];
    let _building = false;

    function showStatus(msg: string): void {
      statusEl!.textContent = msg;
    }
    function showResult(html: string): void {
      resultEl!.innerHTML = html;
    }

    function setBusy(busy: boolean): void {
      _building = busy;
      allButtons.forEach(function (b) {
        b.disabled = busy;
      });
    }

    function post(action: string): Promise<APResponse> {
      return AP.postAction(action);
    }

    interface PageInfo {
      slug: string;
      state: string;
    }

    function renderPages(pages: PageInfo[] | undefined): void {
      if (!pagesEl || !pages || !pages.length) {
        if (pagesEl) pagesEl.innerHTML = "";
        return;
      }
      const counts: Record<string, number> = { current: 0, outdated: 0, not_built: 0 };
      pages.forEach(function (p) {
        counts[p.state] = (counts[p.state] || 0) + 1;
      });

      const summary = '<span class="ap-static-summary">' +
        "✅ " + counts.current + " 最新" +
        " / ⚠️ " + counts.outdated + " 要更新" +
        " / ❌ " + counts.not_built + " 未生成" +
        "</span><br>";

      let badges = "";
      pages.forEach(function (p) {
        const icon = p.state === "current" ? "✅" : (p.state === "outdated" ? "⚠️" : "❌");
        const label = p.state === "current"
          ? "最新"
          : (p.state === "outdated" ? "更新必要" : "未生成");
        badges += '<span class="ap-static-page-badge" title="' + esc(label) + '">' +
          icon + " " + esc(p.slug) +
          "</span> ";
      });
      pagesEl.innerHTML = summary + badges;
    }

    function showWarnings(data: APResponse): void {
      const warnings = data.warnings as string[] | undefined;
      if (warnings && warnings.length) {
        showResult(
          resultEl!.innerHTML +
            '<br><span style="color:#c0392b">警告: ' + warnings.map(esc).join(", ") + "</span>",
        );
      }
    }

    /* ── ステータス取得 ── */
    function refreshStatus(): void {
      showStatus("状態を取得中...");
      post("static_status").then(function (data: APResponse) {
        if (!data.ok) {
          showStatus("エラー: " + (data.error || "不明"));
          return;
        }

        /* Static-First インジケータ */
        if (modeEl) {
          modeEl.textContent = (data.static_exists as boolean)
            ? "Static-First 有効（静的ファイルあり）"
            : "無効（静的ファイルなし — ビルドで有効化）";
        }

        let info = "";
        if (data.last_full_build) info += "フルビルド: " + esc(data.last_full_build) + " ";
        if (data.last_diff_build) info += "差分ビルド: " + esc(data.last_diff_build);
        if (!info) info = "まだビルドされていません";
        showStatus(info);
        renderPages(data.pages as PageInfo[] | undefined);
      }).catch(function (e: Error) {
        showStatus("取得失敗: " + e.message);
      });
    }

    /* ── 差分ビルド ── */
    const diffBtn = document.getElementById("ap-static-diff") as HTMLButtonElement | null;
    if (diffBtn) {
      diffBtn.addEventListener("click", function () {
        setBusy(true);
        showStatus("差分ビルド中...");
        showResult("");
        post("generate_static_diff").then(function (data: APResponse) {
          setBusy(false);
          if (!data.ok) {
            showResult("エラー: " + esc(data.error || "不明"));
            return;
          }
          showResult(
            "ビルド: " + data.built + " / スキップ: " + data.skipped +
              " / 削除: " + data.deleted + " (" + data.elapsed_ms + "ms)",
          );
          showWarnings(data);
          refreshStatus();
        }).catch(function (e: Error) {
          setBusy(false);
          showResult("失敗: " + esc(e.message));
        });
      });
    }

    /* ── フルビルド ── */
    const fullBtn = document.getElementById("ap-static-full") as HTMLButtonElement | null;
    if (fullBtn) {
      fullBtn.addEventListener("click", function () {
        setBusy(true);
        showStatus("フルビルド中...");
        showResult("");
        post("generate_static_full").then(function (data: APResponse) {
          setBusy(false);
          if (!data.ok) {
            showResult("エラー: " + esc(data.error || "不明"));
            return;
          }
          showResult(
            "ビルド: " + data.built + " / 削除: " + data.deleted + " (" + data.elapsed_ms + "ms)",
          );
          showWarnings(data);
          refreshStatus();
        }).catch(function (e: Error) {
          setBusy(false);
          showResult("失敗: " + esc(e.message));
        });
      });
    }

    /* ── クリーン ── */
    const cleanBtn = document.getElementById("ap-static-clean") as HTMLButtonElement | null;
    if (cleanBtn) {
      cleanBtn.addEventListener("click", function () {
        if (!confirm("静的ファイルをすべて削除しますか？")) return;
        setBusy(true);
        showStatus("クリーン中...");
        showResult("");
        post("clean_static").then(function (data: APResponse) {
          setBusy(false);
          showResult(data.ok ? "削除完了" : "エラー: " + esc(data.error || "不明"));
          refreshStatus();
        }).catch(function (e: Error) {
          setBusy(false);
          showResult("失敗: " + esc(e.message));
        });
      });
    }

    /* ── ZIP ダウンロード ── */
    const zipBtn = document.getElementById("ap-static-zip") as HTMLButtonElement | null;
    if (zipBtn) {
      zipBtn.addEventListener("click", function () {
        const csrf = AP.getCsrf();
        setBusy(true);
        showStatus("ZIP 生成中...");
        fetch("./", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded", "X-CSRF-TOKEN": csrf },
          body: new URLSearchParams({ ap_action: "build_zip", csrf: csrf }),
        }).then(function (r) {
          if (!r.ok) {
            return r.json().then(function (d: { error?: string }) {
              throw new Error(d.error || "HTTP " + r.status);
            });
          }
          return r.blob();
        }).then(function (blob) {
          if (!(blob instanceof Blob)) return;
          const a = document.createElement("a");
          a.href = URL.createObjectURL(blob);
          a.download = "static-" + new Date().toISOString().slice(0, 10) + ".zip";
          document.body.appendChild(a);
          a.click();
          a.remove();
          URL.revokeObjectURL(a.href);
          setBusy(false);
          showStatus("ZIP ダウンロード完了");
        }).catch(function (e: Error) {
          setBusy(false);
          showStatus("ZIP 失敗: " + e.message);
        });
      });
    }

    /* ── 初期ステータス取得 ── */
    refreshStatus();
  });
})();
