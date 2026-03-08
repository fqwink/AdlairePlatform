document.addEventListener('DOMContentLoaded', function () {
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (!csrfMeta) return; /* CSRF メタタグが存在しない（非ログインページ等）場合は何もしない */
    var csrf = csrfMeta.getAttribute('content');

    /* ── DOM 要素キャッシュ ── */
    var statusEl = document.getElementById('ap-update-status');
    var resultEl = document.getElementById('ap-update-result');

    /* ── インライン通知ヘルパー ── */
    function _apNotify(msg, type) {
        if (!resultEl) return;
        var cls = type === 'error' ? 'color:#c0392b;' : type === 'success' ? 'color:#27ae60;' : 'color:#2d3748;';
        var el = document.createElement('div');
        el.style.cssText = 'padding:8px 12px;margin:4px 0;border-radius:4px;font-size:13px;background:#f7fafc;border:1px solid #e2e8f0;' + cls;
        el.textContent = msg;
        resultEl.appendChild(el);
        setTimeout(function () { if (el.parentNode) el.style.opacity = '0.6'; }, 8000);
    }

    /* ── 更新確認ボタン ── */
    var checkBtn = document.getElementById('ap-check-update');
    if (checkBtn) {
        checkBtn.addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            if (statusEl) statusEl.textContent = '確認中...';
            if (resultEl) resultEl.innerHTML = '';

            _apPost({ ap_action: 'check', csrf: csrf })
                .then(function (data) {
                    if (statusEl) statusEl.textContent = '';
                    if (data.error) {
                        if (resultEl) resultEl.innerHTML =
                            '<span style="color:#c0392b;">エラー: ' + _apEsc(data.error) + '</span>';
                    } else if (data.update_available) {
                        if (resultEl) resultEl.innerHTML =
                            '<b style="color:#27ae60;">バージョン ' + _apEsc(data.latest) + ' が利用可能です</b>' +
                            '（現在: ' + _apEsc(data.current) + '）<br>' +
                            '<button id="ap-apply-update"' +
                            ' data-zip="' + _apEsc(data.zip_url) + '"' +
                            ' data-version="' + _apEsc(data.latest) + '"' +
                            ' style="margin-top:8px;cursor:pointer;">今すぐ更新する</button>' +
                            ' <button id="ap-list-backups" style="margin-top:8px;cursor:pointer;">ロールバック</button>';
                    } else {
                        if (resultEl) resultEl.innerHTML =
                            '<span style="color:#555;">最新バージョン ' + _apEsc(data.current) + ' を使用中です。</span><br>' +
                            '<button id="ap-list-backups" style="margin-top:8px;cursor:pointer;">ロールバック</button>';
                    }
                })
                .catch(function () {
                    if (statusEl) statusEl.textContent = '通信エラーが発生しました。';
                })
                .finally(function () { btn.disabled = false; });
        });
    }

    /* ── 動的生成ボタン用イベント委譲 ── */
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t) return;

        /* 更新適用ボタン */
        if (t.id === 'ap-apply-update') {
            var btn     = t;
            var zip_url = btn.dataset.zip;
            var version = btn.dataset.version;
            btn.disabled = true;
            btn.textContent = '環境確認中...';

            _apPost({ ap_action: 'check_env', csrf: csrf })
                .then(function (env) {
                    if (!env.ok) {
                        var issues = [];
                        if (!env.ziparchive) issues.push('ZipArchive 拡張が無効です');
                        if (!env.url_fopen)  issues.push('allow_url_fopen が無効です');
                        if (!env.writable)   issues.push('ディレクトリへの書き込み権限がありません');
                        _apNotify('更新を実行できません: ' + issues.join(' / '), 'error');
                        btn.disabled = false;
                        btn.textContent = '今すぐ更新する';
                        return;
                    }
                    var diskMsg = env.disk_free >= 0
                        ? '（空き容量: ' + _apFmtSize(env.disk_free) + '）' : '';
                    if (!confirm('アップデートを適用します。' + diskMsg + '\n事前にバックアップが自動作成されます。よろしいですか？')) {
                        btn.disabled = false;
                        btn.textContent = '今すぐ更新する';
                        return;
                    }
                    btn.textContent = '更新中...';
                    return _apPost({ ap_action: 'apply', zip_url: zip_url, version: version, csrf: csrf })
                        .then(function (data) {
                            if (data.error) {
                                _apNotify('更新エラー: ' + data.error, 'error');
                                btn.disabled = false;
                                btn.textContent = '今すぐ更新する';
                            } else {
                                _apNotify(data.message || 'アップデートが完了しました。ページをリロードします...', 'success');
                                setTimeout(function () { location.reload(true); }, 1500);
                            }
                        });
                })
                .catch(function (err) {
                    _apNotify('エラー: ' + (err.message || '更新中にエラーが発生しました。'), 'error');
                    btn.disabled = false;
                    btn.textContent = '今すぐ更新する';
                });
        }

        /* バックアップ一覧ボタン */
        if (t.id === 'ap-list-backups') {
            var btn = t;
            btn.disabled = true;
            var ex = document.getElementById('ap-backup-list');
            if (ex) ex.remove();

            _apPost({ ap_action: 'list_backups', csrf: csrf })
                .then(function (data) {
                    var html = '';
                    if (data.backups && data.backups.length > 0) {
                        html = '<b>バックアップ一覧:</b>' +
                            '<table style="margin-top:6px;border-collapse:collapse;font-size:0.9em;">' +
                            '<tr style="background:#eee;">' +
                            '<th style="padding:3px 8px;text-align:left;">作成日時</th>' +
                            '<th style="padding:3px 8px;text-align:left;">更新前</th>' +
                            '<th style="padding:3px 8px;text-align:right;">サイズ</th>' +
                            '<th style="padding:3px 8px;"></th></tr>';
                        data.backups.forEach(function (b) {
                            var name = b.name;
                            var meta = b.meta || {};
                            var date = meta.created_at    ? _apEsc(meta.created_at)           : _apEsc(_apFmtDate(name));
                            var ver  = meta.version_before ? _apEsc(meta.version_before)      : '―';
                            var size = meta.size_bytes >= 0 ? _apFmtSize(meta.size_bytes)     : '―';
                            html += '<tr>' +
                                '<td style="padding:3px 8px;">' + date + '</td>' +
                                '<td style="padding:3px 8px;">' + ver  + '</td>' +
                                '<td style="padding:3px 8px;text-align:right;">' + size + '</td>' +
                                '<td style="padding:3px 8px;white-space:nowrap;">' +
                                '<button class="ap-do-rollback" data-name="' + _apEsc(name) + '" style="cursor:pointer;">復元</button> ' +
                                '<button class="ap-delete-backup" data-name="' + _apEsc(name) + '" style="cursor:pointer;color:#c0392b;">削除</button>' +
                                '</td></tr>';
                        });
                        html += '</table>';
                    } else {
                        html = '<span style="color:#555;">バックアップはありません。</span>';
                    }
                    if (resultEl) resultEl.insertAdjacentHTML('beforeend', '<div id="ap-backup-list" style="margin-top:10px;">' + html + '</div>');
                })
                .catch(function () { _apNotify('バックアップ一覧の取得に失敗しました。', 'error'); })
                .finally(function () { btn.disabled = false; });
        }

        /* ロールバック実行ボタン */
        if (t.classList.contains('ap-do-rollback')) {
            var btn  = t;
            var name = btn.dataset.name;
            if (!confirm('バックアップ "' + name + '" に復元します。\n現在のファイルは上書きされます。よろしいですか？')) return;
            btn.disabled = true;
            btn.textContent = '復元中...';
            _apPost({ ap_action: 'rollback', backup: name, csrf: csrf })
                .then(function (data) {
                    if (data.error) {
                        _apNotify('復元エラー: ' + data.error, 'error');
                        btn.disabled = false;
                        btn.textContent = '復元';
                    } else {
                        _apNotify(data.message || 'ロールバックが完了しました。ページをリロードします...', 'success');
                        setTimeout(function () { location.reload(true); }, 1500);
                    }
                })
                .catch(function (err) {
                    _apNotify('復元エラー: ' + (err.message || '復元中にエラーが発生しました。'), 'error');
                    btn.disabled = false;
                    btn.textContent = '復元';
                });
        }

        /* バックアップ削除ボタン */
        if (t.classList.contains('ap-delete-backup')) {
            var btn  = t;
            var name = btn.dataset.name;
            if (!confirm('バックアップ "' + name + '" を削除します。この操作は取り消せません。よろしいですか？')) return;
            btn.disabled = true;
            btn.textContent = '削除中...';
            _apPost({ ap_action: 'delete_backup', backup: name, csrf: csrf })
                .then(function (data) {
                    if (data.error) {
                        _apNotify('削除エラー: ' + data.error, 'error');
                        btn.disabled = false;
                        btn.textContent = '削除';
                    } else {
                        var row = btn.closest('tr');
                        if (row) row.remove();
                    }
                })
                .catch(function (err) {
                    _apNotify('削除エラー: ' + (err.message || '削除中にエラーが発生しました。'), 'error');
                    btn.disabled = false;
                    btn.textContent = '削除';
                });
        }
    });

    /* ── ユーティリティ関数 ── */

    function _apPost(params) {
        return fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params)
        }).then(function (r) {
            if (!r.ok) return r.text().then(function (t) {
                try { var d = JSON.parse(t); throw new Error(d.error || 'HTTP ' + r.status); }
                catch (e) { if (e.message && !e.message.startsWith('HTTP ')) throw e; throw new Error('HTTP ' + r.status); }
            });
            return r.json();
        });
    }

    function _apEsc(s) {
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function _apFmtDate(name) {
        var m = name.match(/^(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})$/);
        if (!m) return name;
        return m[1] + '-' + m[2] + '-' + m[3] + ' ' + m[4] + ':' + m[5] + ':' + m[6];
    }

    function _apFmtSize(bytes) {
        if (bytes < 1024)        return bytes + ' B';
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
});
