@php
$siteName = \App\Models\Setting::getSiteName();
@endphp

@extends('layouts.nexus_legacy')

@section('title', ($lang['text_recover_user'] ?? 'Recover lost user name or password') . ' :: ' . $siteName)

@section('content')
    <x-nexus.card>
        @if ($error)
            <x-nexus.alert variant="danger" :title="$lang['std_message'] ?? 'Error'">{{ $error }}</x-nexus.alert>
        @endif

        @if ($errors->any())
            <x-nexus.alert variant="danger" :title="$lang['std_message'] ?? 'Error'">
                @foreach ($errors->all() as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </x-nexus.alert>
        @endif

        @if ($status === 'requested')
            <x-nexus.alert variant="success" :title="$lang['std_message'] ?? 'Success'">If an account with that email exists, a reset link has been sent.</x-nexus.alert>
        @endif

        <form method="get" action="/recover" class="mb-4 text-right">
            <input type="hidden" name="secret" value="{{ $secret }}" />
            {{ $lang['text_select_lang'] ?? 'Select Site Language:' }}
            <select name="sitelanguage" onchange="this.form.submit()" class="border border-nexus-border bg-nexus-surface text-nexus-text">
                @foreach ($languages as $row)
                    <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                        {{ $row['lang_name'] }}
                    </option>
                @endforeach
            </select>
        </form>

        <h1>{{ $lang['text_recover_user'] ?? 'Recover lost user name or password' }}</h1>
        <p>{{ $lang['text_use_form_below'] ?? 'Use the form below to have your password reset and your account details mailed back to you.' }}</p>
        <p>{{ $lang['text_reply_to_confirmation_email'] ?? '(You will have to reply to a confirmation email.)' }}</p>
        <p><b>{{ $lang['text_note'] ?? 'Note:' }}</b> {{ $maxAttempts }} {{ $lang['text_ban_ip'] ?? ' failed attempts in a row will result in banning your ip!' }}</p>
        <p>{{ $lang['text_you_have'] ?? 'You have' }} <b>{{ $remaining }}</b> {{ $lang['text_remaining_tries'] ?? ' remaining tries.' }}</p>

        <form method="post" action="/recover">
            @csrf
            <input type="hidden" name="secret" value="{{ $secret }}" />
            <table class="w-full text-sm">
                <tr>
                    <td class="rowhead">{{ $lang['row_registered_email'] ?? 'Registered email:' }}</td>
                    <td class="rowfollow"><input type="email" name="email" autocomplete="email" value="{{ old('email') }}" class="w-full max-w-xs border border-nexus-border px-2 py-1" /></td>
                </tr>

                @if ($captchaEnabled && $captchaMarkup !== '')
                    {!! $captchaMarkup !!}
                @endif

                <tr>
                    <td class="toolbox text-center" colspan="2">
                        <x-nexus.button type="submit" variant="primary">{{ $lang['submit_recover_it'] ?? 'Recover It!' }}</x-nexus.button>
                    </td>
                </tr>
            </table>
        </form>
    </x-nexus.card>
@endsection
