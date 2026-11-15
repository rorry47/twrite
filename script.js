// ─── Синхронизация hidden inputs перед отправкой ───────────────────────────
document.getElementById('formText')?.addEventListener('submit', function (event) {
    const titleEl    = document.getElementById('b_input');
    const authorEl   = document.getElementById('a_input');
    const editableEl = document.getElementById('editable');

    document.getElementById('titleInput').value    = titleEl    ? titleEl.textContent    : '';
    document.getElementById('authorInput').value   = authorEl   ? authorEl.textContent   : '';
    document.getElementById('editableInput').value = editableEl ? editableEl.innerHTML   : '';

    // Keep the current editor intact when required fields are missing.
    const title = titleEl ? titleEl.textContent.trim() : '';
    const body = editableEl ? editableEl.textContent.trim() : '';
    const invalid = !title ? titleEl : (!body && !editableEl?.querySelector('img') ? editableEl : null);
    if (invalid) {
        event.preventDefault();
        invalid.focus();
        invalid.setAttribute('aria-invalid', 'true');
        invalid.style.outline = '2px solid #dc3545';
        invalid.style.outlineOffset = '4px';
        invalid.addEventListener('input', function clearInvalid() {
            invalid.removeAttribute('aria-invalid');
            invalid.style.outline = '';
            invalid.style.outlineOffset = '';
            invalid.removeEventListener('input', clearInvalid);
        });
        window.alert(!title ? 'Enter a publication title.' : 'Enter publication text.');
    }
});

// ─── Per-tab drafts: isolated by editor mode and post slug ──────────────
(function () {
    const params = new URLSearchParams(window.location.search);
    const saved = params.get('draft_saved');
    if (saved === 'create' || /^edit_[a-f0-9]{32}$/.test(saved || '')) {
        try { sessionStorage.removeItem('twrite_draft_' + saved); } catch (e) {}
        params.delete('draft_saved');
        const clean = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
        history.replaceState(null, '', clean);
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('formText');
    if (!form) return;
    const editor = document.getElementById('editable');
    const editing = editor && editor.dataset.editMode === 'true';
    const slug = new URLSearchParams(window.location.search).get('edit');
    const scope = editing && /^[a-f0-9]{32}$/.test(slug || '') ? 'edit_' + slug : 'create';
    const storageKey = 'twrite_draft_' + scope;
    const fields = ['b_input', 'a_input', 'editable'];
    const snapshot = () => {
        const data = {};
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) data[id] = id === 'editable' ? el.innerHTML : el.textContent;
        });
        ['delete', 'access_ip'].forEach(id => {
            const el = document.getElementById(id);
            if (el) data[id] = el.checked;
        });
        return data;
    };
    let baseline = JSON.stringify(snapshot());
    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        if (saved && typeof saved === 'object' && saved.fields) {
            const restore = !editing || window.confirm('Restore unsaved edits for this post?');
            if (restore) {
                fields.forEach(id => {
                    const el = document.getElementById(id);
                    const value = saved.fields[id];
                    if (!el || typeof value !== 'string') return;
                    if (id === 'editable') el.innerHTML = value;
                    else el.textContent = value;
                });
                ['delete', 'access_ip'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el && typeof saved.fields[id] === 'boolean') el.checked = saved.fields[id];
                });
                baseline = JSON.stringify(snapshot());
            }
        }
    } catch (e) {}
    let pending;
    const save = () => {
        try {
            const current = snapshot();
            if (JSON.stringify(current) !== baseline) {
                sessionStorage.setItem(storageKey, JSON.stringify({fields: current}));
            }
        } catch (e) {}
    };
    const schedule = () => { clearTimeout(pending); pending = setTimeout(save, 350); };
    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    form.addEventListener('submit', () => { clearTimeout(pending); save(); });
    window.addEventListener('pagehide', () => { clearTimeout(pending); save(); });
});

// ─── Preserve drafts on submit; only clear after confirmed redirect ─────
// ─── Placeholder для contenteditable ──────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('div[contenteditable="true"]').forEach(function (div) {
        function checkEmpty() {
            div.classList.toggle('empty', div.textContent.trim() === '');
        }
        checkEmpty();
        div.addEventListener('input', checkEmpty);
    });
});

// ─── Лимиты символов ───────────────────────────────────────────────────────
function applyMaxLength(selector, max) {
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.querySelector(selector);
        if (!el) return;
        el.addEventListener('input', function () {
            if (el.textContent.length > max) {
                el.textContent = el.textContent.substring(0, max);
                const range = document.createRange();
                const sel   = window.getSelection();
                range.selectNodeContents(el);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
            }
        });
    });
}
applyMaxLength('.b_input',  100);
applyMaxLength('.a_input',   60);
applyMaxLength('.editable', 32000);

// ─── Авто-превью изображений при вставке URL ───────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const editable = document.getElementById('editable');
    if (!editable) return;

    // Convert only a complete pasted/typed image URL in a text node.
    editable.addEventListener('input', function () {
        const regex = /https?:\/\/[^\s<>"']+?\.(?:jpe?g|png|gif|webp)(?:\?[^\s<>"']*)?/gi;
        const walker = document.createTreeWalker(editable, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(function (node) {
            if (!node.parentNode || node.parentElement.closest('a,code,pre')) return;
            const text = node.nodeValue;
            regex.lastIndex = 0;
            if (!regex.test(text)) return;
            regex.lastIndex = 0;
            const fragment = document.createDocumentFragment();
            let last = 0, match;
            while ((match = regex.exec(text)) !== null) {
                fragment.appendChild(document.createTextNode(text.slice(last, match.index)));
                const img = document.createElement('img');
                img.src = match[0];
                img.alt = 'Image';
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
                fragment.appendChild(img);
                last = match.index + match[0].length;
            }
            fragment.appendChild(document.createTextNode(text.slice(last)));
            node.parentNode.replaceChild(fragment, node);
        });
    });
});

// ─── Тулбар форматирования ─────────────────────────────────────────────────
document.addEventListener('mouseup', function (event) {
    const sel      = window.getSelection();
    const toolbar  = document.getElementById('toolbar');
    const editable = document.querySelector('.editable');
    if (!toolbar || !editable) return;

    if (sel && sel.toString().length > 0 && editable.contains(sel.anchorNode)) {
        const rect = sel.getRangeAt(0).getBoundingClientRect();
        toolbar.style.display = 'flex';
        toolbar.style.top  = `${rect.top  + window.scrollY - toolbar.offsetHeight - 5}px`;
        toolbar.style.left = `${rect.left + window.scrollX}px`;
    } else {
        toolbar.style.display = 'none';
    }
});

document.addEventListener('mousedown', function (event) {
    const toolbar = document.getElementById('toolbar');
    if (!toolbar) return;
    if (!event.target.closest('#toolbar') && !event.target.closest('.editable')) {
        toolbar.style.display = 'none';
    }
});

// ─── Функции тулбара ───────────────────────────────────────────────────────
function changeFontSize(size) {
    document.execCommand('fontSize', false, '7');
    Array.from(document.querySelectorAll("font[size='7']")).forEach(function (el) {
        el.removeAttribute('size');
        el.style.fontSize = size;
    });
}

function insertLink() {
    navigator.clipboard.readText().then(function (text) {
        if (text.startsWith('http')) {
            document.execCommand('createLink', false, text);
        } else {
            const link = prompt('Enter URL:', 'https://');
            if (link) document.execCommand('createLink', false, link);
        }
    }).catch(function () {
        const link = prompt('Enter URL:', 'https://');
        if (link) document.execCommand('createLink', false, link);
    });
}

function formatAsCode() {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const range    = sel.getRangeAt(0);
    const contents = range.extractContents();
    const pre      = document.createElement('pre');
    const code     = document.createElement('code');
    code.appendChild(contents);
    pre.appendChild(code);
    range.insertNode(pre);
    sel.removeAllRanges();
}

// ─── Споiler ───────────────────────────────────────────────────────────────
function toggleSpoiler(element) {
    const content = element.nextElementSibling;
    if (content.style.maxHeight) {
        content.style.maxHeight  = null;
        content.style.paddingTop = '0';
        content.style.paddingBottom = '0';
    } else {
        content.style.maxHeight  = content.scrollHeight + 'px';
        content.style.paddingTop = '10px';
        content.style.paddingBottom = '10px';
    }
}

// ─── Тёмная тема ───────────────────────────────────────────────────────────
(function () {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    // Кнопка переключения темы — добавляем динамически
    const btn = document.createElement('button');
    btn.id        = 'theme-toggle';
    btn.innerHTML = '<i class="fa-solid fa-circle-half-stroke"></i>';
    btn.title     = 'Toggle dark mode';
    document.body.appendChild(btn);

    btn.addEventListener('click', function () {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        document.documentElement.setAttribute('data-theme', isDark ? 'light' : 'dark');
        localStorage.setItem('theme', isDark ? 'light' : 'dark');
    });
});


// Generate a cryptographically secure password without sending it anywhere.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.generate-password').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = button.parentElement.querySelector('input[type="password"]');
            if (!input || !window.crypto || !window.crypto.getRandomValues) return;
            const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*-_';
            const limit = Math.floor(256 / alphabet.length) * alphabet.length;
            let password = '';
            while (password.length < 20) {
                const bytes = new Uint8Array(32);
                window.crypto.getRandomValues(bytes);
                for (const byte of bytes) {
                    if (byte >= limit) continue;
                    password += alphabet[byte % alphabet.length];
                    if (password.length === 20) break;
                }
            }
            input.value = password;
            input.type = 'text';
            input.dispatchEvent(new Event('input', {bubbles: true}));
            button.setAttribute('title', 'Password generated; copy it before publishing');
            button.setAttribute('aria-label', 'Generate another secure password');
            input.focus();
            input.select();
        });
    });
});
