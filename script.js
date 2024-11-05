    document.getElementById('formText').addEventListener('submit', function() {
        const content_title = document.querySelector('.b_input').textContent;
        document.getElementById('titleInput').value = content_title;

        const content_author = document.querySelector('.a_input').textContent;
        document.getElementById('authorInput').value = content_author;

        const content_editable = document.querySelector('.editable').innerHTML;
        document.getElementById('editableInput').value = content_editable;
    });


    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('textarea, input, div[contenteditable="true"]').forEach(function(e) {
            const storedValue = window.sessionStorage.getItem(e.getAttribute('data-name') || e.getAttribute('name'));
            if (storedValue !== null && e.tagName.toLowerCase() === 'div') {
                e.innerHTML = storedValue;
            } else if (storedValue !== null) {
                e.value = storedValue;
            }

            e.addEventListener('input', function() {
                const key = e.getAttribute('data-name') || e.getAttribute('name');
                if (key) {
                    const value = e.tagName.toLowerCase() === 'div' ? e.innerHTML : e.value;
                    window.sessionStorage.setItem(key, value);
                }
            });
        });
    });




document.getElementById('editable').addEventListener('input', function(event) {
    const editableDiv = event.currentTarget;
    
     const regex = /(https?:\/\/\S+\.(jpg|jpeg|png|gif))/gi;

     const selection = window.getSelection();
    const range = selection.getRangeAt(0).cloneRange();

     editableDiv.childNodes.forEach((node) => {
         if (node.nodeType === Node.TEXT_NODE && regex.test(node.textContent)) {
            const matches = node.textContent.match(regex);

            matches.forEach(url => {
                 const img = document.createElement("img");
                img.src = url;
                img.alt = "Image";
                img.style.maxWidth = "100%";
                img.style.height = "auto";

                 const textBefore = node.textContent.split(url)[0];
                const textAfter = node.textContent.split(url)[1];
                
                 if (textBefore) {
                    editableDiv.insertBefore(document.createTextNode(textBefore), node);
                }
                editableDiv.insertBefore(img, node);
                if (textAfter) {
                    editableDiv.insertBefore(document.createTextNode(textAfter), node);
                }
                editableDiv.removeChild(node);
            });
        }
    });

     selection.removeAllRanges();
    selection.addRange(range);
});



    document.addEventListener("DOMContentLoaded", function() {
        const editableDivs = document.querySelectorAll('div[contenteditable="true"]');

        editableDivs.forEach(div => {
            function checkEmptyDiv() {
                if (div.textContent.trim() === "") {
                    div.classList.add("empty");
                } else {
                    div.classList.remove("empty");
                }
            }

            checkEmptyDiv();

            div.addEventListener("input", checkEmptyDiv);
        });
    });

    document.getElementById("formText").addEventListener("submit", function() {
        document.querySelectorAll('textarea, input, div[contenteditable="true"]').forEach(function(e) {
            const key = e.getAttribute('data-name') || e.getAttribute('name');
            if (key) {
                window.sessionStorage.removeItem(key);
            }
        });
    });

    document.addEventListener("DOMContentLoaded", function() {
        const editableDiv = document.querySelector('.b_input');
        const maxLength = 100;

        editableDiv.addEventListener("input", function() {
            if (editableDiv.textContent.length > maxLength) {
                editableDiv.textContent = editableDiv.textContent.substring(0, maxLength);

                const range = document.createRange();
                const selection = window.getSelection();
                range.selectNodeContents(editableDiv);
                range.collapse(false);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        });
    });

    document.addEventListener("DOMContentLoaded", function() {
        const editableDiv = document.querySelector('.a_input');
        const maxLength = 60;

        editableDiv.addEventListener("input", function() {
            if (editableDiv.textContent.length > maxLength) {
                editableDiv.textContent = editableDiv.textContent.substring(0, maxLength);

                const range = document.createRange();
                const selection = window.getSelection();
                range.selectNodeContents(editableDiv);
                range.collapse(false);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        });
    });

    document.addEventListener("DOMContentLoaded", function() {
        const editableDiv = document.querySelector('.editable');
        const maxLength = 32000;

        editableDiv.addEventListener("input", function() {
            if (editableDiv.textContent.length > maxLength) {
                editableDiv.textContent = editableDiv.textContent.substring(0, maxLength);

                const range = document.createRange();
                const selection = window.getSelection();
                range.selectNodeContents(editableDiv);
                range.collapse(false);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        });
    });



    function toggleSpoiler(element) {
        const content = element.nextElementSibling;

        if (content.style.maxHeight) {
            content.style.maxHeight = null;
            content.style.paddingTop = "0";
            content.style.paddingBottom = "0";
        } else {
            content.style.maxHeight = content.scrollHeight + "px";
            content.style.paddingTop = "10px";
            content.style.paddingBottom = "10px";
        }
    }

    document.addEventListener('mouseup', function(event) {
        const selection = window.getSelection();
        const toolbar = document.getElementById('toolbar');

        const editableDiv = document.querySelector('.editable');
        if (selection.toString().length > 0 && editableDiv.contains(selection.anchorNode)) {
            const range = selection.getRangeAt(0);
            const rect = range.getBoundingClientRect();

            toolbar.style.display = 'flex';
            toolbar.style.top = `${rect.top + window.scrollY - toolbar.offsetHeight - 5}px`;
            toolbar.style.left = `${rect.left + window.scrollX}px`;
        } else {
            toolbar.style.display = 'none';
        }
    });

    document.addEventListener('mousedown', function(event) {
        const toolbar = document.getElementById('toolbar');
        if (!event.target.closest('#toolbar') && !event.target.closest('.editable')) {
            toolbar.style.display = 'none';
        }
    });

    function changeFontSize(size) {
        document.execCommand("fontSize", false, "7");
        Array.from(document.querySelectorAll("font[size='7']")).forEach(el => {
            el.removeAttribute("size");
            el.style.fontSize = size;
        });
    }

    function insertLink() {
        const url = navigator.clipboard.readText().then(text => {
            if (text.startsWith('http')) {
                document.execCommand("createLink", false, text);
            } else {
                const link = prompt("Введите URL:", "http://");
                if (link) document.execCommand("createLink", false, link);
            }
        });
    }


    function formatAsCode() {
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
            const range = selection.getRangeAt(0);
            const selectedContent = range.extractContents();

            const preNode = document.createElement("pre");
            const codeNode = document.createElement("code");
            codeNode.appendChild(selectedContent);
            preNode.appendChild(codeNode);

            range.insertNode(preNode);

            selection.removeAllRanges();
        }
    }
