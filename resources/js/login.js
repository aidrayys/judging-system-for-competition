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
