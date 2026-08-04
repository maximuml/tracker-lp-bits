@php
$siteName = \App\Models\Setting::getSiteName();
@endphp

@extends('layouts.auth')

@section('title', ($lang['text_recover_user'] ?? 'Recover lost user name or password') . ' :: ' . $siteName)

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

    @if ($status === 'requested')
        <div class="success">If an account with that email exists, a reset link has been sent.</div>
    @endif

    <form method="get" action="/recover">
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

    <h1>{{ $lang['text_recover_user'] ?? 'Recover lost user name or password' }}</h1>
    <p>{{ $lang['text_use_form_below'] ?? 'Use the form below to have your password reset and your account details mailed back to you.' }}</p>
    <p>{{ $lang['text_reply_to_confirmation_email'] ?? '(You will have to reply to a confirmation email.)' }}</p>
    <p><b>{{ $lang['text_note'] ?? 'Note:' }}</b> {{ $maxAttempts }} {{ $lang['text_ban_ip'] ?? ' failed attempts in a row will result in banning your ip!' }}</p>
    <p>{{ $lang['text_you_have'] ?? 'You have' }} <b>{{ $remaining }}</b> {{ $lang['text_remaining_tries'] ?? ' remaining tries.' }}</p>

    <form method="post" action="/recover">
        @csrf
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <table border="1" cellspacing="0" cellpadding="10" style="width: 100%;">
            <tr>
                <td class="rowhead">{{ $lang['row_registered_email'] ?? 'Registered email:' }}</td>
                <td class="rowfollow"><input type="email" name="email" autocomplete="email" value="{{ old('email') }}" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" /></td>
            </tr>

            @if ($captchaEnabled && $captchaMarkup !== '')
                {!! $captchaMarkup !!}
            @endif

            <tr>
                <td class="toolbox" colspan="2" align="center">
                    <input type="submit" value="{{ $lang['submit_recover_it'] ?? 'Recover It!' }}" class="btn" />
                </td>
            </tr>
        </table>
    </form>
@endsection
