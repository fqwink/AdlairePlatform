var ta = document.createElement('textarea');
ta.name  = 'textarea';
ta.id    = a.id + '_field';
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
