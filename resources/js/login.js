/**
 * Judging System for Competition - login page interactions.
 * Keeps the existing Laravel authentication flow untouched.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.jc-form');
    if (!form) return;

    /* ---------- password visibility toggle ---------- */
    const passwordInput = document.getElementById('password');
    const toggle = document.getElementById('togglePassword');

    if (passwordInput && toggle) {
        const setState = (visible) => {
            passwordInput.type = visible ? 'text' : 'password';
            toggle.classList.toggle('is-visible', visible);
            toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
            toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            toggle.setAttribute('title', visible ? 'Hide password' : 'Show password');
        };

        toggle.addEventListener('click', () => {
            setState(passwordInput.type === 'password');
        });

        // Keep the caret position when switching visibility.
        passwordInput.addEventListener('keyup', () => {
            if (passwordInput.type === 'text') {
                const position = passwordInput.selectionStart;
                passwordInput.setSelectionRange(position, position);
            }
        });
    }

    /* ---------- demo access: copy buttons and judge picker (no dependencies) ---------- */
    const copyText = async (text) => {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (error) {
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            let ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(area);
            return ok;
        }
    };

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const source = document.getElementById(button.dataset.copyTarget);
            if (! source) return;
            const ok = await copyText(source.textContent.trim());
            const original = button.dataset.label || button.textContent;
            button.dataset.label = original;
            button.textContent = ok ? 'Copied' : 'Press Ctrl+C';
            button.classList.toggle('is-copied', ok);
            window.setTimeout(() => {
                button.textContent = original;
                button.classList.remove('is-copied');
            }, 1500);
        });
    });

    const judgeSelect = document.getElementById('demoJudgeSelect');
    const judgeUser = document.getElementById('demoJudgeUser');
    if (judgeSelect && judgeUser) {
        judgeSelect.addEventListener('change', () => {
            judgeUser.textContent = judgeSelect.value;
        });
    }

    /* ---------- loading state on submit ---------- */
    const submitButton = document.getElementById('signInButton');

    if (submitButton) {
        form.addEventListener('submit', () => {
            submitButton.classList.add('is-loading');
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.disabled = true;
        });
    }

    /* ---------- focus the first invalid field ---------- */
    const invalid = form.querySelector('.jc-input__field--error');
    if (invalid) {
        invalid.focus({ preventScroll: false });
    } else {
        // Focus the username field on desktop without scrolling the page.
        const username = document.getElementById('username');
        if (username && window.matchMedia('(min-width: 769px)').matches) {
            username.focus({ preventScroll: true });
        }
    }
});
