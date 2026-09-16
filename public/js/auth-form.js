// Auth form behaviour, previously emitted per-page by
// App\Support\Form::passwordHashJs() / passwordChallengeJs() as inline
// nonce'd scripts. Configuration now lives on the <form> element's
// data-* attributes so this file can be a static 'self'-allowed asset.
//
// Modes (data-auth-form):
//   hash      — signup/security forms: validate + copy password into the
//               hidden hash-name field, then submit.
//   challenge — usercp security confirm: challenge-response via
//               /api/challenge (CryptoJS), argon2id users fall back to
//               plaintext submit over HTTPS.
//
// Lookups go through theForm.elements rather than querySelector: legacy
// rowsHtml emits stray table markup that makes the HTML parser foster-
// parent inputs outside the <form> element while keeping them
// form-associated. form.elements sees them; descendant selectors don't.
// The same applies to the click binding — the Save button is associated
// with the form but not inside it, so handlers attach per-button.
//
// Validation failures use alert() — the previous layer.alert dependency
// is gone, which lets the auth layout stop loading layer.js.
(function () {
    var forms = document.querySelectorAll('form[data-auth-form]');
    Array.prototype.forEach.call(forms, function (theForm) {
        Array.prototype.forEach.call(theForm.elements, function (el) {
            if (!el || el.type !== 'button') return;
            el.addEventListener('click', function () { handle(theForm); });
        });
    });

    function byName(theForm, name) {
        var el = theForm.elements.namedItem(name);
        return el instanceof Element ? el : null;
    }

    function byClass(theForm, cls) {
        var found = null;
        Array.prototype.forEach.call(theForm.elements, function (el) {
            if (!found && el.classList && el.classList.contains(cls)) found = el;
        });
        return found;
    }

    function handle(theForm) {
        var d = theForm.dataset;

        if (d.authForm === 'challenge') {
            if (byName(theForm, 'response') === null) {
                theForm.submit();
                return;
            }
            var cu = byName(theForm, d.usernameName);
            var cp = byClass(theForm, d.passwordClass);
            challengeLogin(cu ? cu.value : '', cp ? cp.value : '', theForm);
            return;
        }

        var usernameEl = byName(theForm, d.usernameName);
        var passwordEl = byClass(theForm, d.passwordClass);
        var passwordConfirmEl = byClass(theForm, d.passwordConfirmClass);
        var password = passwordEl ? passwordEl.value : '';

        if (d.passwordRequired === '1') {
            if (password.length < 6) { alert(d.tipShort); return; }
            if (password.length > 40) { alert(d.tipLong); return; }
        }
        if (usernameEl && usernameEl.value === password) {
            alert(d.tipEqualUsername);
            return;
        }
        if (passwordConfirmEl && password !== passwordConfirmEl.value) {
            alert(d.tipUnmatched);
            return;
        }
        if (password !== '') {
            // Send plaintext password over HTTPS so the server can use
            // argon2id. Client-side SHA256 hashing prevented argon2id
            // upgrades.
            byName(theForm, d.passwordHashName).value = password;
            var passAgainHidden = byName(theForm, d.passwordConfirmClass);
            if (passAgainHidden) {
                passAgainHidden.value = passwordConfirmEl ? passwordConfirmEl.value : password;
            }
        }
        theForm.submit();
    }

    async function challengeLogin(username, password, theForm) {
        try {
            const challengeResponse = await fetch('/api/challenge', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username: username })
            });

            const challengeData = await challengeResponse.json();
            if (challengeData.ret !== 0) {
                alert(challengeData.msg);
                return;
            }

            // For argon2id users, send plaintext password (over HTTPS) since
            // the server-side hash cannot be computed client-side.
            if (challengeData.data.passhash_algo === 'argon2id') {
                byName(theForm, 'response').value = '';
                var oldPwd = byName(theForm, 'oldpassword');
                if (oldPwd) oldPwd.remove();
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'oldpassword';
                hiddenInput.value = password;
                theForm.appendChild(hiddenInput);
                theForm.submit();
                return;
            }

            const clientHashedPassword = CryptoJS.SHA256(password).toString();

            const serverSideHash = CryptoJS.SHA256(challengeData.data.secret + clientHashedPassword).toString();

            const clientResponse = CryptoJS.HmacSHA256(serverSideHash, challengeData.data.challenge).toString();
            byName(theForm, 'response').value = clientResponse;
            theForm.submit();
        } catch (error) {
            console.error(error);
            alert(error.toString());
        }
    }
})();
