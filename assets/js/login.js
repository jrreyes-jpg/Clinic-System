const form = document.querySelector('.login-form');
const usernameInput = document.querySelector('#username');
const passwordInput = document.querySelector('#password');
const togglePassword = document.querySelector('.toggle-password');

window.addEventListener('load', () => {
    window.setTimeout(() => {
        document.body.classList.add('intro-complete');
        document.body.classList.remove('login-intro-active');
    }, 1600);
});

function setFieldError(fieldName, message) {
    const error = document.querySelector(`[data-error-for="${fieldName}"]`);

    if (error) {
        error.textContent = message;
    }
}

if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', () => {
        const isPassword = passwordInput.type === 'password';

        passwordInput.type = isPassword ? 'text' : 'password';
        togglePassword.classList.toggle('is-visible', isPassword);
        togglePassword.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });
}

if (form) {
    form.addEventListener('submit', (event) => {
        let hasError = false;

        setFieldError('username', '');
        setFieldError('password', '');

        if (!usernameInput.value.trim()) {
            setFieldError('username', 'Username is required.');
            hasError = true;
        }

        if (!passwordInput.value) {
            setFieldError('password', 'Password is required.');
            hasError = true;
        }

        if (hasError) {
            event.preventDefault();
            return;
        }

        const submitButton = form.querySelector('.login-submit');

        if (submitButton) {
            submitButton.classList.add('is-loading');
            submitButton.disabled = true;
        }
    });
}
