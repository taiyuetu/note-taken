document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.querySelector('[data-theme-toggle]');
    const storedTheme = localStorage.getItem('notes-theme');

    if (storedTheme) {
        document.body.dataset.theme = storedTheme;
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const next = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
            document.body.dataset.theme = next;
            localStorage.setItem('notes-theme', next);
        });
    }

    const enhanceCodeBlocks = () => {
        document.querySelectorAll('.note-content pre').forEach((pre) => {
            const code = pre.querySelector('code') || pre;
            const copyText = code.innerText || pre.innerText || '';

            if (!copyText.trim()) {
                return;
            }

            if (!pre.dataset.highlighted) {
                if (code !== pre) {
                    hljs.highlightElement(code);
                } else if (pre.classList.contains('ql-syntax')) {
                    hljs.highlightElement(pre);
                }
                pre.dataset.highlighted = 'true';
            }

            if (pre.parentElement?.classList.contains('code-block-wrap')) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'code-block-wrap';

            const toolbar = document.createElement('div');
            toolbar.className = 'code-block-toolbar';

            const label = document.createElement('span');
            label.className = 'code-block-label';
            const rawClass = code.className || pre.className || '';
            const language = rawClass.replace('language-', '').replace('ql-syntax', '').trim();
            label.textContent = language || 'code';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'code-copy-btn';
            button.textContent = 'Copy';
            button.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(copyText);
                    button.textContent = 'Copied';
                    window.setTimeout(() => {
                        button.textContent = 'Copy';
                    }, 1400);
                } catch (error) {
                    button.textContent = 'Failed';
                    window.setTimeout(() => {
                        button.textContent = 'Copy';
                    }, 1400);
                }
            });

            toolbar.append(label, button);
            pre.parentNode.insertBefore(wrapper, pre);
            wrapper.append(toolbar, pre);
        });
    };

    enhanceCodeBlocks();

    const editorElement = document.querySelector('[data-note-editor]');

    if (editorElement) {
        const textarea = document.querySelector('[data-note-content]');
        const autosaveUrl = editorElement.dataset.autosaveUrl || '';
        const noteId = editorElement.dataset.noteId || '';
        const csrf = editorElement.dataset.csrf || '';
        const quill = new Quill(editorElement, {
            theme: 'snow',
            modules: {
                syntax: true,
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'blockquote'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'code-block'],
                    ['clean']
                ]
            }
        });

        quill.root.innerHTML = textarea.value;
        let autosaveTimer;

        const syncValue = () => {
            textarea.value = quill.root.innerHTML;
        };

        quill.on('text-change', () => {
            syncValue();

            if (!autosaveUrl || !noteId) {
                return;
            }

            clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(() => {
                const title = document.querySelector('[name="title"]')?.value || 'Untitled note';
                const categoryId = document.querySelector('[name="category_id"]')?.value || '';
                const isPublic = document.querySelector('[name="is_public"]')?.checked ? '1' : '0';
                const shareSlug = document.querySelector('[name="share_slug"]')?.value || '';

                fetch(autosaveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        _csrf: csrf,
                        action: 'autosave',
                        note_id: noteId,
                        title: title,
                        category_id: categoryId,
                        content: textarea.value,
                        is_public: isPublic,
                        share_slug: shareSlug
                    })
                }).then((response) => response.json())
                    .then((data) => {
                        const target = document.querySelector('[data-autosave-status]');
                        if (target && data.status === 'ok') {
                            target.textContent = 'Autosaved ' + data.saved_at;
                        }
                    })
                    .catch(() => {});
            }, 800);
        });

        editorElement.closest('form')?.addEventListener('submit', syncValue);
    }
});
