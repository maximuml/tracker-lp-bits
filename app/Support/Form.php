<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Legacy form-emitter helpers extracted from `include/functions.php`.
 *
 * Backs `datetimepicker_input()` and similar HTML/JS input builders.
 */
final class Form
{
    /**
     * Render a jQuery datetimepicker input and queue its assets.
     *
     * Mirrors `datetimepicker_input()`.
     */
    /**
     * @param  array<string, mixed>  $options
     */
    public static function datetimepickerInput(string $name, ?string $value = '', string $label = '', array $options = []): string
    {
        $value = (string) $value;
        $lang = Locale::folderFromCookie(Input::cookieValue('c_lang_folder'), true);
        if ($lang === 'zh_CN') {
            $lang = 'zh';
        }
        $lang = str_replace('_', '-', $lang);

        $js = '';

        $id = "datetime-picker-$name";
        $input = sprintf(
            '%s<input type="datetime-local" id="%s" name="%s" value="%s" autocomplete="off" style="%s">',
            $label,
            $id,
            $name,
            $value,
            $options['style'] ?? ''
        );

        return $input;
    }

    /**
     * Render the client-side password hashing JS for a login/signup form.
     *
     * Mirrors `render_password_hash_js()`.
     */
    public static function passwordHashJs(
        string $formId,
        string $passwordOriginalClass,
        string $passwordHashedName,
        bool $passwordRequired,
        string $passwordConfirmClass = 'password_confirmation',
        string $usernameName = 'username',
    ): void {
        $tipTooShort = Locale::trans('signup.password_too_short');
        $tipTooLong = Locale::trans('signup.password_too_long');
        $tipEqualUsername = Locale::trans('signup.password_equals_username');
        $tipNotMatch = Locale::trans('signup.passwords_unmatched');
        $passwordValidateJS = '';

        if ($passwordRequired) {
            $passwordValidateJS = <<<JS
if (password.length < 6) {
    layer.alert("$tipTooShort")
    return
}
if (password.length > 40) {
    layer.alert("$tipTooLong")
    return
}
JS;
        }

        $formVar = 'jqForm'.md5($formId);
        $js = <<<JS
var $formVar = document.getElementById("{$formId}");
$formVar.addEventListener("click", function(e) {
    if (!e.target || e.target.type !== 'button') return;
    var usernameEl = $formVar.querySelector("[name={$usernameName}]")
    var passwordEl = $formVar.querySelector(".{$passwordOriginalClass}")
    var passwordConfirmEl = $formVar.querySelector(".{$passwordConfirmClass}")
    var password = passwordEl.value
    $passwordValidateJS
    if (usernameEl && usernameEl.value === password) {
        layer.alert("$tipEqualUsername")
        return
    }
    if (passwordConfirmEl && password !== passwordConfirmEl.value) {
        layer.alert("$tipNotMatch")
        return
    }
    if (password !== "") {
        // Send plaintext password over HTTPS so the server can use argon2id.
        // Client-side SHA256 hashing prevented argon2id upgrades.
        $formVar.querySelector("input[name={$passwordHashedName}]").value = password
        var passAgainHidden = $formVar.querySelector("input[name={$passwordConfirmClass}]")
        if (passAgainHidden) {
            passAgainHidden.value = passwordConfirmEl ? passwordConfirmEl.value : password
        }
        $formVar.submit()
    } else {
        $formVar.submit()
    }
})
JS;
        AssetAppender::js('js/crypto-js.js', 'footer', true);
        AssetAppender::js($js, 'footer', false);
    }

    /**
     * Render the challenge-response login JS for a form.
     *
     * Mirrors `render_password_challenge_js()`.
     */
    public static function passwordChallengeJs(string $formId, string $usernameName, string $passwordOriginalClass): void
    {
        $formVar = 'jqForm'.md5($formId);
        $js = <<<JS
var $formVar = document.getElementById("{$formId}");
$formVar.addEventListener("click", function(e) {
    if (!e.target || e.target.type !== 'button') return;
    var useChallengeResponseAuthentication = $formVar.querySelector("input[name=response]") !== null
    if (!useChallengeResponseAuthentication) {
        $formVar.submit()
        return
    }
    var usernameEl = $formVar.querySelector("[name={$usernameName}]")
    var passwordEl = $formVar.querySelector(".{$passwordOriginalClass}")
    var username = usernameEl.value
    var password = passwordEl.value
    login(username, password, $formVar)
})
async function login(username, password, theForm) {
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
            layer.alert(challengeData.msg)
            return
        }

        // For argon2id users, send plaintext password (over HTTPS) since
        // the server-side hash cannot be computed client-side.
        if (challengeData.data.passhash_algo === 'argon2id') {
            theForm.querySelector("input[name=response]").value = ''
            var oldPwd = theForm.querySelector("input[name=oldpassword]")
            if (oldPwd) oldPwd.remove()
            var escDiv = document.createElement('div')
            escDiv.textContent = password
            var hiddenInput = document.createElement('input')
            hiddenInput.type = 'hidden'
            hiddenInput.name = 'oldpassword'
            hiddenInput.value = escDiv.innerHTML
            theForm.appendChild(hiddenInput)
            theForm.submit()
            return
        }

        const clientHashedPassword = CryptoJS.SHA256(password).toString();

        const serverSideHash = CryptoJS.SHA256(challengeData.data.secret + clientHashedPassword).toString();

        const clientResponse = CryptoJS.HmacSHA256(serverSideHash, challengeData.data.challenge).toString();
        theForm.querySelector("input[name=response]").value = clientResponse
        theForm.submit()
    } catch (error) {
        console.error(error);
        layer.alert(error.toString())
    }
}
JS;
        AssetAppender::js('js/crypto-js.js', 'footer', true);
        AssetAppender::js($js, 'footer', false);
    }
}
