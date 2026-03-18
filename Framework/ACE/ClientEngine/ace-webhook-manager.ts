/// <reference lib="dom" />
/// <reference path="../../ACS/ClientEngine/browser-types.d.ts" />
/**
 * webhook_manager.ts - Outgoing Webhook / キャッシュ / ユーザー管理 UI
 *
 * 依存: ap-utils.js (AP.post, AP.apiPost, AP.getCsrf)
 */
(function () {
  "use strict";

  const post = AP.post;
  const apiPost = AP.apiPost;

  document.addEventListener("DOMContentLoaded", function () {
    /* ── Webhook 追加 ── */
    const addBtn = document.getElementById("ap-webhook-add") as HTMLButtonElement | null;
    if (addBtn) {
      addBtn.addEventListener("click", function () {
        const url = (document.getElementById("ap-webhook-url") as HTMLInputElement | null)?.value ??
          "";
        const label =
          (document.getElementById("ap-webhook-label") as HTMLInputElement | null)?.value ?? "";
        const secret =
          (document.getElementById("ap-webhook-secret") as HTMLInputElement | null)?.value ?? "";
        if (!url.trim()) {
          alert("URL を入力してください");
          return;
        }
        post(
          "webhook_add",
          { url: url.trim(), label: label.trim(), secret: secret.trim() },
          function (res: APResponse) {
            const r = document.getElementById("ap-webhook-result");
            if (res.ok) {
              if (r) r.textContent = "追加しました";
              setTimeout(function () {
                location.reload();
              }, 800);
            } else {
              if (r) {
                r.textContent = "エラー: " + (res.error || "");
                r.style.color = "#e53e3e";
              }
            }
          },
        );
      });
    }

    /* ── Webhook 削除 ── */
    document.querySelectorAll<HTMLButtonElement>(".ap-webhook-delete").forEach(function (btn) {
      btn.addEventListener("click", function (this: HTMLButtonElement) {
        const index = this.getAttribute("data-index") || "";
        if (!confirm("この Webhook を削除しますか？")) return;
        post("webhook_delete", { index: index }, function (res: APResponse) {
          if (res.ok) location.reload();
          else alert("エラー: " + (res.error || ""));
        });
      });
    });

    /* ── Webhook 切替 ── */
    document.querySelectorAll<HTMLButtonElement>(".ap-webhook-toggle").forEach(function (btn) {
      btn.addEventListener("click", function (this: HTMLButtonElement) {
        const index = this.getAttribute("data-index") || "";
        post("webhook_toggle", { index: index }, function (res: APResponse) {
          if (res.ok) location.reload();
          else alert("エラー: " + (res.error || ""));
        });
      });
    });

    /* ── Webhook テスト ── */
    document.querySelectorAll<HTMLButtonElement>(".ap-webhook-test").forEach(function (btn) {
      btn.addEventListener("click", function (this: HTMLButtonElement) {
        const index = this.getAttribute("data-index") || "";
        post("webhook_test", { index: index }, function (res: APResponse) {
          const r = document.getElementById("ap-webhook-result");
          if (r) {
            r.textContent = res.ok ? "テスト送信しました" : "エラー: " + (res.error || "");
            r.style.color = res.ok ? "#38a169" : "#e53e3e";
          }
        });
      });
    });

    /* ── キャッシュクリア ── */
    const cacheClearBtn = document.getElementById("ap-cache-clear") as HTMLButtonElement | null;
    if (cacheClearBtn) {
      cacheClearBtn.addEventListener("click", function () {
        apiPost("cache_clear", {}, function (res: APResponse) {
          const r = document.getElementById("ap-cache-result");
          if (r) {
            r.textContent = res.ok ? "キャッシュをクリアしました" : "エラー";
            r.style.color = res.ok ? "#38a169" : "#e53e3e";
          }
        });
      });
    }

    /* ── ユーザー追加 ── */
    const userAddBtn = document.getElementById("ap-user-add") as HTMLButtonElement | null;
    if (userAddBtn) {
      userAddBtn.addEventListener("click", function () {
        const username =
          (document.getElementById("ap-user-username") as HTMLInputElement | null)?.value ?? "";
        const password =
          (document.getElementById("ap-user-password") as HTMLInputElement | null)?.value ?? "";
        const role = (document.getElementById("ap-user-role") as HTMLSelectElement | null)?.value ??
          "editor";
        if (!username.trim() || !password) {
          alert("ユーザー名とパスワードを入力してください");
          return;
        }
        post(
          "user_add",
          { username: username.trim(), password: password, role: role },
          function (res: APResponse) {
            const r = document.getElementById("ap-user-result");
            if (res.ok) {
              if (r) {
                r.textContent = "ユーザーを追加しました";
                r.style.color = "#38a169";
              }
              setTimeout(function () {
                location.reload();
              }, 800);
            } else {
              if (r) {
                r.textContent = "エラー: " + (res.error || "");
                r.style.color = "#e53e3e";
              }
            }
          },
        );
      });
    }

    /* ── ユーザー削除 ── */
    document.querySelectorAll<HTMLButtonElement>(".ap-user-delete").forEach(function (btn) {
      btn.addEventListener("click", function (this: HTMLButtonElement) {
        const username = this.getAttribute("data-username") || "";
        if (!confirm("ユーザー「" + username + "」を削除しますか？")) return;
        post("user_delete", { username: username }, function (res: APResponse) {
          if (res.ok) location.reload();
          else alert("エラー: " + (res.error || ""));
        });
      });
    });

    /* ── リダイレクト追加 ── */
    const redirectAddBtn = document.getElementById("ap-redirect-add") as HTMLButtonElement | null;
    if (redirectAddBtn) {
      redirectAddBtn.addEventListener("click", function () {
        const from =
          (document.getElementById("ap-redirect-from") as HTMLInputElement | null)?.value ?? "";
        const to = (document.getElementById("ap-redirect-to") as HTMLInputElement | null)?.value ??
          "";
        const code =
          (document.getElementById("ap-redirect-code") as HTMLSelectElement | null)?.value ?? "301";
        if (!from.trim() || !to.trim()) {
          alert("旧URLと新URLを入力してください");
          return;
        }
        post(
          "redirect_add",
          { from: from.trim(), to: to.trim(), code: code },
          function (res: APResponse) {
            const r = document.getElementById("ap-redirect-result");
            if (res.ok) {
              if (r) {
                r.textContent = "追加しました";
                r.style.color = "#38a169";
              }
              setTimeout(function () {
                location.reload();
              }, 800);
            } else {
              if (r) {
                r.textContent = "エラー: " + (res.error || "");
                r.style.color = "#e53e3e";
              }
            }
          },
        );
      });
    }

    /* ── リダイレクト削除 ── */
    document.querySelectorAll<HTMLButtonElement>(".ap-redirect-delete").forEach(function (btn) {
      btn.addEventListener("click", function (this: HTMLButtonElement) {
        const index = this.getAttribute("data-index") || "";
        if (!confirm("このリダイレクトを削除しますか？")) return;
        post("redirect_delete", { index: index }, function (res: APResponse) {
          if (res.ok) location.reload();
          else alert("エラー: " + (res.error || ""));
        });
      });
    });

    /* ── 差分デプロイ ZIP ── */
    const deployDiffBtn = document.getElementById("ap-static-deploy-diff") as
      | HTMLButtonElement
      | null;
    if (deployDiffBtn) {
      deployDiffBtn.addEventListener("click", function () {
        const c = AP.getCsrf();
        const fd = new FormData();
        fd.append("ap_action", "deploy_diff");
        fd.append("csrf", c);
        fetch("./", { method: "POST", headers: { "X-CSRF-TOKEN": c }, body: fd })
          .then(function (r) {
            if (
              r.headers.get("content-type") &&
              r.headers.get("content-type")!.indexOf("application/zip") !== -1
            ) {
              return r.blob().then(function (blob) {
                const a = document.createElement("a");
                const url = URL.createObjectURL(blob);
                a.href = url;
                a.download = "deploy-diff.zip";
                a.click();
                setTimeout(function () {
                  URL.revokeObjectURL(url);
                }, 1000);
              });
            } else {
              return r.json().then(function (data: { error?: string }) {
                alert(data.error || "エラーが発生しました");
              });
            }
          })
          .catch(function (e: Error) {
            alert("エラー: " + e.message);
          });
      });
    }
  });
})();
