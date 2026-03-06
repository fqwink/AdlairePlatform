$(document).ready(function () {
    var csrf = $('meta[name="csrf-token"]').attr('content');

    /* ── 更新確認ボタン ── */
    $('#ap-check-update').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true);
        $('#ap-update-status').text('確認中...');
        $('#ap-update-result').empty();

        $.post('index.php', { ap_action: 'check', csrf: csrf })
            .done(function (data) {
                $('#ap-update-status').text('');
                if (data.error) {
                    $('#ap-update-result').html(
                        '<span style="color:#c0392b;">エラー: ' + esc(data.error) + '</span>'
                    );
                } else if (data.update_available) {
                    $('#ap-update-result').html(
                        '<b style="color:#27ae60;">バージョン ' + esc(data.latest) + ' が利用可能です</b>' +
                        '（現在: ' + esc(data.current) + '）<br>' +
                        '<button id="ap-apply-update"' +
                        ' data-zip="' + esc(data.zip_url) + '"' +
                        ' data-version="' + esc(data.latest) + '"' +
                        ' style="margin-top:8px;cursor:pointer;">今すぐ更新する</button>' +
                        ' <button id="ap-list-backups" style="margin-top:8px;cursor:pointer;">ロールバック</button>'
                    );
                } else {
                    $('#ap-update-result').html(
                        '<span style="color:#555;">最新バージョン ' + esc(data.current) + ' を使用中です。</span><br>' +
                        '<button id="ap-list-backups" style="margin-top:8px;cursor:pointer;">ロールバック</button>'
                    );
                }
            })
            .fail(function () {
                $('#ap-update-status').text('通信エラーが発生しました。');
            })
            .always(function () {
                btn.prop('disabled', false);
            });
    });

    /* ── 更新適用ボタン（動的生成）── */
    $(document).on('click', '#ap-apply-update', function () {
        var btn     = $(this);
        var zip_url = btn.data('zip');
        var version = btn.data('version');

        /* 事前環境チェック */
        btn.prop('disabled', true).text('環境確認中...');
        $.post('index.php', { ap_action: 'check_env', csrf: csrf })
            .done(function (env) {
                if (!env.ok) {
                    var issues = [];
                    if (!env.ziparchive) issues.push('ZipArchive 拡張が無効です');
                    if (!env.url_fopen)  issues.push('allow_url_fopen が無効です');
                    if (!env.writable)   issues.push('ディレクトリへの書き込み権限がありません');
                    alert('更新を実行できません:\n・' + issues.join('\n・'));
                    btn.prop('disabled', false).text('今すぐ更新する');
                    return;
                }
                var diskMsg = env.disk_free >= 0
                    ? '（空き容量: ' + formatSize(env.disk_free) + '）'
                    : '';
                if (!confirm('アップデートを適用します。' + diskMsg + '\n事前にバックアップが自動作成されます。よろしいですか？')) {
                    btn.prop('disabled', false).text('今すぐ更新する');
                    return;
                }
                btn.text('更新中...');
                $.post('index.php', {
                    ap_action: 'apply',
                    zip_url:   zip_url,
                    version:   version,
                    csrf:      csrf
                })
                    .done(function (data) {
                        if (data.error) {
                            alert('エラー: ' + data.error);
                            btn.prop('disabled', false).text('今すぐ更新する');
                        } else {
                            alert(data.message || 'アップデートが完了しました。');
                            location.reload(true);
                        }
                    })
                    .fail(function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.error)
                            ? xhr.responseJSON.error : '更新中にエラーが発生しました。';
                        alert('エラー: ' + msg);
                        btn.prop('disabled', false).text('今すぐ更新する');
                    });
            })
            .fail(function () {
                alert('環境チェックに失敗しました。');
                btn.prop('disabled', false).text('今すぐ更新する');
            });
    });

    /* ── バックアップ一覧ボタン（動的生成）── */
    $(document).on('click', '#ap-list-backups', function () {
        var btn = $(this);
        btn.prop('disabled', true);
        $('#ap-backup-list').remove();

        $.post('index.php', { ap_action: 'list_backups', csrf: csrf })
            .done(function (data) {
                var html = '';
                if (data.backups && data.backups.length > 0) {
                    html = '<b>バックアップ一覧:</b>' +
                        '<table style="margin-top:6px;border-collapse:collapse;font-size:0.9em;">' +
                        '<tr style="background:#eee;">' +
                        '<th style="padding:3px 8px;text-align:left;">作成日時</th>' +
                        '<th style="padding:3px 8px;text-align:left;">更新前</th>' +
                        '<th style="padding:3px 8px;text-align:right;">サイズ</th>' +
                        '<th style="padding:3px 8px;"></th>' +
                        '</tr>';
                    data.backups.forEach(function (b) {
                        var name    = b.name;
                        var meta    = b.meta || {};
                        var date    = meta.created_at  ? esc(meta.created_at)               : esc(formatBackupDate(name));
                        var ver     = meta.version_before ? esc(meta.version_before)         : '―';
                        var size    = meta.size_bytes >= 0 ? formatSize(meta.size_bytes)     : '―';
                        html += '<tr>' +
                            '<td style="padding:3px 8px;">' + date + '</td>' +
                            '<td style="padding:3px 8px;">' + ver  + '</td>' +
                            '<td style="padding:3px 8px;text-align:right;">' + size + '</td>' +
                            '<td style="padding:3px 8px;white-space:nowrap;">' +
                            '<button class="ap-do-rollback" data-name="' + esc(name) + '" style="cursor:pointer;">復元</button> ' +
                            '<button class="ap-delete-backup" data-name="' + esc(name) + '" style="cursor:pointer;color:#c0392b;">削除</button>' +
                            '</td></tr>';
                    });
                    html += '</table>';
                } else {
                    html = '<span style="color:#555;">バックアップはありません。</span>';
                }
                $('#ap-update-result').append(
                    '<div id="ap-backup-list" style="margin-top:10px;">' + html + '</div>'
                );
            })
            .fail(function () {
                alert('通信エラーが発生しました。');
            })
            .always(function () {
                btn.prop('disabled', false);
            });
    });

    /* ── ロールバック実行ボタン（動的生成）── */
    $(document).on('click', '.ap-do-rollback', function () {
        var name = $(this).data('name');
        if (!confirm('バックアップ "' + name + '" に復元します。\n現在のファイルは上書きされます。よろしいですか？')) return;
        var btn = $(this);
        btn.prop('disabled', true).text('復元中...');

        $.post('index.php', {
            ap_action: 'rollback',
            backup:    name,
            csrf:      csrf
        })
            .done(function (data) {
                if (data.error) {
                    alert('エラー: ' + data.error);
                    btn.prop('disabled', false).text('復元');
                } else {
                    alert(data.message || 'ロールバックが完了しました。');
                    location.reload(true);
                }
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error : '復元中にエラーが発生しました。';
                alert('エラー: ' + msg);
                btn.prop('disabled', false).text('復元');
            });
    });

    /* ── バックアップ削除ボタン（動的生成）── */
    $(document).on('click', '.ap-delete-backup', function () {
        var name = $(this).data('name');
        if (!confirm('バックアップ "' + name + '" を削除します。この操作は取り消せません。よろしいですか？')) return;
        var btn = $(this);
        btn.prop('disabled', true).text('削除中...');

        $.post('index.php', {
            ap_action: 'delete_backup',
            backup:    name,
            csrf:      csrf
        })
            .done(function (data) {
                if (data.error) {
                    alert('エラー: ' + data.error);
                    btn.prop('disabled', false).text('削除');
                } else {
                    btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
                }
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error : '削除中にエラーが発生しました。';
                alert('エラー: ' + msg);
                btn.prop('disabled', false).text('削除');
            });
    });

    /* ── ユーティリティ関数 ── */

    /* XSS 対策エスケープ */
    function esc(s) {
        return $('<span>').text(String(s)).html();
    }

    /* バックアップ名（YYYYMMDD_HHIISS）を読みやすい日時に変換 */
    function formatBackupDate(name) {
        var m = name.match(/^(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})$/);
        if (!m) return name;
        return m[1]+'-'+m[2]+'-'+m[3]+' '+m[4]+':'+m[5]+':'+m[6];
    }

    /* バイト数を KB / MB 表示に変換 */
    function formatSize(bytes) {
        if (bytes < 1024)        return bytes + ' B';
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
});
