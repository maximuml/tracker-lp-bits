// Delegated bindings for the standalone auth pages (login, signup,
// recover, confirm_resend). These pages do not load common.js, and
// inline on*= handlers are blocked by CSP.
document.addEventListener('change', function (e) {
    var el = e.target;
    if (el && el.name === 'sitelanguage' && el.form) {
        el.form.submit();
    }
});
