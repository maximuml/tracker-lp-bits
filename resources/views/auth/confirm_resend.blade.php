@extends('layouts.auth')

@section('title', ($lang['resend_confirmation_email_failed'] ?? 'Send confirmation e-mail failed') . ' :: ' . $siteName)

@section('content')
    @if ($error)
        <div class="error">{{ $error }}</div>
    @endif

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form method="get" action="/confirm_resend">
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <div align="right">
            {{ $lang['text_select_lang'] ?? 'Select Site Language:' }}
            <select name="sitelanguage">
                @foreach ($languages as $row)
                    <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                        {{ $row['lang_name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml(sprintf($lang['text_resend_confirmation_mail_note'] ?? '<h1>Send confirmation mail again</h1>', $maxAttempts)))

    <p>{{ $lang['text_you_have'] ?? 'You have' }} <b>{{ $remaining }}</b> {{ $lang['text_remaining_tries'] ?? ' remaining tries.' }}</p>

    <form method="post" action="/confirm_resend">
        @csrf
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <div class="nx-fgrid nx-fgrid--b">
            <div class="nx-fhead">{{ $lang['row_registered_email'] ?? 'Registered email:' }}</div>
            <div class="nx-fcell"><input type="email" name="email" autocomplete="email" value="{{ old('email') }}" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" /></div>
            <div class="nx-fhead">{{ $lang['row_new_password'] ?? 'New password:' }}</div>
            <div class="nx-fcell">
                <input type="password" name="wantpassword" autocomplete="new-password" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" /><br />
                <font class="small">{{ $lang['text_password_note'] ?? 'Minimum is 6 characters' }}</font>
            </div>
            <div class="nx-fhead">{{ $lang['row_enter_password_again'] ?? 'Enter password again:' }}</div>
            <div class="nx-fcell"><input type="password" name="passagain" autocomplete="new-password" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" /></div>

            @if ($captchaEnabled && $captchaMarkup !== '')
                @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($captchaMarkup))
            @endif

            <div class="toolbox nx-ffull">
                <input type="submit" class="btn" value="{{ $lang['submit_send_it'] ?? 'Send It!' }}" />
            </div>
        </div>
    </form>
@endsection
