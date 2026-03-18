/// <reference lib="dom" />
/// <reference path="../../ACS/ClientEngine/browser-types.d.ts" />
/**
 * collection_manager.ts - コレクション管理 UI
 *
 * ダッシュボードのコレクション管理セクション用バニラ JS。
 * 依存: ap-utils.js (AP.post, AP.escHtml)
 */
(function () {
  "use strict";

  const post = AP.post;

  function fetchApi(
    action: string,
    params: Record<string, string> | null,
    callback: (res: APResponse) => void,
  ): void {
    let url = "./?ap_api=" + encodeURIComponent(action);
    if (params) {
      for (const k in params) {
        if (Object.prototype.hasOwnProperty.call(params, k)) {
          url += "&" + encodeURIComponent(k) + "=" + encodeURIComponent(params[k]);
        }
      }
    }
    fetch(url)
      .then(function (r) {
        return r.json();
      })
      .then(callback)
      .catch(function (e: Error) {
        callback({ ok: false, error: e.message });
      });
  }

  document.addEventListener("DOMContentLoaded", function () {
    /* ── コレクション作成 ── */
    const createBtn = document.getElementById("ap-collection-create") as HTMLButtonElement | null;
    if (createBtn) {
      createBtn.addEventListener("click", function () {
        const name =
          (document.getElementById("ap-collection-name") as HTMLInputElement | null)?.value ?? "";
        const label =
          (document.getElementById("ap-collection-label") as HTMLInputElement | null)?.value ?? "";
        if (!name.trim()) {
          alert("コレクション名を入力してください");
          return;
        }
        post(
          "collection_create",
          { name: name.trim(), label: label.trim() || name.trim() },
          function (res: APResponse) {
            if (res.ok) {
              location.reload();
            } else {
              alert("エラー: " + (res.error || "不明なエラー"));
            }
          },
        );
      });
    }

    /* ── コレクション削除 ── */
    document.querySelectorAll<HTMLButtonElement>(".ap-collection-delete").forEach(function (btn) {
      btn.addEventListener("click", function (this: HTMLButtonElement) {
        const name = this.getAttribute("data-collection");
        if (
          !confirm("コレクション定義「" + name + "」を削除しますか？\n（ファイルは削除されません）")
        ) return;
        post("collection_delete", { name: name || "" }, function (res: APResponse) {
          if (res.ok) {
            location.reload();
          } else {
            alert("エラー: " + (res.error || "不明なエラー"));
          }
        });
      });
    });

    /* ── pages.json → コレクション移行 ── */
    const migrateBtn = document.getElementById("ap-collection-migrate") as HTMLButtonElement | null;
    if (migrateBtn) {
      migrateBtn.addEventListener("click", function () {
        if (
          !confirm(
            "pages.json のページをコレクション（Markdown）に移行しますか？\n（pages.json は削除されません）",
          )
        ) return;
        const resultEl = document.getElementById("ap-collection-migrate-result");
        post("collection_migrate", {}, function (res: APResponse) {
          const data = res.data as { migrated: number; total: number } | undefined;
          if (res.ok && data) {
            if (resultEl) {
              resultEl.textContent = data.migrated + "件移行しました（全" + data.total + "件）";
            }
            setTimeout(function () {
              location.reload();
            }, 1500);
          } else {
            if (resultEl) resultEl.textContent = "エラー: " + (res.error || "不明なエラー");
          }
        });
      });
    }

    /* ── コレクション編集ボタン ── */
    const editorSection = document.getElementById("ap-collection-editor");
    const editorName = document.getElementById("ap-editor-collection-name");
    const editorItems = document.getElementById("ap-editor-items");
    let currentCollection = "";

    document.querySelectorAll<HTMLButtonElement>(".ap-collection-edit").forEach(function (btn) {
      btn.addEventListener("click", function (this: HTMLButtonElement) {
        currentCollection = this.getAttribute("data-collection") || "";
        if (editorName) editorName.textContent = currentCollection;
        if (editorSection) editorSection.style.display = "";
        loadItems(currentCollection);
      });
    });

    function loadItems(collection: string): void {
      if (!editorItems) return;
      editorItems.innerHTML = '<p style="color:#718096;">読み込み中...</p>';
      fetchApi("collection", { name: collection }, function (res: APResponse) {
        if (!res.ok || !res.data) {
          editorItems.innerHTML = '<p style="color:#e53e3e;">読み込みエラー</p>';
          return;
        }
        const data = res.data as { items?: Array<{ slug: string; meta?: { title?: string } }> };
        const items = data.items || [];
        if (items.length === 0) {
          editorItems.innerHTML = '<p style="color:#718096;">アイテムがありません</p>';
          return;
        }
        let html = "";
        items.forEach(function (item) {
          const title = (item.meta && item.meta.title) ? item.meta.title : item.slug;
          html += '<div class="ap-dash-page-item" style="cursor:pointer;">' +
            '<div class="ap-editor-item-row" data-slug="' + escHtml(item.slug) + '">' +
            "<strong>" + escHtml(item.slug) + "</strong>" +
            '<span style="margin-left:8px;color:#718096;">' + escHtml(title) + "</span>" +
            "</div>" +
            '<button class="ap-editor-item-delete" data-slug="' + escHtml(item.slug) +
            '" title="削除">&times;</button>' +
            "</div>";
        });
        editorItems.innerHTML = html;
        bindItemEvents();
      });
    }

    const escHtml = AP.escHtml;

    function generateSlug(title: string): string {
      let slug = title.toLowerCase()
        .replace(/[^a-z0-9\s\-_]/g, "")
        .replace(/\s+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-|-$/g, "");
      if (!slug && title) {
        slug = "item-" + Date.now();
      }
      return slug;
    }

    function bindItemEvents(): void {
      /* アイテム行クリック → 編集エリア表示 */
      document.querySelectorAll<HTMLElement>(".ap-editor-item-row").forEach(function (row) {
        row.addEventListener("click", function (this: HTMLElement) {
          const slug = this.getAttribute("data-slug");
          if (slug) openItemEditor(slug);
        });
      });
      /* アイテム削除 */
      document.querySelectorAll<HTMLButtonElement>(".ap-editor-item-delete").forEach(
        function (btn) {
          btn.addEventListener("click", function (this: HTMLButtonElement, e: Event) {
            e.stopPropagation();
            const slug = this.getAttribute("data-slug");
            if (!confirm("「" + slug + "」を削除しますか？")) return;
            post(
              "collection_item_delete",
              { collection: currentCollection, slug: slug || "" },
              function (res: APResponse) {
                if (res.ok) {
                  loadItems(currentCollection);
                  const editArea = document.getElementById("ap-editor-edit-area");
                  if (editArea) editArea.style.display = "none";
                } else {
                  alert("エラー: " + (res.error || ""));
                }
              },
            );
          });
        },
      );
    }

    /* ── アイテムエディタ ── */
    function openItemEditor(slug: string): void {
      const editArea = document.getElementById("ap-editor-edit-area");
      const editSlug = document.getElementById("ap-editor-edit-slug");
      const editTitle = document.getElementById("ap-editor-edit-title") as HTMLInputElement | null;
      const editBody = document.getElementById("ap-editor-edit-body") as HTMLTextAreaElement | null;
      if (!editArea) return;

      editArea.style.display = "";
      if (editSlug) editSlug.textContent = slug;
      editArea.setAttribute("data-slug", slug);

      /* API からアイテム詳細取得 */
      fetchApi("item", { collection: currentCollection, slug: slug }, function (res: APResponse) {
        const data = res.data as { meta?: { title?: string }; markdown?: string } | undefined;
        if (!res.ok || !data) {
          if (editTitle) editTitle.value = "";
          if (editBody) editBody.value = "";
          return;
        }
        if (editTitle) editTitle.value = (data.meta && data.meta.title) || slug;
        if (editBody) editBody.value = data.markdown || "";
        updatePreview();
      });
    }

    /* ── 保存 ── */
    const saveBtn = document.getElementById("ap-editor-edit-save") as HTMLButtonElement | null;
    if (saveBtn) {
      saveBtn.addEventListener("click", function () {
        const editArea = document.getElementById("ap-editor-edit-area");
        const slug = editArea ? editArea.getAttribute("data-slug") : "";
        const title =
          (document.getElementById("ap-editor-edit-title") as HTMLInputElement | null)?.value ?? "";
        const body =
          (document.getElementById("ap-editor-edit-body") as HTMLTextAreaElement | null)?.value ??
            "";
        const resultEl = document.getElementById("ap-editor-edit-result");
        if (!slug || !currentCollection) return;
        post("collection_item_save", {
          collection: currentCollection,
          slug: slug,
          title: title,
          body: body,
        }, function (res: APResponse) {
          if (resultEl) {
            resultEl.textContent = res.ok ? "保存しました" : "エラー: " + (res.error || "");
            resultEl.style.color = res.ok ? "#38a169" : "#e53e3e";
          }
          if (res.ok) loadItems(currentCollection);
        });
      });
    }

    /* ── キャンセル ── */
    const cancelBtn = document.getElementById("ap-editor-edit-cancel") as HTMLButtonElement | null;
    if (cancelBtn) {
      cancelBtn.addEventListener("click", function () {
        const editArea = document.getElementById("ap-editor-edit-area");
        if (editArea) editArea.style.display = "none";
      });
    }

    /* ── タイトルからスラッグ自動生成 ── */
    const titleInput = document.getElementById("ap-editor-new-title") as HTMLInputElement | null;
    const slugInput = document.getElementById("ap-editor-new-slug") as HTMLInputElement | null;
    if (titleInput && slugInput) {
      titleInput.addEventListener("input", function (this: HTMLInputElement) {
        if (slugInput.dataset.manual) return;
        slugInput.value = generateSlug(this.value);
      });
      slugInput.addEventListener("input", function (this: HTMLInputElement) {
        this.dataset.manual = this.value ? "1" : "";
      });
    }

    /* ── 新規アイテム ── */
    const newCreateBtn = document.getElementById("ap-editor-new-create") as
      | HTMLButtonElement
      | null;
    if (newCreateBtn) {
      newCreateBtn.addEventListener("click", function () {
        const slug =
          (document.getElementById("ap-editor-new-slug") as HTMLInputElement | null)?.value ?? "";
        const title =
          (document.getElementById("ap-editor-new-title") as HTMLInputElement | null)?.value ?? "";
        if (!slug.trim()) {
          alert("スラッグを入力してください");
          return;
        }
        if (!currentCollection) return;
        post("collection_item_save", {
          collection: currentCollection,
          slug: slug.trim(),
          title: title.trim() || slug.trim(),
          body: "",
          is_new: "1",
        }, function (res: APResponse) {
          if (res.ok) {
            const slugEl = document.getElementById("ap-editor-new-slug") as HTMLInputElement | null;
            const titleEl = document.getElementById("ap-editor-new-title") as
              | HTMLInputElement
              | null;
            if (slugEl) slugEl.value = "";
            if (titleEl) titleEl.value = "";
            loadItems(currentCollection);
            openItemEditor(slug.trim());
          } else {
            alert("エラー: " + (res.error || ""));
          }
        });
      });
    }

    /* ── Markdown プレビュー（簡易） ── */
    const editBody = document.getElementById("ap-editor-edit-body") as HTMLTextAreaElement | null;
    if (editBody) {
      editBody.addEventListener("input", debounce(updatePreview, 300));
    }

    function updatePreview(): void {
      const _body =
        (document.getElementById("ap-editor-edit-body") as HTMLTextAreaElement | null)?.value ?? "";
      const previewEl = document.getElementById("ap-editor-preview-html");
      if (!previewEl) return;
      /* サーバーサイドで Markdown 変換する API を使用 */
      const slug = document.getElementById("ap-editor-edit-area")?.getAttribute("data-slug") ||
        "preview";
      fetchApi("item", { collection: currentCollection, slug: slug }, function (res: APResponse) {
        const data = res.data as { content?: string } | undefined;
        if (res.ok && data && data.content) {
          /* R25 fix: サーバーレスポンスの HTML をサニタイズ（XSS 防止） */
          const tmp = document.createElement("div");
          tmp.innerHTML = data.content;
          const dangerous = tmp.querySelectorAll("script,iframe,object,embed,form");
          for (let i = 0; i < dangerous.length; i++) dangerous[i].remove();
          const all = tmp.querySelectorAll("*");
          for (let j = 0; j < all.length; j++) {
            const attrs = all[j].attributes;
            for (let a = attrs.length - 1; a >= 0; a--) {
              if (attrs[a].name.indexOf("on") === 0) all[j].removeAttribute(attrs[a].name);
            }
            if (
              all[j].tagName === "A" && /^\s*javascript:/i.test(all[j].getAttribute("href") || "")
            ) {
              all[j].removeAttribute("href");
            }
          }
          previewEl.innerHTML = tmp.innerHTML;
        }
      });
    }

    function debounce(fn: () => void, ms: number): () => void {
      let timer: ReturnType<typeof setTimeout>;
      return function () {
        clearTimeout(timer);
        timer = setTimeout(fn, ms);
      };
    }
  });
})();
