// Delegated bindings for the standalone auth pages (login, signup,
// recover, confirm_resend). These pages do not load common.js, and
// inline on*= handlers are blocked by CSP.
document.addEventListener('change', function (e) {
    var el = e.target;
    if (el && el.name === 'sitelanguage' && el.form) {
        el.form.submit();
    }
});

// Password visibility toggle: [data-pw-toggle] buttons flip the type of
// the input inside the same .nx-pw wrapper between password and text.
document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-pw-toggle]') : null;
    if (!btn) return;
    var wrap = btn.closest('.nx-pw');
    var input = wrap ? wrap.querySelector('input') : null;
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.setAttribute('aria-label', show ? btn.dataset.labelHide : btn.dataset.labelShow);
});
