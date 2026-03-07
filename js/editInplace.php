var _apChanging = false;

document.addEventListener('DOMContentLoaded', function () {
    /* ── インライン編集 ── */
    document.querySelectorAll('span.editText').forEach(function (el) {
        el.addEventListener('click', function () {
            if (_apChanging) return;
            _apChanging = true;
            var a  = this;
            var ta = document.createElement('textarea');
            ta.name = 'textarea';
            ta.id   = a.id + '_field';
            if (a.title) ta.setAttribute('title', a.title);
            ta.value = a.innerHTML.replace(/<br\s*\/?>/gi, "\n");
            ta.addEventListener('blur', function handler() {
                ta.removeEventListener('blur', handler);
                _apFieldSave(ta.id.slice(0, -6), _apNl2br(ta.value));
            });
            a.innerHTML = '';
            a.appendChild(ta);
            ta.focus();
            _apAutosize(ta);
        });
    });

    /* ── メニューリフレッシュリンク ── */
    var refreshLink = document.getElementById('ap-refresh-link');
    if (refreshLink) {
        refreshLink.addEventListener('click', function (e) {
            e.preventDefault();
            location.reload(true);
        });
    }

    /* ── テーマ選択 ── */
    var themeSelect = document.getElementById('ap-theme-select');
    if (themeSelect) {
        themeSelect.addEventListener('change', function () {
            _apFieldSave('themeSelect', this.value);
        });
    }

    /* ── 設定パネル開閉 ── */
    document.querySelectorAll('.toggle').forEach(function (el) {
        el.addEventListener('click', function () {
            document.querySelectorAll('.hide').forEach(function (h) {
                h.style.display = (h.style.display === 'block') ? 'none' : 'block';
            });
        });
    });
});

/* テキストエリア高さ自動調整 */
function _apAutosize(el) {
    el.style.boxSizing  = 'border-box';
    el.style.overflowY  = 'hidden';
    el.style.resize     = 'none';
    el.style.height     = 'auto';
    el.style.height     = el.scrollHeight + 'px';
    el.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
}

/* 改行 → <br /> 変換（\r\n を \n に正規化してから変換） */
function _apNl2br(s) {
    return (s + '').replace(/\r\n|\r/g, '\n').replace(/([^>\n]?)(\n)/g, '$1<br />\n');
}

/* フィールド保存（Fetch API） */
function _apFieldSave(key, val) {
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    fetch('index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': csrf
        },
        body: new URLSearchParams({ fieldname: key, content: val, csrf: csrf })
    }).then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
    }).then(function (data) {
        if (key === 'themeSelect') {
            location.reload(true);
        } else {
            var el = document.getElementById(key);
            if (el) el.innerHTML = (val === '') ? (el.getAttribute('title') || '') : data;
        }
        _apChanging = false;
    }).catch(function () {
        alert('保存に失敗しました。再試行してください。');
        _apChanging = false;
    });
}
