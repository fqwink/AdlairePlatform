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
        if (!confirm('アップデートを適用します。\n事前にバックアップが自動作成されます。よろしいですか？')) return;
        var btn     = $(this);
        var zip_url = btn.data('zip');
        var version = btn.data('version');
        btn.prop('disabled', true).text('更新中...');

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
            .fail(function () {
                alert('更新中にエラーが発生しました。');
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
                    html = '<b>バックアップ一覧:</b><ul style="margin:5px 0;padding-left:20px;">';
                    data.backups.forEach(function (name) {
                        html += '<li>' + esc(name) +
                            ' <button class="ap-do-rollback" data-name="' + esc(name) +
                            '" style="cursor:pointer;">復元</button></li>';
                    });
                    html += '</ul>';
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
            .fail(function () {
                alert('復元中にエラーが発生しました。');
                btn.prop('disabled', false).text('復元');
            });
    });

    /* ── XSS 対策用エスケープヘルパー ── */
    function esc(s) {
        return $('<span>').text(String(s)).html();
    }
});
