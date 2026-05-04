// ─── Синхронизация hidden inputs перед отправкой ───────────────────────────
document.getElementById('formText').addEventListener('submit', function () {
    const titleEl    = document.getElementById('b_input');
    const authorEl   = document.getElementById('a_input');
    const editableEl = document.getElementById('editable');

    document.getElementById('titleInput').value    = titleEl    ? titleEl.textContent    : '';
    document.getElementById('authorInput').value   = authorEl   ? authorEl.textContent   : '';
    document.getElementById('editableInput').value = editableEl ? editableEl.innerHTML   : '';
});

// ─── SessionStorage: восстановление черновика ──────────────────────────────
// В режиме редактирования (edit-mode) НЕ загружаем sessionStorage —
// контент уже вставлен сервером, и sessionStorage от create его бы затёр.
document.addEventListener('DOMContentLoaded', function () {
    const editableEl = document.getElementById('editable');
    const isEditMode = editableEl && editableEl.dataset.editMode === 'true';

    if (!isEditMode) {
        document.querySelectorAll('textarea, input, div[contenteditable="true"]').forEach(function (e) {
            const key = e.getAttribute('data-name') || e.getAttribute('name');
            if (!key) return;
            const stored = window.sessionStorage.getItem(key);
            if (stored !== null) {
                if (e.tagName.toLowerCase() === 'div') {
                    e.innerHTML = stored;
                } else {
                    e.value = stored;
                }
            }
            e.addEventListener('input', function () {
                const val = e.tagName.toLowerCase() === 'div' ? e.innerHTML : e.value;
                window.sessionStorage.setItem(key, val);
            });
        });
    } else {
        // В edit-режиме слушаем изменения, но не восстанавливаем из хранилища
        document.querySelectorAll('div[contenteditable="true"]').forEach(function (e) {
            const key = e.getAttribute('data-name');
            if (!key) return;
            e.addEventListener('input', function () {
                window.sessionStorage.setItem('edit_' + key, e.innerHTML);
            });
        });
    }
});

// ─── Очищаем sessionStorage при submit ────────────────────────────────────
document.getElementById('formText').addEventListener('submit', function () {
    document.querySelectorAll('textarea, input, div[contenteditable="true"]').forEach(function (e) {
        const key = e.getAttribute('data-name') || e.getAttribute('name');
        if (key) {
            window.sessionStorage.removeItem(key);
            window.sessionStorage.removeItem('edit_' + key);
        }
    });
});

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

    editable.addEventListener('input', function () {
        const regex = /(https?:\/\/\S+\.(?:jpg|jpeg|png|gif))/gi;
        const sel   = window.getSelection();
        const range = sel && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;

        Array.from(editable.childNodes).forEach(function (node) {
            if (node.nodeType !== Node.TEXT_NODE) return;
            const matches = node.textContent.match(regex);
            if (!matches) return;

            matches.forEach(function (url) {
                const parts    = node.textContent.split(url);
                const img      = document.createElement('img');
                img.src        = url;
                img.alt        = 'Image';
                img.style.cssText = 'max-width:100%;height:auto;';

                if (parts[0]) editable.insertBefore(document.createTextNode(parts[0]), node);
                editable.insertBefore(img, node);
                if (parts[1]) editable.insertBefore(document.createTextNode(parts[1]), node);
                editable.removeChild(node);
            });
        });

        if (range && sel) {
            sel.removeAllRanges();
            sel.addRange(range);
        }
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
