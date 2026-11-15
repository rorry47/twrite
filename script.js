// Sanitize browser-side draft/preview HTML; server sanitizer remains authoritative.
function twriteSanitizeDraft(html) {
    const template = document.createElement('template');
    template.innerHTML = html;
    const allowed = new Set(['B','STRONG','I','EM','U','S','STRIKE','A','BR','P','DIV','PRE','CODE','IMG','FONT','SPAN','UL','OL','LI','BLOCKQUOTE']);
    const blocked = new Set(['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','SVG','MATH','FORM','INPUT','BUTTON','TEXTAREA','TEMPLATE','VIDEO','AUDIO','BASE','META','LINK']);
    function safeUrl(value) {
        try {
            if (/[\\x00-\\x20\\x7f]/.test(value)) return '';
            const url = new URL(value);
            return url.protocol === 'https:' || url.protocol === 'http:' ? value : '';
        } catch (e) { return ''; }
    }
    function clean(parent) {
        Array.from(parent.childNodes).forEach(function (node) {
            if (node.nodeType === Node.COMMENT_NODE) { node.remove(); return; }
            if (node.nodeType !== Node.ELEMENT_NODE) return;
            if (blocked.has(node.tagName)) { node.remove(); return; }
            clean(node);
            if (!allowed.has(node.tagName)) {
                node.replaceWith(...Array.from(node.childNodes));
                return;
            }
            const href = node.tagName === 'A' ? safeUrl(node.getAttribute('href') || '') : '';
            const src = node.tagName === 'IMG' ? safeUrl(node.getAttribute('src') || '') : '';
            const fontSize = /(?:^|;)\\s*font-size\\s*:\\s*(1[0-9]|2[0-9]|30)px\\s*(?:;|$)/i.exec(node.getAttribute('style') || '');
            Array.from(node.attributes).forEach(attr => node.removeAttribute(attr.name));
            if (node.tagName === 'A' && href) {
                node.setAttribute('href', href);
                node.setAttribute('rel', 'nofollow noopener noreferrer');
            }
            if (node.tagName === 'IMG') {
                if (!src) { node.remove(); return; }
                node.setAttribute('src', src);
                node.setAttribute('alt', 'Image');
                node.setAttribute('loading', 'lazy');
            }
            if (fontSize && (node.tagName === 'FONT' || node.tagName === 'SPAN')) {
                node.setAttribute('style', 'font-size:' + fontSize[1] + 'px');
            }
        });
    }
    clean(template.content);
    return template.innerHTML;
}

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
                    if (id === 'editable') el.innerHTML = twriteSanitizeDraft(value);
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

// Theme selector shared by editor, reader and locked publications.
(function () {
    const themes = [
        {id:'light', label:'Paper', colors:['#faf9f6','#a69abf']},
        {id:'dark', label:'Midnight', colors:['#17191f','#b1aace']},
        {id:'black', label:'OLED Black', colors:['#08090c','#9eabff']},
        {id:'sepia', label:'Sepia', colors:['#f4ead7','#a16d42']},
        {id:'rose', label:'Rose', colors:['#fff3f5','#ce708e']},
        {id:'ocean', label:'Ocean', colors:['#eaf5f7','#277f91']},
        {id:'forest', label:'Forest', colors:['#eaf2e9','#477f62']}
    ];
    function initialTheme() {
        let saved = null;
        try { saved = localStorage.getItem('theme'); } catch (e) {}
        if (themes.some(t => t.id === saved)) return saved;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    function applyTheme(id) {
        document.documentElement.setAttribute('data-theme', id);
        try { localStorage.setItem('theme', id); } catch (e) {}
        document.querySelectorAll('[data-theme-option]').forEach(button => {
            const selected = button.dataset.themeOption === id;
            button.setAttribute('aria-checked', String(selected));
            button.classList.toggle('selected', selected);
        });
    }
    applyTheme(initialTheme());
    document.addEventListener('DOMContentLoaded', function () {
        const existing = document.getElementById('theme-toggle');
        if (existing) existing.remove();
        const picker = document.createElement('div');
        picker.className = 'theme-picker';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = 'theme-toggle';
        trigger.className = 'theme-picker-trigger';
        trigger.setAttribute('aria-label', 'Choose color theme');
        trigger.setAttribute('aria-haspopup', 'true');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.innerHTML = '<i class="fa-solid fa-palette" aria-hidden="true"></i><span>Themes</span>';
        const menu = document.createElement('div');
        menu.className = 'theme-picker-menu';
        menu.setAttribute('role', 'radiogroup');
        menu.setAttribute('aria-label', 'Color theme');
        menu.hidden = true;
        const heading = document.createElement('div');
        heading.className = 'theme-picker-heading';
        heading.textContent = 'Appearance';
        menu.appendChild(heading);
        themes.forEach(theme => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'theme-picker-option';
            button.dataset.themeOption = theme.id;
            button.setAttribute('role', 'radio');
            const swatch = document.createElement('span');
            swatch.className = 'theme-swatch';
            swatch.style.setProperty('--swatch-bg', theme.colors[0]);
            swatch.style.setProperty('--swatch-accent', theme.colors[1]);
            const name = document.createElement('span');
            name.textContent = theme.label;
            const check = document.createElement('i');
            check.className = 'fa-solid fa-check';
            check.setAttribute('aria-hidden', 'true');
            button.append(swatch, name, check);
            button.addEventListener('click', () => {
                applyTheme(theme.id);
                setOpen(false);
                trigger.focus();
            });
            menu.appendChild(button);
        });
        picker.append(trigger, menu);
        document.body.appendChild(picker);
        function setOpen(open) {
            menu.hidden = !open;
            trigger.setAttribute('aria-expanded', String(open));
        }
        trigger.addEventListener('click', () => setOpen(menu.hidden));
        document.addEventListener('pointerdown', event => {
            if (!picker.contains(event.target)) setOpen(false);
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !menu.hidden) {
                setOpen(false);
                trigger.focus();
            }
        });
        applyTheme(initialTheme());
    });
})();

// Generate a cryptographically secure password without sending it anywhere.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.generate-password').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = button.closest('.password-field')?.querySelector('input[name="password"]');
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


// Copy the password without changing its value.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.copy-password').forEach(function (button) {
        button.addEventListener('click', async function () {
            const input = button.closest('.password-field')?.querySelector('input[name="password"]');
            if (!input || !input.value) {
                button.title = 'Enter or generate a password first';
                return;
            }
            try {
                await navigator.clipboard.writeText(input.value);
                const icon = button.querySelector('i');
                if (icon) icon.className = 'fa-solid fa-check';
                button.title = 'Copied';
                button.setAttribute('aria-label', 'Password copied');
                window.setTimeout(function () {
                    if (icon) icon.className = 'fa-regular fa-copy';
                    button.title = 'Copy password';
                    button.setAttribute('aria-label', 'Copy password');
                }, 1500);
            } catch (error) {
                // Clipboard permission may be denied; select for manual copy.
                input.focus();
                input.select();
                button.title = 'Press Ctrl+C or Cmd+C to copy';
            }
        });
    });
});


// Preview locally without submitting or persisting the publication.
document.addEventListener('DOMContentLoaded', function () {
    const dialog = document.getElementById('preview-dialog');
    const open = document.getElementById('preview-post');
    const close = document.getElementById('close-preview');
    if (!dialog || !open || !close) return;
    open.addEventListener('click', function () {
        document.getElementById('preview-title').textContent = document.getElementById('b_input')?.textContent || 'Untitled';
        document.getElementById('preview-author').textContent = document.getElementById('a_input')?.textContent || '';
        const target = document.getElementById('preview-body');
        target.innerHTML = twriteSanitizeDraft(document.getElementById('editable').innerHTML);
        dialog.showModal();
    });
    close.addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
});

// Keyboard shortcuts: Cmd/Ctrl+S saves or publishes, Cmd/Ctrl+B formats selection.
document.addEventListener('keydown', function (event) {
    if (!(event.ctrlKey || event.metaKey) || event.altKey) return;
    const form = document.getElementById('formText');
    if (!form || document.getElementById('preview-dialog')?.open) return;
    const key = event.key.toLowerCase();
    if (key === 's') {
        event.preventDefault();
        const submit = document.getElementById('publish');
        if (submit) form.requestSubmit(submit);
    } else if (key === 'b') {
        const selection = window.getSelection();
        const editor = document.getElementById('editable');
        if (selection && selection.rangeCount && editor && editor.contains(selection.anchorNode)) {
            event.preventDefault();
            document.execCommand('bold', false, null);
        }
    }
});


// Live writing metrics; count Unicode characters rather than UTF-16 units.
(function () {
    function measure(element) {
        const text = element?.textContent || '';
        const words = text.trim().match(/[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/gu) || [];
        return {words: words.length, characters: Array.from(text).length, minutes: words.length ? Math.max(1, Math.ceil(words.length / 200)) : 0};
    }
    document.addEventListener('DOMContentLoaded', function () {
        const editor = document.getElementById('editable');
        if (!editor) return;
        const wordsEl = document.getElementById('word-count');
        const charsEl = document.getElementById('char-count');
        const timeEl = document.getElementById('reading-time');
        const readerTime = document.getElementById('reader-reading-time');
        const update = function () {
            const result = measure(editor);
            if (wordsEl) wordsEl.textContent = result.words + ' words';
            if (charsEl) charsEl.textContent = result.characters + ' characters';
            if (timeEl) timeEl.textContent = result.minutes + ' min read';
            if (readerTime) readerTime.textContent = result.minutes + ' min read';
        };
        update();
        if (wordsEl) {
            editor.addEventListener('input', update);
            // Restore may run earlier during DOMContentLoaded.
            window.addEventListener('pageshow', update);
        }
    });
})();

// Focus mode hides page chrome and publication settings, not the actual editor.
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('focus-toggle');
    if (!toggle) return;
    const setFocus = function (enabled) {
        document.body.classList.toggle('focus-mode', enabled);
        toggle.setAttribute('aria-pressed', String(enabled));
        toggle.innerHTML = enabled ? '<i class="fa-solid fa-compress"></i> Exit focus' : '<i class="fa-solid fa-expand"></i> Focus';
        toggle.title = enabled ? 'Exit focus mode (Esc)' : 'Focus mode';
    };
    toggle.addEventListener('click', function () { setFocus(!document.body.classList.contains('focus-mode')); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.body.classList.contains('focus-mode')) setFocus(false);
    });
});
