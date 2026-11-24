document.querySelectorAll('form').forEach(form => {
    const editor = form.querySelector('[contenteditable]');
    const input = form.querySelector('textarea[name="contentHtml"]');
    if (!editor || !input) return;
    let selection;
    const remember = () => {
        const current = window.getSelection();
        if (current.rangeCount && editor.contains(current.anchorNode)) selection = current.getRangeAt(0).cloneRange();
    };
    editor.addEventListener('keyup', remember);
    editor.addEventListener('mouseup', remember);
    editor.addEventListener('input', remember);
    form.querySelectorAll('[data-editor-command], [data-editor-block]').forEach(button => {
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => {
            editor.focus();
            if (selection) {
                const current = window.getSelection();
                current.removeAllRanges(); current.addRange(selection);
            }
            const command = button.dataset.editorCommand || 'formatBlock';
            let value = button.dataset.editorBlock || null;
            if (command === 'createLink') {
                value = window.prompt('Enter a link (https://, mailto:, /path, or #anchor)');
                if (!value) return;
                if (!/^(https?:\/\/|mailto:|\/(?!\/)|#)/i.test(value.trim())) {
                    window.alert('Enter a valid link.'); return;
                }
            }
            document.execCommand(command, false, value);
            remember();
        });
    });
    form.addEventListener('submit', () => { input.value = editor.innerHTML.trim(); });
});
