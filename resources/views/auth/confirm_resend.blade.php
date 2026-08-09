@php
$siteName = \App\Models\Setting::getSiteName();
@endphp

@extends('layouts.nexus_legacy')

@section('title', ($lang['resend_confirmation_email_failed'] ?? 'Send confirmation e-mail failed') . ' :: ' . $siteName)

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

        <form method="get" action="/confirm_resend" class="mb-4 text-right">
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

        {!! sprintf($lang['text_resend_confirmation_mail_note'] ?? '<h1>Send confirmation mail again</h1>', $maxAttempts) !!}

        <p>{{ $lang['text_you_have'] ?? 'You have' }} <b>{{ $remaining }}</b> {{ $lang['text_remaining_tries'] ?? ' remaining tries.' }}</p>

        <form method="post" action="/confirm_resend">
            @csrf
            <input type="hidden" name="secret" value="{{ $secret }}" />
            <table class="w-full text-sm">
                <tr>
                    <td class="rowhead nowrap">{{ $lang['row_registered_email'] ?? 'Registered email:' }}</td>
                    <td class="rowfollow"><input type="email" name="email" autocomplete="email" value="{{ old('email') }}" class="w-full max-w-xs border border-nexus-border px-2 py-1" /></td>
                </tr>
                <tr>
                    <td class="rowhead nowrap">{{ $lang['row_new_password'] ?? 'New password:' }}</td>
                    <td class="rowfollow">
                        <input type="password" name="wantpassword" autocomplete="new-password" class="w-full max-w-xs border border-nexus-border px-2 py-1" /><br />
                        <span class="text-nexus-muted text-xs">{{ $lang['text_password_note'] ?? 'Minimum is 6 characters' }}</span>
                    </td>
                </tr>
                <tr>
                    <td class="rowhead nowrap">{{ $lang['row_enter_password_again'] ?? 'Enter password again:' }}</td>
                    <td class="rowfollow"><input type="password" name="passagain" autocomplete="new-password" class="w-full max-w-xs border border-nexus-border px-2 py-1" /></td>
                </tr>

                @if ($captchaEnabled && $captchaMarkup !== '')
                    {!! $captchaMarkup !!}
                @endif

                <tr>
                    <td class="toolbox text-center" colspan="2">
                        <x-nexus.button type="submit" variant="primary">{{ $lang['submit_send_it'] ?? 'Send It!' }}</x-nexus.button>
                    </td>
                </tr>
            </table>
        </form>
    </x-nexus.card>
@endsection
