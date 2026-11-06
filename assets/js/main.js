// JS chung cho toàn site

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-password-toggle]');
    if (!toggle) return;

    const input = document.getElementById(toggle.dataset.passwordToggle);
    if (!input) return;

    const shouldShow = input.type === 'password';
    input.type = shouldShow ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(shouldShow));
    toggle.setAttribute('aria-label', shouldShow ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
    toggle.innerHTML = `<i class="bi ${shouldShow ? 'bi-eye-slash' : 'bi-eye'}"></i>`;
});

const confirmPassword = document.getElementById('confirm_password');
const password = document.getElementById('password');

if (confirmPassword && password) {
    const updatePasswordMatch = () => {
        const mismatch = confirmPassword.value !== '' && confirmPassword.value !== password.value;
        confirmPassword.setCustomValidity(mismatch ? 'Mật khẩu nhập lại không khớp.' : '');
        confirmPassword.classList.toggle('is-invalid', mismatch);
        const feedback = confirmPassword.closest('.mb-4')?.querySelector('.auth-password-feedback');
        if (feedback) feedback.classList.toggle('d-block', mismatch);
    };

    password.addEventListener('input', updatePasswordMatch);
    confirmPassword.addEventListener('input', updatePasswordMatch);
}

document.querySelectorAll('[data-online-text-editor]').forEach((editor) => {
    const form = editor.closest('form');
    const fallback = form?.querySelector('[data-online-text-fallback]');
    if (!form || !fallback) return;

    fallback.classList.add('d-none');
    editor.classList.remove('d-none');

    editor.addEventListener('click', (event) => {
        const button = event.target.closest('[data-editor-command]');
        if (!button) return;

        editor.querySelector('[contenteditable="true"]')?.focus();
        const command = button.dataset.editorCommand;
        if (command === 'blockquote') {
            document.execCommand('formatBlock', false, 'blockquote');
        } else if (command === 'createLink') {
            const href = window.prompt('Nhập địa chỉ liên kết (https://...)');
            if (href) document.execCommand('createLink', false, href);
        } else {
            document.execCommand(command, false, null);
        }
    });

    editor.addEventListener('mousedown', (event) => {
        if (event.target.closest('[data-editor-command]')) event.preventDefault();
    });

    form.addEventListener('submit', () => {
        const content = editor.querySelector('[contenteditable="true"]');
        if (content) fallback.value = content.innerHTML;
    });
});

const submissionMode = document.getElementById('submission_mode');
const submissionFilePolicy = document.querySelector('[data-file-policy]');

if (submissionMode && submissionFilePolicy) {
    const updateFilePolicy = () => {
        submissionFilePolicy.classList.toggle('d-none', submissionMode.value === 'online_text');
    };
    submissionMode.addEventListener('change', updateFilePolicy);
    updateFilePolicy();
}
