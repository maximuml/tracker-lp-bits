@php
$siteName = \App\Models\Setting::getSiteName();
@endphp

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
            <select name="sitelanguage" onchange="this.form.submit()">
                @foreach ($languages as $row)
                    <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                        {{ $row['lang_name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    {!! sprintf($lang['text_resend_confirmation_mail_note'] ?? '<h1>Send confirmation mail again</h1>', $maxAttempts) !!}

    <p>{{ $lang['text_you_have'] ?? 'You have' }} <b>{{ $remaining }}</b> {{ $lang['text_remaining_tries'] ?? ' remaining tries.' }}</p>

    <form method="post" action="/confirm_resend">
        @csrf
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <table border="1" cellspacing="0" cellpadding="10" style="width: 100%;">
            <tr>
                <td class="rowhead nowrap">{{ $lang['row_registered_email'] ?? 'Registered email:' }}</td>
                <td class="rowfollow"><input type="email" name="email" autocomplete="email" value="{{ old('email') }}" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" /></td>
            </tr>
            <tr>
                <td class="rowhead nowrap">{{ $lang['row_new_password'] ?? 'New password:' }}</td>
                <td align="left">
                    <input type="password" name="wantpassword" autocomplete="new-password" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" /><br />
                    <font class="small">{{ $lang['text_password_note'] ?? 'Minimum is 6 characters' }}</font>
                </td>
            </tr>
            <tr>
                <td class="rowhead nowrap">{{ $lang['row_enter_password_again'] ?? 'Enter password again:' }}</td>
                <td align="left"><input type="password" name="passagain" autocomplete="new-password" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" /></td>
            </tr>

            @if ($captchaEnabled && $captchaMarkup !== '')
                {!! $captchaMarkup !!}
            @endif

            <tr>
                <td class="toolbox" colspan="2" align="center">
                    <input type="submit" class="btn" value="{{ $lang['submit_send_it'] ?? 'Send It!' }}" />
                </td>
            </tr>
        </table>
    </form>
@endsection
