/**
 * collection_manager.js - コレクション管理 UI
 *
 * ダッシュボードのコレクション管理セクション用バニラ JS。
 * 依存: ap-utils.js (AP.post, AP.escHtml)
 */
(function() {
	'use strict';

	var post = AP.post;

	function fetchApi(action, params, callback) {
		var url = './?ap_api=' + encodeURIComponent(action);
		if (params) {
			for (var k in params) {
				if (params.hasOwnProperty(k)) {
					url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
				}
			}
		}
		fetch(url)
			.then(function(r) { return r.json(); })
			.then(callback)
			.catch(function(e) { callback({ ok: false, error: e.message }); });
	}

	document.addEventListener('DOMContentLoaded', function() {

		/* ── コレクション作成 ── */
		var createBtn = document.getElementById('ap-collection-create');
		if (createBtn) {
			createBtn.addEventListener('click', function() {
				var name = (document.getElementById('ap-collection-name') || {}).value || '';
				var label = (document.getElementById('ap-collection-label') || {}).value || '';
				if (!name.trim()) { alert('コレクション名を入力してください'); return; }
				post('collection_create', { name: name.trim(), label: label.trim() || name.trim() }, function(res) {
					if (res.ok) {
						location.reload();
					} else {
						alert('エラー: ' + (res.error || '不明なエラー'));
					}
				});
			});
		}

		/* ── コレクション削除 ── */
		document.querySelectorAll('.ap-collection-delete').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var name = this.getAttribute('data-collection');
				if (!confirm('コレクション定義「' + name + '」を削除しますか？\n（ファイルは削除されません）')) return;
				post('collection_delete', { name: name }, function(res) {
					if (res.ok) {
						location.reload();
					} else {
						alert('エラー: ' + (res.error || '不明なエラー'));
					}
				});
			});
		});

		/* ── pages.json → コレクション移行 ── */
		var migrateBtn = document.getElementById('ap-collection-migrate');
		if (migrateBtn) {
			migrateBtn.addEventListener('click', function() {
				if (!confirm('pages.json のページをコレクション（Markdown）に移行しますか？\n（pages.json は削除されません）')) return;
				var resultEl = document.getElementById('ap-collection-migrate-result');
				post('collection_migrate', {}, function(res) {
					if (res.ok && res.data) {
						if (resultEl) resultEl.textContent = res.data.migrated + '件移行しました（全' + res.data.total + '件）';
						setTimeout(function() { location.reload(); }, 1500);
					} else {
						if (resultEl) resultEl.textContent = 'エラー: ' + (res.error || '不明なエラー');
					}
				});
			});
		}

		/* ── コレクション編集ボタン ── */
		var editorSection = document.getElementById('ap-collection-editor');
		var editorName = document.getElementById('ap-editor-collection-name');
		var editorItems = document.getElementById('ap-editor-items');
		var currentCollection = '';

		document.querySelectorAll('.ap-collection-edit').forEach(function(btn) {
			btn.addEventListener('click', function() {
				currentCollection = this.getAttribute('data-collection');
				if (editorName) editorName.textContent = currentCollection;
				if (editorSection) editorSection.style.display = '';
				loadItems(currentCollection);
			});
		});

		function loadItems(collection) {
			if (!editorItems) return;
			editorItems.innerHTML = '<p style="color:#718096;">読み込み中...</p>';
			fetchApi('collection', { name: collection }, function(res) {
				if (!res.ok || !res.data) {
					editorItems.innerHTML = '<p style="color:#e53e3e;">読み込みエラー</p>';
					return;
				}
				var items = res.data.items || [];
				if (items.length === 0) {
					editorItems.innerHTML = '<p style="color:#718096;">アイテムがありません</p>';
					return;
				}
				var html = '';
				items.forEach(function(item) {
					var title = (item.meta && item.meta.title) ? item.meta.title : item.slug;
					html += '<div class="ap-dash-page-item" style="cursor:pointer;">'
						+ '<div class="ap-editor-item-row" data-slug="' + escHtml(item.slug) + '">'
						+ '<strong>' + escHtml(item.slug) + '</strong>'
						+ '<span style="margin-left:8px;color:#718096;">' + escHtml(title) + '</span>'
						+ '</div>'
						+ '<button class="ap-editor-item-delete" data-slug="' + escHtml(item.slug) + '" title="削除">&times;</button>'
						+ '</div>';
				});
				editorItems.innerHTML = html;
				bindItemEvents();
			});
		}

		var escHtml = AP.escHtml;

		function generateSlug(title) {
			var slug = title.toLowerCase()
				.replace(/[^a-z0-9\s\-_]/g, '')
				.replace(/\s+/g, '-')
				.replace(/-+/g, '-')
				.replace(/^-|-$/g, '');
			if (!slug && title) {
				slug = 'item-' + Date.now();
			}
			return slug;
		}

		function bindItemEvents() {
			/* アイテム行クリック → 編集エリア表示 */
			document.querySelectorAll('.ap-editor-item-row').forEach(function(row) {
				row.addEventListener('click', function() {
					var slug = this.getAttribute('data-slug');
					openItemEditor(slug);
				});
			});
			/* アイテム削除 */
			document.querySelectorAll('.ap-editor-item-delete').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					var slug = this.getAttribute('data-slug');
					if (!confirm('「' + slug + '」を削除しますか？')) return;
					post('collection_item_delete', { collection: currentCollection, slug: slug }, function(res) {
						if (res.ok) {
							loadItems(currentCollection);
							var editArea = document.getElementById('ap-editor-edit-area');
							if (editArea) editArea.style.display = 'none';
						} else {
							alert('エラー: ' + (res.error || ''));
						}
					});
				});
			});
		}

		/* ── アイテムエディタ ── */
		function openItemEditor(slug) {
			var editArea = document.getElementById('ap-editor-edit-area');
			var editSlug = document.getElementById('ap-editor-edit-slug');
			var editTitle = document.getElementById('ap-editor-edit-title');
			var editBody = document.getElementById('ap-editor-edit-body');
			if (!editArea) return;

			editArea.style.display = '';
			if (editSlug) editSlug.textContent = slug;
			editArea.setAttribute('data-slug', slug);

			/* API からアイテム詳細取得 */
			fetchApi('item', { collection: currentCollection, slug: slug }, function(res) {
				if (!res.ok || !res.data) {
					if (editTitle) editTitle.value = '';
					if (editBody) editBody.value = '';
					return;
				}
				if (editTitle) editTitle.value = (res.data.meta && res.data.meta.title) || slug;
				if (editBody) editBody.value = res.data.markdown || '';
				updatePreview();
			});
		}

		/* ── 保存 ── */
		var saveBtn = document.getElementById('ap-editor-edit-save');
		if (saveBtn) {
			saveBtn.addEventListener('click', function() {
				var editArea = document.getElementById('ap-editor-edit-area');
				var slug = editArea ? editArea.getAttribute('data-slug') : '';
				var title = (document.getElementById('ap-editor-edit-title') || {}).value || '';
				var body = (document.getElementById('ap-editor-edit-body') || {}).value || '';
				var resultEl = document.getElementById('ap-editor-edit-result');
				if (!slug || !currentCollection) return;
				post('collection_item_save', {
					collection: currentCollection,
					slug: slug,
					title: title,
					body: body
				}, function(res) {
					if (resultEl) {
						resultEl.textContent = res.ok ? '保存しました' : 'エラー: ' + (res.error || '');
						resultEl.style.color = res.ok ? '#38a169' : '#e53e3e';
					}
					if (res.ok) loadItems(currentCollection);
				});
			});
		}

		/* ── キャンセル ── */
		var cancelBtn = document.getElementById('ap-editor-edit-cancel');
		if (cancelBtn) {
			cancelBtn.addEventListener('click', function() {
				var editArea = document.getElementById('ap-editor-edit-area');
				if (editArea) editArea.style.display = 'none';
			});
		}

		/* ── タイトルからスラッグ自動生成 ── */
		var titleInput = document.getElementById('ap-editor-new-title');
		var slugInput = document.getElementById('ap-editor-new-slug');
		if (titleInput && slugInput) {
			titleInput.addEventListener('input', function() {
				if (slugInput.dataset.manual) return;
				slugInput.value = generateSlug(this.value);
			});
			slugInput.addEventListener('input', function() {
				this.dataset.manual = this.value ? '1' : '';
			});
		}

		/* ── 新規アイテム ── */
		var newCreateBtn = document.getElementById('ap-editor-new-create');
		if (newCreateBtn) {
			newCreateBtn.addEventListener('click', function() {
				var slug = (document.getElementById('ap-editor-new-slug') || {}).value || '';
				var title = (document.getElementById('ap-editor-new-title') || {}).value || '';
				if (!slug.trim()) { alert('スラッグを入力してください'); return; }
				if (!currentCollection) return;
				post('collection_item_save', {
					collection: currentCollection,
					slug: slug.trim(),
					title: title.trim() || slug.trim(),
					body: '',
					is_new: '1'
				}, function(res) {
					if (res.ok) {
						document.getElementById('ap-editor-new-slug').value = '';
						document.getElementById('ap-editor-new-title').value = '';
						loadItems(currentCollection);
						openItemEditor(slug.trim());
					} else {
						alert('エラー: ' + (res.error || ''));
					}
				});
			});
		}

		/* ── Markdown プレビュー（簡易） ── */
		var editBody = document.getElementById('ap-editor-edit-body');
		if (editBody) {
			editBody.addEventListener('input', debounce(updatePreview, 300));
		}

		function updatePreview() {
			var body = (document.getElementById('ap-editor-edit-body') || {}).value || '';
			var previewEl = document.getElementById('ap-editor-preview-html');
			if (!previewEl) return;
			/* サーバーサイドで Markdown 変換する API を使用 */
			var slug = ((document.getElementById('ap-editor-edit-area') || {}).getAttribute('data-slug')) || 'preview';
			fetchApi('item', { collection: currentCollection, slug: slug }, function(res) {
				if (res.ok && res.data && res.data.content) {
					/* R25 fix: サーバーレスポンスの HTML をサニタイズ（XSS 防止） */
					var tmp = document.createElement('div');
					tmp.innerHTML = res.data.content;
					var dangerous = tmp.querySelectorAll('script,iframe,object,embed,form');
					for (var i = 0; i < dangerous.length; i++) dangerous[i].remove();
					var all = tmp.querySelectorAll('*');
					for (var j = 0; j < all.length; j++) {
						var attrs = all[j].attributes;
						for (var a = attrs.length - 1; a >= 0; a--) {
							if (attrs[a].name.indexOf('on') === 0) all[j].removeAttribute(attrs[a].name);
						}
						if (all[j].tagName === 'A' && /^\s*javascript:/i.test(all[j].getAttribute('href') || '')) {
							all[j].removeAttribute('href');
						}
					}
					previewEl.innerHTML = tmp.innerHTML;
				}
			});
		}

		function debounce(fn, ms) {
			var timer;
			return function() {
				clearTimeout(timer);
				timer = setTimeout(fn, ms);
			};
		}
	});
})();
